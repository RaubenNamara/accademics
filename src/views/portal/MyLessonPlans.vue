<template>
  <div class="page">
    <ToastBanner />

    <!-- ============ Editor: the school's lesson plan, top to bottom ============ -->
    <template v-if="form">
      <div class="page-hero">
        <button type="button" class="mb-1 inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline" @click="closeForm">
          <ArrowLeft class="h-4 w-4" />All lesson plans
        </button>
        <div class="flex flex-wrap items-end justify-between gap-3">
          <h1 class="page-hero-title">{{ form.id ? 'Edit lesson plan' : 'New lesson plan' }}</h1>
          <span v-if="form.status !== 'draft'" class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="PLAN_STATUS[form.status].style">{{ PLAN_STATUS[form.status].label }}</span>
        </div>
      </div>

      <div v-if="form.review_comment" class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <MessageSquareWarning class="mt-0.5 h-5 w-5 flex-shrink-0" />
        <div>
          <p class="font-semibold">Returned with a comment{{ form.reviewed_by ? ` from ${form.reviewed_by}` : '' }}</p>
          <p class="mt-0.5 whitespace-pre-line">{{ form.review_comment }}</p>
        </div>
      </div>

      <form class="card mx-auto w-full max-w-5xl p-5 pb-6 sm:p-8" @submit.prevent="save(false)">
        <p class="text-sm font-bold uppercase tracking-wide text-gray-900">{{ schoolName }}</p>
        <p class="text-sm font-bold uppercase tracking-wide text-gray-900">Lesson Plan</p>

        <!-- Header grid, as on the template -->
        <div class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
          <label class="block">
            <span class="field-label">Week</span>
            <select v-model="form.week_number" class="input">
              <option value="">From the date</option>
              <option v-for="w in weekOptions" :key="w" :value="w">Week {{ w }}</option>
            </select>
          </label>
          <label class="block">
            <span class="field-label">Date <span class="text-red-600">*</span></span>
            <input v-model="form.lesson_date" type="date" class="input">
            <span v-if="errors.lesson_date" class="field-error">{{ errors.lesson_date }}</span>
          </label>

          <label class="block">
            <span class="field-label">Subject &amp; class <span class="text-red-600">*</span></span>
            <select v-model="form.key" class="input" required>
              <option :value="null" disabled>Choose</option>
              <option v-for="a in assignments" :key="keyOf(a)" :value="keyOf(a)">{{ a.subject_name }} · {{ a.full_class_name }}</option>
            </select>
            <span v-if="errors.class_id || errors.subject_id" class="field-error">{{ errors.class_id || errors.subject_id }}</span>
          </label>
          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="field-label">Time</span>
              <input v-model="form.start_time" type="time" class="input" list="period-starts">
              <datalist id="period-starts"><option v-for="p in periodStarts" :key="p.value" :value="p.value">{{ p.label }}</option></datalist>
            </label>
            <label class="block">
              <span class="field-label">Duration</span>
              <select :value="Number(form.lesson_span)" class="input" @change="setSpan(Number($event.target.value))">
                <option v-for="s in spans" :key="s.value" :value="s.value">{{ s.label }} ({{ s.minutes }} min)</option>
              </select>
            </label>
            <p v-if="form.start_time" class="col-span-2 -mt-2 text-xs text-gray-500">{{ timeRange(form.start_time, minutes) }}</p>
          </div>

          <div>
            <span class="field-label">Teacher</span>
            <p class="input bg-gray-50 text-gray-700">{{ teacherName }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <label class="block">
              <span class="field-label">Number of students</span>
              <input v-model="form.learners" type="number" min="0" class="input" placeholder="From the register">
            </label>
            <div>
              <span class="field-label">Term</span>
              <p class="input bg-gray-50 text-gray-700">Term {{ planTerm }}</p>
            </div>
          </div>

          <!-- Theme and topic come from Curriculum Setup when it has topics for this class and term -->
          <template v-if="curriculum.length">
            <label class="block">
              <span class="field-label">Theme</span>
              <select v-model="cuTheme" class="input">
                <option value="">Choose a theme</option>
                <option v-for="th in cuThemes" :key="th" :value="th">{{ th }}</option>
              </select>
            </label>
            <label class="block">
              <span class="field-label">Topic <span class="text-red-600">*</span></span>
              <select :value="form.curriculum_topic_id || ''" class="input" :disabled="!cuTheme" @change="applyTopic(Number($event.target.value))">
                <option value="">{{ cuTheme ? 'Choose a topic' : 'Choose a theme first' }}</option>
                <option v-for="t in cuTopics" :key="t.id" :value="t.id">{{ t.topic }}</option>
              </select>
              <span v-if="errors.topic" class="field-error">{{ errors.topic }}</span>
            </label>
          </template>
          <template v-else>
            <label class="block">
              <span class="field-label">Theme</span>
              <input v-model="form.theme" maxlength="255" class="input" :placeholder="form.key && !cuLoading ? 'No curriculum topics for this class yet' : 'e.g. Computer Systems'">
            </label>
            <label class="block">
              <span class="field-label">Topic <span class="text-red-600">*</span></span>
              <input v-model="form.topic" maxlength="255" class="input" placeholder="e.g. Topic 4: Computer Software" required>
              <span v-if="errors.topic" class="field-error">{{ errors.topic }}</span>
            </label>
          </template>
          <label class="block sm:col-span-2">
            <span class="field-label">Sub-Topic</span>
            <input v-model="form.subtopic" maxlength="255" class="input" placeholder="e.g. 4.1 Software Selection & 4.2 OS Installation">
          </label>
        </div>

        <!-- Sections in the template's order -->
        <div class="mt-6 space-y-5">
          <label class="block">
            <span class="section-label">Competency <span class="text-red-600">*</span></span>
            <textarea v-model="form.competences" rows="2" class="input" placeholder="The learner …"></textarea>
            <span v-if="errors.competences" class="field-error">{{ errors.competences }}</span>
          </label>
          <label class="block">
            <span class="section-label">Generic skill(s)</span>
            <textarea v-model="form.generic_skills" rows="3" class="input" placeholder="One per line, e.g. Critical thinking & problem-solving: Evaluating software options …"></textarea>
          </label>
          <label class="block">
            <span class="section-label">Value(s)</span>
            <textarea v-model="form.values_text" rows="2" class="input" placeholder="e.g. Hard work for self-reliance; Integrity"></textarea>
          </label>
          <label class="block">
            <span class="section-label">Cross-cutting issue(s)</span>
            <textarea v-model="form.cross_cutting" rows="2" class="input" placeholder="e.g. Life skills (self-management)"></textarea>
          </label>

          <div>
            <span class="section-label">Key Learning Outcome(s) <span class="text-red-600">*</span></span>
            <!-- From the curriculum topic: tick the outcomes this lesson covers -->
            <div v-if="pickedTopic" class="space-y-1 rounded-xl border border-gray-200 p-3">
              <label v-for="(o, i) in pickedTopic.outcomes" :key="i" class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 text-sm text-gray-800 hover:bg-gray-50">
                <input type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 accent-brand-600" :checked="pickedOutcomes.includes(i)" @change="toggleOutcome(i)">
                <span>{{ o }}</span>
              </label>
              <p v-if="!pickedTopic.outcomes.length" class="px-2 text-sm text-gray-500">This topic has no learning outcomes in Curriculum Setup.</p>
            </div>
            <textarea v-else v-model="form.objectives" rows="3" class="input" placeholder="The learner should be able to …"></textarea>
            <span v-if="errors.objectives" class="field-error">{{ errors.objectives }}</span>
          </div>

          <hr class="border-gray-200">

          <label class="block">
            <span class="section-label">Pre-Requisite Knowledge</span>
            <textarea v-model="form.prerequisite" rows="2" class="input" placeholder="What learners already know from earlier lessons"></textarea>
          </label>
          <label class="block">
            <span class="section-label">Learning materials</span>
            <textarea v-model="form.materials" rows="2" class="input" placeholder="Computers, charts, textbooks, projector …"></textarea>
          </label>
          <label class="block">
            <span class="section-label">References</span>
            <textarea v-model="form.references_text" rows="2" class="input" placeholder="Syllabus (NCDC), scheme of work, textbooks"></textarea>
          </label>

          <hr class="border-gray-200">

          <!-- Learning procedure -->
          <div>
            <div class="flex flex-wrap items-end justify-between gap-2">
              <span class="section-label mb-0 text-base">Learning Procedure</span>
              <span class="text-sm font-medium" :class="minutesStyle">{{ procedureTotal }} of {{ minutes }} minutes</span>
            </div>
            <div class="mt-2 overflow-x-auto">
              <table class="proc w-full min-w-[640px]">
                <thead>
                  <tr>
                    <th class="w-24">Time per phase</th>
                    <th>Teacher Activity: Observation, Conversation, Product</th>
                    <th>Learners' Activity: Discovery, Explanatory, Analysis and Application</th>
                    <th class="w-10"><span class="sr-only">Remove</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in form.procedure" :key="i">
                    <td>
                      <div class="flex items-center gap-1">
                        <input v-model.number="row.minutes" type="number" min="1" max="400" class="input px-2 py-1.5 text-center" :aria-label="`Minutes for phase ${i + 1}`">
                        <span class="text-xs text-gray-500">min</span>
                      </div>
                    </td>
                    <td><textarea v-model="row.teacher" rows="4" class="input text-sm" placeholder="e.g. Group Presentations: Facilitates …"></textarea></td>
                    <td><textarea v-model="row.learners" rows="4" class="input text-sm" placeholder="e.g. Communication & Analysis: Groups present …"></textarea></td>
                    <td class="text-center">
                      <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove phase" @click="form.procedure.splice(i, 1)"><Trash2 class="h-4 w-4" /></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn-secondary mt-3 px-3 py-1.5 text-sm" :disabled="form.procedure.length >= 15" @click="addPhase"><Plus class="h-4 w-4" />Add phase</button>
            <p v-if="errors.procedure" class="field-error">{{ errors.procedure }}</p>
          </div>

          <hr class="border-gray-200">

          <label class="block">
            <span class="section-label">Assessment Strategies</span>
            <textarea v-model="form.assessment" rows="3" class="input" placeholder="How you will check learning, e.g. observing group presentations, oral questions, written exercise, assignment"></textarea>
          </label>

          <hr class="border-gray-200">

          <label class="block">
            <span class="section-label">Teacher Self-Assessment</span>
            <textarea v-model="form.reflection" rows="3" class="input" placeholder="To be completed after the lesson"></textarea>
          </label>
        </div>

        <div class="mt-8 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-5">
          <button type="button" class="btn-secondary" @click="closeForm">Cancel</button>
          <button type="submit" class="btn-secondary" :disabled="saving">Save draft</button>
          <button type="button" class="btn-primary" :disabled="saving" @click="save(true)"><Send class="h-4 w-4" />Submit for review</button>
        </div>
      </form>
    </template>

    <!-- ============ List ============ -->
    <template v-else>
      <div class="page-hero">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="page-hero-title">Lesson Plans</h1>
          </div>
          <button type="button" class="btn-hero-primary" :disabled="!assignments.length" @click="openForm()"><Plus class="h-4 w-4" />New lesson plan</button>
        </div>
      </div>

      <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
      <div v-else-if="!loading && !assignments.length" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        No classes are assigned to you this term, so new plans cannot be written yet. Ask the academic office.
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="t in tabs"
          :key="t.value"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
          :class="statusFilter === t.value ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
          @click="statusFilter = t.value"
        >
          {{ t.label }}
          <span class="rounded-full px-1.5 text-xs" :class="statusFilter === t.value ? 'bg-white/25' : 'bg-gray-100 text-gray-600'">{{ count(t.value) }}</span>
        </button>
        <div class="relative ml-auto w-full sm:w-64">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input v-model="q" type="search" class="input py-2 pl-9" placeholder="Search topic, class, subject">
        </div>
      </div>

      <div>
        <div class="card overflow-hidden">
          <div v-if="loading" class="px-5 py-10 text-center text-sm text-gray-500">Loading…</div>
          <div v-else-if="!shown.length" class="px-5 py-14 text-center">
            <NotebookPen class="mx-auto h-9 w-9 text-gray-300" />
            <p class="mt-2 text-sm font-medium text-gray-700">{{ plans.length ? 'No plans match' : 'No lesson plans yet' }}</p>
            <button v-if="!plans.length && assignments.length" type="button" class="btn-primary mt-4" @click="openForm()"><Plus class="h-4 w-4" />Write your first plan</button>
          </div>
          <ul v-else class="divide-y divide-gray-100">
            <li v-for="p in shown" :key="p.id">
              <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-brand-50/50 sm:px-5" @click="openPlan(p.id)">
                <span class="flex w-12 flex-shrink-0 flex-col items-center rounded-xl border border-gray-200 bg-white py-1 text-center">
                  <span class="text-[10px] font-semibold uppercase text-brand-700">{{ dateBits(p.lesson_date).month }}</span>
                  <span class="text-lg font-bold leading-tight text-gray-900">{{ dateBits(p.lesson_date).day }}</span>
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-medium text-gray-900">{{ p.topic }}</span>
                  <span class="block truncate text-xs text-gray-500">{{ p.full_class_name }} · {{ p.subject_name }}</span>
                  <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="PLAN_STATUS[p.status].style">{{ PLAN_STATUS[p.status].label }}</span>
                </span>
              </button>
            </li>
          </ul>
        </div>

      </div>
    </template>

    <!-- ============ One plan, in a window on top of the list ============ -->
    <Teleport to="body">
    <div v-if="!form && current" class="fixed inset-0 z-[60] flex items-end justify-center bg-gray-900/60 backdrop-blur-sm sm:items-center sm:p-4" @click.self="closePlan">
      <div class="flex h-[100dvh] w-full max-w-[64rem] flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[94vh] sm:rounded-2xl" role="dialog" aria-modal="true" :aria-label="current.topic">
        <header class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-gray-100 px-5 py-4">
          <div class="min-w-0 flex-1">
            <h2 class="truncate text-lg font-semibold text-gray-900">{{ current.topic }}</h2>
            <p class="truncate text-sm text-gray-500">{{ current.full_class_name }} · {{ current.subject_name }}</p>
          </div>
          <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="PLAN_STATUS[current.status].style">{{ PLAN_STATUS[current.status].label }}</span>
          <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="closePlan"><X class="h-5 w-5" /></button>
          <div class="flex w-full flex-wrap gap-2">
            <button type="button" class="btn-secondary px-3 py-1.5 text-sm" @click="downloadPdf(current)"><Download class="h-4 w-4" />PDF</button>
            <button type="button" class="btn-secondary px-3 py-1.5 text-sm" @click="copyPlan(current)"><Copy class="h-4 w-4" />Use as template</button>
            <button type="button" class="btn-secondary px-3 py-1.5 text-sm text-red-600" @click="remove(current)"><Trash2 class="h-4 w-4" />Delete</button>
            <button v-if="editable(current)" type="button" class="btn-primary ml-auto px-3 py-1.5 text-sm" @click="openForm(current)"><Pencil class="h-4 w-4" />Edit</button>
          </div>
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto bg-gray-100 p-2 sm:p-5">
          <div v-if="current.review_comment" class="mx-auto mb-3 max-w-[210mm] rounded-xl border px-4 py-3 text-sm" :class="current.status === 'returned' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-gray-200 bg-white text-gray-700'">
            <p class="text-xs font-semibold uppercase tracking-wide">Reviewer's comment{{ current.reviewed_by ? ` · ${current.reviewed_by}` : '' }}</p>
            <p class="mt-1 whitespace-pre-line">{{ current.review_comment }}</p>
          </div>
          <!-- As it prints -->
          <article class="a4-sheet bg-white text-gray-900 ring-1 ring-gray-200">
            <p class="text-sm font-bold uppercase tracking-wide">{{ schoolName }}</p>
            <p class="mb-4 text-sm font-bold uppercase tracking-wide">Lesson Plan</p>
            <LessonPlanDetail :plan="current" />
          </article>
        </div>
      </div>
    </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Copy, Download, FileText, MessageSquareWarning, NotebookPen, Pencil, Plus, Search, Send, Trash2, X } from 'lucide-vue-next';
