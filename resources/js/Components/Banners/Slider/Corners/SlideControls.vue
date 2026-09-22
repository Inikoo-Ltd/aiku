<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Jul 2023 21:46:49 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faPlay, faPause, faChevronRight, faChevronLeft } from '@fas'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from 'vue'

const props = defineProps<{
    dataBanner: {
        navigation: {
            colorNav?: string
        }
    }
    swiperRef: Element
}>()

library.add(faPlay, faPause, faChevronRight, faChevronLeft)

const swiperAutoplayPause = ref(false)
const toggleAutoplay = (swiper: any) => {
    if (swiperAutoplayPause.value) {
        swiper.autoplay.start() // Resume autoplay
    } else {
        swiper.autoplay.stop() // Pause autoplay
    }
    swiperAutoplayPause.value = !swiperAutoplayPause.value // Toggle the autoplay state
}

</script>

<template>
    <div class="opacity-80  flex justify-center items-center gap-x-3 my-3 text-gray-800/40">
        <!-- Button: Left (if on pause) -->
        <div v-if="swiperAutoplayPause" @click="() => props.swiperRef.$el.swiper.slidePrev()"
            class="relative flex items-center justify-center cursor-pointer w-8 overflow-hidden isolate h-fit aspect-square border border-gray-400 rounded-full hover:bg-black/10 hover:text-white active:bg-gray-300/80"
            title="Go to previous banner" id="previous"
            :style="{'color': dataBanner.navigation.colorNav}"
        >
            <FontAwesomeIcon icon='fas fa-chevron-left' class='z-10 text-xl mr-0.5' fixed-width aria-hidden='true' />
            <div :style="[`background-color: ${dataBanner.navigation.colorNav}`]" class="-z-10 absolute opacity-30 inset-0"></div>
        </div>

        <!-- Button: Play/pause -->
        <div @click="toggleAutoplay(props.swiperRef.$el.swiper)"
            class="relative flex items-center justify-center cursor-pointer w-9 overflow-hidden isolate aspect-square border border-gray-400 rounded-full hover:bg-black/10 active:bg-gray-300/80"
            :style="{'color': dataBanner.navigation.colorNav}"
            title="Pause/resume autoplay" id="autoplay">
            <FontAwesomeIcon v-if="swiperAutoplayPause" icon="fas fa-play" class='text-xl ml-1' fixed-width aria-hidden='true' />
            <FontAwesomeIcon v-if="!swiperAutoplayPause" icon="fas fa-pause" class='text-xl' fixed-width aria-hidden='true' />
            <div :style="[`background-color: ${dataBanner.navigation.colorNav}`]" class="-z-10 absolute opacity-30 inset-0"></div>
        </div>

        <!-- Button: Right (if on pause) -->
        <div v-if="swiperAutoplayPause" @click="() => props.swiperRef.$el.swiper.slideNext()"
            class="relative flex items-center justify-center cursor-pointer w-8 overflow-hidden isolate h-fit aspect-square border border-gray-400 rounded-full hover:bg-black/10 active:bg-gray-300/80"
            :style="{'color': dataBanner.navigation.colorNav}"
            title="Go to next banner" id="next">
            <FontAwesomeIcon icon='fas fa-chevron-right' class='text-xl ml-0.5' fixed-width aria-hidden='true' />
            <div :style="[`background-color: ${dataBanner.navigation.colorNav}`]" class="-z-10 absolute opacity-30 inset-0"></div>
        </div>
    </div>
</template>
