<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import axios from "axios"
import { faChevronDown } from "@far"
import { faChartLine, faPlay, faTimesCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { faCog, faFolderOpen, faSeedling, faTriangle, faSitemap, faGiftCard, faBox, faInventory, faSkullCow, faBan, faDollarSign, faBoxesAlt, faCheckCircle, faCircle, faHandsHelping, faMapSigns, faWarehouse } from "@fal"
import "tippy.js/dist/tippy.css"
import { ref, provide } from "vue"
import { Link } from "@inertiajs/vue3"
import { set } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { useTabChange } from "@/Composables/tab-change"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import DashboardWidget from "@/Components/DataDisplay/Dashboard/DashboardWidget.vue"
import DashboardShopWidget from "@/Components/DataDisplay/Dashboard/DashboardShopWidget.vue"
import ShopIntervalStats from "@/Components/DataDisplay/Dashboard/ShopIntervalStats.vue"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import { Dashboard as DashboardTS } from "@/types/Components/Dashboard"

library.add(faTriangle, faSitemap, faChevronDown, faSeedling, faTimesCircle, faFolderOpen, faPlay, faCog, faChartLine, faGiftCard, faBox, faInventory, faSkullCow, faBan, faDollarSign, faBoxesAlt, faCheckCircle, faCircle, faHandsHelping, faMapSigns, faWarehouse)

const locale = useLocaleStore()

const props = defineProps<{
	title: string
	dashboard: DashboardTS
	stockHistoryGroup?: {
		date: string
		number_org_stocks: number
		number_out_of_stock_org_stocks: number
		percentage_out_of_stock: number
		number_locations: number
		grp_stock_value: number
		currency_code: string
		grp_value_dormant_stock_1y: number
		percentage_dormant_1y: number
		number_org_stocks_not_sold_1y: number
		percentage_not_sold_1y: number
		organisations: {
			name: string
			slug: string
			currency_code: string
			number_org_stocks: number
			number_out_of_stock_org_stocks: number
			percentage_out_of_stock: number
			number_locations: number
			org_stock_value: number
			value_dormant_stock_1y: number
			percentage_dormant_1y: number
			number_org_stocks_not_sold_1y: number
			percentage_not_sold_1y: number
			routes: {
				dashboard: { name: string; parameters: Record<string, string> }
				history: { name: string; parameters: Record<string, string | number> }
				locations: { name: string; parameters: Record<string, string> }
			} | null
		}[]
	} | null
}>()

ChartJS.register(ArcElement, Tooltip, Legend, Colors)

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)
const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)

