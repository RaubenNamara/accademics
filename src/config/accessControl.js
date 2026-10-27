/**
 * Role-based access: super_admin (all), admin (HR + school records), teacher (the academic office:
 * academics + school records) and subject_teacher (classroom teachers: their own portal only).
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

/** Staff dashboard (every staff role, not classroom teachers) */
export const STAFF_ROUTES = ['/hr-dashboard'];

/** Exams, report cards, report settings, lesson-plan review and teacher logins: admin + academic office */
export const SCHOOL_ROUTES = [
  '/examinations',
  '/report-cards',
  '/report-settings',
  '/lesson-plans',
  '/academic-calendar',
  '/curriculum-setup',
  '/teaching-assignments'
];

/** Classroom teacher portal */
/** Managing schools (SaaS): the platform owner only, i.e. a super admin of the home school */
export const PLATFORM_ROUTES = ['/platform/schools'];

export function isPlatformOwner(user) {
  return normalizeRole(user?.role) === ROLES.SUPER_ADMIN && user?.school?.is_home !== false;
}

export const PORTAL_ROUTES = [
  '/my/dashboard',
  '/my/timetable',
  '/my/lesson-plans',
  '/my/marks',
  '/my/performance',
  '/my/attendance',
  '/my/leave'
];

/** Routes allowed for HR (admin) */
export const HR_ROUTES = [
  '/hr-dashboard',
  '/teachers',
  '/non-teaching-staff',
  '/teaching-analytics',
  '/class-teacher-assignments',
  '/leave',
  '/departments',
  '/users'
];

/** Routes allowed for teachers (academics) */
export const ACADEMIC_ROUTES = [
  '/subject-performance',
  '/lesson-monitoring',
  '/observations',
  '/duty-performance',
  '/class-performance',
  '/reports',
  '/digital-register',
  '/student-academic-track',
  '/subjects-management',
  '/classes-management',
  '/bulk-import-results',
  '/timetable'
];

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
  if (r === ROLES.SUBJECT_TEACHER) return PORTAL_ROUTES.includes(route);
  // The portal belongs to a teacher record, so other roles never open it.
  if (PORTAL_ROUTES.includes(route)) return false;
  if (r === ROLES.SUPER_ADMIN) return true;
  if (STAFF_ROUTES.includes(route)) return r === ROLES.ADMIN || r === ROLES.TEACHER;
  if (SCHOOL_ROUTES.includes(route)) return r === ROLES.ADMIN || r === ROLES.TEACHER;
  if (r === ROLES.ADMIN) return HR_ROUTES.includes(route);
  if (r === ROLES.TEACHER) return ACADEMIC_ROUTES.includes(route);
  return false;
}

export function getDefaultRoute(role) {
  const r = normalizeRole(role);
  if (r === ROLES.SUBJECT_TEACHER) return '/my/dashboard';
  if (r === ROLES.TEACHER || r === ROLES.ADMIN || r === ROLES.SUPER_ADMIN) return '/hr-dashboard';
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
 * Items may define `zone`: 'hr' | 'academic' | 'school' (admin + academic office) | 'staff' (every staff role) | 'portal' (classroom teachers)
 */
export function filterNavSections(sections, role, user = null) {
  const r = normalizeRole(role);

  return sections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => {
        if (item.zone === 'platform') return isPlatformOwner(user || { role });
        if (r === ROLES.SUBJECT_TEACHER) return item.zone === 'portal';
        if (item.zone === 'portal') return false;
        if (r === ROLES.SUPER_ADMIN || item.zone === 'staff' || item.zone === 'school') return true;
        if (r === ROLES.ADMIN) return item.zone === 'hr';
        if (r === ROLES.TEACHER) return item.zone === 'academic';
        return false;
      })
    }))
    .filter((section) => section.items.length > 0);
}
