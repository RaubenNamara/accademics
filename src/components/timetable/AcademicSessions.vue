<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Academic Sessions</h2>
      <button @click="showForm = true" class="btn-primary">+ Add Session</button>
    </div>
    
    <!-- Sessions List -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Session Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Year</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Term</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Start Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">End Date</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="session in sessions" :key="session.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ session.session_name }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ session.academic_year }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">Term {{ session.term }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ formatDate(session.start_date) }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ formatDate(session.end_date) }}</td>
            <td class="px-6 py-4">
              <span v-if="session.is_active" class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
              <span v-else-if="session.is_archived" class="px-2 py-1 bg-slate-100 text-slate-800 rounded-full text-xs font-medium">Archived</span>
              <span v-else class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">Inactive</span>
            </td>
            <td class="px-6 py-4 text-sm">
              <button v-if="!session.is_active" @click="activateSession(session.id)" class="text-blue-600 hover:text-blue-800 mr-3">Activate</button>
              <button @click="editSession(session)" class="text-slate-600 hover:text-slate-800 mr-3">Edit</button>
              <button @click="deleteSession(session.id)" class="text-rose-600 hover:text-rose-800">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Session Form Modal -->
    <Modal v-if="showForm" @close="showForm = false" :title="form.id ? 'Edit Session' : 'Add Session'">
      <form @submit.prevent="saveSession" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Session Name</label>
          <input v-model="form.session_name" type="text" class="input w-full" placeholder="e.g., 2025 Term 1" required>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Academic Year</label>
            <input v-model.number="form.academic_year" type="number" class="input w-full" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Term</label>
            <select v-model.number="form.term" class="input w-full" required>
              <option :value="1">Term 1</option>
              <option :value="2">Term 2</option>
              <option :value="3">Term 3</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Start Date</label>
            <input v-model="form.start_date" type="date" class="input w-full" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">End Date</label>
            <input v-model="form.end_date" type="date" class="input w-full" required>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
            Set as active session
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_archived" type="checkbox" class="rounded border-slate-300">
            Archive session
          </label>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Notes</label>
          <textarea v-model="form.notes" class="input w-full" rows="3"></textarea>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary">Save Session</button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { academicSessionsAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import Modal from '../hr/Modal.vue';

const { showToast } = useToast();

const sessions = ref([]);
const showForm = ref(false);
const form = ref({
  id: null,
  session_name: '',
  academic_year: new Date().getFullYear(),
  term: 1,
  start_date: '',
  end_date: '',
  is_active: false,
  is_archived: false,
  notes: ''
});

const loadSessions = async () => {
  try {
    const res = await academicSessionsAPI.getAll();
    if (res.success) {
      sessions.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading sessions:', error);
    showToast('Failed to load sessions', 'error');
  }
};

const saveSession = async () => {
  try {
    if (form.value.id) {
      await academicSessionsAPI.update(form.value);
      showToast('Session updated');
    } else {
      await academicSessionsAPI.create(form.value);
      showToast('Session created');
    }
    showForm.value = false;
    loadSessions();
  } catch (error) {
    console.error('Error saving session:', error);
    showToast('Failed to save session', 'error');
  }
};

const editSession = (session) => {
  form.value = { ...session };
  showForm.value = true;
};

const activateSession = async (id) => {
  try {
    const session = sessions.value.find(s => s.id === id);
    await academicSessionsAPI.update({ ...session, is_active: true });
    showToast('Session activated');
    loadSessions();
  } catch (error) {
    console.error('Error activating session:', error);
    showToast('Failed to activate session', 'error');
  }
};

const deleteSession = async (id) => {
  if (!confirm('Are you sure you want to delete this session?')) return;
  
  try {
    await academicSessionsAPI.delete(id);
    showToast('Session deleted');
    loadSessions();
  } catch (error) {
    console.error('Error deleting session:', error);
    showToast('Failed to delete session', 'error');
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

onMounted(() => {
  loadSessions();
});
</script>