const currentTab = ref(props.dashboard?.super_blocks?.[0]?.tabs_box?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const isExpanded = ref(false)

const fetchDashboardTabData = async (tabSlug: string): Promise<void> => {
	const block = props.dashboard?.super_blocks?.[0]?.blocks?.[0]
	const fetchRoute = block?.tab_fetch_route
	if (!block?.tables || !fetchRoute?.name) {
		return
	}

	if (block.tables[tabSlug]) {
		return
	}

	isLoadingOnTable.value = true
	try {
		const { data } = await axios.get(route(fetchRoute.name), {
			params: {
				tab: tabSlug,
			},
		})

		if (data?.tab && data?.table) {
			set(props, `dashboard.super_blocks[0].blocks[0].tables.${data.tab}`, data.table)
		}

		if (data?.tab && data?.table_2) {
			set(props, `dashboard.super_blocks[0].blocks_2[0].tables.${data.tab}`, data.table_2)
		}
	} finally {
		isLoadingOnTable.value = false
	}
}

const onChangeDashboardTab = async (tabSlug: string): Promise<void> => {
	set(props, "dashboard.super_blocks[0].blocks[0].current_tab", tabSlug)
	await fetchDashboardTabData(tabSlug)
}
</script>

<template>
	<Head :title="capitalize(title)" />

	<div>
		<KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
			<TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" />
		</KeepAlive>

		<div v-if="stockHistoryGroup" class="px-3 sm:px-6 mt-1">
			<dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-y divide-gray-100 bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-dollar-sign" fixed-width aria-hidden="true" />
						{{ trans('Stock Value') }}
					</dt>
					<dd class="mt-1 text-xl sm:text-3xl font-semibold tabular-nums text-gray-800">
						{{ locale.currencyFormat(stockHistoryGroup.currency_code, Number(stockHistoryGroup.grp_stock_value)) }}
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-box" fixed-width aria-hidden="true" />
						{{ trans('Stored SKUs') }}
					</dt>
					<dd class="mt-1 text-xl sm:text-2xl font-semibold tabular-nums text-gray-800">
						{{ locale.number(stockHistoryGroup.number_org_stocks) }}
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-inventory" fixed-width aria-hidden="true" />
						{{ trans('Locations') }}
					</dt>
					<dd class="mt-1 text-xl sm:text-2xl font-semibold tabular-nums text-gray-800">
						{{ locale.number(stockHistoryGroup.number_locations) }}
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fas fa-times-circle" class="text-red-400" fixed-width aria-hidden="true" />
						{{ trans('Out of Stock') }}
					</dt>
					<dd class="mt-1 flex items-baseline gap-x-2">
						<span class="text-2xl font-semibold tabular-nums text-red-500">
							{{ locale.number(stockHistoryGroup.number_out_of_stock_org_stocks) }}
						</span>
						<span class="text-sm font-medium tabular-nums text-red-500" v-tooltip="trans('Percentage of total SKUs')">
							{{ stockHistoryGroup.percentage_out_of_stock }}%
						</span>
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-skull-cow" class="text-red-500" fixed-width aria-hidden="true" />
						{{ trans('Dormant 1Y') }}
					</dt>
					<dd class="mt-1 flex items-baseline gap-x-2">
						<span class="text-2xl font-semibold tabular-nums text-red-500">
							{{ locale.currencyFormat(stockHistoryGroup.currency_code, Number(stockHistoryGroup.grp_value_dormant_stock_1y)) }}
						</span>
						<span class="text-sm font-medium tabular-nums text-red-500" v-tooltip="trans('Percentage of total stock value')">
							{{ stockHistoryGroup.percentage_dormant_1y }}%
						</span>
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" />
						{{ trans('No Sold 1Y') }}
					</dt>
					<dd class="mt-1 flex items-baseline gap-x-2">
						<span class="text-2xl font-semibold tabular-nums text-red-500">
							{{ locale.number(stockHistoryGroup.number_org_stocks_not_sold_1y) }}
						</span>
						<span class="text-sm font-medium tabular-nums text-red-500" v-tooltip="trans('Percentage of total SKUs')">
							{{ stockHistoryGroup.percentage_not_sold_1y }}%
						</span>
					</dd>
				</div>
			</dl>

			<div v-if="stockHistoryGroup.organisations.length > 0" class="flex justify-center mt-1">
				<button
					class="flex items-center gap-x-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors px-3 py-1 rounded hover:bg-gray-100"
					@click="isExpanded = !isExpanded"
				>
					<FontAwesomeIcon
						icon="far fa-chevron-down"
						class="text-[10px] transition-transform duration-200"
						:class="isExpanded ? 'rotate-180' : ''"
						fixed-width
						aria-hidden="true"
					/>
				</button>
			</div>

			<div v-if="isExpanded && stockHistoryGroup.organisations.length > 0" class="mt-1 mb-4 bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden overflow-x-auto">
				<table class="w-full text-sm">
					<thead>
						<tr class="bg-gray-50 border-b border-gray-200">
							<th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">{{ trans('Organisation') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ trans('Stock Value') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ trans('SKUs') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ trans('Locations') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ trans('Out of Stock') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ trans('Dormant 1Y') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ trans('No Sold 1Y') }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="org in stockHistoryGroup.organisations" :key="org.slug" class="hover:bg-gray-50 transition-colors">
							<td class="px-4 py-2.5 font-medium whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.dashboard.name, org.routes.dashboard.parameters)" class="text-gray-800 hover:text-blue-600 hover:underline">
									{{ org.name }}
								</Link>
								<span v-else class="text-gray-800">{{ org.name }}</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'org_stocks' })" class="text-gray-700 hover:text-blue-600 hover:underline">
									{{ locale.currencyFormat(org.currency_code, org.org_stock_value) }}
								</Link>
								<span v-else class="text-gray-700">{{ locale.currencyFormat(org.currency_code, org.org_stock_value) }}</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'org_stocks' })" class="text-gray-700 hover:text-blue-600 hover:underline">
									{{ locale.number(org.number_org_stocks) }}
								</Link>
								<span v-else class="text-gray-700">{{ locale.number(org.number_org_stocks) }}</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.locations.name, org.routes.locations.parameters)" class="text-gray-700 hover:text-blue-600 hover:underline">
									{{ locale.number(org.number_locations) }}
								</Link>
								<span v-else class="text-gray-700">{{ locale.number(org.number_locations) }}</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'out_of_stock' })" class="text-red-500 hover:text-red-700 hover:underline">
									{{ locale.number(org.number_out_of_stock_org_stocks) }}
								</Link>
								<span v-else class="text-red-500">{{ locale.number(org.number_out_of_stock_org_stocks) }}</span>
								<span class="text-xs text-red-400 ml-1">{{ org.percentage_out_of_stock }}%</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'dormant_stock_1y' })" class="text-red-500 hover:text-red-700 hover:underline">
									{{ locale.currencyFormat(org.currency_code, org.value_dormant_stock_1y) }}
								</Link>
								<span v-else class="text-red-500">{{ locale.currencyFormat(org.currency_code, org.value_dormant_stock_1y) }}</span>
								<span class="text-xs text-red-400 ml-1">{{ org.percentage_dormant_1y }}%</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'not_sold_1y' })" class="text-red-500 hover:text-red-700 hover:underline">
									{{ locale.number(org.number_org_stocks_not_sold_1y) }}
								</Link>
								<span v-else class="text-red-500">{{ locale.number(org.number_org_stocks_not_sold_1y) }}</span>
								<span class="text-xs text-red-400 ml-1">{{ org.percentage_not_sold_1y }}%</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<ShopIntervalStats v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks" :shop-blocks="props.dashboard?.super_blocks?.[0]?.shop_blocks" />

		<DashboardSettings
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks?.[0]?.current_tab"
		/>

		<DashboardTable
			v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			class="border-t border-gray-200"
			:idTable="props.dashboard?.super_blocks?.[0]?.id"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab"
			@onChangeTab="onChangeDashboardTab"
		/>

		<DashboardTable
			v-if="props.dashboard?.super_blocks?.[0]?.blocks_2?.[0]?.tables?.[props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab]"
			class="border-t border-gray-200"
			:idTable="props.dashboard?.super_blocks?.[0]?.blocks_2[0]?.id"
			:tableData="{
				...props.dashboard?.super_blocks?.[0]?.blocks_2[0],
				current_tab: props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab
			}"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab"
			:showTabs="false"
			@onChangeTab="onChangeDashboardTab"
		/>

		<DashboardWidget
			v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
		/>

		<DashboardShopWidget
			v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks"
			:interval="props.dashboard?.super_blocks?.[0]?.intervals?.value"
			:data="props.dashboard?.super_blocks?.[0]?.shop_blocks"
		/>
	</div>
</template>
