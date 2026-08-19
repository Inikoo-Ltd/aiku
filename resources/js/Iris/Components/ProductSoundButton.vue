<script setup lang="ts">
import { ref, computed, inject, onBeforeUnmount } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"

// ponytail: spectrum logic mirrors Components/Pure/AudioWaveform.vue; extract a composable if a third user appears
const props = defineProps<{ src: string, topSeller?: number | null }>()

const layout = inject('layout', retinaLayoutStructure)

// Per-website look, set in the website settings. Unknown or missing values fall back to rainbow.
const VARIANTS = ['rainbow', 'mono', 'wave', 'equalizer', 'minimal']
const variant = computed(() => {
    const style = layout?.iris?.sound_player_style
    return VARIANTS.includes(style) ? style : 'rainbow'
})

const MONO_COLOR = '#334155'

// Logged out cards show the "Login or Register" call to action under the image, which the
// bottom-left button overlaps on narrow screens, so it moves to the top-left corner there.
// The bestseller badge already sits at top-2 left-2, so drop below it when one is shown.
const cornerClass = computed(() => {
    if (layout?.iris?.is_logged_in) {
        return 'left-2 bottom-2'
    }

    return props.topSeller ? 'left-2 top-11' : 'left-2 top-2'
})

const canvasRef = ref<HTMLCanvasElement | null>(null)
const isPlaying = ref(false)
let audio: HTMLAudioElement | null = null
let analyser: AnalyserNode | null = null
let playbackContext: AudioContext | null = null
let frequencyData: Uint8Array | null = null
let rafId = 0

const BAR_COUNT = 32

function barValues(): number[] | null {
    if (!analyser || !frequencyData) return null
    analyser.getByteFrequencyData(frequencyData)
    const usableBins = Math.floor(frequencyData.length * 0.66)
    const binsPerBar = Math.max(1, Math.floor(usableBins / BAR_COUNT))
    const values: number[] = []
    for (let i = 0; i < BAR_COUNT; i++) {
        let sum = 0
        for (let j = i * binsPerBar; j < (i + 1) * binsPerBar; j++) {
            sum += frequencyData[j]
        }
        values.push(sum / binsPerBar / 255)
    }
    return values
}

function drawSpectrum() {
    const canvas = canvasRef.value
    if (!canvas) return

    const dpr = window.devicePixelRatio || 1
    const width = canvas.clientWidth
    const height = canvas.clientHeight
    if (!width || !height) return
    canvas.width = width * dpr
    canvas.height = height * dpr

    const context = canvas.getContext("2d")
    if (!context) return
    context.scale(dpr, dpr)
    context.clearRect(0, 0, width, height)

    const values = barValues()
    if (!values) return

    if (variant.value === 'wave') {
        context.beginPath()
        context.moveTo(0, height / 2)
        const step = width / (BAR_COUNT - 1)
        values.forEach((v, i) => context.lineTo(i * step, height / 2 - v * height * 0.45))
        for (let i = BAR_COUNT - 1; i >= 0; i--) {
            context.lineTo(i * step, height / 2 + values[i] * height * 0.45)
        }
        context.closePath()
        context.fillStyle = 'rgba(51, 65, 85, 0.55)'
        context.fill()
        return
    }

    const gap = 3
    const barWidth = Math.max((width - gap * (BAR_COUNT - 1)) / BAR_COUNT, 1)

    values.forEach((value, i) => {
        const barHeight = Math.max(value * height * 0.85, 4)
        const x = i * (barWidth + gap)
        const y = variant.value === 'equalizer' ? height - barHeight : (height - barHeight) / 2
        context.fillStyle = variant.value === 'rainbow'
            ? `hsla(${(i / BAR_COUNT) * 300}, 85%, 50%, 0.9)`
            : MONO_COLOR + 'e6'
        context.beginPath()
        context.roundRect(x, y, barWidth, barHeight, barWidth / 2)
        context.fill()
    })
}

function tick() {
    if (audio && isPlaying.value) {
        drawSpectrum()
        rafId = requestAnimationFrame(tick)
    }
}

function togglePlay() {
    if (!audio) {
        audio = new Audio(props.src)
        audio.crossOrigin = "anonymous"
        audio.onended = () => {
            isPlaying.value = false
            cancelAnimationFrame(rafId)
        }
        playbackContext = new AudioContext()
        const source = playbackContext.createMediaElementSource(audio)
        analyser = playbackContext.createAnalyser()
        analyser.fftSize = 256
        analyser.smoothingTimeConstant = 0.75
        source.connect(analyser)
        analyser.connect(playbackContext.destination)
        frequencyData = new Uint8Array(analyser.frequencyBinCount)
    }

    if (isPlaying.value) {
        audio.pause()
        isPlaying.value = false
        cancelAnimationFrame(rafId)
    } else {
        playbackContext?.resume()
        audio.play()
        isPlaying.value = true
        if (variant.value !== 'minimal') {
            tick()
        }
    }
}

onBeforeUnmount(() => {
    audio?.pause()
    playbackContext?.close()
    cancelAnimationFrame(rafId)
})
</script>

<template>
    <div>
        <!-- Live spectrum overlay on the product image -->
        <div v-if="variant !== 'minimal'" v-show="isPlaying"
            class="absolute inset-0 z-10 pointer-events-none flex items-end"
            :class="variant === 'equalizer' ? '' : 'bg-white/40'">
            <canvas ref="canvasRef" class="w-full" :class="variant === 'equalizer' ? 'h-1/4' : 'h-2/3'" />
        </div>

        <!-- Circular wave play/stop button -->
        <button
            @click.prevent.stop="togglePlay()"
            class="absolute z-20 h-10 w-10 rounded-full bg-white/90 shadow-lg flex items-center justify-center transition hover:scale-105"
            :class="cornerClass"
            :title="isPlaying ? 'Stop sound sample' : 'Play sound sample'"
            aria-label="Sound sample"
        >
            <svg v-if="!isPlaying" viewBox="0 0 24 24" class="h-5 w-5 text-gray-700" fill="none" stroke="currentColor"
                stroke-width="2.5" stroke-linecap="round">
                <line x1="4" y1="9" x2="4" y2="15" />
                <line x1="8" y1="6" x2="8" y2="18" />
                <line x1="12" y1="3" x2="12" y2="21" />
                <line x1="16" y1="7" x2="16" y2="17" />
                <line x1="20" y1="10" x2="20" y2="14" />
            </svg>
            <svg v-else viewBox="0 0 24 24" class="h-4 w-4 text-gray-700" fill="currentColor"
                :class="{ 'animate-pulse': variant === 'minimal' }">
                <rect x="5" y="5" width="14" height="14" rx="2" />
            </svg>
        </button>
    </div>
</template>
