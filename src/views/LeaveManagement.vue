<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Leave</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero px-3" :disabled="loading" title="Refresh" aria-label="Refresh" @click="reload"><RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /></button>
          <button type="button" class="btn-hero-primary" @click="openRequest"><Plus class="h-4 w-4" />New request</button>
        </div>
      </div>
    </div>

    <StatCards :cards="stats" />

    <!-- Who is away this week -->
    <section class="card overflow-hidden">
      <header class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <div class="flex items-center gap-2">
          <Plane class="h-4 w-4 text-brand-600" />
          <p class="font-semibold text-gray-900">Away this week</p>
        </div>
        <p class="text-xs text-gray-500">{{ shortDate(today) }} – {{ shortDate(weekEnd) }}</p>
      </header>
      <p v-if="!awayThisWeek.length" class="px-5 py-4 text-sm text-gray-500">Everyone is in this week.</p>
      <ul v-else class="flex gap-3 overflow-x-auto px-4 py-3 sm:px-5">
        <li v-for="r in awayThisWeek" :key="r.id">
          <button type="button" class="flex w-56 items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-left transition hover:border-brand-200 hover:bg-brand-50/40" @click="openDetail(r)">
            <span class="relative">
              <span class="avatar">{{ initials(`${r.first_name} ${r.last_name}`) }}</span>
              <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full ring-2 ring-white" :class="leaveType(r.leave_type).tile"><component :is="leaveType(r.leave_type).icon" class="h-3 w-3" /></span>
            </span>
            <span class="min-w-0">
              <span class="block truncate text-sm font-semibold text-gray-900">{{ r.first_name }} {{ r.last_name }}</span>
              <span class="block truncate text-xs" :class="leaveTiming(r, today).tone">{{ r.start_date <= today ? `Back after ${shortDate(r.end_date)}` : `From ${shortDate(r.start_date)}` }}</span>
            </span>
          </button>
        </li>
      </ul>
    </section>

    <ListToolbar
      v-model:search="q"
      :placeholder="tab === 'requests' ? 'Search name, code or reason' : 'Search name'"
      :filter-count="[status, type].filter(Boolean).length"
      @clear="status = ''; type = ''"
    >
      <template #start>
        <div class="inline-flex rounded-xl bg-gray-100 p-1" role="tablist" aria-label="Leave view">
          <button v-for="t in TABS" :key="t.value" type="button" role="tab" :aria-selected="tab === t.value" class="rounded-lg px-3 py-1.5 text-sm font-medium transition" :class="tab === t.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'" @click="switchTab(t.value)">
            {{ t.label }}<span v-if="t.value === 'requests' && pendingCount" class="ml-1.5 rounded-full bg-amber-500 px-1.5 text-[11px] font-semibold text-white">{{ pendingCount }}</span>
          </button>
        </div>
      </template>
      <template #filters>
        <label v-if="tab === 'requests'" class="block">
          <span class="filter-label">Status</span>
          <select v-model="status" class="input py-2">
            <option value="">Any status</option>
            <option v-for="(v, k) in LEAVE_STATUS" :key="k" :value="k">{{ v.label }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Type</span>
          <select v-model="type" class="input py-2">
            <option value="">All types</option>
            <option v-for="t in TYPES" :key="t" :value="t">{{ leaveType(t).label }}</option>
          </select>
        </label>
      </template>
    </ListToolbar>

    <!-- Requests -->
    <section v-if="tab === 'requests'" class="card overflow-hidden">
      <div v-if="loading && !requests.length" class="space-y-3 p-5">
        <div v-for="i in 4" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="!shownRequests.length" class="px-5 py-14 text-center">
        <PlaneTakeoff class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">{{ requests.length ? 'No requests match' : 'No leave requests yet' }}</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Staff member</th>
              <th class="px-4 py-3">Leave</th>
              <th class="px-4 py-3">When</th>
              <th class="px-4 py-3">Status</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in shownRequests" :key="r.id" class="row transition hover:bg-brand-50/40" :class="r.status === 'pending' ? 'is-pending' : ''">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <span class="avatar">{{ initials(`${r.first_name} ${r.last_name}`) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900">{{ r.first_name }} {{ r.last_name }}</span>
                    <span class="block text-xs text-gray-500">{{ r.hr_code }} · {{ r.staff_type === 'teaching' ? 'Teaching' : 'Non-teaching' }}</span>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2.5">
                  <span class="type-tile" :class="leaveType(r.leave_type).tile"><component :is="leaveType(r.leave_type).icon" class="h-4 w-4" /></span>
                  <span class="min-w-0">
                    <span class="block font-medium text-gray-800">{{ leaveType(r.leave_type).label }}</span>
                    <span v-if="r.reason" class="block max-w-[16rem] truncate text-xs text-gray-500" :title="r.reason">{{ r.reason }}</span>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <span class="date-tile"><span class="date-tile-m">{{ monthShort(r.start_date) }}</span><span class="date-tile-d">{{ dayOfMonth(r.start_date) }}</span></span>
                  <span class="min-w-0">
                    <span class="block whitespace-nowrap text-gray-800">{{ dateRange(r) }} · {{ num(r.days) }} day{{ Number(r.days) === 1 ? '' : 's' }}</span>
                    <span v-if="r.status === 'approved' || r.status === 'pending'" class="block text-xs" :class="leaveTiming(r, today).tone">{{ leaveTiming(r, today).label }}</span>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3"><span class="status-pill" :class="leaveStatus(r.status).pill"><span class="h-1.5 w-1.5 rounded-full" :class="leaveStatus(r.status).dot"></span>{{ leaveStatus(r.status).label }}</span></td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${r.first_name} ${r.last_name}`" :items="requestActions(r)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="r in shownRequests" :key="r.id" class="row flex items-start gap-3 px-4 py-3" :class="r.status === 'pending' ? 'is-pending' : ''">
            <div class="flex min-w-0 flex-1 items-start gap-3">
              <span class="date-tile"><span class="date-tile-m">{{ monthShort(r.start_date) }}</span><span class="date-tile-d">{{ dayOfMonth(r.start_date) }}</span></span>
              <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold text-gray-900">{{ r.first_name }} {{ r.last_name }}</span>
                <span class="block text-xs text-gray-500">{{ leaveType(r.leave_type).label }} · {{ num(r.days) }} day{{ Number(r.days) === 1 ? '' : 's' }} · {{ dateRange(r) }}</span>
                <span class="status-pill mt-1" :class="leaveStatus(r.status).pill"><span class="h-1.5 w-1.5 rounded-full" :class="leaveStatus(r.status).dot"></span>{{ leaveStatus(r.status).label }}</span>
              </span>
            </div>
            <RowActionsMenu :label="`Actions for ${r.first_name} ${r.last_name}`" :items="requestActions(r)" />
          </li>
        </ul>
      </template>
    </section>

    <!-- Balances -->
    <section v-else class="card overflow-hidden">
      <div v-if="loading && !balances.length" class="space-y-3 p-5">
        <div v-for="i in 4" :key="i" class="h-8 animate-pulse rounded bg-gray-100"></div>
      </div>
      <div v-else-if="!shownBalances.length" class="px-5 py-14 text-center">
        <CalendarDays class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No balances for {{ year }} yet. They start when leave is approved.</p>
      </div>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="b in shownBalances" :key="b.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-5">
          <div class="flex min-w-0 flex-1 items-center gap-3 sm:w-64 sm:flex-none">
            <span class="avatar">{{ initials(`${b.first_name} ${b.last_name}`) }}</span>
            <span class="min-w-0">
              <span class="block truncate font-semibold text-gray-900">{{ b.first_name }} {{ b.last_name }}</span>
              <span class="block font-mono text-[11px] text-gray-500">{{ b.hr_code }}</span>
            </span>
          </div>
          <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1" :class="leaveType(b.leave_type).tile"><component :is="leaveType(b.leave_type).icon" class="h-3.5 w-3.5" />{{ leaveType(b.leave_type).label }}</span>
          <div class="order-last flex w-full items-center gap-3 sm:order-none sm:w-auto sm:flex-1">
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full" :class="usedPct(b) > 85 ? 'bg-amber-500' : leaveType(b.leave_type).bar" :style="{ width: `${usedPct(b)}%` }"></div></div>
            <span class="w-24 text-right text-xs tabular-nums text-gray-600">{{ num(b.used_days) }} of {{ num(b.entitled_days) }} used</span>
          </div>
          <span class="w-20 text-right font-semibold tabular-nums" :class="Number(b.remaining_days) <= 2 ? 'text-amber-700' : 'text-brand-700'">{{ num(b.remaining_days) }} left</span>
          <RowActionsMenu :label="`Actions for ${b.first_name} ${b.last_name}`" :items="[{ label: 'Delete balance', icon: Trash2, danger: true, onClick: () => deleteBalance(b.id) }]" />
        </li>
      </ul>
    </section>

    <!-- ⋮ › View: the request's details -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="closeDetail" @keydown.esc="closeDetail">
      <div class="flex max-h-[100dvh] w-full max-w-lg flex-col overflow-hidden bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-2xl" role="dialog" aria-modal="true" aria-label="Leave request">
          <header class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
            <span class="avatar h-10 w-10">{{ initials(`${detail.first_name} ${detail.last_name}`) }}</span>
            <div class="min-w-0 flex-1">
              <h3 class="truncate font-semibold text-gray-900">{{ detail.first_name }} {{ detail.last_name }}</h3>
              <p class="text-xs text-gray-500">{{ detail.hr_code }} · {{ detail.staff_type === 'teaching' ? 'Teaching staff' : 'Non-teaching staff' }}</p>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="closeDetail"><X class="h-5 w-5" /></button>
          </header>

          <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5">
            <div class="rounded-2xl p-4 ring-1" :class="leaveType(detail.leave_type).tile">
              <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/70"><component :is="leaveType(detail.leave_type).icon" class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                  <p class="font-semibold">{{ leaveType(detail.leave_type).label }} leave</p>
                  <p class="text-sm opacity-80">{{ num(detail.days) }} day{{ Number(detail.days) === 1 ? '' : 's' }}</p>
                </div>
                <span class="status-pill bg-white" :class="leaveStatus(detail.status).pill"><span class="h-1.5 w-1.5 rounded-full" :class="leaveStatus(detail.status).dot"></span>{{ leaveStatus(detail.status).label }}</span>
              </div>
              <p class="mt-3 text-sm">{{ longDate(detail.start_date) }}<template v-if="detail.end_date !== detail.start_date"> to {{ longDate(detail.end_date) }}</template></p>
              <p v-if="detail.status === 'approved' || detail.status === 'pending'" class="mt-0.5 text-xs font-medium" :class="leaveTiming(detail, today).tone">{{ leaveTiming(detail, today).label }}</p>
            </div>

            <section>
              <p class="detail-label">Reason</p>
              <p class="whitespace-pre-line text-sm text-gray-800">{{ detail.reason || 'No reason given.' }}</p>
            </section>

            <section v-if="detailBalance">
              <p class="detail-label">{{ leaveType(detail.leave_type).label }} balance, {{ year }}</p>
              <div class="flex items-center gap-3">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full" :class="leaveType(detail.leave_type).bar" :style="{ width: `${usedPct(detailBalance)}%` }"></div></div>
                <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-gray-900">{{ num(detailBalance.remaining_days) }} of {{ num(detailBalance.entitled_days) }} left</span>
              </div>
              <p v-if="detail.status === 'pending' && Number(detail.days) > Number(detailBalance.remaining_days)" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">This request is longer than the days left.</p>
            </section>

            <section>
              <p class="detail-label">History</p>
              <ol class="space-y-3">
                <li class="flex gap-3">
                  <span class="mt-1.5 h-2.5 w-2.5 flex-shrink-0 rounded-full bg-gray-400"></span>
                  <span>
                    <span class="block text-sm font-medium text-gray-900">Requested</span>
                    <span class="block text-xs text-gray-500">{{ stamp(detail.created_at) }}</span>
                  </span>
                </li>
                <li v-if="detail.status !== 'pending'" class="flex gap-3">
                  <span class="mt-1.5 h-2.5 w-2.5 flex-shrink-0 rounded-full" :class="leaveStatus(detail.status).dot"></span>
                  <span>
                    <span class="block text-sm font-medium text-gray-900">{{ leaveStatus(detail.status).label }}<template v-if="detail.approved_by_name && detail.status !== 'cancelled'"> by {{ detail.approved_by_name }}</template></span>
                    <span class="block text-xs text-gray-500">{{ stamp(detail.approved_at || detail.updated_at) }}</span>
                  </span>
                </li>
                <li v-else class="flex gap-3">
                  <span class="mt-1.5 h-2.5 w-2.5 flex-shrink-0 animate-pulse rounded-full bg-amber-400"></span>
                  <span class="text-sm font-medium text-amber-800">Waiting for a decision</span>
                </li>
              </ol>
            </section>
          </div>

          <footer class="flex flex-wrap items-center gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-sm font-medium text-red-600 hover:bg-red-50" @click="deleteRequest(detail.id)"><Trash2 class="h-4 w-4" />Delete</button>
            <template v-if="detail.status === 'pending'">
              <button type="button" class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="deciding" @click="approve(detail.id, 'rejected')"><X class="h-4 w-4" />Decline</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60" :disabled="deciding" @click="approve(detail.id, 'approved')"><Check class="h-4 w-4" />Approve</button>
            </template>
            <button v-else type="button" class="ml-auto rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeDetail">Close</button>
          </footer>
      </div>
    </div>

    <!-- New request (entered by HR for a staff member) -->
    <LeaveRequestDialog
      v-if="showRequest"
      title="New leave request"
      subtitle="Record leave for a staff member. It starts as pending."
      submit-label="Submit request"
      :types="TYPES"
      :employees="employees"
      :busy="saving"
      :form-error="requestError"
      @close="showRequest = false"
      @submit="submitRequest"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { CalendarDays, Check, Clock, Eye, Plane, PlaneTakeoff, Plus, RefreshCw, Trash2, X } from 'lucide-vue-next';
import { leaveAPI, employeesAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import LeaveRequestDialog from '../components/leave/LeaveRequestDialog.vue';
import {
  LEAVE_STATUS, dateRange, dayOfMonth, leaveStatus, leaveTiming, leaveType, longDate, monthShort, shortDate, todayISO,
} from '../utils/leaveMeta.js';

const TABS = [{ value: 'requests', label: 'Requests' }, { value: 'balances', label: 'Balances' }];
const TYPES = ['annual', 'sick', 'maternity', 'paternity', 'compassionate', 'unpaid', 'other'];

const { showToast } = useToast();
const tab = ref('requests');
const loading = ref(false);
const saving = ref(false);
const deciding = ref(false);
const showRequest = ref(false);
const requestError = ref('');
const selectedId = ref(null);
const requests = ref([]);
const balances = ref([]);
const employees = ref([]);
const q = ref('');
const status = ref('');
const type = ref('');
const year = new Date().getFullYear();
const today = todayISO();
const weekEnd = (() => { const d = new Date(`${today}T00:00:00`); d.setDate(d.getDate() + 6); return d.toISOString().slice(0, 10); })();

const num = (v) => Number(v || 0).toString();
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const usedPct = (b) => (Number(b.entitled_days) ? Math.min(100, Math.round((Number(b.used_days) / Number(b.entitled_days)) * 100)) : 0);
const stamp = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '');

// Pending first (oldest request first, so nothing waits too long), then the newest leave.
const sorted = computed(() => [...requests.value].sort((a, b) => {
  if ((a.status === 'pending') !== (b.status === 'pending')) return a.status === 'pending' ? -1 : 1;
  return a.status === 'pending' ? String(a.created_at).localeCompare(String(b.created_at)) : String(b.start_date).localeCompare(String(a.start_date));
}));
const shownRequests = computed(() => {
  const term = q.value.trim().toLowerCase();
  return sorted.value.filter((r) => (!status.value || r.status === status.value) && (!type.value || r.leave_type === type.value)
    && (!term || `${r.first_name} ${r.last_name} ${r.hr_code} ${r.reason || ''}`.toLowerCase().includes(term)));
});
const shownBalances = computed(() => {
  const term = q.value.trim().toLowerCase();
  return balances.value.filter((b) => (!type.value || b.leave_type === type.value) && (!term || `${b.first_name} ${b.last_name}`.toLowerCase().includes(term)));
});
const pendingCount = computed(() => requests.value.filter((r) => r.status === 'pending').length);
const awayThisWeek = computed(() => requests.value
  .filter((r) => r.status === 'approved' && r.start_date <= weekEnd && r.end_date >= today)
  .sort((a, b) => String(a.start_date).localeCompare(String(b.start_date))));
// The request opened with ⋮ › View (nothing is open until someone asks).
const detail = computed(() => requests.value.find((r) => r.id === selectedId.value) || null);
const detailBalance = computed(() => (detail.value
  ? balances.value.find((b) => String(b.employee_id) === String(detail.value.employee_id) && b.leave_type === detail.value.leave_type) || null
  : null));

const stats = computed(() => {
  const approvedThisYear = requests.value.filter((r) => r.status === 'approved' && String(r.start_date).startsWith(String(year)));
  const away = approvedThisYear.filter((r) => r.start_date <= today && r.end_date >= today).length;
  const show = (v) => () => { tab.value = 'requests'; status.value = status.value === v ? '' : v; };
  return [
    { label: 'Waiting for approval', value: pendingCount.value, icon: Clock, tone: pendingCount.value ? 'amber' : 'gray', onClick: show('pending'), active: status.value === 'pending' },
    { label: 'On leave today', value: away, icon: Plane, tone: 'sky' },
    { label: `Approved in ${year}`, value: approvedThisYear.length, icon: Check, onClick: show('approved'), active: status.value === 'approved' },
    { label: `Days taken in ${year}`, value: approvedThisYear.reduce((t, r) => t + Number(r.days || 0), 0), icon: CalendarDays, tone: 'gray' },
  ];
});
const requestActions = (r) => [
  { label: 'View', icon: Eye, onClick: () => openDetail(r) },
  ...(r.status === 'pending' ? [
    { label: 'Approve', icon: Check, onClick: () => approve(r.id, 'approved') },
    { label: 'Decline', icon: X, onClick: () => approve(r.id, 'rejected') },
  ] : []),
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteRequest(r.id) },
];

