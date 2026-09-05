<template>
  <div class="space-y-5">
    <ToastBanner />
    <PageHeader title="Event Management" subtitle="Manage school events and activities" @refresh="load" @add="openForm()" />

    <DataPanel :loading="loading">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="event in events"
          :key="event.id"
          class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
          :style="{ borderLeft: `4px solid ${event.event_color}` }"
        >
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">{{ event.event_name }}</h3>
              <p class="text-sm text-slate-500 capitalize">{{ event.event_type.replace('_', ' ') }}</p>
            </div>
            <div class="flex gap-2">
              <button class="text-blue-600 hover:text-blue-800" @click="openForm(event)">Edit</button>
              <button class="text-rose-600 hover:text-rose-800" @click="deleteEvent(event.id)">Delete</button>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <div
              class="h-6 w-6 rounded-full"
              :style="{ backgroundColor: event.event_color }"
            ></div>
            <span class="text-sm text-slate-600">{{ event.event_color }}</span>
          </div>
          <div class="mt-2 text-sm text-slate-600">
            <span class="font-medium">Duration:</span> {{ event.duration_minutes }} minutes
          </div>
          <p v-if="event.description" class="mt-2 text-sm text-slate-500">{{ event.description }}</p>
        </div>
      </div>
    </DataPanel>

    <Modal v-if="showForm" :title="form.id ? 'Edit Event' : 'Add Event'" @close="showForm = false">
      <form class="space-y-4" @submit.prevent="saveEvent">
        <div>
          <label class="block text-sm font-medium text-slate-700">Event Name</label>
          <input v-model="form.event_name" class="input mt-1" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Event Type</label>
          <select v-model="form.event_type" class="input mt-1" required>
            <option value="breakfast">Breakfast</option>
            <option value="assembly">Morning Assembly</option>
            <option value="tea_break">Tea Break</option>
            <option value="lunch_break">Lunch Break</option>
            <option value="evening_prep">Evening Prep</option>
            <option value="sports">Sports</option>
            <option value="clubs">Clubs & Societies</option>
            <option value="guidance">Guidance & Counselling</option>
            <option value="examination">Examinations</option>
            <option value="meeting">Meetings</option>
            <option value="custom">Custom Event</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Event Color</label>
          <div class="mt-1 flex items-center gap-3">
            <input
              v-model="form.event_color"
              type="color"
              class="h-10 w-20 rounded border border-slate-300"
            />
            <input v-model="form.event_color" class="input flex-1" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Duration (minutes)</label>
          <input v-model.number="form.duration_minutes" type="number" class="input mt-1" min="1" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700">Description</label>
          <textarea v-model="form.description" class="input mt-1" rows="3"></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="showForm = false">Cancel</button>
          <button type="submit" class="btn-primary">Save</button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { eventsAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import PageHeader from '../components/hr/PageHeader.vue';
import DataPanel from '../components/hr/DataPanel.vue';
import Modal from '../components/hr/Modal.vue';

const { showToast } = useToast();

const loading = ref(false);
const events = ref([]);
const showForm = ref(false);
const form = ref({
  id: null,
  event_name: '',
  event_type: 'custom',
  event_color: '#FF6B6B',
  description: '',
  duration_minutes: 40
});

const load = async () => {
  loading.value = true;
  try {
    const res = await eventsAPI.getAll();
    events.value = res.success ? res.data || [] : [];
  } catch {
    showToast('Failed to load events', 'error');
  } finally {
    loading.value = false;
  }
};

const openForm = (event = null) => {
  form.value = event ? { ...event } : {
    id: null,
    event_name: '',
    event_type: 'custom',
    event_color: '#FF6B6B',
    description: '',
    duration_minutes: 40
  };
  showForm.value = true;
};

const saveEvent = async () => {
  try {
    console.log('Saving event:', form.value);
    const res = form.value.id
      ? await eventsAPI.update(form.value)
      : await eventsAPI.create(form.value);
    console.log('Response:', res);
    if (res.success) {
      showToast(res.message || 'Event saved');
      showForm.value = false;
      load();
    } else {
      showToast(res.message || 'Save failed', 'error');
    }
  } catch (e) {
    console.error('Save error:', e);
    console.error('Error response:', e.response?.data);
    showToast(e.response?.data?.message || e.message || 'Save failed', 'error');
  }
};

const deleteEvent = async (id) => {
  if (!confirm('Delete this event?')) return;
  try {
    const res = await eventsAPI.delete(id);
    if (res.success) {
      showToast('Event deleted');
      load();
    } else {
      showToast(res.message || 'Delete failed', 'error');
    }
  } catch {
    showToast('Delete failed', 'error');
  }
};

onMounted(load);
</script>
