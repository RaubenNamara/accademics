<template>
  <div class="space-y-8">
    <div class="relative overflow-hidden rounded-[2rem] bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-5 text-white shadow-2xl sm:p-7">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(255,255,255,0.14),transparent_28%)]"></div>

      <div class="relative z-10 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="max-w-3xl">
         
          <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
            Teacher Attendance & Time Lost
          </h1>
     
        </div>

        <div class="flex flex-wrap gap-3">
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
            @click="downloadPDF"
            :disabled="records.length === 0"
            class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11V3m0 8l-3-3m3 3l3-3m-9 10h12a2 2 0 002-2v-3H4v3a2 2 0 002 2z" />
            </svg>
            PDF
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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
      <div class="summary-card">
        <div class="summary-label">Records</div>
        <div class="summary-value text-slate-900">{{ summary.records }}</div>
      </div>

      <div class="summary-card">
        <div class="summary-label">Minutes Lost</div>
        <div class="summary-value text-rose-600">{{ summary.total_minutes_lost }}</div>
      </div>

      <div class="summary-card">
        <div class="summary-label">Single Lessons</div>
        <div class="summary-value text-blue-600">{{ summary.equivalent_single_lessons }}</div>
      </div>

      <div class="summary-card">
        <div class="summary-label">Double Lessons</div>
        <div class="summary-value text-emerald-600">{{ summary.equivalent_double_lessons }}</div>
      </div>

      <div class="summary-card">
        <div class="summary-label">Compensated</div>
        <div class="summary-value text-teal-600">{{ summary.compensated_lessons }}</div>
      </div>
    </div>

    <!-- Reports Section -->
    <div v-if="showReports" class="rounded-[2rem] border border-slate-200 bg-white/90 p-5 shadow-xl shadow-slate-200/60 backdrop-blur">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-extrabold text-slate-900">Lesson Monitoring Reports</h3>
        <div class="flex items-center gap-2">
          <button
            @click="printReport"
            :disabled="getReportData().length === 0"
            class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print
          </button>
          <button
            @click="showReports = false"
            class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
          >
            Close
          </button>
        </div>
      </div>

      <div class="mb-4">
        <label class="filter-label">Report Type</label>
        <select v-model="reportType" class="input">
          <option value="top-teachers">Top 20 Teachers - Highest Minutes Lost</option>
          <option value="few-teachers">Teachers - Few Minutes Lost</option>
          <option value="top-classes">Classes - Most Minutes Lost</option>
          <option value="few-classes">Classes - Few Minutes Lost</option>
        </select>
      </div>

      <!-- Top 20 Teachers - Highest Minutes Lost -->
      <div v-if="reportType === 'top-teachers' && topTeachersByMinutesLost.length > 0">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-rose-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Rank</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Teacher</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Total Minutes Lost</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Single Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Double Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Total Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Classes Affected</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(teacher, index) in topTeachersByMinutesLost" :key="teacher.teacher_id" class="border-t border-slate-100">
                <td class="px-4 py-3 font-bold text-rose-600">{{ index + 1 }}</td>
                <td class="px-4 py-3 font-semibold">{{ teacher.teacher_name }}</td>
                <td class="px-4 py-3 font-bold text-rose-600">{{ teacher.total_minutes_lost }}</td>
                <td class="px-4 py-3">{{ teacher.equivalent_single_lessons }}</td>
                <td class="px-4 py-3">{{ teacher.equivalent_double_lessons }}</td>
                <td class="px-4 py-3">{{ teacher.total_lessons }}</td>
                <td class="px-4 py-3">
                  <div class="text-xs text-slate-600">{{ teacher.classes_count }} classes</div>
                  <div class="text-[10px] text-slate-400">{{ teacher.classes_list }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Teachers - Few Minutes Lost -->
      <div v-if="reportType === 'few-teachers' && teachersFewMinutesLosts.length > 0">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-emerald-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Teacher</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Total Minutes Lost</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Single Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Double Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Total Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Performance %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="teacher in teachersFewMinutesLosts" :key="teacher.teacher_id" class="border-t border-slate-100">
                <td class="px-4 py-3 font-semibold">{{ teacher.teacher_name }}</td>
                <td class="px-4 py-3 font-bold text-emerald-600">{{ teacher.total_minutes_lost }}</td>
                <td class="px-4 py-3">{{ teacher.equivalent_single_lessons }}</td>
                <td class="px-4 py-3">{{ teacher.equivalent_double_lessons }}</td>
                <td class="px-4 py-3">{{ teacher.total_lessons }}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex rounded-full px-2 py-1 text-xs font-bold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                    {{ teacher.performance_percentage }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Classes - Most Minutes Lost -->
      <div v-if="reportType === 'top-classes' && classesMostMinutesLost.length > 0">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-rose-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Rank</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Class</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Stream</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Total Minutes Lost</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Single Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Double Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-rose-700">Top 5 Teachers (Minutes)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(classData, index) in classesMostMinutesLost" :key="classData.class_key" class="border-t border-slate-100">
                <td class="px-4 py-3 font-bold text-rose-600">{{ index + 1 }}</td>
                <td class="px-4 py-3 font-semibold">{{ classData.class }}</td>
                <td class="px-4 py-3">{{ classData.stream || '-' }}</td>
                <td class="px-4 py-3 font-bold text-rose-600">{{ classData.total_minutes_lost }}</td>
                <td class="px-4 py-3">{{ classData.equivalent_single_lessons }}</td>
                <td class="px-4 py-3">{{ classData.equivalent_double_lessons }}</td>
                <td class="px-4 py-3">
                  <div class="text-xs text-slate-600">{{ classData.teachers_count }} teacher(s) total</div>
                  <div class="mt-1 space-y-1">
                    <div v-for="(teacher, tIndex) in classData.top_teachers" :key="teacher.teacher_id" class="text-[10px] text-slate-500">
                      {{ tIndex + 1 }}. {{ teacher.teacher_name }} ({{ teacher.minutes_lost }} mins)
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Classes - Few Minutes Lost -->
      <div v-if="reportType === 'few-classes' && classesFewMinutesLost.length > 0">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-emerald-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Class</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Stream</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Total Minutes Lost</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Single Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Double Lessons</th>
                <th class="px-4 py-3 text-left font-semibold text-emerald-700">Teachers Affected</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="classData in classesFewMinutesLost" :key="classData.class_key" class="border-t border-slate-100">
                <td class="px-4 py-3 font-semibold">{{ classData.class }}</td>
                <td class="px-4 py-3">{{ classData.stream || '-' }}</td>
                <td class="px-4 py-3 font-bold text-emerald-600">{{ classData.total_minutes_lost }}</td>
                <td class="px-4 py-3">{{ classData.equivalent_single_lessons }}</td>
                <td class="px-4 py-3">{{ classData.equivalent_double_lessons }}</td>
                <td class="px-4 py-3">
                  <div class="text-xs text-slate-600">{{ classData.teachers_count }} teacher(s)</div>
                  <div class="text-[10px] text-slate-400">{{ classData.teachers_list }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="getReportData().length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 py-12 text-center">
        <div class="text-sm text-slate-500">No data available for this report. Use the filters above to load data for the desired year/term.</div>
      </div>
    </div>

    <!-- Top 12 Classes Analytics -->
    <div class="rounded-[2rem] border border-slate-200 bg-white/90 p-5 shadow-xl shadow-slate-200/60 backdrop-blur">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-extrabold text-slate-900">Top Classes by Minutes Lost</h3>
        <div class="flex items-center gap-3">
          <span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 ring-1 ring-rose-200">
            {{ topClassesByMinutesLost.length }} classes
          </span>
          <button
            @click="isAnalyticsCollapsed = !isAnalyticsCollapsed"
            class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
            :title="isAnalyticsCollapsed ? 'Expand' : 'Collapse'"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 transition-transform duration-200"
              :class="{ 'rotate-180': !isAnalyticsCollapsed }"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
      </div>

      <div v-if="!isAnalyticsCollapsed">
        <div v-if="topClassesByMinutesLost.length > 0" class="grid gap-3 sm:grid-cols-2">
          <div
            v-for="(item, index) in topClassesByMinutesLost"
            :key="`${item.class}_${item.stream}_${index}`"
            class="rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-50 to-white p-4 transition hover:shadow-md"
          >
            <div class="mb-2 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-rose-500 to-pink-600 text-xs font-bold text-white shadow-lg">
                  {{ index + 1 }}
                </div>
                <div>
                  <div class="font-bold text-slate-900 text-sm">{{ item.class }}{{ item.stream ? ` (${item.stream})` : '' }}</div>
                  <div class="text-[11px] text-slate-500">{{ item.teachers.length }} teacher(s) • {{ item.total_lessons }} lesson(s)</div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-lg font-extrabold text-rose-600">{{ item.total_minutes_lost }}</div>
                <div class="text-[11px] font-semibold text-slate-500">mins lost</div>
              </div>
            </div>

            <!-- Horizontal Bar Chart -->
            <div class="relative h-3 w-full overflow-hidden rounded-full bg-slate-200">
              <div
                class="absolute left-0 top-0 h-full rounded-full bg-gradient-to-r from-rose-500 to-pink-600 transition-all duration-500 ease-out"
                :style="{
                  width: `${(item.total_minutes_lost / Math.max(...topClassesByMinutesLost.map(c => c.total_minutes_lost))) * 100}%`
                }"
              ></div>
            </div>
          </div>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 py-12 text-center">
          <div class="text-sm text-slate-500">No class data available</div>
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
        class="rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/50 overflow-hidden"
      >
        <!-- Horizontal Teacher Info Card -->
        <div class="bg-gradient-to-r from-blue-700 via-blue-600 to-blue-500 px-6 py-5 sm:px-8 sm:py-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
              <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-2xl bg-white/20 text-2xl font-bold text-white shadow-xl ring-2 ring-white/30">
                {{ group.teacher_name?.charAt(0) || 'T' }}
              </div>
              <div>
                <div class="text-xs font-bold uppercase tracking-wider text-white/80">Teacher</div>
                <div class="text-xl font-extrabold text-white">{{ group.teacher_name || '-' }}</div>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-6 lg:gap-8">
              <div class="text-center">
                <div class="text-xs font-bold uppercase tracking-wider text-white/70">Classes</div>
                <div class="text-2xl font-extrabold text-white">{{ group.subjects_classes.length }}</div>
              </div>

              <div class="text-center">
                <div class="text-xs font-bold uppercase tracking-wider text-white/70">Total Lessons</div>
                <div class="text-2xl font-extrabold text-white">{{ group.total_lessons }}</div>
              </div>

              <div class="text-center">
                <div class="text-xs font-bold uppercase tracking-wider text-white/70">Time Lost</div>
                <div class="text-2xl font-extrabold text-white">{{ group.total_minutes_lost }}</div>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="openTeacherView(group)"
                class="rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/20 hover:shadow-lg"
                title="View attendance"
                aria-label="View attendance"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0A9 9 0 1112 3a9 9 0 019 9zm-9 0a3 3 0 110-6 3 3 0 010 6z" />
                </svg>
              </button>

              <button
                @click="openQuickAddModal(group)"
                class="rounded-xl border border-white/30 bg-white/10 px-4 py-2 text-sm font-bold text-white transition hover:bg-white/20 hover:shadow-lg"
                title="Add attendance"
                aria-label="Add attendance"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Subject & Class Details Section -->
        <div class="p-4 sm:p-5">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-lg font-extrabold text-slate-900">
              Subject & Class Details
            </h3>

            <div class="flex flex-wrap items-center gap-2">
              <span class="detail-badge">Year {{ group.year }}</span>
              <span class="detail-badge">Term {{ group.term }}</span>
              <span class="detail-badge detail-badge-rose">{{ group.total_minutes_lost }} mins lost</span>
            </div>
          </div>

          <div class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-slate-50">
            <div class="space-y-4 p-4">
              <div
                v-for="subjectClass in group.subjects_classes"
                :key="subjectClass.key"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
              >
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-gradient-to-r from-blue-50 to-blue-50 px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-600 text-xs font-bold text-white">
                      {{ subjectClass.subject?.charAt(0) || 'S' }}
                    </div>
                    <div>
                      <div class="text-sm font-bold text-blue-700">{{ subjectClass.subject || '-' }}</div>
                      <div class="text-[11px] text-slate-500">{{ subjectClass.class || '-' }} {{ subjectClass.stream ? `(${subjectClass.stream})` : '' }} • {{ subjectClass.total_lessons }} lesson(s)</div>
                    </div>
                  </div>

                  <div class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 ring-1 ring-rose-200">
                    {{ subjectClass.total_minutes_lost }} mins lost
                  </div>
                </div>

                <div class="space-y-3 p-4">
                  <div
                    v-for="week in subjectClass.weeks"
                    :key="week.week_number"
                    class="overflow-hidden rounded-xl border border-slate-100 bg-slate-50"
                  >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white px-3 py-2">
                      <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-500 text-[10px] font-bold text-white">
                          {{ week.week_number }}
                        </div>
                        <div>
                          <div class="text-xs font-bold text-blue-600">Week {{ week.week_number }}</div>
                          <div class="text-[10px] text-slate-500">{{ week.entries.length }} record(s)</div>
                        </div>
                      </div>

                      <div class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 ring-1 ring-rose-200">
                        {{ getWeekTotalLost(week) }} mins lost
                      </div>
                    </div>

                    <div class="overflow-x-auto">
                      <table class="min-w-[800px] w-full">
                        <thead class="bg-slate-100">
                          <tr class="text-left text-[10px] uppercase text-slate-500">
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Day</th>
                            <th class="px-3 py-2">In</th>
                            <th class="px-3 py-2">Out</th>
                            <th class="px-3 py-2">Expected</th>
                            <th class="px-3 py-2">Actual</th>
                            <th class="px-3 py-2">Lost</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                          </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                          <tr v-for="entry in week.entries" :key="entry.id" class="hover:bg-slate-50">
                            <td class="px-3 py-2 text-xs font-semibold text-slate-700">{{ entry.attendance_date }}</td>
                            <td class="px-3 py-2 text-xs font-bold text-blue-600">{{ entry.day_of_week || '-' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-700">{{ entry.time_in }}</td>
                            <td class="px-3 py-2 text-xs text-slate-700">{{ entry.time_out }}</td>
                            <td class="px-3 py-2 text-xs text-slate-700">{{ entry.expected_minutes }}</td>
                            <td class="px-3 py-2 text-xs text-slate-700">{{ entry.actual_minutes }}</td>
                            <td class="px-3 py-2">
                              <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ring-1"
                                :class="entry.minutes_lost > 0
                                  ? 'bg-rose-50 text-rose-700 ring-rose-200'
                                  : 'bg-emerald-50 text-emerald-700 ring-emerald-200'"
                              >
                                {{ entry.minutes_lost }}
                              </span>
                            </td>
                            <td class="px-3 py-2">
                              <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ring-1"
                                :class="entry.minutes_lost === 0
                                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                  : entry.compensation_status === 'Fully Compensated'
                                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                  : entry.compensation_status === 'Partially Compensated'
                                  ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                  : 'bg-rose-50 text-rose-700 ring-rose-200'"
                              >
                                {{ entry.minutes_lost === 0 ? 'Complete' : (entry.compensation_status || 'Not Compensated') }}
                              </span>
                            </td>
                            <td class="px-3 py-2">
                              <div class="flex justify-end gap-1">
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
import { ref, onMounted, computed, watch } from 'vue';
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
const filterTerm = ref(2);
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

  return {
    records: rows.length,
    total_minutes_lost: totalLost,
    equivalent_single_lessons: Math.floor(totalLost / 40),
    equivalent_double_lessons: Math.floor(totalLost / 80),
    compensated_lessons: compensatedLessons
  };
};

const subjects = computed(() => {
  const result = [...new Set(allSubjects.value.map(s => s.subject_name || s.subject || s.name).filter(Boolean))].sort();
  console.log('Subjects computed:', result);
  return result;
});

const classes = computed(() => {
  const result = [...new Set(allClasses.value.map(c => c.class_name || c.class || c.name).filter(Boolean))].sort();
  console.log('Classes computed:', result);
  return result;
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
        total_lessons: 0
      };
    }

    teacherMap[record.teacher_id].total_minutes_lost += Number(record.minutes_lost || 0);
    teacherMap[record.teacher_id].total_lessons += 1;
  });

  return Object.values(teacherMap)
    .filter(item => item.total_lessons >= 10)
    .map(item => ({
      ...item,
      equivalent_single_lessons: Math.floor(item.total_minutes_lost / 40),
      equivalent_double_lessons: Math.floor(item.total_minutes_lost / 80),
      performance_percentage: item.total_lessons > 0
        ? Math.round((1 - (item.total_minutes_lost / (item.total_lessons * 40))) * 100)
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

const getReportData = () => {
  switch (reportType.value) {
    case 'top-teachers':
      return topTeachersByMinutesLost.value;
    case 'few-teachers':
      return teachersFewMinutesLosts.value;
    case 'top-classes':
      return classesMostMinutesLost.value;
    case 'few-classes':
      return classesFewMinutesLost.value;
    default:
      return [];
  }
};

const printReport = () => {
  const data = getReportData();
  if (data.length === 0) return;

  const doc = new jsPDF();
  let title = '';

  switch (reportType.value) {
    case 'top-teachers':
      title = 'Top 20 Teachers - Highest Minutes Lost';
      break;
    case 'few-teachers':
      title = 'Teachers - Few Minutes Lost';
      break;
    case 'top-classes':
      title = 'Classes - Most Minutes Lost';
      break;
    case 'few-classes':
      title = 'Classes - Few Minutes Lost';
      break;
  }

  doc.setFontSize(18);
  doc.text(title, 14, 22);
  doc.setFontSize(10);
  doc.text(`Year: ${filterYear.value} | Term: ${filterTerm.value || 'All Terms'}`, 14, 30);

  let columns = [];
  let rows = [];

  if (reportType.value === 'top-teachers') {
    columns = ['Rank', 'Teacher', 'Minutes Lost', 'Single Lessons', 'Double Lessons', 'Total Lessons', 'Classes'];
    rows = data.map((item, index) => [
      index + 1,
      item.teacher_name,
      item.total_minutes_lost,
      item.equivalent_single_lessons,
      item.equivalent_double_lessons,
      item.total_lessons,
      item.classes_list
    ]);
  } else if (reportType.value === 'few-teachers') {
    columns = ['Teacher', 'Minutes Lost', 'Single Lessons', 'Double Lessons', 'Total Lessons', 'Performance %'];
    rows = data.map(item => [
      item.teacher_name,
      item.total_minutes_lost,
      item.equivalent_single_lessons,
      item.equivalent_double_lessons,
      item.total_lessons,
      item.performance_percentage + '%'
    ]);
  } else if (reportType.value === 'top-classes') {
    columns = ['Class', 'Stream', 'Minutes Lost', 'Single Lessons', 'Double Lessons', 'Top 5 Teachers'];
    rows = data.map((item, index) => [
      item.class,
      item.stream || '-',
      item.total_minutes_lost,
      item.equivalent_single_lessons,
      item.equivalent_double_lessons,
      item.top_teachers.map((t, i) => `${i + 1}. ${t.teacher_name} (${t.minutes_lost})`).join('\n')
    ]);
  } else if (reportType.value === 'few-classes') {
    columns = ['Class', 'Stream', 'Minutes Lost', 'Single Lessons', 'Double Lessons', 'Teachers'];
    rows = data.map((item, index) => [
      item.class,
      item.stream || '-',
      item.total_minutes_lost,
      item.equivalent_single_lessons,
      item.equivalent_double_lessons,
      item.teachers_list
    ]);
  }

  autoTable(doc, {
    startY: 40,
    head: columns,
    body: rows,
    theme: 'grid',
    headStyles: {
      fillColor: [239, 68, 68],
      textColor: 255,
      fontSize: 10
    },
    styles: {
      fontSize: 9,
      cellPadding: 3
    }
  });

  doc.save(`${title.toLowerCase().replace(/\s+/g, '-')}-${filterYear.value}.pdf`);
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

const downloadPDF = () => {
  const doc = new jsPDF();

  doc.setFontSize(18);
  doc.text('Teacher Attendance and Time Lost Report', 14, 22);

  doc.setFontSize(11);
  doc.setTextColor(100);

  const teacherName = filterTeacher.value
    ? teachersList.value.find(t => String(t.id) === String(filterTeacher.value))?.full_name || ''
    : '';

  const filterInfo = [
    `Year: ${filterYear.value}`,
    `Term: ${filterTerm.value}`,
    filterWeek.value ? `Week: ${filterWeek.value}` : 'Week: All',
    teacherName ? `Teacher: ${teacherName}` : ''
  ].filter(Boolean).join(' | ');

  doc.text(filterInfo, 14, 30);
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);

  doc.setFontSize(12);
  doc.setTextColor(40);
  doc.text(`Total Minutes Lost: ${summary.value.total_minutes_lost}`, 14, 46);
  doc.text(`Single Lessons: ${summary.value.equivalent_single_lessons}`, 14, 52);
  doc.text(`Double Lessons: ${summary.value.equivalent_double_lessons}`, 14, 58);

  const tableData = records.value.map(record => [
    record.teacher_name || '',
    record.subject || '',
    record.class || '',
    record.stream || '',
    `Week ${record.week_number || ''}`,
    record.attendance_date || '',
    record.time_in || '',
    record.time_out || '',
    record.expected_minutes || 0,
    record.actual_minutes || 0,
    record.minutes_lost || 0,
    record.cumulative_minutes_lost || 0
  ]);

  autoTable(doc, {
    head: [[
      'Teacher', 'Subject', 'Class', 'Stream', 'Week', 'Date',
      'Time In', 'Time Out', 'Expected', 'Actual', 'Lost', 'Cumulative'
    ]],
    body: tableData,
    startY: 65,
    styles: {
      fontSize: 8,
      cellPadding: 3
    },
    headStyles: {
      fillColor: [79, 70, 229],
      textColor: 255,
      fontStyle: 'bold'
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245]
    },
    margin: { top: 10, right: 10, bottom: 10, left: 10 }
  });

  doc.save(`teacher-attendance-${filterYear.value}-term-${filterTerm.value}.pdf`);
};

onMounted(() => {
  loadClasses();
  loadSubjects();
  loadTeachers();
  loadData();
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

.summary-card {
  border-radius: 1.6rem;
  border: 1px solid rgb(226 232 240);
  background: white;
  padding: 1.2rem;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.summary-label {
  font-size: 13px;
  font-weight: 600;
  color: rgb(100 116 139);
}

.summary-value {
  margin-top: 0.45rem;
  font-size: 1.9rem;
  font-weight: 800;
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
  .summary-value {
    font-size: 1.5rem;
  }

  .mini-value {
    font-size: 1rem;
  }
}
</style>
