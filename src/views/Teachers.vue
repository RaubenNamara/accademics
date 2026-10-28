<template>
  <div class="teachers-page page pb-8">
    <!-- Toast -->
    <Teleport to="body">
      <Transition name="toast">
        <div
          v-if="toast.show"
          class="fixed right-4 top-4 z-[200] flex min-w-[300px] max-w-md items-start gap-3 rounded-2xl border px-4 py-3.5 shadow-2xl backdrop-blur-md"
          :class="toast.type === 'success'
            ? 'border-emerald-200/80 bg-white/95 text-emerald-900'
            : 'border-rose-200/80 bg-white/95 text-rose-900'"
          role="alert"
        >
          <div
            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
            :class="toast.type === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"
          >
            <CheckCircle2 v-if="toast.type === 'success'" class="h-4 w-4" />
            <AlertCircle v-else class="h-4 w-4" />
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold">{{ toast.type === 'success' ? 'Success' : 'Error' }}</p>
            <p class="mt-0.5 text-sm text-slate-600">{{ toast.message }}</p>
          </div>
          <button type="button" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="hideToast">
            <X class="h-4 w-4" />
          </button>
        </div>
      </Transition>
    </Teleport>

    <!-- Header -->
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Teaching Staff</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero px-3" :disabled="loading" title="Refresh" aria-label="Refresh" @click="reloadAll">
            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          </button>
          <button type="button" class="btn-hero" @click="openImportModal"><Upload class="h-4 w-4" />Import</button>
          <button type="button" class="btn-hero-primary" @click="openAddModal"><Plus class="h-4 w-4" />Add teacher</button>
        </div>
      </div>
    </div>

    <!-- Summary (whole directory) -->
    <StatCards :cards="statCards" />

    <!-- Toolbar: view, search, filters, export -->
    <div class="card p-3 sm:p-4">
      <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-xl bg-gray-100 p-1" role="tablist" aria-label="Teachers view">
          <button
            v-for="v in views"
            :key="v.value"
            type="button"
            role="tab"
            :aria-selected="view === v.value"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition"
            :class="view === v.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
            @click="view = v.value"
          >
            <component :is="v.icon" class="h-4 w-4" /><span class="hidden sm:inline">{{ v.label }}</span>
          </button>
        </div>

        <template v-if="view === 'directory'">
          <div class="relative min-w-[12rem] flex-1">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input v-model="filters.search" type="search" placeholder="Search name, code, email, subject…" class="input py-2 !pl-9" @input="debouncedLoad" />
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition"
            :class="showFilters || activeFilterCount ? 'border-brand-300 bg-brand-50 text-brand-800' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
            :aria-expanded="showFilters"
            @click="showFilters = !showFilters"
          >
            <SlidersHorizontal class="h-4 w-4" />Filters
            <span v-if="activeFilterCount" class="rounded-full bg-brand-600 px-1.5 text-xs text-white">{{ activeFilterCount }}</span>
          </button>
          <div class="flex items-center gap-1">
            <button type="button" class="tool-btn" :disabled="!displayedTeachers.length || loading" title="Download PDF" @click="downloadPDF"><FileText class="h-4 w-4" /><span class="hidden xl:inline">PDF</span></button>
            <button type="button" class="tool-btn" :disabled="!displayedTeachers.length || loading" title="Download Excel" @click="downloadExcel"><FileSpreadsheet class="h-4 w-4" /><span class="hidden xl:inline">Excel</span></button>
            <button type="button" class="tool-btn" :disabled="!displayedTeachers.length || loading" title="Download CSV" @click="downloadCSV"><Download class="h-4 w-4" /><span class="hidden xl:inline">CSV</span></button>
            <router-link v-if="canViewAnalytics" to="/teaching-analytics" class="tool-btn" title="Teaching analytics"><BarChart3 class="h-4 w-4" /><span class="hidden xl:inline">Analytics</span></router-link>
          </div>
        </template>
      </div>

      <div v-if="view === 'directory' && showFilters" class="mt-3 grid gap-3 border-t border-gray-100 pt-3 sm:grid-cols-2 lg:grid-cols-5">
        <label class="block">
          <span class="filter-label">Subject</span>
          <select v-model="filters.subject" class="input py-2" @change="resetAndLoad">
            <option value="">All subjects</option>
            <option v-for="item in subjects" :key="item" :value="item">{{ item }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Department</span>
          <select v-model="filters.department" class="input py-2" @change="applyLocalFilters">
            <option value="">All departments</option>
            <option v-for="item in departmentOptions" :key="item" :value="item">{{ item }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Employment</span>
          <select v-model="filters.employment_type" class="input py-2" @change="applyLocalFilters">
            <option value="">All types</option>
            <option v-for="item in employmentTypeOptions" :key="item" :value="item">{{ item }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Obligation</span>
          <select v-model="filters.obligation" class="input py-2" @change="resetAndLoad">
            <option value="">All obligations</option>
            <option v-for="item in obligations" :key="item" :value="item">{{ item }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Status</span>
          <select v-model="filters.is_active" class="input py-2" @change="resetAndLoad">
            <option value="">Any status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </label>
        <div class="flex justify-end sm:col-span-2 lg:col-span-5">
          <button type="button" class="link-btn" :disabled="!activeFilterCount" @click="clearFilters">Clear all filters</button>
        </div>
      </div>
    </div>

    <template v-if="view === 'directory'">
    <section class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600">
          <strong class="text-gray-900">{{ totalTeachers }}</strong> teacher{{ totalTeachers === 1 ? '' : 's' }}<span v-if="activeFilterCount || filters.search"> match</span>
        </p>
        <label class="flex items-center gap-2 text-sm text-gray-600">
          Rows
          <select v-model.number="itemsPerPage" class="input w-auto py-1 text-sm" @change="resetAndLoad">
            <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
        </label>
      </div>

      <!-- Loading / empty -->
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="i in 5" :key="i" class="flex animate-pulse items-center gap-3">
          <div class="h-10 w-10 rounded-full bg-gray-100"></div>
          <div class="h-3 flex-1 rounded bg-gray-100"></div>
          <div class="hidden h-3 w-24 rounded bg-gray-100 sm:block"></div>
        </div>
      </div>
      <div v-else-if="!displayedTeachers.length" class="px-5 py-14 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400"><Users class="h-7 w-7" /></div>
        <p class="mt-3 font-semibold text-gray-800">No teachers found</p>
        <p class="mt-1 text-sm text-gray-500">{{ activeFilterCount || filters.search ? 'Try other filters or clear them.' : 'Add the first teacher to get started.' }}</p>
        <button v-if="activeFilterCount || filters.search" type="button" class="btn-secondary mt-4" @click="clearFilters">Clear filters</button>
        <button v-else type="button" class="btn-primary mt-4" @click="openAddModal"><Plus class="h-4 w-4" />Add teacher</button>
      </div>

      <template v-else>
        <!-- Table (tablet and up) -->
        <div class="hidden overflow-x-auto md:block">
          <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50/70">
              <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3">Teacher</th>
                <th class="px-4 py-3">Subject</th>
                <th class="hidden px-4 py-3 lg:table-cell">Position</th>
                <th class="hidden px-4 py-3 xl:table-cell">Contact</th>
                <th class="px-4 py-3">Obligation</th>
                <th class="px-4 py-3">Status</th>
                <th class="hidden px-4 py-3 xl:table-cell">Joined</th>
                <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="teacher in displayedTeachers" :key="teacher.id ?? teacher.teacher_id" class="transition hover:bg-brand-50/40">
                <td class="px-5 py-3">
                  <button type="button" class="flex min-w-0 items-center gap-3 text-left" @click="viewTeacher(teacher)">
                    <span class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">
                      <img v-if="teacher.passport_photo" :src="photoUrl(teacher.passport_photo)" alt="" class="h-full w-full object-cover" />
                      <template v-else>{{ initials(teacher.full_name) }}</template>
                    </span>
                    <span class="min-w-0">
                      <span class="block truncate font-semibold text-gray-900 hover:text-brand-700">{{ teacher.full_name }}</span>
                      <span class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-700">{{ teacher.teacher_code || 'No code' }}</span>
                        <span class="hidden max-w-[14rem] truncate lg:inline">{{ teacher.email || '' }}</span>
                      </span>
                    </span>
                  </button>
                </td>
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-800">{{ teacher.subject || '-' }}</p>
                  <p v-if="teacher.second_subject || teacher.department" class="text-xs text-gray-500">{{ [teacher.second_subject, teacher.department].filter(Boolean).join(' · ') }}</p>
                </td>
                <td class="hidden max-w-[12rem] px-4 py-3 text-gray-700 lg:table-cell"><span class="line-clamp-2">{{ teacher.position || '-' }}</span></td>
                <td class="hidden whitespace-nowrap px-4 py-3 text-gray-700 xl:table-cell">{{ teacher.contact || teacher.phone_number || '-' }}</td>
                <td class="px-4 py-3"><span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="obligationStyle(teacher.obligation)">{{ teacher.obligation || 'Subject Teacher' }}</span></td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium" :class="Number(teacher.is_active) === 0 ? 'text-gray-500' : 'text-brand-700'">
                    <span class="h-2 w-2 rounded-full" :class="Number(teacher.is_active) === 0 ? 'bg-gray-400' : 'bg-brand-500'"></span>{{ Number(teacher.is_active) === 0 ? 'Inactive' : 'Active' }}
                  </span>
                </td>
                <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 xl:table-cell">{{ formatDateShort(teacher.date_joined) }}</td>
                <td class="px-3 py-3 text-right">
                  <RowActionsMenu :label="`Actions for ${teacher.full_name}`" :items="rowActions(teacher)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Cards (phones) -->
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="teacher in displayedTeachers" :key="teacher.id ?? teacher.teacher_id" class="flex items-start gap-3 px-4 py-3">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">
              <img v-if="teacher.passport_photo" :src="photoUrl(teacher.passport_photo)" alt="" class="h-full w-full object-cover" />
              <template v-else>{{ initials(teacher.full_name) }}</template>
            </span>
            <button type="button" class="min-w-0 flex-1 text-left" @click="viewTeacher(teacher)">
              <p class="truncate font-semibold text-gray-900">{{ teacher.full_name }}</p>
              <p class="text-xs text-gray-500">{{ teacher.teacher_code || 'No code' }} · {{ teacher.subject || 'No subject' }}</p>
              <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="obligationStyle(teacher.obligation)">{{ teacher.obligation || 'Subject Teacher' }}</span>
                <span class="inline-flex items-center gap-1 text-[11px] font-medium" :class="Number(teacher.is_active) === 0 ? 'text-gray-500' : 'text-brand-700'">
                  <span class="h-1.5 w-1.5 rounded-full" :class="Number(teacher.is_active) === 0 ? 'bg-gray-400' : 'bg-brand-500'"></span>{{ Number(teacher.is_active) === 0 ? 'Inactive' : 'Active' }}
                </span>
              </div>
            </button>
            <RowActionsMenu :label="`Actions for ${teacher.full_name}`" :items="rowActions(teacher)" />
          </li>
        </ul>
      </template>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600">{{ ((currentPage - 1) * itemsPerPage) + 1 }}–{{ Math.min(currentPage * itemsPerPage, totalTeachers) }} of {{ totalTeachers }}</p>
        <nav class="flex items-center gap-1" aria-label="Pages">
          <button type="button" class="page-btn" :disabled="currentPage === 1" aria-label="Previous page" @click="prevPage"><ChevronLeft class="h-4 w-4" /></button>
          <template v-for="(pg, i) in pageWindow" :key="i">
            <span v-if="pg === '…'" class="px-1 text-gray-400">…</span>
            <button v-else type="button" class="page-btn" :class="pg === currentPage ? 'page-btn--on' : ''" :aria-current="pg === currentPage ? 'page' : undefined" @click="goToPage(pg)">{{ pg }}</button>
          </template>
          <button type="button" class="page-btn" :disabled="currentPage === totalPages" aria-label="Next page" @click="nextPage"><ChevronRight class="h-4 w-4" /></button>
        </nav>
      </div>
    </section>
    </template>

    <TeachersByDepartment v-else-if="view === 'department'" @toast="(m, t) => notify(m, t)" />
    <TeacherLoginsPanel v-else />

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
          <div class="modal-panel modal-panel--form" role="dialog" aria-modal="true">
            <div class="modal-header">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-200">{{ editingId ? 'Update record' : 'New registration' }}</p>
                <h3 class="text-xl font-bold">{{ editingId ? 'Edit Teacher' : 'Add Teacher' }}</h3>
                <p class="mt-1 text-sm text-brand-100/80">{{ editingId ? 'Update teaching staff details below.' : 'Register a new teacher in the directory.' }}</p>
              </div>
              <button type="button" class="modal-close" @click="closeModal"><X class="h-5 w-5" /></button>
            </div>

            <form id="teacher-form" class="modal-body" @submit.prevent="saveTeacher">
              <!-- Section 1 -->
              <FormSection title="Personal Information" desc="Identity and demographic details" :icon="User">
                <div class="form-grid">
                  <div class="field sm:col-span-2">
                    <label class="field-label">Full Name <span class="text-rose-500">*</span></label>
                    <input v-model.trim="form.full_name" type="text" required class="input" placeholder="e.g. Jane Wanjiku" />
                  </div>
                  <div class="field">
                    <label class="field-label">Gender</label>
                    <select v-model="form.gender" class="input">
                      <option value="">Select gender</option>
                      <option v-for="g in genderOptions" :key="g" :value="g">{{ g }}</option>
                    </select>
                  </div>
                  <div class="field">
                    <label class="field-label">Date of Birth</label>
                    <input v-model="form.date_of_birth" type="date" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">National ID</label>
                    <input v-model.trim="form.national_id" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Nationality</label>
                    <input v-model.trim="form.nationality" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Marital Status</label>
                    <select v-model="form.marital_status" class="input">
                      <option value="">Select status</option>
                      <option v-for="m in maritalOptions" :key="m" :value="m">{{ m }}</option>
                    </select>
                  </div>
                  <div class="field">
                    <label class="field-label">Religion</label>
                    <input v-model.trim="form.religion" type="text" class="input" />
                  </div>
                  <div class="field sm:col-span-2">
                    <label class="field-label">Passport Photo</label>
                    <div class="photo-upload">
                      <div v-if="form.passport_photo" class="photo-preview">
                        <img :src="photoUrl(form.passport_photo)" alt="Preview" class="h-20 w-20 rounded-2xl object-cover ring-2 ring-white" />
                        <button type="button" class="photo-remove" @click="clearPhoto">Remove</button>
                      </div>
                      <label class="photo-drop">
                        <Upload class="h-5 w-5 text-brand-500" />
                        <span class="text-sm font-medium text-slate-700">Upload photo</span>
                        <span class="text-xs text-slate-500">PNG, JPG up to 2MB</span>
                        <input type="file" accept="image/*" class="sr-only" @change="handlePhotoUpload" />
                      </label>
                    </div>
                  </div>
                </div>
              </FormSection>

              <!-- Section 2 -->
              <FormSection title="Contact Information" desc="Communication and emergency contacts" :icon="Phone">
                <div class="form-grid">
                  <div class="field">
                    <label class="field-label">Email <span class="text-rose-500">*</span></label>
                    <input v-model.trim="form.email" type="email" required class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Contact</label>
                    <input v-model.trim="form.contact" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Alternative Phone</label>
                    <input v-model.trim="form.alternative_phone" type="text" class="input" />
                  </div>
                  <div class="field sm:col-span-2">
                    <label class="field-label">Address</label>
                    <input v-model.trim="form.address" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">District</label>
                    <input v-model.trim="form.district" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Emergency Contact Name</label>
                    <input v-model.trim="form.emergency_contact_name" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Emergency Contact Phone</label>
                    <input v-model.trim="form.emergency_contact_phone" type="text" class="input" />
                  </div>
                </div>
              </FormSection>

              <!-- Section 3 -->
              <FormSection title="Professional Information" desc="Role, department, and teaching assignment" :icon="Briefcase">
                <div class="form-grid">
                  <div class="field">
                    <label class="field-label">Subject <span class="text-rose-500">*</span></label>
                    <select v-model="form.subject" required class="input">
                      <option value="">Select subject</option>
                      <option v-for="item in subjects" :key="item" :value="item">{{ item }}</option>
                    </select>
                  </div>
                  <div class="field">
                    <label class="field-label">Second Subject</label>
                    <select v-model="form.second_subject" class="input">
                      <option value="">None</option>
                      <option v-for="item in subjects" :key="'s2-' + item" :value="item">{{ item }}</option>
                    </select>
                  </div>
                  <div class="field">
                    <label class="field-label">Obligation</label>
                    <select v-model="form.obligation" class="input">
                      <option v-for="item in obligations" :key="item" :value="item">{{ item }}</option>
                    </select>
                  </div>
                  <div class="field">
                    <label class="field-label">Department</label>
                    <select v-model="form.department" class="input">
                      <option value="">Select department</option>
                      <option v-for="d in departmentOptions" :key="d" :value="d">{{ d }}</option>
                    </select>
                  </div>
                  <div class="field">
                    <label class="field-label">Position</label>
                    <input v-model.trim="form.position" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Employment Type</label>
                    <select v-model="form.employment_type" class="input">
                      <option value="">Select type</option>
                      <option v-for="t in employmentTypeOptions" :key="t" :value="t">{{ t }}</option>
                    </select>
                  </div>
                  <div class="field">
                    <label class="field-label">Supervisor</label>
                    <input v-model.trim="form.supervisor" type="text" class="input" />
                  </div>
                  <div class="field sm:col-span-2">
                    <label class="field-label">Other Duties</label>
                    <textarea v-model="form.other_duties" rows="4" class="input" placeholder="Enter other duties using ordered lists (e.g. 1. Duty one, 2. Duty two)"></textarea>
                  </div>
                </div>
              </FormSection>

              <!-- Section 4 -->
              <FormSection title="Education Information" desc="Academic qualifications and licensing" :icon="GraduationCap">
                <div class="form-grid">
                  <div class="field">
                    <label class="field-label">Qualification</label>
                    <input v-model.trim="form.qualification" type="text" class="input" placeholder="e.g. B.Ed" />
                  </div>
                  <div class="field">
                    <label class="field-label">University</label>
                    <input v-model.trim="form.university" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Year Graduated</label>
                    <input v-model.trim="form.year_graduated" type="text" class="input" placeholder="e.g. 2018" />
                  </div>
                  <div class="field">
                    <label class="field-label">Teaching License Number</label>
                    <input v-model.trim="form.teaching_license_number" type="text" class="input" />
                  </div>
                </div>
              </FormSection>

              <!-- Section 5 -->
              <FormSection title="Employment Information" desc="Contract, compensation, and status" :icon="Building2">
                <div class="form-grid">
                  <div class="field">
                    <label class="field-label">Date Joined</label>
                    <input v-model="form.date_joined" type="date" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Contract Start</label>
                    <input v-model="form.contract_start" type="date" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Contract End</label>
                    <input v-model="form.contract_end" type="date" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Salary</label>
                    <input v-model="form.salary" type="number" min="0" step="0.01" class="input" placeholder="0.00" />
                  </div>
                  <div class="field">
                    <label class="field-label">Bank Name</label>
                    <input v-model.trim="form.bank_name" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">Account Number</label>
                    <input v-model.trim="form.account_number" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">TIN Number</label>
                    <input v-model.trim="form.tin_number" type="text" class="input" />
                  </div>
                  <div class="field">
                    <label class="field-label">NSSF Number</label>
                    <input v-model.trim="form.nssf_number" type="text" class="input" />
                  </div>
                  <div class="field sm:col-span-2">
                    <label class="status-toggle mt-1" :class="{ 'status-toggle--on': form.is_active }">
                      <input v-model="form.is_active" type="checkbox" class="sr-only" />
                      <span class="status-toggle-track"><span class="status-toggle-thumb" /></span>
                      <span>
                        <span class="block text-sm font-semibold text-slate-900">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
                        <span class="block text-xs text-slate-500">Teacher appears in active listings when enabled</span>
                      </span>
                    </label>
                  </div>
                </div>
              </FormSection>

              <div v-if="createdTeacherCode" class="alert alert--success">
                Generated teacher code: <strong>{{ createdTeacherCode }}</strong>
              </div>
              <div v-if="error" class="alert alert--error">{{ error }}</div>
            </form>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeModal">Cancel</button>
              <button type="submit" form="teacher-form" class="btn-primary" :disabled="saving">
                {{ saving ? 'Saving...' : (editingId ? 'Update Teacher' : 'Save Teacher') }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- View Profile Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showViewModal" class="modal-overlay" @click.self="closeViewModal">
          <div class="modal-panel modal-panel--profile" role="dialog" aria-modal="true">
            <div class="profile-hero">
              <button type="button" class="modal-close modal-close--light" @click="closeViewModal"><X class="h-5 w-5" /></button>
              <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <div class="profile-photo-wrap">
                  <img
                    v-if="selectedTeacher.passport_photo"
                    :src="photoUrl(selectedTeacher.passport_photo)"
                    alt="Passport"
                    class="h-28 w-28 rounded-2xl object-cover ring-4 ring-white/30"
                  />
                  <div v-else class="profile-avatar-lg">{{ teacherInitials(selectedTeacher.full_name) }}</div>
                </div>
                <div class="flex-1 text-white">
                  <p class="text-xs font-semibold uppercase tracking-wider text-brand-200">{{ selectedTeacher.teacher_code || 'No code' }}</p>
                  <h3 class="mt-1 text-2xl font-bold">{{ selectedTeacher.full_name || 'Unknown' }}</h3>
                  <p class="mt-1 text-sm text-brand-100">{{ selectedTeacher.position || 'Teacher' }} · {{ selectedTeacher.department || '-' }}</p>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <span class="profile-pill" :class="Number(selectedTeacher.is_active) === 1 ? 'profile-pill--active' : 'profile-pill--inactive'">
                      {{ Number(selectedTeacher.is_active) === 1 ? 'Active' : 'Inactive' }}
                    </span>
                    <span v-if="selectedTeacher.obligation" class="profile-pill profile-pill--role">{{ selectedTeacher.obligation }}</span>
                    <span v-if="selectedTeacher.employment_type" class="profile-pill profile-pill--muted">{{ selectedTeacher.employment_type }}</span>
                  </div>
                </div>
                <button type="button" class="btn-profile-edit" @click="editFromView">
                  <Pencil class="h-4 w-4" />
                  Edit
                </button>
              </div>
            </div>

            <div class="modal-body profile-body">
              <ProfileBlock title="Personal Information" :icon="User">
                <DetailGrid :items="personalDetails" />
              </ProfileBlock>
              <ProfileBlock title="Contact Information" :icon="Phone">
                <DetailGrid :items="contactDetails" />
              </ProfileBlock>
              <ProfileBlock title="Employment Information" :icon="Briefcase">
                <DetailGrid :items="employmentDetails" />
              </ProfileBlock>
              <ProfileBlock title="Academic Qualifications" :icon="GraduationCap">
                <DetailGrid :items="academicDetails" />
              </ProfileBlock>
              <ProfileBlock title="Financial Information" :icon="Wallet">
                <DetailGrid :items="financialDetails" />
              </ProfileBlock>
              <ProfileBlock title="System" :icon="Clock">
                <DetailGrid :items="systemDetails" />
              </ProfileBlock>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-primary" @click="closeViewModal">Close</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Import Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showImportModal" class="modal-overlay" @click.self="closeImportModal">
          <div class="modal-panel" role="dialog" aria-modal="true">
            <div class="modal-header modal-header--compact">
              <div>
                <h3 class="text-xl font-bold">Import Teachers</h3>
                <p class="mt-1 text-sm text-brand-100/80">Upload CSV or paste JSON to bulk-register staff.</p>
              </div>
              <button type="button" class="modal-close" @click="closeImportModal"><X class="h-5 w-5" /></button>
            </div>

            <div class="modal-body space-y-5">
              <div
                class="import-dropzone"
                :class="{ 'import-dropzone--active': importDragOver }"
                @dragover.prevent="importDragOver = true"
                @dragleave.prevent="importDragOver = false"
                @drop.prevent="handleImportDrop"
              >
                <Upload class="h-8 w-8 text-brand-500" />
                <p class="mt-2 text-sm font-medium text-slate-800">Drag &amp; drop CSV here</p>
                <label class="btn-secondary mt-3 cursor-pointer">
                  Browse CSV
                  <input type="file" accept=".csv" class="sr-only" @change="handleImportFile" />
                </label>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                <p class="font-semibold text-slate-800">Expected CSV headers</p>
                <p class="mt-1 font-mono break-all">full_name, email, contact, subject, obligation, department, employment_type, is_active</p>
              </div>
              <div>
                <label class="field-label">Or paste JSON</label>
                <textarea v-model="importData" rows="8" class="input font-mono text-xs" placeholder='[{"full_name":"John Smith","email":"john@school.ac.ke","subject":"Mathematics"}]' />
              </div>
              <div v-if="importError" class="alert alert--error">{{ importError }}</div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeImportModal">Cancel</button>
              <button type="button" class="btn-primary" :disabled="importing" @click="importTeachers">
                {{ importing ? 'Importing...' : 'Import Teachers' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, h, defineComponent, watch } from 'vue';
import { teachersAPI, subjectsNewAPI } from '../services/api';
import TeachersByDepartment from '../components/teachers/TeachersByDepartment.vue';
import TeacherLoginsPanel from '../components/teachers/TeacherLoginsPanel.vue';
import { useRoute } from 'vue-router';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import authStore from '../services/authStore.js';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import {
  List,
  LayoutGrid,
  Eye,
  Pencil,
  Power,
  Trash2,
  Plus,
  RefreshCw,
  Upload,
  Download,
  FileText,
  FileSpreadsheet,
  BarChart3,
  Search,
  X,
  User,
  Phone,
  Briefcase,
  SlidersHorizontal,
  KeyRound,
  Crown,
  GraduationCap,
  Building2,
  Wallet,
  Clock,
  Users,
  CheckCircle2,
  AlertCircle,
  ChevronLeft,
  ChevronRight
} from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';

/* --- Inline section components --- */
const FormSection = defineComponent({
  name: 'FormSection',
  props: { title: String, desc: String, icon: Object },
  setup(props, { slots }) {
    return () =>
      h('section', { class: 'form-section' }, [
        h('div', { class: 'form-section-head' }, [
          h(props.icon, { class: 'h-5 w-5 text-brand-600' }),
          h('div', [
            h('h4', { class: 'form-section-title' }, props.title),
            h('p', { class: 'form-section-desc' }, props.desc)
          ])
        ]),
        h('div', { class: 'mt-4' }, slots.default?.())
      ]);
  }
});

const ProfileBlock = defineComponent({
  name: 'ProfileBlock',
  props: { title: String, icon: Object },
  setup(props, { slots }) {
    return () =>
      h('section', { class: 'profile-block' }, [
        h('div', { class: 'profile-block-head' }, [
          h(props.icon, { class: 'h-4 w-4 text-brand-600' }),
          h('h4', { class: 'profile-block-title' }, props.title)
        ]),
        slots.default?.()
      ]);
  }
});

const formatOtherDuties = (value) => {
  if (!value) return [];

  if (Array.isArray(value)) {
    return value.map((item) => String(item).trim()).filter(Boolean);
  }

  const text = String(value).trim();
  if (!text) return [];

  const normalized = text
    .replace(/\r\n/g, '\n')
    .replace(/;/g, '\n')
    .replace(/\|/g, '\n')
    .replace(/(?:^|\n)\s*(\d+)\.\s*/g, '\n$1. ');

  const parts = normalized
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean)
    .map((item) => item.replace(/^\d+\.\s*/, ''));

  return parts.length ? parts : [text];
};

const DetailGrid = defineComponent({
  name: 'DetailGrid',
  props: { items: { type: Array, default: () => [] } },
  setup(props) {
    return () =>
      h(
        'div',
        { class: 'detail-grid' },
        props.items.map((item) =>
          h('div', { class: 'detail-card', key: item.label }, [
            h(
  'div',
  {
    class:
      'mb-2 text-sm font-extrabold uppercase tracking-wider text-black'
  },
  item.label
),
            item.label === 'Other Duties'
              ? (() => {
                  const duties = formatOtherDuties(item.value);

                  return duties.length
                    ? h(
                        'ol',
                        { class: 'other-duties-list' },
                        duties.map((duty, index) =>
                          h(
                            'li',
                            {
                              key: `${item.label}-${index}`,
                              class: 'other-duty-item'
                            },
                            [
                              h(
                                'span',
                                { class: 'other-duty-number' },
                                `${index + 1}.`
                              ),
                              h(
                                'span',
                                { class: 'other-duty-text' },
                                duty
                              )
                            ]
                          )
                        )
                      )
                    : h('p', { class: 'detail-value' }, '-');
                })()
              : h('p', { class: 'detail-value' }, item.value ?? '-')
          ])
        )
      );
  }
});

const uploadsBase = import.meta.env.DEV
  ? 'http://localhost/accademics/backend/'
  : '/backend/';

const photoUrl = (path) => {
  if (!path) return '';
  if (path.startsWith('http') || path.startsWith('data:')) return path;
  return uploadsBase + String(path).replace(/^\//, '');
};

const canViewAnalytics = computed(() => authStore.canAccess('/teaching-analytics'));

const teachers = ref([]);
const loading = ref(false);
const saving = ref(false);
const importing = ref(false);

const showModal = ref(false);
const showViewModal = ref(false);
const showImportModal = ref(false);
const importDragOver = ref(false);

const editingId = ref(null);
const createdTeacherCode = ref('');
const selectedTeacher = ref({});

const error = ref('');
const importError = ref('');
const importData = ref('');

const filters = ref({
  search: '',
  subject: '',
  department: '',
  employment_type: '',
  obligation: '',
  is_active: ''
});

const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalPages = ref(1);
const totalTeachers = ref(0);

const genderOptions = ['Male', 'Female', 'Other'];
const maritalOptions = ['Single', 'Married', 'Divorced', 'Widowed'];
const defaultEmploymentTypes = ['Full-time', 'Part-time', 'Contract', 'Temporary', 'Intern'];

const normalizeEmploymentType = (value) => {
  if (!value || value === '' || value === null) return null;
  const key = String(value).trim().toLowerCase();

  const mapping = {
    permanent: 'permanent',
    contract: 'contract',
    temporary: 'temporary',
    fulltime: 'full_time',
    'full time': 'full_time',
    'full-time': 'full_time',
    parttime: 'part_time',
    'part time': 'part_time',
    'part-time': 'part_time',
    casual: 'casual',
    intern: 'internship',
    internship: 'internship',
    probation: 'probation'
  };

  return mapping[key] || value;
};

const obligations = [
  'Class Teacher',
  'Subject Teacher',
  'HoD',
  'Deputy Admin',
  'Deputy Academics',
  'Head Teacher'
];

const emptyForm = () => ({
  full_name: '',
  gender: '',
  date_of_birth: '',
  national_id: '',
  passport_photo: '',
  nationality: '',
  marital_status: '',
  religion: '',
  email: '',
  contact: '',
  alternative_phone: '',
  address: '',
  district: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  subject: '',
  second_subject: '',
  obligation: 'Subject Teacher',
  department: '',
  position: '',
  employment_type: '',
  supervisor: '',
  other_duties: '',
  qualification: '',
  university: '',
  year_graduated: '',
  teaching_license_number: '',
  date_joined: '',
  contract_start: '',
  contract_end: '',
  salary: '',
  bank_name: '',
  account_number: '',
  tin_number: '',
  nssf_number: '',
  is_active: true
});

const form = ref(emptyForm());
const allSubjects = ref([]);

const toast = ref({ show: false, type: 'success', message: '' });
// Directory (table) or By department; remembered for this browser.
const views = [
  { value: 'directory', label: 'Directory', icon: List },
  { value: 'department', label: 'By department', icon: LayoutGrid },
  { value: 'logins', label: 'Logins', icon: KeyRound },
];
const VIEW_VALUES = views.map((v) => v.value);
// ?view=logins (e.g. the old Teacher Logins link) wins; otherwise the last view used here.
const route = useRoute();
const view = ref((() => {
  if (VIEW_VALUES.includes(route.query.view)) return route.query.view;
  try {
    const saved = localStorage.getItem('teachers.view');
    return VIEW_VALUES.includes(saved) ? saved : 'directory';
  } catch {
    return 'directory';
  }
})());
watch(view, (v) => { try { localStorage.setItem('teachers.view', v); } catch { /* not available */ } });
let toastTimer = null;
let searchTimer = null;

const hideToast = () => {
  toast.value.show = false;
  toast.value.message = '';
  if (toastTimer) {
    clearTimeout(toastTimer);
    toastTimer = null;
  }
};

// The table row menu (⋮): view, edit, activate/deactivate, delete.
const rowActions = (teacher) => [
  { label: 'View profile', icon: Eye, onClick: () => viewTeacher(teacher) },
  { label: 'Edit', icon: Pencil, onClick: () => editTeacher(teacher) },
  { label: Number(teacher.is_active) === 1 ? 'Deactivate' : 'Activate', icon: Power, onClick: () => toggleTeacherStatus(teacher) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteTeacher(teacher.id ?? teacher.teacher_id) },
];

const notify = (message, type = 'success') => {
  hideToast();
  toast.value = { show: true, type, message };
  toastTimer = setTimeout(hideToast, 3200);
};

const subjects = computed(() => {
  const values = allSubjects.value
    .map((s) => s.subject_name ?? s.name ?? s.subject ?? '')
    .filter(Boolean);
  return [...new Set(values)].sort();
});

const departmentOptions = [
  'KISW',
  'ENG',
  'LIT',
  'IRE',
  'LUGANDA',
  'HIST',
  'GEOG',
  'BIO',
  'CHEM',
  'PHY',
  'MTC',
  'ICT',
  'P.E',
  'CRE',
  'F/ART',
  'TD',
  'ECON',
  'SUB-MTC',
  'G.P',
  'ENT'
];

const employmentTypeOptions = computed(() => {
  const fromData = teachers.value.map((t) => t.employment_type).filter(Boolean);
  return [...new Set([...defaultEmploymentTypes, ...fromData])].sort();
});

const displayedTeachers = computed(() => {
  let list = [...teachers.value];
  if (filters.value.department) {
    list = list.filter((t) => (t.department || '') === filters.value.department);
  }
  if (filters.value.employment_type) {
    list = list.filter((t) => (t.employment_type || '') === filters.value.employment_type);
  }
  return list;
});

// Whole-directory counts (the table only holds one page).
const summary = ref({ total: 0, active: 0, inactive: 0, obligations: [] });
const loadSummary = async () => {
  try {
    const res = await teachersAPI.summary();
    if (res?.success) summary.value = res.data;
  } catch { /* the cards keep their last values */ }
};
const heads = computed(() => summary.value.obligations
  .filter((o) => /hod|head/i.test(o.obligation)).reduce((t, o) => t + o.count, 0));
// A card with `filter` narrows the table to that status when clicked.
const statCards = computed(() => [
  { label: 'Teachers', value: summary.value.total, icon: Users, filter: '' },
  { label: 'Active', value: summary.value.active, icon: CheckCircle2, filter: '1' },
  { label: 'Inactive', value: summary.value.inactive, icon: AlertCircle, tone: 'gray', filter: '0' },
  { label: 'Heads & HoDs', value: heads.value, icon: Crown, tone: 'amber' },
].map((c) => (c.filter === undefined ? c : { ...c, onClick: () => setStatus(c.filter), active: filters.value.is_active === c.filter })));
const setStatus = (value) => {
  filters.value.is_active = value;
  resetAndLoad();
};

const showFilters = ref(false);
const activeFilterCount = computed(() => ['subject', 'department', 'employment_type', 'obligation', 'is_active']
  .filter((k) => String(filters.value[k] ?? '') !== '').length);
const resetAndLoad = () => {
  currentPage.value = 1;
  loadTeachers();
};
const reloadAll = () => {
  loadTeachers();
};
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
const obligationStyle = (o) => {
  const v = String(o || '').toLowerCase();
  if (v.includes('head') || v === 'hod') return 'bg-amber-50 text-amber-800 ring-1 ring-amber-200';
  if (v.includes('deputy')) return 'bg-violet-50 text-violet-800 ring-1 ring-violet-200';
  if (v.includes('class')) return 'bg-sky-50 text-sky-800 ring-1 ring-sky-200';
  return 'bg-gray-100 text-gray-700';
};
// Pages around the current one: 1 … 4 5 6 … 12
const pageWindow = computed(() => {
  const n = totalPages.value;
  const c = currentPage.value;
  const pages = new Set([1, n, c - 1, c, c + 1].filter((p) => p >= 1 && p <= n));
  const sorted = [...pages].sort((a, b) => a - b);
  const out = [];
  sorted.forEach((p, i) => {
    if (i && p - sorted[i - 1] > 1) out.push('…');
    out.push(p);
  });
  return out;
});

const formatDate = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
};

const formatDateShort = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString();
};

const formatMoney = (value) => {
  if (value === null || value === undefined || value === '') return '-';
  const num = Number(value);
  if (Number.isNaN(num)) return String(value);
  return num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};

const teacherInitials = (name) => {
  if (!name) return '?';
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('');
};

const mapTeacherToForm = (teacher) => ({
  full_name: teacher.full_name || '',
  gender: teacher.gender || '',
  date_of_birth: teacher.date_of_birth?.slice?.(0, 10) || teacher.date_of_birth || '',
  national_id: teacher.national_id || '',
  passport_photo: teacher.passport_photo || '',
  nationality: teacher.nationality || '',
  marital_status: teacher.marital_status || '',
  religion: teacher.religion || '',
  email: teacher.email || '',
  contact: teacher.contact || teacher.phone_number || '',
  alternative_phone: teacher.alternative_phone || '',
  address: teacher.address || '',
  district: teacher.district || '',
  emergency_contact_name: teacher.emergency_contact_name || '',
  emergency_contact_phone: teacher.emergency_contact_phone || '',
  subject: teacher.subject || teacher.main_subject || '',
  second_subject: teacher.second_subject || '',
  obligation: teacher.obligation || 'Subject Teacher',
  department: teacher.department || '',
  position: teacher.position || '',
  employment_type: teacher.employment_type || '',
  supervisor: teacher.supervisor || '',
  other_duties: teacher.other_duties || teacher.specialization || '',
  qualification: teacher.qualification || '',
  university: teacher.university || '',
  year_graduated: teacher.year_graduated || '',
  teaching_license_number: teacher.teaching_license_number || '',
  date_joined: teacher.date_joined?.slice?.(0, 10) || teacher.date_joined || '',
  contract_start: teacher.contract_start?.slice?.(0, 10) || teacher.contract_start || '',
  contract_end: teacher.contract_end?.slice?.(0, 10) || teacher.contract_end || '',
  salary: teacher.salary ?? '',
  bank_name: teacher.bank_name || '',
  account_number: teacher.account_number || '',
  tin_number: teacher.tin_number || '',
  nssf_number: teacher.nssf_number || '',
  is_active: Number(teacher.is_active) === 1
});

const buildPayload = () => {
  const f = form.value;
  return {
    full_name: f.full_name,
    gender: f.gender || null,
    date_of_birth: f.date_of_birth || null,
    national_id: f.national_id || null,
    passport_photo: f.passport_photo || null,
    nationality: f.nationality || null,
    marital_status: f.marital_status || null,
    religion: f.religion || null,
    email: f.email,
    contact: f.contact || null,
    alternative_phone: f.alternative_phone || null,
    address: f.address || null,
    district: f.district || null,
    emergency_contact_name: f.emergency_contact_name || null,
    emergency_contact_phone: f.emergency_contact_phone || null,
    subject: f.subject,
    second_subject: f.second_subject || null,
    obligation: f.obligation || 'Subject Teacher',
    department: f.department || null,
    position: f.position || null,
    employment_type: f.employment_type ? normalizeEmploymentType(f.employment_type) : null,
    supervisor: f.supervisor || null,
    other_duties: f.other_duties || null,
    qualification: f.qualification || null,
    university: f.university || null,
    year_graduated: f.year_graduated || null,
    teaching_license_number: f.teaching_license_number || null,
    date_joined: f.date_joined || null,
    contract_start: f.contract_start || null,
    contract_end: f.contract_end || null,
    salary: f.salary === '' || f.salary === null ? null : Number(f.salary),
    bank_name: f.bank_name || null,
    account_number: f.account_number || null,
    tin_number: f.tin_number || null,
    nssf_number: f.nssf_number || null,
    is_active: f.is_active ? 1 : 0
  };
};

const detail = (label, value) => ({ label, value: value || '-' });

const personalDetails = computed(() => {
  const t = selectedTeacher.value;
  return [
    detail('Full Name', t.full_name),
    detail('Gender', t.gender),
    detail('Date of Birth', formatDateShort(t.date_of_birth)),
    detail('National ID', t.national_id),
    detail('Nationality', t.nationality),
    detail('Marital Status', t.marital_status),
    detail('Religion', t.religion),
  
  ];
});

const contactDetails = computed(() => {
  const t = selectedTeacher.value;
  return [
    detail('Email', t.email),
    detail('Contact', t.contact || t.phone_number),
    detail('Alternative Phone', t.alternative_phone),
    detail('Address', t.address),
    detail('District', t.district),
    detail('Emergency Name', t.emergency_contact_name),
    detail('Emergency Phone', t.emergency_contact_phone)
  ];
});

const employmentDetails = computed(() => {
  const t = selectedTeacher.value;
  return [
    detail('Subject', t.subject),
    detail('Second Subject', t.second_subject),
    detail('Obligation', t.obligation),
    detail('Department', t.department),
    detail('Position', t.position),
    detail('Employment Type', t.employment_type),
    detail('Supervisor', t.supervisor),
    detail('Other Duties', t.other_duties || t.specialization),
    detail('Date Joined', formatDateShort(t.date_joined)),
    detail('Contract Start', formatDateShort(t.contract_start)),
    detail('Contract End', formatDateShort(t.contract_end))
  ];
});

const academicDetails = computed(() => {
  const t = selectedTeacher.value;
  return [
    detail('Qualification', t.qualification),
    detail('University', t.university),
    detail('Year Graduated', t.year_graduated),
    detail('Teaching License', t.teaching_license_number)
  ];
});

const financialDetails = computed(() => {
  const t = selectedTeacher.value;
  return [
    detail('Salary', formatMoney(t.salary)),
    detail('Bank Name', t.bank_name),
    detail('Account Number', t.account_number),
    detail('TIN Number', t.tin_number),
    detail('NSSF Number', t.nssf_number)
  ];
});

const systemDetails = computed(() => {
  const t = selectedTeacher.value;
  return [
    detail('Teacher Code', t.teacher_code),
    detail('Created At', formatDate(t.created_at)),
    detail('Updated At', formatDate(t.updated_at))
  ];
});

const exportHeaders = () => [
  'Code',
  'Full Name',
  'Gender',
  'Contact',
  'Email',
  'Subject',
  'Department',
  'Position',
  'Employment Type',
  'Obligation',
  'Status',
  'Date Joined'
];

const exportRow = (t) => [
  t.teacher_code,
  t.full_name,
  t.gender,
  t.contact || t.phone_number,
  t.email,
  t.subject,
  t.department,
  t.position,
  t.employment_type,
  t.obligation,
  Number(t.is_active) === 1 ? 'Active' : 'Inactive',
  formatDateShort(t.date_joined)
];

const escapeCsv = (val) => `"${String(val ?? '').replace(/"/g, '""')}"`;

const loadSubjects = async () => {
  try {
    const response = await subjectsNewAPI.getAll();
    allSubjects.value = Array.isArray(response.data) ? response.data : [];
  } catch (err) {
    console.error('Error loading subjects:', err);
  }
};

const loadTeachers = async () => {
  loading.value = true;
  loadSummary();
  error.value = '';
  try {
    const result = await teachersAPI.getAll({
      search: filters.value.search,
      obligation: filters.value.obligation,
      subject: filters.value.subject,
      is_active: filters.value.is_active,
      page: currentPage.value,
      limit: itemsPerPage.value
    });
    if (result.success) {
      if (result.data && result.data.teachers && result.data.pagination) {
        teachers.value = Array.isArray(result.data.teachers) ? result.data.teachers : [];
        totalTeachers.value = result.data.pagination.total || 0;
        totalPages.value = result.data.pagination.totalPages || 1;
      } else {
        teachers.value = Array.isArray(result.data) ? result.data : [];
        totalTeachers.value = teachers.value.length;
        totalPages.value = 1;
      }
    } else {
      teachers.value = [];
      totalTeachers.value = 0;
      totalPages.value = 1;
      notify(result.message || 'Failed to load teachers', 'error');
    }
  } catch (err) {
    teachers.value = [];
    totalTeachers.value = 0;
    totalPages.value = 1;
    notify(err?.response?.data?.message || 'Failed to load teachers', 'error');
  } finally {
    loading.value = false;
  }
};

const applyLocalFilters = () => {
  /* department & employment_type are client-side via displayedTeachers */
};


const debouncedLoad = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(resetAndLoad, 300);
};

const clearFilters = () => {
  filters.value = {
    search: '',
    subject: '',
    department: '',
    employment_type: '',
    obligation: '',
    is_active: ''
  };
  currentPage.value = 1;
  loadTeachers();
};

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    loadTeachers();
  }
};

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++;
    loadTeachers();
  }
};

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--;
    loadTeachers();
  }
};

