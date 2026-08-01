/** @type {import('tailwindcss').Config} */
export default { content: ['./index.html', './src/**/*.{ts,tsx}'], theme: { extend: { colors: { brand: { 50: '#F2FBF6', 100: '#DCF8C6', 500: '#25D366', 700: '#075E54', 800: '#054C44' }, ink: '#101828', muted: '#667085', line: '#E4E7EC', canvas: '#F7F9FA' }, boxShadow: { card: '0 1px 2px rgb(16 24 40 / 0.04)' } } }, plugins: [] };
