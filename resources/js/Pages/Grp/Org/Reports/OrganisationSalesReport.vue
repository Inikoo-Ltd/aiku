<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 03 Feb 2025 23:37:54 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref } from "vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { Switch } from "@headlessui/vue"
import { useLocaleStore } from "@/Stores/locale"
import { RadioGroup, RadioGroupOption } from "@headlessui/vue"
import { Pie } from "vue-chartjs"
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Row from "primevue/row"
import ColumnGroup from "primevue/columngroup"
import { computed } from "vue"
import { useTruncate } from "@/Composables/useTruncate"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown } from "@far"
import { faPlay, faTriangle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { get } from "lodash"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useGetCurrencySymbol } from "@/Composables/useCurrency"
import Tag from "@/Components/Tag.vue"
import ToggleSwitch from "primevue/toggleswitch"
import { faSortDown, faSortUp } from "@fas"
import Select from "primevue/select"
import { Link } from "@inertiajs/vue3"
import Dashboard from "@/Components/DataDisplay/Dashboard/DashboardOld.vue"

library.add(faTriangle, faChevronDown, faSortDown, faSortUp, faPlay)

const props = defineProps<{
	dashboard: any
	interval_options: {
		label: string
		labelShort: string
		value: string
	}[]
	dashboard_stats:{}
}>()

console.log(props.dashboard, "hehe")
const selectedDateOption = ref<string>("ytd")
const locale = inject("locale", aikuLocaleStructure)
const checked = ref(true)

const datas = computed(() => {
	return props.dashboard.shops
		.filter((org) => {
			if (checked.value) {
                return org.state !== "closed"; // Exclude closed shops when checked is true
            }
            return true;
		})
		.map((org) => ({
			name: org.name,
			code: org.code,
			interval_percentages: org.interval_percentages,
			sales: org.sales || 0,
			route: org.route,
			currency:
				selectedCurrency.value.code === "org"
					? props.dashboard.currency.code
					: org.currency.code,
		}))
})
const currency = ref([
	{ name: "Organisation", code: "org" },
	{ name: "Shop", code: "shp" },
])

const selectedCurrency = ref(currency.value[0])
const isOrganisation = ref(selectedCurrency.value.code === "org")
const toggleCurrency = () => {
	selectedCurrency.value = isOrganisation.value ? currency.value[1] : currency.value[0]
}

function ShopDashboard(shop: any) {
	return route(shop.route.name, shop.route.parameters)
}
</script>

<template>
	<Head :title="trans('Dashboard')" />
	<div class="grid grid-cols-12 m-3 gap-4">
		<!-- <pre>{{ props.groupStats.organisations }}</pre> -->
			<!-- 	<div class="flex justify-between items-center">
					<ToggleSwitch v-model="checked" />

					<div class="flex items-center space-x-4">
						<p
							class="font-medium transition-opacity"
							:class="{ 'opacity-60': isOrganisation }">
							Organisation
						</p>

						<ToggleSwitch
							v-model="isOrganisation"
							class="mx-2"
							@change="toggleCurrency" />

						<p
							class="font-medium transition-opacity"
							:class="{ 'opacity-60': !isOrganisation }">
							Shop
						</p>
					</div>
				</div> -->
