<template>
  <div class="space-y-8">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-8 text-white shadow-2xl">
      <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>
      <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
      <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
         
          <h1 class="mt-3 text-4xl font-bold tracking-tight">Duty Performance Evaluation</h1>
         
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            @click="loadData"
            class="group inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-medium text-white backdrop-blur transition-all hover:bg-white/20 hover:border-white/30"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
          </button>
          <button
            @click="openAddModal"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-500 to-blue-500 px-5 py-2.5 text-sm font-medium text-white shadow-xl shadow-blue-500/25 transition-all duration-300 hover:shadow-2xl hover:shadow-blue-500/40 hover:scale-105"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Evaluation
            </span>
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 p-6 shadow-xl backdrop-blur-sm">
      <div class="grid gap-5 xl:grid-cols-5">
        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year</label>
          <select v-model="filterYear" @change="loadData" class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Term</label>
          <select v-model="filterTerm" @change="loadData" class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50">
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </div>
        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Week</label>
          <select v-model="filterWeek" @change="loadData" class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50">
            <option value="">All Weeks</option>
            <option v-for="w in 13" :key="w" :value="w">Week {{ w }}</option>
          </select>
        </div>
        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Search Teacher</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name..."
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
            @input="filterRecords"
          >
        </div>
        <div class="flex items-end">
          <div class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-50 to-blue-100 px-4 py-3">
            <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
            <span class="text-sm font-medium text-slate-600">
              <span class="font-bold text-blue-600">{{ filteredRecords.length }}</span> records found
            </span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="teacherOfTheWeek" class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-400 via-yellow-500 to-orange-500 p-8 shadow-2xl text-white">
      <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>
      <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/10 blur-3xl"></div>
      <div class="relative z-10 flex items-center gap-6">
        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-white/20 text-5xl backdrop-blur-sm shadow-2xl">🏆</div>
        <div class="flex-1">
          <h3 class="text-xl font-bold uppercase tracking-wider text-white/90">Teacher of the Week</h3>
          <p class="text-4xl font-bold mt-2">{{ teacherOfTheWeek.teacher_name }}</p>
          <div class="flex flex-wrap gap-3 mt-3">
            <span class="inline-flex items-center rounded-full bg-white/20 px-4 py-2 text-sm font-semibold backdrop-blur-sm">Score: {{ teacherOfTheWeek.total_score }}/100</span>
            <span class="inline-flex items-center rounded-full bg-white/20 px-4 py-2 text-sm font-semibold backdrop-blur-sm">{{ teacherOfTheWeek.percentage }}%</span>
            <span class="inline-flex items-center rounded-full bg-white/20 px-4 py-2 text-sm font-semibold backdrop-blur-sm">{{ teacherOfTheWeek.status }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 shadow-xl backdrop-blur-sm">
      <div class="border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-5">
        <h2 class="text-xl font-bold text-slate-900">Previous Evaluations</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-[1200px] w-full divide-y divide-slate-200/60">
          <thead class="bg-gradient-to-r from-slate-50 to-slate-100/50">
            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
              <th class="px-6 py-4">Rank</th>
              <th class="px-6 py-4">Teacher</th>
              <th class="px-6 py-4">Week</th>
              <th class="px-6 py-4">Total Score</th>
              <th class="px-6 py-4">Percentage</th>
              <th class="px-6 py-4">Status</th>
              <th class="px-6 py-4">Date</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100/60 bg-white">
            <tr v-if="loading">
              <td colspan="8" class="px-6 py-16 text-center text-slate-500">
                <div class="flex items-center justify-center gap-3">
                  <div class="h-6 w-6 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"></div>
                  <span class="text-sm font-medium">Loading...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="filteredRecords.length === 0">
              <td colspan="8" class="px-6 py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="rounded-full bg-slate-100 p-4">
                    <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-slate-500">No evaluation records found.</p>
                </div>
              </td>
            </tr>
            <tr
              v-for="(record, index) in filteredRecords"
              :key="record.id"
              class="transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50/30 hover:to-blue-100/30"
            >
              <td class="px-6 py-4">
                <span v-if="index === 0" class="text-3xl">🥇</span>
                <span v-else-if="index === 1" class="text-3xl">🥈</span>
                <span v-else-if="index === 2" class="text-3xl">🥉</span>
                <span v-else class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-slate-100 text-sm font-semibold text-slate-600">{{ index + 1 }}</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-sm font-bold text-white shadow-lg shadow-blue-500/30">
                    {{ record.teacher_name?.charAt(0) || '?' }}
                  </div>
                  <div class="font-semibold text-slate-900">{{ record.teacher_name }}</div>
                </div>
              </td>
              <td class="px-6 py-4 text-sm font-medium text-slate-700">Week {{ record.week_number }}</td>
              <td class="px-6 py-4 font-bold text-slate-900">{{ record.total_score }}/100</td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-bold shadow-sm"
                  :class="getStatusBadgeClass(record.percentage)"
                >
                  {{ record.percentage }}%
                </span>
              </td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-semibold shadow-sm"
                  :class="getStatusBadgeClass(record.percentage)"
                >
                  {{ record.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-slate-600">{{ formatDate(record.created_at) }}</td>
              <td class="px-6 py-4">
                <div class="flex justify-end gap-2">
                  <button
                    @click="viewRecord(record)"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-teal-50 px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-emerald-500/20"
                    title="View"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <button
                    @click="editRecord(record)"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-blue-500/20"
                    title="Edit"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    @click="printRecord(record)"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-blue-500/20"
                    title="Print"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                  </button>
                  <button
                    @click="deleteRecord(record.id)"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-rose-200 bg-gradient-to-r from-rose-50 to-pink-50 px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-rose-500/20"
                    title="Delete"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="relative w-full max-w-4xl rounded-3xl bg-white shadow-2xl flex flex-col max-h-[90vh] ring-1 ring-white/20">
        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-blue-500/5 to-blue-600/5 pointer-events-none"></div>
        <div class="relative border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-6 flex-shrink-0">
          <h3 class="text-xl font-bold text-slate-900">
            {{ editingId ? 'Edit Duty Evaluation' : 'Add Duty Evaluation' }}
          </h3>
          <p class="mt-1.5 text-sm text-slate-500">
            Select one option per category. Scores will be calculated automatically.
          </p>
        </div>
        <form @submit.prevent="saveRecord" class="relative flex flex-col flex-1 overflow-hidden">
          <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6">
            <!-- Basic Info -->
            <div class="grid gap-5 sm:grid-cols-4">
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Teacher *</label>
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
                <select v-model="form.teacher_id" required class="input">
                  <option value="">Select Teacher</option>
                  <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
                </select>
              </div>
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year</label>
                <input v-model.number="form.year" type="number" class="input">
              </div>
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Term</label>
                <select v-model="form.term" class="input">
                  <option :value="1">Term 1</option>
                  <option :value="2">Term 2</option>
                  <option :value="3">Term 3</option>
                </select>
              </div>
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Week</label>
                <input v-model.number="form.week_number" type="number" min="1" max="13" class="input">
              </div>
            </div>

            <!-- Performance Scoring Section -->
            <div class="border-t border-slate-200/60 pt-6">
              <h4 class="mb-5 font-bold text-slate-900 text-lg">Performance Scoring (Enter marks out of 20 for each category)</h4>
              <div class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-slate-50 to-blue-50 p-6 shadow-sm">
                <div class="overflow-x-auto">
                  <table class="w-full">
                    <thead>
                      <tr class="border-b-2 border-slate-200">
                        <th class="text-left py-3 px-4 font-bold text-slate-700 text-sm uppercase tracking-wider">Category</th>
                        <th class="text-center py-3 px-4 font-bold text-slate-700 text-sm uppercase tracking-wider w-32">Marks (0-20)</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                      <tr class="hover:bg-white/50 transition-colors">
                        <td class="py-4 px-4">
                          <div class="flex items-center gap-3">
                            <span class="text-2xl">⏰</span>
                            <div>
                              <div class="font-semibold text-slate-900">Time Management & Availability</div>
                              <div class="text-xs text-slate-500">IN SCHOOL</div>
                            </div>
                          </div>
                        </td>
                        <td class="py-4 px-4">
                          <input 
                            type="number" 
                            v-model.number="form.punctuality" 
                            min="0" 
                            max="20" 
                            @input="autoCalculate"
                            class="w-full text-center rounded-xl border-2 border-slate-300 bg-white px-4 py-3 font-bold text-slate-900 outline-none transition-all focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
                          >
                        </td>
                      </tr>
                      <tr class="hover:bg-white/50 transition-colors">
                        <td class="py-4 px-4">
                          <div class="flex items-center gap-3">
                            <span class="text-2xl">👁</span>
                            <div>
                              <div class="font-semibold text-slate-900">Meals Supervision</div>
                            </div>
                          </div>
                        </td>
                        <td class="py-4 px-4">
                          <input 
                            type="number" 
                            v-model.number="form.supervision" 
                            min="0" 
                            max="20" 
                            @input="autoCalculate"
                            class="w-full text-center rounded-xl border-2 border-slate-300 bg-white px-4 py-3 font-bold text-slate-900 outline-none transition-all focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
                          >
                        </td>
                      </tr>
                      <tr class="hover:bg-white/50 transition-colors">
                        <td class="py-4 px-4">
                          <div class="flex items-center gap-3">
                            <span class="text-2xl">🧹</span>
                            <div>
                              <div class="font-semibold text-slate-900">Compound Cleanliness</div>
                            </div>
                          </div>
                        </td>
                        <td class="py-4 px-4">
                          <input 
                            type="number" 
                            v-model.number="form.cleanliness" 
                            min="0" 
                            max="20" 
                            @input="autoCalculate"
                            class="w-full text-center rounded-xl border-2 border-slate-300 bg-white px-4 py-3 font-bold text-slate-900 outline-none transition-all focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
                          >
                        </td>
                      </tr>
                      <tr class="hover:bg-white/50 transition-colors">
                        <td class="py-4 px-4">
                          <div class="flex items-center gap-3">
                            <span class="text-2xl">📅</span>
                            <div>
                              <div class="font-semibold text-slate-900">Order & Sanity</div>
                              <div class="text-xs text-slate-500">IN SCHOOL</div>
                            </div>
                          </div>
                        </td>
                        <td class="py-4 px-4">
                          <input 
                            type="number" 
                            v-model.number="form.time_keeping" 
                            min="0" 
                            max="20" 
                            @input="autoCalculate"
                            class="w-full text-center rounded-xl border-2 border-slate-300 bg-white px-4 py-3 font-bold text-slate-900 outline-none transition-all focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
                          >
                        </td>
                      </tr>
                      <tr class="hover:bg-white/50 transition-colors">
                        <td class="py-4 px-4">
                          <div class="flex items-center gap-3">
                            <span class="text-2xl">🎯</span>
                            <div>
                              <div class="font-semibold text-slate-900">School Programs Preparation</div>
                              <div class="text-xs text-slate-500">Assembly, Fellowship, Mentorship & Other Programs</div>
                            </div>
                          </div>
                        </td>
                        <td class="py-4 px-4">
                          <input 
                            type="number" 
                            v-model.number="form.participation" 
                            min="0" 
                            max="20" 
                            @input="autoCalculate"
                            class="w-full text-center rounded-xl border-2 border-slate-300 bg-white px-4 py-3 font-bold text-slate-900 outline-none transition-all focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
                          >
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Summary Card -->
            <div class="rounded-3xl border-2 border-slate-200/60 bg-gradient-to-br from-blue-50 to-blue-100 p-6 shadow-sm">
              <h4 class="mb-5 font-bold text-slate-900 text-lg">Summary</h4>
              <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-white p-5 shadow-md">
                  <div class="text-sm text-slate-600 mb-1">Total Score</div>
                  <div class="text-3xl font-bold text-slate-900">{{ form.total_score }}<span class="text-lg text-slate-500">/100</span></div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-md">
                  <div class="text-sm text-slate-600 mb-1">Percentage</div>
                  <div class="text-3xl font-bold" :class="getStatusTextColor(form.percentage)">{{ form.percentage }}%</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-md">
                  <div class="text-sm text-slate-600 mb-1">Status</div>
                  <div class="text-lg font-semibold" :class="getStatusTextColor(form.percentage)">{{ form.status }}</div>
                </div>
                <div class="rounded-2xl bg-white p-5 shadow-md">
                  <div class="text-sm text-slate-600 mb-2">Progress</div>
                  <div class="w-full bg-slate-200 rounded-full h-3 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 ease-out"
                         :class="getProgressBarClass(form.percentage)"
                         :style="{ width: form.percentage + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Admin Comments -->
            <div>
              <label class="mb-2.5 block text-sm font-semibold text-slate-700">Admin Remarks</label>
              <textarea
                v-model="form.comment"
                rows="4"
                placeholder="Enter your comments about the teacher's performance..."
                class="input resize-none text-base"
              ></textarea>
            </div>

            <!-- Optional Fields -->
            <div class="grid gap-5 sm:grid-cols-2">
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Areas of Improvement <span class="text-slate-400 font-normal">(Optional)</span></label>
                <textarea
                  v-model="form.areas_of_improvement"
                  rows="3"
                  placeholder="Enter areas where the teacher needs improvement..."
                  class="input resize-none text-base"
                ></textarea>
              </div>
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">General Remarks <span class="text-slate-400 font-normal">(Optional)</span></label>
                <textarea
                  v-model="form.general_remarks"
                  rows="3"
                  placeholder="Enter any general remarks..."
                  class="input resize-none text-base"
                ></textarea>
              </div>
            </div>

            <div>
              <label class="mb-2.5 block text-sm font-semibold text-slate-700">Supervisor <span class="text-slate-400 font-normal">(Optional)</span></label>
              <input
                v-model="form.supervisor"
                type="text"
                placeholder="Enter supervisor name..."
                class="input"
              >
            </div>

            <div v-if="error" class="rounded-2xl border border-rose-200/60 bg-gradient-to-r from-rose-50 to-pink-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
              {{ error }}
            </div>
          </div>

          <div class="relative border-t border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-5 flex-shrink-0">
            <div class="flex items-center justify-end gap-3">
              <button
                type="button"
                @click="closeModal"
                class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:bg-slate-50 hover:shadow-md"
              >
                Cancel
              </button>
              <button
                type="submit"
                @click="saveRecord"
                :disabled="saving"
                class="rounded-2xl bg-gradient-to-r from-blue-700 to-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition-all duration-200 hover:shadow-xl hover:shadow-blue-500/40 hover:scale-105 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
              >
                {{ saving ? 'Saving...' : 'Save Evaluation' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- View Modal -->
    <div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
      <div class="relative w-full max-w-4xl rounded-3xl bg-white shadow-2xl flex flex-col max-h-[90vh] ring-1 ring-white/20">
        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-blue-500/5 to-blue-600/5 pointer-events-none"></div>
        <div class="relative border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-8 py-6 flex-shrink-0">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-2xl font-bold text-slate-900">Evaluation Details</h3>
              <p class="text-sm text-slate-500 mt-1">Teacher Performance Report</p>
            </div>
            <button @click="showViewModal = false" class="rounded-xl bg-slate-100 p-2 hover:bg-slate-200 transition-colors">
              <svg class="h-6 w-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto px-8 py-6">
          <div v-if="viewData" class="space-y-8">
            <!-- Header Info -->
            <div class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-blue-50 to-blue-100 p-6">
              <div class="grid gap-6 sm:grid-cols-4">
                <div class="text-center">
                  <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-2xl font-bold text-white shadow-lg mx-auto">
                    {{ viewData.teacher_name?.charAt(0) || '?' }}
                  </div>
                  <div class="font-bold text-slate-900 mt-3">{{ viewData.teacher_name }}</div>
                  <div class="text-sm text-slate-500">Teacher</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-blue-600">{{ viewData.total_score }}</div>
                  <div class="text-sm text-slate-500">Total Score</div>
                  <div class="text-xs text-slate-400">/100</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold" :class="getStatusTextColor(viewData.percentage)">{{ viewData.percentage }}%</div>
                  <div class="text-sm text-slate-500">Percentage</div>
                </div>
                <div class="text-center">
                  <div class="inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold shadow-sm" :class="getStatusBadgeClass(viewData.percentage)">
                    {{ getStatusFromPercentage(viewData.percentage) }}
                  </div>
                  <div class="text-sm text-slate-500 mt-2">Status</div>
                </div>
              </div>
            </div>

            <!-- Category Scores Table -->
            <div>
              <h4 class="text-lg font-bold text-slate-900 mb-4">Performance Breakdown</h4>
              <div class="rounded-2xl border border-slate-200/60 bg-white overflow-hidden shadow-sm">
                <table class="w-full">
                  <thead class="bg-gradient-to-r from-slate-50 to-blue-50">
                    <tr>
                      <th class="text-left py-4 px-6 font-bold text-slate-700 text-sm uppercase tracking-wider">Category</th>
                      <th class="text-center py-4 px-6 font-bold text-slate-700 text-sm uppercase tracking-wider w-32">Score</th>
                      <th class="text-center py-4 px-6 font-bold text-slate-700 text-sm uppercase tracking-wider w-32">Percentage</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200">
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-4 px-6">
                        <div class="flex items-center gap-3">
                          <span class="text-2xl">⏰</span>
                          <div>
                            <div class="font-semibold text-slate-900">Time Management & Availability</div>
                            <div class="text-xs text-slate-500">IN SCHOOL</div>
                          </div>
                        </div>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="inline-flex items-center rounded-xl bg-blue-100 px-4 py-2 font-bold text-blue-700">{{ viewData.punctuality }}/20</span>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="font-semibold text-slate-700">{{ ((viewData.punctuality / 20) * 100).toFixed(0) }}%</span>
                      </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-4 px-6">
                        <div class="flex items-center gap-3">
                          <span class="text-2xl">👁</span>
                          <div class="font-semibold text-slate-900">Meals Supervision</div>
                        </div>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="inline-flex items-center rounded-xl bg-blue-100 px-4 py-2 font-bold text-blue-700">{{ viewData.supervision }}/20</span>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="font-semibold text-slate-700">{{ ((viewData.supervision / 20) * 100).toFixed(0) }}%</span>
                      </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-4 px-6">
                        <div class="flex items-center gap-3">
                          <span class="text-2xl">🧹</span>
                          <div class="font-semibold text-slate-900">Compound Cleanliness</div>
                        </div>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="inline-flex items-center rounded-xl bg-blue-100 px-4 py-2 font-bold text-blue-700">{{ viewData.cleanliness }}/20</span>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="font-semibold text-slate-700">{{ ((viewData.cleanliness / 20) * 100).toFixed(0) }}%</span>
                      </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-4 px-6">
                        <div class="flex items-center gap-3">
                          <span class="text-2xl">📅</span>
                          <div>
                            <div class="font-semibold text-slate-900">Order & Sanity</div>
                            <div class="text-xs text-slate-500">IN SCHOOL</div>
                          </div>
                        </div>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="inline-flex items-center rounded-xl bg-blue-100 px-4 py-2 font-bold text-blue-700">{{ viewData.time_keeping }}/20</span>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="font-semibold text-slate-700">{{ ((viewData.time_keeping / 20) * 100).toFixed(0) }}%</span>
                      </td>
                    </tr>
                    <tr class="hover:bg-slate-50 transition-colors">
                      <td class="py-4 px-6">
                        <div class="flex items-center gap-3">
                          <span class="text-2xl">🎯</span>
                          <div>
                            <div class="font-semibold text-slate-900">School Programs Preparation</div>
                            <div class="text-xs text-slate-500">Assembly, Fellowship, Mentorship & Other Programs</div>
                          </div>
                        </div>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="inline-flex items-center rounded-xl bg-blue-100 px-4 py-2 font-bold text-blue-700">{{ viewData.participation }}/20</span>
                      </td>
                      <td class="py-4 px-6 text-center">
                        <span class="font-semibold text-slate-700">{{ ((viewData.participation / 20) * 100).toFixed(0) }}%</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Remarks Section -->
            <div class="grid gap-6 sm:grid-cols-2">
              <div v-if="viewData.comment" class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-slate-50 to-blue-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                  </svg>
                  Admin Remarks
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.comment }}</p>
              </div>
              <div v-if="viewData.areas_of_improvement" class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-slate-50 to-amber-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.5-5a5 5 0 00-5.072 0z" />
                  </svg>
                  Areas of Improvement
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.areas_of_improvement }}</p>
              </div>
              <div v-if="viewData.general_remarks" class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-slate-50 to-blue-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  General Remarks
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.general_remarks }}</p>
              </div>
              <div v-if="viewData.supervisor" class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-slate-50 to-blue-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  Supervisor
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.supervisor }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="relative border-t border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-8 py-5 flex-shrink-0">
          <div class="flex items-center justify-end gap-3">
            <button
              @click="printRecord(viewData)"
              class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:bg-slate-50 hover:shadow-md flex items-center gap-2"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              Print
            </button>
            <button
              @click="showViewModal = false"
              class="rounded-2xl bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition-all duration-200 hover:shadow-xl hover:shadow-blue-500/40 hover:scale-105"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { dutyPerformanceAPI, teachersAPI } from '../services/api.js';

