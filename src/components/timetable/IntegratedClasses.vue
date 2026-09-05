<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Classes & Streams</h2>
      <button @click="$emit('navigate', 'classes')" class="btn-primary">Manage Classes</button>
    </div>
    
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-slate-700">Total Classes: {{ classesCount }}</span>
        <span :class="classesCount > 0 ? 'text-sm text-green-600' : 'text-sm text-amber-600'">
          {{ classesCount > 0 ? '✓ Classes configured' : '⚠ No classes found' }}
        </span>
      </div>
      
      <div v-if="classesCount > 0" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left border">Class Name</th>
              <th class="px-4 py-2 text-left border">Stream</th>
              <th class="px-4 py-2 text-left border">Students</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in classes" :key="c.id" class="hover:bg-slate-50">
              <td class="px-4 py-2 border">{{ c.class_name }}</td>
              <td class="px-4 py-2 border">{{ c.stream_name || '-' }}</td>
              <td class="px-4 py-2 border">{{ c.student_count || 0 }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { classesAPI } from '../../services/api.js';

const classes = ref([]);
const classesCount = ref(0);

const loadClasses = async () => {
  try {
    const res = await classesAPI.getAll();
    if (res.success) {
      classes.value = res.data || [];
      classesCount.value = classes.value.length;
    }
  } catch (error) {
    console.error('Error loading classes:', error);
  }
};

onMounted(() => {
  loadClasses();
});
</script>
