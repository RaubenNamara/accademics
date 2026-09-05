<template>
  <div class="min-h-screen bg-slate-50">
    <ToastBanner />
    
    <!-- Header -->
    <div class="bg-white border-b border-slate-200 px-6 py-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-slate-900">Timetable Management</h1>
          <p class="text-sm text-slate-600">Complete timetable suite · Setup · Generate · Manage</p>
        </div>
        <div v-if="activeSession" class="flex items-center gap-2">
          <span class="text-sm text-slate-600">Active Session:</span>
          <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
            {{ activeSession.session_name }}
          </span>
        </div>
      </div>
    </div>
    
    <!-- Horizontal Menu -->
    <TimetableMenu :active-item="activeView" @select="handleViewChange" />
    
    <!-- Content Area -->
    <div class="p-6">
      <!-- Setup Wizard View -->
      <div v-if="activeView === 'setup-wizard'" class="space-y-6">
        <SetupWizard 
          :classes-count="stats.totalClasses"
          :subjects-count="stats.totalSubjects"
          :teachers-count="stats.totalTeachers"
          :rooms-count="stats.totalRooms"
          :events-count="schoolEvents.length"
          :requirements-count="lessonRequirements.length"
          :availability-count="0"
          :constraints-count="0"
          @navigate="handleViewChange"
          @complete="handleWizardComplete"
          @generate="handleGenerateFromWizard"
        />
      </div>
      
      <!-- Dashboard View -->
      <div v-if="activeView === 'dashboard'" class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-slate-600">Total Classes</p>
                <p class="text-2xl font-bold text-slate-900">{{ stats.totalClasses }}</p>
              </div>
              <div class="h-12 w-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
            </div>
          </div>
          
          <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-slate-600">Total Teachers</p>
                <p class="text-2xl font-bold text-slate-900">{{ stats.totalTeachers }}</p>
              </div>
              <div class="h-12 w-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              </div>
            </div>
          </div>
          
          <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-slate-600">Total Subjects</p>
                <p class="text-2xl font-bold text-slate-900">{{ stats.totalSubjects }}</p>
              </div>
              <div class="h-12 w-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
            </div>
          </div>
          
          <div class="bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-slate-600">Total Rooms</p>
                <p class="text-2xl font-bold text-slate-900">{{ stats.totalRooms }}</p>
              </div>
              <div class="h-12 w-12 bg-amber-100 rounded-lg flex items-center justify-center">
                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                </svg>
              </div>
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Setup Progress -->
          <SetupProgress :progress-items="progressItems" />
          
          <!-- Conflict Summary -->
          <ConflictSummary :conflicts="conflicts" @refresh="loadConflicts" />
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
          <h3 class="text-lg font-semibold text-slate-900 mb-4">Quick Actions</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <button @click="handleViewChange('sessions')" class="p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors text-left">
              <svg class="h-6 w-6 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <p class="text-sm font-medium text-slate-900">Manage Sessions</p>
            </button>
            
            <button @click="handleViewChange('lesson-requirements')" class="p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors text-left">
              <svg class="h-6 w-6 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
              <p class="text-sm font-medium text-slate-900">Lesson Requirements</p>
            </button>
            
            <button @click="handleViewChange('generate')" class="p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors text-left">
              <svg class="h-6 w-6 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              <p class="text-sm font-medium text-slate-900">Generate Timetable</p>
            </button>
            
            <button @click="handleViewChange('conflicts')" class="p-4 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors text-left">
              <svg class="h-6 w-6 text-red-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <p class="text-sm font-medium text-slate-900">Check Conflicts</p>
            </button>
          </div>
        </div>
      </div>
      
      <!-- Academic Sessions View -->
      <div v-else-if="activeView === 'sessions'" class="space-y-6">
        <AcademicSessions />
      </div>
      
      <!-- Bell Schedule View -->
      <div v-else-if="activeView === 'bell-schedule'" class="space-y-6">
        <BellSchedule />
      </div>
      
      <!-- Lesson Requirements View -->
      <div v-else-if="activeView === 'lesson-requirements'" class="space-y-6">
        <LessonRequirementsManager />
      </div>
      
      <!-- Class Timetable View -->
      <div v-else-if="activeView === 'class-timetable'" class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-900">Class Timetables</h2>
          <div class="flex gap-3">
            <select v-model="selectedClassId" class="input" @change="loadClassTimetable">
              <option value="">Select Class</option>
              <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.class_name }}</option>
            </select>
            <button @click="exportClassPdf" class="btn-secondary">Export PDF</button>
          </div>
        </div>
        
        <div v-if="selectedClassId && classTimetable.length > 0" class="bg-white rounded-xl border border-slate-200 p-6">
          <TimetableGrid 
            :entries="classTimetable" 
            :editable="true"
            @add="openTimetableForm"
            @remove="removeTimetableEntry"
          />
        </div>
        <div v-else class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
          Select a class to view its timetable
        </div>
      </div>
      
      <!-- Teacher Timetable View -->
      <div v-else-if="activeView === 'teacher-timetable'" class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-900">Teacher Timetables</h2>
          <div class="flex gap-3">
            <select v-model="selectedTeacherId" class="input" @change="loadTeacherTimetable">
              <option value="">Select Teacher</option>
              <option v-for="t in teachersList" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
            </select>
            <button @click="exportTeacherPdf" class="btn-secondary">Export PDF</button>
          </div>
        </div>
        
        <div v-if="selectedTeacherId && teacherTimetable.length > 0" class="bg-white rounded-xl border border-slate-200 p-6">
          <TimetableGrid 
            :entries="teacherTimetable" 
            :editable="false"
          />
        </div>
        <div v-else class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
          Select a teacher to view their timetable
        </div>
      </div>
      
      <!-- Generate Timetable View -->
      <div v-else-if="activeView === 'generate'" class="space-y-6">
        <h2 class="text-xl font-semibold text-slate-900">Generate Timetable</h2>
        
        <SetupProgress :progress-items="progressItems" />
        
        <div class="bg-white rounded-xl border border-slate-200 p-6">
          <h3 class="text-lg font-medium text-slate-900 mb-4">Generation Settings</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Academic Session</label>
              <select v-model="generationSessionId" class="input w-full">
                <option value="">Select Session</option>
                <option v-for="s in sessions" :key="s.id" :value="s.id">{{ s.session_name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Target Class</label>
              <select v-model="generationClassId" class="input w-full">
                <option value="">All Classes</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.class_name }}</option>
              </select>
            </div>
          </div>
          
          <div class="mt-6 flex gap-3">
            <button 
              @click="generateTimetable" 
              :disabled="!canGenerate || generating"
              class="btn-primary"
              :class="{ 'opacity-50 cursor-not-allowed': !canGenerate || generating }"
            >
              {{ generating ? 'Generating...' : 'Generate Timetable' }}
            </button>
            <button @click="loadConflicts" class="btn-secondary">Check Conflicts</button>
          </div>
          
          <div v-if="generationResult" class="mt-6 p-4 rounded-lg" :class="generationResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
            <p class="font-medium" :class="generationResult.success ? 'text-green-800' : 'text-red-800'">
              {{ generationResult.message }}
            </p>
            <p v-if="generationResult.details" class="text-sm mt-2 text-slate-600">{{ generationResult.details }}</p>
          </div>
        </div>
      </div>
      
      <!-- Conflict Checker View -->
      <div v-else-if="activeView === 'conflicts'" class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-900">Conflict Checker</h2>
          <button @click="loadConflicts" class="btn-secondary">Refresh</button>
        </div>
        
        <ConflictSummary :conflicts="conflicts" @refresh="loadConflicts" />
      </div>
      
      <!-- Classes View -->
      <div v-else-if="activeView === 'classes'" class="space-y-6">
        <IntegratedClasses @navigate="handleViewChange" />
      </div>
      
      <!-- Subjects View -->
      <div v-else-if="activeView === 'subjects'" class="space-y-6">
        <IntegratedSubjects @navigate="handleViewChange" />
      </div>
      
      <!-- Teachers View -->
      <div v-else-if="activeView === 'teachers'" class="space-y-6">
        <IntegratedTeachers @navigate="handleViewChange" />
      </div>
      
      <!-- Rooms View -->
      <div v-else-if="activeView === 'rooms'" class="space-y-6">
        <IntegratedRooms @navigate="handleViewChange" />
      </div>
      
      <!-- School Events View -->
      <div v-else-if="activeView === 'events'" class="space-y-6">
        <SchoolEventsManager />
      </div>
      
      <!-- Teacher Availability View -->
      <div v-else-if="activeView === 'availability'" class="space-y-6">
        <TeacherAvailability />
      </div>
      
      <!-- Constraints View -->
      <div v-else-if="activeView === 'constraints'" class="space-y-6">
        <ConstraintsManager />
      </div>
      
      <!-- Room Timetables View -->
      <div v-else-if="activeView === 'room-timetables'" class="space-y-6">
        <RoomTimetables />
      </div>
      
      <!-- Master Timetable View -->
      <div v-else-if="activeView === 'master-timetable'" class="space-y-6">
        <MasterTimetable />
      </div>
      
      <!-- Analytics View -->
      <div v-else-if="activeView === 'analytics'" class="space-y-6">
        <AnalyticsDashboard />
      </div>
      
      <!-- PDF Exports View -->
      <div v-else-if="activeView === 'exports'" class="space-y-6">
        <PDFExports />
      </div>
      
      <!-- Settings View -->
      <div v-else-if="activeView === 'settings'" class="space-y-6">
        <Settings />
      </div>
      
      <!-- Placeholder for other views -->
      <div v-else class="bg-white rounded-xl border border-slate-200 p-8 text-center">
        <svg class="mx-auto h-16 w-16 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
        <h3 class="text-lg font-medium text-slate-900 mb-2">{{ getViewTitle(activeView) }}</h3>
        <p class="text-slate-600">This view is coming soon. The comprehensive timetable management suite is being built step by step.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { 
  academicSessionsAPI, 
  bellSchedulesAPI,
  classesAPI, 
  teachersAPI, 
  subjectsNewAPI, 
  roomsAPI,
  lessonRequirementsAPI,
  timetableAPI,
  timetableConflictsAPI
} from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import TimetableMenu from '../components/timetable/TimetableMenu.vue';
import SetupProgress from '../components/timetable/SetupProgress.vue';
import ConflictSummary from '../components/timetable/ConflictSummary.vue';
import TimetableGrid from '../components/timetable/TimetableGrid.vue';
import AcademicSessions from '../components/timetable/AcademicSessions.vue';
import BellSchedule from '../components/timetable/BellSchedule.vue';
import LessonRequirementsManager from '../components/timetable/LessonRequirementsManager.vue';
import IntegratedClasses from '../components/timetable/IntegratedClasses.vue';
import IntegratedSubjects from '../components/timetable/IntegratedSubjects.vue';
import IntegratedTeachers from '../components/timetable/IntegratedTeachers.vue';
import IntegratedRooms from '../components/timetable/IntegratedRooms.vue';
import SchoolEventsManager from '../components/timetable/SchoolEventsManager.vue';
import TeacherAvailability from '../components/timetable/TeacherAvailability.vue';
import ConstraintsManager from '../components/timetable/ConstraintsManager.vue';
import RoomTimetables from '../components/timetable/RoomTimetables.vue';
import MasterTimetable from '../components/timetable/MasterTimetable.vue';
import AnalyticsDashboard from '../components/timetable/AnalyticsDashboard.vue';
import PDFExports from '../components/timetable/PDFExports.vue';
import Settings from '../components/timetable/Settings.vue';

