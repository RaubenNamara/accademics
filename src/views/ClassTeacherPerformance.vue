<template>
  <div class="space-y-8">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-8 text-white shadow-2xl">
      <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent"></div>
      <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
      <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
       
          <h1 class="mt-3 text-4xl font-bold tracking-tight">Class Teacher Performance Tracking</h1>
        
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            @click="loadData"
            class="group inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-medium text-white backdrop-blur transition-all hover:border-white/30 hover:bg-white/20"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
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
            @click="openAddModal"
            class="group relative inline-flex items-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 py-2.5 text-sm font-medium text-white shadow-xl shadow-blue-500/25 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-blue-500/40"
          >
            <span class="relative z-10 flex items-center gap-2">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Record
            </span>
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 p-6 shadow-xl backdrop-blur-sm">
      <div class="grid gap-5 xl:grid-cols-4">
        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year</label>
          <select
            v-model="filterYear"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
          >
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Term</label>
          <select
            v-model="filterTerm"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
          >
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </div>

        <div>
          <label class="mb-2.5 block text-sm font-semibold text-slate-700">Week</label>
          <select
            v-model="filterWeek"
            @change="loadData"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 outline-none transition-all duration-200 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-100/50"
          >
            <option value="">All Weeks</option>
            <option v-for="week in 13" :key="week" :value="week">Week {{ week }}</option>
          </select>
        </div>

        <div class="flex items-end">
          <div class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-50 to-blue-100 px-4 py-3">
            <div class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></div>
            <span class="text-sm font-medium text-slate-600">
              <span class="font-bold text-blue-600">{{ records.length }}</span> records found
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-200/60 bg-white/80 shadow-xl backdrop-blur-sm">
      <div class="border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-5">
        <h2 class="text-xl font-bold text-slate-900">Performance Records</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-[1600px] w-full divide-y divide-slate-200/60">
          <thead class="bg-gradient-to-r from-slate-50 to-slate-100/50">
            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
              <th class="border-r border-slate-200 px-6 py-4">Teacher</th>
              <th class="border-r border-slate-200 px-6 py-4">Class</th>
              <th class="border-r border-slate-200 px-6 py-4">Stream</th>
              <th class="border-r border-slate-200 px-6 py-4">Week</th>
              <th class="border-r border-slate-200 px-6 py-4 text-center">Roll Call</th>
              <th class="border-r border-slate-200 px-6 py-4 text-center">Mentorship</th>
              <th class="border-r border-slate-200 px-6 py-4 text-center">Devotion</th>
              <th class="border-r border-slate-200 px-6 py-4 text-center">Cleanliness</th>
              <th class="border-r border-slate-200 px-6 py-4 text-center">Parent Contacted</th>
              <th class="border-r border-slate-200 px-6 py-4 text-center">Weekly Score</th>
              <th class="border-r border-slate-200 px-6 py-4 text-center">Weekly Avg</th>
              <th class="border-r border-slate-200 px-6 py-4">BT1</th>
              <th class="border-r border-slate-200 px-6 py-4">T1</th>
              <th class="border-r border-slate-200 px-6 py-4">C1</th>
              <th class="border-r border-slate-200 px-6 py-4">T2</th>
              <th class="border-r border-slate-200 px-6 py-4">C2</th>
              <th class="border-r border-slate-200 px-6 py-4">T3</th>
              <th class="border-r border-slate-200 px-6 py-4">C3</th>
              <th class="border-r border-slate-200 px-6 py-4">Average</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100/60 bg-white">
            <tr v-if="groupedRecords.length === 0">
              <td colspan="19" class="px-6 py-16 text-center">
                <div class="flex flex-col items-center gap-3">
                  <div class="rounded-full bg-slate-100 p-4">
                    <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                  </div>
                  <p class="text-sm font-medium text-slate-500">No performance records found.</p>
                </div>
              </td>
            </tr>

            <template v-for="group in groupedRecords" :key="`${group.teacher_id}-${group.class}-${group.stream}-${group.year}-${group.term}`">
              <tr class="bg-gradient-to-r from-blue-50/50 to-blue-100/50">
                <td class="border-r border-slate-100 px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-sm font-bold text-white shadow-lg shadow-blue-500/30">
                      {{ group.teacher_name?.charAt(0) || '?' }}
                    </div>
                    <div class="font-semibold text-slate-900">{{ group.teacher_name }}</div>
                  </div>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-sm font-medium text-slate-700">{{ group.class }}</td>
                <td class="border-r border-slate-100 px-6 py-4 text-sm font-medium text-slate-700">{{ group.stream }}</td>
                <td class="border-r border-slate-100 px-6 py-4 text-sm font-medium text-slate-700">
                 <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-bold text-blue-800">
                  Term {{ group.term }}
                </span>
                                </td>
                <td colspan="15" class="px-6 py-4 text-sm text-slate-500">
                  {{ group.weeks.length }} week{{ group.weeks.length > 1 ? 's' : '' }} recorded
                </td>
                <td class="px-6 py-4">
                
                </td>
              </tr>

              <tr
                v-for="record in group.weeks"
                :key="record.id"
                class="transition-all duration-200 hover:bg-gradient-to-r hover:from-blue-50/30 hover:to-blue-100/30"
              >
                <td class="border-r border-slate-100 px-6 py-4 text-sm text-slate-400 pl-12">
                  Week {{ record.week }}
                </td>
                <td class="border-r border-slate-100 px-6 py-4"></td>
                <td class="border-r border-slate-100 px-6 py-4"></td>
                <td class="border-r border-slate-100 px-6 py-4"></td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <div class="flex justify-center">
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.roll_call_score)">
                      {{ getScoreLabel(record.roll_call_score) }}
                    </span>
                  </div>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <div class="flex justify-center">
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.mentorship_score)">
                      {{ getScoreLabel(record.mentorship_score) }}
                    </span>
                  </div>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <div class="flex justify-center">
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.devotion_score)">
                      {{ getScoreLabel(record.devotion_score) }}
                    </span>
                  </div>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <div class="flex justify-center">
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.cleanliness_score)">
                      {{ getScoreLabel(record.cleanliness_score) }}
                    </span>
                  </div>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <div class="flex justify-center">
                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getParentContactedClass(record.parent_contacted)">
                      {{ record.parent_contacted ? 'Yes' : 'No' }}
                    </span>
                  </div>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-xl px-3 py-1.5 text-sm font-bold" :class="getWeeklyScoreClass(record.weekly_score)">
                    {{ record.weekly_score }}/90
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span
                    class="inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-bold shadow-sm"
                    :class="record.weekly_average_score >= 70 ? 'bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-800' : 'bg-gradient-to-r from-rose-100 to-pink-100 text-rose-800'"
                  >
                    {{ record.weekly_average_score || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.bt1)">
                    {{ record.bt1 || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.t1)">
                    {{ record.t1 || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.c1)">
                    {{ record.c1 || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.t2)">
                    {{ record.t2 || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.c2)">
                    {{ record.c2 || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.t3)">
                    {{ record.t3 || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-bold" :class="getScoreClass(record.c3)">
                    {{ record.c3 || '-' }}
                  </span>
                </td>
                <td class="border-r border-slate-100 px-6 py-4">
                  <span
                    class="inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-bold shadow-sm"
                    :class="record.average_score >= 0 ? 'bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-800' : 'bg-gradient-to-r from-rose-100 to-pink-100 text-rose-800'"
                  >
                    {{ record.average_score }}%
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex justify-end gap-2">
                    <button
                      @click="editRecord(record)"
                      class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-blue-500/20"
                    >
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button
                      @click="editTerm1(record)"
                      class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-cyan-50 px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-blue-500/20"
                      title="Add T1 Score"
                    >
                      T1
                    </button>
                    <button
                      @click="editTerm2(record)"
                      class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 px-3 py-2 text-sm font-semibold text-blue-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-blue-500/20"
                      title="Add T2 Score"
                    >
                      T2
                    </button>
                    <button
                      @click="editTerm3(record)"
                      class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 px-3 py-2 text-sm font-semibold text-amber-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-amber-500/20"
                      title="Add T3 Score"
                    >
                      T3
                    </button>
                    <button
                      @click="deleteRecord(record.id)"
                      class="group relative inline-flex items-center gap-2 overflow-hidden rounded-xl border border-rose-200 bg-gradient-to-r from-rose-50 to-pink-50 px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm transition-all duration-300 hover:shadow-md hover:shadow-rose-500/20"
                    >
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="relative flex max-h-[90vh] w-full max-w-5xl flex-col rounded-3xl bg-white shadow-2xl ring-1 ring-white/20">
        <div class="absolute inset-0 rounded-3xl bg-gradient-to-br from-blue-500/5 to-blue-600/5 pointer-events-none"></div>

        <div class="relative flex-shrink-0 border-b border-slate-200/60 bg-gradient-to-r from-slate-50 to-white px-6 py-6">
          <h3 class="text-xl font-bold text-slate-900">
            {{ editingId ? 'Edit Record' : 'Add Record' }}
          </h3>
          <p class="mt-1.5 text-sm text-slate-500">
            Enter weekly performance metrics and academic scores. Calculations will be done automatically.
          </p>
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
                  <svg class="w-5 h-5 text-gray-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                  </svg>
                </div>
                <select v-model="form.teacher_id" required class="input">
                  <option value="">Select</option>
                  <option v-for="t in filteredTeachers" :key="t.id" :value="t.id">{{ t.full_name }} ({{ t.teacher_code }})</option>
                </select>
              </div>
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Class</label>
                <select v-model="form.class" class="input">
                  <option value="">Select</option>
                  <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Stream</label>
                <select v-model="form.stream" class="input">
                  <option value="">Select</option>
                  <option v-for="s in streams" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Year</label>
                <input v-model.number="form.year" type="number" class="input" />
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
                <select v-model="form.week" class="input">
                  <option :value="1">Week 1</option>
                  <option :value="2">Week 2</option>
                  <option :value="3">Week 3</option>
                  <option :value="4">Week 4</option>
                  <option :value="5">Week 5</option>
                  <option :value="6">Week 6</option>
                  <option :value="7">Week 7</option>
                  <option :value="8">Week 8</option>
                  <option :value="9">Week 9</option>
                  <option :value="10">Week 10</option>
                  <option :value="11">Week 11</option>
                  <option :value="12">Week 12</option>
                  <option :value="13">Week 13</option>
                </select>
              </div>
            </div>

            <div class="border-t border-slate-200/60 pt-6">
              <h4 class="mb-4 font-bold text-slate-900">Weekly Assessment (Each out of 20 marks)</h4>

              <div class="mb-4 rounded-xl border border-slate-200 bg-gradient-to-r from-blue-50 to-blue-50 p-4">
                <div class="mb-3 flex items-center justify-between">
                  <h6 class="font-medium text-slate-900">Roll Call</h6>
                  <span class="text-sm text-slate-600">Score: {{ form.roll_call_score }}/20</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.roll_call_score" :value="20" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500" />
                    <span class="text-sm font-medium text-emerald-800">Excellent (20)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.roll_call_score" :value="15" class="h-4 w-4 text-blue-600 focus:ring-blue-500" />
                    <span class="text-sm font-medium text-blue-800">Very Good (15)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.roll_call_score" :value="12" class="h-4 w-4 text-amber-600 focus:ring-amber-500" />
                    <span class="text-sm font-medium text-amber-800">Good (12)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.roll_call_score" :value="0" class="h-4 w-4 text-red-600 focus:ring-red-500" />
                    <span class="text-sm font-medium text-red-800">Need Improvement (0)</span>
                  </label>
                </div>
              </div>

              <div class="mb-4 rounded-xl border border-slate-200 bg-gradient-to-r from-blue-50 to-blue-100 p-4">
                <div class="mb-3 flex items-center justify-between">
                  <h6 class="font-medium text-slate-900">Mentorship</h6>
                  <span class="text-sm text-slate-600">Score: {{ form.mentorship_score }}/20</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.mentorship_score" :value="20" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500" />
                    <span class="text-sm font-medium text-emerald-800">Excellent (20)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.mentorship_score" :value="15" class="h-4 w-4 text-blue-600 focus:ring-blue-500" />
                    <span class="text-sm font-medium text-blue-800">Very Good (15)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.mentorship_score" :value="12" class="h-4 w-4 text-amber-600 focus:ring-amber-500" />
                    <span class="text-sm font-medium text-amber-800">Good (12)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.mentorship_score" :value="0" class="h-4 w-4 text-red-600 focus:ring-red-500" />
                    <span class="text-sm font-medium text-red-800">Need Improvement (0)</span>
                  </label>
                </div>
              </div>

              <div class="mb-4 rounded-xl border border-slate-200 bg-gradient-to-r from-green-50 to-emerald-50 p-4">
                <div class="mb-3 flex items-center justify-between">
                  <h6 class="font-medium text-slate-900">Devotion</h6>
                  <span class="text-sm text-slate-600">Score: {{ form.devotion_score }}/20</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.devotion_score" :value="20" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500" />
                    <span class="text-sm font-medium text-emerald-800">Excellent (20)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.devotion_score" :value="15" class="h-4 w-4 text-blue-600 focus:ring-blue-500" />
                    <span class="text-sm font-medium text-blue-800">Very Good (15)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.devotion_score" :value="12" class="h-4 w-4 text-amber-600 focus:ring-amber-500" />
                    <span class="text-sm font-medium text-amber-800">Good (12)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.devotion_score" :value="0" class="h-4 w-4 text-red-600 focus:ring-red-500" />
                    <span class="text-sm font-medium text-red-800">Need Improvement (0)</span>
                  </label>
                </div>
              </div>

              <div class="mb-4 rounded-xl border border-slate-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4">
                <div class="mb-3 flex items-center justify-between">
                  <h6 class="font-medium text-slate-900">Cleanliness</h6>
                  <span class="text-sm text-slate-600">Score: {{ form.cleanliness_score }}/20</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.cleanliness_score" :value="20" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500" />
                    <span class="text-sm font-medium text-emerald-800">Excellent (20)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.cleanliness_score" :value="15" class="h-4 w-4 text-blue-600 focus:ring-blue-500" />
                    <span class="text-sm font-medium text-blue-800">Very Good (15)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.cleanliness_score" :value="12" class="h-4 w-4 text-amber-600 focus:ring-amber-500" />
                    <span class="text-sm font-medium text-amber-800">Good (12)</span>
                  </label>
                  <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                    <input type="radio" v-model="form.cleanliness_score" :value="0" class="h-4 w-4 text-red-600 focus:ring-red-500" />
                    <span class="text-sm font-medium text-red-800">Need Improvement (0)</span>
                  </label>
                </div>
              </div>

              <div class="rounded-xl border border-slate-200 bg-gradient-to-r from-cyan-50 to-sky-50 p-4">
                <div class="mb-3 flex items-center justify-between">
                  <h6 class="font-medium text-slate-900">Parent Contacted</h6>
                  <span class="text-sm text-slate-600">{{ form.parent_contacted ? 'Recorded' : 'Not recorded' }}</span>
                </div>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                  <input
                    type="checkbox"
                    v-model="form.parent_contacted"
                    class="h-4 w-4 rounded text-blue-600 focus:ring-blue-500"
                  />
                  <span class="text-sm font-medium text-slate-700">At least one parent contacted this week</span>
                </label>
                <p v-if="!form.parent_contacted" class="mt-3 text-sm text-amber-700">
                  No parent contact recorded for this week.
                </p>
              </div>
            </div>

            <div class="border-t border-slate-200/60 pt-6">
              <h4 class="mb-4 font-bold text-slate-900">Academic Performance (Termly/Yearly)</h4>
              <p class="mb-3 text-sm text-slate-600">
                Track academic performance across terms: BT1 (Beginning of Term), T1, T2, T3 assessments
              </p>
              <div class="grid gap-5 sm:grid-cols-4">
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">BT1 (Beginning of Term)</label>
                  <input v-model.number="form.bt1" type="number" @input="autoCalculateAcademic" class="input" placeholder="Beginning of Term" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">T1 (Term 1)</label>
                  <input v-model.number="form.t1" type="number" @input="autoCalculateAcademic" class="input" placeholder="Term 1" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">T2 (Term 2)</label>
                  <input v-model.number="form.t2" type="number" @input="autoCalculateAcademic" class="input" placeholder="Term 2" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-700">T3 (Term 3)</label>
                  <input v-model.number="form.t3" type="number" @input="autoCalculateAcademic" class="input" placeholder="Term 3" />
                </div>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200/60 bg-gradient-to-br from-blue-50 to-blue-100 p-5 shadow-sm">
              <h4 class="mb-4 font-bold text-slate-900">Calculated Results (Auto)</h4>

              <div class="mb-6">
                <h5 class="mb-3 font-semibold text-slate-800">Weekly Assessment</h5>
                <div class="grid gap-5 sm:grid-cols-3">
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Week</label>
                    <select v-model.number="form.week" class="input">
                      <option v-for="week in 13" :key="week" :value="week">Week {{ week }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Weekly Score</label>
                    <input v-model="form.weekly_score" readonly class="input bg-white/70" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Parent Contact Status</label>
                    <input :value="form.parent_contacted ? 'Contacted' : 'Not Contacted'" readonly class="input bg-white/70" />
                  </div>
                </div>
              </div>

              <div>
                <h5 class="mb-3 font-semibold text-slate-800">Academic Performance</h5>
                <div class="grid gap-5 sm:grid-cols-4">
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">C1 = T1 - BT1</label>
                    <input v-model="form.c1" readonly class="input bg-white/70" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">C2 = T2 - T1</label>
                    <input v-model="form.c2" readonly class="input bg-white/70" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">C3 = T3 - T2</label>
                    <input v-model="form.c3" readonly class="input bg-white/70" />
                  </div>
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Average %</label>
                    <input v-model="form.average_score" readonly class="input bg-white/70" />
                  </div>
                </div>
                <div class="mt-5">
                  <label class="mb-2 block text-sm font-semibold text-slate-700">Comment</label>
                  <input v-model="form.average_comment" readonly class="input bg-white/70" />
                </div>
              </div>
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
                class="rounded-2xl bg-gradient-to-r from-blue-700 to-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/30 transition-all duration-200 hover:scale-105 hover:shadow-xl hover:shadow-blue-500/40 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:scale-100"
              >
                {{ saving ? 'Saving...' : 'Save' }}
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
import { classTeacherPerformanceAPI, teachersAPI, classesAPI } from '../services/api';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

const createEmptyForm = () => ({
  teacher_id: '',
  class: '',
  stream: '',
  year: new Date().getFullYear(),
  term: 1,
  week: 1,
  parent_contacted: false,
  roll_call_score: 12,
  mentorship_score: 12,
  devotion_score: 12,
  cleanliness_score: 12,
  bt1: 0,
  t1: 0,
  t2: 0,
  t3: 0,
  c1: 0,
  c2: 0,
  c3: 0,
  weekly_score: 0,
  average_score: 0,
  average_comment: '',
  academic_score: 0
});

const records = ref([]);
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref(1);
const filterWeek = ref('');
const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i);
const showModal = ref(false);

const filteredTeachers = computed(() => {
  if (!teacherSearch.value) return teachersList.value;
  const search = teacherSearch.value.toLowerCase();
  return teachersList.value.filter(teacher =>
    teacher.full_name.toLowerCase().includes(search) ||
    teacher.teacher_code.toLowerCase().includes(search)
  );
});

// Group records by teacher to avoid repeating names
const groupedRecords = computed(() => {
  const grouped = {};
  records.value.forEach(record => {
    const key = `${record.teacher_id}-${record.class}-${record.stream}-${record.year}-${record.term}`;
    if (!grouped[key]) {
      grouped[key] = {
        teacher_id: record.teacher_id,
        teacher_name: record.teacher_name,
        class: record.class,
        stream: record.stream,
        year: record.year,
        term: record.term,
        weeks: []
      };
    }
    grouped[key].weeks.push(record);
  });
  return Object.values(grouped);
});
const editingId = ref(null);
const saving = ref(false);
const error = ref('');

const allClasses = ref([]);
const streams = ref([]);

const classes = computed(() => {
  const uniqueClassNames = [...new Set(allClasses.value.map(c => c.class_name))];
  return uniqueClassNames.sort();
});

const form = ref(createEmptyForm());

const autoCalculate = () => {
  const rollCall = parseFloat(form.value.roll_call_score) || 0;
  const mentorship = parseFloat(form.value.mentorship_score) || 0;
  const devotion = parseFloat(form.value.devotion_score) || 0;
  const cleanliness = parseFloat(form.value.cleanliness_score) || 0;
  const parentContacted = form.value.parent_contacted ? 10 : 0;
  form.value.weekly_score = rollCall + mentorship + devotion + cleanliness + parentContacted;
};

const autoCalculateAcademic = () => {
  const bt1 = parseFloat(form.value.bt1) || 0;
  const t1 = parseFloat(form.value.t1) || 0;
  const t2 = parseFloat(form.value.t2) || 0;
  const t3 = parseFloat(form.value.t3) || 0;

  // Only calculate C values if the prerequisite T values are present
  const c1 = t1 > 0 ? t1 - bt1 : 0;
  const c2 = (t1 > 0 && t2 > 0) ? t2 - t1 : 0;
  const c3 = (t2 > 0 && t3 > 0) ? t3 - t2 : 0;

  // Calculate academic score with bonuses/penalties
  let academicScore = 0;
  if (c1 > 0) academicScore += 3;
  if (c2 > 0) academicScore += 3;
  if (c3 > 0) academicScore += 3;
  if (c1 < 0) academicScore -= 5;
  if (c2 < 0) academicScore -= 5;
  if (c3 < 0) academicScore -= 5;

  // Calculate average based on available values only
  let cValues = [];
  if (t1 > 0) cValues.push(c1);
  if (t2 > 0) cValues.push(c2);
  if (t3 > 0) cValues.push(c3);

  const average = cValues.length > 0 ? cValues.reduce((a, b) => a + b, 0) / cValues.length : 0;

  form.value.c1 = t1 > 0 ? c1.toFixed(2) : '0';
  form.value.c2 = (t1 > 0 && t2 > 0) ? c2.toFixed(2) : '0';
  form.value.c3 = (t2 > 0 && t3 > 0) ? c3.toFixed(2) : '0';
  form.value.average_score = Number.isFinite(average) ? average.toFixed(2) : '0.00';
  form.value.average_comment = average >= 0 ? 'Good progress' : 'Needs improvement';
  form.value.academic_score = academicScore;
};

watch(
  () => [
    form.value.roll_call_score,
    form.value.mentorship_score,
    form.value.devotion_score,
    form.value.cleanliness_score
  ],
  autoCalculate,
  { deep: true }
);

watch(
  () => [form.value.bt1, form.value.t1, form.value.t2, form.value.t3],
  () => {
    // Only calculate academic score and average, don't recalculate C values
    // C values are only calculated by individual autoCalculateC functions
    const c1 = parseFloat(form.value.c1) || 0;
    const c2 = parseFloat(form.value.c2) || 0;
    const c3 = parseFloat(form.value.c3) || 0;
    const t1 = parseFloat(form.value.t1) || 0;
    const t2 = parseFloat(form.value.t2) || 0;
    const t3 = parseFloat(form.value.t3) || 0;

    let academicScore = 0;
    if (c1 > 0) academicScore += 3;
    if (c2 > 0) academicScore += 3;
    if (c3 > 0) academicScore += 3;
    if (c1 < 0) academicScore -= 5;
    if (c2 < 0) academicScore -= 5;
    if (c3 < 0) academicScore -= 5;

    let cValues = [];
    if (t1 > 0) cValues.push(c1);
    if (t2 > 0) cValues.push(c2);
    if (t3 > 0) cValues.push(c3);

    const average = cValues.length > 0 ? cValues.reduce((a, b) => a + b, 0) / cValues.length : 0;

    form.value.average_score = Number.isFinite(average) ? average.toFixed(2) : '0.00';
    form.value.average_comment = average >= 0 ? 'Good progress' : 'Needs improvement';
    form.value.academic_score = academicScore;
  },
  { deep: true }
);

const loadData = async () => {
  try {
    const params = {
      year: filterYear.value,
      term: filterTerm.value
    };
    if (filterWeek.value) {
      params.week = filterWeek.value;
    }

    const result = await classTeacherPerformanceAPI.getAll(params);
    if (result.success) records.value = result.data.data || result.data;
  } catch (err) {
    console.error('Failed to load data:', err);
  }
};

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    allClasses.value = response.data || [];
    streams.value = [...new Set(allClasses.value.map(c => c.stream_name))];
  } catch (err) {
    console.error('Error loading classes:', err);
  }
};

const loadTeachers = async () => {
  try {
    const result = await teachersAPI.getAll();
    if (result.success) teachersList.value = result.data.teachers || result.data;
  } catch (err) {
    console.error('Failed to load teachers:', err);
  }
};

const getScoreClass = (score) => {
  const value = Number(score) || 0;
  if (value >= 20) return 'bg-emerald-100 text-emerald-800';
  if (value >= 15) return 'bg-blue-100 text-blue-800';
  if (value >= 12) return 'bg-amber-100 text-amber-800';
  return 'bg-red-100 text-red-800';
};

const getScoreLabel = (score) => {
  const value = Number(score) || 0;
  if (value >= 20) return 'Excellent';
  if (value >= 15) return 'Very Good';
  if (value >= 12) return 'Good';
  return 'Need Improvement';
};

const getParentContactedClass = (contacted) => {
  return contacted ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800';
};

const getWeeklyScoreClass = (score) => {
  const value = Number(score) || 0;
  if (value >= 70) return 'bg-emerald-100 text-emerald-800';
  if (value >= 50) return 'bg-blue-100 text-blue-800';
  if (value >= 30) return 'bg-amber-100 text-amber-800';
  return 'bg-red-100 text-red-800';
};

const resetForm = () => {
  form.value = createEmptyForm();
  autoCalculate();
  autoCalculateAcademic();
};

const openAddModal = () => {
  editingId.value = null;
  resetForm();
  showModal.value = true;
};

const editRecord = (record) => {
  editingId.value = record.id;
  form.value = {
    ...createEmptyForm(),
    ...record,
    teacher_id: record.teacher_id ?? '',
    class: record.class ?? '',
    stream: record.stream ?? '',
    year: Number(record.year) || new Date().getFullYear(),
    term: Number(record.term) || 1,
    week: Number(record.week) || 1,
    parent_contacted: !!record.parent_contacted,
    roll_call_score: Number(record.roll_call_score) || 0,
    mentorship_score: Number(record.mentorship_score) || 0,
    devotion_score: Number(record.devotion_score) || 0,
    cleanliness_score: Number(record.cleanliness_score) || 0,
    bt1: Number(record.bt1) || 0,
    t1: Number(record.t1) || 0,
    t2: Number(record.t2) || 0,
    t3: Number(record.t3) || 0,
    c1: record.c1 ?? 0,
    c2: record.c2 ?? 0,
    c3: record.c3 ?? 0,
    weekly_score: Number(record.weekly_score) || 0,
    average_score: record.average_score ?? 0,
    average_comment: record.average_comment ?? '',
    academic_score: Number(record.academic_score) || 0
  };
  showModal.value = true;
};

const editTermScores = (record) => {
  editingId.value = record.id;
  form.value = {
    ...createEmptyForm(),
    ...record,
    teacher_id: record.teacher_id ?? '',
    class: record.class ?? '',
    stream: record.stream ?? '',
    year: Number(record.year) || new Date().getFullYear(),
    term: Number(record.term) || 1,
    week: Number(record.week) || 1,
    parent_contacted: !!record.parent_contacted,
    roll_call_score: Number(record.roll_call_score) || 0,
    mentorship_score: Number(record.mentorship_score) || 0,
    devotion_score: Number(record.devotion_score) || 0,
    cleanliness_score: Number(record.cleanliness_score) || 0,
    bt1: Number(record.bt1) || 0,
    t1: Number(record.t1) || 0,
    t2: Number(record.t2) || 0,
    t3: Number(record.t3) || 0,
    c1: record.c1 ?? 0,
    c2: record.c2 ?? 0,
    c3: record.c3 ?? 0,
    weekly_score: Number(record.weekly_score) || 0,
    average_score: record.average_score ?? 0,
    average_comment: record.average_comment ?? '',
    academic_score: Number(record.academic_score) || 0
  };
  autoCalculateAcademic();
  showModal.value = true;
};

const editTerm1 = (record) => {
  editingId.value = record.id;
  form.value = {
    ...createEmptyForm(),
    ...record,
    teacher_id: record.teacher_id ?? '',
    class: record.class ?? '',
    stream: record.stream ?? '',
    year: Number(record.year) || new Date().getFullYear(),
    term: Number(record.term) || 1,
    week: Number(record.week) || 1,
    parent_contacted: !!record.parent_contacted,
    roll_call_score: Number(record.roll_call_score) || 0,
    mentorship_score: Number(record.mentorship_score) || 0,
    devotion_score: Number(record.devotion_score) || 0,
    cleanliness_score: Number(record.cleanliness_score) || 0,
    bt1: Number(record.bt1) || 0,
    t1: Number(record.t1) || 0,
    t2: Number(record.t2) || 0,
    t3: Number(record.t3) || 0,
    c1: record.c1 ?? 0,
    c2: record.c2 ?? 0,
    c3: record.c3 ?? 0,
    weekly_score: Number(record.weekly_score) || 0,
    average_score: record.average_score ?? 0,
    average_comment: record.average_comment ?? '',
    academic_score: Number(record.academic_score) || 0
  };
  autoCalculateC1();
  showModal.value = true;
};

const editTerm2 = (record) => {
  editingId.value = record.id;
  form.value = {
    ...createEmptyForm(),
    ...record,
    teacher_id: record.teacher_id ?? '',
    class: record.class ?? '',
    stream: record.stream ?? '',
    year: Number(record.year) || new Date().getFullYear(),
    term: Number(record.term) || 1,
    week: Number(record.week) || 1,
    parent_contacted: !!record.parent_contacted,
    roll_call_score: Number(record.roll_call_score) || 0,
    mentorship_score: Number(record.mentorship_score) || 0,
    devotion_score: Number(record.devotion_score) || 0,
    cleanliness_score: Number(record.cleanliness_score) || 0,
    bt1: Number(record.bt1) || 0,
    t1: Number(record.t1) || 0,
    t2: Number(record.t2) || 0,
    t3: Number(record.t3) || 0,
    c1: record.c1 ?? 0,
    c2: record.c2 ?? 0,
    c3: record.c3 ?? 0,
    weekly_score: Number(record.weekly_score) || 0,
    average_score: record.average_score ?? 0,
    average_comment: record.average_comment ?? '',
    academic_score: Number(record.academic_score) || 0
  };
  autoCalculateC2();
  showModal.value = true;
};

const editTerm3 = (record) => {
  editingId.value = record.id;
  form.value = {
    ...createEmptyForm(),
    ...record,
    teacher_id: record.teacher_id ?? '',
    class: record.class ?? '',
    stream: record.stream ?? '',
    year: Number(record.year) || new Date().getFullYear(),
    term: Number(record.term) || 1,
    week: Number(record.week) || 1,
    parent_contacted: !!record.parent_contacted,
    roll_call_score: Number(record.roll_call_score) || 0,
    mentorship_score: Number(record.mentorship_score) || 0,
    devotion_score: Number(record.devotion_score) || 0,
    cleanliness_score: Number(record.cleanliness_score) || 0,
    bt1: Number(record.bt1) || 0,
    t1: Number(record.t1) || 0,
    t2: Number(record.t2) || 0,
    t3: Number(record.t3) || 0,
    c1: record.c1 ?? 0,
    c2: record.c2 ?? 0,
    c3: record.c3 ?? 0,
    weekly_score: Number(record.weekly_score) || 0,
    average_score: record.average_score ?? 0,
    average_comment: record.average_comment ?? '',
    academic_score: Number(record.academic_score) || 0
  };
  autoCalculateC3();
  showModal.value = true;
};

const autoCalculateC1 = () => {
  const bt1 = parseFloat(form.value.bt1) || 0;
  const t1 = parseFloat(form.value.t1) || 0;
  if (t1 > 0) {
    const c1 = t1 - bt1;
    form.value.c1 = c1.toFixed(2);
  } else {
    form.value.c1 = '0';
  }
  // Don't call autoCalculateAcademic here to avoid overriding the value
};

const autoCalculateC2 = () => {
  const t1 = parseFloat(form.value.t1) || 0;
  const t2 = parseFloat(form.value.t2) || 0;
  if (t1 > 0 && t2 > 0) {
    const c2 = t2 - t1;
    form.value.c2 = c2.toFixed(2);
  } else {
    form.value.c2 = '0';
  }
  // Don't call autoCalculateAcademic here to avoid overriding the value
};

const autoCalculateC3 = () => {
  const t2 = parseFloat(form.value.t2) || 0;
  const t3 = parseFloat(form.value.t3) || 0;
  if (t2 > 0 && t3 > 0) {
    const c3 = t3 - t2;
    form.value.c3 = c3.toFixed(2);
  } else {
    form.value.c3 = '0';
  }
  // Don't call autoCalculateAcademic here to avoid overriding the value
};

const addNewWeek = (group) => {
  editingId.value = null;
  form.value = {
    ...createEmptyForm(),
    teacher_id: group.teacher_id,
    class: group.class,
    stream: group.stream,
    year: group.year,
    term: group.term,
    week: group.weeks.length + 1
  };
  showModal.value = true;
};

const saveRecord = async () => {
  saving.value = true;
  error.value = '';

  autoCalculate();
  // Don't call autoCalculateAcademic here - C values are set by individual autoCalculateC functions
  // Only calculate academic score and average from current C values
  const c1 = parseFloat(form.value.c1) || 0;
  const c2 = parseFloat(form.value.c2) || 0;
  const c3 = parseFloat(form.value.c3) || 0;
  const t1 = parseFloat(form.value.t1) || 0;
  const t2 = parseFloat(form.value.t2) || 0;
  const t3 = parseFloat(form.value.t3) || 0;

  let academicScore = 0;
  if (c1 > 0) academicScore += 3;
  if (c2 > 0) academicScore += 3;
  if (c3 > 0) academicScore += 3;
  if (c1 < 0) academicScore -= 5;
  if (c2 < 0) academicScore -= 5;
  if (c3 < 0) academicScore -= 5;

  let cValues = [];
  if (t1 > 0) cValues.push(c1);
  if (t2 > 0) cValues.push(c2);
  if (t3 > 0) cValues.push(c3);

  const average = cValues.length > 0 ? cValues.reduce((a, b) => a + b, 0) / cValues.length : 0;

  form.value.average_score = Number.isFinite(average) ? average.toFixed(2) : '0.00';
  form.value.average_comment = average >= 0 ? 'Good progress' : 'Needs improvement';
  form.value.academic_score = academicScore;

  try {
    let result;
    if (editingId.value) {
      result = await classTeacherPerformanceAPI.update(editingId.value, form.value);
    } else {
      result = await classTeacherPerformanceAPI.create(form.value);
    }

    if (result.success) {
      showModal.value = false;
      await loadData();
    } else {
      error.value = result.message || result.data?.message || JSON.stringify(result);
      alert('Failed to save: ' + error.value);
    }
  } catch (err) {
    console.error('Save error:', err);
    error.value = err.response?.data?.message || err.message || 'An error occurred while saving';
    alert('Error: ' + error.value);
  } finally {
    saving.value = false;
  }
};

const deleteRecord = async (id) => {
  if (!confirm('Delete?')) return;
  try {
    const result = await classTeacherPerformanceAPI.delete(id);
    if (result.success) {
      await loadData();
    } else {
      alert('Failed to delete: ' + (result.message || 'Unknown error'));
    }
  } catch (err) {
    console.error('Delete error:', err);
    alert('Error: ' + (err.response?.data?.message || err.message || 'An error occurred while deleting'));
  }
};

const downloadPDF = () => {
  const doc = new jsPDF();

  doc.setFontSize(18);
  doc.text('Class Teacher Performance Report', 14, 22);

  doc.setFontSize(11);
  doc.setTextColor(100);
  doc.text(`Year: ${filterYear.value} | Term: ${filterTerm.value}`, 14, 30);
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);

  const tableData = records.value.map(record => [
    record.teacher_name,
    record.class,
    record.stream,
    record.parent_contacted ? 'Yes' : 'No',
    record.bt1,
    record.t1,
    record.t2,
    record.t3,
    record.c1,
    record.c2,
    record.c3,
    `${record.average_score}%`,
    record.average_comment
  ]);

  autoTable(doc, {
    head: [['Teacher', 'Class', 'Stream', 'Parents Contacted', 'BT1', 'T1', 'T2', 'T3', 'C1', 'C2', 'C3', 'Average', 'Comment']],
    body: tableData,
    startY: 45,
    styles: {
      fontSize: 7,
      cellPadding: 2
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

  doc.save(`class-teacher-performance-${filterYear.value}-term-${filterTerm.value}.pdf`);
};

onMounted(() => {
  loadClasses();
  loadData();
  loadTeachers();
  autoCalculate();
  autoCalculateAcademic();
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
