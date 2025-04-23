<script setup lang="ts">
import CountUp from "vue-countup-v3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faExclamation, faInfo, faPlay } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, inject, ref } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import MeterGroup from "primevue/metergroup"
import { values } from "lodash"
import ChartDashboardDynamic from "../../ChartDashboardDynamic.vue"
import Chart from "primevue/chart"
import ProgressDashboardCard from "../../ProgressDashboardCard.vue"
import { Link } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useLocaleStore } from "@/Stores/locale"
library.add(faCheck, faExclamation, faInfo, faPlay)

// Props for dynamic behavior
const props = withDefaults(
	defineProps<{
		showRedBorder: boolean
		widget: {
			value: string
			description: string
			status: "success" | "warning" | "danger" | "information" | "neutral"
			type?: "number" | "currency"
			currency_code?: string
			route?: {}
		}
		visual?: any
	}>(),
	{
		widget: () => {
			return {
				value: "0",
				description: "",
				status: "information",
			}
		},
	}
)
// console.log(props)

// Example data to use in grand parent (Dashboard)
const widgets = {
	column_count: 4,
	components: [
		{
			type: "basic",
			col_span: 1,
			row_span: 2,
			data: {
				value: 0,
				description: "xxxxxxx",
				status: "success",
			},
		},
		{
			type: "basic",
			col_span: 1,
			row_span: 1,
			data: {
				value: 180000,
				description: "ggggggg",
				status: "danger",
				type: "currency",
				currency_code: "GBP",
			},
		},
		{
			type: "basic",
			col_span: 1,
			row_span: 1,
			data: {
				value: 662137,
				description: "ggggggg",
				// 'status': 'information',
				type: "currency",
				currency_code: "GBP",
			},
		},
		{
			type: "basic",
			col_span: 1,
			row_span: 1,
			data: {
				value: 99,
				type: "number",
				description: "Hell owrodl",
				status: "warning",
			},
		},
		{
			type: "basic",
			col_span: 3,
			row_span: 1,
			data: {
				value: 44400,
				description: "6666",
				status: "information",
				// 'status': 'success',
			},
		},
	],
}

const locale = inject("locale", aikuLocaleStructure)
const layoutStore = inject("layout", layoutStructure)

const getStatusColor = (status: string) => {
	switch (status) {
		case "success":
			return "bg-green-100 border border-green-400 text-green-600"
		case "warning":
			return "bg-yellow-100 border border-yellow-400 text-yellow-600"
		case "danger":
			return "bg-red-100 border border-red-400 text-red-600"
		case "information":
			return "bg-gray-200 border border-gray-400"
		default:
			return "bg-white border border-gray-200"
	}
}

const printLabelByType = (label?: string) => {
	switch (props.widget.type) {
		case "currency":
			return locale.currencyFormat(props.widget.currency_code || "usd", Number(label))
		default:
			return label
	}
}

function NumberDashboard(shop: any) {
	console.log(shop)
	return route(shop?.name, shop?.parameters)
}

const setChartOptions = () => {
	// Base chart options
	const options: any = {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: {
				display: false,
			},
			tooltip: {
				callbacks: {
					title: function (tooltipItems) {
						if (
							props.visual &&
							props.visual.value.hover_labels &&
							tooltipItems.length > 0
						) {
							return props.visual.value.hover_labels[tooltipItems[0].dataIndex]
						}
						return tooltipItems[0].label
					},
					label: (context) => {
						const value = parseFloat(context.parsed.y ?? context.parsed) || 0
						const currencyCode = props.widget.currency_code
						if (currencyCode) {
							return locale.currencyFormat(currencyCode, value)
						}
						return locale.number(value)
					},
				},
			},
		},
	}

	// Only apply y-axis currency formatting for bar charts
	if (props.visual && props.visual.type === "bar") {
		options.scales = {
			y: {
				ticks: {
					callback: (value) => {
						const numericValue = Number(value) || 0
						const currencyCode = props.widget.currency_code
						if (currencyCode) {
							return useLocaleStore().CurrencyShort(currencyCode, numericValue, false)
						}
						return locale.number(numericValue)
					},
				},
			},
		}
	}

	return options
}

