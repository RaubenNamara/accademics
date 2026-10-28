<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Examinations</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="sessionId" class="input w-auto py-2" aria-label="Term">
            <option v-for="s in sessions" :key="s.id" :value="s.id">{{ label(s) }}{{ Number(s.is_active) ? ' (open)' : '' }}</option>
          </select>
          <button type="button" class="btn-hero-primary" :disabled="!sessionId || termClosed" @click="openForm()"><Plus class="h-4 w-4" />New exam</button>
        </div>
      </div>
    </div>

    <div v-if="termClosed" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
      This term is closed: its exams and marks are kept for report cards but can no longer be changed.
    </div>

    <!-- How the final mark is worked out, and how far marks entry has got -->
    <div class="card flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
      <div
        class="flex flex-wrap items-center gap-2 text-sm"
        title="Each AOI is out of 20 and the AOIs are averaged; BOT, mid term and end of term are each out of 80 and averaged. A learner with only AOIs or only exams so far is scaled to 100."
      >
        <span class="rounded-lg bg-sky-50 px-2.5 py-1 text-sky-800 ring-1 ring-sky-100">AOIs <strong class="tabular-nums">20</strong></span>
        <span class="text-gray-400">+</span>
        <span class="rounded-lg bg-brand-50 px-2.5 py-1 text-brand-800 ring-1 ring-brand-100">Exams <strong class="tabular-nums">80</strong></span>
        <span class="text-gray-400">=</span>
        <span class="rounded-lg bg-gray-900 px-2.5 py-1 text-white">Final <strong class="tabular-nums">100</strong></span>
      </div>
      <div class="ml-auto flex items-center gap-5 text-sm text-gray-500">
        <span>Marks entered <strong class="text-base tabular-nums text-gray-900">{{ marksEntered.toLocaleString() }}</strong></span>
        <span>Open exams <strong class="text-base tabular-nums text-gray-900">{{ openCount }}</strong></span>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
        <h2 class="card-title">Exams this term</h2>
        <span class="text-xs text-gray-500">{{ exams.length }} exam(s)</span>
      </div>
      <div v-if="loading" class="px-5 py-10 text-center text-sm text-gray-500">Loading…</div>
      <div v-else-if="!exams.length" class="px-5 py-12 text-center">
        <ClipboardList class="mx-auto h-8 w-8 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No exams set for this term</p>
        <button v-if="!termClosed" type="button" class="btn-primary mt-4" @click="openForm()"><Plus class="h-4 w-4" />New exam</button>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr><th>Exam</th><th>Type</th><th>Out of</th><th>Counts as</th><th>Who sits</th><th>Status</th><th>Marks in</th><th class="text-right">Actions</th></tr>
          </thead>
          <tbody>
            <tr v-for="e in exams" :key="e.id">
              <td>
                <p class="font-medium text-gray-900">{{ e.name }}</p>
                <p v-if="e.notes" class="max-w-xs truncate text-xs text-gray-500">{{ e.notes }}</p>
              </td>
              <td><span class="code-badge">{{ e.exam_type }}</span></td>
              <td class="tabular-nums">{{ num(e.max_marks) }}</td>
              <td class="tabular-nums">{{ e.exam_type === 'AOI' ? '/20 (AOI)' : '/80 (exam)' }}</td>
              <td>{{ e.class_level === 'all' ? 'All classes' : e.class_level }}</td>
              <td><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusStyle[e.status]">{{ statusLabel[e.status] }}</span></td>
              <td class="tabular-nums">{{ e.marks_entered }}</td>
              <td>
                <div class="flex flex-wrap justify-end gap-1.5">
                  <button v-if="e.status === 'draft'" type="button" class="btn-primary px-3 py-1.5 text-xs" @click="setStatus(e, 'open')">Open for marks</button>
                  <button v-if="e.status === 'open'" type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="setStatus(e, 'closed')">Close</button>
                  <button v-if="e.status === 'closed'" type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="setStatus(e, 'open')">Reopen</button>
                  <button type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="showProgress(e)">Progress</button>
                  <button type="button" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" title="Edit" @click="openForm(e)"><Pencil class="h-4 w-4" /></button>
                  <button type="button" class="rounded-lg p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600" title="Delete" @click="remove(e)"><Trash2 class="h-4 w-4" /></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Marks-entry progress -->
    <div v-if="progress" class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3">
        <div>
          <h2 class="card-title">{{ progress.exam.name }}: marks entry</h2>
          <p class="text-xs text-gray-500">Each class and subject from this term's lesson requirements. {{ progressDone }} of {{ progress.sheets.length }} complete.</p>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="progressFilter" type="search" class="input w-48 py-1.5" placeholder="Filter class, subject, teacher">
          <button type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="progress = null; sheet = null">Hide</button>
        </div>
      </div>
      <div v-if="!progress.sheets.length" class="px-5 py-8 text-center text-sm text-gray-500">
        No lesson requirements are set for this term, so there is nothing to track. Add them under Timetable → Setup → Lesson requirements.
      </div>
      <div v-else class="max-h-[28rem] overflow-auto">
        <table class="data-table">
          <thead><tr><th>Class</th><th>Subject</th><th>Teacher</th><th class="w-56">Entered</th><th class="text-right"></th></tr></thead>
          <tbody>
            <tr v-for="r in filteredProgress" :key="`${r.class_id}-${r.subject_id}`">
              <td class="font-medium text-gray-900">{{ r.full_class_name }}</td>
              <td>{{ r.subject_name }}</td>
              <td class="text-xs">{{ r.teachers }}</td>
              <td>
                <div class="flex items-center gap-2">
                  <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full" :class="r.entered >= r.students && r.students ? 'bg-brand-600' : 'bg-amber-400'" :style="{ width: `${r.students ? Math.min(100, (r.entered / r.students) * 100) : 0}%` }"></div>
                  </div>
                  <span class="w-14 text-right text-xs tabular-nums text-gray-600">{{ r.entered }}/{{ r.students }}</span>
                </div>
              </td>
              <td class="text-right"><button type="button" class="link-btn" @click="sheet = r">Marks</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <MarkSheet
      v-if="sheet && progress"
      :key="`${progress.exam.id}-${sheet.class_id}-${sheet.subject_id}`"
      :exam-id="Number(progress.exam.id)"
      :class-id="Number(sheet.class_id)"
      :subject-id="Number(sheet.subject_id)"
      :title="`${sheet.full_class_name} · ${sheet.subject_name}`"
      closable
      @close="sheet = null"
      @saved="refreshProgress"
    />

    <!-- Create / edit -->
    <div v-if="form" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="form = null">
      <form class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="save">
        <h2 class="text-lg font-semibold text-gray-900">{{ form.id ? 'Edit exam' : 'New exam' }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
            <input v-model="form.name" class="input" maxlength="150" placeholder="e.g. AOI 1, Mid Term, End of Term" required>
            <span v-if="errors.name" class="mt-1 block text-xs text-red-600">{{ errors.name }}</span>
          </label>
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Type</span>
            <select v-model="form.exam_type" class="input" @change="typeDefaults">
              <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Who sits it</span>
            <select v-model="form.class_level" class="input">
              <option value="all">All classes</option>
              <option value="O-Level">O-Level (S1–S4)</option>
              <option value="A-Level">A-Level (S5–S6)</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Marked out of</span>
            <input v-model="form.max_marks" type="number" min="1" max="1000" step="0.5" class="input" required>
            <span v-if="errors.max_marks" class="mt-1 block text-xs text-red-600">{{ errors.max_marks }}</span>
          </label>
          <div class="block">
            <span class="mb-1 block text-sm font-medium text-gray-700">Counts in the final mark as</span>
            <p class="rounded-xl bg-gray-50 px-3.5 py-2.5 text-sm text-gray-800 ring-1 ring-gray-200">{{ form.exam_type === 'AOI' ? 'Out of 20 (AOI part)' : 'Out of 80 (exam part)' }}</p>
          </div>
          <label class="block sm:col-span-2">
            <span class="mb-1 block text-sm font-medium text-gray-700">Notes for teachers (optional)</span>
            <textarea v-model="form.notes" rows="2" maxlength="500" class="input" placeholder="e.g. Activity of integration on Topic 3"></textarea>
          </label>
        </div>
        <p class="mt-3 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600">
          Teachers enter marks out of {{ form.max_marks || '…' }}. {{ form.exam_type === 'AOI' ? 'Each AOI is converted to 20, and a learner\'s AOIs are averaged.' : 'Each exam is converted to 80, and a learner\'s exams are averaged.' }} Final mark = AOIs (20) + exams (80).
        </p>
        <p v-if="formError" class="mt-3 text-sm text-red-600">{{ formError }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="form = null">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : form.id ? 'Save changes' : 'Create exam' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import MarkSheet from '../components/exams/MarkSheet.vue';
import { examsAPI } from '../services/api.js';
import { useSessions } from '../composables/useSessions.js';
import { useToast } from '../composables/useToast.js';
import { apiError } from '../utils/timetable.js';

const { sessions, sessionId, session, load: loadSessions, label } = useSessions();
const termClosed = computed(() => Number(session.value?.is_archived) === 1);
const { showToast } = useToast();

const exams = ref([]);
const loading = ref(false);
const form = ref(null);
const errors = ref({});
const formError = ref('');
const saving = ref(false);
const progress = ref(null);
const progressFilter = ref('');
const sheet = ref(null);

const types = [
  { value: 'AOI', label: 'AOI (Activity of Integration)', max: 3 },
  { value: 'BOT', label: 'Beginning of Term', max: 100 },
  { value: 'MOT', label: 'Mid Term', max: 100 },
  { value: 'EOT', label: 'End of Term', max: 100 },
  { value: 'OTHER', label: 'Other test', max: 100 },
];
const statusLabel = { draft: 'Draft', open: 'Open for marks', closed: 'Closed' };
const statusStyle = {
  draft: 'bg-gray-100 text-gray-700',
  open: 'bg-brand-100 text-brand-800',
  closed: 'bg-slate-200 text-slate-700',
};
const num = (v) => Number(v).toString();

const marksEntered = computed(() => exams.value.reduce((t, e) => t + Number(e.marks_entered), 0));
const openCount = computed(() => exams.value.filter((e) => e.status === 'open').length);

const filteredProgress = computed(() => {
  const q = progressFilter.value.trim().toLowerCase();
  const list = progress.value?.sheets || [];
  return q ? list.filter((r) => `${r.full_class_name} ${r.subject_name} ${r.teachers}`.toLowerCase().includes(q)) : list;
});
const progressDone = computed(() => (progress.value?.sheets || []).filter((r) => r.students && r.entered >= r.students).length);

async function load() {
  if (!sessionId.value) return;
  loading.value = true;
  try {
    exams.value = (await examsAPI.list(sessionId.value)).data.exams;
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    loading.value = false;
  }
}

function openForm(e = null) {
  errors.value = {};
  formError.value = '';
  form.value = e
    ? { id: e.id, name: e.name, exam_type: e.exam_type, max_marks: Number(e.max_marks), class_level: e.class_level, notes: e.notes || '' }
    : { name: '', exam_type: 'AOI', max_marks: 3, class_level: 'all', notes: '' };
}
function typeDefaults() {
  if (form.value.id) return;
  const t = types.find((x) => x.value === form.value.exam_type);
  if (t) form.value.max_marks = t.max;
}

async function save() {
  saving.value = true;
  errors.value = {};
  formError.value = '';
  try {
    const res = await examsAPI.save({ ...form.value, academic_session_id: sessionId.value });
    showToast(res.message);
    form.value = null;
    await load();
  } catch (e) {
    const err = apiError(e);
    errors.value = err.errors;
    formError.value = Object.keys(err.errors).length ? '' : err.message;
  } finally {
    saving.value = false;
  }
}

async function setStatus(e, status) {
  if (status === 'closed' && !confirm(`Close ${e.name}? Teachers will no longer be able to change its marks.`)) return;
  try {
    showToast((await examsAPI.setStatus(e.id, status)).message);
    await load();
    if (progress.value?.exam.id === e.id) await showProgress(e);
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
}

async function remove(e) {
  if (!confirm(`Delete ${e.name}?`)) return;
  try {
    showToast((await examsAPI.remove(e.id)).message);
  } catch (err) {
    const info = apiError(err);
    if (info.data?.needs_confirmation && confirm(info.message)) {
      showToast((await examsAPI.remove(e.id, true)).message);
    } else if (!info.data?.needs_confirmation) {
      showToast(info.message, 'error');
      return;
    } else {
      return;
    }
  }
  if (progress.value?.exam.id === e.id) progress.value = null;
  await load();
}

async function showProgress(e) {
  try {
    progress.value = (await examsAPI.progress(e.id)).data;
    sheet.value = null;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
}
async function refreshProgress() {
  if (progress.value) progress.value = { ...(await examsAPI.progress(progress.value.exam.id)).data };
  await load();
}

watch(sessionId, () => { progress.value = null; sheet.value = null; load(); });
onMounted(async () => {
  try {
    await loadSessions();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
});
</script>
