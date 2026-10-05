<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 10 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { computed, inject, reactive, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowRight, faClipboardList, faExclamationTriangle, faIndustry } from "@fal"
import Image from "@common/Components/Image.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import ArtisanPicker from "@/Components/Production/ArtisanPicker.vue"
import ToProduceCard from "@/Components/Production/ToProduceCard.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faArrowRight, faClipboardList, faExclamationTriangle, faIndustry)

type Row = {
	artefact_id: number
	stock_code: string
	stock_name: string
	category: string
	sells_rank: number | string
	in_production: number
	packed_in: number | null
	stock_available: number | null
	days_of_cover: number | null
	batch_size: number | null
	job_units: number
	quantum_units: number
	annual_units: number
	sales: number | null
	customers: number | null
	sales_ly: number | null
	currency_code: string
	image: object | null
}

type Level = {
	bucket: string
	label: string
	tone: string
	count: number
	in_production: number
	late: number
	on_board: number
	on_floor: number
	days_min: number | null
	days_max: number | null
}

type SentCard = {
	id: number
	artefact_id: number
	stock_code: string
	stock_name: string
	quantity: number | string
	quantity_to_produce: number | string | null
	priority: string | null
	family: string | null
	stock_available: number | string | null
}

type Artisan = { id: number; name: string; open_job_orders: number; hidden: boolean }

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	leadTime: { days: number }
	cover: Level[]
	filters: Record<
		string,
		{
			label: string
			default: string | null
			options: {
				value: string
				label: string
				count: number
				tooltip: string | null
				tone: string | null
			}[]
		}
	>
	data: { data: Row[] }
	sent?: { cards: Record<number, SentCard>; artisans: Artisan[] }
}>()

const locale = useLocaleStore()
const currencyLocale = inject("locale", aikuLocaleStructure)

const sellsBadge: Record<string, { label: string; class: string }> = {
	"1": { label: ctrans("Top"), class: "bg-green-100 text-green-700" },
	"2": { label: ctrans("Good"), class: "bg-blue-100 text-blue-700" },
	"3": { label: ctrans("Slow"), class: "bg-amber-100 text-amber-700" },
	"4": { label: ctrans("Very slow"), class: "bg-gray-100 text-gray-600" },
}

const selected = reactive<Record<number, number>>({})
const edited = reactive<Record<number, number>>({})

const unitsFor = (row: Row) => edited[row.artefact_id] ?? Number(row.job_units)
const step = (row: Row) => Number(row.quantum_units) || 1

function toggle(row: Row) {
	if (row.artefact_id in selected) {
		delete selected[row.artefact_id]
	} else {
		selected[row.artefact_id] = unitsFor(row)
	}
}

function setUnits(row: Row, value: number) {
	const units = Math.max(step(row), Math.ceil((Number(value) || 0) / step(row)) * step(row))
	edited[row.artefact_id] = units
	if (row.artefact_id in selected) {
		selected[row.artefact_id] = units
	}
}

const batchesOf = (row: Row) =>
	row.batch_size ? Math.ceil(unitsFor(row) / Number(row.batch_size)) : null

const pageRows = computed(() => props.data.data)
const allSelected = computed(
	() => pageRows.value.length > 0 && pageRows.value.every((row) => row.artefact_id in selected)
)

function toggleAll() {
	if (allSelected.value) {
		pageRows.value.forEach((row) => delete selected[row.artefact_id])
	} else {
		pageRows.value.forEach((row) => (selected[row.artefact_id] = unitsFor(row)))
	}
}

const selectedCount = computed(() => Object.keys(selected).length)
const selectedUnits = computed(() =>
	Object.values(selected).reduce((total, units) => total + Number(units), 0)
)

const isHighSuggestion = (row: Row) => {
	const limit = Number(row.annual_units) > 0 ? Number(row.annual_units) : 1000
	return unitsFor(row) > limit
}

