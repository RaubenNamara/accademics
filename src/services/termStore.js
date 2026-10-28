import { computed, reactive } from 'vue';
import { academicSessionsAPI } from './api.js';

/**
 * The term every module shows. It starts on the open term (the one teachers enter marks in); an
 * admin can pick another term from the top bar to look back. The pick lasts for this browser tab
 * and falls back to the open term once that term changes (e.g. after closing a term).
 */
const KEY = 'sf.viewTerm';
const state = reactive({ sessions: [], selectedId: null, loaded: false, loading: null });

const read = () => {
  try { return JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch { return null; }
};
const write = (v) => {
  try { v ? sessionStorage.setItem(KEY, JSON.stringify(v)) : sessionStorage.removeItem(KEY); } catch { /* unavailable */ }
};

const open = computed(() => state.sessions.find((s) => Number(s.is_active) === 1) || null);
const selected = computed(() => state.sessions.find((s) => s.id === state.selectedId) || open.value || state.sessions[0] || null);

async function load(force = false) {
  if (state.loading && !force) return state.loading;
  state.loading = (async () => {
    try {
      const res = await academicSessionsAPI.getAll();
      state.sessions = (res?.data || []).map((s) => ({ ...s, id: Number(s.id) }));
      const saved = read();
      const openId = open.value?.id ?? null;
      const keep = saved && saved.openId === openId && state.sessions.some((s) => s.id === saved.id);
      state.selectedId = keep ? saved.id : openId;
      if (!keep) write(null);
    } finally {
      state.loaded = true;
    }
  })();
  return state.loading;
}

function select(id) {
  state.selectedId = Number(id);
  write(state.selectedId === open.value?.id ? null : { id: state.selectedId, openId: open.value?.id ?? null });
}

const label = (s) => (s ? `${s.academic_year} · Term ${s.term}` : '');
// open = the current term; reopened = a closed term opened again for data entry (alongside the current one)
const statusOf = (s) => (Number(s?.is_active) === 1 ? 'open' : Number(s?.is_archived) === 1 ? 'closed' : s?.reopened_at ? 'reopened' : 'upcoming');

export default {
  state,
  open,
  selected,
  load,
  select,
  label,
  statusOf,
  reset: () => { write(null); state.selectedId = open.value?.id ?? null; },
  isViewingOpen: computed(() => !!selected.value && selected.value.id === open.value?.id),
};
