<template>
  <div class="teachers-page space-y-5 pb-8">
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
    <header class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-blue-950 to-blue-800 p-5 text-white shadow-xl shadow-blue-900/20 sm:p-6">
      <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-blue-200/90">HR · Teaching Staff</p>
          <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Teachers Directory</h1>
          <p class="mt-2 max-w-2xl text-sm leading-relaxed text-blue-100/80">
            Manage teacher profiles, employment records, qualifications, and status — premium HR dashboard experience.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn-header btn-header--ghost"
            :disabled="loading"
            @click="reloadTeachers"
          >
            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
            Refresh
          </button>
          <button type="button" class="btn-header btn-header--emerald" @click="openImportModal">
            <Upload class="h-4 w-4" />
            Import
          </button>
          <button type="button" class="btn-header btn-header--primary" @click="openAddModal">
            <Plus class="h-4 w-4" />
            Add Teacher
          </button>
        </div>
      </div>
    </header>

    <!-- Stats -->
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="card in statCards" :key="card.label" class="stat-card">
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium text-slate-500">{{ card.label }}</p>
          <component :is="card.icon" class="h-5 w-5 text-blue-500/70" />
        </div>
        <p class="mt-2 text-2xl font-bold" :class="card.color">{{ card.value }}</p>
      </div>
    </div>

    <!-- Filters -->
    <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm">
      <div class="mb-4 flex items-center gap-2">
        <Search class="h-4 w-4 text-blue-600" />
        <h2 class="text-sm font-semibold text-slate-800">Filters &amp; Export</h2>
      </div>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <div class="2xl:col-span-2">
          <label class="field-label">Search</label>
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="filters.search"
              type="text"
              placeholder="Code, name, email, subject, department..."
              class="input pl-10"
              @input="debouncedLoad"
            />
          </div>
        </div>
        <div>
          <label class="field-label">Subject</label>
          <select v-model="filters.subject" class="input" @change="loadTeachers">
            <option value="">All subjects</option>
            <option v-for="item in subjects" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>
        <div>
          <label class="field-label">Department</label>
          <select v-model="filters.department" class="input" @change="applyLocalFilters">
            <option value="">All departments</option>
            <option v-for="item in departmentOptions" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>
        <div>
          <label class="field-label">Employment Type</label>
          <select v-model="filters.employment_type" class="input" @change="applyLocalFilters">
            <option value="">All types</option>
            <option v-for="item in employmentTypeOptions" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>
        <div>
          <label class="field-label">Obligation</label>
          <select v-model="filters.obligation" class="input" @change="loadTeachers">
            <option value="">All obligations</option>
            <option v-for="item in obligations" :key="item" :value="item">{{ item }}</option>
          </select>
        </div>
        <div>
          <label class="field-label">Status</label>
          <select v-model="filters.is_active" class="input" @change="loadTeachers">
            <option value="">All status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>

      <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
        <button type="button" class="btn-export btn-export--pdf" :disabled="!displayedTeachers.length || loading" @click="downloadPDF">
          <FileText class="h-4 w-4" />
          PDF
        </button>
        <button type="button" class="btn-export btn-export--excel" :disabled="!displayedTeachers.length || loading" @click="downloadExcel">
          <FileSpreadsheet class="h-4 w-4" />
          Excel
        </button>
        <button type="button" class="btn-export btn-export--csv" :disabled="!displayedTeachers.length || loading" @click="downloadCSV">
          <Download class="h-4 w-4" />
          CSV
        </button>
        <router-link
          v-if="canViewAnalytics"
          to="/teaching-analytics"
          class="btn-export btn-export--analytics"
        >
          <BarChart3 class="h-4 w-4" />
          Analytics
        </router-link>
        <button type="button" class="btn-export btn-export--ghost" @click="clearFilters">
          Clear filters
        </button>
        <div v-if="loading" class="ml-auto inline-flex items-center gap-2 text-sm text-slate-500">
          <span class="loading-dot" />
          <span class="loading-dot animation-delay-150" />
          <span class="loading-dot animation-delay-300" />
          Loading...
        </div>
      </div>
    </section>

    <!-- Table -->
    <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Teachers Records</h2>
          <p class="text-xs text-slate-500">{{ totalTeachers }} total record(s) · Page {{ currentPage }} of {{ totalPages }}</p>
        </div>
      </div>

      <div class="relative overflow-x-auto">
        <table class="teachers-table min-w-[1350px] w-full">
          <thead>
            <tr>
              <th>Photo</th>
              <th>Code</th>
              <th>Full Name</th>
              <th>Gender</th>
              <th>Contact</th>
              <th>Email</th>
              <th>Subject</th>
              <th>Department</th>
              <th>Position</th>
              <th>Employment</th>
              <th>Obligation</th>
              <th>Status</th>
              <th>Joined</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="14" class="py-16 text-center">
                <div class="mx-auto flex max-w-xs flex-col items-center gap-3 text-slate-500">
                  <div class="flex gap-1.5">
                    <span class="loading-dot loading-dot--lg" />
                    <span class="loading-dot loading-dot--lg animation-delay-150" />
                    <span class="loading-dot loading-dot--lg animation-delay-300" />
                  </div>
                  <p class="text-sm font-medium">Loading teachers...</p>
                </div>
              </td>
            </tr>
            <tr v-else-if="!displayedTeachers.length">
              <td colspan="14" class="py-16 text-center">
                <div class="mx-auto flex max-w-sm flex-col items-center gap-3">
                  <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <Users class="h-8 w-8" />
                  </div>
                  <p class="text-base font-semibold text-slate-700">No teachers found</p>
                  <p class="text-sm text-slate-500">Try adjusting filters or add a new teacher to get started.</p>
                  <button type="button" class="btn-export btn-export--primary mt-1" @click="openAddModal">
                    <Plus class="h-4 w-4" />
                    Add Teacher
                  </button>
                </div>
              </td>
            </tr>
            <tr
              v-for="teacher in displayedTeachers"
              :key="teacher.id ?? teacher.teacher_id"
              class="table-row"
            >
              <td>
                <div class="h-12 w-12 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 flex items-center justify-center">
                  <img
                    v-if="teacher.passport_photo"
                    :src="photoUrl(teacher.passport_photo)"
                    alt="Passport"
                    class="h-full w-full object-cover"
                  />
                  <svg v-else class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
              </td>
              <td>
                <span class="code-badge">{{ teacher.teacher_code || '—' }}</span>
              </td>
              <td>
                <p class="font-semibold text-slate-900">{{ teacher.full_name }}</p>
                <p v-if="teacher.employee_id" class="text-xs text-slate-500">Emp #{{ teacher.employee_id }}</p>
              </td>
              <td>{{ teacher.gender || '—' }}</td>
              <td>{{ teacher.contact || teacher.phone_number || '—' }}</td>
              <td class="max-w-[180px] truncate" :title="teacher.email">{{ teacher.email || '—' }}</td>
              <td>{{ teacher.subject || '—' }}</td>
              <td>{{ teacher.department || '—' }}</td>
              <td>{{ teacher.position || '—' }}</td>
              <td>{{ teacher.employment_type || '—' }}</td>
              <td>
                <span class="badge badge--violet">{{ teacher.obligation || 'Subject Teacher' }}</span>
              </td>
              <td>
                <span class="badge" :class="Number(teacher.is_active) === 1 ? 'badge--active' : 'badge--inactive'">
                  {{ Number(teacher.is_active) === 1 ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="whitespace-nowrap text-slate-600">{{ formatDateShort(teacher.date_joined) }}</td>
              <td>
                <div class="flex flex-wrap justify-end gap-1">
                  <button type="button" class="action-btn action-btn--view" title="View" @click="viewTeacher(teacher)">
                    <Eye class="h-3.5 w-3.5" />
                  </button>
                  <button type="button" class="action-btn action-btn--edit" title="Edit" @click="editTeacher(teacher)">
                    <Pencil class="h-3.5 w-3.5" />
                  </button>
                  <button
                    type="button"
                    class="action-btn"
                    :class="Number(teacher.is_active) === 1 ? 'action-btn--warn' : 'action-btn--success'"
                    :title="Number(teacher.is_active) === 1 ? 'Deactivate' : 'Activate'"
                    @click="toggleTeacherStatus(teacher)"
                  >
                    <Power class="h-3.5 w-3.5" />
                  </button>
                  <button type="button" class="action-btn action-btn--danger" title="Delete" @click="deleteTeacher(teacher.id ?? teacher.teacher_id)">
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
        <div class="text-sm text-slate-600">
          Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, totalTeachers) }} of {{ totalTeachers }} entries
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="pagination-btn"
            :disabled="currentPage === 1"
            @click="prevPage"
          >
            <ChevronLeft class="h-4 w-4" />
            Previous
          </button>
          <div class="flex gap-1">
            <button
              v-for="page in Math.min(5, totalPages)"
              :key="page"
              type="button"
              class="pagination-page-btn"
              :class="{ 'pagination-page-btn--active': page === currentPage }"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
            <span v-if="totalPages > 5" class="flex items-center px-2 text-slate-400">...</span>
            <button
              v-if="totalPages > 5"
              type="button"
              class="pagination-page-btn"
              :class="{ 'pagination-page-btn--active': currentPage === totalPages }"
              @click="goToPage(totalPages)"
            >
              {{ totalPages }}
            </button>
          </div>
          <button
            type="button"
            class="pagination-btn"
            :disabled="currentPage === totalPages"
            @click="nextPage"
          >
            Next
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </section>

    <!-- Add / Edit Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
          <div class="modal-panel modal-panel--form" role="dialog" aria-modal="true">
            <div class="modal-header">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-blue-200">{{ editingId ? 'Update record' : 'New registration' }}</p>
                <h3 class="text-xl font-bold">{{ editingId ? 'Edit Teacher' : 'Add Teacher' }}</h3>
                <p class="mt-1 text-sm text-blue-100/80">{{ editingId ? 'Update teaching staff details below.' : 'Register a new teacher in the directory.' }}</p>
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
                        <Upload class="h-5 w-5 text-blue-500" />
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
                    class="h-28 w-28 rounded-3xl object-cover ring-4 ring-white/30"
                  />
                  <div v-else class="profile-avatar-lg">{{ teacherInitials(selectedTeacher.full_name) }}</div>
                </div>
                <div class="flex-1 text-white">
                  <p class="text-xs font-semibold uppercase tracking-wider text-blue-200">{{ selectedTeacher.teacher_code || 'No code' }}</p>
                  <h3 class="mt-1 text-2xl font-bold">{{ selectedTeacher.full_name || 'Unknown' }}</h3>
                  <p class="mt-1 text-sm text-blue-100">{{ selectedTeacher.position || 'Teacher' }} · {{ selectedTeacher.department || '—' }}</p>
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
                <p class="mt-1 text-sm text-blue-100/80">Upload CSV or paste JSON to bulk-register staff.</p>
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
                <Upload class="h-8 w-8 text-blue-500" />
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
import { computed, onMounted, onBeforeUnmount, ref, h, defineComponent } from 'vue';
import { teachersAPI, subjectsNewAPI } from '../services/api';
import authStore from '../services/authStore.js';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import {
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

/* ——— Inline section components ——— */
const FormSection = defineComponent({
  name: 'FormSection',
  props: { title: String, desc: String, icon: Object },
  setup(props, { slots }) {
    return () =>
      h('section', { class: 'form-section' }, [
        h('div', { class: 'form-section-head' }, [
          h(props.icon, { class: 'h-5 w-5 text-blue-600' }),
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
          h(props.icon, { class: 'h-4 w-4 text-blue-600' }),
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
                    : h('p', { class: 'detail-value' }, '—');
                })()
              : h('p', { class: 'detail-value' }, item.value ?? '—')
          ])
        )
      );
  }
});

