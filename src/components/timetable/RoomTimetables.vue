<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Room Timetables</h2>
      <div class="flex gap-3">
        <select v-model="selectedRoomId" class="input" @change="loadRoomTimetable">
          <option value="">Select Room</option>
          <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.room_code }} - {{ r.room_name }}</option>
        </select>
        <button v-if="selectedRoomId" @click="exportPDF" class="btn-secondary">Export PDF</button>
      </div>
    </div>

    <div v-if="selectedRoomId && timetable.length > 0" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-medium text-slate-900 mb-4">
        {{ selectedRoom?.room_name }} ({{ selectedRoom?.room_code }})
      </h3>

      <TimetableGrid :entries="timetable" :editable="false" />
    </div>

    <div v-else-if="selectedRoomId" class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
      No timetable entries found for this room
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
      Select a room to view its timetable
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { roomsAPI, timetableAPI, timetablePDFAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import TimetableGrid from './TimetableGrid.vue';

const props = defineProps({
  activeSession: {
    type: Object,
    default: null
  }
});

const { showToast } = useToast();

const rooms = ref([]);
const selectedRoomId = ref('');
const timetable = ref([]);

const selectedRoom = computed(() => {
  return rooms.value.find(r => r.id === selectedRoomId.value);
});

const loadRooms = async () => {
  try {
    const res = await roomsAPI.getAll();
    if (res.success) {
      rooms.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading rooms:', error);
    showToast('Failed to load rooms', 'error');
  }
};

const loadRoomTimetable = async () => {
  if (!selectedRoomId.value) return;

  if (!props.activeSession) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }

  try {
    const res = await timetableAPI.getByRoom(props.activeSession.academic_year, props.activeSession.term, selectedRoomId.value);
    if (res.success) {
      timetable.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading timetable:', error);
    showToast('Failed to load timetable', 'error');
  }
};

const exportPDF = async () => {
  if (!props.activeSession) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }

  try {
    const res = await timetablePDFAPI.exportRoom({
      academic_session_id: props.activeSession.id,
      room_id: selectedRoomId.value
    });

    if (res.success) {
      const blob = new Blob([res.data.html], { type: 'text/html' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = res.data.filename;
      a.click();
      URL.revokeObjectURL(url);
      showToast('PDF exported successfully');
    }
  } catch (error) {
    console.error('Error exporting PDF:', error);
    showToast('Failed to export PDF', 'error');
  }
};

watch(() => props.activeSession, () => {
  if (selectedRoomId.value) loadRoomTimetable();
});

onMounted(() => {
  loadRooms();
});
</script>
