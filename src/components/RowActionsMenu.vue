<template>
  <div class="inline-block">
    <button
      ref="trigger"
      type="button"
      class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-500"
      :class="open ? 'bg-gray-100 text-gray-800' : ''"
      :aria-label="label"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click.stop="toggle"
    >
      <MoreVertical class="h-4 w-4" />
    </button>
    <!-- Rendered on <body> so tables with overflow do not clip it -->
    <Teleport to="body">
      <div
        v-if="open"
        ref="menu"
        role="menu"
        class="fixed z-[120] min-w-[11rem] overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
        :style="position"
        @keydown.esc="close(true)"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
      >
        <template v-for="(item, i) in items" :key="item.label">
          <div v-if="item.danger && i > 0 && !items[i - 1].danger" class="my-1 h-px bg-gray-100"></div>
          <button
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-sm transition focus:outline-none"
            :class="item.danger ? 'text-red-600 hover:bg-red-50 focus:bg-red-50' : 'text-gray-700 hover:bg-gray-50 focus:bg-gray-50'"
            @click="choose(item)"
          >
            <component :is="item.icon" v-if="item.icon" class="h-4 w-4 flex-shrink-0" :class="item.danger ? '' : 'text-gray-400'" />
            {{ item.label }}
          </button>
        </template>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { MoreVertical } from 'lucide-vue-next';

/** items: [{ label, icon?, danger?, onClick }] */
const props = defineProps({
  items: { type: Array, required: true },
  label: { type: String, default: 'More actions' },
});

const open = ref(false);
const trigger = ref(null);
const menu = ref(null);
const position = ref({});

function place() {
  const r = trigger.value.getBoundingClientRect();
  const height = props.items.length * 38 + 16;
  const below = window.innerHeight - r.bottom > height + 8;
  position.value = {
    right: `${Math.max(8, window.innerWidth - r.right)}px`,
    ...(below ? { top: `${r.bottom + 4}px` } : { bottom: `${window.innerHeight - r.top + 4}px` }),
  };
}
async function toggle() {
  if (open.value) return close();
  place();
  open.value = true;
  document.addEventListener('mousedown', onOutside);
  window.addEventListener('scroll', onScroll, true);
  window.addEventListener('resize', close);
  await nextTick();
  menu.value?.querySelector('button')?.focus();
}
function close(refocus = false) {
  open.value = false;
  document.removeEventListener('mousedown', onOutside);
  window.removeEventListener('scroll', onScroll, true);
  window.removeEventListener('resize', close);
  if (refocus) trigger.value?.focus();
}
function onOutside(e) {
  if (!menu.value?.contains(e.target) && !trigger.value?.contains(e.target)) close();
}
function onScroll(e) {
  if (!menu.value?.contains(e.target)) close();
}
function move(d) {
  const buttons = [...(menu.value?.querySelectorAll('button') || [])];
  const i = buttons.indexOf(document.activeElement);
  buttons[(i + d + buttons.length) % buttons.length]?.focus();
}
function choose(item) {
  close();
  item.onClick?.();
}
onBeforeUnmount(close);
</script>
