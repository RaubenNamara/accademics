<template>
  <div class="space-y-8">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 p-8 text-white shadow-2xl">
      <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>
      <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>

      <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
        
          <h1 class="mt-3 text-4xl font-bold tracking-tight">Subject-Teacher Performance Tracking</h1>
         
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            @click="loadData"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-medium text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/20 hover:shadow-lg hover:shadow-white/10"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Refresh
            </span>
          </button>

          <button
            @click="downloadPDF"
            :disabled="records.length === 0"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-medium text-white shadow-xl shadow-emerald-500/25 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Download PDF
            </span>
          </button>

          <button
            @click="openAddModal"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-500 px-5 py-2.5 text-sm font-medium text-white shadow-xl shadow-blue-500/25 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-blue-500/40"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Record
            </span>
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 p-6 shadow-xl backdrop-blur-sm">
      <div class="grid gap-5 xl:grid-cols-3">
        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year</label>
          <select
            v-model="filterYear"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100/50"
          >
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Term</label>
          <select
            v-model="filterTerm"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100/50"
          >
            <option value="">All Terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </div>

        <div class="flex items-end">
          <div class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-50 to-purple-50 px-4 py-3">
            <div class="h-2 w-2 animate-pulse rounded-full bg-indigo-500"></div>
            <span class="text-sm font-medium text-slate-600">
              <span class="font-bold text-indigo-600">{{ records.length }}</span> records found
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 shadow-xl backdrop-blur-sm">
      <div class="border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-5">
        <h2 class="text-xl font-bold text-slate-900">Performance Records</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-[1400px] w-full divide-y divide-slate-200/60">
          <thead class="bg-gradient-to-r from-slate-50 to-slate-100/50">
            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
              <th
                v-for="column in tableColumns"
                :key="column.key"
                class="px-6 py-4"
                :class="column.align === 'right' ? 'text-right' : ''"
              >
                {{ column.label }}
              </th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100/60 bg-white">
            <tr v-if="records.length === 0">
              <td :colspan="tableColumns.length" class="px-6 py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="rounded-full bg-slate-100 p-4">
                    <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-slate-500">No performance records found.</p>
                </div>
              </td>
            </tr>

            <tr
              v-for="record in records"
              :key="record.id"
              class="transition-all duration-200 hover:bg-gradient-to-r hover:from-indigo-50/30 hover:to-purple-50/30"
            >
              <template v-for="column in tableColumns" :key="column.key">
                <td v-if="column.kind === 'teacher'" class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 text-sm font-bold text-white shadow-lg shadow-indigo-500/30">
                      {{ record.teacher_name?.charAt(0) || '?' }}
                    </div>
                    <div class="font-semibold text-slate-900">{{ record.teacher_name || '-' }}</div>
                  </div>
                </td>

                <td v-else-if="column.kind === 'subject'" class="px-6 py-4">
                  <span class="inline-flex items-center rounded-xl bg-gradient-to-r from-indigo-50 to-blue-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm">
                    {{ record.subject || '-' }}
                  </span>
                </td>

                <td v-else-if="column.kind === 'text'" class="px-6 py-4 text-sm font-medium text-slate-700">
                  {{ record[column.field] || '-' }}
                </td>

                <td v-else-if="column.kind === 'tc'" class="px-6 py-4">
                  <span
                    class="inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-semibold shadow-sm"
                    :class="parseFloat(record[column.field]) < 0
                      ? 'bg-gradient-to-r from-rose-100 to-pink-100 text-rose-800'
                      : 'bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-800'"
                  >
                    {{ formatScore(record[column.field]) }}
                  </span>
                </td>

                <td v-else-if="column.kind === 'agp'" class="px-6 py-4">
                  <span
                    class="inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-bold shadow-sm"
                    :class="parseFloat(record[column.field]) < 0
                      ? 'bg-gradient-to-r from-rose-100 to-pink-100 text-rose-800'
                      : 'bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-800'"
                  >
                    {{ formatScore(record[column.field]) }}
                  </span>
                </td>

                <td v-else-if="column.kind === 'comment'" class="px-6 py-4 text-sm text-slate-600">
                  {{ getCommentForRecord(record) }}
                </td>

                <td v-else-if="column.kind === 'actions'" class="px-6 py-4">
                  <div class="flex justify-end gap-2">
                    <button
                      @click="editRecord(record)"
                      class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-blue-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-indigo-500/20"
                    >
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                      Edit
                    </button>

                    <button
                      @click="deleteRecord(record.id)"
                      class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-rose-200 bg-gradient-to-r from-rose-50 to-pink-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-rose-500/20"
                    >
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                      Delete
                    </button>
                  </div>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-3xl bg-white shadow-2xl ring-1 ring-white/20">
        <div class="pointer-events-none absolute inset-0 rounded-3xl bg-gradient-to-br from-indigo-500/5 to-purple-500/5"></div>

        <div class="relative flex-shrink-0 border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-6">
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
                  <svg class="w-5 h-5 text-gray-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            <div class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-blue-50 to-indigo-50 p-5 shadow-sm">
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
                  class="rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-blue-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                >
                  {{ savingTerm1 ? 'Saving...' : 'Save Term 1' }}
                </button>
              </div>
            </div>

            <!-- Term 2 Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-emerald-50 to-teal-50 p-5 shadow-sm">
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
                  class="rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                >
                  {{ savingTerm2 ? 'Saving...' : 'Save Term 2' }}
                </button>
              </div>
            </div>

            <!-- Term 3 Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-amber-50 to-orange-50 p-5 shadow-sm">
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
                  class="rounded-2xl bg-gradient-to-r from-amber-600 to-orange-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-amber-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-amber-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
                >
                  {{ savingTerm3 ? 'Saving...' : 'Save Term 3' }}
                </button>
              </div>
            </div>

            <!-- Final Calculations Section -->
            <div class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-purple-50 to-pink-50 p-5 shadow-sm">
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
                  class="rounded-2xl bg-gradient-to-r from-purple-600 to-pink-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-purple-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-purple-500/40"
                >
                  Calculate AGP
                </button>
              </div>
            </div>

            <div v-if="error" class="rounded-2xl border border-rose-200/60 bg-gradient-to-r from-rose-50 to-pink-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
              {{ error }}
            </div>
          </div>

          <div class="relative flex-shrink-0 border-t border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-5">
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

const records = ref([]);
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref('');
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
  try {
    const result = await subjectPerformanceAPI.getAll(filterYear.value, filterTerm.value || null);
    if (result.success) {
      records.value = result.data.data || result.data || [];
    }
  } catch (err) {
    console.error('Failed to load data:', err);
  }
};

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
      fillColor: [59, 130, 246],
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
  border-color: rgb(99 102 241);
  box-shadow: 0 0 0 4px rgb(238 242 255);
}

.input:hover:not(:focus) {
  border-color: rgb(203 213 225);
}
</style>