import ToastBanner from '../../components/hr/ToastBanner.vue';
import LessonPlanDetail from '../../components/lessonPlans/LessonPlanDetail.vue';
import { PERIOD_MINUTES, PLAN_STATUS, defaultProcedure, spansFor, timeRange } from '../../components/lessonPlans/planMeta.js';
import { curriculumAPI, lessonPlansAPI, schoolSettingsAPI, teacherPortalAPI } from '../../services/api.js';
import authStore from '../../services/authStore.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';
import { buildLessonPlanPdf, lessonPlanFileName } from '../../utils/lessonPlanPdf.js';

const route = useRoute();
const router = useRouter();
const { showToast } = useToast();
const plans = ref([]);
const assignments = ref([]);
const timetable = ref({ entries: [], periods: [] });
const sessionId = ref(null);
const sessionTerm = ref(1);
const teacherName = ref(authStore.user?.full_name || '');
const settings = ref(null);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const errors = ref({});
const form = ref(null);
// The open plan comes from the address (?plan=<id>), so Back returns to the list.
const current = computed(() => plans.value.find((p) => String(p.id) === String(route.query.plan)) || null);
const openPlan = (id, replace = false) => router[replace ? 'replace' : 'push']({ query: { plan: String(id) } });
const closePlan = () => {
  if (String(window.history.state?.back || '').startsWith(route.path)) router.back();
  else router.replace({ query: {} });
};
const onKey = (e) => { if (e.key === 'Escape' && current.value && !form.value) closePlan(); };
window.addEventListener('keydown', onKey);
watch(() => !!current.value && !form.value, (open) => { document.body.style.overflow = open ? 'hidden' : ''; });
onBeforeUnmount(() => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; });
const statusFilter = ref('');
const q = ref('');

