<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue"
import draggable from "vuedraggable"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVial, faShieldCheck, faShield } from "@fal"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import TicketControls from "@/Components/Tickets/TicketControls.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import axios from "axios"

library.add(faVial, faShieldCheck, faShield)

const props = defineProps<{
	pageHead: any
	title: string
	columns: {
		key: string
		status: string
		group: string
		label: string
		color: string
		icon: any
		period: string | null
		statuses: { status: string; label: string; count: number }[]
		tickets: any[]
	}[]
	periodOptions: string[]
	createdIntervals: Record<string, string>
	createdInterval: string
	updateRoute: string
	can_manage: boolean
}>()

useLiveTickets(["columns", "periodOptions"])

const columnClasses: Record<string, string> = {
	gray: "bg-gray-100 border-t-4 border-gray-400",
	blue: "bg-blue-50 border-t-4 border-blue-400",
	green: "bg-green-50 border-t-4 border-green-500",
}

const periodLabels: Record<string, string> = {
	"24h": "24h",
	today: trans("Today"),
	"1w": trans("1 week"),
	all: trans("All"),
}

const openPicker = ref<string | null>(null)

const setPeriod = (columnKey: string, period: string) => {
	openPicker.value = null
	const periods: Record<string, string> = {}
	props.columns.forEach((column) => {
		if (column.period) periods[column.key] = column.key === columnKey ? period : column.period
	})
	router.get(route("grp.tickets.board"), { periods }, { preserveScroll: true })
}

const bucketStamps: Record<string, string> = {
	open: "created_at",
	assigned: "assigned_at",
	in_progress: "started_at",
	waiting: "waiting_at",
	closed: "closed_at",
}

type SortField = "created_at" | "updated_at" | "priority" | "in_column"

const sortFields: { key: SortField; label: string }[] = [
	{ key: "created_at", label: trans("Created") },
	{ key: "updated_at", label: trans("Updated") },
	{ key: "priority", label: trans("Urgency") },
	{ key: "in_column", label: trans("In column") },
]

const priorityRank: Record<string, number> = { urgent: 3, high: 2, normal: 1, low: 0 }

const readSorts = (): Record<string, { field: SortField; desc: boolean }> => {
	try {
		return JSON.parse(localStorage.getItem("tickets-board-sorts") ?? "{}")
	} catch {
		return {}
	}
}

const columnSorts = reactive(readSorts())

const sortOf = (columnKey: string) => columnSorts[columnKey] ?? { field: "created_at", desc: true }

const sortValue = (ticket: any, columnKey: string, field: SortField) => {
	if (field === "priority") return priorityRank[ticket.priority] ?? 0
	const stamp = field === "in_column" ? ticket[bucketStamps[columnKey]] ?? ticket.updated_at : ticket[field]
	return stamp ? new Date(stamp).getTime() : 0
}

const sortColumn = (column: { key: string; tickets: any[] }) => {
	const { field, desc } = sortOf(column.key)
	column.tickets.sort((a, b) => (sortValue(a, column.key, field) - sortValue(b, column.key, field)) * (desc ? -1 : 1))
}

const changeSort = (column: { key: string; tickets: any[] }, change: "field" | "direction") => {
	const current = sortOf(column.key)
	columnSorts[column.key] = change === "direction"
		? { ...current, desc: !current.desc }
		: { field: sortFields[(sortFields.findIndex((option) => option.key === current.field) + 1) % sortFields.length].key, desc: current.desc }
	try {
		localStorage.setItem("tickets-board-sorts", JSON.stringify(columnSorts))
	} catch {}
	sortColumn(column)
}

const columns = ref(props.columns)
props.columns.forEach(sortColumn)

watch(
	() => props.columns,
	(value) => {
		value.forEach(sortColumn)
		columns.value = value
	}
)

type FilterKey = "module_label" | "kind_label" | "priority_label" | "assignee"

const boardFilters = reactive<Record<FilterKey, string[]>>({
	module_label: [],
	kind_label: [],
	priority_label: [],
	assignee: [],
})

const filterLabels: Record<FilterKey, string> = {
	module_label: trans("Module"),
	kind_label: trans("Kind"),
	priority_label: trans("Urgency"),
	assignee: trans("Assignee"),
}

