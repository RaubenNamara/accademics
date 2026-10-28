<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <h1 class="page-hero-title">Terms & Years</h1>
    </div>

    <div v-if="loading" class="card px-5 py-10 text-center text-sm text-gray-500">Loading…</div>
    <template v-else-if="cal">
      <!-- Current term -->
      <section class="card overflow-hidden">
        <div v-if="cal.open" class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
          <div class="min-w-0 flex-1">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-100">
              <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>Current term
            </span>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">{{ cal.open.academic_year }} · Term {{ cal.open.term }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ formatDate(cal.open.start_date) }} – {{ formatDate(cal.open.end_date) }}</p>
            <div class="mt-4 flex max-w-lg items-center gap-3">
              <span class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full bg-brand-500" :style="{ width: `${termProgress.pct}%` }"></span></span>
              <span class="whitespace-nowrap text-xs text-gray-600">{{ termProgress.text }}</span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
              <span class="rounded-lg bg-gray-50 px-2.5 py-1 text-gray-700 ring-1 ring-gray-100"><strong class="tabular-nums">{{ currentTerm?.exams ?? 0 }}</strong> exams</span>
              <span class="rounded-lg bg-gray-50 px-2.5 py-1 text-gray-700 ring-1 ring-gray-100"><strong class="tabular-nums">{{ currentTerm?.open_exams ?? 0 }}</strong> open for marks</span>
              <span v-for="r in reopened" :key="r.id" class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 py-1 pl-2.5 pr-1 font-medium text-amber-900 ring-1 ring-amber-200">
                {{ r.academic_year }} Term {{ r.term }} reopened
                <button v-if="isAdmin" type="button" class="rounded-md px-1.5 py-0.5 hover:bg-amber-100" :title="`Close ${r.academic_year} Term ${r.term} again`" @click="closeAgain(r)">Close</button>
              </span>
            </div>
          </div>
          <div class="flex flex-col items-start gap-2 lg:items-end">
            <button v-if="isAdmin" type="button" class="btn-primary" @click="openClose">
              <CalendarCheck2 class="h-4 w-4" />
              {{ Number(cal.open.term) === 3 ? `Close ${cal.open.academic_year}, open ${cal.next.academic_year} Term 1` : `Close Term ${cal.open.term}, open Term ${cal.next.term}` }}
            </button>
            <p v-else class="text-sm text-gray-500">Only an administrator can close a term.</p>
            <p v-if="Number(cal.open.term) === 3" class="max-w-xs text-xs text-gray-500 lg:text-right" title="S.1–S.3 and S.5 move up a class in the same stream; S.4 and S.6 are marked completed.">Closing Term 3 closes the year and moves students up.</p>
          </div>
        </div>
        <div v-else class="flex items-center gap-3 p-5 text-sm text-amber-800">
          <CalendarCheck2 class="h-5 w-5" />No term is open. Open one below to start entering marks.
        </div>
      </section>

      <!-- Years and terms: every year shows its three term slots -->
      <section class="card overflow-hidden">
        <header class="flex items-center justify-between px-5 pt-4">
          <h2 class="card-title">Years and terms</h2>
          <div class="hidden items-center gap-3 text-[11px] text-gray-500 sm:flex">
            <span v-for="(s, key) in STATUS" :key="key" class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" :class="s.dot"></span>{{ s.label }}</span>
          </div>
        </header>
        <div v-if="!cal.years.length" class="px-5 py-8 text-center text-sm text-gray-500">No terms yet.</div>
        <div class="divide-y divide-gray-100">
          <div v-for="y in yearRows" :key="y.year" class="grid gap-3 px-5 py-4 md:grid-cols-[4.5rem_1fr] md:items-start">
            <p class="pt-2 text-lg font-bold tabular-nums text-gray-900">{{ y.year }}</p>
            <div class="grid gap-2.5 sm:grid-cols-3">
              <template v-for="slot in y.slots" :key="slot.term">
                <div v-if="slot.t" class="term-tile" :class="STATUS[slot.t.status].tile">
                  <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-900">Term {{ slot.term }}</p>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="STATUS[slot.t.status].chip"><span class="h-1.5 w-1.5 rounded-full" :class="STATUS[slot.t.status].dot"></span>{{ STATUS[slot.t.status].label }}</span>
                  </div>
                  <p class="mt-1 text-xs text-gray-500">{{ shortDate(slot.t.start_date) }} – {{ shortDate(slot.t.end_date) }}</p>
                  <p class="mt-0.5 text-xs text-gray-500">{{ slot.t.exams }} exam{{ Number(slot.t.exams) === 1 ? '' : 's' }}<span v-if="slot.t.closed_at && slot.t.status === 'closed'" :title="slot.t.closed_by ? `Closed by ${slot.t.closed_by}` : ''"> · closed {{ shortDate(slot.t.closed_at) }}</span></p>
                  <div v-if="isAdmin" class="mt-2 flex gap-2">
                    <button v-if="!cal.open && slot.t.status === 'upcoming'" type="button" class="tile-btn" @click="openTerm(slot.t)">Open this term</button>
                    <button v-if="slot.t.status === 'closed'" type="button" class="tile-btn" @click="askReopen(slot.t)"><RotateCcw class="h-3.5 w-3.5" />Reopen</button>
                    <button v-if="slot.t.status === 'reopened'" type="button" class="tile-btn" @click="closeAgain(slot.t)"><Lock class="h-3.5 w-3.5" />Close again</button>
                  </div>
                </div>
                <div v-else class="flex min-h-[5.5rem] items-center justify-center rounded-xl border border-dashed border-gray-200 text-xs text-gray-400">Term {{ slot.term }} · not set up</div>
              </template>
            </div>
          </div>
        </div>
      </section>

      <!-- Subjects by level: one row per subject -->
      <section class="card overflow-hidden">
        <header class="flex flex-wrap items-center justify-between gap-3 px-5 pt-4">
          <div>
            <h2 class="card-title">Subjects by level</h2>
            <p class="text-xs text-gray-500">Offered subjects can be chosen for students; compulsory ones are taken by every student at that level.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input v-model="subjectSearch" type="search" class="input w-48 py-1.5 !pl-8 text-sm" placeholder="Find a subject">
            </div>
            <select v-model="subjectFilter" class="input w-auto py-1.5 text-sm" aria-label="Show">
              <option value="all">All subjects</option>
              <option value="o">Offered at O-Level</option>
              <option value="a">Offered at A-Level</option>
              <option value="comp">Compulsory anywhere</option>
              <option value="none">Not offered</option>
            </select>
          </div>
        </header>

        <!-- One card per level -->
        <div class="mt-4 grid gap-4 px-5 pb-5 lg:grid-cols-2">
          <div v-for="lv in LEVELS" :key="lv" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3" :class="lv === 'O-Level' ? 'bg-sky-50/60' : 'bg-violet-50/60'">
              <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold" :class="lv === 'O-Level' ? 'bg-sky-100 text-sky-800' : 'bg-violet-100 text-violet-800'">{{ lv[0] }}</span>
                <span class="font-semibold text-gray-900">{{ lv }}</span>
              </div>
              <span class="flex gap-3 text-xs text-gray-600">
                <span><strong class="text-base tabular-nums text-gray-900">{{ offered[lv]?.length || 0 }}</strong> offered</span>
                <span><strong class="text-base tabular-nums text-brand-700">{{ comp[lv]?.length || 0 }}</strong> compulsory</span>
              </span>
            </div>
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                  <th class="px-4 py-2 text-left">Subject</th>
                  <th class="w-24 px-2 py-2 text-center">Offered</th>
                  <th class="w-24 px-2 py-2 text-center">Compulsory</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="s in shownSubjects" :key="s.id" class="transition hover:bg-gray-50/70" :class="isOffered(lv, s.id) ? '' : 'opacity-60'">
                  <td class="px-4 py-2">
                    <span class="font-medium text-gray-900">{{ s.subject_name }}</span>
                    <span v-if="s.subject_code" class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] uppercase text-gray-500">{{ s.subject_code }}</span>
                  </td>
                  <td class="px-2 py-2 text-center">
                    <button type="button" role="switch" class="switch" :class="{ on: isOffered(lv, s.id) }" :aria-checked="isOffered(lv, s.id)" :aria-label="`${s.subject_name} offered at ${lv}`" @click="toggleOffered(lv, Number(s.id))"><span></span></button>
                  </td>
                  <td class="px-2 py-2 text-center">
                    <button type="button" role="switch" class="switch comp" :class="{ on: isComp(lv, s.id) }" :aria-checked="isComp(lv, s.id)" :aria-label="`${s.subject_name} compulsory at ${lv}`" @click="toggleComp(lv, Number(s.id))"><span></span></button>
                  </td>
                </tr>
                <tr v-if="!shownSubjects.length"><td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500">No subjects match.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Save bar: only while there are changes -->
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-5 py-3" :class="subjectsDirty ? 'bg-amber-50/60' : ''">
          <span v-if="subjectsDirty" class="mr-auto text-sm text-amber-800">You have unsaved changes</span>
          <button v-if="subjectsDirty" type="button" class="btn-secondary px-3 py-1.5 text-sm" :disabled="savingComp" @click="discardSubjects">Discard</button>
          <button type="button" class="btn-primary px-4 py-1.5 text-sm" :disabled="savingComp || !subjectsDirty" @click="saveCompulsory">{{ savingComp ? 'Saving…' : subjectsDirty ? 'Save changes' : 'Saved' }}</button>
        </div>
      </section>
    </template>

    <!-- Close dialog -->
    <Teleport to="body">
    <div v-if="dialog" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4">
      <form class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl" @submit.prevent="closeTerm">
        <div class="border-b border-gray-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ preview?.year_end ? `Close the year ${cal.open.academic_year}` : `Close Term ${cal.open.term}` }}</h2>
          <p class="text-sm text-gray-500">Then {{ cal.next.academic_year }} Term {{ cal.next.term }} opens.</p>
        </div>
        <div class="flex-1 space-y-5 overflow-auto px-6 py-4">
          <div v-if="!preview" class="py-6 text-center text-sm text-gray-500">Checking…</div>
          <template v-else>
            <div>
              <p class="text-sm font-semibold text-gray-900">Exams of Term {{ cal.open.term }}</p>
              <p v-if="!preview.exams.length" class="text-sm text-gray-500">None.</p>
              <ul v-else class="mt-1 space-y-0.5 text-sm text-gray-700">
                <li v-for="e in preview.exams" :key="e.name">{{ e.name }}, {{ e.marks }} mark(s){{ e.status === 'open' ? ' · will be closed (marks locked)' : ` · ${e.status}` }}</li>
              </ul>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">{{ cal.next.academic_year }} Term {{ cal.next.term }} starts</span>
                <input v-model="form.start_date" type="date" class="input" required>
              </label>
              <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">and ends</span>
                <input v-model="form.end_date" type="date" class="input" :min="form.start_date" required>
              </label>
            </div>
            <label v-if="preview.requirements" class="flex items-start gap-2 text-sm text-gray-700">
              <input v-model="form.copy_requirements" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 accent-brand-600">
              <span>Copy this term's {{ preview.requirements }} lesson requirements (who teaches what) to the new term, so teachers keep their classes and the timetable can be generated.</span>
            </label>

            <p v-if="preview.year_end && preview.already_promoted" class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">Students were already moved up when {{ cal.open.academic_year }} first closed, so closing it again does not move anyone.</p>
            <div v-if="preview.year_end && preview.promotion" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
              <p class="text-sm font-semibold text-amber-900">Promotion at the end of {{ cal.open.academic_year }}</p>
              <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-lg bg-white p-2"><p class="text-lg font-bold text-gray-900">{{ promotedCount }}</p><p class="text-[11px] text-gray-500">move up a class</p></div>
                <div class="rounded-lg bg-white p-2"><p class="text-lg font-bold text-gray-900">{{ preview.promotion.completed_o_level }}</p><p class="text-[11px] text-gray-500">S.4 → Completed S.4</p></div>
                <div class="rounded-lg bg-white p-2"><p class="text-lg font-bold text-gray-900">{{ preview.promotion.completed_a_level }}</p><p class="text-[11px] text-gray-500">S.6 → Completed S.6</p></div>
              </div>
              <details class="mt-3 text-sm">
                <summary class="cursor-pointer font-medium text-amber-900">See every class</summary>
                <ul class="mt-2 grid gap-x-4 sm:grid-cols-2">
                  <li v-for="m in preview.promotion.moves" :key="`${m.from}-${m.stream}`" class="text-gray-700">{{ m.from }} {{ m.stream }} → {{ m.to }} {{ m.stream }} <span class="text-gray-500">({{ m.count }})</span></li>
                </ul>
              </details>
              <p v-if="preview.promotion.missing_streams.length" class="mt-2 text-xs text-amber-900">
                New streams will be created: {{ preview.promotion.missing_streams.map((c) => `${c.class_name} ${c.stream_name}`).join(', ') }}.
              </p>
              <p class="mt-2 text-xs text-amber-900">Completed S.4 students stay in the e-file (greyed) until you admit them to S.5. This cannot be undone from here.</p>
              <label class="mt-3 block">
                <span class="mb-1 block text-sm font-medium text-amber-900">Type <strong>{{ cal.open.academic_year }}</strong> to confirm</span>
                <input v-model="form.confirm" class="input" inputmode="numeric" autocomplete="off">
              </label>
            </div>
          </template>
          <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
          <button type="button" class="btn-secondary" :disabled="closing" @click="dialog = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="closing || !preview || (preview.promotion && form.confirm !== String(cal.open.academic_year))">
            {{ closing ? 'Closing…' : preview?.year_end ? 'Close the year' : 'Close term' }}
          </button>
        </div>
      </form>
    </div>
    </Teleport>

    <!-- Reopen a closed term -->
    <Teleport to="body">
    <div v-if="reopenTarget" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="reopenTarget = null">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true" aria-labelledby="reopen-title">
        <div class="flex items-start gap-3 px-6 pt-5">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 ring-1 ring-amber-100"><RotateCcw class="h-5 w-5" /></span>
          <div>
            <h2 id="reopen-title" class="text-lg font-semibold text-gray-900">Reopen {{ reopenTarget.academic_year }} Term {{ reopenTarget.term }}?</h2>
            <p class="mt-1 text-sm text-gray-600">Marks, exams, lesson plans and other records can be entered for it again.</p>
          </div>
        </div>
        <ul class="mx-6 mt-4 space-y-1.5 rounded-xl bg-gray-50 p-3 text-sm text-gray-700">
          <li v-if="cal.open">· {{ cal.open.academic_year }} Term {{ cal.open.term }} stays open as the current term.</li>
          <li>· Pick it in the term list at the top to work in it.</li>
          <li>· Students are not moved up or back. Close it again when you are done.</li>
        </ul>
        <div class="mt-5 flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
          <button type="button" class="btn-secondary" :disabled="reopening" @click="reopenTarget = null">Cancel</button>
          <button type="button" class="btn-primary" :disabled="reopening" @click="reopen"><RotateCcw class="h-4 w-4" />{{ reopening ? 'Reopening…' : 'Reopen term' }}</button>
        </div>
      </div>
    </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { CalendarCheck2, Lock, RotateCcw, Search } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import { academicCalendarAPI } from '../services/api.js';
