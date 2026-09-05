<template>
  <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
      <input
        :value="search"
        type="text"
        placeholder="Search..."
        class="input flex-1"
        @input="$emit('update:search', $event.target.value); $emit('filter')"
      />
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({ search: String });
defineEmits(['update:search', 'filter']);
</script>

