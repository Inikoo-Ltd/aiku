<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"

const props = defineProps<{
    label?: string
    angry?: boolean
}>()

const palettes = {
    calm: { halo: ["#a855f7", "#c026d3", "#7c3aed"], rim: ["#ffffff", "#f0abfc", "#a855f7", "#60a5fa"], a: ["#e879f9", "#ffffff", "#a78bfa"], b: ["#60a5fa", "#818cf8", "#f5d0fe", "#c084fc"], base: "#9333ea", flare: ["#fae8ff", "#e879f9"] },
    angry: { halo: ["#ef4444", "#dc2626", "#b91c1c"], rim: ["#ffffff", "#fecaca", "#ef4444", "#f97316"], a: ["#f97316", "#fff7ed", "#ef4444"], b: ["#dc2626", "#f43f5e", "#fed7aa", "#fb923c"], base: "#dc2626", flare: ["#fee2e2", "#f87171"] },
}

const mood = computed(() => (props.angry ? "angry" : "calm"))
const palette = computed(() => palettes[mood.value])
const id = (part: string) => `aura-avatar-${mood.value}-${part}`
const paint = (part: string) => `url(#${id(part)})`
</script>

<template>
    <svg class="aura-avatar" :class="{ 'aura-avatar-angry': angry }" viewBox="0 0 48 48" :role="label ? 'img' : undefined" :aria-label="label" :aria-hidden="label ? undefined : true">
        <title v-if="label">{{ label }}</title>
        <defs>
            <radialGradient :id="id('halo')">
                <stop offset="0.36" :stop-color="palette.halo[0]" stop-opacity="0" />
                <stop offset="0.6" :stop-color="palette.halo[0]" stop-opacity="0.7" />
                <stop offset="0.72" :stop-color="palette.halo[1]" stop-opacity="0.28" />
                <stop offset="1" :stop-color="palette.halo[2]" stop-opacity="0" />
            </radialGradient>
            <linearGradient :id="id('rim')" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" :stop-color="palette.rim[0]" />
                <stop offset="0.3" :stop-color="palette.rim[1]" />
                <stop offset="0.7" :stop-color="palette.rim[2]" stop-opacity="0.7" />
                <stop offset="1" :stop-color="palette.rim[3]" stop-opacity="0.9" />
            </linearGradient>
            <linearGradient :id="id('ribbon-a')" x1="0" y1="0" x2="1" y2="0.3">
                <stop offset="0" :stop-color="palette.a[0]" stop-opacity="0" />
                <stop offset="0.3" :stop-color="palette.a[0]" stop-opacity="0.9" />
                <stop offset="0.55" :stop-color="palette.a[1]" />
                <stop offset="0.8" :stop-color="palette.a[2]" stop-opacity="0.8" />
                <stop offset="1" :stop-color="palette.a[2]" stop-opacity="0" />
            </linearGradient>
            <linearGradient :id="id('ribbon-b')" x1="0" y1="1" x2="1" y2="0">
                <stop offset="0" :stop-color="palette.b[0]" stop-opacity="0" />
                <stop offset="0.4" :stop-color="palette.b[1]" stop-opacity="0.95" />
                <stop offset="0.65" :stop-color="palette.b[2]" />
                <stop offset="1" :stop-color="palette.b[3]" stop-opacity="0" />
            </linearGradient>
            <radialGradient :id="id('flare')">
                <stop offset="0" stop-color="#ffffff" />
                <stop offset="0.3" :stop-color="palette.flare[0]" stop-opacity="0.85" />
                <stop offset="1" :stop-color="palette.flare[1]" stop-opacity="0" />
            </radialGradient>
            <filter :id="id('glow')" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur stdDeviation="1.7" />
            </filter>
            <filter :id="id('soft')" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur stdDeviation="0.35" />
            </filter>
        </defs>

        <circle class="aura-avatar-breathe" cx="24" cy="24" r="23" :fill="paint('halo')" />

        <g :filter="paint('glow')" fill="none" stroke-linecap="round">
            <circle cx="24" cy="24" r="14.6" :stroke="palette.base" stroke-width="3.2" opacity="0.6" />
            <g class="aura-avatar-turn aura-avatar-t1"><ellipse cx="24" cy="24" rx="16.5" ry="14.5" :stroke="paint('ribbon-a')" stroke-width="4" /></g>
            <g class="aura-avatar-turn aura-avatar-t2"><ellipse cx="24" cy="24" rx="14.5" ry="17" :stroke="paint('ribbon-b')" stroke-width="3.5" /></g>
        </g>

        <g :filter="paint('soft')" fill="none" stroke-linecap="round">
            <g class="aura-avatar-turn aura-avatar-t1"><ellipse cx="24" cy="24" rx="16.5" ry="14.2" :stroke="paint('ribbon-a')" stroke-width="1.5" /></g>
            <g class="aura-avatar-turn aura-avatar-t2"><ellipse cx="24" cy="24" rx="14.2" ry="17.2" :stroke="paint('ribbon-b')" stroke-width="1.3" /></g>
            <g class="aura-avatar-turn aura-avatar-t3"><ellipse cx="24" cy="24" rx="18.8" ry="15.2" :stroke="paint('ribbon-a')" stroke-width="0.7" /></g>
            <g class="aura-avatar-turn aura-avatar-t4"><ellipse cx="24" cy="24" rx="15.4" ry="19.4" :stroke="paint('ribbon-b')" stroke-width="0.6" /></g>
            <g class="aura-avatar-turn aura-avatar-t5"><ellipse cx="24" cy="24" rx="20.4" ry="17" :stroke="paint('ribbon-a')" stroke-width="0.45" opacity="0.8" /></g>
            <g class="aura-avatar-turn aura-avatar-t6"><ellipse cx="24" cy="24" rx="13.6" ry="15.6" :stroke="paint('ribbon-b')" stroke-width="0.9" /></g>
        </g>
        <circle cx="24" cy="24" r="11.6" fill="none" :stroke="paint('rim')" stroke-width="0.9" />

        <g class="aura-avatar-turn aura-avatar-t7">
            <g class="aura-avatar-twinkle">
                <circle cx="14.6" cy="15.6" r="4.4" :fill="paint('flare')" />
                <ellipse cx="14.6" cy="15.6" rx="7.5" ry="0.45" fill="#ffffff" opacity="0.85" transform="rotate(-38 14.6 15.6)" />
                <ellipse cx="14.6" cy="15.6" rx="4.5" ry="0.35" fill="#ffffff" opacity="0.7" transform="rotate(52 14.6 15.6)" />
            </g>
        </g>
    </svg>
