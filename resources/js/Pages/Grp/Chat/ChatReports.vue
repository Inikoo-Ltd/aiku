<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Chart from "primevue/chart"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faComments,
	faEnvelope,
	faStopwatch,
	faStar,
	faChartLine,
	faChartPie,
	faUserHeadset,
	faStore,
	faReply,
	faTags,
	faFilter,
} from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"

library.add(
	faComments,
	faEnvelope,
	faStopwatch,
	faStar,
	faChartLine,
	faChartPie,
	faUserHeadset,
	faStore,
	faReply,
	faTags,
	faFilter,
	faWhatsapp
)

type ChannelRow = {
	channel: "website" | "email" | "whatsapp"
	label: string
	conversations: number
	answered: number
	unanswered: number
	open: number
	median_reply_minutes: number | null
}

type AgentRow = {
	name: string
	short_name: string
	username: string | null
	avatar: any
	conversations: number
	messages: number
	website: number
	email: number
	whatsapp: number
	median_reply_minutes: number | null
}

type ShopRow = {
	shop: string
	slug: string | null
	conversations: number
	answered: number
	unanswered: number
	website: number
	email: number
	whatsapp: number
	median_reply_minutes: number | null
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	intervals: Record<string, string>
	showShops: boolean
	stats: {
		interval: string
		from: string
		to: string
		days: number
		bucket: "day" | "week" | "month"
		conversations: number
		answered: number
		unanswered: number
		open: number
		median_reply_minutes: number | null
		slowest_reply_minutes: number | null
		csat: number | null
		csat_ratings: number
		csat_by_month: { month: string; average: number | null; total: number }[]
		daily: { date: string; started: number; answered: number; open: number }[]
		by_status: { status: string; label: string; color: string; total: number }[]
		by_channel: ChannelRow[]
		by_shop: ShopRow[]
		by_topic: TopicRow[]
		unclassified: number
		noise: { source: string; noise: number; genuine: number; reversed: number }[]
		agents: AgentRow[]
		agents_total: {
			name: string
			conversations: number
			messages: number
			website: number
			email: number
			whatsapp: number
			median_reply_minutes: number | null
		}
	}
}>()

const CHANNEL_ICONS: Record<string, string> = {
	website: "fal fa-comments",
	email: "fal fa-envelope",
	whatsapp: "fab fa-whatsapp",
}

const STATUS_COLORS: Record<string, string> = {
	active: "#16a34a",
	waiting: "#f59e0b",
	resolved: "#3b82f6",
	transferred: "#a855f7",
	closed: "#9ca3af",
}

const minutes = (value: number | null) => {
	if (value === null) return "-"
	if (value < 60) return `${Math.round(value)} ${ctrans("min")}`
	if (value < 60 * 48) return `${(value / 60).toFixed(1)} ${ctrans("h")}`
	return `${(value / 1440).toFixed(1)} ${ctrans("days")}`
}

const percent = (part: number, whole: number) =>
	whole ? `${((part / whole) * 100).toFixed(0)}%` : "-"

const bucketLabel = (date: string) => {
	const parsed = new Date(`${date}T00:00:00`)
	if (props.stats.bucket === "month") {
		return parsed.toLocaleDateString(undefined, { month: "short", year: "2-digit" })
	}
	return parsed.toLocaleDateString(undefined, { day: "numeric", month: "short" })
}

const lineChart = computed(() => {
	const pointRadius = props.stats.daily.length > 40 ? 0 : 2
	return {
		labels: props.stats.daily.map((row) => bucketLabel(row.date)),
		datasets: [
			{
				label: ctrans("Started"),
				data: props.stats.daily.map((row) => row.started),
				borderColor: "#c0399f",
				backgroundColor: "#c0399f",
				tension: 0,
				borderWidth: 1.5,
				pointRadius,
			},
			{
				label: ctrans("Answered"),
				data: props.stats.daily.map((row) => row.answered),
				borderColor: "#1f845a",
				backgroundColor: "#1f845a",
				tension: 0,
				borderWidth: 1.5,
				pointRadius,
			},
			{
				label: ctrans("Open"),
				data: props.stats.daily.map((row) => row.open),
				borderColor: "#f59e0b",
				backgroundColor: "#f59e0b",
				tension: 0,
				borderWidth: 1.5,
				pointRadius,
			},
		],
	}
})

const lineOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 12 } },
		tooltip: {
			callbacks: {
				title: (items: any[]) =>
					props.stats.bucket === "day"
						? items[0].label
						: `${ctrans(props.stats.bucket === "week" ? "Week of" : "Month")} ${items[0].label}`,
			},
		},
	},
	scales: {
		x: {
			grid: { display: false },
			ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 },
		},
		y: { beginAtZero: true, ticks: { precision: 0 } },
	},
}))

const donutChart = computed(() => ({
	labels: props.stats.by_status.map((row) => row.label),
	datasets: [
		{
			data: props.stats.by_status.map((row) => row.total),
			backgroundColor: props.stats.by_status.map(
				(row) => STATUS_COLORS[row.status] ?? "#9ca3af"
			),
		},
	],
}))

const donutOptions = {
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	hoverOffset: 8,
	plugins: {
		legend: { display: false },
		tooltip: {
			padding: 10,
			boxPadding: 6,
			callbacks: {
				label: (item: { raw: number }) =>
					`${item.raw} ${item.raw === 1 ? ctrans("conversation") : ctrans("conversations")}`,
			},
		},
	},
}

const csatChart = computed(() => ({
	labels: props.stats.csat_by_month.map((row) => row.month.slice(2)),
	datasets: [
		{
			label: ctrans("Average rating"),
			data: props.stats.csat_by_month.map((row) => row.average),
			backgroundColor: "#3b82f6",
			borderRadius: 4,
		},
	],
}))

const csatOptions = {
	responsive: true,
	maintainAspectRatio: false,
	plugins: { legend: { display: false } },
	scales: {
		x: { grid: { display: false } },
		y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } },
	},
}

type TableMode = "agents" | "shops" | "topics"

const sortStates = ref<Record<TableMode, { key: string; direction: 1 | -1 }>>({
	agents: { key: "", direction: -1 },
	shops: { key: "", direction: -1 },
	topics: { key: "", direction: -1 },
})

const sortArrow = (table: TableMode, key: string) =>
	sortStates.value[table].key === key
		? sortStates.value[table].direction === 1
			? " ▲"
			: " ▼"
		: ""

const toggleSort = (table: TableMode, key: string) => {
	const current = sortStates.value[table]
	sortStates.value[table] =
		current.key === key
			? { key, direction: current.direction === 1 ? -1 : 1 }
			: { key, direction: -1 }
}

const sortRows = <T extends Record<string, any>>(rows: T[], table: TableMode): T[] => {
	const { key, direction } = sortStates.value[table]
	if (!key) return rows
	return [...rows].sort((a, b) => {
		const left = a[key] ?? -Infinity
		const right = b[key] ?? -Infinity
		if (typeof left === "string" || typeof right === "string") {
			return String(left).localeCompare(String(right)) * direction
		}
		return (left - right) * direction
	})
}

const agentColumns = [
	{ key: "name", label: ctrans("Agent") },
	{ key: "conversations", label: ctrans("Conversations") },
	{ key: "messages", label: ctrans("Messages") },
	{ key: "website", label: ctrans("Website") },
	{ key: "email", label: ctrans("Email") },
	{ key: "whatsapp", label: ctrans("WhatsApp") },
	{ key: "median_reply_minutes", label: ctrans("Median reply") },
]

const shopColumns = [
	{ key: "shop", label: ctrans("Shop") },
	{ key: "conversations", label: ctrans("Conversations") },
	{ key: "answered", label: ctrans("Answered") },
	{ key: "unanswered", label: ctrans("Unanswered") },
	{ key: "website", label: ctrans("Website") },
	{ key: "email", label: ctrans("Email") },
	{ key: "whatsapp", label: ctrans("WhatsApp") },
	{ key: "median_reply_minutes", label: ctrans("Median reply") },
]

const sortedAgents = computed(() => sortRows(props.stats.agents, "agents"))
const topicColumns = [
	{ key: "label", label: ctrans("Topic") },
	{ key: "conversations", label: ctrans("Conversations") },
	{ key: "share", label: ctrans("Share") },
	{ key: "unanswered", label: ctrans("Unanswered") },
	{ key: "website", label: ctrans("Website") },
	{ key: "email", label: ctrans("Email") },
	{ key: "whatsapp", label: ctrans("WhatsApp") },
	{ key: "median_reply_minutes", label: ctrans("Median reply") },
]

