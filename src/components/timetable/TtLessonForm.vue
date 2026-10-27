<template>
  <div class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" :aria-label="title">
    <div class="absolute inset-0 bg-slate-900/40" @click="close"></div>
    <aside class="relative flex h-full w-full max-w-lg flex-col bg-white shadow-2xl">
      <header class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-700">{{ sessionLabel }}</p>
          <h2 class="mt-1 text-lg font-bold text-slate-900">{{ title }}</h2>
          <p v-if="isEdit && original?.is_locked" class="mt-1 text-xs font-medium text-amber-700">
            <i class="fas fa-lock mr-1"></i>Locked, unlock to change or remove it.
          </p>
        </div>
        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close" @click="close">
          <i class="fas fa-xmark"></i>
        </button>
      </header>

      <form class="flex-1 space-y-4 overflow-y-auto px-5 py-5" novalidate @submit.prevent="save">
        <div v-if="formError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <i class="fas fa-circle-exclamation mr-1"></i>{{ formError }}
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="field-label" for="tt-class">Class &amp; stream <span class="text-red-600">*</span></label>
            <TtSearchSelect
              id="tt-class"
              v-model="form.class_id"
              :options="meta.classes"
              label="full_class_name"
              :hint="(c) => c.level"
              noun="classes"
              placeholder="Choose a class"
              :error="errors.class_id"
              :disabled="locked"
            />
            <p v-if="errors.class_id" class="field-error">{{ errors.class_id }}</p>
          </div>

          <div class="sm:col-span-2">
            <label class="field-label" for="tt-subject">Subject <span class="text-red-600">*</span></label>
            <TtSearchSelect
              id="tt-subject"
              v-model="form.subject_id"
              :options="meta.subjects"
              label="subject_name"
              hint="subject_code"
              noun="subjects"
              placeholder="Choose a subject"
              :error="errors.subject_id"
              :disabled="locked"
            />
            <p v-if="errors.subject_id" class="field-error">{{ errors.subject_id }}</p>
          </div>

          <div class="sm:col-span-2">
            <label class="field-label" for="tt-teacher">Teacher <span class="text-red-600">*</span></label>
            <TtSearchSelect
              id="tt-teacher"
              v-model="form.teacher_id"
              :options="teacherOptions"
              label="full_name"
              :hint="(t) => t.is_active ? t.teacher_code : 'inactive'"
              noun="teachers"
              :placeholder="deptHint.empty ? 'No teachers in this department' : 'Choose a teacher'"
              :error="errors.teacher_id"
              :disabled="locked"
            />
            <p v-if="errors.teacher_id" class="field-error">{{ errors.teacher_id }}</p>
            <p v-else-if="deptHint.text" class="mt-1 text-xs" :class="deptHint.empty ? 'text-amber-700' : 'text-slate-500'"><i class="fas fa-sitemap mr-1"></i>{{ deptHint.text }}</p>
            <p v-else-if="suggestedTeacher" class="mt-1 text-xs text-slate-500">
              Lesson Requirements assign this subject to
              <button type="button" class="font-semibold text-brand-700 hover:underline" @click="form.teacher_id = suggestedTeacher.id">{{ suggestedTeacher.full_name }}</button>.
            </p>
          </div>

          <div class="sm:col-span-2">
            <label class="field-label" for="tt-room">Room</label>
            <TtSearchSelect
              id="tt-room"
              v-model="form.room_id"
              :options="roomOptions"
              :label="(r) => `${r.room_code} · ${r.room_name}`"
              hint="room_type"
              noun="rooms"
              placeholder="No room"
              clearable
              :error="errors.room_id"
              :disabled="locked"
            />
            <p v-if="errors.room_id" class="field-error">{{ errors.room_id }}</p>
          </div>

          <div>
            <label class="field-label" for="tt-day">Day <span class="text-red-600">*</span></label>
            <select id="tt-day" v-model="form.day_of_week" class="input" :class="errors.day_of_week ? 'border-red-400' : ''" :disabled="locked">
              <option v-for="d in meta.days" :key="d" :value="d">{{ d }}</option>
            </select>
            <p v-if="errors.day_of_week" class="field-error">{{ errors.day_of_week }}</p>
          </div>

          <div>
            <label class="field-label" for="tt-period">Starting period <span class="text-red-600">*</span></label>
            <select id="tt-period" v-model.number="form.period_number" class="input" :class="errors.period_number ? 'border-red-400' : ''" :disabled="locked">
              <option v-for="p in lessonPeriods" :key="p.period_number" :value="p.period_number">
                {{ p.label }} ({{ shortTime(p.start_time) }})
              </option>
            </select>
            <p v-if="errors.period_number" class="field-error">{{ errors.period_number }}</p>
          </div>

          <div class="sm:col-span-2">
            <span class="field-label">Lesson length</span>
            <div class="flex flex-wrap gap-2" role="radiogroup">
              <label
                v-for="(opt, span) in spanOptions"
                :key="span"
                class="cursor-pointer rounded-xl border px-3 py-2 text-sm transition"
                :class="Number(span) === form.spans_periods ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
              >
                <input v-model.number="form.spans_periods" type="radio" class="sr-only" :value="Number(span)" :disabled="locked" />
                {{ opt.label }} <span class="text-xs opacity-70">· {{ opt.minutes }} min</span>
              </label>
            </div>
            <p v-if="errors.spans_periods" class="field-error">{{ errors.spans_periods }}</p>
            <p class="mt-2 text-xs text-slate-500">
              <i class="far fa-clock mr-1"></i>
              <template v-if="timeRange">{{ form.day_of_week }}, {{ timeRange }}</template>
              <template v-else>Choose a period to see the lesson time.</template>
            </p>
          </div>

          <div class="sm:col-span-2">
            <label class="field-label" for="tt-notes">Notes</label>
            <textarea id="tt-notes" v-model="form.notes" rows="2" class="input resize-none" placeholder="Optional" :disabled="locked"></textarea>
          </div>
        </div>

        <!-- Live server check -->
        <section class="rounded-2xl border p-4" :class="checkTone.box" aria-live="polite">
          <p class="flex items-center gap-2 text-sm font-semibold" :class="checkTone.text">
            <i :class="checkTone.icon"></i>{{ checkTone.title }}
          </p>
          <ul v-if="liveConflicts.length" class="mt-2 space-y-1.5 text-sm">
            <li v-for="(c, i) in liveConflicts" :key="i" class="flex gap-2" :class="c.severity === 'error' ? 'text-red-700' : 'text-amber-800'">
              <i class="mt-0.5 text-xs" :class="c.severity === 'error' ? 'fas fa-circle-xmark' : 'fas fa-triangle-exclamation'"></i>
              <span>{{ c.message }}</span>
            </li>
          </ul>
        </section>
      </form>

      <footer class="flex flex-wrap items-center gap-2 border-t border-slate-200 px-5 py-4">
        <template v-if="isEdit">
          <button type="button" class="btn-secondary" :disabled="busy" @click="toggleLock">
            <i :class="original?.is_locked ? 'fas fa-lock-open' : 'fas fa-lock'"></i>{{ original?.is_locked ? 'Unlock' : 'Lock' }}
          </button>
          <button v-if="!locked" type="button" class="btn-secondary text-red-700 hover:bg-red-50" :disabled="busy" @click="remove">
            <i class="fas fa-trash"></i>Remove
          </button>
        </template>
        <div class="ml-auto flex gap-2">
          <button type="button" class="btn-secondary" :disabled="saving" @click="close">Cancel</button>
          <button type="button" class="btn-primary min-w-[8rem]" :disabled="saving || locked || hasBlocking" @click="save">
            <i v-if="saving" class="fas fa-circle-notch fa-spin"></i>
            {{ saving ? 'Saving…' : isEdit ? 'Save changes' : 'Add lesson' }}
          </button>
        </div>
      </footer>
    </aside>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch, onBeforeUnmount } from 'vue';
