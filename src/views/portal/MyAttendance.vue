<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="page-hero-title">Lesson Attendance</h1>
        <select v-model="termKey" class="input w-auto py-2" aria-label="Term">
          <option v-for="t in terms" :key="`${t.year}-${t.term}`" :value="`${t.year}-${t.term}`">{{ t.year }} Term {{ t.term }}</option>
        </select>
      </div>
    </div>

    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <StatCards :cards="stats" />

    <ListToolbar v-model:search="search" placeholder="Search subject or class">
      <template #actions>
        <label class="flex items-center gap-2 whitespace-nowrap px-2 text-sm text-gray-600">
          <input v-model="lostOnly" type="checkbox" class="h-4 w-4 rounded border-gray-300 accent-brand-600"> Only time lost
        </label>
      </template>
    </ListToolbar>

    <div v-if="loading" class="space-y-2">
      <div v-for="i in 3" :key="i" class="card h-16 animate-pulse"></div>
    </div>

    <template v-else>
      <!-- My classes by minutes lost (as Top Classes on Lesson Monitoring) -->
      <div v-if="byClass.length" class="card overflow-hidden">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left transition hover:bg-slate-50"
          :aria-expanded="!chartFolded"
          @click="chartFolded = !chartFolded"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600"><BarChart3 class="h-4 w-4" /></div>
            <div>
              <h3 class="text-base font-extrabold text-slate-900">My Classes by Minutes Lost</h3>
              <p class="text-xs text-slate-500">{{ byClass.length }} {{ byClass.length === 1 ? 'class' : 'classes' }} · {{ formatMinutes(totals.lost) }} lost in total</p>
            </div>
          </div>
          <ChevronDown class="h-5 w-5 flex-shrink-0 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': !chartFolded }" />
        </button>
        <ol v-if="!chartFolded" class="gap-x-10 border-t border-slate-100 px-5 py-2 lg:columns-2">
          <li
            v-for="(item, index) in byClass"
            :key="item.key"
            class="grid break-inside-avoid grid-cols-[1.25rem_minmax(0,1fr)_auto] items-center gap-x-3 py-2.5"
            :title="`${item.lessons} lessons · about ${Math.floor(item.lost / 40)} single lessons lost`"
          >
            <span class="text-xs font-bold tabular-nums" :class="index < 3 && item.lost ? 'text-rose-600' : 'text-slate-400'">{{ index + 1 }}</span>
            <div class="min-w-0">
              <span class="truncate text-sm font-semibold text-slate-900">
                {{ item.class }}<span v-if="item.stream" class="font-medium text-slate-500">&nbsp;{{ item.stream }}</span>
                <span class="ml-2 text-[11px] font-normal text-slate-400">{{ item.subject }} · {{ item.lessons }} lessons</span>
              </span>
              <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="index < 3 ? 'bg-gradient-to-r from-rose-400 to-rose-600' : 'bg-gradient-to-r from-rose-200 to-rose-300'"
                  :style="{ width: `${maxLost ? (item.lost / maxLost) * 100 : 0}%` }"
                ></div>
              </div>
            </div>
            <div class="w-20 text-right">
              <div class="text-sm font-bold tabular-nums" :class="item.lost ? 'text-slate-900' : 'text-emerald-600'">{{ item.lost ? formatMinutes(item.lost) : '✓' }}</div>
              <div class="text-[10px] text-slate-400">≈ {{ plural(Math.floor(item.lost / 40), 'lesson', 'lessons') }}</div>
            </div>
          </li>
        </ol>
      </div>

      <!-- Each class: weeks, then the lessons in the chosen week -->
      <div v-if="classes.length" class="card overflow-hidden">
        <div class="divide-y divide-slate-100">
          <div v-for="sc in classes" :key="sc.key">
            <button
              type="button"
              class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50 sm:px-5"
              :class="{ 'bg-slate-50': isOpen(sc) }"
              :aria-expanded="isOpen(sc)"
              @click="toggle(sc)"
            >
              <ChevronRight class="h-4 w-4 flex-shrink-0 text-slate-400 transition-transform duration-200" :class="{ 'rotate-90': isOpen(sc) }" />
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span class="text-base font-extrabold text-slate-900">{{ sc.class || '-' }}<span v-if="sc.stream" class="text-slate-500"> · {{ sc.stream }}</span></span>
                  <span class="rounded-md bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">{{ sc.subject || '-' }}</span>
                </div>
                <div class="mt-0.5 text-xs text-slate-500">{{ plural(sc.lessons, 'lesson', 'lessons') }} · {{ plural(sc.weeks.length, 'week', 'weeks') }}</div>
              </div>
              <div class="flex-shrink-0 text-right">
                <div class="text-sm font-extrabold" :class="sc.lost > 0 ? 'text-rose-600' : 'text-emerald-600'">{{ sc.lost > 0 ? formatMinutes(sc.lost) : 'No time lost' }}</div>
                <div v-if="sc.lost > 0" class="text-[11px] text-slate-500">lost</div>
              </div>
            </button>

            <div v-if="isOpen(sc)" class="border-t border-slate-100 bg-slate-50/70 px-3 py-4 sm:px-5">
              <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                <span><span class="font-extrabold text-slate-900">{{ entriesStats(sc.weeks.flatMap((w) => w.entries)).taughtPct }}%</span> of lesson time taught</span>
                <span v-if="entriesStats(sc.weeks.flatMap((w) => w.entries)).pending > 0">
                  <span class="font-extrabold text-amber-600">{{ entriesStats(sc.weeks.flatMap((w) => w.entries)).pending }}</span> to compensate
                </span>
                <span v-else class="font-semibold text-emerald-600">All settled</span>
              </div>

              <!-- Week picker: colour shows how the week went, number is minutes lost -->
              <div class="mb-3 flex gap-1.5 overflow-x-auto pb-1" role="tablist" aria-label="Weeks">
                <button
                  v-for="week in sc.weeks"
                  :key="week.week_number"
                  type="button"
                  role="tab"
                  :aria-selected="String(selectedWeek(sc)) === String(week.week_number)"
                  class="flex w-14 flex-shrink-0 flex-col items-center rounded-lg border px-1 py-1 transition"
                  :class="String(selectedWeek(sc)) === String(week.week_number) ? 'border-slate-900 bg-slate-900 text-white shadow-sm' : weekTone(week).chip"
                  :title="`Week ${week.week_number} · ${weekTone(week).label}`"
                  @click="selectWeek(sc, week.week_number)"
                >
                  <span class="text-[10px] font-semibold uppercase opacity-80">Wk {{ week.week_number }}</span>
                  <span class="text-xs font-extrabold">{{ entriesStats(week.entries).lost > 0 ? entriesStats(week.entries).lost : '✓' }}</span>
                </button>
              </div>

              <section v-if="weekData(sc)" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2 sm:px-4">
                  <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="text-sm font-extrabold text-slate-900">Week {{ weekData(sc).week_number }}</span>
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="weekTone(weekData(sc)).badge">{{ weekTone(weekData(sc)).label }}</span>
                    <span class="text-xs text-slate-500">{{ plural(weekData(sc).entries.length, 'lesson', 'lessons') }}</span>
                  </div>
                  <div class="flex flex-shrink-0 gap-1">
                    <button type="button" class="step-btn" :disabled="!canStep(sc, -1)" aria-label="Previous week" @click="step(sc, -1)">‹</button>
                    <button type="button" class="step-btn" :disabled="!canStep(sc, 1)" aria-label="Next week" @click="step(sc, 1)">›</button>
                  </div>
                </header>
                <ul class="divide-y divide-slate-100">
                  <li v-for="entry in weekData(sc).entries" :key="entry.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-3 transition hover:bg-slate-50 sm:px-4">
                    <div class="w-14 flex-shrink-0 rounded-lg border border-slate-200 bg-white py-1 text-center">
                      <div class="text-[10px] font-bold uppercase text-brand-600">{{ (entry.day_of_week || '-').slice(0, 3) }}</div>
                      <div class="text-lg font-extrabold leading-none text-slate-900">{{ dateParts(entry.attendance_date).day }}</div>
                      <div class="text-[10px] font-semibold uppercase text-slate-500">{{ dateParts(entry.attendance_date).month }}</div>
                    </div>
                    <div class="min-w-0 flex-1 sm:min-w-[11rem]">
                      <div class="text-sm font-bold text-slate-800">{{ shortTime(entry.time_in) || '-' }} – {{ shortTime(entry.time_out) || '-' }}</div>
                      <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <div class="h-1.5 min-w-[6rem] max-w-[14rem] flex-1 overflow-hidden rounded-full bg-slate-100">
                          <div class="h-full rounded-full" :class="taughtBarClass(entry)" :style="{ width: `${taughtPct(entry)}%` }"></div>
                        </div>
                        <span class="whitespace-nowrap text-[11px] text-slate-500">{{ entry.actual_minutes }} of {{ entry.expected_minutes }} min taught</span>
                      </div>
                    </div>
                    <div class="flex w-full flex-wrap items-center gap-2 border-t border-dashed border-slate-100 pt-2 sm:w-auto sm:flex-nowrap sm:gap-3 sm:border-0 sm:pt-0">
                      <div class="whitespace-nowrap text-sm sm:w-24 sm:text-right">
                        <template v-if="entry.minutes_lost > 0">
                          <span class="font-extrabold text-rose-600">{{ entry.minutes_lost }} min</span><span class="text-xs text-slate-500"> lost</span>
                        </template>
                      </div>
                      <span class="inline-flex justify-center whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 sm:w-36" :class="entryStatus(entry).cls">{{ entryStatus(entry).label }}</span>
                    </div>
                  </li>
                </ul>
              </section>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!classes.length" class="card px-5 py-14 text-center">
        <CalendarCheck class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No lessons recorded {{ lostOnly ? 'with time lost ' : '' }}for this term</p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { AlarmClock, BarChart3, CalendarCheck, ChevronDown, ChevronRight, Hourglass, ListChecks, RotateCcw } from 'lucide-vue-next';