const { showToast } = useToast();

const activeView = ref('dashboard');
const activeSession = ref(null);
const loading = ref(false);

// Stats
const stats = ref({
  totalClasses: 0,
  totalTeachers: 0,
  totalSubjects: 0,
  totalRooms: 0
});

// Progress tracking
const progressItems = ref([
  { id: 'session', label: 'Academic Session', description: 'Set active academic session', status: 'pending' },
  { id: 'bell_schedule', label: 'Bell Schedule', description: 'Configure periods and breaks', status: 'pending' },
  { id: 'classes', label: 'Classes & Streams', description: 'Verify class data', status: 'pending' },
  { id: 'subjects', label: 'Subjects', description: 'Review subject list', status: 'pending' },
  { id: 'teachers', label: 'Teachers', description: 'Check teacher assignments', status: 'pending' },
  { id: 'rooms', label: 'Rooms', description: 'Configure room availability', status: 'pending' },
  { id: 'lesson_requirements', label: 'Lesson Requirements', description: 'Define lesson allocations', status: 'pending' },
  { id: 'constraints', label: 'Constraints', description: 'Set generation rules', status: 'pending' }
]);

// Conflicts
const conflicts = ref({
  teacher_conflicts: [],
  class_conflicts: [],
  room_conflicts: [],
  missing_lessons: [],
  teacher_overload: []
});