import { timetableAPI } from '../../services/api.js';
import { apiError, requestId, shortTime } from '../../utils/timetable.js';
import TtSearchSelect from './TtSearchSelect.vue';

const props = defineProps({
  meta: { type: Object, required: true },
  session: { type: Object, required: true },
  entry: { type: Object, default: null },
  prefill: { type: Object, default: () => ({}) },
  requirements: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'saved', 'removed']);

const isEdit = computed(() => !!props.entry?.id);
const original = ref(props.entry ? { ...props.entry } : null);
const title = computed(() => (isEdit.value ? 'Edit lesson' : 'Add lesson'));
const sessionLabel = computed(() => `${props.session.academic_year} · Term ${props.session.term}`);

const firstLesson = props.meta.periods.find((p) => p.period_type === 'lesson');
const form = reactive({
  class_id: props.entry?.class_id ?? props.prefill.class_id ?? null,
  subject_id: props.entry?.subject_id ?? props.prefill.subject_id ?? null,
  teacher_id: props.entry?.teacher_id ?? props.prefill.teacher_id ?? null,
  room_id: props.entry?.room_id ?? props.prefill.room_id ?? null,
  day_of_week: props.entry?.day_of_week ?? props.prefill.day ?? props.meta.days[0],
  period_number: Number(props.entry?.period_number ?? props.prefill.period ?? firstLesson?.period_number ?? 1),
  spans_periods: Number(props.entry?.spans_periods ?? props.prefill.spans_periods ?? 1),
  notes: props.entry?.notes ?? '',
});

