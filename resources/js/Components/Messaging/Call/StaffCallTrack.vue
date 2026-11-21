<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from "vue"
import type { Track } from "livekit-client"

const props = defineProps<{
    track: Track
    mirror?: boolean
    fit?: "cover" | "contain"
}>()

const element = ref<HTMLVideoElement | null>(null)
const isPlaying = ref(false)

watch([() => props.track, element], ([track, el], [previousTrack]) => {
    if (previousTrack && previousTrack !== track && el) {
        previousTrack.detach(el)
        isPlaying.value = false
    }
    if (track && el) track.attach(el)
}, { immediate: true })

onBeforeUnmount(() => {
    if (element.value) props.track.detach(element.value)
})
</script>

<template>
    <video
        ref="element"
        autoplay
        playsinline
        muted
        class="h-full w-full transition-opacity duration-150"
        :class="[fit === 'contain' ? 'object-contain bg-black' : 'object-cover', mirror && '-scale-x-100', isPlaying ? 'opacity-100' : 'opacity-0']"
        @playing="isPlaying = true" />
</template>