const openAddModal = () => {
  editingId.value = null;
  createdTeacherCode.value = '';
  error.value = '';
  form.value = emptyForm();
  showModal.value = true;
};

const editTeacher = (teacher) => {
  editingId.value = teacher.id ?? teacher.teacher_id ?? null;
  createdTeacherCode.value = '';
  error.value = '';
  form.value = mapTeacherToForm(teacher);
  showModal.value = true;
};

const viewTeacher = (teacher) => {
  selectedTeacher.value = { ...teacher };
  showViewModal.value = true;
};

const editFromView = () => {
  const t = { ...selectedTeacher.value };
  closeViewModal();
  editTeacher(t);
};

const closeViewModal = () => {
  showViewModal.value = false;
  selectedTeacher.value = {};
};

const closeModal = () => {
  showModal.value = false;
  error.value = '';
  createdTeacherCode.value = '';
};

const handlePhotoUpload = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  if (file.size > 2 * 1024 * 1024) {
    notify('Image must be 2MB or smaller.', 'error');
    return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    form.value.passport_photo = reader.result;
  };
  reader.readAsDataURL(file);
};

const clearPhoto = () => {
  form.value.passport_photo = '';
};

const saveTeacher = async () => {
  saving.value = true;
  error.value = '';
  createdTeacherCode.value = '';
  const isEdit = Boolean(editingId.value);
  try {
    const payload = buildPayload();
    const result = isEdit
      ? await teachersAPI.update({ ...payload, id: editingId.value })
      : await teachersAPI.create(payload);

    if (result.success) {
      if (!isEdit && result.data?.teacher_code) {
        createdTeacherCode.value = result.data.teacher_code;
      }
      await loadTeachers();
      closeModal();
      notify(isEdit ? 'Teacher updated successfully.' : 'Teacher added successfully.', 'success');
    } else {
      error.value = result.message || 'Failed to save teacher';
      notify(error.value, 'error');
    }
  } catch (err) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to save teacher';
    notify(error.value, 'error');
  } finally {
    saving.value = false;
  }
};

