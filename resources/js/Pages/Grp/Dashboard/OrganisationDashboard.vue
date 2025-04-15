<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 18 Jan 2024 15:36:09 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { provide, ref } from "vue"
import { faChevronDown } from "@far"
import { faPlay, faTriangle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { faSortDown, faSortUp } from "@fas"
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue"
import { faSitemap } from "@fal"
import DashboardTableNewwww from "@/Components/DataDisplay/Dashboard/DashboardTableNewwww.vue"
import DashboardSettingsNew from "@/Components/DataDisplay/Dashboard/DashboardSettingsNew.vue"
import DashboardWidgetNewww from "@/Components/DataDisplay/Dashboard/DashboardWidgetNewww.vue"

library.add(faTriangle, faChevronDown, faSortDown, faSortUp, faPlay, faSitemap)

const props = defineProps<{
	interval_options: {
		label: string
		labelShort: string
		value: string
	}[]
	dashboard_stats:{}
	dashboard: {
		super_blocks: {
			id: string  // 'main_sales'
			blocks: {
				charts: []
				current_tab: string  // 'shops'
				id: string  // 'sales_table'
				tables: {
					invoice_categories: []
					shops: {
						slug: string
						state: string
						columns: {
							baskets_created_org_currency: {
								'1y': {
									formatted_value: string  // "€0.00"
									raw_value: string  // "0.00"
									tooltip: string
								} 
							}
						}
					}[]
				}
				tabs: {
					[tab: string]: {
						icon: string
						title: string
					}
				}
			}[]
			intervals: {
				options: {
					label: string
					value: string
					labelShort: string
				}[]
				value: string
			}
			settings: {
				[key: string]: {  // 'model_state', 'data_display_type'
					align: string
					id: string
					options: {
						label: string
						value: string
					}[]
					type: string
					value: string
				}
			}
		}[]
	}
}>()



const checked = ref(true)

console.log('11 ewewqq', props.dashboard)
console.log('22 ewewqq', props.dashboard_stats)

const isLoadingOnTable = ref(false)
provide('isLoadingOnTable', isLoadingOnTable)
</script>

<template>
	<Head :title="trans('Dashboard')" />
	<!-- <pre>{{ props.dashboard?.super_blocks?.[0].settings }}</pre> -->
	<DashboardSettingsNew
		:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
		:settings="props.dashboard?.super_blocks?.[0].settings"
	/>
	<DashboardTableNewwww
		:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
		:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
		:settings="props.dashboard?.super_blocks?.[0].settings"
	/>

	<DashboardWidgetNewww v-if="props.dashboard?.widgets" :widgetsData="dashboard.widgets" />
	
	<!-- {{ props.dashboard?.super_blocks?.[0].blocks[0] }} -->

	<div class="grid grid-cols-12 m-3 gap-4 border-t-4 border-red-500 mt-12 pt-12">

    <!-- <pre>{{ dashboard }}</pre> -->

	<!-- <DashboardTableNewwww
		

	/> -->

	
	
				<!-- <div class="col-span-12">
					<Dashboard 
						:dashboard="dashboard_stats"
						:checked="checked"
						tableType="org"/>	
				</div> -->
		

	</div>
</template>

