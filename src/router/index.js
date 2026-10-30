import { createRouter, createWebHashHistory } from 'vue-router';
import authStore from '../services/authStore.js';
import { canAccessRoute, getDefaultRoute, getUserRole } from '../config/accessControl.js';

// Import views
import Login from '../views/Login.vue';
import Layout from '../components/Layout.vue';
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
import HrDashboard from '../views/HrDashboard.vue';
import NonTeachingStaff from '../views/NonTeachingStaff.vue';
import TimetableManagement from '../views/TimetableManagement.vue';
import ClassTeacherAssignments from '../views/ClassTeacherAssignments.vue';
import LeaveManagement from '../views/LeaveManagement.vue';
import TeachingAnalytics from '../views/TeachingAnalytics.vue';
import DepartmentsManagement from '../views/DepartmentsManagement.vue';
import ChangePassword from '../views/ChangePassword.vue';
import Examinations from '../views/Examinations.vue';
import ReportCards from '../views/ReportCards.vue';
import ReportSettings from '../views/ReportSettings.vue';
import LessonPlansReview from '../views/LessonPlansReview.vue';
import AcademicCalendar from '../views/AcademicCalendar.vue';
import CurriculumSetup from '../views/CurriculumSetup.vue';
import MyDashboard from '../views/portal/MyDashboard.vue';
import MyTimetable from '../views/portal/MyTimetable.vue';
import MyLessonPlans from '../views/portal/MyLessonPlans.vue';
import MyMarks from '../views/portal/MyMarks.vue';
import MyPerformance from '../views/portal/MyPerformance.vue';
import MyAttendance from '../views/portal/MyAttendance.vue';

const routes = [
  {
    path: '/login',
    component: Login,
    meta: { public: true },
  },
  { path: '/change-password', component: ChangePassword },
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
      { path: 'hr-dashboard', component: HrDashboard, meta: { zone: 'hr' } },
      { path: 'academic-dashboard', component: () => import('../views/AcademicDashboard.vue'), meta: { zone: 'academic' } },
      { path: 'teachers', component: Teachers, meta: { zone: 'hr' } },
      { path: 'non-teaching-staff', component: NonTeachingStaff, meta: { zone: 'hr' } },
      { path: 'teaching-analytics', component: TeachingAnalytics, meta: { zone: 'hr' } },
      { path: 'class-teacher-assignments', component: ClassTeacherAssignments, meta: { zone: 'hr' } },
      { path: 'timetable', component: TimetableManagement, meta: { zone: 'academic' } },
      { path: 'leave', component: LeaveManagement, meta: { zone: 'hr' } },
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
      { path: 'bulk-import-results', component: BulkImportResults, meta: { zone: 'academic' } },
      { path: 'examinations', component: Examinations, meta: { zone: 'school' } },
      { path: 'report-cards', component: ReportCards, meta: { zone: 'school' } },
      { path: 'report-settings', component: ReportSettings, meta: { zone: 'school' } },
      { path: 'lesson-plans', component: LessonPlansReview, meta: { zone: 'school' } },
      // Teacher logins now live on Teaching Staff (Logins view).
      { path: 'teacher-logins', redirect: { path: '/teachers', query: { view: 'logins' } } },
      { path: 'academic-calendar', component: AcademicCalendar, meta: { zone: 'school' } },
      { path: 'curriculum-setup', component: CurriculumSetup, meta: { zone: 'school' } },
      { path: 'teaching-assignments', component: () => import('../views/TeachingAssignments.vue'), meta: { zone: 'school' } },
      { path: 'platform/schools', component: () => import('../views/PlatformSchools.vue'), meta: { zone: 'platform' } },
      { path: 'my/dashboard', component: MyDashboard, meta: { zone: 'portal' } },
      { path: 'my/timetable', component: MyTimetable, meta: { zone: 'portal' } },
      { path: 'my/lesson-plans', component: MyLessonPlans, meta: { zone: 'portal' } },
      { path: 'my/marks', component: MyMarks, meta: { zone: 'portal' } },
      { path: 'my/performance', component: MyPerformance, meta: { zone: 'portal' } },
      { path: 'my/attendance', component: MyAttendance, meta: { zone: 'portal' } },
      { path: 'my/leave', component: () => import('../views/portal/MyLeave.vue'), meta: { zone: 'portal' } },
    ],
  },
  // Unknown or removed pages (e.g. old bookmarks) go to the user's home page.
  { path: '/:pathMatch(.*)*', redirect: () => getDefaultRoute(getUserRole(authStore.user)) },
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

  // A temporary password must be replaced before anything else.
  const mustChange = !!authStore.user?.must_change_password;
  if (mustChange && to.path !== '/change-password') {
    return next('/change-password');
  }
  if (!mustChange && to.path === '/change-password' && to.query.voluntary !== '1') {
    return next(home);
  }

  if (isAuthenticated && to.path !== '/' && !canAccessRoute(to.path, role, authStore.user)) {
    return next({
      path: home,
      query: { denied: '1' },
    });
  }

  next();
});

export default router;