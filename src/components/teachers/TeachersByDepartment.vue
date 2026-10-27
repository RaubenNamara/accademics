<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="relative w-full sm:w-72">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input v-model="q" type="search" class="input py-2 pl-9" placeholder="Find a teacher or department">
      </div>
      <p v-if="data" class="text-sm text-gray-600">
        <strong class="text-gray-900">{{ data.teachers_in_a_department }}</strong> of {{ data.active_teachers }} teachers are in a department
      </p>
      <button type="button" class="btn-secondary ml-auto px-3 py-1.5 text-sm" :disabled="syncing" title="Add teachers to departments from the subjects in their records" @click="sync">
        <RefreshCw class="h-4 w-4" :class="syncing ? 'animate-spin' : ''" />Fill from teacher records
      </button>
    </div>

    <div v-if="loading" class="card px-5 py-12 text-center text-sm text-gray-500">Loading departments…</div>
    <template v-else-if="data">
      <!-- Teachers not yet in a department -->
      <section v-if="unassignedShown.length" class="card overflow-hidden border-amber-200">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-100 bg-amber-50 px-5 py-3">
          <div>
            <h2 class="text-sm font-semibold text-amber-900">Not in a department ({{ data.unassigned.length }})</h2>
            <p class="text-xs text-amber-800">Their records have no subject. Choose the department each one belongs to.</p>
          </div>
        </div>
        <ul class="divide-y divide-gray-100">
          <li v-for="t in unassignedShown" :key="t.id" class="flex flex-wrap items-center gap-3 px-5 py-2.5">
            <span class="avatar bg-amber-100 text-amber-800">{{ initials(t.full_name) }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-gray-900">{{ t.full_name }}</p>
              <p class="text-xs text-gray-500">{{ t.teacher_code || 'No code' }}</p>
            </div>
            <select class="input w-56 py-1.5 text-sm" :disabled="busy" aria-label="Department" @change="assign(t, Number($event.target.value)); $event.target.value = ''">
              <option value="">Add to department…</option>
              <option v-for="s in data.subjects" :key="s.id" :value="s.id">{{ s.subject_name }}</option>
            </select>
          </li>
        </ul>
      </section>

      <!-- Departments with teachers -->
      <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <section v-for="s in staffedShown" :key="s.id" class="card flex flex-col overflow-hidden">
          <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3.5">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-600 text-xs font-bold text-white">{{ shortCode(s) }}</span>
            <div class="min-w-0 flex-1">
              <h2 class="truncate font-semibold text-gray-900">{{ s.subject_name }}</h2>
              <p class="text-xs text-gray-500">{{ s.teachers.length }} teacher{{ s.teachers.length === 1 ? '' : 's' }}</p>
            </div>
          </div>
          <ul class="max-h-80 flex-1 divide-y divide-gray-50 overflow-auto">
            <li v-for="t in filteredTeachers(s)" :key="t.id" class="group flex items-center gap-3 px-5 py-2">
              <span class="avatar" :class="t.is_active ? 'bg-brand-50 text-brand-800' : 'bg-gray-100 text-gray-500'">{{ initials(t.full_name) }}</span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium" :class="t.is_active ? 'text-gray-900' : 'text-gray-400 line-through'">{{ t.full_name }}</p>
                <p class="text-xs text-gray-500">{{ t.teacher_code || 'No code' }}<span v-if="(memberships[t.id] || 0) > 1"> · also teaches {{ otherSubjects(t.id, s.id) }}</span></p>
              </div>
              <button type="button" class="rounded-lg p-1 text-gray-300 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 focus:opacity-100" :title="`Remove from ${s.subject_name}`" @click="unassign(s, t)"><X class="h-4 w-4" /></button>
            </li>
          </ul>
          <div class="border-t border-gray-100 px-5 py-2.5">
            <select class="input py-1.5 text-sm" :disabled="busy" :aria-label="`Add a teacher to ${s.subject_name}`" @change="addTo(s, Number($event.target.value)); $event.target.value = ''">
              <option value="">+ Add a teacher to {{ s.subject_name }}</option>
              <option v-for="t in candidates(s)" :key="t.id" :value="t.id">{{ t.full_name }}{{ t.teacher_code ? ` (${t.teacher_code})` : '' }}</option>
            </select>
          </div>
        </section>
      </div>

      <!-- Departments with nobody yet -->
      <section v-if="emptyShown.length" class="card p-5">
        <h2 class="text-sm font-semibold text-gray-900">Departments with no teachers yet</h2>
        <div class="mt-3 flex flex-wrap gap-2">
          <div v-for="s in emptyShown" :key="s.id" class="flex items-center gap-2 rounded-xl border border-dashed border-gray-300 py-1 pl-3 pr-1">
            <span class="text-sm text-gray-700">{{ s.subject_name }}</span>
            <select class="input w-40 py-1 text-xs" :disabled="busy" :aria-label="`Add a teacher to ${s.subject_name}`" @change="addTo(s, Number($event.target.value)); $event.target.value = ''">
              <option value="">+ Add teacher</option>
              <option v-for="t in candidates(s)" :key="t.id" :value="t.id">{{ t.full_name }}</option>
            </select>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RefreshCw, Search, X } from 'lucide-vue-next';
