<x-app-layout>
    <x-slot name="title">{{ __('Profile') }}</x-slot>

    <x-slot name="header">
        <x-page-header :title="__('Profile')" :subtitle="__('Your account details and password.')" />
    </x-slot>

    <div>
        <div class="space-y-6">
            <div class="surface p-4 sm:p-6">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="surface p-4 sm:p-6">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="surface p-4 sm:p-6">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