const toggleTeacherStatus = async (teacher) => {
  const teacherId = teacher.id ?? teacher.teacher_id;
  if (!teacherId) {
    notify('Teacher ID not found.', 'error');
    return;
  }
  const nextStatus = Number(teacher.is_active) === 1 ? 0 : 1;
  try {
    const result = await teachersAPI.toggleStatus(teacherId, nextStatus);
    if (result.success) {
      await loadTeachers();
      notify(nextStatus === 1 ? 'Teacher activated.' : 'Teacher deactivated.', 'success');
    } else {
      notify(result.message || 'Failed to update status', 'error');
    }
  } catch (err) {
    notify(err?.response?.data?.message || 'Failed to update status', 'error');
  }
};

const deleteTeacher = async (id) => {
  if (!id) {
    notify('Teacher ID not found.', 'error');
    return;
  }
  if (!confirm('Delete this teacher record? This cannot be undone.')) return;
  try {
    const result = await teachersAPI.delete(id);
    if (result.success) {
      await loadTeachers();
      notify('Teacher deleted successfully.', 'success');
    } else {
      notify(result.message || 'Failed to delete teacher', 'error');
    }
  } catch (err) {
    notify(err?.response?.data?.message || 'Failed to delete teacher', 'error');
  }
};

const openImportModal = () => {
  importData.value = '';
  importError.value = '';
  showImportModal.value = true;
};