// Academic Sessions
const sessions = ref([]);
const showSessionForm = ref(false);
const sessionForm = ref({
  id: null,
  session_name: '',
  academic_year: new Date().getFullYear(),
  term: 1,
  start_date: '',
  end_date: '',
  is_active: false,
  is_archived: false
});

// Bell Schedule
const bellSchedules = ref([]);
const activeBellSchedule = ref(null);
const bellPeriods = ref([]);
const bellPeriodData = ref([]);
const showBellForm = ref(false);

// Lesson Requirements
const lessonRequirements = ref([]);
const showRequirementForm = ref(false);
const requirementForm = ref({
  id: null,
  academic_session_id: null,
  class_id: null,
  subject_id: null,
  teacher_id: null,
  room_id: null,
  periods_per_week: 1
});

// Class/Teacher Timetables
const classes = ref([]);
const teachersList = ref([]);
const selectedClassId = ref('');
const selectedTeacherId = ref('');
const classTimetable = ref([]);
const teacherTimetable = ref([]);

// Generation
const generationSessionId = ref('');
const generationClassId = ref('');
const generating = ref(false);
const generationResult = ref(null);

// Computed
const canGenerate = computed(() => {
  return generationSessionId.value && progressItems.value.every(p => p.status === 'complete');
});

// View change handler
const handleViewChange = (view) => {
  activeView.value = view;
  
  // Load data for specific views
  if (view === 'sessions') loadSessions();
  if (view === 'bell-schedule') loadBellSchedules();
  if (view === 'lesson-requirements') loadLessonRequirements();
  if (view === 'class-timetable') loadClasses();
  if (view === 'teacher-timetable') loadTeachersList();
  if (view === 'generate') loadSessions();
};

