<template>
  <div class="space-y-6">
    <h2 class="text-xl font-semibold text-slate-900">Timetable Settings</h2>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- General Settings -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">General Settings</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Default Period Duration (minutes)</label>
            <input v-model.number="settings.defaultPeriodDuration" type="number" class="input w-full" min="10" max="90">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">School Days</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="day in allDays" :key="day" class="flex items-center gap-2">
                <input v-model="settings.schoolDays" type="checkbox" :value="day" class="rounded border-slate-300">
                {{ day }}
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Time Format</label>
            <select v-model="settings.timeFormat" class="input w-full">
              <option value="24h">24-hour (14:30)</option>
              <option value="12h">12-hour (2:30 PM)</option>
            </select>
          </div>
        </div>
      </div>
      
      <!-- Generation Settings -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Generation Settings</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Default Generation Mode</label>
            <select v-model="settings.defaultGenerationMode" class="input w-full">
              <option value="automatic">Automatic</option>
              <option value="semi-automatic">Semi-Automatic</option>
              <option value="manual">Manual</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Default Optimization Mode</label>
            <select v-model="settings.defaultOptimizationMode" class="input w-full">
              <option value="balanced">Balanced</option>
              <option value="fast">Fast</option>
              <option value="max_accuracy">Max Accuracy</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Max Generation Attempts</label>
            <input v-model.number="settings.maxGenerationAttempts" type="number" class="input w-full" min="1" max="100">
          </div>
        </div>
      </div>
      
      <!-- Conflict Settings -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Conflict Detection</h3>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-700">Detect Teacher Conflicts</span>
            <input v-model="settings.detectTeacherConflicts" type="checkbox" class="rounded border-slate-300">
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-700">Detect Class Conflicts</span>
            <input v-model="settings.detectClassConflicts" type="checkbox" class="rounded border-slate-300">
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-700">Detect Room Conflicts</span>
            <input v-model="settings.detectRoomConflicts" type="checkbox" class="rounded border-slate-300">
          </div>
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-700">Detect Missing Lessons</span>
            <input v-model="settings.detectMissingLessons" type="checkbox" class="rounded border-slate-300">
          </div>
        </div>
      </div>
      
      <!-- Display Settings -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Display Settings</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Cell Display Format</label>
            <select v-model="settings.cellDisplayFormat" class="input w-full">
              <option value="subject_code">Subject Code Only</option>
              <option value="subject_teacher">Subject + Teacher</option>
              <option value="subject_teacher_room">Subject + Teacher + Room</option>
              <option value="full">Full Details</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Show Events in Timetable</label>
            <input v-model="settings.showEvents" type="checkbox" class="rounded border-slate-300">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Color Code by Subject</label>
            <input v-model="settings.colorCodeBySubject" type="checkbox" class="rounded border-slate-300">
          </div>
        </div>
      </div>
    </div>
    
    <!-- Save Button -->
    <div class="flex justify-end">
      <button @click="saveSettings" class="btn-primary">Save Settings</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from '../../composables/useToast.js';

const { showToast } = useToast();

const allDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

const settings = ref({
  defaultPeriodDuration: 40,
  schoolDays: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
  timeFormat: '24h',
  defaultGenerationMode: 'automatic',
  defaultOptimizationMode: 'balanced',
  maxGenerationAttempts: 10,
  detectTeacherConflicts: true,
  detectClassConflicts: true,
  detectRoomConflicts: true,
  detectMissingLessons: true,
  cellDisplayFormat: 'subject_teacher',
  showEvents: true,
  colorCodeBySubject: false
});

const loadSettings = () => {
  const saved = localStorage.getItem('timetableSettings');
  if (saved) {
    settings.value = { ...settings.value, ...JSON.parse(saved) };
  }
};

const saveSettings = () => {
  localStorage.setItem('timetableSettings', JSON.stringify(settings.value));
  showToast('Settings saved successfully');
};

onMounted(() => {
  loadSettings();
});
</script>
