<x-guest-layout>
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ __('Welcome back') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Sign in to manage stock, orders and finances.') }}</p>

    <!-- Session Status -->
    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="link text-sm" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                @endif
            </div>
            <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <label for="remember_me" class="inline-flex items-center gap-2">
            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-950 dark:checked:bg-indigo-500 dark:focus:ring-offset-gray-900" name="remember">
            <span class="text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
        </label>

        <x-primary-button class="h-10 w-full">
            {{ __('Log in') }}
        </x-primary-button>
    </form>
</x-guest-layout>
