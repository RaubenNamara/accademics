<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Departments</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <DownloadMenu :busy="downloading" :disabled="!departments.length" @download="downloadDepartments" />
          <button type="button" class="btn-hero-primary" @click="openForm()"><Plus class="h-4 w-4" />Add department</button>
        </div>
      </div>
    </div>

    <!-- Summary -->
    <StatCards :cards="stats" />

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative w-full sm:w-72">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input v-model="q" type="search" class="input py-2 pl-9" placeholder="Search departments or roles">
      </div>
      <button
        v-for="f in filters"
        :key="f.value"
        type="button"
        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
        :class="statusFilter === f.value ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
        @click="statusFilter = f.value"
      >{{ f.label }}</button>
      <select v-model="sortBy" class="input ml-auto w-auto py-2" aria-label="Sort">
        <option value="name">Sort by name</option>
        <option value="staff">Most staff</option>
        <option value="roles">Most roles</option>
      </select>
    </div>

    <!-- Department cards -->
    <div v-if="loading" class="card px-5 py-12 text-center text-sm text-gray-500">Loading…</div>
    <div v-else-if="!shown.length" class="card px-5 py-14 text-center">
      <Building2 class="mx-auto h-9 w-9 text-gray-300" />
      <p class="mt-2 text-sm font-medium text-gray-700">{{ departments.length ? 'No departments match' : 'No departments yet' }}</p>
      <button v-if="!departments.length" type="button" class="btn-primary mt-4" @click="openForm()"><Plus class="h-4 w-4" />Add department</button>
    </div>
    <div v-else class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
      <article v-for="d in shown" :key="d.id" class="card flex flex-col p-5 transition hover:border-brand-300" :class="Number(d.is_active) ? '' : 'opacity-75'">
        <div class="flex items-start gap-3">
          <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl text-sm font-bold" :class="Number(d.is_active) ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-600'">{{ initials(d.name) }}</span>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <h2 class="truncate text-base font-semibold text-gray-900">{{ d.name }}</h2>
              <span v-if="!Number(d.is_active)" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">Inactive</span>
            </div>
            <p class="mt-0.5 line-clamp-2 text-sm text-gray-500">{{ d.description || 'No description' }}</p>
          </div>
          <div class="flex flex-shrink-0">
            <button type="button" class="icon-btn" title="Edit" @click="openForm(d)"><Pencil class="h-4 w-4" /></button>
            <button type="button" class="icon-btn hover:bg-red-50 hover:text-red-600" title="Delete" @click="remove(d)"><Trash2 class="h-4 w-4" /></button>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2">
          <div class="rounded-xl bg-gray-50 px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Staff</p>
            <p class="text-lg font-bold tabular-nums text-gray-900">{{ d.staff_count ?? 0 }}</p>
          </div>
          <div class="rounded-xl bg-gray-50 px-3 py-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Roles</p>
            <p class="text-lg font-bold tabular-nums text-gray-900">{{ d.roles?.length || 0 }}</p>
          </div>
        </div>

        <div class="mt-4 flex-1">
          <div v-if="d.roles?.length" class="flex flex-wrap gap-1.5">
            <span v-for="r in d.roles.slice(0, 5)" :key="r.id" class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-800 ring-1 ring-brand-100">{{ r.name }}</span>
            <span v-if="d.roles.length > 5" class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">+{{ d.roles.length - 5 }} more</span>
          </div>
          <p v-else class="text-sm text-gray-400">No roles yet</p>
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3">
          <button type="button" class="link-btn inline-flex items-center gap-1" @click="openRoleForm(d)"><Plus class="h-3.5 w-3.5" />Add role</button>
          <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900" @click="viewing = d">View details<ChevronRight class="h-4 w-4" /></button>
        </div>
      </article>
    </div>

    <!-- Details panel -->
    <div v-if="viewing" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="viewing = null">
      <aside class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <div class="flex items-start gap-3 border-b border-gray-100 px-6 py-5">
          <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">{{ initials(viewing.name) }}</span>
          <div class="min-w-0 flex-1">
            <h2 class="text-lg font-semibold text-gray-900">{{ viewing.name }}</h2>
            <p class="text-sm text-gray-500">{{ viewing.description || 'No description' }}</p>
          </div>
          <button type="button" class="icon-btn" title="Close" @click="viewing = null"><X class="h-5 w-5" /></button>
        </div>
        <div class="flex-1 space-y-5 overflow-auto px-6 py-5">
          <div class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-xl font-bold text-gray-900">{{ viewing.staff_count ?? 0 }}</p><p class="text-xs text-gray-500">Staff</p></div>
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-xl font-bold text-gray-900">{{ viewing.roles?.length || 0 }}</p><p class="text-xs text-gray-500">Roles</p></div>
            <div class="rounded-xl bg-gray-50 p-3"><p class="text-sm font-bold" :class="Number(viewing.is_active) ? 'text-brand-700' : 'text-gray-500'">{{ Number(viewing.is_active) ? 'Active' : 'Inactive' }}</p><p class="text-xs text-gray-500">Status</p></div>
          </div>
          <div>
            <div class="mb-2 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-900">Roles</h3>
              <button type="button" class="link-btn" @click="openRoleForm(viewing)">+ Add role</button>
            </div>
            <ul v-if="viewing.roles?.length" class="divide-y divide-gray-100 rounded-xl border border-gray-200">
              <li v-for="r in viewing.roles" :key="r.id" class="flex items-start justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                  <p class="text-sm font-medium text-gray-900">{{ r.name }}</p>
                  <p v-if="r.description" class="text-xs text-gray-500">{{ r.description }}</p>
                </div>
                <span class="flex-shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ r.staff_count || 0 }} staff</span>
              </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">No roles in this department yet.</p>
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
          <button type="button" class="btn-secondary" @click="openForm(viewing)"><Pencil class="h-4 w-4" />Edit</button>
          <button type="button" class="btn-primary" @click="viewing = null">Done</button>
        </div>
      </aside>
    </div>

    <!-- Add / edit department -->
    <div v-if="form" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="form = null">
      <form class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl" @submit.prevent="save">
        <div class="border-b border-gray-100 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ form.id ? 'Edit department' : 'Add department' }}</h2>
        </div>
        <div class="flex-1 space-y-4 overflow-auto px-6 py-5">
          <label class="block">
            <span class="field-label">Name</span>
            <input v-model="form.name" class="input" maxlength="150" placeholder="e.g. Accounts" required>
          </label>
          <label class="block">
            <span class="field-label">Description</span>
            <textarea v-model="form.description" rows="2" class="input" maxlength="255" placeholder="What the department does"></textarea>
          </label>
          <div v-if="form.id">
            <div class="mb-1 flex items-center justify-between">
              <span class="field-label mb-0">Roles in this department</span>
              <span class="text-xs text-gray-500">{{ form.role_ids.length }} chosen</span>
            </div>
            <input v-model="roleQuery" type="search" class="input mb-2 py-2" placeholder="Find a role">
            <div class="max-h-64 overflow-auto rounded-xl border border-gray-200 p-2">
              <p v-if="rolesLoading" class="px-2 py-4 text-center text-sm text-gray-500">Loading roles…</p>
              <label v-for="r in roleChoices" v-else :key="r.id" class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                <input v-model="form.role_ids" type="checkbox" :value="Number(r.id)" class="mt-0.5 h-4 w-4 rounded border-gray-300 accent-brand-600">
                <span class="min-w-0">
                  <span class="block text-sm font-medium text-gray-800">{{ r.name }}</span>
                  <span v-if="r.department_name && Number(r.department_id) !== form.id" class="block text-xs text-amber-700">Now in {{ r.department_name }}; ticking moves it here</span>
                </span>
              </label>
            </div>
          </div>
          <label class="flex items-center gap-2.5 text-sm font-medium text-gray-700">
            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 accent-brand-600">Active
          </label>
          <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">{{ formError }}</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
          <button type="button" class="btn-secondary" @click="form = null">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save department' }}</button>
        </div>
      </form>
    </div>

    <!-- Add role -->
    <div v-if="roleForm" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="roleForm = null">
      <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="addRole">
        <h2 class="text-lg font-semibold text-gray-900">Add a role</h2>
        <p class="text-sm text-gray-500">In {{ roleForm.department.name }}</p>
        <div class="mt-4 space-y-4">
          <label class="block">
            <span class="field-label">Role name</span>
            <input v-model="roleForm.name" class="input" maxlength="150" placeholder="e.g. Accountant" required>
          </label>
          <label class="block">
            <span class="field-label">Description</span>
            <textarea v-model="roleForm.description" rows="2" class="input" maxlength="255" placeholder="What this role does"></textarea>
          </label>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="roleForm = null">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Adding…' : 'Add role' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import DownloadMenu from '../components/ui/DownloadMenu.vue';