const load = async () => {
  loading.value = true;
  try {
    const res = await leaveAPI.getRequests();
    requests.value = res.success ? res.data || [] : [];
  } finally {
    loading.value = false;
  }
};
const loadBalances = async () => {
  const res = await leaveAPI.getBalances(year);
  balances.value = res.success ? res.data || [] : [];
};
const switchTab = (t) => {
  tab.value = t;
  if (t === 'balances') loadBalances();
};
const reload = () => Promise.all([load(), loadBalances()]);
const openRequest = () => {
  requestError.value = '';
  showRequest.value = true;
};
const openDetail = (r) => { selectedId.value = r.id; };
const closeDetail = () => { selectedId.value = null; };

const approve = async (id, decision) => {
  deciding.value = true;
  try {
    const res = await leaveAPI.updateStatus(id, decision);
    if (res.success) {
      showToast(decision === 'approved' ? 'Leave approved' : 'Leave declined');
      closeDetail();
      await reload();
    } else showToast(res.message || 'Failed', 'error');
  } catch (e) {
    showToast(e?.response?.data?.message || 'Failed', 'error');
  } finally {
    deciding.value = false;
  }
};
const submitRequest = async (payload) => {
  saving.value = true;
  requestError.value = '';
  try {
    const res = await leaveAPI.create(payload);
    if (res.success) {
      showToast('Request submitted');
      showRequest.value = false;
      load();
    } else requestError.value = res.message || 'Could not save the request';
  } catch (e) {
    requestError.value = e?.response?.data?.message || 'Could not save the request';
  } finally {
    saving.value = false;
  }
};
const deleteRequest = async (id) => {
  if (!confirm('Delete this leave request? This cannot be undone.')) return;
  const res = await leaveAPI.deleteRequest(id);
  if (res.success) { showToast('Request deleted'); selectedId.value = null; reload(); } else showToast(res.message || 'Failed', 'error');
};
const deleteBalance = async (id) => {
  if (!confirm('Delete this leave balance?')) return;
  const res = await leaveAPI.deleteBalance(id);
  if (res.success) { showToast('Balance deleted'); loadBalances(); } else showToast(res.message || 'Failed', 'error');
};

