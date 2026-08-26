<script setup lang="ts">
import { computed, ref } from "vue"
import { onClickOutside } from "@vueuse/core"
import { ctrans } from "@/Composables/useTrans"
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
const isOpen = ref(false)

onClickOutside(root, () => {
    isOpen.value = false
})

const isSelected = (slug: string) => props.selectedSlugs.includes(slug)

const isFull = computed(() => props.selectedSlugs.length >= props.max)

const buttonLabel = computed(
    () => `${ctrans("Ranges compared")} (${props.selectedSlugs.length}/${props.max})`
)

const sizeClasses = computed(() =>
    props.screenType === "mobile" ? "h-8 text-xs" : "h-9 text-sm"
)
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
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

        <div
            v-if="isOpen"
            role="listbox"
            class="absolute right-0 z-20 mt-1 max-h-72 w-64 overflow-y-auto rounded-md border border-gray-200 bg-white py-1 text-left shadow-lg"
        >
            <label
                v-for="option in options"
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
                <span class="truncate text-gray-700">{{ option.name }}</span>
            </label>

            <div v-if="!options.length" class="px-3 py-2 text-sm text-gray-400">
                {{ ctrans("No other range to compare") }}
            </div>
        </div>
    </div>
</template>
