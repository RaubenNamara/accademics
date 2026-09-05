<template>
  <div class="p-6 bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <!-- Header with Gradient Background -->
    <div class="rounded-3xl bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-800 p-8 text-white shadow-2xl mb-8">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <div class="flex items-center gap-3 mb-3">
            <div class="bg-white/20 p-3 rounded-xl backdrop-blur">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
            <div>
              <p class="text-sm uppercase tracking-[0.2em] text-blue-200">Academic Tracking</p>
              <h1 class="text-4xl font-bold mt-1">Student Academic Track</h1>
            </div>
          </div>
          <p class="text-blue-100 text-lg max-w-2xl">
            Manage and view student academic results with comprehensive tracking
          </p>
        </div>
        <div class="flex flex-wrap gap-3">
          <div class="bg-white/20 backdrop-blur rounded-xl px-6 py-3">
            <div class="text-2xl font-bold">{{ results.length }}</div>
            <div class="text-sm text-blue-200">Total Results</div>
          </div>
          <button
            @click="loadResults"
            class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-5 py-3 text-sm font-medium text-white backdrop-blur hover:bg-white/20 transition-all shadow-lg"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh
          </button>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="flex items-center gap-3 mb-4">
        <div class="bg-blue-100 p-2 rounded-lg">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-800">Filters</h2>
      </div>
      <div class="grid gap-4 xl:grid-cols-6">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Student</label>
          <select
            v-model="filters.student_id"
            @change="loadResults"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
          >
            <option value="">All Students</option>
            <option v-for="student in students" :key="student.id" :value="student.id">
              {{ student.full_name }} ({{ student.admission_number }})
            </option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Year</label>
          <select
            v-model="filters.year"
            @change="loadResults"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
          >
            <option value="">All Years</option>
            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Term</label>
          <select
            v-model="filters.term"
            @change="loadResults"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
          >
            <option value="">All Terms</option>
            <option value="1">Term 1</option>
            <option value="2">Term 2</option>
            <option value="3">Term 3</option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Subject</label>
          <select
            v-model="filters.subject_id"
            @change="loadResults"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
          >
            <option value="">All Subjects</option>
            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">
              {{ subject.subject_name }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Class</label>
          <select
            v-model="filters.class"
            @change="loadResults"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
          >
            <option value="">All Classes</option>
            <option v-for="classItem in classes" :key="classItem" :value="classItem">
              {{ classItem }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Stream</label>
          <select
            v-model="filters.stream"
            @change="loadResults"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
          >
            <option value="">All Streams</option>
            <option v-for="stream in streamOptions" :key="stream" :value="stream">
              {{ stream }}
            </option>
          </select>
        </div>
        <div class="flex items-end">
          <button
            @click="clearFilters"
            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors"
          >
            Clear Filters
          </button>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-lg">
      <div class="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
        <div class="flex items-center gap-3">
          <div class="bg-blue-100 p-2 rounded-lg">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <div>
            <span class="text-sm text-gray-600">Showing </span>
            <span class="font-bold text-blue-600 text-lg">{{ results.length }}</span>
            <span class="text-sm text-gray-600"> results</span>
          </div>
        </div>
        <div class="flex gap-3">
          <button
            @click="downloadPDF"
            :disabled="results.length === 0"
            class="rounded-xl bg-gradient-to-r from-green-600 to-green-700 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-green-500/20 hover:from-green-700 hover:to-green-800 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-all hover:shadow-xl"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Download PDF
          </button>
          <button
            @click="showAddModal = true"
            class="rounded-xl bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-3 text-sm font-medium text-white shadow-lg shadow-blue-500/20 hover:from-blue-700 hover:to-blue-800 flex items-center gap-2 transition-all hover:shadow-xl"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Result
          </button>
        </div>
      </div>
    </div>

    <!-- Results Table -->
    <div class="rounded-3xl border border-gray-200 bg-white shadow-lg overflow-hidden">
      <div class="border-b border-gray-200 bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
        <div class="flex items-center gap-3">
          <div class="bg-white/20 p-2 rounded-lg backdrop-blur">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
          </div>
          <h2 class="text-xl font-bold text-white">Academic Results</h2>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-[1200px] w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-700">
              <th class="px-6 py-4 font-bold">Student</th>
              <th class="px-6 py-4 font-bold">Class</th>
              <th class="px-6 py-4 font-bold">Stream</th>
              <th v-for="subject in subjects" :key="subject.id" class="px-6 py-4 font-bold">
                {{ subject.subject_name }}
              </th>
              <th class="px-6 py-4 font-bold">Status</th>
              <th class="px-6 py-4 text-right font-bold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            <tr v-if="pivotData.length === 0">
              <td :colspan="subjects.length + 5" class="px-6 py-16 text-center text-gray-500">
                <div class="flex flex-col items-center gap-4">
                  <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                  </svg>
                  <div>
                    <p class="text-xl font-semibold text-gray-600">No academic results found</p>
                    <p class="text-sm text-gray-400 mt-1">Try adjusting your filters or add some results to get started</p>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-for="student in pivotData" :key="student.student_id" class="transition hover:bg-blue-50">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-sm font-bold text-blue-600">{{ student.student_name.charAt(0).toUpperCase() }}</span>
                  </div>
                  <div>
                    <div class="font-semibold text-gray-900">{{ student.student_name }}</div>
                    <div class="text-sm text-gray-500">{{ student.admission_number }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                  {{ student.class || '-' }}
                </span>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  {{ student.stream || '-' }}
                </span>
              </td>
              <td v-for="subject in subjects" :key="subject.id" class="px-6 py-4">
                <div v-if="student.subjects[subject.id]" class="text-center">
                  <div class="text-lg font-bold text-gray-900">{{ student.subjects[subject.id].marks }}</div>
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold mt-1" :class="getGradeClass(student.subjects[subject.id].grade)">
                    {{ student.subjects[subject.id].grade }}
                  </span>
                </div>
                <div v-else class="text-center text-gray-400">
                  <svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex rounded-full px-3 py-1 text-sm font-bold" :class="getPredictionClass(student)">
                  {{ getPrediction(student) }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center justify-end gap-2">
                  <button @click="viewStudent(student)" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg text-xs font-medium transition-colors">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View
                  </button>
                  <button @click="editStudent(student)" class="inline-flex items-center px-3 py-1.5 border border-green-600 text-green-600 bg-green-50 hover:bg-green-100 rounded-lg text-xs font-medium transition-colors">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                  </button>
                  <button @click="deleteStudent(student)" class="inline-flex items-center px-3 py-1.5 border border-red-600 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg text-xs font-medium transition-colors">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="border-b border-slate-200 px-6 py-4">
          <h3 class="text-xl font-bold text-slate-900">{{ showEditModal ? 'Edit Student Results' : 'Add Student Results' }}</h3>
        </div>
        <div class="overflow-y-auto px-6 py-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Student *</label>
              <select v-model="formData.student_id" required class="input">
                <option value="">Select Student</option>
                <option v-for="student in students" :key="student.id" :value="student.id">
                  {{ student.full_name }} ({{ student.admission_number }})
                </option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Class</label>
              <select v-model="formData.class" class="input">
                <option value="">Select Class</option>
                <option v-for="classItem in classes" :key="classItem" :value="classItem">
                  {{ classItem }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Stream</label>
              <select v-model="formData.stream" class="input">
                <option value="">Select Stream</option>
                <option v-for="stream in streamOptions" :key="stream" :value="stream">
                  {{ stream }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Year *</label>
              <input v-model="formData.year" type="number" required min="2020" max="2030" class="input">
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Term *</label>
              <select v-model="formData.term" required class="input">
                <option value="">Select Term</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-medium text-slate-700">Exam Type *</label>
              <select v-model="formData.exam_type" required class="input">
                <option value="">Select Exam Type</option>
                <option value="BOT1">BOT 1</option>
                <option value="EOT1">EOT 1</option>
                <option value="BOT2">BOT 2</option>
                <option value="EOT2">EOT 2</option>
                <option value="BOT3">BOT 3</option>
                <option value="EOT3">EOT 3</option>
                <option value="FINAL">FINAL</option>
              </select>
            </div>
          </div>
          
          <div class="mt-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">Subject Marks</h4>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
              <div v-for="subject in subjects" :key="subject.id" class="rounded-lg border border-slate-200 p-4">
                <label class="mb-2 block text-sm font-medium text-slate-700">{{ subject.subject_name }}</label>
                <input 
                  v-model.number="formData.subject_marks[subject.id]" 
                  type="number" 
                  min="0" 
                  max="100" 
                  placeholder="Enter marks (0-100)"
                  class="input"
                >
              </div>
            </div>
          </div>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
          <div class="flex justify-end gap-3">
            <button @click="closeModal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
              Cancel
            </button>
            <button @click="saveResult" :disabled="saving" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save Results' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Student Detail Modal -->
    <div v-if="showDetailModal && selectedStudent" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
      <div class="flex max-h-[90vh] w-full max-w-6xl flex-col rounded-2xl bg-white shadow-2xl">
        <div class="border-b border-slate-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-xl font-bold text-slate-900">Student Details</h3>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="overflow-y-auto px-6 py-6">
          <!-- Student Info -->
          <div class="mb-6 rounded-2xl bg-gradient-to-r from-blue-50 to-indigo-50 p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <p class="text-sm text-slate-500">Student Name</p>
                <p class="text-lg font-semibold text-slate-900">{{ selectedStudent.student_name }}</p>
              </div>
              <div>
                <p class="text-sm text-slate-500">Class</p>
                <p class="text-lg font-semibold text-slate-900">{{ selectedStudent.class || '-' }}</p>
              </div>
              <div>
                <p class="text-sm text-slate-500">Stream</p>
                <p class="text-lg font-semibold text-slate-900">{{ selectedStudent.stream || '-' }}</p>
              </div>
            </div>
          </div>

          <!-- Performance Status -->
          <div class="mb-6 rounded-2xl border border-slate-200 p-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">Current Performance Status</h4>
            <div class="flex items-center gap-4">
              <span class="inline-flex rounded-full px-4 py-2 text-sm font-bold" :class="getPredictionClass(selectedStudent)">
                {{ getPrediction(selectedStudent) }}
              </span>
            </div>
          </div>

          <!-- Performance Graph -->
          <div class="mb-6 rounded-2xl border border-slate-200 p-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">
              Performance Over Years {{ isOLevel ? '(4 Years - O-Level)' : '(2 Years - A-Level)' }}
            </h4>
            <div v-if="performanceData.length > 0" class="h-64">
              <svg viewBox="0 0 400 200" class="h-full w-full">
                <!-- Grid lines -->
                <line v-for="i in 5" :key="i" x1="40" :y1="20 + (i - 1) * 40" x2="380" :y2="20 + (i - 1) * 40" stroke="#e2e8f0" stroke-width="1" />
                <line x1="40" y1="20" x2="40" y2="180" stroke="#94a3b8" stroke-width="2" />
                <line x1="40" y1="180" x2="380" y2="180" stroke="#94a3b8" stroke-width="2" />
                
                <!-- Y-axis labels -->
                <text x="30" y="25" text-anchor="end" font-size="10" fill="#64748b">100</text>
                <text x="30" y="65" text-anchor="end" font-size="10" fill="#64748b">75</text>
                <text x="30" y="105" text-anchor="end" font-size="10" fill="#64748b">50</text>
                <text x="30" y="145" text-anchor="end" font-size="10" fill="#64748b">25</text>
                <text x="30" y="185" text-anchor="end" font-size="10" fill="#64748b">0</text>
                
                <!-- X-axis labels and data points -->
                <template v-for="(data, index) in performanceData.slice(0, isOLevel ? 4 : 2)" :key="data.year">
                  <text :x="60 + index * 90" y="195" text-anchor="middle" font-size="10" fill="#64748b">{{ data.year }}</text>
                  <circle 
                    :cx="60 + index * 90" 
                    :cy="180 - (data.average / 100) * 160" 
                    r="4" 
                    fill="#3b82f6"
                  />
                  <text :x="60 + index * 90" :cy="175 - (data.average / 100) * 160" text-anchor="middle" font-size="10" font-weight="bold" fill="#3b82f6">
                    {{ data.average.toFixed(1) }}
                  </text>
                </template>
                
                <!-- Line connecting points -->
                <polyline 
                  v-if="performanceData.length > 1"
                  :points="performanceData.slice(0, isOLevel ? 4 : 2).map((data, index) => `${60 + index * 90},${180 - (data.average / 100) * 160}`).join(' ')"
                  fill="none"
                  stroke="#3b82f6"
                  stroke-width="2"
                />
              </svg>
            </div>
            <div v-else class="h-64 flex items-center justify-center bg-slate-50 rounded-xl">
              <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <p class="mt-2 text-sm text-slate-500">No performance data available</p>
              </div>
            </div>
          </div>

          <!-- Subject Details -->
          <div class="rounded-2xl border border-slate-200 p-6">
            <h4 class="mb-4 text-lg font-semibold text-slate-900">Subject Performance</h4>
            <div class="space-y-3">
              <div v-for="(subject, subjectId) in selectedStudent.subjects" :key="subjectId" class="flex items-center justify-between rounded-lg bg-slate-50 p-4">
                <div>
                  <p class="font-medium text-slate-900">{{ subject.subject_name }}</p>
                  <p class="text-sm text-slate-500">{{ subject.exam_type }}</p>
                </div>
                <div class="text-right">
                  <p class="text-lg font-semibold text-slate-900">{{ subject.marks }}</p>
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold" :class="getGradeClass(subject.grade)">
                    {{ subject.grade }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '../services/api.js';
import { classesAPI, subjectsNewAPI } from '../services/api';
import authStore from '../services/authStore.js';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const results = ref([]);
const students = ref([]);
const subjects = ref([]);
const allClasses = ref([]);
const streams = ref([]);
const years = ref([]);
const yearOptions = ref([]);

// Generate year options from 2020 to 2033
for (let year = 2020; year <= 2033; year++) {
  yearOptions.value.push(year.toString());
}
const pivotData = ref([]);

const filters = ref({
  student_id: '',
  class: '',
  stream: '',
  year: new Date().getFullYear().toString(),
  term: '',
  subject_id: ''
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDetailModal = ref(false);
const saving = ref(false);
const selectedStudent = ref(null);
const studentHistory = ref([]);

const formData = ref({
  student_id: '',
  class: '',
  stream: '',
  year: new Date().getFullYear(),
  term: '',
  exam_type: '',
  subject_marks: {} // Will hold { subject_id: marks }
});

const editingId = ref(null);

const classes = computed(() => {
  const uniqueClassNames = [...new Set(allClasses.value.map(c => c.class_name))];
  return uniqueClassNames.sort();
});

const streamOptions = computed(() => {
  const uniqueStreamNames = [...new Set(allClasses.value.map(c => c.stream_name))];
  return uniqueStreamNames.sort();
});

const loadStudents = async () => {
  try {
    const response = await api.get(`students.php?action=list&limit=1000`);
    students.value = response.data.data;
  } catch (error) {
    console.error('Error loading students:', error);
  }
};

const loadSubjects = async () => {
  try {
    const response = await subjectsNewAPI.getAll();
    subjects.value = response.data || [];
  } catch (error) {
    console.error('Error loading subjects:', error);
  }
};

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    allClasses.value = response.data || [];
    console.log('Classes loaded:', allClasses.value);
    console.log('Unique classes:', classes.value);
    console.log('Unique streams:', streamOptions.value);
  } catch (error) {
    console.error('Error loading classes:', error);
  }
};

const loadResults = async () => {
  try {
    const token = localStorage.getItem('token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};
    
    const params = new URLSearchParams();
    if (filters.value.student_id) params.append('student_id', filters.value.student_id);
    if (filters.value.class) params.append('class', filters.value.class);
    if (filters.value.stream) params.append('stream', filters.value.stream);
    if (filters.value.year) params.append('year', filters.value.year);
    if (filters.value.term) params.append('term', filters.value.term);
    if (filters.value.subject_id) params.append('subject_id', filters.value.subject_id);
    
    // Debug: Log the API call
    console.log('API Call URL:', `student-results.php?action=list&${params}`);
    console.log('Current filters:', filters.value);
    
    const response = await api.get(`student-results.php?action=list&${params}`, { headers });
    
    console.log('API Response:', response.data);
    results.value = response.data.data;
    transformToPivot();
  } catch (error) {
    console.error('Error loading results:', error);
    console.error('Error response:', error.response?.data);
    if (error.response?.status === 401) {
      alert('Authentication required. Please log in to access this feature.');
    }
  }
};

const transformToPivot = () => {
  // Group results by student
  const studentMap = new Map();
  
  results.value.forEach(result => {
    if (!studentMap.has(result.student_id)) {
      studentMap.set(result.student_id, {
        student_id: result.student_id,
        student_name: result.student_name,
        admission_number: result.admission_number,
        class: result.class,
        stream: result.stream,
        subjects: {}
      });
    }
    
    const student = studentMap.get(result.student_id);
    student.subjects[result.subject_id] = {
      subject_name: result.subject_name,
      marks: result.marks,
      grade: result.grade,
      exam_type: result.exam_type
    };
  });
  
  pivotData.value = Array.from(studentMap.values());
};

const clearFilters = () => {
  filters.value = {
    student_id: '',
    class: '',
    stream: '',
    year: new Date().getFullYear().toString(),
    term: '',
    subject_id: ''
  };
  loadResults();
};

const saveResult = async () => {
  // Debug: Check if token exists
  const token = localStorage.getItem('token');
  console.log('Token exists:', !!token);
  console.log('Token value:', token ? token.substring(0, 20) + '...' : 'null');
  
  saving.value = true;
  try {
    const config = {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    };
    
    // Prepare results for each subject with marks
    const resultsToSave = [];
    for (const [subjectId, marks] of Object.entries(formData.value.subject_marks)) {
      if (marks !== '' && marks !== null && marks !== undefined) {
        resultsToSave.push({
          student_id: formData.value.student_id,
          subject_id: parseInt(subjectId),
          class: formData.value.class || '',
          stream: formData.value.stream || '',
          year: formData.value.year,
          term: formData.value.term,
          exam_type: formData.value.exam_type,
          marks: marks
        });
      }
    }
    
    if (resultsToSave.length === 0) {
      alert('Please enter marks for at least one subject');
      return;
    }
    
    // Save all results at once
    const response = await api.post(`student-results.php?action=bulk-create`, { results: resultsToSave }, config);
    console.log('Bulk create response:', response);
    
    closeModal();
    loadResults();
  } catch (error) {
    console.error('Error saving results:', error);
    console.error('Error config:', error.config);
    console.error('Error response:', error.response);
    console.error('Error data:', error.response?.data);
    console.error('Error status:', error.response?.status);
    
    if (error.response?.status === 401) {
      alert('Authentication failed. The backend is rejecting the token. Check server logs for details.');
    } else {
      alert(error.response?.data?.error || error.response?.data?.message || error.message || 'Error saving results');
    }
  } finally {
    saving.value = false;
  }
};

const editResult = (result) => {
  editingId.value = result.id;
  formData.value = {
    student_id: result.student_id,
    subject_id: result.subject_id,
    class: result.class || '',
    stream: result.stream || '',
    year: result.year,
    term: result.term,
    exam_type: result.exam_type,
    marks: result.marks,
    remarks: result.remarks || ''
  };
  showEditModal.value = true;
};

const deleteResult = async (result) => {
  if (!confirm(`Are you sure you want to delete this result?`)) return;
  
  try {
    await api.delete(`student-results.php?action=delete&id=${result.id}`);
    loadResults();
  } catch (error) {
    console.error('Error deleting result:', error);
    alert('Error deleting result');
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  editingId.value = null;
  formData.value = {
    student_id: '',
    class: '',
    stream: '',
    year: new Date().getFullYear(),
    term: '',
    exam_type: '',
    subject_marks: {}
  };
};

const getGradeClass = (grade) => {
  const classes = {
    'A': 'bg-emerald-100 text-emerald-800',
    'B': 'bg-blue-100 text-blue-800',
    'C': 'bg-amber-100 text-amber-800',
    'D': 'bg-orange-100 text-orange-800',
    'E': 'bg-red-100 text-red-800'
  };
  return classes[grade] || 'bg-slate-100 text-slate-800';
};

const getPrediction = (student) => {
  const subjectValues = Object.values(student.subjects);
  if (subjectValues.length === 0) return '-';
  
  // Calculate average only for subjects with marks
  const subjectsWithMarks = subjectValues.filter(subject => 
    subject.marks !== '' && 
    subject.marks !== null && 
    subject.marks !== undefined && 
    !isNaN(subject.marks)
  );
  if (subjectsWithMarks.length === 0) return '-';
  
  const sumMarks = subjectsWithMarks.reduce((sum, subject) => sum + (parseFloat(subject.marks) || 0), 0);
  const averageMarks = sumMarks / subjectsWithMarks.length;
  
  // Determine status based on average
  if (averageMarks >= 80) return `Exceptional (${averageMarks.toFixed(2)}%)`;
  if (averageMarks >= 70) return `Outstanding (${averageMarks.toFixed(2)}%)`;
  if (averageMarks >= 60) return `Satisfactory (${averageMarks.toFixed(2)}%)`;
  if (averageMarks >= 50) return `Basic (${averageMarks.toFixed(2)}%)`;
  return `Elementary (${averageMarks.toFixed(2)}%)`;
};

const getPredictionClass = (student) => {
  const subjectValues = Object.values(student.subjects);
  if (subjectValues.length === 0) return 'bg-slate-100 text-slate-800';
  
  // Calculate average only for subjects with marks
  const subjectsWithMarks = subjectValues.filter(subject => 
    subject.marks !== '' && 
    subject.marks !== null && 
    subject.marks !== undefined && 
    !isNaN(subject.marks)
  );
  if (subjectsWithMarks.length === 0) return 'bg-slate-100 text-slate-800';
  
  const sumMarks = subjectsWithMarks.reduce((sum, subject) => sum + (parseFloat(subject.marks) || 0), 0);
  const averageMarks = sumMarks / subjectsWithMarks.length;
  
  if (averageMarks >= 80) return 'bg-emerald-100 text-emerald-800';
  if (averageMarks >= 70) return 'bg-blue-100 text-blue-800';
  if (averageMarks >= 60) return 'bg-amber-100 text-amber-800';
  if (averageMarks >= 50) return 'bg-orange-100 text-orange-800';
  return 'bg-red-100 text-red-800';
};

const editStudent = (student) => {
  // Populate form with student's existing results
  formData.value = {
    student_id: student.student_id,
    class: student.class || '',
    stream: student.stream || '',
    year: new Date().getFullYear(),
    term: '1',
    exam_type: 'BOT1',
    subject_marks: {}
  };
  
  // Populate existing marks for each subject
  Object.keys(student.subjects).forEach(subjectId => {
    formData.value.subject_marks[subjectId] = student.subjects[subjectId].marks;
  });
  
  editingId.value = null;
  showEditModal.value = true;
};

const viewStudent = (student) => {
  console.log('viewStudent called', student);
  selectedStudent.value = student;
  // Use current student data from pivotData for the graph
  studentHistory.value = [];
  showDetailModal.value = true;
  console.log('Modal state:', showDetailModal.value);
};

const isOLevel = computed(() => {
  if (!selectedStudent.value) return false;
  const classLevel = selectedStudent.value.class?.toLowerCase() || '';
  return classLevel.includes('s1') || classLevel.includes('s2') || classLevel.includes('s3') || classLevel.includes('s4');
});

const performanceData = computed(() => {
  if (!selectedStudent.value) return [];
  
  // Use current student's subjects data for the graph
  const subjects = Object.values(selectedStudent.value.subjects || {});
  if (subjects.length === 0) return [];
  
  // Calculate average from current subjects
  const marks = subjects.map(s => s.marks).filter(m => m !== null && m !== undefined && !isNaN(m));
  if (marks.length === 0) return [];
  
  const average = marks.reduce((sum, m) => sum + m, 0) / marks.length;
  const currentYear = new Date().getFullYear();
  
  // Return data for current year (can be extended with historical data)
  return [{ year: currentYear, average }];
});

const deleteStudent = async (student) => {
  if (!confirm(`Are you sure you want to delete all results for ${student.student_name}?`)) {
    return;
  }
  
  try {
    const token = localStorage.getItem('token');
    const headers = token ? { 'Authorization': `Bearer ${token}` } : {};
    
    // Delete all results for this student
    const subjectIds = Object.keys(student.subjects);
    for (const subjectId of subjectIds) {
      const result = results.value.find(r => r.student_id === student.student_id && r.subject_id === parseInt(subjectId));
      if (result) {
        await api.delete(`student-results.php?action=delete&id=${result.id}`, { headers });
      }
    }
    
    loadResults();
    alert('Student results deleted successfully');
  } catch (error) {
    console.error('Error deleting student results:', error);
    alert(error.response?.data?.error || error.message || 'Error deleting student results');
  }
};

const downloadPDF = () => {
  const doc = new jsPDF();
  
  // Add title
  doc.setFontSize(18);
  doc.text('Student Academic Track Report', 14, 22);
  
  // Add subtitle with filters
  doc.setFontSize(11);
  doc.setTextColor(100);
  const filterInfo = [
    filters.value.student_id ? `Student: ${students.value.find(s => s.id === filters.value.student_id)?.full_name}` : '',
    filters.value.year ? `Year: ${filters.value.year}` : '',
    filters.value.term ? `Term: ${filters.value.term}` : '',
    filters.value.subject_id ? `Subject: ${subjects.value.find(s => s.id === filters.value.subject_id)?.subject_name}` : ''
  ].filter(Boolean).join(' | ');
  doc.text(filterInfo || 'All Results', 14, 30);
  
  // Add date
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);
  
  // Prepare table data
  const tableData = results.value.map(result => [
    `${result.student_name} (${result.admission_number})`,
    result.subject_name,
    result.year,
    `Term ${result.term}`,
    result.exam_type,
    result.marks,
    result.grade
  ]);
  
  // Add table
  autoTable(doc, {
    head: [['Student', 'Subject', 'Year', 'Term', 'Exam', 'Marks', 'Grade']],
    body: tableData,
    startY: 45,
    styles: {
      fontSize: 9,
      cellPadding: 3,
    },
    headStyles: {
      fillColor: [59, 130, 246],
      textColor: 255,
      fontStyle: 'bold',
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245],
    },
    margin: { top: 10, right: 10, bottom: 10, left: 10 },
  });
  
  // Add footer
  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(8);
    doc.setTextColor(150);
    doc.text(
      `Page ${i} of ${pageCount}`,
      doc.internal.pageSize.width / 2,
      doc.internal.pageSize.height - 10,
      { align: 'center' }
    );
  }
  
  // Save the PDF
  doc.save(`student-academic-track-${new Date().toISOString().split('T')[0]}.pdf`);
};

onMounted(() => {
  // Debug: Check all localStorage items
  console.log('All localStorage keys:', Object.keys(localStorage));
  console.log('Token from localStorage:', localStorage.getItem('token'));
  console.log('User from localStorage:', localStorage.getItem('user'));
  console.log('authStore token:', authStore.token);
  console.log('authStore user:', authStore.user);
  console.log('authStore isAuthenticated:', authStore.isAuthenticated());
  
  loadStudents();
  loadSubjects();
  loadClasses();
  loadResults();
});
</script>

<style scoped>
.input {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.75rem;
  font-size: 0.875rem;
  transition: all 0.2s;
  background-color: white;
}

.input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>

