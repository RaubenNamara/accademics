<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Class Teachers</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero px-3" :disabled="loading" title="Refresh" aria-label="Refresh" @click="load"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /></button>
          <button type="button" class="btn-hero-primary" @click="openModal()"><Plus class="h-4 w-4" />Assign class teacher</button>
        </div>
      </div>
    </div>

    <StatCards :cards="stats" />

    <ListToolbar v-model:search="q" placeholder="Search teacher, code or class" :filter-count="(showHistory ? 1 : 0) + (level ? 1 : 0)" @clear="showHistory = false; level = ''; load()">
      <template #filters>
        <label class="block">
          <span class="filter-label">Year</span>
          <input v-model.number="filters.academic_year" type="number" class="input py-2" @change="load">
        </label>
        <label class="block">
          <span class="filter-label">Level</span>
          <select v-model="level" class="input py-2">
            <option value="">All levels</option>
            <option value="O-Level">O-Level</option>
            <option value="A-Level">A-Level</option>
          </select>
        </label>
        <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
          <input v-model="showHistory" type="checkbox" class="h-4 w-4 rounded border-gray-300 accent-brand-600" @change="load"> Include ended assignments
        </label>
      </template>
    </ListToolbar>

    <!-- Streams still without a class teacher -->
    <section v-if="!loading && uncovered.length" class="card overflow-hidden border-amber-200">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-100 bg-amber-50 px-5 py-3">
        <p class="text-sm font-semibold text-amber-900">{{ uncovered.length }} class stream(s) have no class teacher for {{ filters.academic_year }}</p>
      </div>
      <div class="flex flex-wrap gap-2 p-4">
        <button v-for="c in uncovered.slice(0, showAllUncovered ? undefined : 18)" :key="c.id" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-brand-300 hover:bg-brand-50" @click="openModal(c)">
          {{ c.full_class_name }}<Plus class="h-3.5 w-3.5 text-brand-700" />
        </button>
        <button v-if="uncovered.length > 18" type="button" class="link-btn" @click="showAllUncovered = !showAllUncovered">{{ showAllUncovered ? 'Show fewer' : `Show all ${uncovered.length}` }}</button>
      </div>
    </section>

    <section class="card overflow-hidden">
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="i in 4" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-9 w-9 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="!shown.length" class="px-5 py-14 text-center">
        <UsersRound class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">{{ assignments.length ? 'No assignments match' : `No class teachers assigned for ${filters.academic_year}` }}</p>
        <button v-if="!assignments.length" type="button" class="btn-primary mt-4" @click="openModal()"><Plus class="h-4 w-4" />Assign class teacher</button>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Class</th>
              <th class="px-4 py-3">Class teacher</th>
              <th class="px-4 py-3">Year</th>
              <th class="px-4 py-3">Status</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="a in shown" :key="a.id" class="transition hover:bg-brand-50/40" :class="Number(a.is_active) === 1 ? '' : 'opacity-60'">
              <td class="px-5 py-3"><span class="rounded-lg bg-brand-50 px-2.5 py-1 text-sm font-semibold text-brand-800 ring-1 ring-brand-100">{{ classLabel(a) }}</span></td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700">{{ initials(a.teacher_name) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900">{{ a.teacher_name }}</span>
                    <span class="block text-xs text-gray-500">{{ a.teacher_code || 'No code' }}</span>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 tabular-nums text-gray-700">{{ a.academic_year }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="Number(a.is_active) === 1 ? 'text-brand-700' : 'text-gray-500'">
                  <span class="h-2 w-2 rounded-full" :class="Number(a.is_active) === 1 ? 'bg-brand-500' : 'bg-gray-400'"></span>{{ Number(a.is_active) === 1 ? 'Current' : 'Ended' }}
                </span>
              </td>
              <td class="px-3 py-3 text-right"><RowActionsMenu v-if="Number(a.is_active) === 1" :label="`Actions for ${classLabel(a)}`" :items="rowActions(a)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="a in shown" :key="a.id" class="flex items-center gap-3 px-4 py-3" :class="Number(a.is_active) === 1 ? '' : 'opacity-60'">
            <span class="rounded-lg bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ classLabel(a) }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-gray-900">{{ a.teacher_name }}</p>
              <p class="text-xs text-gray-500">{{ a.teacher_code }} · {{ a.academic_year }}{{ Number(a.is_active) === 1 ? '' : ' · ended' }}</p>
            </div>
            <RowActionsMenu v-if="Number(a.is_active) === 1" :label="`Actions for ${classLabel(a)}`" :items="rowActions(a)" />
          </li>
        </ul>
      </template>
    </section>

    <!-- Assign / reassign -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeModal">
      <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="save">
        <h2 class="text-lg font-semibold text-gray-900">{{ reassignId ? `Reassign ${form.class_id} ${form.stream}` : 'Assign a class teacher' }}</h2>
        <div class="mt-4 space-y-4">
          <div v-if="!reassignId" class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="field-label">Class</span>
              <select v-model="form.class_id" class="input" required @change="form.stream = ''">
                <option value="">Choose</option>
                <option v-for="name in uniqueClassNames" :key="name" :value="name">{{ name }}</option>
              </select>
            </label>
            <label class="block">
              <span class="field-label">Stream</span>
              <select v-model="form.stream" class="input" :disabled="!form.class_id">
                <option value="">Whole class</option>
                <option v-for="st in streamsFor(form.class_id)" :key="st" :value="st">{{ st }}</option>
              </select>
            </label>
          </div>
          <label class="block">
            <span class="field-label">Teacher</span>
            <input v-model="teacherSearch" type="search" class="input mb-2" placeholder="Search name or code">
            <select v-model="form.teacher_id" class="input" size="6" required>
              <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
            </select>
          </label>
          <label v-if="!reassignId" class="block">
            <span class="field-label">Year</span>
            <input v-model.number="form.academic_year" type="number" class="input" required>
          </label>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving || !form.teacher_id">{{ saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { CircleCheck, CircleSlash, History, Plus, RefreshCw, Repeat, School, UsersRound } from 'lucide-vue-next';
