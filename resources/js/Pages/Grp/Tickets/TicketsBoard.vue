<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue"
import draggable from "vuedraggable"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVial, faShieldCheck, faShield, faRocket, faSpinner } from "@fal"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import TicketQuickLook from "@/Components/Tickets/TicketQuickLook.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketAskReporterDialog from "@/Components/Tickets/TicketAskReporterDialog.vue"
import TicketStatusNoteDialog from "@/Components/Tickets/TicketStatusNoteDialog.vue"

library.add(faVial, faShieldCheck, faShield, faRocket, faSpinner)

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
		statuses: { status: string; label: string; color: string; count: number }[]
		tickets: any[]
	}[]
	periodOptions: string[]
	me: string
	formerAssignees: string[]
	assignees: { label: string; value: number; avatar: any; is_me: boolean }[]
	createdIntervals: Record<string, string>
	createdInterval: string
	updateRoute: string
	can_manage: boolean
	can_assign: boolean
}>()


const subActiveClasses: Record<string, string> = {
	blue: "bg-blue-500 text-white",
	amber: "bg-amber-500 text-white",
	green: "bg-green-600 text-white",
	red: "bg-red-500 text-white",
}

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

const sortOf = (columnKey: string) => columnSorts[columnKey] ?? { field: "in_column", desc: true }

const sortValue = (ticket: any, columnKey: string, field: SortField) => {
	if (field === "priority") return priorityRank[ticket.priority] ?? 0
	const stamp = field === "in_column" ? ticket[bucketStamps[columnKey]] ?? ticket.updated_at : ticket[field]
	return stamp ? new Date(stamp).getTime() : 0
}

const sortColumn = (column: { key: string; tickets: any[] }) => {
	const { field, desc } = sortOf(column.key)
	column.tickets.sort((a, b) => (sortValue(a, column.key, field) - sortValue(b, column.key, field)) * (desc ? -1 : 1))
}

const openSortPicker = ref<string | null>(null)

const changeSort = (column: { key: string; tickets: any[] }, change: SortField | "direction") => {
	openSortPicker.value = null
	const current = sortOf(column.key)
	columnSorts[column.key] = change === "direction" ? { ...current, desc: !current.desc } : { field: change, desc: current.desc }
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

type FilterKey = "module_label" | "kind_label" | "priority_label" | "assignee_username"

const boardFilters = reactive<Record<FilterKey, string[]>>({
	module_label: [],
	kind_label: [],
	priority_label: [],
	assignee_username: [],
})

const filterLabels: Record<FilterKey, string> = {
	module_label: trans("Module"),
	kind_label: trans("Kind"),
	priority_label: trans("Urgency"),
	assignee_username: trans("Assignee"),
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
	assignee_username: countedBy("assignee_username"),
}))

const assigneeGroups = computed(() => [
	{ label: trans("Current"), options: filterOptions.value.assignee_username.filter((option) => !props.formerAssignees.includes(option.value)) },
	{ label: trans("Former"), options: filterOptions.value.assignee_username.filter((option) => props.formerAssignees.includes(option.value)) },
].filter((group) => group.options.length))

const shortName = (username: string) => username.charAt(0).toUpperCase() + username.slice(1)

const onlyMine = computed(() => boardFilters.assignee_username.length === 1 && boardFilters.assignee_username[0] === props.me)

const toggleMine = () => (boardFilters.assignee_username = onlyMine.value ? [] : [props.me])

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

const dragging = ref(false)

useLiveTickets(["columns", "periodOptions"], undefined, computed(() => dragging.value))

const closeQuickLook = () => {
	quickLook.value = null
	router.reload({ only: ["columns"] })
}

// ponytail: vuedraggable eats dblclick and bubbled clicks, so the board listens in capture
let lastDragEndedAt = 0

const onBoardClick = (event: MouseEvent) => {
	const target = event.target as HTMLElement
	if (!target?.closest?.("[data-picker]")) {
		openPicker.value = null
		openSortPicker.value = null
	}

}