const uploadsBase = import.meta.env.DEV
  ? 'http://localhost/accademics/backend/'
  : 'https://stmark.sc.ug/accademics/backend/';

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

const stats = computed(() => ({
  total: totalTeachers.value,
  active: displayedTeachers.value.filter((t) => Number(t.is_active) === 1).length,
  inactive: displayedTeachers.value.filter((t) => Number(t.is_active) === 0).length
}));

const obligationCount = computed(() => {
  return new Set(displayedTeachers.value.map((t) => t.obligation).filter(Boolean)).size;
});

const statCards = computed(() => [
  { label: 'Total Teachers', value: stats.value.total, color: 'text-slate-900', icon: Users },
  { label: 'Active', value: stats.value.active, color: 'text-emerald-600', icon: CheckCircle2 },
  { label: 'Inactive', value: stats.value.inactive, color: 'text-rose-600', icon: AlertCircle },
  { label: 'Obligation Types', value: obligationCount.value, color: 'text-blue-700', icon: Briefcase }
]);

const formatDate = (value) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
};

const formatDateShort = (value) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString();
};

const formatMoney = (value) => {
  if (value === null || value === undefined || value === '') return '—';
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

const detail = (label, value) => ({ label, value: value || '—' });

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

const reloadTeachers = () => loadTeachers();

const debouncedLoad = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadTeachers, 300);
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
    headStyles: { fillColor: [37, 99, 235], textColor: 255, fontStyle: 'bold' },
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
.teachers-page {
  --blue: #2563eb;
  --blue-dark: #1e3a8a;
}