const closeImportModal = () => {
  showImportModal.value = false;
  importError.value = '';
};

const handleImportFile = async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  try {
    importData.value = csvToJson(await file.text());
  } catch {
    importError.value = 'Could not read file';
    notify(importError.value, 'error');
  }
};

const handleImportDrop = async (event) => {
  importDragOver.value = false;
  const file = event.dataTransfer?.files?.[0];
  if (!file) return;
  try {
    importData.value = csvToJson(await file.text());
  } catch {
    importError.value = 'Could not read file';
    notify(importError.value, 'error');
  }
};

const csvToJson = (csvText) => {
  const lines = csvText.split('\n').map((l) => l.trim()).filter(Boolean);
  if (lines.length < 2) return '[]';
  const headers = lines[0].split(',').map((h) => h.trim());
  const rows = lines.slice(1).map((line) => {
    const values = line.split(',').map((v) => v.trim());
    const row = {};
    headers.forEach((header, i) => {
      row[header] = values[i] ?? '';
    });
    return row;
  });
  return JSON.stringify(rows, null, 2);
};

const importTeachers = async () => {
  importing.value = true;
  importError.value = '';
  try {
    const parsed = JSON.parse(importData.value);
    if (!Array.isArray(parsed)) throw new Error('Import data must be a JSON array');
    const cleaned = parsed.map((item) => ({
      full_name: item.full_name || '',
      email: item.email || '',
      contact: item.contact || '',
      subject: item.subject || '',
      obligation: item.obligation || 'Subject Teacher',
      department: item.department || '',
      employment_type: item.employment_type || '',
      is_active: item.is_active ?? 1
    }));
    const result = await teachersAPI.import(cleaned);
    if (result.success) {
      closeImportModal();
      await loadTeachers();
      notify(`Imported ${result.data?.imported ?? 0}. Skipped ${result.data?.skipped ?? 0}.`, 'success');
    } else {
      importError.value = result.message || 'Import failed';
      notify(importError.value, 'error');
    }
  } catch (err) {
    importError.value = err?.message || 'Invalid import format';
    notify(importError.value, 'error');
  } finally {
    importing.value = false;
  }
};