const tabs = [
  { value: '', label: 'All' },
  { value: 'draft', label: 'Drafts' },
  { value: 'submitted', label: 'Awaiting review' },
  { value: 'returned', label: 'Returned' },
  { value: 'approved', label: 'Approved' },
];
const weekOptions = Array.from({ length: 16 }, (_, i) => i + 1);

const keyOf = (a) => `${a.class_id}-${a.subject_id}`;
const editable = (p) => ['draft', 'returned'].includes(p.status);
const count = (status) => (status ? plans.value.filter((p) => p.status === status).length : plans.value.length);
const shown = computed(() => {
  const term = q.value.trim().toLowerCase();
  return plans.value.filter((p) => (!statusFilter.value || p.status === statusFilter.value)
    && (!term || `${p.topic} ${p.subtopic || ''} ${p.full_class_name} ${p.subject_name}`.toLowerCase().includes(term)));
});
const schoolName = computed(() => settings.value?.school_name || "St. Mark's College Namagoma");
const dateBits = (d) => {
  if (!d) return { month: '-', day: '' };
  const dt = new Date(`${String(d).slice(0, 10)}T00:00:00`);
  return { month: dt.toLocaleDateString('en-GB', { month: 'short' }), day: dt.getDate() };
};

const assignment = computed(() => assignments.value.find((a) => keyOf(a) === form.value?.key) || null);
const level = computed(() => assignment.value?.level || 'O-Level');
const spans = computed(() => spansFor(level.value));
const minutes = computed(() => Number(form.value?.lesson_span || 1) * PERIOD_MINUTES);
const planTerm = computed(() => Number(form.value?.term) || sessionTerm.value);
const procedureTotal = computed(() => (form.value?.procedure || []).reduce((t, r) => t + Number(r.minutes || 0), 0));
const minutesStyle = computed(() => (procedureTotal.value === minutes.value ? 'text-brand-700' : procedureTotal.value > minutes.value ? 'text-red-600' : 'text-amber-700'));
const periodStarts = computed(() => (timetable.value.periods || [])
  .filter((p) => p.period_type === 'lesson')
  .map((p) => ({ value: String(p.start_time).slice(0, 5), label: p.label })));

