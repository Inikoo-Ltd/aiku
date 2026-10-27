<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { reactive, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import qs from "qs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTable, faThLarge, faInfoCircle } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableAgentWarehouseStocks from "@/Components/Tables/Grp/Org/Procurement/TableAgentWarehouseStocks.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faTable, faThLarge)

type FilterOption = { label: string; value: string; count: number; tooltip?: string }
type OrganisationCard = {
	code: string
	slug: string
	name: string
	country_code: string | null
	states: { state: string; label: string; lines: number; units: number }[]
	products: { code: string; name: string; state: string; quantity: number }[]
}

defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: object
	filterOptions: { state: FilterOption[]; organisation: FilterOption[] }
	cards: OrganisationCard[]
}>()

const locale = useLocaleStore()
const stateDotClass: Record<string, string> = {
	received: "bg-amber-500",
	packed_for_next_container: "bg-green-600",
	packed_for_later_container: "bg-gray-400",
}
const cardStateFilter = reactive<Record<string, string | null>>({})
const toggleCardState = (card: OrganisationCard, state: string) => {
	cardStateFilter[card.code] = cardStateFilter[card.code] === state ? null : state
}
const visibleProducts = (card: OrganisationCard) =>
	cardStateFilter[card.code] ? card.products.filter((product) => product.state === cardStateFilter[card.code]) : card.products

const tableUrl = (card: OrganisationCard, state: string) =>
	`${location.pathname}?${qs.stringify({ view: "table", filter: { state, organisation: card.slug } })}`

const viewOptions = [
	{ label: ctrans("Table"), value: "table", icon: "fal fa-table" },
	{ label: ctrans("Cards"), value: "cards", icon: "fal fa-th-large" },
]
const view = ref<string>(new URLSearchParams(location.search).get("view") ?? "table")
watch(view, () => {
	const url = new URL(location.href)
	url.searchParams.set("view", view.value)
	history.replaceState(history.state, "", url)
})

const currentQuery = () => qs.parse(location.search.substring(1)) as Record<string, any>
const state = ref<string | null>(currentQuery().filter?.state ?? null)
const organisation = ref<string | null>(currentQuery().filter?.organisation ?? null)

watch([state, organisation], () => {
	const query = currentQuery()
	query.filter = { ...(query.filter ?? {}), state: state.value || undefined, organisation: organisation.value || undefined }
	delete query.page
	router.get(`${location.pathname}?${qs.stringify(query, { skipNulls: true })}`, {}, { preserveState: true, preserveScroll: true, replace: true })
})
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<div class="mx-4 mt-3 flex gap-2 rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft] p-3 text-sm text-gray-700">
		<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-[--app-accent]" fixed-width aria-hidden="true" />
		<p>{{ ctrans("Goods in our warehouse, received from the suppliers and not in a container yet, by packing state. Quantities are in units.") }}</p>
	</div>
	<div class="flex flex-wrap gap-x-4 gap-y-2 px-4 pt-3">
		<SegmentedToggle v-model="view" :options="viewOptions" :ariaLabel="ctrans('View')" />
		<PillFilterBar :label="ctrans('AW company')" :options="filterOptions.organisation" :selected="organisation" @select="(value) => (organisation = value)" />
		<PillFilterBar :label="ctrans('State')" :options="filterOptions.state" :selected="state" @select="(value) => (state = value)" />
	</div>
	<TableAgentWarehouseStocks v-if="view === 'table'" :data="data" />
	<div v-else class="grid max-w-[120rem] gap-4 p-4 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
		<div v-if="!cards.length" class="text-gray-500">{{ ctrans("Nothing in the warehouse") }}</div>
		<div v-for="card in cards" :key="card.code" class="flex flex-col rounded-lg border border-gray-200 bg-white pb-3 shadow-sm">
			<div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
				<img v-if="card.country_code" :src="'/flags/' + card.country_code.toLowerCase() + '.png'" :alt="card.country_code" class="h-4 w-auto shrink-0" />
				<div class="min-w-0 flex-1">
					<div class="truncate font-semibold text-gray-900">{{ card.name }}</div>
					<div class="text-xs text-gray-500">{{ card.code.toUpperCase() }}</div>
				</div>
			</div>
			<table class="w-full table-fixed text-xs tabular-nums">
				<colgroup>
					<col class="w-32" />
					<col />
					<col class="w-16" />
					<col class="w-20" />
				</colgroup>
				<thead>
					<tr class="text-left text-gray-500 [&_th]:px-4 [&_th]:pb-1 [&_th]:pt-3 [&_th]:font-normal">
						<th colspan="2">{{ ctrans("In the warehouse") }}</th>
						<th class="text-right">{{ ctrans("Lines") }}</th>
						<th class="text-right">{{ ctrans("Units") }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="state in card.states" :key="state.state" class="cursor-pointer select-none hover:bg-gray-50 [&_td]:px-4 [&_td]:py-0.5"
						:class="[!state.lines ? 'text-gray-400' : '', cardStateFilter[card.code] === state.state ? 'bg-gray-100 font-semibold' : '', cardStateFilter[card.code] && cardStateFilter[card.code] !== state.state ? 'opacity-50' : '']"
						:title="cardStateFilter[card.code] === state.state ? ctrans('Click to show all') : ctrans('Click to show only these')"
						@click="toggleCardState(card, state.state)">
						<td colspan="2" class="truncate">
							<span class="mr-1.5 inline-block size-1.5 rounded-full align-middle" :class="stateDotClass[state.state]" />{{ state.label }}
						</td>
						<td class="text-right font-medium" :class="state.lines ? 'text-gray-900' : ''">
							<a v-if="state.lines" :href="tableUrl(card, state.state)" class="hover:underline" v-tooltip="ctrans('See these in the table')" @click.stop.prevent="router.visit(tableUrl(card, state.state))">{{ locale.number(state.lines) }}</a>
							<template v-else>0</template>
						</td>
						<td class="text-right" :class="state.lines ? 'text-gray-900' : ''">{{ locale.number(state.units) }}</td>
					</tr>
				</tbody>
				<thead class="sticky top-0 bg-white">
					<tr class="border-b border-gray-100 text-left text-gray-500 [&_th]:px-4 [&_th]:pb-1 [&_th]:pt-4 [&_th]:font-normal">
						<th>{{ ctrans(":count products", { count: locale.number(visibleProducts(card).length) }) }}</th>
						<th colspan="2">{{ ctrans("Name") }}</th>
						<th class="text-right">{{ ctrans("Units") }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(product, index) in visibleProducts(card)" :key="index" class="[&_td]:px-4 [&_td]:py-0.5">
						<td class="truncate">
							<span class="mr-1.5 inline-block size-1.5 rounded-full align-middle" :class="stateDotClass[product.state]" />{{ product.code }}
						</td>
						<td colspan="2" class="truncate text-gray-600" :title="product.name">{{ product.name }}</td>
						<td class="text-right text-gray-900">{{ locale.number(product.quantity) }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>
