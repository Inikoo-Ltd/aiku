<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { tileUrl, type ChatWallpaperCatalogue } from "@/Components/Messaging/chatWallpaper"

const props = defineProps<{
    catalogue: ChatWallpaperCatalogue
    showUsage?: boolean
    compact?: boolean
}>()

const pattern = defineModel<string>("pattern", { default: "none" })
const colour = defineModel<string>("colour", { default: "none" })

const colourHex = computed(() => props.catalogue.colours.find((option) => option.key === colour.value)?.hex ?? "#ffffff")

const colourLabel = (key: string) => ctrans(key.charAt(0).toUpperCase() + key.slice(1))

const usersLabel = (count: number) => (count === 1 ? ctrans("1 person") : ctrans(":count people", { count: String(count) }))

const swatchClass = (isChosen: boolean) =>
    [
        "relative flex shrink-0 items-end justify-center overflow-hidden rounded-md border transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]",
        props.compact ? "h-12 w-12" : "h-16 w-20",
        isChosen ? "border-[var(--app-accent)] ring-2 ring-[var(--app-accent)] ring-offset-1" : "border-gray-200 hover:border-gray-400",
    ]
</script>

<template>
    <div class="space-y-3">
        <div>
            <div class="mb-1.5 text-xs font-medium text-gray-500">{{ ctrans("Pattern") }}</div>
            <div class="flex flex-wrap gap-2">
                <button type="button" :class="swatchClass(pattern === 'none')" :style="{ backgroundColor: colourHex }" :aria-pressed="pattern === 'none'" @click="pattern = 'none'">
                    <span class="mb-1 rounded bg-white/90 px-1 text-[10px] text-gray-700">{{ ctrans("None") }}</span>
                </button>
                <button
                    v-for="option in catalogue.patterns"
                    :key="option.key"
                    type="button"
                    :class="swatchClass(pattern === option.key)"
                    :style="{ backgroundColor: colourHex, backgroundImage: tileUrl(option.key) ? `url('${tileUrl(option.key)}')` : undefined }"
                    :aria-pressed="pattern === option.key"
                    v-tooltip="showUsage ? usersLabel(option.users) : undefined"
                    @click="pattern = option.key">
                    <span v-if="option.is_new" class="absolute right-0.5 top-0.5 rounded bg-[var(--app-accent)] px-1 text-[9px] font-semibold uppercase text-[var(--app-accent-text)]">{{ ctrans("New") }}</span>
                    <span class="mb-1 max-w-full truncate rounded bg-white/90 px-1 text-[10px] text-gray-700">{{ ctrans(option.label) }}</span>
                </button>
            </div>
            <div v-if="showUsage" class="mt-1 text-xxs text-gray-400">{{ ctrans("Hover a pattern to see how many people use it") }}</div>
        </div>
        <div>
            <div class="mb-1.5 text-xs font-medium text-gray-500">{{ ctrans("Colour") }}</div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="h-7 w-7 rounded-full border bg-white transition duration-200"
                    :class="colour === 'none' ? 'border-[var(--app-accent)] ring-2 ring-[var(--app-accent)] ring-offset-1' : 'border-gray-300 hover:border-gray-500'"
                    v-tooltip="ctrans('White')"
                    :aria-label="ctrans('White')"
                    :aria-pressed="colour === 'none'"
                    @click="colour = 'none'" />
                <button
                    v-for="option in catalogue.colours"
                    :key="option.key"
                    type="button"
                    class="h-7 w-7 rounded-full border transition duration-200"
                    :class="colour === option.key ? 'border-[var(--app-accent)] ring-2 ring-[var(--app-accent)] ring-offset-1' : 'border-gray-300 hover:border-gray-500'"
                    :style="{ backgroundColor: option.hex }"
                    :aria-label="colourLabel(option.key)"
                    v-tooltip="colourLabel(option.key)"
                    :aria-pressed="colour === option.key"
                    @click="colour = option.key" />
            </div>
        </div>
    </div>
</template>
