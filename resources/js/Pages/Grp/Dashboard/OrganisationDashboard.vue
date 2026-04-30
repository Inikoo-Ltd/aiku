<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 18 Jan 2024 15:36:09 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { provide, ref } from "vue"
import { faChevronDown } from "@far"
import { faPlay, faSortDown, faSortUp } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { faSitemap, faTriangle } from "@fal"
import axios from "axios"
import { set } from "lodash-es"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import DashboardWidget from "@/Components/DataDisplay/Dashboard/DashboardWidget.vue"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import { Dashboard as DashboardTS } from "@/types/Components/Dashboard"

library.add(faTriangle, faChevronDown, faSortDown, faSortUp, faPlay, faSitemap)

const props = defineProps<{
	interval_options: {
		label: string
		labelShort: string
		value: string
	}[]
	dashboard_stats:{}
	dashboard: DashboardTS
}>()

const isLoadingOnTable = ref(false)
provide('isLoadingOnTable', isLoadingOnTable)

const fetchDashboardTabData = async (tabSlug: string): Promise<void> => {
	const block = props.dashboard?.super_blocks?.[0]?.blocks?.[0]
	const fetchRoute = block?.tab_fetch_route
	if (!block?.tables || !fetchRoute?.name) {
		return
	}

	if (block.tables[tabSlug]) {
		return
	}

	isLoadingOnTable.value = true
	try {
		const { data } = await axios.get(route(fetchRoute.name, fetchRoute.parameters ?? {}), {
			params: {
				tab: tabSlug,
			},
		})

		if (data?.tab && data?.table) {
			set(props, `dashboard.super_blocks[0].blocks[0].tables.${data.tab}`, data.table)
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
	<Head :title="trans('Dashboard')" />
	<div>
		<KeepAlive v-if="props.dashboard?.super_blocks?.[0]?.tabs_box">
			<TabsBoxDisplay :tabs_box="props.dashboard?.super_blocks?.[0]?.tabs_box?.navigation" />
		</KeepAlive>

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

		<DashboardWidget
			v-if="props.dashboard?.super_blocks?.[0]?.blocks"
			:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
			:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
		/>
	</div>
</template>