import { downloadExcel, downloadPdf } from '../utils/downloads.js';
import { computed, onMounted, ref } from 'vue';
import { Briefcase, Building2, ChevronRight, CircleCheck, Pencil, Plus, Search, Trash2, Users, X } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import { departmentsAPI, rolesAPI } from '../services/api';
import { useToast } from '../composables/useToast.js';

const { showToast } = useToast();
const departments = ref([]);
const roles = ref([]);
const loading = ref(true);
const rolesLoading = ref(false);
const saving = ref(false);
const q = ref('');
const statusFilter = ref('all');
const sortBy = ref('name');
const viewing = ref(null);
const form = ref(null);
const formError = ref('');
const roleForm = ref(null);
const roleQuery = ref('');

const filters = [
  { value: 'all', label: 'All' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
];
const errorText = (e, fallback) => e?.response?.data?.error || e?.response?.data?.message || e?.message || fallback;
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();

const stats = computed(() => {
  const active = departments.value.filter((d) => Number(d.is_active)).length;
  const roleCount = departments.value.reduce((t, d) => t + (d.roles?.length || 0), 0);
  const staff = departments.value.reduce((t, d) => t + Number(d.staff_count || 0), 0);
  const largest = [...departments.value].sort((a, b) => Number(b.staff_count || 0) - Number(a.staff_count || 0))[0];
  return [
    { label: 'Departments', value: departments.value.length, note: `${active} active`, icon: Building2 },
    { label: 'Roles', value: roleCount, note: 'Across all departments', icon: Briefcase, tone: 'sky' },
    { label: 'Staff', value: staff, note: 'In a department', icon: Users, tone: 'amber' },
    { label: 'Largest', value: largest ? largest.staff_count : '-', note: largest?.name || '', icon: CircleCheck, tone: 'gray' },
  ];
});

const shown = computed(() => {
  const term = q.value.trim().toLowerCase();
  const list = departments.value.filter((d) => {
    if (statusFilter.value === 'active' && !Number(d.is_active)) return false;
    if (statusFilter.value === 'inactive' && Number(d.is_active)) return false;
    return !term || `${d.name} ${d.description || ''} ${(d.roles || []).map((r) => r.name).join(' ')}`.toLowerCase().includes(term);
  });
  const by = {
    name: (a, b) => a.name.localeCompare(b.name),
    staff: (a, b) => Number(b.staff_count || 0) - Number(a.staff_count || 0),
    roles: (a, b) => (b.roles?.length || 0) - (a.roles?.length || 0),
  }[sortBy.value];
  return list.sort(by);
});
const roleChoices = computed(() => {
  const term = roleQuery.value.trim().toLowerCase();
  return roles.value.filter((r) => !term || r.name.toLowerCase().includes(term));
});

async function load() {
  loading.value = true;
  try {
    departments.value = (await departmentsAPI.getAll('', true)).data || [];
    if (viewing.value) viewing.value = departments.value.find((d) => d.id === viewing.value.id) || null;
  } catch (e) {
    showToast(errorText(e, 'Could not load departments'), 'error');
  } finally {
    loading.value = false;
  }
}
async function loadRoles() {
  rolesLoading.value = true;
  try {
    roles.value = (await rolesAPI.getAll()).data || [];
  } catch {
    roles.value = [];
  } finally {
    rolesLoading.value = false;
  }
}

function openForm(d = null) {
  formError.value = '';
  roleQuery.value = '';
  form.value = d
    ? { id: d.id, name: d.name, description: d.description || '', role_ids: (d.roles || []).map((r) => Number(r.id)), is_active: !!Number(d.is_active) }
    : { id: null, name: '', description: '', role_ids: [], is_active: true };
  if (d) loadRoles();
}
async function save() {
  saving.value = true;
  formError.value = '';
  try {
    const payload = { ...form.value, is_active: form.value.is_active ? 1 : 0 };
    if (form.value.id) await departmentsAPI.update(payload);
    else await departmentsAPI.create(payload);
    showToast(form.value.id ? 'Department updated' : 'Department added');
    form.value = null;
    await load();
  } catch (e) {
    formError.value = errorText(e, 'Could not save the department');
  } finally {
    saving.value = false;
  }
}
async function remove(d) {
  const note = d.staff_count ? ` It has ${d.staff_count} staff member(s).` : '';
  if (!confirm(`Delete the ${d.name} department?${note} This cannot be undone.`)) return;
  try {
    await departmentsAPI.delete(d.id);
    showToast(`${d.name} deleted`);
    if (viewing.value?.id === d.id) viewing.value = null;
    await load();
  } catch (e) {
    showToast(errorText(e, 'Could not delete the department'), 'error');
  }
}

const openRoleForm = (d) => { roleForm.value = { department: d, name: '', description: '' }; };
async function addRole() {
  saving.value = true;
  try {
    await rolesAPI.create({ name: roleForm.value.name.trim(), description: roleForm.value.description.trim(), department_id: roleForm.value.department.id });
    showToast(`${roleForm.value.name} added to ${roleForm.value.department.name}`);
    roleForm.value = null;
    await load();
  } catch (e) {
    showToast(errorText(e, 'Could not add the role'), 'error');
  } finally {
    saving.value = false;
  }
}

onMounted(load);

// Departments as Excel (a sheet of departments and a sheet of every role) or a PDF list.
const downloading = ref(false);
const downloadDepartments = async (format) => {
  downloading.value = true;
  try {
    const rows = departments.value.map((d) => ({
      name: d.name, status: Number(d.is_active) ? 'Active' : 'Inactive', staff: Number(d.staff_count || 0),
      roles: (d.roles || []).length, role_list: (d.roles || []).map((r) => r.name).join(', '), description: d.description || '',
    }));
    if (format === 'excel') {
      downloadExcel('departments', [
        { name: 'Departments', columns: [{ key: 'name', label: 'Department' }, { key: 'status', label: 'Status' }, { key: 'staff', label: 'Staff' }, { key: 'roles', label: 'Roles' }, { key: 'role_list', label: 'Role names', width: 60 }, { key: 'description', label: 'Description', width: 50 }], rows },
        { name: 'Roles', columns: [{ key: 'department', label: 'Department' }, { key: 'role', label: 'Role' }], rows: departments.value.flatMap((d) => (d.roles || []).map((r) => ({ department: d.name, role: r.name }))) },
      ]);
    } else {
      await downloadPdf({
        title: 'Departments',
        subtitle: 'Departments, staff and roles',
        columns: [{ key: 'name', label: 'Department' }, { key: 'status', label: 'Status' }, { key: 'staff', label: 'Staff', align: 'right' }, { key: 'role_list', label: 'Roles' }],
        rows,
        summary: [
          { label: 'Departments', value: rows.length },
          { label: 'Active', value: rows.filter((r) => r.status === 'Active').length },
          { label: 'Staff', value: rows.reduce((t, r) => t + r.staff, 0) },
          { label: 'Roles', value: rows.reduce((t, r) => t + r.roles, 0) },
        ],
      });
    }
  } catch (e) {
    alert(e?.response?.data?.message || 'Could not prepare the download');
  } finally {
    downloading.value = false;
  }
};
</script>

<style scoped>
.field-label { @apply mb-1 block text-sm font-medium text-gray-700; }
.icon-btn { @apply rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .field-label {
  color: var(--d-text-2);
}
html.dark .icon-btn {
  color: var(--d-faint);
}
html.dark .icon-btn:hover {
  background-color: var(--d-raised);
  color: var(--d-text-2);
}
</style>
