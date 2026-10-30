import axios from 'axios';

const API_BASE_URL = import.meta.env.DEV
  ? 'http://localhost/accademics/backend/api/'
  : '/backend/api/';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: 'application/json'
  },
  timeout: 30000
});

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');

    config.headers = config.headers || {};

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    const isFormData =
      typeof FormData !== 'undefined' && config.data instanceof FormData;

    if (isFormData) {
      delete config.headers['Content-Type'];
      delete config.headers['content-type'];
    } else if (config.data !== undefined) {
      config.headers['Content-Type'] = 'application/json';
    }

    // JWT is stored in localStorage, so cookies are not needed.
    config.withCredentials = false;
    config.mode = 'cors';

    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('API ERROR:', error);

    if (error.response) {
      console.error('SERVER RESPONSE:', error.response.data);
    }

    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    }

    return Promise.reject(error);
  }
);

/** Which teacher teaches which subject to which class, per term (stored as lesson requirements). */
export const teachingAssignmentsAPI = {
  get: async (sessionId) => (await api.get(`teaching_assignments.php?academic_session_id=${sessionId}`)).data,
  assign: async (sessionId, teacherId, subjectId, classIds) => (await api.post('teaching_assignments.php?action=assign', {
    academic_session_id: sessionId, teacher_id: teacherId, subject_id: subjectId, class_ids: classIds,
  })).data,
  unassign: async (id) => (await api.post('teaching_assignments.php?action=unassign', { id })).data,
  copy: async (sessionId, fromSessionId) => (await api.post('teaching_assignments.php?action=copy', { academic_session_id: sessionId, from_session_id: fromSessionId })).data,
};

/** Skool Monitor platform: the schools on this install (SaaS). */
const schoolQuery = (school) => (school ? `&school=${encodeURIComponent(school)}` : '');
export const platformAPI = {
  context: async (school = '') => (await api.get(`platform.php?action=context${schoolQuery(school)}`)).data,
  list: async () => (await api.get('platform.php')).data,
  setup: async (homeName = '') => (await api.post('platform.php?action=setup', { home_name: homeName })).data,
  create: async (school) => (await api.post('platform.php?action=create', school)).data,
  update: async (code, changes) => (await api.post('platform.php?action=update', { code, ...changes })).data,
  saveSettings: async (settings) => (await api.post('platform.php?action=settings', settings)).data,
};

export const authAPI = {
  // school: the school's code on a multi-school install; empty for the home school.
  login: async (email, password, school = '') => {
    const response = await api.post(`auth.php?action=login${schoolQuery(school)}`, {
      email,
      password
    });

    return response.data;
  },

  changePassword: async (currentPassword, newPassword) => {
    const response = await api.post('auth.php?action=change-password', {
      current_password: currentPassword,
      new_password: newPassword
    });
    return response.data;
  },

  verify: async () => {
    const response = await api.get('auth.php?action=verify');
    return response.data;
  }
};

export const teachersAPI = {
  summary: async () => (await api.get('teachers.php?action=summary')).data,

  getAll: async (filters = {}) => {
    const params = new URLSearchParams();

    params.append('limit', 1000);

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params.append(key, value);
      }
    });

    const query = params.toString();
    const response = await api.get(`teachers.php${query ? `?${query}` : ''}`);
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`teachers.php?id=${id}`);
    return response.data;
  },

  create: async (teacher) => {
    const response = await api.post('teachers.php', teacher);
    return response.data;
  },

  update: async (teacher) => {
    const response = await api.put('teachers.php', teacher);
    return response.data;
  },

  toggleStatus: async (id, is_active) => {
    const response = await api.patch('teachers.php', {
      id,
      is_active
    });
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`teachers.php?id=${id}`);
    return response.data;
  },

  import: async (teachers) => {
    const response = await api.post('import-teachers.php', { teachers });
    return response.data;
  }
};

