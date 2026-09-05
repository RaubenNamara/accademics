<template>
  <div class="space-y-6">
    <div class="rounded-3xl bg-gradient-to-r from-slate-950 via-slate-900 to-blue-900 p-6 text-white shadow-2xl">
      <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.25em] text-blue-200">
            Academic & HR Dashboard
          </p>
          <h1 class="mt-2 text-3xl font-bold">Performance Overview</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-300">
            Track teacher performance, duty scores, monitoring, and term summaries in one place.
          </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
          <select v-model="selectedYear" @change="loadDashboard" class="dashboard-select">
            <option v-for="year in years" :key="year" :value="year">
              {{ year }}
            </option>
          </select>

          <select v-model="selectedTerm" @change="loadDashboard" class="dashboard-select">
            <option :value="1">Term 1</option>
            <option :value="2">Term 2</option>
            <option :value="3">Term 3</option>
          </select>

          <select v-model="selectedClass" @change="loadDashboard" class="dashboard-select">
            <option value="">All Classes</option>
            <option v-for="c in availableClasses" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>

        <button
          @click="loadDashboard"
          class="rounded-2xl bg-blue-500 px-5 py-3 font-medium text-white shadow-lg shadow-blue-500/25 hover:bg-blue-600"
        >
          Refresh
        </button>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="kpi-card">
        <p class="kpi-label">Total Teachers</p>
        <p class="kpi-value">{{ stats.total_teachers || 0 }}</p>
      </div>

      <div class="kpi-card">
        <p class="kpi-label">Active Teachers</p>
        <p class="kpi-value text-emerald-600">{{ stats.active_teachers || 0 }}</p>
      </div>

      <div class="kpi-card">
        <p class="kpi-label">Teacher of the Week</p>
        <p class="kpi-highlight">{{ stats.teacher_of_week || 'N/A' }}</p>
      </div>

      <div class="kpi-card">
        <p class="kpi-label">Total Observations</p>
        <p class="kpi-value">{{ stats.total_observations || 0 }}</p>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <div class="panel">
        <div class="panel-head">
          <h2 class="panel-title">Top Performing Teachers</h2>
          <span class="panel-badge">AGP / Duty Summary</span>
        </div>

        <div v-if="(stats.top_teachers || []).length === 0" class="empty-state">
          No performance data yet.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="t in stats.top_teachers"
            :key="t.name"
            class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3"
          >
            <div class="flex items-center justify-between gap-4">
              <div class="font-semibold text-slate-900">{{ t.name }}</div>
              <div class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-emerald-700">
                {{ t.score }}%
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h2 class="panel-title">Needs Attention</h2>
          <span class="panel-badge danger">Below Target</span>
        </div>

        <div v-if="(stats.low_teachers || []).length === 0" class="empty-state">
          No low performance records yet.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="t in stats.low_teachers"
            :key="t.name"
            class="rounded-2xl border border-rose-100 bg-rose-50/70 px-4 py-3"
          >
            <div class="flex items-center justify-between gap-4">
              <div class="font-semibold text-slate-900">{{ t.name }}</div>
              <div class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-rose-700">
                {{ t.score }}%
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
      <div class="panel">
        <div class="panel-head">
          <h2 class="panel-title">Duty Performance</h2>
        </div>
        <div class="chart-wrap">
          <canvas ref="dutyCanvas"></canvas>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h2 class="panel-title">Performance Trends</h2>
        </div>
        <div class="chart-wrap">
          <canvas ref="performanceCanvas"></canvas>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h2 class="panel-title">Time Lost</h2>
        </div>
        <div class="chart-wrap">
          <canvas ref="timeLostCanvas"></canvas>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Chart from 'chart.js/auto';
import { dashboardAPI } from '../services/api.js';

const selectedYear = ref(new Date().getFullYear());
const selectedTerm = ref(1);
const selectedClass = ref('');

const stats = ref({});
const charts = ref({
  duty_labels: [],
  duty_scores: [],
  trend_labels: [],
  trend_data: [],
  time_labels: [],
  time_data: []
});

const dutyCanvas = ref(null);
const performanceCanvas = ref(null);
const timeLostCanvas = ref(null);

const dutyChart = ref(null);
const performanceChart = ref(null);
const timeChart = ref(null);

const availableClasses = computed(() => stats.value.available_classes || []);

const years = Array.from({ length: 7 }, (_, i) => new Date().getFullYear() - 3 + i);