import StatCards from '../../components/ui/StatCards.vue';
import ListToolbar from '../../components/ui/ListToolbar.vue';
import { teacherPortalAPI } from '../../services/api.js';
import { apiError, shortTime } from '../../utils/timetable.js';

const data = ref(null);
const terms = ref([]);
const termKey = ref('');
const loading = ref(true);
const error = ref('');
const lostOnly = ref(false);
const search = ref('');
const chartFolded = ref(false);

const formatMinutes = (mins) => `${Number(mins || 0).toLocaleString()} min`;
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

const records = computed(() => {
  const q = search.value.trim().toLowerCase();
  return (data.value?.records || []).filter((r) => (!lostOnly.value || r.minutes_lost > 0)
    && (!q || `${r.subject} ${r.class} ${r.stream}`.toLowerCase().includes(q)));
});
const totals = computed(() => {
  const s = data.value?.summary || {};
  return { lost: Number(s.lost || 0), compensated: Number(s.compensated || 0), compensatedLessons: Number(s.compensated_lessons || 0), lessons: Number(s.lessons || 0) };
});
const stats = computed(() => [
  { label: 'Time lost', value: formatMinutes(totals.value.lost), note: `≈ ${Math.floor(totals.value.lost / 40)} single · ${Math.floor(totals.value.lost / 80)} double lessons`, icon: Hourglass, tone: 'red' },
  { label: 'Compensated', value: formatMinutes(totals.value.compensated), note: plural(totals.value.compensatedLessons, 'lesson', 'lessons'), icon: RotateCcw, tone: 'sky' },
  { label: 'Still owed', value: formatMinutes(Math.max(0, totals.value.lost - totals.value.compensated)), icon: AlarmClock, tone: 'amber' },
  { label: 'Records', value: String(totals.value.lessons), note: 'lessons monitored', icon: ListChecks },
]);

