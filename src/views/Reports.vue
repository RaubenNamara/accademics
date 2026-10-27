<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Reports</h1>
        </div>
      </div>
    </div>

    <section class="card overflow-hidden">
      <header class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <span class="step-dot">1</span>
        <p class="font-semibold text-gray-900">Choose a report</p>
      </header>
      <div class="space-y-4 p-4 sm:p-5">
        <div v-for="group in REPORT_GROUPS" :key="group.label">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ group.label }}</p>
          <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <button
              v-for="r in group.items"
              :key="r.value"
              type="button"
              class="flex items-start gap-3 rounded-xl border p-3 text-left transition"
              :class="reportType === r.value ? 'border-brand-500 bg-brand-50/60 ring-2 ring-brand-100' : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'"
              :aria-pressed="reportType === r.value"
              @click="reportType = r.value; onReportTypeChange()"
            >
              <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg ring-1" :class="reportType === r.value ? 'bg-brand-600 text-white ring-brand-600' : 'bg-gray-50 text-gray-600 ring-gray-200'"><component :is="r.icon" class="h-4 w-4" /></span>
              <span class="min-w-0">
                <span class="block text-sm font-semibold text-gray-900">{{ r.label }}</span>
                <span class="block text-xs text-gray-500">{{ r.hint }}</span>
              </span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="card overflow-hidden">
      <header class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <span class="step-dot">2</span>
        <p class="font-semibold text-gray-900">Period and options</p>
      </header>
      <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
        <label class="block">
          <span class="field-label">Year</span>
          <select v-model="selectedYear" class="field-input">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
        <label class="block">
          <span class="field-label">Term</span>
          <select v-model="selectedTerm" class="field-input">
            <option value="">All terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </label>
        <label v-if="reportType === 'weekly' || reportType === 'printable'" class="block">
          <span class="field-label">Day</span>
          <select v-model="selectedDay" class="field-input">
            <option value="">All days</option>
            <option value="Mon">Monday</option>
            <option value="Tue">Tuesday</option>
            <option value="Wed">Wednesday</option>
            <option value="Thu">Thursday</option>
            <option value="Fri">Friday</option>
            <option value="Sat">Saturday</option>
            <option value="Sun">Sunday</option>
          </select>
        </label>
        <label v-if="reportType === 'weekly' || reportType === 'printable'" class="block">
          <span class="field-label">Compensation</span>
          <select v-model="selectedCompensationStatus" class="field-input">
            <option value="">All</option>
            <option value="compensated">Compensated</option>
            <option value="not-compensated">Not compensated</option>
          </select>
        </label>
        <label v-if="reportType === 'printable'" class="block">
          <span class="field-label">Teacher</span>
          <select v-model="selectedTeacher" class="field-input">
            <option value="">Select teacher</option>
            <option v-for="t in teachersList" :key="t.id" :value="t.id">{{ t.full_name }}</option>
          </select>
        </label>
        <label v-if="reportType === 'best-teachers'" class="block">
          <span class="field-label">Award</span>
          <select v-model="awardType" class="field-input">
            <option value="week">Weekly</option>
            <option value="term">Termly</option>
          </select>
        </label>
      </div>
      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50/60 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600">{{ currentReportLabel }}</p>
        <button type="button" class="tool-btn-primary disabled:cursor-not-allowed disabled:opacity-50" :disabled="loading" @click="generateReport">
          <RefreshCw v-if="loading" class="h-4 w-4 animate-spin" /><Play v-else class="h-4 w-4" />{{ loading ? 'Generating…' : 'Generate report' }}
        </button>
      </footer>
    </section>

    <!-- Report Display -->
    <div v-if="reportData" class="card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <div class="min-w-0 flex-1">
          <h3 class="font-semibold text-gray-900">{{ reportTitle }}</h3>
          <p class="text-xs text-gray-500">{{ reportSubtitle }}</p>
        </div>
        <div class="flex gap-2">
          <button type="button" class="tool-btn" title="Print" @click="printReport"><Printer class="h-4 w-4" /><span class="hidden sm:inline">Print</span></button>
          <button type="button" class="tool-btn" title="Download PDF" @click="downloadPDF"><FileText class="h-4 w-4" /><span class="hidden sm:inline">PDF</span></button>
        </div>
      </div>
      <div class="p-6" id="report-content">
        <!-- Weekly Lesson Monitoring Report -->
        <div v-if="reportType === 'weekly' && reportData.lesson_monitoring">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-100">
                <tr>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Teacher</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Class</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Stream</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Date</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Day</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Total Minutes Lost</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Single Lessons</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Double Lessons</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Compensation Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="record in reportData.lesson_monitoring" :key="record.id" class="border-t border-slate-100">
                  <td class="px-4 py-3">{{ record.teacher_name }}</td>
                  <td class="px-4 py-3">{{ record.class }}</td>
                  <td class="px-4 py-3">{{ record.stream }}</td>
                  <td class="px-4 py-3">{{ record.attendance_date }}</td>
                  <td class="px-4 py-3 font-bold text-brand-600">{{ record.day_of_week || '-' }}</td>
                  <td class="px-4 py-3">{{ record.total_minutes_lost }}</td>
                  <td class="px-4 py-3">{{ record.equivalent_single_lessons }}</td>
                  <td class="px-4 py-3">{{ record.equivalent_double_lessons }}</td>
                  <td class="px-4 py-3">
                    <span
                      class="inline-flex rounded-full px-2 py-1 text-xs font-bold ring-1"
                      :class="record.is_compensated
                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                        : 'bg-rose-50 text-rose-700 ring-rose-200'"
                    >
                      {{ record.is_compensated ? 'Compensated' : 'Not Compensated' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Termly Comprehensive Report -->
        <div v-if="reportType === 'termly' && reportData.termly_report">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-100">
                <tr>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Teacher</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Subject</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Class</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">AGP</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Obs Avg</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Duty %</th>
                  <th class="px-4 py-3 text-left font-semibold text-slate-700">Time Lost</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="record in reportData.termly_report" :key="record.id" class="border-t border-slate-100">
                  <td class="px-4 py-3">{{ record.full_name }}</td>
                  <td class="px-4 py-3">{{ record.subject }}</td>
                  <td class="px-4 py-3">{{ record.class }}</td>
                  <td class="px-4 py-3">{{ record.agp }}</td>
                  <td class="px-4 py-3">{{ record.average_score }}</td>
                  <td class="px-4 py-3">{{ record.percentage }}</td>
                  <td class="px-4 py-3">{{ record.total_minutes_lost }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Yearly Summary Report -->
        <div v-if="reportType === 'yearly'">
          <div v-for="term in [1, 2, 3]" :key="term" class="mb-6">
            <h4 class="mb-3 font-semibold text-slate-900">Term {{ term }}</h4>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-100">
                  <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Teacher</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Avg AGP</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Avg Duty</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Total Time Lost</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="record in reportData[`term_${term}`]" :key="record.id" class="border-t border-slate-100">
                    <td class="px-4 py-3">{{ record.full_name }}</td>
                    <td class="px-4 py-3">{{ record.avg_agp }}</td>
                    <td class="px-4 py-3">{{ record.avg_duty }}</td>
                    <td class="px-4 py-3">{{ record.total_time_lost }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <!-- Best Teachers Report -->
        <div v-if="reportType === 'best-teachers'">
          <div v-if="reportData.awards && reportData.awards.length > 0" class="mb-6">
            <h4 class="mb-3 font-semibold text-slate-900">Awarded Teachers</h4>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-100">
                  <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Teacher</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Subject</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Class</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Award Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Awarded At</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="record in reportData.awards" :key="record.id" class="border-t border-slate-100">
                    <td class="px-4 py-3">{{ record.teacher_name }}</td>
                    <td class="px-4 py-3">{{ record.subject }}</td>
                    <td class="px-4 py-3">{{ record.class }}</td>
                    <td class="px-4 py-3">{{ record.award_type }}</td>
                    <td class="px-4 py-3">{{ record.awarded_at }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-if="reportData.top_candidates">
            <h4 class="mb-3 font-semibold text-slate-900">Top Candidates</h4>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-slate-100">
                  <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Teacher</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Subject</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Class</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Week</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Percentage</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="record in reportData.top_candidates" :key="record.id" class="border-t border-slate-100">
                    <td class="px-4 py-3">{{ record.full_name }}</td>
                    <td class="px-4 py-3">{{ record.subject }}</td>
                    <td class="px-4 py-3">{{ record.class }}</td>
                    <td class="px-4 py-3">{{ record.week_number }}</td>
                    <td class="px-4 py-3">{{ record.percentage }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <!-- Single Teacher Printable Report -->
        <div v-if="reportType === 'printable' && reportData.teacher">
          <div class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
              <h4 class="mb-3 font-semibold text-slate-900">Teacher Information</h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><span class="font-medium">Name:</span> {{ reportData.teacher.full_name }}</div>
                <div><span class="font-medium">Subject:</span> {{ reportData.teacher.subject }}</div>
                <div><span class="font-medium">Class:</span> {{ reportData.teacher.class }}</div>
                <div><span class="font-medium">Stream:</span> {{ reportData.teacher.stream }}</div>
              </div>
            </div>
            
        <!-- Top 20 Teachers - Highest Minutes Lost -->
        <div v-if="reportType === 'top20' && reportData.top_teachers">
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
                <tr v-for="(teacher, index) in reportData.top_teachers" :key="teacher.teacher_id" class="border-t border-slate-100">
                  <td class="px-4 py-3 font-bold text-rose-600">{{ index + 1 }}</td>
                  <td class="px-4 py-3 font-semibold">{{ teacher.teacher_name }}</td>
                  <td class="px-4 py-3 font-bold text-rose-600">{{ teacher.total_minutes_lost }}</td>
                  <td class="px-4 py-3">{{ teacher.equivalent_single_lessons }}</td>
                  <td class="px-4 py-3">{{ teacher.equivalent_double_lessons }}</td>
                  <td class="px-4 py-3">{{ teacher.total_lessons }}</td>
                  <td class="px-4 py-3">{{ teacher.classes_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Teachers - Few Minutes Lost -->
        <div v-if="reportType === 'few' && reportData.few_teachers">
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
                <tr v-for="teacher in reportData.few_teachers" :key="teacher.teacher_id" class="border-t border-slate-100">
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
        <div v-if="reportType === 'class-top' && reportData.most_classes">
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
                  <th class="px-4 py-3 text-left font-semibold text-rose-700">Teachers Affected</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(classData, index) in reportData.most_classes" :key="classData.class_key" class="border-t border-slate-100">
                  <td class="px-4 py-3 font-bold text-rose-600">{{ index + 1 }}</td>
                  <td class="px-4 py-3 font-semibold">{{ classData.class }}</td>
                  <td class="px-4 py-3">{{ classData.stream || '-' }}</td>
                  <td class="px-4 py-3 font-bold text-rose-600">{{ classData.total_minutes_lost }}</td>
                  <td class="px-4 py-3">{{ classData.equivalent_single_lessons }}</td>
                  <td class="px-4 py-3">{{ classData.equivalent_double_lessons }}</td>
                  <td class="px-4 py-3">{{ classData.teachers_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- Classes - Few Minutes Lost -->
        <div v-if="reportType === 'class-few' && reportData.few_classes">
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
                <tr v-for="classData in reportData.few_classes" :key="classData.class_key" class="border-t border-slate-100">
                  <td class="px-4 py-3 font-semibold">{{ classData.class }}</td>
                  <td class="px-4 py-3">{{ classData.stream || '-' }}</td>
                  <td class="px-4 py-3 font-bold text-emerald-600">{{ classData.total_minutes_lost }}</td>
                  <td class="px-4 py-3">{{ classData.equivalent_single_lessons }}</td>
                  <td class="px-4 py-3">{{ classData.equivalent_double_lessons }}</td>
                  <td class="px-4 py-3">{{ classData.teachers_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
            
            <div v-if="reportData.subject_performance && reportData.subject_performance.length > 0">
              <h4 class="mb-3 font-semibold text-slate-900">Subject Performance</h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-slate-100">
                    <tr>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Subject</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Class</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">BOT1</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">EOT1</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">AGP</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="record in reportData.subject_performance" :key="record.id" class="border-t border-slate-100">
                      <td class="px-4 py-3">{{ record.subject }}</td>
                      <td class="px-4 py-3">{{ record.class }}</td>
                      <td class="px-4 py-3">{{ record.bot1 }}</td>
                      <td class="px-4 py-3">{{ record.eot1 }}</td>
                      <td class="px-4 py-3">{{ record.agp }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            
            <div v-if="reportData.duty_performance && reportData.duty_performance.length > 0">
              <h4 class="mb-3 font-semibold text-slate-900">Duty Performance</h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-slate-100">
                    <tr>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Week</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Punctuality</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Supervision</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Cleanliness</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Percentage</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="record in reportData.duty_performance" :key="record.id" class="border-t border-slate-100">
                      <td class="px-4 py-3">{{ record.week_number }}</td>
                      <td class="px-4 py-3">{{ record.punctuality }}</td>
                      <td class="px-4 py-3">{{ record.supervision }}</td>
                      <td class="px-4 py-3">{{ record.cleanliness }}</td>
                      <td class="px-4 py-3">{{ record.percentage }}%</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            
            <div v-if="reportData.observations && reportData.observations.length > 0">
              <h4 class="mb-3 font-semibold text-slate-900">Lesson Observations</h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-slate-100">
                    <tr>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Subject</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Class</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Round 1</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Round 2</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Average</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="record in reportData.observations" :key="record.id" class="border-t border-slate-100">
                      <td class="px-4 py-3">{{ record.subject }}</td>
                      <td class="px-4 py-3">{{ record.class }}</td>
                      <td class="px-4 py-3">{{ record.round_1_score }}</td>
                      <td class="px-4 py-3">{{ record.round_2_score }}</td>
                      <td class="px-4 py-3">{{ record.average_score }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            
            <div v-if="reportData.class_performance && reportData.class_performance.length > 0">
              <h4 class="mb-3 font-semibold text-slate-900">Class Teacher Performance</h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-slate-100">
                    <tr>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Class</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Stream</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Parents Contacted</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Average Score</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Comment</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="record in reportData.class_performance" :key="record.id" class="border-t border-slate-100">
                      <td class="px-4 py-3">{{ record.class }}</td>
                      <td class="px-4 py-3">{{ record.stream }}</td>
                      <td class="px-4 py-3">{{ record.parents_contacted }}</td>
                      <td class="px-4 py-3">{{ record.average_score }}%</td>
                      <td class="px-4 py-3">{{ record.average_comment }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            
            <div v-if="reportData.awards && reportData.awards.length > 0">
              <h4 class="mb-3 font-semibold text-slate-900">Awards</h4>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="bg-slate-100">
                    <tr>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Award Type</th>
                      <th class="px-4 py-3 text-left font-semibold text-slate-700">Awarded At</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="record in reportData.awards" :key="record.id" class="border-t border-slate-100">
                      <td class="px-4 py-3">{{ record.award_type }}</td>
                      <td class="px-4 py-3">{{ record.awarded_at }}</td>
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
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { reportsAPI, teachersAPI } from '../services/api.js';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import { CalendarDays, CalendarRange, CalendarCheck, Trophy, UserRound, TrendingDown, TrendingUp, School, Building2, RefreshCw, Play, Printer, FileText } from 'lucide-vue-next';

const REPORT_GROUPS = [
  { label: 'Lesson monitoring and performance', items: [
    { value: 'weekly', label: 'Weekly lesson monitoring', hint: 'Lessons, time lost and compensation', icon: CalendarDays },
    { value: 'termly', label: 'Termly comprehensive', hint: 'All modules for one term', icon: CalendarRange },
    { value: 'yearly', label: 'Yearly summary', hint: 'The whole year at a glance', icon: CalendarCheck },
    { value: 'best-teachers', label: 'Best teachers', hint: 'Weekly or termly awards', icon: Trophy },
    { value: 'printable', label: 'Single teacher', hint: 'One teacher, ready to print', icon: UserRound },
  ] },
  { label: 'Minutes lost', items: [
    { value: 'top20', label: 'Teachers losing most time', hint: 'Top 20 by minutes lost', icon: TrendingDown },
    { value: 'few', label: 'Teachers losing least time', hint: 'Fewest minutes lost', icon: TrendingUp },
    { value: 'class-top', label: 'Classes losing most time', hint: 'Classes with most minutes lost', icon: School },
    { value: 'class-few', label: 'Classes losing least time', hint: 'Classes with fewest minutes lost', icon: Building2 },
  ] },
];

const reportType = ref('weekly');
const selectedYear = ref(new Date().getFullYear());
const selectedTerm = ref('');
useGlobalTerm((y, t) => { selectedYear.value = y; selectedTerm.value = t; });
const selectedTeacher = ref('');
const selectedDay = ref('');
const selectedCompensationStatus = ref('');
const awardType = ref('week');
const years = Array.from({length: 5}, (_, i) => new Date().getFullYear() - 2 + i);
const teachersList = ref([]);
const reportData = ref(null);
const loading = ref(false);

const reportTitle = computed(() => {
  const titles = {
    weekly: 'Weekly Lesson Monitoring Report',
    termly: 'Termly Comprehensive Report',
    yearly: 'Yearly Summary Report',
    'best-teachers': 'Best Teachers Report',
    printable: 'Single Teacher Report',
    top20: 'Top 20 Teachers - Highest Minutes Lost',
    few: 'Teachers - Few Minutes Lost',
    'class-top': 'Classes - Most Minutes Lost',
    'class-few': 'Classes - Few Minutes Lost'
  };
  return titles[reportType.value] || 'Report';
});

const reportSubtitle = computed(() => {
  const yearPart = `Year: ${selectedYear.value}`;
  const termPart = selectedTerm.value ? ` | Term: ${selectedTerm.value}` : '';
  return `${yearPart}${termPart}`;
});

const loadTeachers = async () => {
  try {
    const result = await teachersAPI.getAll();
    if (result.success) teachersList.value = result.data.teachers || result.data;
  } catch (err) {
    console.error('Failed to load teachers:', err);
  }
};

const currentReportLabel = computed(() => REPORT_GROUPS.flatMap((g) => g.items).find((r) => r.value === reportType.value)?.label || '');

const onReportTypeChange = () => {
  reportData.value = null;
};

const generateReport = async () => {
  loading.value = true;
  reportData.value = null;
  
  console.log('Generating report type:', reportType.value);
  console.log('Year:', selectedYear.value, 'Term:', selectedTerm.value);
  
  try {
    let result;
    switch (reportType.value) {
      case 'weekly':
        result = await reportsAPI.getWeekly(selectedYear.value, selectedTerm.value);
        break;
      case 'termly':
        result = await reportsAPI.getTermly(selectedYear.value, selectedTerm.value);
        break;
      case 'yearly':
        result = await reportsAPI.getYearly(selectedYear.value);
        break;
      case 'best-teachers':
        result = await reportsAPI.getBestTeachers(selectedYear.value, selectedTerm.value, awardType.value);
        break;
      case 'printable':
        if (!selectedTeacher.value) {
          alert('Please select a teacher');
          loading.value = false;
          return;
        }
        result = await reportsAPI.getPrintable(selectedTeacher.value, selectedYear.value, selectedTerm.value);
        break;
      case 'top20':
        result = await reportsAPI.getTopTeachersLost(selectedYear.value, selectedTerm.value);
        break;
      case 'few':
        result = await reportsAPI.getTeachersFewLost(selectedYear.value, selectedTerm.value);
        break;
      case 'class-top':
        result = await reportsAPI.getClassesMostLost(selectedYear.value, selectedTerm.value);
        break;
      case 'class-few':
        result = await reportsAPI.getClassesFewLost(selectedYear.value, selectedTerm.value);
        break;
    }
    
    console.log('API Response:', result);
    
    if (result.success) {
      reportData.value = result.data;
      console.log('Report data set:', reportData.value);
    } else {
      alert('Failed to generate report: ' + (result.message || 'Unknown error'));
    }
  } catch (err) {
    console.error('Failed to generate report:', err);
    alert('Failed to generate report');
  }
  
  loading.value = false;
};

const printReport = () => {
  const printContent = document.getElementById('report-content').innerHTML;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html>
    <head>
      <title>${reportTitle.value}</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        h4 { margin-top: 20px; margin-bottom: 10px; }
        .rounded-xl { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
      </style>
    </head>
    <body>
      <h2>${reportTitle.value}</h2>
      <p>${reportSubtitle.value}</p>
      <hr>
      ${printContent}
    </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.print();
};

const downloadPDF = () => {
  if (!reportData.value) return;
  
  const doc = new jsPDF();
  let yPos = 20;
  
  // Header
  doc.setFontSize(20);
  doc.setTextColor(22, 163, 74);
  doc.text(reportTitle.value, 14, yPos);
  yPos += 10;
  
  doc.setFontSize(11);
  doc.setTextColor(100);
  doc.text(reportSubtitle.value, 14, yPos);
  yPos += 10;
  
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, yPos);
  yPos += 15;
  
  // Generate PDF based on report type
  switch (reportType.value) {
    case 'weekly':
      if (reportData.value.lesson_monitoring) {
        doc.setFontSize(14);
        doc.setTextColor(0);
        doc.text('Weekly Lesson Monitoring Report', 14, yPos);
        yPos += 10;
        
        const tableData = reportData.value.lesson_monitoring.map(record => [
          record.teacher_name,
          record.class,
          record.stream,
          record.total_minutes_lost,
          record.equivalent_single_lessons,
          record.equivalent_double_lessons
        ]);
        
        autoTable(doc, {
          head: [['Teacher', 'Class', 'Stream', 'Minutes Lost', 'Single Lessons', 'Double Lessons']],
          body: tableData,
          startY: yPos,
          styles: { fontSize: 9, cellPadding: 3 },
          headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
          alternateRowStyles: { fillColor: [245, 245, 245] },
          margin: { top: 10, right: 10, bottom: 10, left: 10 },
        });
      }
      break;
      
    case 'termly':
      if (reportData.value.termly_report) {
        doc.setFontSize(14);
        doc.setTextColor(0);
        doc.text('Termly Comprehensive Report', 14, yPos);
        yPos += 10;
        
        const tableData = reportData.value.termly_report.map(record => [
          record.full_name,
          record.subject,
          record.class,
          record.agp,
          record.average_score,
          record.percentage,
          record.total_minutes_lost
        ]);
        
        autoTable(doc, {
          head: [['Teacher', 'Subject', 'Class', 'AGP', 'Obs Avg', 'Duty %', 'Time Lost']],
          body: tableData,
          startY: yPos,
          styles: { fontSize: 9, cellPadding: 3 },
          headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
          alternateRowStyles: { fillColor: [245, 245, 245] },
          margin: { top: 10, right: 10, bottom: 10, left: 10 },
        });
      }
      break;
      
    case 'yearly':
      doc.setFontSize(14);
      doc.setTextColor(0);
      doc.text('Yearly Summary Report', 14, yPos);
      yPos += 10;
      
      [1, 2, 3].forEach(term => {
        if (reportData.value[`term_${term}`]) {
          doc.setFontSize(12);
          doc.setFont(undefined, 'bold');
          doc.text(`Term ${term}`, 14, yPos);
          yPos += 8;
          
          const tableData = reportData.value[`term_${term}`].map(record => [
            record.full_name,
            record.avg_agp,
            record.avg_duty,
            record.total_time_lost
          ]);
          
          autoTable(doc, {
            head: [['Teacher', 'Avg AGP', 'Avg Duty', 'Total Time Lost']],
            body: tableData,
            startY: yPos,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            margin: { top: 10, right: 10, bottom: 10, left: 10 },
          });
          
          yPos = doc.lastAutoTable.finalY + 10;
          
          if (yPos > 240) {
            doc.addPage();
            yPos = 20;
          }
        }
      });
      break;
      
    case 'best-teachers':
      if (reportData.value.awards && reportData.value.awards.length > 0) {
        doc.setFontSize(14);
        doc.setTextColor(0);
        doc.text('Awarded Teachers', 14, yPos);
        yPos += 10;
        
        const tableData = reportData.value.awards.map(record => [
          record.teacher_name,
          record.subject,
          record.class,
          record.award_type,
          record.awarded_at
        ]);
        
        autoTable(doc, {
          head: [['Teacher', 'Subject', 'Class', 'Award Type', 'Awarded At']],
          body: tableData,
          startY: yPos,
          styles: { fontSize: 9, cellPadding: 3 },
          headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
          alternateRowStyles: { fillColor: [245, 245, 245] },
          margin: { top: 10, right: 10, bottom: 10, left: 10 },
        });
        
        yPos = doc.lastAutoTable.finalY + 15;
      }
      
      if (reportData.value.top_candidates) {
        if (yPos > 240) {
          doc.addPage();
          yPos = 20;
        }
        
        doc.setFontSize(14);
        doc.setTextColor(0);
        doc.text('Top Candidates', 14, yPos);
        yPos += 10;
        
        const tableData = reportData.value.top_candidates.map(record => [
          record.full_name,
          record.subject,
          record.class,
          record.week_number,
          record.percentage + '%'
        ]);
        
        autoTable(doc, {
          head: [['Teacher', 'Subject', 'Class', 'Week', 'Percentage']],
          body: tableData,
          startY: yPos,
          styles: { fontSize: 9, cellPadding: 3 },
          headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
          alternateRowStyles: { fillColor: [245, 245, 245] },
          margin: { top: 10, right: 10, bottom: 10, left: 10 },
        });
      }
      break;
      
    case 'printable':
      if (reportData.value.teacher) {
        doc.setFontSize(14);
        doc.setTextColor(0);
        doc.text('Single Teacher Report', 14, yPos);
        yPos += 10;
        
        // Teacher Information
        doc.setFontSize(11);
        doc.setFont(undefined, 'bold');
        doc.text('Teacher Information', 14, yPos);
        yPos += 8;
        
        doc.setFont(undefined, 'normal');
        doc.text(`Name: ${reportData.value.teacher.full_name}`, 16, yPos);
        yPos += 6;
        doc.text(`Subject: ${reportData.value.teacher.subject}`, 16, yPos);
        yPos += 6;
        doc.text(`Class: ${reportData.value.teacher.class}`, 16, yPos);
        yPos += 6;
        doc.text(`Stream: ${reportData.value.teacher.stream}`, 16, yPos);
        yPos += 10;
        
        // Subject Performance
        if (reportData.value.subject_performance && reportData.value.subject_performance.length > 0) {
          doc.setFont(undefined, 'bold');
          doc.text('Subject Performance', 14, yPos);
          yPos += 8;
          
          const tableData = reportData.value.subject_performance.map(record => [
            record.subject,
            record.class,
            record.bot1,
            record.eot1,
            record.agp
          ]);
          
          autoTable(doc, {
            head: [['Subject', 'Class', 'BOT1', 'EOT1', 'AGP']],
            body: tableData,
            startY: yPos,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            margin: { top: 10, right: 10, bottom: 10, left: 10 },
          });
          
          yPos = doc.lastAutoTable.finalY + 10;
        }
        
        // Duty Performance
        if (reportData.value.duty_performance && reportData.value.duty_performance.length > 0) {
          if (yPos > 240) {
            doc.addPage();
            yPos = 20;
          }
          
          doc.setFont(undefined, 'bold');
          doc.text('Duty Performance', 14, yPos);
          yPos += 8;
          
          const tableData = reportData.value.duty_performance.map(record => [
            record.week_number,
            record.punctuality,
            record.supervision,
            record.cleanliness,
            record.percentage + '%'
          ]);
          
          autoTable(doc, {
            head: [['Week', 'Punctuality', 'Supervision', 'Cleanliness', 'Percentage']],
            body: tableData,
            startY: yPos,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            margin: { top: 10, right: 10, bottom: 10, left: 10 },
          });
          
          yPos = doc.lastAutoTable.finalY + 10;
        }
        
        // Observations
        if (reportData.value.observations && reportData.value.observations.length > 0) {
          if (yPos > 240) {
            doc.addPage();
            yPos = 20;
          }
          
          doc.setFont(undefined, 'bold');
          doc.text('Lesson Observations', 14, yPos);
          yPos += 8;
          
          const tableData = reportData.value.observations.map(record => [
            record.subject,
            record.class,
            record.round_1_score,
            record.round_2_score,
            record.average_score
          ]);
          
          autoTable(doc, {
            head: [['Subject', 'Class', 'Round 1', 'Round 2', 'Average']],
            body: tableData,
            startY: yPos,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            margin: { top: 10, right: 10, bottom: 10, left: 10 },
          });
          
          yPos = doc.lastAutoTable.finalY + 10;
        }
        
        // Class Performance
        if (reportData.value.class_performance && reportData.value.class_performance.length > 0) {
          if (yPos > 240) {
            doc.addPage();
            yPos = 20;
          }
          
          doc.setFont(undefined, 'bold');
          doc.text('Class Teacher Performance', 14, yPos);
          yPos += 8;
          
          const tableData = reportData.value.class_performance.map(record => [
            record.class,
            record.stream,
            record.parents_contacted,
            record.average_score + '%',
            record.average_comment
          ]);
          
          autoTable(doc, {
            head: [['Class', 'Stream', 'Parents Contacted', 'Avg Score', 'Comment']],
            body: tableData,
            startY: yPos,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            margin: { top: 10, right: 10, bottom: 10, left: 10 },
          });
          
          yPos = doc.lastAutoTable.finalY + 10;
        }
        
        // Awards
        if (reportData.value.awards && reportData.value.awards.length > 0) {
          if (yPos > 240) {
            doc.addPage();
            yPos = 20;
          }
          
          doc.setFont(undefined, 'bold');
          doc.text('Awards', 14, yPos);
          yPos += 8;
          
          const tableData = reportData.value.awards.map(record => [
            record.award_type,
            record.awarded_at
          ]);
          
          autoTable(doc, {
            head: [['Award Type', 'Awarded At']],
            body: tableData,
            startY: yPos,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [22, 163, 74], textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 245, 245] },
            margin: { top: 10, right: 10, bottom: 10, left: 10 },
          });
        }
      }
      break;
  }
  
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
  doc.save(`${reportType.value}-report-${new Date().toISOString().split('T')[0]}.pdf`);
};

onMounted(() => {
  loadTeachers();
});
</script>

<style scoped>
.step-dot { @apply flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700 ring-1 ring-brand-100; }
.field-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.field-input { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.tool-btn-primary { @apply inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700; }
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
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  padding: 0.625rem 1.25rem;
  border-radius: 0.75rem;
  background: #16a34a;
  color: white;
  font-weight: 500;
  font-size: 0.875rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  transition: all 0.2s;
}

.btn-primary:hover {
  background: #15803d;
  transform: translateY(-1px);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>

<style>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark th,
html.dark td {
  border-color: var(--d-line);
}
html.dark th {
  background-color: var(--d-surface);
}
html.dark .rounded-xl {
  border-color: var(--d-line);
}
html.dark .step-dot {
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
  --tw-ring-color: rgba(34, 197, 94, 0.3);
}
html.dark .field-label {
  color: var(--d-muted);
}
html.dark .field-input {
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
html.dark .input {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
</style>
