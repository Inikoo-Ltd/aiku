<script setup lang='ts'>
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import type { Timeline } from '@/types/Timeline'
import { computed } from 'vue'
import { useFormatTime } from '@/Composables/useFormatTime'

import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCalendarAlt, faSparkles, faSpellCheck, faSeedling, } from '@fal'
library.add(faCalendarAlt, faSparkles, faSpellCheck, faSeedling)

const props = withDefaults(defineProps<{
    options: Timeline[]
    state?: string
    width?: string | Number
    slidesPerView?: number
    color?: string
}>(), {
    color: '#4f46e5'
})

const compIndexCurrent = computed(() => {
    return props.options.findLastIndex(item => item.current === true)
})

</script>

<template>
    <div class="w-full py-5 sm:py-2 flex flex-col isolate">
        <Swiper ref="_swiperRef"
            :slideToClickedSlide="false"
            :slidesPerView="slidesPerView"
            :centerInsufficientSlides="true"
            :pagination="{ clickable: true, }"
            class="w-full h-fit isolate"
        >
            <template v-for="(step, stepIndex) in props.options" :key="stepIndex">
                <SwiperSlide>
                    <!-- Section: Title -->
                    <div class="w-fit mx-auto text-xxs md:text-xs text-center"
                        :class="step.timestamp || state == step.key ? 'text-[#888] ' : 'text-gray-300'">
                        {{ step.label }}
                    </div>

                    <div class="relative flex items-center mt-2.5 mb-0.5">
                        <!-- Step: Tail -->
                        <div v-if="stepIndex != 0"
                            class="z-10 px-1 w-full absolute flex align-center items-center align-middle content-center -translate-x-1/2 top-1/2 -translate-y-1/2">
                            <div class="w-full rounded items-center align-middle align-center flex-1 px-3">
                                <div class="w-full py-[2px] rounded"
                                    :style="{
                                        backgroundColor: step.current ? color : `color-mix(in srgb, ${color}, 75% white)`
                                    }"
                                />
                            </div>
                        </div>

                        <!-- Step: Head & icon -->
                        <div v-tooltip="step.label"
                            class="z-20 aspect-square mx-auto rounded-full flex justify-center items-center"
                            :class="[
                                step.icon ? 'h-8' : 'h-3'
                            ]"
                            :style="{
                                backgroundColor: stepIndex <= compIndexCurrent ? color : '#fff',
                                color: stepIndex <= compIndexCurrent ? `color-mix(in srgb, ${color}, 80% white)` : color,
                                border: stepIndex <= compIndexCurrent ? undefined : `1px solid #d1d5db`
                            }"
                        >
                            <FontAwesomeIcon v-if="step.icon" :icon='step.icon' class='text-xs' fixed-width aria-hidden='true' />
                        </div>
                    </div>

                    <!-- Step: Description -->
                    <div v-tooltip="useFormatTime(step.timestamp, { formatTime: 'hms' })"
                        class="text-xxs md:text-xs text-[#555] text-center select-none">
                        {{ useFormatTime(step.timestamp) }}
                    </div>
                </SwiperSlide>
            </template>
        </Swiper>
    </div>
</template>