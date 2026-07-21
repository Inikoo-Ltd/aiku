<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 08 Sept 2022 10:30:19 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import {
	Chart as ChartJS,
	ArcElement,
	Tooltip,
	Legend,
	BarElement,
	CategoryScale,
	LinearScale,
} from "chart.js"
import { Bar, Doughnut } from "vue-chartjs"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime, useSecondsToMS } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserCheck, faUmbrellaBeach, faClock, faUserSlash, faBirthdayCake, faUsers, faBuilding, faSitemap, faArrowRight } from "@fal"

library.add(faUserCheck, faUmbrellaBeach, faClock, faUserSlash, faBirthdayCake, faUsers, faBuilding, faSitemap, faArrowRight)
ChartJS.register(ArcElement, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

interface AttendanceRow {
	id: number
	employee_name: string
	job_title: string | null
	avatar: string
	start_at: string | null
	end_at: string | null
	notes: string | null
	is_open: boolean
	is_late: boolean
	working_duration: number
	breaks_duration: number
	clock_in_count: number
	clock_out_count: number
	route: { name: string; parameters: Record<string, unknown> }
}

interface BirthdayRow {
	id: number
	name: string
	job_title: string | null
	avatar: string
	day: number
	date_label: string
	is_today: boolean
}

interface StatCard {
	name: string
	stat: number
	color: string
	icon: [string, string]
	route?: { name: string; parameters: Record<string, unknown> }
}

interface LeaveOverviewDay {
	label: string
	count: number
	is_today: boolean
}

interface EmployeeLeave {
	id: number
	name: string
	avatar: string
	type_name: string
	type_color: string
	date_label: string
}

interface LeaveTypeSlice {
	name: string
	color: string
	count: number
	percentage: number
}

const props = defineProps<{
	title: string
	pageHead: object
	stats: StatCard[]
	attendance: AttendanceRow[]
	birthdays: BirthdayRow[]
	leaveOverview: LeaveOverviewDay[]
	employeeLeaves: EmployeeLeave[]
	leaveTypes: { total: number; types: LeaveTypeSlice[] }
}>()

const leaveOverviewData = computed(() => ({
	labels: props.leaveOverview.map((d) => d.label),
	datasets: [
		{
			label: trans("Employees"),
			data: props.leaveOverview.map((d) => d.count),
			backgroundColor: props.leaveOverview.map((d) => (d.is_today ? "#10b981" : "#e5e7eb")),
			borderRadius: 6,
			maxBarThickness: 34,
		},
	],
}))

const leaveOverviewOptions = {
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: { display: false },
		tooltip: {
			callbacks: {
				label: (context: any) => `${context.parsed.y} ${trans("Employees")}`,
			},
		},
	},
	scales: {
		x: { grid: { display: false } },
		y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "rgba(0,0,0,0.05)" } },
	},
}

const leaveTypesData = computed(() => ({
	labels: props.leaveTypes.types.map((t) => t.name),
	datasets: [
		{
			data: props.leaveTypes.types.map((t) => t.count),
			backgroundColor: props.leaveTypes.types.map((t) => t.color),
			borderWidth: 0,
			hoverOffset: 6,
		},
	],
}))

const leaveTypesOptions = {
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	plugins: {
		legend: { display: false },
		tooltip: {
			callbacks: {
				label: (context: any) => `${context.label}: ${context.parsed}`,
			},
		},
	},
}

const iconColors: Record<string, { icon: string; bg: string }> = {
	indigo: { icon: "text-indigo-500", bg: "bg-indigo-50" },
	teal: { icon: "text-teal-500", bg: "bg-teal-50" },
	purple: { icon: "text-purple-500", bg: "bg-purple-50" },
	green: { icon: "text-green-500", bg: "bg-green-50" },
	blue: { icon: "text-blue-500", bg: "bg-blue-50" },
	amber: { icon: "text-amber-500", bg: "bg-amber-50" },
	red: { icon: "text-red-500", bg: "bg-red-50" },
}