/* ---- Curriculum Setup: theme → topic fill the competency, outcomes and week ---- */
const curriculum = ref([]);
const cuLoading = ref(false);
const cuTheme = ref('');
const pickedOutcomes = ref([]);
const cuThemes = computed(() => [...new Set(curriculum.value.map((t) => t.theme))]);
const cuTopics = computed(() => curriculum.value.filter((t) => t.theme === cuTheme.value));
const pickedTopic = computed(() => curriculum.value.find((t) => t.id === Number(form.value?.curriculum_topic_id)) || null);

async function loadCurriculum() {
  const f = form.value;
  curriculum.value = [];
  if (!f?.key) return;
  const [classId, subjectId] = f.key.split('-').map(Number);
  cuLoading.value = true;
  try {
    curriculum.value = (await curriculumAPI.forClass(classId, subjectId, planTerm.value)).data.topics;
    const t = pickedTopic.value;
    cuTheme.value = t ? t.theme : (cuThemes.value.includes(f.theme) ? f.theme : '');
    // Tick the outcomes the plan already lists.
    pickedOutcomes.value = t ? t.outcomes.map((o, i) => (String(f.objectives || '').includes(o) ? i : -1)).filter((i) => i >= 0) : [];
  } catch {
    curriculum.value = [];
  } finally {
    cuLoading.value = false;
  }
}

