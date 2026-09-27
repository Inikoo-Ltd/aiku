<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import ShopIntervalStats from "./ShopIntervalStats.vue"
import ChannelHealthBadges from "./ChannelHealthBadges.vue"
import ShopDashboardWidgets from "./ShopDashboardWidgets.vue"
import { ref, provide, computed, onMounted } from "vue"
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
import { set, pick, omit } from 'lodash-es'
import { Dashboard } from "@/types/Components/Dashboard"
import DashboardShopWidget from "@/Components/DataDisplay/Dashboard/DashboardShopWidget.vue"
import { useTabChange } from "@/Composables/tab-change"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import ShopMonthTarget from "@/Components/DataDisplay/Dashboard/ShopMonthTarget.vue"
import axios from "axios"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { faBullseyeArrow, faUserFriends, faCodeBranch, faPlug, faChartLine } from "@fal"
library.add(faBullseyeArrow, faUserFriends, faCodeBranch, faPlug, faChartLine, faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faTriangle, faArrowRight, faCopyright)

const props = defineProps<{
	dashboard?: Dashboard
}>()

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)

const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)

const widgetsInterval = ref(props.dashboard?.super_blocks?.[0]?.intervals?.value ?? 'all')

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

const sections = computed(() => props.dashboard?.super_blocks?.[0]?.sections)
const currentSection = ref<string>(sections.value?.current ?? "")
const inSection = (...keys: string[]) => !sections.value || keys.includes(currentSection.value)

const PLATFORMS_TABLE = "ds_platforms"

const SECTION_WIDGETS: Record<string, string[]> = {
    customers: ["subscriptions", "top_customers", "email"],
    sales_channels: ["channels", "top_customers"],
    platforms: [],
    tendencies: ["top_products", "top_families", "out_of_stock", "marketing", "top_webpages"],
}

const sectionTableData = computed(() => {
    const block = props.dashboard?.super_blocks?.[0]?.blocks?.[0]
    if (!block || !sections.value) {
        return block
    }

    if (currentSection.value === "platforms") {
        return { ...block, tabs: pick(block.tabs, [PLATFORMS_TABLE]), current_tab: PLATFORMS_TABLE }
    }

    const tabs = omit(block.tabs, [PLATFORMS_TABLE])
    const current_tab = block.current_tab === PLATFORMS_TABLE ? Object.keys(tabs)[0] : block.current_tab

    return { ...block, tabs, current_tab }
})

const loadSectionTable = () => {
    const tableTab = sectionTableData.value?.current_tab
    if (tableTab && inSection("platforms", "tendencies")) {
        fetchDashboardTabData(tableTab)
    }
}

const onChangeSection = (section: string) => {
    currentSection.value = section
    axios.patch(route("grp.models.profile.update"), { settings: { shop_dashboard_section: section } })
    loadSectionTable()
}

onMounted(() => {
    if (sections.value) {
        loadSectionTable()
    }
})
</script>

<template>
	<div>
        <Tabs v-if="sections" :navigation="sections.navigation" :current="currentSection" @update:tab="onChangeSection" />

        <ShopMonthTarget
            v-if="inSection('target') && props.dashboard?.super_blocks?.[0]?.month_target"
            :month-target="props.dashboard.super_blocks[0].month_target"
        />

        <KeepAlive v-if="inSection('target') && props.dashboard?.super_blocks?.[0]?.tabs_box">
            <TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" />
        </KeepAlive>

        <slot v-if="inSection('tendencies')" name="afterTabsBox" />

        <ShopIntervalStats v-if="inSection('customers') && props.dashboard?.super_blocks?.[0]?.shop_blocks" :shop-blocks="props.dashboard?.super_blocks?.[0]?.shop_blocks" />

        <ChannelHealthBadges
            v-if="inSection('sales_channels') && props.dashboard?.super_blocks?.[0]?.channel_health?.length"
            :channel-health="props.dashboard?.super_blocks?.[0]?.channel_health"
        />

		<DashboardSettings
            v-if="!sections || !inSection('target')"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks?.[0]?.current_tab"
			@intervalChanged="(value: string) => widgetsInterval = value"
		/>

		<DashboardTable
            v-if="inSection('platforms', 'tendencies') && sectionTableData && Object.keys(sectionTableData.tabs ?? {}).length"
            :key="currentSection"
			class="border-t border-gray-200"
			:idTable="props.dashboard?.super_blocks?.[0]?.id"
			:tableData="sectionTableData"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="sectionTableData.current_tab"
			:showTabs="!sections || currentSection !== 'platforms'"
			@onChangeTab="onChangeDashboardTab"
		/>

		<DashboardTable
            v-if="!sections && props.dashboard?.super_blocks?.[0]?.blocks_2?.[0]?.tables?.[props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab]"
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
            v-if="inSection('tendencies') && props.dashboard?.super_blocks?.[0]?.blocks"

			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
		/>

        <DashboardShopWidget
            v-if="inSection('tendencies') && props.dashboard?.super_blocks?.[0]?.shop_blocks"
            :interval="props.dashboard?.super_blocks?.[0]?.intervals?.value"
            :data="props.dashboard?.super_blocks?.[0]?.shop_blocks"
        />

        <ShopDashboardWidgets
            v-if="(!sections || !inSection('target', 'platforms')) && props.dashboard?.super_blocks?.[0]?.widgets_route"
            :key="currentSection"
            :fetch-route="props.dashboard.super_blocks[0].widgets_route"
            :interval="widgetsInterval"
            :only="sections ? SECTION_WIDGETS[currentSection] : undefined"
        />

        <Link
            v-if="inSection('tendencies') && props.dashboard?.super_blocks?.[0]?.brands_link"
            :href="route(props.dashboard.super_blocks[0].brands_link.route.name, props.dashboard.super_blocks[0].brands_link.route.parameters)"
            class="px-4 py-3 inline-flex items-center gap-1 text-sm opacity-60 hover:opacity-100"
        >
            <FontAwesomeIcon v-if="props.dashboard.super_blocks[0].brands_link.icon" :icon="props.dashboard.super_blocks[0].brands_link.icon" fixed-width aria-hidden="true" />
            {{ props.dashboard.super_blocks[0].brands_link.title }}
            <FontAwesomeIcon icon="fal fa-arrow-right" class="text-xs" fixed-width aria-hidden="true" />
        </Link>
	</div>
</template>
