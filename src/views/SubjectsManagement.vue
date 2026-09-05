<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Subjects Management</h1>
        <p class="mt-2 text-gray-600">Manage school subjects</p>
      </div>

      <!-- Add Subject Button -->
      <div class="mb-6">
        <button @click="showAddModal = true" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          <Plus class="w-5 h-5 mr-2" />
          Add New Subject
        </button>
      </div>

      <!-- Subjects Table -->
      <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="loading">
                <td colspan="4" class="px-6 py-4 text-center text-gray-500">Loading...</td>
              </tr>
              <tr v-else-if="subjects.length === 0">
                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No subjects found</td>
              </tr>
              <tr v-else v-for="subject in subjects" :key="subject.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ subject.subject_code }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ subject.subject_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(subject.created_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button @click="editSubject(subject)" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                  <button @click="deleteSubject(subject.id)" class="text-red-600 hover:text-red-900">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">{{ showEditModal ? 'Edit Subject' : 'Add New Subject' }}</h2>
          </div>
          <form @submit.prevent="saveSubject" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Subject Code</label>
              <input v-model="formData.subject_code" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase" placeholder="e.g., MATH" required>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Subject Name</label>
              <input v-model="formData.subject_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Mathematics" required>
            </div>
            <div v-if="errorMessage" class="text-red-600 text-sm">{{ errorMessage }}</div>
            <div class="flex justify-end gap-3 pt-4">
              <button type="button" @click="closeModal" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Plus } from 'lucide-vue-next';
import { subjectsNewAPI } from '../services/api';

const subjects = ref([]);
const loading = ref(false);
const showAddModal = ref(false);
const showEditModal = ref(false);
const editingId = ref(null);
const errorMessage = ref('');

const formData = ref({
  subject_code: '',
  subject_name: ''
});

const loadSubjects = async () => {
  loading.value = true;
  try {
    const response = await subjectsNewAPI.getAll();
    subjects.value = response.data || [];
  } catch (error) {
    console.error('Error loading subjects:', error);
  } finally {
    loading.value = false;
  }
};

const saveSubject = async () => {
  errorMessage.value = '';
  try {
    if (showEditModal.value) {
      await subjectsNewAPI.update({
        id: editingId.value,
        ...formData.value
      });
    } else {
      await subjectsNewAPI.create(formData.value);
    }
    closeModal();
    await loadSubjects();
  } catch (error) {
    console.error('Error saving subject:', error);
    errorMessage.value = error.response?.data?.error || 'Failed to save subject';
  }
};

const editSubject = (subject) => {
  editingId.value = subject.id;
  formData.value = {
    subject_code: subject.subject_code,
    subject_name: subject.subject_name
  };
  showEditModal.value = true;
};

const deleteSubject = async (id) => {
  if (!confirm('Are you sure you want to delete this subject?')) return;
  
  try {
    await subjectsNewAPI.delete(id);
    await loadSubjects();
  } catch (error) {
    console.error('Error deleting subject:', error);
    alert('Failed to delete subject');
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  editingId.value = null;
  formData.value = {
    subject_code: '',
    subject_name: ''
  };
  errorMessage.value = '';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString();
};

onMounted(() => {
  loadSubjects();
});
</script>

