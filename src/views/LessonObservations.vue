<template>
  <div class="p-6 bg-gradient-to-br from-slate-50 via-blue-50 to-blue-100 min-h-screen">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-4xl font-bold text-gray-800 flex items-center gap-3">
            <div class="bg-gradient-to-br from-blue-600 to-blue-700 text-white p-3 rounded-xl shadow-lg">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
              </svg>
            </div>
            Lesson Observations
          </h1>
          <p class="text-gray-600 mt-2 text-lg">Track and evaluate teacher performance across multiple rounds</p>
        </div>
        <button
          @click="openAddModal"
          class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-3 rounded-xl hover:from-blue-700 hover:to-blue-800 flex items-center gap-2 shadow-lg transition-all duration-200 hover:shadow-xl hover:scale-105"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          New Observation
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 border border-gray-100">
      <div class="flex items-center gap-3 mb-4">
        <div class="bg-blue-100 p-2 rounded-xl">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-800">Filters</h2>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
          <div class="relative">
            <input
              v-model="teacherSearch"
              type="text"
              placeholder="Search teacher..."
              class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 pr-10 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
            >
            <svg class="w-5 h-5 text-gray-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
          <select v-model="filters.teacher_id" @change="loadObservations" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 mt-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
            <option value="">All Teachers</option>
            <option v-for="teacher in filteredTeachers" :key="teacher.id" :value="teacher.id">{{ teacher.full_name }} ({{ teacher.teacher_code }})</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
          <select v-model="filters.subject_id" @change="loadObservations" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
            <option value="">All Subjects</option>
            <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
          <select v-model="filters.year" @change="loadObservations" class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
            <option value="">All Years</option>
            <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
          <input
            v-model="filters.search"
            @input="searchObservations"
            type="text"
            placeholder="Search..."
            class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
          >
        </div>
      </div>
    </div>

    <!-- Observations List -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
      <div class="overflow-x-auto">
        <div v-if="loading" class="p-12 text-center text-gray-600">
          <div class="inline-flex items-center gap-3">
            <svg class="animate-spin w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-lg font-medium">Loading observations...</span>
          </div>
        </div>

        <div v-else-if="observations.length === 0" class="p-12 text-center text-gray-500">
          <div class="inline-flex flex-col items-center gap-4">
            <div class="bg-blue-50 p-6 rounded-full">
              <svg class="w-16 h-16 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
              </svg>
            </div>
            <span class="text-lg font-medium">No observations found.</span>
          </div>
        </div>

        <div v-else class="divide-y divide-gray-100">
          <div v-for="observation in observations" :key="observation.id" class="p-6 hover:bg-blue-50 transition-colors">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-4 mb-3">
                  <div class="bg-gradient-to-br from-blue-600 to-blue-700 text-white p-3 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-xl font-bold text-gray-900">{{ observation.teacher_name }}</h3>
                    <p class="text-gray-600">{{ observation.subject_name }} | {{ observation.class_name }} - {{ observation.stream || observation.stream_id || 'N/A' }}</p>
                  </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                  <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500 mb-1">Term</p>
                    <p class="font-semibold text-gray-900">{{ observation.term }}</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500 mb-1">Year</p>
                    <p class="font-semibold text-gray-900">{{ observation.year }}</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500 mb-1">Rounds</p>
                    <p class="font-semibold text-gray-900">{{ observation.rounds?.length || 0 }}</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs text-gray-500 mb-1">Date</p>
                    <p class="font-semibold text-gray-900">{{ formatDate(observation.created_at) }}</p>
                  </div>
                </div>
                <div v-if="observation.rounds && observation.rounds.length > 0" class="flex flex-wrap gap-2">
                  <span v-for="round in observation.rounds" :key="round.id" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium" :class="getCategoryClass(round.performance_category)">
                    {{ round.round }}: {{ round.performance_category }} ({{ round.total_score }})
                  </span>
                </div>
              </div>
              <div class="flex items-center gap-2 ml-4">
                <button @click="addRoundForTeacher(observation)" class="inline-flex items-center justify-center w-10 h-10 border-2 border-purple-600 text-purple-600 bg-purple-50 hover:bg-purple-100 rounded-xl transition-colors" title="Add new round">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                  </svg>
                </button>
                <button @click="viewObservation(observation)" class="inline-flex items-center px-4 py-2 border-2 border-blue-600 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-xl text-sm font-medium transition-colors">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                  View
                </button>
                <button @click="editObservation(observation)" class="inline-flex items-center px-4 py-2 border-2 border-green-600 text-green-600 bg-green-50 hover:bg-green-100 rounded-xl text-sm font-medium transition-colors">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                  </svg>
                  Edit
                </button>
                <button @click="deleteObservation(observation)" class="inline-flex items-center px-4 py-2 border-2 border-red-600 text-red-600 bg-red-50 hover:bg-red-100 rounded-xl text-sm font-medium transition-colors">
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
          <button
            @click="prevPage"
            :disabled="pagination.page === 1"
            class="px-4 py-2 bg-white border-2 border-gray-300 rounded-xl hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Previous
          </button>

          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-600">Page</span>
            <span class="px-3 py-1 bg-blue-600 text-white rounded-lg font-semibold text-sm">{{ pagination.page }}</span>
            <span class="text-sm text-gray-600">of {{ pagination.totalPages }}</span>
          </div>

          <button
            @click="nextPage"
            :disabled="pagination.page === pagination.totalPages"
            class="px-4 py-2 bg-white border-2 border-gray-300 rounded-xl hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200"
          >
            Next
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 rounded-t-2xl sticky top-0 z-10">
          <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-white">{{ showEditModal ? 'Edit Observation' : 'New Observation' }}</h2>
            <button @click="closeModal" class="text-white hover:text-blue-200 transition-colors bg-white/10 hover:bg-white/20 rounded-full p-2">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>

        <form @submit.prevent="saveObservation" class="p-6 space-y-6">
          <!-- Core Fields -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Teacher *</label>
              <select v-model="formData.teacher_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                <option value="">Select Teacher</option>
                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.full_name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
              <select v-model="formData.subject_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                <option value="">Select Subject</option>
                <option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
              <select v-model="formData.class_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                <option value="">Select Class</option>
                <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stream *</label>
              <select v-model="formData.stream_id" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                <option value="">Select Stream</option>
                <option v-for="stream in streams" :key="stream.id" :value="stream.id">{{ stream.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Term *</label>
              <select v-model="formData.term" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                <option value="">Select Term</option>
                <option value="Term 1">Term 1</option>
                <option value="Term 2">Term 2</option>
                <option value="Term 3">Term 3</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Year *</label>
              <select v-model="formData.year" required class="w-full border-2 border-gray-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all">
                <option value="">Select Year</option>
                <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
              </select>
            </div>
          </div>

          <!-- Rounds Section -->
          <div class="space-y-6">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <div class="bg-blue-100 p-2 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
              </div>
              Observation Rounds
            </h3>

            <!-- Round Cards -->
            <div v-for="(round, index) in formData.rounds" :key="index" class="bg-gradient-to-br from-slate-50 to-blue-50 rounded-2xl p-6 border-2 border-gray-200 shadow-lg">
              <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                  <span class="bg-blue-600 text-white px-4 py-1 rounded-lg">{{ round.round }}</span>
                </h4>
                <button type="button" @click="removeRound(index)" class="text-red-600 hover:text-red-800 transition-colors">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>

              <!-- Total Score Section -->
              <div class="bg-white rounded-xl p-5 mb-4 shadow-sm border border-gray-200">
                <h5 class="text-md font-bold text-gray-800 mb-3 flex items-center gap-2">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                  Total Score
                </h5>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Score (0-100) *</label>
                    <input
                      v-model.number="round.total_score"
                      type="number"
                      min="0"
                      max="100"
                      @input="updateRoundCalculations(index)"
                      required
                      class="w-full border-2 border-blue-300 rounded-xl px-4 py-3 text-2xl font-bold text-center focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all"
                    >
                    <p v-if="round.scoreError" class="text-red-600 text-sm mt-1">{{ round.scoreError }}</p>
                  </div>
                  <div class="flex items-center justify-center">
                    <div class="text-center">
                      <p class="text-sm text-gray-600 mb-1">Calculated Rating</p>
                      <p class="text-4xl font-bold text-blue-600">{{ getRoundRating(round.total_score).toFixed(1) }}</p>
                    </div>
                  </div>
                  <div class="flex items-center justify-center">
                    <span class="inline-flex items-center px-6 py-3 rounded-full text-lg font-bold" :class="getCategoryClass(getRoundCategory(round.total_score))">
                      {{ getRoundCategory(round.total_score) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Auto-Generated Sections -->
              <div class="space-y-3 mb-4">
                <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4">
                  <h5 class="text-md font-bold text-green-800 mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Strengths Observed
                  </h5>
                  <p class="text-gray-700 leading-relaxed text-sm">{{ getRoundStrengths(round.total_score) }}</p>
                </div>

                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                  <h5 class="text-md font-bold text-blue-800 mb-2 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    General Comment
                  </h5>
                  <p class="text-gray-700 leading-relaxed text-sm">{{ getRoundGeneralComment(round.total_score) }}</p>
                </div>
              </div>

              <!-- Manual Section -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Areas for Improvement</label>
                <textarea
                  v-model="round.areas_for_improvement"
                  rows="3"
                  placeholder="Enter areas that require improvement such as learner engagement, lesson planning, assessment methods, classroom management, or use of instructional materials..."
                  class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition-all text-sm"
                ></textarea>
              </div>
            </div>

            <!-- Add Round Button -->
            <button
              type="button"
              @click="addRound"
              class="w-full py-3 border-2 border-dashed border-blue-300 rounded-xl text-blue-600 hover:bg-blue-50 hover:border-blue-400 transition-all flex items-center justify-center gap-2 font-medium"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
              </svg>
              Add Round
            </button>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <button type="button" @click="closeModal" class="px-6 py-3 border-2 border-gray-300 rounded-xl hover:bg-gray-50 font-medium transition-all">
              Cancel
            </button>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 font-medium shadow-lg transition-all">
              Save Observation
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- View Modal -->
    <div v-if="showViewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 rounded-t-2xl sticky top-0 z-10">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="text-2xl font-bold text-white mb-2">Observation Details</h2>
              <p class="text-blue-100 text-sm">View complete observation information</p>
            </div>
            <div class="flex gap-2">
              <button @click="printObservation" class="text-white hover:text-blue-200 transition-colors bg-white/10 hover:bg-white/20 rounded-full p-2" title="Print">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
              </button>
              <button @click="showViewModal = false" class="text-white hover:text-blue-200 transition-colors bg-white/10 hover:bg-white/20 rounded-full p-2" title="Close">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div v-if="selectedRecord" class="p-6 space-y-6">
          <!-- Teacher Info -->
          <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl p-6 border-2 border-blue-100">
            <h3 class="text-2xl font-bold text-gray-800 mb-2">{{ selectedRecord.teacher_name }}</h3>
            <p class="text-gray-600">Complete Observation History</p>
          </div>

          <!-- Summary Cards -->
          <div v-if="selectedRecord.summary" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow-lg">
              <p class="text-blue-100 text-xs mb-1">Total Observations</p>
              <p class="text-2xl font-bold">{{ selectedRecord.summary.total_observations }}</p>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 text-white shadow-lg">
              <p class="text-green-100 text-xs mb-1">Average Score</p>
              <p class="text-2xl font-bold">{{ selectedRecord.summary.average_score }}</p>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white shadow-lg">
              <p class="text-purple-100 text-xs mb-1">Average Rating</p>
              <p class="text-2xl font-bold">{{ selectedRecord.summary.average_rating }}</p>
            </div>
            <div class="bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl p-4 text-white shadow-lg">
              <p class="text-teal-100 text-xs mb-1">Highest Score</p>
              <p class="text-2xl font-bold">{{ selectedRecord.summary.highest_score }}</p>
            </div>
            <div class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-xl p-4 text-white shadow-lg">
              <p class="text-rose-100 text-xs mb-1">Lowest Score</p>
              <p class="text-2xl font-bold">{{ selectedRecord.summary.lowest_score }}</p>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl p-4 text-white shadow-lg">
              <p class="text-amber-100 text-xs mb-1">Best Performance</p>
              <p class="text-sm font-bold">{{ selectedRecord.summary.best_performance_category }}</p>
            </div>
          </div>

          <!-- Observations Table -->
          <div v-if="selectedRecord.observations && selectedRecord.observations.length > 0" class="bg-white rounded-2xl shadow-lg border-2 border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-4">
              <h4 class="text-xl font-bold text-white">All Observations</h4>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Subject</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Class</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Stream</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Term</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Year</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Round</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Total Score</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Rating</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Category</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr v-for="observation in selectedRecord.observations" :key="observation.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ observation.subject }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ observation.class }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ observation.stream || 'N/A' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ observation.term }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ observation.year }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ observation.round }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900 font-bold">{{ observation.total_score }}/100</td>
                    <td class="px-4 py-3 text-sm text-gray-900 font-bold">{{ observation.calculated_rating }}</td>
                    <td class="px-4 py-3">
                      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold" :class="getCategoryClass(observation.performance_category)">
                        {{ observation.performance_category }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-else class="text-center py-8 text-gray-500">
            <p>No observations found for this teacher.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../services/api.js';

const observations = ref([]);
const teachers = ref([]);
const subjects = ref([]);
const classes = ref([]);
const streams = ref([]);
const loading = ref(false);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showViewModal = ref(false);
const editingId = ref(null);
const viewData = ref(null);
const teacherObservations = ref(null);
const selectedRecord = ref(null);

const filters = ref({
  teacher_id: '',
  subject_id: '',
  year: '',
  search: ''
});

const teacherSearch = ref('');

const filteredTeachers = computed(() => {
  if (!teacherSearch.value) return teachers.value;
  const search = teacherSearch.value.toLowerCase();
  return teachers.value.filter(teacher =>
    teacher.full_name.toLowerCase().includes(search) ||
    teacher.teacher_code.toLowerCase().includes(search)
  );
});

const pagination = ref({
  page: 1,
  limit: 10,
  total: 0,
  totalPages: 1
});

const formData = ref({
  teacher_id: '',
  subject_id: '',
  class_id: '',
  stream_id: '',
  term: '',
  year: '',
  rounds: []
});

const availableYears = computed(() => {
  const currentYear = new Date().getFullYear();
  return [currentYear, currentYear - 1, currentYear - 2];
});

const roundNames = ['Round 1', 'Round 2', 'Round 3', 'Round 4'];

const getCategoryClass = (category) => {
  const classes = {
    'Outstanding': 'bg-green-100 text-green-800',
    'Very Good': 'bg-blue-100 text-blue-800',
    'Good': 'bg-yellow-100 text-yellow-800',
    'Fair': 'bg-orange-100 text-orange-800',
    'Below Expectations': 'bg-red-100 text-red-800'
  };
  return classes[category] || 'bg-gray-100 text-gray-800';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getRoundRating = (score) => {
  if (score === null || score === '' || isNaN(score)) {
    return 0;
  }
  return score / 25;
};

const getRoundCategory = (score) => {
  const rating = getRoundRating(score);
  if (rating >= 3.5) return 'Outstanding';
  if (rating >= 3.0) return 'Very Good';
  if (rating >= 2.5) return 'Good';
  if (rating >= 2.0) return 'Fair';
  return 'Below Expectations';
};

const getRoundStrengths = (score) => {
  const category = getRoundCategory(score);
  const comments = {
    'Outstanding': 'The teacher demonstrated excellent lesson delivery with exceptional learner engagement throughout the session. There was clear confidence in subject matter mastery, effective classroom management, and creative use of teaching aids. The teacher maintained excellent rapport with students, encouraged active participation, and demonstrated a deep understanding of effective pedagogical strategies. The lesson was well-structured with smooth transitions and appropriate pacing.',
    'Very Good': 'The teacher exhibited strong teaching methods with good preparation and positive learner interaction. Communication was clear and effective, with proper time management throughout the lesson. Students were engaged and participated actively in learning activities. The teacher demonstrated good subject knowledge and used appropriate teaching strategies to facilitate learning.',
    'Good': 'The teacher delivered a satisfactory lesson with acceptable classroom control and moderate learner participation. Some effective teaching strategies were employed, though there is room for enhancement in certain areas. The lesson objectives were generally met, and students showed reasonable engagement with the material.',
    'Fair': 'The lesson showed limited learner engagement and would benefit from improved planning. Classroom interaction was inconsistent at times, and lesson pacing could be adjusted to better meet student needs. The teacher should focus on developing more engaging activities and improving time management to enhance overall lesson effectiveness.',
    'Below Expectations': 'The lesson delivery was weak with poor classroom engagement and limited teaching effectiveness. There appeared to be a lack of preparation, and the teacher would benefit from additional support and professional development. Significant improvements are needed in lesson planning, classroom management, and instructional delivery to meet expected teaching standards.'
  };
  return comments[category] || '';
};

const getRoundGeneralComment = (score) => {
  const category = getRoundCategory(score);
  const comments = {
    'Outstanding': `This was an exceptional observation with a score of ${score}/100. The teacher's performance was outstanding, demonstrating excellence in all areas of teaching and learning. This level of performance should be maintained and recognized as a model for other teachers. Continue to inspire and motivate students with your innovative teaching approaches.`,
    'Very Good': `This was a very good observation with a score of ${score}/100. The teacher demonstrated strong teaching skills and effective classroom practices. With minor refinements, this performance could reach outstanding levels. Keep up the excellent work and continue to develop your teaching strategies.`,
    'Good': `This was a satisfactory observation with a score of ${score}/100. The teacher met the expected standards with room for growth in specific areas. Focus on enhancing learner engagement and refining teaching techniques to elevate performance to the next level.`,
    'Fair': `This observation with a score of ${score}/100 indicates areas that need attention. The teacher should focus on improving lesson planning, classroom management, and student engagement. Additional support and mentoring may be beneficial to help develop teaching skills.`,
    'Below Expectations': `This observation with a score of ${score}/100 falls below expected standards. Immediate attention is needed to address the identified weaknesses. The teacher should work closely with mentors and participate in professional development to improve teaching effectiveness.`
  };
  return comments[category] || '';
};

const addRound = () => {
  const usedRounds = formData.value.rounds.map(r => r.round);
  const nextRound = roundNames.find(r => !usedRounds.includes(r));
  if (nextRound) {
    formData.value.rounds.push({
      round: nextRound,
      total_score: 0,
      calculated_rating: 0,
      performance_category: '',
      strengths_observed: '',
      general_comment: '',
      areas_for_improvement: '',
      scoreError: ''
    });
  }
};

const removeRound = (index) => {
  formData.value.rounds.splice(index, 1);
};

const updateRoundCalculations = (index) => {
  const round = formData.value.rounds[index];
  const score = round.total_score;
  
  if (score < 0 || score > 100) {
    round.scoreError = 'Score must be between 0 and 100';
    return;
  }
  round.scoreError = '';
  
  round.calculated_rating = getRoundRating(score);
  round.performance_category = getRoundCategory(score);
  round.strengths_observed = getRoundStrengths(score);
  round.general_comment = getRoundGeneralComment(score);
};

const loadTeachers = async () => {
  try {
    const response = await api.get('teachers.php?limit=1000');
    if (response.data.success) {
      teachers.value = response.data.teachers || [];
    }
  } catch (error) {
    console.error('Error loading teachers:', error);
  }
};

const loadSubjects = async () => {
  try {
    const response = await api.get('subjects.php');
    if (response.data.success) {
      subjects.value = response.data.subjects || [];
    }
  } catch (error) {
    console.error('Error loading subjects:', error);
  }
};

const loadClasses = async () => {
  try {
    const response = await api.get('classes.php');
    if (response.data.success) {
      classes.value = response.data.classes || [];
    }
  } catch (error) {
    console.error('Error loading classes:', error);
  }
};

const loadStreams = async () => {
  try {
    const response = await api.get('streams.php');
    if (response.data.success) {
      streams.value = response.data.streams || [];
    }
  } catch (error) {
    console.error('Error loading streams:', error);
  }
};

const loadObservations = async () => {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.page,
      limit: pagination.value.limit,
      teacher_id: filters.value.teacher_id,
      subject_id: filters.value.subject_id,
      year: filters.value.year
    };
    const response = await api.get('lesson_observations.php?action=list', { params });
    console.log('Observations API response:', response.data);
    if (response.data.success) {
      observations.value = response.data.observations || [];
      console.log('First observation:', observations.value[0]);
      console.log('First observation stream_name:', observations.value[0]?.stream_name);
      console.log('First observation stream_id:', observations.value[0]?.stream_id);
      pagination.value.total = response.data.pagination.total;
      pagination.value.totalPages = response.data.pagination.totalPages;
    }
  } catch (error) {
    console.error('Error loading observations:', error);
  } finally {
    loading.value = false;
  }
};

const searchObservations = () => {
  pagination.value.page = 1;
  loadObservations();
};

const openAddModal = () => {
  editingId.value = null;
  formData.value = {
    teacher_id: '',
    subject_id: '',
    class_id: '',
    stream_id: '',
    term: '',
    year: new Date().getFullYear(),
    rounds: []
  };
  showAddModal.value = true;
  showEditModal.value = false;
};

const addRoundForTeacher = (observation) => {
  editingId.value = null;
  formData.value = {
    teacher_id: observation.teacher_id,
    subject_id: observation.subject_id,
    class_id: observation.class_id,
    stream_id: observation.stream_id,
    term: observation.term,
    year: observation.year,
    rounds: []
  };
  // Add an initial round
  const usedRounds = observation.rounds ? observation.rounds.map(r => r.round) : [];
  const nextRound = roundNames.find(r => !usedRounds.includes(r));
  if (nextRound) {
    formData.value.rounds.push({
      round: nextRound,
      total_score: 0,
      calculated_rating: 0,
      performance_category: '',
      strengths_observed: '',
      general_comment: '',
      areas_for_improvement: '',
      scoreError: ''
    });
  }
  showAddModal.value = true;
  showEditModal.value = false;
};

const editObservation = async (observation) => {
  try {
    const response = await api.get(`lesson_observations.php?action=view&id=${observation.id}`);
    if (response.data.success) {
      const data = response.data.observation;
      editingId.value = observation.id;
      formData.value = {
        teacher_id: data.teacher_id,
        subject_id: data.subject_id,
        class_id: data.class_id,
        stream_id: data.stream_id,
        term: data.term,
        year: data.year,
        rounds: data.rounds ? data.rounds.map(r => ({
          ...r,
          scoreError: ''
        })) : []
      };
      showAddModal.value = false;
      showEditModal.value = true;
    }
  } catch (error) {
    console.error('Error loading observation:', error);
  }
};

const viewObservation = async (observation) => {
  try {
    const response = await api.get(`lesson_observations.php?action=teacher-observations&teacher_id=${observation.teacher_id}`);
    if (response.data.success) {
      selectedRecord.value = response.data;
      teacherObservations.value = response.data;
      showViewModal.value = true;
    }
  } catch (error) {
    console.error('Error loading teacher observations:', error);
  }
};

const saveObservation = async () => {
  try {
    const payload = new FormData();
    payload.append('teacher_id', formData.value.teacher_id);
    payload.append('subject_id', formData.value.subject_id);
    payload.append('class_id', formData.value.class_id);
    payload.append('stream_id', formData.value.stream_id);
    payload.append('term', formData.value.term);
    payload.append('year', formData.value.year);
    
    formData.value.rounds.forEach((round, index) => {
      payload.append(`rounds[${index}][round]`, round.round);
      payload.append(`rounds[${index}][total_score]`, round.total_score);
      payload.append(`rounds[${index}][calculated_rating]`, round.calculated_rating);
      payload.append(`rounds[${index}][performance_category]`, round.performance_category);
      payload.append(`rounds[${index}][strengths_observed]`, round.strengths_observed);
      payload.append(`rounds[${index}][general_comment]`, round.general_comment);
      payload.append(`rounds[${index}][areas_for_improvement]`, round.areas_for_improvement);
    });

    if (showEditModal.value && editingId.value) {
      payload.append('id', editingId.value);
      await api.post('lesson_observations.php?action=update', payload);
    } else {
      await api.post('lesson_observations.php?action=create', payload);
    }

    closeModal();
    loadObservations();
  } catch (error) {
    console.error('Error saving observation:', error);
  }
};

const deleteObservation = async (observation) => {
  if (!confirm('Are you sure you want to delete this observation?')) {
    return;
  }

  try {
    await api.delete(`lesson_observations.php?action=delete&id=${observation.id}`);
    loadObservations();
  } catch (error) {
    console.error('Error deleting observation:', error);
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  editingId.value = null;
};

const prevPage = () => {
  if (pagination.value.page > 1) {
    pagination.value.page--;
    loadObservations();
  }
};

const nextPage = () => {
  if (pagination.value.page < pagination.value.totalPages) {
    pagination.value.page++;
    loadObservations();
  }
};

const printObservation = () => {
  window.print();
};

onMounted(() => {
  loadTeachers();
  loadSubjects();
  loadClasses();
  loadStreams();
  loadObservations();
});
</script>
