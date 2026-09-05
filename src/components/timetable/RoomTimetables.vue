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
      
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50">
              <th class="px-4 py-2 text-left border">Period</th>
              <th v-for="day in days" :key="day" class="px-4 py-2 text-left border">{{ day }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="period in periods" :key="period">
              <td class="px-4 py-2 border font-medium">P{{ period }}</td>
              <td v-for="day in days" :key="day" class="px-4 py-2 border">
                <div v-if="getEntry(day, period)" class="p-2 rounded" :class="getEntryClass(day, period)">
                  <p class="font-medium">{{ getEntry(day, period).subject_name }}</p>
                  <p class="text-xs text-slate-600">{{ getEntry(day, period).teacher_name }} ({{ getEntry(day, period).teacher_code }})</p>
                  <p class="text-xs text-slate-600">{{ getEntry(day, period).class_name }} {{ getEntry(day, period).stream_name ? `(${getEntry(day, period).stream_name})` : '' }}</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
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
import { ref, onMounted, computed } from 'vue';
import { roomsAPI, timetablePDFAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';

const { showToast } = useToast();

const rooms = ref([]);
const selectedRoomId = ref('');
const timetable = ref([]);
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const periods = [1, 2, 3, 4, 5, 6, 7, 8];

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
  
  const sessionId = getActiveSessionId();
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const sessionInfo = await getSessionInfo(sessionId);
    const res = await timetableAPI.getByRoom(sessionInfo.academic_year, sessionInfo.term, selectedRoomId.value);
    if (res.success) {
      timetable.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading timetable:', error);
    showToast('Failed to load timetable', 'error');
  }
};

const getEntry = (day, period) => {
  return timetable.value.find(t => t.day_of_week === day && t.period_number === period);
};

const getEntryClass = (day, period) => {
  const entry = getEntry(day, period);
  if (!entry) return '';
  if (entry.entry_type === 'event') return 'bg-yellow-100';
  return 'bg-blue-100';
};

const exportPDF = async () => {
  try {
    const sessionId = getActiveSessionId();
    const res = await timetablePDFAPI.exportRoom({
      academic_session_id: sessionId,
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

const getActiveSessionId = () => {
  return localStorage.getItem('activeSessionId');
};

const getSessionInfo = async (sessionId) => {
  // This would normally call the academic sessions API
  // For now, return placeholder
  return { academic_year: 2025, term: 1 };
};

onMounted(() => {
  loadRooms();
});
</script>