const outcomesText = (t, picked) => (picked.length
  ? `The learner should be able to:\n${picked.map((i) => `- ${t.outcomes[i]}`).join('\n')}`
  : '');

function applyTopic(id) {
  const t = curriculum.value.find((x) => x.id === id);
  const f = form.value;
  if (!t) {
    f.curriculum_topic_id = null;
    return;
  }
  f.curriculum_topic_id = t.id;
  f.theme = t.theme;
  f.topic = t.topic;
  f.competences = t.competence || '';
  if (t.week_from) f.week_number = t.week_from;
  pickedOutcomes.value = t.outcomes.map((_, i) => i);
  f.objectives = outcomesText(t, pickedOutcomes.value);
}
function toggleOutcome(i) {
  const list = pickedOutcomes.value.includes(i) ? pickedOutcomes.value.filter((x) => x !== i) : [...pickedOutcomes.value, i].sort((a, b) => a - b);
  pickedOutcomes.value = list;
  form.value.objectives = outcomesText(pickedTopic.value, list);
}
// A new theme clears a topic from another theme.
watch(cuTheme, (th) => {
  if (form.value && pickedTopic.value && pickedTopic.value.theme !== th) {
    form.value.curriculum_topic_id = null;
    pickedOutcomes.value = [];
  }
  if (form.value && th) form.value.theme = th;
});

