<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
      <div class="p-6 border-b flex justify-between items-center">
        <div>
          <h2 class="text-lg font-bold text-slate-900">{{ isEditing ? 'Edit Lesson' : 'Add Lesson' }}</h2>
          <p class="text-sm text-slate-500">{{ className }} · {{ level }}</p>
        </div>
        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">&#10005;</button>
      </div>

      <form @submit.prevent="save" class="p-6 space-y-4">
        <div v-if="errorMessage" class="p-3 rounded-lg bg-red-50 text-red-700 text-sm">
          {{ errorMessage }}
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Day</label>
            <select v-model="form.day_of_week" class="input w-full">
              <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Start Period</label>
            <select v-model.number="form.period_number" class="input w-full">
              <option v-for="p in periods" :key="p" :value="p">P{{ p }}</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Subject</label>
          <select v-model.number="form.subject_id" class="input w-full" required>
            <option value="">Select Subject</option>
            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.subject_name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Teacher</label>
          <select v-model.number="form.teacher_id" class="input w-full" required>
            <option value="">Select Teacher</option>
            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Room (optional)</label>
          <select v-model.number="form.room_id" class="input w-full">
            <option value="">No room</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.room_code }} - {{ r.room_name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Lesson Length</label>
          <select v-model.number="form.spans_periods" class="input w-full">
            <option v-for="o in spanOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="$emit('close')" class="px-4 py-2 border rounded-lg hover:bg-slate-50">
            Cancel
          </button>
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ saving ? 'Saving...' : 'Save Lesson' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { classSubjectsAPI, roomsAPI, timetableAPI } from '../../services/api.js';
import { getClassLevel, getSpanOptions } from '../../utils/lessonSpans.js';

const props = defineProps({
  classId: { type: [Number, String], required: true },
  className: { type: String, default: '' },
  day: { type: String, default: 'Monday' },
  period: { type: Number, default: 1 },
  teachers: { type: Array, default: () => [] },
  activeSession: { type: Object, default: null },
  entry: { type: Object, default: null }
});

const emit = defineEmits(['close', 'saved']);

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const periods = [1, 2, 3, 4, 5, 6, 7, 8, 9];

const subjects = ref([]);
const rooms = ref([]);
const saving = ref(false);
const errorMessage = ref('');

const isEditing = computed(() => !!props.entry);
const level = computed(() => getClassLevel(props.className));
const spanOptions = computed(() => getSpanOptions(level.value));

const form = ref({
  day_of_week: props.entry?.day_of_week || props.day,
  period_number: props.entry?.period_number || props.period,
  subject_id: props.entry?.subject_id || '',
  teacher_id: props.entry?.teacher_id || '',
  room_id: props.entry?.room_id || '',
  spans_periods: props.entry?.spans_periods || 1
});

const loadSubjects = async () => {
  try {
    const res = await classSubjectsAPI.getByClassId(props.classId);
    subjects.value = res.success ? (res.data || []) : [];
  } catch (error) {
    console.error('Error loading class subjects:', error);
  }
};

const loadRooms = async () => {
  try {
    const res = await roomsAPI.getAll();
    rooms.value = res.success ? (res.data || []) : [];
  } catch (error) {
    console.error('Error loading rooms:', error);
  }
};

const save = async () => {
  errorMessage.value = '';
  saving.value = true;

  const payload = {
    entry_type: 'lesson',
    academic_session_id: props.activeSession?.id,
    class_id: props.classId,
    subject_id: form.value.subject_id,
    teacher_id: form.value.teacher_id,
    room_id: form.value.room_id || null,
    day_of_week: form.value.day_of_week,
    period_number: form.value.period_number,
    spans_periods: form.value.spans_periods
  };

  try {
    const res = isEditing.value
      ? await timetableAPI.update({ id: props.entry.id, ...payload })
      : await timetableAPI.create(payload);

    if (res.success) {
      emit('saved');
    } else {
      errorMessage.value = res.message || 'Failed to save lesson';
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Failed to save lesson';
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadSubjects();
  loadRooms();
});
</script>
