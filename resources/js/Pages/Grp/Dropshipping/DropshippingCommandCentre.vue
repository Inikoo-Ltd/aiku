<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUsers, faPlug, faCube, faLanguage, faCoins, faChartLine } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faUsers, faPlug, faCube, faLanguage, faCoins, faChartLine)

type RouteRef = { name: string; parameters: Record<string, string | number> }
interface Cell {
	value?: number | null
	amounts?: Record<string, number | null>
	broken?: number
	state?: "live" | "preview" | null
	readiness?: number | null
	channels?: number
	route?: RouteRef
}
interface Row { key: string; label: string; tooltip?: string | null; kind: "count" | "money" | "language"; cells: Record<string, Cell | null>; total: Cell | null }
interface Section { key: string; title: string; icon: string; rows: Row[] }
interface Shop { id: number; code: string; name: string; country_code: string | null; currency_code: string; route: RouteRef }
interface Overview { shops: Shop[]; currencies: string[]; headline: { path: string; label: string }[]; sections: Section[] }
interface Column { id: string; code: string; sub: string; currency: string; title?: string; route?: RouteRef; isTotal: boolean }

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	overview?: Overview
}>()

const locale = useLocaleStore()
const href = (target: RouteRef) => route(target.name, target.parameters)

const currencies = computed(() => props.overview?.currencies ?? [])
const columns = computed<Column[]>(() => [
	...(props.overview?.shops ?? []).map((shop) => ({
		id: String(shop.id),
		code: shop.code,
		sub: [shop.country_code, shop.currency_code].filter(Boolean).join(" · "),
		currency: shop.currency_code,
		title: shop.name,
		route: shop.route,
		isTotal: false,
	})),
	{ id: "total", code: ctrans("Total"), sub: currencies.value.join(" · "), currency: currencies.value[0], isTotal: true },
])

const pickedColumn = ref<string | null>(null)
const narrowColumn = computed({
	get: () => pickedColumn.value ?? (props.overview?.shops[0] ? String(props.overview.shops[0].id) : "total"),
	set: (value: string) => (pickedColumn.value = value),
})
const wide = computed(() => ((props.overview?.shops.length ?? 0) <= 3 ? { cell: "lg:table-cell", toggle: "lg:hidden" } : { cell: "xl:table-cell", toggle: "xl:hidden" }))
const columnClass = (column: Column) => [column.id === narrowColumn.value ? "" : `hidden ${wide.value.cell}`, column.isTotal ? "border-l border-gray-200 bg-gray-50 font-medium" : ""]

const visibleSections = computed(() => (props.overview?.sections ?? []).filter((section) => section.rows.length))
const cellOf = (row: Row, column: Column) => (column.isTotal ? row.total : row.cells[column.id])
const amountOf = (row: Row, cell: Cell, currency: string) => (row.kind === "money" ? cell.amounts?.[currency] : cell.value)
const figure = (row: Row, cell: Cell, currency: string) => (row.kind === "money" ? locale.currencyFormat(currency, cell.amounts?.[currency] ?? 0) : locale.number(cell.value ?? 0))
const otherCurrencies = (currency: string) => currencies.value.filter((code) => code !== currency)
const languageTooltip = (cell: Cell) =>
	[
		cell.readiness != null ? ctrans("Product names translated: :percent%", { percent: cell.readiness }) : null,
		cell.channels ? ctrans("Stores in this language: :count", { count: locale.number(cell.channels) }) : null,
	]
		.filter(Boolean)
		.join(" · ") || undefined

const tiles = computed(() =>
	(props.overview?.headline ?? []).map(({ path, label }, index) => {
		const [sectionKey, rowKey] = path.split(".")
		const section = props.overview?.sections.find((candidate) => candidate.key === sectionKey)
		return { path, label, lead: index === 0, section, row: section?.rows.find((row) => row.key === rowKey) }
	})
)
const split = (row: Row) =>
	columns.value.filter((column) => !column.isTotal && row.cells[column.id]).map((column) => ({ code: column.code, figure: figure(row, row.cells[column.id]!, column.currency) }))
