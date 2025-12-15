<script lang="ts">
	import { onMount } from 'svelte';
	import { getAllPlaylists, createPlaylist, deletePlaylist, type Playlist } from '$lib/db/database';
	import { FolderOpen, Plus, Trash2, ChevronRight } from 'lucide-svelte';

	let playlists = $state<Playlist[]>([]);
	let showCreateModal = $state(false);
	let newPlaylistName = $state('');

	onMount(() => {
		loadPlaylists();
	});

	async function loadPlaylists() {
		playlists = await getAllPlaylists();
	}

	async function handleCreate() {
		if (newPlaylistName.trim()) {
			await createPlaylist(newPlaylistName.trim());
			newPlaylistName = '';
			showCreateModal = false;
			await loadPlaylists();
		}
	}

	async function handleDelete(id: string, name: string) {
		if (confirm(`Delete "${name}"?`)) {
			await deletePlaylist(id);
			await loadPlaylists();
		}
	}
</script>

<svelte:head>
	<title>Playlists | Reader</title>
</svelte:head>

<div class="min-h-screen">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center justify-between z-10">
		<div class="flex items-center gap-2">
			<FolderOpen size={24} class="text-blue-500" />
			<h1 class="text-lg font-semibold">Playlists</h1>
		</div>
		<button onclick={() => (showCreateModal = true)} class="p-2 hover:bg-zinc-800 rounded-lg">
			<Plus size={20} />
		</button>
	</header>

	<div class="p-4 flex flex-col gap-3">
		{#if playlists.length === 0}
			<div class="text-center py-12">
				<FolderOpen size={48} class="mx-auto text-zinc-600 mb-4" />
				<p class="text-zinc-400">No playlists yet</p>
				<button
					onclick={() => (showCreateModal = true)}
					class="text-blue-400 hover:underline mt-2"
				>
					Create your first playlist
				</button>
			</div>
		{:else}
			{#each playlists as playlist (playlist.id)}
				<div class="bg-zinc-900 rounded-xl border border-zinc-800 flex items-center">
					<a
						href="/playlists/{playlist.id}"
						class="flex-1 p-4 flex items-center gap-3"
					>
						<div class="w-12 h-12 bg-zinc-800 rounded-lg flex items-center justify-center">
							<FolderOpen size={20} class="text-zinc-500" />
						</div>
						<div class="flex-1 min-w-0">
							<p class="font-medium truncate">{playlist.name}</p>
							<p class="text-sm text-zinc-500">
								{playlist.articleIds.length} article{playlist.articleIds.length !== 1 ? 's' : ''}
							</p>
						</div>
						<ChevronRight size={20} class="text-zinc-500" />
					</a>
					<button
						onclick={() => handleDelete(playlist.id, playlist.name)}
						class="p-4 text-zinc-500 hover:text-red-400"
					>
						<Trash2 size={18} />
					</button>
				</div>
			{/each}
		{/if}
	</div>
</div>

{#if showCreateModal}
	<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
		<div class="bg-zinc-900 rounded-xl p-6 w-full max-w-sm">
			<h2 class="text-lg font-semibold mb-4">Create Playlist</h2>
			<form onsubmit={(e) => { e.preventDefault(); handleCreate(); }}>
				<input
					type="text"
					bind:value={newPlaylistName}
					placeholder="Playlist name..."
					class="w-full px-4 py-3 bg-zinc-800 border border-zinc-700 rounded-lg text-white placeholder-zinc-500 focus:outline-none focus:border-blue-500 mb-4"
				/>
				<div class="flex gap-2">
					<button
						type="button"
						onclick={() => (showCreateModal = false)}
						class="flex-1 py-2 px-4 bg-zinc-800 hover:bg-zinc-700 rounded-lg"
					>
						Cancel
					</button>
					<button
						type="submit"
						disabled={!newPlaylistName.trim()}
						class="flex-1 py-2 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-zinc-700 disabled:text-zinc-500 rounded-lg"
					>
						Create
					</button>
				</div>
			</form>
		</div>
	</div>
{/if}