const errors = ref({});
const formError = ref('');
const liveConflicts = ref([]);
const checking = ref(false);
const checked = ref(false);
const saving = ref(false);
const busy = ref(false);
let submissionId = requestId();

const locked = computed(() => !!original.value?.is_locked);
const lessonPeriods = computed(() => props.meta.periods.filter((p) => p.period_type === 'lesson'));
const selectedClass = computed(() => props.meta.classes.find((c) => c.id === form.class_id));
const spanOptions = computed(() => props.meta.span_options[selectedClass.value?.level || 'O-Level']);
/** Teachers of the chosen subject's department (all teachers if the department has no members yet). */
const deptIds = computed(() => (form.subject_id ? props.meta.subject_teachers?.[form.subject_id] || [] : []));
const teacherOptions = computed(() => {
  const ids = new Set(deptIds.value);
  return props.meta.teachers
    .filter((t) => !ids.size || ids.has(t.id) || t.id === original.value?.teacher_id)
    .map((t) => ({ ...t, disabled: !t.is_active }));
});
const deptHint = computed(() => {
  const subject = props.meta.subjects.find((s) => s.id === form.subject_id);
  if (!subject) return { text: '' };
  if (!deptIds.value.length) return { text: `No ${subject.subject_name} department set up yet, showing all teachers.`, empty: false };
  return { text: `Showing the ${deptIds.value.length} teacher(s) in the ${subject.subject_name} department.` };
});
// New lessons use the class's home room; changing class swaps it unless another room was picked.
const homeRoom = (classId) => props.meta.home_rooms?.[classId] || null;
if (!props.entry && !form.room_id && form.class_id) form.room_id = homeRoom(form.class_id);
watch(() => form.class_id, (cls, prev) => {
  if (!form.room_id || form.room_id === homeRoom(prev)) form.room_id = homeRoom(cls);
});
// Changing the subject clears a teacher who is not in the new subject's department.
watch(() => form.subject_id, () => {
  if (form.teacher_id && deptIds.value.length && !deptIds.value.includes(form.teacher_id)) form.teacher_id = null;
});
const roomOptions = computed(() => props.meta.rooms.filter((r) => r.is_active || r.id === form.room_id));
const suggestedTeacher = computed(() => {
  const req = props.requirements.find((r) => r.class_id === form.class_id && r.subject_id === form.subject_id);
  if (!req || req.teacher_id === form.teacher_id) return null;
  return props.meta.teachers.find((t) => t.id === req.teacher_id) || null;
});
const timeRange = computed(() => {
  const start = props.meta.periods.find((p) => p.period_number === form.period_number);
  const end = props.meta.periods.find((p) => p.period_number === form.period_number + form.spans_periods - 1);
  if (!start || !end) return '';
  const minutes = (Date.parse(`1970-01-01T${end.end_time}`) - Date.parse(`1970-01-01T${start.start_time}`)) / 60000;
  return `${shortTime(start.start_time)}–${shortTime(end.end_time)} (${minutes} min)`;
});

watch(spanOptions, (opts) => { if (opts && !opts[form.spans_periods]) form.spans_periods = 1; });

const payload = () => ({
  ...form,
  id: props.entry?.id,
  entry_type: 'lesson',
  academic_session_id: props.session.id,
  room_id: form.room_id || null,
});

const complete = computed(() => form.class_id && form.subject_id && form.teacher_id && form.day_of_week && form.period_number);
const hasBlocking = computed(() => liveConflicts.value.some((c) => c.severity === 'error'));

