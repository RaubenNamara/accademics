<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="page-hero-title">Enter Marks</h1>
        <!-- Only when a past term has been reopened for marks -->
        <label v-if="terms.length > 1" class="flex items-center gap-2 text-sm text-gray-600">
          Term
          <select v-model="termId" class="input w-auto py-2" @change="loadTerm(termId)">
            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.academic_year }} Term {{ t.term }}{{ t.current ? ' (current)' : ' (reopened)' }}</option>
          </select>
        </label>
      </div>
    </div>

    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <div class="card p-4 sm:p-5">
      <div class="grid gap-3 sm:grid-cols-3">
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Exam</span>
          <select v-model="examId" class="input" :disabled="!exams.length">
            <option :value="null" disabled>{{ exams.length ? 'Choose an exam' : 'No exams are open yet' }}</option>
            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.name }} · /{{ Number(e.max_marks) }}{{ e.status === 'closed' ? ' (closed)' : '' }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Subject</span>
          <select v-model="subjectId" class="input" :disabled="!subjects.length">
            <option :value="null" disabled>{{ subjects.length ? 'Choose a subject' : exam ? 'None of your subjects sit this exam' : 'Choose an exam first' }}</option>
            <option v-for="s in subjects" :key="s.subject_id" :value="s.subject_id">{{ s.subject_name }}</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-sm font-medium text-gray-700">Class</span>
          <select v-model="classId" class="input" :disabled="!classesForSubject.length">
            <option :value="null" disabled>{{ classesForSubject.length ? 'Choose a class' : 'Choose a subject first' }}</option>
            <optgroup v-if="classesForSubject.some((a) => a.source === 'timetable')" label="Classes you teach">
              <option v-for="a in classesForSubject.filter((x) => x.source === 'timetable')" :key="a.class_id" :value="a.class_id">{{ a.full_class_name }}</option>
            </optgroup>
            <optgroup :label="classesForSubject.some((a) => a.source === 'timetable') ? 'Other classes' : 'Classes'">
              <option v-for="a in classesForSubject.filter((x) => x.source !== 'timetable')" :key="a.class_id" :value="a.class_id">{{ a.full_class_name }}</option>
            </optgroup>
          </select>
        </label>
      </div>
      <p v-if="exam?.notes" class="mt-3 rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-600">{{ exam.notes }}</p>
      <p v-if="!loading && !scope.length" class="mt-3 text-sm text-gray-500">No classes are assigned to you this term yet, so there are no mark sheets. The academic office assigns classes under Teaching Assignments.</p>
    </div>

    <MarkSheet
      v-if="exam && assignment"
      :key="`${exam.id}-${assignment.class_id}-${assignment.subject_id}`"
      :exam-id="Number(exam.id)"
      :class-id="assignment.class_id"
      :subject-id="assignment.subject_id"
      :title="`${assignment.full_class_name} · ${assignment.subject_name}`"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import ToastBanner from '../../components/hr/ToastBanner.vue';
import MarkSheet from '../../components/exams/MarkSheet.vue';
import { examsAPI, teacherPortalAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

const route = useRoute();
const exams = ref([]);
// Every class/subject the teacher may mark: the classes assigned to them this term.
const scope = ref([]);
const examId = ref(null);
const subjectId = ref(null);
const classId = ref(null);
const loading = ref(true);
const error = ref('');

const exam = computed(() => exams.value.find((e) => e.id === examId.value) || null);
const usable = computed(() => (exam.value ? scope.value.filter((a) => exam.value.class_level === 'all' || exam.value.class_level === a.level) : []));
const subjects = computed(() => {
  const seen = new Map();
  usable.value.forEach((a) => { if (!seen.has(a.subject_id)) seen.set(a.subject_id, a); });
  return [...seen.values()].sort((a, b) => a.subject_name.localeCompare(b.subject_name));
});
const classesForSubject = computed(() => usable.value.filter((a) => a.subject_id === subjectId.value));
const assignment = computed(() => classesForSubject.value.find((a) => a.class_id === classId.value) || null);

watch(subjects, (list) => {
  if (!list.some((s) => s.subject_id === subjectId.value)) subjectId.value = list.length === 1 ? list[0].subject_id : null;
});
watch(classesForSubject, (list) => {
  if (!list.some((a) => a.class_id === classId.value)) classId.value = list.length === 1 ? list[0].class_id : null;
});

// The current term, plus earlier terms reopened under Terms & Years (marks can still go in).
const terms = ref([]);
const termId = ref(null);

/** Exams of one term and the classes this teacher has in it. */
async function loadTerm(id) {
  const me = (await teacherPortalAPI.view('me', id ? { academic_session_id: id } : {})).data;
  scope.value = me.mark_scope || me.assignments || [];
  exams.value = me.session.id ? (await examsAPI.list(me.session.id)).data.exams : [];
  exams.value.sort((a, b) => (a.status === 'open' ? 0 : 1) - (b.status === 'open' ? 0 : 1));
  examId.value = exams.value.find((e) => e.status === 'open')?.id ?? null;
  return me;
}

onMounted(async () => {
  try {
    const me = await loadTerm(null);
    termId.value = me.session.id;
    terms.value = [{ ...me.session, current: true }, ...(me.reopened_sessions || [])];
    // Open exams first; the newest open exam is the likely one.
    exams.value.sort((a, b) => (a.status === 'open' ? 0 : 1) - (b.status === 'open' ? 0 : 1));
    // A link may name the exam (dashboard "Continue"); otherwise the first open one.
    const wantedExam = Number(route.query.exam_id);
    examId.value = (wantedExam && exams.value.some((e) => e.id === wantedExam) ? wantedExam : null)
      ?? exams.value.find((e) => e.status === 'open')?.id ?? null;
    // A link may also name the subject, and the class to continue with.
    const wantedSubject = Number(route.query.subject_id);
    const wantedClass = Number(route.query.class_id);
    if (wantedSubject && usable.value.some((a) => a.subject_id === wantedSubject)) {
      subjectId.value = wantedSubject;
      if (wantedClass && usable.value.some((a) => a.subject_id === wantedSubject && a.class_id === wantedClass)) classId.value = wantedClass;
    }
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
});
</script>
