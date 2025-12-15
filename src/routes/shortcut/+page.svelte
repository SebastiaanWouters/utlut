<script lang="ts">
	import { ArrowLeft, Download, Play, ClipboardPaste, CheckCircle2, ExternalLink } from 'lucide-svelte';
</script>

<svelte:head>
	<title>iOS Shortcut | Reader</title>
</svelte:head>

<div class="min-h-screen bg-zinc-950">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center gap-3 z-20">
		<a href="/" class="text-zinc-400 hover:text-white">
			<ArrowLeft size={24} />
		</a>
		<h1 class="text-lg font-semibold">iOS Shortcut</h1>
	</header>

	<div class="p-4 flex flex-col gap-6 max-w-lg mx-auto">
		<!-- Hero Section -->
		<div class="bg-gradient-to-br from-purple-900/40 to-indigo-900/40 rounded-2xl p-6 border border-purple-700/30 text-center">
			<div class="w-16 h-16 bg-purple-600/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
				<svg viewBox="0 0 24 24" class="w-8 h-8 text-purple-300" fill="currentColor">
					<path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm-1-6h2v2h-2zm0-8h2v6h-2z"/>
				</svg>
			</div>
			<h2 class="text-xl font-bold text-white mb-2">Save Paywalled Articles</h2>
			<p class="text-purple-200/70 text-sm">
				Capture content from sites you're logged into with a simple iOS Shortcut
			</p>
		</div>

		<!-- Install Section -->
		<div class="bg-zinc-900 rounded-2xl border border-zinc-800 overflow-hidden">
			<div class="p-5 border-b border-zinc-800">
				<div class="flex items-center gap-3 mb-3">
					<div class="bg-blue-600/20 p-2 rounded-lg">
						<Download size={20} class="text-blue-400" />
					</div>
					<h3 class="font-semibold text-white">Step 1: Install Shortcut</h3>
				</div>
				<p class="text-zinc-400 text-sm mb-4">
					Tap the button below to open the Shortcuts app and install "Copy Page Contents".
				</p>
				<a
					href="https://apps.apple.com/us/app/shortcuts/id915249334"
					target="_blank"
					rel="noopener noreferrer"
					class="flex items-center justify-center gap-2 w-full py-3 px-4 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-xl transition-colors"
				>
					<ExternalLink size={18} />
					Open Shortcuts App
				</a>
				<p class="text-xs text-zinc-500 mt-3 text-center">
					Create a shortcut with: Get Safari Page Contents → Copy to Clipboard
				</p>
			</div>

			<div class="p-5 border-b border-zinc-800">
				<div class="flex items-center gap-3 mb-3">
					<div class="bg-green-600/20 p-2 rounded-lg">
						<Play size={20} class="text-green-400" />
					</div>
					<h3 class="font-semibold text-white">Step 2: Use on Article</h3>
				</div>
				<div class="space-y-2 text-sm text-zinc-400">
					<p>1. Open an article in Safari (paywalled sites work!)</p>
					<p>2. Tap Share button → tap your shortcut</p>
					<p>3. Wait for it to complete (clipboard icon appears)</p>
				</div>
			</div>

			<div class="p-5">
				<div class="flex items-center gap-3 mb-3">
					<div class="bg-purple-600/20 p-2 rounded-lg">
						<ClipboardPaste size={20} class="text-purple-400" />
					</div>
					<h3 class="font-semibold text-white">Step 3: Import to Reader</h3>
				</div>
				<div class="space-y-2 text-sm text-zinc-400">
					<p>1. Open Reader and go to Add Article</p>
					<p>2. Tap <strong class="text-purple-300">"Import from Clipboard"</strong></p>
					<p>3. AI extracts the article and saves it</p>
				</div>
			</div>
		</div>

		<!-- Create Shortcut Instructions -->
		<div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800">
			<h3 class="font-semibold text-white mb-4">Creating the Shortcut</h3>

			<div class="space-y-4">
				<div class="flex gap-3">
					<span class="bg-zinc-700 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">1</span>
					<div>
						<p class="text-zinc-300 text-sm">Open <strong>Shortcuts</strong> app</p>
						<p class="text-zinc-500 text-xs mt-0.5">Tap + to create new shortcut</p>
					</div>
				</div>

				<div class="flex gap-3">
					<span class="bg-zinc-700 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">2</span>
					<div>
						<p class="text-zinc-300 text-sm">Add action: <strong>"Get Details of Safari Web Page"</strong></p>
						<p class="text-zinc-500 text-xs mt-0.5">Set detail to "Page Contents"</p>
					</div>
				</div>

				<div class="flex gap-3">
					<span class="bg-zinc-700 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">3</span>
					<div>
						<p class="text-zinc-300 text-sm">Add action: <strong>"Copy to Clipboard"</strong></p>
					</div>
				</div>

				<div class="flex gap-3">
					<span class="bg-zinc-700 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm font-medium shrink-0">4</span>
					<div>
						<p class="text-zinc-300 text-sm">Tap shortcut name → <strong>"Show in Share Sheet"</strong></p>
						<p class="text-zinc-500 text-xs mt-0.5">Select "Safari web pages"</p>
					</div>
				</div>
			</div>
		</div>

		<!-- Why This Works -->
		<div class="bg-zinc-900 rounded-2xl p-5 border border-zinc-800">
			<h4 class="font-semibold text-white mb-2 flex items-center gap-2">
				<CheckCircle2 size={18} class="text-green-400" />
				Why This Works
			</h4>
			<p class="text-zinc-400 text-sm">
				The shortcut runs inside Safari using your logged-in session. It captures the full page content including paywalled articles, then AI extracts just the article text for you.
			</p>
		</div>

		<!-- Back to Add -->
		<a
			href="/add"
			class="flex items-center justify-center gap-2 w-full py-3 px-4 bg-zinc-800 hover:bg-zinc-700 text-white font-medium rounded-xl transition-colors"
		>
			Ready to Import an Article
		</a>
	</div>
</div>
