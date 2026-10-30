<template>
  <!-- Admin: sync learners with SMIS. The switch runs one sync and turns itself off when it ends. -->
  <section v-if="available" class="card relative overflow-hidden">
    <div class="flex items-center gap-3 px-4 py-3">
      <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm shadow-brand-600/30" aria-hidden="true">
        <RefreshCw class="h-5 w-5" :class="on ? 'animate-spin [animation-duration:1.4s]' : ''" />
      </span>
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-bold leading-tight text-gray-900">SMIS student sync</h2>
        <p class="mt-0.5 truncate text-xs" :class="statusTone">{{ statusLine }}</p>
      </div>
      <button v-if="last && last.status === 'success' && !on && changes" type="button" class="link-btn hidden text-xs sm:inline" @click="toggleDetails">{{ details ? 'Hide changes' : 'See changes' }}</button>
      <label class="flex flex-shrink-0 select-none items-center gap-2.5" :class="on || loading ? 'cursor-not-allowed' : 'cursor-pointer'">
        <span class="hidden text-xs font-bold uppercase tracking-wide sm:inline" :class="on ? 'text-brand-700' : 'text-gray-700'">{{ on ? 'Syncing' : 'Sync now' }}</span>
        <button
          type="button"
          role="switch"
          :aria-checked="on"
          aria-label="Sync students from SMIS"
          :disabled="on || loading"
          class="relative inline-flex h-8 w-14 items-center rounded-full ring-1 ring-inset transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed"
          :class="on ? 'bg-brand-600 ring-brand-600' : 'bg-slate-400 ring-slate-500/40 hover:bg-slate-500'"
          @click="start"
        >
          <span class="inline-block h-6 w-6 rounded-full bg-white shadow-md ring-1 ring-black/5 transition-transform duration-200" :class="on ? 'translate-x-7' : 'translate-x-1'"></span>
        </button>
      </label>
    </div>

    <!-- The last run's numbers -->
    <dl v-if="last && last.status === 'success' && !on" class="grid grid-cols-4 divide-x divide-gray-100 border-t border-gray-100 text-center">
      <div v-for="m in metrics" :key="m.label" class="py-2">
        <dt class="text-[11px] font-medium text-gray-500">{{ m.label }}</dt>
        <dd class="text-lg font-extrabold leading-tight tabular-nums" :class="m.value ? m.tone : 'text-gray-300'">{{ m.value.toLocaleString() }}</dd>
      </div>
    </dl>

    <!-- Who changed in the last run -->
    <div v-if="details" class="grid gap-3 border-t border-gray-100 px-4 py-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="g in detailGroups" :key="g.key" class="min-w-0">
        <p class="mb-1 font-semibold text-gray-700">{{ g.label }} <span class="text-gray-400">{{ (details[g.key] || []).length }}</span></p>
        <ul class="max-h-40 space-y-0.5 overflow-y-auto text-gray-600">
          <li v-for="line in details[g.key] || []" :key="line" class="truncate" :title="line">{{ line }}</li>
          <li v-if="!(details[g.key] || []).length" class="text-gray-400">None</li>
        </ul>
      </div>
    </div>

    <!-- Running: a thin moving bar along the bottom -->
    <div v-if="on" class="absolute inset-x-0 bottom-0 h-1 overflow-hidden bg-brand-100" role="status" aria-label="Syncing">
      <span class="sync-bar block h-full w-1/3 rounded-full bg-brand-600"></span>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import { studentSyncAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';
import { useToast } from '../../composables/useToast.js';

const emit = defineEmits(['synced']);
const { showToast } = useToast();
const on = ref(false);
const loading = ref(true);
const runs = ref([]);
const details = ref(null);
// Only the school whose SMIS this is gets the switch.
const available = ref(true);
let poll = null;

const last = computed(() => runs.value[0] || null);
const changes = computed(() => (last.value ? last.value.added + last.value.moved + last.value.left_school + last.value.returned : 0));
const when = (ts) => {
  if (!ts) return '';
  const d = new Date(String(ts).replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? ts : d.toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
};
const statusLine = computed(() => {
  if (on.value) return 'Syncing with SMIS… the switch turns off when done';
  if (loading.value) return 'Loading…';
  const r = last.value;
  if (!r) return 'Not synced yet. Turn on to bring the e-files in line with SMIS';
  if (r.status === 'failed') return `Last sync failed ${when(r.finished_at || r.started_at)}: ${r.message || ''}`;
  return `Last synced ${when(r.finished_at || r.started_at)}${r.started_by_name ? ` by ${r.started_by_name}` : ''} · ${r.fetched.toLocaleString()} in SMIS`;
});
const statusTone = computed(() => (last.value?.status === 'failed' && !on.value ? 'text-red-600' : 'text-gray-500'));
const metrics = computed(() => [
  { label: 'Added', value: last.value?.added || 0, tone: 'text-brand-700' },
  { label: 'Changed class', value: last.value?.moved || 0, tone: 'text-sky-700' },
  { label: 'Left school', value: last.value?.left_school || 0, tone: 'text-amber-700' },
  { label: 'Back in school', value: last.value?.returned || 0, tone: 'text-violet-700' },
]);
const detailGroups = [
  { key: 'added', label: 'Added' },
  { key: 'moved', label: 'Changed class' },
  { key: 'left', label: 'Left school' },
  { key: 'returned', label: 'Back in school' },
];

async function refresh() {
  try {
    const d = (await studentSyncAPI.status()).data;
    available.value = d.available !== false;
    runs.value = d.runs || [];
    on.value = !!d.running;
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    loading.value = false;
  }
}

async function toggleDetails() {
  if (details.value) {
    details.value = null;
    return;
  }
  try {
    details.value = (await studentSyncAPI.details(last.value.id)).data.details || {};
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
}

async function start() {
  if (on.value || loading.value) return;
  const ok = window.confirm('Sync students with SMIS now?\n\nNew learners are added, class changes are applied, and learners no longer in SMIS are marked "Left". Nothing is deleted: parents, results and documents stay in their e-files.');
  if (!ok) return;
  on.value = true;
  details.value = null;
  // Another admin's page may be showing the same run: check in every few seconds.
  poll = setInterval(refresh, 5000);
  try {
    const res = await studentSyncAPI.run();
    runs.value = res.data?.runs || runs.value;
    showToast(res.message);
    emit('synced');
  } catch (e) {
    showToast(apiError(e).message, 'error');
    await refresh();
  } finally {
    clearInterval(poll);
    on.value = false;
  }
}

onMounted(refresh);
onBeforeUnmount(() => clearInterval(poll));
</script>

<style scoped>
.sync-bar { animation: sync-slide 1.2s ease-in-out infinite; }
@keyframes sync-slide {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(300%); }
}
</style>
