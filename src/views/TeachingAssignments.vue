<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Teaching Assignments</h1>
        </div>
        <div v-if="data && !data.closed" class="relative flex flex-wrap gap-2">
          <button type="button" class="btn-hero" :disabled="!copySources.length" @click="copyOpen = !copyOpen">
            <Copy class="h-4 w-4" />Copy from another term
          </button>
          <button type="button" class="btn-hero-primary" @click="openNew()"><Plus class="h-4 w-4" />New assignment</button>
          <div v-if="copyOpen" class="absolute right-0 top-full z-30 mt-2 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
            <p class="border-b border-gray-100 px-4 py-2.5 text-xs text-gray-500">Adds that term's assignments for classes nobody teaches yet. Nothing already set is changed.</p>
            <button v-for="s in copySources" :key="s.id" type="button" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-brand-50" @click="copyFrom(s)">
              <span class="font-medium text-gray-900">{{ s.session_name }}</span>
              <span class="text-xs text-gray-500">{{ s.assignments }} assignments</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="data?.closed" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">This term is closed: its assignments are kept for the record but can no longer be changed.</div>

    <StatCards :cards="summaryCards" />

    <ListToolbar v-model:search="search" placeholder="Search a teacher or department" :filter-count="show !== 'all' ? 1 : 0" @clear="show = 'all'">
      <template #filters>
        <label class="block">
          <span class="filter-label">Show</span>
          <select v-model="show" class="input py-2">
            <option value="all">Every department</option>
            <option value="gaps">Departments with classes not covered</option>
            <option value="unassigned">Teachers without classes</option>
          </select>
        </label>
      </template>
      <template #actions>
        <button type="button" class="tool-btn" title="Fold all" @click="toggleAll"><ChevronsUpDown class="h-4 w-4" /><span class="hidden lg:inline">{{ allOpen ? 'Fold all' : 'Open all' }}</span></button>
      </template>
    </ListToolbar>

    <div v-if="loading && !data" class="space-y-3">
      <div v-for="i in 4" :key="i" class="card h-20 animate-pulse"></div>
    </div>
    <div v-else-if="!shownDepartments.length" class="card px-5 py-14 text-center">
      <Users class="mx-auto h-9 w-9 text-gray-300" />
      <p class="mt-2 text-sm font-medium text-gray-700">No department matches</p>
    </div>

    <section v-for="d in shownDepartments" :key="d.subject_id" class="card overflow-hidden">
      <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-gray-50 sm:px-5" :aria-expanded="isOpen(d)" @click="toggle(d)">
        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-xs font-bold text-brand-800 ring-1 ring-brand-100">{{ shortCode(d) }}</span>
        <span class="min-w-0 flex-1">
          <span class="block truncate font-semibold text-gray-900">{{ d.name }}</span>
          <span class="block text-xs text-gray-500">{{ assignedCount(d) }} of {{ d.teachers.length }} teacher{{ d.teachers.length === 1 ? '' : 's' }} assigned<span class="sm:hidden"> · {{ d.covered }}/{{ d.class_ids.length }} classes</span></span>
        </span>
        <span class="hidden w-56 sm:block">
          <span class="flex items-center justify-between text-xs text-gray-600">
            <span><strong class="text-gray-900">{{ d.covered }}</strong> of {{ d.class_ids.length }} classes covered</span>
            <span class="tabular-nums">{{ pct(d.covered, d.class_ids.length) }}%</span>
          </span>
          <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-gray-100"><span class="block h-full rounded-full" :class="d.covered >= d.class_ids.length ? 'bg-brand-500' : 'bg-amber-400'" :style="{ width: `${pct(d.covered, d.class_ids.length)}%` }"></span></span>
        </span>
        <ChevronDown class="h-4 w-4 flex-shrink-0 text-gray-400 transition" :class="isOpen(d) ? 'rotate-180' : ''" />
      </button>

      <div v-if="isOpen(d)" class="border-t border-gray-100">
        <ul class="divide-y divide-gray-100">
          <li v-for="t in teachersShown(d)" :key="t.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 sm:px-5">
            <div class="flex min-w-0 items-center gap-3 sm:w-64">
              <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(t.full_name) }}</span>
              <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-gray-900">{{ t.full_name }}</span>
                <span class="block font-mono text-[11px] text-gray-500">{{ t.teacher_code }}<template v-if="loadOf(t.id)"> · {{ loadOf(t.id) }} lessons a week</template></span>
              </span>
            </div>
            <div class="flex min-w-0 flex-1 flex-wrap gap-1.5">
              <span v-for="a in classesOf(t.id, d.subject_id)" :key="a.id" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 py-0.5 pl-2 pr-1 text-xs font-medium text-brand-800 ring-1 ring-brand-100">
                {{ className(a.class_id) }}
                <button v-if="!data.closed" type="button" class="rounded p-0.5 text-brand-700/70 hover:bg-white hover:text-red-600" :aria-label="`Remove ${className(a.class_id)}`" @click="remove(a)"><X class="h-3 w-3" /></button>
              </span>
              <span v-if="!classesOf(t.id, d.subject_id).length" class="text-xs text-gray-400">No classes yet</span>
            </div>
            <button v-if="!data.closed" type="button" class="tool-btn" @click="openPicker(t, d)"><Plus class="h-4 w-4" />Assign classes</button>
          </li>
        </ul>
        <div v-if="gapsOf(d).length" class="border-t border-dashed border-gray-200 bg-amber-50/40 px-4 py-2.5 text-xs text-amber-800 sm:px-5">
          <strong>Not covered yet:</strong> {{ gapsOf(d).map(className).join(', ') }}
        </div>
      </div>
    </section>

    <!-- One assignment: a teacher for a subject in one class stream -->
    <Teleport to="body">
      <div v-if="newForm" class="fixed inset-0 z-[60] flex items-end justify-center bg-gray-900/60 backdrop-blur-sm sm:items-center sm:p-4" @click.self="newForm = null">
        <form class="w-full max-w-lg overflow-hidden bg-white shadow-2xl sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="new-title" @submit.prevent="saveNew">
          <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100"><BookUser class="h-5 w-5" /></span>
            <div class="min-w-0 flex-1">
              <h3 id="new-title" class="text-lg font-semibold text-gray-900">New assignment</h3>
              <p class="text-sm text-gray-500">Choose the class stream, the subject and the teacher who teaches it.</p>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="newForm = null"><X class="h-5 w-5" /></button>
          </header>
          <div class="space-y-4 px-5 py-4">
            <label class="block">
              <span class="mb-1 block text-sm font-medium text-gray-700">Class stream</span>
              <select v-model.number="newForm.class_id" class="input" required>
                <option :value="null" disabled>Choose a class stream</option>
                <optgroup v-for="g in classGroups" :key="g.name" :label="g.name">
                  <option v-for="c in g.classes" :key="c.id" :value="c.id">{{ c.full_class_name }}</option>
                </optgroup>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-sm font-medium text-gray-700">Subject</span>
              <select v-model.number="newForm.subject_id" class="input" required :disabled="!newForm.class_id">
                <option :value="null" disabled>{{ newForm.class_id ? 'Choose a subject' : 'Choose the class stream first' }}</option>
                <option v-for="d in subjectsForClass" :key="d.subject_id" :value="d.subject_id">{{ d.name }}{{ holderName(d.subject_id) ? ` (now ${holderName(d.subject_id)})` : '' }}</option>
              </select>
            </label>
            <label class="block">
              <span class="mb-1 block text-sm font-medium text-gray-700">Teacher</span>
              <select v-model.number="newForm.teacher_id" class="input" required :disabled="!newForm.subject_id">
                <option :value="null" disabled>{{ newForm.subject_id ? 'Choose a teacher' : 'Choose the subject first' }}</option>
                <optgroup v-if="teacherChoices.dept.length" :label="`${subjectName} department`">
                  <option v-for="t in teacherChoices.dept" :key="t.id" :value="t.id">{{ t.full_name }} · {{ t.teacher_code }}</option>
                </optgroup>
                <optgroup label="Other teachers">
                  <option v-for="t in teacherChoices.others" :key="t.id" :value="t.id">{{ t.full_name }} · {{ t.teacher_code }}</option>
                </optgroup>
              </select>
            </label>
            <p v-if="newForm.subject_id && holderName(newForm.subject_id) && currentHolderId !== newForm.teacher_id" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200">
              {{ className(newForm.class_id) }} {{ subjectName }} is taught by {{ holderName(newForm.subject_id) }} now. Saving moves it to the teacher you choose.
            </p>
            <p v-else-if="newForm.subject_id && currentHolderId && currentHolderId === newForm.teacher_id" class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 ring-1 ring-gray-200">This teacher already teaches it.</p>
          </div>
          <footer class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
            <button type="button" class="btn-secondary" @click="newForm = null">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving || !newForm.class_id || !newForm.subject_id || !newForm.teacher_id || currentHolderId === newForm.teacher_id"><Check class="h-4 w-4" />{{ saving ? 'Saving…' : 'Assign' }}</button>
          </footer>
        </form>
      </div>
    </Teleport>

    <!-- Choose classes for a teacher in one subject -->
    <div v-if="picker" class="fixed inset-0 z-50 flex items-end justify-center bg-gray-900/60 p-0 backdrop-blur-sm sm:items-center sm:p-4" @click.self="picker = null" @keydown.esc="picker = null">
      <div class="flex max-h-[100dvh] w-full max-w-2xl flex-col overflow-hidden bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="picker-title">
        <header class="flex items-start gap-3 border-b border-gray-100 px-5 py-4">
          <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800 ring-1 ring-brand-100">{{ initials(picker.teacher.full_name) }}</span>
          <div class="min-w-0 flex-1">
            <h3 id="picker-title" class="truncate text-lg font-semibold text-gray-900">{{ picker.teacher.full_name }}</h3>
            <p class="text-sm text-gray-500">{{ picker.department.name }} · tick the classes they teach</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close" @click="picker = null"><X class="h-5 w-5" /></button>
        </header>
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
          <div v-for="form in pickerForms" :key="form.name">
            <div class="mb-1.5 flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ form.name }}</p>
              <button type="button" class="text-xs font-medium text-brand-700 hover:underline" @click="toggleForm(form)">{{ formFull(form) ? 'Clear' : 'All' }}</button>
            </div>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="c in form.classes"
                :key="c.id"
                type="button"
                class="pick"
                :class="picker.selected.has(c.id) ? 'is-on' : holderOf(c.id) ? 'is-taken' : ''"
                :aria-pressed="picker.selected.has(c.id)"
                :aria-label="c.full_class_name"
                :title="holderOf(c.id) ? `Taught by ${holderOf(c.id)}. Ticking it moves the class to ${picker.teacher.full_name}.` : ''"
                @click="togglePick(c.id)"
              >
                <Check v-if="picker.selected.has(c.id)" class="h-3.5 w-3.5" />{{ c.stream_name }}
                <span v-if="holderOf(c.id) && !picker.selected.has(c.id)" class="text-[10px] font-normal opacity-80">{{ initials(holderOf(c.id)) }}</span>
              </button>
            </div>
          </div>
          <p class="text-xs text-gray-500"><span class="pick-key is-taken"></span> taught by another teacher (initials shown). Ticking it moves the class to this teacher.</p>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50/70 px-5 py-3">
          <p class="text-sm text-gray-600"><strong class="text-gray-900">{{ picker.selected.size }}</strong> class{{ picker.selected.size === 1 ? '' : 'es' }} ticked</p>
          <div class="flex gap-2">
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="picker = null">Cancel</button>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60" :disabled="saving" @click="savePicker"><Check class="h-4 w-4" />{{ saving ? 'Saving…' : 'Save' }}</button>
          </div>
        </footer>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { BookUser, Check, ChevronDown, ChevronsUpDown, ClipboardCheck, Copy, Plus, School, UserCheck, UserX, Users, X } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import StatCards from '../components/ui/StatCards.vue';
