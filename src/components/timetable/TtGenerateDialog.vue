<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Generate timetable">
    <div class="absolute inset-0 bg-slate-900/40" @click="!running && $emit('close')"></div>
    <div class="relative flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
      <header class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
        <div>
          <h2 class="text-lg font-bold text-slate-900">Generate timetable</h2>
          <p class="text-sm text-slate-500">Places lessons from Lesson Requirements without breaking any clash rule.</p>
        </div>
        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Close" :disabled="running" @click="$emit('close')">
          <i class="fas fa-xmark"></i>
        </button>
      </header>

      <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
        <div v-if="!requirementCount" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          <i class="fas fa-triangle-exclamation mr-1"></i>
          There are no lesson requirements for this term yet. Add them under <strong>Setup → Lesson Requirements</strong> so the generator knows what to place.
        </div>

        <fieldset>
          <legend class="mb-2 text-sm font-semibold text-slate-800">What should it do?</legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <label v-for="m in modes" :key="m.id" class="cursor-pointer rounded-2xl border p-4 transition" :class="mode === m.id ? 'border-brand-600 bg-brand-50' : 'border-slate-200 hover:border-slate-300'">
              <input v-model="mode" type="radio" class="sr-only" :value="m.id" />
              <p class="text-sm font-semibold text-slate-900"><i :class="m.icon" class="mr-1.5 text-brand-700"></i>{{ m.title }}</p>
              <p class="mt-1 text-xs text-slate-600">{{ m.text }}</p>
            </label>
          </div>
        </fieldset>

        <fieldset>
          <legend class="mb-2 text-sm font-semibold text-slate-800">Which classes?</legend>
          <div class="mb-2 flex flex-wrap items-center gap-3 text-sm">
            <label class="flex items-center gap-2"><input v-model="scope" type="radio" value="all" class="accent-brand-600" /> All classes</label>
            <label class="flex items-center gap-2"><input v-model="scope" type="radio" value="some" class="accent-brand-600" /> Selected classes</label>
          </div>
          <div v-if="scope === 'some'" class="rounded-2xl border border-slate-200">
            <div class="border-b border-slate-100 p-2">
              <input v-model="classQuery" type="search" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500" placeholder="Filter classes…" />
            </div>
            <div class="grid max-h-44 grid-cols-2 gap-1 overflow-y-auto p-2 sm:grid-cols-3">
              <label v-for="c in filteredClasses" :key="c.id" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                <input v-model="classIds" type="checkbox" :value="c.id" class="accent-brand-600" />{{ c.full_class_name }}
              </label>
            </div>
          </div>
        </fieldset>

        <label v-if="state?.published_version" class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          <input v-model="acknowledged" type="checkbox" class="mt-0.5 accent-brand-600" />
          <span>
            Version {{ state.published_version.version_number }} is published. Generating changes the <strong>draft</strong> only, the published
            timetable stays in force until you validate and publish again.
          </span>
        </label>

        <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><i class="fas fa-circle-exclamation mr-1"></i>{{ error }}</div>

        <section v-if="result" class="rounded-2xl border p-4" :class="result.unplaced_periods ? 'border-amber-200 bg-amber-50/50' : 'border-brand-200 bg-brand-50/60'">
          <p class="text-sm font-semibold text-slate-900">
            {{ result.dry_run ? 'Preview' : 'Done' }}: {{ result.placed_periods }} period(s) placed in {{ result.generated }} lesson(s)
            <span v-if="result.removed">, {{ result.removed }} unlocked lesson(s) {{ result.dry_run ? 'would be' : 'were' }} replaced</span>.
          </p>
          <p v-if="!result.unplaced_periods" class="mt-1 text-sm text-brand-800"><i class="fas fa-circle-check mr-1"></i>Every required period fits.</p>
          <div v-else class="mt-3">
            <p class="text-sm font-medium text-amber-900">{{ result.unplaced_periods }} period(s) could not be placed without breaking a rule:</p>
            <ul class="mt-2 max-h-48 space-y-1.5 overflow-y-auto text-sm">
              <li v-for="(u, i) in result.unplaced" :key="i" class="rounded-xl bg-white px-3 py-2">
                <span class="font-medium text-slate-900">{{ u.class }}, {{ u.subject }}</span>
                <span class="text-slate-500"> ({{ u.teacher }}): {{ u.missing }} missing</span>
                <span v-if="u.reasons?.length" class="block text-xs text-slate-600">Blocked by {{ u.reasons.join('; ') }}.</span>
              </li>
            </ul>
          </div>
        </section>
      </div>

      <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-6 py-4">
        <button type="button" class="btn-secondary" :disabled="running" @click="$emit('close')">{{ result && !result.dry_run ? 'Close' : 'Cancel' }}</button>
        <button type="button" class="btn-secondary" :disabled="!canRun || running" @click="run(true)">
          <i class="fas" :class="running === 'preview' ? 'fa-circle-notch fa-spin' : 'fa-eye'"></i>Preview
        </button>
        <button type="button" class="btn-primary" :disabled="!canRun || running || (state?.published_version && !acknowledged)" @click="run(false)">
          <i class="fas" :class="running === 'run' ? 'fa-circle-notch fa-spin' : 'fa-wand-magic-sparkles'"></i>{{ running === 'run' ? 'Generating…' : 'Generate' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { timetableAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

const props = defineProps({
  meta: { type: Object, required: true },
  session: { type: Object, required: true },
  state: { type: Object, default: null },
  requirementCount: { type: Number, default: 0 },
  presetClassIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'done']);

const modes = [
  { id: 'fill', icon: 'fas fa-fill-drip', title: 'Fill missing lessons', text: 'Keeps every existing lesson and only places what is still missing.' },
  { id: 'regenerate', icon: 'fas fa-arrows-rotate', title: 'Rebuild unlocked lessons', text: 'Removes unlocked lessons for the chosen classes and places them again. Locked lessons stay.' },
];
const mode = ref('fill');
const scope = ref(props.presetClassIds.length ? 'some' : 'all');
const classIds = ref([...props.presetClassIds]);
const classQuery = ref('');
const acknowledged = ref(false);
const running = ref(false);
const result = ref(null);
const error = ref('');

const filteredClasses = computed(() => {
  const q = classQuery.value.trim().toLowerCase();
  return props.meta.classes.filter((c) => !q || c.full_class_name.toLowerCase().includes(q));
});
const canRun = computed(() => props.requirementCount > 0 && (scope.value === 'all' || classIds.value.length > 0));

const run = async (dryRun) => {
  if (!dryRun && mode.value === 'regenerate'
    && !confirm('Rebuild removes the unlocked lessons of the chosen classes and places them again. Continue?')) return;
  running.value = dryRun ? 'preview' : 'run';
  error.value = '';
  try {
    const res = await timetableAPI.generate({
      academic_session_id: props.session.id,
      mode: mode.value,
      dry_run: dryRun,
      class_ids: scope.value === 'all' ? [] : classIds.value,
    });
    result.value = res.data;
    if (!dryRun) emit('done', res.data);
  } catch (err) {
    error.value = apiError(err).message;
  } finally {
    running.value = false;
  }
};
</script>
