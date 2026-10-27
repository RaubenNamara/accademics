<template>
  <div class="space-y-4">
    <div class="card p-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 class="card-title">School day</h2>
          <p class="text-xs text-slate-500">
            The periods every class follows, Monday to Friday. Breaks, lunch and assembly are never used for lessons, and double lessons cannot run across them.
          </p>
        </div>
        <div class="flex shrink-0 gap-2 text-xs">
          <span class="rounded-full bg-brand-100 px-2.5 py-1 font-semibold text-brand-800">{{ lessonCount }} lesson periods a day</span>
          <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">{{ lessonCount * 5 }} a week</span>
        </div>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div v-if="errors.length" class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm text-red-700">
        <p v-for="(e, i) in errors" :key="i"><i class="fas fa-circle-exclamation mr-1"></i>{{ e }}</p>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th class="w-12">#</th><th>Name</th><th>Type</th><th>Starts</th><th>Ends</th><th>Length</th><th v-if="canEdit"></th></tr></thead>
          <tbody>
            <tr v-for="(p, i) in rows" :key="i" :class="p.period_type === 'lesson' ? '' : p.period_type === 'lunch' ? 'bg-amber-50/60' : 'bg-slate-50'">
              <td class="text-xs text-slate-400">{{ i + 1 }}</td>
              <td><input v-model="p.label" class="input py-1.5" :disabled="!canEdit" maxlength="50" :aria-label="`Name of row ${i + 1}`" /></td>
              <td>
                <select v-model="p.period_type" class="input py-1.5" :disabled="!canEdit" :aria-label="`Type of row ${i + 1}`">
                  <option value="lesson">Lesson</option>
                  <option value="break">Break</option>
                  <option value="lunch">Lunch</option>
                  <option value="assembly">Assembly</option>
                </select>
              </td>
              <td><input v-model="p.start_time" type="time" class="input py-1.5" :disabled="!canEdit" :aria-label="`Start of row ${i + 1}`" /></td>
              <td><input v-model="p.end_time" type="time" class="input py-1.5" :disabled="!canEdit" :aria-label="`End of row ${i + 1}`" /></td>
              <td class="whitespace-nowrap text-xs" :class="minutes(p) > 0 ? 'text-slate-500' : 'text-red-600'">{{ minutes(p) > 0 ? `${minutes(p)} min` : '-' }}</td>
              <td v-if="canEdit" class="text-right">
                <button v-if="i === rows.length - 1 && rows.length > 1" type="button" class="text-sm font-medium text-red-600 hover:underline" @click="rows.pop()">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="canEdit" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3">
        <button type="button" class="btn-secondary" @click="addRow"><i class="fas fa-plus"></i>Add period at the end</button>
        <div class="flex gap-2">
          <button type="button" class="btn-secondary" :disabled="!dirty || saving" @click="reset">Undo changes</button>
          <button type="button" class="btn-primary" :disabled="!dirty || saving" @click="save">
            <i v-if="saving" class="fas fa-circle-notch fa-spin"></i>{{ saving ? 'Saving…' : 'Save school day' }}
          </button>
        </div>
      </div>
      <p v-else class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500"><i class="fas fa-lock mr-1"></i>Only administrators can change the school day.</p>
    </div>
    <p class="px-1 text-xs text-slate-500">
      Lessons are tied to their period, so existing periods can be renamed and re-timed but not reordered. New periods go at the end; a period that has lessons cannot be removed or turned into a break.
    </p>

    <div class="card p-5">
      <h3 class="text-sm font-semibold text-slate-900">Teacher daily limit</h3>
      <p class="text-xs text-slate-500">The most periods one teacher may teach in a day. The generator never goes over it and saving a lesson over it is refused.</p>
      <div class="mt-3 flex flex-wrap items-end gap-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600" for="daily-limit">Periods per day</label>
          <input id="daily-limit" v-model.number="dailyLimit" type="number" min="1" max="12" class="input w-32" :placeholder="`No limit (warn above ${defaultLimit})`" :disabled="!canEditRules" />
        </div>
        <button v-if="canEditRules" type="button" class="btn-primary" :disabled="savingRules" @click="saveRules">
          <i v-if="savingRules" class="fas fa-circle-notch fa-spin"></i>Save limit
        </button>
        <p class="text-xs text-slate-500">
          <template v-if="limitConfigured">Currently {{ status.daily_limit.max }} periods for {{ session.session_name }}.</template>
          <template v-else>Not set, teachers above {{ defaultLimit }} periods a day get a warning only.</template>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { timetableAPI } from '../../services/api.js';
import authStore from '../../services/authStore.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';

const props = defineProps({ session: { type: Object, required: true } });
const emit = defineEmits(['changed']);
const { showToast } = useToast();
const canEdit = computed(() => ['super_admin', 'admin'].includes(authStore.getRole()));
const canEditRules = computed(() => ['super_admin', 'admin', 'teacher'].includes(authStore.getRole()));

const rows = ref([]);
const original = ref('[]');
const status = ref(null);
const errors = ref([]);
const saving = ref(false);
const savingRules = ref(false);
const dailyLimit = ref(null);

const toRow = (p) => ({ label: p.label, period_type: p.period_type, start_time: p.start_time.slice(0, 5), end_time: p.end_time.slice(0, 5) });
const lessonCount = computed(() => rows.value.filter((p) => p.period_type === 'lesson').length);
const dirty = computed(() => JSON.stringify(rows.value) !== original.value);
const minutes = (p) => {
  const [a, b] = [p.start_time, p.end_time].map((t) => (t ? Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5)) : NaN));
  return b - a;
};
const defaultLimit = 7;
const limitConfigured = computed(() => status.value?.daily_limit?.configured);

const load = async () => {
  try {
    const res = (await timetableAPI.setup(props.session.id)).data;
    rows.value = res.periods.map(toRow);
    original.value = JSON.stringify(rows.value);
    status.value = res.status;
    dailyLimit.value = res.status.daily_limit.configured ? res.status.daily_limit.max : null;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
};
const reset = () => {
  rows.value = JSON.parse(original.value);
  errors.value = [];
};
const addRow = () => {
  const last = rows.value[rows.value.length - 1];
  const start = last?.end_time || '08:00';
  const [h, m] = start.split(':').map(Number);
  const end = `${String(Math.floor((h * 60 + m + 40) / 60) % 24).padStart(2, '0')}:${String((m + 40) % 60).padStart(2, '0')}`;
  const n = rows.value.filter((p) => p.period_type === 'lesson').length + 1;
  rows.value.push({ label: `Period ${n}`, period_type: 'lesson', start_time: start, end_time: end });
};

const save = async () => {
  if (!confirm('Save the school day? Lessons keep their periods and get the new times, and every term will need to be validated again before publishing.')) return;
  saving.value = true;
  errors.value = [];
  try {
    const res = await timetableAPI.savePeriods(rows.value);
    showToast(res.message, 'success', 5000);
    await load();
    emit('changed');
  } catch (err) {
    const e = apiError(err);
    errors.value = e.data?.errors?.length ? e.data.errors : [e.message];
  } finally {
    saving.value = false;
  }
};

const saveRules = async () => {
  savingRules.value = true;
  try {
    const res = await timetableAPI.saveRules(props.session.id, dailyLimit.value || null);
    showToast(res.message);
    await load();
    emit('changed');
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    savingRules.value = false;
  }
};

watch(() => props.session.id, load);
onMounted(load);
</script>
