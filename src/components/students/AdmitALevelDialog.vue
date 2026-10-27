<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="$emit('close')">
    <form class="flex max-h-[92vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-xl" @submit.prevent="admit">
      <div class="border-b border-gray-100 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-900">Admit to S.5</h2>
        <p class="text-sm text-gray-500">Students who completed S.4 and came back for A-Level. Choose them by reg no, then their stream and combination. General Paper is added for everyone.</p>
      </div>

      <div class="grid flex-1 gap-5 overflow-auto px-6 py-4 lg:grid-cols-2">
        <div class="min-w-0">
          <p class="text-sm font-semibold text-gray-900">1. Students <span class="font-normal text-gray-500">· {{ chosen.length }} chosen</span></p>
          <input v-model="q" type="search" class="input mt-2 py-2" placeholder="Search reg no or name">
          <div class="mt-2 max-h-72 overflow-auto rounded-xl border border-gray-200">
            <p v-if="loading" class="px-3 py-6 text-center text-sm text-gray-500">Loading…</p>
            <p v-else-if="!shown.length" class="px-3 py-6 text-center text-sm text-gray-500">{{ students.length ? 'No match.' : 'No students are waiting: S.4 finish when a year is closed.' }}</p>
            <label v-for="s in shown" :key="s.id" class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2 last:border-0 hover:bg-gray-50">
              <input v-model="chosen" type="checkbox" :value="Number(s.id)" class="h-4 w-4 rounded border-gray-300 accent-brand-600">
              <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-gray-900">{{ s.admission_number }} · {{ s.full_name }}</span>
                <span class="block text-xs text-gray-500">{{ s.class }} {{ s.stream }}{{ s.completed_year ? ` · finished ${s.completed_year}` : '' }}{{ s.division ? ` · ${s.division}` : '' }}{{ s.aggregate ? ` · agg ${s.aggregate}` : '' }}</span>
              </span>
            </label>
          </div>
        </div>

        <div class="min-w-0 space-y-4">
          <label class="block">
            <span class="text-sm font-semibold text-gray-900">2. S.5 stream</span>
            <select v-model="classId" class="input mt-2" required>
              <option :value="null" disabled>Choose a stream</option>
              <option v-for="c in streams" :key="c.id" :value="Number(c.id)">{{ c.full_class_name }}</option>
            </select>
          </label>
          <div>
            <p class="text-sm font-semibold text-gray-900">3. Combination <span class="font-normal text-gray-500">· {{ combo.length }} chosen</span></p>
            <div class="mt-2 flex flex-wrap gap-1.5">
              <span v-for="s in compulsory" :key="s.id" class="rounded-full bg-brand-600 px-2.5 py-1 text-xs font-medium text-white">{{ s.subject_name }} · compulsory</span>
              <button
                v-for="s in optional"
                :key="s.id"
                type="button"
                class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
                :class="combo.includes(Number(s.id)) ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                :aria-pressed="combo.includes(Number(s.id))"
                @click="toggle(Number(s.id))"
              >{{ s.subject_name }}</button>
            </div>
            <p class="mt-2 text-xs text-gray-500">e.g. Physics, Chemistry, Mathematics + Sub-ICT. Everyone chosen gets the same combination; change one student later in their e-file.</p>
          </div>
        </div>
      </div>

      <p v-if="error" class="px-6 pb-2 text-sm text-red-600">{{ error }}</p>
      <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
        <button type="button" class="btn-secondary" @click="$emit('close')">Cancel</button>
        <button type="submit" class="btn-primary" :disabled="saving || !chosen.length || !classId || combo.length < 2">
          {{ saving ? 'Admitting…' : `Admit ${chosen.length || ''} to S.5` }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { studentAcademicsAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

const emit = defineEmits(['close', 'done']);
const students = ref([]);
const streams = ref([]);
const compulsory = ref([]);
const subjects = ref([]);
const q = ref('');
const chosen = ref([]);
const classId = ref(null);
const combo = ref([]);
const loading = ref(true);
const saving = ref(false);
const error = ref('');

const shown = computed(() => {
  const term = q.value.trim().toLowerCase();
  return term ? students.value.filter((s) => `${s.admission_number} ${s.full_name}`.toLowerCase().includes(term)) : students.value;
});
const optional = computed(() => {
  const comp = new Set(compulsory.value.map((s) => Number(s.id)));
  return subjects.value.filter((s) => !comp.has(Number(s.id)));
});
const toggle = (id) => {
  combo.value = combo.value.includes(id) ? combo.value.filter((x) => x !== id) : [...combo.value, id];
};

async function admit() {
  saving.value = true;
  error.value = '';
  try {
    const res = await studentAcademicsAPI.admitALevel({ student_ids: chosen.value, class_id: classId.value, subject_ids: combo.value });
    emit('done', res.message);
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const d = (await studentAcademicsAPI.awaiting()).data;
    students.value = d.students;
    streams.value = d.streams;
    compulsory.value = d.compulsory;
    subjects.value = d.subjects;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
});
</script>