const sortedTopics = computed(() => sortRows(props.stats.by_topic, "topics"))
const sortedShops = computed(() => sortRows(props.stats.by_shop, "shops"))
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="flex flex-col gap-4 p-4">
		<div class="flex flex-wrap items-center gap-3">
			<TicketsCreatedInterval
				:options="intervals"
				:selected="stats.interval"
				storage-key="chat-reports-interval" />
			<span class="text-xs text-gray-400">{{ stats.from }} → {{ stats.to }}</span>
		</div>

		<div class="flex flex-wrap gap-3">
			<div
				v-tooltip="ctrans('Conversations with a message from the visitor')"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
				<FontAwesomeIcon
					icon="fal fa-comments"
					class="text-violet-600"
					fixed-width
					aria-hidden="true" />{{ stats.conversations }}
			</div>
			<div
				v-tooltip="ctrans('Answered by an agent')"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
				<FontAwesomeIcon
					icon="fal fa-reply"
					class="text-emerald-600"
					fixed-width
					aria-hidden="true" />{{ stats.answered }}
				<span class="font-normal text-gray-400">{{
					percent(stats.answered, stats.conversations)
				}}</span>
			</div>
			<div
				v-tooltip="ctrans('Never answered')"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
				<FontAwesomeIcon
					icon="fal fa-user-headset"
					class="text-amber-600"
					fixed-width
					aria-hidden="true" />{{ stats.unanswered }}
			</div>
			<div
				v-tooltip="ctrans('Median first reply')"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
				<FontAwesomeIcon
					icon="fal fa-stopwatch"
					class="text-[--app-accent-strong]"
					fixed-width
					aria-hidden="true" />{{ minutes(stats.median_reply_minutes) }}
			</div>
			<div
				v-tooltip="
					`${ctrans('Customer satisfaction')} · ${stats.csat_ratings} ${ctrans('ratings')}`
				"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
				<FontAwesomeIcon
					icon="fal fa-star"
					class="text-sky-600"
					fixed-width
					aria-hidden="true" />{{ stats.csat ?? "-"
				}}<span class="font-normal text-gray-400">/5</span>
			</div>
		</div>

		<div class="grid gap-3 sm:grid-cols-3">
			<div
				v-for="channel in stats.by_channel"
				:key="channel.channel"
				class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<p class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon
						:icon="CHANNEL_ICONS[channel.channel]"
						class="text-[--app-accent-strong]"
						fixed-width
						aria-hidden="true" />
					{{ channel.label }}
				</p>
				<p class="mt-2 text-2xl font-bold tabular-nums">{{ channel.conversations }}</p>
				<dl class="mt-2 grid grid-cols-3 gap-2 text-xs text-gray-500 tabular-nums">
					<div>
						<dt>{{ ctrans("Answered") }}</dt>
						<dd class="font-semibold text-gray-700">
							{{ percent(channel.answered, channel.conversations) }}
						</dd>
					</div>
					<div>
						<dt>{{ ctrans("Open") }}</dt>
						<dd class="font-semibold text-gray-700">{{ channel.open }}</dd>
					</div>
					<div>
						<dt>{{ ctrans("Median reply") }}</dt>
						<dd class="font-semibold text-gray-700">
							{{ minutes(channel.median_reply_minutes) }}
						</dd>
					</div>
				</dl>
			</div>
		</div>

		<DashboardWidgetBox storageKey="chat_reports_started_vs_answered_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon
						icon="fal fa-chart-line"
						class="text-pink-600"
						fixed-width
						aria-hidden="true" />
					{{ ctrans("Started vs Answered") }}
				</span>
				<span class="text-xs text-gray-400"
					>{{ stats.conversations }} {{ ctrans("started") }} · {{ stats.answered }}
					{{ ctrans("answered") }}</span
				>
			</template>
			<div class="grid gap-6 lg:grid-cols-5">
				<div class="h-72 lg:col-span-3">
					<Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
				</div>
				<div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
					<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon
							icon="fal fa-chart-pie"
							class="text-blue-600"
							fixed-width
							aria-hidden="true" />
						{{ ctrans("Status overview") }}
					</p>
					<div class="flex items-center gap-4">
						<div class="relative h-44 w-44 shrink-0">
							<Chart
								type="doughnut"
								:data="donutChart"
								:options="donutOptions"
								class="h-full" />
							<div
								class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
								<span class="text-3xl font-bold">{{ stats.conversations }}</span>
								<span class="text-xs text-gray-500">{{ ctrans("Total") }}</span>
							</div>
						</div>
						<table class="text-sm tabular-nums">
							<tbody>
								<tr
									v-for="row in stats.by_status.filter((status) => status.total)"
									:key="row.status">
									<td class="py-1 pl-2 pr-5">
										<span class="flex items-center gap-2">
											<span
												class="h-3 w-3 shrink-0 rounded-sm"
												:style="{
													backgroundColor:
														STATUS_COLORS[row.status] ?? '#9ca3af',
												}" />
											{{ row.label }}
										</span>
									</td>
									<td class="py-1 pr-5 text-right font-medium">
										{{ row.total }}
									</td>
									<td class="py-1 pr-2 text-right text-gray-500">
										{{ percent(row.total, stats.conversations) }}
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox storageKey="chat_reports_agents_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon
						icon="fal fa-user-headset"
						class="text-violet-600"
						fixed-width
						aria-hidden="true" />
					{{ ctrans("Agents") }}
				</span>
				<span class="text-xs text-gray-400">{{
					ctrans("Conversations they replied to in this period")
				}}</span>
			</template>
			<div class="-mx-4 -mb-4 overflow-x-auto">
				<table class="min-w-full text-sm tabular-nums">
					<thead class="text-left text-xs text-gray-500">
						<tr>
							<th
								v-for="(column, index) in agentColumns"
								:key="column.key"
								class="cursor-pointer select-none px-4 py-2 hover:text-gray-700"
								:class="{ 'text-right': index > 0 }"
								@click="toggleSort('agents', column.key)">
								{{ column.label }}{{ sortArrow("agents", column.key) }}
							</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr
							v-for="agent in sortedAgents"
							:key="agent.username ?? agent.name"
							class="hover:bg-gray-50">
							<td class="px-4 py-2">
								<span class="flex items-center gap-2">
									<TicketUserAvatar
										:name="agent.name"
										:avatar="agent.avatar"
										size="sm" />
									{{ agent.name }}
								</span>
							</td>
							<td class="px-4 py-2 text-right font-medium">
								{{ agent.conversations }}
							</td>
							<td class="px-4 py-2 text-right">{{ agent.messages }}</td>
							<td class="px-4 py-2 text-right">{{ agent.website }}</td>
							<td class="px-4 py-2 text-right">{{ agent.email }}</td>
							<td class="px-4 py-2 text-right">{{ agent.whatsapp }}</td>
							<td class="px-4 py-2 text-right">
								{{ minutes(agent.median_reply_minutes) }}
							</td>
						</tr>
						<tr v-if="!stats.agents.length">
							<td
								:colspan="agentColumns.length"
								class="px-4 py-6 text-center text-gray-500">
								{{ ctrans("Nobody replied in this period.") }}
							</td>
						</tr>
					</tbody>
					<tfoot
						v-if="stats.agents.length"
						class="border-t border-gray-200 text-sm font-semibold">
						<tr>
							<td class="px-4 py-2">{{ stats.agents_total.name }}</td>
							<td class="px-4 py-2 text-right">
								{{ stats.agents_total.conversations }}
							</td>
							<td class="px-4 py-2 text-right">{{ stats.agents_total.messages }}</td>
							<td class="px-4 py-2 text-right">{{ stats.agents_total.website }}</td>
							<td class="px-4 py-2 text-right">{{ stats.agents_total.email }}</td>
							<td class="px-4 py-2 text-right">{{ stats.agents_total.whatsapp }}</td>
							<td class="px-4 py-2 text-right">
								{{ minutes(stats.agents_total.median_reply_minutes) }}
							</td>
						</tr>
					</tfoot>
				</table>
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox v-if="stats.noise.length" storageKey="chat_reports_noise_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-filter" class="text-gray-500" fixed-width aria-hidden="true" />
					{{ ctrans("Noise check on strangers' first messages") }}
				</span>
				<span class="text-xs text-gray-400">
					{{ ctrans("Reversed is what a person undid or overruled") }}
				</span>
			</template>
			<div class="-mx-4 -mb-4 overflow-x-auto">
				<table class="min-w-full text-sm tabular-nums">
					<thead class="text-left text-xs text-gray-500">
						<tr>
							<th class="px-4 py-2">{{ ctrans("Decided by") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Noise") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Genuine") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Reversed") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in stats.noise" :key="row.source" class="hover:bg-gray-50">
							<td class="px-4 py-2">{{ row.source === "ai" ? ctrans("AI") : ctrans("Rule") }}</td>
							<td class="px-4 py-2 text-right">{{ row.noise }}</td>
							<td class="px-4 py-2 text-right">{{ row.genuine }}</td>
							<td class="px-4 py-2 text-right font-medium">{{ row.reversed }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox storageKey="chat_reports_topics_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon
						icon="fal fa-tags"
						class="text-amber-600"
						fixed-width
						aria-hidden="true" />
					{{ ctrans("What customers wanted") }}
				</span>
				<span class="text-xs text-gray-400">
					{{ ctrans("Classified by AI from each conversation") }}
					<template v-if="stats.unclassified">
						· {{ stats.unclassified }} {{ ctrans("not classified yet") }}
					</template>
				</span>
			</template>
			<div class="-mx-4 -mb-4 overflow-x-auto">
				<table class="min-w-full text-sm tabular-nums">
					<thead class="text-left text-xs text-gray-500">
						<tr>
							<th
								v-for="(column, index) in topicColumns"
								:key="column.key"
								class="cursor-pointer select-none px-4 py-2 hover:text-gray-700"
								:class="{ 'text-right': index > 0 }"
								@click="toggleSort('topics', column.key)">
								{{ column.label }}{{ sortArrow("topics", column.key) }}
							</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="topic in sortedTopics" :key="topic.topic" class="hover:bg-gray-50">
							<td class="px-4 py-2">{{ topic.label }}</td>
							<td class="px-4 py-2 text-right font-medium">{{ topic.conversations }}</td>
							<td class="px-4 py-2 text-right">{{ topic.share }}%</td>
							<td class="px-4 py-2 text-right">{{ topic.unanswered }}</td>
							<td class="px-4 py-2 text-right">{{ topic.website }}</td>
							<td class="px-4 py-2 text-right">{{ topic.email }}</td>
							<td class="px-4 py-2 text-right">{{ topic.whatsapp }}</td>
							<td class="px-4 py-2 text-right">
								{{ minutes(topic.median_reply_minutes) }}
							</td>
						</tr>
						<tr v-if="!stats.by_topic.length">
							<td
								:colspan="topicColumns.length"
								class="px-4 py-6 text-center text-gray-500">
								{{ ctrans("No conversations classified in this period yet.") }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox v-if="showShops" storageKey="chat_reports_shops_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon
						icon="fal fa-store"
						class="text-emerald-600"
						fixed-width
						aria-hidden="true" />
					{{ ctrans("Shops") }}
				</span>
			</template>
			<div class="-mx-4 -mb-4 overflow-x-auto">
				<table class="min-w-full text-sm tabular-nums">
					<thead class="text-left text-xs text-gray-500">
						<tr>
							<th
								v-for="(column, index) in shopColumns"
								:key="column.key"
								class="cursor-pointer select-none px-4 py-2 hover:text-gray-700"
								:class="{ 'text-right': index > 0 }"
								@click="toggleSort('shops', column.key)">
								{{ column.label }}{{ sortArrow("shops", column.key) }}
							</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr
							v-for="shop in sortedShops"
							:key="shop.slug ?? shop.shop"
							class="hover:bg-gray-50">
							<td class="px-4 py-2">{{ shop.shop }}</td>
							<td class="px-4 py-2 text-right font-medium">
								{{ shop.conversations }}
							</td>
							<td class="px-4 py-2 text-right">{{ shop.answered }}</td>
							<td class="px-4 py-2 text-right">{{ shop.unanswered }}</td>
							<td class="px-4 py-2 text-right">{{ shop.website }}</td>
							<td class="px-4 py-2 text-right">{{ shop.email }}</td>
							<td class="px-4 py-2 text-right">{{ shop.whatsapp }}</td>
							<td class="px-4 py-2 text-right">
								{{ minutes(shop.median_reply_minutes) }}
							</td>
						</tr>
						<tr v-if="!stats.by_shop.length">
							<td
								:colspan="shopColumns.length"
								class="px-4 py-6 text-center text-gray-500">
								{{ ctrans("No conversations in this period.") }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox storageKey="chat_reports_csat_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon
						icon="fal fa-star"
						class="text-sky-600"
						fixed-width
						aria-hidden="true" />
					{{ ctrans("Customer satisfaction") }}
				</span>
				<span class="text-xs text-gray-400">{{ ctrans("Last 12 months") }}</span>
			</template>
			<div class="h-56">
				<Chart type="bar" :data="csatChart" :options="csatOptions" class="h-full" />
			</div>
		</DashboardWidgetBox>
	</div>
</template>
