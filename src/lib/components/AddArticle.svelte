<script lang="ts">
	import { createArticle, getSettings } from '$lib/db/database';
	import { cleanArticleText, detectLanguage } from '$lib/utils/textCleaner';
	import { goto } from '$app/navigation';
	import { Link, FileText, Loader2, Sparkles, Lock, Wand2 } from 'lucide-svelte';
	import { jobPollingService } from '$lib/services/jobPollingService';
	import { browser } from '$app/environment';

	let mode = $state<'url' | 'text'>('url');
	let url = $state('');
	let title = $state('');
	let content = $state('');
	let loading = $state(false);
	let loadingStatus = $state('');
	let error = $state('');
	let clipboardLoading = $state(false);
	let clipboardStatus = $state('');
	let aiOptimize = $state(browser ? localStorage.getItem('aiOptimize') === 'true' : false);

	// Persist AI optimize preference
	$effect(() => {
		if (browser) {
			localStorage.setItem('aiOptimize', String(aiOptimize));
		}
	});

	interface Props {
		initialUrl?: string;
		initialTitle?: string;
		initialContent?: string;
	}

	let { initialUrl = '', initialTitle = '', initialContent = '' }: Props = $props();

	$effect(() => {
		if (initialUrl) {
			url = initialUrl;
			mode = 'url';
		}
		if (initialTitle) title = initialTitle;
		if (initialContent) {
			content = initialContent;
			mode = 'text';
		}
	});

	async function triggerAudioJob(article: { id: string; title: string; content: string; language: string }) {
		try {
			const settings = await getSettings();
			const response = await fetch('/api/jobs', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					articleId: article.id,
					title: article.title,
					content: article.content,
					language: article.language,
					voice: settings.voice
				})
			});

			if (response.ok) {
				const { jobId } = await response.json();
				jobPollingService.startPolling(article.id, jobId);
			}
		} catch (err) {
			// Don't fail article creation if job creation fails
			console.error('Failed to create audio job:', err);
		}
	}

	async function handleUrlSubmit() {
		if (!url.trim()) return;

		loading = true;
		loadingStatus = 'Fetching article...';
		error = '';

		try {
			const trimmedUrl = url.trim();

			// Validate URL format
			try {
				new URL(trimmedUrl);
			} catch {
				throw new Error('Invalid URL format');
			}

			const response = await fetch('/api/parse', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ url: trimmedUrl })
			});

			if (!response.ok) {
				let errorMessage = `Failed to add article (${response.status})`;
				try {
					const data = await response.json();
					if (data.message) {
						errorMessage = data.message;
					}
				} catch {
					// JSON parsing failed, use default message
				}
				throw new Error(errorMessage);
			}

			let data = await response.json();

			// Optional AI optimization
			if (aiOptimize) {
				loadingStatus = 'Optimizing with AI...';
				try {
					const optimizeResponse = await fetch('/api/optimize', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({
							title: data.title,
							content: data.content
						})
					});

					if (optimizeResponse.ok) {
						const optimized = await optimizeResponse.json();
						data.title = optimized.title;
						data.content = optimized.content;
						data.language = optimized.language;
					}
				} catch {
					// Silently fall back to non-optimized on error
					console.error('AI optimization failed, using original content');
				}
			}

			loadingStatus = 'Saving...';
			const article = await createArticle({
				title: data.title,
				url: url.trim(),
				content: data.content,
				language: data.language
			});

			// Trigger async audio generation
			triggerAudioJob(article);

			goto(`/?added=${article.id}`);
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to add article';
		} finally {
			loading = false;
			loadingStatus = '';
		}
	}

	async function handleTextSubmit() {
		if (!title.trim() || !content.trim()) return;

		loading = true;
		loadingStatus = 'Processing...';
		error = '';

		try {
			let finalTitle = title.trim();
			let finalContent = cleanArticleText(content);
			let detectedLanguage = detectLanguage(finalContent);

			// Optional AI optimization
			if (aiOptimize) {
				loadingStatus = 'Optimizing with AI...';
				try {
					const optimizeResponse = await fetch('/api/optimize', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({
							title: finalTitle,
							content: finalContent
						})
					});

					if (optimizeResponse.ok) {
						const optimized = await optimizeResponse.json();
						finalTitle = optimized.title;
						finalContent = optimized.content;
						detectedLanguage = optimized.language;
					}
				} catch {
					// Silently fall back to non-optimized on error
					console.error('AI optimization failed, using original content');
				}
			}

			loadingStatus = 'Saving...';
			const article = await createArticle({
				title: finalTitle,
				content: finalContent,
				language: detectedLanguage
			});

			// Trigger async audio generation
			triggerAudioJob(article);

			goto(`/?added=${article.id}`);
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to add article';
		} finally {
			loading = false;
			loadingStatus = '';
		}
	}

	async function importFromClipboard() {
		clipboardLoading = true;
		clipboardStatus = 'Reading clipboard...';
		error = '';

		try {
			const text = await navigator.clipboard.readText();
			if (!text || !text.trim()) {
				throw new Error('Clipboard is empty');
			}

			clipboardStatus = 'Extracting article with AI...';

			// Send to AI for extraction
			const response = await fetch('/api/extract', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ rawContent: text })
			});

			if (!response.ok) {
				let errorMessage = 'Failed to extract article';
				try {
					const data = await response.json();
					if (data.message) {
						errorMessage = data.message;
					}
				} catch {
					// JSON parsing failed
				}
				throw new Error(errorMessage);
			}

			const data = await response.json();

			clipboardStatus = 'Saving article...';

			const article = await createArticle({
				title: data.title,
				content: data.content,
				language: data.language
			});

			// Trigger async audio generation
			triggerAudioJob(article);

			goto(`/?added=${article.id}`);
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to import from clipboard';
			clipboardStatus = '';
		} finally {
			clipboardLoading = false;
		}
	}
