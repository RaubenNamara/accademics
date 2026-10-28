/** @type {import('tailwindcss').Config} */
import colors from 'tailwindcss/colors';

// Green, white and black; Inter throughout, with real weights so text renders crisply.
// Modern emerald (was a muted forest green #15874a).
const green = {
  50: '#ecfdf5',
  100: '#d1fae5',
  200: '#a7f3d0',
  300: '#6ee7b7',
  400: '#34d399',
  500: '#10b981',
  600: '#059669',
  700: '#047857',
  800: '#065f46',
  900: '#064e3b',
  950: '#022c22',
};

export default {
  darkMode: 'class',
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // One brand colour for every module — change it here to re-theme the system.
        brand: green,
        primary: green,
        // eSpace uses neutral grey; the pages here were written with slate, so slate is grey.
        slate: colors.gray,
        // Any plain green-* class follows the same emerald.
        green: colors.emerald,
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        brand: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
      },
      // Real weights (the font loads 400–700), so nothing is faux-bolded or blurred.
      fontWeight: {
        semibold: '600',
        bold: '700',
        extrabold: '700',
        black: '700',
      },
    },
  },
  plugins: [],
}
