import { json, error } from '@sveltejs/kit';
import { Readability } from '@mozilla/readability';
import { JSDOM } from 'jsdom';
import { cleanArticleText, detectLanguage } from '$lib/utils/textCleaner';
import type { RequestHandler } from './$types';

export const POST: RequestHandler = async ({ request }) => {
	const { url } = await request.json();

	if (!url || typeof url !== 'string') {
		throw error(400, 'URL is required');
	}

	// Validate URL format
	let parsedUrl: URL;
	try {
		parsedUrl = new URL(url);
	} catch {
		throw error(400, 'Invalid URL format');
	}

	console.log('Fetching URL:', parsedUrl.href);

	let response: Response;
	try {
		response = await fetch(parsedUrl.href, {
			headers: {
				'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language': 'en-US,en;q=0.9,nl;q=0.8'
			},
			redirect: 'follow'
		});
	} catch (fetchErr) {
		console.error('Network error fetching URL:', parsedUrl.href, fetchErr);
		throw error(502, 'Could not connect to the website. Please check the URL and try again.');
	}

	if (!response.ok) {
		console.error('Fetch failed:', response.status, response.statusText, 'for URL:', parsedUrl.href);
		if (response.status === 404) {
			throw error(422, 'Page not found. The URL may be incorrect or the page may have been removed.');
		}
		if (response.status === 403) {
			throw error(422, 'Access denied. The website may be blocking automated requests.');
		}
		throw error(422, `Could not fetch the page (HTTP ${response.status}). Please check the URL.`);
	}

	try {
		const html = await response.text();
		// Strip style tags to avoid CSS parsing errors with modern CSS features
		// (cssstyle/jsdom doesn't handle CSS variables in shorthand properties)
		const htmlWithoutStyles = html.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
		const dom = new JSDOM(htmlWithoutStyles, { url });
		const reader = new Readability(dom.window.document);
		const article = reader.parse();

		if (!article) {
			throw error(422, 'Could not extract article content. The page may not contain readable article text.');
		}

		// Extract text content from HTML
		const textDom = new JSDOM(article.content ?? '');
		const textContent = textDom.window.document.body?.textContent || '';
		const cleanedContent = cleanArticleText(textContent);
		const language = detectLanguage(cleanedContent);

		return json({
			title: article.title,
			content: cleanedContent,
			excerpt: article.excerpt,
			byline: article.byline,
			language
		});
	} catch (err) {
		if (err && typeof err === 'object' && 'status' in err) {
			throw err;
		}
		console.error('Parse error:', err);
		throw error(500, 'An unexpected error occurred while parsing the article. Please try again.');
	}
};
