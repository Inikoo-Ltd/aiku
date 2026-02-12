<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 12 May 2024 21:59:08 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { useFormatTime, useSecondsToMS } from '@/Composables/useFormatTime'
import { Timesheet } from "@/types/timesheet"
import { useLocaleStore } from '@/Stores/locale'

defineProps<{
    data: {}
    tab?: string
    statistics: {
        on_time: number,
        late_clock_in: number,
        early_clock_out: number,
        no_clock_out: number,
        invalid: number,
        absent: number,
        total: number,
    }
}>()

const locale = useLocaleStore()

const timesheetRoute = (timesheet: Timesheet) => {
    switch (route().current()) {
        case "grp.clocking_employees.index":
            return route(
                "grp.clocking_employees.show",
                [timesheet.id])
        default:
            return route(
                "grp.org.hr.timesheets.show",
                [
                    route().params["organisation"],
                    timesheet.id
                ])
    }
}

</script>

<template>
    <div class="mt-4 bg-white border border-gray-200 rounded-lg p-4 shadow-sm mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 text-center divide-x divide-gray-100">
            <div class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.on_time }}</div>
                <div class="text-xs text-gray-500 mt-1">On time</div>
            </div>

            <div class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.late_clock_in }}</div>
                <div class="text-xs text-gray-500 mt-1">Late clock in</div>
            </div>

            <div class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.early_clock_out }}</div>
                <div class="text-xs text-gray-500 mt-1">Early clock out</div>
            </div>

            <div class="px-2">
                <div class="text-lg font-bold text-blue-600 flex justify-center items-center gap-1">
                    {{ statistics.no_clock_out }}
                    <font-awesome-icon :icon="['fal', 'info-circle']" class="text-gray-400 text-[10px]" />
                </div>
                <div class="text-xs text-gray-500 mt-1">No clock out</div>
            </div>

            <div class="px-2">
                <div class="text-lg font-bold text-blue-600">{{ statistics.invalid }}</div>
                <div class="text-xs text-gray-500 mt-1">Invalid</div>
            </div>

            <div class="px-2 border-r-0 lg:border-r">
                <div class="text-lg font-bold text-blue-600">{{ statistics.absent }}</div>
                <div class="text-xs text-gray-500 mt-1">Absent</div>
            </div>

            <div class="px-2 border-l border-gray-200">
                <div class="text-lg font-bold text-gray-800">{{ statistics.total }}</div>
                <div class="text-xs text-gray-500 mt-1">Total Logs</div>
            </div>

        </div>
    </div>
    <Table :resource="data" class="mt-5" :name="tab">

        <!-- Column: Date -->
        <template #cell(date)="{ item: timesheet }">
            <div class="text-gray-500">
                <Link :href="timesheetRoute(timesheet)" class="whitespace-nowrap primaryLink">
                    {{ useFormatTime(timesheet.date, { localeCode: locale.language.code }) }}
                </Link>
            </div>
        </template>

        <!-- Column: Start at -->
        <template #cell(start_at)="{ item: user }">
            <div class="whitespace-nowrap">
                {{ useFormatTime(user.start_at, { formatTime: 'hh:mm', localeCode: locale.language.code }) }}
            </div>
        </template>

        <!-- Column: End at -->
        <template #cell(end_at)="{ item: user }">
            <div class="whitespace-nowrap">
                {{ useFormatTime(user.end_at, { formatTime: 'hh:mm', localeCode: locale.language.code }) }}
            </div>
        </template>

        <!-- Column: Working duration -->
        <template #cell(working_duration)="{ item: user }">
            <div class="tabular-nums">
                {{ useSecondsToMS(user.working_duration) }}
            </div>
        </template>

        <!-- Column: Breaks Duration -->
        <template #cell(breaks_duration)="{ item: user }">
            <div class="tabular-nums">
                {{ useSecondsToMS(user.breaks_duration) }}
            </div>
        </template>



    </Table>
</template>
