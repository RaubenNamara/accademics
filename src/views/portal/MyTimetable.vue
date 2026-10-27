<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">My Timetable</h1>
          <p class="page-hero-subtitle">
            <template v-if="data">{{ data.session.academic_year }} Term {{ data.session.term }} · {{ lessonCount }} lesson period(s) a week</template>
            <template v-else>Your lessons for the week.</template>
          </p>
        </div>
        <button type="button" class="btn-hero" :disabled="!data?.entries?.length" @click="download"><Download class="h-4 w-4" />Download PDF</button>
      </div>
    </div>

    <div v-if="data?.source === 'draft'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      This term's timetable has not been published yet. What you see is the current draft and may still change.
    </div>
    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <div class="card p-3 sm:p-4">
      <div v-if="loading" class="py-12 text-center text-sm text-gray-500">Loading…</div>
      <div v-else-if="!data?.entries?.length" class="py-12 text-center text-sm text-gray-500">You have no lessons on the timetable for this term yet.</div>
      <TtWeekGrid v-else :entries="data.entries" :periods="data.periods" :days="data.days" mode="teacher" @open="selected = $event" />
    </div>

    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="selected = null">
      <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">{{ selected.day_of_week }} · {{ shortTime(selected.start_time) }}–{{ shortTime(selected.end_time) }}</p>
        <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ entryTitle(selected) }}</h2>
        <dl class="mt-3 space-y-1.5 text-sm">
          <div v-if="selected.full_class_name" class="flex justify-between"><dt class="text-gray-500">Class</dt><dd class="font-medium text-gray-900">{{ selected.full_class_name }}</dd></div>
          <div v-if="selected.room_name" class="flex justify-between"><dt class="text-gray-500">Room</dt><dd class="font-medium text-gray-900">{{ selected.room_name }}</dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Length</dt><dd class="font-medium text-gray-900">{{ selected.spans_periods > 1 ? `${selected.spans_periods} periods (double)` : '1 period' }}</dd></div>
        </dl>
        <div class="mt-5 flex justify-end gap-2">
          <RouterLink v-if="selected.subject_id" :to="{ path: '/my/lesson-plans', query: { new: 1, class_id: selected.class_id, subject_id: selected.subject_id } }" class="btn-secondary">Plan this lesson</RouterLink>
          <button type="button" class="btn-primary" @click="selected = null">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Download } from 'lucide-vue-next';
import TtWeekGrid from '../../components/timetable/TtWeekGrid.vue';
import authStore from '../../services/authStore.js';
import { teacherPortalAPI } from '../../services/api.js';
import { apiError, entryTitle, exportGridPdf, shortTime } from '../../utils/timetable.js';

const data = ref(null);
const loading = ref(true);
const error = ref('');
const selected = ref(null);

const lessonCount = computed(() => (data.value?.entries || [])
  .filter((e) => (e.entry_type || 'lesson') === 'lesson')
  .reduce((t, e) => t + Math.max(1, Number(e.spans_periods) || 1), 0));

function download() {
  const s = data.value.session;
  const name = authStore.user?.full_name || 'My';
  exportGridPdf({
    title: `${name}, Timetable`,
    subtitle: `${s.academic_year} Term ${s.term}${data.value.source === 'draft' ? ' (draft)' : ''}`,
    filename: `${name} timetable Term ${s.term} ${s.academic_year}.pdf`,
    entries: data.value.entries,
    periods: data.value.periods,
    days: data.value.days,
    mode: 'teacher',
  });
}

onMounted(async () => {
  try {
    data.value = (await teacherPortalAPI.view('timetable')).data;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
});
</script>
