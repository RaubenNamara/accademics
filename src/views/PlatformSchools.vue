<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Schools</h1>
        </div>
        <div v-if="ready" class="flex flex-wrap gap-2">
          <button type="button" class="btn-hero-primary" @click="openCreate"><Plus class="h-4 w-4" />Add school</button>
        </div>
      </div>
    </div>

    <div v-if="loadError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ loadError }}</div>

    <!-- First use: turn on multi-school -->
    <section v-if="!loading && !ready" class="card overflow-hidden">
      <header class="flex items-start gap-3 border-b border-gray-100 px-4 py-4 sm:px-5">
        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 ring-1 ring-brand-100"><Building class="h-5 w-5" /></span>
        <div>
          <p class="font-semibold text-gray-900">Turn on multiple schools</p>
          <p class="text-sm text-gray-500">This creates the list of schools and adds this school as the home school. Nothing about this school changes: its staff keep signing in as now, without a school code.</p>
        </div>
      </header>
      <div class="grid gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-end sm:p-5">
        <label class="block">
          <span class="m-label">This school's name</span>
          <input v-model.trim="homeName" type="text" class="m-input" placeholder="e.g. St Mark's College Namagoma" />
        </label>
        <button type="button" class="tool-btn-primary justify-center" :disabled="settingUp || homeName.length < 3" @click="setup">
          <Check class="h-4 w-4" />{{ settingUp ? 'Setting up…' : 'Turn on' }}
        </button>
      </div>
      <p v-if="setupError" class="mx-4 mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 sm:mx-5">{{ setupError }}</p>
    </section>

    <template v-if="ready">
      <StatCards :cards="summaryCards" />

      <section class="card flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-5">
        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-600 ring-1 ring-gray-200"><KeyRound class="h-4 w-4" /></span>
        <div class="min-w-0 flex-1">
          <p id="school-field-label" class="text-sm font-semibold text-gray-900">Show "School code" on the sign-in page</p>
          <p class="text-xs text-gray-500">{{ showSchoolField
            ? 'Anyone can type a school code when signing in.'
            : 'Hidden. Staff of other schools sign in with their school\'s sign-in link (⋮ › Copy sign-in link) or its own web address; St Mark\'s staff sign in as usual.' }}</p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="showSchoolField"
          aria-labelledby="school-field-label"
          :disabled="savingSetting"
          class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition disabled:opacity-60"
          :class="showSchoolField ? 'bg-brand-600' : 'bg-gray-300'"
          @click="toggleSchoolField"
        >
          <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition" :class="showSchoolField ? 'translate-x-5' : 'translate-x-0.5'"></span>
        </button>
      </section>

      <ListToolbar
        v-model:search="search"
        placeholder="Search school name, code or address"
        :filter-count="statusFilter ? 1 : 0"
        @clear="statusFilter = ''"
      >
        <template #filters>
          <label class="block">
            <span class="m-label">Status</span>
            <select v-model="statusFilter" class="m-input">
              <option value="">All schools</option>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
            </select>
          </label>
        </template>
        <template #actions>
          <button type="button" class="tool-btn" :disabled="loading" title="Refresh" @click="load"><RefreshCw class="h-4 w-4" :class="loading ? 'animate-spin' : ''" /><span class="hidden lg:inline">Refresh</span></button>
        </template>
      </ListToolbar>

      <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 sm:px-5">
          <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ rows.length }}</strong> school{{ rows.length === 1 ? '' : 's' }}</p>
        </div>
        <div v-if="rows.length === 0" class="px-5 py-14 text-center">
          <Building class="mx-auto h-9 w-9 text-gray-300" />
          <p class="mt-2 text-sm font-medium text-gray-700">No schools match</p>
        </div>
        <template v-else>
          <table class="hidden w-full text-left text-sm md:table">
            <thead class="border-b border-gray-200 bg-gray-50/70">
              <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-5 py-3">School</th>
                <th class="hidden px-4 py-3 lg:table-cell">Address</th>
                <th class="px-4 py-3">Students</th>
                <th class="hidden px-4 py-3 lg:table-cell">Teachers</th>
                <th class="hidden px-4 py-3 xl:table-cell">Last sign-in</th>
                <th class="px-4 py-3">Status</th>
                <th class="w-12 px-3 py-3"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="s in rows" :key="s.code" class="transition" :class="s.status === 'suspended' ? 'bg-gray-50 text-gray-500' : 'hover:bg-brand-50/40'">
                <td class="px-5 py-3">
                  <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(s.name) }}</span>
                    <span class="min-w-0">
                      <span class="flex items-center gap-1.5">
                        <span class="truncate font-semibold text-gray-900">{{ s.name }}</span>
                        <span v-if="s.is_home" class="rounded-full bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-brand-700 ring-1 ring-brand-100">Home</span>
                      </span>
                      <span class="block font-mono text-[11px] text-gray-500">{{ s.is_home ? 'no code needed' : s.code }}</span>
                    </span>
                  </div>
                </td>
                <td class="hidden px-4 py-3 text-gray-600 lg:table-cell">{{ s.domain || '-' }}</td>
                <td class="px-4 py-3 tabular-nums text-gray-800">{{ s.counts ? s.counts.students.toLocaleString() : '?' }}</td>
                <td class="hidden px-4 py-3 tabular-nums text-gray-800 lg:table-cell">{{ s.counts ? s.counts.teachers.toLocaleString() : '?' }}</td>
                <td class="hidden px-4 py-3 text-gray-600 xl:table-cell">{{ formatDate(s.last_sign_in) }}</td>
                <td class="px-4 py-3">
                  <span v-if="s.unreachable" class="inline-flex items-center gap-1.5 text-xs font-medium text-red-600"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Database unreachable</span>
                  <span v-else class="inline-flex items-center gap-1.5 text-xs font-medium" :class="s.status === 'active' ? 'text-emerald-700' : 'text-gray-500'"><span class="h-1.5 w-1.5 rounded-full" :class="s.status === 'active' ? 'bg-emerald-500' : 'bg-gray-300'"></span>{{ s.status === 'active' ? 'Active' : 'Suspended' }}</span>
                </td>
                <td class="px-3 py-3 text-right"><RowActionsMenu :label="`Actions for ${s.name}`" :items="schoolActions(s)" /></td>
              </tr>
            </tbody>
          </table>
          <ul class="divide-y divide-gray-100 md:hidden">
            <li v-for="s in rows" :key="s.code" class="flex items-start gap-3 px-4 py-3" :class="s.status === 'suspended' ? 'bg-gray-50' : ''">
              <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(s.name) }}</span>
              <div class="min-w-0 flex-1">
                <p class="truncate font-semibold text-gray-900">{{ s.name }}</p>
                <p class="text-xs text-gray-500">{{ s.is_home ? 'Home school' : s.code }} · {{ s.counts ? `${s.counts.students} students` : '?' }}</p>
                <span class="mt-1 inline-flex items-center gap-1.5 text-[11px] font-medium" :class="s.status === 'active' ? 'text-emerald-700' : 'text-gray-500'"><span class="h-1.5 w-1.5 rounded-full" :class="s.status === 'active' ? 'bg-emerald-500' : 'bg-gray-300'"></span>{{ s.status === 'active' ? 'Active' : 'Suspended' }}</span>
              </div>
              <RowActionsMenu :label="`Actions for ${s.name}`" :items="schoolActions(s)" />
            </li>
          </ul>
        </template>
      </section>
    </template>

    <!-- Add / edit a school -->
    <div v-if="dialog" class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 p-0 backdrop-blur-sm sm:items-center sm:p-4" @keydown.esc="closeDialog">
      <div class="flex max-h-[100dvh] w-full max-w-2xl flex-col overflow-hidden bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="school-dialog-title">
        <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 ring-1 ring-brand-100"><Building class="h-5 w-5" /></span>
          <div class="min-w-0 flex-1">
            <h3 id="school-dialog-title" class="text-lg font-semibold text-gray-900">{{ created ? 'School ready' : dialog === 'create' ? 'Add a school' : `Edit ${form.name}` }}</h3>
            <p class="text-sm text-gray-500">{{ created ? 'Send these sign-in details to the school.' : dialog === 'create' ? 'The school gets its own database with subjects and the curriculum already in it.' : 'Name, web address and contact.' }}</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="closeDialog"><X class="h-5 w-5" /></button>
        </header>

        <!-- Sign-in details after creating -->
        <div v-if="created" class="space-y-4 px-5 py-5">
          <dl class="divide-y divide-gray-100 rounded-xl border border-gray-200">
            <div v-for="row in createdRows" :key="row.label" class="flex items-center gap-3 px-4 py-2.5">
              <dt class="w-32 flex-shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ row.label }}</dt>
              <dd class="min-w-0 flex-1 break-all font-mono text-sm text-gray-900">{{ row.value }}</dd>
            </div>
          </dl>
          <p v-if="created.sign_in.password_generated" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">This password is shown only once. The administrator will be asked to change it at first sign-in.</p>
          <div class="flex flex-wrap justify-end gap-2">
            <button type="button" class="tool-btn" @click="copySignIn"><Copy class="h-4 w-4" />Copy details</button>
            <button type="button" class="tool-btn-primary" @click="closeDialog"><Check class="h-4 w-4" />Done</button>
          </div>
        </div>

        <form v-else class="flex min-h-0 flex-1 flex-col" @submit.prevent="save">
          <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5">
            <section class="grid gap-3 sm:grid-cols-[1fr_12rem]">
              <label class="block">
                <span class="m-label">School name</span>
                <input v-model.trim="form.name" type="text" class="m-input" :class="errors.name ? 'border-red-400' : ''" placeholder="e.g. St John's Secondary School" @input="suggestCode" />
                <span v-if="errors.name" class="m-error">{{ errors.name }}</span>
              </label>
              <label class="block">
                <span class="m-label">School code</span>
                <input v-model.trim="form.code" type="text" class="m-input lowercase" :class="errors.code ? 'border-red-400' : ''" :disabled="dialog === 'edit'" placeholder="stjohns" @input="codeTouched = true" />
                <span v-if="errors.code" class="m-error">{{ errors.code }}</span>
                <span v-else-if="dialog === 'create'" class="m-hint">Staff type this when signing in</span>
              </label>
            </section>

            <section v-if="dialog === 'create'">
              <p class="mb-2 text-sm font-semibold text-gray-900">School administrator</p>
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="m-label">Full name</span>
                  <input v-model.trim="form.admin_name" type="text" class="m-input" :class="errors.admin_name ? 'border-red-400' : ''" />
                  <span v-if="errors.admin_name" class="m-error">{{ errors.admin_name }}</span>
                </label>
                <label class="block">
                  <span class="m-label">Email</span>
                  <input v-model.trim="form.admin_email" type="email" class="m-input" :class="errors.admin_email ? 'border-red-400' : ''" />
                  <span v-if="errors.admin_email" class="m-error">{{ errors.admin_email }}</span>
                </label>
                <label class="block">
                  <span class="m-label">Password <span class="normal-case tracking-normal text-gray-400">(optional)</span></span>
                  <input v-model="form.admin_password" type="text" autocomplete="new-password" class="m-input" :class="errors.admin_password ? 'border-red-400' : ''" placeholder="Leave empty to generate one" />
                  <span v-if="errors.admin_password" class="m-error">{{ errors.admin_password }}</span>
                </label>
                <label class="block">
                  <span class="m-label">Phone <span class="normal-case tracking-normal text-gray-400">(optional)</span></span>
                  <input v-model.trim="form.contact_phone" type="tel" class="m-input" />
                </label>
              </div>
            </section>

            <section v-else class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="m-label">Contact name</span>
                <input v-model.trim="form.contact_name" type="text" class="m-input" />
              </label>
              <label class="block">
                <span class="m-label">Contact email</span>
                <input v-model.trim="form.contact_email" type="email" class="m-input" />
              </label>
              <label class="block">
                <span class="m-label">Contact phone</span>
                <input v-model.trim="form.contact_phone" type="tel" class="m-input" />
              </label>
            </section>

            <section>
              <label class="block">
                <span class="m-label">Web address <span class="normal-case tracking-normal text-gray-400">(optional)</span></span>
                <input v-model.trim="form.domain" type="text" class="m-input" :class="errors.domain ? 'border-red-400' : ''" placeholder="portal.stjohns.ac.ug" />
                <span v-if="errors.domain" class="m-error">{{ errors.domain }}</span>
                <span v-else class="m-hint">If the school has its own address pointing at this server, its staff won't need the school code.</span>
              </label>
            </section>

            <section v-if="dialog === 'create'">
              <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700" :aria-expanded="showAdvanced" @click="showAdvanced = !showAdvanced">
                <ChevronUp v-if="showAdvanced" class="h-4 w-4" /><ChevronDown v-else class="h-4 w-4" />Database
              </button>
              <label v-if="showAdvanced" class="mt-2 block">
                <span class="m-label">Database name</span>
                <input v-model.trim="form.db_name" type="text" class="m-input font-mono" :class="errors.db_name ? 'border-red-400' : ''" :placeholder="`sm_${(form.code || 'code').replace(/-/g, '_')}`" />
                <span v-if="errors.db_name" class="m-error">{{ errors.db_name }}</span>
                <span v-else class="m-hint">Leave empty to create one automatically. On cPanel hosting, create an empty database in cPanel first and enter its full name here.</span>
              </label>
            </section>

            <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
          </div>
          <footer class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closeDialog">Cancel</button>
            <button type="submit" class="tool-btn-primary" :disabled="saving">
              <Check class="h-4 w-4" />{{ saving ? (dialog === 'create' ? 'Setting up the school…' : 'Saving…') : (dialog === 'create' ? 'Create school' : 'Save changes') }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Building, Check, ChevronDown, ChevronUp, Copy, KeyRound, Link2, Pencil, PauseCircle, PlayCircle, Plus, RefreshCw, GraduationCap, X } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import { platformAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';

const { showToast } = useToast();
const loading = ref(true);
const loadError = ref('');
const ready = ref(false);
const schools = ref([]);
const search = ref('');
const statusFilter = ref('');
const showSchoolField = ref(false);
const savingSetting = ref(false);

// A school's own sign-in page: its web address, or this portal with the school preset.
const signInLink = (s) => (s.domain ? `https://${s.domain}` : `${window.location.origin}${window.location.pathname}#/login?school=${s.code}`);

const toggleSchoolField = async () => {
  savingSetting.value = true;
  try {
    const res = await platformAPI.saveSettings({ show_school_field: !showSchoolField.value });
    showSchoolField.value = res.data.settings.show_school_field;
    showToast(res.message);
  } catch (e) {
    showToast(apiMessage(e, 'Could not save the setting'), 'error');
  } finally {
    savingSetting.value = false;
  }
};

const homeName = ref("St Mark's College Namagoma");
const settingUp = ref(false);
const setupError = ref('');

const dialog = ref(null); // 'create' | 'edit'
const form = ref({});
const errors = ref({});
const formError = ref('');
const saving = ref(false);
const codeTouched = ref(false);
const showAdvanced = ref(false);
const created = ref(null);

const initials = (name) => String(name || '?').split(/\s+/).filter((w) => /^[A-Za-z]/.test(w)).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
const formatDate = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : 'Never');
const apiMessage = (e, fallback) => e?.response?.data?.message || fallback;

