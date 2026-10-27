<template>
  <div class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 p-0 backdrop-blur-sm sm:items-center sm:p-4" @keydown.esc="$emit('close')">
    <div class="flex max-h-[100dvh] w-full max-w-xl flex-col overflow-hidden bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="leave-dialog-title">
      <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 ring-1 ring-brand-100"><PlaneTakeoff class="h-5 w-5" /></span>
        <div class="min-w-0 flex-1">
          <h3 id="leave-dialog-title" class="text-lg font-semibold text-gray-900">{{ title }}</h3>
          <p class="text-sm text-gray-500">{{ subtitle }}</p>
        </div>
        <button type="button" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="$emit('close')"><X class="h-5 w-5" /></button>
      </header>

      <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5">
          <!-- Staff member (HR only) -->
          <section v-if="employees">
            <span class="lv-label">Staff member</span>
            <div v-if="chosen" class="flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50/50 px-3 py-2.5">
              <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(`${chosen.first_name} ${chosen.last_name}`) }}</span>
              <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-gray-900">{{ chosen.first_name }} {{ chosen.last_name }}</p>
                <p class="font-mono text-[11px] text-gray-500">{{ chosen.hr_code }}</p>
              </div>
              <button type="button" class="text-sm font-medium text-brand-700 hover:underline" @click="form.employee_id = ''; search = ''">Change</button>
            </div>
            <div v-else class="rounded-xl border border-gray-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100" :class="errors.employee_id ? 'border-red-400' : ''">
              <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input v-model="search" type="text" class="w-full rounded-t-xl border-0 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none" placeholder="Type a name or staff code" aria-label="Search staff" />
              </div>
              <ul class="max-h-40 overflow-y-auto border-t border-gray-100 py-1" role="listbox">
                <li v-for="e in matches.slice(0, 50)" :key="e.id">
                  <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-brand-50" role="option" @click="form.employee_id = e.id">
                    <span class="truncate text-gray-900">{{ e.first_name }} {{ e.last_name }}</span>
                    <span class="ml-auto font-mono text-[11px] text-gray-500">{{ e.hr_code }}</span>
                  </button>
                </li>
                <li v-if="!matches.length" class="px-3 py-2 text-sm text-gray-500">No staff member matches "{{ search }}"</li>
              </ul>
            </div>
            <span v-if="errors.employee_id" class="lv-error">{{ errors.employee_id }}</span>
          </section>

          <!-- Type -->
          <section>
            <span class="lv-label">Type of leave</span>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <button
                v-for="t in types"
                :key="t"
                type="button"
                class="flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 text-xs font-medium transition"
                :class="form.leave_type === t ? 'border-brand-500 bg-brand-50/60 text-gray-900 ring-2 ring-brand-100' : 'border-gray-200 text-gray-700 hover:border-gray-300 hover:bg-gray-50'"
                :aria-pressed="form.leave_type === t"
                @click="form.leave_type = t"
              >
                <span class="flex h-8 w-8 items-center justify-center rounded-lg ring-1" :class="leaveType(t).tile"><component :is="leaveType(t).icon" class="h-4 w-4" /></span>
                {{ leaveType(t).label }}
              </button>
            </div>
            <span v-if="errors.leave_type" class="lv-error">{{ errors.leave_type }}</span>
          </section>

          <!-- Dates -->
          <section>
            <div class="grid grid-cols-2 gap-3">
              <label class="block">
                <span class="lv-label">First day</span>
                <input v-model="form.start_date" type="date" class="lv-input" required />
              </label>
              <label class="block">
                <span class="lv-label">Last day</span>
                <input v-model="form.end_date" type="date" class="lv-input" :min="form.start_date" required />
              </label>
            </div>
            <p v-if="errors.start_date || errors.end_date" class="lv-error">{{ errors.start_date || errors.end_date }}</p>
            <p v-else-if="days" class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
              <CalendarDays class="h-3.5 w-3.5" />{{ days }} day{{ days === 1 ? '' : 's' }}, {{ longDate(form.start_date) }}<template v-if="days > 1"> to {{ longDate(form.end_date) }}</template>
            </p>
            <p v-else-if="form.end_date && form.start_date && form.end_date < form.start_date" class="lv-error">The last day is before the first day.</p>
          </section>

          <label class="block">
            <span class="lv-label">Reason</span>
            <textarea v-model="form.reason" rows="3" maxlength="1000" class="lv-input resize-none" placeholder="e.g. Medical appointment, family event" :required="reasonRequired"></textarea>
            <span v-if="errors.reason" class="lv-error">{{ errors.reason }}</span>
          </label>

          <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
        </div>

        <footer class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
          <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="$emit('close')">Cancel</button>
          <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="busy || !days || (employees && !form.employee_id)">
            <Send class="h-4 w-4" />{{ busy ? 'Sending…' : submitLabel }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { CalendarDays, PlaneTakeoff, Search, Send, X } from 'lucide-vue-next';
import { dayCount, leaveType, longDate, todayISO } from '../../utils/leaveMeta.js';

const props = defineProps({
  title: { type: String, default: 'Request leave' },
  subtitle: { type: String, default: 'Your request goes to HR for approval.' },
  submitLabel: { type: String, default: 'Send request' },
  types: { type: Array, default: () => ['annual', 'sick', 'maternity', 'paternity', 'compassionate', 'unpaid', 'other'] },
  // Staff to choose from (HR entering leave for someone). Leave out for "my own leave".
  employees: { type: Array, default: null },
  busy: { type: Boolean, default: false },
  errors: { type: Object, default: () => ({}) },
  formError: { type: String, default: '' },
  reasonRequired: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'submit']);

const today = todayISO();
const form = ref({ employee_id: '', leave_type: 'annual', start_date: today, end_date: today, reason: '' });
const search = ref('');
const days = computed(() => dayCount(form.value.start_date, form.value.end_date));
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const chosen = computed(() => props.employees?.find((e) => String(e.id) === String(form.value.employee_id)) || null);
const matches = computed(() => {
  const q = search.value.trim().toLowerCase();
  const list = props.employees || [];
  return q ? list.filter((e) => `${e.first_name} ${e.last_name} ${e.hr_code}`.toLowerCase().includes(q)) : list;
});

const submit = () => {
  const { employee_id: employeeId, ...rest } = form.value;
  emit('submit', props.employees ? { employee_id: employeeId, ...rest } : rest);
};
</script>

<style scoped>
.lv-label { @apply mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.lv-input { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.lv-error { @apply mt-1.5 block text-xs text-red-600; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .lv-label {
  color: var(--d-muted);
}
html.dark .lv-input {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
}
html.dark .lv-error {
  color: #fca5a5;
}
</style>
