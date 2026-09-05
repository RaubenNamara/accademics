<template>
  <div class="space-y-8">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 p-8 text-white shadow-2xl">
      <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>
      <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>

      <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="mt-3 text-4xl font-bold tracking-tight">Teacher Observation Tracking</h1>
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            @click="loadData"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-medium text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/20 hover:shadow-lg hover:shadow-white/10"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              Refresh
            </span>
          </button>

          <button
            @click="downloadPDF"
            :disabled="records.length === 0"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-medium text-white shadow-xl shadow-emerald-500/25 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Download PDF
            </span>
          </button>

          <button
            @click="openAddModal()"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-500 px-5 py-2.5 text-sm font-medium text-white shadow-xl shadow-blue-500/25 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-blue-500/40"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Observation
            </span>
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 p-6 shadow-xl backdrop-blur-sm">
      <div class="grid gap-5 xl:grid-cols-6">
        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year</label>
          <select
            v-model="filterYear"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100/50"
          >
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Term</label>
          <select
            v-model="filterTerm"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100/50"
          >
            <option value="all">All Terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </div>

        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Class</label>
          <select
            v-model="filterClass"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100/50"
          >
            <option value="">All Classes</option>
            <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>

        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Stream</label>
          <select
            v-model="filterStream"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100/50"
          >
            <option value="">All Streams</option>
            <option v-for="s in streams" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Search Teacher</label>
          <input
            v-model="filterSearch"
            @input="loadData"
            type="text"
            placeholder="Search by name..."
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100/50"
          />
        </div>

        <div class="flex items-end">
          <div class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-50 to-purple-50 px-4 py-3">
            <div class="h-2 w-2 animate-pulse rounded-full bg-indigo-500"></div>
            <span class="text-sm font-medium text-slate-600">
              <span class="font-bold text-indigo-600">{{ records.length }}</span> observations found
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 shadow-xl backdrop-blur-sm">
      <div class="border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-5">
        <h2 class="text-xl font-bold text-slate-900">Observation Records</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-[1200px] w-full divide-y divide-slate-200/60">
          <thead class="bg-gradient-to-r from-slate-50 to-slate-100/50">
            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
              <th class="px-6 py-4">Teacher</th>
              <th class="px-6 py-4">Subject</th>
              <th class="px-6 py-4">Class</th>
              <th class="px-6 py-4">Stream</th>
              <th class="px-6 py-4">Observations</th>
              <th class="px-6 py-4">Avg Score</th>
              <th class="px-6 py-4">Avg Rating</th>
              <th class="px-6 py-4">Category</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100/60 bg-white">
            <tr v-if="groupedRecords.length === 0">
              <td colspan="9" class="px-6 py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="rounded-full bg-slate-100 p-4">
                    <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-slate-500">No observation records found.</p>
                </div>
              </td>
            </tr>

            <tr
              v-for="group in groupedRecords"
              :key="group.teacher_id"
              class="transition-all duration-200 hover:bg-gradient-to-r hover:from-indigo-50/30 hover:to-purple-50/30"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 text-sm font-bold text-white shadow-lg shadow-indigo-500/30">
                    {{ group.teacher_name?.charAt(0) || '?' }}
                  </div>
                  <div class="font-semibold text-slate-900">{{ group.teacher_name }}</div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center rounded-xl bg-gradient-to-r from-indigo-50 to-blue-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm">
                  {{ group.subject }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ group.class }}</td>
              <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ group.stream }}</td>
              <td class="px-6 py-4">
                <div class="space-y-1">
                  <div v-for="obs in group.observations" :key="obs.id" class="text-xs text-slate-600">
                    <span class="font-medium">Term {{ obs.term }}</span> - Round {{ obs.round || 1 }}: {{ Math.round(obs.total_score || 0) }}
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-sm font-bold text-slate-700">{{ group.avg_score || 0 }}</td>
              <td class="px-6 py-4 text-sm font-bold text-purple-600">{{ group.avg_rating || 0 }}</td>
              <td class="px-6 py-4">
                <span
                  class="inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-bold shadow-sm"
                  :class="getCategoryBadgeClass(group.overall_category)"
                >
                  {{ group.overall_category || '-' }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex justify-end gap-2">
                  <button
                    @click="viewRecord(group)"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-slate-200 bg-gradient-to-r from-slate-50 to-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-slate-400/20"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    View
                  </button>

                  <button
                    @click="addRoundForTeacher(group)"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-green-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-emerald-500/20"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    
                  </button>

                  <button
                    @click="editRecord(group.observations[0])"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-blue-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-indigo-500/20"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit
                  </button>

                  <button
                    @click="deleteRecord(group.observations[0].id)"
                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-rose-200 bg-gradient-to-r from-rose-50 to-pink-50 px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-rose-500/20"
                  >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-3xl bg-white shadow-2xl ring-1 ring-white/20">
        <div class="pointer-events-none absolute inset-0 rounded-3xl bg-gradient-to-br from-indigo-500/5 to-purple-500/5"></div>

        <div class="relative flex-shrink-0 border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-6">
          <h3 class="text-xl font-bold text-slate-900">
            {{ editingId ? 'Edit Observation' : 'Add Observation' }}
          </h3>
          <p class="mt-1.5 text-sm text-slate-500">
            Enter observation scores and feedback. Average will be calculated automatically.
          </p>
          <div v-if="error" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-600">
            {{ error }}
          </div>
        </div>

        <form @submit.prevent="saveRecord" class="relative flex flex-1 flex-col overflow-hidden">
          <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
            <div class="grid gap-5 sm:grid-cols-6">
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Teacher *</label>
                <div class="relative">
                  <input
                    v-model="teacherSearch"
                    type="text"
                    placeholder="Search teacher..."
                    class="input mb-2"
                  >
                  <svg class="absolute right-3 top-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                  </svg>
                </div>
                <select v-model="form.teacher_id" required class="input">
                  <option value="">Select</option>
                  <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Subject *</label>
                <select v-model="form.subject_id" required class="input">
                  <option value="">Select</option>
                  <option v-for="s in allSubjects" :key="s.id" :value="s.id">{{ s.subject_name }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Class</label>
                <select v-model="form.class_id" @change="onClassChange" class="input">
                  <option value="">Select</option>
                  <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Stream</label>
                <select v-model="form.stream_id" class="input">
                  <option value="">Select</option>
                  <option v-for="s in streams" :key="s.id" :value="s.id">{{ s.stream_name }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Term</label>
                <select v-model.number="form.term" class="input">
                  <option :value="1">Term 1</option>
                  <option :value="2">Term 2</option>
                  <option :value="3">Term 3</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year</label>
                <input v-model.number="form.year" type="number" class="input">
              </div>
            </div>

            <div class="rounded-2xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-50 to-purple-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-indigo-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Observation Round</h3>
              </div>
              <select v-model.number="form.round" class="input">
                <option :value="1">Round 1</option>
                <option :value="2">Round 2</option>
                <option :value="3">Round 3</option>
                <option :value="4">Round 4</option>
              </select>
            </div>

            <div class="rounded-2xl border-2 border-blue-200 bg-gradient-to-br from-blue-50 to-cyan-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-blue-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Total Score (Out of 100)</h3>
              </div>
              <input
                v-model.number="form.total_score"
                type="number"
                min="0"
                max="100"
                step="0.01"
                placeholder="Enter score (0-100)"
                class="input text-center text-2xl font-bold"
              >
              <div v-if="form.total_score !== null && (form.total_score < 0 || form.total_score > 100)" class="mt-2 text-sm font-medium text-red-600">
                Score must be between 0 and 100
              </div>
            </div>

            <div class="rounded-2xl border-2 border-purple-200 bg-gradient-to-br from-purple-50 to-pink-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-purple-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Automated Score Conversion</h3>
              </div>
              <div class="text-center">
                <div class="mb-2 text-4xl font-bold text-purple-600">{{ calculatedRating }} / 4.0</div>
                <div class="text-sm text-slate-600">Final Rating</div>
              </div>
            </div>

            <div class="rounded-2xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-emerald-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Performance Category</h3>
              </div>
              <div class="text-center">
                <span :class="`inline-block rounded-full border-2 px-6 py-3 text-lg font-bold ${categoryBadgeClass}`">
                  {{ performanceCategory }}
                </span>
              </div>
            </div>

            <div class="rounded-2xl border-2 border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-green-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Strengths Observed (Auto Generated)</h3>
              </div>
              <ul class="space-y-2">
                <li v-for="(strength, index) in strengthsObserved" :key="index" class="flex items-start gap-2">
                  <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                  <span class="text-slate-700">{{ strength }}</span>
                </li>
              </ul>
            </div>

            <div class="rounded-2xl border-2 border-amber-200 bg-gradient-to-br from-amber-50 to-yellow-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-amber-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">General Comment (Auto Generated)</h3>
              </div>
              <p class="leading-relaxed text-slate-700">{{ generalComment }}</p>
            </div>

            <div class="rounded-2xl border-2 border-rose-200 bg-gradient-to-br from-rose-50 to-pink-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-rose-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Areas for Improvement</h3>
              </div>
              <textarea
                v-model="form.areas_for_improvement"
                rows="4"
                placeholder="Enter recommendations for improvement..."
                class="input resize-none"
              ></textarea>
            </div>

            <div v-if="error" class="rounded-2xl border border-rose-200/60 bg-gradient-to-r from-rose-50 to-pink-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
              {{ error }}
            </div>
          </div>

          <div class="relative flex-shrink-0 border-t border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-5">
            <div class="flex items-center justify-end gap-3">
              <button
                type="button"
                @click="showModal = false"
                class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:bg-slate-50 hover:shadow-md"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="rounded-2xl bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
              >
                {{ saving ? 'Saving...' : 'Save' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- View Modal -->
    <div
      v-if="showViewModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 p-3 backdrop-blur-md sm:p-4"
    >
      <div class="relative flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-[1.75rem] bg-white shadow-[0_30px_80px_rgba(15,23,42,0.35)] ring-1 ring-white/20">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-white to-indigo-50"></div>
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-indigo-200/30 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-purple-200/30 blur-3xl"></div>

        <div class="relative border-b border-slate-200/70 px-5 py-5 sm:px-6">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <h3 class="truncate text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Lesson Observation Details
              </h3>
              <p class="mt-2 text-sm text-slate-500">
                Review all observation records for this teacher in a clean, responsive layout.
              </p>
            </div>

            <button
              type="button"
              @click="showViewModal = false"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white/90 text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div class="relative flex-1 overflow-y-auto px-4 py-5 sm:px-6">
          <div v-if="selectedRecord" class="space-y-5">
            <div class="rounded-[1.75rem] border border-white/80 bg-white/80 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-sm sm:p-6">
              <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                  <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-500 text-xl font-bold text-white shadow-lg shadow-indigo-500/30">
                    {{ selectedRecord.teacher_name?.charAt(0) || '?' }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">Teacher</p>
                    <h4 class="truncate text-2xl font-bold text-slate-900">{{ selectedRecord.teacher_name || '-' }}</h4>
                    <p class="mt-1 text-sm text-slate-500">{{ selectedRecord.subject || 'All observation records' }}</p>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">
                    Total {{ selectedRecord.totalObservations || 0 }} Observations
                  </span>
                  <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
                    Avg Score {{ selectedRecord.averageScore || 0 }}
                  </span>
                  <span class="rounded-full bg-purple-50 px-3 py-1.5 text-xs font-semibold text-purple-700">
                    Avg Rating {{ selectedRecord.averageRating || 0 }}
                  </span>
                  <span
                    class="rounded-full px-3 py-1.5 text-xs font-semibold"
                    :class="getCategoryBadgeClass(selectedRecord.overall_category)"
                  >
                    {{ selectedRecord.overall_category || '-' }}
                  </span>
                </div>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <div class="rounded-3xl border border-white/80 bg-white/85 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Total Observations</p>
                <p class="mt-3 text-2xl font-bold text-slate-900">{{ selectedRecord.totalObservations || 0 }}</p>
              </div>

              <div class="rounded-3xl border border-white/80 bg-white/85 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Average Score</p>
                <p class="mt-3 text-2xl font-bold text-slate-900">{{ selectedRecord.averageScore || 0 }}</p>
              </div>

              <div class="rounded-3xl border border-white/80 bg-white/85 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Average Rating</p>
                <p class="mt-3 text-2xl font-bold text-purple-600">{{ selectedRecord.averageRating || 0 }}</p>
              </div>

              <div class="rounded-3xl border border-white/80 bg-white/85 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Best Category</p>
                <div class="mt-3 inline-flex rounded-2xl px-4 py-2 text-lg font-bold" :class="getCategoryBadgeClass(selectedRecord.overall_category)">
                  {{ selectedRecord.overall_category || '-' }}
                </div>
              </div>
            </div>

            <div class="rounded-3xl border border-white/80 bg-white/85 p-5 shadow-lg shadow-slate-200/40 backdrop-blur-sm">
              <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                  <h3 class="text-lg font-bold text-slate-900">Observation History</h3>
                  <p class="mt-1 text-sm text-slate-500">All classes, streams, terms, years and rounds for this teacher.</p>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full divide-y divide-slate-200">
                  <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                      <th class="px-4 py-3">Subject</th>
                      <th class="px-4 py-3">Class</th>
                      <th class="px-4 py-3">Stream</th>
                      <th class="px-4 py-3">Term</th>
                      <th class="px-4 py-3">Year</th>
                      <th class="px-4 py-3">Round</th>
                      <th class="px-4 py-3">Total Score</th>
                      <th class="px-4 py-3">Rating</th>
                      <th class="px-4 py-3">Category</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 bg-white">
                    <tr
                      v-for="obs in (selectedRecord.observations || [])"
                      :key="obs.id"
                      class="hover:bg-slate-50"
                    >
                      <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ obs.subject || '-' }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ obs.class || '-' }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ obs.stream || '-' }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-slate-800">Term {{ obs.term || '-' }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ obs.year || '-' }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-slate-800">Round {{ obs.round || 1 }}</td>
                      <td class="px-4 py-3 text-sm font-bold text-slate-800">{{ Number(obs.total_score || 0).toFixed(2) }}</td>
                      <td class="px-4 py-3 text-sm font-bold text-purple-600">{{ Number(obs.calculated_rating || 0).toFixed(2) }}</td>
                      <td class="px-4 py-3">
                        <span class="inline-flex rounded-xl px-3 py-1.5 text-xs font-bold" :class="getCategoryBadgeClass(obs.performance_category)">
                          {{ obs.performance_category || '-' }}
                        </span>
                      </td>
                    </tr>

                    <tr v-if="!selectedRecord.observations || selectedRecord.observations.length === 0">
                      <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">
                        No observation history found.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-3xl border-2 border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-6 shadow-lg">
                <div class="mb-4 flex items-center gap-3">
                  <div class="rounded-xl bg-green-500 p-2">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                    </svg>
                  </div>
                  <h3 class="text-lg font-bold text-slate-900">Overall Strengths</h3>
                </div>
                <p class="leading-relaxed text-slate-700">
                  {{ selectedRecord.observations?.[0]?.strengths_observed || '-' }}
                </p>
              </div>

              <div class="rounded-3xl border-2 border-amber-200 bg-gradient-to-br from-amber-50 to-yellow-50 p-6 shadow-lg">
                <div class="mb-4 flex items-center gap-3">
                  <div class="rounded-xl bg-amber-500 p-2">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                    </svg>
                  </div>
                  <h3 class="text-lg font-bold text-slate-900">General Comment</h3>
                </div>
                <p class="leading-relaxed text-slate-700">
                  {{ selectedRecord.observations?.[0]?.general_comment || '-' }}
                </p>
              </div>
            </div>

            <div class="rounded-3xl border-2 border-rose-200 bg-gradient-to-br from-rose-50 to-pink-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-rose-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Areas for Improvement</h3>
              </div>
              <p class="leading-relaxed whitespace-pre-line text-slate-700">
                {{ selectedRecord.observations?.[0]?.areas_for_improvement || '-' }}
              </p>
            </div>
          </div>
        </div>

        <div class="relative border-t border-slate-200/70 bg-white/90 px-4 py-4 sm:px-6">
          <div class="flex justify-end">
            <button
              type="button"
              @click="showViewModal = false"
              class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:bg-slate-50 hover:shadow-md"
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
import { ref, onMounted, computed, watch } from 'vue';
import { lessonObservationAPI, teachersAPI, classesAPI, subjectsNewAPI } from '../services/api';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const records = ref([]);
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref('all');
const filterClass = ref('');
const filterStream = ref('');
const filterSearch = ref('');
const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i);
const showModal = ref(false);
const showViewModal = ref(false);
const editingId = ref(null);
const saving = ref(false);
const error = ref('');
const selectedRecord = ref(null);

const allSubjects = ref([]);
const allClasses = ref([]);
const streams = ref([]);

const filteredTeachers = computed(() => {
  if (!teacherSearch.value) return teachersList.value;
  const search = teacherSearch.value.toLowerCase();
  return teachersList.value.filter(teacher =>
    teacher.full_name.toLowerCase().includes(search) ||
    teacher.teacher_code.toLowerCase().includes(search)
  );
});

const classes = computed(() => {
  return allClasses.value.map(c => c.class_name).sort();
});

const form = ref({
  teacher_id: '',
  subject_id: '',
  class_id: '',
  stream_id: '',
  year: new Date().getFullYear(),
  term: 1,
  round: 1,
  total_score: 0,
  calculated_rating: 0,
  performance_category: '',
  strengths_observed: '',
  general_comment: '',
  areas_for_improvement: ''
});

const calculatedRating = computed(() => {
  const score = Number(form.value.total_score) || 0;
  return (score / 25).toFixed(2);
});

const performanceCategory = computed(() => {
  const rating = parseFloat(calculatedRating.value) || 0;
  if (rating >= 3.5) return 'Outstanding';
  if (rating >= 3.0) return 'Very Good';
  if (rating >= 2.5) return 'Good';
  if (rating >= 2.0) return 'Fair';
  return 'Below Expectation';
});

const categoryBadgeClass = computed(() => {
  const category = performanceCategory.value;
  switch (category) {
    case 'Outstanding': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    case 'Very Good': return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'Good': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    case 'Fair': return 'bg-orange-100 text-orange-800 border-orange-200';
    case 'Below Expectation': return 'bg-red-100 text-red-800 border-red-200';
    default: return 'bg-gray-100 text-gray-800 border-gray-200';
  }
});

const strengthsObserved = computed(() => {
  const category = performanceCategory.value;
  const strengths = {
    'Outstanding': [
      'Demonstrates excellent lesson delivery and learner engagement.',
      'Shows exceptional classroom management skills.',
      'Uses highly effective learner-centered teaching strategies.',
      'Displays strong mastery of subject content.',
      'Lesson pacing and preparation are outstanding.'
    ],
    'Very Good': [
      'Lesson organization and delivery are very effective.',
      'Learners participate actively during lessons.',
      'Teaching methods support understanding well.',
      'Good classroom control and communication observed.'
    ],
    'Good': [
      'Lesson objectives are generally achieved.',
      'Teaching methods are satisfactory.',
      'Subject mastery is evident though some improvement is needed.',
      'Learner participation is moderate.'
    ],
    'Fair': [
      'Improvement is needed in instructional delivery.',
      'Learner engagement is limited.',
      'Classroom interaction should be strengthened.',
      'More effective teaching strategies are recommended.'
    ],
    'Below Expectation': [
      'Lesson delivery falls below expected standards.',
      'Limited preparation and learner engagement observed.',
      'Classroom management requires significant improvement.',
      'Instructional methods should be improved urgently.'
    ]
  };
  return strengths[category] || [];
});

const generalComment = computed(() => {
  const category = performanceCategory.value;
  const comments = {
    'Outstanding': 'The lesson was exceptionally well-delivered with excellent preparation, subject mastery, and learner engagement. The teacher demonstrated outstanding classroom management and used highly effective teaching strategies.',
    'Very Good': 'The lesson was very effectively organized and delivered with good preparation. Learners participated actively and teaching methods supported understanding well. Classroom control and communication were strong.',
    'Good': 'The lesson objectives were generally achieved with satisfactory teaching methods. Subject mastery was evident though some improvement is needed in learner participation and engagement.',
    'Fair': 'The lesson was fairly conducted with evidence of preparation and subject knowledge. However, improvement is needed in learner engagement, instructional strategies, and classroom interaction to achieve better learning outcomes.',
    'Below Expectation': 'The lesson delivery falls below expected standards with limited preparation and learner engagement. Classroom management requires significant improvement and instructional methods should be improved urgently.'
  };
  return comments[category] || '';
});

const getCategoryBadgeClass = (category) => {
  switch (category) {
    case 'Outstanding': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    case 'Very Good': return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'Good': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    case 'Fair': return 'bg-orange-100 text-orange-800 border-orange-200';
    case 'Below Expectation': return 'bg-red-100 text-red-800 border-red-200';
    default: return 'bg-gray-100 text-gray-800 border-gray-200';
  }
};

const rankCategory = (category) => {
  switch (category) {
    case 'Outstanding': return 5;
    case 'Very Good': return 4;
    case 'Good': return 3;
    case 'Fair': return 2;
    case 'Below Expectation': return 1;
    default: return 0;
  }
};

const groupedRecords = computed(() => {
  const grouped = {};

  records.value.forEach(record => {
    const teacherId = record.teacher_id;

    if (!grouped[teacherId]) {
      grouped[teacherId] = {
        teacher_id: record.teacher_id,
        teacher_name: record.teacher_name,
        subject: record.subject,
        class: record.class,
        stream: record.stream,
        observations: [],
        total_score: 0,
        avg_score: 0,
        avg_rating: 0,
        overall_category: ''
      };
    }

    grouped[teacherId].observations.push(record);
  });

  Object.values(grouped).forEach(group => {
    const valid = group.observations.filter(item => item.total_score !== null && item.total_score !== undefined);
    const totalRounds = valid.length;
    const totalScore = valid.reduce((sum, item) => sum + Number(item.total_score || 0), 0);
    const totalRating = valid.reduce((sum, item) => sum + (Number(item.calculated_rating || (Number(item.total_score || 0) / 25))), 0);

    group.avg_score = totalRounds > 0 ? (totalScore / totalRounds).toFixed(0) : 0;
    group.avg_rating = totalRounds > 0 ? (totalRating / totalRounds).toFixed(1) : 0;

    const best = group.observations.reduce((acc, item) => {
      if (!acc) return item;
      return rankCategory(item.performance_category) > rankCategory(acc.performance_category) ? item : acc;
    }, null);

    group.overall_category = best?.performance_category || '-';
  });

  return Object.values(grouped);
});

const loadData = async () => {
  try {
    const result = await lessonObservationAPI.getAll(
      filterYear.value,
      filterTerm.value,
      filterSearch.value,
      filterClass.value,
      filterStream.value
    );

    if (result.success) {
      records.value = result.observations || result.data?.data || result.data || [];
    } else {
      console.error('Load data failed:', result);
    }
  } catch (err) {
    console.error('Failed to load data:', err);
  }
};

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    let classData = [];

    if (response?.data && Array.isArray(response.data)) {
      classData = response.data;
    } else if (response?.success && response.data && Array.isArray(response.data)) {
      classData = response.data;
    } else if (Array.isArray(response)) {
      classData = response;
    }

    if (classData.length > 0) {
      const uniqueClassNames = [...new Set(classData.map(c => c.class_name))];
      allClasses.value = uniqueClassNames.map(name => ({ class_name: name }));
    }
  } catch (error) {
    console.error('Error loading classes:', error);
  }
};

