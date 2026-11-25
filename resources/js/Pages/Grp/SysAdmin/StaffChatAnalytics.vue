<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Chart from "primevue/chart"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faUsers,
    faCommentsAlt,
    faCommentAltLines,
    faComments,
    faClipboardList,
    faImage,
    faLanguage,
    faSmile,
    faEnvelope,
    faCheckCircle,
    faCalendarAlt,
    faChartBar,
} from "@fal"

type Tone = "indigo" | "sky" | "violet" | "emerald" | "pink" | "teal" | "yellow" | "amber" | "muted"
type TabKey = "users" | "conversations"

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
    index_route: { name: string; parameters: Record<string, string> }
    show_route?: { name: string; parameters: Record<string, string> } | null
}>()

const SERIES_DIRECT = "#6366f1"
const SERIES_CONTEXT = "#34d399"
const DAY_IN_MS = 86400000

const toneClasses: Record<Tone, { icon: string; value: string }> = {
    indigo: { icon: "bg-indigo-50 text-indigo-600", value: "text-indigo-600" },
    sky: { icon: "bg-sky-50 text-sky-600", value: "text-sky-600" },
    violet: { icon: "bg-violet-50 text-violet-600", value: "text-violet-600" },
    emerald: { icon: "bg-emerald-50 text-emerald-600", value: "text-emerald-600" },
    pink: { icon: "bg-pink-50 text-pink-600", value: "text-pink-600" },
    teal: { icon: "bg-teal-50 text-teal-600", value: "text-teal-600" },
    yellow: { icon: "bg-yellow-50 text-yellow-600", value: "text-yellow-600" },
    amber: { icon: "bg-amber-100 text-amber-700", value: "text-amber-600" },
    muted: { icon: "bg-gray-100 text-gray-400", value: "text-gray-400" },
}

const indexUrl = () => route(props.index_route.name, props.index_route.parameters)
const showUrl = (ulid: string) => props.show_route ? route(props.show_route.name, { ...props.show_route.parameters, staffConversation: ulid }) : null

const queryTargetsConversations = (): boolean => {
    if (typeof window === "undefined") {
        return false
    }

    return Array.from(new URLSearchParams(window.location.search).keys()).some(key => key.startsWith("conversations"))
}

const activeTab = ref<TabKey>(queryTargetsConversations() ? "conversations" : "users")

const tabOptions = computed<{ label: string; value: TabKey; icon: any }[]>(() => [
    { label: ctrans("By user"), value: "users", icon: faUsers },
    { label: ctrans("Conversations"), value: "conversations", icon: faCommentsAlt },
])

const tiles = computed(() => {
    const insights = props.insights
    const counted: { key: string; label: string; value: number; icon: any; tone: Tone }[] = [
        { key: "messages", label: ctrans("Messages"), value: insights.messages, icon: faCommentAltLines, tone: "indigo" },
        { key: "users", label: ctrans("People chatting"), value: insights.users, icon: faUsers, tone: "sky" },
        { key: "conversations", label: ctrans("Conversations"), value: insights.conversations, icon: faComments, tone: "violet" },
        { key: "context_messages", label: ctrans("On orders / delivery notes"), value: insights.context_messages, icon: faClipboardList, tone: "emerald" },
        { key: "media_messages", label: ctrans("Images"), value: insights.media_messages, icon: faImage, tone: "pink" },
        { key: "translated", label: ctrans("Translated"), value: insights.translated, icon: faLanguage, tone: "teal" },
        { key: "reactions", label: ctrans("Reactions"), value: insights.reactions, icon: faSmile, tone: "yellow" },
    ]

    const unread = insights.unread_conversations

    return [
        ...counted.map(tile => ({
            ...tile,
            iconClass: toneClasses[tile.value ? tile.tone : "muted"].icon,
            valueClass: toneClasses[tile.value ? tile.tone : "muted"].value,
            tileClass: "border-gray-200 bg-white",
        })),
        {
            key: "unread_conversations",
            label: ctrans("Unread conversations"),
            value: unread,
            icon: unread ? faEnvelope : faCheckCircle,
            iconClass: unread ? toneClasses.amber.icon : toneClasses.emerald.icon,
            valueClass: unread ? toneClasses.amber.value : toneClasses.muted.value,
            tileClass: unread ? "border-amber-200 bg-amber-50" : "border-gray-200 bg-white",
        },
    ]
})

const periodRange = computed(() => {
    const until = new Date()
    const since = new Date(until.getTime() - props.insights.days * DAY_IN_MS)

    return `${useFormatTime(since, { formatTime: "mdy" })} – ${useFormatTime(until, { formatTime: "mdy" })}`
})

