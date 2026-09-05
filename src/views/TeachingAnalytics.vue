<template>
  <div class="space-y-5">
    <div class="rounded-3xl bg-gradient-to-r from-indigo-950 via-blue-900 to-slate-900 p-6 text-white shadow-xl">
      <h1 class="text-2xl font-bold">Teaching Analytics</h1>
      <p class="mt-1 text-sm text-blue-200">Workload, class allocation & lesson attendance</p>
      <div class="mt-4 flex flex-wrap gap-3">
        <select v-model="year" class="rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-sm" @change="load">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
        <select v-model="term" class="rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-sm" @change="load">
          <option :value="1">Term 1</option>
          <option :value="2">Term 2</option>
          <option :value="3">Term 3</option>
        </select>
        <button type="button" class="rounded-xl bg-blue-500 px-4 py-2 text-sm font-medium" @click="exportReport">Export PDF</button>
      </div>
    </div>

    <div v-if="loading" class="py-12 text-center text-slate-500">Loading analytics...</div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Total Teachers</p>
          <p class="text-3xl font-bold">{{ summary.total_teachers || 0 }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm text-slate-500">Active Teachers</p>
          <p class="text-3xl font-bold text-emerald-600">{{ summary.active_teachers || 0 }}</p>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold">Teacher Workload</h2>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Subjects</th>
                <th>Classes</th>
                <th>Load Est.</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="w in workload" :key="w.teacher_id">
                <td><span class="code-badge">{{ w.teacher_code }}</span></td>
                <td class="font-medium">{{ w.full_name }}</td>
                <td>{{ w.subjects_count }}</td>
                <td>{{ w.classes_count }}</td>
                <td>
                  <div class="flex items-center gap-2">
                    <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100">
                      <div class="h-full bg-blue-500" :style="{ width: loadBar(w.weekly_load_estimate) }" />
                    </div>
                    <span class="text-xs">{{ w.weekly_load_estimate }}</span>
                  </div>
                </td>
                <td><StatusPill :status="w.is_active ? 'active' : 'inactive'" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold">Lesson Attendance (Term)</h2>
        <table class="data-table">
          <thead>
            <tr>
              <th>Teacher</th>
              <th>Lessons Recorded</th>
              <th>Attended</th>
              <th>Rate</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in attendance" :key="a.teacher_id">
              <td>{{ a.full_name }}</td>
              <td>{{ a.lessons_recorded }}</td>
              <td>{{ a.lessons_attended }}</td>
              <td>{{ attendanceRate(a) }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { teachingAnalyticsAPI } from '../services/api.js';
import { useExport } from '../composables/useExport.js';
import StatusPill from '../components/hr/StatusPill.vue';

const { exportToPdf } = useExport();
const loading = ref(true);
const year = ref(new Date().getFullYear());
const term = ref(1);
const years = [year.value - 1, year.value, year.value + 1];
const workload = ref([]);
const attendance = ref([]);
const summary = ref({});

const loadBar = (load) => `${Math.min(100, (load / 20) * 100)}%`;
const attendanceRate = (a) => {
  const r = parseInt(a.lessons_recorded, 10) || 0;
  const att = parseInt(a.lessons_attended, 10) || 0;
  return r ? Math.round((att / r) * 100) : 0;
};

const load = async () => {
  loading.value = true;
  try {
    const res = await teachingAnalyticsAPI.get(year.value, term.value);
    if (res.success) {
      workload.value = res.data.workload || [];
      attendance.value = res.data.attendance || [];
      summary.value = res.data.summary || {};
    }
  } finally {
    loading.value = false;
  }
};

const exportReport = () => {
  exportToPdf(
    `Teaching Analytics ${year.value} T${term.value}`,
    [
      { key: 'teacher_code', label: 'Code' },
      { key: 'full_name', label: 'Name' },
      { key: 'weekly_load_estimate', label: 'Load' }
    ],
    workload.value,
    'teaching-analytics'
  );
};

onMounted(load);
</script>


