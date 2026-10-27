<template>
  <div>
    <!-- Desktop / tablet: weekly grid -->
    <div class="hidden overflow-x-auto md:block">
      <table class="tt-grid w-full min-w-[760px] table-fixed border-separate border-spacing-0 text-sm">
        <thead>
          <tr>
            <th class="sticky left-0 z-10 w-28 border-b border-slate-200 bg-slate-50 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              Period
            </th>
            <th
              v-for="d in days"
              :key="d"
              class="border-b border-l border-slate-200 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
              :class="d === today ? 'bg-brand-50 text-brand-800' : 'bg-slate-50 text-slate-500'"
            >
              {{ d }}
              <span v-if="d === today" class="ml-1 rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold normal-case text-white">Today</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="p in periods" :key="p.period_number">
            <tr v-if="p.period_type !== 'lesson'">
              <td class="sticky left-0 z-10 border-b border-slate-200 px-3 py-2" :class="blockStyle(p).cell">
                <p class="text-xs font-semibold" :class="blockStyle(p).text">{{ p.label }}</p>
                <p class="text-[11px] text-slate-400">{{ shortTime(p.start_time) }}–{{ shortTime(p.end_time) }}</p>
              </td>
              <td :colspan="days.length" class="border-b border-l border-slate-200 px-3 text-center text-xs font-semibold uppercase tracking-[0.2em]" :class="[blockStyle(p).cell, blockStyle(p).text, p.period_type === 'lunch' ? 'py-4' : 'py-2']">
                <i :class="blockStyle(p).icon" class="mr-2"></i>{{ p.label }}<span class="ml-2 font-normal normal-case tracking-normal opacity-80">{{ shortTime(p.start_time) }}–{{ shortTime(p.end_time) }}</span>
              </td>
            </tr>
            <tr v-else>
              <td class="sticky left-0 z-10 border-b border-slate-200 bg-white px-3 py-2 align-top">
                <p class="text-xs font-semibold text-slate-700">{{ p.label }}</p>
                <p class="text-[11px] text-slate-400">{{ shortTime(p.start_time) }}–{{ shortTime(p.end_time) }}</p>
              </td>
              <template v-for="d in days" :key="d">
                <td
                  v-if="!cells[d][p.period_number].skip"
                  :rowspan="cells[d][p.period_number].rowspan"
                  class="h-20 border-b border-l border-slate-200 p-1.5 align-top"
                  :class="[d === today ? 'bg-brand-50/30' : 'bg-white', dropTarget === `${d}|${p.period_number}` ? 'bg-brand-100 ring-2 ring-inset ring-brand-400' : '']"
                  @dragover.prevent="onDragOver(d, p.period_number)"
                  @dragleave="dropTarget = null"
                  @drop.prevent="onDrop(d, p.period_number)"
                >
                  <div class="flex h-full flex-col gap-1">
                    <button
                      v-for="item in cells[d][p.period_number].items"
                      :key="item.entry.id + '-' + item.offset"
                      type="button"
                      class="lesson-card group relative flex min-h-[4rem] w-full flex-1 flex-col rounded-xl border px-2.5 py-2 text-left transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-500"
                      :class="[conflictSet.has(item.entry.id) ? 'ring-2 ring-red-500' : '', focusId === item.entry.id ? 'ring-2 ring-brand-600 ring-offset-1' : '']"
                      :style="cardStyle(item.entry)"
                      :draggable="editable && !item.entry.is_locked"
                      :title="describe(item.entry)"
                      @dragstart="onDragStart(item.entry)"
                      @dragend="dragging = null; dropTarget = null"
                      @click="$emit('open', item.entry)"
                    >
                      <span class="flex items-start justify-between gap-1">
                        <span class="truncate text-[13px] font-semibold leading-tight">{{ entryTitle(item.entry) }}</span>
                        <span class="flex shrink-0 items-center gap-1 text-[10px]">
                          <i v-if="conflictSet.has(item.entry.id)" class="fas fa-triangle-exclamation text-red-600" title="Clash"></i>
                          <i v-if="item.entry.is_locked" class="fas fa-lock opacity-70" title="Locked"></i>
                        </span>
                      </span>
                      <span v-if="item.offset > 0" class="text-[11px] italic opacity-70">continued</span>
                      <template v-else>
                        <span v-if="mode !== 'class' && item.entry.full_class_name" class="truncate text-[11px] font-medium opacity-90">{{ item.entry.full_class_name }}</span>
                        <span v-if="mode !== 'teacher' && item.entry.teacher_name" class="truncate text-[11px] opacity-80">{{ item.entry.teacher_name }}</span>
                        <span class="mt-auto flex items-center justify-between gap-1 pt-1 text-[10px] opacity-75">
                          <span v-if="mode !== 'room'" class="truncate">
                            <i class="fas fa-door-open mr-0.5"></i>{{ item.entry.room_code || item.entry.room_name || 'No room' }}
                          </span>
                          <span class="shrink-0">{{ shortTime(item.entry.start_time) }}–{{ shortTime(item.entry.end_time) }}</span>
                        </span>
                        <span v-if="item.span > 1" class="absolute -top-2 right-2 rounded-full bg-white px-1.5 text-[9px] font-bold uppercase tracking-wide shadow-sm" :style="{ color: subjectColour(item.entry.subject_id).text }">
                          {{ spanLabel(item.span) }}
                        </span>
                      </template>
                    </button>

                    <button
                      v-if="editable && !cells[d][p.period_number].items.length"
                      type="button"
                      class="flex h-full min-h-[4rem] w-full items-center justify-center rounded-xl border border-dashed border-transparent text-slate-300 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-600 focus:border-brand-400 focus:text-brand-600 focus:outline-none"
                      :aria-label="`Add lesson on ${d}, ${p.label}`"
                      @click="$emit('add', { day: d, period: p.period_number })"
                    >
                      <i class="fas fa-plus"></i>
                    </button>
                  </div>
                </td>
              </template>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Phone: one day at a time -->
    <div class="md:hidden">
      <div class="mb-3 flex gap-1 overflow-x-auto pb-1">
        <button
          v-for="d in days"
          :key="d"
          type="button"
          class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold"
          :class="mobileDay === d ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-600'"
          @click="mobileDay = d"
        >
          {{ d.slice(0, 3) }}
        </button>
      </div>
      <ul class="space-y-2">
        <li v-for="p in periods" :key="p.period_number">
          <div v-if="p.period_type !== 'lesson'" class="rounded-xl px-3 py-2 text-center text-xs font-semibold uppercase tracking-widest" :class="[blockStyle(p).cell, blockStyle(p).text]">
            <i :class="blockStyle(p).icon" class="mr-1"></i>
            {{ p.label }} · {{ shortTime(p.start_time) }}–{{ shortTime(p.end_time) }}
          </div>
          <div v-else-if="!(cells[mobileDay]?.[p.period_number]?.covered)" class="flex gap-3">
            <div class="w-14 shrink-0 pt-2">
              <p class="text-xs font-semibold text-slate-700">{{ p.label.replace('Period ', 'P') }}</p>
              <p class="text-[10px] text-slate-400">{{ shortTime(p.start_time) }}</p>
            </div>
            <div class="flex-1 space-y-1.5">
              <button
                v-for="item in (cells[mobileDay]?.[p.period_number]?.items || []).filter((i) => i.offset === 0 || cells[mobileDay][p.period_number].items.length > 1)"
                :key="item.entry.id"
                type="button"
                class="w-full rounded-xl border px-3 py-2 text-left"
                :class="conflictSet.has(item.entry.id) ? 'ring-2 ring-red-500' : ''"
                :style="cardStyle(item.entry)"
                @click="$emit('open', item.entry)"
              >
                <p class="text-sm font-semibold">{{ entryTitle(item.entry) }} <span v-if="item.span > 1" class="text-[10px] font-bold uppercase">· {{ spanLabel(item.span) }}</span></p>
                <p class="text-xs opacity-80">
                  {{ [mode !== 'class' && item.entry.full_class_name, mode !== 'teacher' && item.entry.teacher_name, mode !== 'room' && (item.entry.room_code || item.entry.room_name)].filter(Boolean).join(' · ') }}
                </p>
                <p class="text-[11px] opacity-70">{{ shortTime(item.entry.start_time) }}–{{ shortTime(item.entry.end_time) }}</p>
              </button>
              <button
                v-if="editable && !(cells[mobileDay]?.[p.period_number]?.items || []).length"
                type="button"
                class="w-full rounded-xl border border-dashed border-slate-200 px-3 py-3 text-left text-xs text-slate-400"
                @click="$emit('add', { day: mobileDay, period: p.period_number })"
              >
                <i class="fas fa-plus mr-1"></i> Free, add lesson
              </button>
              <p v-else-if="!(cells[mobileDay]?.[p.period_number]?.items || []).length" class="rounded-xl border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-400">Free</p>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { indexEntries, entryTitle, shortTime, subjectColour } from '../../utils/timetable.js';

