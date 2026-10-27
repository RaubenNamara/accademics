<template>
  <div class="page">
    <ToastBanner />

    <!-- Header -->
    <div class="page-hero print:hidden">
      <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <h1 class="page-hero-title">Timetable Management</h1>
          <div class="mt-4 flex flex-wrap items-center gap-2">
            <label class="sr-only" for="tt-year">Academic year</label>
            <select id="tt-year" v-model.number="year" class="hero-select" @change="onYearChange">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <label class="sr-only" for="tt-term">Term</label>
            <select id="tt-term" v-model.number="term" class="hero-select" @change="pickSession">
              <option v-for="t in termsForYear" :key="t" :value="t">Term {{ t }}</option>
            </select>
            <span v-if="session" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide" :class="statusBadge.class">
              <i :class="statusBadge.icon"></i>{{ statusBadge.label }}
            </span>
            <span v-if="state?.has_unpublished_changes" class="text-xs text-brand-100/80">
              Version {{ state.published_version.version_number }} is in force · draft has unpublished changes
            </span>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <template v-if="canEdit && session">
            <button type="button" class="btn-hero-primary" @click="showGenerate = true"><i class="fas fa-wand-magic-sparkles"></i>Generate Timetable</button>
            <button type="button" class="btn-hero" :disabled="validating" @click="validate">
              <i class="fas" :class="validating ? 'fa-circle-notch fa-spin' : 'fa-shield-halved'"></i>Validate
            </button>
            <button type="button" class="btn-hero" @click="openPublish"><i class="fas fa-upload"></i>Publish</button>
          </template>
          <button type="button" class="btn-hero" :disabled="!session" @click="printView"><i class="fas fa-print"></i>Print</button>
          <div class="relative">
            <button type="button" class="btn-hero" :disabled="!session" :aria-expanded="exportOpen" @click="exportOpen = !exportOpen">
              <i class="fas fa-file-export"></i>Export<i class="fas fa-chevron-down text-[10px]"></i>
            </button>
            <div v-if="exportOpen" class="absolute right-0 z-30 mt-2 w-60 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 text-slate-700 shadow-xl">
              <button type="button" class="menu-item" @click="exportCsvView"><i class="fas fa-file-csv text-brand-700"></i>CSV, {{ exportScopeLabel }}</button>
              <button type="button" class="menu-item" :disabled="!gridTarget" @click="exportPdfView">
                <i class="fas fa-file-pdf text-red-600"></i>PDF, {{ gridTarget ? gridTarget.title : 'open a class, teacher or room' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="metaError" class="card border-red-200 p-6 text-sm text-red-700"><i class="fas fa-circle-exclamation mr-1"></i>{{ metaError }}</div>

    <div v-else-if="!meta" class="grid grid-cols-2 gap-3 sm:grid-cols-4 2xl:grid-cols-7" aria-busy="true">
      <div v-for="i in 7" :key="i" class="card h-28 animate-pulse bg-slate-50"></div>
    </div>

    <div v-else-if="!session" class="space-y-4">
      <div class="card p-8 text-center">
        <i class="fas fa-calendar-plus text-3xl text-brand-600"></i>
        <p class="mt-3 font-semibold text-slate-900">No academic session for {{ year }} Term {{ term }}</p>
        <p class="mt-1 text-sm text-slate-500">Add the term below, then the timetable for it appears here.</p>
      </div>
      <AcademicSessions @changed="loadMeta(true)" />
    </div>

    <template v-else>
      <!-- Stats -->
      <div class="card grid grid-cols-2 divide-slate-100 overflow-hidden sm:grid-cols-4 xl:grid-cols-7 print:hidden">
        <template v-if="!overview">
          <div v-for="i in 7" :key="i" class="h-16 animate-pulse bg-slate-50"></div>
        </template>
        <div v-for="s in statCards" v-else :key="s.label" class="flex items-center gap-2.5 border-b border-r border-slate-100 px-3.5 py-2.5" :title="s.hint">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs" :class="s.iconClass"><i :class="s.icon"></i></span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[11px] font-medium text-slate-500">{{ s.label }}</p>
            <p class="text-lg font-bold leading-tight tabular-nums" :class="s.valueClass || 'text-slate-900'">{{ s.value }}</p>
            <div v-if="s.progress !== undefined" class="mt-1 h-1 overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-brand-600 transition-all" :style="{ width: `${s.progress}%` }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <nav class="card flex gap-1 overflow-x-auto p-1.5 xl:flex-wrap print:hidden" aria-label="Timetable sections">
        <button
          v-for="t in tabs"
          :key="t.id"
          type="button"
          class="tab-btn"
          :class="[tab === t.id ? 'bg-brand-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100', t.first ? 'ml-2 border-l border-slate-200 pl-4' : '']"
          :aria-current="tab === t.id ? 'page' : undefined"
          @click="setTab(t.id)"
        >
          <i :class="t.icon"></i>{{ t.label }}
          <span v-if="t.badge" class="rounded-full px-1.5 text-[10px] font-bold" :class="tab === t.id ? 'bg-white/25' : t.badgeClass">{{ t.badge }}</span>
        </button>
      </nav>

      <!-- Filters for the grid views -->
      <div v-if="isGridTab" class="card p-4 print:hidden">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
          <div v-if="tab === 'class'" class="xl:col-span-2">
            <label class="filter-label">Class &amp; stream</label>
            <TtSearchSelect v-model="selectedClassId" :options="meta.classes" label="full_class_name" :hint="(c) => c.level" noun="classes" placeholder="Choose a class" />
          </div>
          <div v-if="tab === 'teacher'" class="xl:col-span-2">
            <label class="filter-label">Teacher</label>
            <TtSearchSelect v-model="selectedTeacherId" :options="meta.teachers" label="full_name" hint="teacher_code" noun="teachers" placeholder="Choose a teacher" />
          </div>
          <div v-if="tab === 'room'" class="xl:col-span-2">
            <label class="filter-label">Room</label>
            <TtSearchSelect v-model="selectedRoomId" :options="meta.rooms" :label="(r) => `${r.room_code} · ${r.room_name}`" hint="room_type" noun="rooms" placeholder="Choose a room" />
          </div>
          <div v-if="tab === 'overview'">
            <label class="filter-label">Class level</label>
            <select v-model="levelFilter" class="input">
              <option value="">All levels</option>
              <option value="O-Level">O-Level</option>
              <option value="A-Level">A-Level</option>
            </select>
          </div>
          <div>
            <label class="filter-label">Subject</label>
            <TtSearchSelect v-model="subjectFilter" :options="meta.subjects" label="subject_name" noun="subjects" placeholder="All subjects" clearable />
          </div>
          <div v-if="tab !== 'teacher'">
            <label class="filter-label">Teacher</label>
            <TtSearchSelect v-model="teacherFilter" :options="meta.teachers" label="full_name" noun="teachers" placeholder="All teachers" clearable />
          </div>
          <div v-if="tab !== 'room'">
            <label class="filter-label">Room</label>
            <TtSearchSelect v-model="roomFilter" :options="meta.rooms" :label="(r) => r.room_code" noun="rooms" placeholder="All rooms" clearable />
          </div>
          <div>
            <label class="filter-label" for="tt-search">Search</label>
            <div class="relative">
              <i class="fas fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
              <input id="tt-search" v-model="searchInput" type="search" class="input pl-9" placeholder="Subject, teacher, class…" />
            </div>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
          <div class="inline-flex rounded-xl border border-slate-200 p-0.5 text-xs font-semibold">
            <button type="button" class="rounded-lg px-3 py-1.5" :class="viewMode === 'week' ? 'bg-brand-700 text-white' : 'text-slate-600'" @click="viewMode = 'week'">Week</button>
            <button type="button" class="rounded-lg px-3 py-1.5" :class="viewMode === 'day' ? 'bg-brand-700 text-white' : 'text-slate-600'" @click="viewMode = 'day'">Day</button>
          </div>
          <div v-if="viewMode === 'day' || tab === 'overview'" class="flex flex-wrap gap-1">
            <button v-for="d in meta.days" :key="d" type="button" class="rounded-full px-3 py-1 text-xs font-semibold" :class="day === d ? 'bg-brand-100 text-brand-800' : 'text-slate-500 hover:bg-slate-100'" @click="day = d">
              {{ d.slice(0, 3) }}
            </button>
          </div>
          <div v-if="state?.published_version" class="inline-flex rounded-xl border border-slate-200 p-0.5 text-xs font-semibold">
            <button type="button" class="rounded-lg px-3 py-1.5" :class="source === 'draft' ? 'bg-slate-800 text-white' : 'text-slate-600'" @click="source = 'draft'">Working draft</button>
            <button type="button" class="rounded-lg px-3 py-1.5" :class="source === 'published' ? 'bg-slate-800 text-white' : 'text-slate-600'" @click="showPublished">Published v{{ state.published_version.version_number }}</button>
          </div>
          <button v-if="filtersActive" type="button" class="ml-auto text-xs font-semibold text-brand-700 hover:underline" @click="clearFilters">
            <i class="fas fa-xmark mr-1"></i>Clear filters
          </button>
        </div>
      </div>

      <div v-if="loading" class="card space-y-3 p-6">
        <div v-for="i in 6" :key="i" class="h-10 animate-pulse rounded-xl bg-slate-100"></div>
      </div>

      <!-- Overview: whole school, one day -->
      <section v-else-if="tab === 'overview'" class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
          <div>
            <h2 class="card-title">{{ day }} at a glance</h2>
          </div>
          <p class="text-xs text-slate-500">{{ overviewClasses.length }} classes · {{ dayLessonCount }} lessons</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[820px] border-separate border-spacing-0 text-xs">
            <thead>
              <tr>
                <th class="sticky left-0 z-10 w-28 border-b border-slate-200 bg-slate-50 px-3 py-2.5 text-left font-semibold uppercase tracking-wide text-slate-500">Class</th>
                <th v-for="p in meta.periods" :key="p.period_number" class="border-b border-l border-slate-200 px-2 py-2.5 text-left font-semibold text-slate-500" :class="p.period_type === 'lunch' ? 'w-10 bg-amber-50 text-amber-800' : p.period_type !== 'lesson' ? 'w-10 bg-slate-100' : 'bg-slate-50'">
                  <template v-if="p.period_type === 'lesson'">{{ p.label.replace('Period ', 'P') }}<span class="block text-[10px] font-normal text-slate-400">{{ shortTime(p.start_time) }}</span></template>
                  <span v-else class="block text-center text-[10px] uppercase" :title="`${p.label} ${shortTime(p.start_time)}–${shortTime(p.end_time)}`"><i :class="p.period_type === 'lunch' ? 'fas fa-utensils' : 'fas fa-mug-hot'"></i></span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in overviewClasses" :key="c.id" class="group">
                <th class="sticky left-0 z-10 border-b border-slate-100 bg-white px-3 py-1.5 text-left font-semibold text-slate-700 group-hover:bg-slate-50">
                  <button type="button" class="hover:text-brand-700 hover:underline" @click="openClass(c.id)">{{ c.full_class_name }}</button>
                </th>
                <td v-for="p in meta.periods" :key="p.period_number" class="h-12 border-b border-l border-slate-100 p-1 align-top" :class="p.period_type === 'lunch' ? 'bg-amber-50' : p.period_type !== 'lesson' ? 'bg-slate-100' : ''">
                  <template v-if="p.period_type === 'lesson'">
                    <button
                      v-for="item in overviewIndex[`${c.id}|${p.period_number}`] || []"
                      :key="item.entry.id"
                      type="button"
                      class="mb-0.5 block w-full truncate rounded-lg border px-1.5 py-1 text-left font-semibold"
                      :class="conflictIds.includes(item.entry.id) ? 'ring-2 ring-red-500' : ''"
                      :style="chipStyle(item.entry)"
                      :title="describeEntry(item.entry)"
                      @click="openEntry(item.entry)"
                    >
                      {{ item.offset ? '↳ ' : '' }}{{ item.entry.subject_code || entryTitle(item.entry) }}
                      <span class="block truncate text-[10px] font-normal opacity-80">{{ initials(item.entry.teacher_name) }} {{ item.entry.room_code ? '· ' + item.entry.room_code : '' }}</span>
                    </button>
                    <button
                      v-if="canEditView && !(overviewIndex[`${c.id}|${p.period_number}`] || []).length"
                      type="button"
                      class="flex h-full min-h-[2.25rem] w-full items-center justify-center rounded-lg text-slate-200 hover:bg-brand-50 hover:text-brand-600"
                      :aria-label="`Add lesson for ${c.full_class_name}, ${day} ${p.label}`"
                      @click="openForm({ class_id: c.id, day, period: p.period_number })"
                    >
                      <i class="fas fa-plus text-[10px]"></i>
                    </button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Class / teacher / room timetables -->
      <section v-else-if="isGridTab" class="card overflow-hidden print:border-0 print:shadow-none">
        <template v-if="gridTarget">
          <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
              <h2 class="text-lg font-bold text-slate-900">{{ gridTarget.title }}</h2>
              <p class="text-xs text-slate-500">{{ gridTarget.subtitle }} · {{ session.session_name }}{{ source === 'published' ? ' · published version' : '' }}</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
              <span v-for="s in gridTarget.summary" :key="s" class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">{{ s }}</span>
            </div>
          </div>
          <div class="p-3 sm:p-4">
            <TtWeekGrid
              :entries="gridEntries"
              :periods="meta.periods"
              :days="viewMode === 'day' ? [day] : meta.days"
              :mode="tab"
              :editable="canEditView"
              :conflict-ids="conflictIds"
              :focus-id="focusId"
              @open="openEntry"
              @add="(slot) => openForm({ ...gridPrefill, ...slot })"
              @move="moveEntry"
            />
          </div>
        </template>
        <div v-else class="px-6 py-14 text-center text-sm text-slate-500">
          <i class="fas fa-hand-pointer mb-2 block text-2xl text-slate-300"></i>Choose a {{ tab }} above to see its timetable.
        </div>
      </section>

      <!-- Unscheduled -->
      <section v-else-if="tab === 'unscheduled'" class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
          <div>
            <h2 class="card-title">Unscheduled lessons</h2>
            <p class="text-xs text-slate-500">Required periods from Lesson Requirements that are not on the timetable yet.</p>
          </div>
          <div class="flex gap-2">
            <input v-model="unscheduledQuery" type="search" class="input w-56" placeholder="Filter by class, subject, teacher" />
            <button v-if="canEdit && unscheduled.length" type="button" class="btn-primary shrink-0" @click="openGenerate([])"><i class="fas fa-fill-drip"></i>Fill all</button>
          </div>
        </div>
        <div v-if="!overview?.stats.requirements" class="px-6 py-12 text-center text-sm text-slate-500">
          No lesson requirements are set for this term, so nothing can be missing yet.
          <button type="button" class="font-semibold text-brand-700 hover:underline" @click="setTab('requirements')">Add requirements</button>
        </div>
        <div v-else-if="!unscheduled.length" class="px-6 py-12 text-center text-sm text-brand-800">
          <i class="fas fa-circle-check mb-2 block text-2xl"></i>Every required lesson is scheduled.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="data-table">
            <thead><tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Scheduled</th><th>Missing</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
              <tr v-for="u in filteredUnscheduled" :key="u.requirement_id">
                <td class="font-medium text-slate-900">{{ u.class }}</td>
                <td>{{ u.subject }}</td>
                <td>{{ u.teacher }}</td>
                <td>
                  <div class="flex items-center gap-2">
                    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-brand-600" :style="{ width: `${(u.scheduled / u.required) * 100}%` }"></div></div>
                    <span class="text-xs text-slate-500">{{ u.scheduled }}/{{ u.required }}</span>
                  </div>
                </td>
                <td><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ u.missing }} period(s)</span></td>
                <td class="text-right">
                  <div v-if="canEdit" class="inline-flex gap-2">
                    <button type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="scheduleRequirement(u)"><i class="fas fa-plus"></i>Schedule</button>
                    <button type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="openGenerate([u.class_id])"><i class="fas fa-wand-magic-sparkles"></i>Fill class</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Conflicts & validation -->
      <TtValidationPanel
        v-else-if="tab === 'validation'"
        :data="overview"
        :loading="validating"
        @revalidate="validate"
        @open="openEntryById"
        @schedule="(i) => scheduleRequirement(unscheduled.find((u) => u.requirement_id === i.requirement_id))"
      />

      <TtHistory v-else-if="tab === 'history'" ref="historyRef" :session="session" :can-edit="canEdit" @restored="refresh" />

      <!-- Setup (existing screens, unchanged) -->
      <!-- Setup: one place for everything the generator needs -->
      <div v-else-if="tab === 'setup'" class="grid gap-4 lg:grid-cols-[15rem_1fr]">
        <nav class="card h-fit overflow-hidden p-1.5" aria-label="Setup sections">
          <button
            v-for="s in setupSections"
            :key="s.id"
            type="button"
            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition"
            :class="section === s.id ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100'"
            @click="setTab(s.id)"
          >
            <i :class="s.icon" class="w-4 text-center"></i>{{ s.label }}
            <i v-if="s.external" class="fas fa-arrow-up-right-from-square ml-auto text-[10px] opacity-60"></i>
          </button>
        </nav>
        <div class="min-w-0">
          <TtSetupChecklist v-if="section === 'checklist'" :session="session" @go="setTab" />
          <AcademicSessions v-else-if="section === 'sessions'" @changed="loadMeta(true)" />
          <TtSchoolDay v-else-if="section === 'school-day'" :session="session" @changed="loadMeta(true); refresh()" />
          <TtRooms v-else-if="section === 'rooms'" @changed="loadMeta(true)" />
          <TtSubjectDepartments v-else-if="section === 'departments'" @changed="loadMeta(true)" />
          <LessonRequirementsManager v-else-if="section === 'requirements'" :active-session="session" />
          <TtAvailability v-else-if="section === 'availability'" :active-session="session" @changed="refresh" />
        </div>
      </div>
    </template>

    <TtLessonForm
      v-if="form.open"
      :meta="meta"
      :session="session"
      :entry="form.entry"
      :prefill="form.prefill"
      :requirements="requirements"
      @close="form.open = false"
      @saved="onSaved"
      @removed="onRemoved"
    />
    <TtGenerateDialog
      v-if="showGenerate"
      :meta="meta"
      :session="session"
      :state="state"
      :requirement-count="overview?.stats.requirements || 0"
      :preset-class-ids="generatePreset"
      @close="showGenerate = false; generatePreset = []"
      @done="refresh"
    />
    <TtPublishDialog
      v-if="showPublish"
      :session="session"
      :summary="overview?.summary"
      :next-number="(state?.published_version?.version_number || 0) + 1"
      @close="showPublish = false"
      @revalidated="(v) => (overview = { ...overview, ...v })"
      @published="onPublished"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { timetableAPI, lessonRequirementsAPI } from '../services/api.js';