const loadStreamsByClass = async (className) => {
  try {
    if (!className) {
      streams.value = [];
      return;
    }

    const response = await classesAPI.getAll();
    let classData = [];

    if (response?.data && Array.isArray(response.data)) {
      classData = response.data;
    } else if (response?.success && response.data && Array.isArray(response.data)) {
      classData = response.data;
    } else if (Array.isArray(response)) {
      classData = response;
    }

    if (classData.length > 0) {
      const filteredStreams = classData.filter(c =>
        c.class_name && c.class_name.toLowerCase() === className.toLowerCase()
      );

      if (filteredStreams.length > 0) {
        streams.value = filteredStreams.map(c => ({
          id: c.id,
          stream_name: c.stream_name
        }));
      } else {
        streams.value = [];
      }
    } else {
      streams.value = [];
    }
  } catch (error) {
    console.error('Error loading streams:', error);
    streams.value = [];
  }
};

const onClassChange = async () => {
  form.value.stream_id = '';
  await loadStreamsByClass(form.value.class_id);
};

watch(() => form.value.class_id, async (newClass) => {
  form.value.stream_id = '';
  await loadStreamsByClass(newClass);
});

const loadSubjects = async () => {
  try {
    const response = await subjectsNewAPI.getAll();
    allSubjects.value = response.data || [];
  } catch (error) {
    console.error('Error loading subjects:', error);
  }
};

