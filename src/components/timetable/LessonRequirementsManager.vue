<template>
  <div class="space-y-4">
    <div class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="card-title">Lesson Requirements</h2>
        <p class="text-xs text-slate-500">
          What each class must be taught each week, the generator places these.
          <template v-if="requirements.length">{{ requirements.length }} requirement(s), {{ totalPeriods }} periods a week.</template>
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn-secondary" @click="openImport"><i class="fas fa-file-excel"></i>Import from Excel</button>
        <button type="button" class="btn-primary" @click="openForm()"><i class="fas fa-plus"></i>Add Requirement</button>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3">
        <div class="w-full sm:w-64">
          <TtSearchSelect v-model="classFilter" :options="meta?.classes || []" label="full_class_name" noun="classes" placeholder="All classes" clearable />
        </div>
        <input v-model="query" type="search" class="input w-full sm:w-64" placeholder="Search subject or teacher" />
        <p v-if="classFilter" class="text-xs text-slate-500">{{ filtered.length }} subject(s) · {{ filteredPeriods }} periods a week</p>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Periods/week</th><th>Lesson length</th><th class="text-right">Actions</th></tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="7" class="py-8 text-center text-slate-500">Loading…</td></tr>
            <tr v-else-if="!filtered.length">
              <td colspan="7" class="py-10 text-center text-slate-500">
                {{ requirements.length ? 'No requirements match these filters.' : 'No requirements yet, add them one by one or import the Excel template.' }}
              </td>
            </tr>
            <tr v-for="req in filtered" v-else :key="req.id">
              <td class="font-medium text-slate-900">{{ req.full_class_name || req.class_name }}</td>
              <td>{{ req.subject_name }}</td>
              <td>{{ req.teacher_name }} <span class="text-xs text-slate-400">{{ req.teacher_code }}</span></td>
              <td>{{ req.room_code || 'Any' }}</td>
              <td>{{ req.periods_per_week }}</td>
              <td>{{ spanLabel(req.preferred_span || (Number(req.double_lesson_required) ? 2 : 1)) }}</td>
              <td class="text-right">
                <button type="button" class="link-btn mr-3" @click="openForm(req)">Edit</button>
                <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="deleteRequirement(req)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showForm" :title="form.id ? 'Edit Requirement' : 'Add Requirement'" @close="showForm = false">
      <form class="space-y-4" novalidate @submit.prevent="saveRequirement">
        <div v-if="formError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <i class="fas fa-circle-exclamation mr-1"></i>{{ formError }}
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700" for="lr-class">Class &amp; stream <span class="text-red-600">*</span></label>
          <TtSearchSelect id="lr-class" v-model="form.class_id" :options="meta?.classes || []" label="full_class_name" :hint="(c) => c.level" noun="classes" placeholder="Choose a class" :error="errors.class_id" />
          <p v-if="errors.class_id" class="mt-1 text-xs text-red-600">{{ errors.class_id }}</p>
          <p v-else-if="selectedClass" class="mt-1 text-xs text-slate-500">
            Class {{ selectedClass.class_name }} · Stream {{ selectedClass.stream_name || '-' }} · {{ selectedClass.level }}
          </p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700" for="lr-subject">Subject <span class="text-red-600">*</span></label>
          <TtSearchSelect id="lr-subject" v-model="form.subject_id" :options="meta?.subjects || []" label="subject_name" hint="subject_code" noun="subjects" placeholder="Choose a subject" :error="errors.subject_id" />
          <p v-if="errors.subject_id" class="mt-1 text-xs text-red-600">{{ errors.subject_id }}</p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700" for="lr-teacher">Teacher <span class="text-red-600">*</span></label>
          <TtSearchSelect id="lr-teacher" v-model="form.teacher_id" :options="deptTeachers" label="full_name" :hint="(t) => [t.teacher_code, t.subject].filter(Boolean).join(' · ')" noun="teachers" placeholder="Choose a teacher" :error="errors.teacher_id" />
          <p v-if="errors.teacher_id" class="mt-1 text-xs text-red-600">{{ errors.teacher_id }}</p>
          <p v-else-if="deptHint" class="mt-1 text-xs text-slate-500"><i class="fas fa-sitemap mr-1"></i>{{ deptHint }}</p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700" for="lr-room">Room</label>
          <TtSearchSelect id="lr-room" v-model="form.room_id" :options="activeRooms" :label="(r) => `${r.room_code} · ${r.room_name}`" hint="room_type" noun="rooms" placeholder="Any room" clearable />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="lr-periods">Periods per week <span class="text-red-600">*</span></label>
            <input id="lr-periods" v-model.number="form.periods_per_week" type="number" min="1" max="20" class="input" :class="errors.periods_per_week ? 'border-red-400' : ''" />
            <p v-if="errors.periods_per_week" class="mt-1 text-xs text-red-600">{{ errors.periods_per_week }}</p>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="lr-span">
              Lesson length <span v-if="selectedClass" class="font-normal text-slate-400">({{ selectedClass.level }})</span>
            </label>
            <select id="lr-span" v-model.number="form.preferred_span" class="input">
              <option v-for="(o, span) in spanOptions" :key="span" :value="Number(span)">{{ o.label }} ({{ o.minutes }} min)</option>
            </select>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
          <label class="flex items-center gap-2 text-sm"><input v-model="form.double_lesson_allowed" type="checkbox" class="accent-brand-600" />Double lesson allowed</label>
          <label class="flex items-center gap-2 text-sm"><input v-model="form.double_lesson_required" type="checkbox" class="accent-brand-600" />Double lesson required</label>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700" for="lr-notes">Notes</label>
          <textarea id="lr-notes" v-model="form.notes" class="input resize-none" rows="2"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" :disabled="saving" @click="showForm = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            <i v-if="saving" class="fas fa-circle-notch fa-spin"></i>{{ saving ? 'Saving…' : 'Save Requirement' }}
          </button>
        </div>
      </form>
    </Modal>

    <TtRequirementsImport
      v-if="showImportModal"
      :session="activeSession"
      :existing="requirements"
      @close="showImportModal = false"
      @imported="loadRequirements"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { lessonRequirementsAPI, timetableAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';
import Modal from '../hr/Modal.vue';
import TtSearchSelect from './TtSearchSelect.vue';
import TtRequirementsImport from './TtRequirementsImport.vue';

const props = defineProps({
  activeSession: { type: Object, default: null },
});

const { showToast } = useToast();
const spanLabel = (span) => ({ 1: 'Single (40 min)', 2: 'Double (80 min)', 3: 'Triple (120 min)', 4: 'Quadruple (160 min)' }[span] || `${span} periods`);

const meta = ref(null);
const requirements = ref([]);
const loading = ref(false);
const classFilter = ref(null);
const query = ref('');
const showForm = ref(false);
const showImportModal = ref(false);
const saving = ref(false);
const formError = ref('');
const errors = ref({});

const blankForm = () => ({
  id: null, class_id: null, subject_id: null, teacher_id: null, room_id: null,
  periods_per_week: 1, double_lesson_allowed: false, double_lesson_required: false, preferred_span: 1,
  preferred_days: null, preferred_periods: null, avoid_days: null, avoid_periods: null, notes: '',
});
const form = ref(blankForm());

const activeTeachers = computed(() => (meta.value?.teachers || []).filter((t) => t.is_active || t.id === form.value.teacher_id));
/** Only the chosen subject's department (all active teachers if it has no members yet). */
const deptIds = computed(() => (form.value.subject_id ? meta.value?.subject_teachers?.[form.value.subject_id] || [] : []));
const deptTeachers = computed(() => {
  const ids = new Set(deptIds.value);
  return activeTeachers.value.filter((t) => !ids.size || ids.has(t.id) || t.id === form.value.teacher_id);
});
const deptHint = computed(() => {
  const subject = meta.value?.subjects.find((s) => s.id === form.value.subject_id);
  if (!subject) return '';
  return deptIds.value.length
    ? `Showing the ${deptIds.value.length} teacher(s) in the ${subject.subject_name} department.`
    : `No ${subject.subject_name} department set up yet, showing all teachers.`;
});
watch(() => form.value.subject_id, () => {
  if (form.value.teacher_id && deptIds.value.length && !deptIds.value.includes(form.value.teacher_id)) form.value.teacher_id = null;
});
const activeRooms = computed(() => (meta.value?.rooms || []).filter((r) => r.is_active || r.id === form.value.room_id));
const selectedClass = computed(() => (meta.value?.classes || []).find((c) => c.id === form.value.class_id) || null);
const spanOptions = computed(() => meta.value?.span_options[selectedClass.value?.level || 'O-Level'] || {});
watch(spanOptions, (opts) => { if (!opts[form.value.preferred_span]) form.value.preferred_span = 1; });

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return requirements.value.filter((r) => (!classFilter.value || r.class_id === classFilter.value)
    && (!q || `${r.subject_name} ${r.teacher_name} ${r.teacher_code}`.toLowerCase().includes(q)));
});
const totalPeriods = computed(() => requirements.value.reduce((n, r) => n + Number(r.periods_per_week || 0), 0));
const filteredPeriods = computed(() => filtered.value.reduce((n, r) => n + Number(r.periods_per_week || 0), 0));

