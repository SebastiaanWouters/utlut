import { json, error } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { cleanArticleText, detectLanguage } from '$lib/utils/textCleaner';
import type { RequestHandler } from './$types';

const NAGA_API = 'https://api.naga.ac/v1/chat/completions';

export const POST: RequestHandler = async ({ request }) => {
	const { title, content } = await request.json();

	if (!title || typeof title !== 'string') {
		throw error(400, 'Title is required');
	}

	if (!content || typeof content !== 'string') {
		throw error(400, 'Content is required');
	}

	if (!env.NAGA_API_KEY) {
		throw error(500, 'Naga API key not configured');
	}

	// Limit content to prevent API overload (15k chars for optimization)
	const trimmedContent = content.slice(0, 15000);

	try {
		const response = await fetch(NAGA_API, {
			method: 'POST',
			headers: {
				'Authorization': `Bearer ${env.NAGA_API_KEY}`,
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				model: 'gemini-2.5-flash:free',
				messages: [
					{
						role: 'system',
						content: `You are an article optimizer. Given an already-extracted article (title + content), clean up any remaining artifacts while preserving the actual article text.

Output format (JSON):
{
  "title": "Clean article title",
  "content": "Cleaned article content"
}

Title cleanup rules:
- Remove site names and suffixes (e.g., "Article Title - Site Name" → "Article Title")
- Remove section prefixes (e.g., "Opinion: Title" → "Title" only if it's metadata, not part of the title)
- Remove trailing metadata like dates or author names
- Keep the core article headline intact

Content cleanup rules:
- Remove boilerplate phrases: "This story originally appeared...", "This article was first published..."
- Remove promotional content: "Subscribe to...", "Sign up for...", "Follow us on..."
- Remove orphaned captions or credits without context
- Remove "Related:" or "See also:" sections
- Remove author bios at the end
- PRESERVE all actual article paragraphs unchanged
- PRESERVE paragraph structure and breaks

If the content is already clean, return it unchanged. Do not rewrite or summarize the article.`
					},
					{
						role: 'user',
						content: `Optimize this article:\n\nTitle: ${title}\n\nContent:\n${trimmedContent}`
					}
				],
				temperature: 0.1,
				max_tokens: 8000
			})
		});

		if (!response.ok) {
			const errorText = await response.text();
			console.error('Naga API error:', errorText);
			throw error(response.status, `AI optimization failed: ${response.statusText}`);
		}

		const data = await response.json();
		const responseContent = data.choices?.[0]?.message?.content;

		if (!responseContent) {
			throw error(500, 'No response from AI');
		}

		// Parse the JSON response
		let optimized: { title: string; content: string };
		try {
			// Handle potential markdown code blocks in response
			const jsonMatch = responseContent.match(/```(?:json)?\s*([\s\S]*?)\s*```/) || [null, responseContent];
			optimized = JSON.parse(jsonMatch[1] || responseContent);
		} catch {
			console.error('Failed to parse AI response:', responseContent);
			// Fall back to original content if parsing fails
			return json({
				title: title.trim(),
				content: cleanArticleText(content),
				language: detectLanguage(content)
			});
		}

		// Use original values if AI returned empty
		const finalTitle = optimized.title?.trim() || title.trim();
		const finalContent = optimized.content?.trim() || content;

		// Clean and process the optimized content
		const cleanedContent = cleanArticleText(finalContent);
		const language = detectLanguage(cleanedContent);

		return json({
			title: finalTitle,
			content: cleanedContent,
			language
		});
	} catch (err) {
		if (err && typeof err === 'object' && 'status' in err) {
			throw err;
		}
		console.error('Optimize error:', err);
		// Fall back to original content on any error
		return json({
			title: title.trim(),
			content: cleanArticleText(content),
			language: detectLanguage(content)
		});
	}
};