const rows = computed(() => {
  const q = search.value.trim().toLowerCase();
  return schools.value.filter((s) => (!statusFilter.value || s.status === statusFilter.value)
    && (!q || `${s.name} ${s.code} ${s.domain || ''}`.toLowerCase().includes(q)));
});

const summaryCards = computed(() => {
  const sum = (key) => schools.value.reduce((n, s) => n + (s.counts?.[key] || 0), 0);
  const active = schools.value.filter((s) => s.status === 'active').length;
  const pick = (status) => () => { statusFilter.value = statusFilter.value === status ? '' : status; };
  return [
    { label: 'Schools', value: String(schools.value.length), icon: Building },
    { label: 'Active', value: String(active), icon: PlayCircle, tone: 'sky', onClick: pick('active'), active: statusFilter.value === 'active' },
    { label: 'Suspended', value: String(schools.value.length - active), icon: PauseCircle, tone: 'gray', onClick: pick('suspended'), active: statusFilter.value === 'suspended' },
    { label: 'Students', value: sum('students').toLocaleString(), note: `${sum('teachers').toLocaleString()} teachers`, icon: GraduationCap, tone: 'amber' },
  ];
});

const schoolActions = (s) => [
  { label: 'Edit', icon: Pencil, onClick: () => openEdit(s) },
  ...(s.is_home ? [] : [{ label: 'Copy sign-in link', icon: Link2, onClick: () => copy(signInLink(s), 'Sign-in link copied') }]),
  { label: 'Copy school code', icon: Copy, onClick: () => copy(s.is_home ? 'No code needed (home school)' : s.code, 'School code copied') },
  ...(s.is_home ? [] : [s.status === 'active'
    ? { label: 'Suspend', icon: PauseCircle, danger: true, onClick: () => setStatus(s, 'suspended') }
    : { label: 'Reactivate', icon: PlayCircle, onClick: () => setStatus(s, 'active') }]),
];

