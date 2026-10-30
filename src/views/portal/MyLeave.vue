<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">My Leave</h1>
        </div>
        <button type="button" class="btn-hero-primary" :disabled="!data?.linked" @click="openForm"><PlaneTakeoff class="h-4 w-4" />Request leave</button>
      </div>
    </div>

    <div v-if="loading" class="grid gap-3 sm:grid-cols-3">
      <div v-for="i in 3" :key="i" class="card h-28 animate-pulse"></div>
    </div>
    <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>
    <div v-else-if="!data.linked" class="card flex items-start gap-3 p-5">
      <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200"><CircleAlert class="h-5 w-5" /></span>
      <div>
        <p class="font-semibold text-gray-900">Leave isn't set up for your account yet</p>
        <p class="text-sm text-gray-600">Your teacher record is not linked to a staff record, so leave cannot be requested here. Ask HR to link it.</p>
      </div>
    </div>

    <template v-else>
      <!-- Now / next -->
      <section v-if="current" class="card flex items-center gap-4 p-4">
        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ring-1" :class="leaveType(current.leave_type).tile"><component :is="leaveType(current.leave_type).icon" class="h-5 w-5" /></span>
        <div class="min-w-0">
          <p class="text-xs font-medium text-gray-500">{{ current.start_date <= today ? 'You are on leave' : 'Your next leave' }}</p>
          <p class="text-base font-semibold text-gray-900">{{ leaveType(current.leave_type).label }} leave, {{ dateRange(current) }}</p>
          <p class="text-sm text-brand-700">{{ leaveTiming(current, today).label }} · {{ num(current.days) }} day{{ Number(current.days) === 1 ? '' : 's' }}</p>
        </div>
      </section>

      <!-- Balances -->
      <section>
        <h2 class="mb-2 text-sm font-semibold text-gray-900">Days left in {{ data.year }}</h2>
        <div v-if="data.balances.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="b in data.balances" :key="b.leave_type" class="card p-4">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-xl ring-1" :class="leaveType(b.leave_type).tile"><component :is="leaveType(b.leave_type).icon" class="h-5 w-5" /></span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-700">{{ leaveType(b.leave_type).label }}</p>
                <p class="text-2xl font-bold tabular-nums text-gray-900">{{ num(b.remaining_days) }}<span class="text-sm font-medium text-gray-500"> days left</span></p>
              </div>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full" :class="leaveType(b.leave_type).bar" :style="{ width: `${usedPct(b)}%` }"></div></div>
            <p class="mt-1.5 text-xs text-gray-500">{{ num(b.used_days) }} of {{ num(b.entitled_days) }} days used</p>
          </div>
        </div>
        <p v-else class="card px-5 py-4 text-sm text-gray-600">No leave days are set for you for {{ data.year }} yet. HR adds them when your first leave is approved, so you can still send a request.</p>
      </section>

      <!-- Requests -->
      <section class="card overflow-hidden">
        <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
          <p class="font-semibold text-gray-900">My requests</p>
          <div class="inline-flex rounded-xl bg-gray-100 p-1 text-xs" role="tablist" aria-label="Show requests">
            <button v-for="f in FILTERS" :key="f.value" type="button" role="tab" :aria-selected="filter === f.value" class="rounded-lg px-2.5 py-1 font-medium transition" :class="filter === f.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'" @click="filter = f.value">{{ f.label }}</button>
          </div>
        </header>
        <div v-if="!shown.length" class="px-5 py-12 text-center">
          <PlaneTakeoff class="mx-auto h-9 w-9 text-gray-300" />
          <p class="mt-2 text-sm font-medium text-gray-700">{{ data.requests.length ? 'Nothing here' : 'You have not asked for leave yet' }}</p>
          <button v-if="!data.requests.length" type="button" class="mt-3 text-sm font-medium text-brand-700 hover:underline" @click="openForm">Request leave</button>
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li v-for="r in shown" :key="r.id" class="flex items-start gap-3 px-4 py-3.5 sm:px-5" :class="r.status === 'pending' ? 'is-pending' : ''">
            <span class="date-tile"><span class="date-tile-m">{{ monthShort(r.start_date) }}</span><span class="date-tile-d">{{ dayOfMonth(r.start_date) }}</span></span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="inline-flex items-center gap-1.5 font-semibold text-gray-900"><component :is="leaveType(r.leave_type).icon" class="h-4 w-4 text-gray-500" />{{ leaveType(r.leave_type).label }} leave</span>
                <span class="status-pill" :class="leaveStatus(r.status).pill"><span class="h-1.5 w-1.5 rounded-full" :class="leaveStatus(r.status).dot"></span>{{ r.status === 'pending' ? 'Waiting for HR' : leaveStatus(r.status).label }}</span>
              </div>
              <p class="mt-0.5 text-sm text-gray-600">{{ dateRange(r) }} · {{ num(r.days) }} day{{ Number(r.days) === 1 ? '' : 's' }}<template v-if="r.status === 'approved' && r.end_date >= today"> · <span :class="leaveTiming(r, today).tone">{{ leaveTiming(r, today).label }}</span></template></p>
              <p v-if="r.reason" class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ r.reason }}</p>
            </div>
            <div class="flex flex-shrink-0 items-center gap-1">
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800" @click="viewing = r"><Eye class="h-3.5 w-3.5" />View</button>
              <button v-if="r.status === 'pending'" type="button" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-red-50 hover:text-red-600" :disabled="busy" @click="cancel(r)"><Undo2 class="h-3.5 w-3.5" />Withdraw</button>
            </div>
          </li>
        </ul>
      </section>
    </template>

    <LeaveRequestDialog
      v-if="showForm"
      :types="data?.types || undefined"
      :busy="busy"
      :errors="errors"
      :form-error="formError"
      reason-required
      submit-label="Send to HR"
      @close="showForm = false"
      @submit="submit"
    />

    <!-- One request, in full -->
    <Teleport to="body">
      <div v-if="viewing" class="fixed inset-0 z-[60] flex items-end justify-center bg-gray-900/60 backdrop-blur-sm sm:items-center sm:p-4" @click.self="viewing = null">
        <div class="w-full max-w-lg overflow-hidden bg-white shadow-2xl sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="leave-view-title">
          <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ring-1" :class="leaveType(viewing.leave_type).tile"><component :is="leaveType(viewing.leave_type).icon" class="h-5 w-5" /></span>
            <div class="min-w-0 flex-1">
              <h2 id="leave-view-title" class="text-lg font-semibold text-gray-900">{{ leaveType(viewing.leave_type).label }} leave</h2>
              <span class="status-pill mt-1" :class="leaveStatus(viewing.status).pill"><span class="h-1.5 w-1.5 rounded-full" :class="leaveStatus(viewing.status).dot"></span>{{ viewing.status === 'pending' ? 'Waiting for HR' : leaveStatus(viewing.status).label }}</span>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="viewing = null"><X class="h-5 w-5" /></button>
          </header>
          <div class="space-y-4 px-5 py-4">
            <dl class="grid grid-cols-2 gap-3 text-sm">
              <div class="rounded-xl bg-gray-50 px-3.5 py-2.5"><dt class="text-xs text-gray-500">From</dt><dd class="font-semibold text-gray-900">{{ longDate(viewing.start_date) }}</dd></div>
              <div class="rounded-xl bg-gray-50 px-3.5 py-2.5"><dt class="text-xs text-gray-500">To</dt><dd class="font-semibold text-gray-900">{{ longDate(viewing.end_date) }}</dd></div>
              <div class="rounded-xl bg-gray-50 px-3.5 py-2.5"><dt class="text-xs text-gray-500">Days</dt><dd class="font-semibold text-gray-900">{{ num(viewing.days) }} day{{ Number(viewing.days) === 1 ? '' : 's' }}</dd></div>
              <div class="rounded-xl bg-gray-50 px-3.5 py-2.5"><dt class="text-xs text-gray-500">Requested</dt><dd class="font-semibold text-gray-900">{{ viewing.created_at ? longDate(viewing.created_at) : '-' }}</dd></div>
            </dl>
            <div>
              <p class="text-xs font-medium text-gray-500">Your message</p>
              <p class="mt-1 whitespace-pre-line rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-sm leading-relaxed text-gray-800">{{ viewing.reason || 'No message was written.' }}</p>
            </div>
            <p v-if="viewing.status !== 'pending' && viewing.status !== 'cancelled'" class="text-sm text-gray-600">
              {{ leaveStatus(viewing.status).label }}<template v-if="viewing.approved_by_name"> by <strong class="text-gray-900">{{ viewing.approved_by_name }}</strong></template><template v-if="viewing.approved_at"> on {{ longDate(viewing.approved_at) }}</template>.
            </p>
            <p v-else-if="viewing.status === 'pending'" class="text-sm text-amber-800">HR has not decided yet. You can withdraw it while it is waiting.</p>
          </div>
          <footer class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
            <button v-if="viewing.status === 'pending'" type="button" class="btn-secondary text-red-600" :disabled="busy" @click="cancel(viewing); viewing = null"><Undo2 class="h-4 w-4" />Withdraw</button>
            <button type="button" class="btn-primary" @click="viewing = null">Close</button>
          </footer>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { CircleAlert, Eye, PlaneTakeoff, Undo2, X } from 'lucide-vue-next';
