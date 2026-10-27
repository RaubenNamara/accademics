/** @type {import('tailwindcss').Config} */
import colors from 'tailwindcss/colors';

// Same design system as eSpace/Tusome (http://localhost:3001): green, white and black,
// Inter throughout, nothing heavier than semi-bold.
const green = {
  50: '#effbf3',
  100: '#d7f5e1',
  200: '#b0eac6',
  300: '#7dd8a3',
  400: '#46bf7b',
  500: '#22a45d',
  600: '#15874a',
  700: '#126c3d',
  800: '#115633',
  900: '#0f472b',
  950: '#062a18',
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
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        brand: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
      },
      // Light, calm type: "semibold" labels read as medium, nothing heavier than 600.
      fontWeight: {
        semibold: '500',
        bold: '600',
        extrabold: '600',
        black: '600',
      },
    },
  },
  plugins: [],
}
