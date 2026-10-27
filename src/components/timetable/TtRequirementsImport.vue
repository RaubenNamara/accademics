<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Import lesson requirements">
    <div class="absolute inset-0 bg-slate-900/40" @click="!busy && $emit('close')"></div>
    <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
      <header class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
        <div>
          <h2 class="text-lg font-bold text-slate-900">Import lesson requirements</h2>
          <p class="text-sm text-slate-500">{{ session.session_name }}, fill the Excel template, upload it, check the preview, then import.</p>
        </div>
        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Close" :disabled="busy" @click="$emit('close')">
          <i class="fas fa-xmark"></i>
        </button>
      </header>

      <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
        <ol class="grid gap-3 sm:grid-cols-3">
          <li class="rounded-2xl border border-slate-200 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-700">Step 1</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">Download the template</p>
            <p class="mt-1 text-xs text-slate-500">It lists your exact class, subject, teacher and room names{{ existing.length ? ' and this term’s current requirements' : '' }}.</p>
            <button type="button" class="btn-primary mt-3 w-full" :disabled="!meta" @click="downloadTemplate">
              <i class="fas fa-file-excel"></i>Download template
            </button>
          </li>
          <li class="rounded-2xl border border-slate-200 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-700">Step 2</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">Fill it in Excel</p>
            <p class="mt-1 text-xs text-slate-500">One row per class and subject: teacher, periods per week, and whether doubles are allowed.</p>
          </li>
          <li class="rounded-2xl border border-slate-200 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-700">Step 3</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">Upload and check</p>
            <label class="btn-secondary mt-3 w-full cursor-pointer" :class="busy ? 'pointer-events-none opacity-50' : ''">
              <i class="fas fa-upload"></i>{{ fileName ? 'Choose another file' : 'Choose file' }}
              <input type="file" class="sr-only" accept=".xlsx,.xls,.csv" @change="onFile" />
            </label>
            <p v-if="fileName" class="mt-1 truncate text-xs text-slate-500" :title="fileName"><i class="fas fa-file mr-1"></i>{{ fileName }}</p>
          </li>
        </ol>

        <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><i class="fas fa-circle-exclamation mr-1"></i>{{ error }}</div>
        <div v-if="busy === 'preview'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"><i class="fas fa-circle-notch fa-spin mr-1"></i>Checking every row on the server…</div>

        <template v-if="preview">
          <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl bg-brand-50 p-4"><p class="text-2xl font-bold text-brand-800">{{ preview.valid }}</p><p class="text-xs text-brand-800">rows ready to import</p></div>
            <div class="rounded-2xl p-4" :class="preview.errors.length ? 'bg-red-50' : 'bg-slate-50'"><p class="text-2xl font-bold" :class="preview.errors.length ? 'text-red-700' : 'text-slate-400'">{{ preview.errors.length }}</p><p class="text-xs text-slate-600">rows with problems (will be skipped)</p></div>
            <div class="rounded-2xl p-4" :class="preview.warnings.length ? 'bg-amber-50' : 'bg-slate-50'"><p class="text-2xl font-bold" :class="preview.warnings.length ? 'text-amber-700' : 'text-slate-400'">{{ preview.warnings.length }}</p><p class="text-xs text-slate-600">warnings</p></div>
          </div>

          <section v-if="preview.errors.length" class="overflow-hidden rounded-2xl border border-red-200">
            <p class="bg-red-50 px-4 py-2 text-sm font-semibold text-red-800">Fix these rows in Excel and upload again (row numbers match the spreadsheet)</p>
            <ul class="max-h-56 divide-y divide-red-100 overflow-y-auto">
              <li v-for="e in preview.errors" :key="e.line" class="flex gap-3 px-4 py-2 text-sm">
                <span class="w-14 shrink-0 font-semibold text-red-700">Row {{ e.line }}</span>
                <span class="min-w-0">
                  <span class="text-slate-500">{{ [e.class, e.subject].filter(Boolean).join(' · ') }}</span>
                  <span v-for="(m, i) in e.messages" :key="i" class="block text-slate-800">{{ m }}</span>
                </span>
              </li>
            </ul>
          </section>

          <ul v-if="preview.warnings.length" class="space-y-1 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <li v-for="(w, i) in preview.warnings" :key="i"><i class="fas fa-triangle-exclamation mr-1"></i>{{ w }}</li>
          </ul>

          <label v-if="existing.length && preview.valid" class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 text-sm text-slate-700">
            <input v-model="replace" type="checkbox" class="mt-0.5 accent-brand-600" />
            <span>
              <strong>Replace</strong> this term’s requirements with the file. Without this, rows in the file are added or updated
              and any of the term’s {{ existing.length }} existing requirement(s) not in the file are kept.
            </span>
          </label>
        </template>

        <div v-if="done" class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
          <i class="fas fa-circle-check mr-1"></i>
          Imported: {{ done.created }} added, {{ done.updated }} updated<template v-if="done.removed">, {{ done.removed }} removed</template>.
          Next: open the <strong>Timetable</strong> tab and click <strong>Generate Timetable</strong>.
        </div>
      </div>

      <footer class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
        <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ done ? 'Close' : 'Cancel' }}</button>
        <button
          v-if="!done"
          type="button"
          class="btn-primary"
          :disabled="busy || !preview || !preview.valid"
          @click="importNow"
        >
          <i class="fas" :class="busy === 'import' ? 'fa-circle-notch fa-spin' : 'fa-file-import'"></i>
          {{ busy === 'import' ? 'Importing…' : preview?.valid ? `Import ${preview.valid} row(s)` : 'Import' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import * as XLSX from 'xlsx';
import { lessonRequirementsAPI, timetableAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

const props = defineProps({
  session: { type: Object, required: true },
  existing: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'imported']);

const HEADERS = ['Class', 'Subject', 'Teacher', 'Periods per week', 'Lesson length', 'Double allowed', 'Double required', 'Room', 'Preferred days', 'Avoid days', 'Notes'];
const LENGTHS = { 1: 'Single', 2: 'Double', 3: 'Triple', 4: 'Quadruple' };

const meta = ref(null);
const rows = ref([]);
const fileName = ref('');
const preview = ref(null);
const done = ref(null);
const error = ref('');
const busy = ref(false);
const replace = ref(false);

timetableAPI.meta().then((r) => { meta.value = r.data; }).catch((e) => { error.value = apiError(e).message; });

const sheet = (aoa, widths) => {
  const ws = XLSX.utils.aoa_to_sheet(aoa);
  ws['!cols'] = widths.map((w) => ({ wch: w }));
  return ws;
};

const downloadTemplate = () => {
  const m = meta.value;
  const byId = (list, id) => list.find((x) => x.id === Number(id));
  const yesNo = (v) => (Number(v) ? 'Yes' : 'No');
  const days = (raw) => {
    try { return (Array.isArray(raw) ? raw : JSON.parse(raw || '[]')).join(', '); } catch { return ''; }
  };
  const current = props.existing.map((r) => [
    byId(m.classes, r.class_id)?.full_class_name || '',
    byId(m.subjects, r.subject_id)?.subject_name || '',
    byId(m.teachers, r.teacher_id)?.full_name || '',
    Number(r.periods_per_week) || '',
    LENGTHS[r.preferred_span] || '',
    yesNo(r.double_lesson_allowed),
    yesNo(r.double_lesson_required),
    byId(m.rooms, r.room_id)?.room_code || '',
    days(r.preferred_days),
    days(r.avoid_days),
    r.notes || '',
  ]);
  const lessonPeriods = m.periods.filter((p) => p.period_type === 'lesson').length;

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, sheet([
    ['How to fill this template'],
    [],
    ['1. Use the "Requirements" sheet. Add ONE row for each class and subject.'],
    ['2. Copy names exactly from the "Classes", "Subjects", "Teachers" and "Rooms" sheets (capital letters and extra spaces do not matter).'],
    ['3. Class must include the stream, e.g. "S1 A", not just "S1". A teacher can be written by full name or by code (e.g. TS-012).'],
    [`4. Periods per week: how many ${m.periods.find((p) => p.period_type === 'lesson') ? '40-minute ' : ''}periods the subject needs each week. A class has ${lessonPeriods * m.days.length} lesson periods in a week (${lessonPeriods} a day × ${m.days.length} days).`],
    ['5. Lesson length (optional): Single or Double for S1–S4; Single, Double, Triple or Quadruple for S5–S6. Leave blank for no preference.'],
    ['6. Double allowed / Double required: Yes or No.'],
    ['7. Room (optional): a room code from the "Rooms" sheet, e.g. a lab for practical subjects.'],
    ['8. Preferred days / Avoid days (optional): e.g. "Mon, Wed".'],
    ['9. Save the file and upload it in Timetable → Lesson Requirements → Import. You will see a preview before anything is saved.'],
    ['10. Uploading again updates rows with the same class, subject and teacher instead of duplicating them.'],
    [],
    ['Example rows'],
    HEADERS,
    ['S1 A', 'Mathematics', 'TEACHER FULL NAME', 6, 'Double', 'Yes', 'No', '', '', '', ''],
    ['S1 A', 'Chemistry', 'TS-012', 4, '', 'Yes', 'No', 'LAB1', '', 'Fri', 'Practical in the lab'],
    ['S5 A', 'Physics', 'TEACHER FULL NAME', 5, 'Triple', 'Yes', 'No', '', 'Tue, Thu', '', ''],
  ], [120]), 'Instructions');
  XLSX.utils.book_append_sheet(wb, sheet([HEADERS, ...current], [12, 22, 28, 16, 14, 15, 16, 12, 16, 14, 28]), 'Requirements');
  XLSX.utils.book_append_sheet(wb, sheet([['Class', 'Level'], ...m.classes.map((c) => [c.full_class_name, c.level])], [14, 10]), 'Classes');
  XLSX.utils.book_append_sheet(wb, sheet([['Subject', 'Code'], ...m.subjects.map((s) => [s.subject_name, s.subject_code])], [26, 16]), 'Subjects');
  XLSX.utils.book_append_sheet(wb, sheet([['Teacher', 'Code', 'Main subject'], ...m.teachers.filter((t) => t.is_active).map((t) => [t.full_name, t.teacher_code, t.subject || ''])], [32, 12, 24]), 'Teachers');
  XLSX.utils.book_append_sheet(wb, sheet([['Room code', 'Room name', 'Type', 'Capacity'], ...m.rooms.filter((r) => r.is_active).map((r) => [r.room_code, r.room_name, r.room_type, r.capacity])], [14, 26, 14, 10]), 'Rooms');
  XLSX.writeFile(wb, `lesson-requirements-${props.session.session_name.replace(/[^\w-]+/g, '-')}.xlsx`);
};

const onFile = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;
  error.value = '';
  preview.value = null;
  done.value = null;
  fileName.value = file.name;
  try {
    const wb = XLSX.read(await file.arrayBuffer(), { type: 'array' });
    const name = wb.SheetNames.find((n) => n.toLowerCase() === 'requirements') || wb.SheetNames[0];
    rows.value = XLSX.utils.sheet_to_json(wb.Sheets[name], { defval: '', raw: false });
    if (!rows.value.length) {
      error.value = `The "${name}" sheet has no rows under the header.`;
      return;
    }
    await check();
  } catch (e) {
    error.value = `Could not read the file: ${e.message}`;
  }
};

const send = (dryRun) => lessonRequirementsAPI.importCSV({
  academic_session_id: props.session.id,
  rows: rows.value,
  dry_run: dryRun,
  replace: replace.value,
});

const check = async () => {
  busy.value = 'preview';
  try {
    preview.value = (await send(true)).data;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    busy.value = false;
  }
};

const importNow = async () => {
  if (replace.value && !confirm(`Replace this term's requirements? Requirements not in the file will be removed.`)) return;
  busy.value = 'import';
  error.value = '';
  try {
    done.value = (await send(false)).data;
    emit('imported', done.value);
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    busy.value = false;
  }
};
</script>
