<template>
  <div class="space-y-3">
    <!-- Template and import -->
    <div v-if="sheet && rows.length && !locked" class="card flex flex-wrap items-center justify-between gap-3 px-4 py-3">
      <button type="button" class="sheet-outline-btn" @click="downloadTemplate"><FileDown class="h-4 w-4" />Download marks template</button>
      <div class="flex flex-wrap items-center gap-2">
        <span v-if="importNote" class="text-xs" :class="importNote.error ? 'text-red-600' : 'text-brand-700'">{{ importNote.text }}</span>
        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-gray-300">
          <Paperclip class="h-4 w-4 text-gray-400" />
          <span class="max-w-[12rem] truncate">{{ importFile ? importFile.name : 'Choose CSV file' }}</span>
          <input type="file" accept=".csv,text/csv" class="sr-only" @change="pickFile">
        </label>
        <button type="button" class="btn-secondary px-3 py-1.5 text-sm" :disabled="!importFile" @click="importCsv"><Upload class="h-4 w-4" />Import CSV</button>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <div class="min-w-0">
          <h2 class="card-title">{{ title || 'Student roster' }}</h2>
          <p class="text-xs text-gray-500">
            <template v-if="sheet">
              {{ sheet.exam.name }} · enter marks out of {{ max }}<template v-if="scheme.counts">, converted to {{ convertedOutOf }}</template>
              <span v-if="locked" class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-600">{{ termClosed ? `Term ${sheet.exam.term} ${sheet.exam.academic_year} is not the open term, marks are locked` : sheet.exam.status === 'closed' ? 'Closed, marks are locked' : 'Not open for marks yet' }}</span>
            </template>
            <template v-else>Loading…</template>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <span v-if="sheet" class="text-xs text-gray-500 tabular-nums">
            <strong class="text-brand-700">{{ savedCount }}</strong> saved · <strong class="text-amber-700">{{ dirtyCount }}</strong> unsaved · <strong class="text-gray-700">{{ rows.length - enteredCount }}</strong> not entered
          </span>
          <!-- Marks save on their own; this line says where things stand. -->
          <span v-if="!locked && sheet" class="flex items-center gap-1.5 text-xs font-medium" :class="saveState.style" role="status" aria-live="polite">
            <component :is="saveState.icon" class="h-3.5 w-3.5" :class="saving ? 'animate-spin' : ''" />{{ saveState.text }}
          </span>
          <button v-if="saveError && !saving" type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="save">Try again</button>
          <button v-if="closable" type="button" class="btn-secondary" @click="$emit('close')">Close</button>
        </div>
      </div>

      <p v-if="sheet && scheme.note" class="border-b border-gray-100 bg-gray-50/70 px-4 py-2 text-xs text-gray-600 sm:px-5">{{ scheme.note }}</p>

      <div v-if="error" class="m-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
      <div v-if="loading" class="px-5 py-10 text-center text-sm text-gray-500">Loading students…</div>
      <div v-else-if="sheet && !rows.length" class="px-5 py-10 text-center text-sm text-gray-500">No active students are registered in {{ sheet.class.full_class_name }}.</div>

      <div v-else-if="sheet" class="overflow-x-auto">
        <table class="data-table sheet-table">
          <thead>
            <tr>
              <th class="w-10">#</th>
              <th class="w-36">Student no.</th>
              <th>Full name</th>
              <th class="w-32">Raw mark ({{ max }})</th>
              <th class="w-24 text-center">{{ scheme.counts ? `Out of ${convertedOutOf}` : 'Converted' }}</th>
              <th class="w-28">Status</th>
              <th class="hidden w-56 lg:table-cell">Comment</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in rows" :key="r.student_id">
              <td class="text-xs text-gray-500 tabular-nums">{{ i + 1 }}</td>
              <td class="font-mono text-xs text-gray-600">{{ r.admission_number || '-' }}</td>
              <td class="font-medium text-gray-900">{{ r.full_name }}</td>
              <td>
                <input
                  :ref="(el) => (inputs[i] = el)"
                  v-model="r.value"
                  type="text"
                  inputmode="decimal"
                  :placeholder="`0 - ${max}`"
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
              </td>
              <td class="text-center font-semibold tabular-nums text-gray-900">{{ converted(r) }}</td>
              <td><span class="sheet-status" :class="status(r).style">{{ status(r).text }}</span></td>
              <td class="hidden lg:table-cell">
                <input v-model="r.comment" type="text" maxlength="255" class="input py-1.5" placeholder="Optional" :disabled="locked" @input="touch(r)" @blur="flush">
              </td>
            </tr>
          </tbody>
        </table>
        <p class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">
          Marks save as you type. Enter moves to the next student. Leave a box empty for a student who did not sit.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CircleAlert, CircleCheck, FileDown, LoaderCircle, Paperclip, PencilLine, Upload } from 'lucide-vue-next';
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
const importFile = ref(null);
const importNote = ref(null);
let timer = null;
let again = false;
const AUTOSAVE_MS = 1000;

