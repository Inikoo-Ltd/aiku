<script setup lang="ts">
import { onBeforeMount, nextTick, ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBadgePercent } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import VueDatePicker from '@vuepic/vue-datepicker'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Select from 'primevue/select'
import { useFormatTime } from '@/Composables/useFormatTime'
import { useDateIntervals } from '@/Composables/useDateIntervals'
import { Popover } from 'primevue'

library.add(faBadgePercent)

const props = defineProps<{
    label?: string
}>()

let isInitialising = true
let lastPayload = ''

const isLoadingReload = ref(false)
const offerPresence = ref<string | null>(null)
const dateFilterValue = ref<Date[] | null>(null)
const selectedInterval = ref<string | null>(null)

const dateIntervals = useDateIntervals()

const presenceOptions = computed(() => [
    { label: trans('With offer'), value: 'with' },
    { label: trans('Without offer'), value: 'without' },
])

const presenceLabel = computed(() => presenceOptions.value.find(option => option.value === offerPresence.value)?.label)

const hasOfferQuery = computed(() => !!offerPresence.value)

const formattedDateRange = (date: Date[]) => {
    return date.map(dateString => {
        const date = dateString ? new Date(dateString) : new Date()
        const year = date.getFullYear()
        const month = (date.getMonth() + 1).toString().padStart(2, '0')
        const day = date.getDate().toString().padStart(2, '0')

        return `${year}${month}${day}`
    }).join('-')
}

const parseDate = (dateString: string) => {
    const year = dateString.substring(0, 4)
    const month = dateString.substring(4, 6)
    const day = dateString.substring(6, 8)

    return new Date(`${year}-${month}-${day}`)
}

const applyFilter = () => {
    if (isInitialising) {
        return
    }

    const range = dateFilterValue.value?.[0] && dateFilterValue.value?.[1]
        ? formattedDateRange(dateFilterValue.value)
        : null

    if (range && !offerPresence.value) {
        offerPresence.value = 'with'
    }

    const payload = {
        'offer[has]': offerPresence.value,
        'offer[between]': offerPresence.value ? range : null,
    }
    const serialisedPayload = JSON.stringify(payload)

    if (serialisedPayload === lastPayload) {
        return
    }
    lastPayload = serialisedPayload

    router.reload({
        data: payload,
        onStart: () => {
            isLoadingReload.value = true
        },
        onFinish: () => {
            isLoadingReload.value = false
        },
        headers: {
            'X-Timezone': Intl.DateTimeFormat().resolvedOptions().timeZone,
        }
    })
}

watch([offerPresence, dateFilterValue], applyFilter)

watch(selectedInterval, (newValue) => {
    const interval = dateIntervals.value.find(i => i.value === newValue)

    if (interval) {
        dateFilterValue.value = interval.getDateRange()
        _popover.value?.hide()
    }
})

const resetFilter = () => {
    selectedInterval.value = null
    dateFilterValue.value = null
    offerPresence.value = null
}

onBeforeMount(() => {
    const urlParams = new URLSearchParams(window.location.search)
    const presence = urlParams.get('offer[has]')
    const range = urlParams.get('offer[between]')

    if (presence === 'with' || presence === 'without') {
        offerPresence.value = presence
    }

    const dates = range?.split('-') ?? []
    if (dates.length === 2) {
        dateFilterValue.value = [parseDate(dates[0]), parseDate(dates[1])]
    }

    nextTick(() => {
        isInitialising = false
    })
})

const _popover = ref()
const toggle = (event: Event) => {
    _popover.value.toggle(event)
}
</script>

<template>
    <div class="flex items-center gap-2 rounded-md">
        <div
            @click="toggle"
            v-tooltip="props.label ?? trans('Filter by offers')"
            class="cursor-pointer group inline-flex items-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
        >
            <div class="h-9 rounded flex justify-center items-center gap-2 border"
                :class="hasOfferQuery ? 'px-2 border-indigo-300 bg-indigo-50 hover:bg-indigo-100 text-indigo-700' : 'w-9 border-gray-300 hover:bg-gray-200 text-gray-600'"
            >
                <span v-if="hasOfferQuery" class="flex items-center gap-1.5 text-xs whitespace-nowrap">
                    <span class="font-medium">{{ presenceLabel }}</span>
                    <template v-if="dateFilterValue?.[0] && dateFilterValue?.[1]">
                        <span class="text-indigo-400">:</span>
                        <span class="font-medium">{{ useFormatTime(dateFilterValue[0], { formatTime: 'mdy' }) }}</span>
                        <span class="text-indigo-400">-</span>
                        <span class="font-medium">{{ useFormatTime(dateFilterValue[1], { formatTime: 'mdy' }) }}</span>
                    </template>
                </span>
                <FontAwesomeIcon v-if="!isLoadingReload" icon='fal fa-badge-percent' class='cursor-pointer'
                    fixed-width aria-hidden='true' />
                <LoadingIcon v-else />
            </div>
        </div>

        <Popover ref="_popover">
            <div class="bg-gray-50 border border-gray-300 rounded-md z-10 mt-3 w-fit transform px-4 pt-4 pb-6">
                <div class="flex items-center gap-x-3 mb-3">
                    <Select
                        v-model="offerPresence"
                        :options="presenceOptions"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="trans('Offers')"
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
                <div class="text-left px-1.5">
                    <div class="text-gray-400">{{ trans("Since") }}</div>
                    <div>
                        {{ dateFilterValue?.[0] ? useFormatTime(dateFilterValue[0]) : '-' }}
                    </div>
                </div>

                <div class="justify-self-end text-right px-1.5">
                    <div class="text-gray-400">{{ trans("Until") }}</div>
                    <div>
                        {{ dateFilterValue?.[1] ? useFormatTime(dateFilterValue[1]) : '-' }}
                    </div>
                </div>
            </div>
        </Popover>
    </div>
</template>