const downloadPDF = () => {
  const doc = new jsPDF();
  doc.setFontSize(18);
  doc.text('Teachers Directory', 14, 22);
  doc.setFontSize(10);
  doc.setTextColor(100);
  const filterInfo = [
    filters.value.subject && `Subject: ${filters.value.subject}`,
    filters.value.department && `Department: ${filters.value.department}`,
    filters.value.employment_type && `Type: ${filters.value.employment_type}`,
    filters.value.obligation && `Obligation: ${filters.value.obligation}`,
    filters.value.is_active !== '' && `Status: ${filters.value.is_active === '1' ? 'Active' : 'Inactive'}`,
    filters.value.search && `Search: ${filters.value.search}`
  ]
    .filter(Boolean)
    .join(' | ');
  doc.text(filterInfo || 'All Teachers', 14, 30);
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);
  autoTable(doc, {
    head: [exportHeaders()],
    body: displayedTeachers.value.map(exportRow),
    startY: 42,
    styles: { fontSize: 7, cellPadding: 2 },
    headStyles: { fillColor: [5, 150, 105], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [248, 250, 252] }
  });
  doc.save(`teachers-${new Date().toISOString().split('T')[0]}.pdf`);
};

const downloadCSV = () => {
  const headers = exportHeaders();
  const rows = displayedTeachers.value.map(exportRow);
  const csv = [headers.join(','), ...rows.map((r) => r.map(escapeCsv).join(','))].join('\n');
  downloadBlob(csv, `teachers-${dateStamp()}.csv`, 'text/csv;charset=utf-8;');
};