const BASIS_LABEL = { MOT: 'the mid term', EOT: 'the end of term', both: 'the mid term and end of term (averaged)' };

const max = computed(() => Number(sheet.value?.exam?.max_marks || 100));
const isAoi = computed(() => sheet.value?.exam?.exam_type === 'AOI');
const convertedOutOf = computed(() => (isAoi.value ? sheet.value?.scheme?.aoi_each_out_of ?? 10 : sheet.value?.scheme?.exam_out_of ?? 80));
/** How this exam feeds the final mark, in one line. */
const scheme = computed(() => {
  const s = sheet.value?.scheme || {};
  const counts = s.counts !== false;
  let note = '';
  if (isAoi.value) note = `Each AOI counts out of ${s.aoi_each_out_of ?? 10}. The best ${s.aoi_best ?? 2} AOIs make the AOI score out of ${s.aoi_out_of ?? 20}.`;
  else if (counts && s.school_type === 'primary') note = `The final mark (100) comes from ${BASIS_LABEL[s.exam_basis] || BASIS_LABEL.both}.`;
  else if (counts) note = `The ${s.exam_out_of ?? 80} marks come from ${BASIS_LABEL[s.exam_basis] || BASIS_LABEL.both}.`;
  else note = `This exam is recorded but does not count in the final mark: the ${s.exam_out_of ?? 80} comes from ${BASIS_LABEL[s.exam_basis] || BASIS_LABEL.both}.`;
  return { counts, note };
});
const termClosed = computed(() => sheet.value?.exam?.session_open !== undefined && Number(sheet.value.exam.session_open) !== 1);
const locked = computed(() => sheet.value?.exam?.status !== 'open' || termClosed.value);
const dirtyCount = computed(() => rows.value.filter((r) => r.dirty).length);
const invalidCount = computed(() => rows.value.filter((r) => r.invalid).length);
const enteredCount = computed(() => rows.value.filter((r) => String(r.value ?? '').trim() !== '').length);
const savedCount = computed(() => rows.value.filter((r) => !r.dirty && String(r.value ?? '').trim() !== '').length);
const saveState = computed(() => {
  if (saving.value) return { text: 'Saving…', icon: LoaderCircle, style: 'text-gray-500' };
  if (saveError.value) return { text: saveError.value, icon: CircleAlert, style: 'text-red-600' };
  if (invalidCount.value) return { text: `${invalidCount.value} out of range`, icon: CircleAlert, style: 'text-red-600' };
  if (dirtyCount.value) return { text: 'Saving soon…', icon: PencilLine, style: 'text-amber-700' };
  if (lastSaved.value) return { text: `Saved ${lastSaved.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`, icon: CircleCheck, style: 'text-brand-700' };
  return { text: 'Saves automatically', icon: CircleCheck, style: 'text-gray-500' };
});

