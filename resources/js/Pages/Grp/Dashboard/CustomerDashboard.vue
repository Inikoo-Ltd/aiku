<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors, BarElement, CategoryScale, LinearScale } from "chart.js";
import { Pie, Bar } from "vue-chartjs";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave, faCalendarAlt, faSyncAlt, faChartLine } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { useLocaleStore } from "@/Stores/locale";
import { capitalize } from "@/Composables/capitalize";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { Link } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave, faCalendarAlt, faSyncAlt, faChartLine);

ChartJS.register(ArcElement, Tooltip, Legend, Colors, BarElement, CategoryScale, LinearScale);

const locale = useLocaleStore();

// Props definition
const props = defineProps<{
	data: {
		prospectStats: {
			customers: {
				label: string;
				count: number;
				cases: {
					[key: string]: {
						value: string;
						count: number;
						label: string;
						icon: {
							icon: string | string[];
							tooltip: string;
							class: string;
							color: string;
						};
					};
				};
			};
		};
		comparison: {
			current: {
				date: string;
				data: {
					recency: Record<string, number>;
					frequency: Record<string, number>;
					monetary: Record<string, number>;
				};
				total: number;
			};
			previous: {
				date: string;
				data: {
					recency: Record<string, number>;
					frequency: Record<string, number>;
					monetary: Record<string, number>;
				};
				total: number;
			};
			comparison: {
				recency: Record<string, any>;
				frequency: Record<string, any>;
				monetary: Record<string, any>;
			};
		};
		segments: {
			recency: {
				title: string;
				description: string;
				segments: string[];
			};
			frequency: {
				title: string;
				description: string;
				segments: string[];
			};
			monetary: {
				title: string;
				description: string;
				segments: string[];
			};
		};
	};
}>();

// Reactive transformation of props for easier template usage
const customerStats = computed(() => {
	const customers = props.data.prospectStats.customers;
	return {
		label: customers.label,
		count: customers.count,
		cases: Object.values(customers.cases).map((caseItem) => ({
			value: caseItem.value,
			count: caseItem.count,
			label: caseItem.label,
			route: caseItem.route,
			icon: {
				icon: caseItem.icon.icon,
				tooltip: caseItem.icon.tooltip,
				class: caseItem.icon.class,
				color: caseItem.icon.color,
			},
		})),
	};
});

// Chart options
const options = {
	responsive: true,
	plugins: {
		legend: { display: false },
		tooltip: {
			titleFont: { size: 10, weight: "lighter" },
			bodyFont: { size: 11, weight: "bold" },
		},
	},
};

// Bar chart options for RFM segments - grouped bars
const barOptions = {
	responsive: true,
	indexAxis: 'y' as const,
	plugins: {
		legend: {
			display: true,
			position: 'top' as const,
		},
		tooltip: {
			callbacks: {
				label: function(context: any) {
					return `${context.dataset.label}: ${context.parsed.x} customers`;
				}
			}
		}
	},
	scales: {
		x: {
			beginAtZero: true,
			grid: {
				display: true,
				color: "rgba(0, 0, 0, 0.1)"
			},
			ticks: {
				callback: function(value: any) {
					return value >= 1000 ? (value/1000).toFixed(0) + 'K' : value;
				}
			}
		},
		y: {
			grid: {
				display: false
			}
		}
	}
};

// Methods to get RFM data for charts with comparison
const getRecencyChartData = computed(() => {
	const segments = props.data.segments.recency.segments;
	const currentData = props.data.comparison.current.data.recency;
	const previousData = props.data.comparison.previous.data.recency;

	return {
		labels: segments,
		datasets: [
			{
				label: 'Current',
				data: segments.map(segment => currentData[segment] || 0),
				backgroundColor: 'rgba(59, 130, 246, 0.8)', // blue
				borderColor: 'rgb(59, 130, 246)',
				borderWidth: 1,
				borderRadius: 4,
			},
			{
				label: 'Previous',
				data: segments.map(segment => previousData[segment] || 0),
				backgroundColor: 'rgba(147, 197, 253, 0.8)', // light blue
				borderColor: 'rgb(147, 197, 253)',
				borderWidth: 1,
				borderRadius: 4,
			}
		]
	};
});

const getFrequencyChartData = computed(() => {
	const segments = props.data.segments.frequency.segments;
	const currentData = props.data.comparison.current.data.frequency;
	const previousData = props.data.comparison.previous.data.frequency;

	return {
		labels: segments,
		datasets: [
			{
				label: 'Current',
				data: segments.map(segment => currentData[segment] || 0),
				backgroundColor: 'rgba(16, 185, 129, 0.8)', // green
				borderColor: 'rgb(16, 185, 129)',
				borderWidth: 1,
				borderRadius: 4,
			},
			{
				label: 'Previous',
				data: segments.map(segment => previousData[segment] || 0),
				backgroundColor: 'rgba(134, 239, 172, 0.8)', // light green
				borderColor: 'rgb(134, 239, 172)',
				borderWidth: 1,
				borderRadius: 4,
			}
		]
	};
});