export const usersAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params.append(key, value);
      }
    });

    const query = params.toString();
    const response = await api.get(`users.php${query ? `?${query}` : ''}`);
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`users.php?id=${id}`);
    return response.data;
  },

  create: async (user) => {
    const response = await api.post('users.php', user);
    return response.data;
  },

  update: async (user) => {
    const response = await api.put('users.php', user);
    return response.data;
  },

  toggleStatus: async (id, is_active) => {
    const response = await api.patch('users.php', {
      id,
      is_active
    });
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`users.php?id=${id}`);
    return response.data;
  }
};

export const subjectPerformanceAPI = {
  getAll: async (year, term = null) => {
    const params = new URLSearchParams();

    if (year !== undefined && year !== null && year !== '') {
      params.append('year', year);
    }

    if (term !== undefined && term !== null && term !== '') {
      params.append('term', term);
    }

    const query = params.toString();
    const response = await api.get(
      `subject-performance.php${query ? `?${query}` : ''}`
    );
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('subject-performance.php', data);
    return response.data;
  },

  update: async (id, data) => {
    const response = await api.put(`subject-performance.php?id=${id}`, data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`subject-performance.php?id=${id}`);
    return response.data;
  }
};

export const lessonMonitoringAPI = {
  getAll: async (year, term, teacherId = '', weekNumber = '') => {
    const params = new URLSearchParams();

    if (year !== undefined && year !== null && year !== '') {
      params.append('year', year);
    }

    if (term !== undefined && term !== null && term !== '') {
      params.append('term', term);
    }

    if (teacherId !== undefined && teacherId !== null && teacherId !== '') {
      params.append('teacher_id', teacherId);
    }

    if (weekNumber !== undefined && weekNumber !== null && weekNumber !== '') {
      params.append('week_number', weekNumber);
    }

    const query = params.toString();
    const response = await api.get(
      `lesson-monitoring.php${query ? `?${query}` : ''}`
    );
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('lesson-monitoring.php', data);
    return response.data;
  },

  update: async (id, data) => {
    const response = await api.post('lesson-monitoring.php', {
      ...data,
      _method: 'PUT',
      id: id
    });
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`lesson-monitoring.php?id=${id}`);
    return response.data;
  }
};

export const lessonCompensationsAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params.append(key, value);
      }
    });

    const query = params.toString();
    const response = await api.get(
      `lesson-compensations.php${query ? `?${query}` : ''}`
    );
    return response.data;
  },

  getByLesson: async (lessonMonitoringId) => {
    const response = await api.get(
      `lesson-compensations.php?lesson_monitoring_id=${lessonMonitoringId}`
    );
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('lesson-compensations.php', data);
    return response.data;
  },

  update: async (id, data) => {
    const response = await api.put(`lesson-compensations.php?id=${id}`, data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`lesson-compensations.php?id=${id}`);
    return response.data;
  }
};

export const lessonObservationAPI = {
  getAll: async (year, term, search = '', classFilter = '', streamFilter = '') => {
    const response = await api.get(
      `lesson-observations.php?action=list&year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}&search=${encodeURIComponent(search ?? '')}&class=${encodeURIComponent(classFilter ?? '')}&stream=${encodeURIComponent(streamFilter ?? '')}`
    );
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('lesson-observations.php?action=create', data);
    return response.data;
  },

  update: async (id, data) => {
    const response = await api.put(
      `lesson-observations.php?action=update&id=${id}`,
      data
    );
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(
      `lesson-observations.php?action=delete&id=${id}`
    );
    return response.data;
  }
};

