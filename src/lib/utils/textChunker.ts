const MAX_CHUNK_SIZE = 4500;

export function chunkText(text: string): string[] {
	const chunks: string[] = [];
	const paragraphs = text.split(/\n\n+/);

	let currentChunk = '';

	for (const paragraph of paragraphs) {
		// If the paragraph itself is too long, split by sentences
		if (paragraph.length > MAX_CHUNK_SIZE) {
			// First, save any accumulated chunk
			if (currentChunk) {
				chunks.push(currentChunk.trim());
				currentChunk = '';
			}

			// Split long paragraph into sentences
			const sentences = splitIntoSentences(paragraph);
			for (const sentence of sentences) {
				if (currentChunk.length + sentence.length + 1 > MAX_CHUNK_SIZE) {
					if (currentChunk) {
						chunks.push(currentChunk.trim());
					}
					// If single sentence is too long, split by words
					if (sentence.length > MAX_CHUNK_SIZE) {
						chunks.push(...splitLongSentence(sentence));
						currentChunk = '';
					} else {
						currentChunk = sentence;
					}
				} else {
					currentChunk += (currentChunk ? ' ' : '') + sentence;
				}
			}
		} else if (currentChunk.length + paragraph.length + 2 > MAX_CHUNK_SIZE) {
			// Adding this paragraph would exceed limit
			chunks.push(currentChunk.trim());
			currentChunk = paragraph;
		} else {
			// Add paragraph to current chunk
			currentChunk += (currentChunk ? '\n\n' : '') + paragraph;
		}
	}

	// Don't forget the last chunk
	if (currentChunk.trim()) {
		chunks.push(currentChunk.trim());
	}

	return chunks;
}

function splitIntoSentences(text: string): string[] {
	// Split on sentence boundaries, keeping the delimiter
	const sentenceRegex = /[^.!?]+[.!?]+(?:\s|$)|[^.!?]+$/g;
	const matches = text.match(sentenceRegex);
	return matches ? matches.map((s) => s.trim()).filter(Boolean) : [text];
}

function splitLongSentence(sentence: string): string[] {
	const chunks: string[] = [];
	const words = sentence.split(/\s+/);
	let current = '';

	for (const word of words) {
		if (current.length + word.length + 1 > MAX_CHUNK_SIZE) {
			chunks.push(current.trim());
			current = word;
		} else {
			current += (current ? ' ' : '') + word;
		}
	}

	if (current.trim()) {
		chunks.push(current.trim());
	}

	return chunks;
}

export function estimateReadingTime(text: string, wordsPerMinute: number = 220): number {
	const wordCount = text.split(/\s+/).length;
	return Math.round(wordCount / wordsPerMinute);
}
