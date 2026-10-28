<template>
  <div class="page">
    <ToastBanner />

    <!-- Header -->
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">System Users</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero px-3" :disabled="loading" title="Refresh" aria-label="Refresh" @click="reloadUsers">
            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          </button>
          <button type="button" class="btn-hero-primary" @click="openAddModal"><Plus class="h-4 w-4" />Add user</button>
        </div>
      </div>
    </div>

    <StatCards :cards="statCards" />

    <ListToolbar
      :search="filters.search"
      placeholder="Search name, email or role"
      :filter-count="[filters.role, filters.is_active].filter((v) => v !== '').length"
      @update:search="(v) => { filters.search = v; debouncedLoad(); }"
      @clear="clearFilters"
    >
      <template #filters>
        <label class="block">
          <span class="filter-label">Role</span>
          <select v-model="filters.role" class="input py-2" @change="loadUsers">
            <option value="">All roles</option>
            <option v-for="(label, value) in ROLE_LABELS" :key="value" :value="value">{{ label }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Status</span>
          <select v-model="filters.is_active" class="input py-2" @change="loadUsers">
            <option value="">Any status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </label>
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div v-if="loading && !users.length" class="space-y-3 p-5">
        <div v-for="i in 4" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-9 w-9 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="!users.length" class="px-5 py-14 text-center">
        <ShieldCheck class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No users found</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">User</th>
              <th class="px-4 py-3">Role</th>
              <th class="px-4 py-3">Status</th>
              <th class="hidden px-4 py-3 lg:table-cell">Last sign-in</th>
              <th class="hidden px-4 py-3 xl:table-cell">Created</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="user in pagedUsers" :key="user.id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(user.full_name) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900">{{ user.full_name }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ user.email }}</span>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3"><span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="roleStyle(user.role)">{{ formatRole(user.role) }}</span></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="Number(user.is_active) === 1 ? 'text-brand-700' : 'text-gray-500'">
                  <span class="h-2 w-2 rounded-full" :class="Number(user.is_active) === 1 ? 'bg-brand-500' : 'bg-gray-400'"></span>{{ Number(user.is_active) === 1 ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 lg:table-cell">{{ formatDate(user.last_login) }}</td>
              <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 xl:table-cell">{{ formatDate(user.created_at) }}</td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${user.full_name}`" :items="rowActions(user)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="user in pagedUsers" :key="user.id" class="flex items-start gap-3 px-4 py-3">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(user.full_name) }}</span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-gray-900">{{ user.full_name }}</p>
              <p class="truncate text-xs text-gray-500">{{ user.email }}</p>
              <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="roleStyle(user.role)">{{ formatRole(user.role) }}</span>
                <span class="text-[11px] font-medium" :class="Number(user.is_active) === 1 ? 'text-brand-700' : 'text-gray-500'">{{ Number(user.is_active) === 1 ? 'Active' : 'Inactive' }}</span>
              </div>
            </div>
            <RowActionsMenu :label="`Actions for ${user.full_name}`" :items="rowActions(user)" />
          </li>
        </ul>
        <PagerBar v-model:page="page" v-model:per-page="perPage" :total="users.length" />
      </template>
    </section>

    <!-- Add/Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
        <div class="border-b border-slate-200 px-6 py-5">
          <h3 class="text-xl font-semibold text-slate-900">
            {{ editingId ? 'Edit User' : 'Add User' }}
          </h3>
          <p class="mt-1 text-sm text-slate-500">
            {{ editingId ? 'Update user details and permissions.' : 'Create a new user account with appropriate role.' }}
          </p>
        </div>

        <form @submit.prevent="saveUser" class="space-y-5 px-6 py-6">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Full Name *</label>
              <input v-model.trim="form.full_name" type="text" required class="input" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Email *</label>
              <input v-model.trim="form.email" type="email" required class="input" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">
                {{ editingId ? 'Password (leave blank to keep current)' : 'Password *' }}
              </label>
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                :required="!editingId"
                minlength="6"
                autocomplete="new-password"
                class="input"
              />
            </div>

            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Role *</label>
              <select v-model="form.role" required class="input">
                <option value="">Select role</option>
                <option v-for="(label, value) in ROLE_LABELS" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>

            <div class="flex items-end">
              <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                <span class="text-sm font-medium text-slate-700">Active user</span>
              </label>
            </div>
          </div>

          <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ error }}
          </div>

          <div class="flex items-center justify-end gap-3">
            <button
              type="button"
              @click="closeModal"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition-all hover:bg-slate-50"
            >
              ❌ Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-700 hover:shadow-brand-500/30 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <span v-if="saving">⏳</span>
              <span v-else>💾</span>
              {{ saving ? 'Saving...' : (editingId ? 'Update' : 'Save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usersAPI } from '../services/api';
import { Pencil, Plus, Power, RefreshCw, ShieldCheck, Trash2, UserCheck, UserX, Users as UsersIcon } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import PagerBar from '../components/ui/PagerBar.vue';
import { useToast } from '../composables/useToast.js';
import authStore from '../services/authStore.js';

const users = ref([]);
const page = ref(1);
const perPage = ref(10);
const pagedUsers = computed(() => users.value.slice((page.value - 1) * perPage.value, page.value * perPage.value));
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);

const error = ref('');

const filters = ref({
  search: '',
  role: '',
  is_active: ''
});

const form = ref({
  full_name: '',
  email: '',
  password: '',
  role: 'admin',
  is_active: true
});

const { showToast } = useToast();
const notify = (message, type = 'success') => showToast(message, type);
let searchTimer = null;

const stats = computed(() => ({
  total: users.value.length,
  active: users.value.filter(u => Number(u.is_active) === 1).length,
  inactive: users.value.filter(u => Number(u.is_active) === 0).length
}));

const adminCount = computed(() => {
  return users.value.filter(u => u.role === 'admin' || u.role === 'super_admin').length;
});

const loadUsers = async () => {
  loading.value = true;
  error.value = '';

  try {
    const result = await usersAPI.getAll({
      search: filters.value.search,
      role: filters.value.role,
      is_active: filters.value.is_active
    });

    if (result.success) {
      users.value = Array.isArray(result.data) ? result.data : [];
      if ((page.value - 1) * perPage.value >= users.value.length) page.value = 1;
      if (result.message) {
        notify(result.message, 'success');
      }
    } else {
      users.value = [];
      error.value = result.message || 'Failed to load users';
      notify(error.value, 'error');
    }
  } catch (err) {
    users.value = [];
    error.value = err?.response?.data?.message || 'Failed to load users';
    notify(error.value, 'error');
  } finally {
    loading.value = false;
  }
};

const reloadUsers = () => {
  loadUsers();
};

const debouncedLoad = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    loadUsers();
  }, 300);
};

