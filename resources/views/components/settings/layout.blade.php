<div class="flex items-start max-md:flex-col gap-6 md:gap-10">
    <div class="w-full pb-4 md:w-[220px] shrink-0">
        <flux:navlist class="transition-all duration-200">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate class="transition-all duration-200">{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('user-password.edit')" wire:navigate class="transition-all duration-200">{{ __('Password') }}</flux:navlist.item>
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <flux:navlist.item :href="route('two-factor.show')" wire:navigate class="transition-all duration-200">{{ __('Two-Factor Auth') }}</flux:navlist.item>
            @endif
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate class="transition-all duration-200">{{ __('Appearance') }}</flux:navlist.item>
            <flux:navlist.item :href="route('voice.edit')" wire:navigate class="transition-all duration-200">{{ __('Voice') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden dark:border-zinc-700" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <div class="flex flex-col gap-2 mb-5">
            <flux:heading>{{ $heading ?? '' }}</flux:heading>
            <flux:subheading class="text-sm dark:text-zinc-300">{{ $subheading ?? '' }}</flux:subheading>
        </div>

        <div class="w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
