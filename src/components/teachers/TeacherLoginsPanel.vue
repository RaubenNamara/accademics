<template>
  <div class="space-y-4">
    <ToastBanner />
    <div class="flex flex-wrap items-center gap-3">
      <div class="grid flex-1 grid-cols-3 gap-3">
        <div v-for="s in stats" :key="s.label" class="card px-3.5 py-2.5">
          <p class="text-xs font-medium text-gray-500">{{ s.label }}</p>
          <p class="text-xl font-bold leading-tight tabular-nums text-gray-900">{{ s.value }}</p>
        </div>
      </div>
      <button
        type="button"
        class="btn-primary flex-shrink-0"
        :disabled="busy || !withoutLogin"
        title="Each teacher gets a temporary password and chooses their own at first sign-in (email or teacher code)."
        @click="createAll"
      >
        <KeyRound class="h-4 w-4" />Create logins for {{ withoutLogin }} teacher(s)
      </button>
    </div>

    <div class="card overflow-hidden">
      <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2.5">
        <input v-model="q" type="search" class="input max-w-xs py-2" placeholder="Search name, code, email">
        <select v-model="filter" class="input w-auto py-2">
          <option value="all">All teachers</option>
          <option value="none">No login yet</option>
          <option value="pending">Not signed in yet</option>
          <option value="active">Active logins</option>
          <option value="suspended">Suspended</option>
        </select>
        <span class="ml-auto text-xs text-gray-500">{{ shown.length }} shown</span>
      </div>
      <div v-if="loading" class="px-5 py-10 text-center text-sm text-gray-500">Loading…</div>
      <div v-else class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Teacher</th><th>Subject</th><th>Signs in with</th><th>Status</th><th>Last sign-in</th><th class="text-right">Actions</th></tr></thead>
          <tbody>
            <tr v-for="t in shownPage" :key="t.id">
              <td>
                <p class="font-medium text-gray-900">{{ t.full_name }}</p>
                <p class="text-xs text-gray-500"><span v-if="t.teacher_code" class="code-badge mr-1">{{ t.teacher_code }}</span>{{ Number(t.teacher_active) === 0 ? 'Inactive teacher' : '' }}</p>
              </td>
              <td class="text-xs">{{ t.subject || '-' }}</td>
              <td class="text-xs">{{ t.login || '-' }}</td>
              <td><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusOf(t).style">{{ statusOf(t).label }}</span></td>
              <td class="text-xs text-gray-500">{{ t.last_login ? new Date(t.last_login.replace(' ', 'T')).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '-' }}</td>
              <td>
                <div class="flex justify-end">
                  <button v-if="!t.user_id" type="button" class="btn-secondary px-3 py-1 text-xs" :disabled="busy" @click="createOne(t)"><KeyRound class="h-3.5 w-3.5" />Create login</button>
                  <RowActionsMenu v-else :label="`Login actions for ${t.full_name}`" :items="loginActions(t)" />
                </div>
              </td>
            </tr>
            <tr v-if="!shown.length"><td colspan="6" class="py-8 text-center text-sm text-gray-500">No teachers match.</td></tr>
          </tbody>
        </table>
      </div>
      <PagerBar v-if="!loading" v-model:page="page" v-model:per-page="perPage" :total="shown.length" />
    </div>

    <!-- New credentials: shown once -->
    <div v-if="issued.length || failed.length" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl">
        <div class="border-b border-gray-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">Temporary passwords</h2>
          <p class="mt-1 text-sm text-amber-700">These are shown only now. Download or print them and hand each teacher theirs privately.</p>
        </div>
        <div class="flex-1 overflow-auto px-6 py-4">
          <table v-if="issued.length" class="data-table min-w-0">
            <thead><tr><th>Teacher</th><th>Sign in with</th><th>Temporary password</th></tr></thead>
            <tbody>
              <tr v-for="c in issued" :key="c.teacher_id">
                <td class="font-medium text-gray-900">{{ c.name }}</td>
                <td class="font-mono text-xs">{{ c.login }}</td>
                <td class="font-mono text-sm font-semibold text-gray-900">{{ c.password }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="failed.length" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Not created:</p>
            <p v-for="f in failed" :key="f.teacher_id">{{ teacherName(f.teacher_id) }}, {{ f.reason }}</p>
          </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 px-6 py-4">
          <button v-if="issued.length" type="button" class="btn-secondary" @click="copy"><Copy class="h-4 w-4" />Copy</button>
          <button v-if="issued.length" type="button" class="btn-secondary" @click="downloadCsv"><Download class="h-4 w-4" />Download CSV</button>
          <button type="button" class="btn-primary" @click="issued = []; failed = []">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Copy, Download, KeyRound, Power, RotateCcw } from 'lucide-vue-next';
