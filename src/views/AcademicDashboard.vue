<template>
  <div class="page">
    <!-- Welcome: where the term is, and the things the academic office does most -->
    <section class="dash-hero card relative overflow-hidden p-5 sm:p-6">
      <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
          <p class="text-sm font-medium text-brand-700">{{ today }} · Academic office</p>
          <h1 class="mt-0.5 text-2xl font-bold tracking-tight text-gray-900 sm:text-[1.7rem]">{{ greeting }}</h1>
          <div v-if="term" class="mt-3 flex max-w-md items-center gap-3">
            <span class="whitespace-nowrap text-sm text-gray-600">
              {{ term.year }} · Term {{ term.term }}<template v-if="term.week && term.weeks"> · week {{ term.week }} of {{ term.weeks }}</template>
            </span>
            <span v-if="term.weeks" class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200/80" :title="`${termPct}% of the term`">
              <span class="block h-full rounded-full bg-brand-500" :style="{ width: `${termPct}%` }"></span>
            </span>
          </div>
          <p v-else-if="!loading" class="mt-2 text-sm text-gray-500">No term is open.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink v-for="a in quickActions" :key="a.to" :to="a.to" class="quick-action">
            <component :is="a.icon" class="h-4 w-4" />{{ a.label }}
          </RouterLink>
        </div>
      </div>
    </section>

    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <div v-if="loading" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <div v-for="i in 5" :key="i" class="card h-20 animate-pulse"></div>
    </div>

    <template v-else>
      <StatCards :cards="kpiCards" />

      <div class="grid gap-4 xl:grid-cols-3">
        <!-- Lesson time lost, week by week -->
        <section class="card overflow-hidden xl:col-span-2">
          <header class="flex flex-wrap items-start justify-between gap-2 px-5 pt-4">
            <div>
              <h2 class="font-semibold text-gray-900">Lesson time lost by week</h2>
              <p class="text-xs text-gray-500">
                <template v-if="lessons">Term {{ term?.term }}: {{ fmt(lessons.lost) }} min lost of {{ fmt(lessons.expected) }} min planned</template>
                <template v-else>No lessons recorded this term</template>
              </p>
            </div>
            <RouterLink to="/lesson-monitoring" class="text-sm font-medium text-brand-700 hover:underline">Lesson Monitoring</RouterLink>
          </header>
          <div v-if="weekBars.length" class="relative px-3 pb-3 pt-2 sm:px-5" @mouseleave="hoverWeek = null">
            <svg :viewBox="`0 0 ${chart.w} ${chart.h}`" class="h-auto max-h-64 w-full" role="img" :aria-label="`Minutes of lesson time lost each week of term ${term?.term}`">
              <g v-for="t in chart.ticks" :key="t.v">
                <line :x1="chart.left" :x2="chart.w" :y1="t.y" :y2="t.y" class="grid-line" />
                <text :x="chart.left - 6" :y="t.y + 3" text-anchor="end" class="axis-text">{{ fmt(t.v) }}</text>
              </g>
              <g v-for="b in weekBars" :key="b.week">
                <rect :x="b.slotX" :y="0" :width="b.slotW" :height="chart.base" fill="transparent" @mouseenter="hoverWeek = b" />
                <path :d="b.path" :class="hoverWeek && hoverWeek.week !== b.week ? 'bar dim' : 'bar'" />
                <text :x="b.cx" :y="chart.base + 14" text-anchor="middle" class="axis-text">{{ b.week }}</text>
              </g>
              <text :x="chart.w" :y="chart.h - 1" text-anchor="end" class="axis-text">week</text>
            </svg>
            <div
              v-if="hoverWeek"
              class="pointer-events-none absolute top-2 z-10 -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-xs text-white shadow-lg"
              :style="{ left: `${(hoverWeek.cx / chart.w) * 100}%` }"
            >
              <p class="font-semibold">Week {{ hoverWeek.week }}</p>
              <p>{{ fmt(hoverWeek.lost) }} min lost · {{ hoverWeek.taught }}% taught</p>
              <p class="text-white/70">{{ fmt(hoverWeek.lessons) }} lessons</p>
            </div>
          </div>
          <div v-else class="px-5 pb-10 pt-6 text-center text-sm text-gray-500">Lesson records for this term will show here.</div>
        </section>

        <!-- Needs attention -->
        <section class="card overflow-hidden">
          <header class="px-5 pt-4">
            <h2 class="font-semibold text-gray-900">Needs attention</h2>
            <p class="text-xs text-gray-500">{{ attention.length ? 'Things waiting on the academic office' : 'All clear for now' }}</p>
          </header>
          <ul v-if="attention.length" class="mt-2 divide-y divide-gray-100">
            <li v-for="a in attention" :key="a.text">
              <RouterLink :to="a.to" class="flex items-center gap-3 px-5 py-3 transition hover:bg-gray-50">
                <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full" :class="DOT[a.tone] || DOT.sky"></span>
                <span class="min-w-0 flex-1 text-sm text-gray-800">{{ a.text }}</span>
                <ChevronRight class="h-4 w-4 flex-shrink-0 text-gray-400" />
              </RouterLink>
            </li>
          </ul>
          <div v-else class="flex flex-col items-center px-5 py-10 text-center">
            <CircleCheck class="h-8 w-8 text-brand-500" />
            <p class="mt-2 text-sm text-gray-600">Nothing urgent. Plans are reviewed and marks are coming in.</p>
          </div>
          <!-- Teachers losing the most time -->
          <div class="border-t border-gray-100 px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-900">Most time lost</h3>
            <ul v-if="data.teachers_lost?.length" class="mt-2 space-y-2">
              <li v-for="t in data.teachers_lost" :key="t.name" class="flex items-center gap-2.5 text-sm">
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-rose-50 text-[10px] font-semibold text-rose-700 ring-1 ring-rose-100">{{ initials(t.name) }}</span>
                <span class="min-w-0 flex-1 truncate text-gray-800">{{ t.name }}</span>
                <span class="whitespace-nowrap text-xs font-semibold tabular-nums text-rose-600">{{ fmt(t.lost) }} min</span>
              </li>
            </ul>
            <p v-else class="mt-1.5 text-xs text-gray-500">No lesson time lost this term.</p>
          </div>
        </section>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <!-- Marks entry per exam -->
        <section class="card overflow-hidden">
          <header class="flex items-center justify-between px-5 pt-4">
            <div>
              <h2 class="font-semibold text-gray-900">Marks entry</h2>
              <p class="text-xs text-gray-500">Marks in for each exam this term</p>
            </div>
            <RouterLink to="/examinations" class="text-sm font-medium text-brand-700 hover:underline">Exams</RouterLink>
          </header>
          <ul v-if="data.exams?.length" class="space-y-3 p-5">
            <li v-for="e in data.exams" :key="e.id">
              <div class="flex items-center justify-between gap-2 text-sm">
                <span class="min-w-0 truncate font-medium text-gray-900">{{ e.name }}
                  <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :class="e.status === 'open' ? 'bg-brand-50 text-brand-800' : 'bg-gray-100 text-gray-600'">{{ e.status === 'open' ? 'Open' : 'Closed' }}</span>
                </span>
                <span class="whitespace-nowrap text-xs tabular-nums text-gray-500">{{ fmt(e.entered) }} / {{ fmt(e.expected) }}</span>
              </div>
              <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-brand-500" :style="{ width: `${pct(e.entered, e.expected)}%` }"></div>
              </div>
            </li>
          </ul>
          <p v-else class="px-5 pb-8 pt-6 text-center text-sm text-gray-500">No exams are open or closed this term yet.</p>
        </section>

        <!-- Subject averages -->
        <section class="card overflow-hidden xl:col-span-2">
          <header class="flex items-center justify-between px-5 pt-4">
            <div>
              <h2 class="font-semibold text-gray-900">Subject averages</h2>
              <p class="text-xs text-gray-500">Average mark this term, from the marks entered so far</p>
            </div>
            <RouterLink to="/subject-performance" class="text-sm font-medium text-brand-700 hover:underline">Results</RouterLink>
          </header>
          <ul v-if="data.subjects?.length" class="grid gap-x-8 gap-y-2.5 p-5 sm:grid-cols-2">
            <li v-for="s in data.subjects.slice(0, 12)" :key="s.subject" class="grid grid-cols-[minmax(0,9rem)_1fr_2.75rem] items-center gap-3 text-sm" :title="`${s.subject}: ${s.average}% from ${s.marks} mark(s)`">
              <span class="truncate text-gray-700">{{ s.subject }}</span>
              <span class="h-1.5 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full" :class="s.average >= 50 ? 'bg-brand-500' : 'bg-amber-500'" :style="{ width: `${Math.min(100, s.average)}%` }"></span></span>
              <span class="text-right font-semibold tabular-nums text-gray-900">{{ s.average }}%</span>
            </li>
          </ul>
          <p v-else class="px-5 pb-8 pt-6 text-center text-sm text-gray-500">Averages show here once teachers enter marks.</p>
        </section>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <!-- Lesson plans waiting -->
        <section class="card overflow-hidden xl:col-span-2">
          <header class="flex items-center justify-between px-5 pt-4">
            <div>
              <h2 class="font-semibold text-gray-900">Lesson plans waiting for review</h2>
              <p class="text-xs text-gray-500">{{ data.plans ? `${fmt(data.plans.approved)} approved · ${fmt(data.plans.returned)} returned this term` : '' }}</p>
            </div>
            <RouterLink to="/lesson-plans" class="text-sm font-medium text-brand-700 hover:underline">Review</RouterLink>
          </header>
          <ul v-if="data.recent_plans?.length" class="divide-y divide-gray-100 px-2 pb-2 pt-1">
            <li v-for="p in data.recent_plans" :key="p.id">
              <RouterLink :to="`/lesson-plans?plan=${p.id}`" class="flex items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-gray-50">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-100"><NotebookPen class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-semibold text-gray-900">{{ p.topic }}</span>
                  <span class="block truncate text-xs text-gray-500">{{ p.teacher }} · {{ p.class_name }} · {{ p.subject_name }}</span>
                </span>
                <span v-if="Number(p.is_new)" class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white">New</span>
                <span class="whitespace-nowrap text-xs text-gray-400">{{ shortDate(p.submitted_at) }}</span>
              </RouterLink>
            </li>
          </ul>
          <p v-else class="px-5 pb-8 pt-6 text-center text-sm text-gray-500">No lesson plans are waiting.</p>
        </section>

        <!-- Teaching coverage and learners -->
        <section class="card overflow-hidden">
          <header class="px-5 pt-4">
            <h2 class="font-semibold text-gray-900">Teaching coverage</h2>
            <p class="text-xs text-gray-500">This term's teaching assignments</p>
          </header>
          <div v-if="data.coverage" class="space-y-3 px-5 pt-3">
            <div v-for="c in coverageRows" :key="c.label">
              <div class="flex items-center justify-between text-sm"><span class="text-gray-700">{{ c.label }}</span><span class="font-semibold tabular-nums text-gray-900">{{ fmt(c.value) }} / {{ fmt(c.total) }}</span></div>
              <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-sky-500" :style="{ width: `${pct(c.value, c.total)}%` }"></div></div>
            </div>
            <p class="text-xs text-gray-500">{{ fmt(data.coverage.slots) }} class and subject pairs assigned</p>
          </div>
          <div class="mt-4 border-t border-gray-100 px-5 py-3">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-900">Learners</h3>
              <RouterLink to="/digital-register" class="text-xs font-medium text-brand-700 hover:underline">Student E-files</RouterLink>
            </div>
            <dl v-if="data.students" class="mt-2 grid grid-cols-3 gap-2 text-center">
              <div class="rounded-lg bg-gray-50 py-2"><dt class="text-[11px] text-gray-500">O-Level</dt><dd class="font-bold tabular-nums text-gray-900">{{ fmt(data.students.o_level) }}</dd></div>
              <div class="rounded-lg bg-gray-50 py-2"><dt class="text-[11px] text-gray-500">A-Level</dt><dd class="font-bold tabular-nums text-gray-900">{{ fmt(data.students.a_level) }}</dd></div>
              <div class="rounded-lg bg-gray-50 py-2"><dt class="text-[11px] text-gray-500">Left</dt><dd class="font-bold tabular-nums text-gray-900">{{ fmt(data.students.left_school) }}</dd></div>
            </dl>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  BookUser, CalendarDays, ChevronRight, CircleCheck, ClipboardList, Clock, FileBadge, GraduationCap, NotebookPen, PenLine,
} from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import authStore from '../services/authStore.js';
import { academicDashboardAPI } from '../services/api.js';
import { apiError } from '../utils/timetable.js';

