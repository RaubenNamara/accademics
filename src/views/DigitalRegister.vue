<template>
  <div class="page">
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Student E-files</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero" @click="showAdmit = true"><GraduationCap class="h-4 w-4" />Admit to S.5</button>
          <button type="button" class="btn-hero-primary" @click="openAddModal"><Plus class="h-4 w-4" />Add student</button>
        </div>
      </div>
    </div>

    <div v-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
      {{ errorMessage }}
    </div>
    <div v-if="successMessage" class="flex items-center justify-between rounded-2xl border border-brand-200 bg-brand-50 p-4 text-brand-800">
      <span>{{ successMessage }}</span>
      <button type="button" class="text-sm font-medium underline" @click="successMessage = ''">Dismiss</button>
    </div>
    <AdmitALevelDialog v-if="showAdmit" @close="showAdmit = false" @done="onAdmitted" />

    <StatCards :cards="summaryCards" />

    <ListToolbar
      :search="searchQuery"
      placeholder="Search name or admission number"
      :filter-count="[filters.class, filters.stream, filters.level, filters.status !== 'current' ? filters.status : ''].filter(Boolean).length"
      @update:search="(v) => { searchQuery = v; searchStudents(); }"
      @clear="filters.class = ''; filters.stream = ''; filters.level = ''; filters.status = 'current'; pagination.page = 1; loadStudents()"
    >
      <template #filters>
        <label class="block">
          <span class="filter-label">Class</span>
          <select v-model="filters.class" class="input py-2" @change="pagination.page = 1; loadStudents()">
            <option value="">All classes</option>
            <option v-for="cls in classOptions" :key="cls" :value="cls">{{ cls }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Stream</span>
          <select v-model="filters.stream" class="input py-2" @change="pagination.page = 1; loadStudents()">
            <option value="">All streams</option>
            <option v-for="str in streamOptions" :key="str" :value="str">{{ str }}</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Level</span>
          <select v-model="filters.level" class="input py-2" @change="pagination.page = 1; loadStudents()">
            <option value="">All levels</option>
            <option value="O-Level">O-Level</option>
            <option value="A-Level">A-Level</option>
          </select>
        </label>
        <label class="block">
          <span class="filter-label">Show</span>
          <select v-model="filters.status" class="input py-2" @change="pagination.page = 1; loadStudents()">
            <option value="current">Current students</option>
            <option value="active">In school only</option>
            <option value="completed_o_level">Completed S.4 (awaiting A-Level)</option>
            <option value="completed_a_level">Completed S.6</option>
            <option value="all">Everyone</option>
          </select>
        </label>
      </template>
      <template #actions>
        <DownloadMenu :scopes="STUDENT_SCOPES" scope-label="Students" :busy="exportingExcel" :disabled="loading" @download="downloadStudents" />
      </template>
    </ListToolbar>

    <section class="card overflow-hidden">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
        <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ pagination.total.toLocaleString() }}</strong> student{{ pagination.total === 1 ? '' : 's' }}</p>
      </div>
      <div v-if="loading" class="space-y-3 p-5">
        <div v-for="i in 6" :key="i" class="flex animate-pulse items-center gap-3"><div class="h-10 w-10 rounded-full bg-gray-100"></div><div class="h-3 flex-1 rounded bg-gray-100"></div></div>
      </div>
      <div v-else-if="students.length === 0" class="px-5 py-14 text-center">
        <FolderOpen class="mx-auto h-9 w-9 text-gray-300" />
        <p class="mt-2 text-sm font-medium text-gray-700">No students found</p>
      </div>
      <template v-else>
        <table class="hidden w-full text-left text-sm md:table">
          <thead class="border-b border-gray-200 bg-gray-50/70">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <th class="px-5 py-3">Student</th>
              <th class="px-4 py-3">Reg. no</th>
              <th class="px-4 py-3">Class</th>
              <th class="hidden px-4 py-3 lg:table-cell">Gender</th>
              <th class="hidden px-4 py-3 lg:table-cell">Parents</th>
              <th class="px-4 py-3">Status</th>
              <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="student in students" :key="student.id" class="transition" :class="student.status && student.status !== 'active' ? 'bg-gray-50 text-gray-500' : 'hover:bg-brand-50/40'">
              <td class="px-5 py-3">
                <button type="button" class="flex min-w-0 items-center gap-3 text-left" @click="viewStudent(student)">
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">
                    <img v-if="student.profile_picture" :src="fileUrl(student.profile_picture)" alt="" class="h-full w-full object-cover" />
                    <template v-else>{{ initials(student.full_name) }}</template>
                  </span>
                  <span class="min-w-0">
                    <span class="block truncate font-semibold text-gray-900 hover:text-brand-700">{{ student.full_name }}</span>
                  </span>
                </button>
              </td>
              <td class="px-4 py-3"><span v-if="student.admission_number" class="whitespace-nowrap rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs font-medium text-gray-700">{{ student.admission_number }}</span><span v-else class="text-gray-400">-</span></td>
              <td class="px-4 py-3">
                <p class="font-medium text-gray-800">{{ student.class }} {{ student.stream }}</p>
                <p class="text-xs text-gray-500">{{ student.level || '' }}</p>
              </td>
              <td class="hidden px-4 py-3 capitalize text-gray-700 lg:table-cell">{{ student.gender || '-' }}</td>
              <td class="hidden px-4 py-3 tabular-nums text-gray-700 lg:table-cell">{{ student.parent_count || 0 }}</td>
              <td class="px-4 py-3">
                <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="STATUS_STYLE[student.status || 'active']">{{ STATUS_LABEL[student.status] || 'In school' }}</span>
              </td>
              <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${student.full_name}`" :items="studentActions(student)" /></td>
            </tr>
          </tbody>
        </table>
        <ul class="divide-y divide-gray-100 md:hidden">
          <li v-for="student in students" :key="student.id" class="flex items-start gap-3 px-4 py-3" :class="student.status && student.status !== 'active' ? 'bg-gray-50' : ''">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">
              <img v-if="student.profile_picture" :src="fileUrl(student.profile_picture)" alt="" class="h-full w-full object-cover" />
              <template v-else>{{ initials(student.full_name) }}</template>
            </span>
            <button type="button" class="min-w-0 flex-1 text-left" @click="viewStudent(student)">
              <p class="truncate font-semibold text-gray-900">{{ student.full_name }}</p>
              <p class="text-xs text-gray-500">{{ student.admission_number }} · {{ student.class }} {{ student.stream }}</p>
              <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="STATUS_STYLE[student.status || 'active']">{{ STATUS_LABEL[student.status] || 'In school' }}</span>
            </button>
            <RowActionsMenu :label="`Actions for ${student.full_name}`" :items="studentActions(student)" />
          </li>
        </ul>
      </template>
      <PagerBar
        v-if="!searchQuery || searchQuery.trim().length < 2"
        :page="pagination.page"
        :per-page="pagination.limit"
        :total="pagination.total"
        @update:page="(n) => { pagination.page = n; loadStudents(); }"
        @update:per-page="(n) => { pagination.limit = n; pagination.page = 1; loadStudents(); }"
      />
    </section>

    <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b flex justify-between items-center">
          <h2 class="text-xl font-bold">{{ showEditModal ? 'Edit Student' : 'Add New Student' }}</h2>
          <button @click="closeModal" class="text-slate-500 hover:text-slate-700">✕</button>
        </div>

        <form @submit.prevent="saveStudent" class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <h3 class="text-lg font-semibold mb-4 text-slate-700">Student Information</h3>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-2">Profile Picture</label>
              <div class="flex items-start gap-4">
                <div class="w-32 h-32 rounded-lg bg-slate-100 border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden">
                  <img v-if="formData.profile_picture_url || formData.profile_picture" :src="formData.profile_picture ? URL.createObjectURL(formData.profile_picture) : formData.profile_picture_url" alt="Profile" class="w-full h-full object-cover">
                  <User v-else class="w-12 h-12 text-slate-400" />
                </div>
                <div class="flex-1">
                  <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 hover:border-brand-400 transition-colors">
                    <div class="flex items-center justify-center gap-2 mb-3">
                      <Upload class="w-5 h-5 text-slate-500" />
                      <input
                        type="file"
                        @change="handleProfilePictureChange"
                        accept=".jpg,.jpeg,.png"
                        class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100"
                      >
                    </div>
                    <p class="text-xs text-slate-500 text-center">Upload JPG, JPEG, or PNG (Max 5MB)</p>
                  </div>
                  <button v-if="formData.profile_picture || formData.profile_picture_url" @click="removeProfilePicture" type="button" class="mt-2 text-sm text-red-600 hover:text-red-800">
                    Remove Profile Picture
                  </button>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Admission Number *</label>
              <input v-model="formData.admission_number" type="text" required class="input">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
              <input v-model="formData.full_name" type="text" required class="input">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Gender *</label>
              <select v-model="formData.gender" required class="input">
                <option value="">Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Date of Birth</label>
              <input v-model="formData.date_of_birth" type="date" class="input">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Level</label>
              <select v-model="formData.level" class="input">
                <option value="">Select Level</option>
                <option value="O-Level">O-Level</option>
                <option value="A-Level">A-Level</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Class *</label>
              <select v-model="formData.class" required class="input">
                <option value="">Select Class</option>
                <option v-for="cls in classOptions" :key="cls" :value="cls">{{ cls }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Stream *</label>
              <select v-model="formData.stream" required class="input">
                <option value="">Select Stream</option>
                <option v-for="str in streamOptions" :key="str" :value="str">{{ str }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Enrollment Date</label>
              <input v-model="formData.enrollment_date" type="date" class="input">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">LIN</label>
              <input v-model="formData.lin" type="text" class="input" placeholder="Local Identification Number">
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-1">Former School</label>
              <input v-model="formData.former_school" type="text" class="input">
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-2 flex items-center gap-2">
                <FileText class="w-5 h-5 text-brand-600" />
                Former School Support Documents
              </label>
              <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 hover:border-brand-400 transition-colors">
                <div class="flex items-center justify-center gap-2 mb-3">
                  <Upload class="w-5 h-5 text-slate-500" />
                  <input
                    type="file"
                    @change="handleFormerSchoolFileChange"
                    multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100"
                  >
                </div>
                <p class="text-xs text-slate-500 text-center">Upload PDF, DOC, DOCX, JPG, JPEG, or PNG files</p>
              </div>
              
              <div v-if="formData.former_school_documents.length > 0" class="mt-3 space-y-2">
                <div v-for="(doc, index) in formData.former_school_documents" :key="index" class="flex items-center justify-between bg-slate-50 rounded-lg p-3 border">
                  <div class="flex items-center gap-3">
                    <File class="w-5 h-5 text-brand-600" />
                    <div>
                      <p class="text-sm font-medium text-slate-900">{{ getCleanFilename(doc.name) || getCleanFilename(doc.filename) || 'Document' }}</p>
                      <p v-if="doc.size" class="text-xs text-slate-500">{{ formatFileSize(doc.size) }}</p>
                    </div>
                  </div>
                  <button @click="removeFormerSchoolDocument(index)" type="button" class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded">
                    <X class="w-5 h-5" />
                  </button>
                </div>
              </div>

              <div v-if="formData.existing_former_school_documents && formData.existing_former_school_documents.length > 0" class="mt-3">
                <p class="text-sm font-medium text-slate-700 mb-2">Existing Documents:</p>
                <div class="space-y-2">
                  <div v-for="(doc, index) in formData.existing_former_school_documents" :key="index" class="flex items-center justify-between bg-slate-50 rounded-lg p-3 border">
                    <div class="flex items-center gap-3">
                      <FileText class="w-5 h-5 text-green-600" />
                      <div>
                        <p class="text-sm font-medium text-slate-900">{{ getCleanFilename(doc.filename) }}</p>
                        <p v-if="doc.uploaded_at" class="text-xs text-slate-500">Uploaded: {{ formatDate(doc.uploaded_at) }}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button v-if="isPDF(doc.filename)" @click="openPDFViewer(doc.file_path)" type="button" class="bg-brand-600 hover:bg-brand-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="View PDF">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                      </button>
                      <a :href="fileUrl(doc.file_path)" target="_blank" class="bg-brand-600 hover:bg-brand-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="Download">
                        <Download class="w-4 h-4" />
                      </a>
                      <button @click="removeExistingFormerSchoolDocument(index)" type="button" class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded">
                        <X class="w-5 h-5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-1">Behaviour/Discipline Notes</label>
              <textarea v-model="formData.behaviour_notes" rows="3" class="input"></textarea>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-2 flex items-center gap-2">
                <FileText class="w-5 h-5 text-brand-600" />
                Discipline Documents
              </label>
              <div class="border-2 border-dashed border-slate-300 rounded-lg p-4 hover:border-brand-400 transition-colors">
                <div class="flex items-center justify-center gap-2 mb-3">
                  <Upload class="w-5 h-5 text-slate-500" />
                  <input
                    type="file"
                    @change="handleDisciplineFileChange"
                    multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100"
                  >
                </div>
                <p class="text-xs text-slate-500 text-center">Upload PDF, DOC, DOCX, JPG, JPEG, or PNG files</p>
              </div>
              
              <div v-if="formData.behaviour_documents.length > 0" class="mt-3 space-y-2">
                <div v-for="(doc, index) in formData.behaviour_documents" :key="index" class="flex items-center justify-between bg-slate-50 rounded-lg p-3 border">
                  <div class="flex items-center gap-3">
                    <File class="w-5 h-5 text-brand-600" />
                    <div>
                      <p class="text-sm font-medium text-slate-900">{{ getCleanFilename(doc.name) || getCleanFilename(doc.filename) || 'Document' }}</p>
                      <p v-if="doc.size" class="text-xs text-slate-500">{{ formatFileSize(doc.size) }}</p>
                    </div>
                  </div>
                  <button @click="removeDisciplineDocument(index)" type="button" class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded">
                    <X class="w-5 h-5" />
                  </button>
                </div>
              </div>

              <div v-if="formData.existing_behaviour_documents && formData.existing_behaviour_documents.length > 0" class="mt-3">
                <p class="text-sm font-medium text-slate-700 mb-2">Existing Documents:</p>
                <div class="space-y-2">
                  <div v-for="(doc, index) in formData.existing_behaviour_documents" :key="index" class="flex items-center justify-between bg-slate-50 rounded-lg p-3 border">
                    <div class="flex items-center gap-3">
                      <FileText class="w-5 h-5 text-green-600" />
                      <div>
                        <p class="text-sm font-medium text-slate-900">{{ getCleanFilename(doc.filename) }}</p>
                        <p v-if="doc.uploaded_at" class="text-xs text-slate-500">Uploaded: {{ formatDate(doc.uploaded_at) }}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button v-if="isPDF(doc.filename)" @click="openPDFViewer(doc.file_path)" type="button" class="bg-brand-600 hover:bg-brand-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="View PDF">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                      </button>
                      <a :href="fileUrl(doc.file_path)" target="_blank" class="bg-brand-600 hover:bg-brand-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="Download">
                        <Download class="w-4 h-4" />
                      </a>
                      <button @click="removeExistingDisciplineDocument(index)" type="button" class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded">
                        <X class="w-5 h-5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-1">Medical Notes</label>
              <textarea v-model="formData.medical_notes" rows="3" class="input"></textarea>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-slate-700 mb-1">Special Needs</label>
              <textarea v-model="formData.special_needs" rows="3" class="input"></textarea>
            </div>

            <div class="md:col-span-2 mt-6 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
              <StudentSubjects ref="subjectsEditor" :key="editingId || 'new'" editable :student-id="editingId ? Number(editingId) : null" :level="formLevel" />
            </div>

            <div class="md:col-span-2 mt-6">
              <h3 class="text-lg font-semibold mb-4 text-slate-700">Academic Summary</h3>
            </div>

            <template v-if="formData.level === 'A-Level'">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Grade</label>
                <input v-model="formData.division" type="text" placeholder="e.g. A" class="input">
              </div>
            </template>
            <template v-else>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Aggregate</label>
                <input v-model="formData.aggregate" type="text" placeholder="e.g. 8" class="input">
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                <select v-model="formData.division" class="input">
                  <option value="">Select division</option>
                  <option v-if="formData.division && !divisionOptions.includes(formData.division)" :value="formData.division">{{ formData.division }} (current)</option>
                  <option v-for="option in divisionOptions" :key="option" :value="option">{{ option }}</option>
                </select>
              </div>
            </template>

            <div class="md:col-span-2 mt-6">
              <h3 class="text-lg font-semibold mb-4 text-slate-700">Parent/Guardian Information</h3>
            </div>

            <div v-for="(parent, index) in formData.parents" :key="index" class="md:col-span-2 border rounded-lg p-4 mb-4">
              <div class="flex justify-between items-center mb-3">
                <h4 class="font-medium">Parent/Guardian {{ index + 1 }}</h4>
                <button v-if="formData.parents.length > 1" @click="removeParent(index)" type="button" class="text-red-600 hover:text-red-800">
                  Remove
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
                  <input v-model="parent.full_name" type="text" class="input">
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Relationship *</label>
                  <select v-model="parent.relationship" class="input">
                    <option value="father">Father</option>
                    <option value="mother">Mother</option>
                    <option value="guardian">Guardian</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                  <input v-model="parent.phone" type="text" class="input">
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                  <input v-model="parent.email" type="email" class="input">
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1">NIN</label>
                  <input v-model="parent.nin" type="text" class="input" placeholder="National Identification Number">
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                  <textarea v-model="parent.address" rows="2" class="input"></textarea>
                </div>

                <div class="md:col-span-2">
                  <label class="flex items-center">
                    <input v-model="parent.is_primary_contact" type="checkbox" class="mr-2">
                    <span class="text-sm text-slate-700">Primary Contact</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="md:col-span-2">
              <button @click="addParent" type="button" class="text-brand-600 hover:text-brand-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Another Parent/Guardian
              </button>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeModal" class="px-4 py-2 border rounded-lg hover:bg-slate-50">
              Cancel
            </button>
            <button type="submit" class="btn-primary">
              Save Student
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Student e-file: an executive profile -->
    <Teleport to="body">
    <div v-if="showViewModal" class="fixed inset-0 z-[60] flex items-end justify-center bg-gray-900/60 backdrop-blur-sm sm:items-center sm:p-4" @click.self="showViewModal = false">
      <div class="flex h-[100dvh] w-full max-w-6xl flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[94vh] sm:rounded-2xl" role="dialog" aria-modal="true" aria-label="Student e-file">
        <div v-if="!viewData" class="p-10 text-center text-sm text-gray-500">Loading…</div>
        <template v-else>
          <!-- Identity -->
          <header class="efile-hero relative flex flex-col gap-4 border-b border-gray-100 px-5 py-5 sm:flex-row sm:items-center sm:px-7">
            <div class="flex min-w-0 flex-1 items-center gap-4">
              <span class="efile-avatar flex h-20 w-20 flex-shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-brand-50 text-2xl font-bold text-brand-800 ring-4 ring-white shadow-md">
                <img v-if="viewData.student.profile_picture" :src="fileUrl(viewData.student.profile_picture)" alt="" class="h-full w-full object-cover">
                <template v-else>{{ initials(viewData.student.full_name) }}</template>
              </span>
              <div class="min-w-0">
                <h2 class="truncate text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">{{ viewData.student.full_name }}</h2>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
                  <span class="rounded-md bg-gray-900 px-2 py-0.5 font-mono font-semibold text-white">{{ viewData.student.admission_number || 'No reg. no' }}</span>
                  <span class="rounded-md bg-brand-50 px-2 py-0.5 font-semibold text-brand-800 ring-1 ring-brand-100">{{ viewData.student.class }} {{ viewData.student.stream }}</span>
                  <span v-if="viewData.student.level" class="rounded-md bg-sky-50 px-2 py-0.5 font-semibold text-sky-800 ring-1 ring-sky-100">{{ viewData.student.level }}</span>
                  <span class="rounded-full px-2 py-0.5 font-semibold" :class="STATUS_STYLE[viewData.student.status || 'active']">{{ STATUS_LABEL[viewData.student.status] || 'In school' }}</span>
                </div>
              </div>
            </div>
            <div class="flex flex-shrink-0 items-center gap-2">
              <button type="button" class="efile-btn" @click="showViewModal = false; editStudent(viewData.student)"><Pencil class="h-4 w-4" />Edit</button>
              <button type="button" class="efile-btn" @click="downloadStudentPDF"><Download class="h-4 w-4" />PDF</button>
              <button type="button" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="showViewModal = false"><X class="h-5 w-5" /></button>
            </div>
          </header>

          <div class="min-h-0 flex-1 overflow-y-auto bg-gray-50/60 px-5 py-5 sm:px-7">
            <!-- Key figures -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
              <div class="efile-kpi"><p>Gender</p><strong class="capitalize">{{ viewData.student.gender || '-' }}</strong></div>
              <div class="efile-kpi"><p>Age</p><strong>{{ ageOf(viewData.student.date_of_birth) }}</strong><span>{{ viewData.student.date_of_birth || 'No date of birth' }}</span></div>
              <div class="efile-kpi"><p>{{ viewData.student.level === 'A-Level' ? 'Grade' : 'Aggregate · Division' }}</p><strong>{{ viewData.student.level === 'A-Level' ? (viewData.student.division || '-') : [viewData.student.aggregate, viewData.student.division].filter(Boolean).join(' · ') || '-' }}</strong><span>From {{ viewData.student.level === 'A-Level' ? 'O-Level' : 'PLE' }}</span></div>
              <div class="efile-kpi"><p>Results recorded</p><strong>{{ (viewData.results || []).length }}</strong><span>{{ (viewData.parents || []).length }} parent{{ (viewData.parents || []).length === 1 ? '' : 's' }} on file</span></div>
            </div>

            <div v-if="viewData.student.behaviour_notes" class="mt-4 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              <AlertCircle class="mt-0.5 h-4 w-4 flex-shrink-0" />
              <div><p class="font-semibold">Behaviour notes</p><p class="mt-0.5 whitespace-pre-line">{{ viewData.student.behaviour_notes }}</p></div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)]">
              <div class="space-y-4">
                <!-- Details -->
                <section class="efile-card">
                  <h3 class="efile-title"><User class="h-4 w-4" />Details</h3>
                  <dl class="divide-y divide-gray-100 text-sm">
                    <div v-for="row in detailRows" :key="row.label" class="flex justify-between gap-4 py-2">
                      <dt class="text-gray-500">{{ row.label }}</dt>
                      <dd class="text-right font-medium text-gray-900" :class="row.mono ? 'font-mono text-xs' : ''">{{ row.value || '-' }}</dd>
                    </div>
                  </dl>
                </section>

                <!-- Parents -->
                <section class="efile-card">
                  <h3 class="efile-title"><UsersIcon class="h-4 w-4" />Parents & guardians</h3>
                  <ul v-if="viewData.parents?.length" class="space-y-2.5">
                    <li v-for="(parent, i) in viewData.parents" :key="i" class="flex gap-3 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-100">
                      <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-semibold text-gray-700 ring-1 ring-gray-200">{{ initials(parent.full_name) }}</span>
                      <div class="min-w-0 text-sm">
                        <p class="font-semibold text-gray-900">{{ parent.full_name }} <span class="font-normal capitalize text-gray-500">· {{ parent.relationship }}</span></p>
                        <p class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-600">
                          <a v-if="parent.phone" :href="`tel:${parent.phone}`" class="inline-flex items-center gap-1 hover:text-brand-700"><Phone class="h-3 w-3" />{{ parent.phone }}</a>
                          <a v-if="parent.email" :href="`mailto:${parent.email}`" class="inline-flex items-center gap-1 hover:text-brand-700"><Mail class="h-3 w-3" />{{ parent.email }}</a>
                          <span v-if="parent.nin">NIN {{ parent.nin }}</span>
                          <span v-if="parent.address" class="inline-flex items-center gap-1"><MapPin class="h-3 w-3" />{{ parent.address }}</span>
                        </p>
                      </div>
                    </li>
                  </ul>
                  <p v-else class="text-sm text-gray-500">No parent or guardian on file.</p>
                </section>

                <!-- Documents -->
                <section v-if="viewData.student.former_school_documents?.length || viewData.student.behaviour_documents?.length" class="efile-card">
                  <h3 class="efile-title"><FileText class="h-4 w-4" />Documents</h3>
                  <ul class="space-y-1.5">
                    <li v-for="(doc, i) in [...(viewData.student.former_school_documents || []).map((d) => ({ ...d, kind: 'Former school' })), ...(viewData.student.behaviour_documents || []).map((d) => ({ ...d, kind: 'Discipline' }))]" :key="i" class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-gray-50">
                      <File class="h-4 w-4 flex-shrink-0" :class="doc.kind === 'Discipline' ? 'text-rose-500' : 'text-brand-600'" />
                      <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-gray-900">{{ getCleanFilename(doc.filename) }}</span><span class="block text-[11px] text-gray-500">{{ doc.kind }}</span></span>
                      <button v-if="isPDF(doc.filename)" type="button" class="efile-icon" title="View" @click="openPDFViewer(doc.file_path)"><Eye class="h-4 w-4" /></button>
                      <a :href="fileUrl(doc.file_path)" target="_blank" class="efile-icon" title="Download"><Download class="h-4 w-4" /></a>
                    </li>
                  </ul>
                </section>
              </div>

              <div class="space-y-4">
                <StudentSubjects :student-id="Number(viewData.student.id)" />

                <!-- Results -->
                <section class="efile-card !p-0 overflow-hidden">
                  <h3 class="efile-title px-4 pt-4"><BookOpen class="h-4 w-4" />Results</h3>
                  <div v-if="viewData.results?.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead><tr class="border-y border-gray-100 bg-gray-50 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500"><th class="px-4 py-2">Year · term</th><th class="px-4 py-2">Exam</th><th class="px-4 py-2">Subject</th><th class="px-4 py-2 text-right">Marks</th><th class="px-4 py-2 text-center">Grade</th></tr></thead>
                      <tbody class="divide-y divide-gray-100">
                        <tr v-for="result in viewData.results" :key="result.id" class="hover:bg-gray-50/70">
                          <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ result.year }} · T{{ result.term }}</td>
                          <td class="px-4 py-2 text-gray-600">{{ result.exam_type }}</td>
                          <td class="px-4 py-2 font-medium text-gray-900">{{ result.subject_name }}</td>
                          <td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-900">{{ result.marks }}</td>
                          <td class="px-4 py-2 text-center"><span class="inline-flex min-w-[2rem] justify-center rounded-md px-1.5 py-0.5 text-xs font-bold" :class="getGradeColor(result.grade)">{{ result.grade || '-' }}</span></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p v-else class="px-4 pb-4 text-sm text-gray-500">No results recorded yet.</p>
                </section>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
    </Teleport>

    <!-- PDF Viewer Modal -->
    <div v-if="showPDFViewerModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl h-[95vh] overflow-hidden flex flex-col">s
        <div class="bg-brand-600 p-4 rounded-t-2xl flex justify-between items-center">
          <h2 class="text-xl font-bold text-white">PDF Viewer</h2>
          <button @click="showPDFViewerModal = false" class="text-white hover:text-brand-200 transition-colors bg-white/10 hover:bg-white/20 rounded-full p-2" title="Close">
            <X class="w-6 h-6" />
          </button>
        </div>
       <div class="flex-1 overflow-hidden bg-slate-100" style="height: 90vh;">
  <iframe
    :src="pdfViewerUrl"
    class="w-full h-full border-0"
    title="PDF Viewer"
  ></iframe>
</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import DownloadMenu from '../components/ui/DownloadMenu.vue';
import { downloadExcel as saveExcel, downloadPdf as savePdf } from '../utils/downloads.js';
import { ref, onMounted, computed, watch } from 'vue';
import api from '../services/api.js';
import { classesAPI } from '../services/api';
import { FileText, Upload, X, Download, File, AlertCircle, User, Calendar, MapPin, Phone, Mail, GraduationCap, BookOpen, Award, Plus, FileSpreadsheet, FolderOpen, Users as UsersIcon, Eye, Pencil, Trash2, School, BookOpenCheck } from 'lucide-vue-next';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import PagerBar from '../components/ui/PagerBar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx';
import StudentSubjects from '../components/students/StudentSubjects.vue';
import { schoolSettingsAPI, studentAcademicsAPI } from '../services/api.js';
import { buildStudentProfilePdf, imageAsDataUrl } from '../utils/studentProfilePdf.js';
import AdmitALevelDialog from '../components/students/AdmitALevelDialog.vue';

const students = ref([]);
const allClasses = ref([]);
const streams = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const filters = ref({
  class: '',
  stream: '',
  level: '',
  status: 'current'
});
const showAdmit = ref(false);
const STATUS_LABEL = { completed_o_level: 'Completed S.4', completed_a_level: 'Completed S.6', left: 'Left' };
const STATUS_STYLE = {
  active: 'bg-brand-50 text-brand-800 ring-1 ring-brand-100',
  completed_o_level: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
  completed_a_level: 'bg-gray-100 text-gray-600',
  left: 'bg-gray-100 text-gray-600',
};
const initials = (name) => String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
// Age in years from a date of birth.
const ageOf = (dob) => {
  if (!dob) return '-';
  const d = new Date(String(dob).slice(0, 10));
  if (Number.isNaN(d.getTime())) return '-';
  const n = new Date();
  let age = n.getFullYear() - d.getFullYear();
  if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) age--;
  return age > 0 && age < 100 ? `${age} years` : '-';
};
const detailRows = computed(() => {
  const st = viewData.value?.student || {};
  return [
    { label: 'Reg. no', value: st.admission_number, mono: true },
    { label: 'LIN', value: st.lin, mono: true },
    { label: 'Class', value: [st.class, st.stream].filter(Boolean).join(' ') },
    { label: 'Date of birth', value: st.date_of_birth },
    { label: 'Enrolled', value: st.enrollment_date },
    { label: 'Former school', value: st.former_school },
  ];
});
const studentActions = (student) => [
  { label: 'View e-file', icon: Eye, onClick: () => viewStudent(student) },
  { label: 'Edit', icon: Pencil, onClick: () => editStudent(student) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => deleteStudent(student) },
];
// Whole-school counts; the cards also filter the list.
const summary = ref({ active: 0, o_level: 0, a_level: 0, completed_o_level: 0 });
const loadSummary = async () => {
  try {
    const res = await api.get('students.php?action=summary');
    if (res.data?.success) summary.value = res.data.data;
  } catch { /* cards keep their last values */ }
};
const summaryCards = computed(() => {
  const pick = (key, value) => () => {
    if (filters.value[key] === value) filters.value[key] = key === 'status' ? 'current' : '';
    else filters.value[key] = value;
    pagination.value.page = 1;
    loadStudents();
  };
  return [
    { label: 'Students', value: summary.value.active.toLocaleString(), icon: UsersIcon },
    { label: 'O-Level', value: summary.value.o_level.toLocaleString(), icon: School, tone: 'sky', onClick: pick('level', 'O-Level'), active: filters.value.level === 'O-Level' },
    { label: 'A-Level', value: summary.value.a_level.toLocaleString(), icon: BookOpenCheck, tone: 'amber', onClick: pick('level', 'A-Level'), active: filters.value.level === 'A-Level' },
    { label: 'Completed S.4', value: summary.value.completed_o_level.toLocaleString(), note: 'Awaiting A-Level', icon: GraduationCap, tone: 'gray', onClick: pick('status', 'completed_o_level'), active: filters.value.status === 'completed_o_level' },
  ];
});
const onAdmitted = (message) => {
  showAdmit.value = false;
  successMessage.value = message;
  loadStudents();
};

const pagination = ref({
  page: 1,
  limit: 20,
  total: 0,
  pages: 1
});

const exportingExcel = ref(false);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showViewModal = ref(false);
const showPDFViewerModal = ref(false);
const pdfViewerUrl = ref('');
const viewData = ref(null);
const editingId = ref(null);

const createEmptyForm = () => ({
  admission_number: '',
  full_name: '',
  gender: '',
  date_of_birth: '',
  class: '',
  stream: '',
  level: '',
  enrollment_date: new Date().toISOString().split('T')[0],
  lin: '',
  former_school: '',
  former_school_documents: [],
  existing_former_school_documents: [],
  former_school_support_doc: null,
  former_school_support_doc_url: '',
  behaviour_notes: '',
  behaviour_documents: [],
  existing_behaviour_documents: [],
  behaviour_document: null,
  behaviour_document_url: '',
  medical_notes: '',
  special_needs: '',
  aggregate: '',
  division: '',
  profile_picture: null,
  existing_profile_picture: null,
  profile_picture_url: '',
  parents: [
    {
      full_name: '',
      relationship: 'guardian',
      phone: '',
      email: '',
      nin: '',
      address: '',
      is_primary_contact: true
    }
  ]
});

const formData = ref(createEmptyForm());
const subjectsEditor = ref(null);
// Level for the subjects picker: the chosen level, else from the class (S.5–S.6 are A-Level).
const formLevel = computed(() => {
  if (formData.value.level) return formData.value.level;
  const n = Number(/(\d)/.exec(formData.value.class || '')?.[1] || 0);
  return n ? (n >= 5 ? 'A-Level' : 'O-Level') : '';
});

const divisionOptions = ['Division 1', 'Division 2', 'Division 3', 'Division 4'];

const classOptions = computed(() => {
  // Get unique class names from loaded classes
  const uniqueClassNames = [...new Set(allClasses.value.map(c => c.class_name))];
  return uniqueClassNames.sort();
});

const streamOptions = computed(() => {
  // Get unique stream names from loaded classes
  const uniqueStreamNames = [...new Set(allClasses.value.map(c => c.stream_name))];
  return uniqueStreamNames.sort();
});

const loadClasses = async () => {
  try {
    const response = await classesAPI.getAll();
    allClasses.value = response.data || [];
  } catch (error) {
    console.error('Error loading classes:', error);
  }
};

const uploadsBase = import.meta.env.DEV
  ? 'http://localhost/accademics/backend/'
  : '/backend/';

const fileUrl = (path) => {
  if (!path) return '';

  // if already full URL
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }

  // remove starting slashes
  path = path.replace(/^\/+/, '');

  // build correct path based on environment
  return uploadsBase + path;
};
const handleFileChange = (event, fieldName) => {
  const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
  formData.value[fieldName] = file;
};

const handleDisciplineFileChange = (event) => {
  const files = event.target.files;
  if (files && files.length > 0) {
    for (let i = 0; i < files.length; i++) {
      formData.value.behaviour_documents.push(files[i]);
    }
  }
};

const removeDisciplineDocument = (index) => {
  formData.value.behaviour_documents.splice(index, 1);
};

const removeExistingDisciplineDocument = (index) => {
  formData.value.existing_behaviour_documents.splice(index, 1);
};

const handleFormerSchoolFileChange = (event) => {
  const files = event.target.files;
  if (files && files.length > 0) {
    for (let i = 0; i < files.length; i++) {
      formData.value.former_school_documents.push(files[i]);
    }
  }
};

const removeFormerSchoolDocument = (index) => {
  formData.value.former_school_documents.splice(index, 1);
};

const removeExistingFormerSchoolDocument = (index) => {
  formData.value.existing_former_school_documents.splice(index, 1);
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getGradeColor = (grade) => {
  if (!grade) return 'bg-slate-100 text-slate-800';
  const gradeUpper = grade.toUpperCase();
  if (['A', 'A+', 'A*'].includes(gradeUpper)) return 'bg-green-100 text-green-800';
  if (['B', 'B+'].includes(gradeUpper)) return 'bg-brand-100 text-brand-800';
  if (['C', 'C+'].includes(gradeUpper)) return 'bg-yellow-100 text-yellow-800';
  if (['D', 'D+'].includes(gradeUpper)) return 'bg-orange-100 text-orange-800';
  if (['E', 'F', 'F9'].includes(gradeUpper)) return 'bg-red-100 text-red-800';
  return 'bg-slate-100 text-slate-800';
};

const getCleanFilename = (filename) => {
  if (!filename) return 'Document';

  // First, extract just the basename if it contains a path
  let cleanName = filename;
  if (filename.includes('/') || filename.includes('\\')) {
    cleanName = filename.split(/[\/\\]/).pop();
  }

  // Check if the filename is just a timestamp (no actual name)
  const timestampPattern = /^\d+_[a-f0-9]+\.\w+$/;
  if (timestampPattern.test(cleanName)) {
    // If it's just a timestamp, return a generic name
    const extension = cleanName.split('.')[1] || 'pdf';
    return `Document.${extension}`;
  }

  // Remove timestamp prefix (format: filename_1234567890_hash.ext)
  const parts = cleanName.split('_');
  if (parts.length >= 3) {
    // Keep only the original filename part (before the first underscore)
    // Reconstruct with extension from the last part
    const extension = parts[parts.length - 1].split('.')[1] || '';
    const baseName = parts[0];
    return extension ? `${baseName}.${extension}` : baseName;
  }

  // If no timestamp pattern, return the basename
  return cleanName;
};

const isPDF = (filename) => {
  if (!filename) return false;
  const extension = filename.split('.').pop().toLowerCase();
  return extension === 'pdf';
};

const openPDFViewer = (filePath) => {
  if (!filePath) return;
  pdfViewerUrl.value = fileUrl(filePath);
  showPDFViewerModal.value = true;
};

const handleProfilePictureChange = (event) => {
  const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
  if (file) {
    // Check file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      errorMessage.value = 'Profile picture must be less than 5MB';
      return;
    }
    formData.value.profile_picture = file;
    formData.value.profile_picture_url = '';
  }
};

const removeProfilePicture = () => {
  formData.value.profile_picture = null;
  formData.value.profile_picture_url = '';
  formData.value.existing_profile_picture = null;
};

const openAddModal = () => {
  editingId.value = null;
  formData.value = createEmptyForm();
  showEditModal.value = false;
  showAddModal.value = true;
};

const loadStudents = async () => {
  loading.value = true;
  loadSummary();
  errorMessage.value = '';

  try {
    const params = new URLSearchParams({
      page: String(pagination.value.page),
      limit: String(pagination.value.limit),
      class: filters.value.class,
      stream: filters.value.stream,
      level: filters.value.level,
      status: filters.value.status
    });

    const response = await api.get(`students.php?action=list&${params.toString()}`);
    students.value = Array.isArray(response.data?.data) ? response.data.data : [];
    pagination.value = response.data?.pagination || { page: 1, limit: 20, total: 0, pages: 1 };
  } catch (error) {
    console.error(error);
    errorMessage.value = error.response?.data?.error || 'Failed to load students';
    students.value = [];
  } finally {
    loading.value = false;
  }
};

const searchStudents = async () => {
  errorMessage.value = '';

  if (searchQuery.value.trim().length < 2) {
    loadStudents();
    return;
  }

  loading.value = true;

  try {
    const response = await api.get(`students.php?action=search&q=${encodeURIComponent(searchQuery.value.trim())}`);
    students.value = Array.isArray(response.data?.data) ? response.data.data : [];
    pagination.value.total = students.value.length;
    pagination.value.pages = 1;
    pagination.value.page = 1;
  } catch (error) {
    console.error(error);
    errorMessage.value = error.response?.data?.error || 'Failed to search students';
  } finally {
    loading.value = false;
  }
};

const addParent = () => {
  formData.value.parents.push({
    full_name: '',
    relationship: 'guardian',
    phone: '',
    email: '',
    address: '',
    is_primary_contact: false
  });
};

const removeParent = (index) => {
  if (formData.value.parents.length > 1) {
    formData.value.parents.splice(index, 1);
  }
};

const saveStudent = async () => {
  errorMessage.value = '';

  try {
    const payload = new FormData();
    payload.append('admission_number', formData.value.admission_number || '');
    payload.append('full_name', formData.value.full_name || '');
    payload.append('gender', formData.value.gender || '');
    payload.append('date_of_birth', formData.value.date_of_birth || '');
    payload.append('class', formData.value.class || '');
    payload.append('stream', formData.value.stream || '');
    payload.append('level', formData.value.level || '');
    payload.append('enrollment_date', formData.value.enrollment_date || '');
    payload.append('lin', formData.value.lin || '');
    payload.append('former_school', formData.value.former_school || '');
    payload.append('behaviour_notes', formData.value.behaviour_notes || '');
    payload.append('medical_notes', formData.value.medical_notes || '');
    payload.append('special_needs', formData.value.special_needs || '');
    payload.append('aggregate', formData.value.aggregate || '');
    payload.append('division', formData.value.division || '');

    // Handle profile picture
    if (formData.value.profile_picture && typeof formData.value.profile_picture === 'object' && 'name' in formData.value.profile_picture && 'size' in formData.value.profile_picture && 'type' in formData.value.profile_picture) {
      payload.append('profile_picture', formData.value.profile_picture);
    } else if (formData.value.existing_profile_picture) {
      payload.append('existing_profile_picture', formData.value.existing_profile_picture);
    }

    const parents = formData.value.parents.filter(p => p.full_name?.trim() && p.phone?.trim());
    payload.append('parents', JSON.stringify(parents));

    // Handle multiple former school documents
    formData.value.former_school_documents.forEach((doc) => {
      if (doc && typeof doc === 'object' && 'name' in doc && 'size' in doc && 'type' in doc) {
        payload.append('former_school_documents[]', doc);
      }
    });

    // Include existing former school documents to keep them
    if (formData.value.existing_former_school_documents.length > 0) {
      payload.append('existing_former_school_documents', JSON.stringify(formData.value.existing_former_school_documents));
    }

    // Handle multiple discipline documents
    formData.value.behaviour_documents.forEach((doc) => {
      if (doc && typeof doc === 'object' && 'name' in doc && 'size' in doc && 'type' in doc) {
        payload.append('behaviour_documents[]', doc);
      }
    });

    // Include existing documents to keep them
    if (formData.value.existing_behaviour_documents.length > 0) {
      payload.append('existing_behaviour_documents', JSON.stringify(formData.value.existing_behaviour_documents));
    }

    let studentId = editingId.value;
    if (showEditModal.value && editingId.value) {
      payload.append('id', String(editingId.value));
      await api.post('students.php?action=update', payload);
    } else {
      const created = await api.post('students.php?action=create', payload);
      studentId = created.data?.student_id;
    }
    // Subjects are saved with the form (compulsory ones apply by level and are not stored).
    try {
      await subjectsEditor.value?.save(Number(studentId));
    } catch (e) {
      errorMessage.value = `Student saved, but subjects were not: ${e.response?.data?.message || e.message}`;
    }

    closeModal();
    await loadStudents();
  } catch (error) {
    console.error(error);
    errorMessage.value =
      error.response?.data?.error ||
      error.response?.data?.database_error ||
      error.message ||
      'Error saving student';
  }
};

const editStudent = async (student) => {
  errorMessage.value = '';

  try {
    // Fetch complete student details including parents and documents
    const response = await api.get(`students.php?action=view&id=${student.id}`);
    const fullStudentData = response.data?.data?.student;

    if (!fullStudentData) {
      errorMessage.value = 'Failed to load student details';
      return;
    }

    editingId.value = student.id;
    formData.value = {
      admission_number: fullStudentData.admission_number || '',
      full_name: fullStudentData.full_name || '',
      gender: fullStudentData.gender || '',
      date_of_birth: fullStudentData.date_of_birth || '',
      class: fullStudentData.class || '',
      stream: fullStudentData.stream || '',
      level: fullStudentData.level || '',
      enrollment_date: fullStudentData.enrollment_date || new Date().toISOString().split('T')[0],
      lin: fullStudentData.lin || '',
      former_school: fullStudentData.former_school || '',
      former_school_documents: [],
      existing_former_school_documents: fullStudentData.former_school_documents || [],
      former_school_support_doc: null,
      former_school_support_doc_url: fullStudentData.former_school_support_doc || '',
      behaviour_notes: fullStudentData.behaviour_notes || '',
      behaviour_documents: [],
      existing_behaviour_documents: fullStudentData.behaviour_documents || [],
      behaviour_document: null,
      behaviour_document_url: fullStudentData.behaviour_document || '',
      medical_notes: fullStudentData.medical_notes || '',
      special_needs: fullStudentData.special_needs || '',
      aggregate: fullStudentData.aggregate || '',
      division: fullStudentData.division || '',
      profile_picture: null,
      existing_profile_picture: fullStudentData.profile_picture || null,
      profile_picture_url: fullStudentData.profile_picture ? fileUrl(fullStudentData.profile_picture) : '',
      parents: response.data?.data?.parents && response.data.data.parents.length > 0 ? response.data.data.parents.map(p => ({
        full_name: p.full_name || '',
        relationship: p.relationship || 'guardian',
        phone: p.phone || '',
        email: p.email || '',
        nin: p.nin || '',
        address: p.address || '',
        is_primary_contact: p.is_primary_contact || false
      })) : [
        {
          full_name: '',
          relationship: 'guardian',
          phone: '',
          email: '',
          nin: '',
          address: '',
          is_primary_contact: true
        }
      ]
    };
    showAddModal.value = false;
    showEditModal.value = true;
  } catch (error) {
    console.error(error);
    errorMessage.value = error.response?.data?.error || 'Failed to load student details';
  }
};

const viewStudent = async (student) => {
  errorMessage.value = '';

  try {
    const response = await api.get(`students.php?action=view&id=${student.id}`);
    viewData.value = response.data?.data || null;
    showViewModal.value = true;
  } catch (error) {
    console.error(error);
    errorMessage.value = error.response?.data?.error || 'Failed to load student profile';
  }
};

const deleteStudent = async (student) => {
  if (!confirm(`Are you sure you want to delete ${student.full_name}?`)) return;

  errorMessage.value = '';

  try {
    await api.delete(`students.php?action=delete&id=${student.id}`);
    await loadStudents();
  } catch (error) {
    console.error(error);
    errorMessage.value = error.response?.data?.error || 'Error deleting student';
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  editingId.value = null;
  formData.value = createEmptyForm();
};

const prevPage = () => {
  if (pagination.value.page > 1) {
    pagination.value.page--;
    loadStudents();
  }
};

const nextPage = () => {
  if (pagination.value.page < pagination.value.pages) {
    pagination.value.page++;
    loadStudents();
  }
};

const downloadPDF = () => {
  const doc = new jsPDF();
  
  // Add title
  doc.setFontSize(18);
  doc.text('Digital Register - Student List', 14, 22);
  
  // Add subtitle with filters
  doc.setFontSize(11);
  doc.setTextColor(100);
  const filterInfo = [
    filters.value.class ? `Class: ${filters.value.class}` : '',
    filters.value.stream ? `Stream: ${filters.value.stream}` : '',
    filters.value.level ? `Level: ${filters.value.level}` : '',
    searchQuery.value ? `Search: ${searchQuery.value}` : ''
  ].filter(Boolean).join(' | ');
  doc.text(filterInfo || 'All Students', 14, 30);
  
  // Add date
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, 36);
  
  // Prepare table data
  const tableData = students.value.map(student => [
    student.admission_number,
    student.full_name,
    student.gender,
    student.class,
    student.stream,
    student.level,
    student.parent_count || '0'
  ]);
  
  // Add table
  autoTable(doc, {
    head: [['Admission No', 'Name', 'Gender', 'Class', 'Stream', 'Level', 'Parents']],
    body: tableData,
    startY: 45,
    styles: {
      fontSize: 9,
      cellPadding: 3,
    },
    headStyles: {
      fillColor: [5, 150, 105],
      textColor: 255,
      fontStyle: 'bold',
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245],
    },
    margin: { top: 10, right: 10, bottom: 10, left: 10 },
  });
  
  // Add footer
  const pageCount = doc.internal.getNumberOfPages();
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i);
    doc.setFontSize(9);
    doc.setTextColor(150);
    doc.text(
      `Page ${i} of ${pageCount}`,
      doc.internal.pageSize.width / 2,
      doc.internal.pageSize.height - 10,
      { align: 'center' }
    );
  }
  
  // Save the PDF
  doc.save(`digital-register-${new Date().toISOString().split('T')[0]}.pdf`);
};

const downloadExcel = async () => {
  exportingExcel.value = true;
  errorMessage.value = '';

  try {
    const params = new URLSearchParams({
      action: 'export',
      class: filters.value.class,
      stream: filters.value.stream,
      level: filters.value.level,
      status: filters.value.status
    });

    const response = await api.get(`students.php?${params.toString()}`);
    const allStudents = Array.isArray(response.data?.data) ? response.data.data : [];

    const rows = allStudents.map((student, index) => ({
      'S/N': index + 1,
      'Admission No': student.admission_number,
      'Name': student.full_name,
      'Gender': student.gender,
      'Date of Birth': student.date_of_birth,
      'Class': student.class,
      'Stream': student.stream,
      'Level': student.level,
      'Enrollment Date': student.enrollment_date,
      'LIN': student.lin,
      'Former School': student.former_school,
      'Aggregate': student.aggregate || '',
      'Division': student.division || '',
      'Parents': student.parent_count || 0
    }));

    const worksheet = XLSX.utils.json_to_sheet(rows);
    worksheet['!cols'] = [
      { wch: 5 }, { wch: 15 }, { wch: 25 }, { wch: 10 }, { wch: 14 },
      { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 16 }, { wch: 14 },
      { wch: 25 }, { wch: 12 }, { wch: 12 }, { wch: 10 }
    ];

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Students');
    XLSX.writeFile(workbook, `digital-register-${new Date().toISOString().split('T')[0]}.xlsx`);
  } catch (error) {
    console.error(error);
    errorMessage.value = error.response?.data?.error || 'Failed to export students';
  } finally {
    exportingExcel.value = false;
  }
};

const downloadingPdf = ref(false);
const downloadStudentPDF = async () => {
  if (!viewData.value?.student || downloadingPdf.value) return;
  downloadingPdf.value = true;
  try {
    const student = viewData.value.student;
    const [subjects, settings, photo] = await Promise.all([
      studentAcademicsAPI.subjects(student.id).then((r) => r.data).catch(() => null),
      schoolSettingsAPI.get().then((r) => r.data).catch(() => ({})),
      imageAsDataUrl(student.profile_picture ? fileUrl(student.profile_picture) : null),
    ]);
    const doc = buildStudentProfilePdf({ student, parents: viewData.value.parents || [], results: viewData.value.results || [], subjects, settings, photo });
    const safeName = String(student.full_name || 'student').replace(/[^a-zA-Z0-9]+/g, '_');
    doc.save(`${student.admission_number} ${safeName} profile.pdf`);
  } catch (e) {
    errorMessage.value = `Could not build the PDF: ${e.message}`;
  } finally {
    downloadingPdf.value = false;
  }
};

onMounted(() => {
  loadClasses();
  loadStudents();
});

// Download every student (or those matching the filters on this page), as Excel or PDF.
const STUDENT_SCOPES = [{ value: 'all', label: 'All students' }, { value: 'filters', label: 'Students matching the filters' }];
const STUDENT_COLS = [
  { key: 'sn', label: '#', align: 'right' }, { key: 'admission_number', label: 'Admission no' }, { key: 'full_name', label: 'Name' },
  { key: 'gender', label: 'Gender' }, { key: 'date_of_birth', label: 'Date of birth' }, { key: 'class', label: 'Class' },
  { key: 'stream', label: 'Stream' }, { key: 'level', label: 'Level' }, { key: 'enrollment_date', label: 'Enrolled' },
  { key: 'lin', label: 'LIN' }, { key: 'former_school', label: 'Former school' }, { key: 'aggregate', label: 'Aggregate' },
  { key: 'division', label: 'Division' }, { key: 'parent_count', label: 'Parents' },
];
const downloadStudents = async (format, scope) => {
  exportingExcel.value = true;
  errorMessage.value = '';
  try {
    const f = scope === 'filters' ? filters.value : {};
    const params = new URLSearchParams({ action: 'export', class: f.class || '', stream: f.stream || '', level: f.level || '', status: f.status || '' });
    const response = await api.get(`students.php?${params.toString()}`);
    const list = (Array.isArray(response.data?.data) ? response.data.data : []).map((st, i) => ({ ...st, sn: i + 1, parent_count: st.parent_count || 0 }));
    const label = scope === 'filters' ? [f.level, f.class, f.stream].filter(Boolean).join(' ') || 'Filtered students' : 'All students';
    if (format === 'excel') {
      saveExcel(`students ${label}`, [{ name: 'Students', columns: STUDENT_COLS, rows: list }]);
    } else {
      const count = (lv) => list.filter((x) => x.level === lv).length;
      const girls = list.filter((x) => /^f/i.test(x.gender || '')).length;
      const boys = list.filter((x) => /^m/i.test(x.gender || '')).length;
      await savePdf({
        title: 'Students',
        subtitle: label,
        filename: `students ${label}`,
        columns: STUDENT_COLS.filter((c) => !['lin', 'former_school', 'parent_count', 'date_of_birth'].includes(c.key)),
        rows: list,
        summary: [
          { label: 'Students', value: list.length.toLocaleString() },
          { label: 'O-Level', value: count('O-Level') },
          { label: 'A-Level', value: count('A-Level') },
          { label: 'Girls / boys', value: `${girls} / ${boys}` },
        ],
      });
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.error || error.response?.data?.message || 'Could not prepare the download';
  } finally {
    exportingExcel.value = false;
  }
};
</script>

<style scoped>
/* Student e-file profile */
.efile-hero { background: radial-gradient(520px 180px at 100% 0%, rgba(16, 185, 129, 0.12), transparent 70%), #fff; }
.efile-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50; }
.efile-kpi { @apply rounded-xl bg-white px-4 py-3 ring-1 ring-gray-200; }
.efile-kpi p { @apply text-[11px] font-medium text-gray-500; }
.efile-kpi strong { @apply mt-0.5 block truncate text-lg font-bold text-gray-900; }
.efile-kpi span { @apply block truncate text-[11px] text-gray-400; }
.efile-card { @apply rounded-xl bg-white p-4 ring-1 ring-gray-200; }
.efile-title { @apply mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900; }
.efile-title svg { @apply text-brand-600; }
.efile-icon { @apply flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-brand-50 hover:text-brand-700; }
html.dark .efile-hero { background: radial-gradient(520px 180px at 100% 0%, rgba(16, 185, 129, 0.14), transparent 70%), var(--d-surface); }
html.dark .efile-kpi, html.dark .efile-card { background-color: var(--d-surface); --tw-ring-color: var(--d-line); }
html.dark .efile-avatar { --tw-ring-color: var(--d-line); }
html.dark .efile-btn { background-color: var(--d-raised); border-color: var(--d-line-strong); color: var(--d-text-2); }

.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .efile-kpi p {
  color: var(--d-muted);
}
html.dark .efile-kpi strong {
  color: var(--d-text);
}
html.dark .efile-kpi span {
  color: var(--d-faint);
}
html.dark .efile-card {
  background-color: var(--d-surface);
  --tw-ring-color: var(--d-line-strong);
}
html.dark .efile-title {
  color: var(--d-text);
}
html.dark .efile-title svg {
  color: #86efac;
}
html.dark .efile-icon {
  color: var(--d-muted);
}
html.dark .efile-icon:hover {
  background-color: rgba(34, 197, 94, 0.12);
  color: #86efac;
}
html.dark .filter-label {
  color: var(--d-muted);
}
html.dark .tool-btn {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
  color: var(--d-text-2);
}
html.dark .tool-btn:hover {
  background-color: var(--d-sunken);
}
</style>
