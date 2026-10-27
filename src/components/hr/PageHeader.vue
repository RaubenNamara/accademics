<template>
  <div class="page-hero">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="page-hero-title">{{ title }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <slot name="actions" />
        <button type="button" class="btn-hero" @click="$emit('refresh')">Refresh</button>
        <button type="button" class="btn-hero-primary" @click="$emit('add')">Add New</button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({ title: String, subtitle: String, eyebrow: { type: String, default: 'HR Module' } });
defineEmits(['refresh', 'add']);
</script>