const dayKey = (date: Date): string => date.toISOString().slice(0, 10)

const dailySeries = computed(() => {
    const byDate = new Map(props.insights.daily.map(day => [day.date, day]))
    const recordedDates = props.insights.daily.map(day => day.date).sort()
    const today = dayKey(new Date())
    const windowStart = dayKey(new Date(Date.now() - props.insights.days * DAY_IN_MS))
    const firstDate = recordedDates.length && recordedDates[0] < windowStart ? recordedDates[0] : windowStart
    const lastDate = recordedDates.length && recordedDates[recordedDates.length - 1] > today ? recordedDates[recordedDates.length - 1] : today

    const days: { date: string; dm_messages: number; context_messages: number }[] = []
    for (let time = Date.parse(`${firstDate}T00:00:00Z`); time <= Date.parse(`${lastDate}T00:00:00Z`); time += DAY_IN_MS) {
        const date = dayKey(new Date(time))
        const recorded = byDate.get(date)
        days.push({
            date,
            dm_messages: Number(recorded?.dm_messages ?? 0),
            context_messages: Number(recorded?.context_messages ?? 0),
        })
    }

    return days
})

const hasDailyMessages = computed(() => props.insights.daily.some(day => Number(day.dm_messages) + Number(day.context_messages) > 0))
const hasHourlyMessages = computed(() => props.insights.hourly.some(messages => messages > 0))

const dailyChart = computed(() => ({
    labels: dailySeries.value.map(day => day.date.slice(5)),
    datasets: [
        { label: ctrans("Direct messages"), data: dailySeries.value.map(day => day.dm_messages), backgroundColor: SERIES_DIRECT, borderRadius: 2, stack: "messages" },
        { label: ctrans("On orders / delivery notes"), data: dailySeries.value.map(day => day.context_messages), backgroundColor: SERIES_CONTEXT, borderRadius: 2, stack: "messages" },
    ],
}))

const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { stacked: true, grid: { display: false } },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, suggestedMax: 4 },
    },
}

const hourlyChart = computed(() => ({
    labels: props.insights.hourly.map((_, hour) => `${String(hour).padStart(2, "0")}h`),
    datasets: [{ label: ctrans("Messages"), data: props.insights.hourly, backgroundColor: SERIES_DIRECT, borderRadius: 2 }],
}))

const hourlyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 }, suggestedMax: 4 },
    },
}

const recencyOf = (timestamp?: string | null): { dot: string; text: string; tooltip: string } => {
    const quiet = { dot: "bg-gray-300", text: "text-gray-400", tooltip: ctrans("Quiet for more than 7 days") }
    if (!timestamp) {
        return quiet
    }

    const hasTimezone = /([zZ]|[+-]\d{2}:?\d{2})$/.test(timestamp)
    const time = Date.parse(hasTimezone ? timestamp : `${timestamp.replace(" ", "T")}Z`)
    if (Number.isNaN(time)) {
        return quiet
    }

    const age = Date.now() - time
    if (age <= DAY_IN_MS) {
        return { dot: "bg-emerald-500", text: "text-emerald-700", tooltip: ctrans("Active in the last 24 hours") }
    }
    if (age <= 7 * DAY_IN_MS) {
        return { dot: "bg-gray-400", text: "text-gray-600", tooltip: ctrans("Active in the last 7 days") }
    }

    return quiet
}

