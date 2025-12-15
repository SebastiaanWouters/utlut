<script lang="ts">
	import { onMount } from 'svelte';
	import { getSettings, updateSettings, type Settings } from '$lib/db/database';
	import { fetchVoices } from '$lib/services/ttsService';
	import { Settings as SettingsIcon, Volume2, Bell, Music } from 'lucide-svelte';

	let settings = $state<Settings | null>(null);
	let voices = $state<Array<{ id: string; name: string }>>([]);
	let loadingVoices = $state(false);

	onMount(async () => {
		settings = await getSettings();
		loadVoices();
	});

	async function loadVoices() {
		loadingVoices = true;
		try {
			voices = await fetchVoices().catch(() => []);
		} finally {
			loadingVoices = false;
		}
	}

	async function handleChange<K extends keyof Settings>(key: K, value: Settings[K]) {
		if (settings) {
			settings = { ...settings, [key]: value };
			await updateSettings({ [key]: value });
		}
	}
</script>

<svelte:head>
	<title>Settings | Reader</title>
</svelte:head>

<div class="min-h-screen">
	<header class="sticky top-0 bg-zinc-950/90 backdrop-blur border-b border-zinc-800 px-4 pb-3 pt-safe-offset-3 flex items-center gap-2 z-10">
		<SettingsIcon size={24} class="text-blue-500" />
		<h1 class="text-lg font-semibold">Settings</h1>
	</header>

	{#if settings}
		<div class="p-4 flex flex-col gap-6">
			<section>
				<h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wide mb-3 flex items-center gap-2">
					<Volume2 size={16} />
					Voice Settings
				</h2>
				<div class="bg-zinc-900 rounded-xl border border-zinc-800 divide-y divide-zinc-800">
					<div class="p-4">
						<label for="voice" class="block text-sm font-medium mb-2">Voice</label>
						<p class="text-xs text-zinc-500 mb-2">Applies after regenerating audio</p>
						<select
							id="voice"
							value={settings.voice}
							onchange={(e) => handleChange('voice', e.currentTarget.value)}
							class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-white"
							disabled={loadingVoices}
						>
							{#each voices as v}
								<option value={v.id}>{v.name}</option>
							{/each}
						</select>
					</div>
					<div class="p-4">
						<label for="playback-speed" class="block text-sm font-medium mb-2">Playback Speed</label>
						<select
							id="playback-speed"
							value={settings.playbackSpeed}
							onchange={(e) => handleChange('playbackSpeed', parseFloat(e.currentTarget.value))}
							class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-white"
						>
							<option value={0.75}>0.75×</option>
							<option value={1}>1× (Normal)</option>
							<option value={1.25}>1.25×</option>
							<option value={1.5}>1.5×</option>
							<option value={2}>2×</option>
						</select>
					</div>
				</div>
			</section>

			<section>
				<h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wide mb-3 flex items-center gap-2">
					<Bell size={16} />
					Playback
				</h2>
				<div class="bg-zinc-900 rounded-xl border border-zinc-800 divide-y divide-zinc-800">
					<label class="flex items-center justify-between p-4 cursor-pointer">
						<div>
							<p class="font-medium">Announce Articles</p>
							<p class="text-sm text-zinc-500">Speak "Now playing: [title]" before each article</p>
						</div>
						<input
							type="checkbox"
							checked={settings.announceArticles}
							onchange={(e) => handleChange('announceArticles', e.currentTarget.checked)}
							class="w-5 h-5 rounded bg-zinc-800 border-zinc-600 text-blue-600 focus:ring-blue-500"
						/>
					</label>
					<label class="flex items-center justify-between p-4 cursor-pointer">
						<div>
							<p class="font-medium">Transition Chime</p>
							<p class="text-sm text-zinc-500">Play a sound between articles</p>
						</div>
						<input
							type="checkbox"
							checked={settings.transitionChime}
							onchange={(e) => handleChange('transitionChime', e.currentTarget.checked)}
							class="w-5 h-5 rounded bg-zinc-800 border-zinc-600 text-blue-600 focus:ring-blue-500"
						/>
					</label>
				</div>
			</section>

			<section>
				<h2 class="text-sm font-semibold text-zinc-400 uppercase tracking-wide mb-3 flex items-center gap-2">
					<Music size={16} />
					Tools
				</h2>
				<div class="bg-zinc-900 rounded-xl border border-zinc-800 divide-y divide-zinc-800">
					<a href="/shortcut" class="block p-4 hover:bg-zinc-800">
						<p class="font-medium">iOS Shortcut</p>
						<p class="text-sm text-zinc-500">Save paywalled articles from Safari</p>
					</a>
					<a href="/bookmarklet" class="block p-4 hover:bg-zinc-800">
						<p class="font-medium">Desktop Bookmarklet</p>
						<p class="text-sm text-zinc-500">Add articles from desktop browsers</p>
					</a>
				</div>
			</section>
		</div>
	{:else}
		<div class="p-4 text-center text-zinc-500">Loading...</div>
	{/if}
</div>
