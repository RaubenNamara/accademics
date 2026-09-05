<template>
  <div class="space-y-6">
    <div class="rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-6 text-white shadow-2xl">
      <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-200">School HR Management</p>
      <h1 class="mt-2 text-3xl font-bold">Staff Overview</h1>
      <p class="mt-2 text-sm text-slate-300">Teaching & non-teaching staff, payroll, leave, and departments</p>
    </div>

    <div v-if="loading" class="rounded-2xl bg-white p-8 text-center text-slate-500">Loading HR data...</div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="card in kpiCards" :key="card.label" class="kpi">
          <p class="text-sm text-slate-500">{{ card.label }}</p>
          <p class="mt-2 text-3xl font-bold" :class="card.color">{{ card.value }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <div class="panel">
          <h2 class="panel-title">Department Statistics</h2>
          <div v-if="!stats.departments?.length" class="py-8 text-center text-slate-400">No department data</div>
          <div v-else class="space-y-3">
            <div v-for="d in stats.departments" :key="d.name" class="flex items-center gap-3">
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-blue-500 to-sky-400"
                  :style="{ width: deptBarWidth(d.staff_count) }"
                />
              </div>
              <span class="w-28 truncate text-sm font-medium text-slate-700">{{ d.name }}</span>
              <span class="text-sm font-semibold text-slate-900">{{ d.staff_count }}</span>
            </div>
          </div>
        </div>

        <div class="panel">
          <h2 class="panel-title">Payroll & Leave</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4">
              <p class="text-xs uppercase text-emerald-700">Payroll (this month)</p>
              <p class="mt-1 text-2xl font-bold text-emerald-800">
                {{ formatMoney(stats.payroll_summary?.total_net) }}
              </p>
              <p class="text-xs text-emerald-600">{{ stats.payroll_summary?.records || 0 }} payslips</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4">
              <p class="text-xs uppercase text-amber-700">Pending leave</p>
              <p class="mt-1 text-2xl font-bold text-amber-800">{{ stats.leave_summary?.pending || 0 }}</p>
              <p class="text-xs text-amber-600">
                Approved: {{ stats.leave_summary?.approved || 0 }} · Rejected: {{ stats.leave_summary?.rejected || 0 }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="panel-title mb-0">Recently Added Staff</h2>
          <router-link to="/teachers" class="text-sm font-medium text-blue-600 hover:underline">View all</router-link>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-slate-500">
              <tr>
                <th class="pb-3 pr-4">Code</th>
                <th class="pb-3 pr-4">Name</th>
                <th class="pb-3 pr-4">Type</th>
                <th class="pb-3 pr-4">Department</th>
                <th class="pb-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in stats.recent_staff" :key="s.id" class="border-b border-slate-50">
                <td class="py-3 font-mono text-xs text-blue-700">{{ s.hr_code }}</td>
                <td class="py-3 font-medium">{{ s.first_name }} {{ s.last_name }}</td>
                <td class="py-3 capitalize">{{ (s.staff_type || 'teaching').replace('_', ' ') }}</td>
                <td class="py-3 text-slate-600">{{ s.department_name || '—' }}</td>
                <td class="py-3">
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(s.status)">
                    {{ s.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { hrDashboardAPI } from '../services/api.js';

const loading = ref(true);
const stats = ref({});

const kpiCards = computed(() => [
  { label: 'Total Staff', value: stats.value.total_staff || 0, color: 'text-slate-900' },
  { label: 'Teaching Staff', value: stats.value.teaching_staff || 0, color: 'text-blue-600' },
  { label: 'Non-Teaching', value: stats.value.non_teaching_staff || 0, color: 'text-blue-600' },
  { label: 'Active Staff', value: stats.value.active_staff || 0, color: 'text-emerald-600' }
]);

const maxDept = computed(() => Math.max(1, ...(stats.value.departments || []).map((d) => +d.staff_count)));

const deptBarWidth = (count) => `${Math.round((+count / maxDept.value) * 100)}%`;

const formatMoney = (n) => {
  const v = parseFloat(n) || 0;
  return new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX', maximumFractionDigits: 0 }).format(v);
};

const statusClass = (s) => {
  if (s === 'active') return 'bg-emerald-100 text-emerald-800';
  if (s === 'on_leave') return 'bg-amber-100 text-amber-800';
  return 'bg-slate-100 text-slate-600';
};

onMounted(async () => {
  try {
    const res = await hrDashboardAPI.getData(new Date().getFullYear());
    if (res.success) stats.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.kpi {
  @apply rounded-3xl border border-slate-200 bg-white p-5 shadow-sm;
}
.panel {
  @apply rounded-3xl border border-slate-200 bg-white p-6 shadow-sm;
}
.panel-title {
  @apply mb-4 text-lg font-semibold text-slate-900;
}
</style>

