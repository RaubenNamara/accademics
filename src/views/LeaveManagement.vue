<template>
  <div class="space-y-5">
    <ToastBanner />
    <PageHeader title="Leave Management" subtitle="Requests · approvals · balances" @refresh="load" @add="showRequest = true" />

    <div class="flex gap-2 border-b border-slate-200">
      <button type="button" class="tab" :class="{ 'tab-active': tab === 'requests' }" @click="tab = 'requests'; load()">Requests</button>
      <button type="button" class="tab" :class="{ 'tab-active': tab === 'balances' }" @click="tab = 'balances'; loadBalances()">Balances</button>
    </div>

    <DataPanel v-if="tab === 'requests'" :loading="loading">
      <table class="data-table">
        <thead>
          <tr>
            <th>Staff</th>
            <th>Type</th>
            <th>Dates</th>
            <th>Days</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in requests" :key="r.id">
            <td>{{ r.first_name }} {{ r.last_name }} <span class="code-badge">{{ r.hr_code }}</span></td>
            <td class="capitalize">{{ r.leave_type }}</td>
            <td>{{ r.start_date }} → {{ r.end_date }}</td>
            <td>{{ r.days }}</td>
            <td><StatusPill :status="r.status" /></td>
            <td class="space-x-2 text-right">
              <button v-if="r.status === 'pending'" class="link-btn" @click="approve(r.id, 'approved')">Approve</button>
              <button v-if="r.status === 'pending'" class="text-sm text-rose-600" @click="approve(r.id, 'rejected')">Reject</button>
              <button class="text-sm text-rose-600" @click="deleteRequest(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </DataPanel>

    <DataPanel v-else :loading="loading">
      <table class="data-table">
        <thead>
          <tr><th>Staff</th><th>Type</th><th>Entitled</th><th>Used</th><th>Remaining</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="b in balances" :key="b.id">
            <td>{{ b.first_name }} {{ b.last_name }}</td>
            <td class="capitalize">{{ b.leave_type }}</td>
            <td>{{ b.entitled_days }}</td>
            <td>{{ b.used_days }}</td>
            <td class="font-semibold text-emerald-700">{{ b.remaining_days }}</td>
            <td class="text-right">
              <button class="text-sm text-rose-600" @click="deleteBalance(b.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </DataPanel>

    <Modal v-if="showRequest" title="New Leave Request" @close="showRequest = false">
      <form class="grid gap-3" @submit.prevent="submitRequest">
        <select v-model="reqForm.employee_id" class="input" required>
          <option value="">Employee</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.first_name }} {{ e.last_name }} ({{ e.hr_code }})</option>
        </select>
        <select v-model="reqForm.leave_type" class="input">
          <option value="annual">Annual</option>
          <option value="sick">Sick</option>
          <option value="maternity">Maternity</option>
          <option value="paternity">Paternity</option>
          <option value="compassionate">Compassionate</option>
          <option value="unpaid">Unpaid</option>
          <option value="other">Other</option>
        </select>
        <input v-model="reqForm.start_date" type="date" class="input" required />
        <input v-model="reqForm.end_date" type="date" class="input" required />
        <textarea v-model="reqForm.reason" class="input" rows="3" placeholder="Reason"></textarea>
        <button type="submit" class="btn-primary">Submit</button>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { leaveAPI, employeesAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import ToastBanner from '../components/hr/ToastBanner.vue';
import PageHeader from '../components/hr/PageHeader.vue';
import DataPanel from '../components/hr/DataPanel.vue';
import Modal from '../components/hr/Modal.vue';
import StatusPill from '../components/hr/StatusPill.vue';

const { showToast } = useToast();
const tab = ref('requests');
const loading = ref(false);
const showRequest = ref(false);
const requests = ref([]);
const balances = ref([]);
const employees = ref([]);
const reqForm = ref({ employee_id: '', leave_type: 'annual', start_date: '', end_date: '', reason: '' });

const load = async () => {
  loading.value = true;
  const res = await leaveAPI.getRequests();
  requests.value = res.success ? res.data || [] : [];
  loading.value = false;
};

const loadBalances = async () => {
  loading.value = true;
  const res = await leaveAPI.getBalances(new Date().getFullYear());
  balances.value = res.success ? res.data || [] : [];
  loading.value = false;
};

const approve = async (id, status) => {
  const res = await leaveAPI.updateStatus(id, status);
  if (res.success) { showToast(`Leave ${status}`); load(); }
  else showToast(res.message || 'Failed', 'error');
};

const submitRequest = async () => {
  const res = await leaveAPI.create(reqForm.value);
  if (res.success) {
    showToast('Request submitted');
    showRequest.value = false;
    reqForm.value = { employee_id: '', leave_type: 'annual', start_date: '', end_date: '', reason: '' };
    load();
  } else showToast(res.message || 'Failed', 'error');
};

const deleteRequest = async (id) => {
  if (!confirm('Are you sure you want to delete this leave request?')) return;
  const res = await leaveAPI.deleteRequest(id);
  if (res.success) {
    showToast('Request deleted');
    load();
  } else showToast(res.message || 'Failed', 'error');
};

const deleteBalance = async (id) => {
  if (!confirm('Are you sure you want to delete this leave balance?')) return;
  const res = await leaveAPI.deleteBalance(id);
  if (res.success) {
    showToast('Balance deleted');
    loadBalances();
  } else showToast(res.message || 'Failed', 'error');
};

onMounted(async () => {
  const e = await employeesAPI.getAll();
  employees.value = e.success ? e.data || [] : [];
  load();
});
</script>

<style scoped>
.tab { @apply px-4 py-2 text-sm font-medium text-slate-500 border-b-2 border-transparent; }
.tab-active { @apply text-blue-600 border-blue-600; }
</style>
