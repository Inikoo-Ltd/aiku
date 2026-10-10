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
import { faFilePdf, faSearch } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentDepositsEmptyState from "@/Components/Procurement/AgentDepositsEmptyState.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faSearch, faFilePdf)

interface InvoiceRow {
	id: number
	reference: string
	date: string
	currency_code: string
	total_amount: number
	paid_amount: number
	balance_due: number
	organisation: string | null
	pdf_route: routeType
	container: { reference: string; state: string; state_label: string; at_agent: boolean; route: routeType }
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	search: string
	filter: string
	filters: { label: string; value: string }[]
	data: {
		data: InvoiceRow[]
		current_page: number
		per_page: number
		total: number
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

const search = ref(props.search ?? "")
const filter = ref(props.filter || "all")

const filterOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.filters])
const isFiltered = computed(() => Boolean(search.value) || filter.value !== "all")

const query = (extra: Record<string, string | number> = {}) => ({
	...(search.value ? { search: search.value } : {}),
	...(filter.value !== "all" ? { filter: filter.value } : {}),
	...extra,
})

const visit = (parameters: Record<string, string | number>) =>
	router.get(route(route().current() as string, route().params), parameters, { preserveState: true, preserveScroll: true, replace: true })

watch(search, debounce(() => visit(query()), 400))
watch(filter, () => visit(query()))

const onPage = (event: { page: number }) => visit(query({ page: event.page + 1 }))
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="mx-4 mt-4 space-y-3">
			<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
				<IconField class="w-full sm:w-96">
					<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
					<InputText v-model="search" :placeholder="ctrans('Search by invoice or container reference')" class="w-full" />
				</IconField>
				<SegmentedToggle v-model="filter" :options="filterOptions" :aria-label="ctrans('Filter invoices')" />
			</div>

			<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
				<AgentDepositsEmptyState
					v-if="!data.data.length"
					icon="fal fa-file-invoice"
					:title="isFiltered ? ctrans('No invoices found') : ctrans('No invoices issued yet')"
					:filtered="isFiltered"
					:description="ctrans('An invoice is made for each container from its lines, plus your charges, before the container is dispatched. Open a container to make its invoice; it appears here with what has been paid towards it.')" />
				<table v-else class="min-w-full divide-y divide-gray-200 text-sm">
					<thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
						<tr>
							<th class="px-4 py-2">{{ ctrans("Reference") }}</th>
							<th class="px-4 py-2">{{ ctrans("Container") }}</th>
							<th class="px-4 py-2">{{ ctrans("Organisation") }}</th>
							<th class="px-4 py-2">{{ ctrans("Date") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Total") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Paid") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Balance due") }}</th>
							<th class="px-4 py-2">{{ ctrans("Container state") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in data.data" :key="row.id" class="hover:bg-gray-50">
							<td class="px-4 py-2.5">
								<a :href="route(row.pdf_route.name, row.pdf_route.parameters)" target="_blank" rel="noopener" class="inline-flex items-center gap-x-1.5 font-medium text-gray-900 hover:underline">
									<FontAwesomeIcon icon="fal fa-file-pdf" class="text-gray-400" fixed-width aria-hidden="true" />
									{{ row.reference }}
								</a>
							</td>
							<td class="px-4 py-2.5">
								<Link :href="route(row.container.route.name, row.container.route.parameters)" class="text-gray-900 hover:underline">
									{{ row.container.reference }}
								</Link>
							</td>
							<td class="px-4 py-2.5 text-gray-700">{{ row.organisation ?? "—" }}</td>
							<td class="px-4 py-2.5 text-gray-500">{{ useFormatTime(row.date) }}</td>
							<td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ locale.currencyFormat(row.currency_code, row.total_amount) }}</td>
							<td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ locale.currencyFormat(row.currency_code, row.paid_amount) }}</td>
							<td class="px-4 py-2.5 text-right font-semibold tabular-nums" :class="row.balance_due > 0 ? 'text-amber-700' : 'text-green-700'">
								{{ locale.currencyFormat(row.currency_code, row.balance_due) }}
							</td>
							<td class="px-4 py-2.5">
								<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="row.container.at_agent ? 'bg-amber-50 text-amber-700 ring-amber-600/20' : 'bg-gray-100 text-gray-600 ring-gray-500/20'">
									{{ row.container.state_label }}
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<Paginator v-if="data.total > data.per_page" :rows="data.per_page" :totalRecords="data.total" :first="(data.current_page - 1) * data.per_page" @page="onPage" />
		</div>
	</div>
</template>