const getViewTitle = (view) => {
  const titles = {
    'sessions': 'Academic Sessions',
    'bell-schedule': 'Bell Schedule',
    'classes': 'Classes & Streams',
    'subjects': 'Subjects',
    'teachers': 'Teachers',
    'rooms': 'Rooms',
    'lesson-requirements': 'Lesson Requirements',
    'teacher-availability': 'Teacher Availability',
    'constraints': 'Constraints',
    'generate': 'Generate Timetable',
    'conflicts': 'Conflict Checker',
    'class-timetable': 'Class Timetables',
    'teacher-timetable': 'Teacher Timetables',
    'room-timetable': 'Room Timetables',
    'master-timetable': 'Master Timetable',
    'analytics': 'Analytics',
    'pdf-export': 'PDF Export'
  };
  return titles[view] || view;
};

// Load functions
const loadStats = async () => {
  try {
    const [classesRes, teachersRes, subjectsRes, roomsRes] = await Promise.allSettled([
      classesAPI.getAll(),
      teachersAPI.getAll(),
      subjectsNewAPI.getAll(),
      roomsAPI.getAll()
    ]);

    if (classesRes.status === 'fulfilled' && classesRes.value.success) {
      stats.value.totalClasses = Array.isArray(classesRes.value.data) ? classesRes.value.data.length : 0;
      classes.value = classesRes.value.data || [];
    }

    if (teachersRes.status === 'fulfilled' && teachersRes.value.success) {
      const teachersData = teachersRes.value.data.teachers || teachersRes.value.data || [];
      stats.value.totalTeachers = Array.isArray(teachersData) ? teachersData.length : 0;
      teachersList.value = Array.isArray(teachersData) ? teachersData : [];
    }

    if (subjectsRes.status === 'fulfilled' && subjectsRes.value.success) {
      stats.value.totalSubjects = Array.isArray(subjectsRes.value.data) ? subjectsRes.value.data.length : 0;
    }

    if (roomsRes.status === 'fulfilled' && roomsRes.value.success) {
      stats.value.totalRooms = Array.isArray(roomsRes.value.data) ? roomsRes.value.data.length : 0;
    }

    updateProgress();
  } catch (error) {
    console.error('Error loading stats:', error);
  }
};

