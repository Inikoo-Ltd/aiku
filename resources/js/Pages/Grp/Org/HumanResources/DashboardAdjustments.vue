<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faChevronRight } from "@fal"

library.add(faChevronLeft, faChevronRight)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	filters: {
		year: number
		month: number
		employee_id?: number
		status?: string
	}
	calendarData: {
		id: number
		name: string
		adjustments: {
			id: number
			date: string
			original_start_at: string | null
			original_end_at: string | null
			requested_start_at: string | null
			requested_end_at: string | null
			reason: string
			status: string
		}[]
	}[]
	daysInMonth: number
	monthName: string
	employeeOptions: { value: number; label: string }[]
	statusOptions: { value: string; label: string }[]
}>()

const showModal = ref(false)
const selectedAdjustment = ref<any>(null)

const openModal = (adjustment: any) => {
	selectedAdjustment.value = adjustment
	showModal.value = true
}

const closeModal = () => {
	showModal.value = false
	selectedAdjustment.value = null
}

const monthOptions = computed(() => {
	return Array.from({ length: 12 }, (_, i) => {
		const date = new Date(2000, i, 1)
		return {
			value: i + 1,
			label: date.toLocaleString("default", { month: "long" }),
		}
	})
})

const yearOptions = computed(() => {
	const currentYear = new Date().getFullYear()
	return Array.from({ length: 5 }, (_, i) => {
		return {
			value: currentYear - 2 + i,
			label: String(currentYear - 2 + i),
		}
	})
})

const updateFilter = () => {
	router.get(
		route("grp.org.hr.adjustments.dashboard", route().params),
		{
			year: props.filters.year,
			month: props.filters.month,
			employee_id: props.filters.employee_id,
			status: props.filters.status,
		},
		{
			preserveState: true,
			preserveScroll: true,
		}
	)
}

const prevMonth = () => {
	let newMonth = props.filters.month - 1
	let newYear = props.filters.year
	if (newMonth < 1) {
		newMonth = 12
		newYear--
	}

	router.visit(
		route("grp.org.hr.adjustments.dashboard", {
			...route().params,
			year: newYear,
			month: newMonth,
		})
	)
}

const nextMonth = () => {
	let newMonth = props.filters.month + 1
	let newYear = props.filters.year
	if (newMonth > 12) {
		newMonth = 1
		newYear++
	}

	router.visit(
		route("grp.org.hr.adjustments.dashboard", {
			...route().params,
			year: newYear,
			month: newMonth,
		})
	)
}

const days = computed(() => {
	return Array.from({ length: props.daysInMonth }, (_, i) => i + 1)
})

const getDayName = (day: number) => {
	const date = new Date(props.filters.year, props.filters.month - 1, day)
	return date.toLocaleDateString("en-US", { weekday: "short" }).slice(0, 2)
}

const isWeekend = (day: number) => {
	const date = new Date(props.filters.year, props.filters.month - 1, day)
	const dayOfWeek = date.getDay()
	return dayOfWeek === 0 || dayOfWeek === 6
}

const getAdjustmentsForDay = (adjustments: any[], day: number) => {
	const dateStr = `${props.filters.year}-${String(props.filters.month).padStart(2, "0")}-${String(day).padStart(2, "0")}`

	return adjustments.filter((adjustment) => adjustment.date === dateStr)
}

