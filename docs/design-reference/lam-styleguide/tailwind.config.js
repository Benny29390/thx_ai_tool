import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Frutiger LT Std"', 'Frutiger', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                thoxan: {
                    50: '#e6f0f8',
                    100: '#cce1f1',
                    200: '#99c3e3',
                    300: '#66a5d5',
                    400: '#3387c7',
                    500: '#006fb9',
                    600: '#005da8',
                    700: '#004a86',
                    800: '#003864',
                    900: '#002542',
                    950: '#001329',
                },
            },
        },
    },

    plugins: [forms],
};
