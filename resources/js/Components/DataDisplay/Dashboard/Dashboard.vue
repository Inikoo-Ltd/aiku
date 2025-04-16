<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import { inject, ref, computed, provide } from "vue"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTriangle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTriangle)

const props = defineProps<{
	dashboard?: {
		settings?: {}[]
		interval_options?: Array<{ label: string; value: string }>
		table?: {}[]
		total?: {}[]
		widgets?: {}[]
		currency_code?: string
		current?: string
		total_tooltip?:{}[]
	}
	checked?: boolean
	tableType?: string
}>()

const layout = inject("layout")
const locale = inject("locale")
const checked = ref(props.checked || false)


const isOrganisation = ref(false)
/* .filter((org) => {
			if (props.dashboard.settings.key_shop) {
                return org.state !== "closed";
            }
            return true;
		}) */

/* const tableDatas = computed(() => {
    const isShopOpen = props.dashboard.settings.db_settings.selected_shop_open === "open";

    if (props.tableType === "org") {
        return props.dashboard.table
            .filter((item) => {
                return isShopOpen ? item.state !== "closed" : true;
            })
            .map((org) => ({
                name: org.name,
                code: org.code,
                interval_percentages: org.interval_percentages,
                sales: org.interval_percentages?.sales?.amount || 0,
				invoices: org.interval_percentages?.invoices?.amount || 0,
				sales_percentage: org.interval_percentages?.sales?.percentage || 0,
				invoices_percentage: org.interval_percentages?.invoices?.percentage || 0,
                route: org.route,
                currency: org.currency_code,
            }));
    } else {
        return props.dashboard.table
            .filter((org) => org.type !== "agent") 
            .map((org) => ({
                name: org.name,
                code: org.code,
                interval_percentages: org.interval_percentages,
                sales: org.interval_percentages?.sales?.amount || 0,
				invoices: org.interval_percentages?.invoices?.amount || 0,
				sales_percentage: org.interval_percentages?.sales?.percentage || 0,
				invoices_percentage: org.interval_percentages?.invoices?.percentage || 0,
                currency: org.currency_code,
            }));
    }
}); */

/* const dashboardTable = [
	{
		tab_label: "Overview",
		tab_slug: "overview",
		type: "table",  // 
		data: tableDatas.value
	},
	{
		tab_label: "Profile",
		tab_slug: "profile",
		type: "xxx",  // 
		data: null
	},
] */

</script>

<template>
	<div>
		<DashboardSettings
			v-if="props.dashboard?.settings"
			:intervalOptions="props.dashboard?.interval_options"
			:tableType="tableType"
			:settings="props.dashboard?.settings" />

		<DashboardTable
			v-if="props.dashboard?.table"
			:dashboardTable="props.dashboard.table"
			:locale="locale"
			:tableType="props.tableType"
			:totalAmount="props.dashboard.total"
			:current="props.dashboard.current"
			:settings="props.dashboard?.settings"
			:currency_code="props.dashboard?.currency_code"
			:total_tooltip="props.dashboard?.total_tooltip" />

		<DashboardWidget v-if="props.dashboard?.widgets" :widgetsData="dashboard.widgets" />
	</div>
</template>
