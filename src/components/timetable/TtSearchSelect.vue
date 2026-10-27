<template>
  <div ref="root" class="relative">
    <button
      :id="id"
      type="button"
      class="input flex items-center justify-between gap-2 text-left"
      :class="[error ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : '', disabled ? 'cursor-not-allowed bg-slate-50 text-slate-400' : 'bg-white']"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click="toggle"
      @keydown.down.prevent="openAndFocus"
    >
      <span class="truncate" :class="selected ? 'text-slate-900' : 'text-slate-400'">
        {{ selected ? labelOf(selected) : placeholder }}
      </span>
      <i class="fas fa-chevron-down text-xs text-slate-400"></i>
    </button>

    <div
      v-if="open"
      class="absolute z-30 mt-1 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
    >
      <div class="border-b border-slate-100 p-2">
        <input
          ref="search"
          v-model="query"
          type="text"
          class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          :placeholder="`Search ${noun}…`"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="choose(filtered[active])"
          @keydown.esc="open = false"
        />
      </div>
      <ul class="max-h-60 overflow-y-auto py-1" role="listbox">
        <li v-if="clearable && modelValue" >
          <button type="button" class="w-full px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-50" @click="choose(null)">
            Clear selection
          </button>
        </li>
        <li v-for="(opt, i) in filtered" :key="opt[valueKey]" role="option" :aria-selected="opt[valueKey] === modelValue">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm"
            :class="[i === active ? 'bg-brand-50 text-brand-900' : 'text-slate-700 hover:bg-slate-50', opt.disabled ? 'opacity-50' : '']"
            @mouseenter="active = i"
            @click="choose(opt)"
          >
            <span class="truncate">{{ labelOf(opt) }}</span>
            <span v-if="hintOf(opt)" class="shrink-0 text-xs text-slate-400">{{ hintOf(opt) }}</span>
          </button>
        </li>
        <li v-if="!filtered.length" class="px-3 py-3 text-sm text-slate-400">No matches</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  modelValue: { type: [Number, String, null], default: null },
  options: { type: Array, default: () => [] },
  label: { type: [String, Function], default: 'label' },
  hint: { type: [String, Function], default: null },
  valueKey: { type: String, default: 'id' },
  placeholder: { type: String, default: 'Select…' },
  noun: { type: String, default: 'options' },
  error: { type: String, default: '' },
  disabled: Boolean,
  clearable: Boolean,
  id: { type: String, default: undefined },
});
const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const search = ref(null);
const open = ref(false);
const query = ref('');
const active = ref(0);

const labelOf = (o) => (typeof props.label === 'function' ? props.label(o) : o?.[props.label]) ?? '';
const hintOf = (o) => (props.hint ? (typeof props.hint === 'function' ? props.hint(o) : o?.[props.hint]) : '');
const selected = computed(() => props.options.find((o) => o[props.valueKey] === props.modelValue) || null);
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase();
  const list = q
    ? props.options.filter((o) => `${labelOf(o)} ${hintOf(o) || ''}`.toLowerCase().includes(q))
    : props.options;
  return list.slice(0, 200);
});

watch(query, () => { active.value = 0; });

const toggle = () => (open.value ? (open.value = false) : openAndFocus());
const openAndFocus = async () => {
  open.value = true;
  query.value = '';
  active.value = Math.max(0, filtered.value.findIndex((o) => o[props.valueKey] === props.modelValue));
  await nextTick();
  search.value?.focus();
};
const move = (d) => {
  active.value = Math.min(Math.max(0, active.value + d), Math.max(0, filtered.value.length - 1));
};
const choose = (opt) => {
  if (opt === undefined) return;
  const value = opt ? opt[props.valueKey] : null;
  emit('update:modelValue', value);
  emit('change', value);
  open.value = false;
};

const onDocClick = (e) => {
  if (open.value && root.value && !root.value.contains(e.target)) open.value = false;
};
onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick));
</script>
