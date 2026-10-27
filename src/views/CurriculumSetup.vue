<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Curriculum Setup</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero" :disabled="importing" @click="reimport"><DownloadCloud class="h-4 w-4" />{{ importing ? 'Importing…' : 'Re-import from eSpace' }}</button>
          <button type="button" class="btn-hero-primary" @click="openForm()"><Plus class="h-4 w-4" />Add topic</button>
        </div>
      </div>
    </div>

    <StatCards :cards="stats" />

    <!-- Filters -->
    <div class="card space-y-4 p-4 sm:p-5">
      <div class="grid gap-3 md:grid-cols-[minmax(0,16rem)_1fr_auto] md:items-end">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Subject</span>
          <select v-model="subjectId" class="input">
            <option v-for="s in subjectOptions" :key="s.id" :value="s.id">{{ s.subject_name }}{{ s.count ? ` · ${s.count}` : '' }}</option>
          </select>
        </label>
        <div>
          <span class="mb-1 block text-sm font-medium text-gray-700">Class</span>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="l in [1, 2, 3, 4, 5, 6]" :key="l" type="button" class="pill" :class="level === l ? 'pill-on' : ''" @click="level = l">
              S.{{ l }}<span class="ml-1 text-[11px] opacity-70">{{ countFor(l) || '' }}</span>
            </button>
          </div>
        </div>
        <div>
          <span class="mb-1 block text-sm font-medium text-gray-700">Term</span>
          <div class="flex gap-1.5">
            <button v-for="t in [0, 1, 2, 3]" :key="t" type="button" class="pill" :class="term === t ? 'pill-on' : ''" @click="term = t">{{ t ? `Term ${t}` : 'All' }}</button>
          </div>
        </div>
      </div>
      <div class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input v-model="q" type="search" class="input pl-9" placeholder="Search topic, theme or competence">
      </div>
    </div>

    <!-- Topics grouped by term and theme -->
    <div v-if="loading" class="card px-5 py-10 text-center text-sm text-gray-500">Loading…</div>
    <div v-else-if="!shown.length" class="card px-5 py-14 text-center">
      <BookOpenCheck class="mx-auto h-9 w-9 text-gray-300" />
      <p class="mt-2 text-sm font-medium text-gray-700">No topics for {{ subjectName }} S.{{ level }}{{ term ? ` Term ${term}` : '' }}</p>
      <button type="button" class="btn-primary mt-4" @click="openForm()"><Plus class="h-4 w-4" />Add topic</button>
    </div>
    <template v-else>
      <section v-for="block in grouped" :key="block.key" class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 bg-gray-50/70 px-5 py-3">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Term {{ block.term }} · Theme / chapter</p>
            <h2 class="truncate text-base font-semibold text-gray-900">{{ block.theme }}</h2>
          </div>
          <span class="text-xs text-gray-500">{{ block.topics.length }} topic(s)</span>
        </div>
        <ul class="divide-y divide-gray-100">
          <li v-for="t in block.topics" :key="t.id" class="px-5 py-4">
            <div class="flex flex-wrap items-start gap-3">
              <span class="flex-shrink-0 rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-100" :title="'Suggested teaching weeks'">{{ weekLabel(t) }}</span>
              <div class="min-w-0 flex-1">
                <p class="font-semibold text-gray-900">{{ t.topic }}</p>
                <p v-if="t.competence" class="mt-0.5 text-sm text-gray-600"><span class="font-medium text-gray-700">Competence:</span> {{ t.competence }}</p>
                <button type="button" class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline" @click="toggle(t.id)">
                  <ChevronRight class="h-3.5 w-3.5 transition" :class="open.has(t.id) ? 'rotate-90' : ''" />{{ t.outcomes.length }} learning outcome(s)
                </button>
                <ol v-if="open.has(t.id)" class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-700">
                  <li v-for="(o, i) in t.outcomes" :key="i">{{ o }}</li>
                </ol>
              </div>
              <div class="flex gap-1">
                <button type="button" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" title="Edit" @click="openForm(t)"><Pencil class="h-4 w-4" /></button>
                <button type="button" class="rounded-lg p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600" title="Delete" @click="remove(t)"><Trash2 class="h-4 w-4" /></button>
              </div>
            </div>
          </li>
        </ul>
      </section>
    </template>

    <!-- Add / edit -->
    <div v-if="form" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="form = null">
      <form class="flex max-h-[92vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-xl" @submit.prevent="save">
        <div class="border-b border-gray-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ form.id ? 'Edit topic' : 'Add topic' }}</h2>
        </div>
        <div class="flex-1 space-y-4 overflow-auto px-6 py-4">
          <div class="grid gap-3 sm:grid-cols-3">
            <label class="block">
              <span class="field-label">Subject</span>
              <select v-model="form.subject_id" class="input" required>
                <option v-for="s in subjectOptions" :key="s.id" :value="s.id">{{ s.subject_name }}</option>
              </select>
            </label>
            <label class="block">
              <span class="field-label">Class</span>
              <select v-model.number="form.class_level" class="input" required>
                <option v-for="l in [1, 2, 3, 4, 5, 6]" :key="l" :value="l">S.{{ l }}</option>
              </select>
            </label>
            <label class="block">
              <span class="field-label">Term</span>
              <select v-model.number="form.term" class="input" required>
                <option v-for="t in [1, 2, 3]" :key="t" :value="t">Term {{ t }}</option>
              </select>
            </label>
          </div>
          <label class="block">
            <span class="field-label">Theme / chapter</span>
            <input v-model="form.theme" class="input" list="cu-themes" maxlength="255" required placeholder="e.g. Computer Systems">
            <datalist id="cu-themes"><option v-for="th in themeOptions" :key="th" :value="th" /></datalist>
            <span v-if="errors.theme" class="field-error">{{ errors.theme }}</span>
          </label>
          <label class="block">
            <span class="field-label">Topic</span>
            <input v-model="form.topic" class="input" maxlength="255" required placeholder="e.g. Computer Software">
            <span v-if="errors.topic" class="field-error">{{ errors.topic }}</span>
          </label>
          <label class="block">
            <span class="field-label">Competence</span>
            <textarea v-model="form.competence" rows="3" class="input" placeholder="The learner …"></textarea>
          </label>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">
              <span class="field-label">Taught from week</span>
              <select v-model="form.week_from" class="input">
                <option :value="null">Not set</option>
                <option v-for="w in weeks" :key="w" :value="w">Week {{ w }}</option>
              </select>
            </label>
            <label class="block">
              <span class="field-label">to week</span>
              <select v-model="form.week_to" class="input">
                <option :value="null">Same week</option>
                <option v-for="w in weeks.filter((x) => !form.week_from || x >= form.week_from)" :key="w" :value="w">Week {{ w }}</option>
              </select>
              <span v-if="errors.week_from" class="field-error">{{ errors.week_from }}</span>
            </label>
          </div>
          <div>
            <div class="flex items-center justify-between">
              <span class="field-label">Learning outcomes <span class="font-normal text-gray-500">· {{ form.outcomes.filter((o) => o.trim()).length }}</span></span>
              <button type="button" class="link-btn" @click="form.outcomes.push('')">+ Add outcome</button>
            </div>
            <div class="space-y-2">
              <div v-for="(o, i) in form.outcomes" :key="i" class="flex items-start gap-2">
                <span class="mt-2.5 w-5 text-right text-xs text-gray-500">{{ i + 1 }}.</span>
                <textarea v-model="form.outcomes[i]" rows="2" class="input flex-1" placeholder="The learner should be able to …"></textarea>
                <button type="button" class="mt-1.5 rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove" @click="form.outcomes.splice(i, 1)"><X class="h-4 w-4" /></button>
              </div>
            </div>
          </div>
        </div>
        <p v-if="formError" class="px-6 pb-2 text-sm text-red-600">{{ formError }}</p>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
          <button type="button" class="btn-secondary" @click="form = null">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save topic' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { BookOpenCheck, ChevronRight, DownloadCloud, Layers, ListTree, Pencil, Plus, Search, Target, Trash2, X } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import { curriculumAPI } from '../services/api.js';
