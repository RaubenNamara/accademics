import { computed, ref, watch } from 'vue';
import termStore from '../services/termStore.js';

/**
 * Terms for a term picker. Starts on the term chosen in the top bar (the open term by default) and
 * follows it when it changes; the page can still pick another term locally.
 */
export function useSessions() {
  const sessions = computed(() => termStore.state.sessions);
  const sessionId = ref(termStore.selected.value?.id ?? null);

  const load = async () => {
    await termStore.load();
    if (!sessionId.value) sessionId.value = termStore.selected.value?.id ?? null;
  };
  watch(() => termStore.selected.value?.id, (id) => { if (id) sessionId.value = id; });

  const session = computed(() => sessions.value.find((s) => s.id === sessionId.value) || null);
  const label = (s) => `${s.session_name || `${s.academic_year} Term ${s.term}`}${termStore.statusOf(s) === 'closed' ? ' (closed)' : ''}`;

  return { sessions, sessionId, session, load, label };
}
