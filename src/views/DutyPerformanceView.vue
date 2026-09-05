<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-blue-100 p-6">
    <div class="mb-6">
      <button @click="goBack" class="flex items-center gap-2 text-slate-600 hover:text-blue-600 transition-colors font-medium">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Duty Performance
      </button>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="text-slate-500">Loading evaluation details...</div>
    </div>

    <div v-else-if="error" class="rounded-2xl border border-rose-200/60 bg-gradient-to-r from-rose-50 to-pink-50 p-6 text-center">
      <div class="text-rose-700 font-semibold">{{ error }}</div>
    </div>

    <div v-else-if="evaluationData" class="space-y-8">
      <!-- Header Info -->
      <div class="rounded-3xl border border-white/20 bg-gradient-to-br from-white via-blue-50 to-blue-100 p-8 shadow-2xl backdrop-blur-sm">
        <div class="grid gap-8 sm:grid-cols-4">
          <div class="text-center">
            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-blue-600 via-blue-500 to-blue-400 text-4xl font-bold text-white shadow-2xl mx-auto ring-4 ring-white/50">
              {{ evaluationData.teacher_name?.charAt(0) || '?' }}
            </div>
            <div class="font-bold text-slate-900 text-xl mt-4">{{ evaluationData.teacher_name }}</div>
            <div class="text-slate-500">Teacher</div>
          </div>
          <div class="text-center">
            <div class="text-5xl font-bold bg-gradient-to-r from-blue-700 to-blue-500 bg-clip-text text-transparent">{{ evaluationData.total_score }}</div>
            <div class="text-slate-500 text-lg mt-2">Total Score</div>
            <div class="text-slate-400">/100</div>
          </div>
          <div class="text-center">
            <div class="text-5xl font-bold bg-gradient-to-r from-blue-700 to-blue-600 bg-clip-text text-transparent">{{ evaluationData.percentage }}%</div>
            <div class="text-slate-500 text-lg mt-2">Percentage</div>
          </div>
          <div class="text-center">
            <div class="inline-flex items-center rounded-full px-6 py-3 text-lg font-semibold shadow-lg ring-2 ring-white/50" :class="getStatusBadgeClass(evaluationData.percentage)">
              {{ getStatusFromPercentage(evaluationData.percentage) }}
            </div>
            <div class="text-slate-500 text-lg mt-3">Status</div>
          </div>
        </div>
      </div>

      <!-- Evaluation Info -->
      <div class="rounded-2xl border border-white/20 bg-gradient-to-br from-white to-blue-50 p-6 shadow-lg backdrop-blur-sm">
        <h3 class="text-lg font-bold text-slate-900 mb-4">Evaluation Information</h3>
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 p-4 border border-blue-100">
            <label class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Year</label>
            <div class="font-semibold text-slate-900 text-lg mt-1">{{ evaluationData.year }}</div>
          </div>
          <div class="rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 p-4 border border-blue-100">
            <label class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Term</label>
            <div class="font-semibold text-slate-900 text-lg mt-1">Term {{ evaluationData.term }}</div>
          </div>
          <div class="rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 p-4 border border-blue-100">
            <label class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Week</label>
            <div class="font-semibold text-slate-900 text-lg mt-1">Week {{ evaluationData.week_number }}</div>
          </div>
        </div>
      </div>

      <!-- Category Scores Table -->
      <div>
        <h3 class="text-2xl font-bold text-slate-900 mb-6">Performance Breakdown</h3>
        <div class="rounded-2xl border border-white/20 bg-white overflow-hidden shadow-2xl backdrop-blur-sm">
          <table class="w-full">
            <thead class="bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400">
              <tr>
                <th class="text-left py-5 px-8 font-bold text-white text-sm uppercase tracking-wider">Category</th>
                <th class="text-center py-5 px-8 font-bold text-white text-sm uppercase tracking-wider w-40">Score</th>
                <th class="text-center py-5 px-8 font-bold text-white text-sm uppercase tracking-wider w-40">Percentage</th>
                <th class="text-center py-5 px-8 font-bold text-white text-sm uppercase tracking-wider w-40">Progress</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="py-6 px-8">
                  <div class="flex items-center gap-4">
                    <span class="text-3xl">⏰</span>
                    <div>
                      <div class="font-semibold text-slate-900 text-lg">Time Management & Availability</div>
                      <div class="text-sm text-slate-500">IN SCHOOL</div>
                    </div>
                  </div>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="inline-flex items-center rounded-xl bg-blue-100 px-6 py-3 font-bold text-blue-700 text-xl">{{ evaluationData.punctuality }}/20</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="font-semibold text-slate-700 text-lg">{{ ((evaluationData.punctuality / 20) * 100).toFixed(0) }}%</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <div class="w-full bg-slate-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full" :style="{ width: ((evaluationData.punctuality / 20) * 100) + '%' }"></div>
                  </div>
                </td>
              </tr>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="py-6 px-8">
                  <div class="flex items-center gap-4">
                    <span class="text-3xl">👁</span>
                    <div class="font-semibold text-slate-900 text-lg">Meals Supervision</div>
                  </div>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="inline-flex items-center rounded-xl bg-blue-100 px-6 py-3 font-bold text-blue-700 text-xl">{{ evaluationData.supervision }}/20</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="font-semibold text-slate-700 text-lg">{{ ((evaluationData.supervision / 20) * 100).toFixed(0) }}%</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <div class="w-full bg-slate-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full" :style="{ width: ((evaluationData.supervision / 20) * 100) + '%' }"></div>
                  </div>
                </td>
              </tr>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="py-6 px-8">
                  <div class="flex items-center gap-4">
                    <span class="text-3xl">🧹</span>
                    <div class="font-semibold text-slate-900 text-lg">Compound Cleanliness</div>
                  </div>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="inline-flex items-center rounded-xl bg-blue-100 px-6 py-3 font-bold text-blue-700 text-xl">{{ evaluationData.cleanliness }}/20</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="font-semibold text-slate-700 text-lg">{{ ((evaluationData.cleanliness / 20) * 100).toFixed(0) }}%</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <div class="w-full bg-slate-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full" :style="{ width: ((evaluationData.cleanliness / 20) * 100) + '%' }"></div>
                  </div>
                </td>
              </tr>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="py-6 px-8">
                  <div class="flex items-center gap-4">
                    <span class="text-3xl">📅</span>
                    <div>
                      <div class="font-semibold text-slate-900 text-lg">Order & Sanity</div>
                      <div class="text-sm text-slate-500">IN SCHOOL</div>
                    </div>
                  </div>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="inline-flex items-center rounded-xl bg-blue-100 px-6 py-3 font-bold text-blue-700 text-xl">{{ evaluationData.time_keeping }}/20</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="font-semibold text-slate-700 text-lg">{{ ((evaluationData.time_keeping / 20) * 100).toFixed(0) }}%</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <div class="w-full bg-slate-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full" :style="{ width: ((evaluationData.time_keeping / 20) * 100) + '%' }"></div>
                  </div>
                </td>
              </tr>
              <tr class="hover:bg-slate-50 transition-colors">
                <td class="py-6 px-8">
                  <div class="flex items-center gap-4">
                    <span class="text-3xl">🎯</span>
                    <div>
                      <div class="font-semibold text-slate-900 text-lg">School Programs Preparation</div>
                      <div class="text-sm text-slate-500">Assembly, Fellowship, Mentorship & Other Programs</div>
                    </div>
                  </div>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="inline-flex items-center rounded-xl bg-blue-100 px-6 py-3 font-bold text-blue-700 text-xl">{{ evaluationData.participation }}/20</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <span class="font-semibold text-slate-700 text-lg">{{ ((evaluationData.participation / 20) * 100).toFixed(0) }}%</span>
                </td>
                <td class="py-6 px-8 text-center">
                  <div class="w-full bg-slate-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full" :style="{ width: ((evaluationData.participation / 20) * 100) + '%' }"></div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Remarks Section -->
      <div>
        <h3 class="text-2xl font-bold text-slate-900 mb-6">Remarks & Feedback</h3>
        <div class="grid gap-6 sm:grid-cols-2">
          <div v-if="evaluationData.comment" class="rounded-2xl border border-white/20 bg-gradient-to-br from-white to-blue-50 p-6 shadow-lg backdrop-blur-sm ring-1 ring-blue-100">
            <h4 class="font-bold text-slate-900 mb-4 flex items-center gap-2 text-lg">
              <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
              </svg>
              Admin Remarks
            </h4>
            <p class="text-slate-700 leading-relaxed text-base">{{ evaluationData.comment }}</p>
          </div>
          <div v-if="evaluationData.areas_of_improvement" class="rounded-2xl border border-white/20 bg-gradient-to-br from-white to-amber-50 p-6 shadow-lg backdrop-blur-sm ring-1 ring-amber-100">
            <h4 class="font-bold text-slate-900 mb-4 flex items-center gap-2 text-lg">
              <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.5-5a5 5 0 00-5.072 0z" />
              </svg>
              Areas of Improvement
            </h4>
            <p class="text-slate-700 leading-relaxed text-base">{{ evaluationData.areas_of_improvement }}</p>
          </div>
          <div v-if="evaluationData.general_remarks" class="rounded-2xl border border-white/20 bg-gradient-to-br from-white to-blue-50 p-6 shadow-lg backdrop-blur-sm ring-1 ring-blue-100">
            <h4 class="font-bold text-slate-900 mb-4 flex items-center gap-2 text-lg">
              <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              General Remarks
            </h4>
            <p class="text-slate-700 leading-relaxed text-base">{{ evaluationData.general_remarks }}</p>
          </div>
          <div v-if="evaluationData.supervisor" class="rounded-2xl border border-white/20 bg-gradient-to-br from-white to-blue-50 p-6 shadow-lg backdrop-blur-sm ring-1 ring-blue-100">
            <h4 class="font-bold text-slate-900 mb-4 flex items-center gap-2 text-lg">
              <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Supervisor
            </h4>
            <p class="text-slate-700 leading-relaxed text-base">{{ evaluationData.supervisor }}</p>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-end gap-4 pt-6 border-t border-white/20">
        <button
          @click="printEvaluation"
          class="rounded-2xl border border-white/20 bg-white px-6 py-4 text-sm font-semibold text-slate-700 shadow-lg transition-all duration-200 hover:bg-slate-50 hover:shadow-xl flex items-center gap-2 ring-1 ring-slate-200"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          Print Evaluation
        </button>
        <button
          @click="goBack"
          class="rounded-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 px-8 py-4 text-sm font-semibold text-white shadow-2xl shadow-blue-500/40 transition-all duration-200 hover:shadow-3xl hover:shadow-blue-500/50 hover:scale-105 ring-2 ring-white/50"
        >
          Back to List
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { dutyPerformanceAPI } from '../services/api.js';