const props = defineProps({
  entries: { type: Array, default: () => [] },
  periods: { type: Array, default: () => [] },
  days: { type: Array, default: () => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] },
  mode: { type: String, default: 'class' },
  editable: Boolean,
  conflictIds: { type: Array, default: () => [] },
  focusId: { type: Number, default: null },
});
const emit = defineEmits(['open', 'add', 'move']);

const today = new Date().toLocaleDateString('en-US', { weekday: 'long' });
const mobileDay = ref(props.days.includes(today) ? today : props.days[0]);
watch(() => props.days, (d) => { if (!d.includes(mobileDay.value)) mobileDay.value = d[0]; });

const conflictSet = computed(() => new Set(props.conflictIds));
const lessonPeriods = computed(() => props.periods.filter((p) => p.period_type === 'lesson').map((p) => Number(p.period_number)));

/**
 * cells[day][period] = { items, rowspan, skip, covered }. A lone lesson that
 * starts here and has its span to itself is drawn once with a rowspan;
 * anything overlapping (a clash) is drawn per cell so nothing is hidden.
 */
const cells = computed(() => {
  const index = indexEntries(props.entries);
  const out = {};
  for (const d of props.days) {
    out[d] = {};
    for (const p of props.periods) out[d][p.period_number] = { items: [], rowspan: 1, skip: false, covered: false };
    for (const pn of lessonPeriods.value) {
      const cell = out[d][pn];
      if (cell.skip) continue;
      const items = index[`${d}|${pn}`] || [];
      cell.items = items;
      if (items.length === 1 && items[0].isStart && items[0].span > 1) {
        const { span, entry } = items[0];
        const coveredClean = Array.from({ length: span - 1 }, (_, i) => pn + i + 1).every((q) => {
          const here = index[`${d}|${q}`] || [];
          return lessonPeriods.value.includes(q) && here.length === 1 && here[0].entry.id === entry.id;
        });
        if (coveredClean) {
          cell.rowspan = span;
          for (let i = 1; i < span; i++) {
            out[d][pn + i].skip = true;
            out[d][pn + i].covered = true;
          }
        }
      }
    }
  }
  return out;
});

