import axios from 'axios';

const API_BASE_URL = import.meta.env.DEV
  ? 'http://localhost/accademics/backend/api/'
  : 'https://stmark.sc.ug/accademics/backend/api/';

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

export const authAPI = {
  login: async (email, password) => {
    const response = await api.post('auth.php?action=login', {
      email,
      password
    });

    return response.data;
  },

  verify: async () => {
    const response = await api.get('auth.php?action=verify');
    return response.data;
  }
};

export const teachersAPI = {
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

export const bellSchedulesAPI = {
  getAll: async () => {
    const response = await api.get('bell_schedules.php');
    return response.data;
  },

  getActive: async () => {
    const response = await api.get('bell_schedules.php?action=active');
    return response.data;
  },

  getPeriods: async (scheduleId) => {
    const response = await api.get(`bell_schedules.php?action=periods&schedule_id=${scheduleId}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('bell_schedules.php', data);
    return response.data;
  },

  addPeriod: async (data) => {
    const response = await api.post('bell_schedules.php?action=periods', data);
    return response.data;
  },

  bulkUpdatePeriods: async (data) => {
    const response = await api.post('bell_schedules.php?action=bulk-periods', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('bell_schedules.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`bell_schedules.php?id=${id}`);
    return response.data;
  },

  deletePeriod: async (id) => {
    const response = await api.delete(`bell_schedules.php?action=period&id=${id}`);
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

export const timetableConstraintsAPI = {
  getAll: async (filters = {}) => {
    const params = new URLSearchParams(filters);
    const response = await api.get(`timetable_constraints.php?${params.toString()}`);
    return response.data;
  },

  getBySession: async (sessionId) => {
    const response = await api.get(`timetable_constraints.php?action=by-session&academic_session_id=${sessionId}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('timetable_constraints.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('timetable_constraints.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`timetable_constraints.php?id=${id}`);
    return response.data;
  }
};

export const timetableConflictsAPI = {
  getAll: async (sessionId) => {
    const response = await api.get(`timetable_conflicts.php?academic_session_id=${sessionId}`);
    return response.data;
  },

  check: async (params) => {
    const queryParams = new URLSearchParams(params);
    const response = await api.get(`timetable_conflicts.php?action=check&${queryParams.toString()}`);
    return response.data;
  }
};

export const schoolEventsAPI = {
  getAll: async () => {
    const response = await api.get('school_events.php');
    return response.data;
  },

  getByType: async (type) => {
    const response = await api.get(`school_events.php?action=by-type&type=${type}`);
    return response.data;
  },

  getSchedulable: async () => {
    const response = await api.get('school_events.php?action=schedulable');
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('school_events.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('school_events.php', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`school_events.php?id=${id}`);
    return response.data;
  }
};

export const timetableVersionsAPI = {
  getAll: async (sessionId) => {
    const response = await api.get(`timetable_versions.php?academic_session_id=${sessionId}`);
    return response.data;
  },

  getBySession: async (sessionId) => {
    const response = await api.get(`timetable_versions.php?action=by-session&academic_session_id=${sessionId}`);
    return response.data;
  },

  getLatest: async (sessionId) => {
    const response = await api.get(`timetable_versions.php?action=latest&academic_session_id=${sessionId}`);
    return response.data;
  },

  create: async (data) => {
    const response = await api.post('timetable_versions.php', data);
    return response.data;
  },

  update: async (data) => {
    const response = await api.put('timetable_versions.php', data);
    return response.data;
  },

  publish: async (data) => {
    const response = await api.post('timetable_versions.php?action=publish', data);
    return response.data;
  },

  archive: async (data) => {
    const response = await api.post('timetable_versions.php?action=archive', data);
    return response.data;
  },

  restore: async (data) => {
    const response = await api.post('timetable_versions.php?action=restore', data);
    return response.data;
  },

  delete: async (id) => {
    const response = await api.delete(`timetable_versions.php?id=${id}`);
    return response.data;
  }
};

export const timetableAnalyticsAPI = {
  getTeacherWorkload: async (sessionId) => {
    const response = await api.get(`timetable_analytics.php?action=teacher-workload&academic_session_id=${sessionId}`);
    return response.data;
  },

  getSubjectCoverage: async (sessionId) => {
    const response = await api.get(`timetable_analytics.php?action=subject-coverage&academic_session_id=${sessionId}`);
    return response.data;
  },

  getClassCoverage: async (sessionId) => {
    const response = await api.get(`timetable_analytics.php?action=class-coverage&academic_session_id=${sessionId}`);
    return response.data;
  },

  getRoomUtilization: async (sessionId) => {
    const response = await api.get(`timetable_analytics.php?action=room-utilization&academic_session_id=${sessionId}`);
    return response.data;
  },

  getConflictTrends: async (sessionId) => {
    const response = await api.get(`timetable_analytics.php?action=conflict-trends&academic_session_id=${sessionId}`);
    return response.data;
  },

  getDashboard: async (sessionId) => {
    const response = await api.get(`timetable_analytics.php?action=dashboard&academic_session_id=${sessionId}`);
    return response.data;
  }
};

export const timetablePDFAPI = {
  exportClass: async (data) => {
    const response = await api.get(`timetable_pdf.php?action=class&academic_session_id=${data.academic_session_id}&class_id=${data.class_id}`);
    return response.data;
  },

  exportTeacher: async (data) => {
    const response = await api.get(`timetable_pdf.php?action=teacher&academic_session_id=${data.academic_session_id}&teacher_id=${data.teacher_id}`);
    return response.data;
  },

  exportRoom: async (data) => {
    const response = await api.get(`timetable_pdf.php?action=room&academic_session_id=${data.academic_session_id}&room_id=${data.room_id}`);
    return response.data;
  },

  exportMaster: async (data) => {
    const response = await api.get(`timetable_pdf.php?action=master&academic_session_id=${data.academic_session_id}`);
    return response.data;
  }
};

export default api;