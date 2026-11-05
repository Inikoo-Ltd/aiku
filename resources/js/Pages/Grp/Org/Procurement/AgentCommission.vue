<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import DatePicker from "primevue/datepicker"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronRight, faFilePdf, faPercent } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faChevronDown, faChevronRight, faFilePdf, faPercent)

interface SupplierGroup {
	code: string
	name: string
	currency_code: string
	purchase_orders: { reference: string; organisation: string; shipped_at: string; products: number }[]
	products: number
	commission: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	month: string
	pdfRoute: routeType
	commission: {
		rate: number
		suppliers: SupplierGroup[]
		totals: { currency_code: string; products: number; commission: number }[]
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

const [year, monthNumber] = props.month.split("-").map(Number)
const month = ref<Date>(new Date(year, monthNumber - 1, 1))
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const onMonth = (value: Date | Date[] | (Date | null)[] | null | undefined) => {
	if (!(value instanceof Date)) {
		return
	}
	const selected = `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}`
	router.get(route(route().current() as string, route().params), { month: selected }, { preserveScroll: true, replace: true })
}

const expanded = ref<Set<string>>(new Set())
const groupKey = (group: SupplierGroup) => `${group.code}|${group.currency_code}`
const toggle = (group: SupplierGroup) => {
	const key = groupKey(group)
	expanded.value.has(key) ? expanded.value.delete(key) : expanded.value.add(key)
	expanded.value = new Set(expanded.value)
}

const rate = computed(() => `${(props.commission.rate * 100).toFixed(1)}%`)
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="mx-4 mt-4 space-y-3">
			<div class="flex flex-wrap items-center gap-3">
				<DatePicker
					v-model="month"
					view="month"
					dateFormat="MM yy"
					:manualInput="false"
					showIcon
					iconDisplay="input"
					:class="fieldFocusClass"
					:aria-label="ctrans('Month')"
					@update:modelValue="onMonth" />
				<span class="text-sm text-gray-500">{{ ctrans("Purchase orders shipped this month, :rate of products", { rate }) }}</span>
				<a :href="route(pdfRoute.name, pdfRoute.parameters)" target="_blank" rel="noopener" class="sm:ml-auto">
					<Button type="tertiary" icon="fal fa-file-pdf" :label="ctrans('PDF')" />
				</a>
			</div>

			<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
				<table class="min-w-full divide-y divide-gray-200 text-sm">
					<thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
						<tr>
							<th class="whitespace-nowrap px-4 py-2">{{ ctrans("Supplier") }}</th>
							<th class="whitespace-nowrap px-4 py-2">{{ ctrans("Organisation") }}</th>
							<th class="whitespace-nowrap px-4 py-2">{{ ctrans("Shipped") }}</th>
							<th class="whitespace-nowrap px-4 py-2 text-right">{{ ctrans("Products") }}</th>
							<th class="whitespace-nowrap px-4 py-2 text-right">{{ ctrans("Commission") }}</th>
						</tr>
					</thead>
					<tbody v-if="!commission.suppliers.length">
						<tr>
							<td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ ctrans("No purchase orders shipped this month") }}</td>
						</tr>
					</tbody>
					<tbody v-for="group in commission.suppliers" :key="groupKey(group)" class="divide-y divide-gray-100">
						<tr class="cursor-pointer hover:bg-gray-50" @click="toggle(group)">
							<td class="whitespace-nowrap px-4 py-2.5 font-medium text-gray-900">
								<FontAwesomeIcon :icon="expanded.has(groupKey(group)) ? 'fal fa-chevron-down' : 'fal fa-chevron-right'" class="mr-1.5 text-gray-400" fixed-width aria-hidden="true" />
								{{ group.code }} <span class="font-normal text-gray-500">{{ group.name }}</span>
							</td>
							<td class="px-4 py-2.5 text-gray-500">{{ group.purchase_orders.length }} {{ group.purchase_orders.length === 1 ? ctrans("PO") : ctrans("POs") }}</td>
							<td class="px-4 py-2.5"></td>
							<td class="whitespace-nowrap px-4 py-2.5 text-right font-medium tabular-nums text-gray-800">{{ locale.currencyFormat(group.currency_code, group.products) }}</td>
							<td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(group.currency_code, group.commission) }}</td>
						</tr>
						<template v-if="expanded.has(groupKey(group))">
							<tr v-for="purchaseOrder in group.purchase_orders" :key="purchaseOrder.reference" class="bg-gray-50/50">
								<td class="whitespace-nowrap py-2 pl-11 pr-4 text-gray-700">{{ purchaseOrder.reference }}</td>
								<td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ purchaseOrder.organisation }}</td>
								<td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ useFormatTime(purchaseOrder.shipped_at) }}</td>
								<td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-700">{{ locale.currencyFormat(group.currency_code, purchaseOrder.products) }}</td>
								<td class="px-4 py-2"></td>
							</tr>
						</template>
					</tbody>
					<tfoot v-if="commission.totals.length" class="border-t-2 border-gray-200">
						<tr v-for="total in commission.totals" :key="total.currency_code">
							<td colspan="3" class="px-4 py-2.5 text-right font-semibold text-gray-700">{{ ctrans("Total") }} {{ total.currency_code }}</td>
							<td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(total.currency_code, total.products) }}</td>
							<td class="whitespace-nowrap px-4 py-2.5 text-right font-bold tabular-nums text-gray-900">{{ locale.currencyFormat(total.currency_code, total.commission) }}</td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>
	</div>
</template>