const countedBy = (key: FilterKey) => {
	const counts: Record<string, number> = {}
	props.columns
		.flatMap((column) => column.tickets)
		.forEach((ticket) => {
			const value = ticket[key]
			if (value) counts[value] = (counts[value] ?? 0) + 1
		})
	return Object.entries(counts)
		.sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
		.map(([value, count]) => ({ value, count }))
}

const filterOptions = computed(() => ({
	module_label: countedBy("module_label"),
	kind_label: countedBy("kind_label"),
	priority_label: countedBy("priority_label"),
	assignee: countedBy("assignee"),
}))

const toggleFilter = (key: FilterKey, value: string) => {
	const index = boardFilters[key].indexOf(value)
	index === -1 ? boardFilters[key].push(value) : boardFilters[key].splice(index, 1)
}

const activeFilters = computed(() =>
	Object.values(boardFilters).reduce((total, values) => total + values.length, 0)
)

const clearFilters = () =>
	(Object.keys(boardFilters) as FilterKey[]).forEach((key) => (boardFilters[key] = []))

const subFilter = reactive<Record<string, string | null>>({})

const toggleSubFilter = (columnKey: string, status: string) =>
	(subFilter[columnKey] = subFilter[columnKey] === status ? null : status)

const matchesBoardFilters = (ticket: any) =>
	(Object.keys(boardFilters) as FilterKey[]).every(
		(key) => !boardFilters[key].length || boardFilters[key].includes(ticket[key])
	)

const matchesFilters = (ticket: any, columnKey: string) =>
	(!subFilter[columnKey] || subFilter[columnKey] === ticket.status) && matchesBoardFilters(ticket)

const visibleCount = (column: { key: string; tickets: any[] }) =>
	column.tickets.filter((ticket) => matchesFilters(ticket, column.key)).length

const subCount = (column: { tickets: any[] }, status: string) =>
	column.tickets.filter((ticket) => ticket.status === status && matchesBoardFilters(ticket))
		.length

const assigneeMenuOpen = ref(false)

const quickLook = ref<any | null>(null)
const quickLookControls = ref<any | null>(null)
const isQuickLookControlsUnavailable = ref(false)
const quickLookTicket = computed(() => quickLookControls.value?.ticket ?? quickLook.value)

const loadQuickLookControls = async (ticketId: number) => {
	isQuickLookControlsUnavailable.value = false
	try {
		const { data } = await axios.get(route("grp.json.ticket.controls", { ticket: ticketId }))
		if (quickLook.value?.id === ticketId) quickLookControls.value = data
	} catch {
		if (quickLook.value?.id === ticketId) isQuickLookControlsUnavailable.value = true
	}
}

watch(quickLook, (ticket, previousTicket) => {
	if (ticket?.id === previousTicket?.id) return
	quickLookControls.value = null
	if (ticket) loadQuickLookControls(ticket.id)
})

// ponytail: vuedraggable eats dblclick and bubbled clicks, so the board listens in capture
let lastClick = { id: 0, at: 0 }

const onBoardClick = (event: MouseEvent) => {
	const card = (event.target as HTMLElement)?.closest?.("[data-ticket-id]") as HTMLElement | null
	if (!card) return

	const id = Number(card.dataset.ticketId)
	const now = Date.now()

	if (lastClick.id === id && now - lastClick.at < 600) {
		quickLook.value = props.columns.flatMap((column) => column.tickets).find((t) => t.id === id)
	}

	lastClick = { id, at: now }
}

onMounted(() => window.addEventListener("click", onBoardClick, true))
onBeforeUnmount(() => window.removeEventListener("click", onBoardClick, true))

const shortDate = (value: string | null) =>
	value ? new Date(value).toLocaleDateString([], { day: "numeric", month: "short" }) : ""

const shortTime = (value: string) =>
	new Date(value).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

const ageIn = (column: { key: string; period: string | null }, ticket: any) => {
	const since = ticket[bucketStamps[column.key]] ?? ticket.updated_at
	if (!since) return ""

	if (column.key === "closed") {
		return column.period === "24h" || column.period === "today"
			? shortTime(since)
			: shortDate(since)
	}

	const hours = Math.floor((Date.now() - new Date(since).getTime()) / 3600000)
	return hours < 48 ? `${Math.max(hours, 0)}h` : `${Math.floor(hours / 24)}d`
}

