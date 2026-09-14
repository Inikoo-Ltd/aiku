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
import { faVial, faShieldCheck, faShield, faRocket } from "@fal"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"

library.add(faVial, faShieldCheck, faShield, faRocket)

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
	createdIntervals: Record<string, string>
	createdInterval: string
	updateRoute: string
	can_manage: boolean
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
							<span class="truncate">{{ shortName(option.value) }}</span>
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
					@start="dragging = true"
					@end="dragging = false"
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
		class="fixed inset-0 z-50 flex items-stretch justify-center bg-black/40 p-4"
		@click.self="closeQuickLook">
		<div class="relative flex w-4/5 flex-col rounded-2xl bg-white shadow-xl">
			<button
				type="button"
				class="absolute -right-3 -top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow hover:text-gray-800"
				@click="closeQuickLook">
				<FontAwesomeIcon icon="fal fa-times" fixed-width />
			</button>
			<iframe
				:src="route('grp.tickets.show', quickLook.reference) + '?embed=1'"
				class="h-full w-full flex-1 rounded-2xl border-0" />
		</div>
	</div>
</template>
