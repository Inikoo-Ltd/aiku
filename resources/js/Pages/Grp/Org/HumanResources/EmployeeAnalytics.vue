<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import {
	Chart as ChartJS,
	ArcElement,
	Tooltip,
	Legend,
	Colors,
	BarElement,
	CategoryScale,
	LinearScale,
} from "chart.js"
import { Pie, Bar } from "vue-chartjs"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"

ChartJS.register(ArcElement, Tooltip, Legend, Colors, BarElement, CategoryScale, LinearScale)

interface EmployeeAttendance {
	id: number
	name: string
	slug: string
	attendance_percentage: number
	late_clockins: number
	early_clockouts: number
}

interface EmployeeLeave {
	id: number
	name: string
	slug: string
	total_leave_days: number
	leave_breakdown: Record<string, number>
}

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	filters: {
		start_date: string
		end_date: string
	}
	analytics: {
		avg_attendance_percentage: number
		avg_total_working_hours: number
		avg_overtime_hours: number
		total_late_clockins: number
		total_early_clockouts: number
		total_leave_days: number
	} | null
	total_employees: number
	attendance_breakdown: EmployeeAttendance[]
	top_employees_by_leave: EmployeeLeave[]
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

const updateFilters = () => {
	router.get(
		route("grp.org.hr.analytics.index", route().params),
		{
			start_date: startDate.value,
			end_date: endDate.value,
		},
		{
			preserveState: true,
			preserveScroll: true,
		}
	)
}

const quickFilters = computed(() => [
	{ label: trans("This Week"), start: getWeekStart(), end: getWeekEnd() },
	{ label: trans("This Month"), start: getMonthStart(), end: getMonthEnd() },
	{ label: trans("Last Month"), start: getLastMonthStart(), end: getLastMonthEnd() },
	{ label: trans("This Quarter"), start: getQuarterStart(), end: getQuarterEnd() },
])

function getWeekStart(): string {
	const now = new Date()
	const day = now.getDay()
	const diff = now.getDate() - day + (day === 0 ? -6 : 1)
	return new Date(now.setDate(diff)).toISOString().split("T")[0]
}

function getWeekEnd(): string {
	const start = new Date(getWeekStart())
	start.setDate(start.getDate() + 6)
	return start.toISOString().split("T")[0]
}

function getMonthStart(): string {
	const now = new Date()
	return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split("T")[0]
}

function getMonthEnd(): string {
	const now = new Date()
	return new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split("T")[0]
}

function getLastMonthStart(): string {
	const now = new Date()
	return new Date(now.getFullYear(), now.getMonth() - 1, 1).toISOString().split("T")[0]
}

function getLastMonthEnd(): string {
	const now = new Date()
	return new Date(now.getFullYear(), now.getMonth(), 0).toISOString().split("T")[0]
}

function getQuarterStart(): string {
	const now = new Date()
	const quarter = Math.floor(now.getMonth() / 3)
	return new Date(now.getFullYear(), quarter * 3, 1).toISOString().split("T")[0]
}

function getQuarterEnd(): string {
	const now = new Date()
	const quarter = Math.floor(now.getMonth() / 3)
	return new Date(now.getFullYear(), quarter * 3 + 3, 0).toISOString().split("T")[0]
}

const applyQuickFilter = (filter: { start: string; end: string }) => {
	startDate.value = filter.start
	endDate.value = filter.end
	updateFilters()
}

const formatNumber = (num: number | null | undefined, decimals = 2): string => {
	if (num === null || num === undefined) return "0"
	return num.toFixed(decimals)
}

const hasValue = (num: number | null | undefined): boolean => {
	return num !== null && num !== undefined
}

const hasAttendanceBreakdown = computed(
	() => (props.attendance_breakdown?.length ?? 0) > 0
)

const hasTopEmployeesByLeave = computed(
	() => (props.top_employees_by_leave?.length ?? 0) > 0
)

const chartSkeletonWidths = [85, 70, 60, 50, 35]

const attendanceChartOptions = {
	indexAxis: "y" as const,
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: {
			display: false,
		},
		tooltip: {
			callbacks: {
				label: function (context: any) {
					return `${context.parsed.x}%`
				},
			},
		},
	},
	scales: {
		x: {
			beginAtZero: true,
			max: 100,
			grid: {
				display: true,
				color: "rgba(0, 0, 0, 0.1)",
			},
		},
		y: {
			grid: {
				display: false,
			},
		},
	},
}

