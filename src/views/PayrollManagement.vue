<template>
  <div class="space-y-5">
    <ToastBanner />
    <PageHeader title="Payroll" subtitle="Salaries · allowances · deductions · payslips" @refresh="load" @add="openModal()" />

    <div class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4">
      <input v-model.number="filters.period_year" type="number" class="input w-28" @change="load" />
      <select v-model.number="filters.period_month" class="input w-auto" @change="load">
        <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
      </select>
      <button class="btn-secondary" @click="exportCsv">Export CSV</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-sm text-slate-500">Total Net Pay</p>
        <p class="text-2xl font-bold text-emerald-700">{{ formatMoney(totalNet) }}</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:col-span-2">
        <p class="text-sm text-slate-500">Records this period</p>
        <p class="text-2xl font-bold">{{ records.length }}</p>
      </div>
    </div>

    <DataPanel :loading="loading">
      <table class="data-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Employee</th>
            <th>Basic</th>
            <th>Allowances</th>
            <th>Deductions</th>
            <th>Net Pay</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in records" :key="p.id">
            <td><span class="code-badge">{{ p.hr_code }}</span></td>
            <td>{{ p.first_name }} {{ p.last_name }}</td>
            <td>{{ formatMoney(p.basic_salary) }}</td>
            <td>{{ formatMoney(p.total_allowances) }}</td>
            <td>{{ formatMoney(p.total_deductions) }}</td>
            <td class="font-semibold text-emerald-700">{{ formatMoney(p.net_pay) }}</td>
          </tr>
        </tbody>
      </table>
    </DataPanel>

    <Modal v-if="showModal" title="Generate Payslip" @close="showModal = false">
      <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="save">
        <select v-model="form.employee_id" class="input sm:col-span-2" required>
          <option value="">Employee</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.first_name }} {{ e.last_name }}</option>
        </select>
        <input v-model.number="form.basic_salary" type="number" step="0.01" class="input" placeholder="Basic salary" required />
        <input v-model.number="form.total_allowances" type="number" step="0.01" class="input" placeholder="Allowances" />
        <input v-model.number="form.total_deductions" type="number" step="0.01" class="input" placeholder="Deductions" />
        <p class="sm:col-span-2 text-sm text-slate-600">Net: <strong>{{ formatMoney(netPreview) }}</strong></p>
        <button type="submit" class="btn-primary sm:col-span-2">Save Payslip</button>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { payrollAPI, employeesAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import { useExport } from '../composables/useExport.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import PageHeader from '../components/hr/PageHeader.vue';
import DataPanel from '../components/hr/DataPanel.vue';
import Modal from '../components/hr/Modal.vue';

const { showToast } = useToast();
const { exportToCsv } = useExport();

const loading = ref(false);
const showModal = ref(false);
const records = ref([]);
const employees = ref([]);
const filters = ref({ period_year: new Date().getFullYear(), period_month: new Date().getMonth() + 1 });
const form = ref({ employee_id: '', basic_salary: 0, total_allowances: 0, total_deductions: 0 });

const totalNet = computed(() => records.value.reduce((s, r) => s + parseFloat(r.net_pay || 0), 0));
const netPreview = computed(() =>
  (parseFloat(form.value.basic_salary) || 0) +
  (parseFloat(form.value.total_allowances) || 0) -
  (parseFloat(form.value.total_deductions) || 0)
);

const monthName = (m) => new Date(2000, m - 1).toLocaleString('en', { month: 'short' });
const formatMoney = (n) =>
  new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX', maximumFractionDigits: 0 }).format(parseFloat(n) || 0);

const load = async () => {
  loading.value = true;
  const res = await payrollAPI.getAll(filters.value);
  records.value = res.success ? res.data || [] : [];
  loading.value = false;
};

const openModal = () => {
  form.value = {
    employee_id: '',
    basic_salary: 0,
    total_allowances: 0,
    total_deductions: 0,
    period_year: filters.value.period_year,
    period_month: filters.value.period_month
  };
  showModal.value = true;
};

const save = async () => {
  const res = await payrollAPI.save({
    ...form.value,
    period_year: filters.value.period_year,
    period_month: filters.value.period_month
  });
  if (res.success) {
    showToast('Payslip saved');
    showModal.value = false;
    load();
  } else showToast(res.message || 'Failed', 'error');
};

const exportCsv = () => {
  exportToCsv('payroll', [
    { key: 'hr_code', label: 'Code' },
    { key: 'first_name', label: 'First' },
    { key: 'last_name', label: 'Last' },
    { key: 'net_pay', label: 'Net' }
  ], records.value);
};

onMounted(async () => {
  const e = await employeesAPI.getAll();
  employees.value = e.success ? e.data || [] : [];
  load();
});
</script>


