<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

const props = withDefaults(defineProps<{
    name: string
    expectedSeconds?: number
}>(), {
    expectedSeconds: 10,
})

const SHOW_AFTER_MS = 1500
const SCENE_MS = 3500
const scenes = ["potion", "book", "wand", "crystal"] as const

const elapsedMs = ref(0)
let startedAt = 0
let timer: ReturnType<typeof setInterval> | undefined

const isVisible = computed(() => elapsedMs.value >= SHOW_AFTER_MS)
const expectedMs = computed(() => Math.max(3, props.expectedSeconds) * 1000)
const isLongerThanUsual = computed(() => elapsedMs.value > expectedMs.value * 1.3)
const scene = computed(() => scenes[Math.floor(Math.max(0, elapsedMs.value - SHOW_AFTER_MS) / SCENE_MS) % scenes.length])
const progress = computed(() => Math.min(97, 100 * (1 - Math.exp(-1.6 * elapsedMs.value / expectedMs.value))))

const caption = computed(() => {
    if (isLongerThanUsual.value) {
        return ctrans("This one needs a little more thought. Nearly there…")
    }

    return {
        potion: ctrans(":name is brewing a potion…", { name: props.name }),
        book: ctrans(":name is looking it up in his spellbook…", { name: props.name }),
        wand: ctrans(":name is casting a spell…", { name: props.name }),
        crystal: ctrans(":name is gazing into his crystal ball…", { name: props.name }),
    }[scene.value]
})

const usually = computed(() => ctrans("Usually takes about :seconds seconds", { seconds: Math.max(3, props.expectedSeconds) }))

onMounted(() => {
    startedAt = Date.now()
    timer = setInterval(() => {
        elapsedMs.value = Date.now() - startedAt
    }, 200)
})

onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
    <div class="merlin-thinking" role="status" aria-live="polite">
        <template v-if="isVisible">
            <Transition name="merlin-scene" mode="out-in">
                <svg :key="scene" class="merlin-sketch" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <g :transform="{ potion: 'translate(32 20)', book: 'translate(32 18)', wand: 'translate(21 28)', crystal: 'translate(15 25) rotate(6)' }[scene]">
                        <g class="sketch-nod">
                            <path d="M-8-1.5C-6-9-3-14 2-16c3-1 6 .5 6 3" />
                            <path d="M8-1.5C7.5-6 6-9.5 3-12" />
                            <path d="M-12 0C-5-3 5-3 12 0" />
                            <g class="sketch-blink" fill="currentColor" stroke="none">
                                <circle cx="-4" cy="4.5" r="1.5" />
                                <circle cx="4" cy="4.5" r="1.5" />
                            </g>
                            <g fill="currentColor" fill-opacity=".22" stroke="none">
                                <circle cx="-6.8" cy="7.2" r="2.2" />
                                <circle cx="6.8" cy="7.2" r="2.2" />
                            </g>
                            <path d="M-10 3C-12.5 11-7.5 17 0 20.5 7.5 17 12.5 11 10 3" />
                            <path d="M0 9.5c-1.2 2.6-4 3.2-6 1.2M0 9.5c1.2 2.6 4 3.2 6 1.2" />
                        </g>
                    </g>
                    <g v-if="scene === 'potion'">
                        <path d="M16 45c-1 9 5 15 16 15s17-6 16-15z" fill="currentColor" fill-opacity=".14" stroke="none" />
                        <path d="M12 45h40" />
                        <path d="M16 45c-1 9 5 15 16 15s17-6 16-15" />
                        <path d="M22 59l-2 3M42 59l2 3" />
                        <path d="M47 44l9-13" class="sketch-stir" />
                        <circle cx="17" cy="38" r="2.5" class="sketch-bubble" />
                        <circle cx="23" cy="40" r="1.5" class="sketch-bubble sketch-delay-1" />
                        <circle cx="41" cy="39" r="1.8" class="sketch-bubble sketch-delay-2" />
                    </g>
                    <g v-else-if="scene === 'book'">
                        <path d="M32 45c-6-3.5-15-4-23-2.5v15c8-1.5 17-1 23 2.5" />
                        <path d="M32 45c6-3.5 15-4 23-2.5v15c-8 1.5-17 1-23 2.5" />
                        <path d="M32 45v15" />
                        <path d="M14 47.5c4-.3 8 .3 12 1.8M14 52.5c4-.3 8 .3 12 1.8" stroke-width="2" />
                        <path d="M32 45c5-4.5 13-5.5 20-4.5v14c-7-1-15 0-20 5.5" class="sketch-page" />
                        <path d="M53 10l1 3 3 1-3 1-1 3-1-3-3-1 3-1z" class="sketch-twinkle" />
                        <path d="M11 20l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" class="sketch-twinkle sketch-delay-2" />
                    </g>
                    <g v-else-if="scene === 'wand'">
                        <g class="sketch-wave">
                            <path d="M40 53l12-25" />
                            <path d="M40 53l3-6.2" stroke-width="5" />
                        </g>
                        <path d="M54 9l1.5 4.5 4.5 1.5-4.5 1.5-1.5 4.5-1.5-4.5-4.5-1.5 4.5-1.5z" class="sketch-twinkle" />
                        <path d="M42 12l1 3 3 1-3 1-1 3-1-3-3-1 3-1z" class="sketch-twinkle sketch-delay-1" />
                        <path d="M59 40l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" class="sketch-twinkle sketch-delay-2" />
                    </g>
                    <g v-else>
                        <circle cx="45" cy="37" r="13" fill="currentColor" fill-opacity=".25" stroke="none" class="sketch-glow" />
                        <circle cx="45" cy="37" r="13" />
                        <path d="M36 51h18l2 8H34z" />
                        <path d="M40 35c1-4 7-6 10-2s-1 9-5 7" class="sketch-swirl" />
                        <path d="M53 11l1 3 3 1-3 1-1 3-1-3-3-1 3-1z" class="sketch-twinkle" />
                        <path d="M34 14l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" class="sketch-twinkle sketch-delay-1" />
                    </g>
                </svg>
            </Transition>

            <div class="merlin-thinking-text">
                <p class="merlin-thinking-caption">{{ caption }}</p>
                <div class="merlin-thinking-bar" aria-hidden="true">
                    <div class="merlin-thinking-fill" :style="{ width: `${progress}%` }" />
                </div>
                <p class="merlin-thinking-usually">{{ usually }}</p>
            </div>
        </template>
        <LoadingIcon v-else />
    </div>
