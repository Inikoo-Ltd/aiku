<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBullhorn, faTag, faCameraRetro, faPercent, faGlobe } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faBullhorn, faTag, faCameraRetro, faPercent, faGlobe)

const scatteredIcons = [
    { icon: "fal fa-bullhorn", position: "right-[36%] top-[16%]", size: "text-xl", colour: "text-pink-400", rotate: "-12deg", delay: "0s" },
    { icon: "fal fa-tag", position: "right-[32%] bottom-[16%]", size: "text-lg", colour: "text-purple-400", rotate: "18deg", delay: "0.6s" },
    { icon: "fal fa-camera-retro", position: "right-[3%] bottom-[14%]", size: "text-xl", colour: "text-purple-400", rotate: "10deg", delay: "1.8s" },
    { icon: "fal fa-percent", position: "right-[6%] top-[42%]", size: "text-base", colour: "text-sky-400", rotate: "-14deg", delay: "2.7s" },
    { icon: "fal fa-globe", position: "right-[4%] top-[10%]", size: "text-lg", colour: "text-pink-400", rotate: "-6deg", delay: "0.9s" },
]
</script>

<template>
    <div class="backdrop-fade pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div class="marketing-dots absolute inset-0" />

        <div class="backdrop-blob absolute -right-16 -top-20 h-64 w-64 rounded-full bg-pink-200/60 blur-3xl" />
        <div class="backdrop-blob absolute -bottom-24 right-1/4 h-56 w-56 rounded-full bg-sky-200/50 blur-3xl" style="animation-delay: -4s" />

        <div class="absolute right-[6%] top-1/2 hidden h-0 w-0 lg:block lg:right-[12%]">
            <span class="broadcast-ring" style="animation-delay: 0s" />
            <span class="broadcast-ring" style="animation-delay: 1.3s" />
            <span class="broadcast-ring" style="animation-delay: 2.6s" />
        </div>

        <svg class="absolute bottom-0 left-0 h-[55%] w-full opacity-40" viewBox="0 0 1000 120" preserveAspectRatio="none">
            <defs>
                <linearGradient id="marketing-growth-line" x1="0" x2="1" y1="0" y2="0">
                    <stop offset="0%" stop-color="#f9a8d4" />
                    <stop offset="50%" stop-color="#c4b5fd" />
                    <stop offset="100%" stop-color="#7dd3fc" />
                </linearGradient>
            </defs>
            <path
                class="growth-line"
                d="M0 110 C 90 105, 140 80, 220 88 S 360 70, 430 74 S 560 40, 640 52 S 790 18, 880 26 S 960 8, 1000 4"
                fill="none"
                stroke="url(#marketing-growth-line)"
                stroke-width="3"
                stroke-linecap="round"
                pathLength="1"
            />
        </svg>

        <div
            v-for="(scattered, index) in scatteredIcons"
            :key="index"
            class="backdrop-icon absolute"
            :class="scattered.position"
            :style="{ '--icon-rotate': scattered.rotate, animationDelay: scattered.delay }"
        >
            <FontAwesomeIcon :icon="scattered.icon" :class="[scattered.size, scattered.colour]" class="opacity-40" fixed-width />
        </div>
    </div>
</template>

<style scoped>
.backdrop-fade {
    mask-image: radial-gradient(ellipse 55% 90% at 50% 75%, black 25%, transparent 75%);
}

@media (min-width: 1024px) {
    .backdrop-fade {
        mask-image: radial-gradient(ellipse 40% 85% at 80% 50%, black 30%, transparent 80%);
    }
}

.marketing-dots {
    background-image: radial-gradient(rgb(19 41 75 / 0.12) 1px, transparent 1px);
    background-size: 16px 16px;
}

.backdrop-blob {
    animation: backdrop-drift 12s ease-in-out infinite;
}

.backdrop-icon {
    rotate: var(--icon-rotate);
    animation: backdrop-bob 6s ease-in-out infinite;
}

.broadcast-ring {
    position: absolute;
    left: 0;
    top: 0;
    width: 220px;
    height: 220px;
    margin: -110px 0 0 -110px;
    border-radius: 9999px;
    border: 1.5px solid rgb(196 181 253 / 0.7);
    animation: broadcast 3.9s ease-out infinite;
}

.growth-line {
    stroke-dasharray: 1;
    stroke-dashoffset: 1;
    animation: growth-draw 6s ease-in-out infinite;
}

@keyframes backdrop-drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(18px, 10px) scale(1.08); }
    66% { transform: translate(-12px, 14px) scale(0.95); }
}

@keyframes backdrop-bob {
    0%, 100% { translate: 0 0; }
    50% { translate: 0 -6px; }
}

@keyframes broadcast {
    0% { transform: scale(0.35); opacity: 0.9; }
    100% { transform: scale(1.35); opacity: 0; }
}

@keyframes growth-draw {
    0% { stroke-dashoffset: 1; }
    55%, 85% { stroke-dashoffset: 0; }
    100% { stroke-dashoffset: -1; }
}

@media (prefers-reduced-motion: reduce) {
    .backdrop-blob,
    .backdrop-icon,
    .broadcast-ring,
    .growth-line {
        animation: none;
    }

    .growth-line {
        stroke-dashoffset: 0;
    }

    .broadcast-ring {
        opacity: 0.3;
    }
}
</style>
