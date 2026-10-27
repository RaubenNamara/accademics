<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Classes</h1>
        </div>
        <button type="button" class="btn-hero-primary" @click="openForm()"><Plus class="h-4 w-4" />Add class or stream</button>
      </div>
    </div>

    <StatCards :cards="stats" />

    <ListToolbar v-model:search="q" placeholder="Search class or stream" :filter-count="level ? 1 : 0" @clear="level = ''">
      <template #filters>
        <label class="block">
          <span class="filter-label">Level</span>
          <select v-model="level" class="input py-2">
            <option value="">All levels</option>
            <option value="O-Level">O-Level (S1–S4)</option>
            <option value="A-Level">A-Level (S5–S6)</option>
          </select>
        </label>
      </template>
    </ListToolbar>

    <div v-if="loading" class="card px-5 py-12 text-center text-sm text-gray-500">Loading…</div>
    <div v-else-if="!groups.length" class="card px-5 py-14 text-center">
      <School class="mx-auto h-9 w-9 text-gray-300" />
      <p class="mt-2 text-sm font-medium text-gray-700">{{ classes.length ? 'No classes match' : 'No classes yet' }}</p>
    </div>
    <div v-else class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
      <section v-for="g in groups" :key="g.name" class="card overflow-hidden">
        <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3.5">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-600 text-sm font-bold text-white">{{ g.name }}</span>
          <div class="min-w-0 flex-1">
            <h2 class="font-semibold text-gray-900">{{ g.name }}</h2>
            <p class="text-xs text-gray-500">{{ g.level }} · {{ g.streams.length }} stream{{ g.streams.length === 1 ? '' : 's' }}</p>
          </div>
          <button type="button" class="link-btn text-xs" @click="openForm(null, g.name)">+ Stream</button>
        </div>
        <ul class="divide-y divide-gray-50">
          <li v-for="c in g.streams" :key="c.id" class="flex items-center gap-3 px-5 py-2">
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xs font-semibold text-gray-700">{{ c.stream_name }}</span>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-900">{{ c.full_class_name }}</p>
              <p class="text-xs text-gray-500">Added {{ formatDate(c.created_at) }}</p>
            </div>
            <RowActionsMenu :label="`Actions for ${c.full_class_name}`" :items="actions(c)" />
          </li>
        </ul>
      </section>
    </div>

    <!-- Add / edit -->
    <div v-if="form" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="form = null">
      <form class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @submit.prevent="save">
        <h2 class="text-lg font-semibold text-gray-900">{{ form.id ? 'Edit class' : 'Add class or stream' }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-3">
          <label class="block">
            <span class="field-label">Class</span>
            <input v-model="form.class_name" class="input" placeholder="e.g. S1" required>
          </label>
          <label class="block">
            <span class="field-label">Stream</span>
            <input v-model="form.stream_name" class="input" placeholder="e.g. A" required>
          </label>
        </div>
        <p class="mt-2 text-xs text-gray-500">Shown as “{{ (form.class_name || 'S1').trim() }} {{ (form.stream_name || 'A').trim() }}”.</p>
        <p v-if="formError" class="mt-3 text-sm text-red-600">{{ formError }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="form = null">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Layers, Pencil, Plus, School, Trash2 } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import RowActionsMenu from '../components/RowActionsMenu.vue';
import { classesAPI } from '../services/api';
import { useToast } from '../composables/useToast.js';

const { showToast } = useToast();
const classes = ref([]);
const loading = ref(true);
const q = ref('');
const level = ref('');
const form = ref(null);
const formError = ref('');
const saving = ref(false);

const levelOf = (name) => (Number(/(\d)/.exec(name || '')?.[1] || 0) >= 5 ? 'A-Level' : 'O-Level');
const formatDate = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '-');
const errorText = (e, fallback) => e?.response?.data?.error || e?.response?.data?.message || fallback;

const groups = computed(() => {
  const term = q.value.trim().toLowerCase();
  const map = new Map();
  for (const c of classes.value) {
    if (level.value && levelOf(c.class_name) !== level.value) continue;
    if (term && !`${c.class_name} ${c.stream_name} ${c.full_class_name}`.toLowerCase().includes(term)) continue;
    if (!map.has(c.class_name)) map.set(c.class_name, { name: c.class_name, level: levelOf(c.class_name), streams: [] });
    map.get(c.class_name).streams.push(c);
  }
  return [...map.values()]
    .sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true }))
    .map((g) => ({ ...g, streams: g.streams.sort((a, b) => a.stream_name.localeCompare(b.stream_name)) }));
});
const stats = computed(() => {
  const names = new Set(classes.value.map((c) => c.class_name));
  const a = classes.value.filter((c) => levelOf(c.class_name) === 'A-Level').length;
  return [
    { label: 'Classes', value: names.size, icon: School },
    { label: 'Streams', value: classes.value.length, icon: Layers },
    { label: 'O-Level streams', value: classes.value.length - a, icon: Layers, tone: 'sky', onClick: () => { level.value = level.value === 'O-Level' ? '' : 'O-Level'; }, active: level.value === 'O-Level' },
    { label: 'A-Level streams', value: a, icon: Layers, tone: 'amber', onClick: () => { level.value = level.value === 'A-Level' ? '' : 'A-Level'; }, active: level.value === 'A-Level' },
  ];
});
const actions = (c) => [
  { label: 'Edit', icon: Pencil, onClick: () => openForm(c) },
  { label: 'Delete', icon: Trash2, danger: true, onClick: () => remove(c) },
];

async function load() {
  loading.value = true;
  try {
    classes.value = (await classesAPI.getAll()).data || [];
  } catch (e) {
    showToast(errorText(e, 'Could not load classes'), 'error');
  } finally {
    loading.value = false;
  }
}
function openForm(c = null, className = '') {
  formError.value = '';
  form.value = c ? { id: c.id, class_name: c.class_name, stream_name: c.stream_name } : { id: null, class_name: className, stream_name: '' };
}
async function save() {
  saving.value = true;
  formError.value = '';
  try {
    const data = { class_name: form.value.class_name.trim(), stream_name: form.value.stream_name.trim() };
    if (form.value.id) await classesAPI.update({ id: form.value.id, ...data });
    else await classesAPI.create(data);
    showToast(form.value.id ? 'Class updated' : `${data.class_name} ${data.stream_name} added`);
    form.value = null;
    await load();
  } catch (e) {
    formError.value = errorText(e, 'Could not save the class');
  } finally {
    saving.value = false;
  }
}
async function remove(c) {
  if (!confirm(`Delete ${c.full_class_name}? This cannot be undone.`)) return;
  try {
    await classesAPI.delete(c.id);
    showToast(`${c.full_class_name} deleted`);
    await load();
  } catch (e) {
    showToast(errorText(e, 'Could not delete the class'), 'error');
  }
}

onMounted(load);
</script>

<style scoped>
.field-label { @apply mb-1 block text-sm font-medium text-gray-700; }
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .field-label {
  color: var(--d-text-2);
}
html.dark .filter-label {
  color: var(--d-muted);
}
</style>