</template>

<style scoped>
.aura-avatar {
    display: inline-block;
    overflow: visible;
}

.aura-avatar-turn {
    transform-box: view-box;
    transform-origin: 24px 24px;
    animation: aura-avatar-turn 11s linear infinite;
}

.aura-avatar-t2 {
    animation-duration: 15s;
    animation-direction: reverse;
}

.aura-avatar-t3 {
    animation-duration: 19s;
    animation-delay: -6s;
}

.aura-avatar-t4 {
    animation-duration: 23s;
    animation-direction: reverse;
    animation-delay: -9s;
}

.aura-avatar-t5 {
    animation-duration: 29s;
    animation-delay: -14s;
}

.aura-avatar-t6 {
    animation-duration: 8s;
    animation-direction: reverse;
    animation-delay: -3s;
}

.aura-avatar-t7 {
    animation-duration: 37s;
}

.aura-avatar-breathe {
    transform-box: view-box;
    transform-origin: 24px 24px;
    animation: aura-avatar-breathe 4.5s ease-in-out infinite;
}

.aura-avatar-twinkle {
    animation: aura-avatar-twinkle 3.2s ease-in-out infinite;
}

.aura-avatar-angry .aura-avatar-breathe {
    animation-duration: 1.4s;
}

@keyframes aura-avatar-turn {
    to {
        transform: rotate(360deg);
    }
}

@keyframes aura-avatar-breathe {
    50% {
        transform: scale(0.9);
        opacity: 0.75;
    }
}

@keyframes aura-avatar-twinkle {
    50% {
        opacity: 0.55;
    }
}

@media (prefers-reduced-motion: reduce) {
    .aura-avatar-turn,
    .aura-avatar-breathe,
    .aura-avatar-twinkle {
        animation: none;
    }
}
</style>
