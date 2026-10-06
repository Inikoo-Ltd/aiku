<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import axios from "axios"
import { faChevronDown } from "@far"
import { faChartLine, faPlay, faTimesCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head, Deferred } from "@inertiajs/vue3"
import { faCog, faFolderOpen, faSeedling, faTriangle, faSitemap, faGiftCard, faBox, faInventory, faSkullCow, faBan, faDollarSign, faBoxesAlt, faCheckCircle, faCircle, faHandsHelping, faMapSigns, faWarehouse, faChartLine as falChartLine, faCity, faDolly } from "@fal"
import "tippy.js/dist/tippy.css"
import { ref, provide, computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { set } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { useTabChange } from "@/Composables/tab-change"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import DashboardWidget from "@/Components/DataDisplay/Dashboard/DashboardWidget.vue"
import DashboardShopWidget from "@/Components/DataDisplay/Dashboard/DashboardShopWidget.vue"
import ShopIntervalStats from "@/Components/DataDisplay/Dashboard/ShopIntervalStats.vue"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import ShopMonthTarget from "@/Components/DataDisplay/Dashboard/ShopMonthTarget.vue"
import GroupWarehouseOverview from "@/Components/DataDisplay/Dashboard/GroupWarehouseOverview.vue"
import OperationsDashboard from "@/Components/DataDisplay/Dashboard/OperationsDashboard.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { Dashboard as DashboardTS } from "@/types/Components/Dashboard"

library.add(faTriangle, faSitemap, faChevronDown, faSeedling, faTimesCircle, faFolderOpen, faPlay, faCog, faChartLine, faGiftCard, faBox, faInventory, faSkullCow, faBan, faDollarSign, faBoxesAlt, faCheckCircle, faCircle, faHandsHelping, faMapSigns, faWarehouse, falChartLine, faCity, faDolly)

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
		grp_stock_lpp_value: number
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
			org_stock_lpp_value: number
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
	warehouseOverview?: InstanceType<typeof GroupWarehouseOverview>['$props']['overview'] | null
	operations?: { section: string; route: { name: string } } | null
}>()

ChartJS.register(ArcElement, Tooltip, Legend, Colors)

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)
const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)
const failedTableTab = ref<string | null>(null)
provide("failedTableTab", failedTableTab)

