<script lang="ts">
	import { page } from '$app/state';
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import {
		getPlaylist,
		getArticle,
		removeArticleFromPlaylist,
		getAllArticles,
		addArticleToPlaylist,
		type Playlist,
		type Article
	} from '$lib/db/database';
	import { audioManager } from '$lib/audio/AudioManager';
	import { queue } from '$lib/stores/audioStore';
	import { ArrowLeft, Play, Shuffle, Plus, X, GripVertical } from 'lucide-svelte';

	let playlist = $state<Playlist | null | undefined>(null);
	let articles = $state<Article[]>([]);
	let allArticles = $state<Article[]>([]);
	let showAddModal = $state(false);
	let loading = $state(true);

	const playlistId = $derived(page.params.id);

	onMount(() => {
		loadPlaylist();
	});

	$effect(() => {
		if (playlistId) {
			loadPlaylist();
		}
	});

	async function loadPlaylist() {
		loading = true;
		try {
			if (!playlistId) {
				goto('/playlists');
				return;
			}
			playlist = await getPlaylist(playlistId);
			if (!playlist) {
				goto('/playlists');
				return;
			}

			const loadedArticles: Article[] = [];
			for (const id of playlist.articleIds) {
				const article = await getArticle(id);
				if (article) loadedArticles.push(article);
			}
			articles = loadedArticles;
		} finally {
			loading = false;
		}
	}

	async function playAll() {
		if (articles.length > 0) {
			await audioManager?.clearQueue();
			for (const article of articles) {
				await audioManager?.addToQueue(article.id);
			}
			await audioManager?.playArticle(articles[0].id);
		}
	}

	async function shufflePlay() {
		if (articles.length > 0) {
			const shuffled = [...articles].sort(() => Math.random() - 0.5);
			await audioManager?.clearQueue();
			for (const article of shuffled) {
				await audioManager?.addToQueue(article.id);
			}
			await audioManager?.playArticle(shuffled[0].id);
		}
	}

	async function removeFromPlaylist(articleId: string) {
		if (playlist) {
			await removeArticleFromPlaylist(playlist.id, articleId);
			articles = articles.filter((a) => a.id !== articleId);
		}
	}

	async function openAddModal() {
		allArticles = await getAllArticles();
		showAddModal = true;
	}

	async function addToPlaylist(articleId: string) {
		if (playlist && !playlist.articleIds.includes(articleId)) {
			await addArticleToPlaylist(playlist.id, articleId);
			await loadPlaylist();
		}
	}

	const availableArticles = $derived(
		allArticles.filter((a) => !playlist?.articleIds.includes(a.id))
	);
</script>

<svelte:head>
	<title>{playlist?.name || 'Playlist'} | Reader</title>
</svelte:head>

<div class="min-h-screen">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center gap-3 z-10">
		<a href="/playlists" class="text-zinc-400 hover:text-white">
			<ArrowLeft size={24} />
		</a>
		<h1 class="text-lg font-semibold flex-1 truncate">{playlist?.name || 'Loading...'}</h1>
		<button onclick={openAddModal} class="p-2 hover:bg-zinc-800 rounded-lg">
			<Plus size={20} />
		</button>
	</header>

	{#if playlist && articles.length > 0}
		<div class="p-4 flex gap-2">
			<button
				onclick={playAll}
				class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg font-medium flex items-center justify-center gap-2"
			>
				<Play size={20} />
				Play All
			</button>
			<button
				onclick={shufflePlay}
				class="py-3 px-4 bg-zinc-800 hover:bg-zinc-700 rounded-lg"
			>
				<Shuffle size={20} />
			</button>
		</div>
	{/if}

	<div class="p-4 pt-0 flex flex-col gap-2">
		{#if loading}
			<div class="text-center py-12 text-zinc-500">Loading...</div>
		{:else if articles.length === 0}
			<div class="text-center py-12 text-zinc-500">
				<p>No articles in this playlist</p>
				<button onclick={openAddModal} class="text-blue-400 hover:underline mt-2">
					Add articles
				</button>
			</div>
		{:else}
			{#each articles as article, index (article.id)}
				<div class="flex items-center gap-2 p-3 bg-zinc-900 rounded-lg border border-zinc-800">
					<div class="text-zinc-500 cursor-grab">
						<GripVertical size={18} />
					</div>
					<button
						onclick={() => audioManager?.playArticle(article.id)}
						class="flex-1 text-left min-w-0"
					>
						<p class="font-medium truncate">{article.title}</p>
						<p class="text-xs text-zinc-500">{article.language.toUpperCase()}</p>
					</button>
					<button
						onclick={() => removeFromPlaylist(article.id)}
						class="p-2 text-zinc-500 hover:text-red-400"
					>
						<X size={18} />
					</button>
				</div>
			{/each}
		{/if}
	</div>
</div>

{#if showAddModal}
	<div class="fixed inset-0 bg-black/50 flex items-end z-50">
		<div class="bg-zinc-900 rounded-t-xl w-full max-h-[70vh] flex flex-col">
			<div class="p-4 border-b border-zinc-800 flex items-center justify-between">
				<h2 class="font-semibold">Add Articles</h2>
				<button onclick={() => (showAddModal = false)} class="p-2 -mr-2">
					<X size={20} />
				</button>
			</div>
			<div class="flex-1 overflow-auto p-4 flex flex-col gap-2">
				{#if availableArticles.length === 0}
					<p class="text-center text-zinc-500 py-4">
						All articles are already in this playlist
					</p>
				{:else}
					{#each availableArticles as article (article.id)}
						<button
							onclick={() => addToPlaylist(article.id)}
							class="w-full p-3 bg-zinc-800 hover:bg-zinc-700 rounded-lg text-left flex items-center gap-3"
						>
							<Plus size={18} class="text-blue-400 shrink-0" />
							<div class="min-w-0">
								<p class="font-medium truncate">{article.title}</p>
								<p class="text-xs text-zinc-500">{article.language.toUpperCase()}</p>
							</div>
						</button>
					{/each}
				{/if}
			</div>
		</div>
	</div>
{/if}
