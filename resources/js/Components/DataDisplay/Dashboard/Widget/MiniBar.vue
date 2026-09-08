<script setup lang="ts">
defineProps<{
    value: number
    max: number
    color: string
}>()
</script>

<template>
    <div class="mt-1 h-1 rounded bg-gray-100 overflow-hidden">
        <div class="h-full rounded transition-[width] duration-500" :class="color" :style="{ width: `${max ? (value / max * 100) : 0}%` }" />
    </div>
</template>
