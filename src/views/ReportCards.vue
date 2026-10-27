<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Report Cards</h1>
        </div>
        <RouterLink to="/report-settings" class="btn-hero"><Settings class="h-4 w-4" />Logo, address & grading</RouterLink>
      </div>
    </div>

    <div class="card p-4 sm:p-5">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1.4fr_auto] lg:items-end">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Term</span>
          <select v-model="sessionId" class="input">
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ label(s) }}{{ Number(s.is_active) ? ' (open)' : '' }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Class stream</span>
          <select v-model="classId" class="input">
            <option :value="null" disabled>Choose a class</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.full_class_name }}</option>
          </select>
        </label>
        <div class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Print for</span>
          <div class="flex rounded-xl border border-gray-300 p-1">
            <button v-for="m in modes" :key="m.value" type="button" class="flex-1 rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="mode === m.value ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50'" @click="mode = m.value">{{ m.label }}</button>
          </div>
        </div>
        <button type="button" class="btn-primary" :disabled="!sessionId || !classId || loading" @click="load"><RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" />{{ data ? 'Refresh' : 'Load class' }}</button>
      </div>

      <div v-if="mode === 'single' && data?.students?.length" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Student</span>
          <select v-model="studentId" class="input">
            <option :value="null" disabled>Choose a student</option>
            <option v-for="c in sortedByName" :key="c.student.id" :value="c.student.id">{{ c.student.full_name }} · {{ c.student.admission_number }}</option>
          </select>
        </label>
        <div class="flex gap-2">
          <button type="button" class="btn-secondary" :disabled="!studentId" @click="preview(selected)"><Eye class="h-4 w-4" />Preview</button>
          <button type="button" class="btn-primary" :disabled="!studentId" @click="download(selected)"><Download class="h-4 w-4" />Download PDF</button>
        </div>
      </div>
    </div>

    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <div v-if="data" class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
        <div>
          <h2 class="card-title">{{ data.class.full_class_name }} · {{ data.session?.session_name }}</h2>
          <p class="text-xs text-gray-500">
            {{ data.students.length }} student(s) · exams: {{ data.exams.map((e) => `${e.name} (/${e.out_of})`).join(', ') || 'none yet' }} · final = AOIs /20 + exams /80
          </p>
        </div>
        <div v-if="mode === 'bulk' && data.students.length" class="flex gap-2">
          <button type="button" class="btn-secondary" @click="preview()"><Eye class="h-4 w-4" />Preview</button>
          <button type="button" class="btn-primary" @click="download()"><Download class="h-4 w-4" />Download all {{ data.students.length }}</button>
        </div>
      </div>
      <div v-if="data.message || !data.students.length" class="px-5 py-10 text-center text-sm text-gray-500">{{ data.message || 'No active students are registered in this class.' }}</div>
      <div v-else class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th class="w-16">Pos.</th><th>Student</th><th>Subjects</th><th>Average</th><th>Grade</th><th class="text-right">Report card</th></tr></thead>
          <tbody>
            <tr v-for="c in ranked" :key="c.student.id">
              <td class="font-semibold tabular-nums text-gray-900">{{ c.position ?? '-' }}</td>
              <td>
                <p class="font-medium text-gray-900">{{ c.student.full_name }}</p>
                <p class="text-xs text-gray-500">{{ c.student.admission_number }}</p>
              </td>
              <td class="tabular-nums">{{ c.subjects.length }}</td>
              <td class="tabular-nums">{{ c.average !== null ? `${c.average}%` : '-' }}</td>
              <td><span v-if="c.overall?.grade" class="code-badge">{{ c.overall.grade }}</span><span v-else class="text-gray-400">-</span></td>
              <td>
                <div class="flex justify-end gap-3">
                  <button type="button" class="link-btn" @click="preview(c)">Preview</button>
                  <button type="button" class="link-btn" @click="download(c)">PDF</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-else-if="!loading" class="card px-5 py-12 text-center">
      <FileBadge class="mx-auto h-8 w-8 text-gray-300" />
      <p class="mt-2 text-sm text-gray-600">Choose a term and a class stream, then load it.</p>
    </div>

    <!-- PDF preview -->
    <div v-if="previewUrl" class="fixed inset-0 z-50 flex flex-col bg-black/60 p-3 sm:p-6" @click.self="closePreview">
      <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
          <p class="text-sm font-semibold text-gray-900">{{ previewName }}</p>
          <div class="flex gap-2">
            <a :href="previewUrl" :download="previewName" class="btn-primary px-3 py-1.5 text-xs"><Download class="h-3.5 w-3.5" />Download</a>
            <button type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="closePreview">Close</button>
          </div>
        </div>
        <iframe :src="previewUrl" title="Report card preview" class="flex-1"></iframe>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Download, Eye, FileBadge, RefreshCw, Settings } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import { classesAPI, reportCardsAPI } from '../services/api.js';
import { useSessions } from '../composables/useSessions.js';
import { useToast } from '../composables/useToast.js';
import { apiError } from '../utils/timetable.js';
import { buildReportCards, reportFileName } from '../utils/reportCard.js';

const { sessions, sessionId, load: loadSessions, label } = useSessions();
const { showToast } = useToast();
const modes = [{ value: 'bulk', label: 'Whole stream' }, { value: 'single', label: 'One student' }];

const classes = ref([]);
const classId = ref(null);
const mode = ref('bulk');
const studentId = ref(null);
const data = ref(null);
const loading = ref(false);
const error = ref('');
const previewUrl = ref('');
const previewName = ref('');

const ranked = computed(() => [...(data.value?.students || [])].sort((a, b) => (a.position ?? 1e9) - (b.position ?? 1e9) || a.student.full_name.localeCompare(b.student.full_name)));
const sortedByName = computed(() => [...(data.value?.students || [])].sort((a, b) => a.student.full_name.localeCompare(b.student.full_name)));
const selected = computed(() => data.value?.students.find((c) => c.student.id === studentId.value) || null);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    data.value = (await reportCardsAPI.get(sessionId.value, classId.value)).data;
    if (!data.value.students.some((c) => c.student.id === studentId.value)) studentId.value = null;
  } catch (e) {
    data.value = null;
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
}

function makePdf(card) {
  const doc = buildReportCards(data.value, card ? [card] : data.value.students);
  return { doc, name: reportFileName(data.value, card) };
}
function download(card) {
  try {
    const { doc, name } = makePdf(card);
    doc.save(name);
  } catch (e) {
    showToast(`Could not build the PDF: ${e.message}`, 'error');
  }
}
function preview(card) {
  try {
    const { doc, name } = makePdf(card);
    closePreview();
    previewUrl.value = URL.createObjectURL(doc.output('blob'));
    previewName.value = name;
  } catch (e) {
    showToast(`Could not build the PDF: ${e.message}`, 'error');
  }
}
function closePreview() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = '';
}

watch([sessionId, classId], () => {
  data.value = null;
  if (sessionId.value && classId.value) load();
});

onMounted(async () => {
  try {
    const [, cls] = await Promise.all([loadSessions(), classesAPI.getAll()]);
    classes.value = (cls?.data || []).slice().sort((a, b) => a.full_class_name.localeCompare(b.full_class_name, undefined, { numeric: true }));
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
});
</script>
