<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Subjects</h1>
        </div>
        <button type="button" class="btn-hero-primary" @click="openForm()"><Plus class="h-4 w-4" />Add subject</button>
      </div>
    </div>

    <StatCards :cards="stats" />

    <ListToolbar v-model:search="q" placeholder="Search subject name or code" :filter-count="level ? 1 : 0" @clear="level = ''">
      <template #filters>
        <label class="block">
          <span class="filter-label">Offered at</span>
          <select v-model="level" class="input py-2">
            <option value="">Any level</option>
            <option value="O-Level">O-Level</option>
            <option value="A-Level">A-Level</option>
            <option value="none">Not offered</option>
          </select>
        </label>
      </template>
      <template #actions>
        <RouterLink to="/academic-calendar" class="tool-btn" title="Choose the subjects each level offers"><Settings2 class="h-4 w-4" /><span class="hidden lg:inline">Levels &amp; compulsory</span></RouterLink>
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div v-if="loading" class="px-5 py-12 text-center text-sm text-gray-500">Loading…</div>
      <div v-else-if="!shown.length" class="px-5 py-14 text-center">
        <BookOpen class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">{{ subjects.length ? 'No subjects match' : 'No subjects yet' }}</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Subject</th>
              <th class="px-4 py-3">O-Level</th>
              <th class="px-4 py-3">A-Level</th>
              <th class="px-4 py-3">Teachers</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="s in shown" :key="s.id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <span class="code-badge">{{ s.subject_code }}</span>
                  <span class="font-medium text-gray-900">{{ s.subject_name }}</span>
                </div>
              </td>
              <td class="px-4 py-3"><span class="pill" :class="levelPill(s, 'O-Level').style">{{ levelPill(s, 'O-Level').label }}</span></td>
              <td class="px-4 py-3"><span class="pill" :class="levelPill(s, 'A-Level').style">{{ levelPill(s, 'A-Level').label }}</span></td>
              <td class="px-4 py-3 tabular-nums" :class="teacherCount(s) ? 'text-gray-800' : 'text-amber-700'">{{ teacherCount(s) || 'None yet' }}</td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${s.subject_name}`" :items="actions(s)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="s in shown" :key="s.id" class="flex items-start gap-3 px-4 py-3">
            <span class="code-badge mt-0.5">{{ s.subject_code }}</span>
            <div class="min-w-0 flex-1">
              <p class="font-medium text-gray-900">{{ s.subject_name }}</p>
              <div class="mt-1 flex flex-wrap gap-1.5">
                <span class="pill" :class="levelPill(s, 'O-Level').style">O: {{ levelPill(s, 'O-Level').label }}</span>
                <span class="pill" :class="levelPill(s, 'A-Level').style">A: {{ levelPill(s, 'A-Level').label }}</span>
                <span class="text-xs text-gray-500">{{ teacherCount(s) }} teacher(s)</span>
              </div>
            </div>
            <RowActionsMenu :label="`Actions for ${s.subject_name}`" :items="actions(s)" />
          </li>
        </ul>
      </template>
    </section>

    <!-- Add / edit -->
    <div v-if="form" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="form = null">
      <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="save">
        <h2 class="text-lg font-semibold text-gray-900">{{ form.id ? 'Edit subject' : 'Add subject' }}</h2>
        <div class="mt-4 space-y-4">
          <label class="block">
            <span class="field-label">Name</span>
            <input v-model="form.subject_name" class="input" placeholder="e.g. Mathematics" required>
          </label>
          <label class="block">
            <span class="field-label">Code</span>
            <input v-model="form.subject_code" class="input uppercase" placeholder="e.g. MATHS" required>
          </label>
        </div>
        <p v-if="!form.id" class="mt-3 text-xs text-gray-500">New subjects are offered at both levels; change that under Settings → Terms &amp; Years.</p>
        <p v-if="formError" class="mt-3 text-sm text-red-600">{{ formError }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="form = null">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { BookOpen, GraduationCap, Pencil, Plus, Settings2, Trash2, UserX } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import { academicCalendarAPI, subjectDepartmentsAPI, subjectsNewAPI } from '../services/api';
import { useToast } from '../composables/useToast.js';

const { showToast } = useToast();
const subjects = ref([]);
const levels = ref({ offered: {}, compulsory: {} });
const teachers = ref({});
const loading = ref(true);
const q = ref('');
const level = ref('');
const form = ref(null);
const formError = ref('');
const saving = ref(false);

const errorText = (e, fallback) => e?.response?.data?.error || e?.response?.data?.message || fallback;
const idsAt = (kind, lv) => new Set((levels.value[kind]?.[lv] || []).map((s) => Number(s.id)));
const offeredAt = (s, lv) => idsAt('offered', lv).has(Number(s.id));
const levelPill = (s, lv) => {
  if (idsAt('compulsory', lv).has(Number(s.id))) return { label: 'Compulsory', style: 'bg-brand-600 text-white' };
  if (offeredAt(s, lv)) return { label: 'Offered', style: 'bg-brand-50 text-brand-800 ring-1 ring-brand-100' };
  return { label: 'Not offered', style: 'bg-gray-100 text-gray-500' };
};
const teacherCount = (s) => teachers.value[Number(s.id)] || 0;

const shown = computed(() => {
  const term = q.value.trim().toLowerCase();
  return subjects.value.filter((s) => {
    if (term && !`${s.subject_name} ${s.subject_code}`.toLowerCase().includes(term)) return false;
    if (level.value === 'none') return !offeredAt(s, 'O-Level') && !offeredAt(s, 'A-Level');
    return !level.value || offeredAt(s, level.value);
  }).sort((a, b) => a.subject_name.localeCompare(b.subject_name));
});
const stats = computed(() => {
  const toggle = (v) => () => { level.value = level.value === v ? '' : v; };
  return [
    { label: 'Subjects', value: subjects.value.length, icon: BookOpen },
    { label: 'O-Level offers', value: subjects.value.filter((s) => offeredAt(s, 'O-Level')).length, icon: GraduationCap, tone: 'sky', onClick: toggle('O-Level'), active: level.value === 'O-Level' },
    { label: 'A-Level offers', value: subjects.value.filter((s) => offeredAt(s, 'A-Level')).length, icon: GraduationCap, tone: 'amber', onClick: toggle('A-Level'), active: level.value === 'A-Level' },
    { label: 'Without teachers', value: subjects.value.filter((s) => !teacherCount(s)).length, icon: UserX, tone: 'gray' },
  ];
});
const actions = (s) => [
  { label: 'Edit', icon: Pencil, onClick: () => openForm(s) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => remove(s) },
];

async function load() {
  loading.value = true;
  try {
    const [subs, lv, depts] = await Promise.all([
      subjectsNewAPI.getAll(),
      academicCalendarAPI.compulsory().catch(() => null),
      subjectDepartmentsAPI.getAll().catch(() => null),
    ]);
    subjects.value = subs.data || [];
    if (lv) levels.value = lv.data;
    if (depts) teachers.value = Object.fromEntries(depts.data.subjects.map((s) => [Number(s.id), s.teachers.length]));
  } catch (e) {
    showToast(errorText(e, 'Could not load subjects'), 'error');
  } finally {
    loading.value = false;
  }
}
function openForm(s = null) {
  formError.value = '';
  form.value = s ? { id: s.id, subject_name: s.subject_name, subject_code: s.subject_code } : { id: null, subject_name: '', subject_code: '' };
}
async function save() {
  saving.value = true;
  formError.value = '';
  try {
    const data = { subject_name: form.value.subject_name.trim(), subject_code: form.value.subject_code.trim().toUpperCase() };
    if (form.value.id) await subjectsNewAPI.update({ id: form.value.id, ...data });
    else await subjectsNewAPI.create(data);
    showToast(form.value.id ? 'Subject updated' : `${data.subject_name} added`);
    form.value = null;
    await load();
  } catch (e) {
    formError.value = errorText(e, 'Could not save the subject');
  } finally {
    saving.value = false;
  }
}
async function remove(s) {
  const n = teacherCount(s);
  if (!confirm(`Delete ${s.subject_name}?${n ? ` ${n} teacher(s) are in its department.` : ''} Marks, timetable lessons and curriculum topics for it are removed too. This cannot be undone.`)) return;
  try {
    await subjectsNewAPI.delete(s.id);
    showToast(`${s.subject_name} deleted`);
    await load();
  } catch (e) {
    showToast(errorText(e, 'Could not delete the subject'), 'error');
  }
}

onMounted(load);
</script>

<style scoped>
.field-label { @apply mb-1 block text-sm font-medium text-gray-700; }
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.pill { @apply inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .field-label {
  color: var(--d-text-2);
}
html.dark .filter-label {
  color: var(--d-muted);
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