const clearFilters = () => {
  filters.value = {
    search: '',
    role: '',
    is_active: ''
  };
  loadUsers();
};

const openAddModal = () => {
  editingId.value = null;
  error.value = '';
  form.value = {
    full_name: '',
    email: '',
    password: '',
    role: 'admin',
    is_active: true
  };
  showModal.value = true;
};

const editUser = (user) => {
  editingId.value = user.id;
  error.value = '';
  form.value = {
    full_name: user.full_name || '',
    email: user.email || '',
    password: '',
    role: user.role || 'admin',
    is_active: Number(user.is_active) === 1
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  error.value = '';
};

const saveUser = async () => {
  saving.value = true;
  error.value = '';

  try {
    const payload = {
      full_name: form.value.full_name?.trim(),
      email: form.value.email?.trim().toLowerCase(),
      password: form.value.password,
      role: form.value.role,
      is_active: form.value.is_active ? 1 : 0
    };

    // Don't send empty password when editing
    if (editingId.value && payload.password === '') {
      delete payload.password;
    }

    let result;
    if (editingId.value) {
      payload.id = editingId.value;
      result = await usersAPI.update(payload);
    } else {
      result = await usersAPI.create(payload);
    }

    if (result.success) {
      await loadUsers();
      closeModal();
      notify(editingId.value ? 'User updated successfully.' : 'User added successfully.', 'success');
    } else {
      error.value = result.message || 'Failed to save user';
      notify(error.value, 'error');
    }
  } catch (err) {
    error.value = err?.response?.data?.message || 'Failed to save user';
    notify(error.value, 'error');
  } finally {
    saving.value = false;
  }
};

const toggleUserStatus = async (user) => {
  const nextStatus = Number(user.is_active) === 1 ? 0 : 1;

  try {
    const result = await usersAPI.toggleStatus(user.id, nextStatus);
    if (result.success) {
      await loadUsers();
      notify(
        Number(nextStatus) === 1 ? 'User activated successfully.' : 'User deactivated successfully.',
        'success'
      );
    } else {
      notify(result.message || 'Failed to update user status', 'error');
    }
  } catch (err) {
    notify(err?.response?.data?.message || 'Failed to update user status', 'error');
  }
};

const deleteUser = async (id) => {
  const ok = confirm('Are you sure you want to delete this user? This will deactivate their account.');
  if (!ok) return;

  try {
    const result = await usersAPI.delete(id);
    if (result.success) {
      await loadUsers();
      notify('User deleted successfully.', 'success');
    } else {
      notify(result.message || 'Failed to delete user', 'error');
    }
  } catch (err) {
    notify(err?.response?.data?.message || 'Failed to delete user', 'error');
  }
};

// "teacher" accounts are the academic office; classroom teachers use the teacher portal role.
const ROLE_LABELS = {
  // In a school other than the home school, the top account runs that school only.
  super_admin: authStore.user?.school?.is_home === false ? 'School Admin' : 'Super Admin',
  admin: 'Admin',
  teacher: 'Academic Office',
  subject_teacher: 'Teacher (portal)',
  staff: 'Staff',
};
const formatRole = (role) => ROLE_LABELS[role] || role;
const roleStyle = (role) => ({
  super_admin: 'bg-violet-50 text-violet-800 ring-1 ring-violet-200',
  admin: 'bg-sky-50 text-sky-800 ring-1 ring-sky-200',
  teacher: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
  subject_teacher: 'bg-brand-50 text-brand-800 ring-1 ring-brand-100',
}[role] || 'bg-gray-100 text-gray-700');
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const rowActions = (user) => [
  { label: 'Edit', icon: Pencil, onClick: () => editUser(user) },
  { label: Number(user.is_active) === 1 ? 'Deactivate' : 'Activate', icon: Power, onClick: () => toggleUserStatus(user) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteUser(user.id) },
];
const statCards = computed(() => {
  const status = (v) => () => { filters.value.is_active = filters.value.is_active === v ? '' : v; loadUsers(); };
  return [
    { label: 'Users', value: stats.value.total, icon: UsersIcon },
    { label: 'Active', value: stats.value.active, icon: UserCheck, onClick: status('1'), active: filters.value.is_active === '1' },
    { label: 'Inactive', value: stats.value.inactive, icon: UserX, tone: 'gray', onClick: status('0'), active: filters.value.is_active === '0' },
    { label: 'Admins', value: adminCount.value, icon: ShieldCheck, tone: 'amber' },
  ];
});

const formatDate = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleDateString();
};

onMounted(() => {
  loadUsers();
});

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer);
});
</script>

<style scoped>
.input {
  width: 100%;
  border-radius: 1rem;
  border: 1px solid rgb(226 232 240);
  padding: 0.8rem 0.95rem;
  outline: none;
  transition: all 0.2s ease;
  background: white;
  font-size: 0.95rem;
}

.input:focus {
  border-color: rgb(5 150 105);
  box-shadow: 0 0 0 4px rgb(220 252 231);
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.25s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .input {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
</style>
