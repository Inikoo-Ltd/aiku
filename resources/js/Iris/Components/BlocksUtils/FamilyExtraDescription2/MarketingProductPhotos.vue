<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeart, faHashtag, faBell } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@common/Components/Image.vue"
import type { Image as ImageProxy } from "@/types/Image"

library.add(faHeart, faHashtag, faBell)

const props = defineProps<{
    images?: ImageProxy[] | null
    alt?: string
}>()

type PhotoSlot = {
    x: number
    y: number
    rotate: number
    scale: number
    zIndex: number
    floatDuration: number
    floatDelay: number
}

const slots: PhotoSlot[] = [
    { x: -124, y: 18, rotate: -13, scale: 0.8, zIndex: 1, floatDuration: 4.2, floatDelay: 0.6 },
    { x: -66, y: 6, rotate: -6, scale: 0.92, zIndex: 3, floatDuration: 3.6, floatDelay: 1.4 },
    { x: 0, y: -2, rotate: 2, scale: 1.08, zIndex: 5, floatDuration: 3.2, floatDelay: 0 },
    { x: 67, y: 8, rotate: 7, scale: 0.9, zIndex: 3, floatDuration: 3.9, floatDelay: 0.9 },
    { x: 126, y: 20, rotate: 14, scale: 0.82, zIndex: 1, floatDuration: 4.5, floatDelay: 2 },
]

const slotsByPhotoCount: Record<number, number[]> = {
    1: [2],
    2: [1, 3],
    3: [2, 1, 3],
    4: [1, 3, 0, 4],
    5: [2, 1, 3, 0, 4],
}

const photos = computed(() => (props.images ?? []).slice(0, 5))

const placedPhotos = computed(() =>
    photos.value.map((photo, index) => ({
        photo,
        index,
        slot: slots[slotsByPhotoCount[photos.value.length][index]],
    }))
)

const hasEntered = ref(false)
const hoveredIndex = ref<number | null>(null)
const container = ref<HTMLElement | null>(null)
let visibilityObserver: IntersectionObserver | null = null

onMounted(() => {
    if (!container.value || typeof IntersectionObserver === "undefined") {
        hasEntered.value = true
        return
    }

    visibilityObserver = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            hasEntered.value = true
            visibilityObserver?.disconnect()
        }
    }, { threshold: 0.4 })

    visibilityObserver.observe(container.value)
})

onBeforeUnmount(() => visibilityObserver?.disconnect())

const photoStyle = (slot: PhotoSlot, index: number) => {
    const isHovered = hoveredIndex.value === index
    const isOtherHovered = hoveredIndex.value !== null && !isHovered
    const spread = isOtherHovered ? 1.08 : 1

    const transform = hasEntered.value
        ? `translateX(calc(-50% + ${slot.x * spread}px)) translateY(${isHovered ? slot.y - 8 : slot.y}px) rotate(${isHovered ? 0 : slot.rotate}deg) scale(${isHovered ? slot.scale * 1.15 : slot.scale})`
        : "translateX(-50%) translateY(10px) rotate(0deg) scale(0.6)"

    return {
        transform,
        opacity: hasEntered.value ? 1 : 0,
        zIndex: isHovered ? 20 : slot.zIndex,
        transitionDelay: hasEntered.value && hoveredIndex.value === null ? `${index * 90}ms` : "0ms",
    }
}

const floatStyle = (slot: PhotoSlot) => ({
    animationDuration: `${slot.floatDuration}s`,
    animationDelay: `${slot.floatDelay}s`,
})
</script>

