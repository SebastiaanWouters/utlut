import { json, error } from '@sveltejs/kit';
import { env } from '$env/dynamic/private';
import { cleanArticleText, detectLanguage } from '$lib/utils/textCleaner';
import type { RequestHandler } from './$types';

const NAGA_API = 'https://api.naga.ac/v1/chat/completions';

export const POST: RequestHandler = async ({ request }) => {
	const { rawContent } = await request.json();

	if (!rawContent || typeof rawContent !== 'string') {
		throw error(400, 'Raw content is required');
	}

	if (!env.NAGA_API_KEY) {
		throw error(500, 'Naga API key not configured');
	}

	// Limit content to prevent API overload (50k chars max to avoid 431 errors)
	const trimmedContent = rawContent.slice(0, 50000);

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
						content: `You are an article content extractor. Given raw webpage content (which may include HTML, navigation, ads, comments, etc.), extract ONLY the main article content.

Output format (JSON):
{
  "title": "The article title",
  "content": "The full article text, clean paragraphs only. No ads, no navigation, no comments, no author bios, no 'related articles', no subscription prompts. Just the main article body text."
}

Rules:
- Extract the actual article title, not the site name
- Remove all HTML tags, keeping only clean text
- Remove navigation menus, headers, footers, sidebars
- Remove ads, promotional content, newsletter signups
- Remove comments sections
- Remove "related articles" or "you might also like" sections
- Remove author bios at the end
- Remove social sharing buttons/text
- Keep only the main article paragraphs
- Preserve paragraph breaks with double newlines
- If you cannot find article content, return {"title": "", "content": ""}`
					},
					{
						role: 'user',
						content: `Extract the article from this raw webpage content:\n\n${trimmedContent}`
					}
				],
				temperature: 0.1,
				max_tokens: 16000
			})
		});

		if (!response.ok) {
			const errorText = await response.text();
			console.error('Naga API error:', errorText);
			throw error(response.status, `AI extraction failed: ${response.statusText}`);
		}

		const data = await response.json();
		const content = data.choices?.[0]?.message?.content;

		if (!content) {
			throw error(500, 'No response from AI');
		}

		// Parse the JSON response
		let extracted: { title: string; content: string };
		try {
			// Handle potential markdown code blocks in response
			const jsonMatch = content.match(/```(?:json)?\s*([\s\S]*?)\s*```/) || [null, content];
			extracted = JSON.parse(jsonMatch[1] || content);
		} catch {
			console.error('Failed to parse AI response:', content);
			throw error(500, 'Failed to parse extracted content');
		}

		if (!extracted.title || !extracted.content) {
			throw error(422, 'Could not extract article content from the page');
		}

		// Clean and process the extracted content
		const cleanedContent = cleanArticleText(extracted.content);
		const language = detectLanguage(cleanedContent);

		return json({
			title: extracted.title.trim(),
			content: cleanedContent,
			language
		});
	} catch (err) {
		if (err && typeof err === 'object' && 'status' in err) {
			throw err;
		}
		console.error('Extract error:', err);
		throw error(500, 'Failed to extract article content');
	}
};
