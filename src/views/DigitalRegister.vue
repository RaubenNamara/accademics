<template>
  <div class="p-6 bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-4xl font-bold text-gray-800 flex items-center gap-3">
            <div class="bg-blue-600 text-white p-3 rounded-lg">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            Student E-files
          </h1>
          <p class="text-gray-600 mt-2 text-lg">Manage student records and profiles</p>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
          <div class="text-center">
            <div class="text-3xl font-bold text-blue-600">{{ pagination.total }}</div>
            <div class="text-sm text-gray-500 uppercase tracking-wide">Total Students</div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="errorMessage" class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
      {{ errorMessage }}
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
      <div class="flex items-center gap-3 mb-4">
        <div class="bg-blue-100 p-2 rounded-lg">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-800">Filters</h2>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
          <select v-model="filters.class" @change="loadStudents" class="w-full border rounded-lg px-3 py-2">
            <option value="">All Classes</option>
            <option v-for="cls in classOptions" :key="cls" :value="cls">{{ cls }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Stream</label>
          <select v-model="filters.stream" @change="loadStudents" class="w-full border rounded-lg px-3 py-2">
            <option value="">All Streams</option>
            <option v-for="str in streamOptions" :key="str" :value="str">{{ str }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
          <select v-model="filters.level" @change="loadStudents" class="w-full border rounded-lg px-3 py-2">
            <option value="">All Levels</option>
            <option value="O-Level">O-Level</option>
            <option value="A-Level">A-Level</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
          <input
            v-model="searchQuery"
            @input="searchStudents"
            type="text"
            placeholder="Search by name or admission no..."
            class="w-full border rounded-lg px-3 py-2"
          >
        </div>
      </div>
    </div>

    <div class="flex justify-between items-center mb-6">
      <div class="bg-white rounded-lg shadow px-4 py-2 border border-gray-200">
        <span class="text-sm text-gray-600">Showing <span class="font-semibold text-blue-600">{{ pagination.total }}</span> students</span>
      </div>

      <div class="flex gap-3">
        <button
          @click="downloadPDF"
          :disabled="students.length === 0 || loading"
          class="bg-gradient-to-r from-green-600 to-green-700 text-white px-5 py-2.5 rounded-lg hover:from-green-700 hover:to-green-800 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg transition-all duration-200 hover:shadow-xl"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          Download PDF
        </button>
        <button
          @click="downloadExcel"
          :disabled="pagination.total === 0 || loading || exportingExcel"
          class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white px-5 py-2.5 rounded-lg hover:from-emerald-700 hover:to-teal-800 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg transition-all duration-200 hover:shadow-xl"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          {{ exportingExcel ? 'Exporting...' : 'Download Excel' }}
        </button>
        <button
          @click="openAddModal"
          class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-5 py-2.5 rounded-lg hover:from-blue-700 hover:to-blue-800 flex items-center gap-2 shadow-lg transition-all duration-200 hover:shadow-xl"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          Add Student
        </button>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
      <div class="overflow-x-auto">
        <div v-if="loading" class="p-8 text-center text-gray-600">
          <div class="inline-flex items-center gap-3">
            <svg class="animate-spin w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-lg">Loading students...</span>
          </div>
        </div>

        <div v-else-if="students.length === 0" class="p-8 text-center text-gray-500">
          <div class="inline-flex flex-col items-center gap-3">
            <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <span class="text-lg">No students found.</span>
          </div>
        </div>

        <table v-else class="w-full min-w-[900px]">
        <thead class="bg-gradient-to-r from-blue-600 to-indigo-600">
          <tr>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">S/N</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Admission No</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Name</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Gender</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Class</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Stream</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Level</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Parents</th>
            <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-100">
          <tr v-for="(student, index) in students" :key="student.id" class="hover:bg-blue-50 transition-colors">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-600 bg-blue-50">
              {{ (pagination.page - 1) * pagination.limit + index + 1 }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
              {{ student.admission_number }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                  <span class="text-xs font-semibold text-gray-600">{{ student.full_name.charAt(0).toUpperCase() }}</span>
                </div>
                <span class="font-medium">{{ student.full_name }}</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="student.gender === 'male' ? 'bg-blue-100 text-blue-800' : student.gender === 'female' ? 'bg-pink-100 text-pink-800' : 'bg-gray-100 text-gray-800'">
                {{ student.gender }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                {{ student.class }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                {{ student.stream }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="student.level === 'O-Level' ? 'bg-orange-100 text-orange-800' : 'bg-indigo-100 text-indigo-800'">
                {{ student.level }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <div class="flex items-center gap-1">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="font-medium">{{ student.parent_count }}</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              <div class="flex items-center gap-2">
                <button @click="viewStudent(student)" class="inline-flex items-center px-3 py-1.5 border border-blue-600 text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg text-xs font-medium transition-colors">
                  <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                  </svg>
                  View
                </button>
                <button @click="editStudent(student)" class="inline-flex items-center px-3 py-1.5 border border-green-600 text-green-600 bg-green-50 hover:bg-green-100 rounded-lg text-xs font-medium transition-colors">
                  <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                  </svg>
                  Edit
                </button>
                <button @click="deleteStudent(student)" class="inline-flex items-center px-3 py-1.5 border border-red-600 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg text-xs font-medium transition-colors">
                  <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
        <button
          @click="prevPage"
          :disabled="pagination.page === 1"
          class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>
          Previous
        </button>

        <div class="flex items-center gap-2">
          <span class="text-sm text-gray-600">Page</span>
          <span class="px-3 py-1 bg-blue-600 text-white rounded-lg font-semibold text-sm">{{ pagination.page }}</span>
          <span class="text-sm text-gray-600">of {{ pagination.pages }}</span>
        </div>

        <button
          @click="nextPage"
          :disabled="pagination.page === pagination.pages"
          class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium text-gray-700 shadow-sm transition-all duration-200"
        >
          Next
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
          </svg>
        </button>
      </div>
      </div>
    </div>

    <div v-if="showAddModal || showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b flex justify-between items-center">
          <h2 class="text-xl font-bold">{{ showEditModal ? 'Edit Student' : 'Add New Student' }}</h2>
          <button @click="closeModal" class="text-gray-500 hover:text-gray-700">✕</button>
        </div>

        <form @submit.prevent="saveStudent" class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
              <h3 class="text-lg font-semibold mb-4 text-gray-700">Student Information</h3>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
              <div class="flex items-start gap-4">
                <div class="w-32 h-32 rounded-lg bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden">
                  <img v-if="formData.profile_picture_url || formData.profile_picture" :src="formData.profile_picture ? URL.createObjectURL(formData.profile_picture) : formData.profile_picture_url" alt="Profile" class="w-full h-full object-cover">
                  <User v-else class="w-12 h-12 text-gray-400" />
                </div>
                <div class="flex-1">
                  <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors">
                    <div class="flex items-center justify-center gap-2 mb-3">
                      <Upload class="w-5 h-5 text-gray-500" />
                      <input
                        type="file"
                        @change="handleProfilePictureChange"
                        accept=".jpg,.jpeg,.png"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                      >
                    </div>
                    <p class="text-xs text-gray-500 text-center">Upload JPG, JPEG, or PNG (Max 5MB)</p>
                  </div>
                  <button v-if="formData.profile_picture || formData.profile_picture_url" @click="removeProfilePicture" type="button" class="mt-2 text-sm text-red-600 hover:text-red-800">
                    Remove Profile Picture
                  </button>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Admission Number *</label>
              <input v-model="formData.admission_number" type="text" required class="w-full border rounded-lg px-3 py-2">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
              <input v-model="formData.full_name" type="text" required class="w-full border rounded-lg px-3 py-2">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
              <select v-model="formData.gender" required class="w-full border rounded-lg px-3 py-2">
                <option value="">Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
              <input v-model="formData.date_of_birth" type="date" class="w-full border rounded-lg px-3 py-2">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
              <select v-model="formData.level" class="w-full border rounded-lg px-3 py-2">
                <option value="">Select Level</option>
                <option value="O-Level">O-Level</option>
                <option value="A-Level">A-Level</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
              <select v-model="formData.class" required class="w-full border rounded-lg px-3 py-2">
                <option value="">Select Class</option>
                <option v-for="cls in classOptions" :key="cls" :value="cls">{{ cls }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stream *</label>
              <select v-model="formData.stream" required class="w-full border rounded-lg px-3 py-2">
                <option value="">Select Stream</option>
                <option v-for="str in streamOptions" :key="str" :value="str">{{ str }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Enrollment Date</label>
              <input v-model="formData.enrollment_date" type="date" class="w-full border rounded-lg px-3 py-2">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">LIN</label>
              <input v-model="formData.lin" type="text" class="w-full border rounded-lg px-3 py-2" placeholder="Local Identification Number">
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Former School</label>
              <input v-model="formData.former_school" type="text" class="w-full border rounded-lg px-3 py-2">
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                <FileText class="w-5 h-5 text-blue-600" />
                Former School Support Documents
              </label>
              <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors">
                <div class="flex items-center justify-center gap-2 mb-3">
                  <Upload class="w-5 h-5 text-gray-500" />
                  <input
                    type="file"
                    @change="handleFormerSchoolFileChange"
                    multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  >
                </div>
                <p class="text-xs text-gray-500 text-center">Upload PDF, DOC, DOCX, JPG, JPEG, or PNG files</p>
              </div>
              
              <div v-if="formData.former_school_documents.length > 0" class="mt-3 space-y-2">
                <div v-for="(doc, index) in formData.former_school_documents" :key="index" class="flex items-center justify-between bg-gray-50 rounded-lg p-3 border">
                  <div class="flex items-center gap-3">
                    <File class="w-5 h-5 text-blue-600" />
                    <div>
                      <p class="text-sm font-medium text-gray-900">{{ getCleanFilename(doc.name) || getCleanFilename(doc.filename) || 'Document' }}</p>
                      <p v-if="doc.size" class="text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                    </div>
                  </div>
                  <button @click="removeFormerSchoolDocument(index)" type="button" class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded">
                    <X class="w-5 h-5" />
                  </button>
                </div>
              </div>

              <div v-if="formData.existing_former_school_documents && formData.existing_former_school_documents.length > 0" class="mt-3">
                <p class="text-sm font-medium text-gray-700 mb-2">Existing Documents:</p>
                <div class="space-y-2">
                  <div v-for="(doc, index) in formData.existing_former_school_documents" :key="index" class="flex items-center justify-between bg-gray-50 rounded-lg p-3 border">
                    <div class="flex items-center gap-3">
                      <FileText class="w-5 h-5 text-green-600" />
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ getCleanFilename(doc.filename) }}</p>
                        <p v-if="doc.uploaded_at" class="text-xs text-gray-500">Uploaded: {{ formatDate(doc.uploaded_at) }}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button v-if="isPDF(doc.filename)" @click="openPDFViewer(doc.file_path)" type="button" class="bg-purple-600 hover:bg-purple-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="View PDF">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                      </button>
                      <a :href="fileUrl(doc.file_path)" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="Download">
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
              <label class="block text-sm font-medium text-gray-700 mb-1">Behaviour/Discipline Notes</label>
              <textarea v-model="formData.behaviour_notes" rows="3" class="w-full border rounded-lg px-3 py-2"></textarea>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                <FileText class="w-5 h-5 text-blue-600" />
                Discipline Documents
              </label>
              <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 hover:border-blue-400 transition-colors">
                <div class="flex items-center justify-center gap-2 mb-3">
                  <Upload class="w-5 h-5 text-gray-500" />
                  <input
                    type="file"
                    @change="handleDisciplineFileChange"
                    multiple
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  >
                </div>
                <p class="text-xs text-gray-500 text-center">Upload PDF, DOC, DOCX, JPG, JPEG, or PNG files</p>
              </div>
              
              <div v-if="formData.behaviour_documents.length > 0" class="mt-3 space-y-2">
                <div v-for="(doc, index) in formData.behaviour_documents" :key="index" class="flex items-center justify-between bg-gray-50 rounded-lg p-3 border">
                  <div class="flex items-center gap-3">
                    <File class="w-5 h-5 text-blue-600" />
                    <div>
                      <p class="text-sm font-medium text-gray-900">{{ getCleanFilename(doc.name) || getCleanFilename(doc.filename) || 'Document' }}</p>
                      <p v-if="doc.size" class="text-xs text-gray-500">{{ formatFileSize(doc.size) }}</p>
                    </div>
                  </div>
                  <button @click="removeDisciplineDocument(index)" type="button" class="text-red-600 hover:text-red-800 p-1 hover:bg-red-50 rounded">
                    <X class="w-5 h-5" />
                  </button>
                </div>
              </div>

              <div v-if="formData.existing_behaviour_documents && formData.existing_behaviour_documents.length > 0" class="mt-3">
                <p class="text-sm font-medium text-gray-700 mb-2">Existing Documents:</p>
                <div class="space-y-2">
                  <div v-for="(doc, index) in formData.existing_behaviour_documents" :key="index" class="flex items-center justify-between bg-gray-50 rounded-lg p-3 border">
                    <div class="flex items-center gap-3">
                      <FileText class="w-5 h-5 text-green-600" />
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ getCleanFilename(doc.filename) }}</p>
                        <p v-if="doc.uploaded_at" class="text-xs text-gray-500">Uploaded: {{ formatDate(doc.uploaded_at) }}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button v-if="isPDF(doc.filename)" @click="openPDFViewer(doc.file_path)" type="button" class="bg-purple-600 hover:bg-purple-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="View PDF">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                      </button>
                      <a :href="fileUrl(doc.file_path)" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="Download">
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
              <label class="block text-sm font-medium text-gray-700 mb-1">Medical Notes</label>
              <textarea v-model="formData.medical_notes" rows="3" class="w-full border rounded-lg px-3 py-2"></textarea>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Special Needs</label>
              <textarea v-model="formData.special_needs" rows="3" class="w-full border rounded-lg px-3 py-2"></textarea>
            </div>

            <div class="md:col-span-2 mt-6">
              <h3 class="text-lg font-semibold mb-4 text-gray-700">Parent/Guardian Information</h3>
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
                  <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                  <input v-model="parent.full_name" type="text" class="w-full border rounded-lg px-3 py-2">
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Relationship *</label>
                  <select v-model="parent.relationship" class="w-full border rounded-lg px-3 py-2">
                    <option value="father">Father</option>
                    <option value="mother">Mother</option>
                    <option value="guardian">Guardian</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                  <input v-model="parent.phone" type="text" class="w-full border rounded-lg px-3 py-2">
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input v-model="parent.email" type="email" class="w-full border rounded-lg px-3 py-2">
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">NIN</label>
                  <input v-model="parent.nin" type="text" class="w-full border rounded-lg px-3 py-2" placeholder="National Identification Number">
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                  <textarea v-model="parent.address" rows="2" class="w-full border rounded-lg px-3 py-2"></textarea>
                </div>

                <div class="md:col-span-2">
                  <label class="flex items-center">
                    <input v-model="parent.is_primary_contact" type="checkbox" class="mr-2">
                    <span class="text-sm text-gray-700">Primary Contact</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="md:col-span-2">
              <button @click="addParent" type="button" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Another Parent/Guardian
              </button>
            </div>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeModal" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              Save Student
            </button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showViewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto">
        <!-- Header with gradient -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 rounded-t-2xl">
          <div class="flex justify-between items-start">
            <div>
              <h2 class="text-2xl font-bold text-white mb-2">Student Profile</h2>
              <p class="text-blue-100 text-sm">View complete student information and documents</p>
            </div>
            <div class="flex gap-2">
              <button @click="downloadStudentPDF" class="text-white hover:text-blue-200 transition-colors bg-white/10 hover:bg-white/20 rounded-full p-2" title="Download PDF">
                <Download class="w-6 h-6" />
              </button>
              <button @click="showViewModal = false" class="text-white hover:text-blue-200 transition-colors bg-white/10 hover:bg-white/20 rounded-full p-2" title="Close">
                <X class="w-6 h-6" />
              </button>
            </div>
          </div>
        </div>

        <div v-if="viewData" class="p-6 space-y-6">
          <!-- Student Information Card -->
          <div class="bg-gradient-to-br from-gray-50 to-white rounded-xl p-6 border border-gray-200 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <User class="w-5 h-5 text-blue-600" />
              Student Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <!-- Profile Picture -->
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm flex items-center gap-4">
                <div class="w-20 h-20 rounded-full bg-gray-100 border-2 border-gray-200 flex items-center justify-center overflow-hidden flex-shrink-0">
                  <img v-if="viewData.student.profile_picture" :src="fileUrl(viewData.student.profile_picture)" alt="Profile" class="w-full h-full object-cover">
                  <User v-else class="w-10 h-10 text-gray-400" />
                </div>
                <div>
                  <p class="text-sm text-gray-500 mb-1">Profile Picture</p>
                  <p class="text-xs text-gray-400">{{ viewData.student.profile_picture ? 'Uploaded' : 'Not set' }}</p>
                </div>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <Award class="w-4 h-4" />
                  Admission No
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.admission_number }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <User class="w-4 h-4" />
                  Full Name
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.full_name }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <User class="w-4 h-4" />
                  Gender
                </div>
                <p class="font-semibold text-gray-900 capitalize">{{ viewData.student.gender }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <Calendar class="w-4 h-4" />
                  Date of Birth
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.date_of_birth }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <GraduationCap class="w-4 h-4" />
                  Class
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.class }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <GraduationCap class="w-4 h-4" />
                  Stream
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.stream }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <GraduationCap class="w-4 h-4" />
                  Level
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.level }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <Calendar class="w-4 h-4" />
                  Enrollment Date
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.enrollment_date }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <Award class="w-4 h-4" />
                  LIN
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.lin || 'N/A' }}</p>
              </div>
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm lg:col-span-3">
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
                  <MapPin class="w-4 h-4" />
                  Former School
                </div>
                <p class="font-semibold text-gray-900">{{ viewData.student.former_school || 'N/A' }}</p>
              </div>
            </div>

            <div v-if="viewData.student.behaviour_notes" class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-4">
              <div class="flex items-center gap-2 text-amber-700 font-medium mb-2">
                <AlertCircle class="w-4 h-4" />
                Behaviour Notes
              </div>
              <p class="text-gray-700 text-sm">{{ viewData.student.behaviour_notes }}</p>
            </div>
          </div>

          <!-- Documents Section -->
          <div v-if="(viewData.student.former_school_documents && viewData.student.former_school_documents.length > 0) || (viewData.student.behaviour_documents && viewData.student.behaviour_documents.length > 0)" class="bg-gradient-to-br from-indigo-50 to-white rounded-xl p-6 border border-indigo-200 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <FileText class="w-5 h-5 text-indigo-600" />
              Student Documents
            </h3>
            <div class="space-y-4">
              <!-- Former School Documents -->
              <div v-if="viewData.student.former_school_documents && viewData.student.former_school_documents.length > 0">
                <h4 class="text-sm font-semibold text-gray-600 mb-2">Former School Documents</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div v-for="(doc, index) in viewData.student.former_school_documents" :key="'fs-' + index" class="bg-white rounded-lg p-3 border border-indigo-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2 flex-1 min-w-0">
                        <File class="w-4 h-4 text-blue-600 flex-shrink-0" />
                        <p class="text-sm font-medium text-gray-900 truncate">{{ getCleanFilename(doc.filename) }}</p>
                      </div>
                      <div class="flex items-center gap-2 ml-2">
                        <button v-if="isPDF(doc.filename)" @click="openPDFViewer(doc.file_path)" type="button" class="bg-purple-600 hover:bg-purple-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="View PDF">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                          </svg>
                        </button>
                        <a :href="fileUrl(doc.file_path)" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="Download">
                          <Download class="w-4 h-4" />
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Discipline Documents -->
              <div v-if="viewData.student.behaviour_documents && viewData.student.behaviour_documents.length > 0">
                <h4 class="text-sm font-semibold text-gray-600 mb-2">Discipline Documents</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div v-for="(doc, index) in viewData.student.behaviour_documents" :key="'bd-' + index" class="bg-white rounded-lg p-3 border border-indigo-100 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2 flex-1 min-w-0">
                        <File class="w-4 h-4 text-red-600 flex-shrink-0" />
                        <p class="text-sm font-medium text-gray-900 truncate">{{ getCleanFilename(doc.filename) }}</p>
                      </div>
                      <div class="flex items-center gap-2 ml-2">
                        <button v-if="isPDF(doc.filename)" @click="openPDFViewer(doc.file_path)" type="button" class="bg-purple-600 hover:bg-purple-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="View PDF">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                          </svg>
                        </button>
                        <a :href="fileUrl(doc.file_path)" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded-lg flex items-center justify-center transition-colors shadow-sm hover:shadow flex-shrink-0" title="Download">
                          <Download class="w-4 h-4" />
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Parent/Guardian Information -->
          <div class="bg-gradient-to-br from-green-50 to-white rounded-xl p-6 border border-green-200 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <User class="w-5 h-5 text-green-600" />
              Parent/Guardian Information
            </h3>
            <div v-if="viewData.parents && viewData.parents.length > 0">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="(parent, index) in viewData.parents" :key="index" class="bg-white rounded-lg p-5 border border-green-100 shadow-sm">
                  <div class="space-y-3">
                    <div class="flex items-center gap-2">
                      <User class="w-4 h-4 text-gray-500" />
                      <div>
                        <p class="text-xs text-gray-500">Name</p>
                        <p class="font-semibold text-gray-900">{{ parent.full_name }}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <User class="w-4 h-4 text-gray-500" />
                      <div>
                        <p class="text-xs text-gray-500">Relationship</p>
                        <p class="font-medium text-gray-900 capitalize">{{ parent.relationship }}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <Phone class="w-4 h-4 text-gray-500" />
                      <div>
                        <p class="text-xs text-gray-500">Phone</p>
                        <p class="font-medium text-gray-900">{{ parent.phone }}</p>
                      </div>
                    </div>
                    <div v-if="parent.email" class="flex items-center gap-2">
                      <Mail class="w-4 h-4 text-gray-500" />
                      <div>
                        <p class="text-xs text-gray-500">Email</p>
                        <p class="font-medium text-gray-900">{{ parent.email }}</p>
                      </div>
                    </div>
                    <div v-if="parent.nin" class="flex items-center gap-2">
                      <Award class="w-4 h-4 text-gray-500" />
                      <div>
                        <p class="text-xs text-gray-500">NIN</p>
                        <p class="font-medium text-gray-900">{{ parent.nin }}</p>
                      </div>
                    </div>
                    <div v-if="parent.address" class="flex items-center gap-2">
                      <MapPin class="w-4 h-4 text-gray-500" />
                      <div>
                        <p class="text-xs text-gray-500">Address</p>
                        <p class="font-medium text-gray-900">{{ parent.address }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-gray-500 text-center py-4 bg-gray-50 rounded-lg">No parent/guardian information available</div>
          </div>

          <!-- Academic Results -->
          <div class="bg-gradient-to-br from-purple-50 to-white rounded-xl p-6 border border-purple-200 shadow-sm">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <BookOpen class="w-5 h-5 text-purple-600" />
              Academic Results
            </h3>
            <div v-if="viewData.results && viewData.results.length > 0">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr class="bg-purple-100">
                      <th class="px-4 py-3 text-left text-xs font-bold text-purple-800 uppercase tracking-wider rounded-tl-lg">Year</th>
                      <th class="px-4 py-3 text-left text-xs font-bold text-purple-800 uppercase tracking-wider">Term</th>
                      <th class="px-4 py-3 text-left text-xs font-bold text-purple-800 uppercase tracking-wider">Exam</th>
                      <th class="px-4 py-3 text-left text-xs font-bold text-purple-800 uppercase tracking-wider">Subject</th>
                      <th class="px-4 py-3 text-left text-xs font-bold text-purple-800 uppercase tracking-wider">Marks</th>
                      <th class="px-4 py-3 text-left text-xs font-bold text-purple-800 uppercase tracking-wider rounded-tr-lg">Grade</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-purple-100">
                    <tr v-for="(result, index) in viewData.results" :key="result.id" :class="index % 2 === 0 ? 'bg-white' : 'bg-purple-50'">
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ result.year }}</td>
                      <td class="px-4 py-3 text-sm text-gray-700">{{ result.term }}</td>
                      <td class="px-4 py-3 text-sm text-gray-700">{{ result.exam_type }}</td>
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ result.subject_name }}</td>
                      <td class="px-4 py-3 text-sm font-bold text-purple-700">{{ result.marks }}</td>
                      <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold" :class="getGradeColor(result.grade)">
                          {{ result.grade }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div v-else class="text-gray-500 text-center py-4 bg-gray-50 rounded-lg">No academic results available</div>
          </div>
        </div>
      </div>
    </div>

    <!-- PDF Viewer Modal -->
    <div v-if="showPDFViewerModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl h-[95vh] overflow-hidden flex flex-col">s
        <div class="bg-gradient-to-r from-purple-600 to-indigo-700 p-4 rounded-t-2xl flex justify-between items-center">
          <h2 class="text-xl font-bold text-white">PDF Viewer</h2>
          <button @click="showPDFViewerModal = false" class="text-white hover:text-purple-200 transition-colors bg-white/10 hover:bg-white/20 rounded-full p-2" title="Close">
            <X class="w-6 h-6" />
          </button>
        </div>
       <div class="flex-1 overflow-hidden bg-gray-100" style="height: 90vh;">
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
import { ref, onMounted, computed, watch } from 'vue';
import api from '../services/api.js';
import { classesAPI } from '../services/api';
import { FileText, Upload, X, Download, File, AlertCircle, User, Calendar, MapPin, Phone, Mail, GraduationCap, BookOpen, Award } from 'lucide-vue-next';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import * as XLSX from 'xlsx';

const students = ref([]);
const allClasses = ref([]);
const streams = ref([]);
const searchQuery = ref('');
const loading = ref(false);
const errorMessage = ref('');

const filters = ref({
  class: '',
  stream: '',
  level: ''
});

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
  : 'https://stmark.sc.ug/accademics/backend/';

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
  if (!grade) return 'bg-gray-100 text-gray-800';
  const gradeUpper = grade.toUpperCase();
  if (['A', 'A+', 'A*'].includes(gradeUpper)) return 'bg-green-100 text-green-800';
  if (['B', 'B+'].includes(gradeUpper)) return 'bg-blue-100 text-blue-800';
  if (['C', 'C+'].includes(gradeUpper)) return 'bg-yellow-100 text-yellow-800';
  if (['D', 'D+'].includes(gradeUpper)) return 'bg-orange-100 text-orange-800';
  if (['E', 'F', 'F9'].includes(gradeUpper)) return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-800';
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
  errorMessage.value = '';

  try {
    const params = new URLSearchParams({
      page: String(pagination.value.page),
      limit: String(pagination.value.limit),
      class: filters.value.class,
      stream: filters.value.stream,
      level: filters.value.level
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

    if (showEditModal.value && editingId.value) {
      payload.append('id', String(editingId.value));
      await api.post('students.php?action=update', payload);
    } else {
      await api.post('students.php?action=create', payload);
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
      fillColor: [59, 130, 246],
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
      level: filters.value.level
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
      'Parents': student.parent_count || 0
    }));

    const worksheet = XLSX.utils.json_to_sheet(rows);
    worksheet['!cols'] = [
      { wch: 5 }, { wch: 15 }, { wch: 25 }, { wch: 10 }, { wch: 14 },
      { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 16 }, { wch: 14 },
      { wch: 25 }, { wch: 10 }
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

const downloadStudentPDF = () => {
  if (!viewData.value || !viewData.value.student) return;
  
  const student = viewData.value.student;
  const parents = viewData.value.parents || [];
  const doc = new jsPDF();
  
  let yPos = 20;
  
  // Header
  doc.setFontSize(20);
  doc.setTextColor(59, 130, 246);
  doc.text('Student Profile', 14, yPos);
  yPos += 10;
  
  // Subtitle with admission number
  doc.setFontSize(12);
  doc.setTextColor(100);
  doc.text(`Admission No: ${student.admission_number}`, 14, yPos);
  yPos += 10;
  
  doc.text(`Generated: ${new Date().toLocaleDateString()}`, 14, yPos);
  yPos += 15;
  
  // Student Information Section
  doc.setFillColor(245, 245, 245);
  doc.rect(14, yPos, 182, 8, 'F');
  doc.setFontSize(14);
  doc.setTextColor(59, 130, 246);
  doc.text('Student Information', 16, yPos + 6);
  yPos += 12;
  
  doc.setFontSize(10);
  doc.setTextColor(0);
  
  const studentInfo = [
    ['Full Name:', student.full_name],
    ['Gender:', student.gender],
    ['Date of Birth:', student.date_of_birth],
    ['Class:', student.class],
    ['Stream:', student.stream],
    ['Level:', student.level],
    ['Enrollment Date:', student.enrollment_date],
    ['Former School:', student.former_school || 'N/A']
  ];
  
  studentInfo.forEach((info, index) => {
    doc.setFont(undefined, 'bold');
    doc.text(info[0], 16, yPos);
    doc.setFont(undefined, 'normal');
    doc.text(info[1], 60, yPos);
    yPos += 7;
  });
  
  yPos += 5;
  
  // Behaviour Notes
  if (student.behaviour_notes) {
    doc.setFontSize(10);
    doc.setFont(undefined, 'bold');
    doc.text('Behaviour Notes:', 16, yPos);
    yPos += 5;
    doc.setFont(undefined, 'normal');
    const splitNotes = doc.splitTextToSize(student.behaviour_notes, 170);
    doc.text(splitNotes, 16, yPos);
    yPos += splitNotes.length * 5 + 5;
  }
  
  // Medical Notes
  if (student.medical_notes) {
    doc.setFontSize(10);
    doc.setFont(undefined, 'bold');
    doc.text('Medical Notes:', 16, yPos);
    yPos += 5;
    doc.setFont(undefined, 'normal');
    const splitNotes = doc.splitTextToSize(student.medical_notes, 170);
    doc.text(splitNotes, 16, yPos);
    yPos += splitNotes.length * 5 + 5;
  }
  
  // Special Needs
  if (student.special_needs) {
    doc.setFontSize(10);
    doc.setFont(undefined, 'bold');
    doc.text('Special Needs:', 16, yPos);
    yPos += 5;
    doc.setFont(undefined, 'normal');
    const splitNotes = doc.splitTextToSize(student.special_needs, 170);
    doc.text(splitNotes, 16, yPos);
    yPos += splitNotes.length * 5 + 5;
  }
  
  // Check if we need a new page for parents
  if (yPos > 240) {
    doc.addPage();
    yPos = 20;
  }
  
  // Parent/Guardian Information Section
  doc.setFillColor(245, 245, 245);
  doc.rect(14, yPos, 182, 8, 'F');
  doc.setFontSize(14);
  doc.setTextColor(59, 130, 246);
  doc.text('Parent/Guardian Information', 16, yPos + 6);
  yPos += 12;
  
  if (parents.length > 0) {
    parents.forEach((parent, index) => {
      if (yPos > 240) {
        doc.addPage();
        yPos = 20;
      }
      
      doc.setFontSize(11);
      doc.setFont(undefined, 'bold');
      doc.setTextColor(0);
      doc.text(`Parent/Guardian ${index + 1}`, 16, yPos);
      yPos += 8;
      
      doc.setFontSize(10);
      doc.setFont(undefined, 'normal');
      
      const parentInfo = [
        ['Full Name:', parent.full_name],
        ['Relationship:', parent.relationship],
        ['Phone:', parent.phone],
        ['Email:', parent.email || 'N/A'],
        ['Address:', parent.address || 'N/A'],
        ['Primary Contact:', parent.is_primary_contact ? 'Yes' : 'No']
      ];
      
      parentInfo.forEach((info) => {
        doc.setFont(undefined, 'bold');
        doc.text(info[0], 20, yPos);
        doc.setFont(undefined, 'normal');
        doc.text(info[1], 60, yPos);
        yPos += 6;
      });
      
      yPos += 5;
    });
  } else {
    doc.setFontSize(10);
    doc.setTextColor(150);
    doc.text('No parent/guardian information available', 16, yPos);
    yPos += 7;
  }
  
  // Documents Section
  const hasFormerDocs = student.former_school_documents && student.former_school_documents.length > 0;
  const hasBehaviourDocs = student.behaviour_documents && student.behaviour_documents.length > 0;
  
  if (hasFormerDocs || hasBehaviourDocs) {
    if (yPos > 240) {
      doc.addPage();
      yPos = 20;
    }
    
    doc.setFillColor(245, 245, 245);
    doc.rect(14, yPos, 182, 8, 'F');
    doc.setFontSize(14);
    doc.setTextColor(59, 130, 246);
    doc.text('Documents', 16, yPos + 6);
    yPos += 12;
    
    doc.setFontSize(10);
    doc.setTextColor(0);
    
    if (hasFormerDocs) {
      doc.setFont(undefined, 'bold');
      doc.text('Former School Documents:', 16, yPos);
      yPos += 6;
      doc.setFont(undefined, 'normal');
      student.former_school_documents.forEach((doc) => {
        doc.text(`- ${doc.filename}`, 20, yPos);
        yPos += 5;
      });
      yPos += 3;
    }
    
    if (hasBehaviourDocs) {
      doc.setFont(undefined, 'bold');
      doc.text('Discipline Documents:', 16, yPos);
      yPos += 6;
      doc.setFont(undefined, 'normal');
      student.behaviour_documents.forEach((doc) => {
        doc.text(`- ${doc.filename}`, 20, yPos);
        yPos += 5;
      });
    }
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
  const safeName = student.full_name.replace(/[^a-zA-Z0-9]/g, '_');
  doc.save(`student-profile-${safeName}-${student.admission_number}.pdf`);
};

onMounted(() => {
  loadClasses();
  loadStudents();
});
</script>
