<script setup lang="ts">
defineProps<{
    colors: string[]
}>()
</script>

<template>
    <div class="relative aspect-[16/9] w-full overflow-hidden rounded bg-white ring-1 ring-gray-300">
        <div class="absolute inset-y-0 left-0 w-1/6" :style="{ backgroundColor: colors[0] }" />
        <div class="absolute left-[3%] top-[18%] h-0.5 w-[10%]" :style="{ backgroundColor: colors[6] }" />
        <div class="absolute left-[3%] top-[34%] h-0.5 w-[10%]" :style="{ backgroundColor: colors[6] }" />
        <div class="absolute left-[3%] top-[50%] h-0.5 w-[10%]" :style="{ backgroundColor: colors[6] }" />
        <div class="absolute right-[8%] top-[20%] h-[12%] w-[22%] rounded-sm" :style="{ backgroundColor: `${colors[0]}66` }" />
    </div>
</template>
