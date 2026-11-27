<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 26 Nov 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faComments, faUsers, faReply, faPhone, faClock, faUserFriends, faArrowUp, faArrowDown, faVideo, faDesktop, faChartLine } from "@fal"
import { faSlack } from "@fortawesome/free-brands-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faComments, faUsers, faReply, faPhone, faClock, faUserFriends, faArrowUp, faArrowDown, faVideo, faDesktop, faChartLine, faSlack)

type Totals = {
	messages: number
	people: number
	conversations: number
	direct_messages: number
	group_messages: number
	reply_median_seconds: number | null
	calls: number
	answered: number
	missed: number
	declined: number
	cancelled: number
	voice: number
	video: number
	screen: number
	meetings: number
	people_per_call: number | null
	answer_median_seconds: number | null
	call_median_seconds: number | null
	talk_minutes: number
	person_minutes: number
	slack_messages: number
	slack_from_aiku: number
	slack_people: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	periods: number[]
	report: {
		days: number
		by: "day" | "week"
		from: string
		to: string
		current: Totals
		previous: Totals
		series: Record<"messages" | "people" | "slack_messages" | "slack_people" | "calls" | "answered" | "missed" | "talk_minutes", number[]> & { buckets: string[] }
	}
	people: object
}>()

const VERBA = "#7c5cff"
const SLACK = "#e8a33d"
const ANSWERED = "#14c2a3"
const MISSED = "#ef4444"

const locale = useLocaleStore()

const reload = (changes: { days?: number; by?: string }) => {
	const next = { days: props.report.days, by: props.report.by, ...changes }
	router.get(route("grp.chat.staff.reports"), next, { preserveScroll: true })
}

const periodModel = computed({ get: () => props.report.days, set: (days: number) => reload({ days, by: days > 30 ? "week" : "day" }) })
const byModel = computed({ get: () => props.report.by, set: (by: string) => reload({ by }) })
const periodOptions = computed(() => props.periods.map((days) => ({ label: ctrans(":count days", { count: days }), value: days })))
const byOptions = computed(() => [
	{ label: ctrans("Daily"), value: "day" },
	{ label: ctrans("Weekly"), value: "week" },
])

const duration = (seconds: number | null) => {
	if (seconds === null) {
		return "—"
	}
	if (seconds < 60) {
		return `${seconds}s`
	}
	if (seconds < 3600) {
		return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
	}
	return `${Math.floor(seconds / 3600)}h ${Math.round((seconds % 3600) / 60)}m`
}
const minutes = (value: number) => (value < 60 ? `${value}m` : `${Math.floor(value / 60)}h ${value % 60}m`)

const change = (current: number | null, previous: number | null) => {
	if (current === null || previous === null || previous === 0) {
		return null
	}
	return Math.round(((current - previous) / previous) * 100)
}

const current = computed(() => props.report.current)
const previous = computed(() => props.report.previous)
const verbaShare = computed(() => {
	const total = current.value.messages + current.value.slack_messages
	return total ? Math.round((current.value.messages / total) * 100) : null
})

const cards = computed(() => [
	{ key: "messages", label: ctrans("Verbs sent"), icon: "fal fa-comments", value: locale.number(current.value.messages), change: change(current.value.messages, previous.value.messages), note: ctrans(":count chats", { count: current.value.conversations }) },
	{ key: "people", label: ctrans("People writing"), icon: "fal fa-users", value: locale.number(current.value.people), change: change(current.value.people, previous.value.people), note: ctrans(":count on Slack", { count: current.value.slack_people }) },
	{ key: "reply", label: ctrans("Typical reply time"), icon: "fal fa-reply", value: duration(current.value.reply_median_seconds), change: change(current.value.reply_median_seconds, previous.value.reply_median_seconds), lowerIsBetter: true, note: ctrans("median, from a message to the other person's answer") },
	{ key: "calls", label: ctrans("Calls"), icon: "fal fa-phone", value: locale.number(current.value.calls), change: change(current.value.calls, previous.value.calls), note: ctrans(":answered answered, :missed missed", { answered: current.value.answered, missed: current.value.missed + current.value.declined }) },
	{ key: "talk", label: ctrans("Time in calls"), icon: "fal fa-clock", value: minutes(current.value.talk_minutes), change: change(current.value.talk_minutes, previous.value.talk_minutes), note: ctrans(":time counting every person", { time: minutes(current.value.person_minutes) }) },
	{ key: "share", label: ctrans("Verba share"), icon: "fab fa-slack", value: verbaShare.value === null ? "—" : `${verbaShare.value}%`, change: null, note: ctrans("of messages, against :count on Slack", { count: locale.number(current.value.slack_messages) }) },
])

const isEmpty = computed(() => current.value.messages + current.value.calls + current.value.slack_messages === 0)

const bucketLabel = (bucket: string) => useFormatTime(bucket, { formatTime: props.report.by === "week" ? "d MMM" : "EEE d MMM" })
const labels = computed(() => props.report.series.buckets.map(bucketLabel))

