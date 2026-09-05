<template>
  <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
    <div v-if="loading" class="p-12 text-center text-slate-500">Loading...</div>
    <slot v-else />
  </div>
</template>

<script setup>
defineProps({ loading: Boolean });
</script>

