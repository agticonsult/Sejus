/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
        './index.html',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                'es-blue': '#003366',
                'es-pink': '#e63946',
                'es-light-blue': '#38bdf8',
                'es-navy': '#0f172a',
                primary: {
                    DEFAULT: '#0284c7',
                    hover: '#0369a1',
                    light: '#e0f2fe',
                    dark: '#075985',
                },
                'sejus-green': {
                    DEFAULT: '#00875A',
                    hover: '#00704a',
                    light: '#d1fae5',
                    dark: '#065f46',
                },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                heading: ['Outfit', 'Inter', 'sans-serif'],
            },
        },
    },
    plugins: [],
};