const getMonetaryChartData = computed(() => {
	const segments = props.data.segments.monetary.segments;
	const currentData = props.data.comparison.current.data.monetary;
	const previousData = props.data.comparison.previous.data.monetary;

	return {
		labels: segments,
		datasets: [
			{
				label: 'Current',
				data: segments.map(segment => currentData[segment] || 0),
				backgroundColor: 'rgba(139, 92, 246, 0.8)', // purple
				borderColor: 'rgb(139, 92, 246)',
				borderWidth: 1,
				borderRadius: 4,
			},
			{
				label: 'Previous',
				data: segments.map(segment => previousData[segment] || 0),
				backgroundColor: 'rgba(196, 181, 253, 0.8)', // light purple
				borderColor: 'rgb(196, 181, 253)',
				borderWidth: 1,
				borderRadius: 4,
			}
		]
	};
});

// Format dates for display
const currentDate = computed(() => {
	return new Date(props.data.comparison.current.date).toLocaleDateString('en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric'
	});
});

const previousDate = computed(() => {
	return new Date(props.data.comparison.previous.date).toLocaleDateString('en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric'
	});
});

// Listener for backend updates
onMounted(() => {
	window.Echo.private("customer.general").listen(".customers.dashboard", (e) => {
		if (e.data.customers) {
			customerStats.value.count = e.data.customers.count;
		}
		if (e.data.customers?.cases) {
			Object.keys(e.data.customers.cases).forEach((key) => {
				const updatedCase = customerStats.value.cases.find((c) => c.value === key);
				if (updatedCase) {
					updatedCase.count = e.data.customers.cases[key].count;
				}
			});
		}
	});
});

onUnmounted(() => {
	window.Echo.private("customer.general").stopListening(".customers.dashboard");
});

const isLoadingVisit = ref<number | null>(null)
</script>

<template>
	<div class="px-6">
		<!-- Customer Stats Card -->
		<dl class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-x-2 gap-y-3">
			<div
				class="px-4 py-5 sm:p-6 rounded-lg bg-white shadow tabular-nums">
				<dt class="text-base font-medium text-gray-400">
					{{ customerStats.label }}
				</dt>
				<dd class="mt-2 flex justify-between gap-x-2">
					<div
						class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
						<!-- Total Count -->
						<div class="flex gap-x-2 items-end">
							{{ locale.number(customerStats.count) }}
							<span class="text-sm font-medium leading-4 text-gray-500">
                                {{ trans("in total") }}
                            </span>
						</div>

						<!-- Case Breakdown -->
						<div
							class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
							<template v-for="(dCase, idxCase) in customerStats.cases" :key="dCase.value">
								<component
									:is="dCase.route?.name ? Link : 'div'"
									:href="dCase.route?.name ? route(dCase.route.name, dCase.route.parameters) : null"
									:class="dCase.route?.name ? 'hover:bg-gray-200 px-1 py-0.5 rounded' : ''"
									class="flex gap-x-0.5 items-center font-normal"
									v-tooltip="capitalize(dCase.icon.tooltip)"
									@start="() => isLoadingVisit = idxCase"
									@finish="() => isLoadingVisit = null"
								>
									<LoadingIcon v-if="isLoadingVisit === idxCase" class="text-gray-500" />
									<FontAwesomeIcon
										v-else
										:icon="dCase.icon.icon"
										:class="dCase.icon.class"
										fixed-width
										:title="dCase.icon.tooltip"
										aria-hidden="true" />
									<span class="font-semibold">{{ locale.number(dCase.count) }}</span>
								</component>
							</template>
						</div>
					</div>

					<!-- Pie Chart -->
					<div class="w-20">
						<Pie
							:data="{
                                labels: customerStats.cases.map((c) => c.label),
                                datasets: [
                                    {
                                        data: customerStats.cases.map((c) => c.count),
                                        hoverOffset: 4,
                                    },
                                ],
                            }"
							:options="options" />
					</div>
				</dd>
			</div>
		</dl>

		<!-- RFM Segments Cards -->
		<div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
			<!-- Recency Card -->
			<div class="bg-white rounded-lg shadow p-6">
				<div class="flex items-center justify-between mb-4">
					<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon :icon="['fas', 'calendar-alt']" class="text-blue-500" />
						{{ data.segments.recency.title }}
					</h3>
				</div>

				<div class="h-80">
					<Bar :data="getRecencyChartData" :options="barOptions" />
				</div>

				<div class="mt-3 text-xs text-gray-500 text-center">
					Comparing: {{ currentDate }} vs {{ previousDate }}
				</div>
			</div>

			<!-- Frequency Card -->
			<div class="bg-white rounded-lg shadow p-6">
				<div class="flex items-center justify-between mb-4">
					<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon :icon="['fas', 'sync-alt']" class="text-green-500" />
						{{ data.segments.frequency.title }}
					</h3>
				</div>

				<div class="h-80">
					<Bar :data="getFrequencyChartData" :options="barOptions" />
				</div>

				<div class="mt-3 text-xs text-gray-500 text-center">
					Comparing: {{ currentDate }} vs {{ previousDate }}
				</div>
			</div>

			<!-- Monetary Card -->
			<div class="bg-white rounded-lg shadow p-6">
				<div class="flex items-center justify-between mb-4">
					<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon :icon="['fas', 'chart-line']" class="text-purple-500" />
						{{ data.segments.monetary.title }}
					</h3>
				</div>

				<div class="h-80">
					<Bar :data="getMonetaryChartData" :options="barOptions" />
				</div>

				<div class="mt-3 text-xs text-gray-500 text-center">
					Comparing: {{ currentDate }} vs {{ previousDate }}
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.h-80 {
	height: 20rem;
}
</style>
