<template>
  <div class="border-b border-slate-200 bg-white">
    <div class="flex overflow-x-auto">
      <button
        v-for="item in menuItems"
        :key="item.id"
        @click="$emit('select', item.id)"
        class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
        :class="activeItem === item.id 
          ? 'border-blue-600 text-blue-600 bg-blue-50' 
          : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
      >
        {{ item.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  activeItem: {
    type: String,
    required: true
  }
});

defineEmits(['select']);

const menuItems = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'sessions', label: 'Academic Sessions' },
  { id: 'bell-schedule', label: 'Bell Schedule' },
  { id: 'lesson-requirements', label: 'Lesson Requirements' },
  { id: 'availability', label: 'Teacher Availability' },
  { id: 'generate', label: 'Generate Timetable' },
  { id: 'conflicts', label: 'Conflict Checker' },
  { id: 'class-timetable', label: 'Class Timetables' },
  { id: 'teacher-timetable', label: 'Teacher Timetables' }
];
</script>