const attendanceChartData = computed(() => {
	const data = props.attendance_breakdown || []
	return {
		labels: data.map((e) => e.name),
		datasets: [
			{
				label: trans("Attendance %"),
				data: data.map((e) => e.attendance_percentage),
				backgroundColor: data.map((e) =>
					e.attendance_percentage >= 90
						? "rgba(34, 197, 94, 0.8)"
						: e.attendance_percentage >= 75
							? "rgba(234, 179, 8, 0.8)"
							: "rgba(239, 68, 68, 0.8)"
				),
				borderColor: data.map((e) =>
					e.attendance_percentage >= 90
						? "rgb(34, 197, 94)"
						: e.attendance_percentage >= 75
							? "rgb(234, 179, 8)"
							: "rgb(239, 68, 68)"
				),
				borderWidth: 1,
				borderRadius: 4,
			},
		],
	}
})

const leaveChartOptions = {
	indexAxis: "y" as const,
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: {
			display: false,
		},
		tooltip: {
			callbacks: {
				label: function (context: any) {
					return `${context.parsed.x} ${trans("days")}`
				},
			},
		},
	},
	scales: {
		x: {
			beginAtZero: true,
			grid: {
				display: true,
				color: "rgba(0, 0, 0, 0.1)",
			},
		},
		y: {
			grid: {
				display: false,
			},
		},
	},
}