const salesDelta = (row: Row): number | null => {
	const previous = Number(row.sales_ly ?? 0)
	return previous ? Math.round(((Number(row.sales ?? 0) - previous) / previous) * 100) : null
}

function filtersFromUrl(): Record<string, string[]> {
	const params = new URLSearchParams(window.location.search)
	return Object.fromEntries(
		Object.entries(props.filters).map(([key, group]) => {
			const value = params.has(`elements[${key}]`)
				? params.get(`elements[${key}]`)
				: group.default
			return [key, value ? value.split(",") : []]
		})
	)
}

const activeFilters = ref<Record<string, string[]>>(filtersFromUrl())

function applyFilters(next: Record<string, string[]>) {
	activeFilters.value = { ...activeFilters.value, ...next }
	const params = new URLSearchParams(window.location.search)
	params.delete("page")
	Object.entries(activeFilters.value).forEach(([key, values]) => {
		params.set(`elements[${key}]`, values.join(","))
	})
	router.get(
		`${window.location.pathname}?${params}`,
		{},
		{ preserveState: true, preserveScroll: true, replace: true }
	)
}

function toggleFilter(key: string, value: string) {
	const current = activeFilters.value[key]
	applyFilters({
		[key]: current.includes(value) ? current.filter((v) => v !== value) : [...current, value],
	})
}

const categoryOptions = computed(() =>
	(props.filters.category?.options ?? []).map((option) => ({
		label: `${option.label} (${option.count})`,
		value: option.value,
	}))
)

function clearFilters() {
	applyFilters(Object.fromEntries(Object.keys(props.filters).map((key) => [key, []])))
}

const toneColor: Record<string, string> = {
	"red-deep": "#b91c1c",
	red: "#ef4444",
	orange: "#f97316",
	amber: "#fbbf24",
	yellow: "#fde047",
	green: "#16a34a",
	blue: "#3b82f6",
	violet: "#8b5cf6",
	gray: "#9ca3af",
}

const URGENT = ["out", "w1", "w2"]
const isUrgent = (level: Level) => URGENT.includes(level.bucket)
const notSent = (level: Level) => level.count - level.in_production - level.late
const coverTotal = computed(() => props.cover.reduce((sum, level) => sum + level.count, 0))
const share = (count: number) =>
	coverTotal.value ? `${((count / coverTotal.value) * 100).toFixed(1)}%` : "0%"

const coverSummary = computed(() => {
	const urgent = props.cover.filter(isUrgent)
	const sum = (pick: (level: Level) => number) =>
		urgent.reduce((total, level) => total + pick(level), 0)
	return {
		urgent: sum((level) => level.count),
		toMakeNow: sum(notSent),
		late: sum((level) => level.late),
		inProduction: sum((level) => level.in_production),
	}
})

const urgentParts = computed(() => [
	{
		key: "make",
		value: coverSummary.value.toMakeNow,
		label: ctrans("to make now"),
		bar: "bg-amber-400",
		text: "text-amber-700",
		tooltip: ctrans("Nothing on the To produce board"),
	},
	{
		key: "late",
		value: coverSummary.value.late,
		label: ctrans("late"),
		bar: "bg-red-400",
		text: "text-red-700",
		tooltip: ctrans("On the To produce board past its needed-by date, or waiting longer than the lead time"),
	},
	{
		key: "production",
		value: coverSummary.value.inProduction,
		label: ctrans("in production"),
		bar: "bg-blue-400",
		text: "text-blue-700",
		tooltip: ctrans("On the To produce board and on schedule"),
	},
])

const waitingDays = (level: Level) => {
	if (level.days_min === null || level.days_max === null) return ""
	return level.days_min === level.days_max
		? `${level.days_min}d`
		: `${level.days_min}–${level.days_max}d`
}

const sending = reactive<Record<number, boolean>>({})
const sendingAll = ref(false)

