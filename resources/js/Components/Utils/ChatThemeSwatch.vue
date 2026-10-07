<script setup lang="ts">
import { computed } from "vue"
import { useChatThemes } from "@/Composables/useChatThemes"

const props = defineProps<{
    themeKey: string
}>()

const theme = computed(() => useChatThemes[props.themeKey] ?? null)
</script>

<template>
    <div class="relative flex aspect-[16/9] w-full overflow-hidden rounded bg-white ring-1 ring-gray-300">
        <template v-if="theme">
            <div class="h-full w-1/3 border-r border-gray-200 bg-white" />
            <div class="flex h-full flex-1 flex-col justify-center gap-1 px-2" :style="{ backgroundColor: theme.bg }">
                <div class="h-0.5 w-1/2 rounded" :style="{ backgroundColor: theme.text }" />
                <div class="h-0.5 w-1/3 rounded" :style="{ backgroundColor: theme.muted }" />
                <div class="h-0.5 w-1/4 rounded" :style="{ backgroundColor: theme.accent }" />
                <span class="truncate text-[10px]" :style="{ color: theme.label }">{{ theme.name }}</span>
            </div>
        </template>
        <span v-else class="m-auto text-[10px] text-gray-400">{{ themeKey }}</span>
    </div>
</template>