const avatarFor = (assignee: string) =>
	props.columns.flatMap((column) => column.tickets).find((ticket) => ticket.assignee === assignee)
		?.assignee_avatar?.original ?? null

const initials = (name: string) =>
	name
		.split(/[\s-]+/)
		.filter(Boolean)
		.slice(0, 2)
		.map((part) => part[0].toUpperCase())
		.join("")

const onMoved = (status: string, event: { added?: { element: { id: number } } }) => {
	if (!event.added) return
	router.patch(
		route(props.updateRoute, { ticket: event.added.element.id }),
		{ status },
		{ preserveScroll: true, preserveState: true }
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<div class="p-4 overflow-x-auto">
		<TicketsCreatedInterval :options="createdIntervals" :selected="createdInterval" class="mb-3" />
		<div
			class="mb-3 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm">
			<div
				v-for="key in ['module_label', 'kind_label', 'priority_label'] as const"
				v-show="filterOptions[key].length"
				:key="key"
				class="flex flex-wrap items-center gap-1.5">
				<span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{
					filterLabels[key]
				}}</span>
				<button
					v-for="option in filterOptions[key]"
					:key="option.value"
					type="button"
					class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
					:class="
						boardFilters[key].includes(option.value)
							? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
							: option.value === 'Urgent'
								? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
								: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'
					"
					@click="toggleFilter(key, option.value)">
					<span>{{ option.value }}</span>
					<span
						class="rounded-full px-1.5 text-xs tabular-nums"
						:class="
							boardFilters[key].includes(option.value)
								? 'bg-white/20'
								: 'bg-white text-gray-500'
						"
						>{{ option.count }}</span
					>
				</button>
			</div>
			<div v-if="filterOptions.assignee.length" class="relative flex items-center gap-1.5">
				<span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{
					filterLabels.assignee
				}}</span>
				<button
					type="button"
					class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
					:class="
						boardFilters.assignee.length
							? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
							: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'
					"
					@click="assigneeMenuOpen = !assigneeMenuOpen">
					<span class="max-w-48 truncate">{{
						boardFilters.assignee.length
							? boardFilters.assignee.join(", ")
							: trans("Everybody")
					}}</span>
					<span
						class="rounded-full px-1.5 text-xs tabular-nums"
						:class="
							boardFilters.assignee.length ? 'bg-white/20' : 'bg-white text-gray-500'
						"
						>{{ boardFilters.assignee.length || filterOptions.assignee.length }}</span
					>
				</button>
				<div
					v-if="assigneeMenuOpen"
					class="fixed inset-0 z-30"
					@click="assigneeMenuOpen = false" />
				<div
					v-if="assigneeMenuOpen"
					class="absolute left-0 top-8 z-40 max-h-72 w-64 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1.5 shadow-xl">
					<label
						v-for="option in filterOptions.assignee"
						:key="option.value"
						class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50">
						<input
							type="checkbox"
							:checked="boardFilters.assignee.includes(option.value)"
							@change="toggleFilter('assignee', option.value)" />
						<img
							v-if="avatarFor(option.value)"
							:src="avatarFor(option.value)"
							class="h-6 w-6 rounded-full object-cover"
							:alt="option.value" />
						<span
							v-else
							class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[10px] font-semibold text-gray-600"
							>{{ initials(option.value) }}</span
						>
						<span class="truncate">{{ option.value }}</span>
						<span class="ml-auto text-xs text-gray-400">{{ option.count }}</span>
					</label>
					<button
						v-if="boardFilters.assignee.length"
						type="button"
						class="mt-1 w-full rounded px-2 py-1 text-left text-xs text-gray-400 hover:bg-gray-50"
						@click="boardFilters.assignee = []">
						{{ trans("Everybody") }}
					</button>
				</div>
			</div>
			<button
				v-if="activeFilters"
				type="button"
				class="text-xs text-gray-400 hover:text-gray-600"
				@click="clearFilters()">
				× {{ trans("Clear") }}
			</button>
		</div>
		<div class="flex gap-3 min-w-max">
			<div
				v-for="column in columns"
				:key="column.key"
				class="w-72 rounded-lg p-2 flex flex-col"
				:class="columnClasses[column.color]">
				<div class="flex items-center gap-1.5 px-1 pb-2 flex-nowrap whitespace-nowrap">
					<Icon :data="column.icon" />
					<span class="text-sm font-semibold">{{ column.label }}</span>
					<span
						v-if="column.statuses.length === 1"
						class="text-xs text-gray-600 bg-white/70 rounded px-1.5 py-0.5 tabular-nums"
						>{{ visibleCount(column) }}</span
					>
					<span
						v-else
						class="flex items-center text-xs bg-white/70 rounded px-1 py-0.5 tabular-nums">
						<template v-for="(sub, index) in column.statuses" :key="sub.status">
							<span v-if="index" class="px-0.5 text-gray-300">|</span>
							<button
								type="button"
								class="px-1 rounded"
								:title="sub.label"
								:class="
									subFilter[column.key] === sub.status
										? sub.status === 'cancelled'
											? 'bg-red-500 text-white'
											: 'bg-green-600 text-white'
										: subFilter[column.key]
											? 'text-gray-400 hover:text-gray-600'
											: 'text-gray-600 hover:text-gray-900'
								"
								@click="toggleSubFilter(column.key, sub.status)">
								{{ subCount(column, sub.status) }}
							</button>
						</template>
					</span>
					<span class="ml-auto flex items-center text-xs text-gray-500 bg-white/70 rounded">
						<button
							type="button"
							class="px-1 py-0.5 hover:text-gray-900"
							:title="trans('Sort by')"
							@click="changeSort(column, 'field')">
							{{ sortFields.find((option) => option.key === sortOf(column.key).field)?.label }}
						</button>
						<button
							type="button"
							class="px-1 py-0.5 hover:text-gray-900"
							:title="sortOf(column.key).desc ? trans('Newest or highest first') : trans('Oldest or lowest first')"
							@click="changeSort(column, 'direction')">
							{{ sortOf(column.key).desc ? "↓" : "↑" }}
						</button>
					</span>
					<div v-if="column.period" class="relative">
						<button
							type="button"
							class="text-xs text-gray-600 hover:text-gray-900 border border-gray-300 rounded px-1.5 py-0.5 bg-white"
							@click="openPicker = openPicker === column.key ? null : column.key">
							{{ periodLabels[column.period] ?? column.period }}
						</button>
						<div
							v-if="openPicker === column.key"
							class="absolute right-0 z-20 mt-1 w-28 bg-white border border-gray-200 rounded shadow-lg py-1">
							<button
								v-for="option in periodOptions"
								:key="option"
								type="button"
								class="block w-full text-left text-xs px-2 py-1 hover:bg-gray-100"
								:class="
									option === column.period
										? 'font-semibold text-gray-900'
										: 'text-gray-600'
								"
								@click="setPeriod(column.key, option)">
								{{ periodLabels[option] ?? option }}
							</button>
						</div>
					</div>
				</div>
				<draggable
					v-model="column.tickets"
					item-key="id"
					group="tickets"
					:disabled="!can_manage"
					class="flex-1 space-y-2 min-h-24 max-h-[70vh] overflow-y-auto pr-0.5"
					@change="onMoved(column.status, $event)">
					<template #item="{ element }">
						<div
							v-show="matchesFilters(element, column.key)"
							class="bg-white rounded-md border border-gray-200 shadow-sm p-2.5 hover:border-gray-400"
							:class="can_manage ? 'cursor-grab active:cursor-grabbing' : 'cursor-pointer'"
							:data-ticket-id="element.id">
							<p class="text-sm leading-snug break-words line-clamp-3">
								{{ element.subject }}
							</p>
							<div class="flex items-center gap-2 text-xs mt-2">
								<Link
									:href="route('grp.tickets.show', element.reference)"
									class="primaryLink font-medium"
									@click.stop
									>{{ element.reference }}</Link
								>
								<Icon
									v-if="column.group === 'closed'"
									:data="element.status_icon" />
								<Icon
									v-if="element.qa_status_icon"
									:data="element.qa_status_icon" />
								<span
									class="text-gray-400"
									v-tooltip="{ content: trans('Raised'), delay: 0 }"
									>{{ shortDate(element.created_at) }}</span
								>
								<span
									class="text-gray-500"
									v-tooltip="{ content: column.label, delay: 0 }"
									>{{ ageIn(column, element) }}</span
								>
								<span
									v-if="element.assignee"
									v-tooltip="{ content: element.assignee, delay: 0 }"
									class="ml-auto">
									<img
										v-if="element.assignee_avatar?.original"
										:src="element.assignee_avatar.original"
										class="w-6 h-6 rounded-full object-cover"
										:alt="element.assignee" />
									<span
										v-else
										class="w-6 h-6 rounded-full bg-gray-200 text-gray-600 flex items-center justify-center text-[10px] font-medium">
										{{ (element.assignee_short || "?").slice(0, 2) }}
									</span>
								</span>
							</div>
						</div>
					</template>
				</draggable>
			</div>
		</div>
	</div>
	<div
		v-if="quickLook"
		class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
		@click.self="quickLook = null">
		<div class="relative w-full max-w-6xl rounded-2xl bg-white p-6 shadow-xl">
			<button
				type="button"
				class="absolute right-4 top-3 text-gray-400 hover:text-gray-700"
				@click="quickLook = null">
				<FontAwesomeIcon icon="fal fa-times" fixed-width />
			</button>
			<div class="grid max-h-[80vh] gap-6 overflow-y-auto pr-1 lg:grid-cols-3">
				<div class="flex flex-col lg:col-span-2">
				<div class="flex items-center gap-2 text-xs mb-2">
					<Link
						:href="route('grp.tickets.show', quickLookTicket.reference)"
						class="primaryLink font-medium"
						>{{ quickLookTicket.reference }}</Link
					>
					<Icon :data="quickLookTicket.status_icon" />
					<span class="text-gray-600">{{ quickLookTicket.status_label }}</span>
					<Icon :data="quickLookTicket.priority_icon" />
					<span class="text-gray-600">{{ quickLookTicket.priority_label }}</span>
					<span v-if="quickLookTicket.kind_label" class="text-gray-400"
						>· {{ quickLookTicket.kind_label }}</span
					>
					<span v-if="quickLookTicket.module_label" class="text-gray-400"
						>· {{ quickLookTicket.module_label }}</span
					>
				</div>
				<h2 class="text-lg font-semibold leading-snug mb-3">{{ quickLookTicket.subject }}</h2>
				<div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600 mb-4">
					<span
						>{{ trans("Raised") }}: {{ shortDate(quickLookTicket.created_at) }}
						{{ quickLookTicket.reporter ? "· " + quickLookTicket.reporter : "" }}</span
					>
					<span v-if="quickLookTicket.assignee"
						>{{ trans("Assignee") }}: {{ quickLookTicket.assignee }}</span
					>
					<span v-if="quickLookTicket.assigned_at"
						>{{ trans("Assigned") }}: {{ shortDate(quickLookTicket.assigned_at) }}</span
					>
					<span v-if="quickLookTicket.started_at"
						>{{ trans("Started") }}: {{ shortDate(quickLookTicket.started_at) }}</span
					>
					<span v-if="quickLookTicket.waiting_at"
						>{{ trans("Waiting since") }}: {{ shortDate(quickLookTicket.waiting_at) }}</span
					>
					<span v-if="quickLookTicket.closed_at"
						>{{ trans("Closed") }}: {{ shortDate(quickLookTicket.closed_at) }}</span
					>
					<span v-if="quickLookTicket.customer"
						>{{ trans("Customer") }}: {{ quickLookTicket.customer }}</span
					>
					<span v-if="quickLookTicket.shop">{{ trans("Shop") }}: {{ quickLookTicket.shop }}</span>
				</div>
				<p class="text-sm whitespace-pre-wrap break-words">{{ quickLookTicket.description }}</p>
				<div v-if="quickLookControls?.attachment_gallery?.length" class="mt-auto pt-4">
					<TicketAttachmentList :files="quickLookControls.attachment_gallery" compact />
				</div>
				</div>
				<aside class="text-sm lg:border-l lg:border-gray-200 lg:pl-6">
					<TicketControls v-if="quickLookControls" v-bind="quickLookControls" @updated="loadQuickLookControls(quickLook.id)" />
					<p v-else-if="isQuickLookControlsUnavailable" class="text-gray-500">{{ trans("Controls are unavailable") }}</p>
					<p v-else class="text-gray-400"><FontAwesomeIcon icon="fal fa-spinner" spin class="mr-1" />{{ trans("Loading") }}</p>
				</aside>
			</div>
		</div>
	</div>
</template>
