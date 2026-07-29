<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Chart from "primevue/chart"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot, faUsers, faDatabase, faTools, faCheck, faTimes } from "@fal"

library.add(faRobot, faUsers, faDatabase, faTools, faCheck, faTimes)

const props = defineProps<{
    pageHead: any
    title: string
    insights: {
        days: number
        calls: number
        errors: number
        error_rate: number
        users: number
        avg_ms: number
        sql_calls: number
        sql_users: number
        tool_calls: number
        daily: { date: string; tool_calls: number; sql_calls: number; errors: number }[]
        top_tools: { tool: string; calls: number; errors: number; avg_ms: number }[]
        top_users: { username: string; sql_access: boolean; calls: number; errors: number; last_used_at: string }[]
    }
    data: any
    users: any
}>()

const activeTab = ref<'queries' | 'users'>('queries')

const tabs = [
    { key: 'queries', label: 'Queries', icon: 'fal fa-robot' },
    { key: 'users', label: 'By user', icon: 'fal fa-users' },
] as const

const dailyChart = computed(() => ({
    labels: props.insights.daily.map(day => day.date.slice(5)),
    datasets: [
        {
            label: 'Tool queries',
            data: props.insights.daily.map(day => day.tool_calls),
            backgroundColor: '#6366f1',
            stack: 'calls',
        },
        {
            label: 'SQL queries',
            data: props.insights.daily.map(day => day.sql_calls),
            backgroundColor: '#a5b4fc',
            stack: 'calls',
        },
        {
            label: 'Errors',
            data: props.insights.daily.map(day => day.errors),
            backgroundColor: '#ef4444',
            stack: 'errors',
        },
    ],
}))

const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
    scales: {
        x: { stacked: true, grid: { display: false } },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
    },
}

const toolsChart = computed(() => ({
    labels: props.insights.top_tools.map(tool => tool.tool),
    datasets: [
        {
            label: 'Ok',
            data: props.insights.top_tools.map(tool => tool.calls - tool.errors),
            backgroundColor: '#6366f1',
        },
        {
            label: 'Errors',
            data: props.insights.top_tools.map(tool => tool.errors),
            backgroundColor: '#ef4444',
        },
    ],
}))

const toolsChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
    scales: {
        x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
        y: { stacked: true, grid: { display: false } },
    },
}

const formatArguments = (args: Record<string, unknown> | null) => {
    if (!args || !Object.keys(args).length) return '-'
    return Object.entries(args)
        .map(([key, value]) => `${key}: ${typeof value === 'string' ? value : JSON.stringify(value)}`)
        .join('  ')
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 grid gap-4 lg:grid-cols-2">
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300 lg:col-span-2">
            <div class="flex items-baseline justify-between mb-3">
                <h3 class="text-lg font-semibold">
                    {{ ctrans("AI usage") }}
                    <span class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(insights.days) }) }}</span>
                </h3>
            </div>
            <div class="flex flex-wrap gap-x-10 gap-y-4">
                <div>
                    <p class="text-4xl font-bold">{{ insights.calls.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Queries") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold" :class="insights.error_rate > 10 ? 'text-red-500' : ''">{{ insights.error_rate }}%</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Errors") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.users }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Active users") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.avg_ms.toLocaleString() }}<span class="text-lg text-gray-400">ms</span></p>
                    <p class="text-sm text-gray-600">{{ ctrans("Avg response") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">
                        {{ insights.tool_calls.toLocaleString() }}<span class="text-lg text-gray-400"> / {{ insights.sql_calls.toLocaleString() }}</span>
                    </p>
                    <p class="text-sm text-gray-600">
                        <FontAwesomeIcon icon="fal fa-tools" aria-hidden="true" /> {{ ctrans("Tools") }}
                        / <FontAwesomeIcon icon="fal fa-database" aria-hidden="true" /> {{ ctrans("SQL") }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <p class="text-xs text-gray-400 font-medium mb-2">{{ ctrans("Queries per day") }}</p>
            <div class="h-64">
                <Chart type="bar" :data="dailyChart" :options="dailyChartOptions" class="h-full" />
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <p class="text-xs text-gray-400 font-medium mb-2">{{ ctrans("Most used tools") }}</p>
            <div class="h-64">
                <Chart type="bar" :data="toolsChart" :options="toolsChartOptions" class="h-full" />
            </div>
        </div>
    </div>

    <div class="px-4 flex gap-2">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="px-4 py-2 rounded-md text-sm font-medium transition"
            :class="activeTab === tab.key ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = tab.key"
        >
            <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
            {{ ctrans(tab.label) }}
        </button>
    </div>

    <Table v-show="activeTab === 'queries'" :resource="data" class="mt-2">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>

        <template #cell(username)="{ item }">
            <span class="font-medium">{{ item.username ?? '-' }}</span>
            <FontAwesomeIcon v-if="item.sql_access" icon="fal fa-database" class="text-indigo-400 ml-1" v-tooltip="ctrans('SQL access')" fixed-width aria-hidden="true" />
        </template>

        <template #cell(tool)="{ item }">
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ item.tool }}</span>
        </template>

        <template #cell(arguments)="{ item }">
            <span class="text-gray-500 text-xs font-mono break-all">{{ formatArguments(item.arguments) }}</span>
        </template>

        <template #cell(is_error)="{ item }">
            <FontAwesomeIcon v-if="item.is_error" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
        </template>

        <template #cell(duration_ms)="{ item }">
            <span class="tabular-nums text-gray-500">{{ item.duration_ms != null ? `${item.duration_ms.toLocaleString()}ms` : '-' }}</span>
        </template>
    </Table>

    <Table v-show="activeTab === 'users'" :resource="users" name="users" class="mt-2">
        <template #cell(username)="{ item }">
            <Link
                :href="`${route('grp.sysadmin.mcp.index')}?filter[global]=${encodeURIComponent(item.username)}`"
                class="font-medium text-indigo-600 hover:underline"
                v-tooltip="ctrans('See queries by this user')"
            >
                {{ item.username }}
            </Link>
        </template>

        <template #cell(sql_access)="{ item }">
            <span v-if="item.sql_access" class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">
                <FontAwesomeIcon icon="fal fa-database" aria-hidden="true" /> {{ ctrans("SQL") }}
            </span>
            <span v-else class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                <FontAwesomeIcon icon="fal fa-tools" aria-hidden="true" /> {{ ctrans("Tools") }}
            </span>
        </template>

        <template #cell(calls)="{ item }">
            <span class="tabular-nums font-medium">{{ item.calls.toLocaleString() }}</span>
        </template>

        <template #cell(errors)="{ item }">
            <span :class="item.errors > 0 ? 'text-red-500' : 'text-gray-400'" class="tabular-nums">{{ item.errors }}</span>
        </template>

        <template #cell(tools_used)="{ item }">
            <span class="tabular-nums">{{ item.tools_used }}</span>
        </template>

        <template #cell(avg_ms)="{ item }">
            <span class="tabular-nums text-gray-500">{{ item.avg_ms != null ? `${item.avg_ms.toLocaleString()}ms` : '-' }}</span>
        </template>

        <template #cell(last_used_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.last_used_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>
    </Table>
</template>
