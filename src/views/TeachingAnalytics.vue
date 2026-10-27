<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Teaching Analytics</h1>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <ListToolbar
      v-model:search="search"
      placeholder="Search teacher name or code"
      :filter-count="[sortBy !== 'name' ? sortBy : '', onlyActive ? 'active' : ''].filter(Boolean).length"
      @clear="sortBy = 'name'; onlyActive = false"
    >
      <template #filters>
        <label class="block">
          <span class="filter-label">Year</span>
          <select v-model="year" class="filter-select" @change="load">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Term</span>
          <select v-model="term" class="filter-select" @change="load">
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Sort by</span>
          <select v-model="sortBy" class="filter-select">
            <option value="name">Name</option>
            <option value="load">Heaviest load first</option>
            <option value="attendance">Lowest attendance first</option>
          </select>
        </label>
        <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
          <input v-model="onlyActive" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600" />
          Active teachers only
        </label>
      </template>
      <template #actions>
        <button type="button" class="tool-btn" :disabled="loading" title="Refresh" @click="load"><RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" /><span class="hidden lg:inline">Refresh</span></button>
        <button type="button" class="tool-btn" :disabled="workload.length === 0" title="Download PDF" @click="exportReport"><FileText class="h-4 w-4" /><span class="hidden lg:inline">PDF</span></button>
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ rows.length }}</strong> teacher{{ rows.length === 1 ? '' : 's' }}</p>
        <p class="hidden text-xs text-gray-500 sm:block">Load is an estimate of lessons a week; attendance covers Term {{ term }}, {{ year }}.</p>
      </div>
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="i in 6" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="rows.length === 0" class="px-5 py-14 text-center">
        <ChartColumn class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No teachers found</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Teacher</th>
              <th class="px-4 py-3">Subjects</th>
              <th class="hidden px-4 py-3 lg:table-cell">Classes</th>
              <th class="px-4 py-3">Weekly load</th>
              <th class="px-4 py-3">Attendance</th>
              <th class="hidden px-4 py-3 xl:table-cell">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="w in pageRows" :key="w.teacher_id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(w.full_name) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900">{{ w.full_name }}</span>
                    <span class="block font-mono text-[11px] text-gray-500">{{ w.teacher_code }}</span>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 tabular-nums text-gray-700">{{ w.subjects_count }}</td>
              <td class="hidden px-4 py-3 tabular-nums text-gray-700 lg:table-cell">{{ w.classes_count }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full bg-brand-500" :style="{ width: loadBar(w.weekly_load_estimate) }"></span></span>
                  <span class="tabular-nums text-xs text-gray-700">{{ w.weekly_load_estimate }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <template v-if="w.recorded">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums" :class="rateClass(w.rate)">{{ w.rate }}%</span>
                  <span class="ml-1 text-[11px] text-gray-500">{{ w.attended }}/{{ w.recorded }}</span>
                </template>
                <span v-else class="text-xs text-gray-400">No lessons recorded</span>
              </td>
              <td class="hidden px-4 py-3 xl:table-cell">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="w.is_active ? 'text-emerald-700' : 'text-gray-500'"><span class="h-1.5 w-1.5 rounded-full" :class="w.is_active ? 'bg-emerald-500' : 'bg-gray-300'"></span>{{ w.is_active ? 'Active' : 'Inactive' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="w in pageRows" :key="w.teacher_id" class="flex items-start gap-3 px-4 py-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(w.full_name) }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-gray-900">{{ w.full_name }}</p>
              <p class="text-xs text-gray-500">{{ w.teacher_code }} · {{ w.subjects_count }} subjects · {{ w.classes_count }} classes · load {{ w.weekly_load_estimate }}</p>
              <span v-if="w.recorded" class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="rateClass(w.rate)">{{ w.rate }}% attended</span>
            </div>
          </li>
        </ul>
        <PagerBar v-model:page="page" v-model:per-page="perPage" :total="rows.length" />
      </template>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { RefreshCw, FileText, ChartColumn, Users as UsersIcon, Gauge, CalendarCheck, BookOpen } from 'lucide-vue-next';
import { teachingAnalyticsAPI } from '../services/api.js';
import { useExport } from '../composables/useExport.js';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import PagerBar from '../components/ui/PagerBar.vue';

const { exportToPdf } = useExport();
const loading = ref(true);
const year = ref(new Date().getFullYear());
const term = ref(1);
useGlobalTerm((y, t) => { year.value = y; term.value = t; }, () => load());
const years = [year.value - 1, year.value, year.value + 1];
const workload = ref([]);
const attendance = ref([]);
const summary = ref({});
const search = ref('');
const sortBy = ref('name');
const onlyActive = ref(false);
const page = ref(1);
const perPage = ref(25);

const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const loadBar = (load) => `${Math.min(100, (load / 20) * 100)}%`;
const rateClass = (rate) => {
  if (rate >= 90) return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100';
  if (rate >= 75) return 'bg-amber-50 text-amber-700 ring-1 ring-amber-100';
  return 'bg-red-50 text-red-600 ring-1 ring-red-100';
};

// One row per teacher: workload joined with their lesson attendance for the term.
const merged = computed(() => {
  const byTeacher = new Map(attendance.value.map((a) => [String(a.teacher_id), a]));
  return workload.value.map((w) => {
    const a = byTeacher.get(String(w.teacher_id));
    const recorded = parseInt(a?.lessons_recorded, 10) || 0;
    const attended = parseInt(a?.lessons_attended, 10) || 0;
    return { ...w, recorded, attended, rate: recorded ? Math.round((attended / recorded) * 100) : null };
  });
});

const rows = computed(() => {
  const q = search.value.trim().toLowerCase();
  let list = merged.value.filter((w) => (!onlyActive.value || Number(w.is_active))
    && (!q || `${w.full_name} ${w.teacher_code}`.toLowerCase().includes(q)));
  if (sortBy.value === 'load') list = [...list].sort((a, b) => (Number(b.weekly_load_estimate) || 0) - (Number(a.weekly_load_estimate) || 0));
  if (sortBy.value === 'attendance') list = [...list].sort((a, b) => (a.rate ?? 101) - (b.rate ?? 101));
  return list;
});
const pageRows = computed(() => rows.value.slice((page.value - 1) * perPage.value, page.value * perPage.value));
watch([search, sortBy, onlyActive, perPage], () => { page.value = 1; });

const summaryCards = computed(() => {
  const loads = workload.value.map((w) => Number(w.weekly_load_estimate) || 0);
  const avgLoad = loads.length ? Math.round(loads.reduce((x, y) => x + y, 0) / loads.length) : 0;
  const recorded = merged.value.reduce((n, w) => n + w.recorded, 0);
  const attended = merged.value.reduce((n, w) => n + w.attended, 0);
  return [
    { label: 'Teachers', value: String(summary.value.total_teachers || 0), note: `${summary.value.active_teachers || 0} active`, icon: UsersIcon },
    { label: 'Average load', value: String(avgLoad), note: 'lessons a week', icon: Gauge, tone: 'sky' },
    { label: 'Lessons recorded', value: recorded.toLocaleString(), note: `Term ${term.value}`, icon: BookOpen, tone: 'gray' },
    { label: 'Attendance', value: recorded ? Math.round((attended / recorded) * 100) + '%' : '-', note: `${attended.toLocaleString()} attended`, icon: CalendarCheck, tone: 'amber' },
  ];
});

const load = async () => {
  loading.value = true;
  try {
    const res = await teachingAnalyticsAPI.get(year.value, term.value);
    if (res.success) {
      workload.value = res.data.workload || [];
      attendance.value = res.data.attendance || [];
      summary.value = res.data.summary || {};
    }
  } finally {
    loading.value = false;
  }
};

const exportReport = () => {
  exportToPdf(
    `Teaching Analytics ${year.value} T${term.value}`,
    [
      { key: 'teacher_code', label: 'Code' },
      { key: 'full_name', label: 'Name' },
      { key: 'subjects_count', label: 'Subjects' },
      { key: 'classes_count', label: 'Classes' },
      { key: 'weekly_load_estimate', label: 'Load' },
      { key: 'rate', label: 'Attendance %' }
    ],
    rows.value,
    'teaching-analytics'
  );
};

onMounted(load);
</script>

<style scoped>
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.filter-select { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .filter-label {
  color: var(--d-muted);
}
html.dark .filter-select {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
}
html.dark .tool-btn {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .tool-btn:hover {
  background-color: var(--d-sunken);
}
</style>
