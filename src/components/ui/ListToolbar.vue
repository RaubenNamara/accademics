<template>
  <div class="card p-2.5 sm:p-3">
    <div class="flex flex-wrap items-center gap-2">
      <slot name="start" />
      <div v-if="searchable" class="relative min-w-[12rem] flex-1">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          :value="search"
          type="search"
          class="input py-2 !pl-9"
          :placeholder="placeholder"
          :aria-label="placeholder"
          @input="$emit('update:search', $event.target.value)"
        />
      </div>
      <button
        v-if="$slots.filters"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition"
        :class="open || filterCount ? 'border-brand-300 bg-brand-50 text-brand-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
        :aria-expanded="open"
        @click="open = !open"
      >
        <SlidersHorizontal class="h-4 w-4" />Filters
        <span v-if="filterCount" class="rounded-full bg-brand-600 px-1.5 text-xs text-white">{{ filterCount }}</span>
      </button>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-1">
        <slot name="actions" />
      </div>
    </div>
    <div v-if="$slots.filters && open" class="mt-3 border-t border-gray-100 pt-3">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
        <slot name="filters" />
      </div>
      <div v-if="filterCount" class="mt-3 flex justify-end">
        <button type="button" class="link-btn" @click="$emit('clear')">Clear all filters</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Search, SlidersHorizontal } from 'lucide-vue-next';

/**
 * The Teaching Staff toolbar: search, a Filters button that opens the detailed filters
 * (#filters slot, shows a count badge), and compact actions (#actions slot, e.g. exports).
 */
const props = defineProps({
  search: { type: String, default: '' },
  searchable: { type: Boolean, default: true },
  placeholder: { type: String, default: 'Search…' },
  filterCount: { type: Number, default: 0 },
  filtersOpen: { type: Boolean, default: false },
});
defineEmits(['update:search', 'clear']);
const open = ref(props.filtersOpen);
</script>
