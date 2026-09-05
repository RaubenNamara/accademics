<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Lesson Requirements</h2>
      <div class="flex gap-3">
        <button @click="showImportModal = true" class="btn-secondary">Import CSV</button>
        <button @click="showForm = true" class="btn-primary">+ Add Requirement</button>
      </div>
    </div>
    
    <!-- Requirements List -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
      <table class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Class</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Subject</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Teacher</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Room</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Periods/Week</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Lesson Length</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="req in requirements" :key="req.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ req.class_name }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ req.subject_name }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ req.teacher_name }} ({{ req.teacher_code }})</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ req.room_code || 'Any' }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ req.periods_per_week }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">
              {{ spanLabel(req.preferred_span || (req.double_lesson_required || req.double_lesson_allowed ? 2 : 1)) }}
            </td>
            <td class="px-6 py-4 text-sm">
              <button @click="editRequirement(req)" class="text-slate-600 hover:text-slate-800 mr-3">Edit</button>
              <button @click="deleteRequirement(req.id)" class="text-rose-600 hover:text-rose-800">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Requirement Form Modal -->
    <Modal v-if="showForm" @close="showForm = false" :title="form.id ? 'Edit Requirement' : 'Add Requirement'">
      <form @submit.prevent="saveRequirement" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Class</label>
          <select v-model="form.class_id" class="input w-full" required>
            <option value="">Select Class</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.class_name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Stream</label>
          <input v-model="form.stream" type="text" class="input w-full" placeholder="Optional">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Subject</label>
          <select v-model="form.subject_id" class="input w-full" required>
            <option value="">Select Subject</option>
            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.subject_name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Teacher</label>
          <select v-model="form.teacher_id" class="input w-full" required>
            <option value="">Select Teacher</option>
            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Room</label>
          <select v-model="form.room_id" class="input w-full">
            <option value="">Any Room</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.room_code }}</option>
          </select>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Periods Per Week</label>
            <input v-model.number="form.periods_per_week" type="number" class="input w-full" min="1" value="1" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">
              Lesson Length <span v-if="selectedLevel" class="text-slate-400 font-normal">({{ selectedLevel }})</span>
            </label>
            <select v-model.number="form.preferred_span" class="input w-full">
              <option v-for="o in spanOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.double_lesson_allowed" type="checkbox" class="rounded border-slate-300">
            Double Lesson Allowed
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.double_lesson_required" type="checkbox" class="rounded border-slate-300">
            Double Lesson Required
          </label>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Notes</label>
          <textarea v-model="form.notes" class="input w-full" rows="2"></textarea>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary">Save Requirement</button>
        </div>
      </form>
    </Modal>
    
    <!-- Import Modal -->
    <Modal v-if="showImportModal" @close="showImportModal = false" title="Import CSV">
      <div class="space-y-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="font-medium text-blue-800 mb-2">CSV Format</h4>
          <p class="text-sm text-blue-700">class,stream,subject,teacher,room,periods_per_week,double_lesson_allowed,double_lesson_required</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">CSV Data</label>
          <textarea v-model="csvData" class="input w-full" rows="10" placeholder="Paste CSV data here..."></textarea>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="showImportModal = false" class="btn-secondary">Cancel</button>
          <button @click="importCSV" class="btn-primary">Import</button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { lessonRequirementsAPI, classesAPI, teachersAPI, subjectsNewAPI, roomsAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import { getClassLevel, getSpanOptions } from '../../utils/lessonSpans.js';
import Modal from '../hr/Modal.vue';

const props = defineProps({
  activeSession: {
    type: Object,
    default: null
  }
});

const { showToast } = useToast();

const spanLabel = (span) => ({ 1: 'Single (40 min)', 2: 'Double (80 min)', 3: 'Triple (120 min)', 4: 'Quadruple (160 min)' }[span] || `${span} periods`);

const requirements = ref([]);
const classes = ref([]);
const subjects = ref([]);
const teachers = ref([]);
const rooms = ref([]);
const showForm = ref(false);
const showImportModal = ref(false);
const csvData = ref('');

const form = ref({
  id: null,
  academic_session_id: null,
  class_id: null,
  stream: '',
  subject_id: null,
  teacher_id: null,
  room_id: null,
  periods_per_week: 1,
  double_lesson_allowed: false,
  double_lesson_required: false,
  preferred_span: 1,
  notes: ''
});

const selectedLevel = computed(() => {
  const cls = classes.value.find(c => c.id === form.value.class_id);
  return cls ? getClassLevel(cls.class_name) : '';
});

const spanOptions = computed(() => getSpanOptions(selectedLevel.value || 'O-Level'));

watch(selectedLevel, () => {
  if (!spanOptions.value.some(o => o.value === form.value.preferred_span)) {
    form.value.preferred_span = 1;
  }
});

const loadRequirements = async () => {
  try {
    const sessionId = props.activeSession?.id;
    if (!sessionId) {
      showToast('Please select an active academic session first', 'warning');
      return;
    }
    
    const res = await lessonRequirementsAPI.getBySession(sessionId);
    if (res.success) {
      requirements.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading requirements:', error);
    showToast('Failed to load requirements', 'error');
  }
};

const loadDropdownData = async () => {
  try {
    const [classesRes, subjectsRes, teachersRes, roomsRes] = await Promise.allSettled([
      classesAPI.getAll(),
      subjectsNewAPI.getAll(),
      teachersAPI.getAll(),
      roomsAPI.getAll()
    ]);

    if (classesRes.status === 'fulfilled' && classesRes.value.success) {
      classes.value = classesRes.value.data || [];
    }
    if (subjectsRes.status === 'fulfilled' && subjectsRes.value.success) {
      subjects.value = subjectsRes.value.data || [];
    }
    if (teachersRes.status === 'fulfilled' && teachersRes.value.success) {
      teachers.value = teachersRes.value.data.teachers || teachersRes.value.data || [];
    }
    if (roomsRes.status === 'fulfilled' && roomsRes.value.success) {
      rooms.value = roomsRes.value.data || [];
    }
  } catch (error) {
    console.error('Error loading dropdown data:', error);
  }
};

const saveRequirement = async () => {
  try {
    const sessionId = props.activeSession?.id;
    if (!sessionId) {
      showToast('Please select an active academic session first', 'warning');
      return;
    }
    
    const data = { ...form.value, academic_session_id: sessionId };
    
    if (form.value.id) {
      await lessonRequirementsAPI.update(data);
      showToast('Requirement updated');
    } else {
      await lessonRequirementsAPI.create(data);
      showToast('Requirement created');
    }
    showForm.value = false;
    loadRequirements();
  } catch (error) {
    console.error('Error saving requirement:', error);
    showToast('Failed to save requirement', 'error');
  }
};

const editRequirement = (req) => {
  form.value = { ...req };
  showForm.value = true;
};

const deleteRequirement = async (id) => {
  if (!confirm('Are you sure you want to delete this requirement?')) return;
  
  try {
    await lessonRequirementsAPI.delete(id);
    showToast('Requirement deleted');
    loadRequirements();
  } catch (error) {
    console.error('Error deleting requirement:', error);
    showToast('Failed to delete requirement', 'error');
  }
};

const importCSV = async () => {
  try {
    const sessionId = props.activeSession?.id;
    if (!sessionId) {
      showToast('Please select an active academic session first', 'warning');
      return;
    }
    
    const res = await lessonRequirementsAPI.importCSV({
      academic_session_id: sessionId,
      csv_data: csvData.value
    });
    
    if (res.success) {
      showToast(`Imported ${res.data.created} requirements`);
      if (res.data.errors.length > 0) {
        showToast(`${res.data.errors.length} errors occurred`, 'warning');
      }
      showImportModal.value = false;
      csvData.value = '';
      loadRequirements();
    }
  } catch (error) {
    console.error('Error importing CSV:', error);
    showToast('Failed to import CSV', 'error');
  }
};

watch(() => props.activeSession, () => {
  loadRequirements();
});

onMounted(() => {
  loadRequirements();
  loadDropdownData();
});
</script>
