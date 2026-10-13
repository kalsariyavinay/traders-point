import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Colours are CSS custom properties holding space-separated RGB channels, so
 * `bg-ink/60` and friends still work while the whole palette flips between
 * themes from one block in app.css.
 */
const themed = (variable) => `rgb(var(${variable}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // `sans` stays Figtree so the Breeze admin screens are untouched.
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Public site: geometric display, humanist body, terminal mono
                // for every figure, price and label.
                display: ['Sora', ...defaultTheme.fontFamily.sans],
                body: ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                data: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },

            colors: {
                /*
                 * These names are ROLES, not literal colours. `ink` is the page
                 * surface and `paper` is the text on it -- which means in light
                 * mode `ink` is near-white and `paper` is near-black. Accents
                 * darken in light mode so that `bg-gold text-ink` (and the
                 * bull/bear equivalents) stay readable in both themes.
                 */
                ink: {
                    DEFAULT: themed('--c-ink'),       // page surface
                    raised: themed('--c-ink-raised'), // panels sitting on it
                    line: themed('--c-ink-line'),     // hairlines and borders
                },
                paper: themed('--c-paper'),           // primary text
                muted: themed('--c-muted'),           // secondary text
                bull: themed('--c-bull'),             // free / open / success
                bear: themed('--c-bear'),             // failure / down candle
                gold: themed('--c-gold'),             // paid / price
            },

            letterSpacing: {
                eyebrow: '0.18em',
            },
        },
    },

    plugins: [forms],
};
