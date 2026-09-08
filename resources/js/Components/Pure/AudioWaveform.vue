<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlay, faPause } from "@fas"

const props = defineProps<{ src: string }>()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const isPlaying = ref(false)
const bars = ref<number[]>([])
let audio: HTMLAudioElement | null = null
let analyser: AnalyserNode | null = null
let playbackContext: AudioContext | null = null
let frequencyData: Uint8Array | null = null
let rafId = 0

const BAR_COUNT = 48

async function loadWaveform() {
    const audioContext = new AudioContext()
    try {
        const arrayBuffer = await (await fetch(props.src)).arrayBuffer()
        const decoded = await audioContext.decodeAudioData(arrayBuffer)
        const channel = decoded.getChannelData(0)
        const blockSize = Math.floor(channel.length / BAR_COUNT)
        const peaks: number[] = []
        for (let i = 0; i < BAR_COUNT; i++) {
            let peak = 0
            // ponytail: sample every 32nd frame, plenty of resolution for a thumbnail waveform
            for (let j = i * blockSize; j < (i + 1) * blockSize; j += 32) {
                const value = Math.abs(channel[j])
                if (value > peak) peak = value
            }
            peaks.push(peak)
        }
        const maxPeak = Math.max(...peaks, 0.01)
        bars.value = peaks.map((peak) => Math.max(peak / maxPeak, 0.08))
    } catch {
        bars.value = Array.from({ length: BAR_COUNT }, (_, i) => 0.3 + 0.5 * Math.abs(Math.sin(i * 0.7)))
    } finally {
        audioContext.close()
    }
    draw(0)
}

function draw(progress: number) {
    const canvas = canvasRef.value
    if (!canvas || !bars.value.length) return

    const dpr = window.devicePixelRatio || 1
    const width = canvas.clientWidth
    const height = canvas.clientHeight
    canvas.width = width * dpr
    canvas.height = height * dpr

    const context = canvas.getContext("2d")
    if (!context) return
    context.scale(dpr, dpr)
    context.clearRect(0, 0, width, height)

    const gap = 2
    const barWidth = (width - gap * (BAR_COUNT - 1)) / BAR_COUNT

    bars.value.forEach((value, i) => {
        const barHeight = Math.max(value * height * 0.9, 3)
        const x = i * (barWidth + gap)
        const y = (height - barHeight) / 2
        const isPlayed = (i + 0.5) / BAR_COUNT <= progress
        const opacity = progress === 0 || isPlayed ? 1 : 0.25
        context.fillStyle = `hsla(${(i / BAR_COUNT) * 300}, 85%, 48%, ${opacity})`
        context.beginPath()
        context.roundRect(x, y, barWidth, barHeight, barWidth / 2)
        context.fill()
    })
}

function drawSpectrum() {
    const canvas = canvasRef.value
    if (!canvas || !analyser || !frequencyData) return

    analyser.getByteFrequencyData(frequencyData)

    const dpr = window.devicePixelRatio || 1
    const width = canvas.clientWidth
    const height = canvas.clientHeight
    canvas.width = width * dpr
    canvas.height = height * dpr

    const context = canvas.getContext("2d")
    if (!context) return
    context.scale(dpr, dpr)
    context.clearRect(0, 0, width, height)

    const gap = 2
    const barWidth = (width - gap * (BAR_COUNT - 1)) / BAR_COUNT
    // ponytail: log-ish bucketing by skipping the top third of FFT bins, where music has little energy
    const usableBins = Math.floor(frequencyData.length * 0.66)
    const binsPerBar = Math.max(1, Math.floor(usableBins / BAR_COUNT))

    for (let i = 0; i < BAR_COUNT; i++) {
        let sum = 0
        for (let j = i * binsPerBar; j < (i + 1) * binsPerBar; j++) {
            sum += frequencyData[j]
        }
        const value = sum / binsPerBar / 255
        const barHeight = Math.max(value * height * 0.95, 3)
        const x = i * (barWidth + gap)
        const y = (height - barHeight) / 2
        context.fillStyle = `hsl(${(i / BAR_COUNT) * 300}, 85%, 48%)`
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
            draw(0)
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
    playbackContext?.resume()

    if (isPlaying.value) {
        audio.pause()
        isPlaying.value = false
        cancelAnimationFrame(rafId)
        draw(audio.duration ? audio.currentTime / audio.duration : 0)
    } else {
        audio.play()
        isPlaying.value = true
        tick()
    }
}

function stopAndReset() {
    if (audio) {
        audio.pause()
        audio = null
    }
    playbackContext?.close()
    playbackContext = null
    analyser = null
    frequencyData = null
    isPlaying.value = false
    cancelAnimationFrame(rafId)
}

watch(() => props.src, () => {
    stopAndReset()
    loadWaveform()
})

onMounted(loadWaveform)
onBeforeUnmount(stopAndReset)
</script>

<template>
    <div class="relative h-full w-full group/audio" @click.stop="togglePlay()">
        <canvas ref="canvasRef" class="h-full w-full" />
        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/audio:opacity-100 transition cursor-pointer">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-800/70 text-white">
                <FontAwesomeIcon :icon="isPlaying ? faPause : faPlay" class="text-sm" :class="{ 'pl-0.5': !isPlaying }" />
            </div>
        </div>
    </div>
</template>
