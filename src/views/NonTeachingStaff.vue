<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-slate-100 p-4 sm:p-6 lg:p-8">
    <!-- Toast Notifications -->
    <ToastBanner />

    <!-- Page Header -->
    <div class="mb-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-3xl sm:text-4xl font-bold bg-gradient-to-r from-blue-900 via-blue-700 to-blue-900 bg-clip-text text-transparent">
            Non-Teaching Staff Management
          </h1>
          <p class="mt-2 text-slate-600 text-sm sm:text-base">
            Complete HR system for managing non-teaching staff members
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button
            @click="openImportModal"
            class="px-4 py-2.5 bg-white border-2 border-blue-200 text-blue-700 rounded-xl font-semibold hover:bg-blue-50 hover:border-blue-300 transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Import
          </button>
          <button
            @click="exportToExcel"
            class="px-4 py-2.5 bg-white border-2 border-green-200 text-green-700 rounded-xl font-semibold hover:bg-green-50 hover:border-green-300 transition-all duration-200 shadow-sm hover:shadow-md flex items-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export
          </button>
          <button
            @click="openModal()"
            class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-200 shadow-lg hover:shadow-xl flex items-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Staff
          </button>
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
      <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border border-slate-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Total Staff</p>
            <p class="text-3xl font-bold text-slate-900 mt-2">{{ staff.length }}</p>
          </div>
          <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border border-slate-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Active Staff</p>
            <p class="text-3xl font-bold text-emerald-600 mt-2">{{ activeCount }}</p>
          </div>
          <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border border-slate-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Inactive Staff</p>
            <p class="text-3xl font-bold text-amber-600 mt-2">{{ inactiveCount }}</p>
          </div>
          <div class="w-14 h-14 bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-6 border border-slate-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-semibold text-slate-600 uppercase tracking-wide">Departments</p>
            <p class="text-3xl font-bold text-purple-600 mt-2">{{ departments.length }}</p>
          </div>
          <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 border border-slate-100">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-2">
          <label class="block text-sm font-semibold text-slate-700 mb-2">Search</label>
          <div class="relative">
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search by name, code, email, contact..."
              class="w-full pl-11 pr-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
            />
            <svg class="w-5 h-5 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Department</label>
          <select
            v-model="filters.department_id"
            class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
          >
            <option value="">All Departments</option>
            <option v-for="d in departments" :key="d.id" :value="String(d.id)">
              {{ d.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Employment Type</label>
          <select
            v-model="filters.employment_type"
            class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
          >
            <option
              v-for="option in employmentTypeOptions"
              :key="option.value || 'all-types'"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
          <select
            v-model="filters.staff_status"
            class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
          >
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="on_leave">On Leave</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Staff Table -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-slate-100">
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div class="text-center">
          <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent"></div>
          <p class="mt-4 text-slate-600 font-medium">Loading staff...</p>
        </div>
      </div>

      <div v-else-if="filteredStaff.length === 0" class="text-center py-20">
        <svg class="w-20 h-20 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
        </svg>
        <p class="text-slate-600 font-medium text-lg">No staff members found</p>
        <p class="text-slate-500 text-sm mt-2">Try adjusting your filters or add new staff</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gradient-to-r from-blue-900 via-blue-800 to-blue-900 text-white sticky top-0 z-10">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Photo</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Staff Code</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Full Name</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Gender</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Contact</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Email</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Department</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Position</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Employment</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Status</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Date Joined</th>
              <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="s in paginatedStaff"
              :key="s.id"
              class="hover:bg-blue-50 transition-colors duration-150"
            >
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="w-12 h-12 rounded-xl overflow-hidden bg-slate-100 border-2 border-slate-200 flex items-center justify-center">
                  <img
                    v-if="s.passport_photo"
                    :src="s.passport_photo"
                    alt="Profile"
                    class="w-full h-full object-cover"
                  />
                  <svg v-else class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-3 py-1.5 bg-blue-100 text-blue-800 rounded-lg text-sm font-bold">
                  {{ s.hr_code || 'N/A' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="font-semibold text-slate-900">{{ s.first_name }} {{ s.last_name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ s.gender || '—' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ s.phone_number || '—' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ s.email || '—' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ s.department_name || '—' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ s.role_name || '—' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                  :class="getEmploymentTypeBadgeClass(s)"
                >
                  {{ getEmploymentTypeLabel(s) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2.5 py-1 rounded-lg text-xs font-semibold"
                  :class="{
                    'bg-emerald-100 text-emerald-800': (s.staff_status || s.status) === 'active',
                    'bg-red-100 text-red-800': (s.staff_status || s.status) === 'inactive',
                    'bg-amber-100 text-amber-800': s.staff_status === 'on_leave'
                  }"
                >
                  {{ (s.staff_status || s.status || 'N/A').toUpperCase() }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                {{ s.date_joined || '—' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    @click="viewStaff(s)"
                    class="p-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors duration-150"
                    title="View Details"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                  <button
                    @click="openModal(s)"
                    class="p-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200 transition-colors duration-150"
                    title="Edit"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    @click="toggleStatus(s)"
                    class="p-2 rounded-lg transition-colors duration-150"
                    :class="(s.staff_status || s.status) === 'active' ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200'"
                    :title="(s.staff_status || s.status) === 'active' ? 'Deactivate' : 'Activate'"
                  >
                    <svg v-if="(s.staff_status || s.status) === 'active'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                  <button
                    @click="deleteStaff(s)"
                    class="p-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors duration-150"
                    title="Delete"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="filteredStaff.length > 0" class="bg-slate-50 px-6 py-4 border-t border-slate-200">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="text-sm text-slate-600">
            Showing <span class="font-semibold text-slate-900">{{ (page - 1) * perPage + 1 }}</span> to
            <span class="font-semibold text-slate-900">{{ Math.min(page * perPage, filteredStaff.length) }}</span> of
            <span class="font-semibold text-slate-900">{{ filteredStaff.length }}</span> results
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="page--"
              :disabled="page === 1"
              class="px-4 py-2 bg-white border-2 border-slate-200 rounded-lg font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
            >
              Previous
            </button>
            <div class="flex items-center gap-1">
              <button
                v-for="p in visiblePages"
                :key="p"
                @click="page = p"
                class="w-10 h-10 rounded-lg font-semibold transition-all duration-200"
                :class="page === p ? 'bg-blue-600 text-white shadow-lg' : 'bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-50'"
              >
                {{ p }}
              </button>
            </div>
            <button
              @click="page++"
              :disabled="page === totalPages"
              class="px-4 py-2 bg-white border-2 border-slate-200 rounded-lg font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showModal"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="closeModal"
        >
          <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-75 backdrop-blur-sm" @click="closeModal"></div>

            <div class="relative inline-block w-full max-w-5xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl">
              <!-- Modal Header -->
              <div class="sticky top-0 z-10 bg-gradient-to-r from-blue-900 via-blue-800 to-blue-900 px-8 py-6">
                <div class="flex items-center justify-between">
                  <h3 class="text-2xl font-bold text-white">
                    {{ form.id ? 'Edit Staff Member' : 'Add New Staff Member' }}
                  </h3>
                  <button
                    @click="closeModal"
                    class="text-white hover:bg-white hover:bg-opacity-20 rounded-xl p-2 transition-all duration-200"
                  >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Modal Body -->
              <form @submit.prevent="save" class="p-8 max-h-[70vh] overflow-y-auto">
                <!-- Personal Information -->
                <div class="mb-8">
                  <h4 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    Personal Information
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Profile Picture -->
                    <div class="md:col-span-2">
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Profile Picture</label>
                      <div class="flex items-center gap-6">
                        <div class="relative">
                          <div class="w-24 h-24 rounded-2xl overflow-hidden bg-slate-100 border-2 border-slate-200 flex items-center justify-center">
                            <img
                              v-if="form.existing_profile_picture"
                              :src="form.existing_profile_picture"
                              alt="Profile"
                              class="w-full h-full object-cover"
                            />
                            <img
                              v-else-if="form.profile_picture"
                              :src="URL.createObjectURL(form.profile_picture)"
                              alt="Profile Preview"
                              class="w-full h-full object-cover"
                            />
                            <svg v-else class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                          </div>
                          <button
                            v-if="form.existing_profile_picture || form.profile_picture"
                            @click="removeProfilePicture"
                            type="button"
                            class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                          </button>
                        </div>
                        <div>
                          <input
                            type="file"
                            ref="profilePictureInput"
                            @change="handleProfilePictureChange"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                            class="hidden"
                          />
                          <button
                            @click="$refs.profilePictureInput.click()"
                            type="button"
                            class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg font-semibold hover:bg-blue-200 transition-colors"
                          >
                            Choose Photo
                          </button>
                          <p class="text-xs text-slate-500 mt-2">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                        </div>
                      </div>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Full Name *</label>
                      <input
                        v-model.trim="form.full_name"
                        type="text"
                        required
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter full name"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Gender</label>
                      <select
                        v-model="form.gender"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
                      >
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Date of Birth</label>
                      <input
                        v-model="form.date_of_birth"
                        type="date"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">National ID</label>
                      <input
                        v-model.trim="form.national_id"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter national ID"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Nationality</label>
                      <input
                        v-model.trim="form.nationality"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter nationality"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Marital Status</label>
                      <select
                        v-model="form.marital_status"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
                      >
                        <option value="">Select Status</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Widowed">Widowed</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Religion</label>
                      <input
                        v-model.trim="form.religion"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter religion"
                      />
                    </div>
                  </div>
                </div>

                <!-- Contact Information -->
                <div class="mb-8">
                  <h4 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                    </div>
                    Contact Information
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                      <input
                        v-model.trim="form.email"
                        type="email"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="email@example.com"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Contact</label>
                      <input
                        v-model.trim="form.phone_number"
                        type="tel"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="+256 700 000000"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Alternative Phone</label>
                      <input
                        v-model.trim="form.alternative_phone"
                        type="tel"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="+256 700 000000"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">District</label>
                      <input
                        v-model.trim="form.district"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter district"
                      />
                    </div>
                    <div class="md:col-span-2">
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Address</label>
                      <textarea
                        v-model.trim="form.address"
                        rows="2"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none resize-none"
                        placeholder="Enter full address"
                      ></textarea>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Emergency Contact Name</label>
                      <input
                        v-model.trim="form.emergency_contact_name"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter emergency contact name"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Emergency Contact Phone</label>
                      <input
                        v-model.trim="form.emergency_contact_phone"
                        type="tel"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="+256 700 000000"
                      />
                    </div>
                  </div>
                </div>

                <!-- Employment Type -->
                <div class="mb-8">
                  <h4 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h0a4 4 0 014 4v2m-4-8a4 4 0 100-8 4 4 0 000 8zm6 0h4m-2-2v4" />
                      </svg>
                    </div>
                    Employment Type
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Employment Type</label>
                      <select
                        v-model="form.employment_type"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
                      >
                        <option
                          v-for="option in employmentTypeOptions"
                          :key="option.value"
                          :value="option.value"
                        >
                          {{ option.label }}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- Professional Information -->
                <div class="mb-8">
                  <h4 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                    </div>
                    Professional Information
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Department *</label>
                      <select
                        v-model="form.department_id"
                        required
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
                      >
                        <option value="">Select Department</option>
                        <option v-for="d in departments" :key="d.id" :value="String(d.id)">
                          {{ d.name }}
                        </option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Position *</label>
                      <select
                        v-model="form.role_id"
                        required
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
                      >
                        <option value="">Select Position</option>
                        <option v-for="r in filteredRoles" :key="r.id" :value="String(r.id)">
                          {{ r.name }}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- Education Information -->
                <div class="mb-8">
                  <h4 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                      </svg>
                    </div>
                    Education Information
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Qualification</label>
                      <input
                        v-model.trim="form.qualification"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="e.g., Bachelor's Degree"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">University</label>
                      <input
                        v-model.trim="form.university"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter university name"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Year Graduated</label>
                      <input
                        v-model.trim="form.year_graduated"
                        type="number"
                        min="1950"
                        :max="new Date().getFullYear()"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="e.g., 2020"
                      />
                    </div>
                  </div>
                </div>

                <!-- Employment & Financial Information -->
                <div class="mb-8">
                  <h4 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    Employment & Financial Information
                  </h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Date Joined</label>
                      <input
                        v-model="form.date_joined"
                        type="date"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Contract Start</label>
                      <input
                        v-model="form.contract_start"
                        type="date"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Salary</label>
                      <input
                        v-model.number="form.salary"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="0.00"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Bank Name</label>
                      <input
                        v-model.trim="form.bank_name"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter bank name"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Account Number</label>
                      <input
                        v-model.trim="form.account_number"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter account number"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">TIN Number</label>
                      <input
                        v-model.trim="form.tin_number"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter TIN number"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">NSSF Number</label>
                      <input
                        v-model.trim="form.nssf_number"
                        type="text"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none"
                        placeholder="Enter NSSF number"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                      <select
                        v-model="form.staff_status"
                        class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-100 transition-all duration-200 outline-none bg-white"
                      >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="on_leave">On Leave</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200">
                  <button
                    type="button"
                    @click="closeModal"
                    class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 transition-all duration-200"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    :disabled="saving"
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                  >
                    <svg v-if="saving" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ saving ? 'Saving...' : 'Save Staff' }}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- View Profile Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showViewModal"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="closeViewModal"
        >
          <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-75 backdrop-blur-sm" @click="closeViewModal"></div>

            <div class="relative inline-block w-full max-w-5xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl">
              <!-- Modal Header -->
              <div class="sticky top-0 z-10 bg-gradient-to-r from-blue-900 via-blue-800 to-blue-900 px-8 py-6">
                <div class="flex items-center justify-between">
                  <h3 class="text-2xl font-bold text-white">Staff Profile</h3>
                  <button
                    @click="closeViewModal"
                    class="text-white hover:bg-white hover:bg-opacity-20 rounded-xl p-2 transition-all duration-200"
                  >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Modal Body -->
              <div class="p-8 max-h-[70vh] overflow-y-auto">
                <div v-if="viewLoading" class="flex items-center justify-center py-20">
                  <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-blue-500 border-t-transparent"></div>
                    <p class="mt-4 text-slate-600 font-medium">Loading profile...</p>
                  </div>
                </div>

                <div v-else>
                  <!-- Profile Header -->
                  <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl p-6 mb-6">
                    <div class="flex items-center gap-6">
                      <div class="relative">
                        <div class="w-24 h-24 rounded-2xl overflow-hidden bg-gradient-to-br from-blue-600 to-blue-700 flex items-center justify-center text-white text-3xl font-bold shadow-lg">
                          <img
                            v-if="selectedStaff.passport_photo"
                            :src="selectedStaff.passport_photo"
                            alt="Profile"
                            class="w-full h-full object-cover"
                          />
                          <span v-else>{{ getInitials(selectedStaff.first_name, selectedStaff.last_name) }}</span>
                        </div>
                      </div>
                      <div class="flex-1">
                        <h4 class="text-2xl font-bold text-slate-900">
                          {{ selectedStaff.first_name }} {{ selectedStaff.last_name }}
                        </h4>
                        <p class="text-slate-600 mt-1">{{ selectedStaff.role_name || 'N/A' }}</p>
                        <div class="flex items-center gap-3 mt-3">
                          <span class="px-3 py-1.5 bg-blue-100 text-blue-800 rounded-lg text-sm font-bold">
                            {{ selectedStaff.hr_code || 'N/A' }}
                          </span>
                          <span
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold"
                            :class="{
                              'bg-emerald-100 text-emerald-800': (selectedStaff.staff_status || selectedStaff.status) === 'active',
                              'bg-red-100 text-red-800': (selectedStaff.staff_status || selectedStaff.status) === 'inactive',
                              'bg-amber-100 text-amber-800': selectedStaff.staff_status === 'on_leave'
                            }"
                          >
                            {{ (selectedStaff.staff_status || selectedStaff.status || 'N/A').toUpperCase() }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Profile Details -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Personal Information -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200">
                      <h5 class="text-lg font-bold text-slate-900 mb-4">Personal Information</h5>
                      <div class="space-y-3">
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Gender</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.gender || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Date of Birth</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.date_of_birth || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">National ID</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.national_id || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Nationality</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.nationality || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Marital Status</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.marital_status || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Religion</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.religion || '—' }}</p>
                        </div>
                      </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200">
                      <h5 class="text-lg font-bold text-slate-900 mb-4">Contact Information</h5>
                      <div class="space-y-3">
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Email</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.email || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Phone</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.phone_number || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Alternative Phone</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.alternative_phone || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">District</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.district || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Address</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.address || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Emergency Contact</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.emergency_contact || '—' }}</p>
                        </div>
                      </div>
                    </div>

                    <!-- Employment Information -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200">
                      <h5 class="text-lg font-bold text-slate-900 mb-4">Employment Information</h5>
                      <div class="space-y-3">
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Department</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.department_name || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Position</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.role_name || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Employment Type</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ getEmploymentTypeLabel(selectedStaff) }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Date Joined</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.date_joined || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Contract Start</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.contract_start || '—' }}</p>
                        </div>
                      </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200">
                      <h5 class="text-lg font-bold text-slate-900 mb-4">Financial Information</h5>
                      <div class="space-y-3">
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Salary</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.salary || '0' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Bank Name</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.bank_name || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Account Number</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.account_number || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">TIN Number</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.tin_number || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">NSSF Number</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.nssf_number || '—' }}</p>
                        </div>
                      </div>
                    </div>

                    <!-- Education Information -->
                    <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200">
                      <h5 class="text-lg font-bold text-slate-900 mb-4">Education Information</h5>
                      <div class="space-y-3">
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Qualification</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.qualification || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">University</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.university || '—' }}</p>
                        </div>
                        <div>
                          <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">Year Graduated</p>
                          <p class="text-sm font-semibold text-slate-900 mt-1">{{ selectedStaff.year_graduated || '—' }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Timestamps -->
                  <div class="mt-6 pt-6 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
                    <div>
                      <span class="font-semibold">Created:</span> {{ selectedStaff.created_at || '—' }}
                    </div>
                    <div>
                      <span class="font-semibold">Updated:</span> {{ selectedStaff.updated_at || '—' }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="bg-slate-50 px-8 py-4 border-t border-slate-200">
                <div class="flex items-center justify-end">
                  <button
                    @click="closeViewModal"
                    class="px-6 py-3 bg-slate-800 text-white rounded-xl font-semibold hover:bg-slate-900 transition-all duration-200"
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Import Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showImportModal"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="closeImportModal"
        >
          <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900 bg-opacity-75 backdrop-blur-sm" @click="closeImportModal"></div>

            <div class="relative inline-block w-full max-w-2xl my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-3xl">
              <!-- Modal Header -->
              <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-blue-900 px-8 py-6">
                <div class="flex items-center justify-between">
                  <h3 class="text-2xl font-bold text-white">Import Staff Data</h3>
                  <button
                    @click="closeImportModal"
                    class="text-white hover:bg-white hover:bg-opacity-20 rounded-xl p-2 transition-all duration-200"
                  >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Modal Body -->
              <div class="p-8">
                <div class="text-center mb-6">
                  <div class="w-20 h-20 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                  </div>
                  <p class="text-slate-600">Upload CSV or JSON file to import staff data</p>
                </div>

                <div class="border-2 border-dashed border-slate-300 rounded-2xl p-8 text-center hover:border-blue-400 transition-colors duration-200">
                  <input
                    type="file"
                    ref="fileInput"
                    accept=".csv,.json"
                    @change="handleFileUpload"
                    class="hidden"
                  />
                  <button
                    @click="$refs.fileInput.click()"
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-200 shadow-lg hover:shadow-xl"
                  >
                    Choose File
                  </button>
                  <p class="mt-4 text-sm text-slate-500">Supported formats: CSV, JSON</p>
                </div>

                <div v-if="importFile" class="mt-6 bg-blue-50 rounded-2xl p-4 border border-blue-200">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <div>
                        <p class="font-semibold text-slate-900">{{ importFile.name }}</p>
                        <p class="text-sm text-slate-600">{{ (importFile.size / 1024).toFixed(2) }} KB</p>
                      </div>
                    </div>
                    <button
                      @click="importFile = null"
                      class="text-red-600 hover:text-red-700"
                    >
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Modal Footer -->
              <div class="bg-slate-50 px-8 py-4 border-t border-slate-200">
                <div class="flex items-center justify-end gap-3">
                  <button
                    @click="closeImportModal"
                    class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 transition-all duration-200"
                  >
                    Cancel
                  </button>
                  <button
                    @click="processImport"
                    :disabled="!importFile || importing"
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                  >
                    <svg v-if="importing" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ importing ? 'Importing...' : 'Import Data' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { nonTeachingStaffAPI, departmentsAPI, rolesAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import ToastBanner from '../components/hr/ToastBanner.vue';

const { showToast } = useToast();

// State
const loading = ref(false);
const saving = ref(false);
const importing = ref(false);
const showModal = ref(false);
const showViewModal = ref(false);
const showImportModal = ref(false);
const viewLoading = ref(false);

const staff = ref([]);
const departments = ref([]);
const roles = ref([]);
const selectedStaff = ref({});
const importFile = ref(null);

const page = ref(1);
const perPage = 10;

const filters = ref({
  search: '',
  department_id: '',
  employment_type: '',
  staff_status: '',
});

const employmentTypeOptions = [
  { value: '', label: 'All Types' },
  { value: 'permanent', label: 'Full-time' },
  { value: 'contract', label: 'Contract' },
  { value: 'part_time', label: 'Part-time' },
  { value: 'intern', label: 'Intern' },
  { value: 'volunteer', label: 'Volunteer' },
];

// Form
const emptyForm = () => ({
  id: null,
  first_name: '',
  last_name: '',
  full_name: '',
  gender: '',
  date_of_birth: '',
  national_id: '',
  nationality: '',
  marital_status: '',
  religion: '',
  email: '',
  phone_number: '',
  alternative_phone: '',
  address: '',
  district: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  department_id: '',
  role_id: '',
  employment_type: '',
  qualification: '',
  university: '',
  year_graduated: '',
  date_joined: '',
  contract_start: '',
  salary: 0,
  bank_name: '',
  account_number: '',
  tin_number: '',
  nssf_number: '',
  staff_status: 'active',
  profile_picture: null,
  existing_profile_picture: '',
});

const form = ref(emptyForm());

// Computed
const activeCount = computed(() =>
  staff.value.filter((s) => (s.staff_status || s.status) === 'active').length
);

const inactiveCount = computed(() =>
  staff.value.filter((s) => (s.staff_status || s.status) === 'inactive').length
);

const filteredStaff = computed(() => {
  let result = [...staff.value];

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase();
    result = result.filter((s) =>
      (s.hr_code || '').toLowerCase().includes(search) ||
      (s.first_name || '').toLowerCase().includes(search) ||
      (s.last_name || '').toLowerCase().includes(search) ||
      (s.email || '').toLowerCase().includes(search) ||
      (s.phone_number || '').toLowerCase().includes(search) ||
      (s.department_name || '').toLowerCase().includes(search) ||
      (s.role_name || '').toLowerCase().includes(search)
    );
  }

  if (filters.value.department_id) {
    result = result.filter((s) => String(s.department_id) === filters.value.department_id);
  }

  if (filters.value.employment_type) {
    result = result.filter((s) => s.employment_type === filters.value.employment_type);
  }

  if (filters.value.staff_status) {
    result = result.filter((s) => (s.staff_status || s.status) === filters.value.staff_status);
  }

  return result;
});

const paginatedStaff = computed(() => {
  const start = (page.value - 1) * perPage;
  return filteredStaff.value.slice(start, start + perPage);
});

const totalPages = computed(() => Math.ceil(filteredStaff.value.length / perPage));

const visiblePages = computed(() => {
  const pages = [];
  const maxVisible = 5;
  let start = Math.max(1, page.value - Math.floor(maxVisible / 2));
  let end = Math.min(totalPages.value, start + maxVisible - 1);

  if (end - start < maxVisible - 1) {
    start = Math.max(1, end - maxVisible + 1);
  }

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  return pages;
});

const filteredRoles = computed(() => {
  if (!form.value.department_id) return roles.value;
  return roles.value.filter(
    (r) => !r.department_id || String(r.department_id) === String(form.value.department_id)
  );
});

// Watch role_id to auto-populate department_id
watch(() => form.value.role_id, (newRoleId) => {
  if (newRoleId && newRoleId !== '') {
    const selectedRole = roles.value.find(r => String(r.id) === String(newRoleId));
    if (selectedRole && selectedRole.department_id) {
      form.value.department_id = String(selectedRole.department_id);
    }
  }
});

// Methods
const load = async () => {
  loading.value = true;
  try {
    const res = await nonTeachingStaffAPI.getAll(filters.value);
    staff.value = res.success ? res.data || [] : [];
  } catch (e) {
    showToast(e.response?.data?.error || 'Failed to load staff', 'error');
  } finally {
    loading.value = false;
  }
};

const loadMeta = async () => {
  try {
    const [d, r] = await Promise.all([departmentsAPI.getAll(), rolesAPI.getAll()]);
    if (d.success) departments.value = d.data || [];
    if (r.success) roles.value = r.data || [];
  } catch (e) {
    showToast(e.response?.data?.error || 'Failed to load lookup data', 'error');
  }
};

const openModal = (row = null) => {
  if (row) {
    const fullName = row.full_name || `${row.first_name || ''} ${row.last_name || ''}`.trim();
    const nameParts = fullName.split(' ');
    const firstName = row.first_name || nameParts[0] || '';
    const lastName = row.last_name || nameParts.slice(1).join(' ') || '';

    const emergencyContact = row.emergency_contact || '';
    const emergencyParts = emergencyContact.split(' ').filter((p) => p.trim());
    const emergencyName = emergencyParts[0] || '';
    const emergencyPhone = emergencyParts.slice(1).join(' ') || '';

    console.log('Opening modal with row data:', row);

    form.value = {
      id: row.id ?? null,
      first_name: firstName,
      last_name: lastName,
      full_name: fullName,
      gender: row.gender ?? '',
      date_of_birth: row.date_of_birth ?? '',
      national_id: row.national_id ?? '',
      nationality: row.nationality ?? '',
      marital_status: row.marital_status ?? '',
      religion: row.religion ?? '',
      email: row.email ?? '',
      phone_number: row.phone_number ?? '',
      alternative_phone: row.alternative_phone ?? '',
      address: row.address ?? '',
      district: row.district ?? '',
      emergency_contact_name: emergencyName,
      emergency_contact_phone: emergencyPhone,
      department_id: row.department_id ? String(row.department_id) : '',
      role_id: row.role_id ? String(row.role_id) : '',
      employment_type: row.employment_type || '',
      qualification: row.qualification ?? '',
      university: row.university ?? '',
      year_graduated: row.year_graduated ?? '',
      date_joined: row.date_joined ?? '',
      contract_start: row.contract_start ?? '',
      salary: row.salary ?? 0,
      bank_name: row.bank_name ?? '',
      account_number: row.account_number ?? '',
      tin_number: row.tin_number ?? '',
      nssf_number: row.nssf_number ?? '',
      staff_status: row.staff_status || row.status || 'active',
      profile_picture: null,
      existing_profile_picture: row.passport_photo ?? '',
    };

    console.log('Form value after population:', form.value);
  } else {
    form.value = emptyForm();
  }

  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  form.value = emptyForm();
};

const handleProfilePictureChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      showToast('File size exceeds 5MB limit', 'error');
      return;
    }
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      showToast('Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed', 'error');
      return;
    }
    form.value.profile_picture = file;
  }
};

const removeProfilePicture = () => {
  form.value.profile_picture = null;
  form.value.existing_profile_picture = '';
  if (profilePictureInput.value) {
    profilePictureInput.value.value = '';
  }
};

const profilePictureInput = ref(null);

const viewStaff = async (row) => {
  selectedStaff.value = { ...row };
  showViewModal.value = true;
  viewLoading.value = true;

  try {
    const res = await nonTeachingStaffAPI.getById(row.id);
    if (res.success && res.data) {
      selectedStaff.value = res.data;
    }
  } catch (e) {
    showToast(e.response?.data?.error || 'Failed to load employee details', 'error');
  } finally {
    viewLoading.value = false;
  }
};

const closeViewModal = () => {
  showViewModal.value = false;
  viewLoading.value = false;
  selectedStaff.value = {};
};

const buildPayload = () => {
  const nameParts = (form.value.full_name || '').trim().split(' ');
  const firstName = nameParts[0] || '';
  const lastName = nameParts.slice(1).join(' ') || '';

  const emergencyContact = `${form.value.emergency_contact_name || ''} ${form.value.emergency_contact_phone || ''}`.trim();

  return {
    id: form.value.id,
    first_name: firstName,
    last_name: lastName,
    gender: form.value.gender,
    date_of_birth: form.value.date_of_birth,
    national_id: form.value.national_id,
    nationality: form.value.nationality,
    marital_status: form.value.marital_status,
    religion: form.value.religion,
    email: form.value.email,
    phone_number: form.value.phone_number,
    alternative_phone: form.value.alternative_phone,
    address: form.value.address,
    district: form.value.district,
    emergency_contact: emergencyContact,
    department_id: form.value.department_id ? Number(form.value.department_id) : null,
    role_id: form.value.role_id ? Number(form.value.role_id) : null,
    employment_type: form.value.employment_type || 'permanent',
    qualification: form.value.qualification,
    university: form.value.university,
    year_graduated: form.value.year_graduated,
    date_joined: form.value.date_joined,
    contract_start: form.value.contract_start,
    salary: form.value.salary === '' || form.value.salary === null ? 0 : Number(form.value.salary),
    bank_name: form.value.bank_name,
    account_number: form.value.account_number,
    tin_number: form.value.tin_number,
    nssf_number: form.value.nssf_number,
    staff_status: form.value.staff_status,
    existing_profile_picture: form.value.existing_profile_picture,
  };
};

const save = async () => {
  saving.value = true;

  try {
    const payload = buildPayload();

    if (!payload.first_name || !payload.last_name) {
      showToast('First name and last name are required', 'error');
      saving.value = false;
      return;
    }

    if (!payload.department_id || !payload.role_id) {
      showToast('Department and position are required', 'error');
      saving.value = false;
      return;
    }

    console.log('Saving payload:', payload);

    let res;
    if (form.value.profile_picture) {
      const formData = new FormData();
      Object.keys(payload).forEach((key) => {
        formData.append(key, payload[key]);
      });
      formData.append('profile_picture', form.value.profile_picture);

      if (payload.id) {
        res = await nonTeachingStaffAPI.update(formData);
      } else {
        res = await nonTeachingStaffAPI.create(formData);
      }
    } else {
      if (payload.id) {
        res = await nonTeachingStaffAPI.update(payload);
      } else {
        res = await nonTeachingStaffAPI.create(payload);
      }
    }

    console.log('Save response:', res);

    if (res.success) {
      showToast(res.message || 'Saved successfully', 'success');
      closeModal();
      await load();
    } else {
      showToast(res.error || res.message || 'Save failed', 'error');
    }
  } catch (e) {
    console.error('Save error:', e);
    const message =
      e.response?.data?.details ||
      e.response?.data?.error ||
      e.message ||
      'Save failed';
    showToast(message, 'error');
  } finally {
    saving.value = false;
  }
};

const toggleStatus = async (row) => {
  const currentStatus = row.staff_status || row.status;
  const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

  if (!confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this staff member?`)) {
    return;
  }

  try {
    const payload = {
      id: row.id,
      staff_status: newStatus,
      first_name: row.first_name,
      last_name: row.last_name,
      department_id: row.department_id,
      role_id: row.role_id,
      employment_type: row.employment_type,
      salary: row.salary,
      date_joined: row.date_joined,
    };

    const res = await nonTeachingStaffAPI.update(payload);

    if (res.success) {
      showToast(`Staff ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully`, 'success');
      await load();
    } else {
      showToast(res.error || 'Failed to update status', 'error');
    }
  } catch (e) {
    showToast(e.response?.data?.error || 'Failed to update status', 'error');
  }
};

const deleteStaff = async (row) => {
  if (!confirm(`Are you sure you want to delete ${row.first_name} ${row.last_name}? This action cannot be undone.`)) {
    return;
  }

  try {
    const res = await nonTeachingStaffAPI.delete(row.id);

    if (res.success) {
      showToast('Staff deleted successfully', 'success');
      await load();
    } else {
      showToast(res.error || 'Failed to delete staff', 'error');
    }
  } catch (e) {
    showToast(e.response?.data?.error || 'Failed to delete staff', 'error');
  }
};

const exportToExcel = () => {
  const headers = [
    'Staff Code',
    'Full Name',
    'Gender',
    'Contact',
    'Email',
    'Department',
    'Position',
    'Employment Type',
    'Status',
    'Date Joined',
  ];

  const rows = filteredStaff.value.map((s) => [
    s.hr_code || '',
    `${s.first_name} ${s.last_name}`,
    s.gender || '',
    s.phone_number || '',
    s.email || '',
    s.department_name || '',
    s.role_name || '',
    getEmploymentTypeLabel(s) || '',
    (s.staff_status || s.status || '').toUpperCase(),
    s.date_joined || '',
  ]);

  let csv = headers.join(',') + '\n';
  rows.forEach((row) => {
    csv += row.map((cell) => `"${cell}"`).join(',') + '\n';
  });

  const blob = new Blob([csv], { type: 'text/csv' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `non-teaching-staff-${new Date().toISOString().split('T')[0]}.csv`;
  a.click();
  window.URL.revokeObjectURL(url);

  showToast('Data exported successfully', 'success');
};

const openImportModal = () => {
  showImportModal.value = true;
};

const closeImportModal = () => {
  showImportModal.value = false;
  importFile.value = null;
};

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    importFile.value = file;
  }
};

const processImport = async () => {
  if (!importFile.value) return;

  importing.value = true;

  try {
    showToast('Import functionality coming soon', 'info');
    closeImportModal();
  } catch (e) {
    showToast(e.response?.data?.error || 'Import failed', 'error');
  } finally {
    importing.value = false;
  }
};

const getInitials = (firstName, lastName) => {
  const first = (firstName || '').charAt(0).toUpperCase();
  const last = (lastName || '').charAt(0).toUpperCase();
  return first + last || 'NA';
};

const normalizeEmploymentType = (value) => {
  if (value === null || value === undefined || value === '') return null;

  const raw = String(value).trim().toLowerCase().replace(/[_\s]+/g, '-');

  switch (raw) {
    case 'full-time':
    case 'fulltime':
    case 'permanent':
      return 'permanent';
    case 'part-time':
    case 'parttime':
    case 'part_time':
      return 'part_time';
    case 'contract':
      return 'contract';
    case 'temporary':
    case 'casual':
    case 'probation':
      return 'temporary';
    case 'intern':
    case 'internship':
      return 'intern';
    case 'volunteer':
      return 'volunteer';
    default:
      return String(value).trim();
  }
};

const getEmploymentTypeKey = (row) =>
  row?.employment_type ??
  row?.employment ??
  row?.employmentType ??
  row?.employment_type_name ??
  row?.employment_status ??
  row?.staff_type ??
  '';

const getEmploymentTypeLabel = (row) => {
  const type = getEmploymentTypeKey(row);
  const option = employmentTypeOptions.find(opt => opt.value === type);
  return option?.label || type || 'N/A';
};

const getEmploymentTypeBadgeClass = (row) => {
  const key = getEmploymentTypeKey(row);

  if (key === 'permanent') {
    return 'bg-green-100 text-green-800';
  }
  if (key === 'contract') {
    return 'bg-blue-100 text-blue-800';
  }
  if (key === 'temporary') {
    return 'bg-amber-100 text-amber-800';
  }
  if (key === 'intern') {
    return 'bg-purple-100 text-purple-800';
  }
  if (key === 'volunteer') {
    return 'bg-pink-100 text-pink-800';
  }

  return 'bg-slate-100 text-slate-700';
};

// Watchers
watch(
  () => filters.value.search,
  () => {
    page.value = 1;
  }
);

watch(
  () => filters.value.department_id,
  () => {
    page.value = 1;
    load();
  }
);

watch(
  () => filters.value.employment_type,
  () => {
    page.value = 1;
  }
);

watch(
  () => filters.value.staff_status,
  () => {
    page.value = 1;
    load();
  }
);

// Debounced search
let searchTimeout;
watch(
  () => filters.value.search,
  () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      load();
    }, 500);
  }
);

// Lifecycle
onMounted(async () => {
  await loadMeta();
  await load();
});
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .modal-content,
.modal-leave-active .modal-content {
  transition: transform 0.3s ease;
}

.modal-enter-from .modal-content,
.modal-leave-to .modal-content {
  transform: scale(0.9);
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>