export const dutyPerformanceAPI = {
  getAll: async (year, term, week = '') => {
    let url = `duty-performance.php?year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}`;

    if (week) {
      url += `&week=${encodeURIComponent(week)}`;
    }

    const response = await api.get(url);
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`duty-performance.php?id=${id}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('duty-performance.php', data);
    return response.data;
  },

  update: async (id, data) => {
    const response = await api.put(`duty-performance.php?id=${id}`, data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`duty-performance.php?id=${id}`);
    return response.data;
  }
};

export const classTeacherPerformanceAPI = {
  getAll: async (params = {}) => {
    const queryParams = new URLSearchParams();
    if (params.year) queryParams.append('year', params.year);
    if (params.term) queryParams.append('term', params.term);
    if (params.week) queryParams.append('week', params.week);

    const query = queryParams.toString();
    const response = await api.get(
      `class-teacher-performance.php${query ? `?${query}` : ''}`
    );
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('class-teacher-performance.php', data);
    return response.data;
  },

  update: async (id, data) => {
    const response = await api.put(`class-teacher-performance.php?id=${id}`, data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.post('class-teacher-performance.php', {
      _method: 'DELETE',
      id: id
    });
    return response.data;
  }
};

export const reportsAPI = {
  getWeekly: async (year, term) => {
    const response = await api.get(
      `reports.php?type=weekly&year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}`
    );
    return response.data;
  },

  getTermly: async (year, term) => {
    const response = await api.get(
      `reports.php?type=termly&year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}`
    );
    return response.data;
  },

  getYearly: async (year) => {
    const response = await api.get(
      `reports.php?type=yearly&year=${encodeURIComponent(year ?? '')}`
    );
    return response.data;
  },

  getBestTeachers: async (year, term, awardType = 'week') => {
    const response = await api.get(
      `reports.php?type=best-teachers&year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}&award_type=${encodeURIComponent(awardType)}`
    );
    return response.data;
  },

  getPrintable: async (teacherId, year, term = null) => {
    let url = `reports.php?type=printable&teacher_id=${encodeURIComponent(teacherId ?? '')}&year=${encodeURIComponent(year ?? '')}`;

    if (term) {
      url += `&term=${encodeURIComponent(term)}`;
    }

    const response = await api.get(url);
    return response.data;
  },

  getTopTeachersLost: async (year, term = null) => {
    const formData = new FormData();
    formData.append('type', 'top20');
    formData.append('year', year);
    if (term !== null) formData.append('term', term);
    const response = await api.post('reports.php', formData);
    return response.data;
  },

  getTeachersFewLost: async (year, term = null) => {
    const formData = new FormData();
    formData.append('type', 'few');
    formData.append('year', year);
    if (term !== null) formData.append('term', term);
    const response = await api.post('reports.php', formData);
    return response.data;
  },

  getClassesMostLost: async (year, term = null) => {
    const formData = new FormData();
    formData.append('type', 'class-top');
    formData.append('year', year);
    if (term !== null) formData.append('term', term);
    const response = await api.post('reports.php', formData);
    return response.data;
  },

  getClassesFewLost: async (year, term = null) => {
    const formData = new FormData();
    formData.append('type', 'class-few');
    formData.append('year', year);
    if (term !== null) formData.append('term', term);
    const response = await api.post('reports.php', formData);
    return response.data;
  }
};

export const dashboardAPI = {
  getData: async (year, term) => {
    const response = await api.get(
      `dashboard.php?year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}`
    );
    return response.data;
  }
};

export const classesAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        params.append(key, value);
      }
    });

    const query = params.toString();
    const response = await api.get(
      `classes.php${query ? `?${query}` : ''}`
    );
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`classes.php?action=view&id=${id}`);
    return response.data;
  },

  create: async (classData) => {
    const formData = new FormData();

    Object.entries(classData).forEach(([key, value]) => {
      formData.append(key, value);
    });

    const response = await api.post('classes.php?action=create', formData);
    return response.data;
  },

  update: async (classData) => {
    const response = await api.put('classes.php?action=update', classData);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`classes.php?action=delete&id=${id}`);
    return response.data;
  }
};

export const subjectsNewAPI = {
  getAll: async () => {
    const response = await api.get('subjects_new.php');
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`subjects_new.php?action=view&id=${id}`);
    return response.data;
  },

  create: async (subjectData) => {
    const formData = new FormData();

    Object.entries(subjectData).forEach(([key, value]) => {
      formData.append(key, value);
    });

    const response = await api.post('subjects_new.php?action=create', formData);
    return response.data;
  },

  update: async (subjectData) => {
    const response = await api.put('subjects_new.php?action=update', subjectData);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`subjects_new.php?action=delete&id=${id}`);
    return response.data;
  }
};

export const classSubjectsAPI = {
  getAll: async () => {
    const response = await api.get('class_subjects.php');
    return response.data;
  },

  getByClassId: async (classId) => {
    const response = await api.get(
      `class_subjects.php?action=by-class&class_id=${classId}`
    );
    return response.data;
  },

  assign: async (classId, subjectId) => {
    const formData = new FormData();
    formData.append('class_id', classId);
    formData.append('subject_id', subjectId);

    const response = await api.post(
      'class_subjects.php?action=assign',
      formData
    );
    return response.data;
  },

  bulkAssign: async (classId, subjectIds) => {
    const response = await api.post(
      'class_subjects.php?action=bulk-assign',
      {
        class_id: classId,
        subject_ids: subjectIds
      }
    );
    return response.data;
  },

  remove: async (classId, subjectId) => {
    const response = await api.delete(
      `class_subjects.php?action=remove&class_id=${classId}&subject_id=${subjectId}`
    );
    return response.data;
  }
};

export const hrDashboardAPI = {
  getData: async (year) => {
    const response = await api.get(`hr_dashboard.php?year=${encodeURIComponent(year ?? '')}`);
    return response.data;
  }
};

export const nonTeachingStaffAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([k, v]) => {
      if (v !== '' && v != null) params.append(k, v);
    });

    const q = params.toString();
    const response = await api.get(`non_teaching_staff.php${q ? `?${q}` : ''}`);
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`non_teaching_staff.php?id=${id}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('non_teaching_staff.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('non_teaching_staff.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`non_teaching_staff.php?id=${id}`);
    return response.data;
  }
};

