<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Chart from "primevue/chart"
import { Select } from "primevue"
import { useLiveTickets } from "@/Composables/useLiveTickets"

const props = defineProps<{
    pageHead: any
    title: string
    periods: number[]
    stats: {
        days: number
        from: string
        created: number
        done: number
        open: number
        median_hours: number | null
        oldest_open: { reference: string; age_days: number } | null
        csat: number | null
        csat_by_month: { month: string; average: number | null; total: number }[]
        daily: { date: string; created: number; done: number }[]
        by_status: { status: string; label: string; color: string; total: number }[]
        assignees: { name: string; username: string; short_name: string; avatar: any; open: number; done: number; median_hours: number | null }[]
    }
}>()

useLiveTickets(["stats"])

const STATUS_COLORS: Record<string, string> = { blue: "#3b82f6", amber: "#f59e0b", gray: "#9ca3af", green: "#22c55e" }

const periodOptions = computed(() => props.periods.map((days) => ({ label: trans("Past :days days", { days: String(days) }), value: days })))

const changePeriod = (days: number) => router.get(route("grp.tickets.reports"), { days }, { preserveState: true, replace: true })

const lineChart = computed(() => ({
    labels: props.stats.daily.map((day) => day.date.slice(5)),
    datasets: [
        { label: trans("Created"), data: props.stats.daily.map((day) => day.created), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0.2 },
        { label: trans("Done"), data: props.stats.daily.map((day) => day.done), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0.2 },
    ],
}))

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
}

const donutChart = computed(() => ({
    labels: props.stats.by_status.map((row) => row.label),
    datasets: [{ data: props.stats.by_status.map((row) => row.total), backgroundColor: props.stats.by_status.map((row) => STATUS_COLORS[row.color] ?? "#9ca3af") }],
}))

const donutOptions = { responsive: true, maintainAspectRatio: false, cutout: "70%", plugins: { legend: { display: false } } }

const csatChart = computed(() => ({
    labels: props.stats.csat_by_month.map((row) => row.month.slice(2)),
    datasets: [{ label: trans("Average rating"), data: props.stats.csat_by_month.map((row) => row.average), backgroundColor: "#3b82f6", borderRadius: 4 }],
}))

const csatOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } } },
}

const totalTickets = computed(() => props.stats.by_status.reduce((sum, row) => sum + row.total, 0))

const hours = (value: number | null) => (value === null ? "-" : value >= 48 ? `${(value / 24).toFixed(1)} ${trans("days")}` : `${value} ${trans("h")}`)

const OPEN_STATUSES = computed(() => props.stats.by_status.filter((s) => !["resolved", "cancelled"].includes(s.status)).map((s) => s.status).join(","))

const listUrl = (params: Record<string, any>) => route("grp.tickets.list", params)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-500">{{ trans("Show") }}</span>
            <Select :model-value="stats.days" :options="periodOptions" option-label="label" option-value="value" class="w-44" @update:model-value="changePeriod" />
        </div>

        <div class="flex flex-wrap gap-x-10 gap-y-4">
            <div>
                <p class="text-4xl font-bold text-pink-600"><Link :href="listUrl({ filter: { created_since: stats.from } })" class="primaryLink">{{ stats.created }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Created") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold text-green-700"><Link :href="listUrl({ filter: { resolved_since: stats.from } })" class="primaryLink">{{ stats.done }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Done") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold"><Link :href="listUrl({ elements: { status: OPEN_STATUSES } })" class="primaryLink">{{ stats.open }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Open now") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold"><Link :href="listUrl({ filter: { resolved_since: stats.from } })" class="primaryLink">{{ hours(stats.median_hours) }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Median time to resolve") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold">
                    <Link v-if="stats.csat" :href="listUrl({ filter: { rated_since: stats.from } })" class="primaryLink">{{ stats.csat }}</Link>
                    <span v-else>-</span>
                    <span v-if="stats.csat" class="text-lg text-gray-400">/5</span>
                </p>
                <p class="text-sm text-gray-600">{{ trans("Customer satisfaction") }}</p>
            </div>
            <div v-if="stats.oldest_open">
                <p class="text-4xl font-bold">
                    <Link :href="route('grp.tickets.show', stats.oldest_open.reference)" class="primaryLink">{{ stats.oldest_open.age_days }}</Link>
                    <span class="text-lg text-gray-400"> {{ trans("days") }}</span>
                </p>
                <p class="text-sm text-gray-600">
                    {{ trans("Oldest open") }} · <Link :href="route('grp.tickets.show', stats.oldest_open.reference)" class="primaryLink">{{ stats.oldest_open.reference }}</Link>
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300 lg:col-span-2">
                <h3 class="text-lg font-semibold mb-2">{{ trans("Created vs Done") }}</h3>
                <div class="h-72">
                    <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
                </div>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
                <h3 class="text-lg font-semibold">{{ trans("Status overview") }}</h3>
                <p class="text-xs text-gray-500 mb-2">{{ trans("All tickets") }} · <Link :href="route('grp.tickets.list')" class="primaryLink">{{ trans("View all") }}</Link></p>
                <div class="flex items-center gap-4">
                    <div class="relative h-40 w-40 shrink-0">
                        <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="h-full" />
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-2xl font-bold">{{ totalTickets }}</span>
                            <span class="text-[10px] text-gray-500">{{ trans("Total") }}</span>
                        </div>
                    </div>
                    <ul class="space-y-1.5 text-sm">
                        <li v-for="row in stats.by_status" :key="row.status" class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-sm" :style="{ backgroundColor: STATUS_COLORS[row.color] ?? '#9ca3af' }" />
                            {{ row.label }}:
                            <Link v-if="row.total" :href="listUrl({ elements: { status: row.status } })" class="primaryLink font-medium">{{ row.total }}</Link>
                            <span v-else class="font-medium">{{ row.total }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <h3 class="text-lg font-semibold">{{ trans("Customer satisfaction") }}</h3>
            <p class="text-xs text-gray-500 mb-2">{{ trans("Average rating per month, last 12 months") }}</p>
            <div class="h-56">
                <Chart type="bar" :data="csatChart" :options="csatOptions" class="h-full" />
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-xs text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">{{ trans("Assignee") }}</th>
                        <th class="px-4 py-2 text-right">{{ trans("Open") }}</th>
                        <th class="px-4 py-2 text-right">{{ trans("Done") }}</th>
                        <th class="px-4 py-2 text-right">{{ trans("Median time to resolve") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in stats.assignees" :key="row.username" class="border-t border-gray-100">
                        <td class="px-4 py-2 font-medium">
                            <span class="inline-flex items-center gap-2" v-tooltip="{ content: row.name, delay: 0 }">
                                <img v-if="row.avatar?.original" :src="row.avatar.original" class="h-6 w-6 rounded-full object-cover" />
                                <span v-else class="h-6 w-6 rounded-full bg-gray-300 inline-block" />
                                {{ row.short_name }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.open" :href="listUrl({ filter: { assignee: row.username }, elements: { status: OPEN_STATUSES } })" class="primaryLink">{{ row.open }}</Link>
                            <span v-else>{{ row.open }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.done" :href="listUrl({ filter: { assignee: row.username, resolved_since: stats.from } })" class="primaryLink">{{ row.done }}</Link>
                            <span v-else>{{ row.done }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ hours(row.median_hours) }}</td>
                    </tr>
                    <tr v-if="!stats.assignees.length">
                        <td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ trans("No assigned tickets yet") }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