const downloadExcel = () => {
  const headers = exportHeaders();
  const rows = displayedTeachers.value.map(exportRow);
  const tsv = [headers.join('\t'), ...rows.map((r) => r.join('\t'))].join('\n');
  const bom = '\uFEFF';
  downloadBlob(bom + tsv, `teachers-${dateStamp()}.xls`, 'application/vnd.ms-excel;charset=utf-8;');
};

const dateStamp = () => new Date().toISOString().split('T')[0];

const downloadBlob = (content, filename, mime) => {
  const blob = new Blob([content], { type: mime });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
};

onMounted(() => {
  loadSubjects();
  loadTeachers();
});

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer);
  if (toastTimer) clearTimeout(toastTimer);
});
</script>
<style scoped>
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.page-btn { @apply inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-gray-200 px-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40; }
.page-btn--on { @apply border-brand-600 bg-brand-600 text-white hover:bg-brand-600; }
.teachers-page {
  --blue: #059669;
  --blue-dark: #064e3b;
}

/* Header buttons */
.btn-header {
  @apply inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold transition-all sm:text-sm;
}
.btn-header--ghost {
  @apply border-2 border-gray-300 bg-white text-gray-700 hover:bg-gray-50 disabled:opacity-50;
}
.btn-header--emerald {
  @apply bg-emerald-500 text-white shadow-lg shadow-emerald-500/25 hover:bg-emerald-600;
}
.btn-header--primary {
  @apply bg-brand-500 text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600;
}

