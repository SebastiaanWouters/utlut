import Database from 'better-sqlite3';
import { join } from 'path';
import { existsSync, mkdirSync } from 'fs';

// Ensure data directory exists
const dataDir = join(process.cwd(), 'data');
if (!existsSync(dataDir)) {
	mkdirSync(dataDir, { recursive: true });
}

const db = new Database(join(dataDir, 'jobs.db'));

// Enable WAL mode for better concurrent access
db.pragma('journal_mode = WAL');

// Create tables
db.exec(`
	CREATE TABLE IF NOT EXISTS audio_jobs (
		id TEXT PRIMARY KEY,
		article_id TEXT NOT NULL UNIQUE,
		status TEXT NOT NULL DEFAULT 'pending',
		title TEXT NOT NULL,
		content TEXT NOT NULL,
		language TEXT NOT NULL,
		voice TEXT NOT NULL DEFAULT 'alloy',
		progress INTEGER DEFAULT 0,
		total_chunks INTEGER DEFAULT 0,
		completed_chunks INTEGER DEFAULT 0,
		attempts INTEGER DEFAULT 0,
		max_attempts INTEGER DEFAULT 3,
		last_error TEXT,
		audio_path TEXT,
		audio_duration REAL,
		created_at INTEGER NOT NULL,
		updated_at INTEGER NOT NULL,
		started_at INTEGER,
		completed_at INTEGER,
		expires_at INTEGER
	);

	CREATE INDEX IF NOT EXISTS idx_jobs_status ON audio_jobs(status);
	CREATE INDEX IF NOT EXISTS idx_jobs_article ON audio_jobs(article_id);
	CREATE INDEX IF NOT EXISTS idx_jobs_expires ON audio_jobs(expires_at);
	CREATE INDEX IF NOT EXISTS idx_jobs_created ON audio_jobs(created_at);
`);

export type JobStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface AudioJob {
	id: string;
	article_id: string;
	status: JobStatus;
	title: string;
	content: string;
	language: string;
	voice: string;
	progress: number;
	total_chunks: number;
	completed_chunks: number;
	attempts: number;
	max_attempts: number;
	last_error: string | null;
	audio_path: string | null;
	audio_duration: number | null;
	created_at: number;
	updated_at: number;
	started_at: number | null;
	completed_at: number | null;
	expires_at: number | null;
}

// Prepared statements for performance
const insertJobStmt = db.prepare(`
	INSERT INTO audio_jobs (id, article_id, status, title, content, language, voice, created_at, updated_at)
	VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?)
`);

const getJobStmt = db.prepare(`SELECT * FROM audio_jobs WHERE id = ?`);
const getJobByArticleStmt = db.prepare(`SELECT * FROM audio_jobs WHERE article_id = ?`);

const getNextPendingStmt = db.prepare(`
	SELECT * FROM audio_jobs
	WHERE status = 'pending'
	ORDER BY created_at ASC
	LIMIT 1
`);

const updateStatusStmt = db.prepare(`
	UPDATE audio_jobs
	SET status = ?, updated_at = ?
	WHERE id = ?
`);

const updateProgressStmt = db.prepare(`
	UPDATE audio_jobs
	SET completed_chunks = ?, total_chunks = ?, progress = ?, updated_at = ?
	WHERE id = ?
`);

const startJobStmt = db.prepare(`
	UPDATE audio_jobs
	SET status = 'processing', started_at = ?, attempts = attempts + 1, updated_at = ?
	WHERE id = ?
`);

const completeJobStmt = db.prepare(`
	UPDATE audio_jobs
	SET status = 'completed', audio_path = ?, audio_duration = ?, completed_at = ?, expires_at = ?, progress = 100, updated_at = ?
	WHERE id = ?
`);

const failJobStmt = db.prepare(`
	UPDATE audio_jobs
	SET status = ?, last_error = ?, updated_at = ?
	WHERE id = ?
`);

const deleteJobStmt = db.prepare(`DELETE FROM audio_jobs WHERE id = ?`);
const deleteExpiredStmt = db.prepare(`DELETE FROM audio_jobs WHERE expires_at IS NOT NULL AND expires_at < ?`);

const getExpiredJobsStmt = db.prepare(`
	SELECT * FROM audio_jobs
	WHERE expires_at IS NOT NULL AND expires_at < ?
`);

export function createJob(
	articleId: string,
	title: string,
	content: string,
	language: string,
	voice: string = 'alloy'
): AudioJob {
	const id = crypto.randomUUID();
	const now = Date.now();

	insertJobStmt.run(id, articleId, title, content, language, voice, now, now);

	return getJobStmt.get(id) as AudioJob;
}

export function getJob(jobId: string): AudioJob | undefined {
	return getJobStmt.get(jobId) as AudioJob | undefined;
}

export function getJobByArticleId(articleId: string): AudioJob | undefined {
	return getJobByArticleStmt.get(articleId) as AudioJob | undefined;
}

export function getNextPendingJob(): AudioJob | undefined {
	return getNextPendingStmt.get() as AudioJob | undefined;
}

export function startJob(jobId: string): void {
	const now = Date.now();
	startJobStmt.run(now, now, jobId);
}

export function updateJobProgress(
	jobId: string,
	completedChunks: number,
	totalChunks: number
): void {
	const progress = totalChunks > 0 ? Math.round((completedChunks / totalChunks) * 100) : 0;
	const now = Date.now();
	updateProgressStmt.run(completedChunks, totalChunks, progress, now, jobId);
}

export function completeJob(
	jobId: string,
	audioPath: string,
	audioDuration: number
): void {
	const now = Date.now();
	const expiresAt = now + 24 * 60 * 60 * 1000; // 24 hours from now
	completeJobStmt.run(audioPath, audioDuration, now, expiresAt, now, jobId);
}

export function failJob(jobId: string, error: string): void {
	const job = getJob(jobId);
	if (!job) return;

	const now = Date.now();
	// If we haven't exceeded max attempts, set back to pending for retry
	const newStatus = job.attempts < job.max_attempts ? 'pending' : 'failed';
	failJobStmt.run(newStatus, error, now, jobId);
}

export function deleteJob(jobId: string): void {
	deleteJobStmt.run(jobId);
}

export function getExpiredJobs(): AudioJob[] {
	const now = Date.now();
	return getExpiredJobsStmt.all(now) as AudioJob[];
}

export function cleanupExpiredJobs(): number {
	const now = Date.now();
	const result = deleteExpiredStmt.run(now);
	return result.changes;
}

export function getPendingJobCount(): number {
	const result = db.prepare(`SELECT COUNT(*) as count FROM audio_jobs WHERE status = 'pending'`).get() as { count: number };
	return result.count;
}

export function getProcessingJobCount(): number {
	const result = db.prepare(`SELECT COUNT(*) as count FROM audio_jobs WHERE status = 'processing'`).get() as { count: number };
	return result.count;
}

// Reset any jobs that were processing when server restarted (stale jobs)
export function resetStaleProcessingJobs(): number {
	const fiveMinutesAgo = Date.now() - 5 * 60 * 1000;
	const result = db.prepare(`
		UPDATE audio_jobs
		SET status = 'pending', updated_at = ?
		WHERE status = 'processing' AND started_at < ?
	`).run(Date.now(), fiveMinutesAgo);
	return result.changes;
}

// Initialize: reset stale jobs on startup
resetStaleProcessingJobs();
