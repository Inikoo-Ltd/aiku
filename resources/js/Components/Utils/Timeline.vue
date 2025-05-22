<script setup lang='ts'>
import { ref, watch, onBeforeMount, computed } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import { format } from 'date-fns'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCalendarAlt, faSparkles, faSpellCheck, faSeedling, } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { localesCode, OptionsTime, useFormatTime as useFormatTimeComposables } from '@/Composables/useFormatTime'
/* import { useFormatTime } from '@/Composables/useFormatTime'; */
library.add(faCalendarAlt, faSparkles, faSpellCheck, faSeedling)
import type { Timeline } from '@/types/Timeline'

const props = defineProps<{
    options: Timeline[] | {[key: string]: Timeline}
    state?: string
    width?: string | Number
    slidesPerView?: number
}>()

// console.log('ssss',props)
const emits = defineEmits<{
    (e: 'updateButton', value: {step: Timeline, options: Timeline[]}): void
}>()

const _swiperRef = ref()
// const finalOptions = ref<Timeline[]>([])

const computedXxx = computed(() => {
    const finalData = []
    Object.entries(props.options).forEach(([key, value], index) => {
        finalData.push({ ...value, index });
    });

    return finalData
})

// const stepsWithIndex = (() => {
//     const finalData = []
//     Object.entries(props.options).forEach(([key, value], index) => {
//         finalData.push({ ...value, index });
//     });

//     // Do something with finalData array
//     finalOptions.value = finalData
//     // console.log(finalData)
// });

const setupState = (step: Timeline) => {
    const foundState = computedXxx.value.find((item) => item.key === props.state)
    if(foundState){
        const set = step.key == props.state || step.index < foundState.index
        return set
    }else return
}

// watch(() => props.state, (newData) => {
//     stepsWithIndex()
// })

// onBeforeMount(stepsWithIndex)

// Format Date
const useFormatTime = (dateIso: string | Date, OptionsTime?: OptionsTime) => {
    if (!dateIso) return '-'

    let tempLocaleCode = OptionsTime?.localeCode === 'zh-Hans' ? 'zhCN' : OptionsTime?.localeCode ?? 'enUS'
    let tempDateIso = new Date(dateIso)

    return format(tempDateIso, 'EEE, do MMM yy', { locale: localesCode[tempLocaleCode] }) // October 13th, 2023
}

</script>

<template>
    <div class="w-full py-5 sm:py-2 flex flex-col isolate">
        <Swiper ref="_swiperRef" :slideToClickedSlide="false" :slidesPerView="slidesPerView"
            :centerInsufficientSlides="true" :pagination="{ clickable: true, }" class="w-full h-fit isolate">
            <template v-for="(step, stepIndex) in computedXxx" :key="stepIndex">
                <SwiperSlide>
                    <!-- Section: Title -->
                    <div class="w-fit mx-auto capitalize text-xxs md:text-xs text-center whitespace-nowrap truncate max-w-full px-2"
                        :class="step.timestamp || state == step.key ? 'text-[#888] ' : 'text-gray-300'">
                        <FontAwesomeIcon v-if="step.icon" :icon='step.icon' class='text-sm' fixed-width aria-hidden='true' />
                        {{ step.label }}
                    </div>

                    <div class="relative flex items-center mt-2.5 mb-0.5">
                        <!-- Step: Tail -->
                        <div v-if="stepIndex != 0"
                            class="z-10 px-1 w-full absolute flex align-center items-center align-middle content-center -translate-x-1/2 top-1/2 -translate-y-1/2">
                            <div class="w-full rounded items-center align-middle align-center flex-1">
                                <div class="w-full py-[1px] rounded ml-[1px]"
                                    :class="setupState(step) ? 'bg-[#66dc71]' : 'bg-gray-300'" />
                            </div>
                        </div>

                        <!-- Step: Head -->
                        <div @click="() => emits('updateButton', { step: step, options: computedXxx })"
                            v-tooltip="step.label"
                            class="z-20 aspect-square mx-auto rounded-full text-lg flex justify-center items-center"
                            :class="[
                                setupState(step) ? 'text-green-600 bg-[#66dc71] h-3' : 'border border-gray-300 text-gray-400 bg-white h-3'
                            ]"
                        >
                        </div>
                    </div>

                    <!-- Step: Description -->
                    <div v-tooltip="useFormatTimeComposables(step.timestamp, { formatTime: 'PPPPpp' })"
                        class="text-xxs md:text-xs text-[#555] text-center select-none">
                        {{ useFormatTime(step.timestamp) }}
                    </div>
                </SwiperSlide>
            </template>
        </Swiper>
    </div>
</template>