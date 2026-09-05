import { createRouter, createWebHashHistory } from 'vue-router';
import authStore from '../services/authStore.js';
import { canAccessRoute, getDefaultRoute, getUserRole } from '../config/accessControl.js';

// Import views
import Login from '../views/Login.vue';
import Layout from '../components/Layout.vue';
import Dashboard from '../views/Dashboard.vue';
import Teachers from '../views/Teachers.vue';
import Users from '../views/Users.vue';
import SubjectPerformance from '../views/SubjectPerformance.vue';
import LessonMonitoring from '../views/LessonMonitoring.vue';
import LessonObservation from '../views/LessonObservation.vue';
import LessonObservations from '../views/LessonObservations.vue';
import DutyPerformance from '../views/DutyPerformance.vue';
import DutyPerformanceView from '../views/DutyPerformanceView.vue';
import ClassTeacherPerformance from '../views/ClassTeacherPerformance.vue';
import Reports from '../views/Reports.vue';
import DigitalRegister from '../views/DigitalRegister.vue';
import StudentAcademicTrack from '../views/StudentAcademicTrack.vue';
import SubjectsManagement from '../views/SubjectsManagement.vue';
import BulkImportResults from '../views/BulkImportResults.vue';
import ClassesManagement from '../views/ClassesManagement.vue';
import ClassSubjectsAssignment from '../views/ClassSubjectsAssignment.vue';
import HrDashboard from '../views/HrDashboard.vue';
import NonTeachingStaff from '../views/NonTeachingStaff.vue';
import TimetableManagement from '../views/TimetableManagement.vue';
import ClassTeacherAssignments from '../views/ClassTeacherAssignments.vue';
import LeaveManagement from '../views/LeaveManagement.vue';
import PayrollManagement from '../views/PayrollManagement.vue';
import TeachingAnalytics from '../views/TeachingAnalytics.vue';
import DepartmentsManagement from '../views/DepartmentsManagement.vue';

const routes = [
  {
    path: '/login',
    component: Login,
    meta: { public: true },
  },
  {
    path: '/',
    component: Layout,
    children: [
      {
        path: '',
        redirect: () => {
          const isAuthenticated = authStore.isAuthenticated();

          if (!isAuthenticated) {
            return '/login';
          }

          const role = getUserRole(authStore.user);
          return getDefaultRoute(role);
        },
      },
      { path: 'dashboard', component: Dashboard, meta: { zone: 'academic' } },
      { path: 'hr-dashboard', component: HrDashboard, meta: { zone: 'hr' } },
      { path: 'teachers', component: Teachers, meta: { zone: 'hr' } },
      { path: 'non-teaching-staff', component: NonTeachingStaff, meta: { zone: 'hr' } },
      { path: 'teaching-analytics', component: TeachingAnalytics, meta: { zone: 'hr' } },
      { path: 'class-teacher-assignments', component: ClassTeacherAssignments, meta: { zone: 'hr' } },
      { path: 'timetable', component: TimetableManagement, meta: { zone: 'academic' } },
      { path: 'leave', component: LeaveManagement, meta: { zone: 'hr' } },
      { path: 'payroll', component: PayrollManagement, meta: { zone: 'hr' } },
      { path: 'departments', component: DepartmentsManagement, meta: { zone: 'hr' } },
      { path: 'users', component: Users, meta: { zone: 'hr' } },
      { path: 'subject-performance', component: SubjectPerformance, meta: { zone: 'academic' } },
      { path: 'lesson-monitoring', component: LessonMonitoring, meta: { zone: 'academic' } },
      { path: 'observations', component: LessonObservation, meta: { zone: 'academic' } },
      { path: 'lesson-observations', component: LessonObservations, meta: { zone: 'academic' } },
      { path: 'duty-performance', component: DutyPerformance, meta: { zone: 'academic' } },
      { path: 'duty-performance/:id', component: DutyPerformanceView, meta: { zone: 'academic' } },
      { path: 'class-performance', component: ClassTeacherPerformance, meta: { zone: 'academic' } },
      { path: 'reports', component: Reports, meta: { zone: 'academic' } },
      { path: 'digital-register', component: DigitalRegister, meta: { zone: 'academic' } },
      { path: 'student-academic-track', component: StudentAcademicTrack, meta: { zone: 'academic' } },
      { path: 'subjects-management', component: SubjectsManagement, meta: { zone: 'academic' } },
      { path: 'classes-management', component: ClassesManagement, meta: { zone: 'academic' } },
      { path: 'class-subjects-assignment', component: ClassSubjectsAssignment, meta: { zone: 'academic' } },
      { path: 'bulk-import-results', component: BulkImportResults, meta: { zone: 'academic' } },
    ],
  },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  const isAuthenticated = authStore.isAuthenticated();
  const role = getUserRole(authStore.user);
  const home = isAuthenticated ? getDefaultRoute(role) : '/login';

  if (to.path === '/login') {
    if (isAuthenticated) {
      return next(home);
    }
    return next();
  }

  if (!to.meta?.public && !isAuthenticated) {
    return next('/login');
  }

  if (isAuthenticated && to.path !== '/' && !canAccessRoute(to.path, role)) {
    return next({
      path: home,
      query: { denied: '1' },
    });
  }

  next();
});

export default router;