const openQuickLook = (ticket: any, event: MouseEvent) => {
	const target = event.target as HTMLElement | null
	if (target?.closest("a, button") || Date.now() - lastDragEndedAt < 300) return
	quickLook.value = ticket
}

const myUserId = computed(() => (usePage().props.auth as { user?: { id: number } } | undefined)?.user?.id ?? null)

const canDragTicket = (ticket: { assignee_id: number | null }) =>
	props.can_assign || (props.can_manage && myUserId.value !== null && ticket.assignee_id === myUserId.value)

const engineerMoves: Record<string, string[]> = {
	assigned: ["in_progress", "closed"],
	in_progress: ["waiting", "closed"],
	waiting: ["in_progress", "closed"],
}

const canDropTicket = (ticket: { assignee_id: number | null }, fromColumn: string, toColumn: string) => {
	if (fromColumn === toColumn) return true
	if (!canDragTicket(ticket)) return false
	if (!ticket.assignee_id) return toColumn === "assigned"
	if (props.can_assign) return true
	return engineerMoves[fromColumn]?.includes(toColumn) ?? false
}

const onMoveCheck = (event: { draggedContext: { element: any }; from: HTMLElement; to: HTMLElement }) =>
	canDropTicket(event.draggedContext.element, event.from.dataset.column ?? "", event.to.dataset.column ?? "")

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

const avatarFor = (username: string) =>
	props.columns.flatMap((column) => column.tickets).find((ticket) => ticket.assignee_username === username)
		?.assignee_avatar?.original ?? null

const initials = (name: string) =>
	name
		.split(/[\s-]+/)
		.filter(Boolean)
		.slice(0, 2)
		.map((part) => part[0].toUpperCase())
		.join("")

const assigning = ref<any | null>(null)
const assignPosition = ref({ x: 0, y: 0 })

const onDragEnd = (event: { originalEvent?: MouseEvent }) => {
	dragging.value = false
	lastDragEndedAt = Date.now()
	assignPosition.value = {
		x: Math.max(8, Math.min((event.originalEvent?.clientX ?? 0) - 40, window.innerWidth - 330)),
		y: Math.max(8, Math.min((event.originalEvent?.clientY ?? 0) + 8, window.innerHeight - 360)),
	}
}

const savingTicketIds = ref<number[]>([])

const patchTicket = (ticketId: number, data: Record<string, unknown>) =>
	router.patch(route(props.updateRoute, { ticket: ticketId }), data, {
		preserveScroll: true,
		preserveState: true,
		onStart: () => savingTicketIds.value.push(ticketId),
		onFinish: () => (savingTicketIds.value = savingTicketIds.value.filter((id) => id !== ticketId)),
	})

const dropDialogTicket = ref<any | null>(null)
const isDropAskReporterOpen = ref(false)
const isDropStatusNoteOpen = ref(false)
let isDropDialogSaved = false

const openDropDialog = (ticket: any, dialog: "ask" | "note") => {
	dropDialogTicket.value = ticket
	isDropDialogSaved = false
	if (dialog === "ask") isDropAskReporterOpen.value = true
	else isDropStatusNoteOpen.value = true
}

watch([isDropAskReporterOpen, isDropStatusNoteOpen], ([isAskOpen, isNoteOpen]) => {
	if (isAskOpen || isNoteOpen || !dropDialogTicket.value) return
	if (!isDropDialogSaved) router.reload({ only: ["columns"] })
	dropDialogTicket.value = null
})

const onMoved = (column: { key: string; status: string }, event: { added?: { element: any } }) => {
	if (!event.added) return
	const ticket = event.added.element

	if (!canDragTicket(ticket) || (!ticket.assignee_id && column.key !== "assigned")) {
		router.reload({ only: ["columns"] })
		return
	}
	if (column.key === "assigned" && !ticket.assignee_id) {
		assigning.value = ticket
		return
	}
	if (column.key === "waiting") {
		openDropDialog(ticket, "ask")
		return
	}
	if (column.key === "closed") {
		openDropDialog(ticket, "note")
		return
	}
	patchTicket(ticket.id, { status: column.status })
}

