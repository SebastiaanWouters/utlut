<script lang="ts">
	import '../app.css';
	import { page } from '$app/state';
	import { onMount } from 'svelte';
	import { currentArticle } from '$lib/stores/audioStore';
	import { jobPollingService } from '$lib/services/jobPollingService';
	import MiniPlayer from '$lib/components/MiniPlayer.svelte';
	import { Home, ListMusic, FolderOpen, Settings, Plus } from 'lucide-svelte';

	let { children } = $props();

	// The jobPollingService automatically restores active jobs on construction,
	// but we import it here to ensure it's initialized when the app loads

	const navItems = [
		{ href: '/', icon: Home, label: 'Library' },
		{ href: '/queue', icon: ListMusic, label: 'Queue' },
		{ href: '/playlists', icon: FolderOpen, label: 'Playlists' },
		{ href: '/settings', icon: Settings, label: 'Settings' }
	];

	const showNav = $derived(!page.url.pathname.startsWith('/player'));
	const isAddPage = $derived(page.url.pathname === '/add');
</script>

<svelte:head>
	<link rel="icon" href="/icons/favicon.svg" />
</svelte:head>

<div class="min-h-dvh bg-zinc-950 flex flex-col">
	<!-- Main content area with bottom padding for nav bar -->
	<main class="flex-1" style={showNav ? `padding-bottom: calc(${$currentArticle ? '7rem' : '4rem'} + env(safe-area-inset-bottom, 0px));` : ''}>
		{@render children()}
	</main>

	{#if showNav}
		<!-- Mini player positioned above bottom nav -->
		{#if $currentArticle}
			<div class="fixed inset-x-0 z-40" style="bottom: calc(4rem + env(safe-area-inset-bottom, 0px));">
				<MiniPlayer />
			</div>
		{/if}

		<!-- Bottom navigation bar -->
		<nav class="fixed bottom-0 inset-x-0 bg-zinc-900 border-t border-zinc-800 z-50" style="padding-bottom: env(safe-area-inset-bottom, 0);">
			<div class="flex items-center justify-around h-16">
				{#each navItems as item}
					{@const isActive = page.url.pathname === item.href || (item.href !== '/' && page.url.pathname.startsWith(item.href))}
					<a
						href={item.href}
						class="flex flex-col items-center justify-center gap-0.5 px-4 py-2 {isActive
							? 'text-blue-500'
							: 'text-zinc-500'}"
					>
						<item.icon size={22} />
						<span class="text-xs">{item.label}</span>
					</a>
				{/each}
			</div>
		</nav>
	{/if}

	<!-- Floating action button -->
	{#if !isAddPage && showNav}
		<a
			href="/add"
			class="fixed right-4 w-14 h-14 bg-blue-600 hover:bg-blue-700 rounded-full flex items-center justify-center shadow-lg z-50"
			style="bottom: calc({$currentArticle ? '8rem' : '5rem'} + env(safe-area-inset-bottom, 0px));"
		>
			<Plus size={28} />
		</a>
	{/if}
</div>
