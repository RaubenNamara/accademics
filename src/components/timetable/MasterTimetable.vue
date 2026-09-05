<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Master Timetable</h2>
      <div class="flex gap-3">
        <select v-model="selectedClassId" class="input" @change="loadClassTimetable">
          <option value="">Filter by Class</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.class_name }} {{ c.stream_name ? `(${c.stream_name})` : '' }}</option>
        </select>
        <button @click="exportPDF" class="btn-secondary">Export PDF</button>
      </div>
    </div>

    <div v-if="selectedClassId && timetable.length > 0" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-medium text-slate-900 mb-4">
        {{ selectedClass?.class_name }} {{ selectedClass?.stream_name ? `(${selectedClass.stream_name})` : '' }}
      </h3>

      <TimetableGrid :entries="timetable" :editable="false" />
    </div>

    <div v-else-if="selectedClassId" class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
      No timetable entries found for this class
    </div>

    <div v-else class="bg-white rounded-xl border border-slate-200 p-8 text-center text-slate-600">
      Select a class to view its timetable
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { classesAPI, timetableAPI, timetablePDFAPI } from '../../services/api.js';
import { useToast } from '../../composables/useToast.js';
import TimetableGrid from './TimetableGrid.vue';

const props = defineProps({
  activeSession: {
    type: Object,
    default: null
  }
});

const { showToast } = useToast();

const classes = ref([]);
const selectedClassId = ref('');
const timetable = ref([]);

const selectedClass = computed(() => {
  return classes.value.find(c => c.id === selectedClassId.value);
});

const loadClasses = async () => {
  try {
    const res = await classesAPI.getAll();
    if (res.success) {
      classes.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading classes:', error);
    showToast('Failed to load classes', 'error');
  }
};

const loadClassTimetable = async () => {
  if (!selectedClassId.value) return;

  if (!props.activeSession) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }

  try {
    const res = await timetableAPI.getByClass(props.activeSession.academic_year, props.activeSession.term, selectedClassId.value);
    if (res.success) {
      timetable.value = res.data || [];
    }
  } catch (error) {
    console.error('Error loading timetable:', error);
    showToast('Failed to load timetable', 'error');
  }
};

const exportPDF = async () => {
  if (!props.activeSession) {
    showToast('Please select an active academic session first', 'warning');
    return;
  }

  try {
    const res = await timetablePDFAPI.exportClass({
      academic_session_id: props.activeSession.id,
      class_id: selectedClassId.value
    });

    if (res.success) {
      const blob = new Blob([res.data.html], { type: 'text/html' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = res.data.filename;
      a.click();
      URL.revokeObjectURL(url);
      showToast('PDF exported successfully');
    }
  } catch (error) {
    console.error('Error exporting PDF:', error);
    showToast('Failed to export PDF', 'error');
  }
};

watch(() => props.activeSession, () => {
  if (selectedClassId.value) loadClassTimetable();
});

onMounted(() => {
  loadClasses();
});
</script>
