<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
import { faUsers, faCommentsAlt } from "@fal"

library.add(faUsers, faCommentsAlt)

const props = defineProps<{
    pageHead: any
    title: string
    insights: {
        days: number
        messages: number
        users: number
        conversations: number
        media_messages: number
        context_messages: number
        replies: number
        translated: number
        reactions: number
        unread_conversations: number
        daily: { date: string; dm_messages: number; context_messages: number; users: number }[]
        hourly: number[]
        top_users: { username: string; messages: number; conversations: number; last_message_at: string }[]
        top_pairs: { conversation_id: number; members: string; messages: number; last_message_at: string }[]
        by_context: { context: string; conversations: number }[]
    }
    users: any
    conversations: any
}>()

const activeTab = ref<'users' | 'conversations'>('users')

const tabs = [
    { key: 'users', label: 'By user', icon: 'fal fa-users' },
    { key: 'conversations', label: 'Conversations', icon: 'fal fa-comments-alt' },
] as const

const dailyChart = computed(() => ({
    labels: props.insights.daily.map(day => day.date.slice(5)),
    datasets: [
        { label: 'Direct messages', data: props.insights.daily.map(day => day.dm_messages), backgroundColor: '#6366f1', stack: 'messages' },
        { label: 'On orders / delivery notes', data: props.insights.daily.map(day => day.context_messages), backgroundColor: '#34d399', stack: 'messages' },
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

const hourlyChart = computed(() => ({
    labels: props.insights.hourly.map((_, hour) => `${String(hour).padStart(2, '0')}h`),
    datasets: [{ label: 'Messages', data: props.insights.hourly, backgroundColor: '#a5b4fc' }],
}))

const hourlyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 grid gap-4 lg:grid-cols-2">
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300 lg:col-span-2">
            <h3 class="text-lg font-semibold mb-3">
                {{ ctrans("Staff chat usage") }}
                <span class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(insights.days) }) }}</span>
            </h3>
            <div class="flex flex-wrap gap-x-10 gap-y-4">
                <div>
                    <p class="text-4xl font-bold">{{ insights.messages.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Messages") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.users }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("People chatting") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.conversations }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Conversations") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.context_messages.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("On orders / delivery notes") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.media_messages }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Images") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.translated }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Translated") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.reactions }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Reactions") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold" :class="insights.unread_conversations ? 'text-amber-600' : ''">{{ insights.unread_conversations }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Unread conversations") }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <p class="text-xs text-gray-400 font-medium mb-2">{{ ctrans("Messages per day") }}</p>
            <div class="h-64">
                <Chart type="bar" :data="dailyChart" :options="dailyChartOptions" class="h-full" />
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <p class="text-xs text-gray-400 font-medium mb-2">{{ ctrans("Messages by hour of day") }}</p>
            <div class="h-64">
                <Chart type="bar" :data="hourlyChart" :options="hourlyChartOptions" class="h-full" />
            </div>
        </div>

        <div v-if="insights.by_context.length" class="bg-white rounded-lg p-4 shadow-sm border border-gray-300 lg:col-span-2">
            <p class="text-xs text-gray-400 font-medium mb-2">{{ ctrans("Context conversations") }}</p>
            <div class="flex flex-wrap gap-6 text-sm">
                <span v-for="row in insights.by_context" :key="row.context">
                    {{ row.context }}: <b class="tabular-nums">{{ row.conversations }}</b>
                </span>
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

    <Table v-show="activeTab === 'users'" :resource="users" class="mt-2">
        <template #cell(username)="{ item }">
            <Link
                :href="`${route('grp.sysadmin.staff_chat.index')}?conversations_filter[global]=${encodeURIComponent(item.username)}`"
                class="font-medium text-indigo-600 hover:underline"
                v-tooltip="ctrans('See conversations of this user')"
                @click="activeTab = 'conversations'"
            >
                {{ item.username }}
            </Link>
        </template>
        <template #cell(messages)="{ item }">
            <span class="tabular-nums font-medium">{{ item.messages.toLocaleString() }}</span>
        </template>
        <template #cell(last_message_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.last_message_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>
    </Table>

    <Table v-show="activeTab === 'conversations'" :resource="conversations" name="conversations" class="mt-2">
        <template #cell(members)="{ item }">
            <span class="font-medium">{{ item.members ?? '-' }}</span>
        </template>
        <template #cell(type)="{ item }">
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ item.type }}</span>
        </template>
        <template #cell(context)="{ item }">
            <span v-if="item.context" class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">{{ item.context }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(messages)="{ item }">
            <span class="tabular-nums font-medium">{{ item.messages.toLocaleString() }}</span>
        </template>
        <template #cell(last_message_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ item.last_message_at ? useFormatTime(item.last_message_at, { formatTime: 'hms', keepTimezone: true }) : '-' }}</span>
        </template>
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>
    </Table>
</template>
