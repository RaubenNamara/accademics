<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Publish timetable">
    <div class="absolute inset-0 bg-slate-900/40" @click="!busy && $emit('close')"></div>
    <form class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" @submit.prevent="publish">
      <header class="border-b border-slate-200 px-6 py-4">
        <h2 class="text-lg font-bold text-slate-900">Publish timetable</h2>
        <p class="text-sm text-slate-500">{{ session.session_name }}, saves an unchangeable copy that becomes the official timetable.</p>
      </header>

      <div class="space-y-4 px-6 py-5">
        <div class="rounded-2xl border p-4 text-sm" :class="summaryTone">
          <template v-if="!summary">Checking the timetable…</template>
          <template v-else-if="summary.errors">
            <i class="fas fa-circle-xmark mr-1"></i><strong>{{ summary.errors }} blocking error(s).</strong>
            Publishing is blocked until they are fixed in <em>Conflicts &amp; Validation</em>.
          </template>
          <template v-else-if="summary.warnings">
            <i class="fas fa-triangle-exclamation mr-1"></i><strong>No blocking errors</strong>, but {{ summary.warnings }} warning(s) remain
            (for example missing lessons).
          </template>
          <template v-else><i class="fas fa-circle-check mr-1"></i><strong>Validation passed.</strong> No clashes or allocation problems.</template>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="pub-name">Version name</label>
            <input id="pub-name" v-model="form.version_name" class="input" :placeholder="`Version ${nextNumber}`" maxlength="100" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700" for="pub-date">Effective from</label>
            <input id="pub-date" v-model="form.effective_date" type="date" class="input" :class="fieldErrors.effective_date ? 'border-red-400' : ''" />
            <p v-if="fieldErrors.effective_date" class="mt-1 text-xs text-red-600">{{ fieldErrors.effective_date }}</p>
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-slate-700" for="pub-notes">What changed?</label>
          <textarea id="pub-notes" v-model="form.notes" rows="2" class="input resize-none" placeholder="Optional note for the version history"></textarea>
        </div>

        <label v-if="summary?.warnings && !summary?.errors" class="flex items-start gap-3 rounded-2xl bg-amber-50 p-3 text-sm text-amber-900">
          <input v-model="form.confirm_warnings" type="checkbox" class="mt-0.5 accent-brand-600" />
          I have reviewed the warnings and want to publish anyway.
        </label>

        <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
      </div>

      <footer class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
        <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">Cancel</button>
        <button type="submit" class="btn-primary" :disabled="busy || !summary || summary.errors > 0 || (summary.warnings > 0 && !form.confirm_warnings)">
          <i class="fas" :class="busy ? 'fa-circle-notch fa-spin' : 'fa-upload'"></i>{{ busy ? 'Publishing…' : 'Publish' }}
        </button>
      </footer>
    </form>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { timetableAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

const props = defineProps({
  session: { type: Object, required: true },
  summary: { type: Object, default: null },
  nextNumber: { type: Number, default: 1 },
});
const emit = defineEmits(['close', 'published', 'revalidated']);

const form = reactive({ version_name: '', effective_date: new Date().toISOString().slice(0, 10), notes: '', confirm_warnings: false });
const busy = ref(false);
const error = ref('');
const fieldErrors = ref({});

const summaryTone = computed(() => {
  if (!props.summary) return 'border-slate-200 bg-slate-50 text-slate-600';
  if (props.summary.errors) return 'border-red-200 bg-red-50 text-red-800';
  if (props.summary.warnings) return 'border-amber-200 bg-amber-50 text-amber-900';
  return 'border-brand-200 bg-brand-50 text-brand-900';
});

const publish = async () => {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  fieldErrors.value = {};
  try {
    const res = await timetableAPI.publish({ academic_session_id: props.session.id, ...form });
    emit('published', res.data);
  } catch (err) {
    const e = apiError(err);
    error.value = e.message;
    fieldErrors.value = e.errors;
    if (e.data?.validation) emit('revalidated', e.data.validation);
  } finally {
    busy.value = false;
  }
};
</script>