/* Header buttons */
.btn-header {
  @apply inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold transition-all sm:text-sm;
}
.btn-header--ghost {
  @apply border border-white/20 bg-white/10 text-white backdrop-blur hover:bg-white/20 disabled:opacity-50;
}
.btn-header--emerald {
  @apply bg-emerald-500 text-white shadow-lg shadow-emerald-500/25 hover:bg-emerald-600;
}
.btn-header--primary {
  @apply bg-blue-500 text-white shadow-lg shadow-blue-500/25 hover:bg-blue-600;
}

.stat-card {
  @apply rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:shadow-md;
}

.field-label {
  @apply mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500;
}

.input {
  @apply w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100;
}

/* Filters export */
.btn-export {
  @apply inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm;
}
.btn-export--pdf { @apply bg-emerald-600 text-white hover:bg-emerald-700; }
.btn-export--excel { @apply bg-indigo-600 text-white hover:bg-indigo-700; }
.btn-export--csv { @apply bg-slate-700 text-white hover:bg-slate-800; }
.btn-export--analytics { @apply border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100; }
.btn-export--ghost { @apply border border-slate-200 text-slate-700 hover:bg-slate-50; }
.btn-export--primary { @apply bg-blue-600 text-white hover:bg-blue-700; }

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
  @apply inline-flex rounded-lg bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700;
}
.badge {
  @apply inline-flex rounded-full px-2.5 py-1 text-xs font-semibold;
}
.badge--active { @apply bg-emerald-50 text-emerald-700; }
.badge--inactive { @apply bg-rose-50 text-rose-700; }
.badge--violet { @apply bg-violet-50 text-violet-700; }

