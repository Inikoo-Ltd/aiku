<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import { defaultWindow, onClickOutside, useEventListener } from "@vueuse/core"
import { ctrans } from "@/Composables/useTrans"
import Image from "@/Common/Components/Image.vue"
import { filterComparisonFamilies } from "@/Iris/Components/BlocksUtils/CategoryComparison/filterComparisonFamilies"
import type { ComparisonFamily, ScreenType } from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

const props = defineProps<{
    options: ComparisonFamily[]
    selectedSlugs: string[]
    max: number
    screenType: ScreenType
}>()

const emits = defineEmits<{
    (e: "toggle", slug: string): void
}>()

const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const isOpen = ref(false)
const search = ref("")
const panelPosition = ref({ top: 0, left: 0 })

onClickOutside(root, () => {
    isOpen.value = false
}, { ignore: [panel] })

const PANEL_MARGIN = 8

const panelWidthInPixels = computed(() => (props.screenType === "mobile" ? 256 : 320))

const updatePanelPosition = () => {
    const anchor = trigger.value?.getBoundingClientRect()

    if (!anchor) {
        return
    }

    const maxLeft = window.innerWidth - panelWidthInPixels.value - PANEL_MARGIN

    panelPosition.value = {
        top: anchor.bottom + 4,
        left: Math.max(PANEL_MARGIN, Math.min(anchor.left, maxLeft)),
    }
}

useEventListener(defaultWindow, "scroll", () => isOpen.value && updatePanelPosition(), true)
useEventListener(defaultWindow, "resize", () => isOpen.value && updatePanelPosition())

watch(isOpen, async open => {
    if (!open) {
        search.value = ""
        return
    }

    await nextTick()
    updatePanelPosition()
    searchInput.value?.focus()
})

const isSelected = (slug: string) => props.selectedSlugs.includes(slug)

const isFull = computed(() => props.selectedSlugs.length >= props.max)

const visibleOptions = computed(() => filterComparisonFamilies(props.options, search.value))

const buttonLabel = computed(
    () => `${ctrans("Ranges compared")} (${props.selectedSlugs.length}/${props.max})`
)

const sizeClasses = computed(() =>
    props.screenType === "mobile" ? "h-8 text-xs" : "h-9 text-sm"
)

const panelWidth = computed(() => (props.screenType === "mobile" ? "w-64" : "w-80"))
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
            ref="trigger"
            type="button"
            class="flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 text-gray-700 hover:border-gray-400"
            :class="sizeClasses"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            @click="isOpen = !isOpen"
        >
            {{ buttonLabel }}
            <span class="text-[10px] leading-none" aria-hidden="true">{{ isOpen ? "▲" : "▼" }}</span>
        </button>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="panel"
                class="fixed z-50 rounded-md border border-gray-200 bg-white text-left shadow-lg"
                :class="panelWidth"
                :style="{ top: `${panelPosition.top}px`, left: `${panelPosition.left}px` }"
            >
                <div class="border-b border-gray-100 p-2">
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="search"
                        class="h-8 w-full rounded border border-gray-300 px-2 text-sm text-gray-700 placeholder-gray-400 focus:border-gray-400 focus:outline-none"
                        :placeholder="ctrans('Search by code or name')"
                    />
                </div>

                <div role="listbox" class="max-h-72 overflow-y-auto py-1">
                    <label
                        v-for="option in visibleOptions"
                        :key="option.slug"
                        class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50"
                        :class="!isSelected(option.slug as string) && isFull ? 'cursor-not-allowed opacity-40' : ''"
                    >
                        <input
                            type="checkbox"
                            class="h-4 w-4 shrink-0 rounded border-gray-300"
                            :checked="isSelected(option.slug as string)"
                            :disabled="!isSelected(option.slug as string) && isFull"
                            @change="emits('toggle', option.slug as string)"
                        />

                        <div class="h-9 w-9 shrink-0 overflow-hidden rounded border border-gray-100 bg-white">
                            <Image
                                :src="option.image"
                                :alt="option.name"
                                class="h-full w-full object-contain"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="truncate text-gray-700">{{ option.name }}</div>
                            <div v-if="option.code" class="truncate text-xs text-gray-400">{{ option.code }}</div>
                        </div>
                    </label>

                    <div v-if="!options.length" class="px-3 py-2 text-sm text-gray-400">
                        {{ ctrans("No other range to compare") }}
                    </div>

                    <div v-else-if="!visibleOptions.length" class="px-3 py-2 text-sm text-gray-400">
                        {{ ctrans("No range matches your search") }}
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
