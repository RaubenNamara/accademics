<template>
  <div class="page">
    <!-- Welcome: where the term is, and the things people do most -->
    <section class="dash-hero card relative overflow-hidden p-5 sm:p-6">
      <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
          <p class="text-sm font-medium text-brand-700">{{ today }}</p>
          <h1 class="mt-0.5 text-2xl font-bold tracking-tight text-gray-900 sm:text-[1.7rem]">{{ greeting }}</h1>
          <div v-if="term" class="mt-3 flex max-w-md items-center gap-3">
            <span class="whitespace-nowrap text-sm text-gray-600">
              {{ term.year }} · Term {{ term.term }}<template v-if="term.week && term.weeks"> · week {{ term.week }} of {{ term.weeks }}</template>
            </span>
            <span v-if="term.weeks" class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200/80" :title="`${termPct}% of the term`">
              <span class="block h-full rounded-full bg-brand-500" :style="{ width: `${termPct}%` }"></span>
            </span>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink v-for="a in quickActions" :key="a.to" :to="a.to" class="quick-action">
            <component :is="a.icon" class="h-4 w-4" />{{ a.label }}
          </RouterLink>
        </div>
      </div>
    </section>

    <div v-if="loading" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 4" :key="i" class="card h-20 animate-pulse"></div>
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
            <RouterLink v-if="can('/lesson-monitoring')" to="/lesson-monitoring" class="text-sm font-medium text-brand-700 hover:underline">Lesson Monitoring</RouterLink>
          </header>
          <div v-if="weekBars.length" class="relative px-3 pb-3 pt-2 sm:px-5" @mouseleave="hoverWeek = null">
            <svg :viewBox="`0 0 ${chart.w} ${chart.h}`" class="h-auto max-h-64 w-full" role="img" :aria-label="`Minutes of lesson time lost each week of term ${term?.term}`">
              <!-- recessive grid with values -->
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
          <dl v-if="lessons" class="grid grid-cols-3 border-t border-gray-100 text-sm">
            <div class="px-5 py-3"><dt class="text-xs text-gray-500">Time taught</dt><dd class="font-semibold tabular-nums text-gray-900">{{ taughtPct }}%</dd></div>
            <div class="border-l border-gray-100 px-5 py-3"><dt class="text-xs text-gray-500">Made up</dt><dd class="font-semibold tabular-nums text-gray-900">{{ fmt(lessons.compensated) }} min</dd></div>
            <div class="border-l border-gray-100 px-5 py-3"><dt class="text-xs text-gray-500">Still owed</dt><dd class="font-semibold tabular-nums text-rose-600">{{ fmt(Math.max(0, lessons.lost - lessons.compensated)) }} min</dd></div>
          </dl>
        </section>

        <!-- Needs attention -->
        <section class="card overflow-hidden">
          <header class="px-5 pt-4">
            <h2 class="font-semibold text-gray-900">Needs attention</h2>
            <p class="text-xs text-gray-500">{{ attention.filter((a) => a.count).length ? 'Things waiting on someone' : 'All clear for now' }}</p>
          </header>
          <ul class="mt-2 divide-y divide-gray-100">
            <li v-for="a in attention" :key="a.label">
              <component :is="a.to && can(a.to) ? 'RouterLink' : 'div'" :to="a.to" class="flex items-center gap-3 px-5 py-3 transition" :class="a.to && can(a.to) ? 'hover:bg-gray-50' : ''">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg ring-1" :class="a.count ? a.tone : 'bg-gray-50 text-gray-400 ring-gray-100'"><component :is="a.icon" class="h-4 w-4" /></span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-medium text-gray-900">{{ a.label }}</span>
                  <span class="block truncate text-xs text-gray-500">{{ a.note }}</span>
                </span>
                <span class="min-w-[1.75rem] rounded-full px-2 py-0.5 text-center text-xs font-bold tabular-nums" :class="a.count ? a.badge : 'bg-gray-100 text-gray-400'">{{ a.count }}</span>
              </component>
            </li>
          </ul>
        </section>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <!-- Departments -->
        <section class="card overflow-hidden xl:col-span-2">
          <header class="flex items-center justify-between px-5 pt-4">
            <div>
              <h2 class="font-semibold text-gray-900">Departments</h2>
              <p class="text-xs text-gray-500">Active staff in each department</p>
            </div>
            <RouterLink v-if="can('/departments')" to="/departments" class="text-sm font-medium text-brand-700 hover:underline">Manage</RouterLink>
          </header>
          <div v-if="!stats.departments?.length" class="px-5 py-10 text-center text-sm text-gray-500">No department data</div>
          <ul v-else class="grid gap-x-8 gap-y-2.5 p-5 sm:grid-cols-2">
            <li v-for="d in stats.departments" :key="d.name" class="grid grid-cols-[minmax(0,8.5rem)_1fr_2rem] items-center gap-3 text-sm" :title="`${d.name}: ${d.staff_count} staff`">
              <span class="truncate text-gray-700">{{ d.name }}</span>
              <span class="h-1.5 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full bg-brand-500" :style="{ width: deptBarWidth(d.staff_count) }"></span></span>
              <span class="text-right font-semibold tabular-nums text-gray-900">{{ d.staff_count }}</span>
            </li>
          </ul>
        </section>

        <!-- Staff mix and who is away -->
        <section class="card overflow-hidden">
          <header class="px-5 pt-4">
            <h2 class="font-semibold text-gray-900">Staff mix</h2>
            <p class="text-xs text-gray-500">{{ fmt(stats.active_staff || 0) }} of {{ fmt(stats.total_staff || 0) }} staff active</p>
          </header>
          <div class="px-5 pt-3">
            <div class="flex h-3 gap-0.5 overflow-hidden rounded-full" role="img" :aria-label="`${stats.teaching_staff} teaching and ${stats.non_teaching_staff} non-teaching staff`">
              <span class="mix-teaching rounded-l-full" :style="{ width: `${mix.teaching}%` }"></span>
              <span class="mix-support rounded-r-full" :style="{ width: `${mix.support}%` }"></span>
            </div>
            <div class="mt-2 flex justify-between text-xs">
              <span class="flex items-center gap-1.5 text-gray-700"><span class="mix-teaching h-2.5 w-2.5 rounded-sm"></span>Teaching <strong class="tabular-nums text-gray-900">{{ stats.teaching_staff || 0 }}</strong> ({{ mix.teaching }}%)</span>
              <span class="flex items-center gap-1.5 text-gray-700"><span class="mix-support h-2.5 w-2.5 rounded-sm"></span>Non-teaching <strong class="tabular-nums text-gray-900">{{ stats.non_teaching_staff || 0 }}</strong></span>
            </div>
          </div>
          <div class="mt-4 border-t border-gray-100 px-5 py-3">
            <div class="flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-900">On leave today</h3>
              <RouterLink v-if="can('/leave')" to="/leave" class="text-xs font-medium text-brand-700 hover:underline">Leave</RouterLink>
            </div>
            <ul v-if="onLeave.length" class="mt-2 space-y-2">
              <li v-for="p in onLeave" :key="p.name + p.end_date" class="flex items-center gap-2.5 text-sm">
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-amber-50 text-[10px] font-semibold text-amber-800 ring-1 ring-amber-100">{{ initials(p.name) }}</span>
                <span class="min-w-0 flex-1 truncate text-gray-800">{{ p.name }}</span>
                <span class="whitespace-nowrap text-xs text-gray-500">back {{ backDate(p.end_date) }}</span>
              </li>
            </ul>
            <p v-else class="mt-1.5 text-xs text-gray-500">Everyone is in today.</p>
          </div>
        </section>
      </div>

      <!-- Recently added staff -->
      <section class="card overflow-hidden">
        <header class="flex items-center justify-between px-5 pt-4">
          <h2 class="font-semibold text-gray-900">Recently added staff</h2>
          <RouterLink v-if="can('/teachers')" to="/teachers" class="text-sm font-medium text-brand-700 hover:underline">View all</RouterLink>
        </header>
        <div v-if="!stats.recent_staff?.length" class="px-5 py-10 text-center text-sm text-gray-500">No staff added yet</div>
        <ul v-else class="grid gap-2 p-4 sm:grid-cols-2 xl:grid-cols-4">
          <li v-for="s in stats.recent_staff.slice(0, 8)" :key="s.id" class="flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-gray-100">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(`${s.first_name || ''} ${s.last_name || ''}`) }}</span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-semibold text-gray-900">{{ s.first_name }} {{ s.last_name }}</span>
              <span class="block truncate text-xs capitalize text-gray-500">{{ (s.staff_type || 'teaching').replace('_', ' ') }}<template v-if="s.department_name"> · {{ s.department_name }}</template></span>
              <span v-if="s.created_at" class="mt-0.5 flex items-center gap-1 text-[11px] text-gray-400" :title="`Added ${addedAt(s.created_at)}`"><Clock class="h-3 w-3" />{{ addedAt(s.created_at) }}</span>
            </span>
            <span class="h-2 w-2 flex-shrink-0 rounded-full" :class="s.status === 'active' ? 'bg-emerald-500' : s.status === 'on_leave' ? 'bg-amber-500' : 'bg-gray-300'" :title="String(s.status || '').replace('_', ' ')"></span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { hrDashboardAPI } from '../services/api.js';
