<template>
  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="klass">{{ status }}</span>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ status: String });
const klass = computed(() => {
  const s = props.status || '';
  if (s === 'active' || s === 'approved') return 'bg-emerald-100 text-emerald-800';
  if (s === 'pending') return 'bg-amber-100 text-amber-800';
  if (s === 'on_leave') return 'bg-sky-100 text-sky-800';
  if (s === 'rejected' || s === 'inactive' || s === 'terminated') return 'bg-rose-100 text-rose-800';
  return 'bg-slate-100 text-slate-600';
});
</script>

