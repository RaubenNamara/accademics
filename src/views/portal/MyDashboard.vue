<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">{{ greeting }}, {{ firstName }}</h1>
          <p class="page-hero-subtitle">
            <template v-if="me">{{ me.session.academic_year }} Term {{ me.session.term }}<span v-if="me.teacher.teacher_code"> · {{ me.teacher.teacher_code }}</span><span v-if="subjectNames"> · {{ subjectNames }}</span></template>
            <template v-else>Your teaching at a glance.</template>
          </p>
        </div>
      </div>
    </div>

    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <!-- Teacher portal: one tile per part of the teacher's work -->
    <section aria-label="Teacher portal">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
        <RouterLink v-for="t in tiles" :key="t.to" :to="t.to" class="portal-tile card group flex flex-col p-5">
          <span class="flex h-12 w-12 items-center justify-center rounded-xl transition group-hover:scale-105" :class="t.icon"><component :is="t.Icon" class="h-6 w-6" :stroke-width="1.8" /></span>
          <span class="mt-4 text-[17px] font-bold tracking-tight text-gray-900">{{ t.title }}</span>
          <span class="mt-1 text-sm leading-relaxed text-gray-500">{{ t.text }}</span>
          <span v-if="t.badge" class="mt-3 inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="t.badgeTone">{{ t.badge }}</span>
        </RouterLink>
      </div>
    </section>

    <!-- Marks to enter -->
    <section class="card overflow-hidden">
      <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
        <div>
          <h2 class="card-title">Marks to enter</h2>
        </div>
      </header>
      <div v-if="loading" class="space-y-3 p-5"><div v-for="i in 3" :key="i" class="h-10 animate-pulse rounded-xl bg-gray-100"></div></div>
      <div v-else-if="!progress.length" class="px-5 py-10 text-center">
        <CircleCheck class="mx-auto h-8 w-8 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No exams are open for marks right now</p>
        <p class="text-xs text-gray-500">The academic office opens AOIs and exams when it is time to enter marks.</p>
      </div>
      <ul v-else class="divide-y divide-gray-100">
        <template v-for="e in progress" :key="e.exam_id">
          <li v-for="s in e.subjects" :key="`${e.exam_id}-${s.subject_id}`" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl text-xs font-bold ring-1" :class="e.exam_type === 'AOI' ? 'bg-sky-50 text-sky-700 ring-sky-100' : 'bg-brand-50 text-brand-700 ring-brand-100'">
              {{ e.exam_type === 'AOI' ? 'AOI' : e.exam_type }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-gray-900">{{ e.name }} <span class="font-normal text-gray-500">· {{ s.subject_name }}</span></p>
              <p class="text-xs text-gray-500">Marked out of {{ num(e.max_marks) }}, counts out of {{ e.out_of }}</p>
            </div>
            <div class="order-last w-full sm:order-none sm:w-56">
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-600"><strong class="text-gray-900">{{ s.complete }}</strong> of {{ s.classes }} classes done</span>
                <span v-if="s.started" class="font-medium text-amber-700">{{ s.started }} in progress</span>
              </div>
              <div class="mt-1 flex h-1.5 overflow-hidden rounded-full bg-gray-100">
                <span class="bg-brand-500" :style="{ width: `${pct(s.complete, s.classes)}%` }"></span>
                <span class="bg-amber-400" :style="{ width: `${pct(s.started, s.classes)}%` }"></span>
              </div>
            </div>
            <RouterLink
              :to="{ path: '/my/marks', query: { exam_id: e.exam_id, subject_id: s.subject_id, ...(s.next ? { class_id: s.next.class_id } : {}) } }"
              class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
              :title="s.next ? `${s.next.full_class_name}: ${s.next.entered} of ${s.next.students} learners marked` : 'Choose a class'"
            >
              {{ s.next ? `Continue ${s.next.full_class_name}` : 'Start' }}<ChevronRight class="h-4 w-4" />
            </RouterLink>
          </li>
        </template>
      </ul>
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
      <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
          <h2 class="card-title">Today · {{ me?.today || '' }}</h2>
          <RouterLink to="/my/timetable" class="link-btn">Full timetable</RouterLink>
        </div>
        <div v-if="loading" class="px-5 py-8 text-center text-sm text-gray-500">Loading…</div>
        <div v-else-if="!me?.today_lessons?.length" class="px-5 py-10 text-center">
          <CalendarDays class="mx-auto h-8 w-8 text-gray-300" />
          <p class="mt-2 text-sm text-gray-600">No lessons on your timetable today.</p>
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="l in me.today_lessons" :key="l.id" class="flex items-center gap-4 px-5 py-3">
            <div class="w-24 flex-shrink-0 text-xs tabular-nums text-gray-500">
              <p class="font-semibold text-gray-800">{{ shortTime(l.start_time) }}–{{ shortTime(l.end_time) }}</p>
            </div>
            <span class="h-9 w-1 flex-shrink-0 rounded-full" :style="{ background: subjectColour(l.subject_id).border }"></span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-medium text-gray-900">{{ entryTitle(l) }}</p>
              <p class="truncate text-xs text-gray-500">{{ l.full_class_name || l.class_name }}<span v-if="l.room_name"> · {{ l.room_name }}</span></p>
            </div>
          </li>
        </ul>
        <p v-if="me && me.timetable_source === 'draft'" class="border-t border-gray-100 px-5 py-2 text-xs text-amber-700">The timetable for this term is not published yet, so this may still change.</p>
      </section>

      <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
          <h2 class="card-title">My classes</h2>
          <span class="text-xs text-gray-500">{{ mySubjects.length }}</span>
        </div>
        <div v-if="!loading && !mySubjects.length" class="px-5 py-10 text-center text-sm text-gray-500">
          No classes are assigned to you this term yet. The academic office assigns them under Teaching Assignments.
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="s in mySubjects" :key="s.subject_id" class="px-5 py-3">
            <div class="flex items-center justify-between gap-3">
              <p class="font-semibold text-gray-900">{{ s.subject_name }}</p>
              <RouterLink :to="{ path: '/my/marks', query: { subject_id: s.subject_id } }" class="link-btn text-xs">Marks</RouterLink>
            </div>
            <p class="text-xs text-gray-500">
              {{ s.classes }} class{{ s.classes === 1 ? '' : 'es' }}<template v-if="s.levels.length"> · {{ s.levels.join(', ') }}</template>
            </p>
            <p class="mt-1.5 flex flex-wrap gap-1">
              <RouterLink v-for="c in s.classList" :key="c.class_id" :to="{ path: '/my/marks', query: { subject_id: s.subject_id, class_id: c.class_id } }" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-800">{{ c.full_class_name }}</RouterLink>
            </p>
          </li>
        </ul>
      </section>
    </div>

    <MyLeaveCard />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { CalendarCheck, CalendarDays, ChevronRight, CircleCheck, NotebookPen, PenLine, PlaneTakeoff, TrendingUp } from 'lucide-vue-next';
import ToastBanner from '../../components/hr/ToastBanner.vue';
import MyLeaveCard from '../../components/portal/MyLeaveCard.vue';
import authStore from '../../services/authStore.js';
import { teacherPortalAPI } from '../../services/api.js';
import { apiError, entryTitle, shortTime, subjectColour } from '../../utils/timetable.js';

const me = ref(null);
const loading = ref(true);
const error = ref('');

const now = new Date();
const dateLabel = now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
const greeting = now.getHours() < 12 ? 'Good morning' : now.getHours() < 17 ? 'Good afternoon' : 'Good evening';
const firstName = computed(() => (me.value?.teacher.full_name || authStore.user?.full_name || '').split(/\s+/)[0]);
const num = (v) => Number(v).toString();
const pct = (n, of) => (of ? Math.round((n / of) * 100) : 0);

const progress = computed(() => me.value?.marks_progress || []);
// The subjects the teacher can mark (their department), with how many classes and levels each covers.
const mySubjects = computed(() => {
  const by = new Map();
  for (const a of me.value?.mark_scope || []) {
    const s = by.get(a.subject_id) || { subject_id: a.subject_id, subject_name: a.subject_name, classes: 0, levels: [], classList: [] };
    s.classes++;
    s.classList.push({ class_id: a.class_id, full_class_name: a.full_class_name });
    if (!s.levels.includes(a.level)) s.levels.push(a.level);
    by.set(a.subject_id, s);
  }
  return [...by.values()].sort((x, y) => x.subject_name.localeCompare(y.subject_name));
});
const subjectNames = computed(() => mySubjects.value.map((s) => s.subject_name).join(', '));

// The portal tiles, each with a badge only when there is something worth knowing.
const BADGE = {
  amber: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
  red: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
  green: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
  gray: 'bg-gray-100 text-gray-600',
};
const tiles = computed(() => {
  const m = me.value;
  const plans = m?.plans || {};
  const att = m?.attendance || {};
  const toDo = progress.value.reduce((t, e) => t + e.subjects.reduce((n, s) => n + (s.classes - s.complete), 0), 0);
  const plansTotal = Object.values(plans).reduce((t, n) => t + Number(n), 0);
  const today = (m?.today_lessons || []).filter((e) => (e.entry_type || 'lesson') === 'lesson').length;
  const plural = (n, w) => `${n} ${w}${n === 1 ? '' : 's'}`;
  return [
    { to: '/my/marks', title: 'Enter Marks', text: 'Enter marks for the classes assigned to you.', Icon: PenLine, icon: 'bg-teal-50 text-teal-600',
      badge: m?.open_exams ? (toDo ? `${plural(toDo, 'sheet')} to finish` : 'All sheets done') : (m ? 'No exam open' : ''), badgeTone: toDo ? BADGE.amber : m?.open_exams ? BADGE.green : BADGE.gray },
    { to: '/my/lesson-plans', title: 'Lesson Plans', text: 'Write plans and send them for review.', Icon: NotebookPen, icon: 'bg-sky-50 text-sky-600',
      badge: plans.returned ? `${plans.returned} returned` : plansTotal ? plural(plansTotal, 'plan') : '', badgeTone: plans.returned ? BADGE.red : BADGE.gray },
    { to: '/my/timetable', title: 'My Timetable', text: 'Your weekly teaching schedule.', Icon: CalendarDays, icon: 'bg-amber-50 text-amber-600',
      badge: m ? (today ? `${plural(today, 'lesson')} today` : 'No lessons today') : '', badgeTone: today ? BADGE.green : BADGE.gray },
    { to: '/my/attendance', title: 'Lesson Attendance', text: 'Your recorded lessons and any time lost.', Icon: CalendarCheck, icon: 'bg-emerald-50 text-emerald-600',
      badge: Number(att.lost) ? `${att.lost} min lost` : att.lessons ? plural(Number(att.lessons), 'lesson') : '', badgeTone: Number(att.lost) ? BADGE.amber : BADGE.gray },
    { to: '/my/performance', title: 'My Performance', text: 'Duty scores, observations and attendance.', Icon: TrendingUp, icon: 'bg-violet-50 text-violet-600', badge: '', badgeTone: '' },
    { to: '/my/leave', title: 'My Leave', text: 'Apply for leave and track your requests.', Icon: PlaneTakeoff, icon: 'bg-rose-50 text-rose-500', badge: '', badgeTone: '' },
  ];
});

onMounted(async () => {
  try {
    me.value = (await teacherPortalAPI.view('me')).data;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.portal-tile { transition: transform 0.18s ease, box-shadow 0.18s ease; }
.portal-tile:hover { transform: translateY(-3px); box-shadow: var(--glass-shadow-lift); }
.portal-tile:focus-visible { outline: 2px solid rgb(16 185 129); outline-offset: 2px; }
html.dark .portal-tile span.text-gray-900 { color: var(--d-text); }
</style>
