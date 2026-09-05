<template>
  <transition name="fade">
    <div
      v-if="toast.show"
      class="fixed right-4 top-4 z-[100] min-w-[280px] rounded-2xl border px-4 py-3 shadow-2xl"
      :class="toast.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
    >
      <p class="text-sm font-semibold">{{ toast.type === 'success' ? 'Success' : 'Error' }}</p>
      <p class="text-sm">{{ toast.message }}</p>
    </div>
  </transition>
</template>

<script setup>
import { useToast } from '../../composables/useToast.js';
const { toast } = useToast();
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>