// Subject + class, then weeks, then lessons (oldest first), as on Lesson Monitoring.
const classes = computed(() => {
  const map = new Map();
  for (const r of records.value) {
    const key = `${r.subject}|${r.class}|${r.stream}`;
    if (!map.has(key)) map.set(key, { key, subject: r.subject, class: r.class, stream: r.stream, lessons: 0, lost: 0, weeks: new Map() });
    const sc = map.get(key);
    sc.lessons += 1;
    sc.lost += r.minutes_lost;
    if (!sc.weeks.has(r.week_number)) sc.weeks.set(r.week_number, { week_number: r.week_number, entries: [] });
    sc.weeks.get(r.week_number).entries.push(r);
  }
  return [...map.values()].map((sc) => ({
    ...sc,
    weeks: [...sc.weeks.values()]
      .map((w) => ({ ...w, entries: w.entries.sort((a, b) => `${a.attendance_date} ${a.time_in}`.localeCompare(`${b.attendance_date} ${b.time_in}`)) }))
      .sort((a, b) => Number(a.week_number) - Number(b.week_number)),
  })).sort((a, b) => b.lost - a.lost || `${a.class}${a.stream}`.localeCompare(`${b.class}${b.stream}`));
});
const byClass = computed(() => classes.value.map((c) => ({ key: c.key, class: c.class, stream: c.stream, subject: c.subject, lessons: c.lessons, lost: c.lost })));
const maxLost = computed(() => byClass.value.reduce((m, c) => Math.max(m, c.lost), 0));

