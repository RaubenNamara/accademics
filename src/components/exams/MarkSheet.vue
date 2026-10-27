<template>
  <div class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
      <div class="min-w-0">
        <h2 class="card-title">{{ title || 'Mark sheet' }}</h2>
        <p class="text-xs text-gray-500">
          <template v-if="sheet">
            {{ sheet.exam.name }} · out of {{ max }} · {{ enteredCount }} of {{ rows.length }} entered
            <span v-if="locked" class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600">{{ termClosed ? `Term ${sheet.exam.term} ${sheet.exam.academic_year} is not the open term, marks are locked` : sheet.exam.status === 'closed' ? 'Closed, marks are locked' : 'Not open for marks yet' }}</span>
          </template>
          <template v-else>Loading…</template>
        </p>
      </div>
      <div class="flex items-center gap-3">
        <!-- Marks save on their own; this line says where things stand. -->
        <span v-if="!locked && sheet" class="flex items-center gap-1.5 text-xs font-medium" :class="saveState.style" role="status" aria-live="polite">
          <component :is="saveState.icon" class="h-3.5 w-3.5" :class="saving ? 'animate-spin' : ''" />{{ saveState.text }}
        </span>
        <button v-if="saveError && !saving" type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="save">Try again</button>
        <button v-if="closable" type="button" class="btn-secondary" @click="$emit('close')">Close</button>
      </div>
    </div>

    <div v-if="error" class="m-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
    <div v-if="loading" class="px-5 py-10 text-center text-sm text-gray-500">Loading students…</div>
    <div v-else-if="sheet && !rows.length" class="px-5 py-10 text-center text-sm text-gray-500">No active students are registered in {{ sheet.class.full_class_name }}.</div>

    <div v-else-if="sheet" class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th class="w-12">#</th>
            <th>Student</th>
            <th class="w-36">Marks /{{ max }}</th>
            <th class="w-20">%</th>
            <th>Comment (optional)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in rows" :key="r.student_id" :class="r.dirty ? 'bg-amber-50/40' : ''">
            <td class="text-xs text-gray-500">{{ i + 1 }}</td>
            <td>
              <p class="font-medium text-gray-900">{{ r.full_name }}</p>
              <p class="text-xs text-gray-500">{{ r.admission_number }}</p>
            </td>
            <td>
              <input
                :ref="(el) => (inputs[i] = el)"
                v-model="r.value"
                type="text"
                inputmode="decimal"
                class="input py-1.5 text-center tabular-nums"
                :class="r.invalid ? 'border-red-400 ring-2 ring-red-100' : ''"
                :disabled="locked"
                :aria-label="`Marks for ${r.full_name}`"
                @input="touch(r)"
                @blur="flush"
                @keydown.enter.prevent="focusRow(i + 1)"
                @keydown.down.prevent="focusRow(i + 1)"
                @keydown.up.prevent="focusRow(i - 1)"
              >
              <p v-if="r.invalid" class="mt-0.5 text-[11px] text-red-600">0 – {{ max }}</p>
            </td>
            <td class="tabular-nums text-gray-600">{{ pct(r) }}</td>
            <td>
              <input v-model="r.comment" type="text" maxlength="255" class="input py-1.5" :disabled="locked" @input="touch(r)" @blur="flush">
            </td>
          </tr>
        </tbody>
      </table>
      <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">
        Marks save automatically as you type. Press Enter to move to the next student. Leave a box empty for a student who did not sit; clearing a saved mark removes it.
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CircleAlert, CircleCheck, LoaderCircle, PencilLine } from 'lucide-vue-next';
import { examsAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

const props = defineProps({
  examId: { type: Number, required: true },
  classId: { type: Number, required: true },
  subjectId: { type: Number, required: true },
  title: { type: String, default: '' },
  closable: Boolean,
});
const emit = defineEmits(['saved', 'close']);

const sheet = ref(null);
const rows = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const saveError = ref('');
const lastSaved = ref(null);
const inputs = ref([]);
let timer = null;
let again = false;
const AUTOSAVE_MS = 1000;

const max = computed(() => Number(sheet.value?.exam?.max_marks || 100));
const termClosed = computed(() => sheet.value?.exam?.session_open !== undefined && Number(sheet.value.exam.session_open) !== 1);
const locked = computed(() => sheet.value?.exam?.status !== 'open' || termClosed.value);
const dirtyCount = computed(() => rows.value.filter((r) => r.dirty).length);
const invalidCount = computed(() => rows.value.filter((r) => r.invalid).length);
const saveState = computed(() => {
  if (saving.value) return { text: 'Saving…', icon: LoaderCircle, style: 'text-gray-500' };
  if (saveError.value) return { text: saveError.value, icon: CircleAlert, style: 'text-red-600' };
  if (invalidCount.value) return { text: `${invalidCount.value} mark(s) out of range, fix to save`, icon: CircleAlert, style: 'text-red-600' };
  if (dirtyCount.value) return { text: 'Unsaved changes…', icon: PencilLine, style: 'text-amber-700' };
  if (lastSaved.value) return { text: `All changes saved · ${lastSaved.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`, icon: CircleCheck, style: 'text-brand-700' };
  return { text: 'Changes save automatically', icon: CircleCheck, style: 'text-gray-500' };
});
const enteredCount = computed(() => rows.value.filter((r) => String(r.value ?? '').trim() !== '').length);

const parse = (v) => {
  const s = String(v ?? '').trim();
  if (s === '') return null;
  const n = Number(s);
  return Number.isFinite(n) ? n : NaN;
};
const pct = (r) => {
  const n = parse(r.value);
  return n === null || Number.isNaN(n) || r.invalid ? '' : `${Math.round((n / max.value) * 1000) / 10}`;
};
const touch = (r) => {
  r.dirty = true;
  r.rev += 1;
  const n = parse(r.value);
  r.invalid = n !== null && (Number.isNaN(n) || n < 0 || n > max.value);
  clearTimeout(timer);
  timer = setTimeout(save, AUTOSAVE_MS);
};
/** Leaving a box saves straight away instead of waiting for the pause. */
const flush = () => {
  if (!dirtyCount.value) return;
  clearTimeout(timer);
  save();
};
const focusRow = (i) => inputs.value[Math.max(0, Math.min(i, rows.value.length - 1))]?.select();

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const res = await examsAPI.sheet(props.examId, props.classId, props.subjectId);
    sheet.value = res.data;
    rows.value = res.data.students.map((s) => ({ ...s, value: s.marks ?? '', comment: s.comment ?? '', dirty: false, invalid: false, rev: 0 }));
    lastSaved.value = null;
    saveError.value = '';
  } catch (e) {
    sheet.value = null;
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
}

