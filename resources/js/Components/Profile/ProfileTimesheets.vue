<script setup lang='ts'>
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Row from 'primevue/row'
import ColumnGroup from 'primevue/columngroup'

import { Link } from '@inertiajs/vue3'
import { useFormatTime, useSecondsToMS } from '@/Composables/useFormatTime'
import { Timesheet } from "@/types/timesheet"
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { Table } from '@/types/Table'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const props = defineProps<{
    data: Table
}>()

const locale = inject('locale', aikuLocaleStructure)

const timesheetRoute = (timesheet: Timesheet) => {
    switch (route().current()) {
        case "grp.org.hr.employees.show":
            return route(
                "grp.org.hr.employees.show.timesheets.show",
                [route().params["organisation"],
                route().params["employee"],
                timesheet?.id])
        default:
            return route(
                "grp.org.hr.timesheets.show",
                [
                    route().params["organisation"],
                    timesheet?.id
                ])
    }
}
</script>

<template>
    <div>
        <!-- Profile Visit Logs -->

        <DataTable
            v-if="data?.data?.length"
            ref="_dt"
            :value="data.data"
            dataKey="id"
            :paginator="true"
            :rows="15"
            scrollable
            removableSort
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            :rowsPerPageOptions="[15, 25, 40]"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products"
        >
            <Column field="date" header="Username" sortable class="overflow-hidden transition-all">
                <template #body="{ data }">
                    <div class="text-gray-500">
                        <Link :href="timesheetRoute(timesheet)" class="whitespace-nowrap primaryLink">
                            {{ useFormatTime(timesheet.date, { localeCode: locale.language.code }) }}
                        </Link>
                    </div>
                </template>
            </Column>


            <Column field="start_at" header="Start at" class="overflow-hidden transition-all">
                <template #body="{ data }">
                    <div class="whitespace-nowrap">
                        {{ useFormatTime(data.start_at, { formatTime: 'hh:mm', localeCode: locale.language.code }) }}
                    </div>
                </template>
            </Column>

            <Column field="end_at" header="End at" class="overflow-hidden transition-all">
                <template #body="{ data }">
                    <div class="whitespace-nowrap">
                        {{ useFormatTime(data.end_at, {localeCode: locale.language.code }) }}
                    </div>
                </template>
            </Column>

            <Column field="working_duration" header="Working duration" class="overflow-hidden transition-all">
                <template #body="{ data }">
                    <div class="tabular-nums">
                        {{ useSecondsToMS(data.working_duration) }}
                    </div>
                </template>
            </Column>

            <Column field="breaks_duration" header="Breaks Duration" class="overflow-hidden transition-all">
                <template #body="{ data }">
                    <div class="tabular-nums">
                        {{ useSecondsToMS(data.breaks_duration) }}
                    </div>
                </template>
            </Column>

        </DataTable>

        <div v-else class="mx-auto text-center py-8 text-gray-500 text-xl">
            <EmptyState :data="{title: 'No data Timesheets'}" />
        </div>
    </div>
    <!-- <pre>{{ data.data }}</pre> -->
</template>