import ListToolbar from '../components/ui/ListToolbar.vue';
import { teachingAssignmentsAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import { useGlobalTerm } from '../composables/useGlobalTerm.js';

const { showToast } = useToast();
const sessionId = ref(null);
const data = ref(null);
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const show = ref('all');
const open = ref(new Set());
const allOpen = ref(false);
const copyOpen = ref(false);
const picker = ref(null);

const initials = (name) => String(name || '?').split(/\s+/).filter((w) => /^[A-Za-z]/.test(w)).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
const pct = (n, of) => (of ? Math.round((n / of) * 100) : 0);
const shortCode = (d) => String(d.code || d.name).replace(/[^A-Za-z]/g, '').slice(0, 4).toUpperCase();

const classById = computed(() => new Map((data.value?.classes || []).map((c) => [c.id, c])));
const className = (id) => classById.value.get(id)?.full_class_name || '?';
const teacherById = computed(() => new Map((data.value?.teachers || []).map((t) => [t.id, t])));
const assignments = computed(() => data.value?.assignments || []);
const classesOf = (teacherId, subjectId) => assignments.value
  .filter((a) => a.teacher_id === teacherId && a.subject_id === subjectId)
  .sort((a, b) => className(a.class_id).localeCompare(className(b.class_id)));
const loadOf = (teacherId) => assignments.value.filter((a) => a.teacher_id === teacherId).reduce((t, a) => t + a.periods_per_week, 0);
const assignedTo = (subjectId) => new Map(assignments.value.filter((a) => a.subject_id === subjectId).map((a) => [a.class_id, a]));
const gapsOf = (d) => { const taken = assignedTo(d.subject_id); return d.class_ids.filter((id) => !taken.has(id)); };
const assignedCount = (d) => d.teachers.filter((t) => classesOf(t.id, d.subject_id).length).length;
const teachersShown = (d) => (show.value === 'unassigned' ? d.teachers.filter((t) => !classesOf(t.id, d.subject_id).length) : d.teachers);

const shownDepartments = computed(() => {
  const q = search.value.trim().toLowerCase();
  return (data.value?.departments || []).filter((d) => {
    if (show.value === 'gaps' && d.covered >= d.class_ids.length) return false;
    if (show.value === 'unassigned' && !d.teachers.some((t) => !classesOf(t.id, d.subject_id).length)) return false;
    return !q || d.name.toLowerCase().includes(q) || d.teachers.some((t) => `${t.full_name} ${t.teacher_code}`.toLowerCase().includes(q));
  });
});
const isOpen = (d) => allOpen.value || open.value.has(d.subject_id) || !!search.value.trim();
const toggle = (d) => {
  const next = new Set(open.value);
  if (allOpen.value) { allOpen.value = false; (data.value?.departments || []).forEach((x) => next.add(x.subject_id)); }
  next.has(d.subject_id) ? next.delete(d.subject_id) : next.add(d.subject_id);
  open.value = next;
};
const toggleAll = () => { allOpen.value = !allOpen.value; if (!allOpen.value) open.value = new Set(); };

const summaryCards = computed(() => {
  const s = data.value?.stats || { teachers: 0, assigned_teachers: 0, needed: 0, covered: 0, assignments: 0 };
  const pick = (v) => () => { show.value = show.value === v ? 'all' : v; };
  return [
    { label: 'Teachers with classes', value: `${s.assigned_teachers}`, note: `of ${s.teachers} teachers (${pct(s.assigned_teachers, s.teachers)}%)`, icon: UserCheck },
    { label: 'Without classes', value: `${s.teachers - s.assigned_teachers}`, note: 'Show them', icon: UserX, tone: s.teachers - s.assigned_teachers ? 'amber' : 'gray', onClick: pick('unassigned'), active: show.value === 'unassigned' },
    { label: 'Classes covered', value: `${pct(s.covered, s.needed)}%`, note: `${s.covered} of ${s.needed} class subjects`, icon: School, tone: 'sky', onClick: pick('gaps'), active: show.value === 'gaps' },
    { label: 'Assignments', value: `${s.assignments}`, note: 'this term', icon: ClipboardCheck, tone: 'gray' },
  ];
});

const copySources = computed(() => (data.value?.sessions || []).filter((s) => s.id !== sessionId.value && Number(s.assignments) > 0));

// ---- The class picker ----
const holderOf = (classId) => {
  const a = assignedTo(picker.value.department.subject_id).get(classId);
  return a && a.teacher_id !== picker.value.teacher.id ? teacherById.value.get(a.teacher_id)?.full_name || 'another teacher' : null;
};
const pickerForms = computed(() => {
  if (!picker.value) return [];
  const forms = new Map();
  for (const id of picker.value.department.class_ids) {
    const c = classById.value.get(id);
    if (!c) continue;
    if (!forms.has(c.class_name)) forms.set(c.class_name, { name: c.class_name, classes: [] });
    forms.get(c.class_name).classes.push(c);
  }
  return [...forms.values()];
});
const openPicker = (teacher, department) => {
  picker.value = { teacher, department, selected: new Set(classesOf(teacher.id, department.subject_id).map((a) => a.class_id)) };
};
const togglePick = (id) => {
  const s = new Set(picker.value.selected);
  s.has(id) ? s.delete(id) : s.add(id);
  picker.value = { ...picker.value, selected: s };
};
// "All" ticks the classes that are free or already theirs; another teacher's class is only moved one at a time.
const freeIn = (form) => form.classes.filter((c) => picker.value.selected.has(c.id) || !holderOf(c.id));
const formFull = (form) => freeIn(form).every((c) => picker.value.selected.has(c.id));
const toggleForm = (form) => {
  const s = new Set(picker.value.selected);
  const full = formFull(form);
  if (full) form.classes.forEach((c) => s.delete(c.id));
  else freeIn(form).forEach((c) => s.add(c.id));
  picker.value = { ...picker.value, selected: s };
};
const savePicker = async () => {
  const { teacher, department, selected } = picker.value;
  const mine = classesOf(teacher.id, department.subject_id);
  const toAdd = [...selected].filter((id) => !mine.some((a) => a.class_id === id));
  const toRemove = mine.filter((a) => !selected.has(a.class_id));
  saving.value = true;
  const problems = [];
  try {
    if (toAdd.length) {
      const res = await teachingAssignmentsAPI.assign(sessionId.value, teacher.id, department.subject_id, toAdd);
      data.value = res.data;
      showToast(res.message);
    }
    for (const a of toRemove) {
      try { data.value = (await teachingAssignmentsAPI.unassign(a.id)).data; } catch (e) { problems.push(`${className(a.class_id)}: ${e?.response?.data?.message || 'could not remove'}`); }
    }
    if (problems.length) showToast(problems.join(' '), 'error');
    else if (!toAdd.length && toRemove.length) showToast(`${toRemove.length} class${toRemove.length === 1 ? '' : 'es'} removed`);
    picker.value = null;
  } catch (e) {
    showToast(e?.response?.data?.message || 'Could not save', 'error');
  } finally {
    saving.value = false;
  }
};

const remove = async (a) => {
  if (!confirm(`Remove ${className(a.class_id)} from ${teacherById.value.get(a.teacher_id)?.full_name}?`)) return;
  try {
    const res = await teachingAssignmentsAPI.unassign(a.id);
    data.value = res.data;
    showToast(`${className(a.class_id)} removed`);
  } catch (e) {
    showToast(e?.response?.data?.message || 'Could not remove', 'error');
  }
};
const copyFrom = async (s) => {
  copyOpen.value = false;
  if (!confirm(`Copy ${s.session_name}'s assignments into this term? Only classes nobody teaches yet are filled in.`)) return;
  try {
    const res = await teachingAssignmentsAPI.copy(sessionId.value, s.id);
    data.value = res.data;
    showToast(res.message);
  } catch (e) {
    showToast(e?.response?.data?.message || 'Could not copy', 'error');
  }
};

// ---- New assignment: class stream, then subject, then teacher ----
const newForm = ref(null);
const openNew = () => { newForm.value = { class_id: null, subject_id: null, teacher_id: null }; };
// Class streams grouped by form (S1, S2, ...).
const classGroups = computed(() => {
  const groups = new Map();
  for (const c of data.value?.classes || []) {
    if (!groups.has(c.class_name)) groups.set(c.class_name, { name: c.class_name, classes: [] });
    groups.get(c.class_name).classes.push(c);
  }
  return [...groups.values()];
});
// Subjects the chosen stream's level offers.
const subjectsForClass = computed(() => (data.value?.departments || []).filter((d) => d.class_ids.includes(newForm.value?.class_id)));
const subjectName = computed(() => (data.value?.departments || []).find((d) => d.subject_id === newForm.value?.subject_id)?.name || '');
const currentHolderId = computed(() => assignedTo(newForm.value?.subject_id).get(newForm.value?.class_id)?.teacher_id || null);
const holderName = (subjectId) => {
  const a = assignedTo(subjectId).get(newForm.value?.class_id);
  return a ? teacherById.value.get(a.teacher_id)?.full_name || 'another teacher' : '';
};
// The subject's department first, then every other active teacher.
const teacherChoices = computed(() => {
  const dept = (data.value?.departments || []).find((d) => d.subject_id === newForm.value?.subject_id)?.teachers || [];
  const ids = new Set(dept.map((t) => t.id));
  return { dept, others: (data.value?.teachers || []).filter((t) => !ids.has(t.id)) };
});
watch(() => newForm.value?.class_id, () => { if (newForm.value && !subjectsForClass.value.some((d) => d.subject_id === newForm.value.subject_id)) newForm.value.subject_id = null; });
watch(() => newForm.value?.subject_id, () => { if (newForm.value) newForm.value.teacher_id = currentHolderId.value; });
const saveNew = async () => {
  const { class_id: classId, subject_id: subjectId, teacher_id: teacherId } = newForm.value;
  saving.value = true;
  try {
    const res = await teachingAssignmentsAPI.assign(sessionId.value, teacherId, subjectId, [classId]);
    data.value = res.data;
    showToast(res.message);
    newForm.value = null;
  } catch (e) {
    showToast(e?.response?.data?.message || 'Could not save', 'error');
  } finally {
    saving.value = false;
  }
};

const load = async () => {
  if (!sessionId.value) return;
  loading.value = true;
  try {
    data.value = (await teachingAssignmentsAPI.get(sessionId.value)).data;
  } catch (e) {
    showToast(e?.response?.data?.message || 'Could not load the assignments', 'error');
  } finally {
    loading.value = false;
  }
};

useGlobalTerm((year, term, s) => { sessionId.value = s?.id || null; }, () => load());
onMounted(load);
</script>

<style scoped>
.filter-label { @apply mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500; }
.tool-btn { @apply inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50; }
.pick { @apply inline-flex min-w-[3.25rem] items-center justify-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 transition hover:border-brand-400; }
.pick.is-on { @apply border-brand-600 bg-brand-600 text-white hover:border-brand-700; }
.pick.is-taken { @apply border-dashed border-amber-300 bg-amber-50 text-amber-900; }
.pick-key { @apply mr-1 inline-block h-3 w-3 rounded border border-dashed border-amber-300 bg-amber-50 align-middle; }
html.dark .pick { background-color: #0d1512; border-color: #31413a; color: #c9d5cf; }
html.dark .pick.is-on { background-color: #059669; border-color: #059669; color: #fff; }
html.dark .pick.is-taken, html.dark .pick-key.is-taken { background-color: rgba(245, 158, 11, 0.14); border-color: rgba(251, 191, 36, 0.4); color: #fcd34d; }
</style>

<style scoped>
/* dark mode: generated by scripts/build-dark-overrides.cjs */
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
html.dark .pick-key {
  border-color: rgba(245, 158, 11, 0.3);
  background-color: rgba(245, 158, 11, 0.12);
}
</style>
