<script lang="ts">
	import { ArrowLeft, Download, Share, ClipboardPaste, CheckCircle2 } from 'lucide-svelte';
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
					<path d="M7.5 21.5v-13h-3l7.5-7 7.5 7h-3v13h-9z"/>
				</svg>
			</div>
			<h2 class="text-xl font-bold text-white mb-2">Save Paywalled Articles</h2>
			<p class="text-purple-200/70 text-sm">
				Capture content from sites you're logged into with a simple iOS Shortcut
			</p>
		</div>

		<!-- Install Button - Main CTA -->
		<a
			href="https://www.icloud.com/shortcuts/9008d991b2a24d868ab9d9eb4d4b420f"
			target="_blank"
			rel="noopener noreferrer"
			class="flex items-center justify-center gap-3 w-full py-4 px-4 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors text-lg"
		>
			<Download size={22} />
			Add Shortcut to iPhone
		</a>
		<p class="text-xs text-zinc-500 text-center -mt-3">
			Opens in the Shortcuts app - tap "Add Shortcut" to install
		</p>

		<!-- How to Use Section -->
		<div class="bg-zinc-900 rounded-2xl border border-zinc-800 overflow-hidden">
			<div class="p-5 border-b border-zinc-800">
				<h3 class="font-semibold text-white mb-4">How to Use</h3>

				<div class="space-y-5">
					<div class="flex gap-3">
						<div class="bg-green-600/20 p-2 rounded-lg h-fit">
							<Share size={18} class="text-green-400" />
						</div>
						<div>
							<p class="text-zinc-200 text-sm font-medium">1. Run shortcut on any article</p>
							<p class="text-zinc-500 text-sm mt-1">
								Open an article in Safari, tap the <strong class="text-zinc-300">Share button</strong>, then tap <strong class="text-zinc-300">"Copy Page for Reader"</strong>
							</p>
						</div>
					</div>

					<div class="flex gap-3">
						<div class="bg-purple-600/20 p-2 rounded-lg h-fit">
							<ClipboardPaste size={18} class="text-purple-400" />
						</div>
						<div>
							<p class="text-zinc-200 text-sm font-medium">2. Import into Reader</p>
							<p class="text-zinc-500 text-sm mt-1">
								Open Reader, go to <strong class="text-zinc-300">Add Article</strong>, and tap <strong class="text-zinc-300">"Import from Clipboard"</strong>
							</p>
						</div>
					</div>
				</div>
			</div>

			<div class="p-5 bg-zinc-900/50">
				<div class="flex items-start gap-2">
					<CheckCircle2 size={18} class="text-green-400 mt-0.5 shrink-0" />
					<p class="text-zinc-400 text-sm">
						<strong class="text-zinc-200">Works with paywalled content!</strong> The shortcut runs inside Safari using your logged-in session, so it can capture articles behind paywalls.
					</p>
				</div>
			</div>
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
