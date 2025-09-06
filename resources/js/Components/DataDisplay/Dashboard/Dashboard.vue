<script setup lang="ts">
import DashboardSettings from "./DashboardSettings.vue"
import DashboardTable from "./DashboardTable.vue"
import DashboardWidget from "./DashboardWidget.vue"
import { ref, provide } from "vue"
import { faTriangle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { set } from 'lodash-es'
import { Dashboard } from "@/types/Components/Dashboard"
library.add(faTriangle)

const props = defineProps<{
	dashboard?: Dashboard
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
