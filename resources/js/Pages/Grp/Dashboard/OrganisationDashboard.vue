<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 18 Jan 2024 15:36:09 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { provide, ref } from "vue"
import { faChevronDown } from "@far"
import { faPlay, faTriangle ,faSortDown, faSortUp } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { faSitemap } from "@fal"
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue"

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


const isLoadingOnTable = ref(false)
provide('isLoadingOnTable', isLoadingOnTable)
</script>

<template>
	<Head :title="trans('Dashboard')" />
	<Dashboard :dashboard="props.dashboard" />

</template>

