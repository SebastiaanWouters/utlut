<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="flex flex-col gap-6 mt-10">
    <div class="flex flex-col gap-2">
        <flux:heading>{{ __('Delete account') }}</flux:heading>
        <flux:subheading class="text-sm dark:text-zinc-300">{{ __('Delete your account and all of its resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button 
            variant="danger" 
            x-data="" 
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" 
            data-test="delete-user-button"
            class="w-fit transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
        >
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading class="text-sm dark:text-zinc-300">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </flux:subheading>
            </div>

            <flux:input 
                wire:model="password" 
                :label="__('Password')" 
                type="password" 
                class="transition-all duration-200"
            />

            <div class="flex justify-end gap-2 rtl:gap-reverse">
                <flux:modal.close>
                    <flux:button 
                        variant="filled" 
                        class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button 
                    variant="danger" 
                    type="submit" 
                    data-test="confirm-delete-user-button"
                    class="transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>{{ __('Delete account') }}</span>
                    <span wire:loading class="flex items-center gap-2">
                        <flux:icon.loading variant="mini" />
                        {{ __('Deleting...') }}
                    </span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
