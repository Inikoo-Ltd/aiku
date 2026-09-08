<script setup lang="ts">
import { onBeforeMount, nextTick, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faCheckSquare, faSquare, faCalendarAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import VueDatePicker from '@vuepic/vue-datepicker'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Select from 'primevue/select'
import { useFormatTime } from '@/Composables/useFormatTime'
import { useDateIntervals } from '@/Composables/useDateIntervals'
import { Popover } from 'primevue'

library.add(faChevronDown, faCheckSquare, faSquare, faCalendarAlt)


const props = defineProps<{
    optionsList: string[]
    tableName: string
    appliedValue?: { column: string, range: string } | null
}>()

let isInitialising = true

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
const dateFilterValue = ref<Date[] | null>(null)
const hasBetweenQuery = ref(false)
watch(dateFilterValue, (newValue) => {
    if (isInitialising) {
        return
    }

    router.reload(
        {
            data: { [`between[${selectedPeriodType.value}]`]: newValue?.[0] && newValue?.[1] ? formattedDateRange(newValue) : null },  // Sent to url parameter (?tab=showcase, ?tab=menu)
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
    if (isInitialising) {
        return
    }

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

const dateIntervals = useDateIntervals()

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
        _popover.value?.hide()
    }
})

const resetFilter = () => {
    selectedInterval.value = null
    isInitialising = true
    dateFilterValue.value = null
    hasBetweenQuery.value = false
    nextTick(() => {
        isInitialising = false
    })

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

const assignInitValue = (fieldName: string, dateRangeString: string) => {
    const dates = dateRangeString.split('-')  // split '20250206-20250223'

    if (dates.length !== 2) {
        return false
    }

    dateFilterValue.value = [new Date(formatDate(dates[0])), new Date(formatDate(dates[1]))]
    selectedPeriodType.value = fieldName
    hasBetweenQuery.value = true

    return true
}

onBeforeMount(() => {
    const queryString = window.location.search;
    const urlParams = new URLSearchParams(queryString);
    let hasInitValue = false

    // To assign init value
    for (let param of urlParams.keys()) {
        if (param.startsWith('between[') && param.endsWith(']')) {
            const fieldName = param.slice(8, -1)
            const dateRangeString = urlParams.get(param)  // the value of params ('20250206-20250223')

            if (dateRangeString) {
                hasInitValue = assignInitValue(fieldName, dateRangeString)
            } else {
                continue // Skip to the next iteration
            }

            break;
        }
    }

    if (!hasInitValue && props.appliedValue?.range) {
        assignInitValue(props.appliedValue.column, props.appliedValue.range)
    }

    nextTick(() => {
        isInitialising = false
    })
})

watch(() => props.appliedValue, (newValue) => {
    isInitialising = true

    if (newValue?.range) {
        assignInitValue(newValue.column, newValue.range)
    } else {
        dateFilterValue.value = null
        hasBetweenQuery.value = false
    }

    nextTick(() => {
        isInitialising = false
    })
})

// Section: Popover
const _popover = ref()
const toggle = (event) => {
    _popover.value.toggle(event);
}
</script>

<template>
    <div class="flex items-center gap-2 rounded-md">

        <div
            @click="toggle"
            v-tooltip="trans('Filter by dates')"
            class="cursor-pointer group inline-flex items-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
        >
            <div class="h-9 rounded flex justify-center items-center gap-2 border"
                :class="hasBetweenQuery ? 'px-2 border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-700' : 'w-9 border-gray-300 hover:bg-gray-200 text-gray-600'"
            >
                <span v-if="hasBetweenQuery && dateFilterValue?.[0] && dateFilterValue?.[1]"
                    class="flex items-center gap-1.5 text-xs whitespace-nowrap">
                    <span class="font-medium">{{ useFormatTime(dateFilterValue[0], { formatTime: 'mdy' }) }}</span>
                    <span class="text-amber-400">-</span>
                    <span class="font-medium">{{ useFormatTime(dateFilterValue[1], { formatTime: 'mdy' }) }}</span>
                </span>
                <FontAwesomeIcon v-if="!isLoadingReload" icon='fal fa-calendar-alt' class='cursor-pointer'
                    fixed-width aria-hidden='true' />
                <LoadingIcon v-else />
            </div>
        </div>


        <Popover ref="_popover">
            <div class="bg-gray-50 border border-gray-300 rounded-md xabsolute right-0 z-10 mt-3 w-fit transform px-4 pt-4 pb-6" >
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

                <div class="flex justify-end w-[520px]">
                    <VueDatePicker
                        v-model="dateFilterValue"
                        range
                        multi-calendars
                        inline
                        auto-apply
                        :enableTimePicker="false"
                        @update:model-value="selectedInterval = null"
                    />
                </div>
            </div>

            <div class="grid grid-cols-2 text-sm mt-3">
                <!-- cccccccccccccccccccccccccc -->
                <div class="text-left px-1.5">
                    <div class="text-gray-400">{{ trans("Since") }}</div>
                    <div class="">
                        {{ dateFilterValue?.[0] ? useFormatTime(dateFilterValue[0]) : '-' }}
                    </div>
                </div>

                <div class="justify-self-end text-right px-1.5">
                    <div class="text-gray-400">{{ trans("Until") }}</div>
                    <div class="">
                        {{ dateFilterValue?.[1] ? useFormatTime(dateFilterValue[1]) : '-' }}
                    </div>
                </div>
            </div>

        </Popover>
    </div>
</template>
