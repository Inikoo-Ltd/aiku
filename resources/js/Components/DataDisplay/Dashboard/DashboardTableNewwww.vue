<script setup lang="ts">
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Row from "primevue/row"
import ColumnGroup from "primevue/columngroup"
import { ref, computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import { Link } from "@inertiajs/vue3"
import { router } from "@inertiajs/vue3"
import { data } from "@/Components/CMS/Website/Product/ProductTemplates/Product1/Descriptor"
import { trans } from "laravel-vue-i18n"
import DeltaItemDashboard from "../../Utils/DeltaItemDashboard.vue"
import LabelItemDashboard from "@/Components/Utils/LabelItemDashboard.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faYinYang, faShoppingBasket, faSitemap, faStore } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faYinYang, faShoppingBasket, faSitemap, faStore)

const props = defineProps<{
	tableData: {
		charts: []
		current_tab: string  // 'shops'
		id: string  // 'sales_table'
		tables: {
			invoice_categories: []
			shops: {
				body: {

				}
				header: {
					slug: string
					columns: {
						baskets_created_org_currency: {
							'1y': {
								formatted_value: string  // "€0.00"
								raw_value: string  // "0.00"
								tooltip: string
							} 
						}
					}
				}
				total: {

				}
				slug: string
				state: string
				columns: {
					baskets_created_org_currency: {
						'1y': {
							formatted_value: string  // "€0.00"
							raw_value: string  // "0.00"
							tooltip: string
						} 
					}
				}
			}[]
		}
		tabs: {
			[tab: string]: {
				icon: string
				title: string
			}
		}
	}
	intervals: {
		options: {}[]
		value: string
	}
}>()

function ShopDashboard(shop: any) {
	return route(shop?.route?.name, shop?.route?.parameters)
}

const activeIndexTab = ref(props.current)

// const selectedTab = computed(() => {
// 	return props.dashboardTable.find((tab) => tab.tab_slug === activeIndexTab.value)
// })
// function useTabChangeDashboard(tab_slug: string) {
// 	if (tab_slug === activeIndexTab.value) {
// 		return
// 	}

// 	router.reload({
// 		data: { tab_dashboard_interval: tab_slug },
// 		// only: ['dashboard_stats'],
// 		onSuccess: () => {
// 			activeIndexTab.value = tab_slug
// 		},
// 		onError: (error) => {
// 			console.error("Error reloading dashboard:", error)
// 		},
// 	})
// }
// console.log('ewqewqewq', selectedTab.value.data)

// const listColumnInTable = computed(() => {
	
// 	const resultSet = new Set();  // Create a Set to store unique elements

//     // Iterate through each sub-array in the input array
//     selectedTab.value.data?.map((e) => Object.keys(e.interval_percentages || {})).forEach(subArray => {
//         // Add each element of the sub-array to the Set
//         subArray.forEach(item => resultSet.add(item));
//     });

//     // Convert the Set back to an Array to return the result
//     return Array.from(resultSet);
// })

// console.log('dashboard table new', props.tableData.tables[props.tableData.current_tab])
console.log('%c Table ', 'background: red; color: white', props.tableData.tables[props.tableData.current_tab]);
</script>

<template>
	<div class="bg-white mb-3 p-4 shadow-md border border-gray-200">
		<div class="">
			<!-- Section: Tabs -->
			<Tabs :value="tableData.current_tab" class="overflow-x-auto text-sm md:text-base pb-2">
				<TabList>
					<Tab
						v-for="(tab, tabSlug) in tableData.tabs"
						@click="() => (tableData.current_tab = tabSlug, 'useTabChangeDashboard(tab.tab_slug)')"
						:key="tabSlug"
						:value="tabSlug"
					>
						<FontAwesomeIcon :icon="tab.icon" class="" fixed-width aria-hidden="true" />
						{{ tab.title }}
					</Tab>
				</TabList>
			</Tabs>

			<!-- Section: Table -->
			<DataTable :value="tableData.tables[tableData.current_tab].body" removableSort>
				<template #empty>
					<div class="flex items-center justify-center h-full text-center">
						{{ trans("No data available.") }}
					</div>
				</template>

				<!-- Column (looping) -->
				<Column
					v-for="(column, colIndex) in tableData.tables[tableData.current_tab].header.columns"
					:key="colIndex"
					:sortable="column.sortable"
					:sortField="`${column.key}.${intervals.value}.formatted_value`"
					:field="column.key"
				>
					<template #header>
						<div class="px-2 text-xs md:text-base flex items-center w-full gap-x-2"
							:class="column.align === 'right' ? 'justify-end' : ''"
						>
							<FontAwesomeIcon v-if="column.icon" :icon="column.icon" class="" fixed-width aria-hidden="true" />
							<span class="leading-5">{{ column.formatted_value }}</span>
							<FontAwesomeIcon v-if="column.iconRight" :icon="column.iconRight" class="" fixed-width aria-hidden="true" />
						</div>
					</template>

					<template #body="{ data }">
						<div class="px-2 flex relative"
							:class="column.align === 'right' ? 'justify-end' : ''"
						>
						<!-- {{ data.columns[colIndex]?.[intervals.value]?.formatted_value }} -->
							<Transition name="spin-to-right">
								<div :key="intervals.value">
									{{ data.columns?.[colIndex]?.[intervals.value]?.formatted_value ?? data[colIndex]?.[intervals.value]?.formatted_value ?? data[colIndex]?.formatted_value ?? data[colIndex] }}
								</div>
							</transition>
						</div>
					</template>
				</Column>

			</DataTable>

			<!-- <pre>{{ tableData.tables[tableData.current_tab] }}</pre> -->

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
