<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	filters: {
		start_date: string
		end_date: string
	}
	analytics: {
		employee: {
			id: number
			slug: string
			contact_name: string
			worker_number: string | null
		}
		attendance: {
			working_days: number
			present_days: number
			absent_days: number
			late_clockins: number
			early_clockouts: number
			total_working_hours: number
			overtime_hours: number
		}
		leave: {
			total_leave_days: number
			leave_breakdown: Record<string, number>
			leave_balance: {
				annual_remaining: number
				medical_remaining: number
				unpaid_remaining: number
			}
		}
		summary: {
			attendance_percentage: number
			avg_daily_hours: number
			overtime_ratio: number
		}
		period: {
			start: string
			end: string
		}
	}
	leaveTypes: Record<string, string>
	navigation?: {
		previous: { label: string; route: { name: string; parameters: Record<string, string> } } | null
		next: { label: string; route: { name: string; parameters: Record<string, string> } } | null
	}
}>()

const startDate = ref(props.filters.start_date)
const endDate = ref(props.filters.end_date)

const updateFilters = () => {
	router.get(
		route("grp.org.hr.analytics.show", route().params),
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

const formatNumber = (num: number | null | undefined, decimals = 2): string => {
	if (num === null || num === undefined) return "0"
	return num.toFixed(decimals)
}

const getLeaveColor = (type: string): string => {
	const colors: Record<string, string> = {
		annual: "bg-blue-500",
		medical: "bg-red-500",
		unpaid: "bg-gray-500",
	}
	return colors[type] || "bg-indigo-500"
}
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead" />

	<div class="mt-5 space-y-6">
		<div class="bg-white shadow-sm rounded-lg p-4">
			<div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
				<div class="flex items-center gap-2">
					<span class="text-sm text-gray-500">{{ trans("Period:") }}</span>
					<span class="font-medium">{{ analytics.period.start }}</span>
					<span class="text-gray-400">{{ trans("to") }}</span>
					<span class="font-medium">{{ analytics.period.end }}</span>
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

		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
			<div class="lg:col-span-2 space-y-6">
				<div class="bg-white shadow-sm rounded-lg p-6">
					<h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans("Attendance Summary") }}</h3>
					
					<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
						<div class="text-center p-4 bg-gray-50 rounded-lg">
							<div class="text-2xl font-bold text-gray-900">{{ formatNumber(analytics.attendance.working_days, 0) }}</div>
							<div class="text-sm text-gray-500">{{ trans("Working Days") }}</div>
						</div>
						<div class="text-center p-4 bg-green-50 rounded-lg">
							<div class="text-2xl font-bold text-green-600">{{ formatNumber(analytics.attendance.present_days, 0) }}</div>
							<div class="text-sm text-gray-500">{{ trans("Present Days") }}</div>
						</div>
						<div class="text-center p-4 bg-red-50 rounded-lg">
							<div class="text-2xl font-bold text-red-600">{{ formatNumber(analytics.attendance.absent_days, 0) }}</div>
							<div class="text-sm text-gray-500">{{ trans("Absent Days") }}</div>
						</div>
						<div class="text-center p-4 rounded-lg" :class="analytics.summary.attendance_percentage >= 90 ? 'bg-green-50' : analytics.summary.attendance_percentage >= 75 ? 'bg-yellow-50' : 'bg-red-50'">
							<div class="text-2xl font-bold" :class="analytics.summary.attendance_percentage >= 90 ? 'text-green-600' : analytics.summary.attendance_percentage >= 75 ? 'text-yellow-600' : 'text-red-600'">
								{{ formatNumber(analytics.summary.attendance_percentage) }}%
							</div>
							<div class="text-sm text-gray-500">{{ trans("Attendance %") }}</div>
						</div>
					</div>

					<div class="mt-6 grid grid-cols-2 gap-4">
						<div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
							<span class="text-sm text-gray-700">{{ trans("Late Clock-ins") }}</span>
							<span class="font-bold text-yellow-600">{{ formatNumber(analytics.attendance.late_clockins, 0) }}</span>
						</div>
						<div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
							<span class="text-sm text-gray-700">{{ trans("Early Clock-outs") }}</span>
							<span class="font-bold text-orange-600">{{ formatNumber(analytics.attendance.early_clockouts, 0) }}</span>
						</div>
					</div>
				</div>

				<div class="bg-white shadow-sm rounded-lg p-6">
					<h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans("Working Hours") }}</h3>
					
					<div class="grid grid-cols-3 gap-4">
						<div class="text-center p-4 bg-blue-50 rounded-lg">
							<div class="text-2xl font-bold text-blue-600">{{ formatNumber(analytics.attendance.total_working_hours) }}h</div>
							<div class="text-sm text-gray-500">{{ trans("Total Hours") }}</div>
						</div>
						<div class="text-center p-4 bg-purple-50 rounded-lg">
							<div class="text-2xl font-bold text-purple-600">{{ formatNumber(analytics.summary.avg_daily_hours) }}h</div>
							<div class="text-sm text-gray-500">{{ trans("Avg Daily") }}</div>
						</div>
						<div class="text-center p-4 bg-indigo-50 rounded-lg">
							<div class="text-2xl font-bold text-indigo-600">{{ formatNumber(analytics.attendance.overtime_hours) }}h</div>
							<div class="text-sm text-gray-500">{{ trans("Overtime") }}</div>
						</div>
					</div>

					<div class="mt-4">
						<div class="flex justify-between text-sm text-gray-600 mb-1">
							<span>{{ trans("Overtime Ratio") }}</span>
							<span>{{ formatNumber(analytics.summary.overtime_ratio) }}%</span>
						</div>
						<div class="w-full bg-gray-200 rounded-full h-2">
							<div 
								class="bg-indigo-600 h-2 rounded-full" 
								:style="{ width: Math.min(analytics.summary.overtime_ratio, 100) + '%' }">
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="space-y-6">
				<div class="bg-white shadow-sm rounded-lg p-6">
					<h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans("Leave Summary") }}</h3>
					
					<div class="text-center p-4 bg-blue-50 rounded-lg mb-4">
						<div class="text-3xl font-bold text-blue-600">{{ formatNumber(analytics.leave.total_leave_days, 0) }}</div>
						<div class="text-sm text-gray-500">{{ trans("Total Leave Days") }}</div>
					</div>

					<h4 class="text-sm font-medium text-gray-700 mb-3">{{ trans("Breakdown by Type") }}</h4>
					<div class="space-y-2">
						<div 
							v-for="(days, type) in analytics.leave.leave_breakdown" 
							:key="type"
							class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
							<div class="flex items-center gap-2">
								<div class="w-3 h-3 rounded-full" :class="getLeaveColor(type)"></div>
								<span class="text-sm">{{ leaveTypes[type] || type }}</span>
							</div>
							<span class="font-medium">{{ formatNumber(days, 0) }} {{ trans("days") }}</span>
						</div>
						<div 
							v-if="Object.keys(analytics.leave.leave_breakdown).length === 0"
							class="text-center text-sm text-gray-500 py-2">
							{{ trans("No leave taken in this period") }}
						</div>
					</div>
				</div>

				<div class="bg-white shadow-sm rounded-lg p-6">
					<h3 class="text-lg font-medium text-gray-900 mb-4">{{ trans("Leave Balance") }}</h3>
					
					<div class="space-y-3">
						<div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
							<span class="text-sm text-gray-700">{{ trans("Annual Remaining") }}</span>
							<span class="font-bold text-blue-600">{{ formatNumber(analytics.leave.leave_balance.annual_remaining, 0) }}</span>
						</div>
						<div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
							<span class="text-sm text-gray-700">{{ trans("Medical Remaining") }}</span>
							<span class="font-bold text-red-600">{{ formatNumber(analytics.leave.leave_balance.medical_remaining, 0) }}</span>
						</div>
						<div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
							<span class="text-sm text-gray-700">{{ trans("Unpaid Remaining") }}</span>
							<span class="font-bold text-gray-600">{{ formatNumber(analytics.leave.leave_balance.unpaid_remaining, 0) }}</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
