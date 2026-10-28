<template>
  <div class="min-h-screen">
    <!-- Sidebar: a floating white card with a light green edge (same frame as eSpace/Tusome) -->
    <aside
      class="app-sidebar fixed bottom-3 left-0 top-3 z-50 flex flex-col overflow-hidden rounded-r-2xl transition-all duration-300 print:hidden lg:left-3 lg:rounded-2xl"
      :class="[collapsed ? 'lg:w-16' : 'lg:w-56', 'w-60', mobileOpen ? 'translate-x-0' : '-translate-x-[calc(100%+1rem)] lg:translate-x-0']"
      aria-label="Main navigation"
    >
      <div class="flex-shrink-0 border-b px-4 py-3.5" :class="frameDivider">
        <RouterLink :to="authStore.getHomeRoute()" class="flex items-center gap-3" :class="collapsed ? 'lg:justify-center' : ''" @click="mobileOpen = false">
          <SkoolMark v-if="collapsed" :size="36" class="hidden flex-shrink-0 lg:block" />
          <span :class="collapsed ? 'lg:hidden' : ''" class="min-w-0">
            <SkoolLogo :size="22" />
            <span v-if="authStore.user?.school?.name" class="mt-1 line-clamp-2 block max-w-[10rem] text-[11px] font-medium leading-tight text-brand-700" :title="authStore.user.school.name">{{ authStore.user.school.name }}</span>
          </span>
        </RouterLink>
      </div>

      <nav class="scrollbar-thin relative flex-1 overflow-y-auto px-3 py-4">
        <section v-for="(group, gi) in navSections" :key="group.title" class="nav-group" :class="{ 'is-folded': isFolded(group.title) && !collapsed }">
          <button
            v-if="gi > 0 && !collapsed"
            type="button"
            class="nav-fold"
            :aria-expanded="!isFolded(group.title)"
            :title="isFolded(group.title) ? `Show ${group.title}` : `Fold ${group.title}`"
            @click="toggleFold(group.title)"
          >
            <span class="nav-fold-line" aria-hidden="true"></span>
            <span class="nav-fold-label">
              <span class="truncate">{{ group.title }}</span>
              <span v-if="group.items.some((i) => isActive(i.to))" class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-brand-500"></span>
              <span class="ml-auto text-[10px] font-semibold tabular-nums text-gray-400">{{ group.items.length }}</span>
            </span>
            <ChevronDown class="nav-fold-chevron" aria-hidden="true" />
          </button>
          <div v-else-if="gi > 0" class="mx-2 mb-2.5 h-px bg-brand-600/20"></div>

          <div class="nav-fold-body">
            <div class="nav-fold-inner space-y-0.5">
              <RouterLink
                v-for="item in group.items"
                :key="item.label"
                :to="item.to"
                class="group flex items-center gap-2.5 rounded-xl py-1.5 pl-3 pr-2.5 text-sm font-medium transition-all duration-150"
                :class="[
                  itemActive(item)
                    ? 'bg-brand-100 font-semibold text-brand-900 ring-1 ring-brand-200'
                    : 'text-gray-800 hover:bg-brand-50 hover:text-brand-800',
                  collapsed ? 'lg:justify-center lg:px-0' : ''
                ]"
                :title="collapsed ? item.label : undefined"
                :aria-current="itemActive(item) ? 'page' : undefined"
                @click="mobileOpen = false"
              >
                <span
                  class="relative flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-md transition-all duration-150"
                  :class="itemActive(item) ? 'bg-brand-600 text-white shadow-sm' : 'text-brand-700 group-hover:text-brand-800'"
                >
                  <component :is="item.icon" class="h-4 w-4" :stroke-width="1.9" />
                  <span v-if="navBadge(item.to) && collapsed" class="absolute -right-1 -top-1 hidden h-2.5 w-2.5 rounded-full bg-red-600 ring-2 ring-white lg:block"></span>
                </span>
                <span class="truncate" :class="collapsed ? 'lg:hidden' : ''">{{ item.label }}</span>
                <span
                  v-if="navBadge(item.to)"
                  class="ml-auto rounded-full bg-red-600 px-1.5 py-px text-[10px] font-bold leading-4 text-white"
                  :class="collapsed ? 'lg:hidden' : ''"
                  :title="`${navBadge(item.to)} new lesson plan(s) to review`"
                >{{ navBadge(item.to) }}</span>
              </RouterLink>
            </div>
          </div>
        </section>
      </nav>

      <div class="flex-shrink-0 border-t p-2.5" :class="frameDivider">
        <button
          type="button"
          class="flex w-full items-center gap-2.5 rounded-xl py-1.5 pl-3 pr-2.5 text-red-600 transition-colors duration-150 hover:bg-red-50"
          :class="collapsed ? 'lg:justify-center lg:px-0' : ''"
          @click="logout"
        >
          <LogOut class="h-4 w-4 flex-shrink-0" />
          <span class="text-sm font-medium" :class="collapsed ? 'lg:hidden' : ''">Logout</span>
        </button>
      </div>
    </aside>

    <!-- Collapse handle on the sidebar's edge -->
    <button
      type="button"
      class="fixed top-1/2 z-50 hidden h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full border border-gray-400 bg-white text-gray-600 shadow-md transition-all duration-300 hover:text-brand-700 print:hidden lg:flex"
      :style="{ left: collapsed ? '64px' : '212px' }"
      :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      @click="toggleCollapsed"
    >
      <ChevronLeft class="h-3.5 w-3.5 transition-transform duration-300" :class="{ 'rotate-180': collapsed }" />
    </button>

    <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="mobileOpen = false" />

    <div class="flex min-h-screen flex-col transition-all duration-300 print:!ml-0" :class="collapsed ? 'lg:ml-[88px]' : 'lg:ml-[248px]'">
      <header class="app-header sticky top-0 z-30 print:hidden">
        <div class="flex items-center justify-between gap-3 px-3 py-2 sm:px-5">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-lg p-2 text-gray-600 transition-colors hover:bg-gray-100"
              aria-label="Menu"
              @click="toggleMenu"
            >
              <Menu class="h-6 w-6" />
            </button>
          </div>

          <div class="flex items-center gap-3">
            <!-- The term every page shows: the open term unless an admin picks another -->
            <label v-if="!isPortal && termStore.state.sessions.length" class="hidden items-center gap-2 sm:flex">
              <span class="sr-only">Term shown</span>
              <CalendarRange class="h-4 w-4 text-brand-700" aria-hidden="true" />
              <select
                :value="termStore.selected.value?.id"
                class="rounded-full border py-1 pl-3 pr-8 text-xs font-medium outline-none focus:ring-2 focus:ring-brand-100"
                :class="termStore.isViewingOpen.value ? 'border-gray-200 bg-white text-gray-700' : 'border-amber-300 bg-amber-50 text-amber-900'"
                @change="termStore.select($event.target.value)"
              >
                <option v-for="s in termStore.state.sessions" :key="s.id" :value="s.id">
                  {{ termStore.label(s) }}{{ { open: ' · open', reopened: ' · reopened', closed: ' · closed' }[termStore.statusOf(s)] || '' }}
                </option>
              </select>
            </label>
            <span v-else class="hidden rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 sm:inline">
              {{ termStore.open.value ? termStore.label(termStore.open.value) : currentYear }}
            </span>
            <button
              type="button"
              class="theme-toggle"
              :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
              :title="theme === 'dark' ? 'Light mode' : 'Dark mode'"
              @click="toggleTheme"
            >
              <Sun v-if="theme === 'dark'" class="h-[1.15rem] w-[1.15rem]" />
              <Moon v-else class="h-[1.15rem] w-[1.15rem]" />
            </button>
            <div class="flex items-center gap-2.5">
              <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white">{{ userInitials }}</span>
              <span class="hidden min-w-0 leading-tight sm:block">
                <span class="block max-w-[11rem] truncate text-sm font-medium text-gray-900">{{ authStore.user?.full_name || 'User' }}</span>
                <span class="block text-xs text-gray-500">{{ roleLabel }}</span>
              </span>
            </div>
          </div>
        </div>
      </header>

      <main class="flex-1 px-3 py-3 sm:px-5 sm:py-4 print:p-0">
        <div class="mx-auto w-full max-w-[1920px]" :class="{ 'has-hub': currentHub }">
          <div v-if="!isPortal && termStore.selected.value && !termStore.isViewingOpen.value" class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
            <span v-if="termStore.statusOf(termStore.selected.value) === 'reopened'">You are viewing <strong>{{ termStore.label(termStore.selected.value) }}</strong>, reopened for data entry. The current term<template v-if="termStore.open.value">, {{ termStore.label(termStore.open.value) }},</template> stays open.</span>
            <span v-else>You are viewing <strong>{{ termStore.label(termStore.selected.value) }}</strong> ({{ termStore.statusOf(termStore.selected.value) }}). Marks can only be entered in the open term<template v-if="termStore.open.value">, {{ termStore.label(termStore.open.value) }}</template>.</span>
            <button v-if="termStore.open.value" type="button" class="font-semibold text-amber-900 underline" @click="termStore.reset()">Back to the open term</button>
          </div>
          <div v-if="route.query.denied === '1'" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            You do not have permission to access that page.
          </div>
          <nav v-if="currentHub" class="hub-tabs mb-4 print:hidden" :aria-label="currentHub.label">
            <RouterLink
              v-for="t in currentHub.tabs"
              :key="t.to"
              :to="t.to"
              class="hub-tab"
              :class="isActive(t.to) ? 'is-active' : ''"
              :aria-current="isActive(t.to) ? 'page' : undefined"
            >
              <component :is="t.icon" class="flex-shrink-0" :stroke-width="2" /><span>{{ t.label }}</span>
            </RouterLink>
          </nav>
          <router-view />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  LayoutDashboard, GraduationCap, UserRound, ChartColumn, UsersRound, BookUser, PlaneTakeoff, Building2, ShieldCheck,
  CalendarDays, ChartLine, Clock, Eye, ListChecks, Users, FileText, FolderOpen, BookOpen, School, Upload,
  LogOut, Menu, Building, Sun, Moon, ChevronLeft, ChevronDown, ClipboardList, NotebookPen, PenLine, Award, FileBadge, Settings,
  CalendarCheck, TrendingUp, House, CalendarRange, CalendarCog, BookOpenCheck,
} from 'lucide-vue-next';
import authStore from '../services/authStore.js';
import termStore from '../services/termStore.js';
import { theme, toggleTheme } from '../services/theme.js';
import { canAccessRoute, filterNavSections, getRoleLabel } from '../config/accessControl.js';
import { newLessonPlans, refreshNewLessonPlans } from '../services/lessonPlanBadge.js';
import SkoolMark from './SkoolMark.vue';
import SkoolLogo from './SkoolLogo.vue';

