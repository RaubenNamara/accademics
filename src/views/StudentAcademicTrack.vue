<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Academic Track</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero-primary" @click="showAddModal = true"><Plus class="h-4 w-4" />Add result</button>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <ListToolbar
      v-model:search="studentSearch"
      placeholder="Search student name or admission number"
      :filter-count="[filters.term, filters.subject_id, filters.class, filters.stream].filter(Boolean).length"
      @clear="clearFilters"
    >
      <template #filters>
        <label class="block">
          <span class="filter-label">Year</span>
          <select v-model="filters.year" class="filter-select" @change="loadResults">
            <option value="">All years</option>
            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Term</span>
          <select v-model="filters.term" class="filter-select" @change="loadResults">
            <option value="">All terms</option>
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Subject</span>
          <select v-model="filters.subject_id" class="filter-select" @change="loadResults">
            <option value="">All subjects</option>
            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.subject_name }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Class</span>
          <select v-model="filters.class" class="filter-select" @change="loadResults">
            <option value="">All classes</option>
            <option v-for="classItem in classes" :key="classItem" :value="classItem">{{ classItem }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Stream</span>
          <select v-model="filters.stream" class="filter-select" @change="loadResults">
            <option value="">All streams</option>
            <option v-for="stream in streamOptions" :key="stream" :value="stream">{{ stream }}</option>
          </select>
        </label>
      </template>
      <template #actions>
        <button type="button" class="tool-btn" :disabled="loading" title="Refresh" @click="loadResults"><RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" /><span class="hidden lg:inline">Refresh</span></button>
        <button type="button" class="tool-btn" :disabled="results.length === 0" title="Download PDF" @click="downloadPDF"><FileText class="h-4 w-4" /><span class="hidden lg:inline">PDF</span></button>
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ visibleStudents.length }}</strong> student{{ visibleStudents.length === 1 ? '' : 's' }} · {{ results.length }} mark{{ results.length === 1 ? '' : 's' }}</p>
      </div>
      <div v-if="loading && pivotData.length === 0" class="space-y-3 p-5">
        <div v-for="i in 5" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="visibleStudents.length === 0" class="px-5 py-14 text-center">
        <GraduationCap class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No academic results found</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Student</th>
              <th class="px-4 py-3">Class</th>
              <th class="hidden px-4 py-3 lg:table-cell">Marks</th>
              <th class="px-4 py-3">Standing</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="student in pageStudents" :key="student.student_id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <button type="button" class="flex min-w-0 items-center gap-3 text-left" @click="viewStudent(student)">
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(student.student_name) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900 hover:text-brand-700">{{ student.student_name }}</span>
                    <span class="block font-mono text-[11px] text-gray-500">{{ student.admission_number }}</span>
                  </span>
                </button>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ student.class || '-' }} {{ student.stream || '' }}</td>
              <td class="hidden px-4 py-3 lg:table-cell">
                <div class="flex flex-wrap gap-1">
                  <span v-for="(m, sid) in student.subjects" :key="sid" class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600" :title="m.subject_name">{{ subjectCode(sid, m.subject_name) }} <strong class="text-gray-800">{{ Number(m.marks) }}</strong><span v-if="m.grade" class="ml-1 rounded px-1 font-semibold" :class="getGradeClass(m.grade)">{{ m.grade }}</span></span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="getPredictionClass(student)">{{ getPrediction(student) }}</span>
              </td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${student.student_name}`" :items="studentActions(student)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="student in pageStudents" :key="student.student_id" class="flex items-start gap-3 px-4 py-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(student.student_name) }}</span>
            <button type="button" class="min-w-0 flex-1 text-left" @click="viewStudent(student)">
              <p class="truncate font-semibold text-gray-900">{{ student.student_name }}</p>
              <p class="text-xs text-gray-500">{{ student.admission_number }} · {{ student.class || '-' }} {{ student.stream || '' }} · {{ Object.keys(student.subjects).length }} subjects</p>
              <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="getPredictionClass(student)">{{ getPrediction(student) }}</span>
            </button>
            <RowActionsMenu :label="`Actions for ${student.student_name}`" :items="studentActions(student)" />
          </li>
        </ul>
        <PagerBar v-model:page="page" v-model:per-page="perPage" :total="visibleStudents.length" />
      </template>
    </section>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="border-b border-slate-200 px-6 py-4">
          <h3 class="text-xl font-bold text-slate-900">{{ showEditModal ? 'Edit Student Results' : 'Add Student Results' }}</h3>
        </div>
        <div class="overflow-y-auto px-6 py-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Student *</label>
              <select v-model="formData.student_id" required class="input">
                <option value="">Select Student</option>
                <option v-for="student in students" :key="student.id" :value="student.id">
                  {{ student.full_name }} ({{ student.admission_number }})
                </option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Class</label>
              <select v-model="formData.class" class="input">
                <option value="">Select Class</option>
                <option v-for="classItem in classes" :key="classItem" :value="classItem">
                  {{ classItem }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Stream</label>
              <select v-model="formData.stream" class="input">
                <option value="">Select Stream</option>
                <option v-for="stream in streamOptions" :key="stream" :value="stream">
                  {{ stream }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Year *</label>
              <input v-model="formData.year" type="number" required min="2020" max="2030" class="input">
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Term *</label>
              <select v-model="formData.term" required class="input">
                <option value="">Select Term</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Exam Type *</label>
              <select v-model="formData.exam_type" required class="input">
                <option value="">Select Exam Type</option>
                <option value="BOT1">BOT 1</option>
                <option value="EOT1">EOT 1</option>
                <option value="BOT2">BOT 2</option>
                <option value="EOT2">EOT 2</option>
                <option value="BOT3">BOT 3</option>
                <option value="EOT3">EOT 3</option>
                <option value="FINAL">FINAL</option>
              </select>
            </div>
          </div>
          
          <div class="mt-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">Subject Marks</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
              <div v-for="subject in subjects" :key="subject.id" class="rounded-lg border border-slate-200 p-4">
                <label class="mb-2 block text-sm font-medium text-slate-700">{{ subject.subject_name }}</label>
                <input 
                  v-model.number="formData.subject_marks[subject.id]" 
                  type="number" 
                  min="0" 
                  max="100" 
                  placeholder="Enter marks (0-100)"
                  class="input"
                >
              </div>
            </div>
          </div>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
          <div class="flex justify-end gap-3">
            <button @click="closeModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
              Cancel
            </button>
            <button @click="saveResult" :disabled="saving" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save Results' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Student Detail Modal -->
    <div v-if="showDetailModal && selectedStudent" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="flex max-h-[90vh] w-full max-w-6xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="border-b border-slate-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-xl font-bold text-slate-900">Student Details</h3>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="overflow-y-auto px-6 py-6">
          <!-- Student Info -->
          <div class="mb-6 rounded-2xl bg-brand-50 p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <p class="text-sm text-slate-500">Student Name</p>
                <p class="text-lg font-semibold text-slate-900">{{ selectedStudent.student_name }}</p>
              </div>
              <div>
                <p class="text-sm text-slate-500">Class</p>
                <p class="text-lg font-semibold text-slate-900">{{ selectedStudent.class || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-slate-500">Stream</p>
                <p class="text-lg font-semibold text-slate-900">{{ selectedStudent.stream || '-' }}</p>
              </div>
            </div>
          </div>

          <!-- Performance Status -->
          <div class="mb-6 rounded-2xl border border-slate-200 p-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">Current Performance Status</h4>
            <div class="flex items-center gap-4">
              <span class="inline-flex rounded-full px-4 py-2 text-sm font-bold" :class="getPredictionClass(selectedStudent)">
                {{ getPrediction(selectedStudent) }}
              </span>
            </div>
          </div>

          <!-- Performance Graph -->
          <div class="mb-6 rounded-2xl border border-slate-200 p-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">
              Performance Over Years {{ isOLevel ? '(4 Years - O-Level)' : '(2 Years - A-Level)' }}
            </h4>
            <div v-if="performanceData.length > 0" class="h-64">
              <svg viewBox="0 0 400 200" class="h-full w-full">
                <!-- Grid lines -->
                <line v-for="i in 5" :key="i" x1="40" :y1="20 + (i - 1) * 40" x2="380" :y2="20 + (i - 1) * 40" stroke="#e2e8f0" stroke-width="1" />
                <line x1="40" y1="20" x2="40" y2="180" stroke="#94a3b8" stroke-width="2" />
                <line x1="40" y1="180" x2="380" y2="180" stroke="#94a3b8" stroke-width="2" />
                
                <!-- Y-axis labels -->
                <text x="30" y="25" text-anchor="end" font-size="10" fill="#64748b">100</text>
                <text x="30" y="65" text-anchor="end" font-size="10" fill="#64748b">75</text>
                <text x="30" y="105" text-anchor="end" font-size="10" fill="#64748b">50</text>
                <text x="30" y="145" text-anchor="end" font-size="10" fill="#64748b">25</text>
                <text x="30" y="185" text-anchor="end" font-size="10" fill="#64748b">0</text>
                
                <!-- X-axis labels and data points -->
                <template v-for="(data, index) in performanceData.slice(0, isOLevel ? 4 : 2)" :key="data.year">
                  <text :x="60 + index * 90" y="195" text-anchor="middle" font-size="10" fill="#64748b">{{ data.year }}</text>
                  <circle 
                    :cx="60 + index * 90" 
                    :cy="180 - (data.average / 100) * 160" 
                    r="4" 
                    fill="#16a34a"
                  />
                  <text :x="60 + index * 90" :cy="175 - (data.average / 100) * 160" text-anchor="middle" font-size="10" font-weight="bold" fill="#16a34a">
                    {{ data.average.toFixed(1) }}
                  </text>
                </template>
                
                <!-- Line connecting points -->
                <polyline 
                  v-if="performanceData.length > 1"
                  :points="performanceData.slice(0, isOLevel ? 4 : 2).map((data, index) => `${60 + index * 90},${180 - (data.average / 100) * 160}`).join(' ')"
                  fill="none"
                  stroke="#16a34a"
                  stroke-width="2"
                />
              </svg>
            </div>
            <div v-else class="h-64 flex items-center justify-center bg-slate-50 rounded-xl">
              <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <p class="mt-2 text-sm text-slate-500">No performance data available</p>
              </div>
            </div>
          </div>

          <!-- Subject Details -->
          <div class="rounded-2xl border border-slate-200 p-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">Subject Performance</h4>
            <div class="space-y-3">
              <div v-for="(subject, subjectId) in selectedStudent.subjects" :key="subjectId" class="flex items-center justify-between rounded-lg bg-slate-50 p-4">
                <div>
                  <p class="font-medium text-slate-900">{{ subject.subject_name }}</p>
                  <p class="text-sm text-slate-500">{{ subject.exam_type }}</p>
                </div>
                <div class="text-right">
                  <p class="text-lg font-semibold text-slate-900">{{ subject.marks }}</p>
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold" :class="getGradeClass(subject.grade)">
                    {{ subject.grade }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '../services/api.js';
import { classesAPI, subjectsNewAPI } from '../services/api';
import authStore from '../services/authStore.js';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import { Plus, RefreshCw, FileText, GraduationCap, Eye, Pencil, Trash2, Users as UsersIcon, ClipboardList, Gauge, Star } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import PagerBar from '../components/ui/PagerBar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';

const results = ref([]);
const students = ref([]);
const subjects = ref([]);
const allClasses = ref([]);
const streams = ref([]);
const years = ref([]);
const yearOptions = ref([]);

// Generate year options from 2020 to 2033
for (let year = 2020; year <= 2033; year++) {
  yearOptions.value.push(year.toString());
}
const pivotData = ref([]);
const loading = ref(false);
const studentSearch = ref('');
const page = ref(1);
const perPage = ref(25);
const initials = (name) => String(name || '?').split(/s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const shortSubject = (name) => {
  const words = String(name || '').split(/s+/).filter(Boolean);
  return words.length > 1 ? words.map((w) => w[0]).join('').toUpperCase() : String(name || '').slice(0, 4);
};
const subjectCode = (id, name) => subjects.value.find((x) => String(x.id) === String(id))?.subject_code || shortSubject(name);
const studentAverage = (student) => {
  const marks = Object.values(student.subjects).map((m) => parseFloat(m.marks)).filter((n) => !Number.isNaN(n));
  return marks.length ? marks.reduce((a, b) => a + b, 0) / marks.length : null;
};
const visibleStudents = computed(() => {
  const q = studentSearch.value.trim().toLowerCase();
  if (!q) return pivotData.value;
  return pivotData.value.filter((st) => `${st.student_name} ${st.admission_number}`.toLowerCase().includes(q));
});
const pageStudents = computed(() => visibleStudents.value.slice((page.value - 1) * perPage.value, page.value * perPage.value));
watch([studentSearch, perPage, pivotData], () => { page.value = 1; });
const studentActions = (student) => [
  { label: 'View', icon: Eye, onClick: () => viewStudent(student) },
  { label: 'Edit', icon: Pencil, onClick: () => editStudent(student) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteStudent(student) },
];
const summaryCards = computed(() => {
  const avgs = pivotData.value.map(studentAverage).filter((n) => n !== null);
  const overall = avgs.length ? Math.round(avgs.reduce((a, b) => a + b, 0) / avgs.length) : null;
  return [
    { label: 'Students', value: pivotData.value.length.toLocaleString(), icon: UsersIcon },
    { label: 'Marks entered', value: results.value.length.toLocaleString(), icon: ClipboardList, tone: 'sky' },
    { label: 'Average mark', value: overall === null ? '-' : overall + '%', icon: Gauge, tone: 'gray' },
    { label: '70% and above', value: avgs.filter((n) => n >= 70).length.toLocaleString(), note: 'outstanding or better', icon: Star, tone: 'amber' },
  ];
});

const filters = ref({
  student_id: '',
  class: '',
  stream: '',
  year: new Date().getFullYear().toString(),
  term: '',
  subject_id: ''
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDetailModal = ref(false);
const saving = ref(false);
const selectedStudent = ref(null);
const studentHistory = ref([]);

const formData = ref({
  student_id: '',
  class: '',
  stream: '',
  year: new Date().getFullYear(),
  term: '',
  exam_type: '',
  subject_marks: {} // Will hold { subject_id: marks }
});

const editingId = ref(null);

const classes = computed(() => {
  const uniqueClassNames = [...new Set(allClasses.value.map(c => c.class_name))];
  return uniqueClassNames.sort();
});

const streamOptions = computed(() => {
  const uniqueStreamNames = [...new Set(allClasses.value.map(c => c.stream_name))];
  return uniqueStreamNames.sort();
});

const loadStudents = async () => {
  try {
    const response = await api.get(`students.php?action=list&limit=1000`);
    students.value = response.data.data;
  } catch (error) {
    console.error('Error loading students:', error);
  }
};

const loadSubjects = async () => {
  try {
    const response = await subjectsNewAPI.getAll();
    subjects.value = response.data || [];
  } catch (error) {
    console.error('Error loading subjects:', error);
  }
};

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    allClasses.value = response.data || [];
    console.log('Classes loaded:', allClasses.value);
    console.log('Unique classes:', classes.value);
    console.log('Unique streams:', streamOptions.value);
  } catch (error) {
    console.error('Error loading classes:', error);
  }
};

useGlobalTerm((y, t) => { filters.value.year = String(y); filters.value.term = String(t); }, () => loadResults());

const loadResults = async () => {
  loading.value = true;
  try {
    const token = localStorage.getItem('token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};
    
    const params = new URLSearchParams();
    if (filters.value.student_id) params.append('student_id', filters.value.student_id);
    if (filters.value.class) params.append('class', filters.value.class);
    if (filters.value.stream) params.append('stream', filters.value.stream);
    if (filters.value.year) params.append('year', filters.value.year);
    if (filters.value.term) params.append('term', filters.value.term);
    if (filters.value.subject_id) params.append('subject_id', filters.value.subject_id);
    
    // Debug: Log the API call
    console.log('API Call URL:', `student-results.php?action=list&${params}`);
    console.log('Current filters:', filters.value);
    
    const response = await api.get(`student-results.php?action=list&${params}`, { headers });
    
    console.log('API Response:', response.data);
    results.value = response.data.data;
    transformToPivot();
  } catch (error) {
    console.error('Error loading results:', error);
    console.error('Error response:', error.response?.data);
    if (error.response?.status === 401) {
      alert('Authentication required. Please log in to access this feature.');
    }
  } finally {
    loading.value = false;
  }
};

const transformToPivot = () => {
  // Group results by student
  const studentMap = new Map();
  
  results.value.forEach(result => {
    if (!studentMap.has(result.student_id)) {
      studentMap.set(result.student_id, {
        student_id: result.student_id,
        student_name: result.student_name,
        admission_number: result.admission_number,
        class: result.class,
        stream: result.stream,
        subjects: {}
      });
    }
    
    const student = studentMap.get(result.student_id);
    student.subjects[result.subject_id] = {
      subject_name: result.subject_name,
      marks: result.marks,
      grade: result.grade,
      exam_type: result.exam_type
    };
  });
  
  pivotData.value = Array.from(studentMap.values());
};

const clearFilters = () => {
  filters.value = {
    student_id: '',
    class: '',
    stream: '',
    year: new Date().getFullYear().toString(),
    term: '',
    subject_id: ''
  };
  loadResults();
};

const saveResult = async () => {
  // Debug: Check if token exists
  const token = localStorage.getItem('token');
  console.log('Token exists:', !!token);
  console.log('Token value:', token ? token.substring(0, 20) + '...' : 'null');
  
  saving.value = true;
  try {
    const config = {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    };
    
    // Prepare results for each subject with marks
    const resultsToSave = [];
    for (const [subjectId, marks] of Object.entries(formData.value.subject_marks)) {
      if (marks !== '' && marks !== null && marks !== undefined) {
        resultsToSave.push({
          student_id: formData.value.student_id,
          subject_id: parseInt(subjectId),
          class: formData.value.class || '',
          stream: formData.value.stream || '',
          year: formData.value.year,
          term: formData.value.term,
          exam_type: formData.value.exam_type,
          marks: marks
        });
      }
    }
    
    if (resultsToSave.length === 0) {
      alert('Please enter marks for at least one subject');
      return;
    }
    
    // Save all results at once
    const response = await api.post(`student-results.php?action=bulk-create`, { results: resultsToSave }, config);
    console.log('Bulk create response:', response);
    
    closeModal();
    loadResults();
  } catch (error) {
    console.error('Error saving results:', error);
    console.error('Error config:', error.config);
    console.error('Error response:', error.response);
    console.error('Error data:', error.response?.data);
    console.error('Error status:', error.response?.status);
    
    if (error.response?.status === 401) {
      alert('Authentication failed. The backend is rejecting the token. Check server logs for details.');
    } else {
      alert(error.response?.data?.error || error.response?.data?.message || error.message || 'Error saving results');
    }
  } finally {
    saving.value = false;
  }
};

const editResult = (result) => {
  editingId.value = result.id;
  formData.value = {
    student_id: result.student_id,
    subject_id: result.subject_id,
    class: result.class || '',
    stream: result.stream || '',
    year: result.year,
    term: result.term,
    exam_type: result.exam_type,
    marks: result.marks,
    remarks: result.remarks || ''
  };
  showEditModal.value = true;
};

const deleteResult = async (result) => {
  if (!confirm(`Are you sure you want to delete this result?`)) return;
  
  try {
    await api.delete(`student-results.php?action=delete&id=${result.id}`);
    loadResults();
  } catch (error) {
    console.error('Error deleting result:', error);
    alert('Error deleting result');
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  editingId.value = null;
  formData.value = {
    student_id: '',
    class: '',
    stream: '',
    year: new Date().getFullYear(),
    term: '',
    exam_type: '',
    subject_marks: {}
  };
};

const getGradeClass = (grade) => {
  const classes = {
    'A': 'bg-emerald-100 text-emerald-800',
    'B': 'bg-brand-100 text-brand-800',
    'C': 'bg-amber-100 text-amber-800',
    'D': 'bg-orange-100 text-orange-800',
    'E': 'bg-red-100 text-red-800'
  };
  return classes[grade] || 'bg-slate-100 text-slate-800';
};

const getPrediction = (student) => {
  const subjectValues = Object.values(student.subjects);
  if (subjectValues.length === 0) return '-';
  
  // Calculate average only for subjects with marks
  const subjectsWithMarks = subjectValues.filter(subject => 
    subject.marks !== '' && 
    subject.marks !== null && 
    subject.marks !== undefined && 
    !isNaN(subject.marks)
  );
  if (subjectsWithMarks.length === 0) return '-';
  
  const sumMarks = subjectsWithMarks.reduce((sum, subject) => sum + (parseFloat(subject.marks) || 0), 0);
  const averageMarks = sumMarks / subjectsWithMarks.length;
  
  // Determine status based on average
  if (averageMarks >= 80) return `Exceptional (${averageMarks.toFixed(2)}%)`;
  if (averageMarks >= 70) return `Outstanding (${averageMarks.toFixed(2)}%)`;
  if (averageMarks >= 60) return `Satisfactory (${averageMarks.toFixed(2)}%)`;
  if (averageMarks >= 50) return `Basic (${averageMarks.toFixed(2)}%)`;
  return `Elementary (${averageMarks.toFixed(2)}%)`;
};

const getPredictionClass = (student) => {
  const subjectValues = Object.values(student.subjects);
  if (subjectValues.length === 0) return 'bg-slate-100 text-slate-800';
  
  // Calculate average only for subjects with marks
  const subjectsWithMarks = subjectValues.filter(subject => 
    subject.marks !== '' && 
    subject.marks !== null && 
    subject.marks !== undefined && 
    !isNaN(subject.marks)
  );
  if (subjectsWithMarks.length === 0) return 'bg-slate-100 text-slate-800';
  
  const sumMarks = subjectsWithMarks.reduce((sum, subject) => sum + (parseFloat(subject.marks) || 0), 0);
  const averageMarks = sumMarks / subjectsWithMarks.length;
  
  if (averageMarks >= 80) return 'bg-emerald-100 text-emerald-800';
  if (averageMarks >= 70) return 'bg-brand-100 text-brand-800';
  if (averageMarks >= 60) return 'bg-amber-100 text-amber-800';
  if (averageMarks >= 50) return 'bg-orange-100 text-orange-800';
  return 'bg-red-100 text-red-800';
};

const editStudent = (student) => {
  // Populate form with student's existing results
  formData.value = {
    student_id: student.student_id,
    class: student.class || '',
    stream: student.stream || '',
    year: new Date().getFullYear(),
    term: '1',
    exam_type: 'BOT1',
    subject_marks: {}
  };
  
  // Populate existing marks for each subject
  Object.keys(student.subjects).forEach(subjectId => {
    formData.value.subject_marks[subjectId] = student.subjects[subjectId].marks;
  });
  
  editingId.value = null;
  showEditModal.value = true;
};

const viewStudent = (student) => {
  console.log('viewStudent called', student);
  selectedStudent.value = student;
  // Use current student data from pivotData for the graph
  studentHistory.value = [];
  showDetailModal.value = true;
  console.log('Modal state:', showDetailModal.value);
};

const isOLevel = computed(() => {
  if (!selectedStudent.value) return false;
  const classLevel = selectedStudent.value.class?.toLowerCase() || '';
  return classLevel.includes('s1') || classLevel.includes('s2') || classLevel.includes('s3') || classLevel.includes('s4');
});

const performanceData = computed(() => {
  if (!selectedStudent.value) return [];
  
  // Use current student's subjects data for the graph
  const subjects = Object.values(selectedStudent.value.subjects || {});
  if (subjects.length === 0) return [];
  
  // Calculate average from current subjects
  const marks = subjects.map(s => s.marks).filter(m => m !== null && m !== undefined && !isNaN(m));
  if (marks.length === 0) return [];
  
  const average = marks.reduce((sum, m) => sum + m, 0) / marks.length;
  const currentYear = new Date().getFullYear();
  
  // Return data for current year (can be extended with historical data)
  return [{ year: currentYear, average }];
});

const deleteStudent = async (student) => {
  if (!confirm(`Are you sure you want to delete all results for ${student.student_name}?`)) {
    return;
  }
  
  try {
    const token = localStorage.getItem('token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};
    
    // Delete all results for this student
    const subjectIds = Object.keys(student.subjects);
    for (const subjectId of subjectIds) {
      const result = results.value.find(r => r.student_id === student.student_id && r.subject_id === parseInt(subjectId));
      if (result) {
        await api.delete(`student-results.php?action=delete&id=${result.id}`, { headers });
      }
    }
    
    loadResults();
    alert('Student results deleted successfully');
  } catch (error) {
    console.error('Error deleting student results:', error);
    alert(error.response?.data?.error || error.message || 'Error deleting student results');
  }
};

const downloadPDF = () => {
  const doc = new jsPDF();
  
  // Add title
  doc.setFontSize(18);
  doc.text('Student Academic Track Report', 14, 22);
  
  // Add subtitle with filters
  doc.setFontSize(11);
  doc.setTextColor(100);
  const filterInfo = [
    filters.value.student_id ? `Student: ${students.value.find(s => s.id === filters.value.student_id)?.full_name}` : '',
    filters.value.year ? `Year: ${filters.value.year}` : '',
    filters.value.term ? `Term: ${filters.value.term}` : '',
    filters.value.subject_id ? `Subject: ${subjects.value.find(s => s.id === filters.value.subject_id)?.subject_name}` : ''
  ].filter(Boolean).join(' | ');
  doc.text(filterInfo || 'All Results', 14, 30);
  
  // Add date
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);
  
  // Prepare table data
  const tableData = results.value.map(result => [
    `${result.student_name} (${result.admission_number})`,
    result.subject_name,
    result.year,
    `Term ${result.term}`,
    result.exam_type,
    result.marks,
    result.grade
  ]);
  
  // Add table
  autoTable(doc, {
    head: [['Student', 'Subject', 'Year', 'Term', 'Exam', 'Marks', 'Grade']],
    body: tableData,
    startY: 45,
    styles: {
      fontSize: 9,
      cellPadding: 3,
    },
    headStyles: {
      fillColor: [22, 163, 74],
      textColor: 255,
      fontStyle: 'bold',
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245],
    },
    margin: { top: 10, right: 10, bottom: 10, left: 10 },
  });
  
  // Add footer
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
  
  // Save the PDF
  doc.save(`student-academic-track-${new Date().toISOString().split('T')[0]}.pdf`);
};

onMounted(() => {
  // Debug: Check all localStorage items
  console.log('All localStorage keys:', Object.keys(localStorage));
  console.log('Token from localStorage:', localStorage.getItem('token'));
  console.log('User from localStorage:', localStorage.getItem('user'));
  console.log('authStore token:', authStore.token);
  console.log('authStore user:', authStore.user);
  console.log('authStore isAuthenticated:', authStore.isAuthenticated());
  
  loadStudents();
  loadSubjects();
  loadClasses();
  loadResults();
});
</script>

<style scoped>
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.filter-select { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.input {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.75rem;
  font-size: 0.875rem;
  transition: all 0.2s;
  background-color: white;
}

.input:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
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
</style>
