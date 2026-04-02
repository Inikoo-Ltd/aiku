<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import ShopIntervalStats from "./ShopIntervalStats.vue"
import { ref, provide } from "vue"
import {
    faBox,
    faBoxesAlt,
    faBoxOpen,
    faCheckCircle,
    faCircle,
    faHandsHelping,
    faInventory,
    faMapMarkerAlt,
    faMapSigns,
    faPalletAlt,
    faBan,
    faSkullCow,
    faTimesCircle,
    faTriangle,
    faWarehouse
} from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { set } from 'lodash-es'
import { Dashboard } from "@/types/Components/Dashboard"
import DashboardShopWidget from "@/Components/DataDisplay/Dashboard/DashboardShopWidget.vue"
import { useTabChange } from "@/Composables/tab-change"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faBoxOpen, faCircle, faCheckCircle, faHandsHelping, faTriangle, faMapMarkerAlt, faPalletAlt, faBan, faSkullCow, faTimesCircle)

const props = defineProps<{
	dashboard?: Dashboard
}>()

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)

const currentTab = ref(props.dashboard?.super_blocks?.[0]?.tabs_box?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
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
		/>

		<DashboardTable
            v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			class="border-t border-gray-200"
			:idTable="props.dashboard?.super_blocks?.[0]?.id"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab"
			@onChangeTab="(val) => {
				set(props, 'dashboard.super_blocks[0].blocks[0].current_tab', val)
			}"
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

		<DashboardTable
            v-if="props.dashboard?.super_blocks?.[0]?.stock_snapshot_table && props.dashboard?.super_blocks?.[0]?.blocks?.[0]?.current_tab === 'organisations'"
			class="border-t border-gray-200"
			idTable="stock_snapshot_table"
			:tableData="{
				...props.dashboard.super_blocks[0].stock_snapshot_table,
				current_tab: 'organisations'
			}"
			:intervals="props.dashboard.super_blocks[0].intervals"
			:settings="props.dashboard.super_blocks[0].settings"
			:currentTab="'organisations'"
			:showTabs="false"
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