import termStore from '../services/termStore.js';
import { useToast } from '../composables/useToast.js';
import { apiError } from '../utils/timetable.js';

const { showToast } = useToast();
const summary = ref({ rows: [], totals: {}, subjects: [] });
const topics = ref([]);
const subjectId = ref(null);
const level = ref(1);
const term = ref(0);
const q = ref('');
const loading = ref(true);
const importing = ref(false);
const open = ref(new Set());
const form = ref(null);
const errors = ref({});
const formError = ref('');
const saving = ref(false);
const weeks = Array.from({ length: 16 }, (_, i) => i + 1);

const subjectOptions = computed(() => summary.value.subjects.map((s) => ({
  ...s,
  id: Number(s.id),
  count: summary.value.rows.filter((r) => Number(r.subject_id) === Number(s.id)).reduce((t, r) => t + Number(r.topics), 0),
})));
const subjectName = computed(() => subjectOptions.value.find((s) => s.id === subjectId.value)?.subject_name || '');
const countFor = (l) => summary.value.rows.filter((r) => Number(r.subject_id) === subjectId.value && Number(r.class_level) === l).reduce((t, r) => t + Number(r.topics), 0);
const stats = computed(() => [
  { label: 'Topics', value: summary.value.totals.topics ?? '-', icon: ListTree },
  { label: 'Learning outcomes', value: summary.value.totals.outcomes ?? '-', icon: Target, tone: 'sky' },
  { label: 'Subjects with topics', value: subjectOptions.value.filter((s) => s.count).length, icon: BookOpenCheck, tone: 'amber' },
  { label: `${subjectName.value || 'Subject'} S.${level.value}`, value: topics.value.length, note: 'topics', icon: Layers, tone: 'gray' },
]);

