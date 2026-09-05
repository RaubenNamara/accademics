<template>
  <div class="rounded-xl border border-slate-200 bg-white p-6">
    <h3 class="mb-4 text-lg font-semibold text-slate-900">Setup Progress</h3>
    
    <div class="space-y-4">
      <div v-for="item in progressItems" :key="item.id" class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div
            class="flex h-8 w-8 items-center justify-center rounded-full"
            :class="item.status === 'complete' ? 'bg-green-100 text-green-600' : item.status === 'partial' ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-400'"
          >
            <svg v-if="item.status === 'complete'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else-if="item.status === 'partial'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-medium text-slate-900">{{ item.label }}</p>
            <p class="text-xs text-slate-500">{{ item.description }}</p>
          </div>
        </div>
        <span
          class="text-xs font-medium px-2 py-1 rounded-full"
          :class="item.status === 'complete' ? 'bg-green-100 text-green-700' : item.status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
        >
          {{ item.status === 'complete' ? 'Complete' : item.status === 'partial' ? 'Partial' : 'Pending' }}
        </span>
      </div>
    </div>
    
    <div class="mt-6 pt-4 border-t border-slate-200">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-slate-900">Overall Progress</span>
        <span class="text-sm font-semibold text-blue-600">{{ overallProgress }}%</span>
      </div>
      <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
        <div
          class="h-full bg-blue-600 transition-all duration-300"
          :style="{ width: overallProgress + '%' }"
        ></div>
      </div>
    </div>
    
    <div v-if="overallProgress === 100" class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
      <p class="text-sm font-medium text-green-800">✓ All setup items complete. Ready to generate timetable!</p>
    </div>
    <div v-else class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
      <p class="text-sm font-medium text-amber-800">⚠ Complete all required items before generating timetable.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  progressItems: {
    type: Array,
    required: true,
    default: () => []
  }
});

const overallProgress = computed(() => {
  if (!props.progressItems.length) return 0;
  const complete = props.progressItems.filter(item => item.status === 'complete').length;
  return Math.round((complete / props.progressItems.length) * 100);
});
</script>
