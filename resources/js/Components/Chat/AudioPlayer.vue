<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlay, faPause, faMicrophone, faDownload } from "@far"

const props = withDefaults(
    defineProps<{
        src: string
        isVoice?: boolean
        label?: string | null
        downloadUrl?: string | null
        barCount?: number
    }>(),
    { isVoice: false, label: null, downloadUrl: null, barCount: 32 }
)

const PLAY_EVENT = "chat-audio-play"
const RATES = [1, 1.5, 2]

const uid = Symbol("audio-player")

const audio = ref<HTMLAudioElement>()
const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const rate = ref(1)
const durationProbed = ref(false)

/**
 * Bars are derived from the source URL so a clip always draws the same shape
 * without decoding the audio, which would mean fetching every attachment up front.
 */
const bars = computed(() => {
    let seed = 0
    for (let i = 0; i < props.src.length; i++) {
        seed = (seed * 31 + props.src.charCodeAt(i)) >>> 0
    }

    return Array.from({ length: props.barCount }, () => {
        seed = (seed * 1664525 + 1013904223) >>> 0
        return 25 + (seed % 75)
    })
})

const progress = computed(() => (duration.value ? currentTime.value / duration.value : 0))

const playedBars = computed(() => Math.round(progress.value * props.barCount))

const formatTime = (seconds: number) => {
    if (!Number.isFinite(seconds)) return "0:00"
    const total = Math.floor(seconds)
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, "0")}`
}

const displayTime = computed(() =>
    isPlaying.value || currentTime.value > 0 ? formatTime(currentTime.value) : formatTime(duration.value)
)

/**
 * Chrome reports an Infinite duration for streamed Ogg/Opus (what WhatsApp voice
 * notes are), seeking past the end forces it to resolve the real length.
 */
const readDuration = () => {
    const element = audio.value
    if (!element) return

    if (Number.isFinite(element.duration)) {
        duration.value = element.duration
        return
    }

    if (durationProbed.value) return

    durationProbed.value = true
    element.currentTime = 1e101
    element.ontimeupdate = () => {
        element.ontimeupdate = null
        duration.value = Number.isFinite(element.duration) ? element.duration : 0
        element.currentTime = 0
    }
}

const onTimeUpdate = () => {
    currentTime.value = audio.value?.currentTime ?? 0
}

const onEnded = () => {
    isPlaying.value = false
    currentTime.value = 0
    if (audio.value) audio.value.currentTime = 0
}

const toggle = async () => {
    const element = audio.value
    if (!element) return

    if (isPlaying.value) {
        element.pause()
        return
    }

    // Starting a clip stops whichever one was already talking.
    window.dispatchEvent(new CustomEvent(PLAY_EVENT, { detail: uid }))

    try {
        element.playbackRate = rate.value
        await element.play()
    } catch (e) {
        isPlaying.value = false
    }
}

const seek = (event: MouseEvent) => {
    const element = audio.value
    if (!element || !duration.value) return

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
    const ratio = Math.min(Math.max((event.clientX - rect.left) / rect.width, 0), 1)

    element.currentTime = ratio * duration.value
    currentTime.value = element.currentTime
}

const cycleRate = () => {
    rate.value = RATES[(RATES.indexOf(rate.value) + 1) % RATES.length]
    if (audio.value) audio.value.playbackRate = rate.value
}

const download = () => {
    if (props.downloadUrl) {
        window.open(props.downloadUrl, "_blank")
    }
}

const onOtherPlay = (event: Event) => {
    if ((event as CustomEvent).detail !== uid) {
        audio.value?.pause()
    }
}

onMounted(() => window.addEventListener(PLAY_EVENT, onOtherPlay))

onBeforeUnmount(() => {
    window.removeEventListener(PLAY_EVENT, onOtherPlay)
    audio.value?.pause()
})
</script>

<template>
    <div class="mt-1 flex items-center gap-2.5 min-w-[220px] max-w-xs">
        <button type="button" @click.stop="toggle"
            :aria-label="isPlaying ? 'Pause' : 'Play'"
            class="relative w-9 h-9 rounded-full shrink-0 flex items-center justify-center transition hover:opacity-80">
            <span class="absolute inset-0 rounded-full bg-current opacity-10"></span>
            <FontAwesomeIcon :icon="isPlaying ? faPause : faPlay"
                class="relative text-[11px]" :class="isPlaying ? '' : 'ml-0.5'" />
        </button>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-[2px] h-6 cursor-pointer" @click.stop="seek">
                <span v-for="(height, index) in bars" :key="index"
                    class="flex-1 rounded-full bg-current transition-opacity"
                    :style="{ height: `${height}%`, opacity: index < playedBars ? 0.95 : 0.3 }" />
            </div>

            <div class="flex items-center justify-between gap-2 mt-0.5 text-[10px]">
                <span class="opacity-60 tabular-nums">{{ displayTime }}</span>

                <span class="flex items-center gap-1.5 min-w-0">
                    <span v-if="label && !isVoice" class="opacity-60 truncate">{{ label }}</span>
                    <FontAwesomeIcon v-if="isVoice" :icon="faMicrophone" class="opacity-60 text-[9px]" />
                    <button v-if="downloadUrl" type="button" @click.stop="download"
                        class="opacity-60 hover:opacity-100 shrink-0" :aria-label="'Download'">
                        <FontAwesomeIcon :icon="faDownload" class="text-[9px]" />
                    </button>
                    <button v-if="isPlaying || currentTime > 0" type="button" @click.stop="cycleRate"
                        class="relative rounded-full px-1.5 py-[1px] shrink-0 tabular-nums hover:opacity-80">
                        <span class="absolute inset-0 rounded-full bg-current opacity-10"></span>
                        <span class="relative">{{ rate }}x</span>
                    </button>
                </span>
            </div>
        </div>

        <audio ref="audio" :src="src" preload="metadata" class="hidden"
            @loadedmetadata="readDuration"
            @durationchange="readDuration"
            @timeupdate="onTimeUpdate"
            @play="isPlaying = true"
            @pause="isPlaying = false"
            @ended="onEnded" />
    </div>
</template>
