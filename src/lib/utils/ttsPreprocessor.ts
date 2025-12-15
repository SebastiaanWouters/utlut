// TTS-specific text preprocessing for better speech synthesis

// Common abbreviations to expand
const ABBREVIATIONS: Record<string, string> = {
	'Dr.': 'Doctor',
	'Mr.': 'Mister',
	'Mrs.': 'Misses',
	'Ms.': 'Miss',
	'Prof.': 'Professor',
	'Jr.': 'Junior',
	'Sr.': 'Senior',
	'St.': 'Saint',
	'vs.': 'versus',
	'etc.': 'etcetera',
	'e.g.': 'for example',
	'i.e.': 'that is',
	'Inc.': 'Incorporated',
	'Ltd.': 'Limited',
	'Corp.': 'Corporation',
	'CEO': 'C E O',
	'CFO': 'C F O',
	'CTO': 'C T O',
	'AI': 'A I',
	'UI': 'U I',
	'API': 'A P I',
	'URL': 'U R L',
	'FAQ': 'F A Q',
	'ASAP': 'A S A P',
	'DIY': 'D I Y',
	'FBI': 'F B I',
	'CIA': 'C I A',
	'NASA': 'NASA',
	'NATO': 'NATO',
	'UN': 'U N',
	'EU': 'E U',
	'US': 'U S',
	'UK': 'U K'
};

// Build regex from abbreviations
const ABBREVIATION_PATTERN = new RegExp(
	Object.keys(ABBREVIATIONS)
		.map((key) => key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
		.join('|'),
	'g'
);

// URL pattern
const URL_PATTERN = /https?:\/\/[^\s]+/g;

// Markdown patterns
const BOLD_PATTERN = /\*\*([^*]+)\*\*/g;
const ITALIC_PATTERN = /\*([^*]+)\*/g;
const LINK_PATTERN = /\[([^\]]+)\]\([^)]+\)/g;
const HEADING_PATTERN = /^#{1,6}\s+/gm;
const CODE_BLOCK_PATTERN = /```[\s\S]*?```/g;
const INLINE_CODE_PATTERN = /`([^`]+)`/g;

// Number formatting
const CURRENCY_PATTERN = /\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/g;
const PERCENT_PATTERN = /(\d+(?:\.\d+)?)\s*%/g;

// Special characters that should be pauses
const DASH_PATTERN = /\s*[—–]\s*/g;
const ELLIPSIS_PATTERN = /\.{3}|…/g;

export function preprocessForTTS(text: string): string {
	let processed = text;

	// Remove code blocks entirely (not useful for speech)
	processed = processed.replace(CODE_BLOCK_PATTERN, '');

	// Remove inline code markers
	processed = processed.replace(INLINE_CODE_PATTERN, '$1');

	// Remove markdown formatting but keep text
	processed = processed.replace(BOLD_PATTERN, '$1');
	processed = processed.replace(ITALIC_PATTERN, '$1');
	processed = processed.replace(LINK_PATTERN, '$1');
	processed = processed.replace(HEADING_PATTERN, '');

	// Replace URLs with "[link]" to avoid reading long URLs
	processed = processed.replace(URL_PATTERN, 'link');

	// Expand abbreviations
	processed = processed.replace(ABBREVIATION_PATTERN, (match) => {
		return ABBREVIATIONS[match] || match;
	});

	// Handle currency (basic handling)
	processed = processed.replace(CURRENCY_PATTERN, (_, amount) => {
		return `${amount} dollars`;
	});

	// Handle percentages
	processed = processed.replace(PERCENT_PATTERN, '$1 percent');

	// Convert dashes to commas for natural pauses
	processed = processed.replace(DASH_PATTERN, ', ');

	// Convert ellipsis to pause
	processed = processed.replace(ELLIPSIS_PATTERN, '...');

	// Add slight pauses at paragraph breaks for better pacing
	processed = processed.replace(/\n\n+/g, '\n\n');

	// Clean up any double spaces
	processed = processed.replace(/  +/g, ' ');

	return processed.trim();
}

// Language-specific preprocessing
export function preprocessForTTSWithLanguage(text: string, language: 'en' | 'nl'): string {
	let processed = preprocessForTTS(text);

	if (language === 'nl') {
		// Dutch-specific handling
		// Convert euro symbol
		processed = processed.replace(/€\s*(\d+(?:[.,]\d+)?)/g, '$1 euro');
	} else {
		// English-specific handling
		// Convert pound symbol
		processed = processed.replace(/£\s*(\d+(?:[.,]\d+)?)/g, '$1 pounds');
	}

	return processed;
}