/* ---- Form ---- */
const blank = () => ({
  id: null, key: null, lesson_date: '', week_number: '', lesson_span: 1, start_time: '', learners: '', theme: '', topic: '', subtopic: '',
  competences: '', generic_skills: '', values_text: '', cross_cutting: '', objectives: '', prerequisite: '', materials: '', references_text: '', assessment: '',
  status: 'draft', review_comment: '', reflection: '', curriculum_topic_id: null, procedure: defaultProcedure(PERIOD_MINUTES),
});

function openForm(p = null) {
  errors.value = {};
  if (!p) {
    form.value = blank();
  } else {
    const copy = Object.fromEntries(Object.entries(p).map(([k, v]) => [k, v ?? '']));
    form.value = {
      ...blank(),
      ...copy,
      key: keyOf(p),
      curriculum_topic_id: p.curriculum_topic_id ? Number(p.curriculum_topic_id) : null,
      lesson_date: p.lesson_date ? String(p.lesson_date).slice(0, 10) : '',
      start_time: p.start_time ? String(p.start_time).slice(0, 5) : '',
      lesson_span: Number(p.lesson_span) || Math.max(1, Math.round(Number(p.duration_minutes || 40) / PERIOD_MINUTES)),
      procedure: (p.procedure || []).map((r) => ({ ...r })),
    };
    if (!form.value.procedure.length) form.value.procedure = defaultProcedure(minutes.value);
  }
  nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
}
function copyPlan(p) {
  openForm(p);
  Object.assign(form.value, { id: null, status: 'draft', review_comment: '', lesson_date: '', week_number: '', reflection: '' });
}
const closeForm = () => { form.value = null; errors.value = {}; };

/** Changing the duration rescales the main phase so the procedure still fills the lesson. */
function setSpan(n) {
  const before = minutes.value;
  form.value.lesson_span = n;
  const diff = minutes.value - before;
  const rows = form.value.procedure;
  if (rows.length && procedureTotal.value === before) {
    const main = rows.reduce((best, r, i) => (Number(r.minutes) > Number(rows[best].minutes) ? i : best), 0);
    rows[main].minutes = Math.max(5, Number(rows[main].minutes) + diff);
  }
}
const addPhase = () => form.value.procedure.push({ minutes: Math.max(5, minutes.value - procedureTotal.value), teacher: '', learners: '' });