import termStore from '../services/termStore.js';
import authStore from '../services/authStore.js';
import { useToast } from '../composables/useToast.js';
import { apiError, entryTitle, exportCsv, exportGridPdf, indexEntries, shortTime, subjectColour } from '../utils/timetable.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import TtWeekGrid from '../components/timetable/TtWeekGrid.vue';
import TtSearchSelect from '../components/timetable/TtSearchSelect.vue';
import TtLessonForm from '../components/timetable/TtLessonForm.vue';
import TtValidationPanel from '../components/timetable/TtValidationPanel.vue';
import TtGenerateDialog from '../components/timetable/TtGenerateDialog.vue';
import TtPublishDialog from '../components/timetable/TtPublishDialog.vue';
import TtHistory from '../components/timetable/TtHistory.vue';
import AcademicSessions from '../components/timetable/AcademicSessions.vue';
import LessonRequirementsManager from '../components/timetable/LessonRequirementsManager.vue';
import TtAvailability from '../components/timetable/TtAvailability.vue';
import TtSchoolDay from '../components/timetable/TtSchoolDay.vue';
import TtSetupChecklist from '../components/timetable/TtSetupChecklist.vue';
import TtRooms from '../components/timetable/TtRooms.vue';
import TtSubjectDepartments from '../components/timetable/TtSubjectDepartments.vue';

