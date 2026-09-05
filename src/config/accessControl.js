/**
 * Role-based access: super_admin (all), admin (HR only), teacher (academics only).
 */

export const ROLES = {
  SUPER_ADMIN: 'super_admin',
  ADMIN: 'admin',
  TEACHER: 'teacher'
};

/** Routes allowed for HR (admin) */
export const HR_ROUTES = [
  '/hr-dashboard',
  '/teachers',
  '/non-teaching-staff',
  '/teaching-analytics',
  '/class-teacher-assignments',
  '/leave',
  '/payroll',
  '/users'
];

/** Routes allowed for teachers (academics) */
export const ACADEMIC_ROUTES = [
  '/dashboard',
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
  '/class-subjects-assignment',
  '/bulk-import-results',
  '/timetable'
];

export function normalizeRole(role) {
  if (!role) return '';
  const r = String(role).toLowerCase().trim().replace(/-/g, '_');
  if (r === 'superadmin' || r === 'super_admin') return ROLES.SUPER_ADMIN;
  if (r === 'hr_manager' || r === 'hr') return ROLES.ADMIN;
  if (r === 'academic_office' || r === 'academic') return ROLES.TEACHER;
  return r;
}

export function getUserRole(user) {
  return normalizeRole(user?.role);
}

export function canAccessRoute(path, role) {
  const r = normalizeRole(role);
  const route = path.startsWith('/') ? path.split('?')[0] : `/${path.split('?')[0]}`;

  if (!r) return false;
  if (r === ROLES.SUPER_ADMIN) return true;
  if (r === ROLES.ADMIN) return HR_ROUTES.includes(route);
  if (r === ROLES.TEACHER) return ACADEMIC_ROUTES.includes(route);
  return false;
}

export function getDefaultRoute(role) {
  const r = normalizeRole(role);
  if (r === ROLES.TEACHER) return '/dashboard';
  if (r === ROLES.ADMIN || r === ROLES.SUPER_ADMIN) return '/hr-dashboard';
  return '/login';
}

export function getRoleLabel(role) {
  const labels = {
    [ROLES.SUPER_ADMIN]: 'Super Admin',
    [ROLES.ADMIN]: 'Admin',
    [ROLES.TEACHER]: 'Teacher'
  };
  return labels[normalizeRole(role)] || role;
}

/**
 * Filter navigation sections/items by role.
 * Items may define `zone`: 'hr' | 'academic'
 */
export function filterNavSections(sections, role) {
  const r = normalizeRole(role);

  return sections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => {
        if (r === ROLES.SUPER_ADMIN) return true;
        if (r === ROLES.ADMIN) return item.zone === 'hr';
        if (r === ROLES.TEACHER) return item.zone === 'academic';
        return false;
      })
    }))
    .filter((section) => section.items.length > 0);
}