type TooltipContext = {
	chart: { canvas: HTMLCanvasElement }
	tooltip: { opacity: number; caretX: number; caretY: number; title: string[]; dataPoints: { dataset: { label: string; borderColor: string }; raw: unknown }[] }
}
const tableTooltip = ({ chart, tooltip }: TooltipContext) => {
	const parent = chart.canvas.parentNode as HTMLElement
	let element = parent.querySelector<HTMLDivElement>(".verba-tooltip")
	if (!element) {
		element = document.createElement("div")
		element.className = "verba-tooltip pointer-events-none absolute z-10 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs shadow-lg transition-opacity"
		parent.style.position = "relative"
		parent.appendChild(element)
	}
	if (tooltip.opacity === 0) {
		element.style.opacity = "0"
		return
	}
	const cell = (text: string, className = "") => Object.assign(document.createElement("td"), { textContent: text, className })
	const table = document.createElement("table")
	table.className = "tabular-nums"
	for (const point of tooltip.dataPoints) {
		const row = table.insertRow()
		const swatch = document.createElement("td")
		swatch.className = "pr-1.5"
		const box = document.createElement("span")
		box.className = "inline-block h-2.5 w-2.5 rounded-sm"
		box.style.backgroundColor = point.dataset.borderColor
		swatch.appendChild(box)
		row.append(swatch, cell(point.dataset.label, "whitespace-nowrap pr-4 text-gray-700"), cell(locale.number(Number(point.raw)), "whitespace-nowrap text-right font-medium text-gray-900"))
	}
	const title = Object.assign(document.createElement("div"), { textContent: tooltip.title[0] ?? "", className: "mb-1.5 font-semibold text-gray-800" })
	element.replaceChildren(title, table)
	const left = tooltip.caretX + 16 + element.offsetWidth > parent.clientWidth ? tooltip.caretX - element.offsetWidth - 16 : tooltip.caretX + 16
	element.style.left = `${Math.max(0, left)}px`
	element.style.top = `${Math.max(0, Math.min(tooltip.caretY - element.offsetHeight / 2, parent.clientHeight - element.offsetHeight))}px`
	element.style.opacity = "1"
}

const line = (label: string, data: number[], colour: string) => ({
	label,
	data,
	borderColor: colour,
	backgroundColor: colour,
	borderWidth: 1.5,
	fill: false,
	tension: 0,
	pointRadius: data.length > 40 ? 0 : 2.5,
	pointHoverRadius: 4,
})

const chartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 12, color: "#4b5563" } },
		tooltip: { enabled: false, external: tableTooltip },
	},
	scales: {
		x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
		y: { beginAtZero: true, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", precision: 0 } },
	},
}

const messagesChart = computed(() => ({
	labels: labels.value,
	datasets: [line(ctrans("Verba"), props.report.series.messages, VERBA), line(ctrans("Slack"), props.report.series.slack_messages, SLACK)],
}))
const peopleChart = computed(() => ({
	labels: labels.value,
	datasets: [line(ctrans("Verba"), props.report.series.people, VERBA), line(ctrans("Slack"), props.report.series.slack_people, SLACK)],
}))
const callsChart = computed(() => ({
	labels: labels.value,
	datasets: [line(ctrans("Answered"), props.report.series.answered, ANSWERED), line(ctrans("Missed or declined"), props.report.series.missed, MISSED), line(ctrans("Minutes talking"), props.report.series.talk_minutes, VERBA)],
}))

const callFacts = computed(() => [
	{ label: ctrans("Typical wait to answer"), value: duration(current.value.answer_median_seconds) },
	{ label: ctrans("Typical call length"), value: duration(current.value.call_median_seconds) },
	{ label: ctrans("People per answered call"), value: current.value.people_per_call === null ? "—" : locale.number(current.value.people_per_call) },
	{ label: ctrans("Meetings (3 or more people)"), value: locale.number(current.value.meetings) },
	{ label: ctrans("Voice"), value: locale.number(current.value.voice), icon: "fal fa-phone" },
	{ label: ctrans("Video"), value: locale.number(current.value.video), icon: "fal fa-video" },
	{ label: ctrans("Screen share"), value: locale.number(current.value.screen), icon: "fal fa-desktop" },
	{ label: ctrans("Cancelled before an answer"), value: locale.number(current.value.cancelled) },
])

const chatFacts = computed(() => [
	{ label: ctrans("Chats with messages"), value: locale.number(current.value.conversations) },
	{ label: ctrans("Verbs in 1:1 chats"), value: locale.number(current.value.direct_messages) },
	{ label: ctrans("Verbs in Verbrations"), value: locale.number(current.value.group_messages) },
	{ label: ctrans("Slack messages sent from aiku"), value: locale.number(current.value.slack_from_aiku) },
])
</script>