const load = async () => {
  loading.value = true;
  loadError.value = '';
  try {
    const data = (await platformAPI.list()).data;
    ready.value = data.ready;
    schools.value = data.schools;
    showSchoolField.value = !!data.settings?.show_school_field;
  } catch (e) {
    loadError.value = apiMessage(e, 'Could not load the schools');
  } finally {
    loading.value = false;
  }
};

const setup = async () => {
  settingUp.value = true;
  setupError.value = '';
  try {
    const data = (await platformAPI.setup(homeName.value)).data;
    ready.value = data.ready;
    schools.value = data.schools;
    showToast('Multiple schools are on. Add your first school.');
  } catch (e) {
    setupError.value = apiMessage(e, 'Could not set up the school list');
  } finally {
    settingUp.value = false;
  }
};

const openCreate = () => {
  form.value = { name: '', code: '', admin_name: '', admin_email: '', admin_password: '', contact_phone: '', domain: '', db_name: '' };
  errors.value = {};
  formError.value = '';
  codeTouched.value = false;
  showAdvanced.value = false;
  created.value = null;
  dialog.value = 'create';
};
const openEdit = (s) => {
  form.value = { code: s.code, name: s.name, domain: s.domain || '', contact_name: s.contact_name || '', contact_email: s.contact_email || '', contact_phone: s.contact_phone || '' };
  errors.value = {};
  formError.value = '';
  created.value = null;
  dialog.value = 'edit';
};
const closeDialog = () => { dialog.value = null; created.value = null; };