export const employeesAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([k, v]) => {
      if (v !== '' && v != null) params.append(k, v);
    });

    const q = params.toString();
    const response = await api.get(`employees.php${q ? `?${q}` : ''}`);
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`employees.php?id=${id}`);
    return response.data;
  }
};

export const departmentsAPI = {
  getAll: async (search = '', includeRoles = false) => {
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (includeRoles) params.append('include_roles', 'true');
    
    const query = params.toString();
    const response = await api.get(`departments.php${query ? `?${query}` : ''}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('departments.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('departments.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`departments.php?id=${id}`);
    return response.data;
  }
};

export const rolesAPI = {
  create: async (data) => (await api.post('roles.php', data)).data,

  getAll: async (departmentId = '') => {
    const q = departmentId ? `?department_id=${encodeURIComponent(departmentId)}` : '';
    const response = await api.get(`roles.php${q}`);
    return response.data;
  }
};

export const timetableAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams(filters);
    const response = await api.get(`timetable.php?${params.toString()}`);
    return response.data;
  },

  getWithPeriods: async (year, term) => {
    const response = await api.get(
      `timetable.php?view=periods&academic_year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}`
    );
    return response.data;
  },

  getByClass: async (year, term, classId) => {
    const response = await api.get(
      `timetable.php?academic_year=${year}&term=${term}&class_id=${classId}`
    );
    return response.data;
  },

  getByTeacher: async (year, term, teacherId) => {
    const response = await api.get(
      `timetable.php?academic_year=${year}&term=${term}&teacher_id=${teacherId}`
    );
    return response.data;
  },

  getByRoom: async (year, term, roomId) => {
    const response = await api.get(
      `timetable.php?academic_year=${year}&term=${term}&room_id=${roomId}`
    );
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('timetable.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('timetable.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`timetable.php?id=${id}`);
    return response.data;
  },

  generate: async (data) => {
    const response = await api.post('timetable.php?action=generate', data);
    return response.data;
  },

  /** Classes, teachers, subjects, rooms, periods and sessions in one request. */
  meta: async () => {
    const response = await api.get('timetable.php?view=meta');
    return response.data;
  },

  /** Server-validated issues, unscheduled lessons, stats and publication state. */
  overview: async (sessionId) => {
    const response = await api.get(`timetable.php?view=overview&academic_session_id=${sessionId}`);
    return response.data;
  },

  history: async (sessionId) => {
    const response = await api.get(`timetable.php?view=history&academic_session_id=${sessionId}`);
    return response.data;
  },

  published: async (sessionId) => {
    const response = await api.get(`timetable.php?view=published&academic_session_id=${sessionId}`);
    return response.data;
  },

  /** Dry-run conflict check for the lesson form; never writes. */
  check: async (data) => {
    const response = await api.post('timetable.php?action=check', data);
    return response.data;
  },

  validate: async (sessionId) => {
    const response = await api.post('timetable.php?action=validate', { academic_session_id: sessionId });
    return response.data;
  },

  publish: async (data) => {
    const response = await api.post('timetable.php?action=publish', data);
    return response.data;
  },

  restore: async (versionId) => {
    const response = await api.post('timetable.php?action=restore', { version_id: versionId });
    return response.data;
  },

  setLocked: async (id, locked) => {
    const response = await api.post(`timetable.php?action=${locked ? 'lock' : 'unlock'}`, { id });
    return response.data;
  },

  /** Setup checklist, school-day periods and class home rooms. */
  setup: async (sessionId) => {
    const response = await api.get(`timetable.php?view=setup&academic_session_id=${sessionId || ''}`);
    return response.data;
  },

  savePeriods: async (periods) => {
    const response = await api.post('timetable.php?action=save-periods', { periods });
    return response.data;
  },

  saveRules: async (sessionId, maxLessonsPerDay) => {
    const response = await api.post('timetable.php?action=save-rules', { academic_session_id: sessionId, max_lessons_per_day: maxLessonsPerDay });
    return response.data;
  },

  setHomeRoom: async (classId, roomId) => {
    const response = await api.post('timetable.php?action=home-room', { class_id: classId, room_id: roomId || null });
    return response.data;
  },

  linkHomeRooms: async () => {
    const response = await api.post('timetable.php?action=link-home-rooms', {});
    return response.data;
  }
};

/** Which teachers belong to which subject department. */
export const subjectDepartmentsAPI = {
  getAll: async () => {
    const response = await api.get('subject_departments.php');
    return response.data;
  },

  add: async (subjectId, teacherIds) => {
    const response = await api.post('subject_departments.php?action=add', { subject_id: subjectId, teacher_ids: teacherIds });
    return response.data;
  },

  remove: async (subjectId, teacherId, confirm = false) => {
    const response = await api.post('subject_departments.php?action=remove', { subject_id: subjectId, teacher_id: teacherId, confirm });
    return response.data;
  },

  syncFromTeacherRecords: async (dryRun = false) => {
    const response = await api.post('subject_departments.php?action=sync', { dry_run: dryRun });
    return response.data;
  }
};

export const eventsAPI = {
  getAll: async () => {
    const response = await api.get('events.php');
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`events.php?id=${id}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('events.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('events.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`events.php?id=${id}`);
    return response.data;
  }
};

