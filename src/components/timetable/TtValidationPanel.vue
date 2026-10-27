<template>
  <div class="space-y-4">
    <div class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between" :class="tone.card">
      <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="tone.icon">
          <i :class="tone.glyph" class="text-lg"></i>
        </span>
        <div>
          <p class="font-semibold text-slate-900">{{ tone.title }}</p>
          <p class="text-sm text-slate-600">{{ tone.text }}</p>
          <p v-if="data?.validated_at" class="mt-1 text-xs text-slate-400">
            Checked by the server {{ new Date(data.validated_at).toLocaleTimeString() }}
          </p>
        </div>
      </div>
      <button type="button" class="btn-primary shrink-0" :disabled="loading" @click="$emit('revalidate')">
        <i class="fas" :class="loading ? 'fa-circle-notch fa-spin' : 'fa-shield-halved'"></i>
        {{ loading ? 'Validating…' : 'Run validation' }}
      </button>
    </div>

    <div v-if="issues.length" class="flex flex-wrap gap-2">
      <button
        v-for="f in filters"
        :key="f.id"
        type="button"
        class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
        :class="filter === f.id ? 'border-brand-700 bg-brand-700 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
        @click="filter = f.id"
      >
        {{ f.label }} <span class="ml-1 opacity-75">{{ f.count }}</span>
      </button>
    </div>

    <div v-for="group in groups" :key="group.type" class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-5 py-3">
        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
          <i :class="typeIcon(group.type)" class="text-slate-400"></i>{{ group.label }}
        </h3>
        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="group.items[0].severity === 'error' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'">
          {{ group.items.length }}
        </span>
      </div>
      <ul class="divide-y divide-slate-100">
        <li v-for="issue in group.items.slice(0, group.limit)" :key="issue.id" class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-start">
          <span
            class="w-fit shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide"
            :class="issue.severity === 'error' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'"
          >{{ issue.severity === 'error' ? 'Blocking' : 'Warning' }}</span>
          <div class="min-w-0 flex-1">
            <p class="text-sm text-slate-800">{{ issue.message }}</p>
            <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
              <span v-if="issue.class"><i class="fas fa-users mr-1"></i>{{ issue.class }}</span>
              <span v-if="issue.teacher"><i class="fas fa-chalkboard-teacher mr-1"></i>{{ issue.teacher }}</span>
              <span v-if="issue.room"><i class="fas fa-door-open mr-1"></i>{{ issue.room }}</span>
              <span v-if="issue.period"><i class="far fa-clock mr-1"></i>{{ issue.period }}</span>
            </p>
            <p v-if="issue.suggestion" class="mt-1.5 text-xs text-brand-800"><i class="fas fa-lightbulb mr-1"></i>{{ issue.suggestion }}</p>
          </div>
          <div class="flex shrink-0 flex-wrap gap-2">
            <button
              v-for="(id, i) in (issue.entry_ids || []).slice(0, 3)"
              :key="id"
              type="button"
              class="btn-secondary px-3 py-1.5 text-xs"
              @click="$emit('open', id)"
            >
              <i class="fas fa-pen-to-square"></i>{{ issue.entry_ids.length > 1 ? `Lesson ${i + 1}` : 'Open lesson' }}
            </button>
            <button v-if="issue.type === 'missing_lessons'" type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="$emit('schedule', issue)">
              <i class="fas fa-plus"></i>Schedule
            </button>
          </div>
        </li>
      </ul>
      <button
        v-if="group.items.length > group.limit"
        type="button"
        class="w-full border-t border-slate-100 py-2.5 text-xs font-semibold text-brand-700 hover:bg-brand-50"
        @click="expanded[group.type] = true"
      >
        Show {{ group.items.length - group.limit }} more
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';

const props = defineProps({
  data: { type: Object, default: null },
  loading: Boolean,
});
defineEmits(['revalidate', 'open', 'schedule']);

const LABELS = {
  teacher_clash: 'Teacher clashes',
  class_clash: 'Class & stream clashes',
  room_clash: 'Room clashes',
  teacher_unavailable: 'Teacher availability',
  invalid_period: 'Invalid or overlapping periods',
  period_definition: 'Period definitions',
  over_allocation: 'Too many lessons (duplicates)',
  missing_lessons: 'Missing lessons',
  wrong_teacher: 'Incorrect allocations',
  workload: 'Teacher workload',
};
const ORDER = Object.keys(LABELS);
const typeIcon = (t) => ({
  teacher_clash: 'fas fa-chalkboard-teacher', class_clash: 'fas fa-users', room_clash: 'fas fa-door-open',
  teacher_unavailable: 'fas fa-user-clock', invalid_period: 'far fa-clock', period_definition: 'far fa-clock',
  over_allocation: 'fas fa-clone', missing_lessons: 'fas fa-list-check', wrong_teacher: 'fas fa-user-pen', workload: 'fas fa-weight-hanging',
}[t] || 'fas fa-circle-info');

const filter = ref('all');
const expanded = reactive({});
const issues = computed(() => props.data?.issues || []);

const filters = computed(() => [
  { id: 'all', label: 'All', count: issues.value.length },
  { id: 'error', label: 'Blocking', count: issues.value.filter((i) => i.severity === 'error').length },
  { id: 'warning', label: 'Warnings', count: issues.value.filter((i) => i.severity !== 'error').length },
]);

const groups = computed(() => {
  const list = issues.value.filter((i) => filter.value === 'all' || (filter.value === 'error' ? i.severity === 'error' : i.severity !== 'error'));
  const by = {};
  for (const i of list) (by[i.type] ||= []).push(i);
  return Object.keys(by)
    .sort((a, b) => ORDER.indexOf(a) - ORDER.indexOf(b))
    .map((type) => ({ type, label: LABELS[type] || type, items: by[type], limit: expanded[type] ? by[type].length : 8 }));
});

const tone = computed(() => {
  const s = props.data?.summary;
  if (!s) return { card: '', icon: 'bg-slate-100 text-slate-500', glyph: 'fas fa-shield-halved', title: 'Not validated yet', text: 'Run validation to check the timetable on the server.' };
  if (s.state === 'blocking') return { card: 'border-red-200', icon: 'bg-red-100 text-red-600', glyph: 'fas fa-circle-xmark', title: `${s.errors} blocking error${s.errors === 1 ? '' : 's'} detected`, text: 'Publishing is blocked until these are fixed.' + (s.warnings ? ` There are also ${s.warnings} warning(s).` : '') };
  if (s.state === 'warnings') return { card: 'border-amber-200', icon: 'bg-amber-100 text-amber-700', glyph: 'fas fa-triangle-exclamation', title: `${s.warnings} warning${s.warnings === 1 ? '' : 's'} detected`, text: 'No blocking clashes. Review the warnings before publishing.' };
  return { card: 'border-brand-200', icon: 'bg-brand-100 text-brand-700', glyph: 'fas fa-circle-check', title: 'Validation passed', text: 'No clashes, invalid periods, availability or allocation problems were found.' };
});
</script>
