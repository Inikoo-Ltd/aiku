<script setup lang="ts">
import { inject } from "vue"
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import { faChevronDown } from "@far"
import { faChartLine, faPlay } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { faCog, faFolderOpen, faSeedling, faTimesCircle, faTriangle, faSitemap } from "@fal"
import "tippy.js/dist/tippy.css"
import DashboardOld from "@/Components/DataDisplay/Dashboard/DashboardOld.vue"
import { capitalize } from "@/Composables/capitalize"


import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import DashboardWidget from "@/Components/DataDisplay/Dashboard/DashboardWidget.vue"
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue"

library.add(faTriangle, faSitemap, faChevronDown, faSeedling, faTimesCircle, faFolderOpen, faPlay, faCog, faChartLine)

const props = defineProps<{
	title: string
	groupStats: {
		currency: {
			code: string
			symbol: string
		}
		total: {
			[key: string]: {
				total_invoices: number
				total_refunds: number
				total_sales: string
			}
		}
		organisations: {
			name: string
			type: string
			code: string
			currency: {
				code: string
				symbol: string
			}
			invoices: {
				number_invoices: number
			}
			sales: {
				number_sales: number
			}
			refunds: {
				number_refunds: number
			}


			interval_percentages?: {
				sales?: {
					[key: string]: {
						amount: string
						percentage: number
						difference: number
					}
				}
				invoices?: {
					[key: string]: {
						amount: string
						percentage: number
						difference: number
					}
				}
				refunds?: {
					[key: string]: {
						amount: string
						percentage: number
						difference: number
					}
				}
			}
		}[]
	}
	interval_options: {
		label: string
		labelShort: string
		value: string
	}[]
	dashboard_stats: {
		columns?: {
			widgets?: {
				data: {
					currency: {
						code: string
						symbol: string
					}
					total: {
						[key: string]: {
							total_invoices: number
							total_refunds: number
							total_sales: string
						}
					}
					organisations: {
						name: string
						type: string
						code: string
						currency: {
							code: string
							symbol: string
						}
						invoices: {
							number_invoices: number
						}
						sales: {
							number_sales: number
						}
						refunds: {
							number_refunds: number
						}
						interval_percentages?: {
							sales?: {
								[key: string]: {
									amount: string
									percentage: number
									difference: number
								}
							}
							invoices?: {
								[key: string]: {
									amount: string
									percentage: number
									difference: number
								}
							}
							refunds?: {
								[key: string]: {
									amount: string
									percentage: number
									difference: number
								}
							}
						}
					}[]
				}[]
			}[]
		}
		widgets: {
			column_count: number
			components: {
				type: string
				col_span?: number
				row_span?: number
				data: {}
			}
		}
		settings: {}
	}
	dashboard: {}
}>()

console.log(props)


const layout = inject("layout", layoutStructure)



ChartJS.register(ArcElement, Tooltip, Legend, Colors)
const options = {
	responsive: true,
	plugins: {
		legend: {
			display: false,
		},
		tooltip: {
			titleFont: {
				size: 10,
				weight: "lighter",
			},
			bodyFont: {
				size: 11,
				weight: "bold",
			},
		},
	},
}




</script>

<template>
	<Head :title="capitalize(title)" />

	<div class="grid grid-cols-12 m-3 gap-4">
		<div class="col-span-12">
			<Dashboard
				:dashboard="props.dashboard"
			/>

			<!-- <DashboardSettings
				:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
				:settings="props.dashboard?.super_blocks?.[0].settings"
			/>
			
			<DashboardTable
				:tableData="props.dashboard?.super_blocks?.[0]?.blocks[0]"
				:intervals="props.dashboard?.super_blocks?.[0]?.intervals"
				:settings="props.dashboard?.super_blocks?.[0].settings"
			/>

			<DashboardWidget v-if="props.dashboard?.widgets" :widgetsData="dashboard.widgets" /> -->
		</div>
	</div>
</template>

<style>
.align-right {
	justify-items: end;
	text-align: right;
}
.transition-opacity {
	transition: opacity 0.3s ease-in-out;
}
.overflow-x-auto {
	overflow-x: auto;
}
</style>
