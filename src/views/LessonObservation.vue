<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Lesson Observations</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero-primary" @click="openAddModal()"><Plus class="h-4 w-4" />Add observation</button>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <ListToolbar
      :search="filterSearch"
      placeholder="Search teacher"
      :filter-count="[filterTerm !== 'all' ? filterTerm : '', filterClass, filterStream].filter(Boolean).length"
      @update:search="(v) => { filterSearch = v; loadData(); }"
      @clear="filterTerm = 'all'; filterClass = ''; filterStream = ''; loadData()"
    >
      <template #filters>
        <label class="block">
          <span class="filter-label">Year</span>
          <select v-model="filterYear" class="filter-select" @change="loadData">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Term</span>
          <select v-model="filterTerm" class="filter-select" @change="loadData">
            <option value="all">All terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Class</span>
          <select v-model="filterClass" class="filter-select" @change="loadData">
            <option value="">All classes</option>
            <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Stream</span>
          <select v-model="filterStream" class="filter-select" @change="loadData">
            <option value="">All streams</option>
            <option v-for="s in streams" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>
      </template>
      <template #actions>
        <button type="button" class="tool-btn" :disabled="loading" title="Refresh" @click="loadData"><RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" /><span class="hidden lg:inline">Refresh</span></button>
        <button type="button" class="tool-btn" :disabled="records.length === 0" title="Download PDF" @click="downloadPDF"><FileText class="h-4 w-4" /><span class="hidden lg:inline">PDF</span></button>
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ groupedRecords.length }}</strong> teacher{{ groupedRecords.length === 1 ? '' : 's' }} observed · {{ records.length }} observation{{ records.length === 1 ? '' : 's' }}</p>
      </div>
      <div v-if="loading && records.length === 0" class="space-y-3 p-5">
        <div v-for="i in 5" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="groupedRecords.length === 0" class="px-5 py-14 text-center">
        <ClipboardList class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No observations found</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Teacher</th>
              <th class="px-4 py-3">Class</th>
              <th class="hidden px-4 py-3 xl:table-cell">Rounds</th>
              <th class="px-4 py-3">Average</th>
              <th class="hidden px-4 py-3 lg:table-cell">Category</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="group in groupedRecords" :key="group.teacher_id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <button type="button" class="flex min-w-0 items-center gap-3 text-left" @click="viewRecord(group)">
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(group.teacher_name) }}</span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900 hover:text-brand-700">{{ group.teacher_name }}</span>
                    <span class="block truncate text-xs text-gray-500">{{ group.subject || '-' }}</span>
                  </span>
                </button>
              </td>
              <td class="px-4 py-3 text-gray-700">{{ group.class }} {{ group.stream }}</td>
              <td class="hidden px-4 py-3 xl:table-cell">
                <div class="flex flex-wrap gap-1">
                  <span v-for="obs in group.observations" :key="obs.id" class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">T{{ obs.term }} R{{ obs.round || 1 }}: <strong class="text-gray-800">{{ Math.round(obs.total_score || 0) }}</strong></span>
                </div>
              </td>
              <td class="px-4 py-3">
                <p class="tabular-nums font-semibold text-gray-900">{{ group.avg_score || 0 }}</p>
                <p class="text-xs text-gray-500">Rating {{ group.avg_rating || 0 }}</p>
              </td>
              <td class="hidden px-4 py-3 lg:table-cell">
                <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="getCategoryBadgeClass(group.overall_category)">{{ group.overall_category || '-' }}</span>
              </td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${group.teacher_name}`" :items="groupActions(group)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="group in groupedRecords" :key="group.teacher_id" class="flex items-start gap-3 px-4 py-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(group.teacher_name) }}</span>
            <button type="button" class="min-w-0 flex-1 text-left" @click="viewRecord(group)">
              <p class="truncate font-semibold text-gray-900">{{ group.teacher_name }}</p>
              <p class="truncate text-xs text-gray-500">{{ group.subject || '-' }} · {{ group.class }} {{ group.stream }} · avg {{ group.avg_score || 0 }}</p>
              <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="getCategoryBadgeClass(group.overall_category)">{{ group.overall_category || '-' }}</span>
            </button>
            <RowActionsMenu :label="`Actions for ${group.teacher_name}`" :items="groupActions(group)" />
          </li>
        </ul>
      </template>
    </section>

    <!-- Add/Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl bg-white shadow-2xl ring-1 ring-white/20">
        <div class="pointer-events-none absolute inset-0 rounded-2xl bg-brand-500/5"></div>

        <div class="relative flex-shrink-0 border-b border-slate-200/60 bg-slate-50 px-6 py-6">
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
                  <svg class="absolute right-3 top-3 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

            <div class="rounded-2xl border-2 border-brand-200 bg-brand-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-brand-500 p-2">
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

            <div class="rounded-2xl border-2 border-brand-200 bg-brand-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-brand-500 p-2">
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

            <div class="rounded-2xl border-2 border-brand-200 bg-brand-50 p-6 shadow-lg">
              <div class="mb-4 flex items-center gap-3">
                <div class="rounded-xl bg-brand-500 p-2">
                  <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Automated Score Conversion</h3>
              </div>
              <div class="text-center">
                <div class="mb-2 text-4xl font-bold text-brand-600">{{ calculatedRating }} / 4.0</div>
                <div class="text-sm text-slate-600">Final Rating</div>
              </div>
            </div>

            <div class="rounded-2xl border-2 border-emerald-200 bg-emerald-50 p-6 shadow-lg">
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

            <div class="rounded-2xl border-2 border-green-200 bg-green-50 p-6 shadow-lg">
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

            <div class="rounded-2xl border-2 border-amber-200 bg-amber-50 p-6 shadow-lg">
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

            <div class="rounded-2xl border-2 border-rose-200 bg-rose-50 p-6 shadow-lg">
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

            <div v-if="error" class="rounded-2xl border border-rose-200/60 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
              {{ error }}
            </div>
          </div>

          <div class="relative flex-shrink-0 border-t border-slate-200/60 bg-slate-50 px-6 py-5">
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
                class="rounded-2xl bg-brand-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-brand-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
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
      <div class="relative flex h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-[0_30px_80px_rgba(15,23,42,0.35)] ring-1 ring-white/20">
        <div class="absolute inset-0 bg-slate-50"></div>

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
            <div class="card border-white/80 p-5 sm:p-6">
              <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                  <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-500 text-xl font-bold text-white shadow-lg shadow-brand-500/30">
                    {{ selectedRecord.teacher_name?.charAt(0) || '?' }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">Teacher</p>
                    <h4 class="truncate text-2xl font-bold text-slate-900">{{ selectedRecord.teacher_name || '-' }}</h4>
                    <p class="mt-1 text-sm text-slate-500">{{ selectedRecord.subject || 'All observation records' }}</p>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700">
                    Total {{ selectedRecord.totalObservations || 0 }} Observations
                  </span>
                  <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
                    Avg Score {{ selectedRecord.averageScore || 0 }}
                  </span>
                  <span class="rounded-full bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700">
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
              <div class="card border-white/80 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Total Observations</p>
                <p class="mt-3 text-2xl font-bold text-slate-900">{{ selectedRecord.totalObservations || 0 }}</p>
              </div>

              <div class="card border-white/80 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Average Score</p>
                <p class="mt-3 text-2xl font-bold text-slate-900">{{ selectedRecord.averageScore || 0 }}</p>
              </div>

              <div class="card border-white/80 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Average Rating</p>
                <p class="mt-3 text-2xl font-bold text-brand-600">{{ selectedRecord.averageRating || 0 }}</p>
              </div>

              <div class="card border-white/80 p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Best Category</p>
                <div class="mt-3 inline-flex rounded-2xl px-4 py-2 text-lg font-bold" :class="getCategoryBadgeClass(selectedRecord.overall_category)">
                  {{ selectedRecord.overall_category || '-' }}
                </div>
              </div>
            </div>

            <div class="card border-white/80 p-5">
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
                      <td class="px-4 py-3 text-sm font-bold text-brand-600">{{ Number(obs.calculated_rating || 0).toFixed(2) }}</td>
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
              <div class="rounded-2xl border-2 border-green-200 bg-green-50 p-6 shadow-lg">
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

              <div class="rounded-2xl border-2 border-amber-200 bg-amber-50 p-6 shadow-lg">
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

            <div class="rounded-2xl border-2 border-rose-200 bg-rose-50 p-6 shadow-lg">
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
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import { Plus, RefreshCw, FileText, ClipboardList, Eye, Pencil, Trash2, Users as UsersIcon, Gauge, Star, PlusCircle } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';

const records = ref([]);
const loading = ref(false);
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref('all');
const filterClass = ref('');
const filterStream = ref('');
const filterSearch = ref('');
useGlobalTerm((y, t) => { filterYear.value = y; filterTerm.value = t; }, () => loadData());
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
    case 'Very Good': return 'bg-brand-100 text-brand-800 border-brand-200';
    case 'Good': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    case 'Fair': return 'bg-orange-100 text-orange-800 border-orange-200';
    case 'Below Expectation': return 'bg-red-100 text-red-800 border-red-200';
    default: return 'bg-slate-100 text-slate-800 border-slate-200';
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
    case 'Very Good': return 'bg-brand-100 text-brand-800 border-brand-200';
    case 'Good': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    case 'Fair': return 'bg-orange-100 text-orange-800 border-orange-200';
    case 'Below Expectation': return 'bg-red-100 text-red-800 border-red-200';
    default: return 'bg-slate-100 text-slate-800 border-slate-200';
  }
};

const initials = (name) => String(name || '?').split(/s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const groupActions = (group) => [
  { label: 'View', icon: Eye, onClick: () => viewRecord(group) },
  { label: 'Add round', icon: PlusCircle, onClick: () => addRoundForTeacher(group) },
  { label: 'Edit', icon: Pencil, onClick: () => editRecord(group.observations[0]) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteRecord(group.observations[0].id) },
];
const summaryCards = computed(() => {
  const scores = records.value.map((r) => Number(r.total_score)).filter((n) => !Number.isNaN(n));
  const avg = scores.length ? Math.round(scores.reduce((a, b) => a + b, 0) / scores.length) : null;
  const top = groupedRecords.value.filter((g) => ['Outstanding', 'Very Good'].includes(g.overall_category)).length;
  return [
    { label: 'Observations', value: records.value.length.toLocaleString(), icon: ClipboardList },
    { label: 'Teachers observed', value: groupedRecords.value.length.toLocaleString(), icon: UsersIcon, tone: 'sky' },
    { label: 'Average score', value: avg === null ? '-' : String(avg), icon: Gauge, tone: 'gray' },
    { label: 'Very good or better', value: top.toLocaleString(), icon: Star, tone: 'amber' },
  ];
});

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
  loading.value = true;
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
  } finally {
    loading.value = false;
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
      fillColor: [5, 150, 105],
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
  border-color: rgb(5 150 105);
  box-shadow: 0 0 0 4px rgb(220 252 231);
}

.input:hover:not(:focus) {
  border-color: rgb(203 213 225);
}
</style>

<style scoped>
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.filter-select { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .input {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .input:hover:not(:focus) {
  border-color: var(--d-line);
}
html.dark .filter-label {
  color: var(--d-muted);
}
html.dark .filter-select {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
}
html.dark .tool-btn {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .tool-btn:hover {
  background-color: var(--d-sunken);
}
</style>