const typeClass = (type: string): string => type === "dm" ? "bg-indigo-50 text-indigo-700" : "bg-violet-50 text-violet-700"
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 grid gap-4 lg:grid-cols-2">
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300 lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2">
                <h3 class="text-lg font-semibold">{{ ctrans("Verba usage") }}</h3>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                    <FontAwesomeIcon :icon="faCalendarAlt" fixed-width aria-hidden="true" class="text-gray-400" />
                    <span class="first-letter:uppercase">{{ ctrans("last :days days", { days: String(insights.days) }) }}</span>
                    <span class="text-gray-300" aria-hidden="true">|</span>
                    <span class="font-normal tabular-nums">{{ periodRange }}</span>
                </span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 2xl:grid-cols-8">
                <div v-for="tile in tiles" :key="tile.key" class="flex flex-col gap-2 rounded-lg border p-3" :class="tile.tileClass">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md" :class="tile.iconClass">
                        <FontAwesomeIcon :icon="tile.icon" fixed-width aria-hidden="true" />
                    </span>
                    <p class="text-3xl font-bold leading-none tabular-nums" :class="tile.valueClass">{{ tile.value.toLocaleString() }}</p>
                    <p class="text-sm leading-tight text-gray-600">{{ tile.label }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <p class="text-xs text-gray-400 font-medium">{{ ctrans("Messages per day") }}</p>
                <div v-if="hasDailyMessages" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: SERIES_DIRECT }" />
                        {{ ctrans("Direct messages") }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: SERIES_CONTEXT }" />
                        {{ ctrans("On orders / delivery notes") }}
                    </span>
                </div>
            </div>
            <div class="h-64">
                <Chart v-if="hasDailyMessages" type="bar" :data="dailyChart" :options="dailyChartOptions" class="h-full" />
                <div v-else class="flex h-full flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-200 text-gray-400">
                    <FontAwesomeIcon :icon="faChartBar" class="text-2xl" aria-hidden="true" />
                    <p class="text-sm">{{ ctrans("No messages in this period") }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <p class="text-xs text-gray-400 font-medium">{{ ctrans("Messages by hour of day") }}</p>
                <span v-if="hasHourlyMessages" class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                    <span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: SERIES_DIRECT }" />
                    {{ ctrans("Messages") }}
                </span>
            </div>
            <div class="h-64">
                <Chart v-if="hasHourlyMessages" type="bar" :data="hourlyChart" :options="hourlyChartOptions" class="h-full" />
                <div v-else class="flex h-full flex-col items-center justify-center gap-2 rounded-md border border-dashed border-gray-200 text-gray-400">
                    <FontAwesomeIcon :icon="faChartBar" class="text-2xl" aria-hidden="true" />
                    <p class="text-sm">{{ ctrans("No messages in this period") }}</p>
                </div>
            </div>
        </div>

        <div v-if="insights.by_context.length" class="bg-white rounded-lg p-4 shadow-sm border border-gray-300 lg:col-span-2">
            <p class="text-xs text-gray-400 font-medium mb-2">{{ ctrans("Context conversations") }}</p>
            <div class="flex flex-wrap gap-2 text-sm">
                <span v-for="row in insights.by_context" :key="row.context" class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-0.5 text-emerald-700">
                    {{ row.context }}
                    <b class="tabular-nums">{{ row.conversations }}</b>
                </span>
            </div>
        </div>
    </div>

    <div class="px-4">
        <SegmentedToggle v-model="activeTab" :options="tabOptions" :aria-label="ctrans('Verba table')" />
    </div>

    <Table v-show="activeTab === 'users'" :resource="users" class="mt-2">
        <template #cell(username)="{ item }">
            <Link
                :href="`${indexUrl()}?conversations_filter[global]=${encodeURIComponent(item.username)}`"
                class="font-medium text-[--app-accent] hover:text-[--app-accent-strong] hover:underline"
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
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap" :class="recencyOf(item.last_message_at).text" v-tooltip="recencyOf(item.last_message_at).tooltip">
                <span class="h-2 w-2 shrink-0 rounded-full" :class="recencyOf(item.last_message_at).dot" />
                {{ item.last_message_at ? useFormatTime(item.last_message_at, { formatTime: 'hms', keepTimezone: true }) : '-' }}
            </span>
        </template>
    </Table>

    <Table v-show="activeTab === 'conversations'" :resource="conversations" name="conversations" class="mt-2">
        <template #cell(members)="{ item }">
            <Link v-if="showUrl(item.ulid)" :href="showUrl(item.ulid)!" class="font-medium text-[--app-accent] hover:text-[--app-accent-strong] hover:underline">{{ item.members ?? '-' }}</Link>
            <span v-else class="font-medium">{{ item.members ?? '-' }}</span>
        </template>
        <template #cell(type)="{ item }">
            <span class="text-xs px-2 py-0.5 rounded-full" :class="typeClass(item.type)">{{ item.type }}</span>
        </template>
        <template #cell(context)="{ item }">
            <span v-if="item.context" class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">{{ item.context }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(messages)="{ item }">
            <span class="tabular-nums font-medium">{{ item.messages.toLocaleString() }}</span>
        </template>
        <template #cell(last_message_at)="{ item }">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap" :class="recencyOf(item.last_message_at).text" v-tooltip="recencyOf(item.last_message_at).tooltip">
                <span class="h-2 w-2 shrink-0 rounded-full" :class="recencyOf(item.last_message_at).dot" />
                {{ item.last_message_at ? useFormatTime(item.last_message_at, { formatTime: 'hms', keepTimezone: true }) : '-' }}
            </span>
        </template>
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>
    </Table>
</template>
