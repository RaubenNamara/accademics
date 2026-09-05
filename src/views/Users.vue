<template>
  <div class="space-y-5">
    <!-- Toast -->
    <transition name="fade-slide">
      <div
        v-if="toast.show"
        class="fixed right-4 top-4 z-[100] min-w-[280px] max-w-md rounded-2xl border px-4 py-3 shadow-2xl backdrop-blur-sm"
        :class="toast.type === 'success'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
          : 'border-rose-200 bg-rose-50 text-rose-800'"
      >
        <div class="flex items-start gap-3">
          <div class="mt-0.5 text-lg">
            {{ toast.type === 'success' ? '✅' : '⚠️' }}
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold">
              {{ toast.type === 'success' ? 'Success' : 'Error' }}
            </p>
            <p class="text-sm">
              {{ toast.message }}
            </p>
          </div>
          <button
            type="button"
            class="ml-2 text-sm opacity-70 transition hover:opacity-100"
            @click="hideToast"
          >
            ✕
          </button>
        </div>
      </div>
    </transition>

    <!-- Header -->
    <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-purple-900 p-5 text-white shadow-xl">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-xs uppercase tracking-[0.22em] text-purple-200">User Management</p>
          <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Manage Users</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-300">
            Add, edit, and manage admin users. Control access and roles for the system.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            @click="reloadUsers"
            :disabled="loading"
            class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2 text-xs font-medium text-white backdrop-blur transition-all hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm"
          >
            <span v-if="!loading">🔄</span>
            <span v-else>⏳</span>
            Refresh
          </button>
          <button
            @click="openAddModal"
            class="inline-flex items-center gap-2 rounded-xl bg-purple-500 px-3.5 py-2 text-xs font-medium text-white shadow-lg shadow-purple-500/20 transition-all hover:bg-purple-600 hover:shadow-purple-500/30 sm:text-sm"
          >
            ➕ Add User
          </button>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Total Users</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.total }}</p>
      </div>
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Active</p>
        <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ stats.active }}</p>
      </div>
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Inactive</p>
        <p class="mt-2 text-2xl font-semibold text-rose-600">{{ stats.inactive }}</p>
      </div>
      <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Admins</p>
        <p class="mt-2 text-2xl font-semibold text-purple-600">{{ adminCount }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid gap-4 xl:grid-cols-4">
        <div class="xl:col-span-2">
          <label class="mb-2 block text-sm font-medium text-slate-700">🔍 Search</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Search name, email, role..."
            class="w-full rounded-2xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none ring-0 transition-all focus:border-purple-500 focus:ring-4 focus:ring-purple-100"
            @input="debouncedLoad"
          />
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">👤 Role</label>
          <select
            v-model="filters.role"
            @change="loadUsers"
            class="w-full rounded-2xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition-all focus:border-purple-500 focus:ring-4 focus:ring-purple-100"
          >
            <option value="">All</option>
            <option value="admin">Admin</option>
            <option value="super_admin">Super Admin</option>
            <option value="teacher">Teacher</option>
            <option value="staff">Staff</option>
          </select>
        </div>

        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">✅ Status</label>
          <select
            v-model="filters.is_active"
            @change="loadUsers"
            class="w-full rounded-2xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition-all focus:border-purple-500 focus:ring-4 focus:ring-purple-100"
          >
            <option value="">All</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <button
          @click="clearFilters"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-medium text-slate-700 transition-all hover:bg-slate-50 sm:text-sm"
        >
          🧹 Clear Filters
        </button>

        <div v-if="loading" class="inline-flex items-center gap-2 text-sm text-slate-500">
          <span class="animate-spin">⏳</span>
          Loading users...
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-semibold text-slate-900">Users Records</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-[1200px] w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <th class="px-4 py-3">#</th>
              <th class="px-4 py-3">Full Name</th>
              <th class="px-4 py-3">Email</th>
              <th class="px-4 py-3">Role</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Last Login</th>
              <th class="px-4 py-3">Created</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="!loading && users.length === 0">
              <td colspan="8" class="px-4 py-12 text-center text-slate-500">
                No users found.
              </td>
            </tr>

            <tr
              v-for="(user, index) in users"
              :key="user.id"
              class="transition hover:bg-slate-50"
            >
              <td class="px-4 py-3 text-sm text-slate-500">{{ index + 1 }}</td>
              <td class="px-4 py-3">
                <div class="font-semibold text-slate-900">{{ user.full_name }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-slate-700">{{ user.email }}</td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="{
                    'bg-purple-50 text-purple-700': user.role === 'super_admin',
                    'bg-blue-50 text-blue-700': user.role === 'admin',
                    'bg-emerald-50 text-emerald-700': user.role === 'teacher',
                    'bg-slate-50 text-slate-700': user.role === 'staff'
                  }"
                >
                  {{ formatRole(user.role) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="Number(user.is_active) === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                >
                  {{ Number(user.is_active) === 1 ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-slate-500">
                {{ formatDate(user.last_login) }}
              </td>
              <td class="px-4 py-3 text-sm text-slate-500">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                  <button
                    @click="editUser(user)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-blue-700 transition-all hover:bg-blue-100 hover:shadow-sm whitespace-nowrap"
                  >
                    ✏️ Edit
                  </button>
                  <button
                    @click="toggleUserStatus(user)"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-all hover:shadow-sm whitespace-nowrap"
                    :class="Number(user.is_active) === 1
                      ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                      : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                  >
                    {{ Number(user.is_active) === 1 ? '⏸️ Deactivate' : '▶️ Activate' }}
                  </button>
                  <button
                    @click="deleteUser(user.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 transition-all hover:bg-rose-100 hover:shadow-sm whitespace-nowrap"
                  >
                    🗑️ Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
    >
      <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl">
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
                <option value="super_admin">Super Admin</option>
                <option value="admin">Admin</option>
                <option value="teacher">Teacher</option>
                <option value="staff">Staff</option>
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
              class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-purple-500/20 transition-all hover:bg-purple-700 hover:shadow-purple-500/30 disabled:cursor-not-allowed disabled:opacity-60"
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
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { usersAPI } from '../services/api';

const users = ref([]);
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

const toast = ref({
  show: false,
  type: 'success',
  message: ''
});

let toastTimer = null;
let searchTimer = null;

const hideToast = () => {
  toast.value.show = false;
  toast.value.message = '';
  toast.value.type = 'success';

  if (toastTimer) {
    clearTimeout(toastTimer);
    toastTimer = null;
  }
};

const notify = (message, type = 'success') => {
  hideToast();
  toast.value = {
    show: true,
    type,
    message
  };

  toastTimer = setTimeout(() => {
    hideToast();
  }, 2800);
};

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

const formatRole = (role) => {
  const roleMap = {
    'super_admin': 'Super Admin',
    'admin': 'Admin',
    'teacher': 'Teacher',
    'staff': 'Staff'
  };
  return roleMap[role] || role;
};

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
  if (toastTimer) clearTimeout(toastTimer);
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
  border-color: rgb(147 51 234);
  box-shadow: 0 0 0 4px rgb(233 213 255);
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

