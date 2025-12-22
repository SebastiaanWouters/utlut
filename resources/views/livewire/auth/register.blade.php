<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
                class="transition-all duration-200"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
                class="transition-all duration-200"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
                class="transition-all duration-200"
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
                class="transition-all duration-200"
            />

            <div class="flex items-center justify-end">
                <flux:button 
                    type="submit" 
                    variant="primary" 
                    class="w-full transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]" 
                    data-test="register-user-button"
                >
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="flex items-center justify-center gap-1 rtl:gap-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link 
                :href="route('login')" 
                wire:navigate
                class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors duration-200"
            >
                {{ __('Log in') }}
            </flux:link>
        </div>
    </div>
</x-layouts.auth>