const route = useRoute();
const router = useRouter();
const frameDivider = 'border-[rgba(21,80,50,0.1)]';

// Remember the sidebar's state per browser (it may be unavailable in private windows).
const stored = (key, fallback) => {
  try { return JSON.parse(localStorage.getItem(key) ?? 'null') ?? fallback; } catch { return fallback; }
};
const store = (key, value) => {
  try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* not available */ }
};

const collapsed = ref(stored('acc.sidebar.collapsed', false));
const folded = ref(stored('acc.sidebar.folded', []));
const mobileOpen = ref(false);
const currentYear = new Date().getFullYear();

// Related pages share one entry; their pages show as tabs above the page (each keeps its own address).
const allNavSections = [
  { title: 'Overview', items: [
    { to: '/hr-dashboard', label: 'HR Dashboard', icon: LayoutDashboard, zone: 'staff' },
  ] },
  { title: 'My Work', items: [
    { to: '/my/dashboard', label: 'My Dashboard', icon: House, zone: 'portal' },
    { to: '/my/timetable', label: 'My Timetable', icon: CalendarDays, zone: 'portal' },
    { to: '/my/lesson-plans', label: 'Lesson Plans', icon: NotebookPen, zone: 'portal' },
    { to: '/my/marks', label: 'Enter Marks', icon: PenLine, zone: 'portal' },
  ] },
  { title: 'My Record', items: [
    { to: '/my/performance', label: 'My Performance', icon: TrendingUp, zone: 'portal' },
    { to: '/my/attendance', label: 'Lesson Attendance', icon: CalendarCheck, zone: 'portal' },
    { to: '/my/leave', label: 'My Leave', icon: PlaneTakeoff, zone: 'portal' },
  ] },
  { title: 'Staff', items: [
    { label: 'Staff', icon: GraduationCap, tabs: [
      { to: '/teachers', label: 'Teaching staff', icon: GraduationCap },
      { to: '/non-teaching-staff', label: 'Non-teaching staff', icon: UserRound },
      { to: '/departments', label: 'Departments', icon: Building2 },
    ] },
    { label: 'Assignments', icon: BookUser, tabs: [
      { to: '/teaching-assignments', label: 'Subjects & classes', icon: BookUser },
      { to: '/class-teacher-assignments', label: 'Class teachers', icon: UsersRound },
    ] },
    { to: '/teaching-analytics', label: 'Teaching Analytics', icon: ChartColumn, zone: 'hr' },
    { to: '/leave', label: 'Leave Management', icon: PlaneTakeoff, zone: 'hr' },
    { to: '/users', label: 'System Users', icon: ShieldCheck, zone: 'hr' },
  ] },
  { title: 'Teaching', items: [
    { to: '/timetable', label: 'Timetable', icon: CalendarDays, zone: 'academic' },
    { to: '/lesson-monitoring', label: 'Lesson Monitoring', icon: Clock, zone: 'academic' },
    { to: '/lesson-plans', label: 'Lesson Plans', icon: NotebookPen, zone: 'school' },
    { label: 'Staff Evaluation', icon: Award, tabs: [
      { to: '/observations', label: 'Lesson observations', icon: Eye },
      { to: '/duty-performance', label: 'Duty performance', icon: ListChecks },
      { to: '/class-performance', label: 'Class teacher performance', icon: Users },
    ] },
    { to: '/reports', label: 'Reports', icon: FileText, zone: 'academic' },
  ] },
  { title: 'Exams & Results', items: [
    { to: '/examinations', label: 'Examinations', icon: ClipboardList, zone: 'school' },
    { label: 'Report Cards', icon: FileBadge, tabs: [
      { to: '/report-cards', label: 'Report cards', icon: FileBadge },
      { to: '/report-settings', label: 'Logo, address & grading', icon: Settings },
    ] },
    { label: 'Results', icon: ChartLine, tabs: [
      { to: '/student-academic-track', label: 'Student marks', icon: GraduationCap },
      { to: '/subject-performance', label: 'Subject performance', icon: ChartLine },
      { to: '/bulk-import-results', label: 'Bulk import', icon: Upload },
    ] },
  ] },
  { title: 'Students', items: [
    { to: '/digital-register', label: 'Student E-files', icon: FolderOpen, zone: 'academic' },
    { label: 'Classes & Subjects', icon: School, tabs: [
      { to: '/classes-management', label: 'Classes', icon: School },
      { to: '/subjects-management', label: 'Subjects', icon: BookOpen },
      { to: '/curriculum-setup', label: 'Curriculum', icon: BookOpenCheck },
    ] },
  ] },
  { title: 'Settings', items: [
    { to: '/academic-calendar', label: 'Terms & Years', icon: CalendarCog, zone: 'school' },
    { to: '/platform/schools', label: 'Schools', icon: Building, zone: 'platform' },
  ] },
];

