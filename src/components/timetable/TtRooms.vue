<template>
  <div class="space-y-4">
    <div class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="card-title">Rooms</h2>
        <p class="text-xs text-slate-500">
          Classrooms, laboratories and halls that lessons can be assigned to.
          <template v-if="rooms.length">{{ activeCount }} active of {{ rooms.length }}.</template>
        </p>
      </div>
      <div v-if="canManage" class="flex flex-wrap gap-2">
        <button type="button" class="btn-secondary" :disabled="addingClassRooms" @click="addClassRooms">
          <i class="fas" :class="addingClassRooms ? 'fa-circle-notch fa-spin' : 'fa-chalkboard'"></i>Add class rooms
        </button>
        <button type="button" class="btn-primary" @click="openForm()"><i class="fas fa-plus"></i>Add Room</button>
      </div>
      <p v-else class="text-xs text-slate-500"><i class="fas fa-lock mr-1"></i>Only administrators can add or change rooms.</p>
    </div>

    <div class="card overflow-hidden">
      <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3">
        <input v-model="query" type="search" class="input w-full sm:w-64" placeholder="Search code or name" />
        <select v-model="typeFilter" class="input w-full sm:w-48">
          <option value="">All types</option>
          <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="showInactive" type="checkbox" class="accent-brand-600" />Show inactive
        </label>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr><th>Code</th><th>Name</th><th>Type</th><th>Home room of</th><th>Capacity</th><th>Used by</th><th>Status</th><th v-if="canManage" class="text-right">Actions</th></tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="8" class="py-8 text-center text-slate-500">Loading…</td></tr>
            <tr v-else-if="!filtered.length">
              <td colspan="8" class="py-10 text-center text-slate-500">
                {{ rooms.length ? 'No rooms match these filters.' : 'No rooms yet. Add the school’s classrooms, labs and halls so lessons can be given a room.' }}
              </td>
            </tr>
            <tr v-for="r in filtered" v-else :key="r.id" :class="Number(r.is_active) ? '' : 'opacity-60'">
              <td><span class="code-badge">{{ r.room_code }}</span></td>
              <td class="font-medium text-slate-900">{{ r.room_name }}</td>
              <td><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="typeOf(r.room_type).badge"><i :class="typeOf(r.room_type).icon" class="mr-1"></i>{{ typeOf(r.room_type).label }}</span></td>
              <td>
                <span v-if="homeOf[r.id]" class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800">{{ homeOf[r.id].full_class_name }}</span>
                <span v-else class="text-xs text-slate-400">-</span>
              </td>
              <td>
                {{ r.capacity }}
                <span v-if="homeOf[r.id] && enrolment(homeOf[r.id]) > Number(r.capacity)" class="ml-1 text-xs text-amber-700" :title="`${homeOf[r.id].full_class_name} has more students than seats`"><i class="fas fa-triangle-exclamation"></i></span>
              </td>
              <td class="text-xs text-slate-500">
                <template v-if="Number(r.lesson_count) || Number(r.requirement_count)">
                  {{ r.lesson_count }} lesson(s) · {{ r.requirement_count }} requirement(s)
                </template>
                <template v-else>Not used yet</template>
              </td>
              <td>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="Number(r.is_active) ? 'bg-brand-100 text-brand-800' : 'bg-slate-100 text-slate-600'">
                  {{ Number(r.is_active) ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td v-if="canManage" class="whitespace-nowrap text-right">
                <button type="button" class="link-btn mr-3" @click="openForm(r)">Edit</button>
                <button type="button" class="mr-3 text-sm font-medium text-slate-600 hover:underline" @click="toggleActive(r)">{{ Number(r.is_active) ? 'Deactivate' : 'Activate' }}</button>
                <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="remove(r)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal v-if="showForm" :title="form.id ? `Edit ${form.room_code}` : 'Add Room'" @close="showForm = false">
      <form class="space-y-4" novalidate @submit.prevent="save">
        <div v-if="formError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><i class="fas fa-circle-exclamation mr-1"></i>{{ formError }}</div>
        <div class="grid gap-4 sm:grid-cols-3">
          <div>
            <span class="field-label">Code</span>
            <p class="flex h-[42px] items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-sm" aria-live="polite">
              <i class="fas fa-lock text-xs text-slate-400"></i>
              <span class="font-mono font-semibold text-slate-800">{{ form.id ? form.room_code : (nextCode || '…') }}</span>
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ form.id ? 'Codes do not change' : 'Assigned automatically' }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="field-label" for="room-name">Name <span class="text-red-600">*</span></label>
            <input id="room-name" v-model="form.room_name" class="input" maxlength="100" placeholder="e.g. S.1 - A Class or Chemistry Laboratory" :class="errors.room_name ? 'border-red-400' : ''" />
            <p v-if="errors.room_name" class="field-error">{{ errors.room_name }}</p>
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="room-type">Type</label>
            <select id="room-type" v-model="form.room_type" class="input">
              <option v-for="t in TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="field-label" for="room-capacity">Capacity (seats)</label>
            <input id="room-capacity" v-model.number="form.capacity" type="number" min="1" max="2000" class="input" :class="errors.capacity ? 'border-red-400' : ''" />
            <p v-if="errors.capacity" class="field-error">{{ errors.capacity }}</p>
          </div>
        </div>
        <div>
          <label class="field-label" for="room-home">Home room for class</label>
          <TtSearchSelect id="room-home" v-model="form.home_class_id" :options="classOptions" label="full_class_name" noun="classes" placeholder="Not a class's home room" clearable />
          <p class="mt-1 text-xs text-slate-500">Lessons for this class use its home room unless the subject needs a specific room, such as a lab.</p>
        </div>
        <div class="flex flex-wrap gap-5">
          <label class="flex items-center gap-2 text-sm"><input v-model="form.has_projector" type="checkbox" class="accent-brand-600" />Projector</label>
          <label class="flex items-center gap-2 text-sm"><input v-model="form.has_computers" type="checkbox" class="accent-brand-600" />Computers</label>
          <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="accent-brand-600" />Active (can be timetabled)</label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="btn-secondary" :disabled="saving" @click="showForm = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            <i v-if="saving" class="fas fa-circle-notch fa-spin"></i>{{ saving ? 'Saving…' : form.id ? 'Save changes' : 'Add room' }}
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { roomsAPI, timetableAPI } from '../../services/api.js';
import authStore from '../../services/authStore.js';
import { useToast } from '../../composables/useToast.js';
import { apiError } from '../../utils/timetable.js';
import Modal from '../hr/Modal.vue';
import TtSearchSelect from './TtSearchSelect.vue';

const emit = defineEmits(['changed']);
const { showToast } = useToast();

const TYPES = [
  { value: 'classroom', label: 'Classroom', icon: 'fas fa-chalkboard', badge: 'bg-slate-100 text-slate-700' },
  { value: 'laboratory', label: 'Laboratory', icon: 'fas fa-flask', badge: 'bg-brand-100 text-brand-800' },
  { value: 'library', label: 'Library', icon: 'fas fa-book', badge: 'bg-amber-100 text-amber-800' },
  { value: 'hall', label: 'Hall', icon: 'fas fa-landmark', badge: 'bg-sky-100 text-sky-800' },
  { value: 'office', label: 'Office', icon: 'fas fa-briefcase', badge: 'bg-slate-100 text-slate-700' },
  { value: 'other', label: 'Other', icon: 'fas fa-door-open', badge: 'bg-slate-100 text-slate-700' },
];
const typeOf = (t) => TYPES.find((x) => x.value === t) || TYPES[5];
const PREFIXES = { classroom: 'CR', laboratory: 'LAB', library: 'LIB', hall: 'HALL', office: 'OFF', other: 'RM' };

// Matches the server: only administrators may change rooms.
const canManage = computed(() => ['super_admin', 'admin'].includes(authStore.getRole()));

const rooms = ref([]);
const loading = ref(false);
const query = ref('');
const typeFilter = ref('');
const showInactive = ref(true);
const showForm = ref(false);
const saving = ref(false);
const formError = ref('');
const errors = ref({});
const blank = () => ({ id: null, room_code: '', room_name: '', room_type: 'classroom', capacity: 40, has_projector: false, has_computers: false, is_active: true, home_class_id: null });
const homeRooms = ref([]); // [{class_id, full_class_name, room_id}]
const homeOf = computed(() => Object.fromEntries(homeRooms.value.filter((h) => h.room_id).map((h) => [Number(h.room_id), h])));
const classOptions = computed(() => homeRooms.value.map((h) => ({ id: Number(h.class_id), full_class_name: h.full_class_name })));
const enrolment = (h) => Number(h.students) || 0;
const form = ref(blank());
const nextCode = ref('');
// Preview the code the server will assign for the chosen type.
watch(() => [showForm.value, form.value.room_type], async ([open, type]) => {
  if (!open || form.value.id) return;
  nextCode.value = '';
  try {
    nextCode.value = (await roomsAPI.nextCode(type)).data?.room_code || '';
  } catch {
    nextCode.value = `${PREFIXES[type] || 'RM'}-…`;
  }
});
const addingClassRooms = ref(false);

const activeCount = computed(() => rooms.value.filter((r) => Number(r.is_active)).length);
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  return rooms.value.filter((r) => (showInactive.value || Number(r.is_active))
    && (!typeFilter.value || r.room_type === typeFilter.value)
    && (!q || `${r.room_code} ${r.room_name}`.toLowerCase().includes(q)));
});

