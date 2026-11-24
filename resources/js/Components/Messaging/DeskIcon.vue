<script setup lang="ts">
import { computed, useId } from "vue"
import { useStaffCalls } from "@/Stores/staff-calls"

const calls = useStaffCalls()
const overlapId = `desk-overlap-${useId()}`
const isActive = computed(() => !!calls.active)

const left = computed(() => isActive.value ? { cx: 36, cy: 58, r: 26 } : { cx: 38, cy: 50, r: 28 })
const right = computed(() => isActive.value ? { cx: 64, cy: 40, r: 22 } : { cx: 62, cy: 50, r: 28 })
</script>

<template>
    <svg viewBox="0 0 100 100" width="1em" height="1em" class="inline-block shrink-0 align-[-0.125em]" aria-hidden="true">
        <defs>
            <clipPath :id="overlapId">
                <circle v-bind="left" class="desk-circle" />
            </clipPath>
        </defs>
        <circle v-bind="left" fill="#7c5cff" class="desk-circle" />
        <circle v-bind="right" fill="#ff5a7a" :fill-opacity="isActive ? 1 : 0.85" class="desk-circle" />
        <circle v-bind="right" fill="#14c2a3" :clip-path="`url(#${overlapId})`" class="desk-circle" :style="{ opacity: isActive ? 1 : 0 }" />
    </svg>
</template>

<style scoped>
.desk-circle {
    transition: cx 0.5s ease, cy 0.5s ease, r 0.5s ease, opacity 0.5s ease;
}
</style>
