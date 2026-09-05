<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold text-slate-900">Rooms</h2>
      <button @click="$emit('navigate', 'rooms')" class="btn-primary">Manage Rooms</button>
    </div>
    
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <span class="text-sm font-medium text-slate-700">Total Rooms: {{ roomsCount }}</span>
        <span :class="roomsCount > 0 ? 'text-sm text-green-600' : 'text-sm text-amber-600'">
          {{ roomsCount > 0 ? '✓ Rooms configured' : '⚠ No rooms found' }}
        </span>
      </div>
      
      <div v-if="roomsCount > 0" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left border">Room Code</th>
              <th class="px-4 py-2 text-left border">Room Name</th>
              <th class="px-4 py-2 text-left border">Type</th>
              <th class="px-4 py-2 text-left border">Capacity</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rooms" :key="r.id" class="hover:bg-slate-50">
              <td class="px-4 py-2 border">{{ r.room_code }}</td>
              <td class="px-4 py-2 border">{{ r.room_name }}</td>
              <td class="px-4 py-2 border">{{ r.room_type || '-' }}</td>
              <td class="px-4 py-2 border">{{ r.capacity || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { roomsAPI } from '../../services/api.js';

const rooms = ref([]);
const roomsCount = ref(0);

const loadRooms = async () => {
  try {
    const res = await roomsAPI.getAll();
    if (res.success) {
      rooms.value = res.data || [];
      roomsCount.value = rooms.value.length;
    }
  } catch (error) {
    console.error('Error loading rooms:', error);
  }
};

onMounted(() => {
  loadRooms();
});
</script>