const router = useRouter();
const records = ref([]);
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref(1);
const filterWeek = ref('');
const searchQuery = ref('');
const years = Array.from({length: 5}, (_, i) => new Date().getFullYear() - 2 + i);
const showModal = ref(false);
const showViewModal = ref(false);
const editingId = ref(null);
const saving = ref(false);
const loading = ref(false);
const error = ref('');
const viewData = ref(null);

const filteredTeachers = computed(() => {
  if (!teacherSearch.value) return teachersList.value;
  const search = teacherSearch.value.toLowerCase();
  return teachersList.value.filter(teacher =>
    teacher.full_name.toLowerCase().includes(search) ||
    teacher.teacher_code.toLowerCase().includes(search)
  );
});

const scoringOptions = [
  { label: 'Outstanding', value: 20, marks: '20' },
  { label: 'Very Good', value: 16, marks: '16' },
  { label: 'Satisfactory', value: 12, marks: '12' },
  { label: 'Needs Improvement', value: 7, marks: '7' }
];

const form = ref({
  teacher_id: '', year: new Date().getFullYear(), term: 1, week_number: 1,
  punctuality: 20, supervision: 20, cleanliness: 20, time_keeping: 20, participation: 20,
  total_score: 100, percentage: 100, status: 'Outstanding Performance', comment: '',
  areas_of_improvement: '', general_remarks: '', supervisor: ''
});

