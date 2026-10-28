<template>
  <!-- Skool Monitor mark: a graduation cap over a monitor pulse, on the brand green. -->
  <svg :width="size" :height="size" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Skool Monitor">
    <defs>
      <linearGradient :id="gid" x1="4" y1="2" x2="36" y2="38" gradientUnits="userSpaceOnUse">
        <stop stop-color="#10b981" />
        <stop offset="1" stop-color="#047857" />
      </linearGradient>
    </defs>
    <rect width="40" height="40" rx="11" :fill="`url(#${gid})`" />
    <rect x="0.75" y="0.75" width="38.5" height="38.5" rx="10.25" stroke="#ffffff" stroke-opacity="0.18" stroke-width="1.5" />
    <path d="M7.5 15.2 20 9.2l12.5 6L20 21.2z" fill="#ffffff" />
    <path d="M13 18.3v3.2c2 1.6 4.4 2.4 7 2.4s5-.8 7-2.4v-3.2" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.92" />
    <path d="M30.6 16.4v4.2" stroke="#a7f3d0" stroke-width="1.6" stroke-linecap="round" />
    <circle cx="30.6" cy="22" r="1.7" fill="#a7f3d0" />
    <path d="M8.5 29.6h5.6l2.4-3.6 3.4 7 2.8-5.4 1.8 2h7" stroke="#ffffff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" />
  </svg>
</template>

<script setup>
defineProps({ size: { type: [Number, String], default: 32 } });
// Unique gradient id, so several marks on one page don't clash.
const gid = `skool-mark-${Math.random().toString(36).slice(2, 9)}`;
</script>