const navSections = computed(() => filterNavSections(allNavSections, authStore.getRole(), authStore.user));
const roleLabel = computed(() => getRoleLabel(authStore.getRole(), authStore.user));
const isPortal = computed(() => authStore.getRole() === 'subject_teacher');
termStore.load().catch(() => {});
const userInitials = computed(() => {
  const name = authStore.user?.full_name;
  if (!name) return 'U';
  return name.split(' ').filter(Boolean).map((n) => n[0]).join('').toUpperCase().slice(0, 2);
});

const isActive = (path) => route.path === path || route.path.startsWith(`${path}/`);
const itemActive = (item) => (item.tabs ? item.tabs.some((t) => isActive(t.to)) : isActive(item.to));
// The grouped entry the current page belongs to: its pages show as tabs above the page.
const currentHub = computed(() => navSections.value.flatMap((g) => g.items).find((i) => i.tabs && i.tabs.length > 1 && itemActive(i)) || null);
const isFolded = (title) => folded.value.includes(title);
const toggleFold = (title) => {
  folded.value = isFolded(title) ? folded.value.filter((t) => t !== title) : [...folded.value, title];
  store('acc.sidebar.folded', folded.value);
};
const toggleCollapsed = () => {
  collapsed.value = !collapsed.value;
  store('acc.sidebar.collapsed', collapsed.value);
};
const toggleMenu = () => {
  if (window.matchMedia('(min-width: 1024px)').matches) toggleCollapsed();
  else mobileOpen.value = !mobileOpen.value;
};