.action-btn {
  @apply inline-flex h-8 w-8 items-center justify-center rounded-lg border transition;
}
.action-btn--view { @apply border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100; }
.action-btn--edit { @apply border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100; }
.action-btn--warn { @apply border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100; }
.action-btn--success { @apply border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100; }
.action-btn--danger { @apply border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100; }

/* Loading */
.loading-dot {
  @apply h-2 w-2 rounded-full bg-blue-500;
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
  @apply flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl bg-white shadow-2xl;
}
.modal-panel--form { @apply max-w-3xl; }
.modal-panel--profile { @apply max-w-4xl; }
.modal-header {
  @apply flex shrink-0 items-start justify-between gap-4 bg-gradient-to-r from-slate-900 via-blue-950 to-blue-800 px-5 py-5 text-white;
}
.modal-header--compact { @apply bg-gradient-to-r from-slate-900 to-blue-900; }
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
  @apply inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 transition hover:bg-blue-700 disabled:opacity-50;
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
  @apply flex cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-200 bg-white px-6 py-5 transition hover:border-blue-400 hover:bg-blue-50/30;
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
  @apply relative shrink-0 bg-gradient-to-br from-slate-900 via-blue-950 to-blue-800 px-5 pb-6 pt-5;
}
.profile-avatar-lg {
  @apply flex h-28 w-28 items-center justify-center rounded-3xl bg-white/15 text-3xl font-bold text-white ring-4 ring-white/20;
}
.btn-profile-edit {
  @apply inline-flex items-center gap-2 self-start rounded-xl border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20;
}
.profile-pill {
  @apply inline-flex rounded-full px-3 py-1 text-xs font-semibold;
}
.profile-pill--active { @apply bg-emerald-400/20 text-emerald-100; }
.profile-pill--inactive { @apply bg-rose-400/20 text-rose-100; }
.profile-pill--role { @apply bg-violet-400/20 text-violet-100; }
.profile-pill--muted { @apply bg-white/10 text-blue-100; }
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
  @apply border-blue-200 shadow-md;
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
  @apply flex items-start gap-3 rounded-lg bg-blue-50 px-3 py-2 border border-blue-100;
}
.other-duty-number {
  @apply min-w-[24px] font-extrabold text-blue-700;
}
.other-duty-text {
  @apply flex-1 text-slate-800 leading-relaxed;
}

.import-dropzone {
  @apply flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 py-10 transition;
}
.import-dropzone--active {
  @apply border-blue-400 bg-blue-50;
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
  color: #2563eb;
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
  color: #2563eb;
  border-color: #cbd5e1;
}
.pagination-page-btn--active {
  background-color: #2563eb;
  color: white;
  border-color: #2563eb;
}
.pagination-page-btn--active:hover {
  background-color: #1d4ed8;
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