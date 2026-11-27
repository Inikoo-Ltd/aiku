<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { useStaffCalls } from "@/Stores/staff-calls"

const props = defineProps<{ mono?: boolean }>()

const calls = useStaffCalls()
const palette = ["#7c5cff", "#ff5a7a", "#14c2a3", "#ffb020"]
const ballRadius = [0, 0, 24, 22, 22]

const peopleOnCall = computed(() => {
    const call = calls.active?.call
    if (!call) {
        return 0
    }
    return Math.min(4, Math.max(2, call.present_user_ids.length + call.guests_in.length))
})

const isVideoCall = computed(() => !!calls.active && calls.active.call.kind !== "voice")

const parting = ref(false)
watch(() => !!calls.active, (isOnCall, wasOnCall) => {
    if (wasOnCall && !isOnCall) {
        parting.value = true
        setTimeout(() => (parting.value = false), 900)
    }
})

const balls = computed(() => {
    const count = peopleOnCall.value
    if (count) {
        const orbit = count === 2 ? 22 : 26
        return Array.from({ length: count }, (_, index) => {
            const angle = (index / count) * Math.PI * 2 - Math.PI / 2
            return { cx: 50 + orbit * Math.cos(angle), cy: 50 + orbit * Math.sin(angle), r: ballRadius[count] }
        })
    }
    if (parting.value) {
        return [{ cx: 14, cy: 50, r: 14 }, { cx: 86, cy: 50, r: 14 }]
    }
    return [{ cx: 38, cy: 41, r: 30 }, { cx: 66, cy: 60, r: 27 }]
})

const fill = (index: number) => props.mono ? "currentColor" : palette[index]
const opacity = (index: number) => props.mono ? (index % 2 ? 0.55 : 1) : (index === 1 && !peopleOnCall.value ? 0.85 : 1)
</script>

<template>
    <svg viewBox="0 0 100 100" width="1em" height="1em" class="inline-block shrink-0 align-[-0.125em] overflow-visible" aria-hidden="true">
        <g v-if="isVideoCall">
            <rect x="8" y="18" width="84" height="64" rx="18" fill="none" :stroke="fill(0)" stroke-width="10" />
            <circle cx="50" cy="50" r="13" :fill="fill(1)" class="desk-pulse" />
        </g>
        <g v-else :class="{ 'desk-spin': peopleOnCall }">
            <circle v-for="(ball, index) in balls" :key="index" v-bind="ball" :fill="fill(index)" :fill-opacity="opacity(index)" class="desk-circle" :style="{ opacity: parting ? 0.6 : 1 }" />
        </g>
    </svg>
</template>

<style scoped>
.desk-circle {
    transition: cx 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), cy 0.6s cubic-bezier(0.34, 1.56, 0.64, 1), r 0.6s ease, opacity 0.6s ease;
}

.desk-spin {
    transform-origin: 50px 50px;
    animation: desk-spin 3s linear infinite;
}

@keyframes desk-spin {
    to {
        transform: rotate(360deg);
    }
}

.desk-pulse {
    transform-origin: 50px 50px;
    animation: desk-pulse 1.6s ease-in-out infinite;
}

@keyframes desk-pulse {
    50% {
        transform: scale(1.35);
    }
}

@media (prefers-reduced-motion: reduce) {
    .desk-spin,
    .desk-pulse {
        animation: none;
    }
}
</style>
