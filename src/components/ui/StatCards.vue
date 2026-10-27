<template>
  <div class="grid grid-cols-2 gap-3" :class="cards.length >= 4 ? 'xl:grid-cols-4' : cards.length === 3 ? 'lg:grid-cols-3' : ''">
    <component
      :is="card.onClick ? 'button' : 'div'"
      v-for="card in cards"
      :key="card.label"
      :type="card.onClick ? 'button' : undefined"
      class="card flex items-center gap-3 px-3.5 py-3 text-left"
      :class="[card.onClick ? 'transition hover:border-brand-300' : '', card.active ? 'ring-2 ring-brand-500' : '']"
      @click="card.onClick && card.onClick()"
    >
      <span v-if="card.icon" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg ring-1" :class="TONES[card.tone || 'brand']">
        <component :is="card.icon" class="h-[1.1rem] w-[1.1rem]" />
      </span>
      <span class="min-w-0">
        <span class="block truncate text-xs font-medium text-gray-500">{{ card.label }}</span>
        <span class="block truncate text-xl font-bold leading-tight tabular-nums text-gray-900">{{ card.value }}</span>
        <!-- Always present, so every card has the same height and the numbers line up. -->
        <span class="block truncate text-[11px] leading-4 text-gray-400" :aria-hidden="!card.note">{{ card.note || ' ' }}</span>
      </span>
    </component>
  </div>
</template>

<script setup>
/**
 * Summary cards, as on Teaching Staff.
 * cards: [{ label, value, note?, icon?, tone?: 'brand'|'gray'|'amber'|'red'|'sky', onClick?, active? }]
 */
defineProps({ cards: { type: Array, required: true } });

const TONES = {
  brand: 'bg-brand-50 text-brand-700 ring-brand-100',
  gray: 'bg-gray-100 text-gray-600 ring-gray-200',
  amber: 'bg-amber-50 text-amber-700 ring-amber-100',
  red: 'bg-red-50 text-red-600 ring-red-100',
  sky: 'bg-sky-50 text-sky-700 ring-sky-100',
};
</script>
