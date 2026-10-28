<template>
  <Teleport to="body">
    <Transition name="alert-fade">
      <div v-if="dialogState.open" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm" @click.self="closeAlert">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" role="alertdialog" aria-modal="true" aria-labelledby="alert-title" aria-describedby="alert-text">
          <div class="flex items-start gap-4 px-6 pb-4 pt-6">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ring-1" :class="look.icon"><component :is="look.Icon" class="h-5 w-5" /></span>
            <div class="min-w-0 pt-0.5">
              <h2 id="alert-title" class="text-base font-semibold text-gray-900">{{ dialogState.title }}</h2>
              <p id="alert-text" class="mt-1 whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ dialogState.message }}</p>
            </div>
          </div>
          <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-6 py-3">
            <button v-if="dialogState.action && canOpen" type="button" class="btn-secondary px-3.5 py-1.5 text-sm" @click="go">{{ dialogState.action.label }}</button>
            <button ref="okBtn" type="button" class="btn-primary px-5 py-1.5 text-sm" @click="closeAlert">OK</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { CircleAlert, CircleCheck, Info, Lock } from 'lucide-vue-next';
import { closeAlert, dialogState } from '../../services/dialog.js';
import authStore from '../../services/authStore.js';
import { canAccessRoute } from '../../config/accessControl.js';

const router = useRouter();
const okBtn = ref(null);
const LOOKS = {
  locked: { Icon: Lock, icon: 'bg-amber-50 text-amber-700 ring-amber-100' },
  error: { Icon: CircleAlert, icon: 'bg-red-50 text-red-600 ring-red-100' },
  success: { Icon: CircleCheck, icon: 'bg-emerald-50 text-emerald-600 ring-emerald-100' },
  info: { Icon: Info, icon: 'bg-sky-50 text-sky-700 ring-sky-100' },
};
const look = computed(() => LOOKS[dialogState.tone] || LOOKS.info);
const canOpen = computed(() => dialogState.action?.to && canAccessRoute(dialogState.action.to, authStore.user?.role, authStore.user));
const go = () => {
  const to = dialogState.action.to;
  closeAlert();
  router.push(to);
};
watch(() => dialogState.open, async (open) => { if (open) { await nextTick(); okBtn.value?.focus(); } });
const onKey = (e) => { if (dialogState.open && (e.key === 'Escape' || e.key === 'Enter')) { e.preventDefault(); closeAlert(); } };
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<style scoped>
.alert-fade-enter-active, .alert-fade-leave-active { transition: opacity 0.15s ease; }
.alert-fade-enter-from, .alert-fade-leave-to { opacity: 0; }
</style>
