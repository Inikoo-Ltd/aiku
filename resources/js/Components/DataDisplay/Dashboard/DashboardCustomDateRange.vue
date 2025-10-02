<script setup lang="ts">
import { inject, onBeforeMount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faCheckSquare, faSquare, faCalendarAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import VueDatePicker from '@vuepic/vue-datepicker'
import DatePicker from 'primevue/datepicker'
// import { debounce } from 'lodash-es'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Select from 'primevue/select'
import { useFormatTime } from '@/Composables/useFormatTime'
import { Popover } from 'primevue'

library.add(faChevronDown, faCheckSquare, faSquare, faCalendarAlt)


const props = defineProps<{
    interval: {
        value: string
    }
    updateInterval: Function
}>()

// Method: convert Date to '20250206-20250223'
const formattedDateRange = (date: string[] | Date[]) => {
    return date?.map(dateString => {
        const date = dateString ? new Date(dateString) : new Date();
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0'); // Ensure two digits for month
        const day = date.getDate().toString().padStart(2, '0'); // Ensure two digits for day

        return `${year}${month}${day}`;
    }).join('-')
}
const isLoadingReload = ref(false)

// Watch the datepicker
const dateFilterValue = ref([new Date(), new Date()])
watch(dateFilterValue, (newValue) => {
    // props.interval.value = formattedDateRange(newValue)
    // props.updateInterval(formattedDateRange(newValue))
    router.get(
        route('grp.json.dashboard_custom-dates.masters_shops_sales'),
        {
            start_date: formattedDateRange(newValue).split('-')[0],  // start_date
            end_date: formattedDateRange(newValue).split('-')[1],  // end_date
        },
        {
            onStart: () => {
                isLoadingReload.value = true
            },
            onFinish: () => {
                isLoadingReload.value = false
            },
            onSuccess: () => {
                // console.log('success');
            },
            onError: (e) => {
                // console.log('eeerr', e)
            },
            headers: {
                'X-Timezone': Intl.DateTimeFormat().resolvedOptions().timeZone,
            }
        }
    )
})


// Convert Date to '20250206'
function formatDate(dateString: string) {
    const year = dateString.substring(0, 4);
    const month = dateString.substring(4, 6);
    const day = dateString.substring(6, 8);
    return `${year}-${month}-${day}`;
}


onBeforeMount(() => {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);

    // To assign init value
    for (let param of urlParams.keys()) {
        if (param.startsWith('between[') && param.endsWith(']')) {
            const fieldName = param.slice(8, -1)
            const dateRangeString = urlParams.get(param)  // the value of params ('20250206-20250223')

            if (dateRangeString) {
                const dates = dateRangeString.split('-')  // split '20250206-20250223'

                if (dates.length === 2) {
                    dateFilterValue.value = [new Date(formatDate(dates[0])), new Date(formatDate(dates[1]))];
                }
            } else {
                continue // Skip to the next iteration
            }

            break;
        }
    }

})

const _popover = ref(null)
</script>

<template>
    <div class="flex rounded-md">

        <div @click="(e) => _popover?.toggle(e)"
            v-tooltip="trans('Filter by dates')"
            class="group inline-flex items-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
        >
            <div class="h-7 w-9 rounded flex justify-center items-center"
                :class="true ? 'border border-gray-300 hover:bg-gray-300 text-gray-600 hover:text-xgray-200' : 'bg-gray-600 hover:bg-gray-700 text-white'"
            >
                <FontAwesomeIcon v-if="!isLoadingReload" icon='fal fa-calendar-alt' class='cursor-pointer'
                    fixed-width aria-hidden='true' />
                <LoadingIcon v-else />
            </div>
        </div>

        <Popover ref="_popover">
            <div class="xbg-gray-50 xborder border-gray-300 rounded-md xabsolute right-0 z-10 xmt-3 w-fit transform px-4 pt-4 pb-6"
            >
                <!-- {{ dateFilterValue }} -->
                <div class="flex items-center gap-x-3 mb-3">
                    <div @click="() => {}" class="text-red-400 hover:text-red-600 cursor-pointer">
                        {{ trans("Reset filter by dates") }}
                    </div>
                </div>
                
                <VueDatePicker
                    v-model="dateFilterValue"
                    range
                    multi-calendars
                    inline 
                    auto-apply
                    :enableTimePicker="false"
                >
                </VueDatePicker>

                <div class="grid grid-cols-2 text-sm mt-3">
                    <!-- cccccccccccccccccccccccccc -->
                    <div class="text-left px-1.5">
                        <div class="text-gray-400">{{ trans("Since") }}</div>
                        <div class="">
                            {{ useFormatTime(dateFilterValue[0])}}
                        </div>
                    </div>

                    <div class="justify-self-end text-right px-1.5">
                        <div class="text-gray-400">{{ trans("Until") }}</div>
                        <div class="">
                            {{ useFormatTime(dateFilterValue[1])}}
                        </div>
                    </div>
                </div>
            </div>
        </Popover>

        
    </div>
</template>