<template>
    <div ref="container" class="relative h-[128px] w-[340px]" @mouseleave="hoveredIndex = null">
        <div class="marketing-glow absolute inset-x-6 inset-y-1 rounded-full bg-gradient-to-r from-pink-200 via-purple-200 to-sky-200 blur-2xl" />

        <template v-if="photos.length">
            <div
                v-for="{ photo, index, slot } in placedPhotos"
                :key="index"
                class="marketing-photo absolute left-1/2 top-[8px] h-[102px] w-[78px] cursor-pointer"
                :style="photoStyle(slot, index)"
                @mouseenter="hoveredIndex = index"
            >
                <div
                    class="marketing-float relative h-full w-full overflow-hidden rounded-xl border-2 border-white bg-white shadow-lg transition-shadow duration-300"
                    :class="hoveredIndex === index ? 'shadow-2xl' : ''"
                    :style="floatStyle(slot)"
                >
                    <Image :src="photo" :alt="alt" image-cover class="h-full w-full object-cover" />
                    <div v-if="index === 0" class="marketing-shine pointer-events-none absolute inset-0" />
                </div>
            </div>
        </template>

        <div
            v-else
            class="marketing-float absolute left-1/2 top-[10px] z-10 h-[104px] w-[56px] -translate-x-1/2 rotate-12 rounded-[14px] bg-black p-1 shadow-xl"
        >
            <div class="h-full w-full rounded-[10px] bg-gradient-to-br from-pink-300 via-purple-300 to-blue-300" />
        </div>

        <div
            class="marketing-badge absolute left-0 top-0 z-30 flex h-7 w-7 items-center justify-center rounded-full bg-white shadow-md"
            :class="{ 'is-visible': hasEntered }"
            style="--badge-delay: 0.5s; --float-delay: 0s"
        >
            <FontAwesomeIcon icon="fal fa-heart" class="marketing-heartbeat text-sm text-pink-500" fixed-width />
        </div>
        <div
            class="marketing-badge absolute right-0 top-1 z-30 flex h-7 w-7 items-center justify-center rounded-full bg-white shadow-md"
            :class="{ 'is-visible': hasEntered }"
            style="--badge-delay: 0.65s; --float-delay: 1.1s"
        >
            <FontAwesomeIcon icon="fal fa-hashtag" class="text-sm text-purple-500" fixed-width />
        </div>
        <div
            class="marketing-badge absolute bottom-0 right-8 z-30 flex h-7 w-7 items-center justify-center rounded-full bg-white shadow-md"
            :class="{ 'is-visible': hasEntered }"
            style="--badge-delay: 0.8s; --float-delay: 2s"
        >
            <FontAwesomeIcon icon="fal fa-bell" class="marketing-ring text-sm text-sky-500" fixed-width />
        </div>
    </div>
</template>

<style scoped>
.marketing-photo {
    transition:
        transform 0.7s cubic-bezier(0.34, 1.56, 0.64, 1),
        opacity 0.4s ease-out;
}

.marketing-float {
    animation: marketing-float 3.6s ease-in-out infinite;
}

.marketing-glow {
    animation: marketing-glow 6s ease-in-out infinite;
}

.marketing-shine::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(110deg, transparent 35%, rgb(255 255 255 / 0.55) 50%, transparent 65%);
    transform: translateX(-120%);
    animation: marketing-shine 5s ease-in-out 1.5s infinite;
}

.marketing-badge {
    opacity: 0;
    transform: scale(0.4);
    transition:
        opacity 0.4s ease-out var(--badge-delay),
        transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) var(--badge-delay);
}

.marketing-badge.is-visible {
    opacity: 1;
    transform: scale(1);
    animation: marketing-float 4s ease-in-out var(--float-delay) infinite;
}

.marketing-heartbeat {
    animation: marketing-heartbeat 2.4s ease-in-out infinite;
}

.marketing-ring {
    transform-origin: 50% 10%;
    animation: marketing-ring 4s ease-in-out 1s infinite;
}

@keyframes marketing-float {
    0%, 100% { translate: 0 0; }
    50% { translate: 0 -4px; }
}

@keyframes marketing-glow {
    0%, 100% { opacity: 0.55; transform: scale(1); }
    50% { opacity: 0.85; transform: scale(1.06); }
}

@keyframes marketing-shine {
    0% { transform: translateX(-120%); }
    30%, 100% { transform: translateX(120%); }
}

@keyframes marketing-heartbeat {
    0%, 40%, 100% { transform: scale(1); }
    15% { transform: scale(1.25); }
    25% { transform: scale(0.95); }
}

@keyframes marketing-ring {
    0%, 20%, 100% { transform: rotate(0deg); }
    4% { transform: rotate(16deg); }
    8% { transform: rotate(-14deg); }
    12% { transform: rotate(10deg); }
    16% { transform: rotate(-6deg); }
}

@media (prefers-reduced-motion: reduce) {
    .marketing-photo,
    .marketing-badge {
        transition: none;
    }

    .marketing-float,
    .marketing-glow,
    .marketing-shine::after,
    .marketing-badge.is-visible,
    .marketing-heartbeat,
    .marketing-ring {
        animation: none;
    }
}
</style>