const todayLabel = useFormatTime(new Date(), { formatTime: "aiku" })
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>

	<div class="grid grid-cols-2 gap-4 px-4 pt-4 md:grid-cols-4 xl:grid-cols-7">
		<component
			:is="card.route ? Link : 'div'"
			v-for="card in stats"
			:key="card.name"
			:href="card.route ? route(card.route.name, card.route.parameters) : undefined"
			class="group flex flex-col gap-3 overflow-hidden rounded-xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-100 transition hover:shadow-md"
			:class="{ 'cursor-pointer': card.route }">
			<div class="flex items-center justify-between">
				<div
					class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
					:class="iconColors[card.color]?.bg ?? 'bg-gray-100'">
					<FontAwesomeIcon :icon="card.icon" :class="iconColors[card.color]?.icon ?? 'text-gray-500'" fixed-width />
				</div>
				<FontAwesomeIcon
					v-if="card.route"
					:icon="['fal', 'fa-arrow-right']"
					class="text-gray-300 opacity-0 transition group-hover:opacity-100"
					fixed-width />
			</div>
			<div>
				<dd class="text-2xl font-bold tracking-tight text-gray-800">{{ card.stat }}</dd>
				<dt class="mt-0.5 truncate text-sm font-medium text-gray-500">{{ card.name }}</dt>
			</div>
		</component>
	</div>

	<!-- Today's attendance (full width) -->
	<div class="mt-6 px-4 pb-6">
		<div class="bg-white shadow-sm rounded-lg p-4">
			<div class="flex items-center justify-between mb-4">
				<div>
					<h2 class="text-lg font-bold text-gray-800">{{ trans("Today's attendance") }}</h2>
					<p class="text-xs text-gray-500">{{ todayLabel }} · {{ trans("earliest arrivals first") }}</p>
				</div>
				<span class="inline-flex items-center rounded-full bg-green-100 px-3 py-0.5 text-sm font-medium text-green-800">
					{{ attendance.length }} {{ trans("present") }}
				</span>
			</div>

			<div class="overflow-x-auto">
				<table class="w-full text-sm">
					<thead>
						<tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 border-b border-gray-200">
							<th class="py-2 pr-3">{{ trans("Name") }}</th>
							<th class="py-2 px-3">{{ trans("Start At") }}</th>
							<th class="py-2 px-3">{{ trans("End At") }}</th>
							<th class="py-2 px-3">{{ trans("Status") }}</th>
							<th class="py-2 px-3">{{ trans("Notes") }}</th>
							<th class="py-2 px-3 text-right">{{ trans("Working") }}</th>
							<th class="py-2 px-3 text-right">{{ trans("Breaks") }}</th>
							<th class="py-2 px-3 text-center">{{ trans("Clock In") }}</th>
							<th class="py-2 pl-3 text-center">{{ trans("Clock Out") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in attendance" :key="row.id" class="hover:bg-gray-50">
							<td class="py-2 pr-3">
								<div class="flex items-center gap-3">
									<img :src="row.avatar" :alt="row.employee_name" class="h-9 w-9 rounded-full object-cover bg-gray-100" />
									<div class="min-w-0">
										<Link
											:href="route(row.route.name, row.route.parameters)"
											class="block font-medium text-gray-900 hover:text-indigo-600 hover:underline truncate">
											{{ row.employee_name }}
										</Link>
										<div class="text-xs text-gray-500 truncate">{{ row.job_title || "—" }}</div>
									</div>
								</div>
							</td>
							<td class="py-2 px-3 whitespace-nowrap text-gray-700" :class="{ 'text-red-600 font-medium': row.is_late }">
								{{ useFormatTime(row.start_at, { formatTime: "hh:mm a" }) }}
							</td>
							<td class="py-2 px-3 whitespace-nowrap text-gray-700">
								<span v-if="row.is_open" class="text-blue-500 italic">{{ trans("Still working") }}</span>
								<span v-else>{{ useFormatTime(row.end_at, { formatTime: "hh:mm a" }) }}</span>
							</td>
							<td class="py-2 px-3">
								<span v-if="row.is_late" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">
									{{ trans("Late") }}
								</span>
								<span v-else-if="row.is_open" class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
									{{ trans("Working") }}
								</span>
								<span v-else class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
									{{ trans("On time") }}
								</span>
							</td>
							<td class="py-2 px-3 text-gray-500 max-w-[14rem] truncate">{{ row.notes || "—" }}</td>
							<td class="py-2 px-3 text-right whitespace-nowrap text-gray-700">{{ useSecondsToMS(row.working_duration) }}</td>
							<td class="py-2 px-3 text-right whitespace-nowrap text-gray-500">{{ useSecondsToMS(row.breaks_duration) }}</td>
							<td class="py-2 px-3 text-center text-gray-700">{{ row.clock_in_count }}</td>
							<td class="py-2 pl-3 text-center text-gray-700">{{ row.clock_out_count }}</td>
						</tr>
						<tr v-if="attendance.length === 0">
							<td colspan="9" class="py-10 text-center text-gray-400">
								{{ trans("No one has clocked in yet today.") }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="grid grid-cols-1 gap-5 px-4 pb-8 md:grid-cols-2 xl:grid-cols-4">
		<!-- Leave overview -->
		<div class="bg-white shadow-sm rounded-lg p-4 flex flex-col">
			<div class="flex items-center justify-between mb-4">
				<h2 class="text-lg font-bold text-gray-800">{{ trans("Leave overview") }}</h2>
				<span class="text-xs text-gray-400">{{ trans("This week") }}</span>
			</div>
			<div class="h-64">
				<Bar :data="leaveOverviewData" :options="leaveOverviewOptions" />
			</div>
		</div>

		<!-- Employee leaves -->
		<div class="bg-white shadow-sm rounded-lg p-4 flex flex-col">
			<h2 class="text-lg font-bold text-gray-800 mb-3">{{ trans("Employee leaves") }}</h2>
			<ul v-if="employeeLeaves.length" class="divide-y divide-gray-100 max-h-64 overflow-y-auto pr-1">
				<li v-for="leave in employeeLeaves" :key="leave.id" class="flex items-center gap-3 py-2.5">
					<img :src="leave.avatar" :alt="leave.name" class="h-8 w-8 rounded-full object-cover bg-gray-100" />
					<div class="min-w-0 flex-1">
						<div class="font-medium text-gray-900 truncate">{{ leave.name }}</div>
						<div class="text-xs font-medium truncate" :style="{ color: leave.type_color }">{{ leave.type_name }}</div>
					</div>
					<div class="text-xs text-gray-500 whitespace-nowrap">{{ leave.date_label }}</div>
				</li>
			</ul>
			<div v-else class="flex-1 flex items-center justify-center py-10 text-center text-gray-400 text-sm">
				{{ trans("No upcoming leaves.") }}
			</div>
		</div>

		<!-- Leave types -->
		<div class="bg-white shadow-sm rounded-lg p-4 flex flex-col">
			<h2 class="text-lg font-bold text-gray-800 mb-3">{{ trans("Leave types") }}</h2>
			<div class="relative h-40 w-40 mx-auto shrink-0">
				<Doughnut v-if="leaveTypes.total > 0" :data="leaveTypesData" :options="leaveTypesOptions" />
				<div v-else class="flex h-full w-full items-center justify-center rounded-full border-8 border-gray-100" />
				<div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
					<span class="text-3xl font-bold text-gray-800">{{ leaveTypes.total }}</span>
					<span class="text-xs text-gray-400">{{ trans("Employees") }}</span>
				</div>
			</div>
			<div class="mt-4 space-y-2 max-h-40 overflow-y-auto pr-1">
				<div
					v-for="type in leaveTypes.types"
					:key="type.name"
					class="flex items-center justify-between gap-3 text-sm">
					<div class="flex min-w-0 items-center gap-2">
						<span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold text-white" :style="{ backgroundColor: type.color }">
							{{ type.percentage }}%
						</span>
						<span class="truncate text-gray-700">{{ type.name }}</span>
					</div>
					<span class="whitespace-nowrap text-gray-500">
						{{ type.count }} {{ type.count === 1 ? trans("employee") : trans("employees") }}
					</span>
				</div>
				<div v-if="leaveTypes.types.length === 0" class="py-6 text-center text-gray-400 text-sm">
					{{ trans("No leaves this month.") }}
				</div>
			</div>
		</div>

		<!-- Birthdays this month (beside Leave types) -->
		<div class="bg-white shadow-sm rounded-lg p-4 flex flex-col">
			<div class="flex items-center gap-2 mb-3">
				<FontAwesomeIcon :icon="faBirthdayCake" class="text-pink-500" fixed-width />
				<h2 class="text-lg font-bold text-gray-800">{{ trans("Birthdays this month") }}</h2>
			</div>

			<ul v-if="birthdays.length" class="divide-y divide-gray-100 max-h-64 overflow-y-auto pr-1">
				<li
					v-for="person in birthdays"
					:key="person.id"
					class="flex items-center gap-3 py-2.5"
					:class="{ 'bg-pink-50 -mx-2 px-2 rounded': person.is_today }">
					<img :src="person.avatar" :alt="person.name" class="h-8 w-8 rounded-full object-cover bg-gray-100" />
					<div class="min-w-0 flex-1">
						<div class="font-medium text-gray-900 truncate">{{ person.name }}</div>
						<div class="text-xs text-gray-500 truncate">{{ person.job_title || "—" }}</div>
					</div>
					<div class="text-right">
						<div class="text-sm font-medium text-gray-700">{{ person.date_label }}</div>
						<div v-if="person.is_today" class="text-xs font-semibold text-pink-600">🎂 {{ trans("Today") }}</div>
					</div>
				</li>
			</ul>
			<div v-else class="flex-1 flex items-center justify-center py-10 text-center text-gray-400 text-sm">
				{{ trans("No birthdays this month.") }}
			</div>
		</div>
	</div>
</template>
