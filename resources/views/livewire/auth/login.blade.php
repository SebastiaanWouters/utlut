<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
                class="transition-all duration-200"
            />

            <!-- Password -->
            <div class="flex flex-col gap-2">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                    class="transition-all duration-200"
                />

                @if (Route::has('password.request'))
                    <flux:link 
                        class="text-sm text-right text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors duration-200 font-medium" 
                        :href="route('password.request')" 
                        wire:navigate
                    >
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" class="transition-all duration-200" />

            <div class="flex items-center justify-end">
                <flux:button 
                    variant="primary" 
                    type="submit" 
                    class="w-full transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]" 
                    data-test="login-button"
                >
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="flex items-center justify-center gap-1 text-sm text-center rtl:gap-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link 
                    :href="route('register')" 
                    wire:navigate
                    class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors duration-200"
                >
                    {{ __('Sign up') }}
                </flux:link>
            </div>
        @endif
    </div>
</x-layouts.auth>
