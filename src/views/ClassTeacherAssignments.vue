<template>
  <div class="space-y-5">
    <ToastBanner />
    <PageHeader title="Class Teacher Assignments" subtitle="Assign teachers to classes & streams by academic year" @refresh="load" @add="openModal()" />

    <div class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4">
      <input v-model.number="filters.academic_year" type="number" class="input w-32" placeholder="Year" @change="load" />
      <label class="flex items-center gap-2 text-sm">
        <input v-model="showHistory" type="checkbox" @change="load" /> Show history
      </label>
    </div>

    <DataPanel :loading="loading">
      <table class="data-table">
        <thead>
          <tr>
            <th>Teacher</th>
            <th>Code</th>
            <th>Class</th>
            <th>Stream</th>
            <th>Year</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in assignments" :key="a.id">
            <td class="font-medium">{{ a.teacher_name }}</td>
            <td><span class="code-badge">{{ a.teacher_code }}</span></td>
            <td>{{ a.class_name }}</td>
            <td>{{ a.stream || '—' }}</td>
            <td>{{ a.academic_year }}</td>
            <td>
              <StatusPill :status="a.is_active == 1 ? 'active' : 'inactive'" />
            </td>
            <td class="text-right space-x-2">
              <button v-if="a.is_active == 1" class="link-btn" @click="openReassign(a)">Reassign</button>
              <button v-if="a.is_active == 1" class="text-sm text-rose-600" @click="endAssignment(a.id)">End</button>
            </td>
          </tr>
        </tbody>
      </table>
    </DataPanel>

    <Modal v-if="showModal" :title="reassignId ? 'Reassign Class Teacher' : 'New Assignment'" @close="closeModal">
      <form class="grid gap-3" @submit.prevent="save">
        <select v-if="!reassignId" v-model="form.class_id" class="input" required>
          <option value="">Class</option>
          <option v-for="name in uniqueClassNames" :key="name" :value="name">{{ name }}</option>
        </select>
        <div class="relative">
          <input
            v-model="teacherSearch"
            type="text"
            placeholder="Search teacher..."
            class="input mb-2"
          >
          <svg class="w-5 h-5 text-gray-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </div>
        <select v-model="form.teacher_id" class="input" required>
          <option value="">Teacher</option>
          <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
        </select>
        <input v-model="form.stream" class="input" placeholder="Stream (optional)" />
        <input v-model.number="form.academic_year" type="number" class="input" required />
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
          <button type="submit" class="btn-primary">Save</button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { classTeacherAssignmentsAPI, teachersAPI, classesAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import PageHeader from '../components/hr/PageHeader.vue';
import DataPanel from '../components/hr/DataPanel.vue';
import Modal from '../components/hr/Modal.vue';
import StatusPill from '../components/hr/StatusPill.vue';

const { showToast } = useToast();
const loading = ref(false);
const showModal = ref(false);
const showHistory = ref(false);
const reassignId = ref(null);
const assignments = ref([]);
const teachers = ref([]);
const classes = ref([]);
const filters = ref({ academic_year: new Date().getFullYear() });
const form = ref({ teacher_id: '', class_id: '', stream: '', academic_year: new Date().getFullYear() });

// Get unique class names (without stream duplicates)
const uniqueClassNames = computed(() => {
  const names = classes.value.map(c => c.class_name).filter(Boolean);
  return [...new Set(names)].sort();
});

const load = async () => {
  loading.value = true;
  try {
    const res = await classTeacherAssignmentsAPI.getAll({
      academic_year: filters.value.academic_year,
      history: showHistory.value ? '1' : '0',
      active_only: showHistory.value ? '0' : '1'
    });
    assignments.value = res.success ? res.data || [] : [];
  } finally {
    loading.value = false;
  }
};

const openModal = () => {
  reassignId.value = null;
  form.value = { teacher_id: '', class_id: '', stream: '', academic_year: filters.value.academic_year };
  showModal.value = true;
};

const openReassign = (row) => {
  reassignId.value = row.id;
  // Use class_name for the dropdown, not class_id
  const classObj = classes.value.find(c => c.id === row.class_id);
  form.value = { teacher_id: '', class_id: classObj ? classObj.class_name : row.class_id, stream: row.stream, academic_year: row.academic_year };
  showModal.value = true;
};

const closeModal = () => { showModal.value = false; reassignId.value = null; };

const save = async () => {
  try {
    // If class_id is a class name (string), find the corresponding class_id
    let payload = { ...form.value };
    if (typeof payload.class_id === 'string') {
      const classObj = classes.value.find(c => c.class_name === payload.class_id);
      if (classObj) {
        payload.class_id = classObj.id;
      }
    }
    
    const res = reassignId.value
      ? await classTeacherAssignmentsAPI.reassign({ id: reassignId.value, teacher_id: payload.teacher_id })
      : await classTeacherAssignmentsAPI.assign(payload);
    if (res.success) {
      showToast(res.message || 'Saved');
      closeModal();
      load();
    } else showToast(res.message || 'Failed', 'error');
  } catch {
    showToast('Save failed', 'error');
  }
};

const endAssignment = async (id) => {
  if (!confirm('End this assignment?')) return;
  await classTeacherAssignmentsAPI.end(id);
  showToast('Assignment ended');
  load();
};

const teacherSearch = ref('');

const filteredTeachers = computed(() => {
  if (!teacherSearch.value) return teachers.value;
  const search = teacherSearch.value.toLowerCase();
  return teachers.value.filter(teacher =>
    teacher.full_name.toLowerCase().includes(search) ||
    teacher.teacher_code.toLowerCase().includes(search)
  );
});

onMounted(async () => {
  const [t, c] = await Promise.all([teachersAPI.getAll(), classesAPI.getAll()]);
  teachers.value = t.success ? t.data.teachers || t.data || [] : [];
  classes.value = c.success ? c.data || [] : [];
  load();
});
</script>
