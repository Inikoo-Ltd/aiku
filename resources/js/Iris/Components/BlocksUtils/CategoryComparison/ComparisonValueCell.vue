<script setup lang="ts">
import { computed, ref } from "vue"
import Popover from "primevue/popover"
import type { ComparisonValue } from "@/Iris/Components/BlocksUtils/CategoryComparison/formatComparisonValue"

const props = defineProps<{
    value: ComparisonValue
    isCurrent?: boolean
}>()

const popover = ref<any>(null)

const color = computed(
    () => props.value.text && !props.isCurrent ? "var(--comparison-link)" : "var(--comparison-value)"
)

const togglePopover = (event: Event) => popover.value?.toggle?.(event)
</script>

<template>
    <span class="inline-flex flex-wrap items-center justify-center gap-1">
        <span :style="{ color }">{{ value.text ?? "-" }}</span>

        <button
            v-if="value.hiddenCount > 0"
            type="button"
            class="rounded px-1 text-[0.9em] leading-none underline decoration-dotted underline-offset-2 hover:opacity-70"
            :style="{ color }"
            @click="togglePopover"
        >
            +{{ value.hiddenCount }}
        </button>

        <Popover v-if="value.hiddenCount > 0" ref="popover">
            <ul class="max-h-64 max-w-[240px] space-y-1 overflow-y-auto text-xs text-gray-700">
                <li v-for="listed in value.values" :key="listed">{{ listed }}</li>
            </ul>
        </Popover>
    </span>
</template>
