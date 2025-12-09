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
import { format, parseISO } from 'date-fns'
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
        work_start_at?: string    
        work_end_at?: string
        work_duration?: string
        breaks_duration?: string
        total_duration?: number
        overtime?: number
        about?: string
    }

}>()

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

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="grid grid-cols-2 divide-x divide-gray-200 px-3 py-5">
        <div>
            
        </div>

        <div class="px-5 py-1">
            <div class="px-4 sm:px-0">
                <h3 class="text-lg font-semibold">Review Time</h3>
                <p class="mt-1 max-w-2xl text-sm  text-gray-500">The detail of employee's worktime in a day</p>
            </div>
            
            <div class="mt-4 border-t border-gray-100">
                <dl class="divide-y divide-gray-100">
                    <div class="bg-gray-50 px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">Start</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ useHMAP(timesheet.work_start_at) }}</dd>
                    </div>
                    <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">End</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ useHMAP(timesheet.work_end_at) || '-'}}</dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">Breaks</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ timesheet.breaks_duration || '-'}}</dd>
                    </div>
                    <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">Total worktime</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ useSecondsToMS(timesheet.total_duration) }}</dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">Overtime</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ timesheet.overtime ? useSecondsToMS(timesheet.overtime) : '-'}}</dd>
                    </div>

                    <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">About</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">
                            <span v-if="timesheet.about">{{ timesheet.about }}</span>
                            <span v-else class="text-gray-400 italic font-light">{{ trans('No note.') }}</span>
                        </dd>
                    </div>
                    
                </dl>
            </div>
        </div>
    </div>

    <hr class="border-t border-gray-200">

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"></component>
</template>
