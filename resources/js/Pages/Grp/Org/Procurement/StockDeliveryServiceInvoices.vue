<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Paginator from "primevue/paginator"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faPaperclip, faFileInvoiceDollar } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentDepositsEmptyState from "@/Components/Procurement/AgentDepositsEmptyState.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import type { ServiceInvoiceRow } from "@/Components/Procurement/StockDeliveryServiceInvoiceDialog.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faSearch, faPaperclip, faFileInvoiceDollar)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	filters: { search: string | null; type: string | null; paid: string | null; from: string | null; to: string | null }
	types: { label: string; value: string }[]
	org_currency: string
	can_edit: boolean
	data: {
		data: ServiceInvoiceRow[]
		current_page: number
		per_page: number
		total: number
	}
}>()

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"
const datePickerPt = { pcInputText: { root: { class: "!w-32" } } }

const fromIsoDate = (iso: string | null): Date | null => {
	if (!iso) return null
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toIsoDate = (date: Date | null) => (date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null)

const search = ref(props.filters.search ?? "")
const type = ref(props.filters.type)
const paid = ref(props.filters.paid ?? "all")
const from = ref(fromIsoDate(props.filters.from))
const to = ref(fromIsoDate(props.filters.to))

const typeOptions = computed(() => [{ label: ctrans("All types"), value: null }, ...props.types])
const paidOptions = [
	{ label: ctrans("All"), value: "all" },
	{ label: ctrans("Unpaid"), value: "unpaid" },
	{ label: ctrans("Paid"), value: "paid" },
]
const isFiltered = computed(() => Boolean(search.value || type.value || from.value || to.value) || paid.value !== "all")

const query = (extra: Record<string, string | number> = {}) =>
	Object.fromEntries(
		Object.entries({
			search: search.value || null,
			type: type.value,
			paid: paid.value !== "all" ? paid.value : null,
			from: toIsoDate(from.value),
			to: toIsoDate(to.value),
			...extra,
		}).filter(([, value]) => value !== null && value !== "")
	)

const visit = (parameters: Record<string, unknown>) =>
	router.get(route(route().current() as string, route().params), parameters as Record<string, string>, { preserveState: true, preserveScroll: true, replace: true })

watch(search, debounce(() => visit(query()), 400))
watch([type, paid, from, to], () => visit(query()))

const onPage = (event: { page: number }) => visit(query({ page: event.page + 1 }))

const togglePaid = (row: ServiceInvoiceRow) =>
	router.patch(route(row.paid_route.name, row.paid_route.parameters), { paid_at: row.paid_at ? null : new Date().toISOString().slice(0, 10) }, {
		preserveScroll: true,
		onError: (errors) => notify({ title: ctrans("Something went wrong"), text: Object.values(errors)[0], type: "error" }),
	})

const pageTotal = computed(() => props.data.data.reduce((sum, row) => sum + row.org_total_amount, 0))
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="mx-4 mt-4 space-y-3">
			<div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
				<div class="flex flex-col gap-3 sm:flex-row sm:items-center">
					<IconField class="w-full sm:w-80">
						<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
						<InputText v-model="search" :placeholder="ctrans('Search by issuer, invoice or container')" class="w-full" :class="fieldFocusClass" />
					</IconField>
					<Select v-model="type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full sm:w-44" :class="fieldFocusClass" :aria-label="ctrans('Type')" />
					<div class="flex items-center gap-2">
						<DatePicker v-model="from" :maxDate="to ?? undefined" dateFormat="dd/mm/yy" :manualInput="false" showIcon showClear iconDisplay="input" :placeholder="ctrans('From')" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('From')" />
						<DatePicker v-model="to" :minDate="from ?? undefined" dateFormat="dd/mm/yy" :manualInput="false" showIcon showClear iconDisplay="input" :placeholder="ctrans('To')" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('To')" />
					</div>
				</div>
				<SegmentedToggle v-model="paid" :options="paidOptions" :aria-label="ctrans('Filter by paid')" />
			</div>

			<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
				<AgentDepositsEmptyState
					v-if="!data.data.length"
					icon="fal fa-file-invoice-dollar"
					:title="isFiltered ? ctrans('No service invoices found') : ctrans('No service invoices yet')"
					:filtered="isFiltered"
					:description="ctrans('Freight, customs, import VAT and other bills paid here for the containers. Add them from the landed costs box of a container; one invoice can cover several containers.')" />
				<table v-else class="min-w-full divide-y divide-gray-200 text-sm">
					<thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
						<tr>
							<th class="px-4 py-2">{{ ctrans("Date") }}</th>
							<th class="px-4 py-2">{{ ctrans("Type") }}</th>
							<th class="px-4 py-2">{{ ctrans("Issued by") }}</th>
							<th class="px-4 py-2">{{ ctrans("Reference") }}</th>
							<th class="px-4 py-2">{{ ctrans("Containers") }}</th>
							<th class="px-4 py-2 text-right">{{ ctrans("Total") }}</th>
							<th class="px-4 py-2 text-right">{{ org_currency }}</th>
							<th class="px-4 py-2">{{ ctrans("Paid") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in data.data" :key="row.id" class="hover:bg-gray-50">
							<td class="whitespace-nowrap px-4 py-2.5 text-gray-500">{{ useFormatTime(row.date) }}</td>
							<td class="px-4 py-2.5"><span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ row.type_label }}</span></td>
							<td class="px-4 py-2.5 text-gray-900">{{ row.issuer }}</td>
							<td class="px-4 py-2.5 text-gray-700">
								{{ row.reference ?? "—" }}
								<a v-for="file in row.attachments" :key="file.ulid" :href="route('grp.media.download', { id: file.ulid })" class="ml-1 text-gray-500 hover:text-gray-700" :title="file.name" :aria-label="file.name">
									<FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
								</a>
							</td>
							<td class="px-4 py-2.5">
								<span v-for="(allocation, index) in row.allocations" :key="allocation.stock_delivery_id">
									<Link :href="route(allocation.route.name, allocation.route.parameters)" class="text-gray-900 hover:underline" :title="String(locale.currencyFormat(row.currency_code, allocation.amount))">{{ allocation.reference }}</Link><span v-if="index < row.allocations.length - 1">, </span>
								</span>
							</td>
							<td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-gray-800">{{ locale.currencyFormat(row.currency_code, row.total_amount) }}</td>
							<td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-gray-800">{{ locale.currencyFormat(org_currency, row.org_total_amount) }}</td>
							<td class="whitespace-nowrap px-4 py-2.5">
								<button
									type="button"
									class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
									:class="row.paid_at ? 'bg-green-50 text-green-700 ring-green-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20'"
									:disabled="!can_edit"
									:title="can_edit ? (row.paid_at ? ctrans('Mark as unpaid') : ctrans('Mark as paid')) : undefined"
									@click="togglePaid(row)"
								>
									{{ row.paid_at ? `${ctrans("Paid")} ${useFormatTime(row.paid_at)}` : ctrans("Unpaid") }}
								</button>
							</td>
						</tr>
					</tbody>
					<tfoot class="bg-gray-50 text-sm font-semibold text-gray-700">
						<tr>
							<td colspan="6" class="px-4 py-2 text-right">{{ ctrans("Total on this page") }}</td>
							<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">{{ locale.currencyFormat(org_currency, pageTotal) }}</td>
							<td />
						</tr>
					</tfoot>
				</table>
			</div>

			<Paginator v-if="data.total > data.per_page" :rows="data.per_page" :totalRecords="data.total" :first="(data.current_page - 1) * data.per_page" @page="onPage" />
		</div>
	</div>
</template>