export const classTeacherAssignmentsAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams(filters);
    const response = await api.get(
      `class_teacher_assignments.php?${params.toString()}`
    );
    return response.data;
  },

  assign: async (data) => {
    const response = await api.post('class_teacher_assignments.php', data);
    return response.data;
  },

  reassign: async (data) => {
    const response = await api.patch('class_teacher_assignments.php', data);
    return response.data;
  },

  end: async (id) => {
    const response = await api.delete(`class_teacher_assignments.php?id=${id}`);
    return response.data;
  }
};

export const leaveAPI = {
  getRequests: async (filters = {}) => {
    const params = new URLSearchParams(filters);
    const response = await api.get(`leave.php?${params.toString()}`);
    return response.data;
  },

  getBalances: async (year, employeeId = '') => {
    let url = `leave.php?action=balances&year=${encodeURIComponent(year ?? '')}`;
    if (employeeId) url += `&employee_id=${encodeURIComponent(employeeId)}`;
    const response = await api.get(url);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('leave.php', data);
    return response.data;
  },

  updateStatus: async (id, status) => {
    const response = await api.patch('leave.php', { id, status });
    return response.data;
  },

  deleteRequest: async (id) => {
    const response = await api.delete(`leave.php?action=request&id=${id}`);
    return response.data;
  },

  deleteBalance: async (id) => {
    const response = await api.delete(`leave.php?action=balance&id=${id}`);
    return response.data;
  }
};

