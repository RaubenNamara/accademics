<template>
  <div class="space-y-6">
    <h2 class="text-xl font-semibold text-slate-900">PDF Exports</h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- Class Timetables -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Class Timetables</h3>
        <div class="space-y-4">
          <select v-model="classExport.class_id" class="input w-full">
            <option value="">Select Class</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.class_name }} {{ c.stream_name ? `(${c.stream_name})` : '' }}</option>
          </select>
          <button @click="exportClassPDF" :disabled="!classExport.class_id" class="btn-primary w-full" :class="{ 'opacity-50': !classExport.class_id }">
            Export Class Timetable
          </button>
        </div>
      </div>
      
      <!-- Teacher Timetables -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Teacher Timetables</h3>
        <div class="space-y-4">
          <select v-model="teacherExport.teacher_id" class="input w-full">
            <option value="">Select Teacher</option>
            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
          </select>
          <button @click="exportTeacherPDF" :disabled="!teacherExport.teacher_id" class="btn-primary w-full" :class="{ 'opacity-50': !teacherExport.teacher_id }">
            Export Teacher Timetable
          </button>
        </div>
      </div>
      
      <!-- Room Timetables -->
      <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-medium text-slate-900 mb-4">Room Timetables</h3>
        <div class="space-y-4">
          <select v-model="roomExport.room_id" class="input w-full">
            <option value="">Select Room</option>
            <option v-for="r in rooms" :key="r.id" :value="r.id">{{ r.room_code }} - {{ r.room_name }}</option>
          </select>
          <button @click="exportRoomPDF" :disabled="!roomExport.room_id" class="btn-primary w-full" :class="{ 'opacity-50': !roomExport.room_id }">
            Export Room Timetable
          </button>
        </div>
      </div>
    </div>
    
    <!-- Master Timetable -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-medium text-slate-900 mb-4">Master Timetable</h3>
      <p class="text-sm text-slate-600 mb-4">Export all class timetables in a single PDF document.</p>
      <button @click="exportMasterPDF" class="btn-primary">Export Master Timetable</button>
    </div>
    
    <!-- Export History -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-medium text-slate-900 mb-4">Export History</h3>
      <div v-if="exportHistory.length > 0" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left border">Type</th>
              <th class="px-4 py-2 text-left border">Entity</th>
              <th class="px-4 py-2 text-left border">Date</th>
              <th class="px-4 py-2 text-left border">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in exportHistory" :key="h.id" class="hover:bg-slate-50">
              <td class="px-4 py-2 border capitalize">{{ h.type }}</td>
              <td class="px-4 py-2 border">{{ h.entity }}</td>
              <td class="px-4 py-2 border">{{ formatDate(h.date) }}</td>
              <td class="px-4 py-2 border">
                <span :class="h.status === 'success' ? 'text-green-600' : 'text-red-600'">{{ h.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="text-center text-slate-600 py-4">
        No export history
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { classesAPI, teachersAPI, roomsAPI, timetablePDFAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';

const props = defineProps({
  activeSession: {
    type: Object,
    default: null
  }
});

const { showToast } = useToast();

const classes = ref([]);
const teachers = ref([]);
const rooms = ref([]);
const classExport = ref({ class_id: null });
const teacherExport = ref({ teacher_id: null });
const roomExport = ref({ room_id: null });
const exportHistory = ref([]);

const loadDropdownData = async () => {
  try {
    const [classesRes, teachersRes, roomsRes] = await Promise.allSettled([
      classesAPI.getAll(),
      teachersAPI.getAll(),
      roomsAPI.getAll()
    ]);

    if (classesRes.status === 'fulfilled' && classesRes.value.success) {
      classes.value = classesRes.value.data || [];
    }
    if (teachersRes.status === 'fulfilled' && teachersRes.value.success) {
      teachers.value = teachersRes.value.data.teachers || teachersRes.value.data || [];
    }
    if (roomsRes.status === 'fulfilled' && roomsRes.value.success) {
      rooms.value = roomsRes.value.data || [];
    }
  } catch (error) {
    console.error('Error loading dropdown data:', error);
  }
};

const exportClassPDF = async () => {
  const sessionId = props.activeSession?.id;
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const res = await timetablePDFAPI.exportClass({
      academic_session_id: sessionId,
      class_id: classExport.value.class_id
    });
    
    if (res.success) {
      downloadPDF(res.data.html, res.data.filename);
      addToHistory('class', classes.value.find(c => c.id === classExport.value.class_id)?.class_name, 'success');
      showToast('Class timetable exported');
    }
  } catch (error) {
    console.error('Error exporting PDF:', error);
    showToast('Failed to export PDF', 'error');
    addToHistory('class', classes.value.find(c => c.id === classExport.value.class_id)?.class_name, 'failed');
  }
};

const exportTeacherPDF = async () => {
  const sessionId = props.activeSession?.id;
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const res = await timetablePDFAPI.exportTeacher({
      academic_session_id: sessionId,
      teacher_id: teacherExport.value.teacher_id
    });
    
    if (res.success) {
      downloadPDF(res.data.html, res.data.filename);
      addToHistory('teacher', teachers.value.find(t => t.id === teacherExport.value.teacher_id)?.full_name, 'success');
      showToast('Teacher timetable exported');
    }
  } catch (error) {
    console.error('Error exporting PDF:', error);
    showToast('Failed to export PDF', 'error');
    addToHistory('teacher', teachers.value.find(t => t.id === teacherExport.value.teacher_id)?.full_name, 'failed');
  }
};

const exportRoomPDF = async () => {
  const sessionId = props.activeSession?.id;
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const res = await timetablePDFAPI.exportRoom({
      academic_session_id: sessionId,
      room_id: roomExport.value.room_id
    });
    
    if (res.success) {
      downloadPDF(res.data.html, res.data.filename);
      addToHistory('room', rooms.value.find(r => r.id === roomExport.value.room_id)?.room_code, 'success');
      showToast('Room timetable exported');
    }
  } catch (error) {
    console.error('Error exporting PDF:', error);
    showToast('Failed to export PDF', 'error');
    addToHistory('room', rooms.value.find(r => r.id === roomExport.value.room_id)?.room_code, 'failed');
  }
};

const exportMasterPDF = async () => {
  const sessionId = props.activeSession?.id;
  if (!sessionId) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }
  
  try {
    const res = await timetablePDFAPI.exportMaster({
      academic_session_id: sessionId
    });
    
    if (res.success) {
      downloadPDF(res.data.html, res.data.filename);
      addToHistory('master', 'All Classes', 'success');
      showToast('Master timetable exported');
    }
  } catch (error) {
    console.error('Error exporting PDF:', error);
    showToast('Failed to export PDF', 'error');
    addToHistory('master', 'All Classes', 'failed');
  }
};

const downloadPDF = (html, filename) => {
  const blob = new Blob([html], { type: 'text/html' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
};

const addToHistory = (type, entity, status) => {
  exportHistory.value.unshift({
    id: Date.now(),
    type,
    entity,
    date: new Date(),
    status
  });
};

const formatDate = (date) => {
  return new Date(date).toLocaleString();
};

onMounted(() => {
  loadDropdownData();
});
</script>