</template>

<style scoped>
.merlin-thinking {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 3.5rem;
    color: var(--theme-color-4, #ef7d1a);
}

.merlin-sketch {
    width: 3.5rem;
    height: 3.5rem;
    flex-shrink: 0;
}

.merlin-thinking-text {
    min-width: 0;
    flex: 1;
    max-width: 18rem;
}

.merlin-thinking-caption {
    font-size: 0.875rem;
    color: #4b5563;
}

.merlin-thinking-usually {
    margin-top: 0.25rem;
    font-size: 0.6875rem;
    color: #9ca3af;
}

.merlin-thinking-bar {
    margin-top: 0.375rem;
    height: 0.25rem;
    border-radius: 9999px;
    overflow: hidden;
    background-color: color-mix(in srgb, var(--theme-color-4, #ef7d1a) 15%, #f3f4f6);
}

.merlin-thinking-fill {
    height: 100%;
    border-radius: 9999px;
    background-color: var(--theme-color-4, #ef7d1a);
    transition: width 0.2s linear;
}

.sketch-nod {
    animation: sketch-nod 2.4s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: 50% 60%;
}

.sketch-blink {
    animation: sketch-blink 2.4s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: center;
}

.sketch-bubble {
    animation: sketch-rise 1.6s ease-in infinite;
    transform-box: fill-box;
}

.sketch-stir {
    animation: sketch-stir 1.2s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: 0% 100%;
}

.sketch-page {
    animation: sketch-flip 1.6s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: 0% 50%;
}

.sketch-wave {
    animation: sketch-wave 1.2s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: 0% 100%;
}

.sketch-twinkle {
    animation: sketch-twinkle 1.4s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: center;
}

.sketch-swirl {
    animation: sketch-spin 2.4s linear infinite;
    transform-box: fill-box;
    transform-origin: center;
}

.sketch-glow {
    animation: sketch-glow 2s ease-in-out infinite;
}

.sketch-delay-1 {
    animation-delay: 0.45s;
}

.sketch-delay-2 {
    animation-delay: 0.9s;
}

@keyframes sketch-nod {
    0%, 100% { transform: rotate(-3deg); }
    50% { transform: rotate(3deg); }
}

@keyframes sketch-blink {
    0%, 42%, 50%, 100% { transform: scaleY(1); }
    46% { transform: scaleY(0.1); }
}

@keyframes sketch-rise {
    0% { transform: translateY(2px); opacity: 0.9; }
    70% { opacity: 0.8; }
    100% { transform: translateY(-13px); opacity: 0; }
}

@keyframes sketch-stir {
    0%, 100% { transform: rotate(-12deg); }
    50% { transform: rotate(12deg); }
}

@keyframes sketch-flip {
    0%, 20% { transform: scaleX(1); }
    60%, 100% { transform: scaleX(-1); }
}

@keyframes sketch-wave {
    0%, 100% { transform: rotate(-10deg); }
    50% { transform: rotate(10deg); }
}

@keyframes sketch-twinkle {
    0%, 100% { transform: scale(1.1); opacity: 1; }
    50% { transform: scale(0.5); opacity: 0.3; }
}

@keyframes sketch-spin {
    to { transform: rotate(360deg); }
}

@keyframes sketch-glow {
    0%, 100% { opacity: 0.4; }
    50% { opacity: 1; }
}

.merlin-scene-enter-active,
.merlin-scene-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.merlin-scene-enter-from,
.merlin-scene-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

@media (prefers-reduced-motion: reduce) {
    .merlin-sketch *,
    .merlin-scene-enter-active,
    .merlin-scene-leave-active {
        animation: none !important;
        transition: none !important;
    }
}
</style>
