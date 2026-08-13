<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors, BarElement, CategoryScale, LinearScale } from "chart.js";
import { Pie, Bar } from "vue-chartjs";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave, faCalendarAlt, faSyncAlt, faChartLine, faInfoCircle, faEnvelope } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { useLocaleStore } from "@/Stores/locale";
import { capitalize } from "@/Composables/capitalize";
import { computed, onMounted, onUnmounted, provide, ref } from "vue";
import { Link, router } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"

library.add(faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave, faCalendarAlt, faSyncAlt, faChartLine, faInfoCircle, faEnvelope);

ChartJS.register(ArcElement, Tooltip, Legend, Colors, BarElement, CategoryScale, LinearScale);

const locale = useLocaleStore();

interface SegmentRoute {
	name: string;
	parameters: Record<string, string>;
}

interface SegmentGroup {
	title: string;
	description: string;
	segments: string[];
	tooltips: Record<string, string>;
	routes: Record<string, SegmentRoute>;
}

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
		intervals: {
			options: Array<{ value: string; label: string; labelShort: string }>;
			value: string;
			range_interval: string;
		};
		comparison: {
			current: {
				date: string;
				data: Record<string, Record<string, number>>;
				total: number;
			};
			previous: {
				date: string | null;
				data: Record<string, Record<string, number>>;
				total: number;
			};
			comparison: Record<string, Record<string, any>>;
			period: {
				from: string;
				to: string;
			};
		};
		segments: {
			recency: SegmentGroup;
			frequency: SegmentGroup;
			monetary: SegmentGroup;
		};
		newsletterRevenue: {
			currency: string;
			data: Record<string, number>;
		};
	};
}>();

const isLoading = ref(false)
provide("isLoadingOnTable", isLoading)

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

const segmentUrl = (group: SegmentGroup, segment: string): string | null => {
	const segmentRoute = group.routes?.[segment]

	return segmentRoute ? route(segmentRoute.name, segmentRoute.parameters) : null
}

const visitSegment = (group: SegmentGroup, segment: string) => {
	const url = segmentUrl(group, segment)

	if (url) {
		router.visit(url)
	}
}