/** Breaks, lunch and assemblies get their own look so they read as schedule blocks, not empty lessons. */
const blockStyle = (p) => ({
  lunch: { cell: 'bg-amber-50', text: 'text-amber-800', icon: 'fas fa-utensils' },
  assembly: { cell: 'bg-sky-50', text: 'text-sky-800', icon: 'fas fa-people-group' },
}[p.period_type] || { cell: 'bg-slate-100', text: 'text-slate-500', icon: 'fas fa-mug-hot' });
const spanLabel = (n) => ({ 2: 'Double', 3: 'Triple', 4: 'Quad' }[n] || `${n} periods`);
const cardStyle = (e) => {
  if (e.entry_type === 'event') {
    return { background: '#F1F5F9', borderColor: e.event_color || '#CBD5E1', color: '#0F172A', borderLeftWidth: '4px' };
  }
  const c = subjectColour(e.subject_id);
  return { background: c.bg, borderColor: c.border, color: c.text };
};
const describe = (e) => [entryTitle(e), e.full_class_name, e.teacher_name, e.room_name || e.room_code, `${shortTime(e.start_time)}–${shortTime(e.end_time)}`]
  .filter(Boolean).join(' · ');

const dragging = ref(null);
const dropTarget = ref(null);
const onDragStart = (entry) => { dragging.value = entry; };
const onDragOver = (d, p) => { if (dragging.value) dropTarget.value = `${d}|${p}`; };
const onDrop = (d, p) => {
  const entry = dragging.value;
  dragging.value = null;
  dropTarget.value = null;
  if (entry && (entry.day_of_week !== d || Number(entry.period_number) !== p)) emit('move', { entry, day: d, period: p });
};
</script>
