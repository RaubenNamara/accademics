<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Lesson Monitoring</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <DownloadMenu :scopes="periodScopes" scope-label="Period" :default-scope="periodDefault" :busy="downloading" @download="downloadTimeLost" />
          <button type="button" class="btn-hero" @click="showReports = !showReports"><BarChart3 class="h-4 w-4" />Reports</button>
          <button type="button" class="btn-hero-primary" @click="openAddModal"><Plus class="h-4 w-4" />Add record</button>
        </div>
      </div>
    </div>

    <StatCards :cards="summaryCards" />

    <ListToolbar
      v-model:search="groupSearch"
      placeholder="Search teacher, subject or class"
      :filter-count="[filterWeek, filterTeacher].filter(Boolean).length"
      @clear="filterWeek = ''; filterTeacher = ''; loadData()"
    >
      <template #filters>
        <label class="block">
          <span class="tb-label">Year</span>
          <select v-model="filterYear" class="filter-select" @change="loadData">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
        <label class="block">
          <span class="tb-label">Term</span>
          <select v-model="filterTerm" class="filter-select" @change="loadData">
            <option value="">All terms</option>
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>
        </label>
        <label class="block">
          <span class="tb-label">Week</span>
          <select v-model="filterWeek" class="filter-select" @change="loadData">
            <option value="">All weeks</option>
            <option v-for="w in 15" :key="w" :value="w">Week {{ w }}</option>
          </select>
        </label>
        <label class="block">
          <span class="tb-label">Teacher</span>
          <select v-model="filterTeacher" class="filter-select" @change="loadData">
            <option value="">All teachers</option>
            <option v-for="t in teachersList" :key="t.id" :value="t.id">{{ t.full_name }}</option>
          </select>
        </label>
      </template>
      <template #actions>
        <button type="button" class="tool-btn" title="Refresh" @click="loadData"><RefreshCw class="h-4 w-4" /><span class="hidden lg:inline">Refresh</span></button>
      </template>
    </ListToolbar>

    <!-- Reports Modal -->
    <Teleport to="body">
      <div
        v-if="showReports"
        class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-900/60 backdrop-blur-sm sm:items-center sm:p-4"
        @click.self="showReports = false"
      >
        <div
          class="flex h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:h-auto sm:max-h-[92vh] sm:rounded-2xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="reports-title"
        >
          <!-- Header -->
          <header class="flex items-start gap-3 px-5 pb-3 pt-5 sm:px-6">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100"><BarChart3 class="h-5 w-5" /></span>
            <div class="min-w-0 flex-1">
              <h2 id="reports-title" class="text-lg font-bold text-slate-900">Lesson Monitoring Reports</h2>
              <div class="mt-1 flex flex-wrap gap-1.5">
                <span v-for="chip in reportScopeChips" :key="chip" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-600">{{ chip }}</span>
              </div>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close reports" @click="showReports = false"><X class="h-5 w-5" /></button>
          </header>

          <!-- Which report: one segmented control -->
          <div class="px-5 pb-4 sm:px-6">
            <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 lg:grid-cols-4" role="tablist" aria-label="Report">
              <button
                v-for="(cfg, key) in reportConfig"
                :key="key"
                type="button"
                role="tab"
                :aria-selected="reportType === key"
                class="flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                :class="reportType === key ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-800'"
                @click="reportType = key"
              >
                <component :is="cfg.tone === 'rose' ? TrendingDown : TrendingUp" class="h-4 w-4" :class="cfg.tone === 'rose' ? 'text-rose-500' : 'text-emerald-500'" />
                <span class="truncate">{{ cfg.tab }} <span class="font-normal text-slate-400">· {{ cfg.tabSub.toLowerCase() }}</span></span>
              </button>
            </div>
          </div>

          <!-- Body -->
          <div class="min-h-0 flex-1 overflow-y-auto border-t border-slate-100 bg-slate-50/60 px-5 py-5 sm:px-6">
            <div class="mb-4">
              <h3 class="text-base font-bold text-slate-900">{{ activeReport.title }}</h3>
              <p class="text-xs text-slate-500">{{ activeReport.description }}</p>
            </div>

            <template v-if="reportRows.length">
              <!-- Summary -->
              <div class="mb-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <div v-for="k in reportKpis" :key="k.label" class="rounded-xl bg-white px-3.5 py-2.5 ring-1 ring-slate-200">
                  <p class="text-[11px] font-medium text-slate-500">{{ k.label }}</p>
                  <p class="text-lg font-bold tabular-nums" :class="k.accent ? tone.text : 'text-slate-900'">{{ k.value }}</p>
                </div>
              </div>

              <!-- Top three -->
              <div class="mb-3 grid gap-2.5 sm:grid-cols-3">
                <div v-for="row in reportRows.slice(0, 3)" :key="row.key" class="relative overflow-hidden rounded-xl bg-white p-3.5 ring-1 ring-slate-200">
                  <span class="absolute inset-x-0 top-0 h-1" :class="tone.bar"></span>
                  <div class="flex items-start gap-2.5">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" :class="tone.solid">{{ row.rank }}</span>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-semibold text-slate-900">{{ row.name }}</p>
                      <p class="truncate text-[11px] text-slate-500">{{ row.sub }} · {{ row.lessons }} lessons</p>
                    </div>
                  </div>
                  <div class="mt-3 flex items-end justify-between gap-2">
                    <p class="text-xl font-bold tabular-nums" :class="tone.text">{{ formatMinutes(row.minutes) }}</p>
                    <p class="text-[11px] text-slate-500">{{ activeReport.metricLabel }}: <strong class="text-slate-800">{{ row.metric }}</strong></p>
                  </div>
                  <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :class="tone.bar" :style="{ width: `${reportBarPct(row)}%` }"></div></div>
                  <p v-if="row.notes" class="mt-2 line-clamp-2 text-[11px] leading-snug text-slate-500">{{ row.notes }}</p>
                </div>
              </div>

              <!-- The rest -->
              <ol v-if="reportRows.length > 3" class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                <li v-for="row in reportRows.slice(3)" :key="row.key" class="grid grid-cols-[1.75rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 px-3.5 py-2.5 sm:grid-cols-[1.75rem_minmax(0,1fr)_11rem_4rem_5rem]">
                  <span class="text-xs font-semibold tabular-nums text-slate-400">{{ row.rank }}</span>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-900">{{ row.name }}</p>
                    <p class="truncate text-[11px] text-slate-500">{{ row.sub }}<span v-if="row.notes"> · {{ row.notes }}</span></p>
                  </div>
                  <div class="text-right sm:text-left">
                    <p class="text-sm font-semibold tabular-nums" :class="tone.text">{{ formatMinutes(row.minutes) }}</p>
                    <div class="mt-1 hidden h-1 overflow-hidden rounded-full bg-slate-100 sm:block"><div class="h-full rounded-full" :class="tone.barSoft" :style="{ width: `${reportBarPct(row)}%` }"></div></div>
                  </div>
                  <p class="hidden text-right text-xs tabular-nums text-slate-600 sm:block">{{ row.lessons }}<span class="block text-[10px] text-slate-400">lessons</span></p>
                  <p class="hidden text-right text-xs tabular-nums text-slate-600 sm:block">{{ row.metric }}<span class="block text-[10px] text-slate-400">{{ activeReport.metricShort }}</span></p>
                </li>
              </ol>
            </template>

            <div v-else class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center">
              <BarChart3 class="mx-auto h-8 w-8 text-slate-300" />
              <p class="mt-2 text-sm font-medium text-slate-700">No data for this report</p>
              <p class="mt-0.5 text-xs text-slate-500">{{ activeReport.emptyHint }}</p>
            </div>
          </div>

          <!-- Footer -->
          <footer class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3 sm:px-6">
            <button type="button" class="btn-secondary" @click="showReports = false">Close</button>
            <button type="button" class="btn-primary" :disabled="reportRows.length === 0 || pdfBusy" @click="printReport">
              <Download class="h-4 w-4" />{{ pdfBusy ? 'Preparing…' : 'Download PDF' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

    <!-- Top Classes by Minutes Lost -->
    <div class="card overflow-hidden">
      <button
        type="button"
        @click="isAnalyticsCollapsed = !isAnalyticsCollapsed"
        class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left transition hover:bg-slate-50"
        :aria-expanded="!isAnalyticsCollapsed"
      >
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
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
        <ol v-if="topClassesByMinutesLost.length > 0" class="gap-x-10 px-5 py-2 lg:columns-2">
          <li
            v-for="(item, index) in visibleTopClasses"
            :key="`${item.class}_${item.stream}`"
            class="grid break-inside-avoid grid-cols-[1.25rem_minmax(0,1fr)_auto] items-center gap-x-3 py-2.5"
            :title="`${item.total_lessons} lessons · ${item.teachers.length} teachers · about ${Math.floor(item.total_minutes_lost / 40)} single lessons lost`"
          >
            <span class="text-xs font-bold tabular-nums" :class="index < 3 ? 'text-rose-600' : 'text-slate-400'">{{ index + 1 }}</span>

            <div class="min-w-0">
              <div class="flex items-baseline justify-between gap-3">
                <span class="truncate text-sm font-semibold text-slate-900">
                  {{ item.class }}<span v-if="item.stream" class="font-medium text-slate-500">&nbsp;{{ item.stream }}</span>
                  <span class="ml-2 hidden text-[11px] font-normal text-slate-400 sm:inline">{{ item.total_lessons }} lessons · {{ item.teachers.length }} teachers</span>
                </span>
              </div>
              <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-100">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="index < 3 ? 'bg-gradient-to-r from-rose-400 to-rose-600' : 'bg-gradient-to-r from-rose-200 to-rose-300'"
                  :style="{ width: `${topClassesMaxLost ? (item.total_minutes_lost / topClassesMaxLost) * 100 : 0}%` }"
                ></div>
              </div>
            </div>

            <div class="w-20 text-right">
              <div class="text-sm font-bold tabular-nums text-slate-900">{{ formatMinutes(item.total_minutes_lost) }}</div>
              <div class="text-[10px] text-slate-400">≈ {{ Math.floor(item.total_minutes_lost / 40) }} lessons</div>
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
            class="text-sm font-semibold text-brand-700 hover:text-brand-800"
          >
            {{ showAllTopClasses ? 'Show top 5 only' : `Show all ${topClassesByMinutesLost.length}` }}
          </button>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <div
        v-for="group in pagedGroups"
        :key="group.group_key"
        class="card overflow-hidden"
      >
        <!-- Teacher header -->
        <header class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-2.5 sm:px-5" :class="isTeacherOpen(group) ? 'border-b border-gray-100' : ''">
          <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" :aria-expanded="isTeacherOpen(group)" @click="toggleTeacher(group)">
            <ChevronDown class="h-4 w-4 flex-shrink-0 text-gray-400 transition" :class="isTeacherOpen(group) ? 'rotate-180' : ''" />
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(group.teacher_name) }}</span>
            <div class="min-w-0">
              <p class="truncate font-semibold text-gray-900">{{ group.teacher_name || '-' }}</p>
              <p class="text-xs text-gray-500">{{ group.subjects_classes.length }} {{ group.subjects_classes.length === 1 ? 'class' : 'classes' }} · {{ group.total_lessons }} lessons</p>
            </div>
          </button>
          <div class="text-right">
            <p class="tabular-nums text-sm font-semibold" :class="group.total_minutes_lost > 0 ? 'text-rose-600' : 'text-emerald-600'">{{ formatMinutes(group.total_minutes_lost) }}</p>
            <p class="text-[11px] text-gray-500">time lost</p>
          </div>
          <RowActionsMenu :label="`Actions for ${group.teacher_name}`" :items="teacherActions(group)" />
        </header>

        <!-- Subject & Class Details Section -->
        <div v-if="isTeacherOpen(group)" class="p-3 sm:p-4">

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
                    <span class="rounded-md bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700">
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
                    class="grid w-full grid-cols-[4.5rem_1fr_auto_1rem] items-center gap-3 border-b border-slate-100 px-3 py-2.5 text-left text-sm transition last:border-0 hover:bg-brand-50/50 sm:grid-cols-[5rem_4.5rem_1fr_5.5rem_9.5rem_1rem] sm:px-4"
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
                          <div class="text-[10px] font-bold uppercase text-brand-600">{{ entry.day_of_week || '-' }}</div>
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
                        <div class="ml-auto">
                          <RowActionsMenu :label="`Actions for lesson on ${entry.date || ''}`" :items="entryActions(entry)" />
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

      <div v-if="visibleGroups.length > groupsPerPage || groupPage > 1" class="card overflow-hidden">
        <PagerBar v-model:page="groupPage" v-model:per-page="groupsPerPage" :total="visibleGroups.length" class="!border-t-0" />
      </div>

      <div v-if="visibleGroups.length === 0" class="card px-5 py-14 text-center">
        <ClipboardList class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No attendance records found</p>
      </div>
    </div>

    <div
      v-if="showViewModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
    >
      <div class="flex max-h-[92vh] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-white shadow-2xl">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-5 sm:px-8 sm:py-6">
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
                <div class="mt-1 text-2xl font-extrabold text-brand-600">{{ viewingTeacherSummary.total_expected_minutes }}</div>
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
                      <td class="px-6 py-4 text-sm font-bold text-brand-600">{{ r.day_of_week || '-' }}</td>
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
      class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 p-0 backdrop-blur-sm sm:items-center sm:p-4"
      @keydown.esc="showModal = false"
    >
      <div class="flex max-h-[100dvh] w-full max-w-3xl flex-col overflow-hidden bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="lm-modal-title">
        <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 ring-1 ring-brand-100"><Clock class="h-5 w-5" /></span>
          <div class="min-w-0 flex-1">
            <h3 id="lm-modal-title" class="text-lg font-semibold text-gray-900">
              {{ formMode === 'quick' ? 'Add attendance' : (editingId ? 'Edit monitoring record' : 'New monitoring record') }}
            </h3>
            <p class="text-sm text-gray-500">Enter when the lesson started and ended. Minutes lost work themselves out.</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="showModal = false"><X class="h-5 w-5" /></button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="saveRecord">
          <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
            <!-- Lesson -->
            <section>
              <p class="m-section">Lesson</p>

              <div v-if="formMode === 'quick'" class="mb-4 flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50/50 px-3 py-2.5">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(quickTeacherLabel) }}</span>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-semibold text-gray-900">{{ quickTeacherLabel }}</p>
                  <p class="text-xs text-gray-500">Term {{ form.term }}, {{ form.year }}</p>
                </div>
              </div>
              <div v-else class="mb-4">
                <span class="m-label">Teacher</span>
                <div v-if="selectedFormTeacher" class="flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50/50 px-3 py-2.5">
                  <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(selectedFormTeacher.full_name) }}</span>
                  <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-gray-900">{{ selectedFormTeacher.full_name }}</p>
                    <p class="font-mono text-[11px] text-gray-500">{{ selectedFormTeacher.teacher_code }}</p>
                  </div>
                  <button type="button" class="text-sm font-medium text-brand-700 hover:underline" @click="form.teacher_id = ''; teacherSearch = ''">Change</button>
                </div>
                <div v-else class="rounded-xl border border-gray-300 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100">
                  <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input v-model="teacherSearch" type="text" class="w-full rounded-t-xl border-0 bg-transparent py-2.5 pl-9 pr-3 text-sm outline-none" placeholder="Type a name or code" aria-label="Search teacher" />
                  </div>
                  <ul class="max-h-40 overflow-y-auto border-t border-gray-100 py-1" role="listbox">
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
              </div>

              <div class="grid gap-3 sm:grid-cols-3">
                <label class="block sm:col-span-1">
                  <span class="m-label">Subject</span>
                  <select v-model="form.subject" required class="m-input">
                    <option value="">Select subject</option>
                    <option v-for="sub in subjects" :key="sub" :value="sub">{{ sub }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="m-label">Class</span>
                  <select v-model="form.class" required class="m-input">
                    <option value="">Select class</option>
                    <option v-for="c in classes" :key="c" :value="c">{{ c }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="m-label">Stream</span>
                  <select v-model="form.stream" required class="m-input">
                    <option value="">Select stream</option>
                    <option v-for="s in streams" :key="s" :value="s">{{ s }}</option>
                  </select>
                </label>
              </div>
            </section>

            <!-- When -->
            <section>
              <p class="m-section">When</p>
              <div class="grid grid-cols-2 gap-3" :class="formMode === 'quick' ? 'sm:grid-cols-2' : 'sm:grid-cols-4'">
                <label v-if="formMode !== 'quick'" class="block">
                  <span class="m-label">Year</span>
                  <input v-model.number="form.year" type="number" required class="m-input" />
                </label>
                <label v-if="formMode !== 'quick'" class="block">
                  <span class="m-label">Term</span>
                  <select v-model="form.term" required class="m-input">
                    <option :value="1">Term 1</option>
                    <option :value="2">Term 2</option>
                    <option :value="3">Term 3</option>
                  </select>
                </label>
                <label class="block">
                  <span class="m-label">Week</span>
                  <select v-model="form.week_number" required class="m-input">
                    <option value="">Select week</option>
                    <option v-for="w in 15" :key="w" :value="w">Week {{ w }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="m-label">Date <span v-if="form.day_of_week" class="ml-1 normal-case tracking-normal text-brand-700">{{ form.day_of_week }}</span></span>
                  <input v-model="form.attendance_date" type="date" required class="m-input" />
                </label>
              </div>
            </section>

            <!-- Time -->
            <section>
              <p class="m-section">Time</p>
              <div class="mb-3">
                <span class="m-label">Lesson length</span>
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    v-for="opt in LESSON_LENGTHS"
                    :key="opt.minutes"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-sm font-medium transition"
                    :class="Number(form.expected_minutes) === opt.minutes ? 'border-brand-500 bg-brand-50 text-brand-800 ring-2 ring-brand-100' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                    :aria-pressed="Number(form.expected_minutes) === opt.minutes"
                    @click="form.expected_minutes = opt.minutes; autoCalculate()"
                  >{{ opt.label }} <span class="text-gray-400">{{ opt.minutes }} min</span></button>
                  <label class="flex items-center gap-1.5 text-sm text-gray-600">
                    or
                    <input v-model.number="form.expected_minutes" type="number" min="0" required class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-center text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" aria-label="Expected minutes" @input="autoCalculate" />
                    min
                  </label>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <label class="block">
                  <span class="m-label">Time in</span>
                  <input v-model="form.time_in" type="time" required class="m-input" @input="autoCalculate" @change="autoCalculate" />
                </label>
                <label class="block">
                  <span class="m-label">Time out</span>
                  <input v-model="form.time_out" type="time" required class="m-input" @input="autoCalculate" @change="autoCalculate" />
                </label>
              </div>
            </section>

            <p v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
          </div>

          <footer class="flex flex-wrap items-center gap-3 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
            <div class="min-w-0 flex-1">
              <template v-if="form.time_in && form.time_out && Number(form.expected_minutes)">
                <p class="text-sm">
                  <span class="font-semibold text-gray-900">Taught {{ form.actual_minutes }} of {{ form.expected_minutes }} min</span>
                  <span class="ml-2 font-semibold" :class="Number(form.minutes_lost) > 0 ? 'text-red-600' : 'text-emerald-600'">{{ Number(form.minutes_lost) > 0 ? `${form.minutes_lost} min lost` : 'No time lost' }}</span>
                </p>
                <div class="mt-1 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-gray-200">
                  <div class="h-full rounded-full transition-all duration-300" :class="Number(form.minutes_lost) > 0 ? 'bg-red-500' : 'bg-emerald-500'" :style="{ width: Math.min(100, (Number(form.actual_minutes) / Number(form.expected_minutes)) * 100) + '%' }"></div>
                </div>
              </template>
              <p v-else class="hidden text-sm text-gray-500 sm:block">Enter time in and time out to see the time lost.</p>
            </div>
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="showModal = false">Cancel</button>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || !form.teacher_id">
              <Check class="h-4 w-4" />{{ saving ? 'Saving…' : (editingId ? 'Save changes' : 'Save record') }}
            </button>
          </footer>
        </form>
      </div>
    </div>

    <!-- Compensation Modal -->
    <div
      v-if="showCompensationModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm"
    >
      <div class="flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl border border-slate-200/60 bg-white shadow-2xl">
        <div class="flex-shrink-0 border-b border-slate-200/60 bg-emerald-50 px-6 py-5 sm:px-8 sm:py-6">
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
                <input v-model="compensationForm.original_day" type="text" readonly class="input bg-slate-100 font-bold text-brand-600" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Compensation Date *</label>
                <input v-model="compensationForm.compensation_date" type="date" required class="input" />
              </div>

              <div>
                <label class="mb-2.5 block text-sm font-semibold text-slate-700">Compensation Day</label>
                <input v-model="compensationForm.compensation_day" type="text" readonly class="input bg-slate-100 font-bold text-brand-600" />
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

            <div v-if="compensationError" class="rounded-2xl border-2 border-rose-200 bg-rose-50 px-6 py-4 text-sm font-bold text-rose-700">
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
                class="inline-flex items-center gap-2 rounded-2xl bg-emerald-500 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/30 transition hover:shadow-emerald-500/40 disabled:cursor-not-allowed disabled:opacity-60"
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
import DownloadMenu from '../components/ui/DownloadMenu.vue';
import { downloadExcel, downloadPdf } from '../utils/downloads.js';
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { schoolSettingsAPI, lessonMonitoringAPI, teachersAPI, classesAPI, subjectsNewAPI, lessonCompensationsAPI } from '../services/api';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';
import { Plus, RefreshCw, BarChart3, ClipboardList, Pencil, Trash2, Eye, PlusCircle, Hourglass, RotateCcw, ListChecks, AlarmClock, ChevronsUpDown, ChevronDown, Clock, X, Search, Check, Download, TrendingDown, TrendingUp } from 'lucide-vue-next';
import PagerBar from '../components/ui/PagerBar.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';

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
useGlobalTerm((y, t) => { filterYear.value = y; filterTerm.value = t; }, () => loadData());

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

const groupSearch = ref('');
const LESSON_LENGTHS = [
  { label: 'Single', minutes: 40 },
  { label: 'Double', minutes: 80 },
  { label: 'Triple', minutes: 120 },
  { label: 'Quadruple', minutes: 160 },
];
const selectedFormTeacher = computed(() => teachersList.value.find((t) => String(t.id) === String(form.value.teacher_id)) || null);
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const visibleGroups = computed(() => {
  const q = groupSearch.value.trim().toLowerCase();
  if (!q) return groupedRecords.value;
  return groupedRecords.value.filter((g) => [g.teacher_name, ...(g.subjects_classes || []).flatMap((sc) => [sc.subject, sc.subject_name, sc.class, sc.stream])]
    .filter(Boolean).join(' ').toLowerCase().includes(q));
});
// Teacher cards start folded; the list is paged so the page stays short.
const openTeachers = ref(new Set());
const isTeacherOpen = (group) => openTeachers.value.has(group.group_key) || visibleGroups.value.length === 1;
const toggleTeacher = (group) => {
  const next = new Set(openTeachers.value);
  next.has(group.group_key) ? next.delete(group.group_key) : next.add(group.group_key);
  openTeachers.value = next;
};
const groupPage = ref(1);
const groupsPerPage = ref(10);
const pagedGroups = computed(() => visibleGroups.value.slice((groupPage.value - 1) * groupsPerPage.value, groupPage.value * groupsPerPage.value));
const teacherActions = (group) => [
  { label: 'View attendance', icon: Eye, onClick: () => openTeacherView(group) },
  { label: 'Add attendance', icon: PlusCircle, onClick: () => openQuickAddModal(group) },
  ...(group.subjects_classes.length > 1 ? [{ label: allClassesOpen(group) ? 'Collapse all classes' : 'Expand all classes', icon: ChevronsUpDown, onClick: () => toggleAllClasses(group) }] : []),
];
const entryActions = (entry) => [
  ...(entry.minutes_lost > 0 ? [{ label: 'Record time regained', icon: AlarmClock, onClick: () => openCompensationModal(entry) }] : []),
  { label: 'Edit', icon: Pencil, onClick: () => editRecord(entry) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteRecord(entry.id) },
];
const summaryCards = computed(() => {
  const owed = Math.max(0, (Number(summary.value.total_minutes_lost) || 0) - (Number(summary.value.compensated_minutes) || 0));
  return [
    { label: 'Time lost', value: formatMinutes(summary.value.total_minutes_lost), note: `≈ ${summary.value.equivalent_single_lessons || 0} single · ${summary.value.equivalent_double_lessons || 0} double lessons`, icon: Hourglass, tone: 'red' },
    { label: 'Compensated', value: formatMinutes(summary.value.compensated_minutes), note: `${summary.value.compensated_lessons || 0} lessons`, icon: RotateCcw, tone: 'sky' },
    { label: 'Still owed', value: formatMinutes(owed), icon: AlarmClock, tone: 'amber' },
    { label: 'Records', value: String(summary.value.records || 0), note: 'lessons monitored', icon: ListChecks },
  ];
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
    metricShort: 'single lessons',
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
    metricShort: 'time kept',
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
    metricShort: 'single lessons',
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
    metricShort: 'single lessons',
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

// Colours of the open report: red tones for "most time lost", green for "least".
const tone = computed(() => (activeReport.value.tone === 'rose'
  ? { text: 'text-rose-600', bar: 'bg-rose-500', barSoft: 'bg-rose-300', solid: 'bg-rose-600' }
  : { text: 'text-emerald-600', bar: 'bg-emerald-500', barSoft: 'bg-emerald-300', solid: 'bg-emerald-600' }));
const reportScopeChips = computed(() => reportScope.value.split(' · '));
const reportKpis = computed(() => {
  const t = reportTotals.value;
  return [
    { label: 'Time lost', value: formatMinutes(t.minutes), accent: true },
    { label: 'Lessons', value: t.lessons.toLocaleString() },
    { label: `${activeReport.value.entityPlural[0].toUpperCase()}${activeReport.value.entityPlural.slice(1)} listed`, value: String(reportRows.value.length) },
    { label: 'Average lost a lesson', value: `${t.lessons ? (t.minutes / t.lessons).toFixed(1) : '0'} min` },
  ];
});

// School name and logo for the PDF header (from Report Settings), fetched once.
let reportBranding = null;
const pdfBusy = ref(false);

const printReport = async () => {
  const rows = reportRows.value;
  if (rows.length === 0) return;
  pdfBusy.value = true;
  try {
    reportBranding ??= await schoolSettingsAPI.get().then((r) => r.data).catch(() => ({}));
  } finally {
    pdfBusy.value = false;
  }
  const schoolName = reportBranding?.school_name || "St. Mark's College Namagoma";

  const cfg = activeReport.value;
  const accent = cfg.tone === 'rose' ? [225, 29, 72] : [5, 150, 105];
  const accentSoft = cfg.tone === 'rose' ? [255, 241, 242] : [236, 253, 245];
  const brand = [5, 150, 105];
  const ink = [15, 23, 42];
  const muted = [100, 116, 139];
  const faint = [148, 163, 184];
  const line = [226, 232, 240];

  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  const pageH = doc.internal.pageSize.getHeight();
  const M = 14;

  // Brand strip, then the school (logo + name) and when it was made.
  doc.setFillColor(...brand);
  doc.rect(0, 0, pageW, 2.5, 'F');
  let textX = M;
  if (reportBranding?.logo) {
    try {
      const p = doc.getImageProperties(reportBranding.logo);
      const h = 13;
      const w = Math.min(26, (p.width / p.height) * h);
      doc.addImage(reportBranding.logo, p.fileType || 'PNG', M, 9, w, h);
      textX = M + w + 4;
    } catch {
      /* an unreadable logo should not stop the report */
    }
  }
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10.5);
  doc.setTextColor(...ink);
  doc.text(schoolName.toUpperCase(), textX, 14.5);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8);
  doc.setTextColor(...muted);
  doc.text('Lesson Monitoring Report', textX, 19.5);
  const generated = new Date().toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  doc.text(`Generated ${generated}`, pageW - M, 14.5, { align: 'right' });

  // Title block
  doc.setDrawColor(...line);
  doc.line(M, 26, pageW - M, 26);
  doc.setFillColor(...accent);
  doc.roundedRect(M, 31.5, 1.6, 12, 0.8, 0.8, 'F');
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(17);
  doc.setTextColor(...ink);
  doc.text(cfg.title, M + 5, 37);
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.setTextColor(...muted);
  doc.text(`${reportScope.value}  ·  ${cfg.description}`, M + 5, 43, { maxWidth: pageW - M * 2 - 5 });

  // Summary cards
  const gap = 4;
  const cardY = 50;
  const cardH = 19;
  const cardW = (pageW - M * 2 - gap * 3) / 4;
  reportKpis.value.forEach((k, i) => {
    const x = M + i * (cardW + gap);
    doc.setFillColor(255, 255, 255);
    doc.setDrawColor(...line);
    doc.roundedRect(x, cardY, cardW, cardH, 2.5, 2.5, 'FD');
    doc.setFillColor(...(k.accent ? accent : line));
    doc.roundedRect(x + 3, cardY, cardW - 6, 1, 0.5, 0.5, 'F');
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(...muted);
    doc.text(k.label, x + 4, cardY + 7);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(13.5);
    doc.setTextColor(...(k.accent ? accent : ink));
    doc.text(k.value, x + 4, cardY + 14.5);
  });

  // Ranked table: light header, thin dividers, rank badge, name over details, a bar for the minutes.
  const metricHead = cfg.metricLabel.replace('≈ ', '~ ');
  // Long lists (every teacher of a class) are cut to the first few, so rows stay compact.
  const shortNotes = (notes) => {
    if (!notes) return '';
    const [lead, list] = notes.startsWith('Most lost: ') ? ['Most lost: ', notes.slice(11)] : ['', notes];
    const items = list.split(', ');
    return lead + (items.length > 6 ? `${items.slice(0, 6).join(', ')} +${items.length - 6} more` : list);
  };
  const pdfRows = rows.map((r) => ({ ...r, notes: shortNotes(r.notes) }));
  autoTable(doc, {
    startY: cardY + cardH + 7,
    margin: { left: M, right: M, bottom: 18, top: 16 },
    head: [['#', cfg.entity, 'Time lost', '', 'Lessons', metricHead]],
    body: pdfRows.map((r) => [
      { content: '', raw: r },
      { content: r.notes ? `${r.name}\n${r.sub}\n${r.notes}` : `${r.name}\n${r.sub}`, raw: r },
      formatMinutes(r.minutes),
      { content: '', raw: r },
      r.lessons,
      r.metric,
    ]),
    theme: 'plain',
    rowPageBreak: 'avoid',
    styles: { font: 'helvetica', fontSize: 8.5, textColor: ink, cellPadding: { top: 3.2, bottom: 3.2, left: 2.5, right: 2.5 }, valign: 'middle' },
    headStyles: { textColor: muted, fontStyle: 'bold', fontSize: 7.5, fillColor: accentSoft },
    columnStyles: {
      0: { cellWidth: 11 },
      1: { cellWidth: 'auto', fontSize: 7.6 }, // measured at the size it is drawn
      2: { cellWidth: 22, halign: 'right', fontStyle: 'bold', textColor: accent },
      3: { cellWidth: 30 },
      4: { cellWidth: 17, halign: 'right' },
      5: { cellWidth: 26, halign: 'right' },
    },
    willDrawCell: (data) => {
      if (data.section === 'body' && data.column.index === 1) data.cell.text = [];
    },
    didDrawCell: (data) => {
      const { cell } = data;
      if (data.section === 'head' && data.column.index === 0) {
        // the header row's bottom rule
        doc.setDrawColor(...accent);
        doc.setLineWidth(0.3);
        doc.line(M, cell.y + cell.height, pageW - M, cell.y + cell.height);
      }
      if (data.section !== 'body') return;
      const r = cell.raw?.raw;
      if (data.column.index === 0) {
        doc.setDrawColor(...line);
        doc.setLineWidth(0.2);
        doc.line(M, cell.y + cell.height, pageW - M, cell.y + cell.height);
        const cx = cell.x + cell.width / 2;
        const cy = cell.y + cell.height / 2;
        const top = r.rank <= 3;
        doc.setFillColor(...(top ? accent : [241, 245, 249]));
        doc.circle(cx, cy, 3.1, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(...(top ? [255, 255, 255] : muted));
        doc.text(String(r.rank), cx, cy + 1.05, { align: 'center' });
      }
      if (data.column.index === 1) {
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
          doc.setTextColor(...faint);
          doc.text(doc.splitTextToSize(r.notes, cell.width - 5), x, y + 3.6);
        }
      }
      if (data.column.index === 3) {
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
    },
  });

  // Footer on every page; later pages also repeat a slim header.
  const pages = doc.internal.getNumberOfPages();
  for (let p = 1; p <= pages; p++) {
    doc.setPage(p);
    if (p > 1) {
      doc.setFillColor(...brand);
      doc.rect(0, 0, pageW, 2.5, 'F');
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(7.5);
      doc.setTextColor(...muted);
      doc.text(`${cfg.title} · ${reportScope.value}`, M, 9);
    }
    doc.setDrawColor(...line);
    doc.setLineWidth(0.2);
    doc.line(M, pageH - 12, pageW - M, pageH - 12);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(...muted);
    doc.text(`Skool Monitor · ${schoolName}`, M, pageH - 7);
    doc.text(`Page ${p} of ${pages}`, pageW - M, pageH - 7, { align: 'right' });
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
    week_number: filterWeek.value || '',
    attendance_date: new Date().toISOString().slice(0, 10),
    day_of_week: getDayOfWeek(new Date().toISOString().slice(0, 10)),
    time_in: '',
    time_out: '',
    expected_minutes: 40,
    actual_minutes: 0,
    minutes_lost: 0,
    year: Number(filterYear.value) || new Date().getFullYear(),
    term: filterTerm.value || 1
  };
  teacherSearch.value = '';

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
    week_number: filterWeek.value || '',
    attendance_date: new Date().toISOString().slice(0, 10),
    day_of_week: getDayOfWeek(new Date().toISOString().slice(0, 10)),
    time_in: '',
    time_out: '',
    expected_minutes: 40,
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
    error.value = (err?.response?.data?.message || 'Failed to save record');
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
    alert((err?.response?.data?.message || 'Failed to delete record'));
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
// Back to page 1 when filtering leaves the current page empty (declared last: it reads groupedRecords).
watch(visibleGroups, () => { if ((groupPage.value - 1) * groupsPerPage.value >= visibleGroups.value.length) groupPage.value = 1; });

// The whole year of the page's year filter, or one of its terms.
const periodScopes = computed(() => [
  { value: 'year', label: `Whole year ${filterYear.value}` },
  ...[1, 2, 3].map((t) => ({ value: String(t), label: `${filterYear.value} Term ${t}` })),
]);
const periodDefault = computed(() => (filterTerm.value ? String(filterTerm.value) : 'year'));
const periodLabel = (scope) => (scope === 'year' ? `${filterYear.value}, whole year` : `${filterYear.value} Term ${scope}`);
const downloading = ref(false);

// Every lesson record of the period (not only the page's filters), with totals per teacher and per class.
const downloadTimeLost = async (format, scope) => {
  downloading.value = true;
  try {
    const lessons = normalizeRows(await lessonMonitoringAPI.getAll(filterYear.value, scope === 'year' ? '' : scope)).map((r) => ({
      date: r.attendance_date, week: r.week_number, day: r.day_of_week, term: r.term, teacher: r.teacher_name, subject: r.subject,
      class: `${r.class || ''} ${r.stream || ''}`.trim(), time_in: shortTime(r.time_in), time_out: shortTime(r.time_out),
      expected: Number(r.expected_minutes || 0), taught: Number(r.actual_minutes || 0), lost: Number(r.minutes_lost || 0),
      made_up: Number(r.minutes_compensated || 0), status: Number(r.minutes_lost || 0) === 0 ? 'Complete' : (r.compensation_status || 'Not compensated'),
    }));
    const group = (keyOf) => Object.values(lessons.reduce((m, l) => {
      const k = keyOf(l);
      m[k] ||= { name: k, lessons: 0, expected: 0, lost: 0, made_up: 0 };
      Object.assign(m[k], { lessons: m[k].lessons + 1, expected: m[k].expected + l.expected, lost: m[k].lost + l.lost, made_up: m[k].made_up + l.made_up });
      return m;
    }, {})).map((g) => ({ ...g, owed: Math.max(0, g.lost - g.made_up), kept: g.expected ? `${Math.max(0, Math.round(100 - (g.lost / g.expected) * 100))}%` : '-' }))
      .sort((a, b) => b.lost - a.lost);
    const byTeacher = group((l) => l.teacher || '-');
    const byClass = group((l) => l.class || '-');
    const sumCols = (first) => [{ key: 'name', label: first }, { key: 'lessons', label: 'Lessons', align: 'right' }, { key: 'expected', label: 'Planned min', align: 'right' }, { key: 'lost', label: 'Min lost', align: 'right' }, { key: 'made_up', label: 'Made up', align: 'right' }, { key: 'owed', label: 'Still owed', align: 'right' }, { key: 'kept', label: 'Time kept', align: 'right' }];
    const label = periodLabel(scope);
    const lost = lessons.reduce((t, l) => t + l.lost, 0);
    const madeUp = lessons.reduce((t, l) => t + l.made_up, 0);
    if (format === 'excel') {
      downloadExcel(`lesson time lost ${label}`, [
        { name: 'By teacher', columns: sumCols('Teacher'), rows: byTeacher },
        { name: 'By class', columns: sumCols('Class'), rows: byClass },
        { name: 'Every lesson', columns: [{ key: 'date', label: 'Date' }, { key: 'term', label: 'Term' }, { key: 'week', label: 'Week' }, { key: 'day', label: 'Day' }, { key: 'teacher', label: 'Teacher' }, { key: 'subject', label: 'Subject' }, { key: 'class', label: 'Class' }, { key: 'time_in', label: 'In' }, { key: 'time_out', label: 'Out' }, { key: 'expected', label: 'Planned min' }, { key: 'taught', label: 'Taught min' }, { key: 'lost', label: 'Min lost' }, { key: 'made_up', label: 'Made up' }, { key: 'status', label: 'Status' }], rows: lessons },
      ]);
    } else {
      await downloadPdf({
        title: 'Lesson time lost',
        subtitle: `${label}, by teacher`,
        filename: `lesson time lost ${label}`,
        columns: sumCols('Teacher'),
        rows: byTeacher,
        landscape: false,
        summary: [
          { label: 'Lessons recorded', value: lessons.length.toLocaleString() },
          { label: 'Time lost', value: `${lost.toLocaleString()} min` },
          { label: 'Made up', value: `${madeUp.toLocaleString()} min` },
          { label: 'Still owed', value: `${Math.max(0, lost - madeUp).toLocaleString()} min` },
        ],
      });
    }
  } catch (e) {
    alert(e?.response?.data?.message || 'Could not prepare the download');
  } finally {
    downloading.value = false;
  }
};
</script>

<style scoped>
.m-section { @apply mb-3 text-sm font-semibold text-gray-900; }
.m-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.m-input { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tb-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.filter-select { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
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
  border-color: rgb(5 150 105);
  box-shadow: 0 0 0 4px rgb(220 252 231);
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
  background: rgb(220 252 231);
  padding: 0.45rem 0.85rem;
  font-size: 11px;
  font-weight: 700;
  color: rgb(4 120 87);
  border: 1px solid rgb(187 247 208);
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

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .m-section {
  color: var(--d-text);
}
html.dark .m-label {
  color: var(--d-muted);
}
html.dark .m-input {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
}
html.dark .tb-label {
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
html.dark .stat-card {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .teacher-stat {
  background-color: var(--d-surface);
}
html.dark .teacher-action {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .teacher-action:hover {
  background-color: var(--d-surface);
}
html.dark .filter-label {
  color: var(--d-text-2);
}
html.dark .mini-card {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .mini-value {
  color: var(--d-text);
}
html.dark .detail-badge {
  background-color: var(--d-sunken);
  color: var(--d-text-2);
  border-color: var(--d-line);
}
html.dark .detail-badge-rose {
  background-color: var(--d-surface);
  border-color: var(--d-line);
}
</style>
