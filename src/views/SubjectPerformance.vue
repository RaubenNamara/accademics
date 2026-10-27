<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Subject Performance</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero-primary" @click="openAddModal"><Plus class="h-4 w-4" />Add record</button>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <ListToolbar
      v-model:search="searchQuery"
      placeholder="Search teacher, subject or class"
      :filter-count="0"
    >
      <template #filters>
        <label class="block">
          <span class="filter-label">Year</span>
          <select v-model="filterYear" class="filter-select" @change="loadData">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Term</span>
          <select v-model="filterTerm" class="filter-select" @change="loadData">
            <option value="">All terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </label>
      </template>
      <template #actions>
        <button type="button" class="tool-btn" :disabled="loading" title="Refresh" @click="loadData"><RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" /><span class="hidden lg:inline">Refresh</span></button>
        <button type="button" class="tool-btn" :disabled="records.length === 0" title="Download PDF" @click="downloadPDF"><FileText class="h-4 w-4" /><span class="hidden lg:inline">PDF</span></button>
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ visibleRecords.length }}</strong> record{{ visibleRecords.length === 1 ? '' : 's' }}</p>
        <p class="hidden text-xs text-gray-500 sm:block">BOT/EOT are class averages; TC is the change and AGP the overall gain.</p>
      </div>
      <div v-if="loading && records.length === 0" class="space-y-3 p-5">
        <div v-for="i in 5" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="visibleRecords.length === 0" class="px-5 py-14 text-center">
        <ClipboardList class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No performance records found</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Teacher</th>
              <th class="px-4 py-3">Class</th>
              <th class="hidden px-4 py-3 lg:table-cell">Scores</th>
              <th class="px-4 py-3">Change</th>
              <th class="hidden px-4 py-3 xl:table-cell">Comment</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="record in visibleRecords" :key="record.id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(record.teacher_name) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900">{{ record.teacher_name || '-' }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ record.subject || '-' }}</span>
                  </span>
                </div>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ record.class }} {{ record.stream }}</td>
              <td class="hidden px-4 py-3 lg:table-cell">
                <div class="flex flex-wrap gap-1">
                  <span v-for="c in scoreColumns" :key="c.key" class="rounded-md px-1.5 py-0.5 text-[11px]" :class="c.kind === 'text' ? 'bg-gray-100 text-gray-600' : changeClass(record[c.field])">{{ c.label }} <strong>{{ c.kind === 'text' ? (Number(record[c.field]) ? Number(record[c.field]) : '-') : formatScore(record[c.field]) }}</strong></span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span v-if="headlineColumn" class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums" :class="changeClass(record[headlineColumn.field])">{{ headlineColumn.label }} {{ formatScore(record[headlineColumn.field]) }}</span>
              </td>
              <td class="hidden max-w-xs px-4 py-3 text-xs text-gray-600 xl:table-cell">{{ getCommentForRecord(record) }}</td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${record.teacher_name}`" :items="recordActions(record)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="record in visibleRecords" :key="record.id" class="flex items-start gap-3 px-4 py-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(record.teacher_name) }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-gray-900">{{ record.teacher_name || '-' }}</p>
              <p class="truncate text-xs text-gray-500">{{ record.subject || '-' }} · {{ record.class }} {{ record.stream }}</p>
              <div class="mt-1 flex flex-wrap gap-1">
                <span v-for="c in scoreColumns" :key="c.key" class="rounded-md px-1.5 py-0.5 text-[11px]" :class="c.kind === 'text' ? 'bg-gray-100 text-gray-600' : changeClass(record[c.field])">{{ c.label }} <strong>{{ c.kind === 'text' ? (Number(record[c.field]) ? Number(record[c.field]) : '-') : formatScore(record[c.field]) }}</strong></span>
              </div>
            </div>
            <RowActionsMenu :label="`Actions for ${record.teacher_name}`" :items="recordActions(record)" />
          </li>
        </ul>
      </template>
    </section>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl bg-white shadow-2xl ring-1 ring-white/20">
        <div class="pointer-events-none absolute inset-0 rounded-2xl bg-brand-500/5"></div>

        <div class="relative flex-shrink-0 border-b border-slate-200/60 bg-slate-50 px-6 py-6">
          <h3 class="text-xl font-bold text-slate-900">
            {{ editingId ? 'Edit Performance Record' : 'Add Performance Record' }}
          </h3>
          <p class="mt-1.5 text-sm text-slate-500">
            Enter BOT and EOT scores. TC and AGP will be calculated automatically.
          </p>
        </div>

        <form class="relative flex flex-1 flex-col overflow-hidden">
          <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
            <div class="grid gap-5 md:grid-cols-4">
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Teacher *</label>
                <div class="relative">
                  <input
                    v-model="teacherSearch"
                    type="text"
                    placeholder="Search teacher..."
                    class="input mb-2"
                  >
                  <svg class="w-5 h-5 text-slate-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                  </svg>
                </div>
                <select v-model="form.teacher_id" required class="input">
                  <option value="">Select teacher</option>
                  <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Subject *</label>
                <select v-model="form.subject" required class="input">
                  <option value="">Select subject</option>
                  <option v-for="sub in subjects" :key="sub" :value="sub">{{ sub }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Class</label>
                <select v-model="form.class" class="input">
                  <option value="">Select class</option>
                  <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Stream</label>
                <select v-model="form.stream" class="input">
                  <option value="">Select stream</option>
                  <option v-for="s in streams" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year *</label>
                <input v-model.number="form.year" type="number" required class="input" />
              </div>
            </div>

            <!-- Term 1 Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-brand-50 p-5 shadow-sm">
              <h4 class="mb-4 font-bold text-slate-900">Term 1 Performance</h4>
              <div class="grid gap-5 md:grid-cols-4">
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">BOT1</label>
                  <input
                    v-model.number="form.bot1"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    @input="autoCalculateTerm1"
                    class="input"
                  />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">EOT1</label>
                  <input
                    v-model.number="form.eot1"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    @input="autoCalculateTerm1"
                    class="input"
                  />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">TC1 (Auto)</label>
                  <input v-model="form.tc1" readonly class="input bg-white/70" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">TC1 Comment</label>
                  <input v-model="form.tc1_comment" readonly class="input bg-white/70" />
                </div>
              </div>
              <div class="mt-4 flex justify-end">
                <button
                  type="button"
                  @click="saveTerm1"
                  :disabled="savingTerm1"
                  class="rounded-2xl bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-brand-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                >
                  {{ savingTerm1 ? 'Saving...' : 'Save Term 1' }}
                </button>
              </div>
            </div>

            <!-- Term 2 Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-emerald-50 p-5 shadow-sm">
              <h4 class="mb-4 font-bold text-slate-900">Term 2 Performance</h4>
              <div class="grid gap-5 md:grid-cols-3">
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">EOT2</label>
                  <input
                    v-model.number="form.eot2"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    @input="autoCalculateTerm2"
                    class="input"
                  />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">TC2 (Auto)</label>
                  <input v-model="form.tc2" readonly class="input bg-white/70" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">TC2 Comment</label>
                  <input v-model="form.tc2_comment" readonly class="input bg-white/70" />
                </div>
              </div>
              <div class="mt-4 flex justify-end">
                <button
                  type="button"
                  @click="saveTerm2"
                  :disabled="savingTerm2"
                  class="rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                >
                  {{ savingTerm2 ? 'Saving...' : 'Save Term 2' }}
                </button>
              </div>
            </div>

            <!-- Term 3 Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-amber-50 p-5 shadow-sm">
              <h4 class="mb-4 font-bold text-slate-900">Term 3 Performance</h4>
              <div class="grid gap-5 md:grid-cols-3">
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">EOT3</label>
                  <input
                    v-model.number="form.eot3"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    @input="autoCalculateTerm3"
                    class="input"
                  />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">TC3 (Auto)</label>
                  <input v-model="form.tc3" readonly class="input bg-white/70" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">TC3 Comment</label>
                  <input v-model="form.tc3_comment" readonly class="input bg-white/70" />
                </div>
              </div>
              <div class="mt-4 flex justify-end">
                <button
                  type="button"
                  @click="saveTerm3"
                  :disabled="savingTerm3"
                  class="rounded-2xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-amber-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-amber-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                >
                  {{ savingTerm3 ? 'Saving...' : 'Save Term 3' }}
                </button>
              </div>
            </div>

            <!-- Final Calculations Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-brand-50 p-5 shadow-sm">
              <h4 class="mb-4 font-bold text-slate-900">Final Calculations (AGP)</h4>
              <div class="grid gap-5 md:grid-cols-2">
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">AGP (Auto)</label>
                  <input v-model="form.agp" readonly class="input bg-white/70" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">AGP Comment</label>
                  <input v-model="form.agp_comment" readonly class="input bg-white/70" />
                </div>
              </div>
              <div class="mt-4 flex justify-end">
                <button
                  type="button"
                  @click="calculateAGP"
                  class="rounded-2xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-brand-500/40"
                >
                  Calculate AGP
                </button>
              </div>
            </div>

            <div v-if="error" class="rounded-2xl border border-rose-200/60 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
              {{ error }}
            </div>
          </div>

          <div class="relative flex-shrink-0 border-t border-slate-200/60 bg-slate-50 px-6 py-5">
            <div class="flex items-center justify-end gap-3">
              <button
                type="button"
                @click="showModal = false"
                class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:bg-slate-50 hover:shadow-md"
              >
                Close
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { subjectPerformanceAPI, teachersAPI, classesAPI, subjectsNewAPI } from '../services/api';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import { Plus, RefreshCw, FileText, ClipboardList, Pencil, Trash2, Users as UsersIcon, TrendingUp, TrendingDown } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';

const records = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref('');
useGlobalTerm((y, t) => { filterYear.value = y; filterTerm.value = t; }, () => loadData());
const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i);
const showModal = ref(false);
const editingId = ref(null);
const saving = ref(false);
const savingTerm1 = ref(false);
const savingTerm2 = ref(false);
const savingTerm3 = ref(false);
const error = ref('');

const allSubjects = ref([]);
const allClasses = ref([]);
const streams = ref([]);

const filteredTeachers = computed(() => {
  if (!teacherSearch.value) return teachersList.value;
  const search = teacherSearch.value.toLowerCase();
  return teachersList.value.filter(teacher =>
    teacher.full_name.toLowerCase().includes(search) ||
    teacher.teacher_code.toLowerCase().includes(search)
  );
});

const subjects = computed(() => {
  return allSubjects.value.map(s => s.subject_name).sort();
});

const classes = computed(() => {
  const uniqueClassNames = [...new Set(allClasses.value.map(c => c.class_name))];
  return uniqueClassNames.sort();
});

const currentTerm = computed(() => {
  return filterTerm.value === '' ? null : Number(filterTerm.value);
});

const form = ref({
  teacher_id: '',
  subject: '',
  class: '',
  stream: '',
  year: new Date().getFullYear(),
  term: 1,
  bot1: 0,
  eot1: 0,
  tc1: 0,
  tc1_comment: '',
  eot2: 0,
  tc2: 0,
  tc2_comment: '',
  eot3: 0,
  tc3: 0,
  tc3_comment: '',
  agp: 0,
  agp_comment: ''
});

const tableColumns = computed(() => {
  const base = [
    { key: 'teacher_name', label: 'Teacher', kind: 'teacher' },
    { key: 'subject', label: 'Subject', kind: 'subject' },
    { key: 'class', label: 'Class', kind: 'text', field: 'class' },
    { key: 'stream', label: 'Stream', kind: 'text', field: 'stream' },
    { key: 'bot1', label: 'BOT1', kind: 'text', field: 'bot1' },
    { key: 'eot1', label: 'EOT1', kind: 'text', field: 'eot1' },
    { key: 'tc1', label: 'TC1', kind: 'tc', field: 'tc1' }
  ];

  if (currentTerm.value === null) {
    return [
      ...base,
      { key: 'eot2', label: 'EOT2', kind: 'text', field: 'eot2' },
      { key: 'tc2', label: 'TC2', kind: 'tc', field: 'tc2' },
      { key: 'eot3', label: 'EOT3', kind: 'text', field: 'eot3' },
      { key: 'tc3', label: 'TC3', kind: 'tc', field: 'tc3' },
      { key: 'agp', label: 'AGP', kind: 'agp', field: 'agp' },
      { key: 'comment', label: 'Comment', kind: 'comment' },
      { key: 'actions', label: 'Actions', kind: 'actions', align: 'right' }
    ];
  }

  if (currentTerm.value === 1) {
    return [
      ...base,
      { key: 'comment', label: 'Comment', kind: 'comment' },
      { key: 'actions', label: 'Actions', kind: 'actions', align: 'right' }
    ];
  }

  if (currentTerm.value === 2) {
    return [
      ...base,
      { key: 'eot2', label: 'EOT2', kind: 'text', field: 'eot2' },
      { key: 'tc2', label: 'TC2', kind: 'tc', field: 'tc2' },
      { key: 'comment', label: 'Comment', kind: 'comment' },
      { key: 'actions', label: 'Actions', kind: 'actions', align: 'right' }
    ];
  }

  return [
    ...base,
    { key: 'eot2', label: 'EOT2', kind: 'text', field: 'eot2' },
    { key: 'tc2', label: 'TC2', kind: 'tc', field: 'tc2' },
    { key: 'eot3', label: 'EOT3', kind: 'text', field: 'eot3' },
    { key: 'tc3', label: 'TC3', kind: 'tc', field: 'tc3' },
    { key: 'comment', label: 'Comment', kind: 'comment' },
    { key: 'actions', label: 'Actions', kind: 'actions', align: 'right' }
  ];
});

const termLabel = computed(() => {
  if (!filterTerm.value) return 'All Terms';
  return `Term ${filterTerm.value}`;
});

const formatScore = (value) => {
  if (value === null || value === undefined || value === '') return '-';
  const num = Number(value);
  if (Number.isNaN(num)) return String(value);
  return num.toFixed(2);
};

const getCommentForRecord = (record) => {
  if (currentTerm.value === 1) return record.tc1_comment || '-';
  if (currentTerm.value === 2) return record.tc2_comment || '-';
  if (currentTerm.value === 3) return record.tc3_comment || '-';
  return record.agp_comment || '-';
};

const loadData = async () => {
  loading.value = true;
  try {
    const result = await subjectPerformanceAPI.getAll(filterYear.value, filterTerm.value || null);
    if (result.success) {
      records.value = result.data.data || result.data || [];
    }
  } catch (err) {
    console.error('Failed to load data:', err);
  } finally {
    loading.value = false;
  }
};

const initials = (name) => String(name || '?').split(/s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const scoreColumns = computed(() => tableColumns.value.filter((c) => ['text', 'tc', 'agp'].includes(c.kind) && !['class', 'stream'].includes(c.field)));
// The last change column (TC of the term shown, or AGP for all terms) is the headline.
const headlineColumn = computed(() => [...tableColumns.value].reverse().find((c) => c.kind === 'tc' || c.kind === 'agp') || null);
const changeClass = (value) => {
  const n = parseFloat(value);
  if (Number.isNaN(n)) return 'bg-gray-100 text-gray-600';
  return n < 0 ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-100' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100';
};
const visibleRecords = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return records.value;
  return records.value.filter((r) => `${r.teacher_name} ${r.subject} ${r.class} ${r.stream}`.toLowerCase().includes(q));
});
const recordActions = (record) => [
  { label: 'Edit', icon: Pencil, onClick: () => editRecord(record) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteRecord(record.id) },
];
const summaryCards = computed(() => {
  const field = headlineColumn.value?.field;
  const vals = field ? records.value.map((r) => parseFloat(r[field])).filter((n) => !Number.isNaN(n)) : [];
  return [
    { label: 'Records', value: records.value.length.toLocaleString(), icon: ClipboardList },
    { label: 'Teachers', value: new Set(records.value.map((r) => r.teacher_id || r.teacher_name)).size.toLocaleString(), icon: UsersIcon, tone: 'sky' },
    { label: 'Improved', value: vals.filter((n) => n >= 0).length.toLocaleString(), note: headlineColumn.value ? 'by ' + headlineColumn.value.label : '', icon: TrendingUp, tone: 'brand' },
    { label: 'Dropped', value: vals.filter((n) => n < 0).length.toLocaleString(), note: headlineColumn.value ? 'by ' + headlineColumn.value.label : '', icon: TrendingDown, tone: 'red' },
  ];
});

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    allClasses.value = response.data || [];
    streams.value = [...new Set(allClasses.value.map(c => c.stream_name))];
  } catch (error) {
    console.error('Error loading classes:', error);
  }
};

const loadSubjects = async () => {
  try {
    const response = await subjectsNewAPI.getAll();
    allSubjects.value = response.data || [];
  } catch (error) {
    console.error('Error loading subjects:', error);
  }
};

const loadTeachers = async () => {
  try {
    const result = await teachersAPI.getAll();
    if (result.success) {
      teachersList.value = result.data.teachers || result.data || [];
    }
  } catch (err) {
    console.error('Failed to load teachers:', err);
  }
};

const autoCalculateTerm1 = () => {
  const bot1 = parseFloat(form.value.bot1) || 0;
  const eot1 = parseFloat(form.value.eot1) || 0;

  const tc1 = eot1 - bot1;

  form.value.tc1 = tc1.toFixed(2);
  form.value.tc1_comment = tc1 < 0 ? 'Urgent follow-up is required' : 'On track';
};

const autoCalculateTerm2 = () => {
  const eot1 = parseFloat(form.value.eot1) || 0;
  const eot2 = parseFloat(form.value.eot2) || 0;

  const tc2 = eot2 - eot1;

  form.value.tc2 = tc2.toFixed(2);
  form.value.tc2_comment = tc2 < 0 ? 'Urgent follow-up is required' : 'On track';
};

const autoCalculateTerm3 = () => {
  const eot2 = parseFloat(form.value.eot2) || 0;
  const eot3 = parseFloat(form.value.eot3) || 0;

  const tc3 = eot3 - eot2;

  form.value.tc3 = tc3.toFixed(2);
  form.value.tc3_comment = tc3 < 0 ? 'Urgent follow-up is required' : 'On track';
};

const calculateAGP = () => {
  const tc1 = parseFloat(form.value.tc1) || 0;
  const tc2 = parseFloat(form.value.tc2) || 0;
  const tc3 = parseFloat(form.value.tc3) || 0;

  const agp = (tc1 + tc2 + tc3) / 3;

  form.value.agp = agp.toFixed(2);
  form.value.agp_comment = agp < 0 ? 'Urgent follow-up is required' : 'Teacher is placed on growth development plan';
};

const saveTerm1 = async () => {
  savingTerm1.value = true;
  error.value = '';

  try {
    // Calculate AGP before saving
    calculateAGP();

    // Check if a record already exists for this teacher/subject/year
    const existingRecord = records.value.find(
      r => r.teacher_id == form.value.teacher_id &&
          r.subject == form.value.subject &&
          r.year == form.value.year
    );

    let result;
    if (existingRecord) {
      // Update existing record with Term 1 data
      const payload = {
        id: existingRecord.id,
        teacher_id: form.value.teacher_id,
        subject: form.value.subject,
        class: form.value.class,
        stream: form.value.stream,
        year: form.value.year,
        term: 1,
        bot1: form.value.bot1,
        eot1: form.value.eot1,
        tc1: form.value.tc1,
        tc1_comment: form.value.tc1_comment,
        eot2: existingRecord.eot2 || 0,
        tc2: existingRecord.tc2 || 0,
        tc2_comment: existingRecord.tc2_comment || '',
        eot3: existingRecord.eot3 || 0,
        tc3: existingRecord.tc3 || 0,
        tc3_comment: existingRecord.tc3_comment || '',
        agp: form.value.agp,
        agp_comment: form.value.agp_comment
      };
      result = await subjectPerformanceAPI.update(existingRecord.id, payload);
    } else {
      // Create new record with Term 1 data
      const payload = {
        teacher_id: form.value.teacher_id,
        subject: form.value.subject,
        class: form.value.class,
        stream: form.value.stream,
        year: form.value.year,
        term: 1,
        bot1: form.value.bot1,
        eot1: form.value.eot1,
        tc1: form.value.tc1,
        tc1_comment: form.value.tc1_comment,
        eot2: 0,
        tc2: 0,
        tc2_comment: '',
        eot3: 0,
        tc3: 0,
        tc3_comment: '',
        agp: form.value.agp,
        agp_comment: form.value.agp_comment
      };
      result = await subjectPerformanceAPI.create(payload);
    }

    if (result.success) {
      await loadData();
    } else {
      error.value = result.data?.message || result.message || 'Failed to save Term 1';
    }
  } catch (err) {
    console.error('Failed to save Term 1:', err);
    error.value = 'Failed to save Term 1';
  } finally {
    savingTerm1.value = false;
  }
};

const saveTerm2 = async () => {
  savingTerm2.value = true;
  error.value = '';

  try {
    // Calculate AGP before saving
    calculateAGP();

    // Check if a record already exists for this teacher/subject/year
    const existingRecord = records.value.find(
      r => r.teacher_id == form.value.teacher_id &&
          r.subject == form.value.subject &&
          r.year == form.value.year
    );

    let result;
    if (existingRecord) {
      // Update existing record with Term 2 data
      const payload = {
        id: existingRecord.id,
        teacher_id: form.value.teacher_id,
        subject: form.value.subject,
        class: form.value.class,
        stream: form.value.stream,
        year: form.value.year,
        term: 2,
        bot1: existingRecord.bot1 || 0,
        eot1: existingRecord.eot1 || 0,
        tc1: existingRecord.tc1 || 0,
        tc1_comment: existingRecord.tc1_comment || '',
        eot2: form.value.eot2,
        tc2: form.value.tc2,
        tc2_comment: form.value.tc2_comment,
        eot3: existingRecord.eot3 || 0,
        tc3: existingRecord.tc3 || 0,
        tc3_comment: existingRecord.tc3_comment || '',
        agp: form.value.agp,
        agp_comment: form.value.agp_comment
      };
      result = await subjectPerformanceAPI.update(existingRecord.id, payload);
    } else {
      // Create new record with Term 2 data
      const payload = {
        teacher_id: form.value.teacher_id,
        subject: form.value.subject,
        class: form.value.class,
        stream: form.value.stream,
        year: form.value.year,
        term: 2,
        bot1: 0,
        eot1: 0,
        tc1: 0,
        tc1_comment: '',
        eot2: form.value.eot2,
        tc2: form.value.tc2,
        tc2_comment: form.value.tc2_comment,
        eot3: 0,
        tc3: 0,
        tc3_comment: '',
        agp: form.value.agp,
        agp_comment: form.value.agp_comment
      };
      result = await subjectPerformanceAPI.create(payload);
    }

    if (result.success) {
      await loadData();
    } else {
      error.value = result.data?.message || result.message || 'Failed to save Term 2';
    }
  } catch (err) {
    console.error('Failed to save Term 2:', err);
    error.value = 'Failed to save Term 2';
  } finally {
    savingTerm2.value = false;
  }
};

const saveTerm3 = async () => {
  savingTerm3.value = true;
  error.value = '';

  try {
    // Calculate AGP before saving
    calculateAGP();

    // Check if a record already exists for this teacher/subject/year
    const existingRecord = records.value.find(
      r => r.teacher_id == form.value.teacher_id &&
          r.subject == form.value.subject &&
          r.year == form.value.year
    );

    let result;
    if (existingRecord) {
      // Update existing record with Term 3 data
      const payload = {
        id: existingRecord.id,
        teacher_id: form.value.teacher_id,
        subject: form.value.subject,
        class: form.value.class,
        stream: form.value.stream,
        year: form.value.year,
        term: 3,
        bot1: existingRecord.bot1 || 0,
        eot1: existingRecord.eot1 || 0,
        tc1: existingRecord.tc1 || 0,
        tc1_comment: existingRecord.tc1_comment || '',
        eot2: form.value.eot2,
        tc2: existingRecord.tc2 || 0,
        tc2_comment: existingRecord.tc2_comment || '',
        eot3: form.value.eot3,
        tc3: form.value.tc3,
        tc3_comment: form.value.tc3_comment,
        agp: form.value.agp,
        agp_comment: form.value.agp_comment
      };
      result = await subjectPerformanceAPI.update(existingRecord.id, payload);
    } else {
      // Create new record with Term 3 data
      const payload = {
        teacher_id: form.value.teacher_id,
        subject: form.value.subject,
        class: form.value.class,
        stream: form.value.stream,
        year: form.value.year,
        term: 3,
        bot1: 0,
        eot1: 0,
        tc1: 0,
        tc1_comment: '',
        eot2: form.value.eot2,
        tc2: 0,
        tc2_comment: '',
        eot3: form.value.eot3,
        tc3: form.value.tc3,
        tc3_comment: form.value.tc3_comment,
        agp: form.value.agp,
        agp_comment: form.value.agp_comment
      };
      result = await subjectPerformanceAPI.create(payload);
    }

    if (result.success) {
      await loadData();
    } else {
      error.value = result.data?.message || result.message || 'Failed to save Term 3';
    }
  } catch (err) {
    console.error('Failed to save Term 3:', err);
    error.value = 'Failed to save Term 3';
  } finally {
    savingTerm3.value = false;
  }
};

const openAddModal = () => {
  editingId.value = null;
  form.value = {
    teacher_id: '',
    subject: '',
    class: '',
    stream: '',
    year: new Date().getFullYear(),
    term: 1,
    bot1: 0,
    eot1: 0,
    tc1: 0,
    tc1_comment: '',
    eot2: 0,
    tc2: 0,
    tc2_comment: '',
    eot3: 0,
    tc3: 0,
    tc3_comment: '',
    agp: 0,
    agp_comment: ''
  };
  showModal.value = true;
};

const editRecord = (record) => {
  editingId.value = record.id;
  form.value = {
    teacher_id: record.teacher_id ?? '',
    subject: record.subject ?? '',
    class: record.class ?? '',
    stream: record.stream ?? '',
    year: Number(record.year) || new Date().getFullYear(),
    term: Number(record.term) || 1,
    bot1: Number(record.bot1) || 0,
    eot1: Number(record.eot1) || 0,
    tc1: record.tc1 ?? 0,
    tc1_comment: record.tc1_comment ?? '',
    eot2: Number(record.eot2) || 0,
    tc2: record.tc2 ?? 0,
    tc2_comment: record.tc2_comment ?? '',
    eot3: Number(record.eot3) || 0,
    tc3: record.tc3 ?? 0,
    tc3_comment: record.tc3_comment ?? '',
    agp: record.agp ?? 0,
    agp_comment: record.agp_comment ?? ''
  };
  // Auto-calculate AGP when loading existing data
  calculateAGP();
  showModal.value = true;
};

const saveRecord = async () => {
  saving.value = true;
  error.value = '';

  try {
    let result;
    const payload = { ...form.value };

    if (editingId.value) {
      result = await subjectPerformanceAPI.update(editingId.value, payload);
    } else {
      result = await subjectPerformanceAPI.create(payload);
    }

    if (result.success) {
      showModal.value = false;
      await loadData();
    } else {
      error.value = result.data?.message || result.message || 'Failed to save';
    }
  } catch (err) {
    console.error('Failed to save record:', err);
    error.value = 'Failed to save';
  } finally {
    saving.value = false;
  }
};

const deleteRecord = async (id) => {
  if (!confirm('Are you sure you want to delete this performance record?')) return;

  try {
    const result = await subjectPerformanceAPI.delete(id);

    if (result.success) {
      await loadData();
    } else {
      alert(result.message || 'Failed to delete record. Please try again.');
    }
  } catch (error) {
    console.error('Failed to delete record:', error);
    alert('Failed to delete record. Please try again.');
  }
};

const getPdfColumns = () => {
  return tableColumns.value.filter(col => col.kind !== 'actions');
};

const getPdfCellValue = (record, column) => {
  if (column.kind === 'teacher') return record.teacher_name || '-';
  if (column.kind === 'subject') return record.subject || '-';
  if (column.kind === 'comment') return getCommentForRecord(record);
  return record[column.field] ?? '-';
};

const downloadPDF = () => {
  const doc = new jsPDF();
  const pdfColumns = getPdfColumns();

  doc.setFontSize(18);
  doc.text('Subject Performance Report', 14, 22);

  doc.setFontSize(11);
  doc.setTextColor(100);
  doc.text(`Year: ${filterYear.value} | ${termLabel.value}`, 14, 30);
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);

  const tableData = records.value.map(record =>
    pdfColumns.map(column => getPdfCellValue(record, column))
  );

  autoTable(doc, {
    head: [pdfColumns.map(column => column.label)],
    body: tableData,
    startY: 45,
    styles: {
      fontSize: 7,
      cellPadding: 2
    },
    headStyles: {
      fillColor: [22, 163, 74],
      textColor: 255,
      fontStyle: 'bold'
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245]
    },
    margin: { top: 10, right: 10, bottom: 10, left: 10 }
  });

  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(8);
    doc.setTextColor(150);
    doc.text(
      `Page ${i} of ${pageCount}`,
      doc.internal.pageSize.width / 2,
      doc.internal.pageSize.height - 10,
      { align: 'center' }
    );
  }

  const termForFile = filterTerm.value || 'all-terms';
  doc.save(`subject-performance-${filterYear.value}-term-${termForFile}.pdf`);
};

onMounted(() => {
  loadClasses();
  loadSubjects();
  loadData();
  loadTeachers();
});
</script>

<style scoped>
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.filter-select { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.input {
  width: 100%;
  border-radius: 1rem;
  border: 1.5px solid rgb(226 232 240);
  padding: 0.85rem 1rem;
  outline: none;
  transition: all 0.2s ease;
  background: white;
  font-weight: 500;
}

.input:focus {
  border-color: rgb(22 163 74);
  box-shadow: 0 0 0 4px rgb(220 252 231);
}

.input:hover:not(:focus) {
  border-color: rgb(203 213 225);
}
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .filter-label {
  color: var(--d-muted);
}
html.dark .filter-select {
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
html.dark .input {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .input:hover:not(:focus) {
  border-color: var(--d-line);
}
</style>
