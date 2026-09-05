<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Class Subjects Assignment</h1>
        <p class="mt-2 text-gray-600">Assign subjects to classes</p>
      </div>

      <!-- Class Selection -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Select Class</label>
        <select v-model="selectedClassId" @change="loadClassSubjects" class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- Select a Class --</option>
          <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.full_class_name }}</option>
        </select>
      </div>

      <!-- Subjects Assignment Section -->
      <div v-if="selectedClassId" class="bg-white shadow rounded-lg p-6">
        <div class="mb-6">
          <h2 class="text-xl font-semibold text-gray-900">Assign Subjects to {{ selectedClassName }}</h2>
        </div>

        <!-- Available Subjects -->
        <div class="mb-6">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Available Subjects</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <label v-for="subject in subjects" :key="subject.id" class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
              <input 
                v-model="selectedSubjectIds" 
                :value="subject.id" 
                type="checkbox" 
                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
              >
              <span class="ml-2 text-sm text-gray-700">{{ subject.subject_name }}</span>
            </label>
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end gap-3">
          <button @click="saveAssignments" :disabled="loading" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ loading ? 'Saving...' : 'Save Assignments' }}
          </button>
        </div>

        <!-- Currently Assigned Subjects -->
        <div class="mt-8 pt-6 border-t border-gray-200">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Currently Assigned Subjects</h3>
          <div v-if="assignedSubjects.length > 0" class="flex flex-wrap gap-2">
            <span v-for="subject in assignedSubjects" :key="subject.id" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
              {{ subject.subject_name }}
              <button @click="removeSubject(subject.id)" class="ml-2 text-blue-600 hover:text-blue-800">&times;</button>
            </span>
          </div>
          <div v-else class="text-sm text-gray-500">No subjects assigned to this class</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { classesAPI, subjectsNewAPI, classSubjectsAPI } from '../services/api';

const classes = ref([]);
const subjects = ref([]);
const assignedSubjects = ref([]);
const selectedClassId = ref(null);
const selectedSubjectIds = ref([]);
const loading = ref(false);

const selectedClassName = computed(() => {
  if (!selectedClassId.value) return '';
  const cls = classes.value.find(c => c.id === selectedClassId.value);
  return cls ? cls.full_class_name : '';
});

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    classes.value = response.data || [];
  } catch (error) {
    console.error('Error loading classes:', error);
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

const loadClassSubjects = async () => {
  if (!selectedClassId.value) {
    assignedSubjects.value = [];
    selectedSubjectIds.value = [];
    return;
  }

  try {
    const response = await classSubjectsAPI.getByClassId(selectedClassId.value);
    assignedSubjects.value = response.data || [];
    selectedSubjectIds.value = assignedSubjects.value.map(s => s.id);
  } catch (error) {
    console.error('Error loading class subjects:', error);
    assignedSubjects.value = [];
    selectedSubjectIds.value = [];
  }
};

const saveAssignments = async () => {
  loading.value = true;
  try {
    await classSubjectsAPI.bulkAssign(selectedClassId.value, selectedSubjectIds.value);
    await loadClassSubjects();
  } catch (error) {
    console.error('Error saving assignments:', error);
    alert('Failed to save assignments');
  } finally {
    loading.value = false;
  }
};

const removeSubject = async (subjectId) => {
  try {
    await classSubjectsAPI.remove(selectedClassId.value, subjectId);
    await loadClassSubjects();
  } catch (error) {
    console.error('Error removing subject:', error);
    alert('Failed to remove subject');
  }
};

onMounted(() => {
  loadClasses();
  loadSubjects();
});
</script>

