<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Bulk Import Results</h1>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <section class="card overflow-hidden">
      <header class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <span class="step-dot">1</span>
        <div>
          <p class="font-semibold text-gray-900">Exam</p>
        </div>
      </header>
      <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
        <label class="block">
          <span class="field-label">Level</span>
          <select v-model="importConfig.level" class="field-input" required @change="loadSubjects">
            <option value="">Select level</option>
            <option value="O_LEVEL">O-Level</option>
            <option value="A_LEVEL">A-Level</option>
          </select>
        </label>
        <label class="block">
          <span class="field-label">Year</span>
          <input v-model="importConfig.year" type="number" class="field-input" required />
        </label>
        <label class="block">
          <span class="field-label">Term</span>
          <select v-model="importConfig.term" class="field-input" required>
            <option value="">Select term</option>
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
          </select>
        </label>
        <label class="block">
          <span class="field-label">Exam</span>
          <select v-model="importConfig.exam_type" class="field-input" required>
            <option value="">Select exam</option>
            <option value="BOT1">BOT 1</option>
            <option value="EOT1">EOT 1</option>
            <option value="BOT2">BOT 2</option>
            <option value="EOT2">EOT 2</option>
            <option value="BOT3">BOT 3</option>
            <option value="EOT3">EOT 3</option>
            <option value="FINAL">Final</option>
          </select>
        </label>
      </div>
      <div v-if="importConfig.level && availableSubjects.length" class="border-t border-gray-100 px-4 py-3 sm:px-5">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Subjects you can import</p>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="subject in availableSubjects" :key="subject.id" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700"><strong>{{ subject.subject_code }}</strong> {{ subject.subject_name }}</span>
        </div>
      </div>
    </section>

    <section class="card overflow-hidden">
      <header class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <span class="step-dot">2</span>
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-gray-900">Marks</p>
        </div>
        <div class="flex gap-2">
          <button type="button" class="tool-btn" :disabled="importData.length === 0" @click="clearData"><Eraser class="h-4 w-4" /><span class="hidden sm:inline">Clear all</span></button>
          <button type="button" class="tool-btn-primary" @click="addRow"><Plus class="h-4 w-4" />Add row</button>
        </div>
      </header>

      <div v-if="importData.length === 0" class="px-5 py-12 text-center">
        <FileSpreadsheet class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No rows yet</p>
        <p class="text-xs text-gray-500">Choose the exam above, then add a row for each mark.</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="w-12 px-5 py-3">#</th>
              <th class="px-3 py-3">Admission no.</th>
              <th class="px-3 py-3">Subject</th>
              <th class="w-28 px-3 py-3">Marks</th>
              <th class="px-3 py-3">Remarks</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Remove</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(row, index) in importData" :key="index">
              <td class="px-5 py-2 tabular-nums text-gray-500">{{ index + 1 }}</td>
              <td class="px-3 py-2"><input v-model="row.admission_number" type="text" class="field-input" placeholder="e.g. 23-14671" /></td>
              <td class="px-3 py-2">
                <select v-model="row.subject_code" class="field-input">
                  <option value="">Select subject</option>
                  <option v-for="subject in availableSubjects" :key="subject.id" :value="subject.subject_code">{{ subject.subject_code }} · {{ subject.subject_name }}</option>
                </select>
              </td>
              <td class="px-3 py-2"><input v-model="row.marks" type="number" min="0" max="100" class="field-input" /></td>
              <td class="px-3 py-2"><input v-model="row.remarks" type="text" class="field-input" /></td>
              <td class="px-3 py-2 text-right"><button type="button" class="icon-btn" :aria-label="`Remove row ${index + 1}`" title="Remove row" @click="removeRow(index)"><Trash2 class="h-4 w-4" /></button></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="(row, index) in importData" :key="index" class="space-y-2 px-4 py-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-gray-500">Row {{ index + 1 }}</span>
              <button type="button" class="icon-btn" :aria-label="`Remove row ${index + 1}`" @click="removeRow(index)"><Trash2 class="h-4 w-4" /></button>
            </div>
            <input v-model="row.admission_number" type="text" class="field-input" placeholder="Admission no." />
            <select v-model="row.subject_code" class="field-input">
              <option value="">Select subject</option>
              <option v-for="subject in availableSubjects" :key="subject.id" :value="subject.subject_code">{{ subject.subject_code }} · {{ subject.subject_name }}</option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <input v-model="row.marks" type="number" min="0" max="100" class="field-input" placeholder="Marks" />
              <input v-model="row.remarks" type="text" class="field-input" placeholder="Remarks" />
            </div>
          </li>
        </ul>
      </template>

      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50/60 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600">
          <template v-if="canImport"><strong class="text-gray-900">{{ importData.length }}</strong> row{{ importData.length === 1 ? '' : 's' }} ready to import</template>
          <template v-else>Choose the level, term and exam, and add at least one row.</template>
        </p>
        <button type="button" class="tool-btn-primary disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canImport || importing" @click="importResults">
          <Upload class="h-4 w-4" />{{ importing ? 'Importing…' : 'Import results' }}
        </button>
      </footer>
    </section>

    <section v-if="importResult" class="card overflow-hidden" :class="importResult.success ? '' : 'border-red-200'">
      <header class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <CheckCircle2 v-if="importResult.success" class="h-5 w-5 text-emerald-600" />
        <XCircle v-else class="h-5 w-5 text-red-600" />
        <p class="font-semibold text-gray-900">{{ importResult.success ? 'Import completed' : 'Import failed' }}</p>
      </header>
      <dl class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-4">
        <div class="bg-white p-4"><dt class="text-xs text-gray-500">Total</dt><dd class="text-2xl font-semibold text-gray-900">{{ importResult.data.total }}</dd></div>
        <div class="bg-white p-4"><dt class="text-xs text-gray-500">Imported</dt><dd class="text-2xl font-semibold text-emerald-600">{{ importResult.data.success }}</dd></div>
        <div class="bg-white p-4"><dt class="text-xs text-gray-500">Errors</dt><dd class="text-2xl font-semibold text-red-600">{{ importResult.data.errors }}</dd></div>
        <div class="bg-white p-4"><dt class="text-xs text-gray-500">Duplicates</dt><dd class="text-2xl font-semibold text-amber-600">{{ importResult.data.duplicates }}</dd></div>
      </dl>
    </section>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '../services/api.js';