const route = useRoute();
const router = useRouter();
const { showToast } = useToast();

// Matches the roles the timetable API accepts for changes.
const canEdit = computed(() => ['super_admin', 'admin', 'teacher'].includes(authStore.getRole()));

const meta = ref(null);
const metaError = ref('');
const year = ref(new Date().getFullYear());
const term = ref(1);
const session = ref(null);
const entries = ref([]);
const publishedEntries = ref(null);
const overview = ref(null);
const requirements = ref([]);
const loading = ref(false);
const validating = ref(false);

// Old links (?tab=requirements, ?tab=rooms …) open the matching Setup section.
const tab = ref(['sessions', 'bell', 'requirements', 'availability', 'departments', 'rooms'].includes(route.query.tab) ? 'setup' : route.query.tab || 'overview');
const legacySection = { bell: 'school-day', sessions: 'sessions', requirements: 'requirements', availability: 'availability', departments: 'departments', rooms: 'rooms' }[route.query.tab];
const source = ref('draft');
const viewMode = ref(window.matchMedia?.('(max-width: 767px)').matches ? 'day' : 'week');
const todayName = new Date().toLocaleDateString('en-US', { weekday: 'long' });
const day = ref('Monday');
const selectedClassId = ref(null);
const selectedTeacherId = ref(null);
const selectedRoomId = ref(null);
const subjectFilter = ref(null);
const teacherFilter = ref(null);
const roomFilter = ref(null);
const levelFilter = ref('');
const searchInput = ref('');
const search = ref('');
const unscheduledQuery = ref('');
const focusId = ref(null);
const exportOpen = ref(false);
const showGenerate = ref(false);
const generatePreset = ref([]);
const showPublish = ref(false);
const historyRef = ref(null);
const form = reactive({ open: false, entry: null, prefill: {} });