import authStore from '../services/authStore.js';
import termStore from '../services/termStore.js';
import { useToast } from '../composables/useToast.js';
import { apiError } from '../utils/timetable.js';
import { formatDate } from '../components/lessonPlans/planMeta.js';

const STATUS = {
  open: { label: 'Current', chip: 'bg-brand-600 text-white', dot: 'bg-brand-500', tile: 'border-brand-300 bg-brand-50/70 ring-1 ring-brand-200' },
  reopened: { label: 'Reopened', chip: 'bg-amber-100 text-amber-800', dot: 'bg-amber-500', tile: 'border-amber-200 bg-amber-50/60' },
  closed: { label: 'Closed', chip: 'bg-gray-100 text-gray-600', dot: 'bg-gray-400', tile: 'border-gray-200 bg-white' },
  upcoming: { label: 'Upcoming', chip: 'bg-sky-50 text-sky-700', dot: 'bg-sky-400', tile: 'border-dashed border-gray-300 bg-white' },
};
const LEVELS = ['O-Level', 'A-Level'];
const { showToast } = useToast();
const isAdmin = computed(() => ['admin', 'super_admin'].includes(authStore.getRole()));
const cal = ref(null);
const loading = ref(true);
const dialog = ref(false);
const preview = ref(null);
const closing = ref(false);
const error = ref('');
const form = reactive({ start_date: '', end_date: '', copy_requirements: true, confirm: '' });
const subjects = ref([]);
const comp = ref({});
const offered = ref({});
const savingComp = ref(false);