const leaveChartData = computed(() => {
	const data = props.top_employees_by_leave || []
	return {
		labels: data.map((e) => e.name),
		datasets: [
			{
				label: trans("Leave Days"),
				data: data.map((e) => e.total_leave_days),
				backgroundColor: "rgba(59, 130, 246, 0.8)",
				borderColor: "rgb(59, 130, 246)",
				borderWidth: 1,
				borderRadius: 4,
			},
		],
	}
})
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead" />

	<div class="mt-5 space-y-6">
		<div class="bg-white shadow-sm rounded-lg p-4">
			<div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
				<div class="flex gap-2 flex-wrap">
					<button
						v-for="filter in quickFilters"
						:key="filter.label"
						type="button"
						class="px-3 py-1.5 text-sm rounded-md border border-gray-300 hover:bg-gray-50 transition-colors"
						@click="applyQuickFilter(filter)">
						{{ filter.label }}
					</button>
				</div>

				<div class="flex gap-2 items-center">
					<input
						v-model="startDate"
						type="date"
						class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
						@change="updateFilters" />
					<span class="text-gray-500">{{ trans("to") }}</span>
					<input
						v-model="endDate"
						type="date"
						class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
						@change="updateFilters" />
				</div>
			</div>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
			<div class="bg-white shadow-sm rounded-lg p-6">
				<div class="text-sm font-medium text-gray-500">{{ trans("Total Employees") }}</div>
				<div class="mt-2 text-3xl font-bold text-gray-900">
					{{ formatNumber(total_employees, 0) }}
				</div>
			</div>

			<div class="bg-white shadow-sm rounded-lg p-6">
				<div class="text-sm font-medium text-gray-500">{{ trans("Avg Attendance %") }}</div>
				<div
					class="mt-2 text-3xl font-bold"
					:class="
						analytics?.avg_attendance_percentage >= 90
							? 'text-green-600'
							: analytics?.avg_attendance_percentage >= 75
								? 'text-yellow-600'
								: 'text-red-600'
					">
					{{
						hasValue(analytics?.avg_attendance_percentage)
							? formatNumber(analytics.avg_attendance_percentage) + "%"
							: "--"
					}}
				</div>
			</div>

			<div class="bg-white shadow-sm rounded-lg p-6">
				<div class="text-sm font-medium text-gray-500">
					{{ trans("Avg Working Hours") }}
				</div>
				<div class="mt-2 text-3xl font-bold text-gray-900">
					{{
						hasValue(analytics?.avg_total_working_hours)
							? formatNumber(analytics.avg_total_working_hours) + "h"
							: "--"
					}}
				</div>
			</div>

			<div class="bg-white shadow-sm rounded-lg p-6">
				<div class="text-sm font-medium text-gray-500">
					{{ trans("Avg Overtime Hours") }}
				</div>
				<div class="mt-2 text-3xl font-bold text-gray-900">
					{{
						hasValue(analytics?.avg_overtime_hours)
							? formatNumber(analytics.avg_overtime_hours) + "h"
							: "--"
					}}
				</div>
			</div>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
			<div class="bg-white shadow-sm rounded-lg p-6">
				<div class="flex items-center">
					<div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
						<svg
							class="h-6 w-6 text-yellow-600"
							fill="none"
							viewBox="0 0 24 24"
							stroke="currentColor">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
					</div>
					<div class="ml-4">
						<div class="text-sm font-medium text-gray-500">
							{{ trans("Total Late Clock-ins") }}
						</div>
						<div class="text-2xl font-bold text-gray-900">
							{{
								hasValue(analytics?.total_late_clockins)
									? formatNumber(analytics.total_late_clockins, 0)
									: "--"
							}}
						</div>
					</div>
				</div>
			</div>

			<div class="bg-white shadow-sm rounded-lg p-6">
				<div class="flex items-center">
					<div class="flex-shrink-0 bg-orange-100 rounded-md p-3">
						<svg
							class="h-6 w-6 text-orange-600"
							fill="none"
							viewBox="0 0 24 24"
							stroke="currentColor">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
						</svg>
					</div>
					<div class="ml-4">
						<div class="text-sm font-medium text-gray-500">
							{{ trans("Total Early Clock-outs") }}
						</div>
						<div class="text-2xl font-bold text-gray-900">
							{{
								hasValue(analytics?.total_early_clockouts)
									? formatNumber(analytics.total_early_clockouts, 0)
									: "--"
							}}
						</div>
					</div>
				</div>
			</div>

			<div class="bg-white shadow-sm rounded-lg p-6">
				<div class="flex items-center">
					<div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
						<svg
							class="h-6 w-6 text-blue-600"
							fill="none"
							viewBox="0 0 24 24"
							stroke="currentColor">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
						</svg>
					</div>
					<div class="ml-4">
						<div class="text-sm font-medium text-gray-500">
							{{ trans("Total Leave Days") }}
						</div>
						<div class="text-2xl font-bold text-gray-900">
							{{
								hasValue(analytics?.total_leave_days)
									? formatNumber(analytics.total_leave_days, 0)
									: "--"
							}}
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			<div class="bg-white shadow-sm rounded-lg p-6">
				<h3 class="text-lg font-semibold text-gray-900 mb-4">
					{{ trans("Employee Attendance") }}
				</h3>
				<div v-if="hasAttendanceBreakdown" class="h-80">
					<Bar :data="attendanceChartData" :options="attendanceChartOptions" />
				</div>
				<div
					v-else
					class="h-80 rounded-lg border border-gray-100 bg-gray-50 p-4 flex flex-col justify-between">
					<div class="space-y-4 animate-pulse">
						<div
							v-for="(width, index) in chartSkeletonWidths"
							:key="`attendance-skeleton-${index}`"
							class="flex items-center gap-3">
							<div class="h-3 w-16 rounded bg-gray-200" />
							<div class="h-4 rounded bg-gray-200" :style="{ width: `${width}%` }" />
						</div>
					</div>
					<div class="text-center text-sm text-gray-500">
						{{ trans("No attendance data for this period") }}
					</div>
				</div>
			</div>

			<div class="bg-white shadow-sm rounded-lg p-6">
				<h3 class="text-lg font-semibold text-gray-900 mb-4">
					{{ trans("Top Employees by Leave") }}
				</h3>
				<div v-if="hasTopEmployeesByLeave" class="h-80">
					<Bar :data="leaveChartData" :options="leaveChartOptions" />
				</div>
				<div
					v-else
					class="h-80 rounded-lg border border-gray-100 bg-gray-50 p-4 flex flex-col justify-between">
					<div class="space-y-4 animate-pulse">
						<div
							v-for="(width, index) in chartSkeletonWidths"
							:key="`leave-skeleton-${index}`"
							class="flex items-center gap-3">
							<div class="h-3 w-16 rounded bg-gray-200" />
							<div class="h-4 rounded bg-gray-200" :style="{ width: `${width}%` }" />
						</div>
					</div>
					<div class="text-center text-sm text-gray-500">
						{{ trans("No leave data for this period") }}
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
