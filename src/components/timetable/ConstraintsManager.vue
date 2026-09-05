<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Constraints & Rules</h2>
      <button @click="showForm = true" class="btn-primary">+ Add Constraint</button>
    </div>
    
    <!-- Constraint Categories -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- Teacher Constraints -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Teacher Constraints</h3>
        <div class="space-y-3">
          <div v-for="constraint in teacherConstraints" :key="constraint.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ getConstraintLabel(constraint.constraint_type) }}</p>
              <p class="text-xs text-slate-600">{{ formatConstraintValue(constraint.constraint_value) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button @click="toggleConstraint(constraint)" class="text-sm" :class="constraint.is_active ? 'text-green-600' : 'text-slate-400'">
                {{ constraint.is_active ? 'On' : 'Off' }}
              </button>
              <button @click="editConstraint(constraint)" class="text-slate-600 hover:text-slate-800 text-sm">Edit</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Subject Constraints -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Subject Constraints</h3>
        <div class="space-y-3">
          <div v-for="constraint in subjectConstraints" :key="constraint.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ getConstraintLabel(constraint.constraint_type) }}</p>
              <p class="text-xs text-slate-600">{{ formatConstraintValue(constraint.constraint_value) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button @click="toggleConstraint(constraint)" class="text-sm" :class="constraint.is_active ? 'text-green-600' : 'text-slate-400'">
                {{ constraint.is_active ? 'On' : 'Off' }}
              </button>
              <button @click="editConstraint(constraint)" class="text-slate-600 hover:text-slate-800 text-sm">Edit</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Class Constraints -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Class Constraints</h3>
        <div class="space-y-3">
          <div v-for="constraint in classConstraints" :key="constraint.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ getConstraintLabel(constraint.constraint_type) }}</p>
              <p class="text-xs text-slate-600">{{ formatConstraintValue(constraint.constraint_value) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button @click="toggleConstraint(constraint)" class="text-sm" :class="constraint.is_active ? 'text-green-600' : 'text-slate-400'">
                {{ constraint.is_active ? 'On' : 'Off' }}
              </button>
              <button @click="editConstraint(constraint)" class="text-slate-600 hover:text-slate-800 text-sm">Edit</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Room Constraints -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Room Constraints</h3>
        <div class="space-y-3">
          <div v-for="constraint in roomConstraints" :key="constraint.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
            <div>
              <p class="text-sm font-medium text-slate-900">{{ getConstraintLabel(constraint.constraint_type) }}</p>
              <p class="text-xs text-slate-600">{{ formatConstraintValue(constraint.constraint_value) }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button @click="toggleConstraint(constraint)" class="text-sm" :class="constraint.is_active ? 'text-green-600' : 'text-slate-400'">
                {{ constraint.is_active ? 'On' : 'Off' }}
              </button>
              <button @click="editConstraint(constraint)" class="text-slate-600 hover:text-slate-800 text-sm">Edit</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Constraint Form Modal -->
    <Modal v-if="showForm" @close="showForm = false" :title="form.id ? 'Edit Constraint' : 'Add Constraint'">
      <form @submit.prevent="saveConstraint" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Constraint Type</label>
          <select v-model="form.constraint_type" class="input w-full" required>
            <option value="">Select Type</option>
            <optgroup label="Teacher">
              <option value="no_double_booking">No Double Booking</option>
              <option value="max_lessons_per_day">Max Lessons Per Day</option>
              <option value="min_free_periods">Min Free Periods</option>
              <option value="max_consecutive_lessons">Max Consecutive Lessons</option>
              <option value="preferred_teaching_periods">Preferred Teaching Periods</option>
            </optgroup>
            <optgroup label="Subject">
              <option value="subject_sequencing">Subject Sequencing</option>
              <option value="double_lessons_allowed">Double Lessons Allowed</option>
            </optgroup>
            <optgroup label="Class">
              <option value="class_balance">Class Balance</option>
            </optgroup>
            <optgroup label="Room">
              <option value="room_restriction">Room Restriction</option>
            </optgroup>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Constraint Value (JSON)</label>
          <textarea v-model="form.constraint_value_json" class="input w-full font-mono text-sm" rows="5" placeholder='{"enabled": true, "max": 6}'></textarea>
          <p class="text-xs text-slate-500 mt-1">Enter constraint configuration as JSON</p>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="form.is_active" type="checkbox" id="isActive" class="rounded border-slate-300">
          <label for="isActive" class="text-sm text-slate-700">Active</label>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary">Save Constraint</button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { timetableConstraintsAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import Modal from '../hr/Modal.vue';

const props = defineProps({
  activeSession: {
    type: Object,
    default: null
  }
});

const { showToast } = useToast();

const constraints = ref([]);
const showForm = ref(false);
const form = ref({
  id: null,
  constraint_type: '',
  constraint_value_json: '',
  is_active: true
});

const teacherConstraints = computed(() => {
  return constraints.value.filter(c => [
    'no_double_booking',
    'max_lessons_per_day',
    'min_free_periods',
    'max_consecutive_lessons',
    'preferred_teaching_periods'
  ].includes(c.constraint_type));
});

const subjectConstraints = computed(() => {
  return constraints.value.filter(c => [
    'subject_sequencing',
    'double_lessons_allowed'
  ].includes(c.constraint_type));
});

const classConstraints = computed(() => {
  return constraints.value.filter(c => [
    'class_balance'
  ].includes(c.constraint_type));
});

const roomConstraints = computed(() => {
  return constraints.value.filter(c => [
    'room_restriction'
  ].includes(c.constraint_type));
});

const constraintLabels = {
  no_double_booking: 'No Double Booking',
  max_lessons_per_day: 'Max Lessons Per Day',
  min_free_periods: 'Min Free Periods',
  max_consecutive_lessons: 'Max Consecutive Lessons',
  preferred_teaching_periods: 'Preferred Teaching Periods',
  subject_sequencing: 'Subject Sequencing',
  double_lessons_allowed: 'Double Lessons Allowed',
  class_balance: 'Class Balance',
  room_restriction: 'Room Restriction'
};

const loadConstraints = async () => {
  try {
    const sessionId = props.activeSession?.id;
    if (!sessionId) {
      showToast('Please select an active academic session first', 'warning');
      return;
    }
    
    const res = await timetableConstraintsAPI.getBySession(sessionId);
    if (res.success) {
      constraints.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading constraints:', error);
    showToast('Failed to load constraints', 'error');
  }
};

const saveConstraint = async () => {
  try {
    const sessionId = props.activeSession?.id;
    if (!sessionId) {
      showToast('Please select an active academic session first', 'warning');
      return;
    }
    
    let constraintValue;
    try {
      constraintValue = JSON.parse(form.value.constraint_value_json);
    } catch (e) {
      showToast('Invalid JSON format', 'error');
      return;
    }
    
    const data = {
      ...form.value,
      academic_session_id: sessionId,
      constraint_value: constraintValue
    };
    
    if (form.value.id) {
      await timetableConstraintsAPI.update(data);
      showToast('Constraint updated');
    } else {
      await timetableConstraintsAPI.create(data);
      showToast('Constraint created');
    }
    showForm.value = false;
    loadConstraints();
  } catch (error) {
    console.error('Error saving constraint:', error);
    showToast('Failed to save constraint', 'error');
  }
};

const editConstraint = (constraint) => {
  form.value = {
    id: constraint.id,
    constraint_type: constraint.constraint_type,
    constraint_value_json: JSON.stringify(constraint.constraint_value, null, 2),
    is_active: constraint.is_active
  };
  showForm.value = true;
};

const toggleConstraint = async (constraint) => {
  try {
    await timetableConstraintsAPI.update({
      id: constraint.id,
      constraint_value: constraint.constraint_value,
      is_active: !constraint.is_active
    });
    loadConstraints();
  } catch (error) {
    console.error('Error toggling constraint:', error);
    showToast('Failed to toggle constraint', 'error');
  }
};

const getConstraintLabel = (type) => {
  return constraintLabels[type] || type;
};

const formatConstraintValue = (value) => {
  if (!value) return 'Not configured';
  if (typeof value === 'object') {
    return JSON.stringify(value).substring(0, 50) + '...';
  }
  return String(value);
};

watch(() => props.activeSession, () => {
  loadConstraints();
});

onMounted(() => {
  loadConstraints();
});
</script>