const loadMeta = async () => {
  try {
    meta.value = (await timetableAPI.meta()).data;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
};

const loadRequirements = async () => {
  if (!props.activeSession?.id) return;
  loading.value = true;
  try {
    const res = await lessonRequirementsAPI.getBySession(props.activeSession.id);
    requirements.value = (res.data || []).map((r) => ({
      ...r,
      class_id: Number(r.class_id), subject_id: Number(r.subject_id), teacher_id: Number(r.teacher_id),
      room_id: r.room_id ? Number(r.room_id) : null,
    }));
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    loading.value = false;
  }
};

const openForm = (req = null) => {
  if (!props.activeSession?.id) {
    showToast('Please select an academic session first', 'error');
    return;
  }
  errors.value = {};
  formError.value = '';
  form.value = req
    ? {
      ...blankForm(), ...req,
      periods_per_week: Number(req.periods_per_week),
      preferred_span: Number(req.preferred_span) || (Number(req.double_lesson_required) ? 2 : 1),
      double_lesson_allowed: !!Number(req.double_lesson_allowed),
      double_lesson_required: !!Number(req.double_lesson_required),
    }
    : { ...blankForm(), class_id: classFilter.value };
  showForm.value = true;
};

const saveRequirement = async () => {
  if (saving.value) return;
  const e = {};
  if (!form.value.class_id) e.class_id = 'Choose a class';
  if (!form.value.subject_id) e.subject_id = 'Choose a subject';
  if (!form.value.teacher_id) e.teacher_id = 'Choose a teacher';
  if (!(form.value.periods_per_week >= 1 && form.value.periods_per_week <= 20)) e.periods_per_week = 'Enter 1 to 20';
  errors.value = e;
  if (Object.keys(e).length) return;

  saving.value = true;
  formError.value = '';
  try {
    const data = { ...form.value, academic_session_id: props.activeSession.id };
    if (form.value.id) {
      await lessonRequirementsAPI.update(data);
      showToast('Requirement updated');
    } else {
      await lessonRequirementsAPI.create(data);
      showToast('Requirement added');
    }
    showForm.value = false;
    loadRequirements();
  } catch (err) {
    formError.value = apiError(err).message; // keep the form open with what was entered
  } finally {
    saving.value = false;
  }
};

const deleteRequirement = async (req) => {
  if (!confirm(`Delete ${req.subject_name} for ${req.full_class_name || req.class_name}?`)) return;
  try {
    await lessonRequirementsAPI.delete(req.id);
    showToast('Requirement deleted');
    loadRequirements();
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
};

const openImport = () => {
  if (!props.activeSession?.id) {
    showToast('Please select an academic session first', 'error');
    return;
  }
  showImportModal.value = true;
};

watch(() => props.activeSession?.id, loadRequirements);
onMounted(() => {
  loadMeta();
  loadRequirements();
});
</script>
