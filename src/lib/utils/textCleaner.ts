// Pre-compiled combined patterns for efficiency
const SUBSCRIPTION_PATTERN = /(?:subscribe to (?:continue|keep) reading|sign up (?:to|for) (?:read|continue|access)|create (?:a )?free account|already a subscriber\?|unlock (?:this|full) (?:article|story)|get unlimited access|start your free trial|members(?:-| )only)/gi;

const SOCIAL_PATTERN = /(?:share (?:this )?(?:article|story|post)|share on (?:facebook|twitter|linkedin|x)|follow us on|@\w+)/gi;

const RELATED_PATTERN = /(?:related (?:articles?|stories|posts)|read (?:more|next|also)|you (?:might|may) (?:also )?like|recommended for you|more from|trending (?:now|stories))/gi;

const ADS_PATTERN = /(?:advertisement|sponsored (?:content|post)|promoted)/gi;

const COOKIE_PATTERN = /(?:cookie (?:policy|consent|notice)|we use cookies|privacy policy|accept (?:all )?cookies)/gi;

const NEWSLETTER_PATTERN = /(?:sign up for (?:our )?newsletter|subscribe to (?:our )?newsletter|get (?:our )?newsletter|enter your email)/gi;

const COMMENTS_PATTERN = /(?:\d+ comments?|leave a comment|join the (?:conversation|discussion))/gi;

const CAPTION_PATTERN = /(?:\(photo(?::| ).*?\)|credit(?::| ).*$|image(?::| ).*$)/gim;

const MISC_PATTERN = /(?:loading\.\.\.|please wait|click here|tap (?:here|to))/gi;

// HTML tag pattern
const HTML_TAG_PATTERN = /<[^>]+>/g;

// Combined patterns array for single-pass where possible
const UNWANTED_PATTERNS = [
	SUBSCRIPTION_PATTERN,
	SOCIAL_PATTERN,
	RELATED_PATTERN,
	ADS_PATTERN,
	COOKIE_PATTERN,
	NEWSLETTER_PATTERN,
	COMMENTS_PATTERN,
	CAPTION_PATTERN,
	MISC_PATTERN
];

const AUTHOR_BIO_PATTERNS = [
	/^about the author/im,
	/is a (staff )?(writer|reporter|journalist|correspondent|editor)/i,
	/can be reached at/i,
	/follow .* on (twitter|x)/i,
	/^bio:?\s/im
];

export function cleanArticleText(text: string): string {
	let cleaned = text;

	// Strip any remaining HTML tags (Readability sometimes leaves artifacts)
	cleaned = cleaned.replace(HTML_TAG_PATTERN, ' ');

	// Remove unwanted patterns
	for (const pattern of UNWANTED_PATTERNS) {
		cleaned = cleaned.replace(pattern, '');
	}

	// Split into paragraphs
	let paragraphs = cleaned.split(/\n\n+/);

	// Remove short paragraphs that are likely noise (buttons, labels)
	paragraphs = paragraphs.filter((p) => {
		const trimmed = p.trim();
		// Keep if it's a substantial paragraph
		if (trimmed.length > 50) return true;
		// Filter out very short paragraphs that look like UI elements
		if (trimmed.length < 20 && !/[.!?]$/.test(trimmed)) return false;
		return true;
	});

	// Remove author bio section at the end
	const lastParagraphs = paragraphs.slice(-3);
	for (let i = lastParagraphs.length - 1; i >= 0; i--) {
		const para = lastParagraphs[i];
		if (AUTHOR_BIO_PATTERNS.some((pattern) => pattern.test(para))) {
			paragraphs = paragraphs.slice(0, paragraphs.length - (lastParagraphs.length - i));
			break;
		}
	}

	// Rejoin paragraphs
	cleaned = paragraphs.join('\n\n');

	// Normalize whitespace
	cleaned = cleaned
		.replace(/[ \t]+/g, ' ') // Multiple spaces to single
		.replace(/\n{3,}/g, '\n\n') // Multiple newlines to double
		.replace(/^\s+|\s+$/gm, '') // Trim each line
		.trim();

	// Decode common HTML entities
	cleaned = cleaned
		.replace(/&amp;/g, '&')
		.replace(/&lt;/g, '<')
		.replace(/&gt;/g, '>')
		.replace(/&quot;/g, '"')
		.replace(/&#39;/g, "'")
		.replace(/&nbsp;/g, ' ')
		.replace(/&mdash;/g, '—')
		.replace(/&ndash;/g, '–')
		.replace(/&hellip;/g, '…');

	return cleaned;
}

export function detectLanguage(text: string): 'en' | 'nl' {
	const sample = text.toLowerCase().slice(0, 2000);

	// Common Dutch words
	const dutchWords = ['de', 'het', 'een', 'van', 'en', 'in', 'is', 'dat', 'op', 'te', 'voor', 'met', 'zijn', 'aan', 'niet', 'ook', 'als', 'maar', 'bij', 'nog', 'wordt', 'heeft', 'naar', 'kunnen', 'deze', 'meer', 'veel', 'waar', 'echter', 'omdat'];

	// Common English words
	const englishWords = ['the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at', 'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she', 'or', 'will', 'would', 'could', 'should'];

	const words = sample.split(/\s+/);

	let dutchScore = 0;
	let englishScore = 0;

	for (const word of words) {
		if (dutchWords.includes(word)) dutchScore++;
		if (englishWords.includes(word)) englishScore++;
	}

	return dutchScore > englishScore * 1.5 ? 'nl' : 'en';
}