let searchTimer = null;
watch(searchInput, (v) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { search.value = v.trim().toLowerCase(); }, 250);
});

const tabs = computed(() => {
  const s = overview.value?.stats;
  const errors = overview.value?.summary.errors || 0;
  const warnings = overview.value?.summary.warnings || 0;
  return [
    { id: 'overview', label: 'Overview', icon: 'fas fa-table-cells' },
    { id: 'class', label: 'Class Timetables', icon: 'fas fa-users' },
    { id: 'teacher', label: 'Teacher Timetables', icon: 'fas fa-chalkboard-teacher' },
    { id: 'room', label: 'Room Timetables', icon: 'fas fa-door-open' },
    { id: 'unscheduled', label: 'Unscheduled Lessons', icon: 'fas fa-list-check', badge: s?.unscheduled_periods || '', badgeClass: 'bg-amber-100 text-amber-800' },
    { id: 'validation', label: 'Conflicts & Validation', icon: 'fas fa-shield-halved', badge: errors || warnings || '', badgeClass: errors ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' },
    { id: 'history', label: 'History', icon: 'fas fa-clock-rotate-left' },
    { id: 'setup', label: 'Setup', icon: 'fas fa-sliders', first: true },
  ];
});
const isGridTab = computed(() => ['overview', 'class', 'teacher', 'room'].includes(tab.value));
const setupSections = [
  { id: 'checklist', label: 'Checklist', icon: 'fas fa-list-check' },
  { id: 'sessions', label: 'Terms', icon: 'fas fa-calendar' },
  { id: 'school-day', label: 'School day', icon: 'far fa-clock' },
  { id: 'classes', label: 'Classes & streams', icon: 'fas fa-chalkboard', external: true },
  { id: 'rooms', label: 'Rooms', icon: 'fas fa-door-open' },
  { id: 'departments', label: 'Subject departments', icon: 'fas fa-sitemap' },
  { id: 'requirements', label: 'Lesson requirements', icon: 'fas fa-clipboard-list' },
  { id: 'availability', label: 'Teacher availability', icon: 'fas fa-user-clock' },
];
const setupIds = setupSections.map((s) => s.id);
const section = ref(setupIds.includes(route.query.section) ? route.query.section : legacySection || 'checklist');

// Follow the URL (back/forward, links, old ?tab=requirements bookmarks) without a reload.
watch(() => [route.query.tab, route.query.section], ([t, s]) => {
  const legacy = { bell: 'school-day', sessions: 'sessions', requirements: 'requirements', availability: 'availability', departments: 'departments', rooms: 'rooms' }[t];
  const target = legacy ? 'setup' : t || 'overview';
  if (target !== tab.value) tab.value = target;
  const sec = setupIds.includes(s) ? s : legacy;
  if (target === 'setup' && sec && sec !== section.value) section.value = sec;
});

/** Setup sections can be opened by id from anywhere (e.g. setTab('requirements')). */
const setTab = (id) => {
  if (id === 'classes') {
    router.push('/classes-management'); // classes & streams are managed in their own module
    return;
  }
  const target = setupIds.includes(id) ? 'setup' : id;
  if (tab.value === 'setup' && target !== 'setup') loadMeta(true);
  tab.value = target;
  if (target === 'setup') section.value = setupIds.includes(id) ? id : section.value;
  exportOpen.value = false;
  router.replace({ query: { ...route.query, tab: target, section: target === 'setup' ? section.value : undefined } });
};

/* ---------- sessions ---------- */
const years = computed(() => {
  const ys = [...new Set((meta.value?.sessions || []).map((s) => s.academic_year))];
  if (!ys.includes(year.value)) ys.push(year.value);
  return ys.sort((a, b) => b - a);
});
const termsForYear = computed(() => {
  const ts = (meta.value?.sessions || []).filter((s) => s.academic_year === year.value).map((s) => s.term);
  return [...new Set([...ts, 1, 2, 3])].sort();
});
watch(() => termStore.selected.value?.id, (id, old) => {
  const s = termStore.selected.value;
  if (!s || id === old || !meta.value) return;
  year.value = Number(s.academic_year);
  term.value = Number(s.term);
  pickSession();
});
const onYearChange = () => {
  const ts = (meta.value?.sessions || []).filter((s) => s.academic_year === year.value).map((s) => s.term).sort();
  if (ts.length && !ts.includes(term.value)) term.value = ts[0];
  pickSession();
};
const pickSession = () => {
  session.value = (meta.value?.sessions || []).find((s) => s.academic_year === year.value && s.term === term.value) || null;
  source.value = 'draft';
  publishedEntries.value = null;
  if (session.value) refresh();
  else { entries.value = []; overview.value = null; }
};

const loadMeta = async (keepSession = false) => {
  try {
    const [res] = await Promise.all([timetableAPI.meta(), termStore.load().catch(() => null)]);
    meta.value = res.data;
    metaError.value = '';
    if (!keepSession) {
      // Start on the term picked in the top bar (the open term unless an admin chose another).
      const picked = termStore.selected.value;
      const active = (picked && res.data.sessions.find((s) => Number(s.id) === picked.id)) || res.data.sessions.find((s) => s.is_active) || res.data.sessions[0];
      if (active) { year.value = active.academic_year; term.value = active.term; }
      day.value = res.data.days.includes(todayName) ? todayName : res.data.days[0];
      selectedClassId.value ||= res.data.classes[0]?.id ?? null;
    }
    pickSession();
  } catch (err) {
    metaError.value = apiError(err).message;
  }
};

const refresh = async () => {
  if (!session.value) return;
  const sid = session.value.id;
  loading.value = !entries.value.length;
  try {
    const [e, o, r] = await Promise.all([
      timetableAPI.getAll({ academic_session_id: sid }),
      timetableAPI.overview(sid),
      lessonRequirementsAPI.getBySession(sid).catch(() => ({ data: [] })),
    ]);
    if (session.value?.id !== sid) return;
    entries.value = e.data || [];
    overview.value = o.data;
    requirements.value = (r.data || []).map((x) => ({ ...x, class_id: Number(x.class_id), subject_id: Number(x.subject_id), teacher_id: Number(x.teacher_id) }));
    if (source.value === 'published') await loadPublished();
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    loading.value = false;
  }
};

const loadPublished = async () => {
  const res = await timetableAPI.published(session.value.id);
  publishedEntries.value = res.data?.entries || [];
};
const showPublished = async () => {
  source.value = 'published';
  try { await loadPublished(); } catch (err) { showToast(apiError(err).message, 'error'); source.value = 'draft'; }
};

/* ---------- derived data ---------- */
const state = computed(() => overview.value?.state || null);
const statusBadge = computed(() => {
  const s = state.value?.status;
  if (s === 'published') return { label: 'Published', icon: 'fas fa-circle-check', class: 'bg-white text-brand-800' };
  if (s === 'validated') return { label: 'Validated', icon: 'fas fa-shield-halved', class: 'bg-brand-200 text-brand-900' };
  return { label: 'Draft', icon: 'fas fa-pen-ruler', class: 'bg-amber-200 text-amber-900' };
});

const statCards = computed(() => {
  const s = overview.value.stats;
  const hasReq = s.requirements > 0;
  const clashTone = (n) => (n ? { valueClass: 'text-red-600', iconClass: 'bg-red-50 text-red-600' } : { iconClass: 'bg-brand-50 text-brand-700' });
  return [
    { label: 'Required lessons', value: hasReq ? s.required_periods : '-', icon: 'fas fa-clipboard-list', iconClass: 'bg-brand-50 text-brand-700', hint: hasReq ? `periods/week from ${s.requirements} requirement(s)` : 'No lesson requirements yet' },
    { label: 'Scheduled lessons', value: s.scheduled_periods, icon: 'fas fa-calendar-check', iconClass: 'bg-brand-50 text-brand-700', hint: `${s.scheduled_lessons} lesson block(s) on the timetable` },
    { label: 'Unscheduled', value: hasReq ? s.unscheduled_periods : '-', icon: 'fas fa-hourglass-half', iconClass: s.unscheduled_periods ? 'bg-amber-50 text-amber-600' : 'bg-brand-50 text-brand-700', valueClass: s.unscheduled_periods ? 'text-amber-600' : '', hint: 'required periods still to place' },
    { label: 'Teacher conflicts', value: s.teacher_conflicts, icon: 'fas fa-chalkboard-teacher', ...clashTone(s.teacher_conflicts), hint: 'teacher in two places at once' },
    { label: 'Class conflicts', value: s.class_conflicts, icon: 'fas fa-users', ...clashTone(s.class_conflicts), hint: 'class or stream double-booked' },
    { label: 'Room conflicts', value: s.room_conflicts, icon: 'fas fa-door-open', ...clashTone(s.room_conflicts), hint: 'room double-booked' },
    { label: 'Completion', value: s.completion === null ? '-' : `${s.completion}%`, icon: 'fas fa-chart-pie', iconClass: 'bg-brand-50 text-brand-700', progress: s.completion ?? 0, hint: s.completion === null ? 'set requirements to measure' : 'of required periods scheduled' },
  ];
});

const conflictIds = computed(() => (overview.value?.issues || [])
  .filter((i) => ['teacher_clash', 'class_clash', 'room_clash', 'teacher_unavailable', 'invalid_period'].includes(i.type) && i.severity === 'error')
  .flatMap((i) => i.entry_ids || []));
const unscheduled = computed(() => overview.value?.unscheduled || []);
const filteredUnscheduled = computed(() => {
  const q = unscheduledQuery.value.trim().toLowerCase();
  return unscheduled.value.filter((u) => !q || `${u.class} ${u.subject} ${u.teacher}`.toLowerCase().includes(q));
});

const sourceEntries = computed(() => (source.value === 'published' ? publishedEntries.value || [] : entries.value));
const canEditView = computed(() => canEdit.value && source.value === 'draft');

const matchesFilters = (e) => {
  if (subjectFilter.value && e.subject_id !== subjectFilter.value) return false;
  if (teacherFilter.value && tab.value !== 'teacher' && e.teacher_id !== teacherFilter.value) return false;
  if (roomFilter.value && tab.value !== 'room' && e.room_id !== roomFilter.value) return false;
  if (search.value) {
    const hay = `${entryTitle(e)} ${e.subject_code || ''} ${e.teacher_name || ''} ${e.full_class_name || ''} ${e.room_code || ''} ${e.room_name || ''}`.toLowerCase();
    if (!hay.includes(search.value)) return false;
  }
  return true;
};
const filtersActive = computed(() => !!(subjectFilter.value || teacherFilter.value || roomFilter.value || search.value || levelFilter.value));
const clearFilters = () => {
  subjectFilter.value = teacherFilter.value = roomFilter.value = null;
  searchInput.value = '';
  search.value = '';
  levelFilter.value = '';
};

const gridEntries = computed(() => {
  const key = { class: 'class_id', teacher: 'teacher_id', room: 'room_id' }[tab.value];
  const id = { class: selectedClassId.value, teacher: selectedTeacherId.value, room: selectedRoomId.value }[tab.value];
  return sourceEntries.value.filter((e) => e[key] === id && matchesFilters(e));
});

const gridTarget = computed(() => {
  if (!meta.value) return null;
  const lessons = gridEntries.value.filter((e) => e.entry_type === 'lesson');
  const periods = lessons.reduce((n, e) => n + (Number(e.spans_periods) || 1), 0);
  const slots = meta.value.periods.filter((p) => p.period_type === 'lesson').length * meta.value.days.length;
  if (tab.value === 'class' && selectedClassId.value) {
    const c = meta.value.classes.find((x) => x.id === selectedClassId.value);
    return c && { title: c.full_class_name, subtitle: `${c.level} class timetable`, filename: `timetable-${c.full_class_name}`, summary: [`${periods} of ${slots} periods taught`, `${new Set(lessons.map((l) => l.subject_id)).size} subjects`] };
  }
  if (tab.value === 'teacher' && selectedTeacherId.value) {
    const t = meta.value.teachers.find((x) => x.id === selectedTeacherId.value);
    const busiest = Math.max(0, ...meta.value.days.map((d) => lessons.filter((l) => l.day_of_week === d).reduce((n, l) => n + (Number(l.spans_periods) || 1), 0)));
    return t && { title: t.full_name, subtitle: `Teacher timetable · ${t.teacher_code || ''}`, filename: `timetable-${t.full_name}`, summary: [`${periods} periods/week`, `${slots - periods} free periods`, `busiest day ${busiest}`] };
  }
  if (tab.value === 'room' && selectedRoomId.value) {
    const r = meta.value.rooms.find((x) => x.id === selectedRoomId.value);
    return r && { title: `${r.room_code} · ${r.room_name}`, subtitle: `Room timetable · ${r.room_type}${r.capacity ? `, seats ${r.capacity}` : ''}`, filename: `timetable-room-${r.room_code}`, summary: [`${Math.round((periods / slots) * 100)}% occupied`, `${slots - periods} free periods`] };
  }
  return null;
});
const gridPrefill = computed(() => ({
  class: { class_id: selectedClassId.value },
  teacher: { teacher_id: selectedTeacherId.value },
  room: { room_id: selectedRoomId.value },
}[tab.value] || {}));

const overviewClasses = computed(() => (meta.value?.classes || []).filter((c) => !levelFilter.value || c.level === levelFilter.value));
const overviewIndex = computed(() => {
  const map = {};
  for (const e of sourceEntries.value) {
    if (e.day_of_week !== day.value || !matchesFilters(e)) continue;
    const span = Math.max(1, Number(e.spans_periods) || 1);
    for (let i = 0; i < span; i++) (map[`${e.class_id}|${Number(e.period_number) + i}`] ||= []).push({ entry: e, offset: i });
  }
  return map;
});
const dayLessonCount = computed(() => sourceEntries.value.filter((e) => e.day_of_week === day.value && e.entry_type === 'lesson').length);

const initials = (name) => (name || '').split(/\s+/).filter(Boolean).map((p) => p[0]).join('').slice(0, 3).toUpperCase();
const chipStyle = (e) => {
  const c = subjectColour(e.subject_id);
  return { background: c.bg, borderColor: c.border, color: c.text };
};
const describeEntry = (e) => [entryTitle(e), e.full_class_name, e.teacher_name, e.room_code, `${shortTime(e.start_time)}–${shortTime(e.end_time)}`].filter(Boolean).join(' · ');

/* ---------- actions ---------- */
const openForm = (prefill = {}) => {
  if (!canEditView.value) return;
  form.entry = null;
  form.prefill = prefill;
  form.open = true;
};
const openEntry = (entry) => {
  if (!canEditView.value) {
    showToast(source.value === 'published' ? 'Switch to the working draft to make changes' : 'You can view but not change the timetable', 'error');
    return;
  }
  form.entry = entries.value.find((e) => e.id === entry.id) || entry;
  form.prefill = {};
  form.open = true;
};
const openEntryById = (id) => {
  const e = entries.value.find((x) => x.id === id);
  if (!e) return showToast('That lesson no longer exists, run validation again', 'error');
  source.value = 'draft';
  selectedClassId.value = e.class_id;
  if (viewMode.value === 'day') day.value = e.day_of_week;
  focusId.value = e.id;
  setTab('class');
  openEntry(e);
};
const openClass = (id) => { selectedClassId.value = id; setTab('class'); };
const scheduleRequirement = (u) => {
  if (!u) return;
  openForm({ class_id: u.class_id, subject_id: u.subject_id, teacher_id: u.teacher_id, room_id: u.room_id, spans_periods: u.preferred_span || 1 });
};
const openGenerate = (classIds) => { generatePreset.value = classIds; showGenerate.value = true; };

const onSaved = async ({ id, warnings, created, silent }) => {
  if (!silent) {
    form.open = false;
    showToast(warnings?.length ? `Saved with a warning: ${warnings[0].message}` : created ? 'Lesson added' : 'Lesson updated', 'success', warnings?.length ? 6000 : 3000);
  }
  focusId.value = id || null;
  await refresh();
  if (silent && form.entry) form.entry = entries.value.find((e) => e.id === form.entry.id) || form.entry;
};
const onRemoved = async ({ label } = {}) => {
  form.open = false;
  showToast(label ? `Removed ${label}` : 'Lesson removed');
  await refresh();
};

/** Drag-and-drop reschedule, the server validates the new slot. */
const moveEntry = async ({ entry, day: d, period }) => {
  try {
    await timetableAPI.update({ ...entry, academic_session_id: session.value.id, day_of_week: d, period_number: period });
    showToast(`Moved to ${d}, ${meta.value.periods.find((p) => p.period_number === period)?.label}`);
    focusId.value = entry.id;
    await refresh();
  } catch (err) {
    showToast(`Not moved: ${apiError(err).message}`, 'error', 6000);
  }
};

const validate = async () => {
  validating.value = true;
  try {
    const res = await timetableAPI.validate(session.value.id);
    overview.value = res.data;
    const s = res.data.summary;
    showToast(s.errors ? `${s.errors} blocking problem(s) found` : s.warnings ? `Validated with ${s.warnings} warning(s)` : 'Validation passed', s.errors ? 'error' : 'success');
    if (tab.value !== 'validation' && (s.errors || s.warnings)) setTab('validation');
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    validating.value = false;
  }
};

const openPublish = async () => {
  showPublish.value = true;
  try {
    const res = await timetableAPI.overview(session.value.id);
    overview.value = res.data;
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
};
const onPublished = async (data) => {
  showPublish.value = false;
  showToast(`Published version ${data.version_number}`);
  await refresh();
  historyRef.value?.load?.();
};

/* ---------- print & export ---------- */
const exportScopeLabel = computed(() => (gridTarget.value && tab.value !== 'overview' ? gridTarget.value.title : 'whole term'));
const exportCsvView = () => {
  exportOpen.value = false;
  const rows = gridTarget.value && tab.value !== 'overview' ? gridEntries.value : sourceEntries.value.filter(matchesFilters);
  exportCsv(gridTarget.value && tab.value !== 'overview' ? gridTarget.value.filename : `timetable-${session.value.session_name}`, rows);
};
const exportPdfView = () => {
  exportOpen.value = false;
  if (!gridTarget.value) return;
  exportGridPdf({
    title: gridTarget.value.title,
    subtitle: `${gridTarget.value.subtitle} · ${session.value.session_name}${source.value === 'published' ? ' · published' : ' · working draft'}`,
    filename: gridTarget.value.filename,
    entries: gridEntries.value,
    periods: meta.value.periods,
    days: meta.value.days,
    mode: tab.value,
  });
};
const printView = () => {
  exportOpen.value = false;
  if (!isGridTab.value) setTab('class');
  setTimeout(() => window.print(), 50);
};

onMounted(() => loadMeta());
</script>

<style scoped>
.hero-select {
  @apply rounded-xl border border-gray-300 bg-white py-1.5 pl-3 pr-8 text-sm font-medium text-gray-900 outline-none focus:border-brand-600 focus:ring-2 focus:ring-brand-100;
}
.hero-select option {
  @apply text-slate-900;
}
.tab-btn {
  @apply inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-medium transition;
}
.filter-label {
  @apply mb-1 block text-xs font-medium text-slate-500;
}
.menu-item {
  @apply flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50;
}
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .hero-select {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text);
}
html.dark .hero-select option {
  color: var(--d-text);
}
html.dark .filter-label {
  color: var(--d-muted);
}
html.dark .menu-item:hover {
  background-color: var(--d-sunken);
}
</style>
