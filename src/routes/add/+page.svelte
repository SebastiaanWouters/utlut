<script lang="ts">
	import AddArticle from '$lib/components/AddArticle.svelte';
	import { page } from '$app/state';
	import { ArrowLeft } from 'lucide-svelte';

	const url = $derived(page.url.searchParams.get('url') || '');
	const title = $derived(page.url.searchParams.get('title') || '');
	const text = $derived(page.url.searchParams.get('text') || '');
</script>

<svelte:head>
	<title>Add Article | Reader</title>
</svelte:head>

<div class="min-h-screen bg-zinc-950">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center gap-3">
		<a href="/" class="text-zinc-400 hover:text-white">
			<ArrowLeft size={24} />
		</a>
		<h1 class="text-lg font-semibold">Add Article</h1>
	</header>

	<AddArticle initialUrl={url} initialTitle={title} initialContent={text} />
</div>
