<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-10">
    <div class="w-full max-w-[28rem]">
      <div class="mb-6 flex items-center justify-center gap-2.5">
        <SkoolLogo :size="28" />
      </div>

      <div class="rounded-[1.75rem] border border-gray-200 bg-white p-7 shadow-[0_20px_50px_-20px_rgba(15,40,25,0.25)] sm:p-9">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
          <KeyRound class="h-5 w-5" />
        </span>
        <h1 class="mt-5 text-[1.6rem] font-bold tracking-tight text-gray-900">{{ forced ? 'Choose your own password' : 'Change password' }}</h1>
        <p class="mt-2 text-sm text-gray-500">
          <template v-if="forced">Hi {{ firstName }}, you signed in with a temporary password. Replace it with one only you know.</template>
          <template v-else>Use at least 8 characters with letters and numbers.</template>
        </p>

        <form class="mt-7 space-y-5" @submit.prevent="save">
          <div v-for="f in fields" :key="f.key">
            <label :for="`cp-${f.key}`" class="mb-1.5 block text-sm font-medium text-gray-800">{{ f.label }}</label>
            <input
              :id="`cp-${f.key}`"
              v-model="form[f.key]"
              :type="show ? 'text' : 'password'"
              :autocomplete="f.auto"
              required
              class="input"
              :class="errors[f.key] ? 'border-red-400' : ''"
            >
            <p v-if="errors[f.key]" class="mt-1 text-xs text-red-600">{{ errors[f.key] }}</p>
          </div>
          <label class="flex items-center gap-2 text-sm text-gray-600">
            <input v-model="show" type="checkbox" class="h-4 w-4 rounded border-gray-300 accent-brand-600"> Show passwords
          </label>

          <ul class="space-y-1 text-xs">
            <li v-for="r in rules" :key="r.text" class="flex items-center gap-1.5" :class="r.ok ? 'text-brand-700' : 'text-gray-500'">
              <Check v-if="r.ok" class="h-3.5 w-3.5" /><Circle v-else class="h-3.5 w-3.5" />{{ r.text }}
            </li>
          </ul>

          <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ error }}</div>

          <button type="submit" class="btn-primary w-full justify-center py-3" :disabled="saving || !valid">
            {{ saving ? 'Saving…' : 'Save password and continue' }}
          </button>
          <div class="flex justify-between text-sm">
            <button v-if="!forced" type="button" class="link-btn" @click="router.back()">Cancel</button>
            <button type="button" class="ml-auto font-medium text-gray-500 hover:text-red-600" @click="signOut">Sign out</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import SkoolLogo from '../components/SkoolLogo.vue';
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Check, Circle, KeyRound } from 'lucide-vue-next';
import { authAPI } from '../services/api.js';
import authStore from '../services/authStore.js';
import { apiError } from '../utils/timetable.js';

const router = useRouter();
const forced = computed(() => !!authStore.user?.must_change_password);
const firstName = computed(() => (authStore.user?.full_name || '').split(/\s+/)[0] || 'there');
const form = reactive({ current: '', next: '', confirm: '' });
const errors = ref({});
const error = ref('');
const saving = ref(false);
const show = ref(false);

const fields = computed(() => [
  { key: 'current', label: forced.value ? 'Temporary password' : 'Current password', auto: 'current-password' },
  { key: 'next', label: 'New password', auto: 'new-password' },
  { key: 'confirm', label: 'Type the new password again', auto: 'new-password' },
]);
const rules = computed(() => [
  { text: 'At least 8 characters', ok: form.next.length >= 8 },
  { text: 'Letters and numbers', ok: /[A-Za-z]/.test(form.next) && /\d/.test(form.next) },
  { text: 'Both new passwords match', ok: !!form.next && form.next === form.confirm },
]);
const valid = computed(() => form.current && rules.value.every((r) => r.ok));

async function save() {
  error.value = '';
  errors.value = {};
  saving.value = true;
  try {
    await authAPI.changePassword(form.current, form.next);
    authStore.updateUser({ must_change_password: false });
    router.replace(authStore.getHomeRoute());
  } catch (e) {
    const err = apiError(e);
    errors.value = { current: err.errors.current_password, next: err.errors.new_password };
    error.value = err.message;
  } finally {
    saving.value = false;
  }
}

function signOut() {
  authStore.clearAuth();
  router.push('/login');
}
</script>
