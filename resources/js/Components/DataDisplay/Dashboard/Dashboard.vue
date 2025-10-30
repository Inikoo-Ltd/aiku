<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
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
import DashboardShopWidget from "@/Components/DataDisplay/Dashboard/DashboardShopWidget.vue";
import TabsBox from "@/Components/Navigation/TabsBox.vue";
import { useTabChange } from "@/Composables/tab-change";
library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faTriangle)

const props = defineProps<{
	dashboard?: Dashboard
}>()

console.log(props.dashboard?.super_blocks?.[0]?.stats_box);

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)

const currentTab = ref(props.dashboard?.super_blocks?.[0]?.tabs_box?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);
</script>

<template>
	<div>
        <KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
            <TabsBox :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" :current="currentTab" @update:tab="handleTabUpdate" />
        </KeepAlive>

        <div v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 px-4 pt-4">
            <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
                <div>
                    {{ props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.visitors?.['all'].formatted_value ?? 0 }}
                    <span>Visitors</span>
                </div>
            </div>
            <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
                <div>
                    {{ props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.registrations?.['1w'].formatted_value ?? 0 }}
                    <span>New Customers</span>
                    <span class="ml-1 text-gray-500 text-sm italic">(1w)</span>
                </div>
            </div>
            <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
                <div>
                    {{ props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.orders?.['1w'].formatted_value ?? 0 }}
                    <span>Last Orders</span>
                    <span class="ml-1 text-gray-500 text-sm italic">(1w)</span>
                </div>
            </div>
        </div>

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