const assignTo = (assigneeId: number) => {
	patchTicket(assigning.value.id, { status: "assigned", assignee_id: assigneeId })
	assigning.value = null
}

const cancelAssign = () => {
	assigning.value = null
	router.reload({ only: ["columns"] })
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
			<div v-if="filterOptions.assignee_username.length" class="relative flex items-center gap-1.5">
				<span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{
					filterLabels.assignee_username
				}}</span>
				<button
					type="button"
					class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
					:class="
						onlyMine
							? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
							: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'
					"
					@click="toggleMine()">
					{{ trans("Mine") }}
				</button>
				<button
					type="button"
					class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
					:class="
						boardFilters.assignee_username.length && !onlyMine
							? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
							: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'
					"
					@click="assigneeMenuOpen = !assigneeMenuOpen">
					<span class="max-w-48 truncate">{{
						boardFilters.assignee_username.length && !onlyMine
							? boardFilters.assignee_username.map(shortName).join(", ")
							: trans("Everybody")
					}}</span>
					<span
						class="rounded-full px-1.5 text-xs tabular-nums"
						:class="
							boardFilters.assignee_username.length && !onlyMine ? 'bg-white/20' : 'bg-white text-gray-500'
						"
						>{{ (!onlyMine && boardFilters.assignee_username.length) || filterOptions.assignee_username.length }}</span
					>
				</button>
				<div
					v-if="assigneeMenuOpen"
					class="fixed inset-0 z-30"
					@click="assigneeMenuOpen = false" />
				<div
					v-if="assigneeMenuOpen"
					class="absolute left-0 top-8 z-40 max-h-72 w-56 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1.5 shadow-xl">
					<template v-for="group in assigneeGroups" :key="group.label">
						<div class="px-2 pb-1 pt-2 text-xs font-medium uppercase tracking-wide text-gray-400">
							{{ group.label }}
						</div>
						<label
							v-for="option in group.options"
							:key="option.value"
							class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50">
							<input
								type="checkbox"
								:checked="boardFilters.assignee_username.includes(option.value)"
								@change="toggleFilter('assignee_username', option.value)" />
							<TicketUserAvatar :name="option.label ?? option.value" :avatar="avatarFor(option.value) ? { original: avatarFor(option.value) } : null" size="xs" />
							<span class="ml-auto text-xs text-gray-400">{{ option.count }}</span>
						</label>
					</template>
					<button
						v-if="boardFilters.assignee_username.length"
						type="button"
						class="mt-1 w-full rounded px-2 py-1 text-left text-xs text-gray-400 hover:bg-gray-50"
						@click="boardFilters.assignee_username = []">
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
										? subActiveClasses[sub.color]
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
						<div class="relative" data-picker>
							<button
								type="button"
								class="px-1 py-0.5 hover:text-gray-900"
								:title="trans('Sort by')"
								@click="openSortPicker = openSortPicker === column.key ? null : column.key">
								{{ sortFields.find((option) => option.key === sortOf(column.key).field)?.label }}
							</button>
							<div
								v-if="openSortPicker === column.key"
								class="absolute left-0 z-20 mt-1 w-28 bg-white border border-gray-200 rounded shadow-lg py-1">
								<button
									v-for="option in sortFields"
									:key="option.key"
									type="button"
									class="block w-full text-left text-xs px-2 py-1 hover:bg-gray-100"
									:class="option.key === sortOf(column.key).field ? 'font-semibold text-gray-900' : 'text-gray-600'"
									@click="changeSort(column, option.key)">
									{{ option.label }}
								</button>
							</div>
						</div>
						<button
							type="button"
							class="px-1 py-0.5 hover:text-gray-900"
							:title="sortOf(column.key).desc ? trans('Newest or highest first') : trans('Oldest or lowest first')"
							@click="changeSort(column, 'direction')">
							{{ sortOf(column.key).desc ? "↓" : "↑" }}
						</button>
					</span>
					<div v-if="column.period" class="relative" data-picker>
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
					:data-column="column.key"
					:move="onMoveCheck"
					filter=".ticket-card-locked"
					:prevent-on-filter="false"
					:force-fallback="true"
					:fallback-tolerance="4"
					:disabled="!can_manage"
					class="flex-1 space-y-2 min-h-24 max-h-[70vh] overflow-y-auto pr-0.5"
					@start="dragging = true"
					@end="onDragEnd"
					@change="onMoved(column, $event)">
					<template #item="{ element }">
						<div
							v-show="matchesFilters(element, column.key)"
							class="relative bg-white rounded-md border border-gray-200 shadow-sm p-2.5 hover:border-gray-400"
							:aria-busy="savingTicketIds.includes(element.id)"
							:class="canDragTicket(element) ? 'cursor-grab active:cursor-grabbing' : 'ticket-card-locked cursor-pointer'"
							:data-ticket-id="element.id"
							@click="openQuickLook(element, $event)">
							<FontAwesomeIcon v-if="savingTicketIds.includes(element.id)" icon="fal fa-spinner" spin fixed-width class="absolute right-1.5 top-1.5 text-xs text-gray-400" />
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
									v-if="column.statuses.length > 1"
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
									class="ml-auto flex min-w-0 items-center gap-1 rounded-full bg-gray-100 py-0.5 pl-0.5 pr-2">
									<TicketUserAvatar :name="element.assignee" :avatar="element.assignee_avatar" size="xs" />
									<span class="max-w-[5rem] truncate text-[10px] font-medium leading-none text-gray-600">{{ element.assignee_short }}</span>
								</span>
							</div>
						</div>
					</template>
				</draggable>
			</div>
		</div>
	</div>
	<Teleport to="body">
		<div v-if="assigning" class="fixed inset-0 z-40" @click="cancelAssign" />
		<div
			v-if="assigning"
			class="fixed z-50 w-80 rounded-lg border border-indigo-300 bg-white p-3 text-xs shadow-xl"
			:style="{ left: assignPosition.x + 'px', top: assignPosition.y + 'px' }">
			<div class="mb-1.5 font-medium">{{ assigning.reference }} <span class="font-normal text-gray-500">{{ assigning.subject }}</span></div>
			<div class="mb-1 text-gray-500">{{ trans("Assign to") }}</div>
			<div class="flex max-h-64 flex-col gap-0.5 overflow-y-auto">
				<button
					v-for="engineer in assignees"
					:key="engineer.value"
					type="button"
					class="flex w-full items-center gap-2 rounded px-1.5 py-1 text-left hover:bg-indigo-50"
					@click="assignTo(engineer.value)">
					<TicketUserAvatar :name="engineer.label" :avatar="engineer.avatar" size="xs" />
					<span :class="engineer.is_me && 'font-medium'">{{ engineer.label }}</span>
					<span v-if="engineer.is_me" class="text-gray-400">{{ trans("me") }}</span>
				</button>
			</div>
		</div>
	</Teleport>
	<TicketAskReporterDialog
		v-if="dropDialogTicket"
		v-model:visible="isDropAskReporterOpen"
		:update-route="{ name: updateRoute, parameters: { ticket: dropDialogTicket.id } }"
		:default-waiting-hours="dropDialogTicket.default_waiting_hours"
		@updated="isDropDialogSaved = true" />
	<TicketStatusNoteDialog
		v-if="dropDialogTicket"
		v-model:visible="isDropStatusNoteOpen"
		status="resolved"
		:update-route="{ name: updateRoute, parameters: { ticket: dropDialogTicket.id } }"
		:can-wait-for-deployment="dropDialogTicket.status !== 'pending_deploy'"
		@updated="isDropDialogSaved = true" />
	<TicketQuickLook v-model:ticket="quickLook" @closed="closeQuickLook" />
</template>