const formattedVisual = computed(() => {
    if (!props.visual || !props.visual.value) {
        return null;
    }

    const visualValue = props.visual.value;

    return {
        ...visualValue,
        datasets: Array.isArray(visualValue.datasets)
            ? visualValue.datasets.map(dataset => ({
                ...dataset,
                backgroundColor: props.visual.type === "bar" ? "#36a2eb" : dataset.backgroundColor,
                borderColor: props.visual.type === "bar" ? "#2a7bbf" : dataset.borderColor,
                borderWidth: 1, // Optional: Adjust border thickness
            }))
            : [{
                ...visualValue.datasets,
                backgroundColor: props.visual.type === "bar" ? "#36a2eb" : visualValue.datasets.backgroundColor,
                borderColor: props.visual.type === "bar" ? "#2a7bbf" : visualValue.datasets.borderColor,
                borderWidth: 1,
            }]
    };
});

// const chartLabels = ["1", "2", "3", "4", "5", "6", "7", "8"]
// const chartData = [10, 20, 15, 25, 20, 18, 22, 10]
// const dummyChartData = {
// 	labels: ['A', 'B', 'C'],
// 	datasets: [
// 		{
// 			data: [540, 325, 702],
// 			// backgroundColor: [documentStyle.getPropertyValue('--p-cyan-500'), documentStyle.getPropertyValue('--p-orange-500'), documentStyle.getPropertyValue('--p-gray-500')],
// 			// hoverBackgroundColor: [documentStyle.getPropertyValue('--p-cyan-400'), documentStyle.getPropertyValue('--p-orange-400'), documentStyle.getPropertyValue('--p-gray-400')]
// 		}
// 	]
// }
</script>

<template>
	<div :class="['rounded-lg p-3 shadow-md relative h-full', getStatusColor(widget.status)]">
		<p class="text-2xl font-bold leading-tight truncate">
			<!-- v-tooltip="printLabelByType(widget?.value)" -->
			<!-- Render CountUp if widget.type is 'number' -->
			<template v-if="widget?.type === 'number'">
				<template v-if="widget?.route">
					<Link :href="NumberDashboard(widget.route)">
						<CountUp
							class="primaryLink inline-block"
							:endVal="widget?.value"
							:duration="1.5"
							:scrollSpyOnce="true"
							:options="{
						formattingFn: (value: number) => locale.number(value)
					}" />
					</Link>
				</template>
				<template v-else>
					<CountUp
						:endVal="widget?.value"
						:duration="1.5"
						:scrollSpyOnce="true"
						:options="{
          formattingFn: (value: number) => locale.number(value)
        }" />
				</template>
			</template>

			<template v-else>
				<template v-if="widget?.route">
					<Link :href="NumberDashboard(widget.route)" class="primaryLink">
						{{ printLabelByType(widget?.value) }}
					</Link>
				</template>
				<template v-else>
					{{ printLabelByType(widget?.value) }}
				</template>
			</template>
		</p>

		<p class="text-sm text-gray-500">{{ widget.description }}</p>

		<div>
			<div
				v-if="visual && ['line', 'bar', 'pie', 'doughnut'].includes(visual.type)"
				class="mt-4 h-full flex items-end">
				<div class="w-full h-full">
					<Chart
						:type="visual.type"
						:labels="visual.label"
						:data="formattedVisual"
						:height="200"
						:options="setChartOptions()" />
				</div>
			</div>
		</div>
	</div>
</template>
<style scoped lang="scss">
.chart-container {
	flex-grow: 1;
	position: relative;
}

.chart-container canvas {
	display: block;
	width: 100%;
	height: 100% !important;
}

.bottom-content {
	display: flex;
	align-items: flex-end;
	justify-content: center;
	height: 100%;
}
</style>
