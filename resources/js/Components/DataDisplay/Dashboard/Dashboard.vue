<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import ShopIntervalStats from "./ShopIntervalStats.vue"
import ChannelHealthBadges from "./ChannelHealthBadges.vue"
import { ref, provide } from "vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faArrowRight,
    faBox,
    faBoxesAlt,
    faCheckCircle,
    faCircle,
    faCopyright,
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
library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faTriangle, faArrowRight, faCopyright)

const props = defineProps<{
	dashboard?: Dashboard
}>()

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)

const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)

const currentTab = ref(props.dashboard?.super_blocks?.[0]?.tabs_box?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const fetchDashboardTabData = async (tabSlug: string, force: boolean = false): Promise<void> => {
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
        const { data } = await axios.get(route(fetchRoute.name, fetchRoute.parameters ?? {}), {
            params: { tab: tabSlug },
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
</script>

<template>
	<div>
        <KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
            <TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" />
        </KeepAlive>

        <ShopIntervalStats v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks" :shop-blocks="props.dashboard?.super_blocks?.[0]?.shop_blocks" />

        <ChannelHealthBadges
            v-if="props.dashboard?.super_blocks?.[0]?.channel_health?.length"
            :channel-health="props.dashboard?.super_blocks?.[0]?.channel_health"
        />

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

        <Link
            v-if="props.dashboard?.super_blocks?.[0]?.brands_link"
            :href="route(props.dashboard.super_blocks[0].brands_link.route.name, props.dashboard.super_blocks[0].brands_link.route.parameters)"
            class="px-4 py-3 inline-flex items-center gap-1 text-sm opacity-60 hover:opacity-100"
        >
            <FontAwesomeIcon v-if="props.dashboard.super_blocks[0].brands_link.icon" :icon="props.dashboard.super_blocks[0].brands_link.icon" fixed-width aria-hidden="true" />
            {{ props.dashboard.super_blocks[0].brands_link.title }}
            <FontAwesomeIcon icon="fal fa-arrow-right" class="text-xs" fixed-width aria-hidden="true" />
        </Link>
	</div>
</template>
