<script lang="ts">
	import { onMount } from 'svelte';
	import { getAllArticles, type Article } from '$lib/db/database';
	import ArticleCard from '$lib/components/ArticleCard.svelte';
	import { Library, RefreshCw, Loader2 } from 'lucide-svelte';
	import { generatingCount } from '$lib/stores/audioStore';

	let articles = $state<Article[]>([]);
	let loading = $state(true);

	onMount(() => {
		loadArticles();
	});

	async function loadArticles() {
		loading = true;
		try {
			articles = await getAllArticles();
		} catch (err) {
			console.error('Failed to load articles:', err);
		} finally {
			loading = false;
		}
	}

	function handleDelete(articleId: string) {
		articles = articles.filter((a) => a.id !== articleId);
	}
</script>

<svelte:head>
	<title>Library | Reader</title>
</svelte:head>

<div class="min-h-screen">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center justify-between z-10">
		<div class="flex items-center gap-2">
			<Library size={24} class="text-blue-500" />
			<h1 class="text-lg font-semibold">Library</h1>
		</div>
		<button onclick={loadArticles} class="p-2 hover:bg-zinc-800 rounded-lg" disabled={loading}>
			<RefreshCw size={20} class={loading ? 'animate-spin' : ''} />
		</button>
	</header>

	{#if $generatingCount > 0}
		<div class="bg-green-900/30 border-b border-green-800/50 px-4 py-2 flex items-center gap-2 text-sm text-green-300">
			<Loader2 size={16} class="animate-spin" />
			<span>Generating audio for {$generatingCount} article{$generatingCount > 1 ? 's' : ''}...</span>
		</div>
	{/if}

	<div class="p-4 flex flex-col gap-3">
		{#if loading && articles.length === 0}
			<div class="flex items-center justify-center py-12">
				<RefreshCw size={24} class="animate-spin text-zinc-500" />
			</div>
		{:else if articles.length === 0}
			<div class="text-center py-12">
				<Library size={48} class="mx-auto text-zinc-600 mb-4" />
				<p class="text-zinc-400">No articles yet</p>
				<p class="text-sm text-zinc-500 mt-1">
					Tap the + button to add your first article
				</p>
			</div>
		{:else}
			{#each articles as article (article.id)}
				<ArticleCard {article} on:delete={() => handleDelete(article.id)} />
			{/each}
		{/if}
	</div>
</div>
