<template>
  <div class="page">
    <ToastBanner />
    <div class="page-hero">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="page-hero-title">Report Settings</h1>
        </div>
        <div class="flex gap-2">
          <button type="button" class="btn-hero-primary" :disabled="saving || loading" @click="save"><Save class="h-4 w-4" />{{ saving ? 'Saving…' : 'Save settings' }}</button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="card px-5 py-10 text-center text-sm text-gray-500">Loading…</div>
    <div v-else class="grid gap-5 xl:grid-cols-[1fr_24rem]">
      <div class="min-w-0 space-y-5">
        <section class="card p-5">
          <h2 class="card-title">School details</h2>
          <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label v-for="f in textFields" :key="f.key" class="block" :class="f.wide ? 'sm:col-span-2' : ''">
              <span class="mb-1 block text-sm font-medium text-gray-700">{{ f.label }}</span>
              <textarea v-if="f.area" v-model="form[f.key]" rows="2" class="input" :maxlength="f.max" :placeholder="f.placeholder"></textarea>
              <input v-else v-model="form[f.key]" :type="f.type || 'text'" class="input" :maxlength="f.max" :placeholder="f.placeholder">
              <span v-if="errors[f.key]" class="mt-1 block text-xs text-red-600">{{ errors[f.key] }}</span>
            </label>
          </div>
        </section>

        <section class="card p-5">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="card-title">Grade scale</h2>
              <p class="text-xs text-gray-500">A final mark gets the first grade whose minimum it reaches. Keep one grade starting at 0.</p>
            </div>
            <div class="flex gap-2">
              <button type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="resetScale">Use default A–E</button>
              <button type="button" class="btn-secondary px-3 py-1.5 text-xs" :disabled="form.grade_scale.length >= 12" @click="addGrade"><Plus class="h-3.5 w-3.5" />Add grade</button>
            </div>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="data-table min-w-[520px]">
              <thead><tr><th class="w-28">From (%)</th><th class="w-28">Grade</th><th>Descriptor</th><th class="w-24">Range</th><th class="w-10"></th></tr></thead>
              <tbody>
                <tr v-for="(g, i) in form.grade_scale" :key="i">
                  <td><input v-model.number="g.min" type="number" min="0" max="100" step="0.5" class="input py-1.5"></td>
                  <td><input v-model="g.grade" maxlength="5" class="input py-1.5 font-semibold uppercase"></td>
                  <td><input v-model="g.comment" maxlength="60" class="input py-1.5" placeholder="e.g. Exceptional"></td>
                  <td class="text-xs tabular-nums text-gray-500">{{ rangeOf(i) }}</td>
                  <td><button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" :disabled="form.grade_scale.length <= 2" title="Remove" @click="form.grade_scale.splice(i, 1)"><Trash2 class="h-4 w-4" /></button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="errors.grade_scale" class="mt-2 text-sm text-red-600">{{ errors.grade_scale }}</p>
        </section>
      </div>

      <div class="min-w-0 space-y-5">
        <section class="card p-5">
          <h2 class="card-title">School logo</h2>
          <div class="mt-4 flex h-40 items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-gray-50">
            <img v-if="form.logo" :src="form.logo" alt="School logo" class="max-h-32 max-w-[80%] object-contain">
            <span v-else class="text-sm text-gray-400">No logo yet</span>
          </div>
          <div class="mt-3 flex gap-2">
            <label class="btn-secondary cursor-pointer px-3 py-1.5 text-xs">
              <ImageUp class="h-3.5 w-3.5" />{{ form.logo ? 'Replace' : 'Upload logo' }}
              <input type="file" accept="image/png,image/jpeg" class="sr-only" @change="pickLogo">
            </label>
            <button v-if="form.logo" type="button" class="btn-secondary px-3 py-1.5 text-xs" @click="form.logo = ''">Remove</button>
          </div>
          <p v-if="errors.logo" class="mt-2 text-xs text-red-600">{{ errors.logo }}</p>
        </section>

        <!-- Live preview of the report header -->
        <section class="card p-5">
          <h2 class="card-title">Header preview</h2>
          <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4 text-center">
            <img v-if="form.logo" :src="form.logo" alt="" class="mx-auto mb-2 h-14 object-contain">
            <p class="font-bold uppercase tracking-wide text-gray-900">{{ form.school_name || 'School name' }}</p>
            <p v-if="form.motto" class="text-xs italic text-gray-600">“{{ form.motto }}”</p>
            <p class="mt-1 whitespace-pre-line text-xs text-gray-600">{{ contactLine }}</p>
            <p class="mt-3 border-t border-brand-600 pt-2 text-xs font-semibold tracking-widest text-brand-700">STUDENT REPORT CARD</p>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { ImageUp, Plus, Save, Trash2 } from 'lucide-vue-next';
