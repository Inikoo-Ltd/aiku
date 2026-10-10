<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Paginator from "primevue/paginator"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faChevronDown, faCheckCircle } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentDepositsEmptyState from "@/Components/Procurement/AgentDepositsEmptyState.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faSearch, faChevronDown, faCheckCircle)

interface RequestItem {
	id: number
	organisation: string
	deposit: string | null
	amount: number
	exchange: number
	paid_at: string | null
}

interface DepositRequestRow {
	id: number
	reference: string | null
	currency_code: string
	state: string
	state_label: string
	requested_at: string
	settled_at: string | null
	cancelled_at: string | null
	total: number
	outstanding: number
	number_items: number
	items: RequestItem[]
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	search: string
	state: string
	states: { label: string; value: string }[]
	data: {
		data: DepositRequestRow[]
		current_page: number
		per_page: number
		total: number
	}
}>()

const locale = inject("locale", aikuLocaleStructure)
const money = (code: string, amount: number) => locale.currencyFormat(code, amount)

const search = ref(props.search ?? "")
const state = ref(props.state || "all")
const expanded = ref<Set<number>>(new Set())

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

const toggle = (id: number) => {
	const next = new Set(expanded.value)
	next.has(id) ? next.delete(id) : next.add(id)
	expanded.value = next
}

const stateClasses: Record<string, string> = {
	requested: "bg-amber-50 text-amber-700 ring-amber-600/20",
	settled: "bg-green-50 text-green-700 ring-green-600/20",
	cancelled: "bg-red-50 text-red-700 ring-red-600/20",
}

const itemsLabel = (count: number) => (count === 1 ? ctrans("1 item") : ctrans(":count items", { count }))
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="mx-4 mt-4 space-y-3">
			<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
				<IconField class="w-full sm:w-96">
					<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
					<InputText v-model="search" :placeholder="ctrans('Search by reference')" class="w-full" />
				</IconField>
				<SegmentedToggle v-model="state" :options="stateOptions" :aria-label="ctrans('Filter by state')" />
			</div>

			<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
				<AgentDepositsEmptyState v-if="!data.data.length" :title="isFiltered ? ctrans('No deposit requests found') : ctrans('No deposit requests yet')" :filtered="isFiltered" />
				<ul v-else class="divide-y divide-gray-100">
					<li v-for="row in data.data" :key="row.id">
						<button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-gray-50" :aria-expanded="expanded.has(row.id)" @click="toggle(row.id)">
							<FontAwesomeIcon icon="fal fa-chevron-down" class="text-xs text-gray-400 transition" :class="{ '-rotate-90': !expanded.has(row.id) }" fixed-width aria-hidden="true" />
							<div class="min-w-0 flex-1">
								<div class="flex flex-wrap items-center gap-2">
									<span class="font-medium text-gray-900">{{ row.reference || ctrans("Request #:id", { id: row.id }) }}</span>
									<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="stateClasses[row.state]">{{ row.state_label }}</span>
								</div>
								<div class="mt-0.5 text-xs text-gray-500">
									{{ ctrans("Requested :date", { date: useFormatTime(row.requested_at) }) }}
									<template v-if="row.settled_at"> · {{ ctrans("Settled :date", { date: useFormatTime(row.settled_at) }) }}</template>
									<template v-else-if="row.cancelled_at"> · {{ ctrans("Cancelled :date", { date: useFormatTime(row.cancelled_at) }) }}</template>
									· {{ itemsLabel(row.number_items) }}
								</div>
							</div>
							<div class="text-right">
								<div class="font-semibold tabular-nums text-gray-800">{{ money(row.currency_code, row.total) }}</div>
								<div v-if="row.state === 'requested' && row.outstanding > 0" class="text-xs tabular-nums text-amber-600">
									{{ ctrans(":value outstanding", { value: money(row.currency_code, row.outstanding) }) }}
								</div>
							</div>
						</button>

						<table v-if="expanded.has(row.id)" class="min-w-full border-t border-gray-100 bg-gray-50/60 text-sm">
							<thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
								<tr>
									<th class="py-2 pl-12 pr-4">{{ ctrans("Organisation") }}</th>
									<th class="px-4 py-2">{{ ctrans("Deposit") }}</th>
									<th class="px-4 py-2 text-right">{{ ctrans("Amount") }}</th>
									<th class="px-4 py-2 text-right">{{ ctrans("Exchange") }}</th>
									<th class="px-4 py-2">{{ ctrans("Paid") }}</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
								<tr v-for="item in row.items" :key="item.id">
									<td class="py-2 pl-12 pr-4 text-gray-900">{{ item.organisation }}</td>
									<td class="px-4 py-2 text-gray-600">{{ item.deposit ?? "—" }}</td>
									<td class="px-4 py-2 text-right tabular-nums text-gray-800">{{ money(row.currency_code, item.amount) }}</td>
									<td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ item.exchange }}</td>
									<td class="px-4 py-2">
										<span v-if="item.paid_at" class="inline-flex items-center gap-1 text-green-600">
											<FontAwesomeIcon icon="fal fa-check-circle" fixed-width aria-hidden="true" />
											{{ useFormatTime(item.paid_at) }}
										</span>
										<span v-else class="text-amber-600">{{ ctrans("Not yet") }}</span>
									</td>
								</tr>
							</tbody>
						</table>
					</li>
				</ul>
			</div>

			<Paginator v-if="data.total > data.per_page" :rows="data.per_page" :totalRecords="data.total" :first="(data.current_page - 1) * data.per_page" @page="onPage" />
		</div>
	</div>
</template>
