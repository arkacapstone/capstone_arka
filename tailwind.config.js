import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** Colour backed by a CSS variable holding RGB channels, so opacity modifiers keep working. */
const token = (name) => `rgb(var(--console-${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Fraunces', ...defaultTheme.fontFamily.serif],
                barlow: ['Barlow', ...defaultTheme.fontFamily.sans],
                condensed: ['"Barlow Condensed"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // ARKA brand guide v1: Sail navy, Hull teal, Wave cyan, Sail gold.
                arka: {
                    navy: '#16305C',
                    teal: '#2F8299',
                    aqua: '#2AA3B8',
                    cyan: '#2AA3B8',
                    gold: '#F0A81E',
                    sand: '#F0A81E',
                    sky: '#dcedf1',
                    mist: '#f5f7f9',
                },
                // Semantic app palette shared by every role, driven by CSS variables in app.css so the
                // Appearance setting (Light / Dark / System) re-themes every screen at once.
                console: {
                    bg: token('bg'),
                    panel: token('panel'),
                    raised: token('raised'),
                    line: token('line'),
                    mark: token('mark'),
                    hero: token('hero'),
                    'hero-line': token('hero'),
                    accent: '#2F8299', // teal: primary, active, live
                    heading: token('heading'),
                    text: token('text'),
                    muted: token('muted'),
                    dim: token('dim'),
                    track: token('track'),
                    closed: token('closed'),
                    avatar: token('avatar'),
                    error: token('error'), // validation errors: always red
                },
            },
            keyframes: {
                sway: {
                    '0%, 100%': { transform: 'translateX(0) rotate(0deg)' },
                    '50%': { transform: 'translateX(-12px) rotate(-1.5deg)' },
                },
            },
            animation: {
                sway: 'sway 6s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
