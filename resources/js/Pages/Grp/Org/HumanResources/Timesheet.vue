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
import type { routeType } from "@/types/route"
import TableTimeTrackers from "@/Components/Tables/Grp/Org/HumanResources/TableTimeTrackers.vue"
import TableClockings from "@/Components/Tables/Grp/Org/HumanResources/TableClockings.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVoteYea, faArrowsH, faTrash } from '@fal'
import { format, parseISO } from 'date-fns'
import { useSecondsToMS, useHMAP } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

library.add( faVoteYea, faArrowsH, faTrash )

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    delete_route?: routeType | false
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
        paid_duration?: number
        unpaid_overtime_duration?: number
        paid_overtime_duration?: number
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
    <div class="flex items-start justify-between">
        <PageHeading :data="pageHead" class="grow" />

        <ModalConfirmationDelete
            v-if="delete_route"
            :routeDelete="delete_route"
            :title="trans('Delete this timesheet?')"
            :description="trans('This will permanently delete all clockings and time tracker sessions recorded for this day. This action cannot be undone.')"
            class="mt-4 mr-4 shrink-0"
        >
            <template #default="{ changeModel }">
                <Button type="cancel" :label="trans('Delete Timesheet')" size="xs" :icon="faTrash" @click="changeModel(true)" />
            </template>
        </ModalConfirmationDelete>
    </div>

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
                        <dt class="text-sm text-gray-500">Paid time</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ timesheet.paid_duration ? useSecondsToMS(timesheet.paid_duration) : '-'}}</dd>
                    </div>
                    <div class="bg-white px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">Unpaid overtime</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ timesheet.unpaid_overtime_duration ? useSecondsToMS(timesheet.unpaid_overtime_duration) : '-'}}</dd>
                    </div>
                    <div class="bg-gray-50 px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-3">
                        <dt class="text-sm text-gray-500">Paid overtime</dt>
                        <dd class="mt-1 text-sm  font-medium sm:col-span-2 sm:mt-0">{{ timesheet.paid_overtime_duration ? useSecondsToMS(timesheet.paid_overtime_duration) : '-'}}</dd>
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
