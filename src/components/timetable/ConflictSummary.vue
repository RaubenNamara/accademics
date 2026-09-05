<template>
  <div class="rounded-xl border bg-white p-6" :class="hasConflicts ? 'border-red-200' : 'border-green-200'">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold" :class="hasConflicts ? 'text-red-900' : 'text-green-900'">
        {{ hasConflicts ? '⚠ Conflicts Detected' : '✓ No Conflicts' }}
      </h3>
      <button
        v-if="hasConflicts"
        @click="$emit('refresh')"
        class="text-sm text-blue-600 hover:text-blue-800 font-medium"
      >
        Refresh
      </button>
    </div>
    
    <div v-if="hasConflicts" class="space-y-3">
      <div v-for="(conflictList, type) in conflicts" :key="type" v-show="conflictList.length > 0">
        <h4 class="text-sm font-medium text-slate-900 mb-2 capitalize">
          {{ formatConflictType(type) }} ({{ conflictList.length }})
        </h4>
        <div class="space-y-2">
          <div
            v-for="(conflict, index) in conflictList.slice(0, 5)"
            :key="index"
            class="p-3 bg-red-50 border border-red-100 rounded-lg text-sm"
          >
            <p class="font-medium text-red-900">{{ formatConflict(conflict, type) }}</p>
            <p class="text-red-700 mt-1">{{ conflict.day_of_week }} - Period {{ conflict.period_number }}</p>
          </div>
          <div v-if="conflictList.length > 5" class="text-sm text-slate-600 text-center py-2">
            +{{ conflictList.length - 5 }} more conflicts
          </div>
        </div>
      </div>
    </div>
    
    <div v-else class="text-center py-4">
      <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="mt-2 text-sm font-medium text-green-800">No conflicts found in the timetable</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  conflicts: {
    type: Object,
    required: true,
    default: () => ({})
  }
});

defineEmits(['refresh']);

const hasConflicts = computed(() => {
  return Object.values(props.conflicts).some(list => list && list.length > 0);
});

const formatConflictType = (type) => {
  const types = {
    teacher_conflicts: 'Teacher Conflicts',
    class_conflicts: 'Class Conflicts',
    room_conflicts: 'Room Conflicts',
    missing_lessons: 'Missing Lessons',
    teacher_overload: 'Teacher Overload'
  };
  return types[type] || type;
};

const formatConflict = (conflict, type) => {
  switch (type) {
    case 'teacher_conflicts':
      return `${conflict.teacher_name} (${conflict.teacher_code}) is double-booked`;
    case 'class_conflicts':
      return `${conflict.class_name} has multiple lessons scheduled`;
    case 'room_conflicts':
      return `${conflict.room_code} (${conflict.room_name}) is double-booked`;
    case 'missing_lessons':
      return `${conflict.class_name} - ${conflict.subject_name}: ${conflict.scheduled_periods}/${conflict.required_periods} periods scheduled`;
    case 'teacher_overload':
      return `${conflict.teacher_name} has ${conflict.lessons_count} lessons on ${conflict.day_of_week}`;
    default:
      return 'Conflict detected';
  }
};
</script>
