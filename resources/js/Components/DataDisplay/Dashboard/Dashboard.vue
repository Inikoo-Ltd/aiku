<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import { ref, provide, inject } from "vue"
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
import { useTabChange } from "@/Composables/tab-change";
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faTriangle)

const props = defineProps<{
	dashboard?: Dashboard
}>()

const locale = inject('locale', aikuLocaleStructure);

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)

const currentTab = ref(props.dashboard?.super_blocks?.[0]?.tabs_box?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const getExpectedSales = () => {
    const currentMonthSales = props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.sales_org_currency?.['mtd']?.raw_value;
    const lastMonthSales = props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.sales_org_currency?.['lm']?.raw_value;
    const currentMonthOrders = props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.orders?.['mtd']?.raw_value;
    const lastMonthOrders = props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.orders?.['lm']?.raw_value;

    if (!currentMonthSales || !lastMonthSales || !currentMonthOrders || !lastMonthOrders) {
        return locale.currencyFormat(
            props.dashboard?.super_blocks?.[0]?.shop_blocks?.currency_code,
            0
        );
    }

    const now = new Date();
    const currentDay = now.getDate();
    const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
    const remainingDays = daysInMonth - currentDay;

    const currentAOV = parseFloat(currentMonthSales) / currentMonthOrders;
    const lastMonthAOV = parseFloat(lastMonthSales) / lastMonthOrders;

    const projectedAOV = (currentAOV * 0.6) + (lastMonthAOV * 0.4);

    const lastMonthDays = new Date(now.getFullYear(), now.getMonth(), 0).getDate();
    const avgOrdersPerDay = lastMonthOrders / lastMonthDays;

    const currentOrderRate = currentMonthOrders / currentDay;

    const projectedOrderRate = (currentOrderRate * 0.7) + (avgOrdersPerDay * 0.3);

    const projectedTotalOrders = currentMonthOrders + (projectedOrderRate * remainingDays);

    const expectedSales = projectedTotalOrders * projectedAOV;

    return locale.currencyFormat(
        props.dashboard?.super_blocks?.[0]?.shop_blocks?.currency_code,
        expectedSales
    );
}

const getAverageCLV = () => {
    const clv = props.dashboard?.super_blocks?.[0]?.shop_blocks?.average_clv;
    if (!clv || clv === '0') return null;

    return locale.currencyFormat(
        props.dashboard?.super_blocks?.[0]?.shop_blocks?.currency_code,
        parseFloat(clv)
    );
}

const getHistoricCLV = () => {
    const historicClv = props.dashboard?.super_blocks?.[0]?.shop_blocks?.average_historic_clv;
    if (!historicClv || historicClv === '0') return null;

    return locale.currencyFormat(
        props.dashboard?.super_blocks?.[0]?.shop_blocks?.currency_code,
        parseFloat(historicClv)
    );
}
</script>

<template>
	<div>
        <KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
            <TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" />
        </KeepAlive>

        <div v-if="props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 px-4 pt-4">
            <!-- Visitors -->
            <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
                <div class="text-sm w-full">
                    <p class="text-lg font-bold mb-1">Visitors</p>
                    <span class="text-2xl font-bold">
                        {{ props.dashboard?.super_blocks?.[0]?.shop_blocks?.interval_data?.visitors?.['all']?.formatted_value ?? '0' }}
                    </span>
                    <p class="text-xs text-gray-500 mt-1">Total visitors</p>
                </div>
            </div>

            <!-- Expected Sales -->
            <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
                <div class="text-sm w-full">
                    <p class="text-lg font-bold mb-1">Expected Sales</p>
                    <span class="text-2xl font-bold">
                        {{ getExpectedSales() }}
                    </span>
                    <p class="text-xs text-gray-500 mt-1">Projected this month</p>
                </div>
            </div>

            <!-- Average CLV -->
            <div v-if="getAverageCLV() !== null" class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
                <div class="text-sm w-full">
                    <p class="text-lg font-bold mb-1">Average CLV</p>
                    <span class="text-2xl font-bold">
                        {{ getAverageCLV() }}
                    </span>
                    <p class="text-xs text-gray-500 mt-1">Customer Lifetime Value</p>
                </div>
            </div>

            <!-- Historic CLV -->
            <div v-if="getHistoricCLV() !== null" class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
                <div class="text-sm w-full">
                    <p class="text-lg font-bold mb-1">Historic CLV</p>
                    <span class="text-2xl font-bold">
                        {{ getHistoricCLV() }}
                    </span>
                    <p class="text-xs text-gray-500 mt-1">Actual revenue per customer</p>
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
