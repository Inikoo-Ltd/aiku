<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import { faChevronDown } from "@far"
import { faChartLine, faPlay } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Head } from "@inertiajs/vue3"
import { faCog, faFolderOpen, faSeedling, faTimesCircle, faTriangle, faSitemap } from "@fal"
import "tippy.js/dist/tippy.css"
import { capitalize } from "@/Composables/capitalize"
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

ChartJS.register(ArcElement, Tooltip, Legend, Colors)


</script>

<template>
	<Head :title="capitalize(title)" />

	<div class="grid grid-cols-12 m-3 gap-4">
		<div class="col-span-12">
			<Dashboard :dashboard="props.dashboard" />

		</div>
	</div>
</template>
