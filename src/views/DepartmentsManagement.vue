<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Departments Management</h1>
        <p class="mt-2 text-gray-600">Manage school departments and their positions</p>
      </div>

      <!-- Add Department Button -->
      <div class="mb-6">
        <button @click="showAddModal = true" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          <Plus class="w-5 h-5 mr-2" />
          Add New Department
        </button>
      </div>

      <!-- Departments Table -->
      <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roles</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="loading">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading...</td>
              </tr>
              <tr v-else-if="departments.length === 0">
                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No departments found</td>
              </tr>
              <tr v-else v-for="department in departments" :key="department.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ department.name }}</td>
                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">{{ department.description || '-' }}</td>
                <td class="px-6 py-4 text-sm text-gray-500">
                  <span v-if="department.roles && department.roles.length > 0" class="inline-flex flex-wrap gap-1">
                    <span v-for="role in department.roles.slice(0, 3)" :key="role.id" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">{{ role.name }}</span>
                    <span v-if="department.roles.length > 3" class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full">+{{ department.roles.length - 3 }}</span>
                  </span>
                  <span v-else class="text-gray-400">-</span>
                  <button 
                    @click="openAddRoleModal(department)" 
                    class="ml-2 px-2 py-1 bg-blue-500 text-white text-[10px] font-medium rounded-full hover:bg-blue-600 flex items-center transition shadow-sm hover:shadow"
                  >
                    <Plus class="w-2.5 h-2.5 mr-0.5" />
                    Add Role
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span :class="department.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                    {{ department.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button @click="viewDepartment(department)" class="text-green-600 hover:text-green-900 mr-3">View</button>
                  <button @click="editDepartment(department)" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                  <button @click="deleteDepartment(department.id)" class="text-red-600 hover:text-red-900">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add/Edit Modal -->
      <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sticky top-0">
            <h2 class="text-lg font-semibold text-gray-900">{{ showEditModal ? 'Edit Department' : 'Add New Department' }}</h2>
          </div>
          <form @submit.prevent="saveDepartment" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Department Name</label>
                <input v-model="formData.name" type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" placeholder="e.g., Science Department" required>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea v-model="formData.description" rows="3" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none" placeholder="Department description"></textarea>
              </div>
              <div v-if="showEditModal" class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Assign Roles</label>
                <div class="border border-gray-300 rounded-lg p-4 max-h-64 overflow-y-auto bg-gray-50">
                  <div v-if="rolesLoading" class="text-sm text-gray-500 flex items-center justify-center py-4">
                    <svg class="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading roles...
                  </div>
                  <div v-else-if="roles.length === 0" class="text-sm text-gray-500 py-4 text-center">No roles available</div>
                  <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label v-for="role in roles" :key="role.id" class="flex items-center space-x-3 p-3 bg-white rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition">
                      <input 
                        type="checkbox" 
                        :value="role.id" 
                        v-model="formData.role_ids"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      >
                      <div class="flex-1">
                        <span class="text-sm font-medium text-gray-700">{{ role.name }}</span>
                        <span v-if="role.department_name" class="block text-xs text-gray-400">{{ role.department_name }}</span>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
              <div class="md:col-span-2">
                <label class="flex items-center space-x-3 cursor-pointer">
                  <input v-model="formData.is_active" type="checkbox" id="is_active" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                  <span for="is_active" class="text-sm font-medium text-gray-700">Active</span>
                </label>
              </div>
            </div>
            <div v-if="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {{ errorMessage }}
            </div>
            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-gray-200">
              <button type="button" @click="closeModal" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
              <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">Save Department</button>
            </div>
          </form>
        </div>
      </div>

      <!-- View Department Modal -->
      <div v-if="showViewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
          <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900">Department Details</h2>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="p-6" v-if="selectedDepartment">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Department Name</label>
                <p class="text-lg font-semibold text-gray-900">{{ selectedDepartment.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Description</label>
                <p class="text-gray-700">{{ selectedDepartment.description || '-' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                <span :class="selectedDepartment.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                  {{ selectedDepartment.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-500 mb-2">Assigned Roles ({{ selectedDepartment.roles?.length || 0 }})</label>
                <div v-if="selectedDepartment.roles && selectedDepartment.roles.length > 0" class="flex flex-wrap gap-2">
                  <span v-for="role in selectedDepartment.roles" :key="role.id" class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                    {{ role.name }}
                  </span>
                </div>
                <p v-else class="text-gray-500">No roles assigned</p>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
            <button @click="closeModal" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">Close</button>
          </div>
        </div>
      </div>

      <!-- Add New Role Modal -->
      <div v-if="showAddRole" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Add New Role to {{ selectedDepartment?.name }}</h2>
          </div>
          <form @submit.prevent="addNewRole" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Role Name</label>
              <input v-model="newRoleName" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g., Accountant" required>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea v-model="newRoleDescription" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Role description"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-4">
              <button type="button" @click="showAddRole = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add Role</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Plus, Eye } from 'lucide-vue-next';
import { departmentsAPI, rolesAPI } from '../services/api';
import axios from 'axios';

const API_BASE_URL = import.meta.env.DEV
  ? 'http://localhost/accademics/backend/api/'
  : 'https://stmark.sc.ug/accademics/backend/api/';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: 'application/json'
  }
});

const departments = ref([]);
const loading = ref(false);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showViewModal = ref(false);
const showAddRole = ref(false);
const editingId = ref(null);
const errorMessage = ref('');
const roles = ref([]);
const rolesLoading = ref(false);
const selectedDepartment = ref(null);
const newRoleName = ref('');
const newRoleDescription = ref('');

const formData = ref({
  name: '',
  description: '',
  role_ids: [],
  is_active: true
});

const loadDepartments = async () => {
  loading.value = true;
  try {
    const response = await departmentsAPI.getAll('', true);
    departments.value = response.data || [];
  } catch (error) {
    console.error('Error loading departments:', error);
  } finally {
    loading.value = false;
  }
};

const loadRoles = async () => {
  rolesLoading.value = true;
  try {
    const response = await rolesAPI.getAll();
    roles.value = response.data || [];
  } catch (error) {
    console.error('Error loading roles:', error);
  } finally {
    rolesLoading.value = false;
  }
};

const saveDepartment = async () => {
  errorMessage.value = '';
  try {
    if (showEditModal.value) {
      await departmentsAPI.update({
        id: editingId.value,
        ...formData.value
      });
    } else {
      await departmentsAPI.create(formData.value);
    }
    closeModal();
    await loadDepartments();
  } catch (error) {
    console.error('Error saving department:', error);
    errorMessage.value = error.response?.data?.error || 'Failed to save department';
  }
};

const editDepartment = (department) => {
  editingId.value = department.id;
  formData.value = {
    name: department.name,
    description: department.description || '',
    role_ids: department.roles ? department.roles.map(r => r.id) : [],
    is_active: department.is_active
  };
  showEditModal.value = true;
};

const deleteDepartment = async (id) => {
  if (!confirm('Are you sure you want to delete this department?')) return;
  
  try {
    await departmentsAPI.delete(id);
    await loadDepartments();
  } catch (error) {
    console.error('Error deleting department:', error);
    alert('Failed to delete department');
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  showViewModal.value = false;
  showAddRole.value = false;
  editingId.value = null;
  selectedDepartment.value = null;
  formData.value = {
    name: '',
    description: '',
    role_ids: [],
    is_active: true
  };
  newRoleName.value = '';
  newRoleDescription.value = '';
  errorMessage.value = '';
};

const viewDepartment = (department) => {
  selectedDepartment.value = department;
  showViewModal.value = true;
};

const openAddRoleModal = (department) => {
  selectedDepartment.value = department;
  newRoleName.value = '';
  newRoleDescription.value = '';
  showAddRole.value = true;
};

const addNewRole = async () => {
  if (!newRoleName.value.trim()) {
    alert('Role name is required');
    return;
  }
  
  if (!selectedDepartment.value) {
    alert('Please select a department');
    return;
  }
  
  try {
    const response = await api.post('roles.php', {
      name: newRoleName.value,
      description: newRoleDescription.value,
      department_id: selectedDepartment.value.id
    });
    
    if (response.data.success || response.data.data?.id) {
      // Reload departments to show the new role
      await loadDepartments();
      
      // Reset form
      newRoleName.value = '';
      newRoleDescription.value = '';
      showAddRole.value = false;
      selectedDepartment.value = null;
    } else {
      alert('Failed to add role: ' + (response.data.message || 'Unknown error'));
    }
  } catch (error) {
    console.error('Error adding role:', error);
    alert('Failed to add role: ' + (error.response?.data?.message || error.message));
  }
};

onMounted(() => {
  loadDepartments();
  loadRoles();
});
</script>
