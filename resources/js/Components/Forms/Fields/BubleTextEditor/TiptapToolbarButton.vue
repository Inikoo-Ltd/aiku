<script setup lang="ts">
defineProps<{
  label: string
  isActive?: boolean
}>()
</script>


<template>
    <button
      type="button"
      v-tooltip="label"
      :class="[
        'inline-flex !h-9 !w-9 sm:!h-8 sm:!w-8 shrink-0 flex-row items-center justify-center rounded-md disabled:bg-transparent disabled:text-gray-300',
        isActive
          ? 'bg-blue-200 text-blue-800 hover:bg-opacity-80'
          : 'text-gray-600 hover:bg-blue-50',
      ]"
      :aria-label="label"
    >
      <slot />
    </button>
  </template>
  