import { Plus, Trash2, Upload, Eraser, FileSpreadsheet, CheckCircle2, XCircle, ListChecks, BookOpen, AlertTriangle } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';

const importConfig = ref({ level: '', year: new Date().getFullYear(), term: '', exam_type: '' });
const importData = ref([]);
const availableSubjects = ref([]);
const importing = ref(false);
const importResult = ref(null);

const canImport = computed(() => importConfig.value.level && 
         importConfig.value.year && 
         importConfig.value.term && 
         importConfig.value.exam_type &&
         importData.value.length > 0);

const summaryCards = computed(() => {
  const r = importResult.value?.data;
  return [
    { label: 'Rows ready', value: String(importData.value.length), icon: ListChecks },
    { label: 'Subjects', value: String(availableSubjects.value.length), note: importConfig.value.level === 'A_LEVEL' ? 'A-Level' : importConfig.value.level === 'O_LEVEL' ? 'O-Level' : 'Pick a level', icon: BookOpen, tone: 'sky' },
    { label: 'Last import', value: r ? String(r.success) : '-', note: r ? 'imported' : 'Nothing imported yet', icon: CheckCircle2, tone: 'gray' },
    { label: 'Problems', value: r ? String((r.errors || 0) + (r.duplicates || 0)) : '-', note: 'errors and duplicates', icon: AlertTriangle, tone: 'amber' },
  ];
});

const loadSubjects = async () => {
  if (!importConfig.value.level) return;
  try {
    const response = await api.get(`subjects.php?action=list&level=${importConfig.value.level}`);
    availableSubjects.value = response.data.data;
  } catch (error) { console.error(error); }
};

const addRow = () => importData.value.push({ admission_number: '', subject_code: '', marks: '', remarks: '' });
const removeRow = (index) => importData.value.splice(index, 1);
const clearData = () => { importData.value = []; importResult.value = null; };

const importResults = async () => {
  if (!canImport.value) return;
  importing.value = true;
  try {
    const response = await api.post(`bulk-import-results.php`, {
      level: importConfig.value.level,
      year: importConfig.value.year,
      term: importConfig.value.term,
      exam_type: importConfig.value.exam_type,
      results: importData.value
    });
    importResult.value = response.data;
    if (response.data.success) importData.value = [];
  } catch (error) {
    console.error(error);
    importResult.value = { success: false, data: { total: 0, success: 0, errors: 1, duplicates: 0 } };
  } finally {
    importing.value = false;
  }
};
</script>

<style scoped>
.step-dot { @apply flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700 ring-1 ring-brand-100; }
.field-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.field-input { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.tool-btn-primary { @apply inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700; }
.icon-btn { @apply inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-600; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .step-dot {
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
  --tw-ring-color: rgba(34, 197, 94, 0.3);
}
html.dark .field-label {
  color: var(--d-muted);
}
html.dark .field-input {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
}
html.dark .tool-btn {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .tool-btn:hover {
  background-color: var(--d-sunken);
}
html.dark .icon-btn {
  color: var(--d-faint);
}
html.dark .icon-btn:hover {
  background-color: rgba(239, 68, 68, 0.12);
  color: #fca5a5;
}
</style>