import ToastBanner from '../../components/hr/ToastBanner.vue';
import LeaveRequestDialog from '../../components/leave/LeaveRequestDialog.vue';
import { teacherPortalAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';
import { dateRange, dayOfMonth, leaveStatus, leaveTiming, leaveType, longDate, monthShort, todayISO } from '../../utils/leaveMeta.js';

const FILTERS = [{ value: 'all', label: 'All' }, { value: 'pending', label: 'Waiting' }, { value: 'approved', label: 'Approved' }];

const { showToast } = useToast();
const data = ref(null);
const loading = ref(true);
const error = ref('');
const busy = ref(false);
const showForm = ref(false);
const errors = ref({});
const formError = ref('');
const filter = ref('all');
const today = todayISO();

const num = (v) => Number(v || 0).toString();
const usedPct = (b) => (Number(b.entitled_days) ? Math.min(100, Math.round((Number(b.used_days) / Number(b.entitled_days)) * 100)) : 0);
// The request opened with View.
const viewing = ref(null);
const shown = computed(() => (data.value?.requests || []).filter((r) => filter.value === 'all' || r.status === filter.value));
// Leave happening now, or the next approved leave.
const current = computed(() => (data.value?.requests || [])
  .filter((r) => r.status === 'approved' && r.end_date >= today)
  .sort((a, b) => String(a.start_date).localeCompare(String(b.start_date)))[0] || null);

async function load() {
  try {
    data.value = (await teacherPortalAPI.view('leave')).data;
  } catch (e) {
    error.value = apiError(e).message;
  } finally {
    loading.value = false;
  }
}

function openForm() {
  errors.value = {};
  formError.value = '';
  showForm.value = true;
}

async function submit(payload) {
  busy.value = true;
  errors.value = {};
  formError.value = '';
  try {
    showToast((await teacherPortalAPI.requestLeave(payload)).message);
    showForm.value = false;
    await load();
  } catch (e) {
    const err = apiError(e);
    errors.value = err.errors || {};
    formError.value = Object.keys(errors.value).length ? '' : err.message;
  } finally {
    busy.value = false;
  }
}

async function cancel(r) {
  if (!confirm('Withdraw this leave request?')) return;
  busy.value = true;
  try {
    showToast((await teacherPortalAPI.cancelLeave(r.id)).message);
    await load();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    busy.value = false;
  }
}

const route = useRoute();
const router = useRouter();
onMounted(async () => {
  await load();
  // Arrived from the dashboard's Request leave button.
  if (route.query.request === '1') {
    router.replace({ path: '/my/leave' });
    if (data.value?.linked) openForm();
  }
});
</script>

<style scoped>
.status-pill { @apply inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold; }
.date-tile { @apply flex h-11 w-11 flex-shrink-0 flex-col items-center justify-center overflow-hidden rounded-xl bg-white text-center ring-1 ring-gray-200; }
.date-tile-m { @apply w-full bg-brand-600 text-[9px] font-semibold uppercase leading-4 tracking-wide text-white; }
.date-tile-d { @apply flex-1 pt-0.5 text-sm font-bold leading-5 text-gray-900; }
.is-pending { box-shadow: inset 3px 0 0 #f59e0b; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .date-tile {
  background-color: var(--d-surface);
  --tw-ring-color: var(--d-line-strong);
}
html.dark .date-tile-d {
  color: var(--d-text);
}
</style>