const buildBarOptions = (group: SegmentGroup) => ({
	responsive: true,
	maintainAspectRatio: false,
	indexAxis: 'y' as const,
	onClick: (_event: any, elements: any[]) => {
		if (elements.length) {
			visitSegment(group, group.segments[elements[0].index])
		}
	},
	onHover: (event: any, elements: any[]) => {
		if (event.native?.target) {
			event.native.target.style.cursor = elements.length ? 'pointer' : 'default'
		}
	},
	plugins: {
		legend: {
			display: true,
			position: 'top' as const,
		},
		tooltip: {
			callbacks: {
				label: function (context: any) {
					return `${context.dataset.label}: ${context.parsed.x} customers`
				},
				afterBody: function () {
					return trans('Click to list these customers')
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
				callback: function (value: any) {
					return value >= 1000 ? (value / 1000).toFixed(0) + 'K' : value
				}
			}
		},
		y: {
			grid: {
				display: false
			}
		}
	}
});

const chartData = (type: 'recency' | 'frequency' | 'monetary', currentColor: string, previousColor: string) => {
	const segments = props.data.segments[type].segments;
	const currentData = props.data.comparison.current.data[type];
	const previousData = props.data.comparison.previous.data[type];

	return {
		labels: segments,
		datasets: [
			{
				label: trans('End of period'),
				data: segments.map(segment => currentData[segment] || 0),
				backgroundColor: currentColor,
				borderWidth: 1,
				borderRadius: 4,
			},
			{
				label: trans('Start of period'),
				data: segments.map(segment => previousData[segment] || 0),
				backgroundColor: previousColor,
				borderWidth: 1,
				borderRadius: 4,
			}
		]
	};
};

const getRecencyChartData = computed(() => chartData('recency', 'rgba(59, 130, 246, 0.8)', 'rgba(147, 197, 253, 0.8)'));
const getFrequencyChartData = computed(() => chartData('frequency', 'rgba(16, 185, 129, 0.8)', 'rgba(134, 239, 172, 0.8)'));
const getMonetaryChartData = computed(() => chartData('monetary', 'rgba(139, 92, 246, 0.8)', 'rgba(196, 181, 253, 0.8)'));

const recencyBarOptions = computed(() => buildBarOptions(props.data.segments.recency));
const frequencyBarOptions = computed(() => buildBarOptions(props.data.segments.frequency));
const monetaryBarOptions = computed(() => buildBarOptions(props.data.segments.monetary));

const formatDate = (date: string | null) => {
	if (!date) {
		return trans('no data');
	}

	return new Date(date).toLocaleDateString('en-US', {
		month: 'short',
		day: 'numeric',
		year: 'numeric'
	});
};

const currentDate = computed(() => formatDate(props.data.comparison.current.date));
const previousDate = computed(() => formatDate(props.data.comparison.previous.date));

const totalNewsletterRevenue = computed(() =>
	Object.values(props.data.newsletterRevenue?.data ?? {}).reduce((total, value) => total + Number(value || 0), 0)
);

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
	<div>
		<DashboardSettings
			v-if="data.intervals"
			:intervals="data.intervals"
			:settings="{}"
			currentTab="customers"
			:reloadOnly="['customers']"
		/>

		<div class="px-6 relative">
			<div v-if="isLoading" class="absolute inset-0 bg-white/50 flex items-center justify-center z-20">
				<LoadingIcon class="text-indigo-500 text-3xl" />
			</div>

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
			<div v-if="data.segments" class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
				<!-- Recency Card -->
				<div class="bg-white rounded-lg shadow p-6">
					<div class="flex items-center justify-between mb-1">
						<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
							<FontAwesomeIcon :icon="['fal', 'calendar-alt']" class="text-blue-500" />
							{{ data.segments.recency.title }}
						</h3>
					</div>
					<p class="text-xs text-gray-400 mb-4">{{ data.segments.recency.description }}</p>

					<div class="h-80">
						<Bar :data="getRecencyChartData" :options="recencyBarOptions" />
					</div>

					<div class="mt-3 text-xs text-gray-500 text-center">
						{{ trans('Comparing') }}: {{ previousDate }} → {{ currentDate }}
					</div>

					<div class="mt-4 flex flex-wrap gap-2">
						<Link
							v-for="segment in data.segments.recency.segments"
							:key="segment"
							:href="segmentUrl(data.segments.recency, segment) ?? ''"
							v-tooltip="data.segments.recency.tooltips?.[segment]"
							class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-50 text-blue-700 hover:bg-blue-100"
						>
							{{ segment }}
							<span class="font-semibold">{{ locale.number(data.comparison.current.data.recency[segment] ?? 0) }}</span>
							<FontAwesomeIcon :icon="['fal', 'info-circle']" class="text-blue-400 text-xs" />
						</Link>
					</div>
				</div>

				<!-- Frequency Card -->
				<div class="bg-white rounded-lg shadow p-6">
					<div class="flex items-center justify-between mb-1">
						<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
							<FontAwesomeIcon :icon="['fal', 'sync-alt']" class="text-green-500" />
							{{ data.segments.frequency.title }}
						</h3>
					</div>
					<p class="text-xs text-gray-400 mb-4">{{ data.segments.frequency.description }}</p>

					<div class="h-80">
						<Bar :data="getFrequencyChartData" :options="frequencyBarOptions" />
					</div>

					<div class="mt-3 text-xs text-gray-500 text-center">
						{{ trans('Comparing') }}: {{ previousDate }} → {{ currentDate }}
					</div>

					<div class="mt-4 flex flex-wrap gap-2">
						<Link
							v-for="segment in data.segments.frequency.segments"
							:key="segment"
							:href="segmentUrl(data.segments.frequency, segment) ?? ''"
							v-tooltip="data.segments.frequency.tooltips?.[segment]"
							class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-green-50 text-green-700 hover:bg-green-100"
						>
							{{ segment }}
							<span class="font-semibold">{{ locale.number(data.comparison.current.data.frequency[segment] ?? 0) }}</span>
							<FontAwesomeIcon :icon="['fal', 'info-circle']" class="text-green-400 text-xs" />
						</Link>
					</div>
				</div>

				<!-- Monetary Card -->
				<div class="bg-white rounded-lg shadow p-6">
					<div class="flex items-center justify-between mb-1">
						<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
							<FontAwesomeIcon :icon="['fal', 'chart-line']" class="text-purple-500" />
							{{ data.segments.monetary.title }}
						</h3>
					</div>
					<p class="text-xs text-gray-400 mb-4">{{ data.segments.monetary.description }}</p>

					<div class="h-80">
						<Bar :data="getMonetaryChartData" :options="monetaryBarOptions" />
					</div>

					<div class="mt-3 text-xs text-gray-500 text-center">
						{{ trans('Comparing') }}: {{ previousDate }} → {{ currentDate }}
					</div>

					<div v-if="data.newsletterRevenue" class="mt-4 border-t border-gray-100 pt-3">
						<div class="flex items-center justify-between text-xs font-medium text-gray-500 mb-2">
							<span class="flex items-center gap-1">
								<FontAwesomeIcon :icon="['fal', 'envelope']" class="text-purple-400" />
								{{ trans('Newsletter revenue') }}
							</span>
							<span class="font-semibold text-gray-700">
								{{ locale.currencyFormat(data.newsletterRevenue.currency, totalNewsletterRevenue) }}
							</span>
						</div>
						<div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500 tabular-nums">
							<div
								v-for="segment in data.segments.monetary.segments"
								:key="segment"
								class="flex items-center justify-between gap-2"
							>
								<span class="truncate">{{ segment }}</span>
								<span class="font-medium text-gray-700">
									{{ locale.currencyFormat(data.newsletterRevenue.currency, data.newsletterRevenue.data[segment] ?? 0) }}
								</span>
							</div>
						</div>
					</div>

					<div class="mt-4 flex flex-wrap gap-2">
						<Link
							v-for="segment in data.segments.monetary.segments"
							:key="segment"
							:href="segmentUrl(data.segments.monetary, segment) ?? ''"
							v-tooltip="data.segments.monetary.tooltips?.[segment]"
							class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-purple-50 text-purple-700 hover:bg-purple-100"
						>
							{{ segment }}
							<span class="font-semibold">{{ locale.number(data.comparison.current.data.monetary[segment] ?? 0) }}</span>
							<FontAwesomeIcon :icon="['fal', 'info-circle']" class="text-purple-400 text-xs" />
						</Link>
					</div>
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
