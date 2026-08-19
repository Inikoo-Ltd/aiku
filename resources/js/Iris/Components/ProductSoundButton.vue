<script setup lang="ts">
import { ref, computed, inject, onBeforeUnmount } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"

// ponytail: spectrum logic mirrors Components/Pure/AudioWaveform.vue; extract a composable if a third user appears
const props = defineProps<{ src: string, topSeller?: number | null }>()

const layout = inject('layout', retinaLayoutStructure)

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

function drawSpectrum() {
    const canvas = canvasRef.value
    if (!canvas || !analyser || !frequencyData) return

    analyser.getByteFrequencyData(frequencyData)

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

    const gap = 3
    const barWidth = Math.max((width - gap * (BAR_COUNT - 1)) / BAR_COUNT, 1)
    const usableBins = Math.floor(frequencyData.length * 0.66)
    const binsPerBar = Math.max(1, Math.floor(usableBins / BAR_COUNT))

    for (let i = 0; i < BAR_COUNT; i++) {
        let sum = 0
        for (let j = i * binsPerBar; j < (i + 1) * binsPerBar; j++) {
            sum += frequencyData[j]
        }
        const value = sum / binsPerBar / 255
        const barHeight = Math.max(value * height * 0.85, 4)
        const x = i * (barWidth + gap)
        const y = (height - barHeight) / 2
        context.fillStyle = `hsla(${(i / BAR_COUNT) * 300}, 85%, 50%, 0.9)`
        context.beginPath()
        context.roundRect(x, y, barWidth, barHeight, barWidth / 2)
        context.fill()
    }
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
        tick()
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
        <div v-show="isPlaying" class="absolute inset-0 z-10 flex items-end bg-white/40 pointer-events-none">
            <canvas ref="canvasRef" class="w-full h-2/3" />
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
            <svg v-else viewBox="0 0 24 24" class="h-4 w-4 text-gray-700" fill="currentColor">
                <rect x="5" y="5" width="14" height="14" rx="2" />
            </svg>
        </button>
    </div>
</template>