const router = useRouter();
const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const greeting = computed(() => {
  const h = new Date().getHours();
  const part = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  const first = (authStore.user?.full_name || '').trim().split(/\s+/)[0];
  return first ? `${part}, ${first}` : part;
});

const loading = ref(true);
const error = ref('');
const data = ref({});
const fmt = (n) => Number(n || 0).toLocaleString();
const pct = (a, b) => (b ? Math.min(100, Math.round((a / b) * 100)) : 0);
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const shortDate = (when) => {
  const d = new Date(String(when || '').replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
};
const DOT = { red: 'bg-rose-500', amber: 'bg-amber-500', sky: 'bg-sky-500' };

const term = computed(() => data.value.term);
const termPct = computed(() => (term.value?.weeks ? Math.min(100, Math.round(((term.value.week || 0) / term.value.weeks) * 100)) : 0));
const lessons = computed(() => (data.value.lessons?.lessons ? data.value.lessons : null));
// Share of planned lesson time not lost (recorded taught minutes can run over the plan, so they are not used).
const keptPct = (lost, expected) => (expected ? Math.max(0, Math.min(100, Math.round(100 - (lost / expected) * 100))) : 0);
const taughtPct = computed(() => keptPct(lessons.value?.lost || 0, lessons.value?.expected || 0));
const attention = computed(() => data.value.attention || []);
const openExams = computed(() => (data.value.exams || []).filter((e) => e.status === 'open'));
const marksPct = computed(() => {
  const list = openExams.value;
  const expected = list.reduce((t, e) => t + e.expected, 0);
  return expected ? pct(list.reduce((t, e) => t + e.entered, 0), expected) : null;
});
const coverageRows = computed(() => {
  const c = data.value.coverage;
  return c ? [
    { label: 'Class streams with teachers', value: c.classes, total: c.all_classes },
    { label: 'Teachers with classes', value: c.teachers, total: c.all_teachers },
  ] : [];
});

const quickActions = [
  { to: '/timetable', label: 'Timetable', icon: CalendarDays },
  { to: '/teaching-assignments', label: 'Assignments', icon: BookUser },
  { to: '/examinations', label: 'Exams', icon: ClipboardList },
  { to: '/report-cards', label: 'Report cards', icon: FileBadge },
];

const kpiCards = computed(() => {
  const st = data.value.students;
  const plans = data.value.plans;
  const cov = data.value.coverage;
  return [
    { label: 'In school', value: st ? fmt(st.in_school) : '-', note: st ? `${fmt(st.o_level)} O-Level · ${fmt(st.a_level)} A-Level` : '', icon: GraduationCap, tone: 'sky', onClick: () => router.push('/digital-register') },
    { label: 'Lesson time taught', value: lessons.value ? `${taughtPct.value}%` : '-', note: lessons.value ? `${fmt(lessons.value.lessons)} lessons this term` : 'No lessons recorded', icon: Clock, tone: 'amber', onClick: () => router.push('/lesson-monitoring') },
    { label: 'Plans to review', value: plans ? fmt(plans.waiting) : '-', note: plans?.new ? `${plans.new} new` : 'none new', icon: NotebookPen, tone: plans?.new ? 'red' : 'gray', onClick: () => router.push('/lesson-plans') },
    { label: 'Marks entered', value: marksPct.value === null ? '-' : `${marksPct.value}%`, note: openExams.value.length ? `${openExams.value.length} exam(s) open` : 'No exam open', icon: PenLine, onClick: () => router.push('/examinations') },
    { label: 'Classes covered', value: cov ? `${fmt(cov.classes)}/${fmt(cov.all_classes)}` : '-', note: cov ? `${fmt(cov.teachers)} teachers teaching` : '', icon: BookUser, tone: 'gray', onClick: () => router.push('/teaching-assignments') },
  ];
});

// Weekly bar chart (one series: minutes lost). Thin bars with rounded tops, recessive grid, hover tooltip.
const chart = { w: 640, h: 210, left: 40, base: 186 };
const hoverWeek = ref(null);
const weekBars = computed(() => {
  const weeks = lessons.value?.by_week || [];
  if (!weeks.length) return [];
  const max = Math.max(...weeks.map((w) => w.lost), 1);
  const step = max > 1000 ? 500 : max > 400 ? 200 : max > 100 ? 50 : 20;
  const top = Math.ceil(max / step) * step;
  chart.ticks = Array.from({ length: Math.floor(top / step) + 1 }, (_, i) => ({ v: i * step, y: chart.base - ((i * step) / top) * (chart.base - 10) }));
  const slotW = (chart.w - chart.left) / weeks.length;
  const barW = Math.min(28, slotW * 0.55);
  return weeks.map((w, i) => {
    const h = (w.lost / top) * (chart.base - 10);
    const x = chart.left + i * slotW + (slotW - barW) / 2;
    const y = chart.base - h;
    const r = Math.min(4, h, barW / 2);
    const path = h <= 0 ? '' : `M${x},${chart.base} V${y + r} Q${x},${y} ${x + r},${y} H${x + barW - r} Q${x + barW},${y} ${x + barW},${y + r} V${chart.base} Z`;
    return { ...w, path, cx: x + barW / 2, slotX: chart.left + i * slotW, slotW, taught: keptPct(w.lost, w.expected) };
  });
});

onMounted(async () => {
  try {
    data.value = (await academicDashboardAPI.get()).data || {};
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.dash-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(600px 220px at 100% 0%, rgba(16, 185, 129, 0.16), transparent 70%),
    radial-gradient(500px 200px at 0% 100%, rgba(37, 99, 235, 0.07), transparent 70%);
  pointer-events: none;
}
.quick-action {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.8rem;
  border-radius: 0.6rem;
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid rgb(229 231 235);
  font-size: 0.8125rem;
  font-weight: 500;
  color: rgb(31 41 55);
  transition: border-color 0.15s, color 0.15s, transform 0.15s;
}
.quick-action:hover { border-color: rgb(52 211 153); color: rgb(4 120 87); transform: translateY(-1px); }
.grid-line { stroke: rgb(229 231 235); stroke-width: 1; }
.axis-text { fill: rgb(107 114 128); font-size: 10px; }
.bar { fill: #e11d48; transition: opacity 0.15s; pointer-events: none; }
.bar.dim { opacity: 0.35; }
html.dark .quick-action { background: rgba(255, 255, 255, 0.04); border-color: #31413a; color: var(--d-text-2); }
html.dark .quick-action:hover { border-color: #10b981; color: #6ee7b7; }
html.dark .grid-line { stroke: #26332d; }
html.dark .axis-text { fill: #8a9a92; }
html.dark .bar { fill: #f43f5e; }
</style>