const parse = (v) => {
  const s = String(v ?? '').trim();
  if (s === '') return null;
  const n = Number(s);
  return Number.isFinite(n) ? n : NaN;
};
const converted = (r) => {
  const n = parse(r.value);
  if (n === null || Number.isNaN(n) || r.invalid || !scheme.value.counts) return '-';
  return String(Math.round((n / max.value) * convertedOutOf.value * 10) / 10);
};
const status = (r) => {
  if (r.invalid) return { text: `0 - ${max.value} only`, style: 'bg-red-50 text-red-700 ring-red-100' };
  if (r.dirty) return { text: 'Unsaved', style: 'bg-amber-50 text-amber-800 ring-amber-100' };
  if (String(r.value ?? '').trim() !== '') return { text: 'Saved', style: 'bg-brand-50 text-brand-800 ring-brand-100' };
  return { text: 'Not entered', style: 'bg-gray-100 text-gray-600 ring-gray-200' };
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

/* CSV template and import ------------------------------------------------ */
const csvCell = (v) => {
  const s = String(v ?? '');
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
};
function downloadTemplate() {
  const lines = [['student_no', 'full_name', `mark_out_of_${max.value}`, 'comment'].join(',')];
  rows.value.forEach((r) => lines.push([r.admission_number, r.full_name, r.value, r.comment].map(csvCell).join(',')));
  const blob = new Blob([`﻿${lines.join('\r\n')}\r\n`], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `${[sheet.value?.exam?.name, props.title].filter(Boolean).join(' ').replace(/[^\w.-]+/g, '_') || 'marks'}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
}
/** Splits CSV text into rows of cells (handles quoted cells). */
function parseCsv(text) {
  const out = [];
  let row = [];
  let cell = '';
  let quoted = false;
  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i];
    if (quoted) {
      if (ch === '"' && text[i + 1] === '"') { cell += '"'; i += 1; } else if (ch === '"') quoted = false; else cell += ch;
    } else if (ch === '"') quoted = true;
    else if (ch === ',') { row.push(cell); cell = ''; } else if (ch === '\n' || ch === '\r') {
      if (ch === '\r' && text[i + 1] === '\n') i += 1;
      row.push(cell); out.push(row); row = []; cell = '';
    } else cell += ch;
  }
  if (cell !== '' || row.length) { row.push(cell); out.push(row); }
  return out.filter((r) => r.some((c) => c.trim() !== ''));
}
const pickFile = (e) => {
  importFile.value = e.target.files?.[0] || null;
  importNote.value = null;
};
async function importCsv() {
  if (!importFile.value) return;
  const lines = parseCsv((await importFile.value.text()).replace(/^﻿/, ''));
  const head = (lines[0] || []).map((h) => h.trim().toLowerCase());
  const col = (re, fallback) => { const i = head.findIndex((h) => re.test(h)); return i >= 0 ? i : fallback; };
  const hasHeader = head.some((h) => /student|name|mark/.test(h));
  const noCol = col(/student.?no|reg|admission/, 0);
  const nameCol = col(/name/, 1);
  const markCol = col(/mark|score/, 2);
  const commentCol = col(/comment/, -1);
  const byNo = new Map(rows.value.map((r) => [String(r.admission_number || '').trim().toLowerCase(), r]));
  const byName = new Map(rows.value.map((r) => [String(r.full_name || '').trim().toLowerCase(), r]));
  let filled = 0;
  let missing = 0;
  (hasHeader ? lines.slice(1) : lines).forEach((cells) => {
    const r = byNo.get(String(cells[noCol] || '').trim().toLowerCase()) || byName.get(String(cells[nameCol] || '').trim().toLowerCase());
    const mark = String(cells[markCol] ?? '').trim();
    if (!r) { missing += 1; return; }
    if (mark === '' && commentCol < 0) return;
    r.value = mark;
    if (commentCol >= 0 && cells[commentCol] !== undefined) r.comment = cells[commentCol].trim();
    touch(r);
    filled += 1;
  });
  importNote.value = filled
    ? { text: `${filled} mark(s) imported${missing ? `, ${missing} row(s) did not match a student` : ''}`, error: false }
    : { text: missing ? 'No row matched a student in this class' : 'The file has no marks', error: true };
  importFile.value = null;
  if (filled) flush();
}

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

<style scoped>
.sheet-outline-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid #059669;
  padding: 0.45rem 0.9rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #047857;
  background: transparent;
  transition: background-color 0.15s;
}
.sheet-outline-btn:hover { background: #ecfdf5; }
.sheet-table td { padding-top: 0.55rem; padding-bottom: 0.55rem; }
.sheet-status {
  display: inline-flex;
  white-space: nowrap;
  border-radius: 9999px;
  padding: 0.15rem 0.6rem;
  font-size: 0.7rem;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px var(--tw-ring-color, transparent);
}
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .sheet-outline-btn {
  color: var(--d-text-2);
}
html.dark .sheet-outline-btn:hover {
  background-color: var(--d-sunken);
}
</style>
