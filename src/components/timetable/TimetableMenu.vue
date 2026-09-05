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
  { id: 'setup-wizard', label: 'Setup Wizard' },
  { id: 'sessions', label: 'Academic Sessions' },
  { id: 'bell-schedule', label: 'Bell Schedule' },
  { id: 'classes', label: 'Classes & Streams' },
  { id: 'subjects', label: 'Subjects' },
  { id: 'teachers', label: 'Teachers' },
  { id: 'rooms', label: 'Rooms' },
  { id: 'school-events', label: 'School Events' },
  { id: 'lesson-requirements', label: 'Lesson Requirements' },
  { id: 'teacher-availability', label: 'Teacher Availability' },
  { id: 'constraints', label: 'Constraints & Rules' },
  { id: 'generate', label: 'Generate Timetable' },
  { id: 'conflicts', label: 'Conflict Checker' },
  { id: 'class-timetable', label: 'Class Timetables' },
  { id: 'teacher-timetable', label: 'Teacher Timetables' },
  { id: 'room-timetable', label: 'Room Timetables' },
  { id: 'master-timetable', label: 'Master Timetable' },
  { id: 'analytics', label: 'Analytics' },
  { id: 'pdf-exports', label: 'PDF Exports' },
  { id: 'settings', label: 'Settings' }
];
</script>