.stat-card {
  @apply rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md;
}

.field-label {
  @apply mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500;
}

.input {
  @apply w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-100;
}

/* Filters export */
.btn-export {
  @apply inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm;
}
.btn-export--pdf { @apply bg-emerald-600 text-white hover:bg-emerald-700; }
.btn-export--excel { @apply bg-brand-600 text-white hover:bg-brand-700; }
.btn-export--csv { @apply bg-slate-700 text-white hover:bg-slate-800; }
.btn-export--analytics { @apply border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100; }
.btn-export--ghost { @apply border border-slate-200 text-slate-700 hover:bg-slate-50; }
.btn-export--primary { @apply bg-brand-600 text-white hover:bg-brand-700; }

/* Table */
.teachers-table thead {
  @apply sticky top-0 z-10 bg-slate-50 shadow-sm;
}
.teachers-table thead th {
  @apply whitespace-nowrap px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500;
}
.teachers-table tbody td {
  @apply px-4 py-3 text-sm text-slate-700;
}
.table-row {
  @apply border-t border-slate-100 transition hover:bg-slate-50/80;
}
.code-badge {
  @apply inline-flex rounded-lg bg-brand-50 px-2 py-1 text-xs font-bold text-brand-700;
}
.badge {
  @apply inline-flex rounded-full px-2.5 py-1 text-xs font-semibold;
}
.badge--active { @apply bg-emerald-50 text-emerald-700; }
.badge--inactive { @apply bg-rose-50 text-rose-700; }
.badge--violet { @apply bg-brand-50 text-brand-700; }

.action-btn {
  @apply inline-flex h-8 w-8 items-center justify-center rounded-lg border transition;
}
.action-btn--view { @apply border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100; }
.action-btn--edit { @apply border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100; }
.action-btn--warn { @apply border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100; }
.action-btn--success { @apply border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100; }
.action-btn--danger { @apply border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100; }

/* Loading */
.loading-dot {
  @apply h-2 w-2 rounded-full bg-brand-500;
  animation: pulse-dot 1s ease-in-out infinite;
}
.loading-dot--lg { @apply h-2.5 w-2.5; }
.animation-delay-150 { animation-delay: 0.15s; }
.animation-delay-300 { animation-delay: 0.3s; }
@keyframes pulse-dot {
  0%, 100% { opacity: 0.35; transform: scale(0.85); }
  50% { opacity: 1; transform: scale(1); }
}

/* Modals */
.modal-overlay {
  @apply fixed inset-0 z-[150] flex items-center justify-center bg-slate-900/55 p-3 backdrop-blur-md sm:p-4;
}
.modal-panel {
  @apply flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl;
}
.modal-panel--form { @apply max-w-3xl; }
.modal-panel--profile { @apply max-w-4xl; }
.modal-header {
  @apply flex shrink-0 items-start justify-between gap-4 bg-slate-900 px-5 py-5 text-white;
}
.modal-header--compact { @apply bg-slate-900; }
.modal-close {
  @apply rounded-xl p-2 text-white/70 transition hover:bg-white/10 hover:text-white;
}
.modal-close--light { @apply absolute right-4 top-4; }
.modal-body {
  @apply flex-1 overflow-y-auto px-5 py-5;
}
.modal-footer {
  @apply flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-5 py-4;
}
.btn-primary {
  @apply inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/20 transition hover:bg-brand-700 disabled:opacity-50;
}
.btn-secondary {
  @apply inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50;
}

/* Form sections */
.form-section {
  @apply rounded-2xl border border-slate-100 bg-slate-50/50 p-4;
}
.form-section + .form-section { @apply mt-4; }
.form-section-head {
  @apply flex items-center gap-3;
}
.form-section-title {
  @apply text-sm font-bold text-slate-900;
}
.form-section-desc {
  @apply text-xs text-slate-500;
}
.form-grid {
  @apply grid gap-4 sm:grid-cols-2;
}

.photo-upload {
  @apply flex flex-wrap items-center gap-4;
}
.photo-drop {
  @apply flex cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-200 bg-white px-6 py-5 transition hover:border-brand-400 hover:bg-brand-50/30;
}
.photo-preview {
  @apply flex flex-col items-center gap-2;
}
.photo-remove {
  @apply text-xs font-medium text-rose-600 hover:underline;
}

.status-toggle {
  @apply flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition;
}
.status-toggle--on { @apply border-emerald-200 bg-emerald-50/50; }
.status-toggle-track {
  @apply relative h-7 w-12 shrink-0 rounded-full bg-slate-300 transition;
}
.status-toggle--on .status-toggle-track { @apply bg-emerald-500; }
.status-toggle-thumb {
  @apply absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition;
}
.status-toggle--on .status-toggle-thumb { @apply translate-x-5; }

.alert {
  @apply mt-4 rounded-2xl px-4 py-3 text-sm font-medium;
}
.alert--success { @apply border border-emerald-200 bg-emerald-50 text-emerald-800; }
.alert--error { @apply border border-rose-200 bg-rose-50 text-rose-800; }

/* Profile modal */
.profile-hero {
  @apply relative shrink-0 bg-slate-900 px-5 pb-6 pt-5;
}
.profile-avatar-lg {
  @apply flex h-28 w-28 items-center justify-center rounded-2xl bg-white/15 text-3xl font-bold text-white ring-4 ring-white/20;
}
.btn-profile-edit {
  @apply inline-flex items-center gap-2 self-start rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20;
}
.profile-pill {
  @apply inline-flex rounded-full px-3 py-1 text-xs font-semibold;
}
.profile-pill--active { @apply bg-emerald-400/20 text-emerald-100; }
.profile-pill--inactive { @apply bg-rose-400/20 text-rose-100; }
.profile-pill--role { @apply bg-brand-400/20 text-brand-100; }
.profile-pill--muted { @apply bg-white/10 text-brand-100; }
.profile-body { @apply space-y-5; }
.profile-block {
  @apply rounded-2xl border border-slate-100 bg-slate-50/60 p-4;
}
.profile-block-head {
  @apply mb-3 flex items-center gap-2;
}
.other-duty-number {
  @apply min-w-[24px] font-extrabold text-black;
}

