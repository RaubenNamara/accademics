<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Bell Schedule</h2>
      <button @click="showScheduleForm = true" class="btn-primary">+ Add Schedule</button>
    </div>
    
    <!-- Schedules List -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Schedule Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Day Pattern</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Periods</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="schedule in schedules" :key="schedule.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ schedule.schedule_name }}</td>
            <td class="px-6 py-4 text-sm text-slate-600 capitalize">{{ schedule.schedule_type }}</td>
            <td class="px-6 py-4 text-sm text-slate-600 capitalize">{{ schedule.day_pattern }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ schedule.period_count || 0 }}</td>
            <td class="px-6 py-4">
              <span v-if="schedule.is_active" class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span>
              <span v-else class="px-2 py-1 bg-slate-100 text-slate-800 rounded-full text-xs font-medium">Inactive</span>
            </td>
            <td class="px-6 py-4 text-sm">
              <button v-if="!schedule.is_active" @click="activateSchedule(schedule.id)" class="text-blue-600 hover:text-blue-800 mr-3">Activate</button>
              <button @click="editSchedule(schedule)" class="text-slate-600 hover:text-slate-800 mr-3">Edit</button>
              <button @click="configurePeriods(schedule)" class="text-green-600 hover:text-green-800 mr-3">Periods</button>
              <button @click="deleteSchedule(schedule.id)" class="text-rose-600 hover:text-rose-800">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Schedule Form Modal -->
    <Modal v-if="showScheduleForm" @close="showScheduleForm = false" :title="scheduleForm.id ? 'Edit Schedule' : 'Add Schedule'">
      <form @submit.prevent="saveSchedule" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Schedule Name</label>
          <input v-model="scheduleForm.schedule_name" type="text" class="input w-full" placeholder="e.g., Standard Weekly Schedule" required>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Schedule Type</label>
            <select v-model="scheduleForm.schedule_type" class="input w-full" required>
              <option value="weekly">Weekly Schedule</option>
              <option value="fortnightly">Fortnightly Schedule</option>
              <option value="custom">Custom Cycle</option>
              <option value="rotation">Day Rotation</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Day Pattern</label>
            <select v-model="scheduleForm.day_pattern" class="input w-full" required>
              <option value="uniform">Uniform Schedule (all days same)</option>
              <option value="custom">Custom Day Schedule</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="scheduleForm.is_active" type="checkbox" id="setActive" class="rounded border-slate-300">
          <label for="setActive" class="text-sm text-slate-700">Set as active schedule</label>
        </div>
        <div class="flex justify-end gap-3 pt-4">
          <button type="button" @click="showScheduleForm = false" class="btn-secondary">Cancel</button>
          <button type="submit" class="btn-primary">Save Schedule</button>
        </div>
      </form>
    </Modal>
    
    <!-- Period Configuration Modal -->
    <Modal v-if="showPeriodForm" @close="showPeriodForm = false" :title="'Configure Periods - ' + selectedSchedule?.schedule_name" size="large">
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-medium text-slate-900">Period Configuration</h3>
          <div class="flex gap-2">
            <button @click="copyToAllDays" class="btn-secondary text-sm">Copy Monday to All Days</button>
            <button @click="savePeriods" class="btn-primary text-sm">Save Periods</button>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50">
                <th class="px-4 py-2 text-left border">Period</th>
                <th class="px-4 py-2 text-left border">Name</th>
                <th class="px-4 py-2 text-left border">Type</th>
                <th v-for="day in days" :key="day" class="px-4 py-2 text-left border">{{ day }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="period in periodNumbers" :key="period">
                <td class="px-4 py-2 border font-medium">P{{ period }}</td>
                <td class="px-4 py-2 border">
                  <input v-model="periodNames[period]" type="text" class="input w-32 text-sm" placeholder="Period {{ period }}">
                </td>
                <td class="px-4 py-2 border">
                  <select v-model="periodTypes[period]" class="input w-32 text-sm">
                    <option value="lesson">Lesson</option>
                    <option value="devotion">Devotion</option>
                    <option value="breakfast">Breakfast</option>
                    <option value="break">Break</option>
                    <option value="lunch">Lunch</option>
                    <option value="mentorship">Mentorship</option>
                    <option value="games">Games</option>
                    <option value="prep">Prep</option>
                    <option value="supper">Supper</option>
                    <option value="assembly">Assembly</option>
                    <option value="other">Other</option>
                  </select>
                </td>
                <td v-for="day in days" :key="day" class="px-4 py-2 border">
                  <div class="flex gap-1">
                    <input v-model="periodTimes[`${day}-${period}-start`]" type="time" class="input w-24 text-sm">
                    <input v-model="periodTimes[`${day}-${period}-end`]" type="time" class="input w-24 text-sm">
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { bellSchedulesAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import Modal from '../hr/Modal.vue';

const { showToast } = useToast();

const schedules = ref([]);
const showScheduleForm = ref(false);
const showPeriodForm = ref(false);
const selectedSchedule = ref(null);

const scheduleForm = ref({
  id: null,
  schedule_name: '',
  schedule_type: 'weekly',
  day_pattern: 'uniform',
  is_active: false
});

const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const periodNumbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
const periodNames = ref({});
const periodTypes = ref({});
const periodTimes = ref({});

const loadSchedules = async () => {
  try {
    const res = await bellSchedulesAPI.getAll();
    if (res.success) {
      schedules.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading schedules:', error);
    showToast('Failed to load schedules', 'error');
  }
};

const saveSchedule = async () => {
  try {
    if (scheduleForm.value.id) {
      await bellSchedulesAPI.update(scheduleForm.value);
      showToast('Schedule updated');
    } else {
      await bellSchedulesAPI.create(scheduleForm.value);
      showToast('Schedule created');
    }
    showScheduleForm.value = false;
    loadSchedules();
  } catch (error) {
    console.error('Error saving schedule:', error);
    showToast('Failed to save schedule', 'error');
  }
};

const editSchedule = (schedule) => {
  scheduleForm.value = { ...schedule };
  showScheduleForm.value = true;
};

const activateSchedule = async (id) => {
  try {
    const schedule = schedules.value.find(s => s.id === id);
    await bellSchedulesAPI.update({ ...schedule, is_active: true });
    showToast('Schedule activated');
    loadSchedules();
  } catch (error) {
    console.error('Error activating schedule:', error);
    showToast('Failed to activate schedule', 'error');
  }
};

const deleteSchedule = async (id) => {
  if (!confirm('Are you sure you want to delete this schedule?')) return;
  
  try {
    await bellSchedulesAPI.delete(id);
    showToast('Schedule deleted');
    loadSchedules();
  } catch (error) {
    console.error('Error deleting schedule:', error);
    showToast('Failed to delete schedule', 'error');
  }
};

const configurePeriods = async (schedule) => {
  selectedSchedule.value = schedule;
  
  try {
    const res = await bellSchedulesAPI.getPeriods(schedule.id);
    if (res.success && res.data) {
      // Initialize period data
      periodNames.value = {};
      periodTypes.value = {};
      periodTimes.value = {};
      
      res.data.forEach(p => {
        const key = `${p.day_of_week}-${p.period_number}`;
        periodNames.value[p.period_number] = p.period_name || `Period ${p.period_number}`;
        periodTypes.value[p.period_number] = p.period_type || 'lesson';
        periodTimes.value[`${key}-start`] = p.start_time;
        periodTimes.value[`${key}-end`] = p.end_time;
      });
    }
  } catch (error) {
    console.error('Error loading periods:', error);
  }
  
  showPeriodForm.value = true;
};

const copyToAllDays = () => {
  const mondayStart = periodTimes.value['Monday-1-start'];
  const mondayEnd = periodTimes.value['Monday-1-end'];
  
  days.forEach(day => {
    if (day !== 'Monday') {
      periodNumbers.forEach(period => {
        const sourceKey = `Monday-${period}`;
        const targetKey = `${day}-${period}`;
        periodTimes.value[`${targetKey}-start`] = periodTimes.value[`${sourceKey}-start`];
        periodTimes.value[`${targetKey}-end`] = periodTimes.value[`${sourceKey}-end`];
      });
    }
  });
  
  showToast('Monday schedule copied to all days');
};

const savePeriods = async () => {
  const periods = [];
  
  days.forEach(day => {
    periodNumbers.forEach(period => {
      const start = periodTimes.value[`${day}-${period}-start`];
      const end = periodTimes.value[`${day}-${period}-end`];
      
      if (start && end) {
        periods.push({
          bell_schedule_id: selectedSchedule.value.id,
          day_of_week: day,
          period_number: period,
          period_name: periodNames.value[period] || `Period ${period}`,
          start_time: start,
          end_time: end,
          period_type: periodTypes.value[period] || 'lesson',
          is_active: true
        });
      }
    });
  });
  
  try {
    await bellSchedulesAPI.bulkUpdatePeriods({
      bell_schedule_id: selectedSchedule.value.id,
      periods
    });
    showToast('Periods saved successfully');
    showPeriodForm.value = false;
    loadSchedules();
  } catch (error) {
    console.error('Error saving periods:', error);
    showToast('Failed to save periods', 'error');
  }
};

onMounted(() => {
  loadSchedules();
});
</script>
