<template>
  <div v-if="total > perPage" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
    <p class="text-sm text-slate-500">Page {{ page }} of {{ totalPages }}</p>
    <div class="flex gap-2">
      <button type="button" class="page-btn" :disabled="page <= 1" @click="$emit('update:page', page - 1)">Prev</button>
      <button type="button" class="page-btn" :disabled="page >= totalPages" @click="$emit('update:page', page + 1)">Next</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ page: Number, total: Number, perPage: { type: Number, default: 10 } });
defineEmits(['update:page']);
const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));
</script>

<style scoped>
.page-btn {
  @apply rounded-lg border border-slate-200 px-3 py-1.5 text-sm disabled:opacity-40;
}
</style>