const currentTab = ref(props.dashboard?.super_blocks?.[0]?.tabs_box?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const isExpanded = ref(false)

const hasSales = computed(() => (props.dashboard?.super_blocks?.length ?? 0) > 0)

const sectionNavigation = computed(() => {
	const sections: Record<string, { title: string; icon: string }> = {}
	if (hasSales.value) {
		sections.sales = { title: ctrans("Sales"), icon: "fal fa-chart-line" }
	}
	if (props.stockHistoryGroup) {
		sections.warehouse = { title: ctrans("Stock"), icon: "fal fa-boxes-alt" }
	}
	if (props.operations) {
		sections.operations = { title: ctrans("Operations (In/Out)"), icon: "fal fa-dolly" }
	}
	return Object.keys(sections).length > 1 ? sections : null
})

const initialSection = (): string => {
	const saved = props.operations?.section
	if (saved === "operations" && props.operations) {
		return "operations"
	}
	if (saved === "warehouse" && props.stockHistoryGroup) {
		return "warehouse"
	}
	return hasSales.value || !props.operations ? "sales" : "operations"
}
const currentSection = ref(initialSection())

const changeSection = (section: string): void => {
	currentSection.value = section
	axios.patch(route("grp.models.profile.update"), { settings: { group_dashboard_section: section } }).catch(() => {})
}

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
	failedTableTab.value = null
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
	} catch {
		failedTableTab.value = tabSlug
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
		<Tabs v-if="sectionNavigation" :navigation="sectionNavigation" :current="currentSection" @update:tab="changeSection" />

		<OperationsDashboard v-if="operations" v-show="currentSection === 'operations'" :fetch-route="operations.route" :active="currentSection === 'operations'" />

		<div v-if="stockHistoryGroup" v-show="currentSection === 'warehouse'" class="px-3 sm:px-6 mt-4 mb-4">
			<dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-y divide-gray-100 bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-dollar-sign" fixed-width aria-hidden="true" />
						{{ ctrans('Stock Value') }}
						<FontAwesomeIcon icon="fal fa-question-circle" class="cursor-help text-gray-300 hover:text-gray-500" fixed-width aria-hidden="true" v-tooltip="stockHistoryGroup.valuation_legend" />
					</dt>
					<dd class="mt-1 text-xl sm:text-3xl font-semibold tabular-nums text-gray-800">
						{{ locale.CurrencyShort(stockHistoryGroup.currency_code, Number(stockHistoryGroup.grp_stock_lpp_value)) }}
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-box" fixed-width aria-hidden="true" />
						{{ ctrans('Stored SKOs') }}
					</dt>
					<dd class="mt-1 text-xl sm:text-2xl font-semibold tabular-nums text-gray-800">
						{{ locale.numberShort(stockHistoryGroup.number_org_stocks) }}
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-inventory" fixed-width aria-hidden="true" />
						{{ ctrans('Locations') }}
					</dt>
					<dd class="mt-1 text-xl sm:text-2xl font-semibold tabular-nums text-gray-800">
						{{ locale.numberShort(stockHistoryGroup.number_locations) }}
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fas fa-times-circle" class="text-red-400" fixed-width aria-hidden="true" />
						{{ ctrans('Out of Stock') }}
					</dt>
					<dd class="mt-1 flex items-baseline gap-x-2">
						<span class="text-2xl font-semibold tabular-nums text-red-500">
							{{ locale.numberShort(stockHistoryGroup.number_out_of_stock_org_stocks) }}
						</span>
						<span class="text-sm font-medium tabular-nums text-red-500" v-tooltip="ctrans('Percentage of total SKOs')">
							{{ stockHistoryGroup.percentage_out_of_stock }}%
						</span>
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-skull-cow" class="text-red-500" fixed-width aria-hidden="true" />
						{{ ctrans('Dormant 1Y') }}
						<FontAwesomeIcon icon="fal fa-question-circle" class="cursor-help text-gray-300 hover:text-gray-500" fixed-width aria-hidden="true" v-tooltip="stockHistoryGroup.valuation_legend" />
					</dt>
					<dd class="mt-1 flex items-baseline gap-x-2">
						<span class="text-2xl font-semibold tabular-nums text-red-500">
							{{ locale.CurrencyShort(stockHistoryGroup.currency_code, Number(stockHistoryGroup.grp_value_dormant_stock_1y)) }}
						</span>
						<span class="text-sm font-medium tabular-nums text-red-500" v-tooltip="ctrans('Percentage of total stock value')">
							{{ stockHistoryGroup.percentage_dormant_1y }}%
						</span>
					</dd>
				</div>
				<div class="px-5 py-4">
					<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
						<FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" />
						{{ ctrans('No Sold 1Y') }}
					</dt>
					<dd class="mt-1 flex items-baseline gap-x-2">
						<span class="text-2xl font-semibold tabular-nums text-red-500">
							{{ locale.numberShort(stockHistoryGroup.number_org_stocks_not_sold_1y) }}
						</span>
						<span class="text-sm font-medium tabular-nums text-red-500" v-tooltip="ctrans('Percentage of total SKOs')">
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
							<th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">{{ ctrans('Organisation') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ ctrans('Stock Value') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ ctrans('SKOs') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ ctrans('Locations') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ ctrans('Out of Stock') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ ctrans('Dormant 1Y') }}</th>
							<th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">{{ ctrans('No Sold 1Y') }}</th>
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
									{{ locale.CurrencyShort(org.currency_code, Number(org.org_stock_lpp_value)) }}
								</Link>
								<span v-else class="text-gray-700">{{ locale.CurrencyShort(org.currency_code, Number(org.org_stock_lpp_value)) }}</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'org_stocks' })" class="text-gray-700 hover:text-blue-600 hover:underline">
									{{ locale.numberShort(org.number_org_stocks) }}
								</Link>
								<span v-else class="text-gray-700">{{ locale.numberShort(org.number_org_stocks) }}</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.locations.name, org.routes.locations.parameters)" class="text-gray-700 hover:text-blue-600 hover:underline">
									{{ locale.numberShort(org.number_locations) }}
								</Link>
								<span v-else class="text-gray-700">{{ locale.numberShort(org.number_locations) }}</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'out_of_stock' })" class="text-red-500 hover:text-red-700 hover:underline">
									{{ locale.numberShort(org.number_out_of_stock_org_stocks) }}
								</Link>
								<span v-else class="text-red-500">{{ locale.numberShort(org.number_out_of_stock_org_stocks) }}</span>
								<span class="text-xs text-red-400 ml-1">{{ org.percentage_out_of_stock }}%</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'dormant_stock_1y' })" class="text-red-500 hover:text-red-700 hover:underline">
									{{ locale.CurrencyShort(org.currency_code, Number(org.value_dormant_stock_1y)) }}
								</Link>
								<span v-else class="text-red-500">{{ locale.CurrencyShort(org.currency_code, Number(org.value_dormant_stock_1y)) }}</span>
								<span class="text-xs text-red-400 ml-1">{{ org.percentage_dormant_1y }}%</span>
							</td>
							<td class="px-4 py-2.5 text-right tabular-nums whitespace-nowrap">
								<Link v-if="org.routes" :href="route(org.routes.history.name, { ...org.routes.history.parameters, tab: 'not_sold_1y' })" class="text-red-500 hover:text-red-700 hover:underline">
									{{ locale.numberShort(org.number_org_stocks_not_sold_1y) }}
								</Link>
								<span v-else class="text-red-500">{{ locale.numberShort(org.number_org_stocks_not_sold_1y) }}</span>
								<span class="text-xs text-red-400 ml-1">{{ org.percentage_not_sold_1y }}%</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div v-if="stockHistoryGroup" v-show="currentSection === 'warehouse'">
			<Deferred data="warehouseOverview">
				<template #fallback>
					<div class="px-3 sm:px-6 mb-4 grid grid-cols-1 xl:grid-cols-3 gap-4" aria-busy="true">
						<div v-for="index in 3" :key="index" class="h-72 animate-pulse rounded-lg bg-gray-100" />
					</div>
				</template>
				<GroupWarehouseOverview v-if="warehouseOverview" :overview="warehouseOverview" />
			</Deferred>
		</div>

		<div v-show="currentSection === 'sales'">
		<ShopMonthTarget
			v-if="props.dashboard?.super_blocks?.[0]?.month_target"
			:month-target="props.dashboard.super_blocks[0].month_target"
			:year-target="props.dashboard.super_blocks[0].year_target"
		/>

		<KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
			<TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" gutterClass="px-4" />
		</KeepAlive>

		<ShopIntervalStats v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks" :shop-blocks="props.dashboard?.super_blocks?.[0]?.shop_blocks" />

		<DashboardSettings
			v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0]?.settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks?.[0]?.current_tab"
		/>

		<DashboardTable
			v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			class="mx-4 !px-0 border-t border-gray-200 mt-4"
			:idTable="props.dashboard?.super_blocks?.[0]?.id"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0]?.settings"
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
			:settings="props.dashboard?.super_blocks?.[0]?.settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab"
			:showTabs="false"
			@onChangeTab="onChangeDashboardTab"
		/>

		<DashboardWidget
			v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			class="mt-12"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
		/>

		<DashboardShopWidget
			v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks"
			:interval="props.dashboard?.super_blocks?.[0]?.intervals?.value"
			:data="props.dashboard?.super_blocks?.[0]?.shop_blocks"
		/>
		</div>

		<div v-if="!props.dashboard?.super_blocks?.length && !operations" class="flex flex-col items-center justify-center px-4 py-24" role="status">
			<FontAwesomeIcon icon="fal fa-chart-line" class="mb-4 text-6xl text-gray-300" fixed-width aria-hidden="true" />
			<h3 class="mb-2 text-center text-lg font-medium text-gray-500">
				{{ ctrans('No sales data to show') }}
			</h3>
			<p class="max-w-md text-center text-sm text-gray-400">
				{{ ctrans('Your account does not have access to the group sales figures.') }}
			</p>
		</div>
	</div>
</template>