const shortDate = (d) => (d ? new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-');
const currentTerm = computed(() => (cal.value?.years || []).flatMap((y) => y.terms).find((t) => t.status === 'open') || null);
// Where today falls in the current term.
const termProgress = computed(() => {
  const o = cal.value?.open;
  if (!o?.start_date || !o?.end_date) return { pct: 0, text: '' };
  const day = 86400000;
  const start = new Date(`${o.start_date}T00:00:00`).getTime();
  const end = new Date(`${o.end_date}T00:00:00`).getTime() + day;
  const now = Date.now();
  const weeks = Math.max(1, Math.ceil((end - start) / (7 * day)));
  if (now < start) return { pct: 0, text: `starts ${shortDate(o.start_date)}` };
  if (now >= end) return { pct: 100, text: 'term dates have passed' };
  const week = Math.floor((now - start) / (7 * day)) + 1;
  return { pct: Math.round(((now - start) / (end - start)) * 100), text: `week ${week} of ${weeks}` };
});
// Each year with its three term slots (a missing term shows as "not set up").
const yearRows = computed(() => (cal.value?.years || []).map((y) => ({
  year: y.year,
  slots: [1, 2, 3].map((n) => ({ term: n, t: y.terms.find((t) => Number(t.term) === n) || null })),
})));

const subjectSearch = ref('');
const subjectFilter = ref('all');
const savedSubjects = ref('');
const isOffered = (lv, id) => (offered.value[lv] || []).includes(Number(id));
const isComp = (lv, id) => (comp.value[lv] || []).includes(Number(id));
const shownSubjects = computed(() => {
  const q = subjectSearch.value.trim().toLowerCase();
  return subjects.value.filter((s) => {
    if (q && !`${s.subject_name} ${s.subject_code || ''}`.toLowerCase().includes(q)) return false;
    const o = isOffered('O-Level', s.id);
    const a = isOffered('A-Level', s.id);
    return { all: true, o, a, comp: isComp('O-Level', s.id) || isComp('A-Level', s.id), none: !o && !a }[subjectFilter.value];
  });
});
const snapshot = () => JSON.stringify({ o: [...(offered.value['O-Level'] || [])].sort(), a: [...(offered.value['A-Level'] || [])].sort(), co: [...(comp.value['O-Level'] || [])].sort(), ca: [...(comp.value['A-Level'] || [])].sort() });
const subjectsDirty = computed(() => savedSubjects.value !== '' && snapshot() !== savedSubjects.value);
let savedLists = null;
function discardSubjects() {
  if (!savedLists) return;
  offered.value = JSON.parse(JSON.stringify(savedLists.offered));
  comp.value = JSON.parse(JSON.stringify(savedLists.comp));
}

const promotedCount = computed(() => (preview.value?.promotion?.moves || []).reduce((t, m) => t + m.count, 0));

async function load() {
  try {
    const [c, s] = await Promise.all([academicCalendarAPI.get(), academicCalendarAPI.compulsory()]);
    cal.value = c.data;
    subjects.value = s.data.subjects;
    const ids = (byLevel) => Object.fromEntries(Object.entries(byLevel).map(([lv, list]) => [lv, list.map((x) => Number(x.id))]));
    offered.value = ids(s.data.offered);
    comp.value = ids(s.data.compulsory);
    savedLists = { offered: JSON.parse(JSON.stringify(offered.value)), comp: JSON.parse(JSON.stringify(comp.value)) };
    savedSubjects.value = snapshot();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    loading.value = false;
  }
}

async function openClose() {
  error.value = '';
  preview.value = null;
  Object.assign(form, { start_date: cal.value.next.start_date, end_date: cal.value.next.end_date, copy_requirements: true, confirm: '' });
  dialog.value = true;
  try {
    preview.value = (await academicCalendarAPI.preview()).data;
  } catch (e) {
    error.value = apiError(e).message;
  }
}

async function closeTerm() {
  closing.value = true;
  error.value = '';
  try {
    const res = await academicCalendarAPI.close({ ...form });
    showToast(res.message, 'success', 8000);
    dialog.value = false;
    await termStore.load(true);
    termStore.reset();
    await load();
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    closing.value = false;
  }
}

// Closed terms opened again for data entry, alongside the current term.
const reopened = computed(() => (cal.value?.years || []).flatMap((y) => y.terms).filter((t) => t.status === 'reopened'));
async function closeAgain(t) {
  if (!confirm(`Close ${t.academic_year} Term ${t.term} again? Its open exams close and nothing more can be entered for it.`)) return;
  try {
    showToast((await academicCalendarAPI.closeReopened(t.id)).message);
    await termStore.load(true);
    await load();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
}
const reopenTarget = ref(null);
const reopening = ref(false);
const askReopen = (t) => { reopenTarget.value = t; };
async function reopen() {
  reopening.value = true;
  try {
    showToast((await academicCalendarAPI.reopen(reopenTarget.value.id)).message);
    reopenTarget.value = null;
    await termStore.load(true);
    await load();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    reopening.value = false;
  }
}

async function openTerm(t) {
  if (!confirm(`Open ${t.academic_year} Term ${t.term}?`)) return;
  try {
    showToast((await academicCalendarAPI.open(t.id)).message);
    await termStore.load(true);
    termStore.reset();
    await load();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
}

function toggleOffered(level, id) {
  const list = offered.value[level] || [];
  const on = list.includes(id);
  offered.value = { ...offered.value, [level]: on ? list.filter((x) => x !== id) : [...list, id] };
  // A subject the level no longer offers cannot stay compulsory there.
  if (on) comp.value = { ...comp.value, [level]: (comp.value[level] || []).filter((x) => x !== id) };
}
function toggleComp(level, id) {
  const list = comp.value[level] || [];
  const on = list.includes(id);
  comp.value = { ...comp.value, [level]: on ? list.filter((x) => x !== id) : [...list, id] };
  // A compulsory subject is always offered at that level.
  if (!on && !(offered.value[level] || []).includes(id)) offered.value = { ...offered.value, [level]: [...(offered.value[level] || []), id] };
}
async function saveCompulsory() {
  savingComp.value = true;
  try {
    showToast((await academicCalendarAPI.saveCompulsory({ offered: offered.value, compulsory: comp.value })).message);
    savedLists = { offered: JSON.parse(JSON.stringify(offered.value)), comp: JSON.parse(JSON.stringify(comp.value)) };
    savedSubjects.value = snapshot();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    savingComp.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.term-tile { @apply rounded-xl border px-3.5 py-3 transition; }
.tile-btn { @apply inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 transition hover:border-brand-400 hover:text-brand-800; }
.switch { position: relative; display: inline-flex; height: 1.25rem; width: 2.25rem; flex-shrink: 0; align-items: center; border-radius: 9999px; background: rgb(229 231 235); transition: background-color 0.15s; }
.switch span { position: absolute; left: 0.15rem; height: 0.95rem; width: 0.95rem; border-radius: 9999px; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2); transition: transform 0.15s; }
.switch.on { background: rgb(16 185 129); }
.switch.comp.on { background: rgb(4 120 87); }
.switch.on span { transform: translateX(1rem); }
.switch:focus-visible { outline: 2px solid rgb(16 185 129); outline-offset: 2px; }
html.dark .switch { background: #31413a; }
html.dark .switch.on { background: #10b981; }
html.dark .switch.comp.on { background: #059669; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .tile-btn {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .tile-btn:hover {
  color: #86efac;
}
html.dark .switch span {
  background-color: var(--d-surface);
}
</style>
