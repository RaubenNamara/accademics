import { watch } from 'vue';
import termStore from '../services/termStore.js';

/**
 * Keeps a page's own year/term filters on the term chosen in the top bar (the open term by
 * default). `apply(year, term)` copies the term into the page's filters; `reload` refetches.
 * Call it in setup, before the page's onMounted load, so the first load already uses the term.
 */
export function useGlobalTerm(apply, reload) {
  const sync = () => {
    const s = termStore.selected.value;
    if (!s) return false;
    apply(Number(s.academic_year), Number(s.term), s);
    return true;
  };
  sync();
  watch(() => termStore.selected.value?.id, (id, old) => {
    if (id !== old && sync() && reload) reload();
  });
  if (!termStore.state.loaded) termStore.load();
}
