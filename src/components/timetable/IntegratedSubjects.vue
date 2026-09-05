<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Subjects</h2>
      <button @click="$emit('navigate', 'subjects')" class="btn-primary">Manage Subjects</button>
    </div>
    
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-slate-700">Total Subjects: {{ subjectsCount }}</span>
        <span :class="subjectsCount > 0 ? 'text-sm text-green-600' : 'text-sm text-amber-600'">
          {{ subjectsCount > 0 ? '✓ Subjects configured' : '⚠ No subjects found' }}
        </span>
      </div>
      
      <div v-if="subjectsCount > 0" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left border">Subject Code</th>
              <th class="px-4 py-2 text-left border">Subject Name</th>
              <th class="px-4 py-2 text-left border">Category</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in subjects" :key="s.id" class="hover:bg-slate-50">
              <td class="px-4 py-2 border">{{ s.subject_code }}</td>
              <td class="px-4 py-2 border">{{ s.subject_name }}</td>
              <td class="px-4 py-2 border">{{ s.category || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { subjectsNewAPI } from '../../services/api.js';

const subjects = ref([]);
const subjectsCount = ref(0);

const loadSubjects = async () => {
  try {
    const res = await subjectsNewAPI.getAll();
    if (res.success) {
      subjects.value = res.data || [];
      subjectsCount.value = subjects.value.length;
    }
  } catch (error) {
    console.error('Error loading subjects:', error);
  }
};

onMounted(() => {
  loadSubjects();
});
</script>
