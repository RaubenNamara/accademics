/**
 * Role-based access. Four kinds of user:
 *  - super_admin: every page (Schools only for the platform owner).
 *  - admin: every page except Terms & Years, Schools, Classes & Subjects, System Users and the
 *    Academic Dashboard.
 *  - teacher (the academic office): only the academic pages listed in ACADEMIC_OFFICE_ROUTES.
 *  - subject_teacher (classroom teachers): their own portal only.
 * Shared routes are open to every signed-in role.
 */

export const ROLES = {
  SUPER_ADMIN: 'super_admin',
  ADMIN: 'admin',
  TEACHER: 'teacher',
  SUBJECT_TEACHER: 'subject_teacher'
};

/** Routes open to every signed-in role */
export const SHARED_ROUTES = ['/change-password'];

/** Managing schools (SaaS): the platform owner only, i.e. a super admin of the home school */
export const PLATFORM_ROUTES = ['/platform/schools'];

export function isPlatformOwner(user) {
  return normalizeRole(user?.role) === ROLES.SUPER_ADMIN && user?.school?.is_home !== false;
}

/** Classroom teacher portal */
export const PORTAL_ROUTES = [
  '/my/dashboard',
  '/my/timetable',
  '/my/lesson-plans',
  '/my/marks',
  '/my/performance',
  '/my/attendance',
  '/my/leave'
];

/** The academic office: its dashboard and the academic modules, nothing else. */
export const ACADEMIC_OFFICE_ROUTES = [
  '/academic-dashboard',
  '/teaching-assignments',
  '/class-teacher-assignments',
  '/teaching-analytics',
  '/timetable',
  '/lesson-monitoring',
  '/duty-performance',
  '/observations',
  '/lesson-observations',
  '/class-performance',
  '/lesson-plans',
  '/reports',
  '/examinations',
  '/report-cards',
  '/report-settings',
  '/student-academic-track',
  '/subject-performance',
  '/bulk-import-results',
  '/digital-register',
  '/classes-management',
  '/subjects-management',
  '/curriculum-setup'
];

/** Pages an admin does not get: Terms & Years, Classes & Subjects, System Users, the Academic Dashboard. */
export const ADMIN_EXCLUDED_ROUTES = [
  '/academic-dashboard',
  '/academic-calendar',
  '/classes-management',
  '/subjects-management',
  '/curriculum-setup',
  '/users'
];

/** Whether a route is one of the listed pages or a page under it (e.g. /duty-performance/12). */
const inList = (route, list) => list.some((p) => route === p || route.startsWith(`${p}/`));

export function normalizeRole(role) {
  if (!role) return '';
  const r = String(role).toLowerCase().trim().replace(/-/g, '_');
  if (r === 'superadmin' || r === 'super_admin') return ROLES.SUPER_ADMIN;
  if (r === 'hr_manager' || r === 'hr') return ROLES.ADMIN;
  if (r === 'academic_office' || r === 'academic') return ROLES.TEACHER;
  if (r === 'subject_teacher' || r === 'classroom_teacher') return ROLES.SUBJECT_TEACHER;
  return r;
}

export function getUserRole(user) {
  return normalizeRole(user?.role);
}

export function canAccessRoute(path, role, user = null) {
  const r = normalizeRole(role);
  const route = path.startsWith('/') ? path.split('?')[0] : `/${path.split('?')[0]}`;

  if (!r) return false;
  if (SHARED_ROUTES.includes(route)) return true;
  if (PLATFORM_ROUTES.includes(route)) return isPlatformOwner(user || { role });
  if (r === ROLES.SUBJECT_TEACHER) return inList(route, PORTAL_ROUTES);
  // The portal belongs to a teacher record, so other roles never open it.
  if (inList(route, PORTAL_ROUTES)) return false;
  if (r === ROLES.SUPER_ADMIN) return true;
  if (r === ROLES.ADMIN) return !inList(route, ADMIN_EXCLUDED_ROUTES);
  if (r === ROLES.TEACHER) return inList(route, ACADEMIC_OFFICE_ROUTES);
  return false;
}

export function getDefaultRoute(role) {
  const r = normalizeRole(role);
  if (r === ROLES.SUBJECT_TEACHER) return '/my/dashboard';
  if (r === ROLES.TEACHER) return '/academic-dashboard';
  if (r === ROLES.ADMIN || r === ROLES.SUPER_ADMIN) return '/hr-dashboard';
  return '/login';
}

/** user: the signed-in user; in a school other than the home school the top account is its School Admin. */
export function getRoleLabel(role, user = null) {
  const labels = {
    [ROLES.SUPER_ADMIN]: user?.school?.is_home === false ? 'School Admin' : 'Super Admin',
    [ROLES.ADMIN]: 'Admin',
    [ROLES.TEACHER]: 'Academic Office',
    [ROLES.SUBJECT_TEACHER]: 'Teacher'
  };
  return labels[normalizeRole(role)] || role;
}

/**
 * Filter navigation sections/items by role.
 * Items may define `tabs` ([{ to, label, icon }]): several pages under one entry, shown as tabs above the page.
 * An item is shown when its user may open its page (canAccessRoute); `zone: 'platform'` items only to the platform owner.
 */
export function filterNavSections(sections, role, user = null) {
  const allowed = (item) => (item.zone === 'platform' ? isPlatformOwner(user || { role }) : canAccessRoute(item.to, role, user));

  return sections
    .map((section) => ({
      ...section,
      items: section.items
        .map((item) => {
          // A grouped entry (tabs): keep the pages this user may open; it links to the first of them.
          if (!item.tabs) return allowed(item) ? item : null;
          const tabs = item.tabs.filter((t) => canAccessRoute(t.to, role, user));
          if (!tabs.length) return null;
          // Only one page of the group for this user: show it under its own name, without tabs.
          return tabs.length === 1 ? { to: tabs[0].to, label: tabs[0].label.charAt(0).toUpperCase() + tabs[0].label.slice(1), icon: tabs[0].icon } : { ...item, tabs, to: tabs[0].to };
        })
        .filter(Boolean)
    }))
    .filter((section) => section.items.length > 0);
}
