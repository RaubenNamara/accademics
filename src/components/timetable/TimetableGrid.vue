<template>
  <div class="overflow-x-auto">
    <table class="w-full border-collapse text-sm">
      <thead>
        <tr>
          <th class="border border-slate-200 bg-slate-50 p-2 text-left font-medium">Period</th>
          <th v-for="day in days" :key="day" class="border border-slate-200 bg-slate-50 p-2 text-center font-medium">
            {{ day }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="period in periods" :key="period">
          <td class="border border-slate-200 bg-slate-50 p-2 font-medium text-center">
            P{{ period }}
          </td>
          <td
            v-for="day in days"
            :key="day + period"
            class="border border-slate-200 p-1 align-top min-h-[72px] bg-white"
            :class="{ 'cursor-pointer': editable }"
            @click="editable ? $emit('cell-click', { day, period }) : null"
          >
            <div
              v-for="slot in getCellSlots(day, period)"
              :key="slot.id"
              class="mb-1 rounded-lg p-2 text-xs shadow-sm ring-1"
              :class="getSlotClass(slot)"
            >
              <p class="font-semibold" :class="getSlotTextClass(slot)">
                {{ getSlotDisplay(slot) }}
              </p>
              <p v-if="slot.entry_type === 'lesson'" class="text-slate-600 mt-1">
                {{ slot.class_name }} · {{ slot.teacher_name }}
              </p>
              <p v-if="slot.entry_type === 'event' && slot.class_name" class="text-white/90 mt-1">
                {{ slot.class_name }}
              </p>
              <p v-if="slot.stream" class="text-slate-400 mt-1">Stream {{ slot.stream }}</p>
              <button
                v-if="editable && slot.entry_type === 'lesson'"
                type="button"
                class="mt-1 text-rose-600 hover:text-rose-800 text-xs"
                @click.stop="$emit('remove', slot.id)"
              >
                Remove
              </button>
            </div>
            <button
              v-if="editable && !getCellSlots(day, period).length"
              type="button"
              class="w-full rounded-lg border border-dashed border-slate-200 py-3 text-xs text-slate-400 hover:border-blue-300 hover:text-blue-600"
              @click.stop="$emit('add', { day, period })"
            >
              + Add
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  entries: {
    type: Array,
    required: true,
    default: () => []
  },
  days: {
    type: Array,
    default: () => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
  },
  periods: {
    type: Array,
    default: () => [1, 2, 3, 4, 5, 6, 7, 8]
  },
  editable: {
    type: Boolean,
    default: true
  }
});

defineEmits(['cell-click', 'add', 'remove']);

const getCellSlots = (day, period) => {
  return props.entries.filter(
    e => e.day_of_week === day && parseInt(e.period_number) === period
  );
};

const getSlotClass = (slot) => {
  if (slot.entry_type === 'event') {
    return 'text-white';
  }
  return 'bg-blue-50 ring-blue-100';
};

const getSlotTextClass = (slot) => {
  if (slot.entry_type === 'event') {
    return 'text-white';
  }
  return 'text-blue-900';
};

const getSlotDisplay = (slot) => {
  if (slot.entry_type === 'event') {
    return slot.event_display_name || slot.event_name;
  }
  return slot.subject_name;
};
</script>
