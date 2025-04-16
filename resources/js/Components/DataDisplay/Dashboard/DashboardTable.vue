<script setup lang="ts">
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Row from "primevue/row"
import ColumnGroup from "primevue/columngroup"
import { ref, computed, inject, watch } from "vue"
// import { useLocaleStore } from "@/Stores/locale"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
// import { Link } from "@inertiajs/vue3"
// import { router } from "@inertiajs/vue3"
// import { data } from "@/Components/CMS/Website/Product/ProductTemplates/Product1/Descriptor"
import { trans } from "laravel-vue-i18n"
// import DeltaItemDashboard from "../../Utils/DeltaItemDashboard.vue"
// import LabelItemDashboard from "@/Components/Utils/LabelItemDashboard.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faYinYang, faShoppingBasket, faSitemap, faStore } from "@fal"
import { faTriangle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { Link } from "@inertiajs/vue3"
library.add(faYinYang, faShoppingBasket, faSitemap, faStore)

interface Column {
	formatted_value: string  // "€0.00"
	raw_value: string  // "0.00"
	tooltip: string
	sortable?: boolean
	align?: string
} 

interface Header {
	slug: string
	columns: {
		[key: string]: {  // key: 'baskets_created_org_currency', 'baskets_created_grp_currency', 'baskets_created_grp_currency_minified'
			[key: string]: Column  // key: '1y', '3m', '1m', '7d', '1d'
		}
	} | Column
}
interface Tables {
	[key: string]: {
		body: {

		}
		header: Header
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

const props = defineProps<{
	tableData: {
		charts: []
		current_tab: string  // 'shops'
		id: string  // 'sales_table'
		tables: Tables
		tabs: {
			[key: string]: {
				icon: string
				title: string
			}
		}
	}
	intervals: {
		options: {}[]
		value: string
	}
	settings: {
		[key: string]: {  // 'data_display_type'
			align: string
			id: string
			options: {
				label: string
				value: string
			}[]
			type: string
			value: string
		}
	}
}>()

const isLoadingOnTable = inject("isLoadingOnTable", ref(false))

// console.log('dashboard table new', props.tableData.tables[props.tableData.current_tab])
console.log('%c Tables ', 'background: red; color: white', props.tableData.tables);

// const compTableHeaderColumns = computed<Header>(() => {
// 	if (props.settings.data_display_type.value === 'minified') {
// 		const aaa = Object.keys(props.tableData.tables[props.tableData.current_tab].header.columns).reduce((newObj, key) => {
// 			if (key.includes('minified')) {
// 				newObj[key] = props.tableData.tables[props.tableData.current_tab].header.columns[key];
// 			}

// 			return newObj;
// 		}, {});

// 		return aaa;
// 	} else {
// 		const aaa = Object.keys(props.tableData.tables[props.tableData.current_tab].header.columns).reduce((newObj, key) => {
// 			if (!key.includes('minified')) {
// 				newObj[key] = props.tableData.tables[props.tableData.current_tab].header.columns[key];
// 			}

// 			return newObj;
// 		}, {});

// 		return aaa;
// 	}
// })
// const compTableTotalColumns = computed(() => {
// 	if (props.settings.data_display_type.value === 'minified') {
// 		const aaa = Object.keys(props.tableData.tables[props.tableData.current_tab].totals.columns).reduce((newObj, key) => {
// 			if (key.includes('minified')) {
// 				newObj[key] = props.tableData.tables[props.tableData.current_tab].totals.columns[key];
// 			}

// 			return newObj;
// 		}, {});

// 		return aaa;
// 	} else {
// 		const aaa = Object.keys(props.tableData.tables[props.tableData.current_tab].totals.columns).reduce((newObj, key) => {
// 			if (!key.includes('minified')) {
// 				newObj[key] = props.tableData.tables[props.tableData.current_tab].totals.columns[key];
// 			}

// 			return newObj;
// 		}, {});

// 		return aaa;
// 	}
// })


// Section: show/hide setting shop state
const dashboardTabActive = inject("dashboardTabActive", ref(''))
watch(() => props.tableData.current_tab, (newValueTab) => {
	dashboardTabActive.value = newValueTab
}, { immediate: true })
const compTableBody = computed(() => {
	if (props.settings.shop_state_type?.value === 'open') {
		return props.tableData.tables[props.tableData.current_tab].body?.filter(row => row.state === 'open')
	}

	return props.tableData.tables[props.tableData.current_tab].body;
})
</script>

<template>
	<div class="relative bg-white mb-3 p-4 border border-gray-200">
		<!-- <pre>{{ props.tableData.tables.shops.body.length }}</pre> -->

		<div class="">
			<!-- Section: Tabs -->
			<Tabs :value="tableData.current_tab" class="overflow-x-auto text-sm md:text-base pb-2">
				<TabList>
					<Tab
						v-for="(tab, tabSlug) in tableData.tabs"
						@click="() => (tableData.current_tab = tabSlug, dashboardTabActive = tabSlug)"
						:key="tabSlug"
						:value="tabSlug"
					>
						<FontAwesomeIcon :icon="tab.icon" class="" fixed-width aria-hidden="true" />
						{{ tab.title }}
					</Tab>
				</TabList>
			</Tabs>

			<!-- Section: Table -->
			<DataTable :value="compTableBody" removableSort>
				<template #empty>
					<div class="flex items-center justify-center h-full text-center">
						{{ trans("No data available.") }}
					</div>
				</template>
				
				<!-- Column (looping) -->
				<template
					v-for="(column, colSlug, colIndex) in props.tableData?.tables?.[props.tableData?.current_tab]?.header?.columns"
					:key="colSlug"
				>
				<!-- {{column.data_display_type}} -->
					<Column
						v-if="!column.data_display_type || column.data_display_type === 'always' ? true : column.data_display_type === props.settings.data_display_type.value"
						:sortable="column.sortable"
						:sortField="`columns.${colSlug}.${intervals.value}.formatted_value`"
						:field="colSlug"
					>
						<template #header>
							<div class="px-2 text-xs md:text-base flex items-center w-full gap-x-2"
								:class="column.align === 'left' ? '' : 'justify-end text-right'"
								v-tooltip="column.tooltip"
							>
								<FontAwesomeIcon v-if="column.icon" :icon="column.icon" class="" fixed-width aria-hidden="true" />
								<span class="leading-5">{{ column.formatted_value }}</span>
								<FontAwesomeIcon v-if="column.iconRight" :icon="column.iconRight" class="" fixed-width aria-hidden="true" />
							</div>
						</template>
						
						<template #body="{ data }">
							<div class="flex relative" :class="[ column.align === 'left' ? '' : 'justify-end text-right', ]" >
								<!-- <pre>{{ column.data_display_type }}</pre> -->
								<!-- <pre>{{ data.columns?.[colSlug]?.[intervals.value] }}</pre> -->
								<Transition name="spin-to-right">
									<component
										:key="intervals.value"
										class="px-2"
										:class="[
											data.route?.name ? 'cursor-pointer hover:underline' : '',
										]"
										:is="data.route?.name ? Link : 'div'"
										:href="data.route?.name ? route(data.route.name, data.route.parameters) : '#'"
										v-tooltip="data.tooltip"
									>
										{{ data.columns?.[colSlug]?.[intervals.value]?.formatted_value ?? data.columns[colSlug]?.formatted_value }}
										<FontAwesomeIcon
											v-if="data.columns?.[colSlug]?.[intervals.value]?.change"
											:icon="faTriangle"
											class="text-sm"
											:class="[
												data.columns?.[colSlug]?.[intervals.value]?.change == 'increase' ? '' : 'rotate-180',
												data.columns?.[colSlug]?.[intervals.value]?.state == 'positive' ? 'text-green-500' : 'text-red-500',
											]"
											fixed-width
											aria-hidden="true"
										/>
									</component>
								</Transition>
							</div>
						</template>
					</Column>
				</template>
			
				<!-- Row: Total (footer) -->
				<ColumnGroup type="footer">
					<Row>
						<template
							v-for="(column, colSlug) in props.tableData?.tables?.[props.tableData?.current_tab]?.totals?.columns"
							:key="colSlug"
						>
							<Column
								v-if="!column.data_display_type || column.data_display_type === 'always' ? true : column.data_display_type === props.settings.data_display_type.value"
								:sortable="column.sortable"
								:sortField="`${column.key}.${intervals.value}.formatted_value`"
								:field="column.key"
							>
								<template #footer>
									<div class="px-2 flex relative"
										:class="props.tableData.tables?.[props.tableData?.current_tab]?.header?.columns?.[colSlug]?.align === 'left' ? '' : 'justify-end text-right'"
									>
										<transition name="spin-to-right">
											<div :key="intervals.value">
												{{ column[intervals.value]?.formatted_value ?? column?.formatted_value }}
											</div>
										</transition>
									</div>
								</template>
							</Column>
						</template>
					</Row>
				</ColumnGroup>
			</DataTable>

			<div v-if="isLoadingOnTable" class="absolute inset-0 bg-white/50 flex justify-center items-center text-5xl">
				<LoadingIcon />
			</div>

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