const hasAmount = (cell: Cell, code: string) => cell.amounts?.[code] != null
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-4 px-4 py-4">
		<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
			<template v-if="!overview">
				<div v-for="index in 5" :key="index" class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
					<div class="skeleton h-10 rounded" />
				</div>
			</template>
			<div v-for="tile in tiles" v-else :key="tile.path" class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
				<template v-if="tile.row && tile.row.total">
					<div class="flex items-center gap-2 text-xs text-gray-500">
						<FontAwesomeIcon :icon="tile.section!.icon" fixed-width aria-hidden="true" />
						<span class="truncate">{{ tile.label }}</span>
					</div>
					<div class="mt-1 text-sm tabular-nums" :class="tile.lead ? 'font-semibold text-gray-900' : 'font-medium text-gray-700'">
						{{ figure(tile.row, tile.row.total, currencies[0]) }}
						<template v-if="tile.row.kind === 'money'">
							<span v-for="code in otherCurrencies(currencies[0]).filter((code) => hasAmount(tile.row!.total!, code))" :key="code" class="ml-1 text-xs font-normal text-gray-500">{{ figure(tile.row, tile.row.total, code) }}</span>
						</template>
						<span v-if="tile.row.total.broken" class="ml-1 text-xs font-normal text-red-600">{{ ctrans(":count not working", { count: locale.number(tile.row.total.broken) }) }}</span>
					</div>
					<div class="mt-0.5 flex flex-wrap gap-x-2 text-xs tabular-nums text-gray-500">
						<span v-for="part in split(tile.row)" :key="part.code" class="whitespace-nowrap">{{ part.code }} {{ part.figure }}</span>
					</div>
				</template>
			</div>
		</div>

		<div v-if="overview" :class="wide.toggle">
			<SegmentedToggle v-model="narrowColumn" :options="columns.map((column) => ({ label: column.code, value: column.id }))" :aria-label="ctrans('Shop')" />
		</div>

		<div class="rounded-lg border border-gray-200 bg-white shadow-sm">
			<div v-if="!overview" class="space-y-2 p-4">
				<div v-for="line in 8" :key="line" class="skeleton h-5 rounded" />
			</div>
			<table v-else class="w-full table-fixed text-sm tabular-nums">
				<thead class="whitespace-nowrap text-right align-bottom">
					<tr>
						<th />
						<th v-for="column in columns" :key="column.id" class="w-36 px-3 py-2 font-medium text-gray-600" :class="columnClass(column)">
							<Link v-if="column.route" :href="href(column.route)" v-tooltip="column.title" class="hover:underline">{{ column.code }}</Link>
							<span v-else>{{ column.code }}</span>
							<div class="text-xs font-normal text-gray-500">{{ column.sub }}</div>
						</th>
					</tr>
				</thead>

				<tbody v-for="(section, sectionIndex) in visibleSections" :key="section.key">
					<tr>
						<th :colspan="columns.length + 1" scope="colgroup" class="px-4 pb-1.5 pt-4 text-left text-sm font-semibold text-gray-600" :class="{ 'border-t border-gray-200': sectionIndex > 0 }">
							<FontAwesomeIcon :icon="section.icon" class="mr-1.5 text-gray-400" fixed-width aria-hidden="true" />
							{{ section.title }}
						</th>
					</tr>

					<tr v-for="row in section.rows" :key="row.key" class="border-t border-gray-100 hover:bg-gray-50">
						<td class="px-4 py-1.5 text-gray-500">
							<span v-if="row.tooltip" v-tooltip="row.tooltip" class="cursor-help underline decoration-gray-300 decoration-dotted underline-offset-2">{{ row.label }}</span>
							<template v-else>{{ row.label }}</template>
						</td>
						<td v-for="column in columns" :key="column.id" class="whitespace-nowrap px-3 py-1.5 text-right text-gray-700" :class="columnClass(column)">
							<template v-for="cell in [cellOf(row, column)]" :key="column.id">
								<template v-if="cell && amountOf(row, cell, column.currency) != null">
									<span v-if="!amountOf(row, cell, column.currency)" v-tooltip="row.kind === 'language' ? languageTooltip(cell) : undefined" class="text-gray-400">{{ figure(row, cell, column.currency) }}</span>
									<Link v-else-if="cell.route" :href="href(cell.route)" v-tooltip="row.kind === 'language' ? languageTooltip(cell) : undefined" class="hover:underline">{{ figure(row, cell, column.currency) }}</Link>
									<span v-else>{{ figure(row, cell, column.currency) }}</span>
									<div v-if="cell.broken" class="text-xs font-normal text-red-600">{{ ctrans(":count not working", { count: locale.number(cell.broken) }) }}</div>
									<span v-if="cell.state === 'preview'" class="ml-1 text-xs text-amber-700">{{ ctrans("preview") }}</span>
									<template v-if="row.kind === 'money' && amountOf(row, cell, column.currency)">
										<div v-for="code in otherCurrencies(column.currency).filter((code) => hasAmount(cell, code))" :key="code" class="text-xs font-normal text-gray-500">{{ figure(row, cell, code) }}</div>
									</template>
								</template>
							</template>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>
