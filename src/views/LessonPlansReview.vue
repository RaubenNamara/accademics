<template>
  <div class="page">
    <ToastBanner />

    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
          <button v-if="dept" type="button" class="back-link" @click="goTo({})"><ArrowLeft class="h-4 w-4" />All departments</button>
          <h1 class="page-hero-title">{{ dept ? dept.name : 'Lesson Plans' }}</h1>
          <p v-if="dept" class="mt-0.5 text-sm text-gray-500">{{ plural(dept.plans.length, 'plan') }} · {{ plural(dept.teachers, 'teacher') }}<template v-if="dept.waiting"> · {{ dept.waiting }} awaiting review</template></p>
        </div>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="t in tabs"
        :key="t.value"
        type="button"
        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
        :class="status === t.value ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
        @click="status = t.value"
      >
        {{ t.label }}<span v-if="t.value === 'submitted' && waiting.length" class="ml-1.5 rounded-full bg-white/25 px-1.5 text-xs">{{ waiting.length }}</span>
      </button>
      <select v-model="sessionId" class="input ml-auto w-auto py-1.5" aria-label="Term">
        <option :value="null">All terms</option>
        <option v-for="s in sessions" :key="s.id" :value="s.id">{{ label(s) }}</option>
      </select>
      <input v-model="q" type="search" class="input w-56 py-1.5" placeholder="Teacher, class, topic">
    </div>

    <div v-if="loading && !plans.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="card h-20 animate-pulse"></div>
    </div>

    <!-- ---- One department's plans, latest submission first ---- -->
    <div v-else-if="dept" class="card overflow-hidden">
      <ul class="divide-y divide-gray-100">
        <li v-for="p in dept.plans" :key="p.id">
          <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-brand-50/50 sm:px-5" @click="goTo({ dept: dept.subject_id, plan: p.id })">
            <span class="relative flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">
              {{ initials(p.teacher_name) }}
              <span v-if="p.is_new" class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-red-600 ring-2 ring-white" title="New"></span>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm text-gray-900" :class="p.is_new ? 'font-semibold' : 'font-medium'">{{ p.topic }}</span>
              <span class="block truncate text-xs text-gray-500">{{ p.teacher_name }} · {{ p.full_class_name }}<template v-if="p.lesson_date"> · lesson {{ formatDate(p.lesson_date) }}</template></span>
            </span>
            <span class="hidden flex-shrink-0 text-right text-xs text-gray-400 sm:block">
              <span v-if="p.is_new" class="font-semibold text-red-600">New · </span>{{ ago(p.submitted_at || p.updated_at) }}
            </span>
            <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="PLAN_STATUS[p.status].style">{{ PLAN_STATUS[p.status].label }}</span>
            <ChevronRight class="h-4 w-4 flex-shrink-0 text-gray-300" />
          </button>
        </li>
      </ul>
      <p v-if="!dept.plans.length" class="px-5 py-12 text-center text-sm text-gray-500">No plans in this department for this filter.</p>
    </div>

    <!-- ---- Departments ---- -->
    <template v-else>
      <div v-if="!departments.length" class="card px-5 py-14 text-center text-sm text-gray-500">No lesson plans here.</div>
      <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="d in departments"
          :key="d.subject_id"
          type="button"
          class="card flex items-center gap-3 p-4 text-left"
          @click="goTo({ dept: d.subject_id })"
        >
          <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-xs font-bold text-brand-800 ring-1 ring-brand-100">{{ shortCode(d) }}</span>
          <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold text-gray-900">{{ d.name }}</span>
            <span class="block truncate text-xs text-gray-500">{{ plural(d.plans.length, 'plan') }} · {{ plural(d.teachers, 'teacher') }}</span>
            <span class="block truncate text-[11px] text-gray-400">Latest {{ ago(d.plans[0].submitted_at || d.plans[0].updated_at) }}<template v-if="d.waiting"> · {{ d.waiting }} awaiting review</template></span>
          </span>
          <span
            v-if="d.newCount"
            class="flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-red-600 px-1.5 text-xs font-bold text-white shadow-sm"
            :title="`${d.newCount} new plan(s) nobody has opened yet`"
          >{{ d.newCount }}</span>
          <ChevronRight class="h-4 w-4 flex-shrink-0 text-gray-300" />
        </button>
      </div>
    </template>

    <!-- ============ One plan, in a window on top of the list ============ -->
    <Teleport to="body">
    <div v-if="current" class="fixed inset-0 z-[60] flex items-end justify-center bg-gray-900/60 backdrop-blur-sm sm:items-center sm:p-4" @click.self="closePlan">
      <div class="flex h-[100dvh] w-full max-w-[78rem] flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[94vh] sm:rounded-2xl" role="dialog" aria-modal="true" :aria-label="current.topic">
        <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(current.teacher_name) }}</span>
          <div class="min-w-0 flex-1">
            <h2 class="truncate text-lg font-semibold text-gray-900">{{ current.topic }}</h2>
            <p class="truncate text-sm text-gray-500">{{ current.teacher_name }} · {{ current.full_class_name }} · {{ current.subject_name }} · Submitted {{ ago(current.submitted_at || current.updated_at) }}</p>
          </div>
          <span class="mt-1 hidden flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold sm:inline" :class="PLAN_STATUS[current.status].style">{{ PLAN_STATUS[current.status].label }}</span>
          <button type="button" class="btn-secondary flex-shrink-0 px-3 py-1.5 text-sm" @click="downloadPdf(current)"><Download class="h-4 w-4" /><span class="hidden sm:inline">PDF</span></button>
          <button type="button" class="flex-shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="closePlan"><X class="h-5 w-5" /></button>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto bg-gray-100">
          <div class="grid gap-5 p-3 sm:p-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
            <!-- As it prints, on an A4 page -->
            <div class="min-w-0">
              <article class="a4-sheet bg-white text-gray-900 ring-1 ring-gray-200">
                <p class="text-sm font-bold uppercase tracking-wide">{{ schoolName }}</p>
                <p class="mb-4 text-sm font-bold uppercase tracking-wide">Lesson Plan</p>
                <LessonPlanDetail :plan="current" show-teacher />
              </article>
            </div>
            <!-- Review: beside the plan on wide screens, under it otherwise -->
            <aside v-if="current.status === 'submitted' || current.status === 'approved'" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm lg:sticky lg:top-0">
              <h3 class="card-title">Review</h3>
              <label class="mt-3 block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Comment for the teacher{{ current.status === 'submitted' ? ' (needed to return)' : '' }}</span>
                <textarea v-model="comment" rows="4" maxlength="1000" class="input" placeholder="What is good, what to change"></textarea>
              </label>
              <div class="mt-3 flex flex-wrap justify-end gap-2">
                <button type="button" class="btn-secondary" :disabled="busy || !comment.trim()" @click="review('return')"><Undo2 class="h-4 w-4" />Return</button>
                <button v-if="current.status === 'submitted'" type="button" class="btn-primary" :disabled="busy" @click="review('approve')"><Check class="h-4 w-4" />Approve</button>
              </div>
            </aside>
            <aside v-else-if="current.review_comment" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              <h3 class="font-semibold">Returned with</h3>
              <p class="mt-2 whitespace-pre-line">{{ current.review_comment }}</p>
              <p v-if="current.reviewed_by" class="mt-2 text-xs opacity-80">{{ current.reviewed_by }}</p>
            </aside>
          </div>
        </div>
      </div>
    </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Check, ChevronRight, Download, Undo2, X } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import LessonPlanDetail from '../components/lessonPlans/LessonPlanDetail.vue';