const route = useRoute();
const router = useRouter();

const evaluationData = ref(null);
const loading = ref(true);
const error = ref('');

const getStatusFromPercentage = (percentage) => {
  if (percentage >= 80) return 'Outstanding Performance';
  if (percentage >= 60) return 'Very Good Performance';
  if (percentage >= 50) return 'Satisfactory Performance';
  if (percentage >= 40) return 'Needs Improvement';
  return 'Poor Performance';
};

const getStatusTextColor = (percentage) => {
  if (percentage >= 80) return 'text-emerald-600';
  if (percentage >= 60) return 'text-blue-600';
  if (percentage >= 50) return 'text-amber-600';
  if (percentage >= 40) return 'text-orange-600';
  return 'text-rose-600';
};

const getStatusBadgeClass = (percentage) => {
  if (percentage >= 80) return 'bg-emerald-100 text-emerald-700';
  if (percentage >= 60) return 'bg-blue-100 text-blue-700';
  if (percentage >= 50) return 'bg-amber-100 text-amber-700';
  if (percentage >= 40) return 'bg-orange-100 text-orange-700';
  return 'bg-rose-100 text-rose-700';
};

const goBack = () => {
  router.push('/duty-performance');
};

const printEvaluation = () => {
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Duty Performance Evaluation</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 40px; max-width: 900px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .header p { color: #666; margin-top: 10px; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .summary-item { text-align: center; padding: 20px; background: #f5f5f5; border-radius: 10px; }
        .summary-item .value { font-size: 32px; font-weight: bold; color: #4f46e5; }
        .summary-item .label { color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .score { font-weight: bold; color: #4f46e5; }
        .comment-section { background: #f9fafb; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .comment-section h3 { margin-top: 0; color: #333; }
        @media print { body { padding: 20px; } }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>Duty Performance Evaluation Report</h1>
        <p>Teacher: ${evaluationData.value.teacher_name} | Year: ${evaluationData.value.year} | Term: ${evaluationData.value.term} | Week: ${evaluationData.value.week_number}</p>
      </div>
      <div class="summary">
        <div class="summary-item">
          <div class="value">${evaluationData.value.total_score}</div>
          <div class="label">Total Score</div>
        </div>
        <div class="summary-item">
          <div class="value">${evaluationData.value.percentage}%</div>
          <div class="label">Percentage</div>
        </div>
        <div class="summary-item">
          <div class="value">${getStatusFromPercentage(evaluationData.value.percentage)}</div>
          <div class="label">Status</div>
        </div>
        <div class="summary-item">
          <div class="value">${evaluationData.value.teacher_name?.charAt(0) || '?'}</div>
          <div class="label">Teacher Initial</div>
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Category</th>
            <th>Score</th>
            <th>Percentage</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Time Management & Availability</td>
            <td class="score">${evaluationData.value.punctuality}/20</td>
            <td>${((evaluationData.value.punctuality / 20) * 100).toFixed(0)}%</td>
          </tr>
          <tr>
            <td>Meals Supervision</td>
            <td class="score">${evaluationData.value.supervision}/20</td>
            <td>${((evaluationData.value.supervision / 20) * 100).toFixed(0)}%</td>
          </tr>
          <tr>
            <td>Compound Cleanliness</td>
            <td class="score">${evaluationData.value.cleanliness}/20</td>
            <td>${((evaluationData.value.cleanliness / 20) * 100).toFixed(0)}%</td>
          </tr>
          <tr>
            <td>Order & Sanity</td>
            <td class="score">${evaluationData.value.time_keeping}/20</td>
            <td>${((evaluationData.value.time_keeping / 20) * 100).toFixed(0)}%</td>
          </tr>
          <tr>
            <td>School Programs Preparation</td>
            <td class="score">${evaluationData.value.participation}/20</td>
            <td>${((evaluationData.value.participation / 20) * 100).toFixed(0)}%</td>
          </tr>
        </tbody>
      </table>
      ${evaluationData.value.comment ? `<div class="comment-section"><h3>Admin Remarks</h3><p>${evaluationData.value.comment}</p></div>` : ''}
      ${evaluationData.value.areas_of_improvement ? `<div class="comment-section"><h3>Areas of Improvement</h3><p>${evaluationData.value.areas_of_improvement}</p></div>` : ''}
      ${evaluationData.value.general_remarks ? `<div class="comment-section"><h3>General Remarks</h3><p>${evaluationData.value.general_remarks}</p></div>` : ''}
      ${evaluationData.value.supervisor ? `<div class="comment-section"><h3>Supervisor</h3><p>${evaluationData.value.supervisor}</p></div>` : ''}
      <script>window.print();<\/script>
    </body>
    </html>
  `);
  printWindow.document.close();
};

onMounted(async () => {
  const id = route.params.id;
  try {
    const response = await dutyPerformanceAPI.getById(id);
    console.log('API Response:', response);
    
    if (response.success && response.data) {
      evaluationData.value = response.data;
      console.log('Found Record:', evaluationData.value);
    } else {
      error.value = 'Evaluation record not found';
    }
  } catch (err) {
    console.error('Failed to load evaluation:', err);
    error.value = 'Failed to load evaluation details: ' + (err.message || 'Unknown error');
  } finally {
    loading.value = false;
  }
});
</script>