const open = ref(new Set());
const isOpen = (sc) => open.value.has(sc.key) || classes.value.length === 1;
const toggle = (sc) => {
  const next = new Set(open.value);
  next.has(sc.key) ? next.delete(sc.key) : next.add(sc.key);
  open.value = next;
};

const entriesStats = (entries) => {
  const expected = entries.reduce((s, e) => s + e.expected_minutes, 0);
  const actual = entries.reduce((s, e) => s + e.actual_minutes, 0);
  return {
    lost: entries.reduce((s, e) => s + e.minutes_lost, 0),
    pending: entries.filter((e) => e.minutes_lost > 0 && e.compensation_status !== 'Fully Compensated').length,
    taughtPct: expected ? Math.min(100, Math.round((actual / expected) * 100)) : 0,
  };
};
const weekTone = (week) => {
  const s = entriesStats(week.entries);
  if (s.lost === 0) return { chip: 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', label: 'Complete' };
  if (s.pending === 0) return { chip: 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100', badge: 'bg-amber-50 text-amber-700 ring-amber-200', label: 'Compensated' };
  return { chip: 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100', badge: 'bg-rose-50 text-rose-700 ring-rose-200', label: `${s.pending} to compensate` };
};

// Open the first week still owing time, otherwise the latest week.
const chosenWeeks = ref({});
const selectedWeek = (sc) => chosenWeeks.value[sc.key] ?? (sc.weeks.find((w) => entriesStats(w.entries).pending > 0) || sc.weeks[sc.weeks.length - 1])?.week_number;
const selectWeek = (sc, wk) => { chosenWeeks.value = { ...chosenWeeks.value, [sc.key]: wk }; };
const weekData = (sc) => sc.weeks.find((w) => String(w.week_number) === String(selectedWeek(sc))) || null;
const weekIndex = (sc) => sc.weeks.findIndex((w) => String(w.week_number) === String(selectedWeek(sc)));
const canStep = (sc, dir) => { const i = weekIndex(sc) + dir; return i >= 0 && i < sc.weeks.length; };
const step = (sc, dir) => { if (canStep(sc, dir)) selectWeek(sc, sc.weeks[weekIndex(sc) + dir].week_number); };

const taughtPct = (e) => (e.expected_minutes ? Math.min(100, Math.round((e.actual_minutes / e.expected_minutes) * 100)) : 0);
const taughtBarClass = (e) => (taughtPct(e) >= 100 ? 'bg-emerald-500' : taughtPct(e) >= 75 ? 'bg-amber-400' : 'bg-rose-500');
const entryStatus = (e) => {
  if (e.minutes_lost === 0) return { label: 'Complete', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200' };
  if (e.compensation_status === 'Fully Compensated') return { label: 'Fully Compensated', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200' };
  if (e.compensation_status === 'Partially Compensated') return { label: 'Partially Compensated', cls: 'bg-amber-50 text-amber-700 ring-amber-200' };
  return { label: e.compensation_status || 'Not Compensated', cls: 'bg-rose-50 text-rose-700 ring-rose-200' };
};
// "2026-02-03" -> { day: '03', month: 'Feb' }, read as a local date so the day does not shift.
const dateParts = (date) => {
  if (!date) return { day: '-', month: '' };
  const [y, m, d] = String(date).slice(0, 10).split('-').map(Number);
  const dt = new Date(y, (m || 1) - 1, d || 1);
  return Number.isNaN(dt.getTime()) ? { day: '-', month: '' } : { day: String(dt.getDate()).padStart(2, '0'), month: dt.toLocaleString('en-GB', { month: 'short' }) };
};

async function load(params = {}) {
  loading.value = true;
  try {
    data.value = (await teacherPortalAPI.view('attendance', params)).data;
    const current = { year: data.value.year, term: data.value.term };
    terms.value = data.value.terms.some((t) => Number(t.year) === current.year && Number(t.term) === current.term)
      ? data.value.terms
      : [current, ...data.value.terms];
    termKey.value = `${current.year}-${current.term}`;
    open.value = new Set();
    chosenWeeks.value = {};
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
}

watch(termKey, (key, old) => {
  if (!old || !key || key === `${data.value?.year}-${data.value?.term}`) return;
  const [year, term] = key.split('-');
  load({ year, term });
});
onMounted(() => load());
</script>

<style scoped>
.step-btn { @apply flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-30; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .step-btn {
  border-color: var(--d-line);
  color: var(--d-text-3);
}
html.dark .step-btn:hover {
  background-color: var(--d-sunken);
}
</style>