const filteredRecords = computed(() => {
  let filtered = [...records.value];
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(record =>
      record.teacher_name && record.teacher_name.toLowerCase().includes(query)
    );
  }
  
  return filtered.sort((a, b) => b.total_score - a.total_score);
});

const rankedRecords = computed(() => {
  return [...records.value].sort((a, b) => b.total_score - a.total_score);
});

const teacherOfTheWeek = computed(() => {
  if (rankedRecords.value.length === 0) return null;
  return rankedRecords.value[0];
});

const getStatusFromPercentage = (percentage) => {
  if (percentage >= 90) return 'Outstanding Performance';
  if (percentage >= 75) return 'Very Good Performance';
  if (percentage >= 50) return 'Satisfactory Performance';
  return 'Needs Improvement';
};

const getStatusBadgeClass = (percentage) => {
  if (percentage >= 90) return 'bg-emerald-100 text-emerald-800';
  if (percentage >= 75) return 'bg-blue-100 text-blue-800';
  if (percentage >= 50) return 'bg-amber-100 text-amber-800';
  return 'bg-rose-100 text-rose-800';
};

const getStatusTextColor = (percentage) => {
  if (percentage >= 90) return 'text-emerald-600';
  if (percentage >= 75) return 'text-blue-600';
  if (percentage >= 50) return 'text-amber-600';
  return 'text-rose-600';
};