// Opening a page inside a folded group unfolds it, so you can always see where you are.
watch(() => route.path, () => {
  const group = navSections.value.find((g) => g.items.some((i) => itemActive(i)));
  if (group && isFolded(group.title)) toggleFold(group.title);
}, { immediate: true });

// Red count on "Lesson Plans" for reviewers: plans submitted that nobody has opened yet.
const canReviewPlans = computed(() => canAccessRoute('/lesson-plans', authStore.user?.role, authStore.user));
const navBadge = (to) => (to === '/lesson-plans' && canReviewPlans.value ? newLessonPlans.value : 0);
watch(() => route.path, () => { if (canReviewPlans.value) refreshNewLessonPlans(); }, { immediate: true });
const badgeTimer = setInterval(() => { if (canReviewPlans.value && document.visibilityState === 'visible') refreshNewLessonPlans(); }, 60000);
onBeforeUnmount(() => clearInterval(badgeTimer));

const logout = () => {
  termStore.reset();
  authStore.clearAuth();
  router.push('/login');
};
</script>

<style scoped>
/* Tabs for the pages of one sidebar entry: small buttons with a deep orange border. */
.hub-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.hub-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.75rem;
  border: 1.5px solid #ea580c;
  border-radius: 0.5rem;
  background: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #9a3412;
  white-space: nowrap;
  transition: background-color 0.15s, color 0.15s, box-shadow 0.15s;
}
.hub-tab svg { width: 0.9rem; height: 0.9rem; }
.hub-tab:hover { background: #fff7ed; }
.hub-tab.is-active { background: #ffedd5; color: #9a3412; font-weight: 600; }
html.dark .hub-tab { background: transparent; border-color: #f97316; color: #fdba74; }
html.dark .hub-tab:hover { background: rgba(249, 115, 22, 0.12); }
html.dark .hub-tab.is-active { background: rgba(249, 115, 22, 0.2); border-color: #f97316; color: #fed7aa; }

.theme-toggle {
  display: inline-flex;
  height: 2.4rem;
  width: 2.4rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  border: 1px solid rgb(229 231 235);
  background: #fff;
  color: rgb(75 85 99);
  transition: background-color 0.2s, color 0.2s, transform 0.3s;
}
.theme-toggle:hover { background: rgb(236 253 245); color: rgb(4 120 87); transform: rotate(-12deg); }
.theme-toggle:focus-visible { outline: 2px solid #10b981; outline-offset: 2px; }
html.dark .theme-toggle { background: #16211c; border-color: #31413a; color: #fcd34d; }
html.dark .theme-toggle:hover { background: #1a2621; color: #fde68a; }

.app-sidebar {
  background: var(--glass-bg-strong);
  border: 1px solid var(--glass-border);
  box-shadow: var(--glass-shadow);
  -webkit-backdrop-filter: var(--glass-blur);
  backdrop-filter: var(--glass-blur);
}
.app-header {
  background: var(--glass-bg-strong);
  border-bottom: 1px solid var(--glass-edge);
  -webkit-backdrop-filter: var(--glass-blur);
  backdrop-filter: var(--glass-blur);
}

/* Sidebar groups: open = a thin green line; folded = the group's name in a soft row. */
.nav-group { margin-bottom: 1.1rem; transition: margin 0.3s ease; }
.nav-group.is-folded { margin-bottom: 0.35rem; }
.nav-fold {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  width: calc(100% - 0.75rem);
  margin: 0 0.375rem 0.55rem;
  min-height: 0.9rem;
  padding: 0 0.375rem;
  border-radius: 0.75rem;
  color: rgb(100 116 139);
  transition: background-color 0.2s, padding 0.3s ease, margin 0.3s ease;
}
.nav-fold:focus-visible { outline: 2px solid rgb(5 150 105); outline-offset: 1px; }
.nav-fold-line {
  flex: 1 1 auto;
  height: 1px;
  background: rgba(5, 150, 105, 0.2);
  transition: transform 0.3s ease, opacity 0.2s ease;
  transform-origin: left;
}
.nav-fold-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex: 0 1 0;
  min-width: 0;
  max-width: 0;
  overflow: hidden;
  opacity: 0;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
  transition: opacity 0.25s ease 0.05s, max-width 0.3s ease;
}
.nav-fold-chevron {
  width: 0.8rem;
  height: 0.8rem;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.2s, transform 0.3s ease;
}
.nav-fold:hover .nav-fold-chevron,
.nav-fold:focus-visible .nav-fold-chevron { opacity: 0.75; }
.nav-fold:hover .nav-fold-line { background: rgba(5, 150, 105, 0.45); }
@media (hover: none) { .nav-fold-chevron { opacity: 0.45; } }

.is-folded .nav-fold {
  min-height: 2rem;
  margin-bottom: 0;
  padding: 0 0.6rem 0 0.9rem;
  background: rgba(5, 150, 105, 0.06);
}
.is-folded .nav-fold:hover { background: rgba(5, 150, 105, 0.12); color: rgb(6 95 70); }
.is-folded .nav-fold-line { transform: scaleX(0); opacity: 0; flex: 0 0 0; }
.is-folded .nav-fold-label { flex: 1 1 auto; max-width: 100%; opacity: 1; }
.is-folded .nav-fold-chevron { opacity: 0.6; transform: rotate(-90deg); }

.nav-fold-body {
  display: grid;
  grid-template-rows: 1fr;
  transition: grid-template-rows 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.is-folded .nav-fold-body { grid-template-rows: 0fr; }
.nav-fold-inner { min-height: 0; overflow: hidden; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .is-folded .nav-fold:hover {
  color: var(--d-text);
}
</style>
