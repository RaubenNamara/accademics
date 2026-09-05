<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Classes Management</h1>
        <p class="mt-2 text-gray-600">Manage school classes and streams</p>
      </div>

      <!-- Add Class Button -->
      <div class="mb-6">
        <button @click="showAddModal = true" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          <Plus class="w-5 h-5 mr-2" />
          Add New Class
        </button>
      </div>

      <!-- Classes Table -->
      <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stream</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Class Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="loading">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading...</td>
              </tr>
              <tr v-else-if="classes.length === 0">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No classes found</td>
              </tr>
              <tr v-else v-for="cls in classes" :key="cls.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ cls.class_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ cls.stream_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ cls.full_class_name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(cls.created_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button @click="editClass(cls)" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                  <button @click="deleteClass(cls.id)" class="text-red-600 hover:text-red-900">Delete</button>
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
            <h2 class="text-lg font-semibold text-gray-900">{{ showEditModal ? 'Edit Class' : 'Add New Class' }}</h2>
          </div>
          <form @submit.prevent="saveClass" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Class Name</label>
              <input v-model="formData.class_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., S.1" required>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Stream Name</label>
              <input v-model="formData.stream_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., East" required>
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
import { classesAPI } from '../services/api';

const classes = ref([]);
const loading = ref(false);
const showAddModal = ref(false);
const showEditModal = ref(false);
const editingId = ref(null);
const errorMessage = ref('');

const formData = ref({
  class_name: '',
  stream_name: ''
});

const loadClasses = async () => {
  loading.value = true;
  try {
    const response = await classesAPI.getAll();
    classes.value = response.data || [];
  } catch (error) {
    console.error('Error loading classes:', error);
  } finally {
    loading.value = false;
  }
};

const saveClass = async () => {
  errorMessage.value = '';
  try {
    if (showEditModal.value) {
      await classesAPI.update({
        id: editingId.value,
        ...formData.value
      });
    } else {
      await classesAPI.create(formData.value);
    }
    closeModal();
    await loadClasses();
  } catch (error) {
    console.error('Error saving class:', error);
    errorMessage.value = error.response?.data?.error || 'Failed to save class';
  }
};

const editClass = (cls) => {
  editingId.value = cls.id;
  formData.value = {
    class_name: cls.class_name,
    stream_name: cls.stream_name
  };
  showEditModal.value = true;
};

const deleteClass = async (id) => {
  if (!confirm('Are you sure you want to delete this class?')) return;
  
  try {
    await classesAPI.delete(id);
    await loadClasses();
  } catch (error) {
    console.error('Error deleting class:', error);
    alert('Failed to delete class');
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  editingId.value = null;
  formData.value = {
    class_name: '',
    stream_name: ''
  };
  errorMessage.value = '';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString();
};

onMounted(() => {
  loadClasses();
});
</script>

