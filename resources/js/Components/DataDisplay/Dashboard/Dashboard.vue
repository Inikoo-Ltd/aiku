<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import ShopIntervalStats from "./ShopIntervalStats.vue"
import { ref, provide } from "vue"
import {
    faBox,
    faBoxesAlt,
    faCheckCircle,
    faCircle,
    faHandsHelping,
    faInventory,
    faMapSigns,
    faTriangle,
    faWarehouse
} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { set } from 'lodash-es'
import { Dashboard } from "@/types/Components/Dashboard"
import DashboardShopWidget from "@/Components/DataDisplay/Dashboard/DashboardShopWidget.vue"
import { useTabChange } from "@/Composables/tab-change"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import axios from "axios"
library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faTriangle)

const props = defineProps<{
	dashboard?: Dashboard
}>()

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)

const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)

const currentTab = ref(props.dashboard?.super_blocks?.[0]?.tabs_box?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const fetchDashboardTabData = async (tabSlug: string, force: boolean = false, intervalOverride?: string): Promise<void> => {
    const block = props.dashboard?.super_blocks?.[0]?.blocks?.[0]
    const fetchRoute = block?.tab_fetch_route
    if (!block?.tables || !fetchRoute?.name) {
        return
    }

    if (!force && block.tables[tabSlug]) {
        return
    }

    isLoadingOnTable.value = true
    try {
        const params: Record<string, string> = { tab: tabSlug }
        if (intervalOverride) {
            params.interval = intervalOverride
        }

        const { data } = await axios.get(route(fetchRoute.name, fetchRoute.parameters ?? {}), {
            params,
        })

        if (data?.tab && data?.table) {
            const currentTables = props.dashboard?.super_blocks?.[0]?.blocks?.[0]?.tables ?? {}
            set(props, 'dashboard.super_blocks[0].blocks[0].tables', {
                ...currentTables,
                [data.tab]: data.table,
            })
        }
    } finally {
        isLoadingOnTable.value = false
    }
}

const onChangeDashboardTab = async (tabSlug: string): Promise<void> => {
    set(props, "dashboard.super_blocks[0].blocks[0].current_tab", tabSlug)
    await fetchDashboardTabData(tabSlug)
}

const onIntervalChanged = async (newInterval: string): Promise<void> => {
    const block = props.dashboard?.super_blocks?.[0]?.blocks?.[0]
    if (!block?.tables) {
        return
    }

    const intervalSensitiveTabs = ['top_customers']
    const activeTab = block.current_tab

    const nextTables = { ...block.tables }
    intervalSensitiveTabs.forEach((tab) => {
        delete nextTables[tab]
    })
    set(props, 'dashboard.super_blocks[0].blocks[0].tables', nextTables)

    if (activeTab && intervalSensitiveTabs.includes(activeTab)) {
        await fetchDashboardTabData(activeTab, true, newInterval)
    }
}
</script>

<template>
	<div>
        <KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
            <TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" />
        </KeepAlive>

        <ShopIntervalStats v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks" :shop-blocks="props.dashboard?.super_blocks?.[0]?.shop_blocks" />

		<DashboardSettings
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks?.[0]?.current_tab"
			@intervalChanged="onIntervalChanged"
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
			@onChangeTab="(val) => {
				set(props, 'dashboard.super_blocks[0].blocks[0].current_tab', val)
			}"
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