// "St John's Secondary School" -> "stjohns": short and easy to type.
const suggestCode = () => {
  if (dialog.value !== 'create' || codeTouched.value) return;
  const words = form.value.name.toLowerCase().replace(/[^a-z0-9\s]/g, '').split(/\s+/)
    .filter((w) => w && !['secondary', 'school', 'college', 'high', 'the', 'of', 'ss', 'sss'].includes(w));
  form.value.code = words.slice(0, 2).join('').slice(0, 20);
};

const save = async () => {
  saving.value = true;
  errors.value = {};
  formError.value = '';
  try {
    if (dialog.value === 'create') {
      const res = await platformAPI.create({ ...form.value, code: form.value.code.toLowerCase() });
      created.value = res.data;
      showToast(res.message || 'School created');
    } else {
      const { code, ...changes } = form.value;
      const res = await platformAPI.update(code, changes);
      showToast(res.message || 'Saved');
      closeDialog();
    }
    await load();
  } catch (e) {
    errors.value = e?.response?.data?.data?.errors || {};
    formError.value = apiMessage(e, 'Could not save the school');
  } finally {
    saving.value = false;
  }
};

const setStatus = async (s, status) => {
  if (status === 'suspended' && !confirm(`Suspend ${s.name}? Its staff and teachers will not be able to sign in until you reactivate it. No data is deleted.`)) return;
  try {
    const res = await platformAPI.update(s.code, { status });
    showToast(res.message || 'Saved');
    await load();
  } catch (e) {
    showToast(apiMessage(e, 'Could not change the status'), 'error');
  }
};

