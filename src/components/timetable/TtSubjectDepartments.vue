<template>
  <div class="space-y-4">
    <div class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="card-title">Subject Departments</h2>
        <p class="text-xs text-slate-500">
          Put teachers under the subjects they teach. When you timetable a subject, only its department’s teachers are offered.
          <template v-if="data">{{ data.teachers_in_a_department }} of {{ data.active_teachers }} active teachers are in a department.</template>
        </p>
      </div>
      <button v-if="canEdit" type="button" class="btn-secondary shrink-0" :disabled="syncing" @click="sync">
        <i class="fas" :class="syncing ? 'fa-circle-notch fa-spin' : 'fa-rotate'"></i>Fill from teacher records
      </button>
    </div>

    <div v-if="loading && !data" class="card space-y-2 p-5"><div v-for="i in 5" :key="i" class="h-10 animate-pulse rounded-xl bg-slate-100"></div></div>

    <div v-else-if="data" class="grid gap-4 lg:grid-cols-[18rem_1fr]">
      <!-- Subjects -->
      <aside class="card overflow-hidden">
        <div class="border-b border-slate-100 p-3">
          <input v-model="subjectQuery" type="search" class="input" placeholder="Find a subject" />
        </div>
        <ul class="max-h-[32rem] overflow-y-auto py-1">
          <li v-for="s in filteredSubjects" :key="s.id">
            <button
              type="button"
              class="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm transition"
              :class="s.id === selectedId ? 'bg-brand-50 font-semibold text-brand-900' : 'text-slate-700 hover:bg-slate-50'"
              @click="selectedId = s.id"
            >
              <span class="truncate">{{ s.subject_name }}</span>
              <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold" :class="s.teachers.length ? 'bg-brand-100 text-brand-800' : 'bg-amber-100 text-amber-800'">
                {{ s.teachers.length }}
              </span>
            </button>
          </li>
        </ul>
      </aside>

      <!-- Members -->
      <section v-if="selected" class="card overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
          <div>
            <h3 class="text-lg font-bold text-slate-900">{{ selected.subject_name }} department</h3>
            <p class="text-xs text-slate-500">{{ selected.teachers.length }} teacher(s) · code {{ selected.subject_code }}</p>
          </div>
        </div>

        <div v-if="canEdit" class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-end">
          <div class="flex-1">
            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="dept-add">Add a teacher to {{ selected.subject_name }}</label>
            <TtSearchSelect id="dept-add" v-model="toAdd" :options="addable" label="full_name" :hint="(t) => [t.teacher_code, t.subject].filter(Boolean).join(' · ')" noun="teachers" placeholder="Choose a teacher" />
          </div>
          <button type="button" class="btn-primary" :disabled="!toAdd || adding" @click="add">
            <i class="fas" :class="adding ? 'fa-circle-notch fa-spin' : 'fa-user-plus'"></i>Add
          </button>
        </div>

        <p v-if="!selected.teachers.length" class="px-5 py-10 text-center text-sm text-slate-500">
          No teachers in {{ selected.subject_name }} yet. Until someone is added, any teacher can be chosen for this subject.
        </p>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="t in selected.teachers" :key="t.id" class="flex items-center justify-between gap-3 px-5 py-3">
            <div class="flex min-w-0 items-center gap-3">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ initials(t.full_name) }}</span>
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-900">{{ t.full_name }}</p>
                <p class="text-xs text-slate-500">{{ t.teacher_code }}<span v-if="!t.is_active" class="ml-1 text-amber-700">· inactive</span></p>
              </div>
            </div>
            <button v-if="canEdit" type="button" class="text-sm font-medium text-red-600 hover:underline" @click="remove(t)">Remove</button>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { subjectDepartmentsAPI, timetableAPI } from '../../services/api.js';
import authStore from '../../services/authStore.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';
import TtSearchSelect from './TtSearchSelect.vue';

const emit = defineEmits(['changed']);
const { showToast } = useToast();
const canEdit = computed(() => ['super_admin', 'admin', 'teacher'].includes(authStore.getRole()));

const data = ref(null);
const allTeachers = ref([]);
const loading = ref(false);
const syncing = ref(false);
const adding = ref(false);
const selectedId = ref(null);
const subjectQuery = ref('');
const toAdd = ref(null);

const filteredSubjects = computed(() => {
  const q = subjectQuery.value.trim().toLowerCase();
  return (data.value?.subjects || []).filter((s) => !q || `${s.subject_name} ${s.subject_code}`.toLowerCase().includes(q));
});
const selected = computed(() => data.value?.subjects.find((s) => s.id === selectedId.value) || null);
const addable = computed(() => {
  const inDept = new Set((selected.value?.teachers || []).map((t) => t.id));
  return allTeachers.value.filter((t) => t.is_active && !inDept.has(t.id));
});
const initials = (n) => n.split(/\s+/).filter(Boolean).map((p) => p[0]).join('').slice(0, 2).toUpperCase();

const load = async () => {
  loading.value = true;
  try {
    data.value = (await subjectDepartmentsAPI.getAll()).data;
    // Open on a department that has teachers, so the panel is not empty at first.
    if (!selectedId.value) selectedId.value = (data.value.subjects.find((s) => s.teachers.length) || data.value.subjects[0])?.id ?? null;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    loading.value = false;
  }
};

const changed = async () => {
  await load();
  emit('changed');
};

const add = async () => {
  adding.value = true;
  try {
    const res = await subjectDepartmentsAPI.add(selectedId.value, [toAdd.value]);
    showToast(res.message);
    toAdd.value = null;
    await changed();
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    adding.value = false;
  }
};

const remove = async (t) => {
  if (!confirm(`Remove ${t.full_name} from ${selected.value.subject_name}?`)) return;
  try {
    await subjectDepartmentsAPI.remove(selectedId.value, t.id);
  } catch (err) {
    const e = apiError(err);
    if (!e.data?.needs_confirmation || !confirm(e.message)) {
      if (!e.data?.needs_confirmation) showToast(e.message, 'error');
      return;
    }
    await subjectDepartmentsAPI.remove(selectedId.value, t.id, true);
  }
  showToast(`${t.full_name} removed from ${selected.value.subject_name}`);
  await changed();
};

const sync = async () => {
  syncing.value = true;
  try {
    const preview = (await subjectDepartmentsAPI.syncFromTeacherRecords(true)).data;
    const missing = preview.teachers_without_subject.length;
    if (!confirm(`Teacher records list ${preview.links_found} teacher–subject link(s). Add any that are missing? Nothing is removed.`
      + (missing ? `\n\n${missing} active teacher(s) have no subject in their record and must be added by hand.` : ''))) return;
    const res = await subjectDepartmentsAPI.syncFromTeacherRecords(false);
    showToast(res.message, 'success', 5000);
    await changed();
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    syncing.value = false;
  }
};

onMounted(async () => {
  await load();
  try {
    allTeachers.value = (await timetableAPI.meta()).data.teachers;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
});
</script>