const sentCards = reactive<Record<number, SentCard>>({})
const artisans = ref<Artisan[]>([])
const fading = reactive<Record<number, boolean>>({})
const gone = reactive<Record<number, boolean>>({})

const pinned = reactive<Record<number, { row: Row; index: number }>>({})

const tableResource = computed(() => {
	const rows = props.data.data.filter((row) => !gone[row.artefact_id])
	Object.values(pinned)
		.sort((a, b) => a.index - b.index)
		.forEach(({ row, index }) => {
			if (!gone[row.artefact_id] && !rows.some((current) => current.artefact_id === row.artefact_id)) {
				rows.splice(Math.min(index, rows.length), 0, row)
			}
		})
	return { ...props.data, data: rows }
})

function takeSent(page: { props: Record<string, any> }) {
	const sent = page.props.sent as { cards: Record<number, SentCard>; artisans: Artisan[] } | undefined
	if (!sent) return
	Object.assign(sentCards, sent.cards)
	if (sent.artisans.length) artisans.value = sent.artisans
	const url = new URL(window.location.href)
	if (url.searchParams.has("sent")) {
		url.searchParams.delete("sent")
		window.history.replaceState(window.history.state, "", url)
	}
}

const routeParams = () => [route().params["organisation"], route().params["production"]]

function post(lines: { artefact_id: number; units: number }[], onDone: () => void, bulk = false) {
	lines.forEach((line) => {
		const index = props.data.data.findIndex((row) => row.artefact_id === line.artefact_id)
		if (index !== -1) pinned[line.artefact_id] = { row: props.data.data[index], index }
	})
	router.post(
		route("grp.org.productions.show.to_restock.queue", routeParams()),
		{ lines },
		{
			preserveScroll: true,
			preserveState: true,
			only: ["sent", "flash"],
			onStart: () => {
				if (bulk) sendingAll.value = true
				lines.forEach((line) => (sending[line.artefact_id] = true))
			},
			onFinish: () => {
				sendingAll.value = false
				lines.forEach((line) => delete sending[line.artefact_id])
			},
			onSuccess: () => {
				router.reload({
					data: { sent: lines.map((line) => line.artefact_id).join(",") },
					only: ["sent"],
					preserveScroll: true,
					preserveState: true,
					onSuccess: takeSent,
				})
				onDone()
			},
			onError: () => lines.forEach((line) => delete pinned[line.artefact_id]),
		}
	)
}

const picking = ref<{ card: SentCard; x: number; y: number } | null>(null)

function openPicker(card: SentCard, event: MouseEvent) {
	picking.value = {
		card,
		x: Math.max(8, Math.min(event.clientX - 160, window.innerWidth - 330)),
		y: Math.max(8, Math.min(event.clientY + 8, window.innerHeight - 420)),
	}
}

function changeQuantity(card: SentCard, quantity: number) {
	router.post(
		route("grp.org.productions.show.to_produce.items.preparing", routeParams()),
		{ preparing: true, lines: [{ id: card.id, quantity }] },
		{
			preserveScroll: true,
			preserveState: true,
			only: ["sent"],
			onSuccess: takeSent,
		}
	)
}

function assign(artisanId: number) {
	if (!picking.value) return
	const { card } = picking.value
	const artisan = artisans.value.find((candidate) => candidate.id === artisanId)
	picking.value = null
	router.post(
		route("grp.org.productions.show.to_produce.job_orders.store", routeParams()),
		{ ids: [card.id], employee_id: artisanId },
		{
			preserveScroll: true,
			preserveState: true,
			only: ["sent"],
			onSuccess: () => {
				notify({
					title: `${card.stock_code}: ${Math.ceil(Number(card.quantity_to_produce ?? card.quantity))} ${ctrans("assigned to")} ${artisan?.name ?? ""}`,
					text: `${ctrans("Open board")}: ${route("grp.org.productions.show.to_produce.index", routeParams())}`,
					type: "success",
				})
				fading[card.artefact_id] = true
				setTimeout(() => (gone[card.artefact_id] = true), 1000)
			},
		}
	)
}

