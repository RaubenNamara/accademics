<template>
  <div class="space-y-6">
    <!-- Header with Gradient Background -->
    <div class="rounded-2xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-6 shadow-lg text-white">
      <h2 class="text-2xl font-bold">Reports</h2>
      <p class="text-sm opacity-80 mt-1">Generate and view performance reports for teachers and modules</p>
    </div>
    
    <!-- Report Selection -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6">
      <div class="grid gap-4 sm:grid-cols-5">
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Report Type</label>
          <select v-model="reportType" @change="onReportTypeChange" class="input">
            <option value="weekly">Weekly Lesson Monitoring</option>
            <option value="termly">Termly Comprehensive</option>
            <option value="yearly">Yearly Summary</option>
            <option value="best-teachers">Best Teachers</option>
            <option value="printable">Single Teacher Report</option>
            <option value="top20">Top 20 Teachers - Highest Minutes Lost</option>
            <option value="few">Teachers - Few Minutes Lost</option>
            <option value="class-top">Classes - Most Minutes Lost</option>
            <option value="class-few">Classes - Few Minutes Lost</option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Year</label>
          <select v-model="selectedYear" class="input">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="mb-2 block text-sm font-medium text-slate-700">Term</label>
          <select v-model="selectedTerm" class="input">
            <option value="">All Terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </div>
        <div v-if="reportType === 'weekly' || reportType === 'printable'">
          <label class="mb-2 block text-sm font-medium text-slate-700">Day</label>
          <select v-model="selectedDay" class="input">
            <option value="">All Days</option>
            <option value="Mon">Monday</option>
            <option value="Tue">Tuesday</option>
            <option value="Wed">Wednesday</option>
            <option value="Thu">Thursday</option>
            <option value="Fri">Friday</option>
            <option value="Sat">Saturday</option>
            <option value="Sun">Sunday</option>
          </select>
        </div>
        <div v-if="reportType === 'weekly' || reportType === 'printable'">
          <label class="mb-2 block text-sm font-medium text-slate-700">Compensation Status</label>
          <select v-model="selectedCompensationStatus" class="input">
            <option value="">All Status</option>
            <option value="compensated">Compensated</option>
            <option value="not-compensated">Not Compensated</option>
          </select>
        </div>
        <div v-if="reportType === 'printable'">
          <label class="mb-2 block text-sm font-medium text-slate-700">Teacher</label>
          <select v-model="selectedTeacher" class="input">
            <option value="">Select Teacher</option>
            <option v-for="t in teachersList" :key="t.id" :value="t.id">{{ t.full_name }}</option>
          </select>
        </div>
        <div v-if="reportType === 'best-teachers'">
          <label class="mb-2 block text-sm font-medium text-slate-700">Award Type</label>
          <select v-model="awardType" class="input">
            <option value="week">Weekly</option>
            <option value="term">Termly</option>
          </select>
        </div>
      </div>
      <div class="mt-4 flex gap-3">
        <button @click="generateReport" :disabled="loading" class="btn-primary">
          <svg v-if="loading" class="mr-2 h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          {{ loading ? 'Generating...' : 'Generate Report' }}
        </button>
        <button v-if="reportData" @click="downloadPDF" class="rounded-xl bg-green-600 text-white px-5 py-2.5 text-sm font-medium hover:bg-green-700 transition-colors">
          <svg class="mr-2 h-5 w-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          Download PDF
        </button>
        <button v-if="reportData" @click="printReport" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
          <svg class="mr-2 h-5 w-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
          Print
        </button>
      </div>
    </div>
    
    <!-- Report Display -->
    <div v-if="reportData" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
      <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
        <h3 class="text-lg font-bold text-slate-900">{{ reportTitle }}</h3>
        <p class="text-sm text-slate-600">{{ reportSubtitle }}</p>
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
                  <td class="px-4 py-3 font-bold text-blue-600">{{ record.day_of_week || '-' }}</td>
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

const reportType = ref('weekly');
const selectedYear = ref(new Date().getFullYear());
const selectedTerm = ref('');
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
  doc.setTextColor(59, 130, 246);
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
          headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
          headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
            headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
          headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
          headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
            headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
            headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
            headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
            headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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
            headStyles: { fillColor: [59, 130, 246], textColor: 255, fontStyle: 'bold' },
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

.btn-primary {
  display: inline-flex;
  align-items: center;
  padding: 0.625rem 1.25rem;
  border-radius: 0.75rem;
  background: linear-gradient(to right, #2563eb, #1d4ed8);
  color: white;
  font-weight: 500;
  font-size: 0.875rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  transition: all 0.2s;
}

.btn-primary:hover {
  background: linear-gradient(to right, #1d4ed8, #1e40af);
  transform: translateY(-1px);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>

