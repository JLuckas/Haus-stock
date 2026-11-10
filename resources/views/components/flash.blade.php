{{-- Toasts for session('success') / session('error'). Success hides itself after a few seconds (hover pauses); errors stay. --}}
@if (session('success') || session('error'))
    @php($isError = (bool) session('error'))
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex justify-center p-4 sm:inset-x-auto sm:right-0 sm:justify-end sm:p-6" aria-live="{{ $isError ? 'assertive' : 'polite' }}">
        <div x-data="{ show: true, paused: false, timer: null, left: 5000, since: 0, auto: @js(! $isError),
                       start() { if (! this.auto) return; this.since = Date.now(); this.timer = setTimeout(() => this.show = false, this.left) },
                       pause() { if (! this.auto) return; this.paused = true; clearTimeout(this.timer); this.left -= Date.now() - this.since },
                       resume() { this.paused = false; this.start() } }"
             x-init="start()" x-show="show" x-on:mouseenter="pause()" x-on:mouseleave="resume()"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2"
             class="pointer-events-auto relative w-full max-w-sm animate-toast-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-pop dark:border-gray-800 dark:bg-gray-900"
             role="{{ $isError ? 'alert' : 'status' }}">
            <div class="flex items-start gap-3 p-4">
                <span @class(['mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full',
                    'bg-green-100 text-green-600 dark:bg-green-500/15 dark:text-green-400' => ! $isError,
                    'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' => $isError])>
                    <x-icon :name="$isError ? 'warning' : 'check-circle'" class="h-4 w-4" />
                </span>
                <p class="flex-1 pt-0.5 text-sm text-gray-800 dark:text-gray-100">{{ session('error') ?? session('success') }}</p>
                <button type="button" class="-m-1 rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200" x-on:click="show = false" aria-label="{{ __('Dismiss') }}">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
            @unless ($isError)
                <div class="absolute inset-x-0 bottom-0 h-0.5 origin-left animate-shrink bg-green-500/60" style="animation-duration: 5s" :style="{ animationPlayState: paused ? 'paused' : 'running' }"></div>
            @endunless
        </div>
    </div>
@endif
