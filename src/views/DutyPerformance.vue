<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Duty Performance</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero-primary" @click="openAddModal"><Plus class="h-4 w-4" />Add evaluation</button>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <section v-if="teacherOfTheWeek" class="card flex items-center gap-4 border-amber-200 bg-amber-50/60 p-4 sm:p-5">
      <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 ring-1 ring-amber-200"><Trophy class="h-6 w-6" /></span>
      <div class="min-w-0 flex-1">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Teacher of the week{{ filterWeek ? ` · Week ${filterWeek}` : '' }}</p>
        <p class="truncate text-lg font-bold text-gray-900">{{ teacherOfTheWeek.teacher_name }}</p>
        <p class="text-sm text-gray-600">{{ num(teacherOfTheWeek.total_score) }}/100 · {{ teacherOfTheWeek.status }}</p>
      </div>
      <span class="hidden rounded-full px-3 py-1 text-sm font-bold sm:inline-block" :class="getStatusBadgeClass(teacherOfTheWeek.percentage)">{{ num(teacherOfTheWeek.percentage) }}%</span>
    </section>

    <ListToolbar
      v-model:search="searchQuery"
      placeholder="Search teacher"
      :filter-count="[filterWeek].filter(Boolean).length"
      @clear="filterWeek = ''; loadData()"
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
            <option value="">All terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Week</span>
          <select v-model="filterWeek" class="filter-select" @change="loadData">
            <option value="">All weeks</option>
            <option v-for="w in 13" :key="w" :value="w">Week {{ w }}</option>
          </select>
        </label>
      </template>
      <template #actions>
        <button type="button" class="tool-btn" :disabled="loading" title="Refresh" @click="loadData"><RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" /><span class="hidden lg:inline">Refresh</span></button>
        <button type="button" class="tool-btn" :disabled="filteredRecords.length === 0" title="Download PDF" @click="downloadPDF"><FileText class="h-4 w-4" /><span class="hidden lg:inline">PDF</span></button>
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ filteredRecords.length }}</strong> evaluation{{ filteredRecords.length === 1 ? '' : 's' }}</p>
        <p class="text-xs text-gray-500">Ranked by score</p>
      </div>
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="i in 5" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="filteredRecords.length === 0" class="px-5 py-14 text-center">
        <ClipboardList class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No evaluations found</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="w-14 px-5 py-3">Rank</th>
              <th class="px-4 py-3">Teacher</th>
              <th class="px-4 py-3">Week</th>
              <th class="px-4 py-3">Score</th>
              <th class="hidden px-4 py-3 lg:table-cell">Rating</th>
              <th class="hidden px-4 py-3 xl:table-cell">Date</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(record, index) in filteredRecords" :key="record.id" class="transition hover:bg-brand-50/40">
              <td class="px-5 py-3">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold" :class="rankClass(index)">{{ index + 1 }}</span>
              </td>
              <td class="px-4 py-3">
                <button type="button" class="flex min-w-0 items-center gap-3 text-left" @click="viewRecord(record)">
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(record.teacher_name) }}</span>
                  <span class="truncate font-semibold text-gray-900 hover:text-brand-700">{{ record.teacher_name }}</span>
                </button>
              </td>
              <td class="px-4 py-3 text-gray-700">Week {{ record.week_number }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="w-16 tabular-nums font-semibold text-gray-900">{{ num(record.total_score) }}/100</span>
                  <span class="hidden h-1.5 w-20 overflow-hidden rounded-full bg-gray-100 sm:block"><span class="block h-full rounded-full" :class="getProgressBarClass(record.percentage)" :style="{ width: Math.min(100, Number(record.percentage) || 0) + '%' }"></span></span>
                </div>
              </td>
              <td class="hidden px-4 py-3 lg:table-cell">
                <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="getStatusBadgeClass(record.percentage)">{{ shortStatus(record.status) }}</span>
              </td>
              <td class="hidden px-4 py-3 text-gray-600 xl:table-cell">{{ formatDate(record.created_at) }}</td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${record.teacher_name}`" :items="recordActions(record)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="(record, index) in filteredRecords" :key="record.id" class="flex items-start gap-3 px-4 py-3">
            <span class="mt-2 inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="rankClass(index)">{{ index + 1 }}</span>
            <button type="button" class="min-w-0 flex-1 text-left" @click="viewRecord(record)">
              <p class="truncate font-semibold text-gray-900">{{ record.teacher_name }}</p>
              <p class="text-xs text-gray-500">Week {{ record.week_number }} · {{ num(record.total_score) }}/100</p>
              <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="getStatusBadgeClass(record.percentage)">{{ shortStatus(record.status) }}</span>
            </button>
            <RowActionsMenu :label="`Actions for ${record.teacher_name}`" :items="recordActions(record)" />
          </li>
        </ul>
      </template>
    </section>

    <!-- Add/Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 p-0 backdrop-blur-sm sm:items-center sm:p-4"
      @keydown.esc="closeModal"
    >
      <div class="flex max-h-[100dvh] w-full max-w-3xl flex-col overflow-hidden bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="duty-modal-title">
        <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 ring-1 ring-brand-100"><ClipboardList class="h-5 w-5" /></span>
          <div class="min-w-0 flex-1">
            <h3 id="duty-modal-title" class="text-lg font-semibold text-gray-900">{{ editingId ? 'Edit duty evaluation' : 'New duty evaluation' }}</h3>
            <p class="text-sm text-gray-500">Score each duty out of 20. The total works itself out.</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="closeModal"><X class="h-5 w-5" /></button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="saveRecord">
          <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
            <!-- Who and when -->
            <section>
              <p class="m-label">Teacher</p>
              <div v-if="selectedTeacher" class="flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50/50 px-3 py-2.5">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(selectedTeacher.full_name) }}</span>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-semibold text-gray-900">{{ selectedTeacher.full_name }}</p>
                  <p class="font-mono text-[11px] text-gray-500">{{ selectedTeacher.teacher_code }}</p>
                </div>
                <button type="button" class="text-sm font-medium text-brand-700 hover:underline" @click="form.teacher_id = ''; teacherSearch = ''">Change</button>
              </div>
              <div v-else class="rounded-xl border border-gray-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100">
                <div class="relative">
                  <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                  <input v-model="teacherSearch" type="text" class="w-full rounded-t-xl border-0 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none" placeholder="Type a name or code" aria-label="Search teacher" />
                </div>
                <ul class="max-h-44 overflow-y-auto border-t border-gray-100 py-1" role="listbox">
                  <li v-for="t in filteredTeachers.slice(0, 50)" :key="t.id">
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-brand-50" role="option" @click="form.teacher_id = t.id">
                      <span class="truncate text-gray-900">{{ t.full_name }}</span>
                      <span class="ml-auto font-mono text-[11px] text-gray-500">{{ t.teacher_code }}</span>
                    </button>
                  </li>
                  <li v-if="filteredTeachers.length === 0" class="px-3 py-2 text-sm text-gray-500">No teacher matches "{{ teacherSearch }}"</li>
                  <li v-else-if="filteredTeachers.length > 50" class="px-3 py-1.5 text-xs text-gray-400">Keep typing to narrow {{ filteredTeachers.length }} teachers</li>
                </ul>
              </div>

              <div class="mt-4 grid grid-cols-3 gap-3">
                <label class="block">
                  <span class="m-label">Year</span>
                  <input v-model.number="form.year" type="number" class="m-input" />
                </label>
                <label class="block">
                  <span class="m-label">Term</span>
                  <select v-model="form.term" class="m-input">
                    <option :value="1">Term 1</option>
                    <option :value="2">Term 2</option>
                    <option :value="3">Term 3</option>
                  </select>
                </label>
                <label class="block">
                  <span class="m-label">Week</span>
                  <select v-model.number="form.week_number" class="m-input">
                    <option v-for="w in 13" :key="w" :value="w">Week {{ w }}</option>
                  </select>
                </label>
              </div>
            </section>

            <!-- Scores -->
            <section>
              <div class="mb-2 flex items-baseline justify-between">
                <p class="m-label mb-0">Scores</p>
                <button type="button" class="text-xs font-medium text-brand-700 hover:underline" @click="setAllScores(20)">Set all to 20</button>
              </div>
              <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200">
                <li v-for="d in DUTY_FIELDS" :key="d.key" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-3 sm:flex-nowrap">
                  <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-600 ring-1 ring-gray-200"><component :is="d.icon" class="h-4 w-4" /></span>
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900">{{ d.label }}</p>
                    <p v-if="d.hint" class="text-xs text-gray-500">{{ d.hint }}</p>
                  </div>
                  <div class="flex w-full items-center gap-3 sm:w-auto">
                    <input
                      v-model.number="form[d.key]"
                      type="range"
                      min="0"
                      max="20"
                      step="1"
                      class="score-range flex-1 sm:w-36"
                      :aria-label="`${d.label} score`"
                      @input="autoCalculate"
                    />
                    <div class="flex items-baseline gap-0.5">
                      <input
                        v-model.number="form[d.key]"
                        type="number"
                        min="0"
                        max="20"
                        class="w-14 rounded-lg border border-gray-300 px-2 py-1.5 text-center text-sm font-semibold tabular-nums outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100"
                        :aria-label="`${d.label} marks out of 20`"
                        @input="autoCalculate"
                      />
                      <span class="text-xs text-gray-400">/20</span>
                    </div>
                  </div>
                </li>
              </ul>
            </section>

            <!-- Remarks -->
            <section>
              <label class="block">
                <span class="m-label">Remarks</span>
                <textarea v-model="form.comment" rows="3" class="m-input resize-none" placeholder="How did the teacher do on duty this week?"></textarea>
              </label>
              <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand-700" :aria-expanded="showMoreDetails" @click="showMoreDetails = !showMoreDetails">
                <ChevronUp v-if="showMoreDetails" class="h-4 w-4" /><ChevronDown v-else class="h-4 w-4" />
                {{ showMoreDetails ? 'Hide' : 'Add' }} more details
                <span class="font-normal text-gray-400">(optional)</span>
              </button>
              <div v-if="showMoreDetails" class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="m-label">Areas to improve</span>
                  <textarea v-model="form.areas_of_improvement" rows="3" class="m-input resize-none"></textarea>
                </label>
                <label class="block">
                  <span class="m-label">General remarks</span>
                  <textarea v-model="form.general_remarks" rows="3" class="m-input resize-none"></textarea>
                </label>
                <label class="block sm:col-span-2">
                  <span class="m-label">Supervisor</span>
                  <input v-model="form.supervisor" type="text" class="m-input" placeholder="Name of the supervisor" />
                </label>
              </div>
            </section>

            <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
          </div>

          <footer class="flex flex-wrap items-center gap-3 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
            <div class="min-w-0 flex-1">
              <div class="flex items-baseline gap-2">
                <span class="text-xl font-bold tabular-nums text-gray-900">{{ form.total_score }}<span class="text-sm font-medium text-gray-400">/100</span></span>
                <span class="hidden truncate text-sm font-semibold sm:inline" :class="getStatusTextColor(form.percentage)">{{ form.status }}</span>
              </div>
              <div class="mt-1 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-gray-200">
                <div class="h-full rounded-full transition-all duration-300" :class="getProgressBarClass(form.percentage)" :style="{ width: form.percentage + '%' }"></div>
              </div>
            </div>
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeModal">Cancel</button>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || !form.teacher_id">
              <Check class="h-4 w-4" />{{ saving ? 'Saving…' : editingId ? 'Save changes' : 'Save evaluation' }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- View Modal -->
    <div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
      <div class="relative w-full max-w-4xl rounded-2xl bg-white shadow-2xl flex flex-col max-h-[90vh] ring-1 ring-white/20">
        <div class="absolute inset-0 rounded-2xl bg-brand-500/5 pointer-events-none"></div>
        <div class="relative border-b border-slate-200/60 bg-slate-50 px-8 py-6 flex-shrink-0">
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
            <div class="rounded-2xl border border-slate-200/60 bg-brand-50 p-6">
              <div class="grid gap-6 sm:grid-cols-4">
                <div class="text-center">
                  <div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500 text-2xl font-bold text-white shadow-lg mx-auto">
                    {{ viewData.teacher_name?.charAt(0) || '?' }}
                  </div>
                  <div class="font-bold text-slate-900 mt-3">{{ viewData.teacher_name }}</div>
                  <div class="text-sm text-slate-500">Teacher</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-brand-600">{{ viewData.total_score }}</div>
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
              <div class="card overflow-hidden">
                <table class="w-full">
                  <thead class="bg-slate-50">
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
                        <span class="inline-flex items-center rounded-xl bg-brand-100 px-4 py-2 font-bold text-brand-700">{{ viewData.punctuality }}/20</span>
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
                        <span class="inline-flex items-center rounded-xl bg-brand-100 px-4 py-2 font-bold text-brand-700">{{ viewData.supervision }}/20</span>
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
                        <span class="inline-flex items-center rounded-xl bg-brand-100 px-4 py-2 font-bold text-brand-700">{{ viewData.cleanliness }}/20</span>
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
                        <span class="inline-flex items-center rounded-xl bg-brand-100 px-4 py-2 font-bold text-brand-700">{{ viewData.time_keeping }}/20</span>
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
                        <span class="inline-flex items-center rounded-xl bg-brand-100 px-4 py-2 font-bold text-brand-700">{{ viewData.participation }}/20</span>
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
              <div v-if="viewData.comment" class="rounded-2xl border border-slate-200/60 bg-slate-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                  </svg>
                  Admin Remarks
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.comment }}</p>
              </div>
              <div v-if="viewData.areas_of_improvement" class="rounded-2xl border border-slate-200/60 bg-slate-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.5-5a5 5 0 00-5.072 0z" />
                  </svg>
                  Areas of Improvement
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.areas_of_improvement }}</p>
              </div>
              <div v-if="viewData.general_remarks" class="rounded-2xl border border-slate-200/60 bg-slate-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  General Remarks
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.general_remarks }}</p>
              </div>
              <div v-if="viewData.supervisor" class="rounded-2xl border border-slate-200/60 bg-slate-50 p-6">
                <h4 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                  <svg class="h-5 w-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  Supervisor
                </h4>
                <p class="text-slate-700 leading-relaxed">{{ viewData.supervisor }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="relative border-t border-slate-200/60 bg-slate-50 px-8 py-5 flex-shrink-0">
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
              class="rounded-2xl bg-brand-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-500/30 transition-all duration-200 hover:shadow-xl hover:shadow-brand-500/40 hover:scale-105"
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
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import { Plus, Trophy, RefreshCw, FileText, ClipboardList, Eye, Pencil, Printer, Trash2, Users as UsersIcon, Gauge, Star, X, Search, Check, ChevronDown, ChevronUp, AlarmClock, Utensils, Sparkles, ShieldCheck, CalendarHeart } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';

const router = useRouter();
const records = ref([]);
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref(1);
const filterWeek = ref('');
useGlobalTerm((y, t) => { filterYear.value = y; filterTerm.value = t; }, () => loadData());
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

const DUTY_FIELDS = [
  { key: 'punctuality', label: 'Time management & availability', hint: 'In school', icon: AlarmClock },
  { key: 'supervision', label: 'Meals supervision', hint: '', icon: Utensils },
  { key: 'cleanliness', label: 'Compound cleanliness', hint: '', icon: Sparkles },
  { key: 'time_keeping', label: 'Order & sanity', hint: 'In school', icon: ShieldCheck },
  { key: 'participation', label: 'School programs preparation', hint: 'Assembly, fellowship, mentorship and other programs', icon: CalendarHeart },
];
const showMoreDetails = ref(false);
const selectedTeacher = computed(() => teachersList.value.find((t) => String(t.id) === String(form.value.teacher_id)) || null);
const setAllScores = (n) => {
  DUTY_FIELDS.forEach((d) => { form.value[d.key] = n; });
  autoCalculate();
};

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

const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const rankClass = (index) => ['bg-amber-100 text-amber-800 ring-1 ring-amber-200', 'bg-gray-200 text-gray-700', 'bg-orange-100 text-orange-800'][index] || 'bg-gray-100 text-gray-600';
const num = (v) => Number(Number(v || 0).toFixed(1));
const shortStatus = (status) => String(status || '').replace(/ Performance$/, '');
const recordActions = (record) => [
  { label: 'View', icon: Eye, onClick: () => viewRecord(record) },
  { label: 'Edit', icon: Pencil, onClick: () => editRecord(record) },
  { label: 'Print', icon: Printer, onClick: () => printRecord(record) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteRecord(record.id) },
];
const summaryCards = computed(() => {
  const list = records.value;
  const pct = list.map((r) => Number(r.percentage) || 0);
  const avg = pct.length ? Math.round(pct.reduce((a, b) => a + b, 0) / pct.length) : 0;
  return [
    { label: 'Evaluations', value: list.length.toLocaleString(), icon: ClipboardList },
    { label: 'Teachers rated', value: new Set(list.map((r) => r.teacher_id || r.teacher_name)).size.toLocaleString(), icon: UsersIcon, tone: 'sky' },
    { label: 'Average score', value: pct.length ? avg + '%' : '-', icon: Gauge, tone: 'gray' },
    { label: 'Outstanding', value: pct.filter((p) => p >= 90).length.toLocaleString(), note: pct.filter((p) => p < 50).length + ' need improvement', icon: Star, tone: 'amber' },
  ];
});

const getStatusFromPercentage = (percentage) => {
  if (percentage >= 90) return 'Outstanding Performance';
  if (percentage >= 75) return 'Very Good Performance';
  if (percentage >= 50) return 'Satisfactory Performance';
  return 'Needs Improvement';
};

const getStatusBadgeClass = (percentage) => {
  if (percentage >= 90) return 'bg-emerald-100 text-emerald-800';
  if (percentage >= 75) return 'bg-brand-100 text-brand-800';
  if (percentage >= 50) return 'bg-amber-100 text-amber-800';
  return 'bg-rose-100 text-rose-800';
};

const getStatusTextColor = (percentage) => {
  if (percentage >= 90) return 'text-emerald-600';
  if (percentage >= 75) return 'text-brand-600';
  if (percentage >= 50) return 'text-amber-600';
  return 'text-rose-600';
};

const getProgressBarClass = (percentage) => {
  if (percentage >= 90) return 'bg-emerald-500';
  if (percentage >= 75) return 'bg-brand-500';
  if (percentage >= 50) return 'bg-amber-500';
  return 'bg-rose-500';
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const downloadPDF = () => {
  const doc = new jsPDF();

  doc.setFontSize(18);
  doc.text('Duty Performance Evaluation Report', 14, 22);

  doc.setFontSize(11);
  doc.setTextColor(100);
  const termLabel = filterTerm.value === '' ? 'All Terms' : `Term ${filterTerm.value}`;
  const weekLabel = filterWeek.value ? `Week ${filterWeek.value}` : 'All Weeks';
  doc.text(`Year: ${filterYear.value} | ${termLabel} | ${weekLabel}`, 14, 30);
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);

  let startY = 45;
  if (teacherOfTheWeek.value) {
    doc.setFontSize(11);
    doc.setTextColor(180, 130, 0);
    doc.text(
      `Top Performer: ${teacherOfTheWeek.value.teacher_name} - ${teacherOfTheWeek.value.total_score}/100 (${teacherOfTheWeek.value.percentage}%)`,
      14,
      startY
    );
    startY += 8;
  }

  const rankedForReport = [...filteredRecords.value].sort((a, b) => b.total_score - a.total_score);
  const tableData = rankedForReport.map((record, index) => [
    index + 1,
    record.teacher_name || '',
    record.week_number,
    `${record.total_score}/100`,
    `${record.percentage}%`,
    record.status,
    formatDate(record.created_at)
  ]);

  autoTable(doc, {
    head: [['Rank', 'Teacher', 'Week', 'Total Score', 'Percentage', 'Status', 'Date']],
    body: tableData,
    startY,
    styles: { fontSize: 9, cellPadding: 3 },
    headStyles: { fillColor: [30, 41, 59], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [245, 245, 245] },
    margin: { top: 10, right: 10, bottom: 10, left: 10 }
  });

  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(9);
    doc.setTextColor(150);
    doc.text(
      `Page ${i} of ${pageCount}`,
      doc.internal.pageSize.width / 2,
      doc.internal.pageSize.height - 10,
      { align: 'center' }
    );
  }

  doc.save(`duty-performance-${filterYear.value}-${termLabel.replace(/\s+/g, '-').toLowerCase()}-${new Date().toISOString().split('T')[0]}.pdf`);
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
    teacher_id: '', year: Number(filterYear.value) || new Date().getFullYear(), term: Number(filterTerm.value) || 1, week_number: Number(filterWeek.value) || 1,
    punctuality: 20, supervision: 20, cleanliness: 20, time_keeping: 20, participation: 20,
    total_score: 100, percentage: 100, status: 'Outstanding Performance', comment: '',
    areas_of_improvement: '', general_remarks: '', supervisor: ''
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  showMoreDetails.value = false;
  teacherSearch.value = '';
  error.value = '';
};

const editRecord = (record) => {
  editingId.value = record.id;
  form.value = { ...record };
  teacherSearch.value = '';
  showMoreDetails.value = !!(record.areas_of_improvement || record.general_remarks || record.supervisor);
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
        .header h1 { margin: 0; color: #065f46; }
        .info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-item { padding: 15px; background: #f3f4f6; border-radius: 8px; }
        .info-item label { display: block; font-weight: bold; margin-bottom: 5px; color: #6b7280; }
        .scores { margin-bottom: 30px; }
        .scores h3 { margin-bottom: 15px; color: #065f46; }
        .score-row { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #e5e7eb; }
        .summary { background: #047857; color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        .summary h3 { margin: 0 0 20px 0; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 2em; font-weight: bold; }
        .comment { background: #f9fafb; padding: 20px; border-radius: 8px; border-left: 4px solid #065f46; }
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
    error.value = (err?.response?.data?.message || 'An error occurred while saving');
    alert((err?.response?.data?.message || 'An error occurred while saving'));
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
    alert((err?.response?.data?.message || 'Failed to delete evaluation'));
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
  border-color: rgb(5 150 105);
  box-shadow: 0 0 0 4px rgb(220 252 231);
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
  border-color: #059669;
  background-color: #ecfdf5;
  box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.1);
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

<style scoped>
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.filter-select { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.m-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.m-input { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.score-range { @apply h-1.5 cursor-pointer accent-brand-600; }
</style>

<style>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .header h1 {
  color: var(--d-text);
}
html.dark .info-item {
  background-color: var(--d-sunken);
}
html.dark .scores h3 {
  color: var(--d-text);
}
html.dark .score-row {
  border-bottom-color: var(--d-line);
}
html.dark .comment {
  background-color: var(--d-surface);
}
html.dark .input {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .input:hover:not(:focus) {
  border-color: var(--d-line);
}
html.dark input[type="radio"]:checked + div {
  background-color: var(--d-sunken);
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
html.dark .m-label {
  color: var(--d-muted);
}
html.dark .m-input {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
}
</style>
