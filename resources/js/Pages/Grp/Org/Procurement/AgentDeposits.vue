<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Paginator from "primevue/paginator"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentDepositsEmptyState from "@/Components/Procurement/AgentDepositsEmptyState.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faSearch)

interface DepositRow {
	id: number
	reference: string | null
	amount: number
	currency_code: string
	state: string
	state_label: string
	created_at: string
	paid_to_supplier_at: string | null
	refunded_at: string | null
	cancelled_at: string | null
	notes: string | null
	organisation: string | null
	purchase_order: { reference: string; route: routeType } | null
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	search: string
	state: string
	states: { label: string; value: string }[]
	data: {
		data: DepositRow[]
		current_page: number
		per_page: number
		total: number
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

const search = ref(props.search ?? "")
const state = ref(props.state || "all")

const stateOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.states])
const isFiltered = computed(() => Boolean(search.value) || state.value !== "all")

const query = (extra: Record<string, string | number> = {}) => ({
	...(search.value ? { search: search.value } : {}),
	...(state.value !== "all" ? { state: state.value } : {}),
	...extra,
})

const visit = (parameters: Record<string, string | number>) =>
	router.get(route(route().current() as string, route().params), parameters, { preserveState: true, preserveScroll: true, replace: true })

watch(search, debounce(() => visit(query()), 400))
watch(state, () => visit(query()))

const onPage = (event: { page: number }) => visit(query({ page: event.page + 1 }))

const stateClasses: Record<string, string> = {
	pending: "bg-amber-50 text-amber-700 ring-amber-600/20",
	paid_to_supplier: "bg-green-50 text-green-700 ring-green-600/20",
	refunded: "bg-gray-100 text-gray-600 ring-gray-500/20",
	cancelled: "bg-red-50 text-red-700 ring-red-600/20",
}

const stateDate = (row: DepositRow) => row.paid_to_supplier_at ?? row.refunded_at ?? row.cancelled_at
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="mx-4 mt-4 space-y-3">
			<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
				<IconField class="w-full sm:w-96">
					<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
					<InputText v-model="search" :placeholder="ctrans('Search by deposit or purchase order reference')" class="w-full" />
				</IconField>
				<SegmentedToggle v-model="state" :options="stateOptions" :aria-label="ctrans('Filter by state')" />
			</div>

			<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
				<AgentDepositsEmptyState v-if="!data.data.length" :title="isFiltered ? ctrans('No deposits found') : ctrans('No deposits recorded yet')" :filtered="isFiltered" />
				<table v-else class="min-w-full divide-y divide-gray-200 text-sm">
					<thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
						<tr>
							<th class="px-4 py-2">{{ ctrans("Reference") }}</th>
							<th class="px-4 py-2">{{ ctrans("Purchase order") }}</th>
							<th class="px-4 py-2">{{ ctrans("Organisation") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Amount") }}</th>
							<th class="px-4 py-2">{{ ctrans("State") }}</th>
							<th class="px-4 py-2">{{ ctrans("Recorded") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in data.data" :key="row.id" class="hover:bg-gray-50">
							<td class="px-4 py-2.5">
								<div class="font-medium text-gray-900">{{ row.reference || ctrans("Deposit #:id", { id: row.id }) }}</div>
								<div v-if="row.notes" class="max-w-xs truncate text-xs text-gray-500" :title="row.notes">{{ row.notes }}</div>
							</td>
							<td class="px-4 py-2.5">
								<Link v-if="row.purchase_order" :href="route(row.purchase_order.route.name, row.purchase_order.route.parameters)" class="text-gray-900 hover:underline">
									{{ row.purchase_order.reference }}
								</Link>
								<span v-else class="text-gray-400">—</span>
							</td>
							<td class="px-4 py-2.5 text-gray-700">{{ row.organisation ?? "—" }}</td>
							<td class="px-4 py-2.5 text-right font-semibold tabular-nums text-gray-800">{{ locale.currencyFormat(row.currency_code, row.amount) }}</td>
							<td class="px-4 py-2.5">
								<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="stateClasses[row.state]">{{ row.state_label }}</span>
								<div v-if="stateDate(row)" class="mt-0.5 text-xs text-gray-400">{{ useFormatTime(stateDate(row)!) }}</div>
							</td>
							<td class="px-4 py-2.5 text-gray-500">{{ useFormatTime(row.created_at) }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<Paginator v-if="data.total > data.per_page" :rows="data.per_page" :totalRecords="data.total" :first="(data.current_page - 1) * data.per_page" @page="onPage" />
		</div>
	</div>
</template>
