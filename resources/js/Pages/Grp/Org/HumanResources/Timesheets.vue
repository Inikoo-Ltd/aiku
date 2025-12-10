<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 16 May 2024 12:23:59 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableSheets from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheets.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { startOfWeek, startOfMonth, startOfQuarter, startOfYear, addDays, addWeeks } from 'date-fns'

import { format, parse, getYear, getMonth, getDate } from 'date-fns'
import { getISOWeek, getISOWeekYear, parseISO } from 'date-fns'
import { computed, ref, watch } from 'vue'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'


import { faChevronLeft, faChevronRight } from '@fas'
import { faCalendarAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faChevronLeft, faChevronRight, faCalendarAlt)

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
}>()



function periodLabel(period: any) {
    if (!period) return false

    if (period.day) {
        // May 28th, 2024
        const date = new Date(period.day.slice(0, 4), period.day.slice(4, 6) - 1, period.day.slice(6, 8))
        return `${format(date, 'MMMM do, yyyy')}`
    }

    if (period.week) {
        // May 26th, 2024 - June 1st, 2024
        const year = period.week.slice(0, 4)
        const weekNumber = parseInt(period.week.slice(4), 10)
        const startOfTheWeek = startOfWeek(addDays(new Date(year, 0, 1), (weekNumber - 1) * 7))
        return `${format(startOfTheWeek, 'MMMM do, yyyy')} - ${format(addDays(startOfTheWeek, 6), 'MMMM do, yyyy')}`
    }

    if (period.month) {
        // May 2024
        const year = period.month.slice(0, 4)
        const monthNumber = period.month.slice(4, 6) - 1
        const startOfTheMonth = startOfMonth(new Date(year, monthNumber))
        return `${format(startOfTheMonth, 'MMMM yyyy')}`
    }

    if (period.quarter) {
        // April 2024 - June 2024
        const year = period.quarter.slice(0, 4)
        const quarterNumber = parseInt(period.quarter.slice(5), 10)
        const startOfTheQuarter = startOfQuarter(new Date(year, (quarterNumber - 1) * 3))
        return `${format(startOfTheQuarter, 'MMMM yyyy')} - ${format(addDays(startOfTheQuarter, 89), 'MMMM yyyy')}`
    }

    if (period.year) {
        // 2024
        const year = period.year
        const startOfTheYear = startOfYear(new Date(year))
        return `${format(startOfTheYear, 'yyyy')}`
    }
}
</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #afterTitle>
            <div v-if="route().params?.period" class="flex font-normal text-lg leading-none h-full text-gray-400">
                <div>({{ periodLabel(route().params.period) }}</div>
                <div>

                </div>
                <div>)</div>
            </div>
        </template>
    </PageHeading>

    <TableSheets :data="data" />
</template>
