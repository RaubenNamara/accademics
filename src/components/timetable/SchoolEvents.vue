<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-slate-900">School Events</h2>
        <p class="text-sm text-slate-600 mt-1">Manage school-wide events for the timetable</p>
      </div>
      <button @click="showForm = true" class="btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Event
      </button>
    </div>

    <!-- Event Type Filter -->
    <div class="flex gap-2 flex-wrap">
      <button
        v-for="type in eventTypes"
        :key="type.value"
        @click="filterType = type.value"
        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
        :class="filterType === type.value ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
      >
        {{ type.label }}
      </button>
    </div>

    <!-- Events Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="event in filteredEvents"
        :key="event.id"
        class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-lg flex items-center justify-center"
              :style="{ backgroundColor: event.event_color + '20', color: event.event_color }"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div>
              <h3 class="font-semibold text-slate-900">{{ event.event_name }}</h3>
              <span class="text-xs px-2 py-1 rounded-full" :class="getEventTypeClass(event.event_type)">
                {{ formatEventType(event.event_type) }}
              </span>
            </div>
          </div>
          <div class="flex gap-1">
            <button @click="editEvent(event)" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button @click="deleteEvent(event.id)" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <div class="space-y-2 text-sm">
          <div class="flex items-center gap-2 text-slate-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ event.duration_minutes }} minutes</span>
            <span v-if="event.spans_periods > 1">({{ event.spans_periods }} periods)</span>
          </div>

          <div v-if="event.event_description" class="text-slate-600 line-clamp-2">
            {{ event.event_description }}
          </div>

          <div class="flex gap-2 pt-2">
            <span v-if="event.is_mandatory" class="text-xs px-2 py-1 bg-amber-100 text-amber-800 rounded-full">Mandatory</span>
            <span v-if="event.applies_to_all_classes" class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">All Classes</span>
            <span v-if="!event.is_active" class="text-xs px-2 py-1 bg-slate-100 text-slate-600 rounded-full">Inactive</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="filteredEvents.length === 0" class="bg-white rounded-xl border border-slate-200 p-12 text-center">
      <svg class="mx-auto h-16 w-16 text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <h3 class="text-lg font-medium text-slate-900 mb-2">No events found</h3>
      <p class="text-slate-600 mb-4">Get started by adding your first school event</p>
      <button @click="showForm = true" class="btn-primary">Add Event</button>
    </div>

    <!-- Event Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-slate-200">
          <h3 class="text-xl font-semibold text-slate-900">{{ editingEvent ? 'Edit Event' : 'Add Event' }}</h3>
        </div>

        <form @submit.prevent="saveEvent" class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Event Name *</label>
            <input v-model="formData.event_name" type="text" class="input w-full" required>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Event Type *</label>
            <select v-model="formData.event_type" class="input w-full" required>
              <option value="">Select type</option>
              <option v-for="type in eventTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Event Color</label>
            <div class="flex gap-2">
              <input v-model="formData.event_color" type="color" class="h-10 w-14 rounded border border-slate-300 cursor-pointer">
              <input v-model="formData.event_color" type="text" class="input flex-1" placeholder="#FF6B6B">
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Duration (minutes)</label>
              <input v-model.number="formData.duration_minutes" type="number" class="input w-full" min="5" step="5">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Spans Periods</label>
              <input v-model.number="formData.spans_periods" type="number" class="input w-full" min="1" max="4">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
            <textarea v-model="formData.event_description" class="input w-full" rows="3"></textarea>
          </div>

          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="formData.is_mandatory" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
              <span class="text-sm text-slate-700">Mandatory Event</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="formData.applies_to_all_classes" type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
              <span class="text-sm text-slate-700">Applies to All Classes</span>
            </label>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
            <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
            <button type="submit" class="btn-primary">{{ editingEvent ? 'Update' : 'Create' }} Event</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { schoolEventsAPI } from '../../services/api.js';

const events = ref([]);
const showForm = ref(false);
const editingEvent = ref(null);
const filterType = ref('');

const formData = ref({
  id: null,
  event_name: '',
  event_type: '',
  event_color: '#FF6B6B',
  event_description: '',
  is_mandatory: false,
  applies_to_all_classes: true,
  duration_minutes: 40,
  spans_periods: 1,
  is_active: true
});

const eventTypes = [
  { value: 'devotion', label: 'Devotion' },
  { value: 'assembly', label: 'Assembly' },
  { value: 'breakfast', label: 'Breakfast' },
  { value: 'break', label: 'Break' },
  { value: 'lunch', label: 'Lunch' },
  { value: 'mentorship', label: 'Mentorship' },
  { value: 'games', label: 'Games' },
  { value: 'clubs', label: 'Clubs' },
  { value: 'prep', label: 'Prep' },
  { value: 'supper', label: 'Supper' },
  { value: 'other', label: 'Other' }
];

const filteredEvents = computed(() => {
  if (!filterType.value) return events.value;
  return events.value.filter(e => e.event_type === filterType.value);
});

const loadEvents = async () => {
  try {
    const res = await schoolEventsAPI.getAll();
    if (res.success) {
      events.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading events:', error);
  }
};

const editEvent = (event) => {
  editingEvent.value = event;
  formData.value = { ...event };
  showForm.value = true;
};

const saveEvent = async () => {
  try {
    let res;
    if (editingEvent.value) {
      res = await schoolEventsAPI.update(formData.value);
    } else {
      res = await schoolEventsAPI.create(formData.value);
    }

    if (res.success) {
      showForm.value = false;
      editingEvent.value = null;
      resetForm();
      await loadEvents();
    }
  } catch (error) {
    console.error('Error saving event:', error);
  }
};

const deleteEvent = async (id) => {
  if (!confirm('Are you sure you want to delete this event?')) return;

  try {
    const res = await schoolEventsAPI.delete(id);
    if (res.success) {
      await loadEvents();
    }
  } catch (error) {
    console.error('Error deleting event:', error);
  }
};

const resetForm = () => {
  formData.value = {
    id: null,
    event_name: '',
    event_type: '',
    event_color: '#FF6B6B',
    event_description: '',
    is_mandatory: false,
    applies_to_all_classes: true,
    duration_minutes: 40,
    spans_periods: 1,
    is_active: true
  };
};

const formatEventType = (type) => {
  const found = eventTypes.find(t => t.value === type);
  return found ? found.label : type;
};

const getEventTypeClass = (type) => {
  const classes = {
    devotion: 'bg-purple-100 text-purple-800',
    assembly: 'bg-blue-100 text-blue-800',
    breakfast: 'bg-amber-100 text-amber-800',
    break: 'bg-green-100 text-green-800',
    lunch: 'bg-orange-100 text-orange-800',
    mentorship: 'bg-pink-100 text-pink-800',
    games: 'bg-teal-100 text-teal-800',
    clubs: 'bg-indigo-100 text-indigo-800',
    prep: 'bg-cyan-100 text-cyan-800',
    supper: 'bg-rose-100 text-rose-800',
    other: 'bg-slate-100 text-slate-800'
  };
  return classes[type] || classes.other;
};

onMounted(() => {
  loadEvents();
});
</script>
