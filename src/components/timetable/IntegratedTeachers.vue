<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Teachers</h2>
      <button @click="$emit('navigate', 'teachers')" class="btn-primary">Manage Teachers</button>
    </div>
    
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-slate-700">Total Teachers: {{ teachersCount }}</span>
        <span :class="teachersCount > 0 ? 'text-sm text-green-600' : 'text-sm text-amber-600'">
          {{ teachersCount > 0 ? '✓ Teachers configured' : '⚠ No teachers found' }}
        </span>
      </div>
      
      <div v-if="teachersCount > 0" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left border">Teacher Code</th>
              <th class="px-4 py-2 text-left border">Full Name</th>
              <th class="px-4 py-2 text-left border">Subject</th>
              <th class="px-4 py-2 text-left border">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in teachers" :key="t.id" class="hover:bg-slate-50">
              <td class="px-4 py-2 border">{{ t.teacher_code }}</td>
              <td class="px-4 py-2 border">{{ t.full_name }}</td>
              <td class="px-4 py-2 border">{{ t.subject || '-' }}</td>
              <td class="px-4 py-2 border">
                <span :class="t.is_active ? 'text-green-600' : 'text-slate-400'">
                  {{ t.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { teachersAPI } from '../../services/api.js';

const teachers = ref([]);
const teachersCount = ref(0);

const loadTeachers = async () => {
  try {
    const res = await teachersAPI.getAll();
    if (res.success) {
      teachers.value = res.data.teachers || res.data || [];
      teachersCount.value = teachers.value.length;
    }
  } catch (error) {
    console.error('Error loading teachers:', error);
  }
};

onMounted(() => {
  loadTeachers();
});
</script>
