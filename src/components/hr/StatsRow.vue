<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div v-for="item in items" :key="item.label" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <p class="text-sm text-slate-500">{{ item.label }}</p>
      <p class="mt-2 text-2xl font-bold" :class="item.color || 'text-slate-900'">{{ item.value }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({ items: { type: Array, default: () => [] } });
</script>

