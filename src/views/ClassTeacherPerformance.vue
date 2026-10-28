<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Class Teacher Performance</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero-primary" @click="openAddModal"><Plus class="h-4 w-4" />Add record</button>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <ListToolbar
      v-model:search="searchQuery"
      placeholder="Search teacher or class"
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
        <button type="button" class="tool-btn" :disabled="records.length === 0" title="Download PDF" @click="downloadPDF"><FileText class="h-4 w-4" /><span class="hidden lg:inline">PDF</span></button>
      </template>
    </ListToolbar>

    <section v-if="loading && records.length === 0" class="card space-y-3 p-5">
      <div v-for="i in 4" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
    </section>
    <section v-else-if="visibleGroups.length === 0" class="card px-5 py-14 text-center">
      <ClipboardList class="mx-auto h-9 w-9 text-gray-300" />
      <p class="mt-2 text-sm font-medium text-gray-700">No performance records found</p>
    </section>

    <template v-else>
    <section v-for="group in visibleGroups" :key="`${group.teacher_id}-${group.class}-${group.stream}-${group.year}-${group.term}`" class="card overflow-hidden">
      <header class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(group.teacher_name) }}</span>
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold text-gray-900">{{ group.teacher_name }}</p>
          <p class="text-xs text-gray-500">{{ group.class }} {{ group.stream }} · Term {{ group.term }} · {{ group.weeks.length }} week{{ group.weeks.length === 1 ? '' : 's' }} recorded</p>
        </div>
        <div class="hidden text-right sm:block">
          <p class="tabular-nums text-sm font-semibold text-gray-900">{{ groupAverage(group) }}/90</p>
          <p class="text-[11px] text-gray-500">average week</p>
        </div>
        <RowActionsMenu :label="`Actions for ${group.teacher_name}`" :items="[{ label: 'Add next week', icon: PlusCircle, onClick: () => addNewWeek(group) }]" />
      </header>

      <table class="hidden w-full text-left text-sm md:table">
        <thead class="bg-gray-50/70">
          <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
            <th class="w-20 px-5 py-2.5">Week</th>
            <th class="px-4 py-2.5">Duties</th>
            <th class="px-4 py-2.5">Parent</th>
            <th class="px-4 py-2.5">Weekly</th>
            <th class="hidden px-4 py-2.5 xl:table-cell">Tests</th>
            <th class="hidden px-4 py-2.5 lg:table-cell">Average</th>
            <th class="w-12 px-3 py-2.5"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="record in group.weeks" :key="record.id" class="transition hover:bg-brand-50/40">
            <td class="whitespace-nowrap px-5 py-2.5 font-medium text-gray-800">Week {{ record.week }}</td>
            <td class="px-4 py-2.5">
              <div class="flex flex-wrap gap-1">
                <span v-for="d in DUTIES" :key="d.key" class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold" :class="getScoreClass(record[d.key])" :title="`${d.label}: ${getScoreLabel(record[d.key])}`">{{ d.short }} {{ Number(record[d.key]) || 0 }}</span>
              </div>
            </td>
            <td class="px-4 py-2.5">
              <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="record.parent_contacted ? 'text-emerald-700' : 'text-gray-500'"><span class="h-1.5 w-1.5 rounded-full" :class="record.parent_contacted ? 'bg-emerald-500' : 'bg-gray-300'"></span>{{ record.parent_contacted ? 'Contacted' : 'No' }}</span>
            </td>
            <td class="px-4 py-2.5">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold tabular-nums" :class="getWeeklyScoreClass(record.weekly_score)">{{ record.weekly_score }}/90</span>
            </td>
            <td class="hidden px-4 py-2.5 xl:table-cell">
              <div class="flex flex-wrap gap-1">
                <span v-for="t in TESTS" :key="t" class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ t.toUpperCase() }} <strong class="text-gray-800">{{ num(record[t]) }}</strong></span>
              </div>
            </td>
            <td class="hidden px-4 py-2.5 tabular-nums font-semibold text-gray-900 lg:table-cell">{{ num(record.average_score) }}{{ Number(record.average_score) ? "%" : "" }}</td>
            <td class="px-3 py-2.5 text-right"><RowActionsMenu :label="`Actions for week ${record.week}`" :items="weekActions(record)" /></td>
          </tr>
        </tbody>
      </table>
      <ul class="divide-y divide-gray-100 md:hidden">
        <li v-for="record in group.weeks" :key="record.id" class="flex items-start gap-3 px-4 py-3">
          <div class="min-w-0 flex-1">
            <p class="font-medium text-gray-900">Week {{ record.week }} <span class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="getWeeklyScoreClass(record.weekly_score)">{{ record.weekly_score }}/90</span></p>
            <div class="mt-1 flex flex-wrap gap-1">
              <span v-for="d in DUTIES" :key="d.key" class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold" :class="getScoreClass(record[d.key])">{{ d.short }} {{ Number(record[d.key]) || 0 }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Parent {{ record.parent_contacted ? 'contacted' : 'not contacted' }} · average {{ num(record.average_score) }}{{ Number(record.average_score) ? "%" : "" }}</p>
          </div>
          <RowActionsMenu :label="`Actions for week ${record.week}`" :items="weekActions(record)" />
        </li>
      </ul>
    </section>

    </template>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="relative flex max-h-[90vh] w-full max-w-5xl flex-col rounded-2xl bg-white shadow-2xl ring-1 ring-white/20">
        <div class="absolute inset-0 rounded-2xl bg-brand-500/5 pointer-events-none"></div>

        <div class="relative flex-shrink-0 border-b border-slate-200/60 bg-slate-50 px-6 py-6">
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
                  <svg class="w-5 h-5 text-slate-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

              <div class="mb-4 rounded-xl border border-slate-200 bg-brand-50 p-4">
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
                    <input type="radio" v-model="form.roll_call_score" :value="15" class="h-4 w-4 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm font-medium text-brand-800">Very Good (15)</span>
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

              <div class="mb-4 rounded-xl border border-slate-200 bg-brand-50 p-4">
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
                    <input type="radio" v-model="form.mentorship_score" :value="15" class="h-4 w-4 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm font-medium text-brand-800">Very Good (15)</span>
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

              <div class="mb-4 rounded-xl border border-slate-200 bg-green-50 p-4">
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
                    <input type="radio" v-model="form.devotion_score" :value="15" class="h-4 w-4 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm font-medium text-brand-800">Very Good (15)</span>
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

              <div class="mb-4 rounded-xl border border-slate-200 bg-amber-50 p-4">
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
                    <input type="radio" v-model="form.cleanliness_score" :value="15" class="h-4 w-4 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm font-medium text-brand-800">Very Good (15)</span>
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

              <div class="rounded-xl border border-slate-200 bg-brand-50 p-4">
                <div class="mb-3 flex items-center justify-between">
                  <h6 class="font-medium text-slate-900">Parent Contacted</h6>
                  <span class="text-sm text-slate-600">{{ form.parent_contacted ? 'Recorded' : 'Not recorded' }}</span>
                </div>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition-colors hover:bg-white/50">
                  <input
                    type="checkbox"
                    v-model="form.parent_contacted"
                    class="h-4 w-4 rounded text-brand-600 focus:ring-brand-500"
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

            <div class="rounded-2xl border border-slate-200/60 bg-brand-50 p-5 shadow-sm">
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
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { classTeacherPerformanceAPI, teachersAPI, classesAPI } from '../services/api';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import { Plus, RefreshCw, FileText, ClipboardList, Pencil, Trash2, Users as UsersIcon, Gauge, Phone, PlusCircle } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';

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
const loading = ref(false);
const searchQuery = ref('');
const teachersList = ref([]);
const teacherSearch = ref('');
const filterYear = ref(new Date().getFullYear());
const filterTerm = ref(1);
const filterWeek = ref('');
useGlobalTerm((y, t) => { filterYear.value = y; filterTerm.value = t; }, () => loadData());
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
const DUTIES = [
  { key: 'roll_call_score', short: 'RC', label: 'Roll call' },
  { key: 'mentorship_score', short: 'M', label: 'Mentorship' },
  { key: 'devotion_score', short: 'D', label: 'Devotion' },
  { key: 'cleanliness_score', short: 'C', label: 'Cleanliness' },
];
const num = (v) => (Number(v) ? Number(Number(v).toFixed(1)) : '-');
const TESTS = ['bt1', 't1', 'c1', 't2', 'c2', 't3', 'c3'];
const initials = (name) => String(name || '?').split(/s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const visibleGroups = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return groupedRecords.value;
  return groupedRecords.value.filter((g) => `${g.teacher_name} ${g.class} ${g.stream}`.toLowerCase().includes(q));
});
const groupAverage = (group) => {
  const n = group.weeks.length;
  return n ? Math.round(group.weeks.reduce((sum, r) => sum + (Number(r.weekly_score) || 0), 0) / n) : 0;
};
const weekActions = (record) => [
  { label: 'Edit week', icon: Pencil, onClick: () => editRecord(record) },
  { label: 'Add T1 score', icon: PlusCircle, onClick: () => editTerm1(record) },
  { label: 'Add T2 score', icon: PlusCircle, onClick: () => editTerm2(record) },
  { label: 'Add T3 score', icon: PlusCircle, onClick: () => editTerm3(record) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteRecord(record.id) },
];
const summaryCards = computed(() => {
  const list = records.value;
  const avg = list.length ? Math.round(list.reduce((sum, r) => sum + (Number(r.weekly_score) || 0), 0) / list.length) : null;
  const contacted = list.filter((r) => Number(r.parent_contacted)).length;
  return [
    { label: 'Weekly records', value: list.length.toLocaleString(), icon: ClipboardList },
    { label: 'Class teachers', value: groupedRecords.value.length.toLocaleString(), icon: UsersIcon, tone: 'sky' },
    { label: 'Average week', value: avg === null ? '-' : avg + '/90', icon: Gauge, tone: 'gray' },
    { label: 'Parents contacted', value: list.length ? Math.round((contacted / list.length) * 100) + '%' : '-', note: contacted + ' of ' + list.length + ' weeks', icon: Phone, tone: 'amber' },
  ];
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
  loading.value = true;
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
  } finally {
    loading.value = false;
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
  if (value >= 15) return 'bg-brand-100 text-brand-800';
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
  if (value >= 50) return 'bg-brand-100 text-brand-800';
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
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.filter-select { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
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
/* dark mode: generated by scripts/build-dark-overrides.cjs */
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
html.dark .input {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .input:hover:not(:focus) {
  border-color: var(--d-line);
}
</style>
