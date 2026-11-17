<script setup lang="ts">
import { computed, useId } from "vue"

const props = withDefaults(defineProps<{
    mono?: boolean
    themed?: boolean
    animated?: boolean
}>(), {
    mono: false,
    themed: false,
    animated: true,
})

const gradientId = `merlin-gradient-${useId()}`

const paint = computed(() => props.mono ? "currentColor" : `url(#${gradientId})`)

const sparkle = (centreX: number, centreY: number, radius: number): string => {
    const pull = radius * 0.16

    return `M${centreX} ${centreY - radius}`
        + `Q${centreX + pull} ${centreY - pull} ${centreX + radius} ${centreY}`
        + `Q${centreX + pull} ${centreY + pull} ${centreX} ${centreY + radius}`
        + `Q${centreX - pull} ${centreY + pull} ${centreX - radius} ${centreY}`
        + `Q${centreX - pull} ${centreY - pull} ${centreX} ${centreY - radius}Z`
}

const bigSparkle = sparkle(25.7, 47.6, 9.4)
const middleSparkle = sparkle(35.2, 27.7, 5.9)
const smallSparkle = sparkle(40.6, 39.7, 4.4)
</script>

<template>
    <svg
        class="merlin-icon"
        :class="{ 'merlin-icon-animated': animated, 'merlin-icon-themed': themed }"
        viewBox="0 0 64 64"
        fill="none"
        aria-hidden="true"
        focusable="false"
    >
        <defs v-if="!mono">
            <linearGradient :id="gradientId" x1="4" y1="60" x2="60" y2="4" gradientUnits="userSpaceOnUse">
                <stop class="merlin-gradient-start" offset="0" stop-color="#ea580c" />
                <stop class="merlin-gradient-end" offset="1" stop-color="#facc15" />
            </linearGradient>
        </defs>
        <g :stroke="paint" stroke-linecap="round" stroke-linejoin="round">
            <g class="merlin-hat">
                <path
                    class="merlin-stroke-outline"
                    d="M7.6 59 21.6 27.6C23.4 23.6 26 21.1 29.5 18.9L56.6 2.7C52 10 48.1 18 45.5 25.6 43.8 30.6 43.9 32.7 45.6 36.2L56 59"
                />
                <path class="merlin-stroke-brim" d="M3.8 60.6H60.2" />
            </g>
            <path class="merlin-sparkle merlin-sparkle-big merlin-stroke-sparkle" :d="bigSparkle" />
            <path class="merlin-sparkle merlin-sparkle-middle merlin-stroke-fill" :fill="paint" :d="middleSparkle" />
            <path class="merlin-sparkle merlin-sparkle-small merlin-stroke-fill" :fill="paint" :d="smallSparkle" />
        </g>
    </svg>
</template>

<style scoped>
.merlin-icon {
    display: inline-block;
    width: 1.75em;
    height: 1.75em;
    flex-shrink: 0;
    vertical-align: -0.5em;
    overflow: visible;
    user-select: none;
}

.merlin-icon-themed .merlin-gradient-start {
    stop-color: color-mix(in srgb, var(--theme-color-4, #ef7d22) 85%, #000);
}

.merlin-icon-themed .merlin-gradient-end {
    stop-color: color-mix(in srgb, var(--theme-color-4, #ef7d22) 55%, #fff);
}

@supports (color: oklch(from red l c h)) {
    .merlin-icon-themed .merlin-gradient-start {
        stop-color: oklch(from var(--theme-color-4, #ef7d22) min(calc(l - 0.06), 0.6) max(c, 0.11) calc(h - 10));
    }

    .merlin-icon-themed .merlin-gradient-end {
        stop-color: oklch(from var(--theme-color-4, #ef7d22) min(calc(l + 0.12), 0.8) max(calc(c * 1.1), 0.13) calc(h + 40));
    }
}

.merlin-stroke-outline {
    stroke-width: calc(3.3 * var(--merlin-stroke-weight, 1));
}

.merlin-stroke-brim {
    stroke-width: calc(3.4 * var(--merlin-stroke-weight, 1));
}

.merlin-stroke-sparkle {
    stroke-width: calc(2.2 * var(--merlin-stroke-weight, 1));
}

.merlin-stroke-fill {
    stroke-width: calc(1 * var(--merlin-stroke-weight, 1));
}

.merlin-hat,
.merlin-sparkle {
    transform-box: fill-box;
}

.merlin-hat {
    transform-origin: 50% 100%;
}

.merlin-sparkle {
    transform-origin: center;
}

@media (prefers-reduced-motion: no-preference) {
    .merlin-icon-animated .merlin-hat {
        animation: merlin-hat-sway 4.8s ease-in-out infinite;
    }

    .merlin-icon-animated .merlin-sparkle {
        animation: merlin-sparkle-twinkle 2.4s ease-in-out infinite;
    }

    .merlin-icon-animated .merlin-sparkle-middle {
        animation-delay: 0.8s;
    }

    .merlin-icon-animated .merlin-sparkle-small {
        animation-delay: 1.6s;
    }
}

@keyframes merlin-hat-sway {
    0%, 100% {
        transform: rotate(0deg);
    }

    25% {
        transform: rotate(-4deg);
    }

    75% {
        transform: rotate(3deg);
    }
}

@keyframes merlin-sparkle-twinkle {
    0%, 60%, 100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }

    20% {
        transform: scale(0.45) rotate(45deg);
        opacity: 0.35;
    }

    40% {
        transform: scale(1.2) rotate(90deg);
        opacity: 1;
    }
}
</style>
