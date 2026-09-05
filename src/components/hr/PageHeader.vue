<template>
  <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-blue-900 p-5 text-white shadow-xl">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-xs uppercase tracking-[0.2em] text-blue-200">HR Module</p>
        <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 text-sm text-slate-300">{{ subtitle }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <slot name="actions" />
        <button type="button" class="btn-glass" @click="$emit('refresh')">Refresh</button>
        <button type="button" class="btn-accent" @click="$emit('add')">Add New</button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({ title: String, subtitle: String });
defineEmits(['refresh', 'add']);
</script>

<style scoped>
.btn-glass {
  @apply rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-medium backdrop-blur hover:bg-white/20;
}
.btn-accent {
  @apply rounded-xl bg-blue-500 px-4 py-2 text-sm font-medium shadow-lg hover:bg-blue-600;
}
</style>

