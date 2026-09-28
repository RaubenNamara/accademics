<template>
  <div class="space-y-8">
    <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-5 text-white shadow-2xl sm:p-7">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(255,255,255,0.14),transparent_28%)]"></div>

      <div class="relative z-10 flex justify-end">
        <div class="flex flex-wrap justify-end gap-3">
          <button
            @click="loadData"
            class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8 8 0 014.582 9m0 0H9m11 11v-5h-.581m0 0A8 8 0 019.418 15m15.356 2H15" />
            </svg>
            Refresh
          </button>

          <button
            @click="openAddModal"
            class="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-extrabold text-blue-700 shadow-xl shadow-black/10 transition hover:bg-blue-50"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Record
          </button>

          <button
            @click="showReports = !showReports"
            :class="showReports ? 'bg-white text-blue-700' : 'bg-white/10 text-white border border-white/20'"
            class="inline-flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-semibold backdrop-blur transition hover:bg-white/20"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Reports
          </button>
        </div>
      </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
      <div class="stat-card">
        <div class="stat-icon bg-rose-50 text-rose-600"><i class="fas fa-hourglass-half"></i></div>
        <div class="min-w-0">
          <div class="stat-label">Time Lost</div>
          <div class="stat-value text-rose-600">{{ formatMinutes(summary.total_minutes_lost) }}</div>
          <div class="stat-sub">
            ≈ {{ summary.equivalent_single_lessons || 0 }} single · {{ summary.equivalent_double_lessons || 0 }} double lessons
          </div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon bg-teal-50 text-teal-600"><i class="fas fa-rotate-left"></i></div>
        <div class="min-w-0">
          <div class="stat-label">Time Compensated</div>
          <div class="stat-value text-teal-600">{{ formatMinutes(summary.compensated_minutes) }}</div>
          <div class="stat-sub">
            {{ summary.compensated_lessons || 0 }} {{ summary.compensated_lessons === 1 ? 'lesson' : 'lessons' }} compensated
          </div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon bg-slate-100 text-slate-600"><i class="fas fa-list-check"></i></div>
        <div class="min-w-0">
          <div class="stat-label">Records</div>
          <div class="stat-value text-slate-900">{{ summary.records || 0 }}</div>
          <div class="stat-sub">lessons monitored</div>
        </div>
      </div>
    </div>

    <!-- Reports Modal -->
    <Teleport to="body">
      <div
        v-if="showReports"
        class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 backdrop-blur-sm sm:items-center sm:p-4"
        @click.self="showReports = false"
      >
        <div
          class="flex h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:rounded-3xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="reports-title"
        >
          <!-- Header -->
          <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-4 py-4 sm:px-6">
            <div class="min-w-0">
              <div class="text-[11px] font-bold uppercase tracking-wider text-blue-600">Lesson Monitoring</div>
              <h2 id="reports-title" class="text-lg font-extrabold text-slate-900 sm:text-xl">Reports</h2>
              <p class="mt-0.5 text-xs text-slate-500">{{ reportScope }}</p>
            </div>
            <button
              type="button"
              @click="showReports = false"
              class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
              aria-label="Close reports"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Report type tabs -->
          <div class="border-b border-slate-100 px-4 py-3 sm:px-6">
            <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
              <button
                v-for="(cfg, key) in reportConfig"
                :key="key"
                type="button"
                @click="reportType = key"
                class="flex items-center gap-2.5 rounded-xl border px-3 py-2.5 text-left transition"
                :class="reportType === key
                  ? (cfg.tone === 'rose' ? 'border-rose-300 bg-rose-50 ring-1 ring-rose-200' : 'border-emerald-300 bg-emerald-50 ring-1 ring-emerald-200')
                  : 'border-slate-200 bg-white hover:bg-slate-50'"
              >
                <span
                  class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-sm"
                  :class="cfg.tone === 'rose' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'"
                >
                  <i :class="cfg.icon"></i>
                </span>
                <span class="min-w-0">
                  <span class="block truncate text-xs font-bold text-slate-900 sm:text-sm">{{ cfg.tab }}</span>
                  <span class="block truncate text-[11px] text-slate-500">{{ cfg.tabSub }}</span>
                </span>
              </button>
            </div>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
              <div>
                <h3 class="text-base font-extrabold text-slate-900">{{ activeReport.title }}</h3>
                <p class="text-xs text-slate-500">{{ activeReport.description }}</p>
              </div>
              <div v-if="reportRows.length" class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">
                  {{ reportRows.length }} {{ activeReport.entityPlural }}
                </span>
                <span class="rounded-full bg-rose-50 px-2.5 py-1 font-semibold text-rose-700">
                  {{ formatMinutes(reportTotals.minutes) }} lost
                </span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">
                  {{ reportTotals.lessons.toLocaleString() }} lessons
                </span>
              </div>
            </div>

            <template v-if="reportRows.length">
              <!-- Desktop / tablet table -->
              <div class="hidden overflow-hidden rounded-2xl border border-slate-200 md:block">
                <table class="w-full text-sm">
                  <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                      <th class="w-12 px-4 py-2.5">#</th>
                      <th class="px-4 py-2.5">{{ activeReport.entity }}</th>
                      <th class="w-56 px-4 py-2.5">Time lost</th>
                      <th class="w-20 px-4 py-2.5 text-right">Lessons</th>
                      <th class="w-32 px-4 py-2.5 text-right">{{ activeReport.metricLabel }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in reportRows" :key="row.key" class="align-top hover:bg-slate-50">
                      <td class="px-4 py-3">
                        <span
                          class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                          :class="row.rank <= 3 ? (activeReport.tone === 'rose' ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white') : 'bg-slate-100 text-slate-600'"
                        >{{ row.rank }}</span>
                      </td>
                      <td class="px-4 py-3">
                        <div class="font-bold text-slate-900">{{ row.name }}</div>
                        <div class="text-xs text-slate-500">{{ row.sub }}</div>
                        <div v-if="row.notes" class="mt-1 text-[11px] leading-snug text-slate-400">{{ row.notes }}</div>
                      </td>
                      <td class="px-4 py-3">
                        <div class="font-extrabold" :class="activeReport.tone === 'rose' ? 'text-rose-600' : 'text-emerald-600'">
                          {{ formatMinutes(row.minutes) }}
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                          <div
                            class="h-full rounded-full"
                            :class="activeReport.tone === 'rose' ? 'bg-rose-400' : 'bg-emerald-400'"
                            :style="{ width: `${reportBarPct(row)}%` }"
                          ></div>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ row.lessons }}</td>
                      <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ row.metric }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Phone cards -->
              <ol class="space-y-2 md:hidden">
                <li v-for="row in reportRows" :key="row.key" class="rounded-xl border border-slate-200 p-3">
                  <div class="flex items-start gap-3">
                    <span
                      class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold"
                      :class="row.rank <= 3 ? (activeReport.tone === 'rose' ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white') : 'bg-slate-100 text-slate-600'"
                    >{{ row.rank }}</span>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                          <div class="font-bold text-slate-900">{{ row.name }}</div>
                          <div class="text-xs text-slate-500">{{ row.sub }} · {{ row.lessons }} lessons</div>
                        </div>
                        <div class="whitespace-nowrap text-right">
                          <div class="text-sm font-extrabold" :class="activeReport.tone === 'rose' ? 'text-rose-600' : 'text-emerald-600'">
                            {{ formatMinutes(row.minutes) }}
                          </div>
                          <div class="text-[11px] text-slate-500">{{ row.metric }}</div>
                        </div>
                      </div>
                      <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div
                          class="h-full rounded-full"
                          :class="activeReport.tone === 'rose' ? 'bg-rose-400' : 'bg-emerald-400'"
                          :style="{ width: `${reportBarPct(row)}%` }"
                        ></div>
                      </div>
                      <div v-if="row.notes" class="mt-1.5 text-[11px] leading-snug text-slate-400">{{ row.notes }}</div>
                    </div>
                  </div>
                </li>
              </ol>
            </template>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-12 text-center">
              <div class="text-sm font-semibold text-slate-700">No data for this report</div>
              <div class="mt-1 text-xs text-slate-500">{{ activeReport.emptyHint }}</div>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-4 py-3 sm:px-6">
            <span class="hidden text-xs text-slate-500 sm:block">PDF includes the summary and the full ranked table.</span>
            <div class="ml-auto flex gap-2">
              <button
                type="button"
                @click="showReports = false"
                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
              >
                Close
              </button>
              <button
                type="button"
                @click="printReport"
                :disabled="reportRows.length === 0"
                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <i class="fas fa-file-pdf"></i>
                Download PDF
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Top Classes by Minutes Lost -->
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
      <button
        type="button"
        @click="isAnalyticsCollapsed = !isAnalyticsCollapsed"
        class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-50"
        :aria-expanded="!isAnalyticsCollapsed"
      >
        <div class="flex items-center gap-3">
          <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
            <i class="fas fa-chart-bar"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-slate-900">Top Classes by Minutes Lost</h3>
            <p class="text-xs text-slate-500">
              <template v-if="topClassesByMinutesLost.length">
                Top {{ topClassesByMinutesLost.length }} classes · {{ formatMinutes(topClassesTotalLost) }} lost in total
              </template>
              <template v-else>No lost time recorded for these filters</template>
            </p>
          </div>
        </div>
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-5 w-5 flex-shrink-0 text-slate-400 transition-transform duration-200"
          :class="{ 'rotate-180': !isAnalyticsCollapsed }"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div v-if="!isAnalyticsCollapsed" class="border-t border-slate-100">
        <ol v-if="topClassesByMinutesLost.length > 0" class="divide-y divide-slate-100">
          <li
            v-for="(item, index) in visibleTopClasses"
            :key="`${item.class}_${item.stream}`"
            class="grid grid-cols-[2rem_1fr_auto] items-center gap-x-3 gap-y-2 px-5 py-3 sm:grid-cols-[2rem_12rem_1fr_7rem]"
          >
            <span
              class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
              :class="index < 3 ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600'"
            >
              {{ index + 1 }}
            </span>

            <div class="min-w-0">
              <div class="truncate text-sm font-bold text-slate-900">
                {{ item.class }}<span v-if="item.stream" class="font-semibold text-slate-500"> · {{ item.stream }}</span>
              </div>
              <div class="text-[11px] text-slate-500">
                {{ item.total_lessons }} {{ item.total_lessons === 1 ? 'lesson' : 'lessons' }} ·
                {{ item.teachers.length }} {{ item.teachers.length === 1 ? 'teacher' : 'teachers' }}
              </div>
            </div>

            <div class="col-span-3 col-start-1 row-start-2 h-2 overflow-hidden rounded-full bg-slate-100 sm:col-span-1 sm:col-start-3 sm:row-start-1">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="index < 3 ? 'bg-rose-500' : 'bg-rose-300'"
                :style="{ width: `${topClassesMaxLost ? (item.total_minutes_lost / topClassesMaxLost) * 100 : 0}%` }"
              ></div>
            </div>

            <div class="text-right sm:col-start-4 sm:row-start-1">
              <div class="text-sm font-extrabold text-slate-900">{{ formatMinutes(item.total_minutes_lost) }}</div>
              <div class="text-[11px] text-slate-500">≈ {{ Math.floor(item.total_minutes_lost / 40) }} single lessons</div>
            </div>
          </li>
        </ol>

        <div v-else class="px-5 py-10 text-center text-sm text-slate-500">
          No class data for the selected filters.
        </div>

        <div v-if="topClassesByMinutesLost.length > 5" class="border-t border-slate-100 px-5 py-3 text-center">
          <button
            type="button"
            @click="showAllTopClasses = !showAllTopClasses"
            class="text-sm font-semibold text-blue-700 hover:text-blue-800"
          >
            {{ showAllTopClasses ? 'Show top 5 only' : `Show all ${topClassesByMinutesLost.length}` }}
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-[2rem] border border-slate-200 bg-white/90 p-5 shadow-xl shadow-slate-200/60 backdrop-blur">
      <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <div>
          <label class="filter-label">Year</label>
          <select v-model="filterYear" @change="loadData" class="input">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div>
          <label class="filter-label">Term</label>
          <select v-model="filterTerm" @change="loadData" class="input">
            <option value="">All Terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </div>

        <div>
          <label class="filter-label">Week</label>
          <select v-model="filterWeek" @change="loadData" class="input">
            <option value="">All Weeks</option>
            <option v-for="w in 15" :key="w" :value="w">Week {{ w }}</option>
          </select>
        </div>

        <div>
          <label class="filter-label">Teacher</label>
          <select v-model="filterTeacher" @change="loadData" class="input">
            <option value="">All Teachers</option>
            <option v-for="t in teachersList" :key="t.id" :value="t.id">{{ t.full_name }}</option>
          </select>
        </div>
      </div>
    </div>

    <div class="space-y-5">
      <div
        v-for="group in groupedRecords"
        :key="group.group_key"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:rounded-3xl"
      >
        <!-- Teacher header strip -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-3 py-3 sm:px-5">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
            <!-- Name -->
            <div class="order-1 flex min-w-0 flex-1 items-center gap-3">
              <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/20 text-lg font-bold text-white ring-1 ring-white/30">
                {{ group.teacher_name?.charAt(0) || 'T' }}
              </div>
              <div class="min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-wider text-white/70">Teacher</div>
                <div class="truncate text-base font-extrabold text-white sm:text-lg">{{ group.teacher_name || '-' }}</div>
              </div>
            </div>

            <!-- Stats: own full-width row on phones, inline from tablet up -->
            <div class="order-3 grid w-full grid-cols-3 gap-2 md:order-2 md:w-auto">
              <div class="teacher-stat">
                <div class="teacher-stat-value">{{ group.subjects_classes.length }}</div>
                <div class="teacher-stat-label">{{ group.subjects_classes.length === 1 ? 'Class' : 'Classes' }}</div>
              </div>
              <div class="teacher-stat">
                <div class="teacher-stat-value">{{ group.total_lessons }}</div>
                <div class="teacher-stat-label">Lessons</div>
              </div>
              <div class="teacher-stat">
                <div class="teacher-stat-value">{{ formatMinutes(group.total_minutes_lost) }}</div>
                <div class="teacher-stat-label">Time lost</div>
              </div>
            </div>

            <!-- Actions -->
            <div class="order-2 flex flex-shrink-0 items-center gap-2 md:order-3">
              <button
                @click="openTeacherView(group)"
                class="teacher-action"
                title="View attendance"
                aria-label="View attendance"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0A9 9 0 1112 3a9 9 0 019 9zm-9 0a3 3 0 110-6 3 3 0 010 6z" />
                </svg>
              </button>

              <button
                @click="openQuickAddModal(group)"
                class="teacher-action"
                title="Add attendance"
                aria-label="Add attendance"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Subject & Class Details Section -->
        <div class="p-3 sm:p-5">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
              <h3 class="text-base font-extrabold text-slate-900">Subject & Class Details:</h3>
              <p class="text-xs text-slate-500">
                {{ group.subjects_classes.length }} {{ group.subjects_classes.length === 1 ? 'class' : 'classes' }} ·
                Year {{ group.year }} ·
                <span class="font-extrabold text-emerald-600">{{ filterTerm ? `Term ${filterTerm}` : 'All terms' }}</span>
              </p>
            </div>

            <button
              v-if="group.subjects_classes.length > 1"
              type="button"
              @click="toggleAllClasses(group)"
              class="rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-50"
            >
              {{ allClassesOpen(group) ? 'Collapse all' : 'Expand all' }}
            </button>
          </div>

          <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200">
            <div v-for="subjectClass in group.subjects_classes" :key="subjectClass.key">
              <!-- Class row (click to expand) -->
              <button
                type="button"
                @click="toggleClass(group, subjectClass)"
                class="flex w-full items-center gap-3 px-3 py-3 text-left transition hover:bg-slate-50 sm:px-4"
                :class="{ 'bg-slate-50': isClassOpen(group, subjectClass) }"
                :aria-expanded="isClassOpen(group, subjectClass)"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4 flex-shrink-0 text-slate-400 transition-transform duration-200"
                  :class="{ 'rotate-90': isClassOpen(group, subjectClass) }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>

                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="text-base font-extrabold text-slate-900">
                      {{ subjectClass.class || '-' }}<span v-if="subjectClass.stream" class="text-slate-500"> · {{ subjectClass.stream }}</span>
                    </span>
                    <span class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                      {{ subjectClass.subject || '-' }}
                    </span>
                  </div>
                  <div class="mt-0.5 text-xs text-slate-500">
                    {{ subjectClass.total_lessons }} {{ subjectClass.total_lessons === 1 ? 'lesson' : 'lessons' }} ·
                    {{ subjectClass.weeks.length }} {{ subjectClass.weeks.length === 1 ? 'week' : 'weeks' }}
                  </div>
                </div>

                <div class="flex-shrink-0 text-right">
                  <div
                    class="text-sm font-extrabold"
                    :class="subjectClass.total_minutes_lost > 0 ? 'text-rose-600' : 'text-emerald-600'"
                  >
                    {{ subjectClass.total_minutes_lost > 0 ? formatMinutes(subjectClass.total_minutes_lost) : 'No time lost' }}
                  </div>
                  <div v-if="subjectClass.total_minutes_lost > 0" class="text-[11px] text-slate-500">lost</div>
                </div>
              </button>

              <!-- Lessons, grouped by week -->
              <div v-if="isClassOpen(group, subjectClass)" class="border-t border-slate-100 bg-slate-50/70 px-3 py-4 sm:px-5">
                <!-- One-line class summary -->
                <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                  <span><span class="font-extrabold text-slate-900">{{ classStats(subjectClass).taughtPct }}%</span> of lesson time taught</span>
                  <span v-if="classStats(subjectClass).pending > 0">
                    <span class="font-extrabold text-amber-600">{{ classStats(subjectClass).pending }}</span>
                    {{ classStats(subjectClass).pending === 1 ? 'lesson' : 'lessons' }} to compensate
                  </span>
                  <span v-else class="font-semibold text-emerald-600">All settled</span>
                </div>

                <!-- Week picker: colour shows how the week went, number is minutes lost -->
                <div class="mb-3 flex gap-1.5 overflow-x-auto pb-1" role="tablist" aria-label="Weeks">
                  <button
                    v-for="week in subjectClass.weeks"
                    :key="week.week_number"
                    type="button"
                    role="tab"
                    :aria-selected="String(selectedWeek(group, subjectClass)) === String(week.week_number)"
                    @click="selectWeek(group, subjectClass, week.week_number)"
                    class="flex w-14 flex-shrink-0 flex-col items-center rounded-lg border px-1 py-1 transition"
                    :class="String(selectedWeek(group, subjectClass)) === String(week.week_number)
                      ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                      : weekTone(week).chip"
                    :title="`Week ${week.week_number} · ${weekTone(week).label}${weekStats(week).lost ? ` · ${weekStats(week).lost} min lost` : ''}`"
                  >
                    <span class="text-[10px] font-semibold uppercase opacity-80">Wk {{ week.week_number }}</span>
                    <span class="text-xs font-extrabold">{{ weekStats(week).lost > 0 ? weekStats(week).lost : '✓' }}</span>
                  </button>
                  <button
                    v-if="subjectClass.weeks.length > 1"
                    type="button"
                    role="tab"
                    :aria-selected="selectedWeek(group, subjectClass) === 'all'"
                    @click="selectWeek(group, subjectClass, 'all')"
                    class="flex flex-shrink-0 flex-col items-center justify-center rounded-lg border px-3 py-1 transition"
                    :class="selectedWeek(group, subjectClass) === 'all'
                      ? 'border-slate-900 bg-slate-900 text-white shadow-sm'
                      : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                    title="Overview of every week"
                  >
                    <span class="text-[10px] font-semibold uppercase opacity-80">All</span>
                    <span class="text-xs font-extrabold">weeks</span>
                  </button>
                </div>

                <!-- All weeks: one compact row per week, click to open it -->
                <div
                  v-if="selectedWeek(group, subjectClass) === 'all'"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                  <button
                    v-for="week in subjectClass.weeks"
                    :key="week.week_number"
                    type="button"
                    @click="selectWeek(group, subjectClass, week.week_number)"
                    class="grid w-full grid-cols-[4.5rem_1fr_auto_1rem] items-center gap-3 border-b border-slate-100 px-3 py-2.5 text-left text-sm transition last:border-0 hover:bg-blue-50/50 sm:grid-cols-[5rem_4.5rem_1fr_5.5rem_9.5rem_1rem] sm:px-4"
                  >
                    <span class="font-bold text-slate-900">Week {{ week.week_number }}</span>
                    <span class="hidden text-xs text-slate-500 sm:block">{{ plural(weekStats(week).lessons, 'lesson', 'lessons') }}</span>
                    <span class="flex min-w-0 items-center gap-2">
                      <span class="h-1.5 min-w-[3rem] flex-1 overflow-hidden rounded-full bg-slate-100">
                        <span
                          class="block h-full rounded-full"
                          :class="weekStats(week).taughtPct >= 100 ? 'bg-emerald-500' : weekStats(week).taughtPct >= 90 ? 'bg-amber-400' : 'bg-rose-500'"
                          :style="{ width: `${weekStats(week).taughtPct}%` }"
                        ></span>
                      </span>
                      <span class="w-9 text-right text-xs font-semibold text-slate-600">{{ weekStats(week).taughtPct }}%</span>
                    </span>
                    <span class="whitespace-nowrap text-right font-extrabold" :class="weekStats(week).lost > 0 ? 'text-rose-600' : 'text-emerald-600'">
                      {{ weekStats(week).lost > 0 ? `${weekStats(week).lost} min` : '✓' }}
                    </span>
                    <span class="hidden sm:block">
                      <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="weekTone(week).badge">
                        {{ weekTone(week).label }}
                      </span>
                    </span>
                    <span class="text-slate-400">›</span>
                  </button>
                </div>

                <!-- Selected week's lessons -->
                <section
                  v-else-if="selectedWeekData(group, subjectClass)"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                  <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-2 sm:px-4">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                      <span class="text-sm font-extrabold text-slate-900">Week {{ selectedWeekData(group, subjectClass).week_number }}</span>
                      <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="weekTone(selectedWeekData(group, subjectClass)).badge">
                        {{ weekTone(selectedWeekData(group, subjectClass)).label }}
                      </span>
                      <span class="text-xs text-slate-500">
                        {{ plural(weekStats(selectedWeekData(group, subjectClass)).lessons, 'lesson', 'lessons') }}
                      </span>
                    </div>
                    <div class="flex flex-shrink-0 gap-1">
                      <button
                        type="button"
                        @click="stepWeek(group, subjectClass, -1)"
                        :disabled="!canStepWeek(group, subjectClass, -1)"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-30"
                        aria-label="Previous week"
                        title="Previous week"
                      >‹</button>
                      <button
                        type="button"
                        @click="stepWeek(group, subjectClass, 1)"
                        :disabled="!canStepWeek(group, subjectClass, 1)"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50 disabled:opacity-30"
                        aria-label="Next week"
                        title="Next week"
                      >›</button>
                    </div>
                  </header>

                    <ul class="divide-y divide-slate-100">
                      <li
                        v-for="entry in selectedWeekData(group, subjectClass).entries"
                        :key="entry.id"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-3 transition hover:bg-slate-50 sm:px-4"
                      >
                        <!-- Date tile -->
                        <div class="w-14 flex-shrink-0 rounded-lg border border-slate-200 bg-white py-1 text-center">
                          <div class="text-[10px] font-bold uppercase text-blue-600">{{ entry.day_of_week || '-' }}</div>
                          <div class="text-lg font-extrabold leading-none text-slate-900">{{ dateParts(entry.attendance_date).day }}</div>
                          <div class="text-[10px] font-semibold uppercase text-slate-500">{{ dateParts(entry.attendance_date).month }}</div>
                        </div>

                        <!-- Time slot + taught bar -->
                        <div class="min-w-0 flex-1 sm:min-w-[11rem]">
                          <div class="text-sm font-bold text-slate-800">
                            {{ shortTime(entry.time_in) }} – {{ shortTime(entry.time_out) }}
                          </div>
                          <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <div class="h-1.5 min-w-[6rem] max-w-[14rem] flex-1 overflow-hidden rounded-full bg-slate-100">
                              <div
                                class="h-full rounded-full"
                                :class="taughtBarClass(entry)"
                                :style="{ width: `${taughtPct(entry)}%` }"
                              ></div>
                            </div>
                            <span class="whitespace-nowrap text-[11px] text-slate-500">
                              {{ entry.actual_minutes }} of {{ entry.expected_minutes }} min taught
                            </span>
                          </div>
                        </div>

                        <!-- Lost, status, actions: one row under the time on phones -->
                        <div class="flex w-full flex-wrap items-center gap-2 border-t border-dashed border-slate-100 pt-2 sm:w-auto sm:flex-nowrap sm:gap-3 sm:border-0 sm:pt-0">
                        <!-- Lost minutes (blank when the lesson was fully taught; the status says Complete) -->
                        <div class="whitespace-nowrap text-sm sm:w-24 sm:text-right">
                          <template v-if="entry.minutes_lost > 0">
                            <span class="font-extrabold text-rose-600">{{ entry.minutes_lost }} min</span>
                            <span class="text-xs text-slate-500"> lost</span>
                          </template>
                        </div>

                        <!-- Status -->
                        <span
                          class="inline-flex justify-center whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 sm:w-36"
                          :class="entryStatus(entry).cls"
                        >
                          {{ entryStatus(entry).label }}
                        </span>

                        <!-- Actions -->
                        <div class="ml-auto flex gap-1">
                                <button
                                  v-if="entry.minutes_lost > 0"
                                  @click="openCompensationModal(entry)"
                                  class="table-icon-btn border-emerald-200 bg-emerald-50 text-emerald-700"
                                  title="Record Time Regained"
                                  aria-label="Record Time Regained"
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                  </svg>
                                </button>

                                <button
                                  @click="editRecord(entry)"
                                  class="table-icon-btn border-blue-200 bg-blue-50 text-blue-700"
                                  title="Edit record"
                                  aria-label="Edit record"
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                  </svg>
                                </button>

                                <button
                                  @click="deleteRecord(entry.id)"
                                  class="table-icon-btn border-rose-200 bg-rose-50 text-rose-700"
                                  title="Delete record"
                                  aria-label="Delete record"
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                  </svg>
                                </button>
                        </div>
                        </div>
                      </li>
                    </ul>
                </section>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="groupedRecords.length === 0"
        class="rounded-[2rem] border border-dashed border-slate-300 bg-white py-24 text-center"
      >
        <div class="text-slate-500">No attendance records found.</div>
      </div>
    </div>

    <div
      v-if="showViewModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
    >
      <div class="flex max-h-[92vh] w-full max-w-7xl flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-white shadow-2xl">
        <div class="border-b border-slate-200 bg-gradient-to-r from-slate-50 to-sky-50 px-6 py-5 sm:px-8 sm:py-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-xl font-extrabold text-slate-900 sm:text-2xl">
                {{ viewingTeacher?.name || 'Teacher' }} Attendance View
              </h3>
              <p class="mt-2 text-sm text-slate-600">
                Showing lesson attendance details across classes, streams, dates, and times.
              </p>
            </div>

            <button
              @click="closeTeacherView"
              class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
            >
              Close
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-6 sm:px-8">
          <div v-if="viewingTeacherLoading" class="py-20 text-center text-slate-500">
            Loading teacher details...
          </div>

          <div v-else>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold text-slate-500">Total Lessons</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ viewingTeacherSummary.lessons }}</div>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold text-slate-500">Total Minutes Lost</div>
                <div class="mt-1 text-2xl font-extrabold text-rose-600">{{ viewingTeacherSummary.total_minutes_lost }}</div>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold text-slate-500">Expected Minutes</div>
                <div class="mt-1 text-2xl font-extrabold text-blue-600">{{ viewingTeacherSummary.total_expected_minutes }}</div>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold text-slate-500">Actual Minutes</div>
                <div class="mt-1 text-2xl font-extrabold text-emerald-600">{{ viewingTeacherSummary.total_actual_minutes }}</div>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold text-slate-500">Classes / Streams</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">
                  {{ viewingTeacherSummary.classes_count }} / {{ viewingTeacherSummary.streams_count }}
                </div>
              </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200">
              <div class="overflow-x-auto">
                <table class="min-w-[1400px] w-full divide-y divide-slate-200">
                  <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                      <th class="px-6 py-4">Week</th>
                      <th class="px-6 py-4">Date</th>
                      <th class="px-6 py-4">Day</th>
                      <th class="px-6 py-4">Subject</th>
                      <th class="px-6 py-4">Class</th>
                      <th class="px-6 py-4">Stream</th>
                      <th class="px-6 py-4">Time In</th>
                      <th class="px-6 py-4">Time Out</th>
                      <th class="px-6 py-4">Expected</th>
                      <th class="px-6 py-4">Actual</th>
                      <th class="px-6 py-4">Lost</th>
                      <th class="px-6 py-4">Status</th>
                    </tr>
                  </thead>

                  <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-if="viewingTeacherRecords.length === 0">
                      <td colspan="12" class="px-6 py-16 text-center text-slate-500">
                        No attendance records found for this teacher.
                      </td>
                    </tr>

                    <tr v-for="r in viewingTeacherRecords" :key="r.id" class="hover:bg-slate-50">
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">Week {{ r.week_number }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.attendance_date }}</td>
                      <td class="px-6 py-4 text-sm font-bold text-blue-600">{{ r.day_of_week || '-' }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.subject }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.class }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.stream }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.time_in }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.time_out }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.expected_minutes }}</td>
                      <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ r.actual_minutes }}</td>
                      <td class="px-6 py-4">
                        <span
                          class="inline-flex rounded-full px-4 py-1.5 text-sm font-bold ring-1"
                          :class="r.minutes_lost > 0
                            ? 'bg-rose-50 text-rose-700 ring-rose-200'
                            : 'bg-emerald-50 text-emerald-700 ring-emerald-200'"
                        >
                          {{ r.minutes_lost }}
                        </span>
                      </td>
                      <td class="px-6 py-4">
                        <span
                          class="inline-flex rounded-full px-4 py-1.5 text-sm font-bold ring-1"
                          :class="r.minutes_lost === 0
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                            : r.compensation_status === 'Fully Compensated'
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                            : r.compensation_status === 'Partially Compensated'
                            ? 'bg-amber-50 text-amber-700 ring-amber-200'
                            : 'bg-rose-50 text-rose-700 ring-rose-200'"
                        >
                          {{ r.minutes_lost === 0 ? 'Complete' : (r.compensation_status || 'Not Compensated') }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="flex max-h-[90vh] w-full max-w-6xl flex-col rounded-3xl border border-slate-200/60 bg-white shadow-2xl">
        <div class="flex-shrink-0 border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-blue-50 px-6 py-5 sm:px-8 sm:py-6">
          <h3 class="text-lg font-bold text-slate-900 sm:text-xl">
            {{ formMode === 'quick' ? 'Add Attendance for Existing Teacher' : (editingId ? 'Edit Monitoring Record' : 'Add Monitoring Record') }}
          </h3>
          <p class="mt-2 text-sm text-slate-600">
            <span v-if="formMode === 'quick'">
              Only week, date, time in, and time out are required. Teacher details are copied from the selected group.
            </span>
            <span v-else>
              Enter attendance details. Minutes lost are calculated automatically.
            </span>
          </p>
        </div>

        <form @submit.prevent="saveRecord" class="flex flex-1 flex-col overflow-hidden">
          <div class="flex-1 space-y-8 overflow-y-auto px-6 py-6 sm:px-8">
            <div v-if="formMode === 'quick'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
              <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Teacher</div>
                  <div class="mt-1 font-bold text-slate-900">{{ quickTeacherLabel }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Year</div>
                  <div class="mt-1 font-bold text-slate-900">{{ form.year }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Term</div>
                  <div class="mt-1 font-bold text-slate-900">Term {{ form.term }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</div>
                  <div class="mt-1 font-bold text-blue-600">Quick Add Mode</div>
                </div>
              </div>
              <div class="mt-3 text-sm text-slate-600">
                Teacher is pre-selected. Please select subject, class, and stream below.
              </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
              <div v-if="formMode !== 'quick'">
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
                  <option value="">Select teacher</option>
                  <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Subject *</label>
                <select v-model="form.subject" required class="input">
                  <option value="">Select subject</option>
                  <option v-for="sub in subjects" :key="sub" :value="sub">{{ sub }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Class *</label>
                <select v-model="form.class" required class="input">
                  <option value="">Select class</option>
                  <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Stream *</label>
                <select v-model="form.stream" required class="input">
                  <option value="">Select stream</option>
                  <option v-for="s in streams" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Week *</label>
                <select v-model="form.week_number" required class="input">
                  <option value="">Select week</option>
                  <option v-for="w in 15" :key="w" :value="w">Week {{ w }}</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Attendance Date *</label>
                <input v-model="form.attendance_date" type="date" required class="input" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Day</label>
                <input v-model="form.day_of_week" type="text" readonly class="input bg-slate-100 font-bold text-blue-600" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Time In *</label>
                <input v-model="form.time_in" type="time" required class="input" @change="autoCalculate" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Time Out *</label>
                <input v-model="form.time_out" type="time" required class="input" @change="autoCalculate" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Expected Minutes *</label>
                <input v-model.number="form.expected_minutes" type="number" min="0" required class="input" @input="autoCalculate" />
              </div>

              <div v-if="formMode !== 'quick'">
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year *</label>
                <input v-model.number="form.year" type="number" required class="input" />
              </div>

              <div v-if="formMode !== 'quick'">
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Term *</label>
                <select v-model="form.term" required class="input">
                  <option :value="1">Term 1</option>
                  <option :value="2">Term 2</option>
                  <option :value="3">Term 3</option>
                </select>
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Actual Minutes</label>
                <input v-model="form.actual_minutes" readonly class="input bg-slate-100 font-bold" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Minutes Lost</label>
                <input v-model="form.minutes_lost" readonly class="input bg-slate-100 font-bold" />
              </div>
            </div>

            <div v-if="error" class="rounded-2xl border-2 border-rose-200 bg-gradient-to-r from-rose-50 to-pink-50 px-6 py-4 text-sm font-bold text-rose-700">
              {{ error }}
            </div>
          </div>

          <div class="flex-shrink-0 border-t border-slate-200/60 bg-white px-6 py-5 sm:px-8">
            <div class="flex items-center justify-end gap-3">
              <button
                type="button"
                @click="showModal = false"
                class="inline-flex items-center gap-2 rounded-2xl border-2 border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition-all hover:bg-slate-50"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Cancel
              </button>
              <button
                type="submit"
                :disabled="saving"
                class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-700 to-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-500/25 transition hover:shadow-blue-500/35 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <svg v-if="!saving" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ saving ? 'Saving...' : (editingId ? 'Update Record' : 'Save Record') }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Compensation Modal -->
    <div
      v-if="showCompensationModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-3xl border border-slate-200/60 bg-white shadow-2xl">
        <div class="flex-shrink-0 border-b border-slate-200/60 bg-gradient-to-r from-emerald-50 to-teal-50 px-6 py-5 sm:px-8 sm:py-6">
          <h3 class="text-lg font-bold text-slate-900 sm:text-xl">
            Record Time Regained
          </h3>
          <p class="mt-2 text-sm text-slate-600">
            Record compensation for missed lesson time.
          </p>
        </div>

        <form @submit.prevent="saveCompensation" class="flex flex-1 flex-col overflow-hidden">
          <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6 sm:px-8">
            <!-- Read-only fields -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
              <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Teacher</div>
                  <div class="mt-1 font-bold text-slate-900">{{ compensationForm.teacher_name }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</div>
                  <div class="mt-1 font-bold text-slate-900">{{ compensationForm.subject }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Class</div>
                  <div class="mt-1 font-bold text-slate-900">{{ compensationForm.class }} {{ compensationForm.stream ? `(${compensationForm.stream})` : '' }}</div>
                </div>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Original Date</div>
                  <div class="mt-1 font-bold text-slate-900">{{ compensationForm.original_date }}</div>
                </div>
              </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Original Day</label>
                <input v-model="compensationForm.original_day" type="text" readonly class="input bg-slate-100 font-bold text-blue-600" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Compensation Date *</label>
                <input v-model="compensationForm.compensation_date" type="date" required class="input" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Compensation Day</label>
                <input v-model="compensationForm.compensation_day" type="text" readonly class="input bg-slate-100 font-bold text-blue-600" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Minutes Compensated *</label>
                <input v-model.number="compensationForm.minutes_compensated" type="number" min="0" required class="input" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Remaining Minutes Lost</label>
                <input :value="remainingMinutesLost" type="number" readonly class="input bg-slate-100 font-bold text-red-600" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Status *</label>
                <select v-model="compensationForm.status" required class="input">
                  <option value="Partially Compensated">Partially Compensated</option>
                  <option value="Fully Compensated">Fully Compensated</option>
                </select>
              </div>

              <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Remarks</label>
                <textarea v-model="compensationForm.remarks" rows="3" class="input" placeholder="Optional notes about this compensation..."></textarea>
              </div>
            </div>

            <div v-if="compensationError" class="rounded-2xl border-2 border-rose-200 bg-gradient-to-r from-rose-50 to-pink-50 px-6 py-4 text-sm font-bold text-rose-700">
              {{ compensationError }}
            </div>
          </div>

          <div class="flex-shrink-0 border-t border-slate-200/60 bg-white px-6 py-5 sm:px-8">
            <div class="flex items-center justify-end gap-3">
              <button
                type="button"
                @click="closeCompensationModal"
                class="inline-flex items-center gap-2 rounded-2xl border-2 border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition-all hover:bg-slate-50"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Cancel
              </button>
              <button
                type="submit"
                :disabled="compensationSaving"
                class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/30 transition hover:shadow-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <svg v-if="!compensationSaving" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ compensationSaving ? 'Saving...' : 'Record Compensation' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { lessonMonitoringAPI, teachersAPI, classesAPI, subjectsNewAPI, lessonCompensationsAPI } from '../services/api';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const records = ref([]);
const teachersList = ref([]);
const teacherSearch = ref('');
const allSubjects = ref([]);
const allClasses = ref([]);
const streams = ref([]);

const filterYear = ref(new Date().getFullYear());
const filterTerm = ref('');
const filterWeek = ref('');
const filterTeacher = ref('');

const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i);

const showModal = ref(false);
const showViewModal = ref(false);
const editingId = ref(null);
const saving = ref(false);
const error = ref('');
const isAnalyticsCollapsed = ref(false);
const formMode = ref('full');
const quickTeacherLabel = ref('');
const quickBaseRecord = ref(null);

// Reports state
const showReports = ref(false);
const reportType = ref('top-teachers');

const filteredTeachers = computed(() => {
  if (!teacherSearch.value) return teachersList.value;
  const search = teacherSearch.value.toLowerCase();
  return teachersList.value.filter(teacher =>
    teacher.full_name.toLowerCase().includes(search) ||
    teacher.teacher_code.toLowerCase().includes(search)
  );
});

const viewingTeacher = ref(null);
const viewingTeacherRecords = ref([]);
const viewingTeacherLoading = ref(false);
const viewingTeacherSummary = ref({
  lessons: 0,
  total_minutes_lost: 0,
  total_expected_minutes: 0,
  total_actual_minutes: 0,
  classes_count: 0,
  streams_count: 0
});

const summary = ref({
  records: 0,
  total_minutes_lost: 0,
  equivalent_single_lessons: 0,
  equivalent_double_lessons: 0,
  compensated_lessons: 0
});

const unwrap = (response) => response?.data ?? response;

const getDayOfWeek = (date) => {
  if (!date) return '';
  const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const d = new Date(date);
  return days[d.getDay()];
};

const normalizeRows = (payload) => {
  if (!payload) return [];
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload.data)) return payload.data;
  if (Array.isArray(payload.data?.data)) return payload.data.data;
  if (Array.isArray(payload.teachers)) return payload.teachers;
  return [];
};

const normalizeSummary = (payload, rows) => {
  if (payload?.summary) return payload.summary;
  if (payload?.data?.summary) return payload.data.summary;

  const totalLost = rows.reduce((sum, row) => sum + Number(row.minutes_lost || 0), 0);
  const compensatedLessons = rows.filter(row => row.is_compensated).length;
  const compensatedMinutes = rows.reduce((sum, row) => sum + Number(row.minutes_compensated || 0), 0);

  return {
    records: rows.length,
    total_minutes_lost: totalLost,
    equivalent_single_lessons: Math.floor(totalLost / 40),
    equivalent_double_lessons: Math.floor(totalLost / 80),
    compensated_lessons: compensatedLessons,
    compensated_minutes: compensatedMinutes
  };
};

const subjects = computed(() => {
  return [...new Set(allSubjects.value.map(s => s.subject_name || s.subject || s.name).filter(Boolean))].sort();
});

const classes = computed(() => {
  return [...new Set(allClasses.value.map(c => c.class_name || c.class || c.name).filter(Boolean))].sort();
});

const groupedRecords = computed(() => {
  const grouped = {};

  records.value.forEach((record) => {
    const key = [
      record.teacher_id,
      record.teacher_name
    ].join('_');

    if (!grouped[key]) {
      grouped[key] = {
        group_key: key,
        teacher_id: record.teacher_id,
        teacher_name: record.teacher_name,
        year: record.year,
        term: record.term,
        subjects_classes: [],
        total_minutes_lost: 0,
        total_lessons: 0
      };
    }

    grouped[key].total_minutes_lost += Number(record.minutes_lost || 0);
    grouped[key].total_lessons += 1;

    // Group by subject, class, stream combination within teacher
    const subjectClassKey = [
      record.subject,
      record.class,
      record.stream
    ].join('_');

    let subjectClassGroup = grouped[key].subjects_classes.find(
      sc => sc.key === subjectClassKey
    );

    if (!subjectClassGroup) {
      subjectClassGroup = {
        key: subjectClassKey,
        subject: record.subject,
        class: record.class,
        stream: record.stream,
        weeks: [],
        total_minutes_lost: 0,
        total_lessons: 0
      };
      grouped[key].subjects_classes.push(subjectClassGroup);
    }

    subjectClassGroup.total_minutes_lost += Number(record.minutes_lost || 0);
    subjectClassGroup.total_lessons += 1;

    let weekGroup = subjectClassGroup.weeks.find(
      w => String(w.week_number) === String(record.week_number)
    );

    if (!weekGroup) {
      weekGroup = {
        week_number: record.week_number,
        entries: []
      };
      subjectClassGroup.weeks.push(weekGroup);
    }

    weekGroup.entries.push(record);
  });

  return Object.values(grouped).map(group => ({
    ...group,
    subjects_classes: group.subjects_classes.map(sc => ({
      ...sc,
      weeks: sc.weeks
        .map(week => ({
          ...week,
          entries: week.entries.sort((a, b) => {
            const da = `${a.attendance_date || ''} ${a.time_in || ''}`;
            const db = `${b.attendance_date || ''} ${b.time_in || ''}`;
            return da.localeCompare(db);
          })
        }))
        .sort((a, b) => Number(a.week_number) - Number(b.week_number))
    })).sort((a, b) => {
      // Sort by subject, then class, then stream
      if (a.subject !== b.subject) return a.subject.localeCompare(b.subject);
      if (a.class !== b.class) return a.class.localeCompare(b.class);
      return (a.stream || '').localeCompare(b.stream || '');
    })
  })).sort((a, b) => {
    // Sort teachers by total minutes lost (highest first)
    return b.total_minutes_lost - a.total_minutes_lost;
  });
});

const topClassesByMinutesLost = computed(() => {
  const classMap = {};

  records.value.forEach((record) => {
    const classKey = `${record.class}_${record.stream || ''}`;

    if (!classMap[classKey]) {
      classMap[classKey] = {
        class: record.class,
        stream: record.stream,
        total_minutes_lost: 0,
        total_lessons: 0,
        teachers: new Set()
      };
    }

    classMap[classKey].total_minutes_lost += Number(record.minutes_lost || 0);
    classMap[classKey].total_lessons += 1;
    classMap[classKey].teachers.add(record.teacher_name);
  });

  return Object.values(classMap)
    .map(item => ({
      ...item,
      teachers: Array.from(item.teachers)
    }))
    .sort((a, b) => b.total_minutes_lost - a.total_minutes_lost)
    .slice(0, 12);
});

const showAllTopClasses = ref(false);

const visibleTopClasses = computed(() =>
  showAllTopClasses.value ? topClassesByMinutesLost.value : topClassesByMinutesLost.value.slice(0, 5)
);

const topClassesMaxLost = computed(() =>
  topClassesByMinutesLost.value.reduce((max, c) => Math.max(max, c.total_minutes_lost), 0)
);

const topClassesTotalLost = computed(() =>
  topClassesByMinutesLost.value.reduce((sum, c) => sum + c.total_minutes_lost, 0)
);

// Subject & Class Details: which class rows are expanded, keyed per teacher group.
const openClasses = ref({});
const classOpenKey = (group, sc) => `${group.group_key}|${sc.key}`;
const isClassOpen = (group, sc) => !!openClasses.value[classOpenKey(group, sc)];
const toggleClass = (group, sc) => {
  const key = classOpenKey(group, sc);
  openClasses.value = { ...openClasses.value, [key]: !openClasses.value[key] };
};
const allClassesOpen = (group) => group.subjects_classes.every(sc => isClassOpen(group, sc));
const toggleAllClasses = (group) => {
  const open = !allClassesOpen(group);
  const next = { ...openClasses.value };
  group.subjects_classes.forEach(sc => { next[classOpenKey(group, sc)] = open; });
  openClasses.value = next;
};

const weekStats = (week) => entriesStats(week.entries);
const classStats = (sc) => entriesStats(sc.weeks.flatMap(w => w.entries));

// Chip / badge colours for a week: green = nothing lost, amber = lost but all compensated, red = still owed
const weekTone = (week) => {
  const s = weekStats(week);
  if (s.lost === 0) {
    return { chip: 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', label: 'Complete' };
  }
  if (s.pending === 0) {
    return { chip: 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100', badge: 'bg-amber-50 text-amber-700 ring-amber-200', label: 'Compensated' };
  }
  return { chip: 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100', badge: 'bg-rose-50 text-rose-700 ring-rose-200', label: `${s.pending} to compensate` };
};

// Which week is open inside each expanded class. Until the user picks one, open the first
// week that still needs compensation, otherwise the latest week.
const selectedWeeks = ref({});
const defaultWeek = (sc) => {
  if (!sc.weeks.length) return null;
  const owed = sc.weeks.find(w => weekStats(w).pending > 0);
  return (owed || sc.weeks[sc.weeks.length - 1]).week_number;
};
const selectedWeek = (group, sc) => selectedWeeks.value[classOpenKey(group, sc)] ?? defaultWeek(sc);
const selectWeek = (group, sc, weekNumber) => {
  selectedWeeks.value = { ...selectedWeeks.value, [classOpenKey(group, sc)]: weekNumber };
};
const selectedWeekData = (group, sc) => {
  const wk = selectedWeek(group, sc);
  return wk === null ? null : sc.weeks.find(w => String(w.week_number) === String(wk)) || null;
};
const weekIndex = (group, sc) => sc.weeks.findIndex(w => String(w.week_number) === String(selectedWeek(group, sc)));
const canStepWeek = (group, sc, dir) => {
  const i = weekIndex(group, sc) + dir;
  return i >= 0 && i < sc.weeks.length;
};
const stepWeek = (group, sc, dir) => {
  if (canStepWeek(group, sc, dir)) selectWeek(group, sc, sc.weeks[weekIndex(group, sc) + dir].week_number);
};

const entriesStats = (entries) => {
  const expected = entries.reduce((s, e) => s + Number(e.expected_minutes || 0), 0);
  const actual = entries.reduce((s, e) => s + Number(e.actual_minutes || 0), 0);
  const lost = entries.reduce((s, e) => s + Number(e.minutes_lost || 0), 0);
  const pending = entries.filter(e => e.minutes_lost > 0 && e.compensation_status !== 'Fully Compensated').length;
  return {
    lessons: entries.length,
    expected,
    actual,
    lost,
    pending,
    taughtPct: expected ? Math.min(100, Math.round((actual / expected) * 100)) : 0
  };
};

const taughtPct = (entry) => {
  const expected = Number(entry.expected_minutes || 0);
  return expected ? Math.min(100, Math.round((Number(entry.actual_minutes || 0) / expected) * 100)) : 0;
};

const taughtBarClass = (entry) => {
  const pct = taughtPct(entry);
  if (pct >= 100) return 'bg-emerald-500';
  if (pct >= 75) return 'bg-amber-400';
  return 'bg-rose-500';
};

const entryStatus = (entry) => {
  if (entry.minutes_lost === 0) return { label: 'Complete', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200' };
  if (entry.compensation_status === 'Fully Compensated') return { label: 'Fully Compensated', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200' };
  if (entry.compensation_status === 'Partially Compensated') return { label: 'Partially Compensated', cls: 'bg-amber-50 text-amber-700 ring-amber-200' };
  return { label: entry.compensation_status || 'Not Compensated', cls: 'bg-rose-50 text-rose-700 ring-rose-200' };
};

// "2026-02-03" -> { day: '03', month: 'Feb' }; parsed as local date to avoid a timezone day shift
const dateParts = (date) => {
  if (!date) return { day: '-', month: '' };
  const [y, m, d] = String(date).slice(0, 10).split('-').map(Number);
  const dt = new Date(y, (m || 1) - 1, d || 1);
  if (Number.isNaN(dt.getTime())) return { day: '-', month: '' };
  return {
    day: String(dt.getDate()).padStart(2, '0'),
    month: dt.toLocaleString('en', { month: 'short' })
  };
};

// "08:00:00" -> "08:00"
const shortTime = (t) => (t ? String(t).slice(0, 5) : '-');

// 1199 -> "1,199 min"
const formatMinutes = (mins) => `${Number(mins || 0).toLocaleString()} min`;

// Report computed properties
const topTeachersByMinutesLost = computed(() => {
  const teacherMap = {};

  records.value.forEach((record) => {
    if (!teacherMap[record.teacher_id]) {
      teacherMap[record.teacher_id] = {
        teacher_id: record.teacher_id,
        teacher_name: record.teacher_name,
        total_minutes_lost: 0,
        total_lessons: 0,
        classes: new Set()
      };
    }

    teacherMap[record.teacher_id].total_minutes_lost += Number(record.minutes_lost || 0);
    teacherMap[record.teacher_id].total_lessons += 1;
    teacherMap[record.teacher_id].classes.add(`${record.class}${record.stream ? '-' + record.stream : ''}`);
  });

  return Object.values(teacherMap)
    .map(item => ({
      ...item,
      classes_count: item.classes.size,
      classes_list: Array.from(item.classes).join(', '),
      equivalent_single_lessons: Math.floor(item.total_minutes_lost / 40),
      equivalent_double_lessons: Math.floor(item.total_minutes_lost / 80)
    }))
    .sort((a, b) => b.total_minutes_lost - a.total_minutes_lost)
    .slice(0, 20);
});

const teachersFewMinutesLosts = computed(() => {
  const teacherMap = {};

  records.value.forEach((record) => {
    if (!teacherMap[record.teacher_id]) {
      teacherMap[record.teacher_id] = {
        teacher_id: record.teacher_id,
        teacher_name: record.teacher_name,
        total_minutes_lost: 0,
        total_expected_minutes: 0,
        total_lessons: 0
      };
    }

    teacherMap[record.teacher_id].total_minutes_lost += Number(record.minutes_lost || 0);
    teacherMap[record.teacher_id].total_expected_minutes += Number(record.expected_minutes || 0);
    teacherMap[record.teacher_id].total_lessons += 1;
  });

  return Object.values(teacherMap)
    .filter(item => item.total_lessons >= 10)
    .map(item => ({
      ...item,
      equivalent_single_lessons: Math.floor(item.total_minutes_lost / 40),
      equivalent_double_lessons: Math.floor(item.total_minutes_lost / 80),
      // Share of scheduled lesson time actually kept (uses each lesson's real length, 40 or 80 min)
      performance_percentage: item.total_expected_minutes > 0
        ? Math.max(0, Math.round((1 - (item.total_minutes_lost / item.total_expected_minutes)) * 100))
        : 100
    }))
    .sort((a, b) => {
      // Sort by performance percentage (descending - highest performance first)
      // Performance considers both total lessons and minutes lost
      return b.performance_percentage - a.performance_percentage;
    })
    .slice(0, 20);
});

const classesMostMinutesLost = computed(() => {
  const classMap = {};

  records.value.forEach((record) => {
    const classKey = `${record.class}_${record.stream || ''}`;

    if (!classMap[classKey]) {
      classMap[classKey] = {
        class: record.class,
        stream: record.stream,
        total_minutes_lost: 0,
        total_lessons: 0,
        teachers: new Map()
      };
    }

    classMap[classKey].total_minutes_lost += Number(record.minutes_lost || 0);
    classMap[classKey].total_lessons += 1;

    // Track individual teacher minutes lost for this class
    const teacherKey = record.teacher_id;
    if (!classMap[classKey].teachers.has(teacherKey)) {
      classMap[classKey].teachers.set(teacherKey, {
        teacher_id: record.teacher_id,
        teacher_name: record.teacher_name,
        minutes_lost: 0
      });
    }
    classMap[classKey].teachers.get(teacherKey).minutes_lost += Number(record.minutes_lost || 0);
  });

  return Object.values(classMap)
    .map(item => {
      // Get top 5 teachers by minutes lost for this class
      const topTeachers = Array.from(item.teachers.values())
        .sort((a, b) => b.minutes_lost - a.minutes_lost)
        .slice(0, 5);

      return {
        ...item,
        class_key: `${item.class}_${item.stream || ''}`,
        teachers_count: item.teachers.size,
        top_teachers: topTeachers,
        teachers_list: topTeachers.map(t => `${t.teacher_name} (${t.minutes_lost})`).join(', '),
        equivalent_single_lessons: Math.floor(item.total_minutes_lost / 40),
        equivalent_double_lessons: Math.floor(item.total_minutes_lost / 80)
      };
    })
    .sort((a, b) => b.total_minutes_lost - a.total_minutes_lost)
    .slice(0, 20);
});

const classesFewMinutesLost = computed(() => {
  const classMap = {};

  records.value.forEach((record) => {
    const classKey = `${record.class}_${record.stream || ''}`;

    if (!classMap[classKey]) {
      classMap[classKey] = {
        class: record.class,
        stream: record.stream,
        total_minutes_lost: 0,
        total_lessons: 0,
        teachers: new Set()
      };
    }

    classMap[classKey].total_minutes_lost += Number(record.minutes_lost || 0);
    classMap[classKey].total_lessons += 1;
    classMap[classKey].teachers.add(record.teacher_name);
  });

  return Object.values(classMap)
    .filter(item => item.total_lessons > 0)
    .map(item => ({
      ...item,
      class_key: `${item.class}_${item.stream || ''}`,
      teachers_count: item.teachers.size,
      teachers_list: Array.from(item.teachers).join(', '),
      equivalent_single_lessons: Math.floor(item.total_minutes_lost / 40),
      equivalent_double_lessons: Math.floor(item.total_minutes_lost / 80)
    }))
    .sort((a, b) => a.total_minutes_lost - b.total_minutes_lost)
    .slice(0, 20);
});

const reportConfig = {
  'top-teachers': {
    tab: 'Teachers',
    tabSub: 'Most time lost',
    icon: 'fas fa-user-clock',
    tone: 'rose',
    title: 'Teachers with the most time lost',
    description: 'Top 20 teachers ranked by total minutes lost.',
    entity: 'Teacher',
    entityPlural: 'teachers',
    metricLabel: '≈ Single lessons',
    emptyHint: 'No lessons match the current filters.'
  },
  'few-teachers': {
    tab: 'Teachers',
    tabSub: 'Least time lost',
    icon: 'fas fa-user-check',
    tone: 'emerald',
    title: 'Teachers with the least time lost',
    description: 'Teachers with at least 10 lessons, ranked by the share of lesson time kept.',
    entity: 'Teacher',
    entityPlural: 'teachers',
    metricLabel: 'Time kept',
    emptyHint: 'No teacher has 10 or more lessons under the current filters.'
  },
  'top-classes': {
    tab: 'Classes',
    tabSub: 'Most time lost',
    icon: 'fas fa-school',
    tone: 'rose',
    title: 'Classes with the most time lost',
    description: 'Top 20 classes ranked by total minutes lost, with the teachers who lost the most.',
    entity: 'Class',
    entityPlural: 'classes',
    metricLabel: '≈ Single lessons',
    emptyHint: 'No lessons match the current filters.'
  },
  'few-classes': {
    tab: 'Classes',
    tabSub: 'Least time lost',
    icon: 'fas fa-medal',
    tone: 'emerald',
    title: 'Classes with the least time lost',
    description: '20 classes with the fewest minutes lost.',
    entity: 'Class',
    entityPlural: 'classes',
    metricLabel: '≈ Single lessons',
    emptyHint: 'No lessons match the current filters.'
  }
};

const activeReport = computed(() => reportConfig[reportType.value] || reportConfig['top-teachers']);

const classLabel = (item) => `${item.class || '-'}${item.stream ? ` · ${item.stream}` : ''}`;
const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

// One row shape for every report type, used by both the modal and the PDF.
const reportRows = computed(() => {
  switch (reportType.value) {
    case 'top-teachers':
      return topTeachersByMinutesLost.value.map((t, i) => ({
        key: t.teacher_id,
        rank: i + 1,
        name: t.teacher_name || '-',
        sub: plural(t.classes_count, 'class', 'classes'),
        notes: t.classes_list,
        minutes: t.total_minutes_lost,
        lessons: t.total_lessons,
        metric: String(t.equivalent_single_lessons)
      }));
    case 'few-teachers':
      return teachersFewMinutesLosts.value.map((t, i) => ({
        key: t.teacher_id,
        rank: i + 1,
        name: t.teacher_name || '-',
        sub: `${plural(t.total_lessons, 'lesson', 'lessons')} monitored`,
        notes: '',
        minutes: t.total_minutes_lost,
        lessons: t.total_lessons,
        metric: `${t.performance_percentage}%`
      }));
    case 'top-classes':
      return classesMostMinutesLost.value.map((c, i) => ({
        key: c.class_key,
        rank: i + 1,
        name: classLabel(c),
        sub: plural(c.teachers_count, 'teacher', 'teachers'),
        notes: c.top_teachers.some(t => t.minutes_lost > 0)
          ? `Most lost: ${c.top_teachers.filter(t => t.minutes_lost > 0).map(t => `${t.teacher_name} (${t.minutes_lost} min)`).join(', ')}`
          : '',
        minutes: c.total_minutes_lost,
        lessons: c.total_lessons,
        metric: String(c.equivalent_single_lessons)
      }));
    case 'few-classes':
      return classesFewMinutesLost.value.map((c, i) => ({
        key: c.class_key,
        rank: i + 1,
        name: classLabel(c),
        sub: plural(c.teachers_count, 'teacher', 'teachers'),
        notes: c.teachers_list,
        minutes: c.total_minutes_lost,
        lessons: c.total_lessons,
        metric: String(c.equivalent_single_lessons)
      }));
    default:
      return [];
  }
});

const reportTotals = computed(() => ({
  minutes: reportRows.value.reduce((s, r) => s + Number(r.minutes || 0), 0),
  lessons: reportRows.value.reduce((s, r) => s + Number(r.lessons || 0), 0),
  max: reportRows.value.reduce((m, r) => Math.max(m, Number(r.minutes || 0)), 0)
}));

const reportBarPct = (row) => (reportTotals.value.max ? Math.round((row.minutes / reportTotals.value.max) * 100) : 0);

const reportScope = computed(() => {
  const teacherName = filterTeacher.value
    ? teachersList.value.find(t => String(t.id) === String(filterTeacher.value))?.full_name
    : '';
  return [
    `Year ${filterYear.value}`,
    filterTerm.value ? `Term ${filterTerm.value}` : 'All terms',
    filterWeek.value ? `Week ${filterWeek.value}` : 'All weeks',
    teacherName ? `Teacher: ${teacherName}` : ''
  ].filter(Boolean).join(' · ');
});

// Close on Esc and stop the page behind the modal from scrolling.
const onReportsKeydown = (e) => { if (e.key === 'Escape') showReports.value = false; };
watch(showReports, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) window.addEventListener('keydown', onReportsKeydown);
  else window.removeEventListener('keydown', onReportsKeydown);
});
onUnmounted(() => {
  document.body.style.overflow = '';
  window.removeEventListener('keydown', onReportsKeydown);
});

const printReport = () => {
  const rows = reportRows.value;
  if (rows.length === 0) return;

  const cfg = activeReport.value;
  const accent = cfg.tone === 'rose' ? [225, 29, 72] : [5, 150, 105];
  const accentSoft = cfg.tone === 'rose' ? [255, 228, 230] : [209, 250, 229];
  const ink = [15, 23, 42];
  const muted = [100, 116, 139];
  const line = [226, 232, 240];

  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  const pageH = doc.internal.pageSize.getHeight();
  const margin = 14;

  // Header band
  doc.setFillColor(...ink);
  doc.rect(0, 0, pageW, 34, 'F');
  doc.setFillColor(...accent);
  doc.rect(0, 34, pageW, 1.2, 'F');

  doc.setTextColor(148, 163, 184);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(8);
  doc.text('LESSON MONITORING REPORT', margin, 12);

  doc.setTextColor(255, 255, 255);
  doc.setFontSize(16);
  doc.text(cfg.title, margin, 21);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(203, 213, 225);
  doc.text(reportScope.value, margin, 28);

  const generated = new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  doc.setFontSize(8);
  doc.text(`Generated ${generated}`, pageW - margin, 12, { align: 'right' });

  // Summary cards
  const cards = [
    { label: 'TIME LOST', value: formatMinutes(reportTotals.value.minutes), color: accent },
    { label: 'LESSONS', value: reportTotals.value.lessons.toLocaleString(), color: ink },
    { label: cfg.entityPlural.toUpperCase() + ' LISTED', value: String(rows.length), color: ink },
    {
      label: 'AVG LOST / LESSON',
      value: `${reportTotals.value.lessons ? (reportTotals.value.minutes / reportTotals.value.lessons).toFixed(1) : '0'} min`,
      color: ink
    }
  ];
  const gap = 4;
  const cardW = (pageW - margin * 2 - gap * (cards.length - 1)) / cards.length;
  const cardY = 42;
  cards.forEach((c, i) => {
    const x = margin + i * (cardW + gap);
    doc.setDrawColor(...line);
    doc.setFillColor(248, 250, 252);
    doc.roundedRect(x, cardY, cardW, 18, 2.5, 2.5, 'FD');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7);
    doc.setTextColor(...muted);
    doc.text(c.label, x + 4, cardY + 6);
    doc.setFontSize(13);
    doc.setTextColor(...c.color);
    doc.text(c.value, x + 4, cardY + 14);
  });

  // Section heading
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(...muted);
  doc.text(cfg.description, margin, cardY + 26);

  const barCol = 3;
  autoTable(doc, {
    startY: cardY + 30,
    margin: { left: margin, right: margin, bottom: 18 },
    head: [['#', cfg.entity, 'Time lost', '', 'Lessons', cfg.metricLabel.replace('≈ ', '~ ')]],
    body: rows.map(r => [
      r.rank,
      { content: r.notes ? `${r.name}\n${r.sub}\n${r.notes}` : `${r.name}\n${r.sub}`, raw: r },
      formatMinutes(r.minutes),
      { content: '', raw: r },
      r.lessons,
      r.metric
    ]),
    theme: 'plain',
    rowPageBreak: 'avoid',
    styles: { font: 'helvetica', fontSize: 8.5, textColor: ink, cellPadding: { top: 3, bottom: 3, left: 2.5, right: 2.5 }, valign: 'middle' },
    headStyles: { fillColor: accentSoft, textColor: accent, fontStyle: 'bold', fontSize: 8 },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: {
      0: { cellWidth: 10, halign: 'center', fontStyle: 'bold' },
      1: { cellWidth: 'auto' },
      2: { cellWidth: 22, halign: 'right', fontStyle: 'bold', textColor: accent },
      3: { cellWidth: 30 },
      4: { cellWidth: 17, halign: 'right' },
      5: { cellWidth: 26, halign: 'right' }
    },
    // Name in bold, the class/teacher line and notes muted underneath
    willDrawCell: (data) => {
      if (data.section === 'body' && data.column.index === 1) data.cell.text = [];
    },
    didDrawCell: (data) => {
      if (data.section !== 'body') return;
      const { cell } = data;
      if (data.column.index === 1) {
        const r = cell.raw.raw;
        const x = cell.x + 2.5;
        let y = cell.y + 5;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(...ink);
        doc.text(r.name, x, y);
        y += 4;
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(...muted);
        doc.text(r.sub, x, y);
        if (r.notes) {
          const wrapped = doc.splitTextToSize(r.notes, cell.width - 5);
          doc.setTextColor(148, 163, 184);
          doc.text(wrapped, x, y + 3.6);
        }
      }
      if (data.column.index === barCol) {
        const r = cell.raw.raw;
        const w = cell.width - 5;
        const y = cell.y + cell.height / 2 - 1;
        doc.setFillColor(...line);
        doc.roundedRect(cell.x + 2.5, y, w, 2, 1, 1, 'F');
        const pct = reportBarPct(r) / 100;
        if (pct > 0) {
          doc.setFillColor(...accent);
          doc.roundedRect(cell.x + 2.5, y, Math.max(2, w * pct), 2, 1, 1, 'F');
        }
      }
      if (data.column.index === 0 && data.cell.raw <= 3) {
        // Top three get an accent rank dot
        doc.setFillColor(...accent);
        doc.circle(cell.x + cell.width / 2, cell.y + cell.height / 2, 3, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.text(String(cell.raw), cell.x + cell.width / 2, cell.y + cell.height / 2 + 1, { align: 'center' });
      }
    }
  });

  // Footer on every page
  const pages = doc.internal.getNumberOfPages();
  for (let p = 1; p <= pages; p++) {
    doc.setPage(p);
    doc.setDrawColor(...line);
    doc.line(margin, pageH - 12, pageW - margin, pageH - 12);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(...muted);
    doc.text('School HR System · Lesson Monitoring', margin, pageH - 7);
    doc.text(`Page ${p} of ${pages}`, pageW - margin, pageH - 7, { align: 'right' });
  }

  const termPart = filterTerm.value ? `term-${filterTerm.value}` : 'all-terms';
  doc.save(`lesson-monitoring-${reportType.value}-${filterYear.value}-${termPart}.pdf`);
};

const form = ref({
  teacher_id: '',
  subject: '',
  class: '',
  stream: '',
  week_number: '',
  attendance_date: new Date().toISOString().slice(0, 10),
  day_of_week: getDayOfWeek(new Date().toISOString().slice(0, 10)),
  time_in: '',
  time_out: '',
  expected_minutes: 0,
  actual_minutes: 0,
  minutes_lost: 0,
  year: new Date().getFullYear(),
  term: 1
});

// Compensation modal state
const showCompensationModal = ref(false);
const compensationForm = ref({
  lesson_monitoring_id: '',
  teacher_id: '',
  teacher_name: '',
  subject: '',
  class: '',
  stream: '',
  original_date: '',
  original_day: '',
  compensation_date: new Date().toISOString().slice(0, 10),
  compensation_day: getDayOfWeek(new Date().toISOString().slice(0, 10)),
  minutes_compensated: 0,
  original_minutes_lost: 0,
  remarks: '',
  status: 'Partially Compensated'
});
const compensationSaving = ref(false);
const compensationError = ref('');

// Computed property for remaining minutes lost
const remainingMinutesLost = computed(() => {
  const original = Number(compensationForm.value.original_minutes_lost || 0);
  const compensated = Number(compensationForm.value.minutes_compensated || 0);
  return Math.max(0, original - compensated);
});

const autoCalculate = () => {
  if (!form.value.time_in || !form.value.time_out) {
    form.value.actual_minutes = 0;
    form.value.minutes_lost = 0;
    return;
  }

  const inTime = new Date(`1970-01-01T${form.value.time_in}:00`);
  const outTime = new Date(`1970-01-01T${form.value.time_out}:00`);

  if (outTime <= inTime) {
    outTime.setDate(outTime.getDate() + 1);
  }

  const actualMinutes = Math.round((outTime - inTime) / 60000);
  form.value.actual_minutes = actualMinutes;
  form.value.minutes_lost = Math.max(0, Number(form.value.expected_minutes || 0) - actualMinutes);
};

// Auto-generate day when attendance date changes
watch(() => form.value.attendance_date, (newDate) => {
  if (newDate) {
    form.value.day_of_week = getDayOfWeek(newDate);
  }
});

// Auto-generate compensation day when compensation date changes
watch(() => compensationForm.value.compensation_date, (newDate) => {
  if (newDate) {
    compensationForm.value.compensation_day = getDayOfWeek(newDate);
  }
});

const openCompensationModal = (entry) => {
  compensationForm.value = {
    lesson_monitoring_id: entry.id,
    teacher_id: entry.teacher_id,
    teacher_name: entry.teacher_name || '',
    subject: entry.subject || '',
    class: entry.class || '',
    stream: entry.stream || '',
    original_date: entry.attendance_date || '',
    original_day: entry.day_of_week || '',
    compensation_date: new Date().toISOString().slice(0, 10),
    compensation_day: getDayOfWeek(new Date().toISOString().slice(0, 10)),
    minutes_compensated: 0,
    original_minutes_lost: entry.minutes_lost || 0,
    remarks: '',
    status: 'Partially Compensated'
  };
  compensationError.value = '';
  showCompensationModal.value = true;
};

const closeCompensationModal = () => {
  showCompensationModal.value = false;
  compensationError.value = '';
};

const saveCompensation = async () => {
  compensationSaving.value = true;
  compensationError.value = '';

  console.log('Saving compensation, form:', compensationForm.value);

  // Validation
  if (!compensationForm.value.compensation_date) {
    compensationError.value = 'Compensation date is required';
    compensationSaving.value = false;
    return;
  }

  if (new Date(compensationForm.value.compensation_date) < new Date(compensationForm.value.original_date)) {
    compensationError.value = 'Compensation date cannot be earlier than original lesson date';
    compensationSaving.value = false;
    return;
  }

  try {
    const payload = {
      lesson_monitoring_id: compensationForm.value.lesson_monitoring_id,
      teacher_id: compensationForm.value.teacher_id,
      subject: compensationForm.value.subject,
      class: compensationForm.value.class,
      stream: compensationForm.value.stream,
      original_date: compensationForm.value.original_date,
      original_day: compensationForm.value.original_day,
      compensation_date: compensationForm.value.compensation_date,
      compensation_day: compensationForm.value.compensation_day,
      minutes_compensated: Number(compensationForm.value.minutes_compensated || 0),
      remarks: compensationForm.value.remarks,
      status: compensationForm.value.status
    };

    console.log('Compensation payload:', payload);

    const response = await lessonCompensationsAPI.create(payload);
    console.log('Compensation API response:', response);

    const result = unwrap(response);

    if (result?.success) {
      showCompensationModal.value = false;
      await loadData();
      alert('Time regained recorded successfully!');
    } else {
      console.log('Compensation failed, result:', result);
      compensationError.value = result?.message || 'Failed to record compensation';
    }
  } catch (err) {
    console.error('COMPENSATION SAVE ERROR:', err);
    console.error('Error response:', err?.response);
    console.error('Error response data:', err?.response?.data);
    console.error('Error response status:', err?.response?.status);
    compensationError.value = err?.response?.data?.message || 'Failed to record compensation';
  } finally {
    compensationSaving.value = false;
  }
};

const loadData = async () => {
  try {
    const response = await lessonMonitoringAPI.getAll(
      filterYear.value,
      filterTerm.value,
      filterTeacher.value,
      filterWeek.value
    );

    const rows = normalizeRows(response).map((row) => ({
      ...row,
      expected_minutes: Number(row.expected_minutes || 0),
      actual_minutes: Number(row.actual_minutes || 0),
      minutes_lost: Number(row.minutes_lost || 0)
    }));

    let running = 0;
    const rowsWithCumulative = rows.map((row) => {
      running += Number(row.minutes_lost || 0);
      return {
        ...row,
        cumulative_minutes_lost: row.cumulative_minutes_lost ?? running
      };
    });

    records.value = rowsWithCumulative;
    summary.value = normalizeSummary(response, rowsWithCumulative);
  } catch (err) {
    console.error('LOAD ERROR:', err);
    records.value = [];
    summary.value = {
      records: 0,
      total_minutes_lost: 0,
      equivalent_single_lessons: 0,
      equivalent_double_lessons: 0
    };
  }
};

const loadTeachers = async () => {
  try {
    const response = await teachersAPI.getAll();
    const payload = unwrap(response);
    teachersList.value = normalizeRows(payload);
  } catch (err) {
    console.error('LOAD TEACHERS ERROR:', err);
    teachersList.value = [];
  }
};

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    const payload = unwrap(response);
    const rows = normalizeRows(payload);
    allClasses.value = rows;
    streams.value = [...new Set(rows.map(c => c.stream_name || c.stream).filter(Boolean))].sort();
    console.log('Classes loaded:', allClasses.value);
    console.log('Streams loaded:', streams.value);
  } catch (err) {
    console.error('LOAD CLASSES ERROR:', err);
    allClasses.value = [];
    streams.value = [];
  }
};

const loadSubjects = async () => {
  try {
    const response = await subjectsNewAPI.getAll();
    const payload = unwrap(response);
    allSubjects.value = normalizeRows(payload);
    console.log('Subjects loaded:', allSubjects.value);
  } catch (err) {
    console.error('LOAD SUBJECTS ERROR:', err);
    allSubjects.value = [];
  }
};

const openAddModal = () => {
  formMode.value = 'full';
  editingId.value = null;
  quickBaseRecord.value = null;
  quickTeacherLabel.value = '';
  error.value = '';

  form.value = {
    teacher_id: '',
    subject: '',
    class: '',
    stream: '',
    week_number: '',
    attendance_date: new Date().toISOString().slice(0, 10),
    day_of_week: getDayOfWeek(new Date().toISOString().slice(0, 10)),
    time_in: '',
    time_out: '',
    expected_minutes: 0,
    actual_minutes: 0,
    minutes_lost: 0,
    year: new Date().getFullYear(),
    term: filterTerm.value || 1
  };

  showModal.value = true;
};

const openQuickAddModal = (groupOrRecord) => {
  formMode.value = 'quick';
  editingId.value = null;
  error.value = '';

  const base = groupOrRecord?.teacher_id ? groupOrRecord : null;
  quickBaseRecord.value = base;
  quickTeacherLabel.value = base?.teacher_name || 'Teacher';

  // For teacher-level quick add, we need to select subject/class/stream
  // So we'll just pre-fill teacher info and let user select the rest
  form.value = {
    teacher_id: base?.teacher_id || '',
    subject: '',
    class: '',
    stream: '',
    week_number: '',
    attendance_date: new Date().toISOString().slice(0, 10),
    day_of_week: getDayOfWeek(new Date().toISOString().slice(0, 10)),
    time_in: '',
    time_out: '',
    expected_minutes: 0,
    actual_minutes: 0,
    minutes_lost: 0,
    year: Number(base?.year || filterYear.value || new Date().getFullYear()),
    term: Number(base?.term || filterTerm.value || 1)
  };

  showModal.value = true;
};

const editRecord = (record) => {
  console.log('Edit record called with:', record);
  formMode.value = 'full';
  editingId.value = record.id;
  quickBaseRecord.value = null;
  quickTeacherLabel.value = '';
  error.value = '';

  form.value = {
    id: record.id,
    teacher_id: record.teacher_id,
    subject: record.subject,
    class: record.class,
    stream: record.stream,
    week_number: record.week_number,
    attendance_date: record.attendance_date,
    time_in: record.time_in,
    time_out: record.time_out,
    expected_minutes: record.expected_minutes,
    actual_minutes: record.actual_minutes,
    minutes_lost: record.minutes_lost,
    year: record.year,
    term: record.term
  };

  console.log('Form populated:', form.value);
  console.log('Editing ID set to:', editingId.value);
  showModal.value = true;
};

const saveRecord = async () => {
  saving.value = true;
  error.value = '';

  try {
    autoCalculate();

    const payload = {
      ...form.value,
      teacher_id: String(form.value.teacher_id),
      subject: form.value.subject,
      class: form.value.class,
      stream: form.value.stream,
      week_number: Number(form.value.week_number),
      expected_minutes: Number(form.value.expected_minutes || 0),
      actual_minutes: Number(form.value.actual_minutes || 0),
      minutes_lost: Number(form.value.minutes_lost || 0),
      year: Number(form.value.year || new Date().getFullYear()),
      term: Number(form.value.term || 1)
    };

    console.log('Saving record, editingId:', editingId.value);
    console.log('Payload:', payload);

    const response = editingId.value
      ? await lessonMonitoringAPI.update(editingId.value, payload)
      : await lessonMonitoringAPI.create(payload);

    console.log('API Response:', response);

    const result = unwrap(response);

    if (result?.success) {
      showModal.value = false;
      await loadData();
    } else {
      console.log('Save failed, result:', result);
      error.value = result?.message || 'Failed to save record';
    }
  } catch (err) {
    console.error('SAVE ERROR:', err);
    console.error('Error response:', err?.response);
    error.value = 'Failed to save record';
  } finally {
    saving.value = false;
  }
};

const deleteRecord = async (id) => {
  if (!confirm('Are you sure you want to delete this attendance record?')) return;

  try {
    const response = await lessonMonitoringAPI.delete(id);
    const result = unwrap(response);

    if (result?.success) {
      await loadData();
    } else {
      alert(result?.message || 'Failed to delete record');
    }
  } catch (err) {
    console.error('DELETE ERROR:', err);
    alert('Failed to delete record');
  }
};

const openTeacherView = async (group) => {
  viewingTeacher.value = {
    id: group.teacher_id,
    name: group.teacher_name || 'Teacher'
  };

  viewingTeacherLoading.value = true;
  showViewModal.value = true;
  viewingTeacherRecords.value = [];

  try {
    const response = await lessonMonitoringAPI.getAll(
      filterYear.value,
      filterTerm.value,
      group.teacher_id,
      ''
    );

    let rows = normalizeRows(response).filter(
      r => String(r.teacher_id) === String(group.teacher_id)
    );

    rows = rows.sort((a, b) => {
      const da = `${a.attendance_date || ''} ${a.time_in || ''}`;
      const db = `${b.attendance_date || ''} ${b.time_in || ''}`;
      return db.localeCompare(da);
    });

    let totalLost = 0;
    let totalExpected = 0;
    let totalActual = 0;
    const classSet = new Set();
    const streamSet = new Set();

    rows.forEach((r) => {
      totalLost += Number(r.minutes_lost || 0);
      totalExpected += Number(r.expected_minutes || 0);
      totalActual += Number(r.actual_minutes || 0);
      if (r.class) classSet.add(r.class);
      if (r.stream) streamSet.add(r.stream);
    });

    viewingTeacherRecords.value = rows;
    viewingTeacherSummary.value = {
      lessons: rows.length,
      total_minutes_lost: totalLost,
      total_expected_minutes: totalExpected,
      total_actual_minutes: totalActual,
      classes_count: classSet.size,
      streams_count: streamSet.size
    };
  } catch (err) {
    console.error('VIEW TEACHER ERROR:', err);
    viewingTeacherRecords.value = [];
  } finally {
    viewingTeacherLoading.value = false;
  }
};

const closeTeacherView = () => {
  showViewModal.value = false;
  viewingTeacher.value = null;
  viewingTeacherRecords.value = [];
};

const getGroupTotalLost = (group) => {
  return group.total_minutes_lost ?? group.subjects_classes.reduce((sum, sc) => sum + sc.total_minutes_lost, 0);
};

const getWeekTotalLost = (week) => {
  return (week?.entries || []).reduce((sum, entry) => sum + Number(entry.minutes_lost || 0), 0);
};

onMounted(() => {
  // Records first so the table request isn't queued behind the dropdown lookups.
  loadData();
  loadTeachers();
  loadClasses();
  loadSubjects();
});
</script>

<style scoped>
.input {
  width: 100%;
  border-radius: 1rem;
  border: 1.5px solid rgb(226 232 240);
  padding: 0.9rem 1rem;
  outline: none;
  transition: all 0.2s ease;
  background: white;
  font-weight: 500;
  font-size: 14px;
}

.input:focus {
  border-color: rgb(99 102 241);
  box-shadow: 0 0 0 4px rgb(238 242 255);
}

.input:hover:not(:focus) {
  border-color: rgb(203 213 225);
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border-radius: 1.1rem;
  border: 1px solid rgb(226 232 240);
  background: white;
  padding: 0.85rem 1rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.stat-icon {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.75rem;
}

.stat-label {
  font-size: 12px;
  font-weight: 600;
  color: rgb(100 116 139);
}

.stat-value {
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.2;
}

.teacher-stat {
  min-width: 4.5rem;
  border-radius: 0.6rem;
  background: rgba(255, 255, 255, 0.12);
  padding: 0.3rem 0.75rem;
  text-align: center;
}

.teacher-stat-value {
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.25;
  color: white;
  white-space: nowrap;
}

.teacher-stat-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgba(255, 255, 255, 0.75);
}

.teacher-action {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.6rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.1);
  color: white;
  transition: background 0.15s;
}

.teacher-action:hover {
  background: rgba(255, 255, 255, 0.22);
}

.stat-sub {
  font-size: 11px;
  color: rgb(100 116 139);
}

.filter-label {
  margin-bottom: 0.55rem;
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: rgb(51 65 85);
}

.mini-card {
  border-radius: 1.3rem;
  border: 1px solid rgb(226 232 240);
  background: white;
  padding: 1rem;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
}

.mini-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgb(100 116 139);
}

.mini-value {
  margin-top: 0.45rem;
  font-size: 1.1rem;
  font-weight: 800;
  color: rgb(15 23 42);
}

.mini-desc {
  margin-top: 0.25rem;
  font-size: 12px;
  color: rgb(148 163 184);
}

.detail-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  background: rgb(238 242 255);
  padding: 0.45rem 0.85rem;
  font-size: 11px;
  font-weight: 700;
  color: rgb(67 56 202);
  border: 1px solid rgb(199 210 254);
}

.detail-badge-rose {
  background: rgb(255 241 242);
  color: rgb(190 18 60);
  border-color: rgb(254 202 202);
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 1rem;
  border-width: 1px;
  width: 42px;
  height: 42px;
  transition: 0.2s ease;
  box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
}

.icon-btn:hover {
  transform: translateY(-1px);
}

.table-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 0.9rem;
  border-width: 1px;
  transition: 0.2s ease;
}

.table-icon-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}

@media (max-width: 640px) {
  .stat-value {
    font-size: 1.2rem;
  }

  .mini-value {
    font-size: 1rem;
  }
}
</style>