const loadActiveSession = async () => {
  try {
    const res = await academicSessionsAPI.getActive();
    if (res.success && res.data) {
      activeSession.value = res.data;
      progressItems.value.find(p => p.id === 'session').status = 'complete';
      generationSessionId.value = res.data.id;
    }
  } catch (error) {
    console.error('Error loading active session:', error);
  }
};

const loadConflicts = async () => {
  const sessionId = generationSessionId.value || activeSession.value?.id;
  if (!sessionId) return;
  
  try {
    const res = await timetableConflictsAPI.getAll(sessionId);
    if (res.success) {
      conflicts.value = res.data;
    }
  } catch (error) {
    console.error('Error loading conflicts:', error);
  }
};

const loadSessions = async () => {
  try {
    const res = await academicSessionsAPI.getAll();
    if (res.success) {
      sessions.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading sessions:', error);
  }
};

const loadBellSchedules = async () => {
  try {
    const [schedulesRes, activeRes] = await Promise.allSettled([
      bellSchedulesAPI.getAll(),
      bellSchedulesAPI.getActive()
    ]);

    if (schedulesRes.status === 'fulfilled' && schedulesRes.value.success) {
      bellSchedules.value = schedulesRes.value.data || [];
    }

    if (activeRes.status === 'fulfilled' && activeRes.value.success) {
      activeBellSchedule.value = activeRes.value.data;
      if (activeBellSchedule.value) {
        loadBellPeriods(activeBellSchedule.value.id);
        progressItems.value.find(p => p.id === 'bell_schedule').status = 'complete';
      }
    }
  } catch (error) {
    console.error('Error loading bell schedules:', error);
  }
};

const loadBellPeriods = async (scheduleId) => {
  try {
    const res = await bellSchedulesAPI.getPeriods(scheduleId);
    if (res.success) {
      bellPeriodData.value = res.data || [];
      const periods = [...new Set(res.data.map(p => p.period_number))].sort((a, b) => a - b);
      bellPeriods.value = periods;
    }
  } catch (error) {
    console.error('Error loading bell periods:', error);
  }
};

const getPeriodTime = (day, period) => {
  const periodData = bellPeriodData.value.find(p => p.day_of_week === day && p.period_number === period);
  if (periodData) {
    return `${periodData.start_time} - ${periodData.end_time}`;
  }
  return '';
};

const loadLessonRequirements = async () => {
  const sessionId = generationSessionId.value || activeSession.value?.id;
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const res = await lessonRequirementsAPI.getBySession(sessionId);
    if (res.success) {
      lessonRequirements.value = res.data || [];
      if (lessonRequirements.value.length > 0) {
        progressItems.value.find(p => p.id === 'lesson_requirements').status = 'complete';
      }
    }
  } catch (error) {
    console.error('Error loading lesson requirements:', error);
  }
};

const loadClasses = async () => {
  if (classes.value.length === 0) {
    await loadStats();
  }
};

const loadTeachersList = async () => {
  if (teachersList.value.length === 0) {
    await loadStats();
  }
};

