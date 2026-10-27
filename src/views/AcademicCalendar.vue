<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <h1 class="page-hero-title">Terms & Years</h1>
    </div>

    <div v-if="loading" class="card px-5 py-10 text-center text-sm text-gray-500">Loading…</div>
    <template v-else-if="cal">
      <!-- The open term -->
      <section class="card p-5">
        <div v-if="cal.open" class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Open term</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ cal.open.academic_year }} · Term {{ cal.open.term }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ formatDate(cal.open.start_date) }} – {{ formatDate(cal.open.end_date) }}</p>
          </div>
          <div class="flex flex-col items-start gap-2 lg:items-end">
            <button v-if="isAdmin" type="button" class="btn-primary" @click="openClose">
              <CalendarCheck2 class="h-4 w-4" />
              {{ Number(cal.open.term) === 3 ? `Close ${cal.open.academic_year} and open ${cal.next.academic_year} Term 1` : `Close Term ${cal.open.term} and open Term ${cal.next.term}` }}
            </button>
            <p v-else class="text-sm text-gray-500">Only an administrator can close a term.</p>
            <p v-if="Number(cal.open.term) === 3" class="max-w-md text-xs text-gray-500 lg:text-right">Closing Term 3 closes the year: S.1–S.3 and S.5 move up a class in the same stream, S.4 become “Completed S.4”, S.6 become “Completed S.6”.</p>
          </div>
        </div>
        <div v-else class="flex flex-wrap items-center justify-between gap-3">
          <p class="text-sm text-amber-800">No term is open. Open one to start entering marks.</p>
        </div>
      </section>

      <!-- Years -->
      <section class="card overflow-hidden">
        <div class="border-b border-gray-100 px-5 py-3"><h2 class="card-title">Years and terms</h2></div>
        <div v-if="!cal.years.length" class="px-5 py-8 text-center text-sm text-gray-500">No terms yet.</div>
        <div v-for="y in cal.years" :key="y.year" class="border-b border-gray-100 px-5 py-4 last:border-0">
          <p class="text-sm font-semibold text-gray-900">{{ y.year }}</p>
          <div class="mt-2 grid gap-2 sm:grid-cols-3">
            <div v-for="t in [...y.terms].sort((a, b) => a.term - b.term)" :key="t.id" class="rounded-xl border px-3 py-2.5" :class="STATUS[t.status].box">
              <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-900">Term {{ t.term }}</p>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="STATUS[t.status].chip">{{ STATUS[t.status].label }}</span>
              </div>
              <p class="mt-0.5 text-xs text-gray-500">{{ formatDate(t.start_date) }} – {{ formatDate(t.end_date) }}</p>
              <p class="text-xs text-gray-500">{{ t.exams }} exam(s)<span v-if="t.closed_at"> · closed {{ formatDate(t.closed_at) }}{{ t.closed_by ? ` by ${t.closed_by}` : '' }}</span></p>
              <button v-if="!cal.open && isAdmin && t.status === 'upcoming'" type="button" class="link-btn mt-1 text-xs" @click="openTerm(t)">Open this term</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Subjects by level -->
      <section class="card p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="card-title">Subjects by level</h2>
          </div>
          <button type="button" class="btn-primary px-3 py-1.5 text-sm" :disabled="savingComp" @click="saveCompulsory">{{ savingComp ? 'Saving…' : 'Save' }}</button>
        </div>
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
          <div v-for="lv in ['O-Level', 'A-Level']" :key="lv" class="rounded-xl border border-gray-200 p-4">
            <p class="text-sm font-semibold text-gray-900">{{ lv }}</p>

            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Offered · {{ offered[lv]?.length || 0 }}</p>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
              <button
                v-for="s in subjects"
                :key="s.id"
                type="button"
                class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                :class="offered[lv]?.includes(Number(s.id)) ? 'border-brand-300 bg-brand-50 text-brand-800' : 'border-dashed border-gray-300 bg-white text-gray-400 line-through'"
                :aria-pressed="offered[lv]?.includes(Number(s.id))"
                :title="offered[lv]?.includes(Number(s.id)) ? `Offered at ${lv}, click to remove` : `Not offered at ${lv}, click to add`"
                @click="toggleOffered(lv, Number(s.id))"
              >{{ s.subject_name }}</button>
            </div>

            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Compulsory · {{ comp[lv]?.length || 0 }}</p>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
              <button
                v-for="s in subjects.filter((x) => offered[lv]?.includes(Number(x.id)))"
                :key="s.id"
                type="button"
                class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                :class="comp[lv]?.includes(Number(s.id)) ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                :aria-pressed="comp[lv]?.includes(Number(s.id))"
                @click="toggleComp(lv, Number(s.id))"
              >{{ s.subject_name }}</button>
            </div>
          </div>
        </div>
      </section>
    </template>

    <!-- Close dialog -->
    <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
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

            <div v-if="preview.year_end" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
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
          <button type="submit" class="btn-primary" :disabled="closing || !preview || (preview.year_end && form.confirm !== String(cal.open.academic_year))">
            {{ closing ? 'Closing…' : preview?.year_end ? 'Close the year' : 'Close term' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { CalendarCheck2 } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import { academicCalendarAPI } from '../services/api.js';
import authStore from '../services/authStore.js';
import termStore from '../services/termStore.js';
import { useToast } from '../composables/useToast.js';
import { apiError } from '../utils/timetable.js';
import { formatDate } from '../components/lessonPlans/planMeta.js';

const STATUS = {
  open: { label: 'Open', chip: 'bg-brand-600 text-white', box: 'border-brand-300 bg-brand-50' },
  closed: { label: 'Closed', chip: 'bg-gray-200 text-gray-700', box: 'border-gray-200 bg-gray-50' },
  upcoming: { label: 'Upcoming', chip: 'bg-white text-gray-600 ring-1 ring-gray-200', box: 'border-gray-200 bg-white' },
};
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

const promotedCount = computed(() => (preview.value?.promotion?.moves || []).reduce((t, m) => t + m.count, 0));

async function load() {
  try {
    const [c, s] = await Promise.all([academicCalendarAPI.get(), academicCalendarAPI.compulsory()]);
    cal.value = c.data;
    subjects.value = s.data.subjects;
    const ids = (byLevel) => Object.fromEntries(Object.entries(byLevel).map(([lv, list]) => [lv, list.map((x) => Number(x.id))]));
    offered.value = ids(s.data.offered);
    comp.value = ids(s.data.compulsory);
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
  comp.value = { ...comp.value, [level]: list.includes(id) ? list.filter((x) => x !== id) : [...list, id] };
}
async function saveCompulsory() {
  savingComp.value = true;
  try {
    showToast((await academicCalendarAPI.saveCompulsory({ offered: offered.value, compulsory: comp.value })).message);
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    savingComp.value = false;
  }
}

onMounted(load);
</script>