onMounted(async () => {
  reload();
  const e = await employeesAPI.getAll();
  employees.value = e.success ? e.data || [] : [];
});
</script>

<style scoped>
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.avatar { @apply flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100; }
.type-tile { @apply flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg ring-1; }
.status-pill { @apply inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold; }
.date-tile { @apply flex h-11 w-11 flex-shrink-0 flex-col items-center justify-center overflow-hidden rounded-xl bg-white text-center ring-1 ring-gray-200; }
.date-tile-m { @apply w-full bg-brand-600 text-[9px] font-semibold uppercase leading-4 tracking-wide text-white; }
.date-tile-d { @apply flex-1 pt-0.5 text-sm font-bold leading-5 text-gray-900; }
.detail-label { @apply mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500; }
/* Pending requests: a thin amber marker on the left edge. */
.row.is-pending { box-shadow: inset 3px 0 0 #f59e0b; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .filter-label {
  color: var(--d-muted);
}
html.dark .avatar {
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
  --tw-ring-color: rgba(34, 197, 94, 0.3);
}
html.dark .date-tile {
  background-color: var(--d-surface);
  --tw-ring-color: var(--d-line-strong);
}
html.dark .date-tile-d {
  color: var(--d-text);
}
html.dark .detail-label {
  color: var(--d-muted);
}
</style>
