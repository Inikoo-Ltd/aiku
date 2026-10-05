<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"

defineProps<{
    isLive: boolean
    lastUpdate: Date | null
}>()
</script>

<template>
    <div v-if="isLive" class="flex items-center gap-2 text-xs text-gray-500">
        <span class="relative flex h-2 w-2">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
            <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
        </span>
        {{ ctrans("Live") }}
        <span v-if="lastUpdate" class="tabular-nums">· {{ ctrans("updated :time", { time: lastUpdate.toLocaleTimeString() }) }}</span>
    </div>
</template>