</script>

<div class="flex flex-col gap-6 p-4">
	<!-- Siri Shortcut Section -->
	<div class="bg-gradient-to-br from-purple-900/40 to-indigo-900/40 rounded-2xl p-5 border border-purple-700/30">
		<div class="flex items-start gap-3 mb-4">
			<div class="bg-purple-600/30 p-2.5 rounded-xl">
				<Lock size={22} class="text-purple-300" />
			</div>
			<div class="flex-1">
				<h2 class="font-semibold text-white">Paywalled Articles</h2>
				<p class="text-sm text-purple-200/70 mt-0.5">Use iOS Shortcut to capture content</p>
			</div>
		</div>

		<button
			type="button"
			onclick={importFromClipboard}
			disabled={clipboardLoading}
			class="w-full py-3.5 px-4 bg-purple-600 hover:bg-purple-500 disabled:bg-purple-800 text-white font-medium rounded-xl transition-all flex items-center justify-center gap-2.5 shadow-lg shadow-purple-900/30"
		>
			{#if clipboardLoading}
				<Loader2 size={20} class="animate-spin" />
				{clipboardStatus}
			{:else}
				<Sparkles size={20} />
				Import from Clipboard
			{/if}
		</button>

		<p class="text-xs text-purple-300/60 mt-3 text-center">
			First run the <a href="/shortcut" class="text-purple-300 underline underline-offset-2">iOS Shortcut</a> on a Safari article
		</p>
	</div>

	<div class="flex items-center gap-3">
		<div class="flex-1 h-px bg-zinc-800"></div>
		<span class="text-zinc-500 text-sm">or add directly</span>
		<div class="flex-1 h-px bg-zinc-800"></div>
	</div>

	{#if error}
		<div class="bg-red-900/50 border border-red-700 text-red-200 px-4 py-3 rounded-lg">
			{error}
		</div>
	{/if}

	<div class="flex gap-2">
		<button
			type="button"
			onclick={() => (mode = 'url')}
			class="flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-xl transition-colors {mode === 'url'
				? 'bg-blue-600 text-white'
				: 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'}"
		>
			<Link size={20} />
			URL
		</button>
		<button
			type="button"
			onclick={() => (mode = 'text')}
			class="flex-1 flex items-center justify-center gap-2 py-3 px-4 rounded-xl transition-colors {mode === 'text'
				? 'bg-blue-600 text-white'
				: 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'}"
		>
			<FileText size={20} />
			Paste Text
		</button>
	</div>

	{#if mode === 'url'}
		<form onsubmit={(e) => { e.preventDefault(); handleUrlSubmit(); }} class="flex flex-col gap-4">
			<input
				type="url"
				bind:value={url}
				placeholder="Paste article URL..."
				class="w-full px-4 py-3 bg-zinc-800 border border-zinc-700 rounded-xl text-white placeholder-zinc-500 focus:outline-none focus:border-blue-500"
				disabled={loading}
			/>
			<label class="flex items-center gap-2 text-sm text-zinc-400 cursor-pointer select-none">
				<input
					type="checkbox"
					bind:checked={aiOptimize}
					disabled={loading}
					class="w-4 h-4 rounded bg-zinc-700 border-zinc-600 text-blue-600 focus:ring-blue-500 focus:ring-offset-zinc-900"
				/>
				<Wand2 size={16} class={aiOptimize ? 'text-blue-400' : ''} />
				<span>AI Optimization</span>
				<span class="text-zinc-600">(cleaner title & content)</span>
			</label>
			<button
				type="submit"
				disabled={loading || !url.trim()}
				class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-zinc-700 disabled:text-zinc-500 text-white font-medium rounded-xl transition-colors flex items-center justify-center gap-2"
			>
				{#if loading}
					<Loader2 size={20} class="animate-spin" />
					{loadingStatus || 'Processing...'}
				{:else}
					Add Article
				{/if}
			</button>
		</form>
		<p class="text-xs text-zinc-500 text-center -mt-2">
			For public articles only. Paywalled content won't work.
		</p>
	{:else}
		<form onsubmit={(e) => { e.preventDefault(); handleTextSubmit(); }} class="flex flex-col gap-4">
			<input
				type="text"
				bind:value={title}
				placeholder="Article title..."
				class="w-full px-4 py-3 bg-zinc-800 border border-zinc-700 rounded-xl text-white placeholder-zinc-500 focus:outline-none focus:border-blue-500"
				disabled={loading}
			/>
			<textarea
				bind:value={content}
				placeholder="Paste article content here..."
				rows={10}
				class="w-full px-4 py-3 bg-zinc-800 border border-zinc-700 rounded-xl text-white placeholder-zinc-500 focus:outline-none focus:border-blue-500 resize-none"
				disabled={loading}
			></textarea>
			<label class="flex items-center gap-2 text-sm text-zinc-400 cursor-pointer select-none">
				<input
					type="checkbox"
					bind:checked={aiOptimize}
					disabled={loading}
					class="w-4 h-4 rounded bg-zinc-700 border-zinc-600 text-blue-600 focus:ring-blue-500 focus:ring-offset-zinc-900"
				/>
				<Wand2 size={16} class={aiOptimize ? 'text-blue-400' : ''} />
				<span>AI Optimization</span>
				<span class="text-zinc-600">(cleaner title & content)</span>
			</label>
			<button
				type="submit"
				disabled={loading || !title.trim() || !content.trim()}
				class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-zinc-700 disabled:text-zinc-500 text-white font-medium rounded-xl transition-colors flex items-center justify-center gap-2"
			>
				{#if loading}
					<Loader2 size={20} class="animate-spin" />
					{loadingStatus || 'Processing...'}
				{:else}
					Add Article
				{/if}
			</button>
		</form>
	{/if}
</div>