/* Detail cards */
.detail-grid {
  @apply grid gap-3 sm:grid-cols-2 lg:grid-cols-3;
}
.detail-card {
  @apply rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition;
}
.detail-card:hover {
  @apply border-brand-200 shadow-md;
}
.detail-heading {
  color: #000000;
  font-weight: 900;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 0.5rem;
  display: block;
}
.detail-value {
  @apply text-sm font-semibold leading-relaxed text-slate-800 break-words;
}

/* Other Duties */
.other-duties-list {
  @apply mt-2 space-y-3;
  list-style: none;
  padding-left: 0;
  margin-left: 0;
}
.other-duty-item {
  @apply flex items-start gap-3 rounded-lg bg-brand-50 px-3 py-2 border border-brand-100;
}
.other-duty-number {
  @apply min-w-[24px] font-extrabold text-brand-700;
}
.other-duty-text {
  @apply flex-1 text-slate-800 leading-relaxed;
}

.import-dropzone {
  @apply flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 py-10 transition;
}
.import-dropzone--active {
  @apply border-brand-400 bg-brand-50;
}

/* Pagination */
.pagination-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #64748b;
  background-color: white;
  border: 1px solid #e2e8f0;
  transition: all 0.2s;
}
.pagination-btn:hover:not(:disabled) {
  background-color: #f1f5f9;
  color: #059669;
  border-color: #cbd5e1;
}
.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.pagination-page-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.25rem;
  height: 2.25rem;
  padding: 0 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #64748b;
  background-color: white;
  border: 1px solid #e2e8f0;
  transition: all 0.2s;
}
.pagination-page-btn:hover {
  background-color: #f1f5f9;
  color: #059669;
  border-color: #cbd5e1;
}
.pagination-page-btn--active {
  background-color: #059669;
  color: white;
  border-color: #059669;
}
.pagination-page-btn--active:hover {
  background-color: #047857;
}

/* Transitions */
.toast-enter-active,
.toast-leave-active,
.modal-enter-active,
.modal-leave-active {
  transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to,
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.98);
}
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .tool-btn {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .tool-btn:hover {
  background-color: var(--d-sunken);
}
html.dark .filter-label {
  color: var(--d-muted);
}
html.dark .page-btn {
  border-color: var(--d-line);
  color: var(--d-text-2);
}
html.dark .page-btn:hover {
  background-color: var(--d-sunken);
}
html.dark .btn-header--ghost {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .btn-header--ghost:hover {
  background-color: var(--d-sunken);
}
html.dark .stat-card {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .field-label {
  color: var(--d-muted);
}
html.dark .input {
  border-color: var(--d-line);
  background-color: var(--d-surface);
  color: var(--d-text);
}
html.dark .btn-export--analytics {
  border-color: rgba(34, 197, 94, 0.3);
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
}
html.dark .btn-export--analytics:hover {
  background-color: rgba(34, 197, 94, 0.2);
}
html.dark .btn-export--ghost {
  border-color: var(--d-line);
  color: var(--d-text-2);
}
html.dark .btn-export--ghost:hover {
  background-color: var(--d-sunken);
}
html.dark .teachers-table thead {
  background-color: var(--d-sunken);
}
html.dark .teachers-table thead th {
  color: var(--d-muted);
}
html.dark .teachers-table tbody td {
  color: var(--d-text-2);
}
html.dark .table-row {
  border-color: var(--d-line);
}
html.dark .table-row:hover {
  background-color: var(--d-sunken);
}
html.dark .code-badge {
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
}
html.dark .badge--active {
  background-color: rgba(16, 185, 129, 0.12);
  color: #6ee7b7;
}
html.dark .badge--inactive {
  background-color: rgba(244, 63, 94, 0.12);
  color: #fda4af;
}
html.dark .badge--violet {
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
}
html.dark .action-btn--view {
  border-color: rgba(34, 197, 94, 0.3);
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
}
html.dark .action-btn--view:hover {
  background-color: rgba(34, 197, 94, 0.2);
}
html.dark .action-btn--edit {
  border-color: rgba(34, 197, 94, 0.3);
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
}
html.dark .action-btn--edit:hover {
  background-color: rgba(34, 197, 94, 0.2);
}
html.dark .action-btn--warn {
  border-color: rgba(245, 158, 11, 0.3);
  background-color: rgba(245, 158, 11, 0.12);
  color: #fcd34d;
}
html.dark .action-btn--warn:hover {
  background-color: rgba(245, 158, 11, 0.2);
}
html.dark .action-btn--success {
  border-color: rgba(16, 185, 129, 0.3);
  background-color: rgba(16, 185, 129, 0.12);
  color: #6ee7b7;
}
html.dark .action-btn--success:hover {
  background-color: rgba(16, 185, 129, 0.2);
}
html.dark .action-btn--danger {
  border-color: rgba(244, 63, 94, 0.3);
  background-color: rgba(244, 63, 94, 0.12);
  color: #fda4af;
}
html.dark .action-btn--danger:hover {
  background-color: rgba(244, 63, 94, 0.2);
}
html.dark .modal-panel {
  background-color: var(--d-surface);
}
html.dark .modal-close:hover {
  background-color: var(--d-surface);
}
html.dark .modal-footer {
  border-color: var(--d-line);
  background-color: var(--d-sunken);
}
html.dark .btn-secondary {
  border-color: var(--d-line);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .btn-secondary:hover {
  background-color: var(--d-sunken);
}
html.dark .form-section {
  border-color: var(--d-line);
  background-color: var(--d-sunken);
}
html.dark .form-section-title {
  color: var(--d-text);
}
html.dark .form-section-desc {
  color: var(--d-muted);
}
html.dark .photo-drop {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .photo-drop:hover {
  background-color: rgba(34, 197, 94, 0.12);
}
html.dark .photo-remove {
  color: #fda4af;
}
html.dark .status-toggle {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .status-toggle--on {
  border-color: rgba(16, 185, 129, 0.3);
  background-color: rgba(16, 185, 129, 0.12);
}
html.dark .status-toggle-track {
  background-color: var(--d-line-strong);
}
html.dark .status-toggle-thumb {
  background-color: var(--d-surface);
}
html.dark .alert--success {
  border-color: rgba(16, 185, 129, 0.3);
  background-color: rgba(16, 185, 129, 0.12);
  color: #6ee7b7;
}
html.dark .alert--error {
  border-color: rgba(244, 63, 94, 0.3);
  background-color: rgba(244, 63, 94, 0.12);
  color: #fda4af;
}
html.dark .profile-avatar-lg {
  background-color: var(--d-surface);
}
html.dark .btn-profile-edit {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .btn-profile-edit:hover {
  background-color: var(--d-surface);
}
html.dark .profile-pill--muted {
  background-color: var(--d-surface);
}
html.dark .profile-block {
  border-color: var(--d-line);
  background-color: var(--d-sunken);
}
html.dark .other-duty-number {
  color: var(--d-text);
}
html.dark .detail-card {
  border-color: var(--d-line);
  background-color: var(--d-surface);
}
html.dark .detail-card:hover {
  border-color: rgba(34, 197, 94, 0.3);
}
html.dark .detail-heading {
  color: var(--d-text);
}
html.dark .detail-value {
  color: var(--d-text);
}
html.dark .other-duty-item {
  background-color: rgba(34, 197, 94, 0.12);
  border-color: rgba(34, 197, 94, 0.3);
}
html.dark .other-duty-number {
  color: #86efac;
}
html.dark .other-duty-text {
  color: var(--d-text);
}
html.dark .import-dropzone {
  border-color: var(--d-line);
  background-color: var(--d-sunken);
}
html.dark .import-dropzone--active {
  background-color: rgba(34, 197, 94, 0.12);
}
html.dark .pagination-btn {
  background-color: var(--d-surface);
  border-color: var(--d-line);
}
html.dark .pagination-btn:hover:not(:disabled) {
  background-color: var(--d-surface);
  color: var(--d-text-2);
  border-color: var(--d-line);
}
html.dark .pagination-page-btn {
  background-color: var(--d-surface);
  border-color: var(--d-line);
}
html.dark .pagination-page-btn:hover {
  background-color: var(--d-surface);
  color: var(--d-text-2);
  border-color: var(--d-line);
}
</style>
