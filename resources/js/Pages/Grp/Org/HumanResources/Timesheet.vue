<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 08 Sept 2022 00:38:38 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import TableTimeTrackers from "@/Components/Tables/Grp/Org/HumanResources/TableTimeTrackers.vue"
import TableClockings from "@/Components/Tables/Grp/Org/HumanResources/TableClockings.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVoteYea, faArrowsH } from '@fal'
import { useSecondsToMS, useHMAP } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

library.add( faVoteYea, faArrowsH )

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: Navigation
    },
    history?: {}
    time_trackers?: {}
    clockings?: {}
    timesheet: {
        id?: number
        date?: string
        store_clocking_route?: string
        work_start_at?: string
        work_end_at?: string
        work_duration?: string
        breaks_duration?: string
        total_duration?: number
        paid_duration?: number
        unpaid_overtime_duration?: number
        paid_overtime_duration?: number
        overtime?: number
        about?: string
        scheduled_hours?: {
            source: "employee" | "organisation" | null
            start_time: string | null
            end_time: string | null
            breaks: { name: string | null; start_time: string | null; end_time: string | null }[]
        }
    }

}>()

const formatHM = (value: string | null | undefined): string => value ? value.slice(0, 5) : "-"
const duration = (seconds: number | undefined): string => seconds ? useSecondsToMS(seconds) : "-"

const summary = computed(() => [
    { label: trans('Start'), value: useHMAP(props.timesheet.work_start_at) || '-' },
    { label: trans('End'), value: useHMAP(props.timesheet.work_end_at) || '-' },
    { label: trans('Breaks'), value: duration(props.timesheet.breaks_duration as unknown as number) },
    { label: trans('Total worktime'), value: duration(props.timesheet.total_duration) },
    { label: trans('Paid time'), value: duration(props.timesheet.paid_duration) },
    { label: trans('Unpaid overtime'), value: duration(props.timesheet.unpaid_overtime_duration) },
    { label: trans('Paid overtime'), value: duration(props.timesheet.paid_overtime_duration) },
])

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        time_trackers: TableTimeTrackers,
        clockings: TableClockings,
        history: TableHistories
    }

    return components[currentTab.value]
})

const extraProps = computed(() => {
    if (currentTab.value === 'clockings' || currentTab.value === 'time_trackers') {
        return {
            storeClockingRoute: props.timesheet.store_clocking_route,
            timesheetDate: props.timesheet.date,
        }
    }

    return {}
})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 pt-4 space-y-3">
        <dl class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
            <div v-for="item in summary" :key="item.label" class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-100">
                <dd class="text-xl font-bold tabular-nums tracking-tight text-gray-800">{{ item.value }}</dd>
                <dt class="mt-0.5 truncate text-sm text-gray-500">{{ item.label }}</dt>
            </div>
        </dl>

        <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-500">
            <div v-if="timesheet.scheduled_hours?.source">
                <span class="font-medium text-gray-700">{{ trans('Scheduled') }}:</span>
                <template v-if="timesheet.scheduled_hours.start_time || timesheet.scheduled_hours.end_time">
                    {{ formatHM(timesheet.scheduled_hours.start_time) }} – {{ formatHM(timesheet.scheduled_hours.end_time) }}
                    <span v-for="(brk, index) in timesheet.scheduled_hours.breaks" :key="index" class="text-gray-400">
                        · {{ brk.name || trans('Break') }} {{ formatHM(brk.start_time) }}–{{ formatHM(brk.end_time) }}
                    </span>
                </template>
                <span v-else class="italic">{{ trans('Not a working day') }}</span>
                <span class="text-xs text-gray-400">({{ timesheet.scheduled_hours.source === 'organisation' ? trans('organisation default') : trans('employee schedule') }})</span>
            </div>
            <div v-if="timesheet.about">
                <span class="font-medium text-gray-700">{{ trans('Note') }}:</span> {{ timesheet.about }}
            </div>
        </div>
    </div>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" class="mt-4" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" v-bind="extraProps"></component>
</template>
