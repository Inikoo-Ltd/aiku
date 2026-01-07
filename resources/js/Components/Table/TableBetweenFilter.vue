<script setup lang="ts">
import { onBeforeMount, ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faCheckSquare, faSquare, faCalendarAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import VueDatePicker from '@vuepic/vue-datepicker'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Select from 'primevue/select'
import { useFormatTime } from '@/Composables/useFormatTime'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'

library.add(faChevronDown, faCheckSquare, faSquare, faCalendarAlt)


const props = defineProps<{
    optionsList: string[]
    tableName: string
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
const hasBetweenQuery = ref(false)
watch(dateFilterValue, (newValue) => {
    router.reload(
        {
            data: { [`between[${selectedPeriodType.value}]`]: formattedDateRange(newValue) },  // Sent to url parameter (?tab=showcase, ?tab=menu)
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

    if (newValue?.[0] && newValue?.[1]) {
        hasBetweenQuery.value = true
    } else {
        hasBetweenQuery.value = false
    }
})

// Section: multiselect
const selectedPeriodType = ref(props.optionsList?.[0])
watch(selectedPeriodType, (newValue, oldValue) => {
    const oldBetween = oldValue ? {
        [`between[${oldValue}]`]: null
    } : {}

    if(dateFilterValue.value) {
        router.reload(
            {
                data: {
                    ...oldBetween,
                    [`between[${newValue}]`]: formattedDateRange(dateFilterValue.value),
                },
                onStart: () => {
                    isLoadingReload.value = true
                },
                onFinish: () => {
                    isLoadingReload.value = false
                },
                onSuccess: () => {
                },
                onError: (e) => {
                    // console.log('eeerr', e)
                }
            }
        )
    }
})

// Convert Date to '20250206'
function formatDate(dateString: string) {
    const year = dateString.substring(0, 4);
    const month = dateString.substring(4, 6);
    const day = dateString.substring(6, 8);
    return `${year}-${month}-${day}`;
}

// Date interval shortcuts
interface DateInterval {
    value: string
    label: string
    getDateRange: () => [Date, Date]
}

const dateIntervals = computed<DateInterval[]>(() => [
    {
        value: 'tdy',
        label: trans('Today'),
        getDateRange: () => {
            const now = new Date()
            return [new Date(now.setHours(0, 0, 0, 0)), new Date(new Date().setHours(23, 59, 59, 999))]
        }
    },
    {
        value: 'ld',
        label: trans('Yesterday'),
        getDateRange: () => {
            const yesterday = new Date()
            yesterday.setDate(yesterday.getDate() - 1)
            return [new Date(yesterday.setHours(0, 0, 0, 0)), new Date(yesterday.setHours(23, 59, 59, 999))]
        }
    },
    {
        value: '3d',
        label: trans('3 Days'),
        getDateRange: () => {
            const threeDaysAgo = new Date()
            threeDaysAgo.setDate(threeDaysAgo.getDate() - 3)
            return [new Date(threeDaysAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: '1w',
        label: trans('1 Week'),
        getDateRange: () => {
            const oneWeekAgo = new Date()
            oneWeekAgo.setDate(oneWeekAgo.getDate() - 7)
            return [new Date(oneWeekAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'wtd',
        label: trans('Week to Date'),
        getDateRange: () => {
            const now = new Date()
            const startOfWeek = new Date(now)
            const day = startOfWeek.getDay()
            const diff = startOfWeek.getDate() - day + (day === 0 ? -6 : 1)
            startOfWeek.setDate(diff)
            return [new Date(startOfWeek.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'lw',
        label: trans('Last Week'),
        getDateRange: () => {
            const now = new Date()
            const startOfLastWeek = new Date(now)
            const day = startOfLastWeek.getDay()
            const diff = startOfLastWeek.getDate() - day - 6
            startOfLastWeek.setDate(diff)
            const endOfLastWeek = new Date(startOfLastWeek)
            endOfLastWeek.setDate(endOfLastWeek.getDate() + 6)
            return [new Date(startOfLastWeek.setHours(0, 0, 0, 0)), new Date(endOfLastWeek.setHours(23, 59, 59, 999))]
        }
    },
    {
        value: '1m',
        label: trans('1 Month'),
        getDateRange: () => {
            const oneMonthAgo = new Date()
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1)
            return [new Date(oneMonthAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'mtd',
        label: trans('Month to Date'),
        getDateRange: () => {
            const now = new Date()
            const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
            return [new Date(startOfMonth.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'lm',
        label: trans('Last Month'),
        getDateRange: () => {
            const now = new Date()
            const startOfLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1)
            const endOfLastMonth = new Date(now.getFullYear(), now.getMonth(), 0)
            return [new Date(startOfLastMonth.setHours(0, 0, 0, 0)), new Date(endOfLastMonth.setHours(23, 59, 59, 999))]
        }
    },
    {
        value: '1q',
        label: trans('1 Quarter'),
        getDateRange: () => {
            const threeMonthsAgo = new Date()
            threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3)
            return [new Date(threeMonthsAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'qtd',
        label: trans('Quarter to Date'),
        getDateRange: () => {
            const now = new Date()
            const quarter = Math.floor(now.getMonth() / 3)
            const startOfQuarter = new Date(now.getFullYear(), quarter * 3, 1)
            return [new Date(startOfQuarter.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: '1y',
        label: trans('1 Year'),
        getDateRange: () => {
            const oneYearAgo = new Date()
            oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1)
            return [new Date(oneYearAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'ytd',
        label: trans('Year to Date'),
        getDateRange: () => {
            const now = new Date()
            const startOfYear = new Date(now.getFullYear(), 0, 1)
            return [new Date(startOfYear.setHours(0, 0, 0, 0)), new Date()]
        }
    }
])

const selectedInterval = ref<string | null>(null)

const applyInterval = (intervalValue: string) => {
    if (!intervalValue) {
        return
    }
    
    const interval = dateIntervals.value.find(i => i.value === intervalValue)
    if (interval) {
        selectedInterval.value = interval.value
        dateFilterValue.value = interval.getDateRange()
    }
}

watch(selectedInterval, (newValue) => {
    if (newValue) {
        applyInterval(newValue)
    }
})

const resetFilter = () => {
    selectedInterval.value = null
    dateFilterValue.value = [new Date(), new Date()]
    hasBetweenQuery.value = false
    
    router.reload({
        data: { [`between[${selectedPeriodType.value}]`]: null },
        onStart: () => {
            isLoadingReload.value = true
        },
        onFinish: () => {
            isLoadingReload.value = false
        }
    })
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
                // console.log('dates', dates)

                if (dates.length === 2) {
                    // Store the field name and the date range
                    dateFilterValue.value = [new Date(formatDate(dates[0])), new Date(formatDate(dates[1]))];
                    // console.log('dateFilterValue', dateFilterValue.value)
                    selectedPeriodType.value = fieldName;

                    hasBetweenQuery.value = true
                }
            } else {
                continue // Skip to the next iteration
            }

            break;
        }
    }

})
</script>

<template>
    <div class="flex items-center gap-2 rounded-md" v-tooltip="trans('Filter data by dates')">
        <!-- Display selected date range when custom interval is active -->
        <transition name="slide-fade">
            <div v-if="hasBetweenQuery && dateFilterValue[0] && dateFilterValue[1]"
                 class="flex items-center gap-1.5 px-2 py-1 bg-indigo-50 border border-indigo-200 rounded-sm text-xs text-indigo-700 whitespace-nowrap">
                <span class="font-medium">{{ useFormatTime(dateFilterValue[0], { formatTime: 'mdy' }) }}</span>
                <span class="text-indigo-400">-</span>
                <span class="font-medium">{{ useFormatTime(dateFilterValue[1], { formatTime: 'mdy' }) }}</span>
            </div>
        </transition>

        <Popover v-slot="{ open }" class="relative">
            <PopoverButton
                :class="open ? '' : ''"
                v-tooltip="trans('Filter by dates')"
                class="group inline-flex items-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
            >
                <div class="h-9 w-9 rounded-sm flex justify-center items-center"
                    :class="true ? 'border border-gray-300 hover:bg-gray-300 text-gray-600 hover:text-xgray-200' : 'bg-gray-600 hover:bg-gray-700 text-white'"
                >
                    <FontAwesomeIcon v-if="!isLoadingReload" icon='fal fa-calendar-alt' class='cursor-pointer'
                        fixed-width aria-hidden='true' />
                    <LoadingIcon v-else />
                </div>
            </PopoverButton>

            <Transition name="headlessui" >
                <PopoverPanel
                    class="bg-gray-50 border border-gray-300 rounded-md absolute right-0 z-10 mt-3 w-fit transform px-4 pt-4 pb-6"
                >
                    <div class="flex items-center gap-x-3 mb-3">
                        <Select
                            v-model="selectedPeriodType"
                            :options="optionsList"
                            :placeholder="trans('Dates range')"
                            class="flex-1"
                        />

                        <Select
                            v-model="selectedInterval"
                            :options="dateIntervals.map(i => ({ label: i.label, value: i.value }))"
                            optionLabel="label"
                            optionValue="value"
                            :placeholder="trans('Quick intervals')"
                            class="flex-1"
                        />

                        <div @click="resetFilter" class="text-red-400 hover:text-red-600 cursor-pointer whitespace-nowrap">
                            {{ trans("Reset") }}
                        </div>
                    </div>

                    <VueDatePicker
                        v-model="dateFilterValue"
                        range
                        multi-calendars
                        inline
                        auto-apply
                        :enableTimePicker="false"
                        @update:model-value="selectedInterval = null"
                    />

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
                </PopoverPanel>
            </Transition>
        </Popover>
    </div>
</template>
