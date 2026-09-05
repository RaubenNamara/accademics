<template>
  <div class="min-h-screen bg-slate-100">
    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
      @click="mobileOpen = false"
    />

    <aside
      class="sidebar-shell fixed left-0 top-0 z-50 flex h-full flex-col text-white transition-all duration-300 ease-out"
      :class="[
        collapsed ? 'w-[72px]' : 'w-72',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div class="flex items-center gap-3 border-b border-white/10 px-4 py-5" :class="collapsed ? 'justify-center' : ''">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 shadow-lg backdrop-blur">
          <i class="fas fa-graduation-cap text-xl text-sky-200"></i>
        </div>
        <div v-if="!collapsed" class="min-w-0">
          <h1 class="truncate text-lg font-bold tracking-tight">School HR</h1>
          <p class="text-xs text-sky-200/80">Enterprise Staff Suite</p>
        </div>
        <button
          type="button"
          class="ml-auto hidden rounded-lg p-2 text-white/70 hover:bg-white/10 lg:inline-flex"
          @click="collapsed = !collapsed"
        >
          <i :class="collapsed ? 'fas fa-angle-right' : 'fas fa-angle-left'"></i>
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 py-4 scrollbar-thin">
        <template v-for="section in navSections" :key="section.title">
          <p
            v-if="!collapsed && section.title"
            class="mb-2 mt-4 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-sky-200/60 first:mt-0"
          >
            {{ section.title }}
          </p>
          <div v-else-if="collapsed && section.title" class="my-3 border-t border-white/10" />

          <router-link
            v-for="item in section.items"
            :key="item.to"
            :to="item.to"
            class="nav-item group mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200"
            :class="[
              isActive(item.to) ? 'nav-item-active' : 'text-sky-100/90 hover:bg-white/10 hover:text-white',
              collapsed ? 'justify-center' : ''
            ]"
            :title="collapsed ? item.label : ''"
            @click="mobileOpen = false"
          >
            <i :class="[item.icon, 'w-5 text-center text-base transition-transform group-hover:scale-110']"></i>
            <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
            <span
              v-if="!collapsed && item.badge"
              class="ml-auto rounded-full bg-sky-400/20 px-2 py-0.5 text-[10px] font-semibold text-sky-100"
            >{{ item.badge }}</span>
          </router-link>
        </template>
      </nav>

      <div class="border-t border-white/10 p-3">
        <div class="flex items-center gap-3 rounded-2xl bg-white/10 p-3 backdrop-blur" :class="collapsed ? 'justify-center' : ''">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-sm font-bold shadow-lg">
            {{ userInitials }}
          </div>
          <div v-if="!collapsed" class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold">{{ authStore.user?.full_name || 'User' }}</p>
            <p class="truncate text-xs text-sky-200/70">{{ roleLabel }}</p>
          </div>
          <button
            v-if="!collapsed"
            type="button"
            class="rounded-lg p-2 text-white/70 transition hover:bg-white/10 hover:text-rose-200"
            title="Logout"
            @click="logout"
          >
            <i class="fas fa-sign-out-alt"></i>
          </button>
        </div>
      </div>
    </aside>

    <div class="min-h-screen transition-all duration-300" :class="collapsed ? 'lg:ml-[72px]' : 'lg:ml-72'">
      <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
        <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="rounded-xl border border-slate-200 p-2.5 text-slate-600 lg:hidden"
              @click="mobileOpen = true"
            >
              <i class="fas fa-bars"></i>
            </button>
            <div>
              <h2 class="text-lg font-semibold text-slate-900 sm:text-xl">{{ pageTitle }}</h2>
              <p class="hidden text-xs text-slate-500 sm:block">{{ pageSubtitle }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="hidden rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600 sm:inline">
              {{ currentYear }} · Term {{ currentTerm }}
            </span>
            <button
              type="button"
              class="rounded-xl border border-slate-200 p-2.5 text-slate-500 hover:border-rose-200 hover:text-rose-600 lg:hidden"
              @click="logout"
            >
              <i class="fas fa-sign-out-alt"></i>
            </button>
          </div>
        </div>
      </header>

      <main class="p-4 sm:p-6 lg:p-8">
        <div
          v-if="route.query.denied === '1'"
          class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
        >
          You do not have permission to access that page.
        </div>
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import authStore from '../services/authStore.js';
import { filterNavSections, getRoleLabel } from '../config/accessControl.js';

const route = useRoute();
const router = useRouter();
const collapsed = ref(false);
const mobileOpen = ref(false);
const currentYear = ref(new Date().getFullYear());
const currentTerm = ref(1);

const allNavSections = [
  {
    title: 'Overview',
    items: [
      { to: '/hr-dashboard', label: 'HR Dashboard', icon: 'fas fa-chart-pie', zone: 'hr' },
      { to: '/dashboard', label: 'Performance', icon: 'fas fa-tachometer-alt', zone: 'academic' }
    ]
  },
  {
    title: 'Staff Management',
    items: [
      { to: '/teachers', label: 'Teaching Staff', icon: 'fas fa-chalkboard-teacher', zone: 'hr' },
      { to: '/non-teaching-staff', label: 'Non-Teaching Staff', icon: 'fas fa-user-tie', zone: 'hr' },
      { to: '/teaching-analytics', label: 'Teaching Analytics', icon: 'fas fa-chart-bar', zone: 'hr' },
      { to: '/class-teacher-assignments', label: 'Class Teachers', icon: 'fas fa-users-cog', zone: 'hr' }
    ]
  },
  {
    title: 'HR Operations',
    items: [
      { to: '/leave', label: 'Leave Management', icon: 'fas fa-plane-departure', zone: 'hr' },
      { to: '/payroll', label: 'Payroll', icon: 'fas fa-money-check-alt', zone: 'hr' },
      { to: '/departments', label: 'Departments', icon: 'fas fa-building', zone: 'hr' },
      { to: '/users', label: 'System Users', icon: 'fas fa-user-shield', zone: 'hr' }
    ]
  },
  {
    title: 'Academic Performance',
    items: [
      { to: '/timetable', label: 'Timetable', icon: 'fas fa-calendar-alt', zone: 'academic' },
      { to: '/subject-performance', label: 'Subject Performance', icon: 'fas fa-chart-line', zone: 'academic' },
      { to: '/lesson-monitoring', label: 'Lesson Monitoring', icon: 'fas fa-clock', zone: 'academic' },
      { to: '/observations', label: 'Observations', icon: 'fas fa-eye', zone: 'academic' },
      { to: '/duty-performance', label: 'Duty Performance', icon: 'fas fa-tasks', zone: 'academic' },
      { to: '/class-performance', label: 'Class Performance', icon: 'fas fa-users', zone: 'academic' },
      { to: '/reports', label: 'Reports', icon: 'fas fa-file-alt', zone: 'academic' }
    ]
  },
  {
    title: 'Students',
    items: [
      { to: '/digital-register', label: 'Student E-files', icon: 'fas fa-user-graduate', zone: 'academic' },
      { to: '/student-academic-track', label: 'Academic Track', icon: 'fas fa-graduation-cap', zone: 'academic' },
      { to: '/subjects-management', label: 'Subjects', icon: 'fas fa-book', zone: 'academic' },
      { to: '/classes-management', label: 'Classes', icon: 'fas fa-chalkboard', zone: 'academic' },
      { to: '/class-subjects-assignment', label: 'Class Subjects', icon: 'fas fa-link', zone: 'academic' },
      { to: '/bulk-import-results', label: 'Bulk Import', icon: 'fas fa-upload', zone: 'academic' }
    ]
  }
];

const navSections = computed(() =>
  filterNavSections(allNavSections, authStore.getRole())
);

const titles = {
  '/hr-dashboard': ['HR Dashboard', 'Staff overview & analytics'],
  '/dashboard': ['Performance Dashboard', 'Academic performance metrics'],
  '/teachers': ['Teaching Staff', 'Teachers directory & HR codes TS-xxx'],
  '/non-teaching-staff': ['Non-Teaching Staff', 'Support staff NTS-xxx'],
  '/teaching-analytics': ['Teaching Analytics', 'Workload & lesson attendance'],
  '/class-teacher-assignments': ['Class Teacher Assignments', 'Assign & reassign class teachers'],
  '/timetable': ['Timetable Management', 'Schedules, rooms & conflict detection'],
  '/leave': ['Leave Management', 'Requests, approvals & balances'],
  '/payroll': ['Payroll', 'Salaries, allowances & payslips'],
  '/departments': ['Departments Management', 'Manage school departments & positions'],
  '/users': ['User Management', 'System access control'],
  '/digital-register': ['Student E-files', 'Digital student records & documents']
};

const pageTitle = computed(() => titles[route.path]?.[0] || 'School HR System');
const pageSubtitle = computed(() => titles[route.path]?.[1] || '');

const userInitials = computed(() => {
  if (!authStore.user?.full_name) return 'U';
  return authStore.user.full_name.split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2);
});

const roleLabel = computed(() => getRoleLabel(authStore.getRole()));

const isActive = (path) => route.path === path;

const logout = () => {
  authStore.clearAuth();
  router.push('/login');
};
</script>

<style scoped>
.sidebar-shell {
  background: linear-gradient(165deg, #0c1929 0%, #0f2744 35%, #1e3a5f 70%, #1d4ed8 100%);
  box-shadow: 4px 0 24px rgba(15, 23, 42, 0.15);
}

.nav-item-active {
  background: linear-gradient(90deg, rgba(56, 189, 248, 0.25), rgba(59, 130, 246, 0.15));
  color: #fff;
  box-shadow: inset 3px 0 0 #38bdf8;
}

.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
}
</style>

