/**
 * Light / dark theme for every page. The choice is saved per device; until someone picks, it
 * follows the device's own setting. Dark colours live in assets/dark.css and in each component's
 * generated dark block (scripts/build-dark-overrides.cjs).
 */
import { ref, watch } from 'vue';

const KEY = 'theme';

const read = () => {
  try {
    const saved = localStorage.getItem(KEY);
    if (saved === 'dark' || saved === 'light') return saved;
  } catch { /* storage unavailable */ }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

export const theme = ref(read());

const apply = () => document.documentElement.classList.toggle('dark', theme.value === 'dark');
apply(); // before the first paint, so there is no flash of the other theme
watch(theme, apply);

export const setTheme = (value) => {
  theme.value = value === 'dark' ? 'dark' : 'light';
  try { localStorage.setItem(KEY, theme.value); } catch { /* storage unavailable */ }
};
export const toggleTheme = () => setTheme(theme.value === 'dark' ? 'light' : 'dark');
