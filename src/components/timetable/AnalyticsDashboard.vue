<template>
  <div class="space-y-6">
    <h2 class="text-xl font-semibold text-slate-900">Timetable Analytics</h2>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-slate-600">Total Lessons</p>
            <p class="text-2xl font-bold text-slate-900">{{ dashboard.total_lessons }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-slate-600">Active Teachers</p>
            <p class="text-2xl font-bold text-slate-900">{{ dashboard.total_teachers }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-slate-600">Classes Covered</p>
            <p class="text-2xl font-bold text-slate-900">{{ dashboard.total_classes }}</p>
          </div>
          <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-slate-600">Room Utilization</p>
            <p class="text-2xl font-bold text-slate-900">{{ dashboard.total_rooms }}</p>
          </div>
          <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path>
            </svg>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Analytics Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Teacher Workload -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Teacher Workload</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left border">Teacher</th>
                <th class="px-4 py-2 text-left border">Lessons</th>
                <th class="px-4 py-2 text-left border">Classes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in teacherWorkload.slice(0, 10)" :key="t.id" class="hover:bg-slate-50">
                <td class="px-4 py-2 border">{{ t.full_name }}</td>
                <td class="px-4 py-2 border">{{ t.total_lessons }}</td>
                <td class="px-4 py-2 border">{{ t.classes_taught }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Subject Coverage -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Subject Coverage</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left border">Subject</th>
                <th class="px-4 py-2 text-left border">Classes</th>
                <th class="px-4 py-2 text-left border">Lessons</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in subjectCoverage.slice(0, 10)" :key="s.id" class="hover:bg-slate-50">
                <td class="px-4 py-2 border">{{ s.subject_name }}</td>
                <td class="px-4 py-2 border">{{ s.classes_covered }}</td>
                <td class="px-4 py-2 border">{{ s.total_lessons }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Class Coverage -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Class Coverage</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left border">Class</th>
                <th class="px-4 py-2 text-left border">Subjects</th>
                <th class="px-4 py-2 text-left border">Lessons</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in classCoverage.slice(0, 10)" :key="c.id" class="hover:bg-slate-50">
                <td class="px-4 py-2 border">{{ c.class_name }} {{ c.stream_name ? `(${c.stream_name})` : '' }}</td>
                <td class="px-4 py-2 border">{{ c.subjects_covered }}</td>
                <td class="px-4 py-2 border">{{ c.total_lessons }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Room Utilization -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Room Utilization</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-2 text-left border">Room</th>
                <th class="px-4 py-2 text-left border">Type</th>
                <th class="px-4 py-2 text-left border">Lessons</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in roomUtilization.slice(0, 10)" :key="r.id" class="hover:bg-slate-50">
                <td class="px-4 py-2 border">{{ r.room_code }}</td>
                <td class="px-4 py-2 border">{{ r.room_type || '-' }}</td>
                <td class="px-4 py-2 border">{{ r.total_lessons }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { timetableAnalyticsAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';

const props = defineProps({
  activeSession: {
    type: Object,
    default: null
  }
});

const { showToast } = useToast();

const dashboard = ref({
  total_lessons: 0,
  total_teachers: 0,
  total_classes: 0,
  total_rooms: 0
});

const teacherWorkload = ref([]);
const subjectCoverage = ref([]);
const classCoverage = ref([]);
const roomUtilization = ref([]);

const loadAnalytics = async () => {
  const sessionId = props.activeSession?.id;
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const [dashboardRes, workloadRes, subjectRes, classRes, roomRes] = await Promise.allSettled([
      timetableAnalyticsAPI.getDashboard(sessionId),
      timetableAnalyticsAPI.getTeacherWorkload(sessionId),
      timetableAnalyticsAPI.getSubjectCoverage(sessionId),
      timetableAnalyticsAPI.getClassCoverage(sessionId),
      timetableAnalyticsAPI.getRoomUtilization(sessionId)
    ]);

    if (dashboardRes.status === 'fulfilled' && dashboardRes.value.success) {
      dashboard.value = dashboardRes.value.data || dashboard.value;
    }
    if (workloadRes.status === 'fulfilled' && workloadRes.value.success) {
      teacherWorkload.value = workloadRes.value.data || [];
    }
    if (subjectRes.status === 'fulfilled' && subjectRes.value.success) {
      subjectCoverage.value = subjectRes.value.data || [];
    }
    if (classRes.status === 'fulfilled' && classRes.value.success) {
      classCoverage.value = classRes.value.data || [];
    }
    if (roomRes.status === 'fulfilled' && roomRes.value.success) {
      roomUtilization.value = roomRes.value.data || [];
    }
  } catch (error) {
    console.error('Error loading analytics:', error);
    showToast('Failed to load analytics', 'error');
  }
};

watch(() => props.activeSession, () => {
  loadAnalytics();
});

onMounted(() => {
  loadAnalytics();
});
</script>
