<script lang="ts">
	import { ArrowLeft, Bookmark, Copy, Check, Smartphone } from 'lucide-svelte';
	import { page } from '$app/state';
	import { browser } from '$app/environment';

	let copiedBookmarklet = $state(false);

	const appUrl = $derived(page.url.origin);

	const isIOS = $derived(
		browser && /iPad|iPhone|iPod/.test(navigator.userAgent)
	);

	// Bookmarklet code for desktop
	const bookmarkletCode = $derived(`javascript:(function(){
	var text = '';
	var sel = window.getSelection();
	if (sel && sel.toString().length > 0) {
		text = sel.toString();
	} else {
		var article = document.querySelector('article') || document.querySelector('[role="main"]') || document.body;
		text = article.innerText || article.textContent || '';
	}
	var title = document.title;
	var url = location.href;
	var maxLen = 50000;
	if (text.length > maxLen) text = text.substring(0, maxLen);
	window.open('${appUrl}/add?title=' + encodeURIComponent(title) + '&url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text), '_blank');
})();`);

	const bookmarkletMinified = $derived(bookmarkletCode.replace(/\s+/g, ' ').trim());

	function copyToClipboard(text: string): boolean {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).catch(() => {
				fallbackCopy(text);
			});
			return true;
		}
		return fallbackCopy(text);
	}

	function fallbackCopy(text: string): boolean {
		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		textarea.style.top = '0';
		document.body.appendChild(textarea);
		textarea.focus();
		textarea.select();
		try {
			document.execCommand('copy');
			return true;
		} catch {
			return false;
		} finally {
			document.body.removeChild(textarea);
		}
	}

	function copyBookmarklet() {
		copyToClipboard(bookmarkletMinified);
		copiedBookmarklet = true;
		setTimeout(() => (copiedBookmarklet = false), 2000);
	}
</script>

<svelte:head>
	<title>{isIOS ? 'iOS Shortcut' : 'Bookmarklet'} | Reader</title>
</svelte:head>

<div class="min-h-screen bg-zinc-950">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center gap-3 z-20">
		<a href="/" class="text-zinc-400 hover:text-white">
			<ArrowLeft size={24} />
		</a>
		<h1 class="text-lg font-semibold">{isIOS ? 'iOS Shortcut' : 'Bookmarklet'}</h1>
	</header>

	<div class="p-4 flex flex-col gap-6 max-w-lg mx-auto relative z-0">
		{#if isIOS}
			<!-- iOS Shortcut Instructions -->
			<div class="bg-zinc-900 rounded-xl p-6 border border-zinc-800">
				<div class="flex items-center gap-3 mb-4">
					<div class="bg-blue-600 p-2 rounded-lg">
						<Smartphone size={24} />
					</div>
					<div>
						<h2 class="font-semibold">Add to Reader</h2>
						<p class="text-sm text-zinc-400">Save from Safari's share sheet</p>
					</div>
				</div>
				<p class="text-zinc-300 text-sm">
					Create a simple iOS Shortcut to save articles from Safari - works with paywalled sites you're logged into.
				</p>
			</div>

			<div class="flex flex-col gap-4">
				<h3 class="font-semibold">Create Shortcut</h3>

				<div class="flex gap-3">
					<span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">1</span>
					<p class="text-zinc-300 text-sm">Open <strong>Shortcuts</strong> app and create a new shortcut</p>
				</div>

				<div class="flex gap-3">
					<span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">2</span>
					<div class="flex-1">
						<p class="text-zinc-300 text-sm">Add action: <strong>"Get Details of Safari Web Page"</strong></p>
						<p class="text-zinc-400 text-xs mt-1">Select <strong>"Page Contents"</strong> as the detail type</p>
					</div>
				</div>

				<div class="flex gap-3">
					<span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">3</span>
					<p class="text-zinc-300 text-sm">Add action: <strong>"Copy to Clipboard"</strong></p>
				</div>

				<div class="flex gap-3">
					<span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">4</span>
					<div class="flex-1">
						<p class="text-zinc-300 text-sm">Tap the shortcut name at top</p>
						<p class="text-zinc-400 text-xs mt-1">Enable <strong>"Show in Share Sheet"</strong> → select <strong>"Safari web pages"</strong></p>
					</div>
				</div>
			</div>

			<div class="bg-zinc-900 rounded-xl p-4 border border-zinc-800">
				<h4 class="font-medium mb-2 text-sm">How to Use</h4>
				<ol class="text-zinc-400 text-sm space-y-1 list-decimal list-inside">
					<li>Open an article in Safari</li>
					<li>Tap Share → tap your shortcut (copies content)</li>
					<li>Open Reader → tap <strong>"Import from Clipboard"</strong></li>
				</ol>
			</div>
		{:else}
			<!-- Desktop Bookmarklet -->
			<div class="bg-zinc-900 rounded-xl p-6 border border-zinc-800">
				<div class="flex items-center gap-3 mb-4">
					<div class="bg-blue-600 p-2 rounded-lg">
						<Bookmark size={24} />
					</div>
					<div>
						<h2 class="font-semibold">Add to Reader</h2>
						<p class="text-sm text-zinc-400">For paywalled articles</p>
					</div>
				</div>

				<p class="text-zinc-300 text-sm mb-4">
					Drag this button to your bookmarks bar, or copy the code and create a bookmark manually:
				</p>

				<a
					href={bookmarkletMinified}
					title="+ Reader"
					class="block w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-center mb-4"
					onclick={(e) => e.preventDefault()}
					ondragstart={(e) => {
						const dt = e.dataTransfer;
						if (dt) {
							dt.setData('text/uri-list', bookmarkletMinified);
							dt.setData('text/plain', '+ Reader\n' + bookmarkletMinified);
							dt.setData('text/x-moz-url', bookmarkletMinified + '\n+ Reader');
						}
					}}
				>
					Add to Reader
				</a>

				<button
					onclick={copyBookmarklet}
					class="w-full py-2 px-4 bg-zinc-800 hover:bg-zinc-700 text-white rounded-lg flex items-center justify-center gap-2"
				>
					{#if copiedBookmarklet}
						<Check size={18} />
						Copied!
					{:else}
						<Copy size={18} />
						Copy Bookmarklet Code
					{/if}
				</button>
			</div>

			<div class="flex flex-col gap-4">
				<h3 class="font-semibold">How to Use</h3>

				<div class="flex flex-col gap-3">
					<div class="flex gap-3">
						<span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">1</span>
						<p class="text-zinc-300 text-sm">Navigate to an article you want to save (works for paywalled sites you're logged into)</p>
					</div>
					<div class="flex gap-3">
						<span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">2</span>
						<p class="text-zinc-300 text-sm">Optionally select specific text you want to convert</p>
					</div>
					<div class="flex gap-3">
						<span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">3</span>
						<p class="text-zinc-300 text-sm">Click the "Add to Reader" bookmark</p>
					</div>
				</div>
			</div>
		{/if}

		<div class="bg-zinc-900 rounded-xl p-4 border border-zinc-800">
			<h4 class="font-medium mb-2 text-sm">Why use this?</h4>
			<p class="text-zinc-400 text-sm">
				{#if isIOS}
					The shortcut runs in Safari using your logged-in session, so it can capture content from paywalled articles and subscription sites.
				{:else}
					The bookmarklet runs in your browser using your logged-in session, so it can extract content from paywalled articles and subscription content.
				{/if}
			</p>
		</div>
	</div>
</div>
