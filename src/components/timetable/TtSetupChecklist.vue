<template>
  <div class="space-y-4">
    <div class="card p-5">
      <h2 class="card-title">Get the timetable ready</h2>
      <p class="text-xs text-slate-500">Work down the list for {{ session.session_name }}. When the required steps are done, use <strong>Generate Timetable</strong> at the top.</p>
    </div>
    <div v-if="!status" class="card space-y-2 p-5"><div v-for="i in 6" :key="i" class="h-12 animate-pulse rounded-xl bg-slate-100"></div></div>
    <ol v-else class="card divide-y divide-slate-100 overflow-hidden">
      <li v-for="(s, i) in steps" :key="s.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold" :class="s.done ? 'bg-brand-600 text-white' : s.optional ? 'bg-slate-100 text-slate-500' : 'bg-amber-100 text-amber-800'">
          <i v-if="s.done" class="fas fa-check"></i><template v-else>{{ i + 1 }}</template>
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-slate-900">{{ s.title }} <span v-if="s.optional" class="text-xs font-normal text-slate-400">(optional)</span></p>
          <p class="text-xs text-slate-500">{{ s.detail }}</p>
        </div>
        <button type="button" class="btn-secondary shrink-0 px-3 py-1.5 text-xs" @click="$emit('go', s.section)">{{ s.done ? 'Review' : 'Open' }}<i class="fas fa-arrow-right"></i></button>
      </li>
    </ol>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { timetableAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';
import { useToast } from '../../composables/useToast.js';

const props = defineProps({ session: { type: Object, required: true } });
defineEmits(['go']);
const { showToast } = useToast();
const status = ref(null);

const steps = computed(() => {
  const s = status.value;
  return [
    { id: 'term', section: 'sessions', title: 'Term', done: s.session, detail: s.session ? `${props.session.session_name} is set up.` : 'Create the term.' },
    { id: 'day', section: 'school-day', title: 'School day', done: s.lesson_periods > 0,
      detail: `${s.lesson_periods} lesson periods and ${s.breaks} break(s) a day.` },
    { id: 'classes', section: 'classes', title: 'Classes & streams', done: s.classes > 0, detail: `${s.classes} class streams.` },
    { id: 'rooms', section: 'rooms', title: 'Rooms', done: s.rooms > 0 && s.classes_with_home_room >= s.classes,
      detail: `${s.rooms} active room(s); ${s.classes_with_home_room} of ${s.classes} classes have a home room.` },
    { id: 'depts', section: 'departments', title: 'Subject departments', done: s.subjects_with_teachers > 0,
      detail: `${s.subjects_with_teachers} of ${s.subjects} subjects have teachers.` },
    { id: 'reqs', section: 'requirements', title: 'Lesson requirements', done: s.requirements > 0,
      detail: s.requirements ? `${s.requirements} requirement(s) for this term.` : 'Say what each class studies, with whom, and how often, required before generating.' },
    { id: 'avail', section: 'availability', title: 'Teacher availability', optional: true, done: s.availability_marks > 0,
      detail: s.availability_marks ? `${s.availability_marks} unavailable period(s) marked.` : 'Mark periods teachers cannot teach, if any.' },
    { id: 'limit', section: 'school-day', title: 'Teacher daily limit', optional: true, done: s.daily_limit.configured,
      detail: s.daily_limit.configured ? `At most ${s.daily_limit.max} periods a day.` : 'Not set (warnings above 7 periods a day).' },
  ];
});

const load = async () => {
  try {
    status.value = (await timetableAPI.setup(props.session.id)).data.status;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
};
watch(() => props.session.id, load);
onMounted(load);
</script>
