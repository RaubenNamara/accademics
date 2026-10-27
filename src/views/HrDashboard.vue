<template>
  <div class="page">
    <div class="page-hero">
      <h1 class="page-hero-title">{{ greeting }}</h1>
    </div>

    <div v-if="loading" class="card space-y-3 p-5">
      <div v-for="i in 4" :key="i" class="h-4 animate-pulse rounded bg-gray-100"></div>
    </div>

    <template v-else>
      <StatCards :cards="kpiCards" />

      <div class="grid gap-4 lg:grid-cols-2">
        <section class="card overflow-hidden">
          <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
            <p class="font-semibold text-gray-900">Departments</p>
            <router-link to="/departments" class="text-sm font-medium text-brand-700 hover:underline">Manage</router-link>
          </header>
          <div v-if="!stats.departments?.length" class="px-5 py-10 text-center text-sm text-gray-500">No department data</div>
          <ul v-else class="space-y-2.5 p-4 sm:p-5">
            <li v-for="d in stats.departments" :key="d.name" class="grid grid-cols-[minmax(0,9rem)_1fr_2.5rem] items-center gap-3 text-sm">
              <span class="truncate font-medium text-gray-700" :title="d.name">{{ d.name }}</span>
              <span class="h-1.5 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full bg-brand-500" :style="{ width: deptBarWidth(d.staff_count) }"></span></span>
              <span class="text-right tabular-nums font-semibold text-gray-900">{{ d.staff_count }}</span>
            </li>
          </ul>
        </section>

        <section class="card overflow-hidden">
          <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
            <p class="font-semibold text-gray-900">Leave</p>
            <router-link to="/leave" class="text-sm font-medium text-brand-700 hover:underline">Open leave</router-link>
          </header>
          <dl class="grid grid-cols-3 gap-px bg-gray-100">
            <div class="bg-white p-4"><dt class="text-xs text-gray-500">Pending</dt><dd class="text-2xl font-semibold text-amber-600">{{ stats.leave_summary?.pending || 0 }}</dd></div>
            <div class="bg-white p-4"><dt class="text-xs text-gray-500">Approved</dt><dd class="text-2xl font-semibold text-emerald-600">{{ stats.leave_summary?.approved || 0 }}</dd></div>
            <div class="bg-white p-4"><dt class="text-xs text-gray-500">Rejected</dt><dd class="text-2xl font-semibold text-gray-700">{{ stats.leave_summary?.rejected || 0 }}</dd></div>
          </dl>
          <p class="px-4 py-3 text-xs text-gray-500 sm:px-5">
            <template v-if="stats.leave_summary?.pending">{{ stats.leave_summary.pending }} request{{ stats.leave_summary.pending === 1 ? '' : 's' }} waiting for a decision.</template>
            <template v-else>No leave requests are waiting.</template>
          </p>
        </section>
      </div>

      <section class="card overflow-hidden">
        <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
          <p class="font-semibold text-gray-900">Recently added staff</p>
          <router-link to="/teachers" class="text-sm font-medium text-brand-700 hover:underline">View all</router-link>
        </header>
        <div v-if="!stats.recent_staff?.length" class="px-5 py-10 text-center text-sm text-gray-500">No staff added yet</div>
        <table v-else class="w-full text-left text-sm">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Staff member</th>
              <th class="hidden px-4 py-3 sm:table-cell">Type</th>
              <th class="hidden px-4 py-3 md:table-cell">Department</th>
              <th class="px-4 py-3">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="s in stats.recent_staff" :key="s.id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(`${s.first_name || ''} ${s.last_name || ''}`) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900">{{ s.first_name }} {{ s.last_name }}</span>
                    <span class="block font-mono text-[11px] text-gray-500">{{ s.hr_code }}</span>
                  </span>
                </div>
              </td>
              <td class="hidden px-4 py-3 capitalize text-gray-700 sm:table-cell">{{ (s.staff_type || 'teaching').replace('_', ' ') }}</td>
              <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ s.department_name || '-' }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium capitalize" :class="statusClass(s.status)"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ String(s.status || '').replace('_', ' ') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { hrDashboardAPI } from '../services/api.js';
import authStore from '../services/authStore.js';
import { useRouter } from 'vue-router';
import { Users as UsersIcon, GraduationCap, UserRound, BadgeCheck } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';

// Same opening as the eSpace dashboard: the date, then a greeting by name.
const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
const greeting = computed(() => {
  const h = new Date().getHours();
  const part = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  const first = (authStore.user?.full_name || '').trim().split(/\s+/)[0];
  return first ? `${part}, ${first}` : part;
});

const loading = ref(true);
const stats = ref({});

const router = useRouter();
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const kpiCards = computed(() => [
  { label: 'Total staff', value: String(stats.value.total_staff || 0), icon: UsersIcon },
  { label: 'Teaching staff', value: String(stats.value.teaching_staff || 0), icon: GraduationCap, tone: 'sky', onClick: () => router.push('/teachers') },
  { label: 'Non-teaching', value: String(stats.value.non_teaching_staff || 0), icon: UserRound, tone: 'amber', onClick: () => router.push('/non-teaching-staff') },
  { label: 'Active staff', value: String(stats.value.active_staff || 0), icon: BadgeCheck, tone: 'gray' }
]);

const maxDept = computed(() => Math.max(1, ...(stats.value.departments || []).map((d) => +d.staff_count)));

const deptBarWidth = (count) => `${Math.round((+count / maxDept.value) * 100)}%`;

const statusClass = (s) => {
  if (s === 'active') return 'text-emerald-700';
  if (s === 'on_leave') return 'text-amber-700';
  return 'text-gray-500';
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