import ToastBanner from '../components/hr/ToastBanner.vue';
import { schoolSettingsAPI } from '../services/api.js';
import { useToast } from '../composables/useToast.js';
import { apiError } from '../utils/timetable.js';

const DEFAULT_SCALE = [
  { min: 80, grade: 'A', comment: 'Exceptional' },
  { min: 70, grade: 'B', comment: 'Outstanding' },
  { min: 60, grade: 'C', comment: 'Satisfactory' },
  { min: 50, grade: 'D', comment: 'Basic' },
  { min: 0, grade: 'E', comment: 'Elementary' },
];
const textFields = [
  { key: 'school_name', label: 'School name', max: 255, wide: true },
  { key: 'motto', label: 'Motto', max: 255, wide: true },
  { key: 'address', label: 'Address', max: 500, area: true, wide: true, placeholder: 'P.O. Box …, town' },
  { key: 'phone', label: 'Phone', max: 100 },
  { key: 'email', label: 'Email', max: 150, type: 'email' },
  { key: 'website', label: 'Website', max: 150 },
  { key: 'head_teacher', label: 'Head teacher’s name', max: 150 },
  { key: 'next_term_begins', label: 'Next term begins', max: 100, placeholder: 'e.g. Monday 2 February 2027' },
  { key: 'footer_note', label: 'Note at the bottom of reports', max: 500, area: true, wide: true, placeholder: 'e.g. This report is not valid without the school stamp.' },
];

const { showToast } = useToast();
const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const form = ref({ grade_scale: [] });

const contactLine = computed(() => [form.value.address, [form.value.phone, form.value.email, form.value.website].filter(Boolean).join(' · ')].filter(Boolean).join('\n'));
const sortedMins = computed(() => form.value.grade_scale.map((g) => Number(g.min)));
const rangeOf = (i) => {
  const min = sortedMins.value[i];
  const higher = sortedMins.value.filter((m) => m > min);
  return higher.length ? `${min}–${Math.min(...higher) - 0.1}` : `${min}–100`;
};

const resetScale = () => { form.value.grade_scale = DEFAULT_SCALE.map((g) => ({ ...g })); };
const addGrade = () => form.value.grade_scale.push({ min: 0, grade: '', comment: '' });

function pickLogo(ev) {
  const file = ev.target.files?.[0];
  ev.target.value = '';
  if (!file) return;
  if (!['image/png', 'image/jpeg'].includes(file.type)) {
    errors.value = { ...errors.value, logo: 'Choose a PNG or JPG image' };
    return;
  }
  // Scale big images down to 400px so the logo stays small in the database and in PDFs.
  const img = new Image();
  const url = URL.createObjectURL(file);
  img.onload = () => {
    const scale = Math.min(1, 400 / Math.max(img.width, img.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(img.width * scale);
    canvas.height = Math.round(img.height * scale);
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
    form.value.logo = canvas.toDataURL(file.type === 'image/jpeg' ? 'image/jpeg' : 'image/png', 0.9);
    errors.value = { ...errors.value, logo: '' };
    URL.revokeObjectURL(url);
  };
  img.onerror = () => { errors.value = { ...errors.value, logo: 'That image could not be read' }; };
  img.src = url;
}

async function load() {
  loading.value = true;
  try {
    const d = (await schoolSettingsAPI.get()).data;
    form.value = { ...d, logo: d.logo || '', grade_scale: (d.grade_scale || DEFAULT_SCALE).map((g) => ({ ...g })) };
  } catch (e) {
    showToast(apiError(e).message, 'error');
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  errors.value = {};
  try {
    const scale = [...form.value.grade_scale].sort((a, b) => Number(b.min) - Number(a.min));
    const res = await schoolSettingsAPI.save({ ...form.value, grade_scale: scale });
    form.value = { ...res.data, logo: res.data.logo || '', grade_scale: res.data.grade_scale.map((g) => ({ ...g })) };
    showToast(res.message || 'Settings saved');
  } catch (e) {
    const err = apiError(e);
    errors.value = err.errors;
    showToast(err.message, 'error');
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
