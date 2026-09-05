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
          <template v-for="day in days" :key="day + period">
            <td
              v-if="grid[period][day].type !== 'skip'"
              :rowspan="grid[period][day].rowspan || 1"
              class="border border-slate-200 p-1 align-top min-h-[72px] bg-white"
              :class="{ 'cursor-pointer': editable }"
              @click="editable ? $emit('cell-click', { day, period }) : null"
            >
              <div
                v-if="grid[period][day].type === 'slot'"
                class="mb-1 rounded-lg p-2 text-xs shadow-sm ring-1 h-full"
                :class="getSlotClass(grid[period][day].slot)"
              >
                <p class="font-semibold" :class="getSlotTextClass(grid[period][day].slot)">
                  {{ getSlotDisplay(grid[period][day].slot) }}
                </p>
                <p v-if="grid[period][day].slot.entry_type === 'lesson'" class="text-slate-600 mt-1">
                  {{ grid[period][day].slot.class_name }} · {{ grid[period][day].slot.teacher_name }}
                </p>
                <p v-if="grid[period][day].rowspan > 1" class="text-slate-400 mt-1">
                  {{ spanLabel(grid[period][day].rowspan) }} · {{ grid[period][day].slot.duration_minutes || grid[period][day].rowspan * 40 }} min
                </p>
                <p v-if="grid[period][day].slot.stream" class="text-slate-400 mt-1">Stream {{ grid[period][day].slot.stream }}</p>
                <button
                  v-if="editable && grid[period][day].slot.entry_type === 'lesson'"
                  type="button"
                  class="mt-1 text-rose-600 hover:text-rose-800 text-xs"
                  @click.stop="$emit('remove', grid[period][day].slot.id)"
                >
                  Remove
                </button>
              </div>
              <button
                v-else-if="editable"
                type="button"
                class="w-full rounded-lg border border-dashed border-slate-200 py-3 text-xs text-slate-400 hover:border-blue-300 hover:text-blue-600"
                @click.stop="$emit('add', { day, period })"
              >
                + Add
              </button>
            </td>
          </template>
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

const spanLabel = (span) => ({ 1: 'Single', 2: 'Double', 3: 'Triple', 4: 'Quadruple' }[span] || `${span}-period`);

// Builds a period x day matrix: a cell is either the start of a slot (carries
// a rowspan covering its spans_periods), 'skip' (covered by an earlier
// multi-period slot's rowspan and must render no <td> at all), or empty.
const grid = computed(() => {
  const matrix = {};
  props.periods.forEach(p => {
    matrix[p] = {};
    props.days.forEach(d => { matrix[p][d] = { type: 'empty' }; });
  });

  const coveredUntil = {};
  props.days.forEach(d => { coveredUntil[d] = 0; });

  const sortedPeriods = [...props.periods].sort((a, b) => a - b);

  for (const period of sortedPeriods) {
    for (const day of props.days) {
      if (period <= coveredUntil[day]) {
        matrix[period][day] = { type: 'skip' };
        continue;
      }

      const slot = props.entries.find(
        e => e.day_of_week === day && parseInt(e.period_number) === period
      );

      if (slot) {
        const span = Math.max(1, parseInt(slot.spans_periods) || 1);
        coveredUntil[day] = period + span - 1;
        matrix[period][day] = { type: 'slot', slot, rowspan: span };
      } else {
        matrix[period][day] = { type: 'empty' };
      }
    }
  }

  return matrix;
});

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
