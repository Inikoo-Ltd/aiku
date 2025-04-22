<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import { inject, ref, computed, provide } from "vue"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTriangle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { set } from "lodash"
library.add(faTriangle)

const props = defineProps<{
	dashboard?: {
		id: string  //  organisation_dashboard_tab
		super_blocks: {
			settings: {
				[key: string]: {  // 'data_display_type' || 'model_state_type' || 'currency_type'
					align: string
					id: string
					options: {
						label: string
						value: string
						tooltip?: string
					}[]
					type: string
					value: string
				}
			}
			interval_options?: Array<{ label: string; value: string }>
			table?: {}[]
			total?: {}[]
			widgets?: {}[]
			currency_code?: string
			current?: string
			total_tooltip?:{}[]
		}[]
	}
}>()

const dashboardTabActive = ref('')
provide("dashboardTabActive", dashboardTabActive)

</script>

<template>
	<div>
		<DashboardSettings
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab"
		/>

		<DashboardTable
			:idTable="props.dashboard?.super_blocks?.[0]?.id"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
			:settings="props.dashboard?.super_blocks?.[0].settings"
			:currentTab="props.dashboard?.super_blocks?.[0]?.blocks[0].current_tab"
			@onChangeTab="(val) => {
				set(props, 'dashboard.super_blocks[0].blocks[0].current_tab', val)
			}"
		/>

		<DashboardWidget v-if="props.dashboard?.widgets" :widgetsData="dashboard.widgets" />
		
		<!-- <DashboardSettings
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

		<DashboardWidget v-if="props.dashboard?.widgets" :widgetsData="dashboard.widgets" /> -->
	</div>
</template>