<!-- 
				<div class="mt-4 block">
					<nav class="isolate flex rounded border-b border-gray-300" aria-label="Tabs">
						<div
							v-for="(interval, idxInterval) in interval_options"
							:key="idxInterval"
							@click="() => (selectedDateOption = interval.value)"
							:class="[
								interval.value === selectedDateOption
									? ''
									: 'text-gray-500 hover:text-gray-700',
							]"
							class="relative min-w-0 flex-1 overflow-hidden bg-white hover:bg-gray-100 py-0 text-center text-sm cursor-pointer select-none focus:z-10">
							<span>{{ interval.value }}</span>
							<span
								aria-hidden="true"
								:class="[
									interval.value === selectedDateOption
										? 'bg-indigo-500'
										: 'bg-transparent',
									'absolute inset-x-0 bottom-0 h-0.5',
								]" />
						</div>
					</nav>
				</div> -->

				<!-- <div class="mt-6">
					<DataTable :value="datas" removableSort tableStyle="min-width: 50rem"> -->
						<!-- <Column
							field="name"
							sortable
							class="overflow-hidden transition-all"
							header="Name">
							<template #body="{ data }">
								<div class="relative">
									<Transition name="spin-to-down" mode="out-in">
										<div :key="data.name">
											<Link :href="ShopDashboard(data)" class="primaryLink">
												{{ data.name }}
											</Link>
										</div>
									</Transition>
								</div>
							</template>
						</Column> -->

						<!-- Refunds -->
					<!-- 	<Column sortable hidden headerClass="align-right">
							<template #header>
								<div class="flex justify-end items-end">
									<span class="font-bold">Refunds</span>
								</div>
							</template>
							<template #body="{ data }">
								<div class="flex justify-end relative">
									<Transition name="spin-to-down" mode="out-in">
										<div
											:key="
												data.interval_percentages?.refunds[
													selectedDateOption
												]?.amount || 0
											">
											{{
												locale.number(
													data.interval_percentages?.refunds[
														selectedDateOption
													]?.amount || 0
												)
											}}
										</div>
									</Transition>
								</div>
							</template>
						</Column> -->

						<!-- Refunds: Diff 1y -->
						<!-- <Column
							hidden
							sortable
							class="overflow-hidden transition-all"
							headerClass="align-right"
							headerStyle="text-align: green; width: 270px">
							<template #header>
								<div class="flex justify-end items-end">
									<span class="font-bold">
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
									<Transition name="spin-to-down" mode="out-in">
										<div
											:key="`${data.interval_percentages?.refunds[selectedDateOption].difference}_${data.interval_percentages?.refunds[selectedDateOption].percentage}`"
											style="
												display: flex;
												align-items: center;
												line-height: 1;
												gap: 6px;
											">
											<span
												style="
													font-size: 16px;
													font-weight: 500;
													line-height: 1;
												">
												{{
													data.interval_percentages?.refunds[
														selectedDateOption
													]?.percentage
														? `${
																data.interval_percentages.refunds[
																	selectedDateOption
																].percentage > 0
																	? "+"
																	: ""
														  }${data.interval_percentages.refunds[
																selectedDateOption
														  ].percentage.toFixed(2)}%`
														: `0.0%`
												}}
											</span>
											<FontAwesomeIcon
												v-if="
													data.interval_percentages?.refunds[
														selectedDateOption
													]?.percentage
												"
												:icon="
													data.interval_percentages.refunds[
														selectedDateOption
													].percentage < 0
														? 'fas fa-sort-down'
														: 'fas fa-sort-up'
												"
												style="font-size: 20px; margin-top: 6px"
												:class="
													data.interval_percentages.refunds[
														selectedDateOption
													].percentage < 0
														? 'text-red-500'
														: 'text-green-500'
												" />
										</div>
									</Transition>
								</div>
							</template>
						</Column> -->

						<!-- Invoice -->
					<!-- 	<Column
							sortable
							class="overflow-hidden transition-all"
							headerClass="align-right">
							<template #header>
								<div class="flex justify-end items-end">
									<span class="font-bold">Invoices</span>
								</div>
							</template>
							<template #body="{ data }">
								<div class="flex justify-end relative">
									<Transition name="spin-to-down" mode="out-in">
										<div
											
											:key="
												data.interval_percentages?.invoices[
													selectedDateOption
												]?.amount || 0
											">
											{{
												locale.number(data.interval_percentages?.invoices[
														selectedDateOption
													]?.amount || 0)
											}}
										</div>
									</Transition>
								</div>
							</template>
						</Column> -->

						<!-- Invoice: Diff 1y -->
						<!-- <Column
							field="invoices_diff"
							sortable
							class="overflow-hidden transition-all"
							headerClass="align-right"
							headerStyle="text-align: green; width: 200px">
							<template #header>
								<div class="flex justify-end items-end">
									<span class="font-bold">
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
									<Transition name="spin-to-down" mode="out-in">
										<div class="flex justify-end relative">
										
											<Transition name="spin-to-down" mode="out-in">
												<div
										
													:key="`${data.interval_percentages?.invoices[selectedDateOption].difference}_${data.interval_percentages?.invoices[selectedDateOption].percentage}`"
													style="
														display: flex;
														align-items: center;
														line-height: 1;
														gap: 6px;
													">
													<span
														style="
															font-size: 16px;
															font-weight: 500;
															line-height: 1;
														">
														{{
															data.interval_percentages?.invoices[
																selectedDateOption
															]?.percentage
																? `${
																		data.interval_percentages
																			.invoices[
																			selectedDateOption
																		].percentage > 0
																			? "+"
																			: ""
																  }${data.interval_percentages.invoices[
																		selectedDateOption
																  ].percentage.toFixed(2)}%`
																: `0.0%`
														}}
													</span>
													<FontAwesomeIcon
														v-if="
															data.interval_percentages?.invoices[
																selectedDateOption
															]?.percentage
														"
														:icon="
															data.interval_percentages.invoices[
																selectedDateOption
															].percentage < 0
																? 'fas fa-play'
														: 'fas fa-play'
														"
														style="font-size: 20px; margin-top: 6px"
														:class="
															data.interval_percentages.invoices[
																selectedDateOption
															].percentage < 0
																? 'text-red-500'
																: 'text-green-500'
														" />
												</div>
											</Transition>
										</div>
									</Transition>
								</div>
							</template>
						</Column> -->

						<!-- Sales -->
						<!-- <Column
							field="sales"
							sortable
							class="overflow-hidden transition-all"
							headerClass="align-right"
							headerStyle="text-align: green; width: 250px">
							<template #header>
								<div class="flex justify-end items-end">
									<span class="font-bold">Sales</span>
								</div>
							</template>
							<template #body="{ data }">
								<div class="flex justify-end relative">
									<Transition name="spin-to-down" mode="out-in">
										<div
											v-tooltip="
												useLocaleStore().currencyFormat(
													data.currency,
													data.interval_percentages?.sales[
														selectedDateOption
													]?.amount || 0
												)
											"
											:key="
												data.interval_percentages?.sales[selectedDateOption]
													?.amount
											">
											{{
												useLocaleStore().CurrencyShort(
													data.currency,
													data.interval_percentages?.sales[
														selectedDateOption
													]?.amount || 0
												)
											}}
										</div>
									</Transition>
								</div>
							</template>
						</Column> -->

						<!-- Sales: Diff 1y -->
						<!-- <Column
							field="sales_diff"
							sortable
							class="overflow-hidden transition-all"
							headerClass="align-right"
							headerStyle="text-align: green; width: 270px">
							<template #header>
								<div class="flex justify-end items-end">
									<span class="font-bold">
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
									
									<Transition name="spin-to-down" mode="out-in">
										<div
											:key="`${data.interval_percentages?.sales[selectedDateOption].difference}_${data.interval_percentages?.sales[selectedDateOption].percentage}`"
											style="
												display: flex;
												align-items: center;
												line-height: 1;
												gap: 6px;
											">
											<span
												style="
													font-size: 16px;
													font-weight: 500;
													line-height: 1;
												">
												{{
													data.interval_percentages?.sales[
														selectedDateOption
													]?.percentage
														? `${
																data.interval_percentages.sales[
																	selectedDateOption
																].percentage > 0
																	? "+"
																	: ""
														  }${data.interval_percentages.sales[
																selectedDateOption
														  ].percentage.toFixed(2)}%`
														: `0.0%`
												}}
											</span>
											<FontAwesomeIcon
												v-if="
													data.interval_percentages?.sales[
														selectedDateOption
													]?.percentage
												"
												:icon="
													data.interval_percentages.sales[
														selectedDateOption
													].percentage < 0
														? 'fas fa-play'
														: 'fas fa-play'
												"
												style="font-size: 20px; margin-top: 6px"
												:class="
													data.interval_percentages.sales[
														selectedDateOption
													].percentage < 0
														? 'text-red-500 rotate-90'
														: 'text-green-500 rotate-[-90deg]'
												" />
												<div v-else style="width: 20px; height: 20px"></div>
										</div>
									</Transition>
								</div>
							</template>
						</Column> -->

						<!-- Total -->
						<!-- <ColumnGroup type="footer">
							<Row>
								<Column footer="Total"> Total </Column>
								<Column
									hidden
									:footer="
										dashboard.total[selectedDateOption].total_refunds.toString()
									"
									footerStyle="text-align:right" />
								<Column hidden footer="" footerStyle="text-align:right" />

								<Column
									:footer="
									locale.number(Number(dashboard.total[
											selectedDateOption
										].total_invoices.toString())
									)
									"
									footerStyle="text-align:right" />
								<Column footer="" footerStyle="text-align:right" />
								<Column
									:footer="
										useLocaleStore().CurrencyShort(
											dashboard.currency.code,
											Number(dashboard.total[selectedDateOption].total_sales)
										)
									"
									footerStyle="text-align:right" />
								<Column footer="" footerStyle="text-align:right" />
							</Row>
						</ColumnGroup> -->
					<!-- </DataTable>
				</div> -->
				<div class="col-span-12">
					<Dashboard 
						:dashboard="dashboard_stats"
						:checked="checked"
						tableType="org"/>	
				</div>
		
		<!-- <pre>{{ groupStats }}</pre> -->
	</div>
</template>

<style>
.align-right {
	justify-items: end;
	text-align: right;
}

.transition-opacity {
	transition: opacity 0.3s ease-in-out;
}
</style>
