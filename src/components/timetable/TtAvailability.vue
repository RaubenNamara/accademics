<template>
  <div class="space-y-4">
    <div class="card p-5">
      <h2 class="card-title">Teacher availability</h2>
      <p class="text-xs text-slate-500">
        Mark the periods a teacher cannot teach (part-time days, meetings, other duties). The generator never schedules them there and saving a lesson there is refused.
      </p>
      <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="w-full sm:w-80">
          <label class="mb-1 block text-xs font-medium text-slate-600" for="avail-teacher">Teacher</label>
          <TtSearchSelect id="avail-teacher" v-model="teacherId" :options="teachers" label="full_name" :hint="(t) => t.teacher_code" noun="teachers" placeholder="Choose a teacher" />
        </div>
        <p v-if="teacherId && !loading" class="text-sm text-slate-600">
          <strong>{{ unavailableCount }}</strong> of {{ slots }} lesson periods marked unavailable
        </p>
      </div>
    </div>

    <div v-if="teacherId" class="card overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-sm text-slate-500">Loading…</div>
      <template v-else>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[640px] border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th class="w-32 border-b border-slate-200 bg-slate-50 px-3 py-2.5 text-left text-xs font-semibold uppercase text-slate-500">Period</th>
                <th v-for="d in days" :key="d" class="border-b border-l border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-semibold uppercase text-slate-500">
                  {{ d }}
                  <button v-if="canEdit" type="button" class="ml-1 font-normal normal-case text-brand-700 hover:underline" @click="toggleDay(d)">
                    {{ dayAllOff(d) ? 'free all' : 'block all' }}
                  </button>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in periods" :key="p.period_number">
                <td class="border-b border-slate-100 px-3 py-2" :class="p.period_type !== 'lesson' ? 'bg-slate-50' : ''">
                  <p class="text-xs font-semibold text-slate-700">{{ p.label }}</p>
                  <p class="text-[11px] text-slate-400">{{ p.start_time.slice(0, 5) }}–{{ p.end_time.slice(0, 5) }}</p>
                </td>
                <td v-if="p.period_type !== 'lesson'" :colspan="days.length" class="border-b border-l border-slate-100 bg-slate-50 text-center text-[11px] uppercase tracking-widest text-slate-400">{{ p.label }}</td>
                <template v-else>
                  <td v-for="d in days" :key="d" class="border-b border-l border-slate-100 p-1">
                    <button
                      type="button"
                      class="flex h-10 w-full items-center justify-center gap-1.5 rounded-lg text-xs font-semibold transition"
                      :class="isOff(d, p.period_number) ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-brand-50 text-brand-700 hover:bg-brand-100'"
                      :disabled="!canEdit"
                      :aria-pressed="isOff(d, p.period_number)"
                      :aria-label="`${d} ${p.label}: ${isOff(d, p.period_number) ? 'unavailable' : 'available'}`"
                      @click="toggle(d, p.period_number)"
                    >
                      <i :class="isOff(d, p.period_number) ? 'fas fa-ban' : 'fas fa-check'"></i>{{ isOff(d, p.period_number) ? 'Unavailable' : 'Available' }}
                    </button>
                  </td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="canEdit" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3">
          <button type="button" class="btn-secondary" :disabled="!unavailableCount" @click="off = {}">Mark all available</button>
          <button type="button" class="btn-primary" :disabled="!dirty || saving" @click="save">
            <i v-if="saving" class="fas fa-circle-notch fa-spin"></i>{{ saving ? 'Saving…' : 'Save availability' }}
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { teacherAvailabilityAPI, timetableAPI } from '../../services/api.js';
import authStore from '../../services/authStore.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';
import TtSearchSelect from './TtSearchSelect.vue';

const props = defineProps({ activeSession: { type: Object, required: true } });
const emit = defineEmits(['changed']);
const { showToast } = useToast();
const canEdit = computed(() => ['super_admin', 'admin', 'teacher'].includes(authStore.getRole()));

const teachers = ref([]);
const periods = ref([]);
const days = ref([]);
const teacherId = ref(null);
const off = ref({});
const saved = ref('{}');
const loading = ref(false);
const saving = ref(false);

const lessonPeriods = computed(() => periods.value.filter((p) => p.period_type === 'lesson'));
const slots = computed(() => lessonPeriods.value.length * days.value.length);
const unavailableCount = computed(() => Object.keys(off.value).length);
const dirty = computed(() => JSON.stringify(Object.keys(off.value).sort()) !== saved.value);
const isOff = (d, p) => !!off.value[`${d}|${p}`];
const toggle = (d, p) => {
  const k = `${d}|${p}`;
  const next = { ...off.value };
  if (next[k]) delete next[k]; else next[k] = true;
  off.value = next;
};
const dayAllOff = (d) => lessonPeriods.value.every((p) => isOff(d, p.period_number));
const toggleDay = (d) => {
  const next = { ...off.value };
  const all = dayAllOff(d);
  for (const p of lessonPeriods.value) {
    const k = `${d}|${p.period_number}`;
    if (all) delete next[k]; else next[k] = true;
  }
  off.value = next;
};

const load = async () => {
  if (!teacherId.value) return;
  loading.value = true;
  try {
    const res = await teacherAvailabilityAPI.getByTeacher(props.activeSession.id, teacherId.value);
    const map = {};
    for (const a of res.data || []) if (!Number(a.is_available)) map[`${a.day_of_week}|${Number(a.period_number)}`] = true;
    off.value = map;
    saved.value = JSON.stringify(Object.keys(map).sort());
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    loading.value = false;
  }
};

const save = async () => {
  saving.value = true;
  try {
    await teacherAvailabilityAPI.bulkUpdate({
      teacher_id: teacherId.value,
      academic_session_id: props.activeSession.id,
      availability: Object.keys(off.value).map((k) => {
        const [day, p] = k.split('|');
        return { day_of_week: day, period_number: Number(p), is_available: false };
      }),
    });
    showToast('Availability saved, validate the timetable again to check existing lessons');
    await load();
    emit('changed');
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    saving.value = false;
  }
};

watch(teacherId, load);
watch(() => props.activeSession.id, load);
onMounted(async () => {
  try {
    const meta = (await timetableAPI.meta()).data;
    teachers.value = meta.teachers.filter((t) => t.is_active);
    periods.value = meta.periods;
    days.value = meta.days;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
});
</script>