import { subjectDepartmentsAPI } from '../../services/api.js';

const emit = defineEmits(['toast']);
const data = ref(null);
const loading = ref(true);
const busy = ref(false);
const syncing = ref(false);
const q = ref('');

const message = (e, fallback) => e?.response?.data?.message || e?.message || fallback;
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const shortCode = (s) => String(s.subject_code || s.subject_name).replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase();

// Every teacher once (department members + unassigned), for the "add a teacher" lists.
const allTeachers = computed(() => {
  const map = new Map();
  for (const s of data.value?.subjects || []) for (const t of s.teachers) map.set(t.id, t);
  for (const t of data.value?.unassigned || []) map.set(Number(t.id), { ...t, id: Number(t.id), is_active: 1 });
  return [...map.values()].sort((a, b) => a.full_name.localeCompare(b.full_name));
});
const memberships = computed(() => {
  const m = {};
  for (const s of data.value?.subjects || []) for (const t of s.teachers) m[t.id] = (m[t.id] || 0) + 1;
  return m;
});
const otherSubjects = (teacherId, subjectId) => (data.value?.subjects || [])
  .filter((s) => s.id !== subjectId && s.teachers.some((t) => t.id === teacherId)).map((s) => s.subject_name).join(', ');
const candidates = (s) => allTeachers.value.filter((t) => t.is_active && !s.teachers.some((m) => m.id === t.id));

const term = computed(() => q.value.trim().toLowerCase());
const matchesTeacher = (t) => `${t.full_name} ${t.teacher_code || ''}`.toLowerCase().includes(term.value);
const filteredTeachers = (s) => (!term.value || s.subject_name.toLowerCase().includes(term.value) ? s.teachers : s.teachers.filter(matchesTeacher));
const staffedShown = computed(() => (data.value?.subjects || [])
  .filter((s) => s.teachers.length && (!term.value || s.subject_name.toLowerCase().includes(term.value) || s.teachers.some(matchesTeacher)))
  .sort((a, b) => b.teachers.length - a.teachers.length || a.subject_name.localeCompare(b.subject_name)));
const emptyShown = computed(() => (term.value ? [] : (data.value?.subjects || []).filter((s) => !s.teachers.length)));
const unassignedShown = computed(() => (data.value?.unassigned || []).filter((t) => !term.value || matchesTeacher(t)));

async function load() {
  try {
    data.value = (await subjectDepartmentsAPI.getAll()).data;
  } catch (e) {
    emit('toast', message(e, 'Could not load departments'), 'error');
  } finally {
    loading.value = false;
  }
}
async function run(fn) {
  busy.value = true;
  try {
    const res = await fn();
    if (res?.message) emit('toast', res.message, 'success');
    await load();
  } catch (e) {
    emit('toast', message(e, 'Something went wrong'), 'error');
  } finally {
    busy.value = false;
  }
}
const assign = (t, subjectId) => subjectId && run(() => subjectDepartmentsAPI.add(subjectId, [Number(t.id)]));
const addTo = (s, teacherId) => teacherId && run(() => subjectDepartmentsAPI.add(s.id, [teacherId]));
async function unassign(s, t) {
  if (!confirm(`Remove ${t.full_name} from ${s.subject_name}?`)) return;
  busy.value = true;
  try {
    let res;
    try {
      res = await subjectDepartmentsAPI.remove(s.id, t.id);
    } catch (e) {
      // The server asks first when the teacher has lessons of this subject on the timetable.
      if (e?.response?.status === 409 && confirm(message(e, 'This teacher has lessons of this subject. Remove anyway?'))) {
        res = await subjectDepartmentsAPI.remove(s.id, t.id, true);
      } else {
        throw e;
      }
    }
    emit('toast', res?.message || 'Removed', 'success');
    await load();
  } catch (e) {
    if (e?.response?.status !== 409) emit('toast', message(e, 'Could not remove'), 'error');
  } finally {
    busy.value = false;
  }
}
async function sync() {
  syncing.value = true;
  try {
    const res = await subjectDepartmentsAPI.syncFromTeacherRecords(false);
    emit('toast', res.message, 'success');
    await load();
  } catch (e) {
    emit('toast', message(e, 'Could not fill departments'), 'error');
  } finally {
    syncing.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.avatar { @apply flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-[11px] font-semibold; }
</style>