const load = async () => {
  loading.value = true;
  try {
    const [r, setup] = await Promise.all([roomsAPI.getAll(), timetableAPI.setup()]);
    rooms.value = r.data || [];
    homeRooms.value = setup.data.home_rooms || [];
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    loading.value = false;
  }
};

const openForm = (r = null) => {
  errors.value = {};
  formError.value = '';
  form.value = r
    ? { ...r, capacity: Number(r.capacity), has_projector: !!Number(r.has_projector), has_computers: !!Number(r.has_computers), is_active: !!Number(r.is_active),
      home_class_id: homeOf.value[r.id] ? Number(homeOf.value[r.id].class_id) : null }
    : blank();
  showForm.value = true;
};

const save = async () => {
  if (saving.value) return;
  const e = {};
  if (!form.value.room_name.trim()) e.room_name = 'Enter a room name';
  if (!(form.value.capacity >= 1)) e.capacity = 'Enter the number of seats';
  errors.value = e;
  if (Object.keys(e).length) return;

  saving.value = true;
  formError.value = '';
  try {
    const res = form.value.id ? await roomsAPI.update(form.value) : await roomsAPI.create(form.value);
    const roomId = form.value.id || res.data?.id;
    const before = form.value.id && homeOf.value[form.value.id] ? Number(homeOf.value[form.value.id].class_id) : null;
    if (roomId && before !== form.value.home_class_id) {
      if (before) await timetableAPI.setHomeRoom(before, null);
      if (form.value.home_class_id) await timetableAPI.setHomeRoom(form.value.home_class_id, roomId);
    }
    showToast(res.message || 'Room saved');
    showForm.value = false;
    await load();
    emit('changed');
  } catch (err) {
    const x = apiError(err);
    errors.value = x.errors;
    formError.value = x.message; // keep what was typed
  } finally {
    saving.value = false;
  }
};