const checkTone = computed(() => {
  if (!complete.value) return { box: 'border-slate-200 bg-slate-50', text: 'text-slate-500', icon: 'fas fa-shield-halved', title: 'Choose class, subject, teacher and time to check for clashes' };
  if (checking.value) return { box: 'border-slate-200 bg-slate-50', text: 'text-slate-500', icon: 'fas fa-circle-notch fa-spin', title: 'Checking with the server…' };
  if (hasBlocking.value) return { box: 'border-red-200 bg-red-50', text: 'text-red-700', icon: 'fas fa-circle-xmark', title: 'This lesson cannot be scheduled here' };
  if (liveConflicts.value.length) return { box: 'border-amber-200 bg-amber-50', text: 'text-amber-800', icon: 'fas fa-triangle-exclamation', title: 'Can be saved, but check these warnings' };
  if (checked.value) return { box: 'border-brand-200 bg-brand-50', text: 'text-brand-800', icon: 'fas fa-circle-check', title: 'No clashes, teacher, class and room are free' };
  return { box: 'border-slate-200 bg-slate-50', text: 'text-slate-500', icon: 'fas fa-shield-halved', title: 'Ready to check' };
});

let timer = null;
let checkSeq = 0;
const runCheck = async () => {
  if (!complete.value || locked.value) return;
  const seq = ++checkSeq;
  checking.value = true;
  try {
    const res = await timetableAPI.check(payload());
    if (seq !== checkSeq) return;
    errors.value = { ...(res.data?.errors || {}) };
    liveConflicts.value = res.data?.conflicts || [];
    checked.value = true;
  } catch (err) {
    if (seq === checkSeq) formError.value = apiError(err).message;
  } finally {
    if (seq === checkSeq) checking.value = false;
  }
};
watch(() => [form.class_id, form.subject_id, form.teacher_id, form.room_id, form.day_of_week, form.period_number, form.spans_periods], () => {
  checked.value = false;
  formError.value = '';
  clearTimeout(timer);
  timer = setTimeout(runCheck, 350);
}, { immediate: true });
onBeforeUnmount(() => clearTimeout(timer));

const validateLocally = () => {
  const e = {};
  if (!form.class_id) e.class_id = 'Choose a class';
  if (!form.subject_id) e.subject_id = 'Choose a subject';
  if (!form.teacher_id) e.teacher_id = 'Choose a teacher';
  if (!form.period_number) e.period_number = 'Choose a period';
  errors.value = e;
  return !Object.keys(e).length;
};

const save = async () => {
  if (saving.value || locked.value) return;
  formError.value = '';
  if (!validateLocally()) return;
  saving.value = true;
  try {
    const body = { ...payload(), client_request_id: submissionId };
    const res = isEdit.value ? await timetableAPI.update(body) : await timetableAPI.create(body);
    submissionId = requestId();
    emit('saved', { id: res.data?.id, warnings: res.data?.warnings || [], created: !isEdit.value });
  } catch (err) {
    // Keep everything the user entered; show why it failed.
    const e = apiError(err);
    errors.value = { ...e.errors };
    if (e.conflicts.length) liveConflicts.value = [...e.conflicts, ...(e.data?.warnings || [])];
    formError.value = e.message;
  } finally {
    saving.value = false;
  }
};

const toggleLock = async () => {
  busy.value = true;
  try {
    await timetableAPI.setLocked(props.entry.id, !original.value.is_locked);
    original.value = { ...original.value, is_locked: original.value.is_locked ? 0 : 1 };
    emit('saved', { id: props.entry.id, warnings: [], silent: true });
    if (!original.value.is_locked) runCheck();
  } catch (err) {
    formError.value = apiError(err).message;
  } finally {
    busy.value = false;
  }
};

// The saved lesson, named in full, so the confirmation leaves no doubt which day is removed.
const removeLabel = computed(() => {
  const e = original.value || {};
  const first = Number(e.period_number);
  const last = first + Math.max(1, Number(e.spans_periods) || 1) - 1;
  const periods = last > first ? `periods ${first}-${last}` : `period ${first}`;
  return [e.subject_name || 'this lesson', e.full_class_name, `${e.day_of_week} ${periods}`].filter(Boolean).join(', ');
});
const remove = async () => {
  if (!confirm(`Remove ${removeLabel.value} from the timetable?\n\nOnly this lesson is removed; the class's lessons on other days stay.`)) return;
  busy.value = true;
  try {
    await timetableAPI.delete(props.entry.id);
    emit('removed', { id: props.entry.id, label: removeLabel.value });
  } catch (err) {
    formError.value = apiError(err).message;
  } finally {
    busy.value = false;
  }
};

const close = () => { if (!saving.value) emit('close'); };
</script>

<style scoped>
.field-label {
  @apply mb-1.5 block text-sm font-medium text-slate-700;
}
.field-error {
  @apply mt-1 text-xs font-medium text-red-600;
}
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .field-label {
  color: var(--d-text-2);
}
html.dark .field-error {
  color: #fca5a5;
}
</style>