import authStore from '../services/authStore.js';
import { useRouter } from 'vue-router';
import {
  Users as UsersIcon, GraduationCap, Clock, NotebookPen, UserPlus, ClipboardList, PlaneTakeoff, BookUser, School,
} from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import { canAccessRoute } from '../config/accessControl.js';

const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const greeting = computed(() => {
  const h = new Date().getHours();
  const part = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  const first = (authStore.user?.full_name || '').trim().split(/\s+/)[0];
  return first ? `${part}, ${first}` : part;
});

const loading = ref(true);
const stats = ref({});
const router = useRouter();
const can = (path) => canAccessRoute(path, authStore.user?.role, authStore.user);
const fmt = (n) => Number(n || 0).toLocaleString();
const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0);
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
// '30 Oct 2026, 14:05' (the time this staff member was added)
const addedAt = (when) => {
  const d = new Date(String(when).replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? String(when) : d.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};
const backDate = (end) => {
  const d = new Date(`${end}T00:00:00`);
  d.setDate(d.getDate() + 1);
  return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
};

const school = computed(() => stats.value.school || {});
const term = computed(() => school.value.term);
const termPct = computed(() => (term.value?.weeks ? Math.min(100, Math.round(((term.value.week || 0) / term.value.weeks) * 100)) : 0));
const lessons = computed(() => (school.value.lessons?.lessons ? school.value.lessons : null));
// Share of planned lesson time not lost (recorded taught minutes can run over the plan, so they are not used).
const keptPct = (lost, expected) => (expected ? Math.max(0, Math.min(100, Math.round(100 - (lost / expected) * 100))) : 0);
const taughtPct = computed(() => keptPct(lessons.value?.lost || 0, lessons.value?.expected || 0));
const onLeave = computed(() => school.value.on_leave_today || []);

const quickActions = computed(() => [
  { to: '/teachers', label: 'Add teacher', icon: UserPlus },
  { to: '/examinations', label: 'Exams', icon: ClipboardList },
  { to: '/lesson-monitoring', label: 'Lesson monitoring', icon: Clock },
  { to: '/leave', label: 'Leave', icon: PlaneTakeoff },
].filter((a) => can(a.to)));

const kpiCards = computed(() => {
  const s = stats.value;
  const st = school.value.students;
  const plans = school.value.plans;
  return [
    { label: 'Staff', value: fmt(s.total_staff), note: `${fmt(s.teaching_staff)} teaching · ${fmt(s.non_teaching_staff)} non-teaching`, icon: UsersIcon, onClick: can('/teachers') ? () => router.push('/teachers') : undefined },
    { label: 'Students', value: st ? fmt(st.total) : '-', note: st ? `${fmt(st.o_level)} O-Level · ${fmt(st.a_level)} A-Level` : '', icon: GraduationCap, tone: 'sky', onClick: can('/digital-register') ? () => router.push('/digital-register') : undefined },
    { label: 'Lesson time taught', value: lessons.value ? `${taughtPct.value}%` : '-', note: lessons.value ? `${fmt(lessons.value.lessons)} lessons this term` : 'No lessons recorded', icon: Clock, tone: 'amber', onClick: can('/lesson-monitoring') ? () => router.push('/lesson-monitoring') : undefined },
    { label: 'Plans to review', value: plans ? fmt(plans.waiting) : '-', note: plans?.new ? `${plans.new} new` : 'none new', icon: NotebookPen, tone: plans?.new ? 'red' : 'gray', onClick: can('/lesson-plans') ? () => router.push('/lesson-plans') : undefined },
  ];
});

const attention = computed(() => {
  const sch = school.value;
  const asg = sch.assignments;
  return [
    { label: 'Leave requests', note: 'Waiting for a decision', count: stats.value.leave_summary?.pending || 0, icon: PlaneTakeoff, to: '/leave', tone: 'bg-amber-50 text-amber-700 ring-amber-100', badge: 'bg-amber-100 text-amber-800' },
    { label: 'Lesson plans', note: 'Submitted, not opened yet', count: sch.plans?.new || 0, icon: NotebookPen, to: '/lesson-plans', tone: 'bg-rose-50 text-rose-600 ring-rose-100', badge: 'bg-rose-600 text-white' },
    { label: 'Teachers without classes', note: asg ? `${fmt(asg.with_classes)} of ${fmt(asg.teachers)} have classes this term` : 'Teaching assignments', count: asg ? Math.max(0, asg.teachers - asg.with_classes) : 0, icon: BookUser, to: '/teaching-assignments', tone: 'bg-sky-50 text-sky-700 ring-sky-100', badge: 'bg-sky-100 text-sky-800' },
    { label: 'Exams open', note: 'Teachers can enter marks', count: sch.exams_open || 0, icon: School, to: '/examinations', tone: 'bg-emerald-50 text-emerald-700 ring-emerald-100', badge: 'bg-emerald-100 text-emerald-800' },
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

const mix = computed(() => {
  const t = Number(stats.value.teaching_staff || 0);
  const n = Number(stats.value.non_teaching_staff || 0);
  const total = t + n || 1;
  return { teaching: Math.round((t / total) * 100), support: 100 - Math.round((t / total) * 100) };
});
const maxDept = computed(() => Math.max(1, ...(stats.value.departments || []).map((d) => +d.staff_count)));
const deptBarWidth = (count) => `${Math.round((+count / maxDept.value) * 100)}%`;

onMounted(async () => {
  try {
    const res = await hrDashboardAPI.getData(new Date().getFullYear());
    if (res.success) stats.value = res.data;
  } catch (e) {
    console.error(e);
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
/* Staff mix: emerald and blue, checked for colour-blind separation in both themes */
.mix-teaching { display: inline-block; background: #059669; }
.mix-support { display: inline-block; background: #2563eb; }
html.dark .quick-action { background: rgba(255, 255, 255, 0.04); border-color: #31413a; color: var(--d-text-2); }
html.dark .quick-action:hover { border-color: #10b981; color: #6ee7b7; }
html.dark .grid-line { stroke: #26332d; }
html.dark .axis-text { fill: #8a9a92; }
html.dark .bar { fill: #f43f5e; }
html.dark .mix-support { background: #3b82f6; }
</style>
