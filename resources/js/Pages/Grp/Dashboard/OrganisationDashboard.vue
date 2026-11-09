<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 18 Jan 2024 15:36:09 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { provide, ref } from "vue"
import { faChevronDown } from "@far"
import { faPlay, faSortDown, faSortUp } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Deferred, Head } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { faSitemap, faTriangle } from "@fal"
import axios from "axios"
import { set } from "lodash-es"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import { Dashboard as DashboardTS } from "@/types/Components/Dashboard"
import ProductDevelopmentWaitingWidget, { type ProductDevelopmentWaitingWidgetData } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentWaitingWidget.vue"
import CleanHandoverPanel, { type CleanHandoverData } from "@/Components/Procurement/CleanHandoverPanel.vue"
import ShopMonthTarget from "@/Components/DataDisplay/Dashboard/ShopMonthTarget.vue"
import CompetitorsSummaryLine from "@/Components/DataDisplay/Dashboard/CompetitorsSummaryLine.vue"
import AgentDashboardPurchaseOrders, { type AgentDashboardPurchaseOrdersData } from "@/Components/Procurement/AgentDashboardPurchaseOrders.vue"

library.add(faTriangle, faChevronDown, faSortDown, faSortUp, faPlay, faSitemap)

const props = defineProps<{
	dashboard: DashboardTS
	cleanHandover?: CleanHandoverData
	isAgentOrganisation?: boolean
	agentPurchaseOrders?: AgentDashboardPurchaseOrdersData
	productDevelopmentsWaiting?: ProductDevelopmentWaitingWidgetData | null
	competitors?: object | null
	salesTargets?: {
		month: InstanceType<typeof ShopMonthTarget>["$props"]["monthTarget"]
		year: InstanceType<typeof ShopMonthTarget>["$props"]["yearTarget"]
	} | null
}>()

const isLoadingOnTable = ref(false)
provide('isLoadingOnTable', isLoadingOnTable)

const failedTableTab = ref<string | null>(null)
provide('failedTableTab', failedTableTab)

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
		const { data } = await axios.get(route(fetchRoute.name, fetchRoute.parameters ?? {}), {
			params: {
				tab: tabSlug,
			},
		})

		if (data?.tab && data?.table) {
			set(props, `dashboard.super_blocks[0].blocks[0].tables.${data.tab}`, data.table)
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
	<Head :title="ctrans('Dashboard')" />
	<div>
		<Deferred v-if="props.isAgentOrganisation" data="agentPurchaseOrders">
			<template #fallback>
				<div class="mx-4 mt-4 space-y-4">
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
						<div v-for="tile in 4" :key="tile" class="skeleton h-28 rounded-lg" />
					</div>
					<div class="skeleton h-24 rounded-lg" />
					<div class="skeleton h-64 rounded-lg" />
				</div>
			</template>
			<AgentDashboardPurchaseOrders v-if="props.agentPurchaseOrders" class="mx-4 mt-4" :data="props.agentPurchaseOrders" />
		</Deferred>
		<CleanHandoverPanel v-if="props.cleanHandover" class="mx-4 mt-4" :data="props.cleanHandover" />
		<Deferred v-if="props.dashboard?.super_blocks?.length" data="salesTargets">
			<template #fallback>
				<div class="skeleton mx-4 mt-4 h-72 rounded-lg" aria-busy="true" />
			</template>
			<ShopMonthTarget v-if="props.salesTargets" :month-target="props.salesTargets.month" :year-target="props.salesTargets.year">
				<template v-if="props.competitors !== null" #footer>
					<CompetitorsSummaryLine />
				</template>
			</ShopMonthTarget>
		</Deferred>
		<KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
			<TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" gutterClass="px-4" />
		</KeepAlive>

		<div v-if="props.dashboard?.super_blocks?.[0]" class="mx-4 mt-4 rounded-lg border border-gray-200 bg-white pb-4 shadow-sm">
		<DashboardSettings
			v-if="props.dashboard?.super_blocks?.[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks?.[0]?.current_tab"
		/>

		<DashboardTable
			v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			class="mx-4 !p-0 border-t border-gray-200"
			:idTable="props.dashboard?.super_blocks?.[0]?.id"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab"
			@onChangeTab="onChangeDashboardTab"
		/>
		</div>
		<Deferred v-if="props.productDevelopmentsWaiting !== null" data="productDevelopmentsWaiting">
			<template #fallback>
				<div class="skeleton mx-4 my-4 h-40 rounded-lg" />
			</template>
			<ProductDevelopmentWaitingWidget v-if="props.productDevelopmentsWaiting" class="mx-4 my-4" :data="props.productDevelopmentsWaiting" />
		</Deferred>
	</div>
</template>
