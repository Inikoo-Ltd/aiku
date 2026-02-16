<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableTimesheets from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheets.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { format, startOfWeek, startOfMonth, startOfQuarter, startOfYear, addDays } from 'date-fns'
import { ref, computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'

// Import Icons
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInfoCircle } from '@fal'
library.add(faInfoCircle)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string,
        navigation: any
    }
    statistics: {
        on_time: number,
        late_clock_in: number,
        early_clock_out: number,
        no_clock_out: number,
        invalid: number,
        absent: number,
        total: number
    }
    employees: {}
    employee: {}
}>()


const currentTab = ref(props.tabs?.current || 'employees')
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const currentData = computed(() => {
    return (props as any)[currentTab.value]
})

const periodParam = computed(() => {
    const params = (route().params as any)
    return params?.period ?? null
})

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

function applyStatus(status: string | null) {
    const params = new URLSearchParams(location.search)
    if (status) {
        params.set('timesheet_status', status)
    } else {
        params.delete('timesheet_status')
    }
    const url = location.pathname + (params.toString() ? `?${params.toString()}` : '')
    router.get(url, {}, { preserveState: true, preserveScroll: true })
}
</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <template #afterTitle>
            <div v-if="periodParam" class="flex font-normal text-lg leading-none h-full text-gray-400">
                <div>({{ periodLabel(periodParam) }})</div>
            </div>
        </template>
    </PageHeading>

    <!-- STATISTICS CARDS SECTION -->
    <div class="mt-4 bg-white border border-gray-200 rounded-lg p-4 shadow-sm mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 text-center divide-x divide-gray-100">
            <button type="button" @click="applyStatus('on_time')" class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.on_time }}</div>
                <div class="text-xs text-gray-500 mt-1">On time</div>
            </button>

            <button type="button" @click="applyStatus('late_clock_in')" class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.late_clock_in }}</div>
                <div class="text-xs text-gray-500 mt-1">Late clock in</div>
            </button>

            <button type="button" @click="applyStatus('early_clock_out')" class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.early_clock_out }}</div>
                <div class="text-xs text-gray-500 mt-1">Early clock out</div>
            </button>

            <button type="button" @click="applyStatus('no_clock_out')" class="px-2">
                <div class="text-lg font-bold text-blue-600 flex justify-center items-center gap-1">
                    {{ statistics.no_clock_out }}
                    <font-awesome-icon :icon="['fal', 'info-circle']" class="text-gray-400 text-[10px]" />
                </div>
                <div class="text-xs text-gray-500 mt-1">No clock out</div>
            </button>

            <button type="button" @click="applyStatus('invalid')" class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.invalid }}</div>
                <div class="text-xs text-gray-500 mt-1">Invalid</div>
            </button>

            <button type="button" @click="applyStatus(null)" class="px-2 border-r-0 lg:border-r">
                <div class="text-lg font-bold text-blue-600">{{ statistics.absent }}</div>
                <div class="text-xs text-gray-500 mt-1">Absent</div>
            </button>

            <button type="button" @click="applyStatus(null)" class="px-2 border-l border-gray-200">
                <div class="text-lg font-bold text-gray-800">{{ statistics.total }}</div>
                <div class="text-xs text-gray-500 mt-1">Total Logs</div>
            </button>

        </div>
    </div>
    <!-- TABLE -->
    <TableTimesheets :key="currentTab" :tab="currentTab" :data="currentData" />
</template>
