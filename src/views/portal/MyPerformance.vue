<template>
  <div class="page">
    <div class="page-hero">
      <h1 class="page-hero-title">My Performance</h1>
    </div>

    <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <StatCards :cards="stats" />

    <section class="card overflow-hidden">
      <div class="border-b border-gray-100 px-5 py-3"><h2 class="card-title">Lesson observations</h2></div>
      <div v-if="loading" class="px-5 py-8 text-center text-sm text-gray-500">Loading…</div>
      <div v-else-if="!data?.observations?.length" class="px-5 py-8 text-center text-sm text-gray-500">No lesson observations recorded yet.</div>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="(o, i) in data.observations" :key="i" class="px-5 py-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-medium text-gray-900">{{ o.year }} Term {{ o.term }}<span v-if="o.round"> · Round {{ o.round }}</span><span class="font-normal text-gray-500"> · {{ [o.subject_name, o.full_class_name].filter(Boolean).join(' · ') }}</span></p>
            <div class="flex items-center gap-2">
              <span v-if="o.total_score !== null" class="text-sm font-semibold tabular-nums text-gray-900">{{ Number(o.total_score) }}</span>
              <span v-if="o.performance_category || o.calculated_rating" class="code-badge">{{ o.performance_category || o.calculated_rating }}</span>
            </div>
          </div>
          <div class="mt-2 grid gap-3 text-sm md:grid-cols-3">
            <p v-if="o.strengths_observed"><span class="block text-xs font-semibold uppercase tracking-wide text-brand-700">Strengths</span>{{ o.strengths_observed }}</p>
            <p v-if="o.areas_for_improvement"><span class="block text-xs font-semibold uppercase tracking-wide text-amber-700">To improve</span>{{ o.areas_for_improvement }}</p>
            <p v-if="o.general_comment"><span class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Comment</span>{{ o.general_comment }}</p>
          </div>
        </li>
      </ul>
    </section>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
        <h2 class="card-title">Duty assessments</h2>
        <span v-if="data?.duty_average !== null && data?.duty_average !== undefined" class="text-xs text-gray-500">Average {{ data.duty_average }}%</span>
      </div>
      <div v-if="!loading && !data?.duty?.length" class="px-5 py-8 text-center text-sm text-gray-500">No duty assessments recorded yet.</div>
      <div v-else-if="data" class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Term / week</th><th>Punctuality</th><th>Supervision</th><th>Cleanliness</th><th>Time keeping</th><th>Participation</th><th>Score</th><th>Comment</th></tr></thead>
          <tbody>
            <tr v-for="(d, i) in data.duty" :key="i">
              <td class="whitespace-nowrap">{{ d.year }} T{{ d.term }} · Wk {{ d.week_number }}</td>
              <td v-for="k in ['punctuality', 'supervision', 'cleanliness', 'time_keeping', 'participation']" :key="k" class="tabular-nums">{{ d[k] ?? '-' }}</td>
              <td class="font-semibold tabular-nums">{{ d.percentage !== null ? `${Number(d.percentage)}%` : d.total_score }}</td>
              <td class="max-w-xs text-xs">{{ d.comment || d.general_remarks || '' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
        <h2 class="card-title">Lesson attendance by term</h2>
        <RouterLink to="/my/attendance" class="link-btn">Details</RouterLink>
      </div>
      <div v-if="!loading && !data?.attendance_by_term?.length" class="px-5 py-8 text-center text-sm text-gray-500">No lesson attendance recorded yet.</div>
      <div v-else-if="data" class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Term</th><th>Lessons</th><th>Expected (min)</th><th>Taught (min)</th><th>Lost (min)</th><th class="w-48">Time taught</th></tr></thead>
          <tbody>
            <tr v-for="a in data.attendance_by_term" :key="`${a.year}-${a.term}`">
              <td>{{ a.year }} Term {{ a.term }}</td>
              <td class="tabular-nums">{{ a.lessons }}</td>
              <td class="tabular-nums">{{ a.expected }}</td>
              <td class="tabular-nums">{{ a.actual }}</td>
              <td class="tabular-nums" :class="Number(a.lost) ? 'text-amber-700' : ''">{{ a.lost }}</td>
              <td>
                <div class="flex items-center gap-2">
                  <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-brand-600" :style="{ width: `${rate(a)}%` }"></div></div>
                  <span class="w-10 text-right text-xs tabular-nums">{{ rate(a) }}%</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Award, ClipboardCheck, Eye, RotateCcw } from 'lucide-vue-next';
import StatCards from '../../components/ui/StatCards.vue';
import { teacherPortalAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';

const data = ref(null);
const loading = ref(true);
const error = ref('');

const rate = (a) => (Number(a.expected) ? Math.max(0, Math.min(100, Math.round((Number(a.actual) / Number(a.expected)) * 100))) : 0);
const stats = computed(() => {
  const d = data.value;
  const latestObs = d?.observations?.[0];
  const term = d?.attendance_by_term?.[0];
  return [
    { label: 'Duty average', value: d?.duty_average !== null && d?.duty_average !== undefined ? `${d.duty_average}%` : '-', note: `${d?.duty?.length || 0} assessment(s)`, icon: Award },
    { label: 'Latest observation', value: latestObs ? (latestObs.performance_category || latestObs.calculated_rating || Number(latestObs.total_score)) : '-', note: latestObs ? `${latestObs.year} Term ${latestObs.term}` : 'None yet', icon: Eye, tone: 'sky' },
    { label: 'Time taught', value: term ? `${rate(term)}%` : '-', note: term ? `${term.year} Term ${term.term}` : 'No records', icon: ClipboardCheck, tone: 'amber' },
    { label: 'Minutes made up', value: d?.minutes_compensated ?? '-', note: 'Compensation lessons', icon: RotateCcw, tone: 'gray' },
  ];
});

onMounted(async () => {
  try {
    data.value = (await teacherPortalAPI.view('performance')).data;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
});
</script>
