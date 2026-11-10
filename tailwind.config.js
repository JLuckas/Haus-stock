import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    // Dark mode follows the `dark` class on <html> (set before paint by layouts/partials/head).
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            /*
             * Design tokens. Views use `gray-*` for neutrals and `indigo-*` for the accent;
             * the neutral scale is zinc (no blue cast), which reads cleaner on both themes.
             */
            colors: {
                gray: colors.zinc,
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                xs: '0 1px 2px 0 rgb(0 0 0 / 0.04)',
                card: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.03)',
                pop: '0 10px 38px -10px rgb(0 0 0 / 0.25), 0 10px 20px -15px rgb(0 0 0 / 0.2)',
            },
            // Full HD / ultrawide monitors: let pages grow instead of staying a narrow column.
            screens: {
                '3xl': '1920px',
                '4xl': '2560px',
            },
            keyframes: {
                'toast-in': {
                    from: { opacity: '0', transform: 'translateY(8px) scale(0.98)' },
                    to: { opacity: '1', transform: 'translateY(0) scale(1)' },
                },
                shrink: {
                    from: { transform: 'scaleX(1)' },
                    to: { transform: 'scaleX(0)' },
                },
            },
            animation: {
                'toast-in': 'toast-in 200ms ease-out',
                shrink: 'shrink linear forwards',
            },
        },
    },

    plugins: [
        forms,
        // `collapsed:` — desktop sidebar shrunk to icons (class on <html>, set before paint).
        plugin(({ addVariant }) => {
            addVariant('collapsed', '@media (min-width: 1024px) { .sidebar-collapsed & }');
        }),
    ],
};
