<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors, BarElement, CategoryScale, LinearScale } from "chart.js";
import { Pie, Bar } from "vue-chartjs";
import { ctrans } from "@/Composables/useTrans";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave, faCalendarAlt, faSyncAlt, faChartLine, faInfoCircle, faEnvelope, faCircleNotch } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { useLocaleStore } from "@/Stores/locale";
import { capitalize } from "@/Composables/capitalize";
import { computed, onMounted, onUnmounted, provide, ref } from "vue";
import { Link, router } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import { externalChartTooltip, hideChartTooltip } from "@/Composables/useChartExternalTooltip"
import { faExclamationCircle } from "@fas"

library.add(faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave, faCalendarAlt, faSyncAlt, faChartLine, faInfoCircle, faEnvelope, faCircleNotch, faExclamationCircle);

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
				is_live: boolean;
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

const caseColours: Record<string, string> = {
	green: "#22c55e",
	lime: "#84cc16",
	emerald: "#10b981",
	teal: "#14b8a6",
	blue: "#3b82f6",
	indigo: "#6366f1",
	purple: "#a855f7",
	yellow: "#eab308",
	amber: "#f59e0b",
	orange: "#f97316",
	red: "#ef4444",
	gray: "#9ca3af",
}

const caseColour = (color: string) => caseColours[color] ?? caseColours.gray

const caseShare = (count: number) => customerStats.value.count ? Math.round((count / customerStats.value.count) * 1000) / 10 : 0

const caseUrl = (index: number): string | null => {
	const caseRoute = customerStats.value.cases[index]?.route
	return caseRoute?.name ? route(caseRoute.name, caseRoute.parameters) : null
}

const pieData = computed(() => ({
	labels: customerStats.value.cases.map((c) => c.label),
	datasets: [
		{
			data: customerStats.value.cases.map((c) => c.count),
			backgroundColor: customerStats.value.cases.map((c) => caseColour(c.icon.color)),
			borderColor: "#ffffff",
			borderWidth: 2,
			hoverOffset: 6,
		},
	],
}))

