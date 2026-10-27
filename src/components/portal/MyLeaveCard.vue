<template>
  <section class="card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3">
      <div>
        <h2 class="card-title">My leave</h2>
      </div>
      <div class="flex items-center gap-2">
        <RouterLink to="/my/leave" class="text-sm font-medium text-brand-700 hover:underline">Open</RouterLink>
        <RouterLink v-if="data?.linked" :to="{ path: '/my/leave', query: { request: '1' } }" class="btn-primary px-3 py-1.5 text-sm"><PlaneTakeoff class="h-4 w-4" />Request leave</RouterLink>
      </div>
    </div>

    <div v-if="loading" class="px-5 py-8 text-center text-sm text-gray-500">Loading…</div>
    <div v-else-if="error" class="px-5 py-6 text-sm text-red-600">{{ error }}</div>
    <div v-else-if="!data.linked" class="px-5 py-6 text-sm text-amber-700">Your teacher record is not linked to a staff record yet, so leave cannot be requested here. Ask HR to link it.</div>
    <template v-else>
      <div v-if="data.balances.length" class="flex flex-wrap gap-2 border-b border-gray-100 px-5 py-3">
        <span v-for="b in data.balances" :key="b.leave_type" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs ring-1" :class="leaveType(b.leave_type).tile">
          <component :is="leaveType(b.leave_type).icon" class="h-3.5 w-3.5" /><span class="font-semibold">{{ leaveType(b.leave_type).label }}</span> {{ num(b.remaining_days) }} of {{ num(b.entitled_days) }} days left
        </span>
      </div>
      <div v-if="!data.requests.length" class="px-5 py-8 text-center text-sm text-gray-500">You have not requested any leave.</div>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="r in data.requests.slice(0, 3)" :key="r.id" class="flex items-center gap-3 px-5 py-3">
          <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg ring-1" :class="leaveType(r.leave_type).tile"><component :is="leaveType(r.leave_type).icon" class="h-4 w-4" /></span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-900">{{ leaveType(r.leave_type).label }} · {{ num(r.days) }} day{{ Number(r.days) === 1 ? '' : 's' }}</p>
            <p class="text-xs text-gray-500">{{ dateRange(r) }}</p>
          </div>
          <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="leaveStatus(r.status).pill"><span class="h-1.5 w-1.5 rounded-full" :class="leaveStatus(r.status).dot"></span>{{ r.status === 'pending' ? 'Waiting for HR' : leaveStatus(r.status).label }}</span>
        </li>
      </ul>
    </template>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { PlaneTakeoff } from 'lucide-vue-next';
import { teacherPortalAPI } from '../../services/api.js';
import { apiError } from '../../utils/timetable.js';
import { dateRange, leaveStatus, leaveType } from '../../utils/leaveMeta.js';

const num = (v) => Number(v || 0).toString();
const data = ref(null);
const loading = ref(true);
const error = ref('');

onMounted(async () => {
  try {
    data.value = (await teacherPortalAPI.view('leave')).data;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
});
</script>