<template>
	<div class="min-h-full bg-[var(--vx-soft,#f5f3f5)]">
		<Head :title="capitalize(title)" />
		<div class="verba-stats-heading bg-[var(--vx-sidebar,#3f0e40)] text-[var(--vx-sidebar-strong,#ffffff)]">
			<PageHeading :data="pageHead" />
		</div>

		<div class="space-y-5 px-4 pb-8 pt-4">
			<div class="flex flex-wrap items-center gap-3">
				<SegmentedToggle v-model="periodModel" :options="periodOptions" :aria-label="ctrans('Period')" />
				<SegmentedToggle v-model="byModel" :options="byOptions" :aria-label="ctrans('Group by')" />
				<span class="text-xs text-gray-500">
					{{ ctrans(":from to :to, compared with the :days days before", { from: useFormatTime(report.from, { formatTime: "d MMM" }), to: useFormatTime(report.to, { formatTime: "d MMM" }), days: report.days }) }}
				</span>
			</div>

			<div v-if="isEmpty" class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
				{{ ctrans("No messages or calls in this period yet. Pick a longer period above.") }}
			</div>

			<div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
				<div v-for="card in cards" :key="card.key" class="rounded-lg border border-gray-200 bg-white px-4 py-3">
					<div class="flex items-center gap-1.5 text-xs text-gray-500">
						<FontAwesomeIcon :icon="card.icon" fixed-width aria-hidden="true" />
						<span class="truncate">{{ card.label }}</span>
					</div>
					<div class="mt-1 flex items-baseline gap-2">
						<span class="text-2xl font-semibold tabular-nums text-gray-900">{{ card.value }}</span>
						<span
							v-if="card.change !== null"
							v-tooltip="ctrans('Against the previous :days days', { days: report.days })"
							class="whitespace-nowrap text-xs font-medium tabular-nums"
							:class="card.change === 0 ? 'text-gray-400' : (card.change > 0) !== !!card.lowerIsBetter ? 'text-green-600' : 'text-red-500'">
							<FontAwesomeIcon v-if="card.change !== 0" :icon="card.change > 0 ? 'fal fa-arrow-up' : 'fal fa-arrow-down'" aria-hidden="true" />
							{{ Math.abs(card.change) }}%
						</span>
					</div>
					<div class="mt-0.5 truncate text-xs text-gray-400" v-tooltip="card.note">{{ card.note }}</div>
				</div>
			</div>

			<div class="grid gap-4 lg:grid-cols-2">
				<div class="rounded-lg border border-gray-200 bg-white p-4">
					<h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Messages: Verba and Slack") }}</h3>
					<div class="mt-3 h-64">
						<Chart type="line" :data="messagesChart" :options="chartOptions" class="h-full" />
					</div>
				</div>
				<div class="rounded-lg border border-gray-200 bg-white p-4">
					<h3 class="text-sm font-semibold text-gray-800">{{ ctrans("People writing: Verba and Slack") }}</h3>
					<div class="mt-3 h-64">
						<Chart type="line" :data="peopleChart" :options="chartOptions" class="h-full" />
					</div>
				</div>
			</div>

			<div class="grid gap-4 lg:grid-cols-3">
				<div class="rounded-lg border border-gray-200 bg-white p-4 lg:col-span-2">
					<h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Calls and meetings") }}</h3>
					<div class="mt-3 h-64">
						<Chart type="line" :data="callsChart" :options="chartOptions" class="h-full" />
					</div>
				</div>
				<div class="space-y-4">
					<div class="rounded-lg border border-gray-200 bg-white p-4">
						<h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Calls") }}</h3>
						<dl class="mt-2 divide-y divide-gray-100 text-sm">
							<div v-for="fact in callFacts" :key="fact.label" class="flex items-center justify-between gap-3 py-1.5">
								<dt class="flex items-center gap-1.5 text-gray-500">
									<FontAwesomeIcon v-if="fact.icon" :icon="fact.icon" fixed-width class="text-gray-400" aria-hidden="true" />
									{{ fact.label }}
								</dt>
								<dd class="font-medium tabular-nums text-gray-900">{{ fact.value }}</dd>
							</div>
						</dl>
					</div>
					<div class="rounded-lg border border-gray-200 bg-white p-4">
						<h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Chats") }}</h3>
						<dl class="mt-2 divide-y divide-gray-100 text-sm">
							<div v-for="fact in chatFacts" :key="fact.label" class="flex items-center justify-between gap-3 py-1.5">
								<dt class="text-gray-500">{{ fact.label }}</dt>
								<dd class="font-medium tabular-nums text-gray-900">{{ fact.value }}</dd>
							</div>
						</dl>
					</div>
				</div>
			</div>

			<div>
				<h3 class="text-sm font-semibold text-gray-800">{{ ctrans("People") }}</h3>
				<Table :resource="people" name="people" class="mt-2">
					<template #cell(call_minutes)="{ item }">
						<span class="tabular-nums">{{ item.call_minutes ? minutes(item.call_minutes) : "—" }}</span>
					</template>
				</Table>
			</div>
		</div>
	</div>
</template>

<style scoped>
.verba-stats-heading :deep(*) {
	color: inherit;
}
</style>