const shown = computed(() => {
  const term_ = q.value.trim().toLowerCase();
  return topics.value.filter((t) => !term_ || `${t.topic} ${t.theme} ${t.competence || ''}`.toLowerCase().includes(term_));
});
const grouped = computed(() => {
  const out = [];
  for (const t of shown.value) {
    const key = `${t.term}|${t.theme}`;
    let block = out.find((b) => b.key === key);
    if (!block) out.push((block = { key, term: t.term, theme: t.theme, topics: [] }));
    block.topics.push(t);
  }
  return out;
});
const themeOptions = computed(() => [...new Set(topics.value.map((t) => t.theme))]);
const weekLabel = (t) => (t.week_from ? (t.week_to && t.week_to !== t.week_from ? `Wk ${t.week_from}–${t.week_to}` : `Wk ${t.week_from}`) : 'No week');
const toggle = (id) => {
  const s = new Set(open.value);
  s.has(id) ? s.delete(id) : s.add(id);
  open.value = s;
};

async function loadSummary() {
  summary.value = (await curriculumAPI.summary()).data;
  if (!subjectId.value) subjectId.value = subjectOptions.value.find((s) => s.count)?.id ?? subjectOptions.value[0]?.id ?? null;
}
async function loadTopics() {
  if (!subjectId.value) return;
  loading.value = true;
  try {
    topics.value = (await curriculumAPI.list({ subject_id: subjectId.value, class_level: level.value, term: term.value || undefined })).data;
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    loading.value = false;
  }
}

function openForm(t = null) {
  errors.value = {};
  formError.value = '';
  form.value = t
    ? { ...t, outcomes: [...t.outcomes, ...(t.outcomes.length ? [] : [''])] }
    : { id: null, subject_id: subjectId.value, class_level: level.value, term: term.value || Number(termStore.open.value?.term) || 1, theme: '', topic: '', competence: '', week_from: null, week_to: null, outcomes: [''] };
}
async function save() {
  saving.value = true;
  errors.value = {};
  formError.value = '';
  try {
    const res = await curriculumAPI.save(form.value);
    showToast(res.message);
    form.value = null;
    await Promise.all([loadSummary(), loadTopics()]);
  } catch (e) {
    const err = apiError(e);
    errors.value = err.errors;
    formError.value = Object.keys(err.errors).length ? '' : err.message;
  } finally {
    saving.value = false;
  }
}
async function remove(t) {
  if (!confirm(`Delete “${t.topic}” and its ${t.outcomes.length} learning outcome(s)? Lesson plans that used it keep their text.`)) return;
  try {
    showToast((await curriculumAPI.remove(t.id)).message);
    await Promise.all([loadSummary(), loadTopics()]);
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
}
async function reimport() {
  if (!confirm('Add any eSpace curriculum topics that are missing here? Topics already here (including your edits) are kept.')) return;
  importing.value = true;
  try {
    showToast((await curriculumAPI.reimport()).message);
    await Promise.all([loadSummary(), loadTopics()]);
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    importing.value = false;
  }
}

watch([subjectId, level, term], loadTopics);
onMounted(async () => {
  try {
    await loadSummary();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
  await loadTopics();
});
</script>

<style scoped>
.pill { @apply rounded-full border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50; }
.pill-on { @apply border-brand-600 bg-brand-600 text-white hover:bg-brand-700; }
.field-label { @apply mb-1 block text-sm font-medium text-gray-700; }
.field-error { @apply mt-1 block text-xs text-red-600; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .pill {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .pill:hover {
  background-color: var(--d-sunken);
}
html.dark .field-label {
  color: var(--d-text-2);
}
html.dark .field-error {
  color: #fca5a5;
}
</style>