async function save() {
  clearTimeout(timer);
  if (locked.value) return;
  if (saving.value) {
    again = true; // typed while a save was running: save once more when it finishes
    return;
  }
  // Out-of-range marks stay unsaved (and red) until fixed; the rest save now.
  const batch = rows.value.filter((r) => r.dirty && !r.invalid).map((r) => ({ row: r, rev: r.rev }));
  if (!batch.length) return;
  saving.value = true;
  saveError.value = '';
  try {
    await examsAPI.saveMarks({
      exam_id: props.examId,
      class_id: props.classId,
      subject_id: props.subjectId,
      rows: batch.map(({ row }) => ({ student_id: row.student_id, marks: parse(row.value), comment: row.comment })),
    });
    // A row edited again during the save stays dirty and goes in the next save.
    batch.forEach(({ row, rev }) => { if (row.rev === rev) row.dirty = false; });
    lastSaved.value = new Date();
    emit('saved');
  } catch (e) {
    const err = apiError(e);
    saveError.value = err.status === 0 ? 'Not saved, check your connection' : err.message;
    if (err.status === 423) await load(); // the exam was closed meanwhile
  } finally {
    saving.value = false;
  }
  if (again) {
    again = false;
    save();
  }
}

const warnUnsaved = (e) => {
  if (dirtyCount.value || saving.value) {
    e.preventDefault();
    e.returnValue = '';
  }
};
onMounted(() => window.addEventListener('beforeunload', warnUnsaved));
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', warnUnsaved);
  if (dirtyCount.value) save(); // switching exam/class or leaving the page: keep what was typed
});

watch(() => [props.examId, props.classId, props.subjectId], load);
onMounted(load);
defineExpose({ dirtyCount });
</script>