// A lesson on the timetable for this class/subject on the chosen day fills in the time and duration.
watch(() => [form.value?.key, form.value?.lesson_date], () => {
  const f = form.value;
  if (!f || !f.key || !f.lesson_date || f.start_time) return;
  const day = new Date(`${f.lesson_date}T00:00:00`).toLocaleDateString('en-US', { weekday: 'long' });
  const [classId, subjectId] = f.key.split('-').map(Number);
  const lesson = (timetable.value.entries || []).find((e) => e.day_of_week === day && Number(e.class_id) === classId && Number(e.subject_id) === subjectId);
  if (lesson) {
    f.start_time = String(lesson.start_time).slice(0, 5);
    const span = Math.min(spans.value.length, Math.max(1, Number(lesson.spans_periods) || 1));
    if (span !== Number(f.lesson_span)) setSpan(span);
  }
});
watch(() => form.value?.key, (k, old) => {
  if (k === old) return;
  if (old && form.value) form.value.curriculum_topic_id = null; // another class/subject: the old topic no longer applies
  cuTheme.value = '';
  pickedOutcomes.value = [];
  loadCurriculum();
});
watch(level, () => {
  if (form.value && Number(form.value.lesson_span) > spans.value.length) setSpan(spans.value.length);
});

async function load() {
  loading.value = true;
  try {
    plans.value = (await lessonPlansAPI.list()).data;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
}

async function save(submit) {
  const [classId, subjectId] = String(form.value.key || '').split('-').map(Number);
  saving.value = true;
  errors.value = {};
  try {
    const payload = { ...form.value, class_id: classId || null, subject_id: subjectId || null, submit, academic_session_id: sessionId.value };
    delete payload.key;
    const res = await lessonPlansAPI.save(payload);
    showToast(res.message);
    form.value = null;
    await load();
    openPlan(res.data.id, !!route.query.plan);
  } catch (e) {
    const err = apiError(e);
    errors.value = err.errors;
    showToast(err.message, 'error');
  } finally {
    saving.value = false;
  }
}

async function remove(p) {
  const note = { submitted: ' It has been submitted and is waiting for review; it will be withdrawn.', approved: ' It has already been approved.' }[p.status] || '';
  if (!confirm(`Delete the plan “${p.topic}”?${note} This cannot be undone.`)) return;
  try {
    showToast((await lessonPlansAPI.remove(p.id)).message);
    router.replace({ query: {} });
    await load();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  }
}

async function downloadPdf(p) {
  try {
    settings.value ??= (await schoolSettingsAPI.get()).data;
    buildLessonPlanPdf(p, settings.value).save(lessonPlanFileName(p));
  } catch (e) {
    showToast(`Could not build the PDF: ${apiError(e).message}`, 'error');
  }
}

onMounted(async () => {
  try {
    const [me, tt, st] = await Promise.all([
      teacherPortalAPI.view('me'),
      teacherPortalAPI.view('timetable').catch(() => null),
      schoolSettingsAPI.get().catch(() => null),
    ]);
    assignments.value = me.data.assignments;
    sessionId.value = me.data.session.id;
    sessionTerm.value = Number(me.data.session.term) || 1;
    teacherName.value = me.data.teacher?.full_name || teacherName.value;
    if (tt) timetable.value = tt.data;
    if (st) settings.value = st.data;
  } catch (e) {
    error.value = apiError(e).message;
  }
  await load();
  if (route.query.new) {
    openForm();
    const wanted = `${route.query.class_id}-${route.query.subject_id}`;
    if (assignments.value.some((a) => keyOf(a) === wanted)) form.value.key = wanted;
  }
});
</script>

<style scoped>
.field-label { @apply mb-1 block text-sm font-semibold text-gray-800; }
.section-label { @apply mb-1.5 block text-sm font-bold text-gray-900; }
.field-error { @apply mt-1 block text-xs text-red-600; }
.proc { border-collapse: collapse; }
.proc th, .proc td { border: 1px solid #d1d5db; padding: 0.5rem; vertical-align: top; text-align: left; }
.proc th { background: #f9fafb; font-size: 0.8rem; font-weight: 700; color: #111827; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .field-label {
  color: var(--d-text);
}
html.dark .section-label {
  color: var(--d-text);
}
html.dark .field-error {
  color: #fca5a5;
}
html.dark .proc th,
html.dark .proc td {
  border-color: var(--d-line);
}
html.dark .proc th {
  background-color: var(--d-surface);
  color: var(--d-text);
}
</style>