const loadClassTimetable = async () => {
  if (!selectedClassId.value) return;
  
  try {
    const res = await timetableAPI.getAll({
      class_id: selectedClassId.value,
      academic_session_id: generationSessionId.value || activeSession.value?.id
    });
    if (res.success) {
      classTimetable.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading class timetable:', error);
  }
};

const loadTeacherTimetable = async () => {
  if (!selectedTeacherId.value) return;
  
  try {
    const res = await timetableAPI.getAll({
      teacher_id: selectedTeacherId.value,
      academic_session_id: generationSessionId.value || activeSession.value?.id
    });
    if (res.success) {
      teacherTimetable.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading teacher timetable:', error);
  }
};

// Session management
const activateSession = async (id) => {
  try {
    const session = sessions.value.find(s => s.id === id);
    await academicSessionsAPI.update({ ...session, is_active: true });
    showToast('Session activated');
    loadSessions();
    loadActiveSession();
  } catch (error) {
    showToast('Failed to activate session', 'error');
  }
};

const editSession = (session) => {
  sessionForm.value = { ...session };
  showSessionForm.value = true;
};

const deleteSession = async (id) => {
  if (!confirm('Are you sure you want to delete this session?')) return;
  
  try {
    await academicSessionsAPI.delete(id);
    showToast('Session deleted');
    loadSessions();
  } catch (error) {
    showToast('Failed to delete session', 'error');
  }
};

// Requirement management
const editRequirement = (req) => {
  requirementForm.value = { ...req };
  showRequirementForm.value = true;
};

const deleteRequirement = async (id) => {
  if (!confirm('Are you sure you want to delete this requirement?')) return;
  
  try {
    await lessonRequirementsAPI.delete(id);
    showToast('Requirement deleted');
    loadLessonRequirements();
  } catch (error) {
    showToast('Failed to delete requirement', 'error');
  }
};

// Timetable management
const openTimetableForm = (data) => {
  // Open form to add/edit timetable entry
  console.log('Open timetable form for:', data);
};

const removeTimetableEntry = async (id) => {
  if (!confirm('Remove this timetable entry?')) return;
  
  try {
    await timetableAPI.delete(id);
    showToast('Entry removed');
    if (selectedClassId.value) loadClassTimetable();
  } catch (error) {
    showToast('Failed to remove entry', 'error');
  }
};

// Generation
const generateTimetable = async () => {
  if (!canGenerate.value) {
    showToast('Please complete all setup items first', 'warning');
    return;
  }
  
  generating.value = true;
  generationResult.value = null;
  
  try {
    const res = await timetableAPI.generate({
      academic_session_id: generationSessionId.value,
      class_id: generationClassId.value || null
    });
    
    generationResult.value = {
      success: res.success,
      message: res.message || 'Generation completed',
      details: res.data ? `Generated ${res.data.generated || 0} entries` : ''
    };
    
    if (res.success) {
      showToast('Timetable generated successfully');
      loadConflicts();
    } else {
      showToast(res.message || 'Generation failed', 'error');
    }
  } catch (error) {
    generationResult.value = {
      success: false,
      message: 'Generation failed',
      details: error.message
    };
    showToast('Failed to generate timetable', 'error');
  } finally {
    generating.value = false;
  }
};

// PDF Export
const exportClassPdf = () => {
  showToast('PDF export coming soon', 'info');
};

const exportTeacherPdf = () => {
  showToast('PDF export coming soon', 'info');
};

const updateProgress = () => {
  if (stats.value.totalClasses > 0) {
    progressItems.value.find(p => p.id === 'classes').status = 'complete';
  }
  if (stats.value.totalTeachers > 0) {
    progressItems.value.find(p => p.id === 'teachers').status = 'complete';
  }
  if (stats.value.totalSubjects > 0) {
    progressItems.value.find(p => p.id === 'subjects').status = 'complete';
  }
  if (stats.value.totalRooms > 0) {
    progressItems.value.find(p => p.id === 'rooms').status = 'complete';
  }
};

onMounted(async () => {
  loading.value = true;
  try {
    await Promise.all([
      loadStats(),
      loadActiveSession()
    ]);
    
    if (activeSession.value) {
      await loadConflicts();
    }
  } catch (error) {
    console.error('Error initializing dashboard:', error);
    showToast('Failed to load dashboard data', 'error');
  } finally {
    loading.value = false;
  }
});
</script>
