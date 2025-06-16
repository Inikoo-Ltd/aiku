<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import { ref, provide } from "vue"
import { faTriangle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { set } from 'lodash-es'
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
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
		/>

	</div>
</template>
