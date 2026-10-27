<template>
  <div class="grid gap-6 xl:grid-cols-5">
    <section class="card overflow-hidden xl:col-span-3">
      <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <div>
          <h3 class="card-title">Published versions</h3>
          <p class="text-xs text-slate-500">Each publication is a frozen copy. Restoring one replaces the current draft.</p>
        </div>
        <button type="button" class="btn-secondary px-3 py-1.5 text-xs" :disabled="loading" @click="load"><i class="fas fa-rotate"></i>Refresh</button>
      </div>
      <div v-if="loading" class="space-y-2 p-5"><div v-for="i in 3" :key="i" class="h-12 animate-pulse rounded-xl bg-slate-100"></div></div>
      <p v-else-if="!versions.length" class="px-5 py-10 text-center text-sm text-slate-500">Nothing has been published for this term yet.</p>
      <ul v-else class="divide-y divide-slate-100">
        <li v-for="v in versions" :key="v.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
          <div class="min-w-0 flex-1">
            <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
              {{ v.version_name }}
              <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase" :class="v.status === 'published' ? 'bg-brand-100 text-brand-800' : 'bg-slate-100 text-slate-600'">{{ v.status }}</span>
            </p>
            <p class="text-xs text-slate-500">
              <template v-if="v.published_at">Published {{ formatDate(v.published_at) }}</template>
              <template v-if="v.created_by_name"> by {{ v.created_by_name }}</template>
              <template v-if="v.effective_date"> · effective {{ v.effective_date }}</template>
              <template v-if="v.entry_count"> · {{ v.entry_count }} entries</template>
            </p>
            <p v-if="v.notes" class="mt-1 text-xs text-slate-600">{{ v.notes }}</p>
          </div>
          <button
            v-if="canEdit && Number(v.restorable)"
            type="button"
            class="btn-secondary shrink-0 px-3 py-1.5 text-xs"
            :disabled="restoring === v.id"
            @click="restore(v)"
          >
            <i class="fas" :class="restoring === v.id ? 'fa-circle-notch fa-spin' : 'fa-clock-rotate-left'"></i>Restore
          </button>
          <span v-else-if="!Number(v.restorable)" class="text-xs text-slate-400">No stored copy</span>
        </li>
      </ul>
    </section>

    <section class="card overflow-hidden xl:col-span-2">
      <div class="border-b border-slate-100 px-5 py-4">
        <h3 class="card-title">Change log</h3>
        <p class="text-xs text-slate-500">Who changed what, newest first.</p>
      </div>
      <p v-if="!loading && !audit.length" class="px-5 py-10 text-center text-sm text-slate-500">No changes recorded yet.</p>
      <ol class="max-h-[32rem] divide-y divide-slate-100 overflow-y-auto">
        <li v-for="a in audit" :key="a.id" class="flex gap-3 px-5 py-3">
          <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs text-slate-500"><i :class="actionIcon(a.action)"></i></span>
          <div class="min-w-0">
            <p class="text-sm text-slate-800">{{ a.summary }}</p>
            <p class="text-xs text-slate-400">{{ a.user_name || 'Unknown' }} · {{ formatDate(a.created_at) }}</p>
          </div>
        </li>
      </ol>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { timetableAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';
import { useToast } from '../../composables/useToast.js';

const props = defineProps({
  session: { type: Object, required: true },
  canEdit: Boolean,
});
const emit = defineEmits(['restored']);
const { showToast } = useToast();

const versions = ref([]);
const audit = ref([]);
const loading = ref(false);
const restoring = ref(null);

const formatDate = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleString() : '');
const actionIcon = (a) => ({
  create: 'fas fa-plus', update: 'fas fa-pen', delete: 'fas fa-trash', lock: 'fas fa-lock', unlock: 'fas fa-lock-open',
  generate: 'fas fa-wand-magic-sparkles', publish: 'fas fa-upload', restore: 'fas fa-clock-rotate-left', validate: 'fas fa-shield-halved',
}[a] || 'fas fa-circle');

const load = async () => {
  loading.value = true;
  try {
    const res = await timetableAPI.history(props.session.id);
    versions.value = res.data?.versions || [];
    audit.value = res.data?.audit || [];
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    loading.value = false;
  }
};

const restore = async (v) => {
  if (!confirm(`Restore "${v.version_name}"? The current draft timetable for this term will be replaced by this version.`)) return;
  restoring.value = v.id;
  try {
    const res = await timetableAPI.restore(v.id);
    const skipped = res.data?.skipped?.length || 0;
    showToast(skipped ? `Restored with ${skipped} entr(ies) skipped (their class, teacher or room no longer exists)` : res.message, skipped ? 'error' : 'success', 6000);
    emit('restored');
    load();
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    restoring.value = null;
  }
};

onMounted(load);
watch(() => props.session.id, load);
defineExpose({ load });
</script>
