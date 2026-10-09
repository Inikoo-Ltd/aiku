<script setup lang='ts'>
import { isToday } from 'date-fns'
import Popover from '@/Components/Popover.vue'
import { router } from '@inertiajs/vue3'
import DatePicker from '@vuepic/vue-datepicker'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { formatInTimeZone } from 'date-fns-tz'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faArrowRight } from '@far'
import { faExclamationTriangle, faPencil } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useFormatTime } from '@/Composables/useFormatTime'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { computed, ref } from 'vue'
import { ctrans } from '@/Composables/useTrans'
library.add(faArrowRight, faExclamationTriangle, faPencil)

    
const props = defineProps<{
    startDate: string
    endDate: string
    updateRoute?: routeType
    isEndDateNotEditable?: boolean
}>()

const isEndDateToday = isToday(new Date(props.endDate))


// Method: Set end date
const getTomorrowDate = () => {
    const today = new Date()
    const tomorrow = new Date(today)
    tomorrow.setDate(today.getDate() + 1)
    return tomorrow
}
const isLoadingSetEstimatedDate = ref(false)
const onChangeEstimateDate = async (newDate: Date, close: Function) => {
    if(!props.updateRoute) return

    router.patch(route(props.updateRoute.name, props.updateRoute.parameters),
    {
        end_date : newDate
    },
    {
        onStart: () => isLoadingSetEstimatedDate.value = true,
        onError: (error) => {
            notify({
                title: "Failed",
                text: error?.end_date || "Failed to update the end date, try again.",
                type: "error",
            })
        },
        onSuccess: () => close(),
        onFinish: () => isLoadingSetEstimatedDate.value = false,
    })
}


// Date: to UTC instead follow local machine
const formattedEndDate = computed(() => {
    return formatInTimeZone(new Date(props.endDate), 'UTC', 'MMMM do, yyyy');
})
</script>

<template>
    <div class="relative grid lg:grid-cols-11 gap-y-1.5 xl:min-w-[400px] w-full text-gray-600">
        <div class="bg-black/10 lg:col-span-5 px-4 py-2 rounded-md ring-1 ring-black/20 flex flex-col">
            <div class="text-xs text-gray-500">{{ ctrans("Start date") }}</div>
            <div class="font-medium">
                {{ useFormatTime(startDate)}}
            </div>
        </div>

        <div class="absolute top-1/2 left-1/2 -translate-y-1/2 lg:translate-y-0 -translate-x-1/2 lg:translate-x-0 lg:static flex justify-center items-center text-gray-700 lg:text-gray-400 rotate-90 lg:rotate-0">
            <FontAwesomeIcon icon='far fa-arrow-right' class='' fixed-width aria-hidden='true' />
        </div>

        <div
            class="lg:col-span-5 px-4 py-2 rounded-md ring-1 ring-black/20 flex flex-col bg-black/10"
            :xxclass="[ isEndDateToday ? 'bg-red-100 text-gray-500' : 'bg-black/10']"
            
        >
            <div class="flex justify-between text-xs">
                <div class="text-xs text-gray-500">{{ ctrans("End date") }}</div>
                <!-- <FontAwesomeIcon v-if="isEndDateToday" v-tooltip="isEndDateToday ? ctrans('Today is the end date') : undefined" icon='fal fa-exclamation-triangle' class='text-sm text-red-500' fixed-width aria-hidden='true' /> -->
            </div>
            <div class="font-medium">
                <div v-if="isEndDateNotEditable">
                    <div>{{ formattedEndDate }}</div>
                </div>
                
                <Popover v-else position="right-0">
                    <template #button>
                        <div class="flex flex-nowrap text-left items-center gap-x-1">
                            <Transition name="spin-to-down">
                                <div :key="formattedEndDate">{{ formattedEndDate }}</div>
                            </Transition>
                            <div class="px-1 flex items-center py-1 hover:text-gray-700">
                                <FontAwesomeIcon icon='fal fa-pencil' class='text-xs' fixed-width aria-hidden='true' />
                            </div>
                        </div>
                    </template>

                    <template #content="{ close }">
                        <DatePicker
                            :modelValue="endDate"
                            @update:modelValue="(newVal: Date) => onChangeEstimateDate(newVal, close)"
                            inline auto-apply
                            :enable-time-picker="false"
                            :min-date="getTomorrowDate()"
                        />
                        <div v-if="isLoadingSetEstimatedDate" class="absolute inset-0 bg-white/70 flex items-center justify-center">
                            <LoadingIcon class="text-5xl" />
                        </div>
                    </template>
                </Popover>
            </div>
        </div>
    </div>
</template>