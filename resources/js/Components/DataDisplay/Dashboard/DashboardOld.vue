<script setup lang="ts">
import DashboardWidget from "./DashboardWidgetOld.vue"
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

</script>

<template>
	<div>
    <DashboardWidget v-if="props.dashboard?.widgets" :widgetsData="dashboard.widgets" />
	</div>
</template>