const getProgressBarClass = (percentage) => {
  if (percentage >= 90) return 'bg-gradient-to-r from-emerald-500 to-emerald-600';
  if (percentage >= 75) return 'bg-gradient-to-r from-blue-500 to-blue-600';
  if (percentage >= 50) return 'bg-gradient-to-r from-amber-500 to-amber-600';
  return 'bg-gradient-to-r from-rose-500 to-rose-600';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const loadData = async () => {
  loading.value = true;
  try {
    const result = await dutyPerformanceAPI.getAll(filterYear.value, filterTerm.value, filterWeek.value);
    if (result.success) {
      const data = result.data.data || result.data;
      records.value = data.map(record => ({
        ...record,
        status: getStatusFromPercentage(record.percentage)
      }));
    }
  } catch (err) {
    console.error('Failed to load data:', err);
  }
  loading.value = false;
};

const loadTeachers = async () => {
  try {
    const result = await teachersAPI.getAll();
    if (result.success) teachersList.value = result.data.teachers || result.data;
  } catch (err) {
    console.error('Failed to load teachers:', err);
  }
};

const filterRecords = () => {
  // Filter is handled by computed property
};

const autoCalculate = () => {
  // Clamp values between 0 and 20
  form.value.punctuality = Math.min(20, Math.max(0, parseFloat(form.value.punctuality) || 0));
  form.value.supervision = Math.min(20, Math.max(0, parseFloat(form.value.supervision) || 0));
  form.value.cleanliness = Math.min(20, Math.max(0, parseFloat(form.value.cleanliness) || 0));
  form.value.time_keeping = Math.min(20, Math.max(0, parseFloat(form.value.time_keeping) || 0));
  form.value.participation = Math.min(20, Math.max(0, parseFloat(form.value.participation) || 0));
  
  const total = form.value.punctuality + 
                form.value.supervision + 
                form.value.cleanliness + 
                form.value.time_keeping + 
                form.value.participation;
  form.value.total_score = total;
  form.value.percentage = parseFloat(total.toFixed(2));
  form.value.status = getStatusFromPercentage(form.value.percentage);
};

const openAddModal = () => {
  editingId.value = null;
  form.value = {
    teacher_id: '', year: new Date().getFullYear(), term: 1, week_number: 1,
    punctuality: 20, supervision: 20, cleanliness: 20, time_keeping: 20, participation: 20,
    total_score: 100, percentage: 100, status: 'Outstanding Performance', comment: '',
    areas_of_improvement: '', general_remarks: '', supervisor: ''
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  error.value = '';
};

const editRecord = (record) => {
  editingId.value = record.id;
  form.value = { ...record };
  showModal.value = true;
};

const viewRecord = (record) => {
  router.push(`/duty-performance/${record.id}`);
};

const printRecord = (record) => {
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html>
    <head>
      <title>Duty Performance Evaluation - ${record.teacher_name}</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #1e40af; }
        .info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-item { padding: 15px; background: #f3f4f6; border-radius: 8px; }
        .info-item label { display: block; font-weight: bold; margin-bottom: 5px; color: #6b7280; }
        .scores { margin-bottom: 30px; }
        .scores h3 { margin-bottom: 15px; color: #1e40af; }
        .score-row { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .summary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        .summary h3 { margin: 0 0 20px 0; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 2em; font-weight: bold; }
        .comment { background: #f9fafb; padding: 20px; border-radius: 8px; border-left: 4px solid #1e40af; }
        @media print { body { padding: 20px; } }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>Teacher Weekly Duty Performance Evaluation</h1>
      </div>
      <div class="info">
        <div class="info-item">
          <label>Teacher</label>
          <div>${record.teacher_name}</div>
        </div>
        <div class="info-item">
          <label>Week</label>
          <div>Week ${record.week_number}</div>
        </div>
        <div class="info-item">
          <label>Year</label>
          <div>${record.year}</div>
        </div>
        <div class="info-item">
          <label>Term</label>
          <div>Term ${record.term}</div>
        </div>
      </div>
      <div class="summary">
        <h3>Performance Summary</h3>
        <div class="summary-grid">
          <div class="summary-item">
            <div class="value">${record.total_score}/100</div>
            <div>Total Score</div>
          </div>
          <div class="summary-item">
            <div class="value">${record.percentage}%</div>
            <div>Percentage</div>
          </div>
          <div class="summary-item">
            <div class="value">${getStatusFromPercentage(record.percentage)}</div>
            <div>Status</div>
          </div>
        </div>
      </div>
      <div class="scores">
        <h3>Category Scores</h3>
        <div class="score-row"><span>Time Management & Availability</span><span>${record.punctuality}/20</span></div>
        <div class="score-row"><span>Meals Supervision</span><span>${record.supervision}/20</span></div>
        <div class="score-row"><span>Compound Cleanliness</span><span>${record.cleanliness}/20</span></div>
        <div class="score-row"><span>Order & Sanity</span><span>${record.time_keeping}/20</span></div>
        <div class="score-row"><span>School Programs Preparation</span><span>${record.participation}/20</span></div>
      </div>
      ${record.comment ? `<div class="comment"><h3>Admin Remarks</h3><p>${record.comment}</p></div>` : ''}
      ${record.areas_of_improvement ? `<div class="comment"><h3>Areas of Improvement</h3><p>${record.areas_of_improvement}</p></div>` : ''}
      ${record.general_remarks ? `<div class="comment"><h3>General Remarks</h3><p>${record.general_remarks}</p></div>` : ''}
      ${record.supervisor ? `<div class="comment"><h3>Supervisor</h3><p>${record.supervisor}</p></div>` : ''}
      <script>window.print();<\/script>
    </body>
    </html>
  `);
  printWindow.document.close();
};

const saveRecord = async () => {
  if (!form.value.teacher_id) {
    error.value = 'Please select a teacher';
    return;
  }

  saving.value = true;
  error.value = '';
  
  try {
    let result;
    if (editingId.value) {
      result = await dutyPerformanceAPI.update(editingId.value, form.value);
    } else {
      result = await dutyPerformanceAPI.create(form.value);
    }
    
    if (result.success) {
      closeModal();
      loadData();
      alert(editingId.value ? 'Evaluation updated successfully!' : 'Evaluation added successfully!');
    } else {
      error.value = result.data?.message || 'Failed to save';
      alert('Failed to save: ' + error.value);
    }
  } catch (err) {
    console.error('Save error:', err);
    error.value = 'An error occurred while saving';
    alert('An error occurred while saving');
  }
  
  saving.value = false;
};

const deleteRecord = async (id) => {
  if (!confirm('Are you sure you want to delete this evaluation? This action cannot be undone.')) return;
  
  try {
    await dutyPerformanceAPI.delete(id);
    loadData();
    alert('Evaluation deleted successfully!');
  } catch (err) {
    console.error('Delete error:', err);
    alert('Failed to delete evaluation');
  }
};

watch(() => [form.value.punctuality, form.value.supervision, form.value.cleanliness, form.value.time_keeping, form.value.participation], () => {
  autoCalculate();
}, { deep: true });

onMounted(() => {
  loadData();
  loadTeachers();
});
</script>

<style scoped>
.input {
  width: 100%;
  border-radius: 1rem;
  border: 1.5px solid rgb(226 232 240);
  padding: 0.85rem 1rem;
  outline: none;
  transition: all 0.2s ease;
  background: white;
  font-weight: 500;
}

.input:focus {
  border-color: rgb(99 102 241);
  box-shadow: 0 0 0 4px rgb(238 242 255);
}

.input:hover:not(:focus) {
  border-color: rgb(203 213 225);
}

.input::placeholder {
  color: #94a3b8;
}

/* Animation for progress bar */
@keyframes progressAnimation {
  from {
    width: 0%;
  }
}

.animate-progress {
  animation: progressAnimation 0.5s ease-out;
}

/* Custom radio button styling */
input[type="radio"]:checked + div {
  border-color: #3b82f6;
  background-color: #eff6ff;
  box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1);
}

/* Loading spinner */
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}

/* Smooth transitions */
.transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}

/* Print styles */
@media print {
  .no-print {
    display: none !important;
  }
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .sm\:grid-cols-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .lg\:grid-cols-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>