import { PLAN_STATUS, formatDate } from '../components/lessonPlans/planMeta.js';
import { lessonPlansAPI, schoolSettingsAPI } from '../services/api.js';
import { buildLessonPlanPdf, lessonPlanFileName } from '../utils/lessonPlanPdf.js';
import { useSessions } from '../composables/useSessions.js';
import { useToast } from '../composables/useToast.js';
import { apiError } from '../utils/timetable.js';
import { newLessonPlans, refreshNewLessonPlans } from '../services/lessonPlanBadge.js';

const tabs = [
  { value: 'submitted', label: 'Awaiting review' },
  { value: 'returned', label: 'Returned' },
  { value: 'approved', label: 'Approved' },
  { value: '', label: 'All submitted' },
];
const route = useRoute();
const router = useRouter();
const { sessions, sessionId, load: loadSessions, label } = useSessions();
const { showToast } = useToast();
const status = ref('submitted');
const plans = ref([]);
const waiting = ref([]); // every plan awaiting review (the tab count and the departments' badges)
const q = ref('');
const loading = ref(false);
const busy = ref(false);
const comment = ref('');

const initials = (name) => String(name || '?').split(/\s+/).filter((w) => /^[A-Za-z]/.test(w)).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
const shortCode = (d) => String(d.code || d.name).replace(/[^A-Za-z]/g, '').slice(0, 4).toUpperCase();
const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
const ago = (when) => {
  if (!when) return '';
  const mins = Math.round((Date.now() - new Date(String(when).replace(' ', 'T')).getTime()) / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins} min ago`;
  if (mins < 60 * 24) return `${Math.round(mins / 60)} h ago`;
  if (mins < 60 * 24 * 7) return `${Math.round(mins / 1440)} day${Math.round(mins / 1440) === 1 ? '' : 's'} ago`;
  return formatDate(String(when).slice(0, 10));
};

/* Where we are: ?dept=<subject id> opens a department, &plan=<id> opens one plan. */
const goTo = ({ dept: d, plan } = {}) => {
  comment.value = '';
  router.push({ query: { ...(d ? { dept: String(d) } : {}), ...(plan ? { plan: String(plan) } : {}) } });
};

/** Close the plan window: back to the list it was opened from. */
const closePlan = () => {
  comment.value = '';
  const back = String(window.history.state?.back || '');
  if (back.startsWith(route.path) && !back.includes('plan=')) router.back();
  else router.replace({ query: route.query.dept ? { dept: String(route.query.dept) } : {} });
};
const onKey = (e) => { if (e.key === 'Escape' && route.query.plan) closePlan(); };
window.addEventListener('keydown', onKey);
onBeforeUnmount(() => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; });

const shown = computed(() => {
  const term = q.value.trim().toLowerCase();
  return term ? plans.value.filter((p) => `${p.teacher_name} ${p.full_class_name} ${p.subject_name} ${p.topic}`.toLowerCase().includes(term)) : plans.value;
});

// A department is the plan's subject. Departments with new plans come first, then the most recent submission.
const departments = computed(() => {
  const newBySubject = new Map();
  const waitingBySubject = new Map();
  for (const p of waiting.value) {
    waitingBySubject.set(p.subject_id, (waitingBySubject.get(p.subject_id) || 0) + 1);
    if (p.is_new) newBySubject.set(p.subject_id, (newBySubject.get(p.subject_id) || 0) + 1);
  }
  const map = new Map();
  for (const p of shown.value) { // already newest submission first
    if (!map.has(p.subject_id)) map.set(p.subject_id, { subject_id: p.subject_id, name: p.subject_name, code: p.subject_code, plans: [], teacherIds: new Set() });
    const d = map.get(p.subject_id);
    d.plans.push(p);
    d.teacherIds.add(p.teacher_id);
  }
  const stamp = (p) => String(p.submitted_at || p.updated_at || '');
  return [...map.values()]
    .map((d) => ({ ...d, teachers: d.teacherIds.size, newCount: newBySubject.get(d.subject_id) || 0, waiting: waitingBySubject.get(d.subject_id) || 0 }))
    .sort((a, b) => (b.newCount > 0) - (a.newCount > 0) || stamp(b.plans[0]).localeCompare(stamp(a.plans[0])));
});
const dept = computed(() => {
  const id = Number(route.query.dept);
  if (!id) return null;
  const found = departments.value.find((d) => d.subject_id === id);
  if (found) return found;
  // Nothing in this filter: still show the department (empty) so the page does not jump.
  const any = [...plans.value, ...waiting.value].find((p) => p.subject_id === id);
  return any ? { subject_id: id, name: any.subject_name, code: any.subject_code, plans: [], teachers: 0, waiting: 0, newCount: 0 } : null;
});
const current = computed(() => {
  const id = Number(route.query.plan);
  return id ? [...plans.value, ...waiting.value].find((p) => p.id === id) || null : null;
});

/** Opening a plan clears its "new" mark for every reviewer (and the badges with it). */
watch(current, (p) => { document.body.style.overflow = p ? 'hidden' : ''; });
watch(current, async (p) => {
  if (!p?.is_new) return;
  for (const list of [plans.value, waiting.value]) {
    const same = list.find((x) => x.id === p.id);
    if (same) same.is_new = false;
  }
  newLessonPlans.value = Math.max(0, newLessonPlans.value - 1);
  try {
    await lessonPlansAPI.seen(p.id);
  } catch {
    /* it stays new on the next load */
  }
  refreshNewLessonPlans();
});

async function load() {
  loading.value = true;
  try {
    const filters = { status: status.value, academic_session_id: sessionId.value };
    const [list, pending] = await Promise.all([
      lessonPlansAPI.list(filters),
      lessonPlansAPI.list({ status: 'submitted', academic_session_id: sessionId.value }),
    ]);
    plans.value = list.data;
    waiting.value = pending.data;
    // A plan linked by id but outside this filter (e.g. already approved): fetch it so the page still opens.
    const want = Number(route.query.plan);
    if (want && !current.value) {
      const one = (await lessonPlansAPI.list({ id: want })).data[0];
      if (one) plans.value = [...plans.value, one];
    }
    refreshNewLessonPlans();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    loading.value = false;
  }
}

const settings = ref(null);
const schoolName = computed(() => settings.value?.school_name || "St. Mark's College Namagoma");
schoolSettingsAPI.get().then((r) => { settings.value = r.data; }).catch(() => {});
async function downloadPdf(p) {
  try {
    settings.value ??= (await schoolSettingsAPI.get()).data;
    buildLessonPlanPdf(p, settings.value).save(lessonPlanFileName(p));
  } catch (e) {
    showToast(`Could not build the PDF: ${apiError(e).message}`, 'error');
  }
}

async function review(decision) {
  busy.value = true;
  try {
    const { id } = current.value;
    showToast((await lessonPlansAPI.review(id, decision, comment.value)).message);
    comment.value = '';
    await load();
    // Close the window: the department's list is underneath, ready for the next plan.
    closePlan();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    busy.value = false;
  }
}

watch([status, sessionId], load);
watch(() => route.query.plan, (id) => { if (id && !current.value && !loading.value) load(); });
onMounted(async () => {
  try {
    await loadSessions();
  } catch {
    /* the term filter is optional */
  }
  load();
});
</script>

<style scoped>
.back-link { @apply mb-1 inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .back-link {
  color: #86efac;
}
</style>