import { classTeacherAssignmentsAPI, teachersAPI, classesAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';

const { showToast } = useToast();
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const showHistory = ref(false);
const showAllUncovered = ref(false);
const reassignId = ref(null);
const assignments = ref([]);
const teachers = ref([]);
const classes = ref([]);
const q = ref('');
const level = ref('');
const filters = ref({ academic_year: new Date().getFullYear() });
const form = ref({ teacher_id: '', class_id: '', stream: '', academic_year: new Date().getFullYear() });
const teacherSearch = ref('');

const levelOf = (name) => (Number(/(\d)/.exec(name || '')?.[1] || 0) >= 5 ? 'A-Level' : 'O-Level');
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const classLabel = (a) => `${a.class_name}${a.stream ? ` ${a.stream}` : ''}`;
const uniqueClassNames = computed(() => [...new Set(classes.value.map((c) => c.class_name).filter(Boolean))].sort((a, b) => a.localeCompare(b, undefined, { numeric: true })));
const streamsFor = (name) => classes.value.filter((c) => c.class_name === name).map((c) => c.stream_name).sort();

const shown = computed(() => {
  const term = q.value.trim().toLowerCase();
  return assignments.value
    .filter((a) => (!level.value || levelOf(a.class_name) === level.value)
      && (!term || `${a.teacher_name} ${a.teacher_code} ${classLabel(a)}`.toLowerCase().includes(term)))
    .sort((a, b) => classLabel(a).localeCompare(classLabel(b), undefined, { numeric: true }));
});
const current = computed(() => assignments.value.filter((a) => Number(a.is_active) === 1));
// Class streams with no current class teacher (a whole-class assignment covers all its streams).
const uncovered = computed(() => classes.value
  .filter((c) => !current.value.some((a) => a.class_name === c.class_name && (!a.stream || a.stream === c.stream_name)))
  .filter((c) => !level.value || levelOf(c.class_name) === level.value)
  .sort((a, b) => a.full_class_name.localeCompare(b.full_class_name, undefined, { numeric: true })));
const stats = computed(() => [
  { label: 'Class teachers', value: current.value.length, icon: UsersRound },
  { label: 'Streams covered', value: classes.value.length - uncovered.value.length, note: `of ${classes.value.length}`, icon: CircleCheck },
  { label: 'Without a class teacher', value: uncovered.value.length, icon: CircleSlash, tone: uncovered.value.length ? 'amber' : 'gray' },
  { label: 'Ended', value: assignments.value.length - current.value.length, note: showHistory.value ? 'Shown in the list' : 'Turn on in Filters', icon: History, tone: 'gray', onClick: () => { showHistory.value = !showHistory.value; load(); }, active: showHistory.value },
]);
const rowActions = (a) => [
  { label: 'Reassign', icon: Repeat, onClick: () => openReassign(a) },
  { label: 'End assignment', icon: CircleSlash, danger: true, onClick: () => endAssignment(a.id) },
];
const filteredTeachers = computed(() => {
  const term = teacherSearch.value.trim().toLowerCase();
  return term ? teachers.value.filter((t) => `${t.full_name} ${t.teacher_code}`.toLowerCase().includes(term)) : teachers.value;
});

useGlobalTerm((y) => { filters.value.academic_year = y; form.value.academic_year = y; }, () => load());

const load = async () => {
  loading.value = true;
  try {
    const res = await classTeacherAssignmentsAPI.getAll({
      academic_year: filters.value.academic_year,
      history: showHistory.value ? '1' : '0',
      active_only: showHistory.value ? '0' : '1',
    });
    assignments.value = res.success ? res.data || [] : [];
  } finally {
    loading.value = false;
  }
};

const openModal = (cls = null) => {
  reassignId.value = null;
  teacherSearch.value = '';
  form.value = { teacher_id: '', class_id: cls?.class_name || '', stream: cls?.stream_name || '', academic_year: filters.value.academic_year };
  showModal.value = true;
};
const openReassign = (row) => {
  reassignId.value = row.id;
  teacherSearch.value = '';
  const classObj = classes.value.find((c) => c.id === row.class_id);
  form.value = { teacher_id: '', class_id: classObj ? classObj.class_name : row.class_name, stream: row.stream || '', academic_year: row.academic_year };
  showModal.value = true;
};
const closeModal = () => { showModal.value = false; reassignId.value = null; };

const save = async () => {
  saving.value = true;
  try {
    const payload = { ...form.value };
    if (typeof payload.class_id === 'string') {
      const classObj = classes.value.find((c) => c.class_name === payload.class_id);
      if (classObj) payload.class_id = classObj.id;
    }
    const res = reassignId.value
      ? await classTeacherAssignmentsAPI.reassign({ id: reassignId.value, teacher_id: payload.teacher_id })
      : await classTeacherAssignmentsAPI.assign(payload);
    if (res.success) {
      showToast(res.message || 'Saved');
      closeModal();
      load();
    } else showToast(res.message || 'Failed', 'error');
  } catch (e) {
    showToast(e?.response?.data?.message || 'Save failed', 'error');
  } finally {
    saving.value = false;
  }
};

const endAssignment = async (id) => {
  if (!confirm('End this class teacher assignment?')) return;
  await classTeacherAssignmentsAPI.end(id);
  showToast('Assignment ended');
  load();
};

onMounted(async () => {
  const [t, c] = await Promise.all([teachersAPI.getAll(), classesAPI.getAll()]);
  teachers.value = t.success ? t.data.teachers || t.data || [] : [];
  classes.value = c.success ? c.data || [] : [];
  load();
});
</script>

<style scoped>
.field-label { @apply mb-1 block text-sm font-medium text-gray-700; }
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .field-label {
  color: var(--d-text-2);
}
html.dark .filter-label {
  color: var(--d-muted);
}
</style>