const loadTeachers = async () => {
  try {
    const result = await teachersAPI.getAll();
    if (result.success) {
      teachersList.value = result.data.teachers || result.data || [];
    }
  } catch (err) {
    console.error('Failed to load teachers:', err);
  }
};

const openAddModal = (group = null) => {
  editingId.value = null;
  form.value = {
    teacher_id: '',
    subject_id: '',
    class_id: '',
    stream_id: '',
    year: new Date().getFullYear(),
    term: 1,
    round: 1,
    total_score: 0,
    calculated_rating: 0,
    performance_category: '',
    strengths_observed: '',
    general_comment: '',
    areas_for_improvement: ''
  };
  error.value = '';

  if (group) {
    const latest = [...(group.observations || [])].sort((a, b) => {
      const ad = new Date(a.created_at || 0).getTime();
      const bd = new Date(b.created_at || 0).getTime();
      return bd - ad;
    })[0] || group.observations?.[0];

    form.value.teacher_id = group.teacher_id || '';
    teacherSearch.value = group.teacher_name || '';

    if (latest) {
      form.value.subject_id = latest.subject_id || '';
      form.value.class_id = latest.class || '';
      form.value.stream_id = latest.stream_id || '';
      form.value.term = latest.term || 1;
      form.value.year = latest.year || new Date().getFullYear();

      const maxRound = Math.max(...(group.observations || []).map(o => Number(o.round || 1)));
      form.value.round = Math.min((maxRound || 0) + 1, 4);

      if (latest.class) {
        loadStreamsByClass(latest.class);
      }
    }
  }

  showModal.value = true;
};

