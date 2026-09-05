<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-800">Bulk Import Results</h1>
      <p class="text-gray-600 mt-2">Import marksheet results for multiple students</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Import Configuration</h2>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Level *</label>
          <select v-model="importConfig.level" @change="loadSubjects" required class="w-full border rounded-lg px-3 py-2">
            <option value="">Select Level</option>
            <option value="O_LEVEL">O Level</option>
            <option value="A_LEVEL">A Level</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Year *</label>
          <input v-model="importConfig.year" type="number" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Term *</label>
          <select v-model="importConfig.term" required class="w-full border rounded-lg px-3 py-2">
            <option value="">Select Term</option>
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Exam Type *</label>
          <select v-model="importConfig.exam_type" required class="w-full border rounded-lg px-3 py-2">
            <option value="">Select Exam</option>
            <option value="BOT1">BOT 1</option>
            <option value="EOT1">EOT 1</option>
            <option value="BOT2">BOT 2</option>
            <option value="EOT2">EOT 2</option>
            <option value="BOT3">BOT 3</option>
            <option value="EOT3">EOT 3</option>
            <option value="FINAL">Final</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="importConfig.level" class="bg-white rounded-lg shadow p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Available Subjects</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
        <div v-for="subject in availableSubjects" :key="subject.id" class="bg-gray-50 rounded p-2 text-sm">
          <span class="font-medium">{{ subject.subject_code }}</span> - {{ subject.subject_name }}
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">Results Data</h2>
        <div class="flex gap-2">
          <button @click="addRow" class="bg-green-600 text-white px-3 py-2 rounded-lg text-sm">Add Row</button>
          <button @click="clearData" class="bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm">Clear All</button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Admission No *</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject *</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Marks *</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Remarks</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="(row, index) in importData" :key="index">
              <td class="px-4 py-2"><input v-model="row.admission_number" type="text" class="w-full border rounded px-2 py-1 text-sm"></td>
              <td class="px-4 py-2">
                <select v-model="row.subject_code" class="w-full border rounded px-2 py-1 text-sm">
                  <option value="">Select Subject</option>
                  <option v-for="subject in availableSubjects" :key="subject.id" :value="subject.subject_code">
                    {{ subject.subject_code }} - {{ subject.subject_name }}
                  </option>
                </select>
              </td>
              <td class="px-4 py-2"><input v-model="row.marks" type="number" min="0" max="100" class="w-full border rounded px-2 py-1 text-sm"></td>
              <td class="px-4 py-2"><input v-model="row.remarks" type="text" class="w-full border rounded px-2 py-1 text-sm"></td>
              <td class="px-4 py-2"><button @click="removeRow(index)" class="text-red-600">Remove</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="flex justify-end">
      <button @click="importResults" :disabled="!canImport || importing" class="bg-blue-600 text-white px-6 py-3 rounded-lg disabled:opacity-50">
        {{ importing ? 'Importing...' : 'Import Results' }}
      </button>
    </div>

    <div v-if="importResult" class="mt-6 p-6 rounded-lg" :class="importResult.success ? 'bg-green-50' : 'bg-red-50'">
      <h3 class="text-lg font-semibold mb-4">{{ importResult.success ? 'Import Completed' : 'Import Failed' }}</h3>
      <div class="grid grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded p-3">
          <div class="text-2xl font-bold">{{ importResult.data.total }}</div>
          <div class="text-sm text-gray-600">Total</div>
        </div>
        <div class="bg-white rounded p-3">
          <div class="text-2xl font-bold text-green-600">{{ importResult.data.success }}</div>
          <div class="text-sm text-gray-600">Success</div>
        </div>
        <div class="bg-white rounded p-3">
          <div class="text-2xl font-bold text-red-600">{{ importResult.data.errors }}</div>
          <div class="text-sm text-gray-600">Errors</div>
        </div>
        <div class="bg-white rounded p-3">
          <div class="text-2xl font-bold text-orange-600">{{ importResult.data.duplicates }}</div>
          <div class="text-sm text-gray-600">Duplicates</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '../services/api.js';

const importConfig = ref({ level: '', year: new Date().getFullYear(), term: '', exam_type: '' });
const importData = ref([]);
const availableSubjects = ref([]);
const importing = ref(false);
const importResult = ref(null);

const canImport = computed(() => importConfig.value.level && 
         importConfig.value.year && 
         importConfig.value.term && 
         importConfig.value.exam_type &&
         importData.value.length > 0);

const loadSubjects = async () => {
  if (!importConfig.value.level) return;
  try {
    const response = await api.get(`subjects.php?action=list&level=${importConfig.value.level}`);
    availableSubjects.value = response.data.data;
  } catch (error) { console.error(error); }
};

const addRow = () => importData.value.push({ admission_number: '', subject_code: '', marks: '', remarks: '' });
const removeRow = (index) => importData.value.splice(index, 1);
const clearData = () => { importData.value = []; importResult.value = null; };

const importResults = async () => {
  if (!canImport.value) return;
  importing.value = true;
  try {
    const response = await api.post(`bulk-import-results.php`, {
      level: importConfig.value.level,
      year: importConfig.value.year,
      term: importConfig.value.term,
      exam_type: importConfig.value.exam_type,
      results: importData.value
    });
    importResult.value = response.data;
    if (response.data.success) importData.value = [];
  } catch (error) {
    console.error(error);
    importResult.value = { success: false, data: { total: 0, success: 0, errors: 1, duplicates: 0 } };
  } finally {
    importing.value = false;
  }
};
</script>