function send() {
	post(
		Object.entries(selected).map(([id, units]) => ({ artefact_id: Number(id), units })),
		() => {
			for (const key in selected) delete selected[key]
			for (const key in edited) delete edited[key]
		},
		true
	)
}

function sendOne(row: Row) {
	post([{ artefact_id: row.artefact_id, units: unitsFor(row) }], () => {
		delete selected[row.artefact_id]
		delete edited[row.artefact_id]
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="mx-4 mt-4 space-y-3 text-sm">
		<div class="text-gray-600">
			{{ ctrans("Lead time") }}:
			<span class="font-semibold tabular-nums text-gray-900">{{
				ctrans(":count days", { count: leadTime.days })
			}}</span>
		</div>

		<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<div
				v-if="coverSummary.urgent"
				class="mb-3 flex items-center gap-x-2.5 whitespace-nowrap text-xs tabular-nums text-gray-500">
				<span v-tooltip="ctrans('Out of stock, Runs out before we can make more and Running low')"
					><span class="font-semibold text-gray-700">{{
						locale.number(coverSummary.urgent)
					}}</span>
					{{ ctrans("urgent") }}</span
				>
				<span class="flex h-1.5 w-20 shrink-0 overflow-hidden rounded-full bg-gray-100">
					<span
						v-for="part in urgentParts"
						:key="part.key"
						:class="part.bar"
						:style="{ width: (part.value / coverSummary.urgent) * 100 + '%' }" />
				</span>
				<span
					v-for="part in urgentParts"
					:key="part.key"
					v-tooltip="part.tooltip"
					class="inline-flex items-center gap-1">
					<span class="h-2 w-2 rounded-full" :class="part.bar" />
					<span class="font-medium" :class="part.text">{{
						locale.number(part.value)
					}}</span>
					{{ part.label }}
				</span>
			</div>
			<div class="overflow-x-auto">
				<table class="w-full min-w-0 text-[13px] tabular-nums">
					<thead>
						<tr class="border-b border-gray-200 text-[11px] text-gray-400">
							<th />
							<th class="pb-1 pr-2 text-right font-normal">{{ ctrans("Products") }}</th>
							<th class="border-l border-gray-200 pb-1 pl-3 pr-2 text-right font-normal">
								{{ ctrans("In production") }}
							</th>
							<th class="border-l border-gray-200 pb-1 pl-3 pr-1.5 text-right font-normal">
								{{ ctrans("Not sent") }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="level in cover"
							:key="level.bucket"
							class="group cursor-pointer border-b border-gray-100 last:border-b-0"
							@click="applyFilters({ state: [level.bucket] })">
							<td class="whitespace-nowrap rounded-l-md py-1 pl-1.5 pr-2 leading-snug group-hover:bg-gray-50">
								<span class="flex items-center gap-1.5">
									<span
										class="h-3 w-3 shrink-0 rounded-sm"
										:style="{ backgroundColor: toneColor[level.tone] ?? '#9ca3af' }" />
									{{ level.label }}
								</span>
							</td>
							<td class="whitespace-nowrap py-1 pr-2 text-right group-hover:bg-gray-50">
								<span class="font-medium">{{ locale.number(level.count) }}</span>
								<span class="ml-1.5 inline-block w-11 text-xs text-gray-400">{{
									share(level.count)
								}}</span>
							</td>
							<td class="whitespace-nowrap border-l border-gray-200 py-1 pl-3 pr-2 text-right text-[12px] group-hover:bg-gray-50">
								<span
									v-if="level.in_production || level.late"
									class="inline-flex items-center justify-end">
									<span
										v-tooltip="ctrans('Past the needed-by date, or waiting longer than the lead time')"
										class="inline-flex w-10 items-center justify-end gap-0.5 text-red-600">
										<template v-if="level.late"
											><FontAwesomeIcon
												icon="fal fa-exclamation-triangle"
												class="text-[10px]"
												aria-hidden="true" />{{ locale.number(level.late) }}</template
										>
									</span>
									<span
										v-tooltip="ctrans('Queued on the To produce board, no job order yet')"
										class="inline-flex w-10 items-center justify-end gap-0.5 text-gray-400">
										<template v-if="level.on_board"
											><FontAwesomeIcon
												icon="fal fa-clipboard-list"
												class="text-[10px]"
												aria-hidden="true" />{{ level.on_board }}</template
										>
									</span>
									<span
										v-tooltip="ctrans('On the floor, with a job order')"
										class="inline-flex w-10 items-center justify-end gap-0.5 text-gray-400">
										<template v-if="level.on_floor"
											><FontAwesomeIcon
												icon="fal fa-industry"
												class="text-[10px]"
												aria-hidden="true" />{{ level.on_floor }}</template
										>
									</span>
									<span class="inline-block w-16 text-right text-gray-400">{{ waitingDays(level) }}</span>
									<span class="inline-block w-10 text-right text-[13px] font-medium text-blue-700">{{
										level.in_production + level.late
											? locale.number(level.in_production + level.late)
											: ""
									}}</span>
								</span>
							</td>
							<td
								class="whitespace-nowrap rounded-r-md border-l border-gray-200 py-1 pl-3 pr-1.5 text-right group-hover:bg-gray-50"
								:class="isUrgent(level) ? 'text-amber-700' : 'text-gray-400'">
								<span :class="{ 'font-medium': isUrgent(level) }">{{
									locale.number(notSent(level))
								}}</span>
								<span class="ml-1.5 inline-block w-11 text-xs opacity-70">{{
									share(notSent(level))
								}}</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="mx-4 mt-4 text-sm">
		<div
			class="flex items-start gap-4 rounded-lg border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900">
			<div class="flex-1 space-y-2">
				<div v-for="(group, key) in filters" :key="key" class="flex items-center gap-3">
					<span
						class="w-24 shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400"
						>{{ group.label }}</span
					>
					<div v-if="key === 'category'" class="w-72">
						<PureMultiselect
							mode="multiple"
							:searchable="true"
							:options="categoryOptions"
							:modelValue="activeFilters.category"
							:placeholder="ctrans('All categories')"
							@update:modelValue="(values: string[]) => applyFilters({ category: values })" />
					</div>
					<div v-else class="flex flex-wrap items-center gap-1.5">
						<button
							v-for="option in group.options"
							:key="option.value"
							type="button"
							v-tooltip="option.tooltip"
							class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
							:class="
								activeFilters[key].includes(option.value)
									? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
									: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'
							"
							@click="toggleFilter(key, option.value)">
							<span
								v-if="option.tone"
								class="h-2 w-2 rounded-full"
								:style="{ backgroundColor: toneColor[option.tone] ?? '#9ca3af' }" />
							<span>{{ option.label }}</span>
							<span
								class="rounded-full px-1.5 text-xs tabular-nums"
								:class="
									activeFilters[key].includes(option.value)
										? 'bg-white/20'
										: 'bg-white text-gray-500'
								"
								>{{ option.count }}</span
							>
						</button>
					</div>
				</div>
			</div>
			<button
				v-if="Object.values(activeFilters).some((values) => values.length)"
				type="button"
				class="shrink-0 text-xs text-gray-400 hover:text-gray-600"
				@click="clearFilters">
				× {{ ctrans("Clear") }}
			</button>
		</div>
	</div>

	<div
		v-if="selectedCount"
		class="sticky top-0 z-10 mx-4 mt-3 flex items-center justify-between rounded-lg border border-gray-200 border-l-4 border-l-indigo-500 bg-white px-4 py-2 text-sm text-gray-700 shadow-sm dark:bg-gray-900">
		<span>{{ ctrans(":count selected", { count: selectedCount }) }}</span>
		<Button
			type="white-w-outline"
			size="s"
			icon="fal fa-clipboard-list"
			iconRight="fal fa-arrow-right"
			class="font-semibold hover:!border-indigo-400 hover:!text-indigo-700"
			:disabled="sendingAll"
			:label="
				ctrans('Send :count to Prepare (≈ :units units)', {
					count: selectedCount,
					units: locale.number(selectedUnits),
				})
			"
			@click="send" />
	</div>

	<Table :resource="tableResource" class="mt-3">
		<template #header(pick)>
			<th scope="col" class="px-2 text-left font-normal lg:px-6">
				<input
					type="checkbox"
					class="align-middle"
					:checked="allSelected"
					:title="ctrans('Select every product on this page')"
					@change="toggleAll" />
			</th>
		</template>
		<template #cell(pick)="{ item }: { item: Row }">
			<input
				type="checkbox"
				class="align-middle"
				:checked="item.artefact_id in selected"
				@change="toggle(item)" />
		</template>
		<template #cell(stock_code)="{ item }: { item: Row }">
			<span class="font-medium">{{ item.stock_code }}</span>
		</template>
		<template #cell(info)="{ item }: { item: Row }">
			<div
				class="flex items-start gap-x-2 transition-opacity duration-1000"
				:class="fading[item.artefact_id] ? 'opacity-0' : ''">
				<Image
					v-if="item.image"
					:src="item.image"
					class="mt-0.5 aspect-square w-9 shrink-0 overflow-hidden rounded shadow" />
				<div v-else class="mt-0.5 aspect-square w-9 shrink-0 rounded bg-gray-100" />
				<div class="flex flex-col gap-y-0.5">
					<span class="font-medium">{{ item.stock_name }}</span>
					<span class="text-xs text-gray-500">
						{{ item.stock_code }}
						<template v-if="Number(item.packed_in) > 1"
							>· {{ ctrans("packed in :count", { count: item.packed_in }) }}</template
						>
						<template v-if="item.category">· {{ item.category }}</template>
					</span>
					<span v-if="item.sales !== null" class="text-xs tabular-nums text-gray-500">
						{{ ctrans("Last 12 months") }}:
						<span class="font-medium text-gray-700">{{
							currencyLocale.currencyFormat(item.currency_code, Number(item.sales))
						}}</span>
						· {{ locale.number(Number(item.customers)) }} {{ ctrans("customers") }}
						<span
							v-if="salesDelta(item) !== null"
							class="ml-1 font-medium"
							:class="salesDelta(item)! >= 0 ? 'text-green-600' : 'text-red-600'"
							:title="ctrans('vs same period a year earlier')">
							{{ salesDelta(item)! >= 0 ? "+" : "" }}{{ salesDelta(item) }}%
							{{ salesDelta(item)! >= 0 ? "▲" : "▼" }}
						</span>
					</span>
				</div>
			</div>
		</template>
		<template #cell(sells)="{ item }: { item: Row }">
			<span
				class="rounded-full px-2 py-0.5 text-xs"
				:class="sellsBadge[String(item.sells_rank)]?.class"
				v-tooltip="ctrans('Ranked on the last 12 months of sales')">
				{{ sellsBadge[String(item.sells_rank)]?.label }}
			</span>
		</template>
		<template #cell(stock_available)="{ item }: { item: Row }">
			<span class="tabular-nums">{{ locale.number(Number(item.stock_available ?? 0)) }}</span>
		</template>
		<template #cell(lasts)="{ item }: { item: Row }">
			<span v-if="Number(item.stock_available) <= 0" class="font-medium text-red-600">{{
				ctrans("Out")
			}}</span>
			<span v-else-if="item.days_of_cover != null" class="tabular-nums">{{
				Number(item.days_of_cover) < 1
					? ctrans("under 1 day")
					: Math.round(Number(item.days_of_cover)) === 1
						? ctrans("1 day")
						: ctrans(":count days", { count: Math.round(Number(item.days_of_cover)) })
			}}</span>
		</template>
		<template #cell(in_production)="{ item }: { item: Row }">
			<span class="tabular-nums text-gray-600">{{
				Number(item.in_production) ? locale.number(Number(item.in_production)) : "-"
			}}</span>
		</template>
		<template #cell(job_units)="{ item }: { item: Row }">
			<span
				class="tabular-nums"
				:class="isHighSuggestion(item) ? 'font-semibold text-amber-600' : ''"
				:title="
					isHighSuggestion(item)
						? ctrans('Unusually high suggestion, check before sending')
						: ''
				">
				{{ locale.number(Number(item.job_units)) }}
			</span>
		</template>
		<template #header(make)>
			<th
				scope="col"
				class="px-2 text-right font-normal lg:px-6"
				v-tooltip="ctrans('Rounded up to whole batches')">
				{{ ctrans("Make (units)") }}
			</th>
		</template>
		<template #cell(make)="{ item }: { item: Row }">
			<ToProduceCard
				v-if="sentCards[item.artefact_id]"
				class="w-64 text-left transition-opacity duration-1000"
				:class="fading[item.artefact_id] ? 'opacity-0' : ''"
				:item="sentCards[item.artefact_id]"
				@change-quantity="(quantity) => changeQuantity(sentCards[item.artefact_id], quantity)"
				@open="(event) => openPicker(sentCards[item.artefact_id], event)" />
			<div
				v-else-if="pinned[item.artefact_id]"
				class="w-64 rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-left text-xs text-amber-800">
				{{ ctrans("Sent, but not in Preparing. Open the To produce board to finish it.") }}
			</div>
			<div v-else class="flex flex-col items-end gap-1">
				<NumberWithButtonSave
					:key="`${item.artefact_id}-${unitsFor(item)}`"
					isWithRefreshModel
					:modelValue="unitsFor(item)"
					:min="step(item)"
					:bindToTarget="{ step: step(item), min: step(item) }"
					noUndoButton
					noSaveButton
					@update:modelValue="(value: number) => setUnits(item, value)" />
				<Button
					type="white-w-outline"
					size="xs"
					icon="fal fa-clipboard-list"
					iconRight="fal fa-arrow-right"
					class="whitespace-nowrap hover:!border-indigo-400 hover:!text-indigo-700"
					:disabled="sending[item.artefact_id]"
					:label="ctrans('Send to Prepare')"
					@click="sendOne(item)" />
				<div v-if="item.batch_size" class="text-xs text-gray-400">
					{{
						ctrans(":count batches of :size", {
							count: batchesOf(item),
							size: locale.number(Number(item.batch_size)),
						})
					}}
				</div>
			</div>
		</template>
	</Table>

	<Teleport to="body">
		<div v-if="picking" class="fixed inset-0 z-40" @click="picking = null" />
		<div
			v-if="picking"
			class="fixed z-50 w-80 rounded-lg border border-indigo-300 bg-white p-3 text-xs shadow-xl dark:bg-gray-900"
			:style="{ left: picking.x + 'px', top: picking.y + 'px' }">
			<div class="mb-1.5 font-medium">
				{{ picking.card.stock_code }}
				<span class="font-normal text-gray-500">{{ picking.card.stock_name }}</span>
			</div>
			<div class="mb-1 text-gray-500">
				{{
					ctrans("Who makes :count?", {
						count: Math.ceil(
							Number(picking.card.quantity_to_produce ?? picking.card.quantity)
						),
					})
				}}
			</div>
			<ArtisanPicker :artisans="artisans" @pick="assign" />
		</div>
	</Teleport>

</template>
