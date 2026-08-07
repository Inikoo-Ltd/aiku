<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableTimesheets from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheets.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { format, startOfWeek, startOfMonth, startOfQuarter, startOfYear, addDays } from 'date-fns'
import { ref, computed } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import qs from 'qs'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCalendarAlt } from '@fal'
library.add(faCalendarAlt)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string,
        navigation: any
    }
    employee_view: {
        current: string,
        navigation: any
    }
    employees?: {}
    employee?: {}
}>()


const currentTab = ref(props.tabs?.current || 'employee')
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const currentData = computed(() => {
    return (props as any)[currentTab.value]
})

const currentEmployeeView = ref(props.employee_view?.current || 'overview')

function handleEmployeeViewUpdate(view: string) {
    currentEmployeeView.value = view
    const params = new URLSearchParams(location.search)
    params.set('view', view)
    router.get(location.pathname + `?${params.toString()}`, {}, { preserveState: true, preserveScroll: true })
}

const periodPrefix = computed(() => currentTab.value === 'employee' ? 'employee' : 'employees')

const periodParam = computed(() => {
    const url = usePage().url as string
    const queryString = url.includes('?') ? url.slice(url.indexOf('?') + 1) : ''
    const params = qs.parse(queryString) as Record<string, any>

    return params?.[`${periodPrefix.value}_period`] ?? params?.period ?? null
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
        const startOfTheWeek = startOfWeek(addDays(new Date(year, 0, 1), (weekNumber - 1) * 7), { weekStartsOn: 1 })
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

    <PageHeading :data="pageHead" />

    <Tabs v-if="Object.keys(tabs.navigation || {}).length" :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <Tabs
        v-if="currentTab === 'employee' && Object.keys(employee_view.navigation || {}).length"
        :current="currentEmployeeView"
        :navigation="employee_view.navigation"
        @update:tab="handleEmployeeViewUpdate"
        class="mt-2"
    />

    <div v-if="periodParam" class="mt-3 mb-1">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">
            <font-awesome-icon :icon="['fal', 'calendar-alt']" class="text-indigo-400" />
            {{ periodLabel(periodParam) }}
        </span>
    </div>

    <!-- TABLE -->
    <TableTimesheets :key="`${currentTab}-${currentEmployeeView}`" :tab="currentTab" :data="currentData" />
</template>
