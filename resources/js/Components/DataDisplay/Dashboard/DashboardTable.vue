<script setup lang="ts">
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Row from "primevue/row"
import ColumnGroup from "primevue/columngroup"
import { ref, computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import { Link } from "@inertiajs/vue3"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import DeltaItemDashboard from "../../Utils/DeltaItemDashboard.vue"
import LabelItemDashboard from "@/Components/Utils/LabelItemDashboard.vue"

const props = defineProps<{
	tableData: {}[]
	locale: any
	totalAmount: {
		total_invoices: number
		total_sales: number
		total_refunds: number
	}
	total_tooltip: {
		total_sales: string
		total_invoices: string
		total_refunds: string
	}
	currency_code?: string
	tableType?: string
	current?: string
	settings: {}
	dashboardTable: {
		tab_label: string
		tab_slug: string
		type: string // 'table'
		data: {}
	}[]
}>()

function ShopDashboard(shop: any) {
	return route(shop?.route?.name, shop?.route?.parameters)
}

const activeIndexTab = ref(props.current)

const selectedTab = computed(() => {
	return props.dashboardTable.find((tab) => tab.tab_slug === activeIndexTab.value)
})
function useTabChangeDashboard(tab_slug: string) {
	if (tab_slug === activeIndexTab.value) {
		return
	}

	router.reload({
		data: { tab_dashboard_interval: tab_slug },
		// only: ['dashboard_stats'],
		onSuccess: () => {
			activeIndexTab.value = tab_slug
		},
		onError: (error) => {
			console.error("Error reloading dashboard:", error)
		},
	})
}
console.log('ewqewqewq', selectedTab.value.data)

const listColumnInTable = computed(() => {
	
	const resultSet = new Set();  // Create a Set to store unique elements

    // Iterate through each sub-array in the input array
    selectedTab.value?.data?.map((e) => Object.keys(e.interval_percentages || {}))?.forEach(subArray => {
        // Add each element of the sub-array to the Set
        subArray.forEach(item => resultSet.add(item));
    });

    // Convert the Set back to an Array to return the result
    return Array.from(resultSet);
})
</script>

<template>
	<div class="bg-white mb-3 p-4 shadow-md border border-gray-200">
		<div class="">
			<Tabs :value="activeIndexTab" class="overflow-x-auto text-sm md:text-base pb-2">
				<TabList>
					<Tab
						v-for="tab in dashboardTable"
						@click="() => useTabChangeDashboard(tab.tab_slug)"
						:key="tab.tab_slug"
						:value="tab.tab_slug"
						class="qwezxc">
						<FontAwesomeIcon
							:icon="tab.tab_icon"
							class=""
							fixed-width
							aria-hidden="true" />
						{{ tab.tab_label }}</Tab
					>
				</TabList>
			</Tabs>

			<DataTable v-if="selectedTab.type === 'table'" :value="selectedTab.data" removableSort>
				<template #empty>
					<div class="flex items-center justify-center h-full text-center">
						No data available.
					</div>
				</template>

				<!-- Code -->
				<Column sortable field="code">
					<template #header>
						<div class="text-xs md:text-base flex items-center justify-between">
							<span class="">{{ trans("Name") }}</span>
						</div>
					</template>

					<template #body="{ data }" v-if="tableType == 'org' || current == 'shops'">
						<div class="relative">
							<Transition name="spin-to-down" mode="out-in">
								<div :key="data.name">
									<Link
										v-if="data.route"
										:href="ShopDashboard(data)"
										class="hover-underline text-sm">
										{{ data.name }}
									</Link>
									<span v-else class="text-sm">
										{{ data.name }}
									</span>
								</div>
							</Transition>
						</div>
					</template>

					<template #body="{ data }" v-else>
						<div class="relative">
							<Transition name="spin-to-down" mode="out-in">
								<div :key="data.code">
									<span class="text-[14px] md:text-[16px]">
										{{ data.name }}
									</span>
								</div>
							</Transition>
						</div>
					</template>
				</Column>

				<!-- Refunds -->
				<Column
					v-if="listColumnInTable.includes('refunds')"
					field="interval_percentages.refunds.amount"
					sortField="interval_percentages.refunds.amount"
					sortable
					headerClass="align-right">
					<template #header>
						<div class="text-xs md:text-base flex justify-end items-end w-full">
							<span class="">Refunds</span>
						</div>
					</template>

					<template #body="{ data }">
						<div class="flex justify-end relative">
							<LabelItemDashboard
								:dataTable="data"
								:settings="props.settings"
								type="refunds"
								:locale />
						</div>
					</template>
				</Column>

				<!-- Refunds: Diff 1y -->
				<Column
					v-if="listColumnInTable.includes('refunds')"
					sortable
					field="interval_percentages.refunds.percentage"
					sortField="interval_percentages.refunds.percentage"
					class="overflow-hidden transition-all"
					headerClass="align-right"
					headerStyle=" width: 140px">
					<template #header>
						<div class="text-xs md:text-base flex justify-end items-end w-full">
							<span class="font-semibold">
								<FontAwesomeIcon
									fixed-width
									icon="fal fa-triangle"
									aria-hidden="true" />
								1y
							</span>
						</div>
					</template>
					<template #body="{ data }">
						<div class="flex justify-end relative">
							<DeltaItemDashboard :dataTable="data" type="refunds" section="body" />
						</div>
					</template>
				</Column>

				<!-- Invoice -->
				<Column
					v-if="listColumnInTable.includes('invoices')"
					sortable
					field="interval_percentages.invoices.amount"
					sortField="interval_percentages.invoices.amount"
					class="overflow-hidden transition-all"
					headerClass="align-right">
					<template #header>
						<div class="text-xs md:text-base flex justify-end items-end w-full">
							<span class="">Invoices</span>
						</div>
					</template>
					<template #body="{ data }">
						<div class="flex justify-end relative">
							<LabelItemDashboard
								:dataTable="data"
								:settings="props.settings"
								type="invoices"
								:locale />
						</div>
					</template>
				</Column>

				<!-- Invoice: Diff 1y -->
				<Column
					v-if="listColumnInTable.includes('invoices')"
					field="interval_percentages.invoices.percentage"
					sortField="interval_percentages.invoices.percentage"
					sortable
					class="overflow-hidden transition-all"
					headerClass="align-right"
					headerStyle=" width: 140px">
					<template #header>
						<div class="text-xs md:text-base flex justify-end items-end w-full">
							<span class="">
								<FontAwesomeIcon
									fixed-width
									icon="fal fa-triangle"
									aria-hidden="true" />
								1y</span
							>
						</div>
					</template>

					<template #body="{ data }">
						<div class="flex justify-end relative">
							<DeltaItemDashboard :dataTable="data" type="invoices" section="body" />
						</div>
					</template>
				</Column>

				<!-- Sales -->
				<Column
					v-if="listColumnInTable.includes('sales')"
					field="interval_percentages.sales.amount"
					sortField="interval_percentages.sales.amount"
					sortable
					class="overflow-hidden transition-all"
					headerClass="align-right">
					<template #header>
						<div class="text-xs md:text-base flex justify-end items-end w-full">
							<span class="">Sales</span>
						</div>
					</template>
					<template #body="{ data }">
						<div class="flex justify-end relative">
							<LabelItemDashboard
								:dataTable="data"
								:settings="props.settings"
								type="sales" />
						</div>
					</template>
				</Column>

				<!-- Sales: Diff 1y -->
				<Column
					v-if="listColumnInTable.includes('sales')"
					field="interval_percentages.sales.percentage"
					sortField="interval_percentages.sales.percentage"
					sortable
					class="overflow-hidden transition-all"
					headerClass="align-right"
					headerStyle=" width: 140px">
					<template #header>
						<div class="text-xs md:text-base flex justify-end items-end w-full">
							<span class="text-gray-700">
								<FontAwesomeIcon
									fixed-width
									icon="fal fa-triangle"
									aria-hidden="true" />
								1y</span
							>
						</div>
					</template>
					<template #body="{ data }">
						<div class="flex justify-end relative">
							<DeltaItemDashboard :dataTable="data" type="sales" section="body" />
						</div>
					</template>
				</Column>

				<!-- Total -->
				<ColumnGroup type="footer">
					<Row>
						<Column footer="Total"> Total </Column>
						<Column
							
							footerStyle="text-align:right">
							<template #footer>
								<div class="whitespace-nowrap text-[#474545]">
									<span  class="text-[14px] md:text-[16px] font-mono">
										{{
											locale.number(
												totalAmount.total_refunds || 0
											)
										}}
									</span>
								</div>
							</template>
						</Column>
						<Column footerStyle="text-align:right">
							<template #footer>
								<div class="whitespace-nowrap text-[#474545]">
									<DeltaItemDashboard
										:totalAmount="totalAmount"
										:totalTooltip="total_tooltip"
										type="total_refunds"
										:settings="props.settings.db_settings.selected_interval"
										section="footer" />
								</div>
							</template>
						</Column>
						<Column
							footerStyle="text-align:right"  >
							<template #footer>
								<div class="whitespace-nowrap text-[#474545]">
									<span  class="text-[14px] md:text-[16px] font-mono">
										{{
											locale.number(
												totalAmount.total_invoices || 0
											)
										}}
									</span>
								</div>
							</template>
						</Column>
						<Column footerStyle="text-align:right">
							<template #footer>
								<div class="whitespace-nowrap  text-[#474545]">
									<DeltaItemDashboard
										:totalAmount="totalAmount"
										:totalTooltip="total_tooltip"
										:settings="props.settings.db_settings.selected_interval"
										type="total_invoices"
										section="footer" />
								</div>
							</template>
						</Column>
						<Column
							v-tooltip="
								useLocaleStore().currencyFormat(
									props.currency_code,
									Number(totalAmount.total_sales)
								)
							"
							:footer="
								props.currency_code ||
								settings.options_currency[0].label ==
									settings.options_currency[1].label
									? useLocaleStore().CurrencyShort(
											props.currency_code,
											Number(totalAmount.total_sales),
											props.settings
									  )
									: ''
							"
							footerStyle="text-align:right" class="font-mono" />
						<Column footerStyle="text-align:right text-[#474545]">
							<template
								#footer
								v-if="
									props.currency_code ||
									settings.options_currency[0].label ==
										settings.options_currency[1].label
								">
								<div class="whitespace-nowrap">
									<DeltaItemDashboard
										:totalAmount="totalAmount"
										:totalTooltip="total_tooltip"
										:settings="props.settings.db_settings.selected_interval"
										type="total_sales"
										section="footer" />
								</div>
							</template>
						</Column>
					</Row>
				</ColumnGroup>
			</DataTable>

			<div v-else class="text-red-500">Type not found</div>
			<!-- <pre>{{ listColumnInTable }}</pre> -->
		</div>
	</div>
</template>
<style scoped>
:deep(.p-tab) {
	/* padding: 0.5rem 1rem; */
	@apply py-2.5 px-3 md:py-4 md:px-4;
}

::v-deep .p-datatable-tbody > tr > td {
	padding: 0.25em !important;
	color: #7c7c7c !important;
}
::v-deep .p-datatable-header-cell {
	padding: 0.25em !important;
	color: #7c7c7c !important;
}
::v-deep .p-datatable-tfoot > tr > td {
	padding: 0.25em !important;
	color: #7c7c7c !important;
	border-top: 1px solid rgba(59, 59, 59, 0.5) !important;
}

::v-deep .p-datatable-column-footer {
	font-weight: 400 !important;
	color: #474545 !important;
}
</style>