const addRoundForTeacher = (group) => {
  openAddModal(group);
};

const viewRecord = (group) => {
  selectedRecord.value = {
    ...group,
    totalObservations: group.observations?.length || 0,
    averageScore: group.avg_score || 0,
    averageRating: group.avg_rating || 0
  };
  showViewModal.value = true;
};

const editRecord = (record) => {
  editingId.value = record.id;
  form.value = {
    teacher_id: record.teacher_id,
    subject_id: record.subject_id || '',
    class_id: record.class_id || '',
    stream_id: record.stream_id || '',
    year: record.year,
    term: record.term,
    round: record.round || 1,
    total_score: record.total_score || 0,
    calculated_rating: record.calculated_rating || 0,
    performance_category: record.performance_category || '',
    strengths_observed: record.strengths_observed || '',
    general_comment: record.general_comment || '',
    areas_for_improvement: record.areas_for_improvement || ''
  };
  showModal.value = true;
};

const saveRecord = async () => {
  saving.value = true;
  error.value = '';

  try {
    const classRecordId = form.value.stream_id ? parseInt(form.value.stream_id) : null;

    const dataToSave = {
      teacher_id: form.value.teacher_id,
      subject_id: form.value.subject_id,
      class_id: classRecordId,
      stream_id: classRecordId,
      year: form.value.year,
      term: form.value.term,
      round: form.value.round,
      total_score: form.value.total_score || 0,
      calculated_rating: calculatedRating.value,
      performance_category: performanceCategory.value,
      strengths_observed: strengthsObserved.value.join('\n'),
      general_comment: generalComment.value,
      areas_for_improvement: form.value.areas_for_improvement
    };

    let result;
    if (editingId.value) {
      result = await lessonObservationAPI.update(editingId.value, dataToSave);
    } else {
      result = await lessonObservationAPI.create(dataToSave);
    }

    if (result.success) {
      showModal.value = false;
      await loadData();
    } else {
      const errorMsg = result.message || result.data?.message || result.error || JSON.stringify(result);
      error.value = `Failed to save: ${errorMsg}`;
    }
  } catch (err) {
    error.value = err.message || 'An error occurred while saving';
  } finally {
    saving.value = false;
  }
};

