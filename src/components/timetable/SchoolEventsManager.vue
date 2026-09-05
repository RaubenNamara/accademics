<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">School Events</h2>
      <button @click="showForm = true" class="btn-primary">+ Add Event</button>
    </div>
    
    <!-- Events List -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
      <table class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Event Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Duration</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Color</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="event in events" :key="event.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ event.event_name }}</td>
            <td class="px-6 py-4 text-sm text-slate-600 capitalize">{{ event.event_type }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ event.duration_minutes }} min</td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded" :style="{ backgroundColor: event.event_color }"></div>
                <span class="text-sm text-slate-600">{{ event.event_color }}</span>
              </div>
            </td>
            <td class="px-6 py-4">
              <span v-if="event.is_active" class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
              <span v-else class="px-2 py-1 bg-slate-100 text-slate-800 rounded-full text-xs font-medium">Inactive</span>
            </td>
            <td class="px-6 py-4 text-sm">
              <button @click="editEvent(event)" class="text-slate-600 hover:text-slate-800 mr-3">Edit</button>
              <button @click="deleteEvent(event.id)" class="text-rose-600 hover:text-rose-800">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Event Form Modal -->
    <Modal v-if="showForm" @close="showForm = false" :title="form.id ? 'Edit Event' : 'Add Event'">
      <form @submit.prevent="saveEvent" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Event Name</label>
          <input v-model="form.event_name" type="text" class="input w-full" placeholder="e.g., Morning Assembly" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Event Type</label>
          <select v-model="form.event_type" class="input w-full" required>
            <option value="devotion">Devotion</option>
            <option value="assembly">Assembly</option>
            <option value="breakfast">Breakfast</option>
            <option value="break">Break</option>
            <option value="lunch">Lunch</option>
            <option value="mentorship">Mentorship</option>
            <option value="games">Games</option>
            <option value="clubs">Clubs & Societies</option>
            <option value="prep">Evening Prep</option>
            <option value="supper">Supper</option>
            <option value="guidance">Guidance & Counselling</option>
            <option value="examination">Examinations</option>
            <option value="meeting">Meetings</option>
            <option value="custom">Custom</option>
          </select>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Duration (minutes)</label>
            <input v-model.number="form.duration_minutes" type="number" class="input w-full" value="40">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Color</label>
            <input v-model="form.event_color" type="color" class="input w-full h-10">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
          <textarea v-model="form.description" class="input w-full" rows="3"></textarea>
        </div>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300">
            Active
          </label>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary">Save Event</button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { schoolEventsAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import Modal from '../hr/Modal.vue';

const { showToast } = useToast();

const events = ref([]);
const showForm = ref(false);
const form = ref({
  id: null,
  event_name: '',
  event_type: 'assembly',
  event_color: '#FF6B6B',
  duration_minutes: 40,
  description: '',
  is_active: true
});

const loadEvents = async () => {
  try {
    const res = await schoolEventsAPI.getAll();
    if (res.success) {
      events.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading events:', error);
    showToast('Failed to load events', 'error');
  }
};

const saveEvent = async () => {
  try {
    if (form.value.id) {
      await schoolEventsAPI.update(form.value);
      showToast('Event updated');
    } else {
      await schoolEventsAPI.create(form.value);
      showToast('Event created');
    }
    showForm.value = false;
    loadEvents();
  } catch (error) {
    console.error('Error saving event:', error);
    showToast('Failed to save event', 'error');
  }
};

const editEvent = (event) => {
  form.value = { ...event };
  showForm.value = true;
};

const deleteEvent = async (id) => {
  if (!confirm('Are you sure you want to delete this event?')) return;
  
  try {
    await schoolEventsAPI.delete(id);
    showToast('Event deleted');
    loadEvents();
  } catch (error) {
    console.error('Error deleting event:', error);
    showToast('Failed to delete event', 'error');
  }
};

onMounted(() => {
  loadEvents();
});
</script>