import RowActionsMenu from '../RowActionsMenu.vue';
import PagerBar from '../ui/PagerBar.vue';
import ToastBanner from '../hr/ToastBanner.vue';
import { teacherAccountsAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';

const { showToast } = useToast();
const teachers = ref([]);
const loading = ref(true);
const busy = ref(false);
const q = ref('');
const filter = ref('all');
const issued = ref([]);
const failed = ref([]);

const isActiveTeacher = (t) => t.teacher_active === null || Number(t.teacher_active) === 1;
const withoutLogin = computed(() => teachers.value.filter((t) => !t.user_id && isActiveTeacher(t)).length);
const stats = computed(() => [
  { label: 'With a login', value: teachers.value.filter((t) => t.user_id).length },
  { label: 'Not signed in yet', value: teachers.value.filter((t) => t.user_id && Number(t.must_change_password)).length },
  { label: 'No login yet', value: withoutLogin.value },
]);

const statusOf = (t) => {
  if (!t.user_id) return { label: 'No login', style: 'bg-gray-100 text-gray-600' };
  if (!Number(t.login_active)) return { label: 'Suspended', style: 'bg-red-50 text-red-700' };
  if (Number(t.must_change_password)) return { label: 'Temporary password', style: 'bg-amber-50 text-amber-800' };
  return { label: 'Active', style: 'bg-brand-100 text-brand-800' };
};
const shown = computed(() => {
  const term = q.value.trim().toLowerCase();
  return teachers.value.filter((t) => {
    if (term && !`${t.full_name} ${t.teacher_code || ''} ${t.email || ''} ${t.login || ''}`.toLowerCase().includes(term)) return false;
    if (filter.value === 'none') return !t.user_id;
    if (filter.value === 'pending') return t.user_id && Number(t.must_change_password);
    if (filter.value === 'active') return t.user_id && Number(t.login_active);
    if (filter.value === 'suspended') return t.user_id && !Number(t.login_active);
    return true;
  });
});
const page = ref(1);
const perPage = ref(10);
const shownPage = computed(() => shown.value.slice((page.value - 1) * perPage.value, page.value * perPage.value));
watch([q, filter], () => { page.value = 1; });
const teacherName = (id) => teachers.value.find((t) => t.id === id)?.full_name || `Teacher #${id}`;

async function load() {
  loading.value = true;
  try {
    teachers.value = (await teacherAccountsAPI.list()).data.teachers;
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    loading.value = false;
  }
}

async function run(fn) {
  busy.value = true;
  try {
    await fn();
    await load();
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    busy.value = false;
  }
}

const createAll = () => {
  if (!confirm(`Create logins for ${withoutLogin.value} teacher(s) who do not have one?`)) return;
  run(async () => {
    const res = await teacherAccountsAPI.create({ all: true });
    issued.value = res.data.created;
    failed.value = res.data.failed;
    showToast(res.message);
  });
};
const createOne = (t) => run(async () => {
  const res = await teacherAccountsAPI.create({ teacher_ids: [t.id] });
  issued.value = res.data.created;
  failed.value = res.data.failed;
});
const reset = (t) => {
  if (!confirm(`Give ${t.full_name} a new temporary password? Their current password stops working.`)) return;
  run(async () => {
    const res = await teacherAccountsAPI.reset(t.id);
    issued.value = [res.data];
  });
};
const toggle = (t) => {
  const active = !Number(t.login_active);
  if (!active && !confirm(`Suspend ${t.full_name}'s login? They will not be able to sign in.`)) return;
  run(async () => showToast((await teacherAccountsAPI.setActive(t.id, active)).message));
};

const loginActions = (t) => [
  { label: 'Reset password', icon: RotateCcw, onClick: () => reset(t) },
  Number(t.login_active)
    ? { label: 'Suspend login', icon: Power, danger: true, onClick: () => toggle(t) }
    : { label: 'Re-activate login', icon: Power, onClick: () => toggle(t) },
];

const lines = () => issued.value.map((c) => [c.name, c.login, c.password]);
async function copy() {
  try {
    await navigator.clipboard.writeText(lines().map((l) => l.join('\t')).join('\n'));
    showToast('Copied');
  } catch {
    showToast('Copy is not available here, use Download CSV', 'error');
  }
}
function downloadCsv() {
  const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
  const csv = [['Teacher', 'Sign in with', 'Temporary password'], ...lines()].map((r) => r.map(esc).join(',')).join('\r\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
  a.download = `Skool Monitor teacher logins ${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
}

onMounted(load);
</script>
