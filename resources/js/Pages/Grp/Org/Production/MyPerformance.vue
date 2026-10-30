<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 21 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import DatePicker from "primevue/datepicker"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChartLine, faFilter } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faChartLine, faFilter)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    period: { key: string, from: string, to: string }
    totals: {
        hours: number
        quantity_made: number
        quantity_rejected: number
        tasks: number
        on_target: number
        with_target: number
        preparation_hours: number
        cleaning_hours: number
        maintenance_hours: number
    }
    steps: { task_name: string, tasks: number, hours: number, quantity_made: number, units_per_hour: number | null, standard_rate: number | null }[]
    data: object
}>()

const periodKey = ref(props.period.key)
const periodOptions = [
    { value: "today", label: ctrans("Today") },
    { value: "this_week", label: ctrans("This week") },
    { value: "last_week", label: ctrans("Last week") },
    { value: "this_month", label: ctrans("This month") },
    { value: "custom", label: ctrans("Dates") },
]

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const from = ref(props.period.from)
const to = ref(props.period.to)
const dateModel = (target: typeof from) =>
    computed<Date | null>({
        get: () => (target.value ? fromIsoDate(target.value) : null),
        set: (value) => {
            if (value) target.value = toLocalIsoDate(value)
        },
    })
const fromDate = dateModel(from)
const toDate = dateModel(to)
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

function load(period: string) {
    router.get(window.location.pathname, period === "custom" ? { period, from: from.value, to: to.value } : { period }, { preserveState: false })
}

function choosePeriod(period: string) {
    periodKey.value = period
    if (period !== "custom") load(period)
}

const clockHours = (hours: number) => {
    const minutes = Math.round(Number(hours) * 60)
    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, "0")}`
}

const finishedAt = (iso: string) => new Date(iso).toLocaleString([], { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" })

const tiles = computed(() => [
    { label: ctrans("Hours working"), value: clockHours(props.totals.hours) },
    { label: ctrans("Units made"), value: props.totals.quantity_made.toLocaleString() },
    { label: ctrans("Tasks finished"), value: props.totals.tasks.toLocaleString() },
    { label: ctrans("On target"), value: props.totals.with_target ? `${props.totals.on_target} / ${props.totals.with_target}` : "—" },
    { label: ctrans("Preparation"), value: clockHours(props.totals.preparation_hours) },
    { label: ctrans("Cleaning"), value: clockHours(props.totals.cleaning_hours) },
    { label: ctrans("Maintenance"), value: clockHours(props.totals.maintenance_hours) },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <SegmentedToggle :modelValue="periodKey" :options="periodOptions" :aria-label="ctrans('Period')" @update:modelValue="choosePeriod" />
            <template v-if="periodKey === 'custom'">
                <DatePicker v-model="fromDate" :maxDate="toDate ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('From')" />
                <DatePicker v-model="toDate" :minDate="fromDate ?? undefined" :maxDate="new Date()" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :aria-label="ctrans('To')" />
                <Button type="tertiary" :label="ctrans('Show')" @click="load('custom')">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'filter']" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div v-for="tile in tiles" :key="tile.label" class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="text-2xl font-semibold tabular-nums text-gray-800">{{ tile.value }}</div>
                <div class="text-xs text-gray-500">{{ tile.label }}</div>
            </div>
        </div>

        <table v-if="steps.length" class="mb-6 w-auto text-sm tabular-nums">
            <thead class="whitespace-nowrap">
                <tr class="border-b border-gray-200 text-xs text-gray-500">
                    <th class="py-2 pr-6 text-left font-medium">{{ ctrans('Step') }}</th>
                    <th class="py-2 px-3 text-right font-medium">{{ ctrans('Tasks') }}</th>
                    <th class="py-2 px-3 text-right font-medium">{{ ctrans('Time (h:mm)') }}</th>
                    <th class="py-2 px-3 text-right font-medium">{{ ctrans('Units') }}</th>
                    <th class="py-2 px-3 text-right font-medium">{{ ctrans('Units/h') }}</th>
                    <th class="py-2 pl-3 text-right font-medium">{{ ctrans('Target/h') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="step in steps" :key="step.task_name" class="border-b border-gray-100 text-gray-700">
                    <td class="py-1.5 pr-6">{{ step.task_name }}</td>
                    <td class="py-1.5 px-3 text-right">{{ step.tasks }}</td>
                    <td class="py-1.5 px-3 text-right">{{ clockHours(step.hours) }}</td>
                    <td class="py-1.5 px-3 text-right">{{ step.quantity_made }}</td>
                    <td class="py-1.5 px-3 text-right" :class="step.standard_rate !== null && step.units_per_hour !== null && step.units_per_hour < step.standard_rate ? 'text-red-600' : ''">{{ step.units_per_hour?.toFixed(1) ?? '—' }}</td>
                    <td class="py-1.5 pl-3 text-right">{{ step.standard_rate?.toFixed(1) ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <Table :resource="data">
        <template #cell(ended_at)="{ item }">
            <span class="whitespace-nowrap">{{ finishedAt(item.ended_at) }}</span>
        </template>
        <template #cell(task_name)="{ item }">
            <span :class="item.is_non_productive ? 'text-gray-500' : ''">{{ item.task_name }}</span>
            <span v-if="item.reason" class="text-xs text-gray-400"> · {{ item.reason }}</span>
        </template>
        <template #cell(artefact_code)="{ item }">
            <span class="whitespace-nowrap">{{ item.artefact_code ?? '—' }}</span>
        </template>
        <template #cell(job_order_reference)="{ item }">
            <span class="whitespace-nowrap">{{ item.job_order_reference ?? '—' }}</span>
        </template>
        <template #cell(hours)="{ item }">{{ clockHours(item.hours) }}</template>
        <template #cell(quantity_made)="{ item }">{{ item.quantity_made ?? '—' }}</template>
        <template #cell(quantity_rejected)="{ item }">{{ item.quantity_rejected || '—' }}</template>
        <template #cell(units_per_hour)="{ item }">
            <span :class="item.is_under_target ? 'text-red-600' : ''">{{ item.units_per_hour?.toFixed(1) ?? '—' }}</span>
        </template>
        <template #cell(standard_rate)="{ item }">{{ item.standard_rate?.toFixed(1) ?? '—' }}</template>
    </Table>
</template>