const toggleActive = async (r) => {
  try {
    await roomsAPI.update({ ...r, is_active: !Number(r.is_active) });
    showToast(Number(r.is_active) ? `${r.room_code} deactivated, it won’t be offered for new lessons` : `${r.room_code} activated`);
    await load();
    emit('changed');
  } catch (err) {
    showToast(apiError(err).message, 'error');
  }
};

/** One classroom per class stream, named "S.1 - A Class", with automatic codes. */
const addClassRooms = async () => {
  if (!confirm('Add a classroom for every class stream that does not have one yet (named like "S.1 - A Class")?')) return;
  addingClassRooms.value = true;
  try {
    const res = await roomsAPI.createClassRooms();
    await timetableAPI.linkHomeRooms().catch(() => null); // make each new room its class's home room
    showToast(res.message, 'success', 5000);
    await load();
    emit('changed');
  } catch (err) {
    showToast(apiError(err).message, 'error');
  } finally {
    addingClassRooms.value = false;
  }
};

const remove = async (r) => {
  if (!confirm(`Delete room ${r.room_code} (${r.room_name})?`)) return;
  try {
    await roomsAPI.delete(r.id);
    showToast(`Room ${r.room_code} deleted`);
    await load();
    emit('changed');
  } catch (err) {
    showToast(apiError(err).message, 'error', 6000);
  }
};

onMounted(load);
</script>

<style scoped>
.field-label {
  @apply mb-1.5 block text-sm font-medium text-slate-700;
}
.field-error {
  @apply mt-1 text-xs font-medium text-red-600;
}
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
html.dark .field-label {
  color: var(--d-text-2);
}
html.dark .field-error {
  color: #fca5a5;
}
</style>