const destroyCharts = () => {
  if (dutyChart.value) {
    dutyChart.value.destroy();
    dutyChart.value = null;
  }
  if (performanceChart.value) {
    performanceChart.value.destroy();
    performanceChart.value = null;
  }
  if (timeChart.value) {
    timeChart.value.destroy();
    timeChart.value = null;
  }
};

const renderCharts = async () => {
  await nextTick();
  destroyCharts();

  if (dutyCanvas.value) {
    dutyChart.value = new Chart(dutyCanvas.value, {
      type: 'bar',
      data: {
        labels: charts.value.duty_labels || [],
        datasets: [
          {
            label: 'Duty Scores',
            data: charts.value.duty_scores || [],
            backgroundColor: 'rgba(59, 130, 246, 0.75)',
            borderRadius: 10
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { color: '#64748b' },
            grid: { color: 'rgba(148, 163, 184, 0.2)' }
          },
          x: {
            ticks: { color: '#64748b' },
            grid: { display: false }
          }
        }
      }
    });
  }

  if (performanceCanvas.value) {
    performanceChart.value = new Chart(performanceCanvas.value, {
      type: 'line',
      data: {
        labels: charts.value.trend_labels || [],
        datasets: [
          {
            label: 'Average Performance',
            data: charts.value.trend_data || [],
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.12)',
            tension: 0.35,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: 'rgb(16, 185, 129)'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { color: '#64748b' },
            grid: { color: 'rgba(148, 163, 184, 0.2)' }
          },
          x: {
            ticks: { color: '#64748b' },
            grid: { display: false }
          }
        }
      }
    });
  }

  if (timeLostCanvas.value) {
    timeChart.value = new Chart(timeLostCanvas.value, {
      type: 'bar',
      data: {
        labels: charts.value.time_labels || [],
        datasets: [
          {
            label: 'Minutes Lost',
            data: charts.value.time_data || [],
            backgroundColor: 'rgba(239, 68, 68, 0.75)',
            borderRadius: 10
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { color: '#64748b' },
            grid: { color: 'rgba(148, 163, 184, 0.2)' }
          },
          x: {
            ticks: { color: '#64748b' },
            grid: { display: false }
          }
        }
      }
    });
  }
};

const loadDashboard = async () => {
  try {
    const result = await dashboardAPI.getData(
      selectedYear.value,
      selectedTerm.value,
      selectedClass.value
    );

    if (result.success) {
      stats.value = result.data?.stats || {};
      charts.value = result.data?.charts || {
        duty_labels: [],
        duty_scores: [],
        trend_labels: [],
        trend_data: [],
        time_labels: [],
        time_data: []
      };

      if (stats.value.available_classes && stats.value.available_classes.length) {
        // keep the list from backend
      }
      await renderCharts();
    } else {
      stats.value = {};
      charts.value = {
        duty_labels: [],
        duty_scores: [],
        trend_labels: [],
        trend_data: [],
        time_labels: [],
        time_data: []
      };
    }
  } catch (err) {
    console.error('Failed to load dashboard:', err);
  }
};

watch(selectedClass, () => {
  loadDashboard();
});

onMounted(loadDashboard);

onBeforeUnmount(() => {
  destroyCharts();
});
</script>

<style scoped>
.dashboard-select {
  min-width: 150px;
  border-radius: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.98);
  color: #0f172a;
  padding: 0.85rem 1rem;
  font-weight: 600;
  outline: none;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.kpi-card {
  border-radius: 1.75rem;
  background: white;
  padding: 1.4rem 1.5rem;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
  border: 1px solid rgba(226, 232, 240, 0.9);
}

.kpi-label {
  color: #64748b;
  font-size: 0.95rem;
  margin-bottom: 0.5rem;
}

.kpi-value {
  font-size: 2.1rem;
  line-height: 1;
  font-weight: 800;
  color: #0f172a;
}

.kpi-highlight {
  font-size: 1.2rem;
  font-weight: 800;
  color: #2563eb;
}

.panel {
  border-radius: 1.75rem;
  background: white;
  padding: 1.35rem;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
  border: 1px solid rgba(226, 232, 240, 0.9);
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.panel-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0f172a;
}

.panel-badge {
  border-radius: 9999px;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 0.35rem 0.8rem;
  font-size: 0.78rem;
  font-weight: 700;
}

.panel-badge.danger {
  background: #fef2f2;
  color: #b91c1c;
}

.chart-wrap {
  height: 280px;
}

.empty-state {
  border-radius: 1.25rem;
  border: 1px dashed #cbd5e1;
  background: #f8fafc;
  color: #64748b;
  padding: 1rem;
  text-align: center;
}
</style>