const getStatusColor = (status: string): string => {
	switch (status) {
		case "approved":
			return "#22C55E"
		case "pending":
			return "#EAB308"
		case "rejected":
			return "#EF4444"
		default:
			return "#4F46E5"
	}
}
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #before-title> </template>
	</PageHeading>

	<div class="mt-5 bg-white shadow-sm rounded-lg p-4">
		<div class="flex flex-col sm:flex-row gap-4 items-center justify-between mb-6">
			<div class="flex gap-2 items-center">
				<Button type="secondary" :icon="faChevronLeft" size="sm" @click="prevMonth" />
				<h2 class="text-xl font-bold text-gray-800 w-48 text-center">
					{{ monthName }} {{ filters.year }}
				</h2>
				<Button type="secondary" :icon="faChevronRight" size="sm" @click="nextMonth" />
			</div>

			<div class="flex gap-2 items-center flex-wrap">
				<select
					v-model="filters.employee_id"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option :value="null">{{ trans("All Employees") }}</option>
					<option
						v-for="employee in employeeOptions"
						:key="employee.value"
						:value="employee.value">
						{{ employee.label }}
					</option>
				</select>

				<select
					v-model="filters.status"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option :value="null">{{ trans("All Statuses") }}</option>
					<option
						v-for="status in statusOptions"
						:key="status.value"
						:value="status.value">
						{{ status.label }}
					</option>
				</select>

				<select
					v-model="filters.year"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option v-for="year in yearOptions" :key="year.value" :value="year.value">
						{{ year.label }}
					</option>
				</select>

				<select
					v-model="filters.month"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option v-for="month in monthOptions" :key="month.value" :value="month.value">
						{{ month.label }}
					</option>
				</select>
			</div>
		</div>

		<div class="overflow-x-auto">
			<div class="min-w-max">
				<table class="w-full border-collapse">
					<thead>
						<tr>
							<th
								class="p-2 border-b border-r border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 z-10 w-48 min-w-[12rem]">
								{{ trans("Employee") }}
							</th>
							<th
								v-for="day in days"
								:key="day"
								class="p-1 border-b border-gray-200 bg-gray-50 text-center w-10 min-w-[2.5rem]"
								:class="{ 'bg-gray-100': isWeekend(day) }">
								<div class="text-xs font-semibold text-gray-700">{{ day }}</div>
								<div class="text-[10px] text-gray-500">{{ getDayName(day) }}</div>
							</th>
						</tr>
					</thead>
					<tbody class="bg-white divide-y divide-gray-200">
						<tr
							v-for="employee in calendarData"
							:key="employee.id"
							class="hover:bg-gray-50">
							<td
								class="p-2 border-r border-gray-200 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-white z-10">
								{{ employee.name }}
							</td>
							<td
								v-for="day in days"
								:key="day"
								class="p-1 border-r border-gray-100 h-12 relative align-top"
								:class="{ 'bg-gray-50': isWeekend(day) }">
								<div class="w-full h-full flex flex-col gap-0.5 justify-center">
									<template
										v-for="adjustment in getAdjustmentsForDay(
											employee.adjustments,
											day
										)"
										:key="adjustment.id">
										<div
											class="flex-1 min-h-[4px] rounded w-full cursor-pointer group relative flex items-center justify-center text-[10px] text-white font-medium hover:opacity-80 transition-opacity"
											:style="{
												backgroundColor: getStatusColor(adjustment.status),
											}"
											:title="`${adjustment.status}`"
											@click="openModal(adjustment)">
											A
										</div>
									</template>
								</div>
							</td>
						</tr>
						<tr v-if="calendarData.length === 0">
							<td :colspan="daysInMonth + 1" class="p-8 text-center text-gray-500">
								{{ trans("No employees found.") }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<Modal :show="showModal" @close="closeModal">
		<div class="p-6">
			<div class="flex items-center justify-between mb-4">
				<h3 class="text-lg font-medium text-gray-900">
					{{ trans("Adjustment Details") }}
				</h3>
				<button @click="closeModal" class="text-gray-400 hover:text-gray-500">
					<span class="sr-only">Close</span>
					<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M6 18L18 6M6 6l12 12" />
					</svg>
				</button>
			</div>

			<div v-if="selectedAdjustment" class="space-y-4">
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Date")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">{{ selectedAdjustment.date }}</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Status")
						}}</label>
						<div class="mt-1">
							<span
								class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
								:class="{
									'bg-green-100 text-green-800':
										selectedAdjustment.status === 'approved',
									'bg-yellow-100 text-yellow-800':
										selectedAdjustment.status === 'pending',
									'bg-red-100 text-red-800':
										selectedAdjustment.status === 'rejected',
								}">
								{{ capitalize(selectedAdjustment.status) }}
							</span>
						</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Original Start")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">
							{{ selectedAdjustment.original_start_at ?? "—" }}
						</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Original End")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">
							{{ selectedAdjustment.original_end_at ?? "—" }}
						</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Requested Start")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">
							{{ selectedAdjustment.requested_start_at ?? "—" }}
						</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Requested End")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">
							{{ selectedAdjustment.requested_end_at ?? "—" }}
						</div>
					</div>
				</div>

				<div v-if="selectedAdjustment.reason">
					<label class="block text-sm font-medium text-gray-500">{{
						trans("Reason")
					}}</label>
					<div
						class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md border border-gray-100">
						{{ selectedAdjustment.reason }}
					</div>
				</div>
			</div>

			<div class="mt-6 flex justify-end">
				<Button type="secondary" @click="closeModal">
					{{ trans("Close") }}
				</Button>
			</div>
		</div>
	</Modal>
</template>
