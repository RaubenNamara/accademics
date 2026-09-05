<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Master Timetable</h2>
      <div class="flex gap-3">
        <select v-model="selectedClassId" class="input" @change="loadClassTimetable">
          <option value="">Filter by Class</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.class_name }} {{ c.stream_name ? `(${c.stream_name})` : '' }}</option>
        </select>
        <button @click="exportPDF" class="btn-secondary">Export PDF</button>
      </div>
    </div>
    
    <div v-if="selectedClassId && timetable.length > 0" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-medium text-slate-900 mb-4">
        {{ selectedClass?.class_name }} {{ selectedClass?.stream_name ? `(${selectedClass.stream_name})` : '' }}
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
                  <p v-if="getEntry(day, period).entry_type === 'event'" class="font-medium" :style="{ color: getEntry(day, period).event_color }">
                    {{ getEntry(day, period).event_name }}
                  </p>
                  <p v-else class="font-medium">{{ getEntry(day, period).subject_code }}</p>
                  <p v-if="getEntry(day, period).entry_type === 'lesson'" class="text-xs text-slate-600">
                    {{ getEntry(day, period).teacher_name }} ({{ getEntry(day, period).teacher_code }})
                  </p>
                  <p v-if="getEntry(day, period).room_code" class="text-xs text-slate-600">
                    {{ getEntry(day, period).room_code }}
                  </p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <div v-else-if="selectedClassId" class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
      No timetable entries found for this class
    </div>
    
    <div v-else class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
      Select a class to view its timetable
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { classesAPI, timetablePDFAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';

const { showToast } = useToast();

const classes = ref([]);
const selectedClassId = ref('');
const timetable = ref([]);
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const periods = [1, 2, 3, 4, 5, 6, 7, 8];

const selectedClass = computed(() => {
  return classes.value.find(c => c.id === selectedClassId.value);
});

const loadClasses = async () => {
  try {
    const res = await classesAPI.getAll();
    if (res.success) {
      classes.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading classes:', error);
    showToast('Failed to load classes', 'error');
  }
};

const loadClassTimetable = async () => {
  if (!selectedClassId.value) return;
  
  const sessionId = getActiveSessionId();
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const sessionInfo = await getSessionInfo(sessionId);
    const res = await timetableAPI.getByClass(sessionInfo.academic_year, sessionInfo.term, selectedClassId.value);
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
    const res = await timetablePDFAPI.exportClass({
      academic_session_id: sessionId,
      class_id: selectedClassId.value
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
  return { academic_year: 2025, term: 1 };
};

onMounted(() => {
  loadClasses();
});
</script>
