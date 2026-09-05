<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Teacher Availability</h2>
      <div class="flex gap-3">
        <select v-model="selectedTeacherId" class="input" @change="loadTeacherAvailability">
          <option value="">Select Teacher</option>
          <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
        </select>
        <button @click="saveAvailability" class="btn-primary">Save Availability</button>
      </div>
    </div>
    
    <div v-if="selectedTeacherId" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-medium text-slate-900 mb-4">
        {{ selectedTeacher?.full_name }} ({{ selectedTeacher?.teacher_code }})
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
                <label class="flex items-center gap-2">
                  <input 
                    type="checkbox" 
                    v-model="availability[`${day}-${period}`]"
                    :true-value="true"
                    :false-value="false"
                    class="rounded border-slate-300"
                  >
                  <span :class="availability[`${day}-${period}`] ? 'text-green-600' : 'text-red-600'">
                    {{ availability[`${day}-${period}`] ? 'Available' : 'Unavailable' }}
                  </span>
                </label>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <div class="mt-4 p-4 bg-slate-50 rounded-lg">
        <h4 class="font-medium text-slate-900 mb-2">Instructions</h4>
        <p class="text-sm text-slate-600">
          Check the box to mark the teacher as available for that period. 
          Uncheck to mark as unavailable. By default, teachers are assumed available for all periods.
        </p>
      </div>
    </div>
    
    <div v-else class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
      Select a teacher to view and edit their availability
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { teacherAvailabilityAPI, teachersAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';

const { showToast } = useToast();

const teachers = ref([]);
const selectedTeacherId = ref('');
const availability = ref({});
const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const periods = [1, 2, 3, 4, 5, 6, 7, 8];

const selectedTeacher = computed(() => {
  return teachers.value.find(t => t.id === selectedTeacherId.value);
});

const loadTeachers = async () => {
  try {
    const res = await teachersAPI.getAll();
    if (res.success) {
      teachers.value = res.data.teachers || res.data || [];
    }
  } catch (error) {
    console.error('Error loading teachers:', error);
    showToast('Failed to load teachers', 'error');
  }
};

const loadTeacherAvailability = async () => {
  if (!selectedTeacherId.value) return;
  
  const sessionId = getActiveSessionId();
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const res = await teacherAvailabilityAPI.getByTeacher(sessionId, selectedTeacherId.value);
    if (res.success && res.data) {
      // Initialize availability - default to available (true)
      availability.value = {};
      days.forEach(day => {
        periods.forEach(period => {
          availability.value[`${day}-${period}`] = true;
        });
      });
      
      // Mark unavailable periods from database
      res.data.forEach(avail => {
        if (!avail.is_available) {
          availability.value[`${avail.day_of_week}-${avail.period_number}`] = false;
        }
      });
    }
  } catch (error) {
    console.error('Error loading availability:', error);
    showToast('Failed to load availability', 'error');
  }
};

const saveAvailability = async () => {
  if (!selectedTeacherId.value) {
    showToast('Please select a teacher first', 'warning');
    return;
  }
  
  const sessionId = getActiveSessionId();
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  // Convert availability to array of unavailable periods
  const unavailablePeriods = [];
  days.forEach(day => {
    periods.forEach(period => {
      if (!availability.value[`${day}-${period}`]) {
        unavailablePeriods.push({
          day_of_week: day,
          period_number: period,
          is_available: false
        });
      }
    });
  });
  
  try {
    await teacherAvailabilityAPI.bulkUpdate({
      teacher_id: selectedTeacherId.value,
      academic_session_id: sessionId,
      availability: unavailablePeriods
    });
    showToast('Availability saved successfully');
  } catch (error) {
    console.error('Error saving availability:', error);
    showToast('Failed to save availability', 'error');
  }
};

const getActiveSessionId = () => {
  return localStorage.getItem('activeSessionId');
};

onMounted(() => {
  loadTeachers();
});
</script>
