<template>
  <div class="card overflow-x-auto">
    <div v-if="loading" class="p-12 text-center text-slate-500">Loading...</div>
    <slot v-else />
  </div>
</template>

<script setup>
defineProps({ loading: Boolean });
</script>

