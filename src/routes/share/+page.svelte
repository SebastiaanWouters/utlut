<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/state';
	import { onMount } from 'svelte';

	onMount(() => {
		const params = page.url.searchParams;
		const url = params.get('url') || '';
		const title = params.get('title') || '';
		const text = params.get('text') || '';

		// Redirect to add page with the shared data
		const addParams = new URLSearchParams();
		if (url) addParams.set('url', url);
		if (title) addParams.set('title', title);
		if (text) addParams.set('text', text);

		goto(`/add?${addParams.toString()}`, { replaceState: true });
	});
</script>

<div class="min-h-screen bg-zinc-950 flex items-center justify-center">
	<div class="text-zinc-400">Redirecting...</div>
</div>