const createdRows = computed(() => {
  if (!created.value) return [];
  const { school, sign_in: signIn } = created.value;
  return [
    { label: 'School', value: school.name },
    { label: 'Sign in at', value: signInLink(school) },
    { label: 'School code', value: signIn.school_code },
    { label: 'Email', value: signIn.email },
    ...(signIn.password ? [{ label: 'Password', value: signIn.password }] : []),
  ];
});

const copy = async (text, message) => {
  try {
    await navigator.clipboard.writeText(text);
    showToast(message);
  } catch {
    showToast('Could not copy. Select the text and copy it instead.', 'error');
  }
};
const copySignIn = () => copy(createdRows.value.map((r) => `${r.label}: ${r.value}`).join('\n'), 'Sign-in details copied');

onMounted(load);
</script>

<style scoped>
.m-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.m-input { @apply w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 disabled:bg-gray-50 disabled:text-gray-500; }
.m-error { @apply mt-1 block text-xs text-red-600; }
.m-hint { @apply mt-1 block text-xs text-gray-500; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.tool-btn-primary { @apply inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .m-label {
  color: var(--d-muted);
}
html.dark .m-input {
  border-color: var(--d-line-strong);
  background-color: var(--d-surface);
}
html.dark .m-error {
  color: #fca5a5;
}
html.dark .m-hint {
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