const deleteRecord = async (id) => {
  if (!confirm('Are you sure you want to delete this lesson observation?')) return;

  try {
    const result = await lessonObservationAPI.delete(id);

    if (result.success) {
      await loadData();
    } else {
      alert(result.message || 'Failed to delete record. Please try again.');
    }
  } catch (error) {
    console.error('Failed to delete record:', error);
    alert('Failed to delete record. Please try again.');
  }
};

const downloadPDF = () => {
  const doc = new jsPDF();

  doc.setFontSize(18);
  doc.text('Lesson Observations Report', 14, 22);

  doc.setFontSize(11);
  doc.setTextColor(100);
  doc.text(`Year: ${filterYear.value} | Term: ${filterTerm.value}`, 14, 30);
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);

  const tableData = records.value.map(record => [
    record.teacher_name,
    record.subject,
    record.class,
    record.stream,
    `Term ${record.term}`,
    `Round ${record.round || 1}`,
    `${record.total_score || 0} / 100`,
    `${record.calculated_rating || 0} / 4.0`,
    record.performance_category || '-'
  ]);

  autoTable(doc, {
    head: [['Teacher', 'Subject', 'Class', 'Stream', 'Term', 'Round', 'Total Score', 'Rating', 'Category']],
    body: tableData,
    startY: 45,
    styles: {
      fontSize: 9,
      cellPadding: 3
    },
    headStyles: {
      fillColor: [59, 130, 246],
      textColor: 255,
      fontStyle: 'bold'
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245]
    },
    margin: { top: 10, right: 10, bottom: 10, left: 10 }
  });

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

  doc.save(`lesson-observations-${filterYear.value}-term-${filterTerm.value}.pdf`);
};

onMounted(() => {
  loadClasses();
  loadSubjects();
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
</style>