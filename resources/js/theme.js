/**
 * Light / dark / system theme. The choice lives in localStorage ("theme"); "system" (no value)
 * follows the device setting live. The initial class is applied before paint by
 * resources/views/layouts/partials/head.blade.php to avoid a flash of the wrong theme.
 */
const STORAGE_KEY = 'theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function stored() {
    try {
        return localStorage.getItem(STORAGE_KEY) ?? 'system';
    } catch {
        return 'system';
    }
}

function apply(theme) {
    const dark = theme === 'dark' || (theme === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', dark);
}

export function themeSwitcher() {
    return {
        theme: stored(),

        init() {
            media.addEventListener('change', () => this.theme === 'system' && apply('system'));
            // Keep several open tabs in sync.
            window.addEventListener('storage', (event) => {
                if (event.key === STORAGE_KEY) {
                    this.theme = event.newValue ?? 'system';
                    apply(this.theme);
                }
            });
        },

        set(theme) {
            this.theme = theme;
            try {
                theme === 'system' ? localStorage.removeItem(STORAGE_KEY) : localStorage.setItem(STORAGE_KEY, theme);
            } catch {
                // Private mode / blocked storage: the choice just won't persist.
            }
            apply(theme);
        },

        cycle() {
            this.set({ light: 'dark', dark: 'system', system: 'light' }[this.theme]);
        },
    };
}
