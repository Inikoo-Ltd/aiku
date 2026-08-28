<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    isActive?: boolean
    preserveSelection?: boolean
  }>(),
  { preserveSelection: true }
)
</script>


<template>
    <button
      type="button"
      v-tooltip="label"
      @mousedown="(event: MouseEvent) => preserveSelection && event.preventDefault()"
      :class="[
        'inline-flex !h-7 !w-7 shrink-0 flex-row items-center justify-center rounded transition-colors disabled:bg-transparent disabled:text-gray-300 disabled:cursor-not-allowed',
        isActive
          ? 'bg-blue-100 text-blue-800 hover:bg-blue-200'
          : 'text-gray-600 hover:bg-blue-50',
      ]"
      :aria-label="label"
    >
      <slot />
    </button>
  </template>