export const payrollAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams(filters);
    const response = await api.get(`payroll.php?${params.toString()}`);
    return response.data;
  },

  getById: async (id) => {
    const response = await api.get(`payroll.php?id=${id}`);
    return response.data;
  },

  save: async (data) => {
    const response = await api.post('payroll.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`payroll.php?id=${id}`);
    return response.data;
  }
};

export const teachingAnalyticsAPI = {
  get: async (year, term, teacherId = '') => {
    let url = `teaching_analytics.php?year=${encodeURIComponent(year ?? '')}&term=${encodeURIComponent(term ?? '')}`;
    if (teacherId) url += `&teacher_id=${encodeURIComponent(teacherId)}`;
    const response = await api.get(url);
    return response.data;
  }
};

export const academicSessionsAPI = {
  getAll: async () => {
    const response = await api.get('academic_sessions.php');
    return response.data;
  },

  getActive: async () => {
    const response = await api.get('academic_sessions.php?action=active');
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('academic_sessions.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('academic_sessions.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`academic_sessions.php?id=${id}`);
    return response.data;
  }
};

export const roomsAPI = {
  getAll: async () => {
    const response = await api.get('rooms.php');
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('rooms.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('rooms.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`rooms.php?id=${id}`);
    return response.data;
  },

  /** The code a new room of this type will be given (codes are assigned by the server). */
  nextCode: async (type) => {
    const response = await api.get(`rooms.php?action=next-code&type=${encodeURIComponent(type)}`);
    return response.data;
  },

  /** One classroom per class stream ("S.1 - A Class") with automatic codes. */
  createClassRooms: async () => {
    const response = await api.post('rooms.php?action=class-rooms', {});
    return response.data;
  }
};

