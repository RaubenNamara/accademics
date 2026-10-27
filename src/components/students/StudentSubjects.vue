<template>
  <div :class="editable ? '' : 'rounded-xl border border-slate-200 bg-white p-6 shadow-sm'">
    <h3 class="flex flex-wrap items-center gap-2" :class="editable ? 'mb-1 text-lg font-semibold text-slate-700' : 'text-lg font-bold text-slate-800'">
      <BookMarked v-if="!editable" class="h-5 w-5 text-brand-600" />
      Subjects offered
      <span v-if="lvl" class="text-sm font-normal text-slate-500">· {{ lvl }} · {{ compulsory.length + chosen.length }} subjects</span>
    </h3>
    <p v-if="editable" class="mb-3 text-xs text-slate-500">Compulsory subjects are fixed for the level. Click the {{ lvl === 'A-Level' ? 'subjects in the combination' : 'optional subjects' }} this student takes.</p>

    <div v-if="loading" class="py-3 text-sm text-slate-500">Loading…</div>
    <p v-else-if="error" class="py-2 text-sm text-red-600">{{ error }}</p>
    <p v-else-if="!lvl" class="py-2 text-sm text-slate-500">Choose the class or level first.</p>
    <template v-else>
      <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Compulsory</p>
      <div class="mt-1.5 flex flex-wrap gap-1.5">
        <span v-for="s in compulsory" :key="s.id" class="inline-flex items-center gap-1 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-medium text-white">
          <Lock class="h-3 w-3" />{{ s.subject_name }}
        </span>
        <span v-if="!compulsory.length" class="text-sm text-slate-500">None set, see Settings → Terms &amp; Years.</span>
      </div>

      <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ lvl === 'A-Level' ? 'Combination' : 'Options' }}</p>
      <div v-if="!editable" class="mt-1.5 flex flex-wrap gap-1.5">
        <span v-for="s in chosen" :key="s.id" class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-200">{{ s.subject_name }}</span>
        <span v-if="!chosen.length" class="text-sm text-slate-500">{{ lvl === 'A-Level' ? 'No combination recorded yet, add it under Edit.' : 'No optional subjects recorded yet, add them under Edit.' }}</span>
      </div>
      <div v-else class="mt-1.5 flex flex-wrap gap-1.5">
        <button
          v-for="s in choices"
          :key="s.id"
          type="button"
          class="rounded-full border px-2.5 py-1 text-xs font-medium transition"
          :class="picked.includes(s.id) ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
          :aria-pressed="picked.includes(s.id)"
          @click="toggle(s.id)"
        >{{ s.subject_name }}</button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { BookMarked, Lock } from 'lucide-vue-next';
import { academicCalendarAPI, studentAcademicsAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

/**
 * The subjects a student offers. View mode shows them; edit mode (inside the student form) lets the
 * options be picked and saves them with the form through the exposed save(studentId).
 */
const props = defineProps({
  studentId: { type: Number, default: null },
  level: { type: String, default: '' }, // the form's level when editing (class may have changed)
  editable: Boolean,
});

const config = ref(null); // { offered: {level: [...]}, compulsory: {level: [...]} }
const studentLevel = ref('');
const picked = ref([]);
const loading = ref(true);
const error = ref('');

const lvl = computed(() => (props.editable && props.level) || studentLevel.value || props.level || '');
const compulsory = computed(() => config.value?.compulsory?.[lvl.value] || []);
const choices = computed(() => {
  const comp = new Set(compulsory.value.map((s) => Number(s.id)));
  return (config.value?.offered?.[lvl.value] || []).map((s) => ({ ...s, id: Number(s.id) })).filter((s) => !comp.has(s.id));
});
const chosen = computed(() => choices.value.filter((s) => picked.value.includes(s.id)));

const toggle = (id) => {
  picked.value = picked.value.includes(id) ? picked.value.filter((x) => x !== id) : [...picked.value, id];
};

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const [cfg, mine] = await Promise.all([
      academicCalendarAPI.compulsory(),
      props.studentId ? studentAcademicsAPI.subjects(props.studentId) : Promise.resolve(null),
    ]);
    config.value = cfg.data;
    studentLevel.value = mine?.data?.level || '';
    picked.value = (mine?.data?.options || []).map((s) => Number(s.id));
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
}

/** Saves the picked options (only those offered at the level). Returns the server's result. */
async function save(studentId) {
  const id = studentId || props.studentId;
  if (!id || !config.value) return null;
  const allowed = new Set(choices.value.map((s) => s.id));
  return studentAcademicsAPI.saveSubjects(id, picked.value.filter((x) => allowed.has(x)));
}

watch(() => props.studentId, load);
onMounted(load);
defineExpose({ save, chosen, compulsory });
</script>