const options = {
	responsive: true,
	maintainAspectRatio: true,
	onClick: (_event: any, elements: any[]) => {
		const url = elements.length ? caseUrl(elements[0].index) : null
		if (url) {
			hideChartTooltip()
			router.visit(url)
		}
	},
	onHover: (event: any, elements: any[]) => {
		if (event.native?.target) {
			event.native.target.style.cursor = elements.length && caseUrl(elements[0].index) ? "pointer" : "default"
		}
	},
	plugins: {
		legend: { display: false },
		tooltip: {
			enabled: false,
			external: externalChartTooltip,
			callbacks: {
				afterBody: (items: any[]) => (items.length && caseUrl(items[0].dataIndex) ? ctrans("Click to list these customers") : ""),
			},
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

const segmentChips = (group: SegmentGroup, hoverClass: string) =>
	group.segments.map((segment) => {
		const url = segmentUrl(group, segment)

		return {
			segment,
			is: url ? Link : 'span',
			href: url ?? undefined,
			class: url ? hoverClass : 'cursor-default',
			tooltip: group.tooltips?.[segment],
		}
	})

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
			const clickable = elements.length > 0 && !!segmentUrl(group, group.segments[elements[0].index])

			event.native.target.style.cursor = clickable ? 'pointer' : 'default'
		}
	},
	plugins: {
		legend: {
			display: true,
			position: 'top' as const,
			labels: { boxWidth: 10, boxHeight: 10, font: { size: 11 }, color: '#6b7280' },
		},
		tooltip: {
			callbacks: {
				label: function (context: any) {
					return `${context.dataset.label}: ${context.parsed.x} customers`
				},
				afterBody: function () {
					return ctrans('Click to list these customers')
				}
			}
		}
	},
	scales: {
		x: {
			beginAtZero: true,
			grid: {
				display: true,
				color: "rgba(0, 0, 0, 0.05)"
			},
			ticks: {
				font: { size: 11 },
				color: '#9ca3af',
				callback: function (value: any) {
					return value >= 1000 ? (value / 1000).toFixed(0) + 'K' : value
				}
			}
		},
		y: {
			grid: {
				display: false
			},
			ticks: {
				font: { size: 11 },
				color: '#4b5563'
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
				label: ctrans('End of period'),
				data: segments.map(segment => currentData[segment] || 0),
				backgroundColor: currentColor,
				borderWidth: 1,
				borderRadius: 4,
			},
			{
				label: ctrans('Start of period'),
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
		return ctrans('no data');
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
				<LoadingIcon class="text-[--app-accent] text-3xl" />
			</div>

			<!-- Customer Stats Card -->
			<section class="mt-5 flex flex-col gap-6 rounded-xl border border-gray-200 bg-white p-5 tabular-nums shadow-sm md:flex-row md:items-center">
				<div class="shrink-0 md:w-48">
					<p class="text-sm font-medium text-gray-500">{{ customerStats.label }}</p>
					<p class="mt-1 text-3xl font-semibold text-gray-900">{{ locale.number(customerStats.count) }}</p>
					<p class="text-xs text-gray-400">{{ ctrans("in total") }}</p>
				</div>

				<ul class="grid min-w-0 flex-1 grid-cols-1 gap-1 sm:grid-cols-2 xl:grid-cols-3">
					<li v-for="(dCase, idxCase) in customerStats.cases" :key="dCase.value">
						<component
							:is="dCase.route?.name ? Link : 'div'"
							:href="dCase.route?.name ? route(dCase.route.name, dCase.route.parameters) : null"
							class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition duration-200"
							:class="dCase.route?.name ? 'hover:bg-gray-50 active:bg-gray-100' : ''"
							v-tooltip="capitalize(dCase.icon.tooltip)"
							@start="() => isLoadingVisit = idxCase"
							@finish="() => isLoadingVisit = null"
						>
							<LoadingIcon v-if="isLoadingVisit === idxCase" class="text-gray-500" />
							<span v-else class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: caseColour(dCase.icon.color) }" aria-hidden="true" />
							<FontAwesomeIcon :icon="dCase.icon.icon" :class="dCase.icon.class" fixed-width aria-hidden="true" />
							<span class="min-w-0 flex-1 truncate text-gray-600">{{ capitalize(dCase.label) }}</span>
							<span class="font-semibold text-gray-900">{{ locale.number(dCase.count) }}</span>
							<span class="w-12 text-right text-xs text-gray-400">{{ caseShare(dCase.count) }}%</span>
						</component>
					</li>
				</ul>

				<div class="mx-auto w-28 shrink-0 md:mx-0">
					<Pie :data="pieData" :options="options" />
				</div>
			</section>

			<!-- RFM Segments Cards -->
			<div v-if="data.segments" class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
				<!-- Recency Card -->
				<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
					<div class="flex items-center justify-between mb-1">
						<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
							<FontAwesomeIcon :icon="['fal', 'calendar-alt']" class="text-blue-500" fixed-width />
							{{ data.segments.recency.title }}
						</h3>
					</div>
					<p class="text-xs text-gray-400">{{ data.segments.recency.description }}</p>
					<p class="mb-3 mt-1 text-[11px] text-gray-400">{{ ctrans('Comparing') }}: {{ previousDate }} → {{ currentDate }}</p>

					<div class="h-80">
						<Bar :data="getRecencyChartData" :options="recencyBarOptions" />
					</div>

					<div class="mt-4 flex flex-wrap gap-2">
						<component
							v-for="chip in segmentChips(data.segments.recency, 'hover:bg-blue-100')"
							:is="chip.is"
							:key="chip.segment"
							:href="chip.href"
							v-tooltip="chip.tooltip"
							class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-50 text-blue-700"
							:class="chip.class"
						>
							{{ chip.segment }}
							<span class="font-semibold">{{ locale.number(data.comparison.current.data.recency[chip.segment] ?? 0) }}</span>
						</component>
					</div>
				</div>

				<!-- Frequency Card -->
				<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
					<div class="flex items-center justify-between mb-1">
						<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
							<FontAwesomeIcon :icon="['fal', 'sync-alt']" class="text-green-500" fixed-width />
							{{ data.segments.frequency.title }}
						</h3>
					</div>
					<p class="text-xs text-gray-400">{{ data.segments.frequency.description }}</p>
					<p class="mb-3 mt-1 text-[11px] text-gray-400">{{ ctrans('Comparing') }}: {{ previousDate }} → {{ currentDate }}</p>

					<div class="h-80">
						<Bar :data="getFrequencyChartData" :options="frequencyBarOptions" />
					</div>

					<div class="mt-4 flex flex-wrap gap-2">
						<component
							v-for="chip in segmentChips(data.segments.frequency, 'hover:bg-green-100')"
							:is="chip.is"
							:key="chip.segment"
							:href="chip.href"
							v-tooltip="chip.tooltip"
							class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-green-50 text-green-700"
							:class="chip.class"
						>
							{{ chip.segment }}
							<span class="font-semibold">{{ locale.number(data.comparison.current.data.frequency[chip.segment] ?? 0) }}</span>
						</component>
					</div>
				</div>

				<!-- Monetary Card -->
				<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
					<div class="flex items-center justify-between mb-1">
						<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
							<FontAwesomeIcon :icon="['fal', 'chart-line']" class="text-purple-500" fixed-width />
							{{ data.segments.monetary.title }}
						</h3>
					</div>
					<p class="text-xs text-gray-400">{{ data.segments.monetary.description }}</p>
					<p class="mb-3 mt-1 text-[11px] text-gray-400">{{ ctrans('Comparing') }}: {{ previousDate }} → {{ currentDate }}</p>

					<div class="h-80">
						<Bar :data="getMonetaryChartData" :options="monetaryBarOptions" />
					</div>

					<div v-if="data.newsletterRevenue" class="mt-4 border-t border-gray-100 pt-3">
						<div class="flex items-center justify-between text-xs font-medium text-gray-500 mb-2">
							<span class="flex items-center gap-1">
								<FontAwesomeIcon :icon="['fal', 'envelope']" class="text-purple-400" fixed-width />
								{{ ctrans('Newsletter revenue') }}
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
						<component
							v-for="chip in segmentChips(data.segments.monetary, 'hover:bg-purple-100')"
							:is="chip.is"
							:key="chip.segment"
							:href="chip.href"
							v-tooltip="chip.tooltip"
							class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-purple-50 text-purple-700"
							:class="chip.class"
						>
							{{ chip.segment }}
							<span class="font-semibold">{{ locale.number(data.comparison.current.data.monetary[chip.segment] ?? 0) }}</span>
						</component>
					</div>
				</div>
			</div>

			<p v-if="data.segments && !data.comparison.current.is_live" class="mt-3 text-xs text-gray-500">
				{{ ctrans('Counts taken from the snapshot on') }} {{ currentDate }}.
				{{ ctrans('Customer tags only reflect today, so you can open a segment customer list from a period that ends today.') }}
			</p>
		</div>
	</div>
</template>

<style scoped>
.h-80 {
	height: 20rem;
}
</style>