export const lessonRequirementsAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams(filters);
    const response = await api.get(`lesson_requirements.php?${params.toString()}`);
    return response.data;
  },

  getBySession: async (sessionId) => {
    const response = await api.get(`lesson_requirements.php?action=by-session&academic_session_id=${sessionId}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('lesson_requirements.php', data);
    return response.data;
  },

  bulkCreate: async (data) => {
    const response = await api.post('lesson_requirements.php?action=bulk', data);
    return response.data;
  },

  importCSV: async (data) => {
    const response = await api.post('lesson_requirements.php?action=import', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('lesson_requirements.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`lesson_requirements.php?id=${id}`);
    return response.data;
  }
};

export const teacherAvailabilityAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams(filters);
    const response = await api.get(`teacher_availability.php?${params.toString()}`);
    return response.data;
  },

  getByTeacher: async (sessionId, teacherId) => {
    const response = await api.get(`teacher_availability.php?action=by-teacher&academic_session_id=${sessionId}&teacher_id=${teacherId}`);
    return response.data;
  },

  set: async (data) => {
    const response = await api.post('teacher_availability.php', data);
    return response.data;
  },

  bulkUpdate: async (data) => {
    const response = await api.post('teacher_availability.php?action=bulk', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('teacher_availability.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`teacher_availability.php?id=${id}`);
    return response.data;
  }
};


const qs = (params = {}) => new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '')).toString();

export const examsAPI = {
  list: async (sessionId) => (await api.get(`exams.php?${qs({ academic_session_id: sessionId })}`)).data,
  save: async (data) => (await api.post('exams.php', data)).data,
  setStatus: async (id, status) => (await api.post('exams.php?action=status', { id, status })).data,
  setScheme: async (examBasis) => (await api.post('exams.php?action=scheme', { exam_basis: examBasis })).data,
  remove: async (id, confirm = false) => (await api.delete(`exams.php?${qs({ id, confirm: confirm ? 1 : '' })}`)).data,
  sheet: async (examId, classId, subjectId) => (await api.get(`exams.php?${qs({ action: 'sheet', exam_id: examId, class_id: classId, subject_id: subjectId })}`)).data,
  saveMarks: async (data) => (await api.post('exams.php?action=marks', data)).data,
  progress: async (examId) => (await api.get(`exams.php?${qs({ action: 'progress', exam_id: examId })}`)).data
};

export const schoolSettingsAPI = {
  get: async () => (await api.get('school_settings.php')).data,
  save: async (data) => (await api.post('school_settings.php', data)).data
};

export const reportCardsAPI = {
  get: async (sessionId, classId, studentId) =>
    (await api.get(`report_cards.php?${qs({ academic_session_id: sessionId, class_id: classId, student_id: studentId })}`)).data
};

export const teacherAccountsAPI = {
  list: async () => (await api.get('teacher_accounts.php')).data,
  create: async (payload) => (await api.post('teacher_accounts.php?action=create', payload)).data,
  reset: async (teacherId) => (await api.post('teacher_accounts.php?action=reset', { teacher_id: teacherId })).data,
  setActive: async (teacherId, active) => (await api.post('teacher_accounts.php?action=active', { teacher_id: teacherId, active })).data
};

export const lessonPlansAPI = {
  list: async (filters = {}) => (await api.get(`lesson_plans.php?${qs(filters)}`)).data,
  save: async (data) => (await api.post('lesson_plans.php', data)).data,
  review: async (id, decision, comment) => (await api.post('lesson_plans.php?action=review', { id, decision, comment })).data,
  seen: async (id) => (await api.post('lesson_plans.php?action=seen', { id })).data,
  newCount: async () => (await api.get('lesson_plans.php?view=new_count')).data,
  remove: async (id) => (await api.delete(`lesson_plans.php?${qs({ id })}`)).data
};

export const teacherPortalAPI = {
  view: async (view, params = {}) => (await api.get(`teacher_portal.php?${qs({ view, ...params })}`)).data,
  requestLeave: async (data) => (await api.post('teacher_portal.php?action=leave', data)).data,
  cancelLeave: async (id) => (await api.post('teacher_portal.php?action=cancel-leave', { id })).data
};

export const academicCalendarAPI = {
  get: async () => (await api.get('academic_calendar.php')).data,
  preview: async () => (await api.get('academic_calendar.php?action=preview')).data,
  close: async (data) => (await api.post('academic_calendar.php?action=close', data)).data,
  open: async (sessionId) => (await api.post('academic_calendar.php?action=open', { session_id: sessionId })).data,
  reopen: async (sessionId) => (await api.post('academic_calendar.php?action=reopen', { session_id: sessionId })).data,
  closeReopened: async (sessionId) => (await api.post('academic_calendar.php?action=close_reopened', { session_id: sessionId })).data,
  compulsory: async () => (await api.get('academic_calendar.php?action=compulsory')).data,
  saveCompulsory: async (data) => (await api.post('academic_calendar.php?action=compulsory', data)).data
};

export const studentAcademicsAPI = {
  subjects: async (id) => (await api.get(`student_academics.php?${qs({ action: 'subjects', id })}`)).data,
  saveSubjects: async (studentId, subjectIds) => (await api.post('student_academics.php?action=subjects', { student_id: studentId, subject_ids: subjectIds })).data,
  awaiting: async (q = '') => (await api.get(`student_academics.php?${qs({ action: 'awaiting', q })}`)).data,
  admitALevel: async (data) => (await api.post('student_academics.php?action=admit-a-level', data)).data
};

export const academicDashboardAPI = {
  get: async () => (await api.get('academic_dashboard.php')).data
};

export const studentSyncAPI = {
  status: async () => (await api.get('student_sync.php')).data,
  details: async (id) => (await api.get(`student_sync.php?${qs({ id })}`)).data,
  run: async () => (await api.post('student_sync.php', {}, { timeout: 300000 })).data
};

export const curriculumAPI = {
  list: async (filters = {}) => (await api.get(`curriculum.php?${qs(filters)}`)).data,
  summary: async () => (await api.get('curriculum.php?action=summary')).data,
  forClass: async (classId, subjectId, term) => (await api.get(`curriculum.php?${qs({ action: 'for-class', class_id: classId, subject_id: subjectId, term })}`)).data,
  save: async (data) => (await api.post('curriculum.php', data)).data,
  remove: async (id) => (await api.delete(`curriculum.php?${qs({ id })}`)).data,
  reimport: async () => (await api.post('curriculum.php?action=import', {})).data
};

export default api;
