<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faChevronRight, faDownload, faFileExcel, faFileCsv } from "@fal"

library.add(faChevronLeft, faChevronRight, faDownload, faFileExcel, faFileCsv)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	filters: {
		year: number
		month: number
		employee_id?: number
		type?: string
	}
	calendarData: {
		id: number
		name: string
		leaves: {
			id: number
			start_date: string
			end_date: string
			type: string
			type_label: string
			duration_days: number
			reason: string
			status: string
		}[]
	}[]
	daysInMonth: number
	monthName: string
	employeeOptions: { value: number; label: string }[]
	typeOptions: { value: string; label: string }[]
	type_options?: Record<string, string>
	status_options?: Record<string, string>
}>()

const showModal = ref(false)
const selectedLeave = ref<any>(null)
const isExportModalOpen = ref(false)
const isExporting = ref(false)

const exportForm = useForm({
	from: "",
	to: "",
	type: "",
	status: "",
	department: "",
	team: "",
	employee_id: null as number | null,
	format: "xlsx",
})

const openModal = (leave: any) => {
	selectedLeave.value = leave
	showModal.value = true
}

const closeModal = () => {
	showModal.value = false
	selectedLeave.value = null
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
		route("grp.org.hr.leaves.dashboard", route().params),
		{
			year: props.filters.year,
			month: props.filters.month,
			employee_id: props.filters.employee_id,
			type: props.filters.type,
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
		route("grp.org.hr.leaves.dashboard", { ...route().params, year: newYear, month: newMonth })
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
		route("grp.org.hr.leaves.dashboard", { ...route().params, year: newYear, month: newMonth })
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

const getLeavesForDay = (leaves: any[], day: number) => {
	const dateStr = `${props.filters.year}-${String(props.filters.month).padStart(2, "0")}-${String(day).padStart(2, "0")}`

	return leaves.filter((leave) => {
		const start = leave.start_date
		const end = leave.end_date
		return dateStr >= start && dateStr <= end
	})
}

const getTypeColor = (type: string): string => {
	switch (type) {
		case "annual":
			return "#3B82F6"
		case "medical":
			return "#EF4444"
		case "unpaid":
			return "#6B7280"
		default:
			return "#4F46E5"
	}
}

const openExportModal = () => {
	exportForm.reset()
	isExportModalOpen.value = true
}

const closeExportModal = () => {
	isExportModalOpen.value = false
	exportForm.reset()
}

const submitExport = () => {
	const orgId = route().params.organisation
	if (!orgId) {
		alert("Error: Cannot find organisation ID")
		return
	}

	isExporting.value = true

	const exportParams: Record<string, any> = {
		organisation: orgId,
		format: exportForm.format,
	}

	if (exportForm.from) exportParams.from = exportForm.from
	if (exportForm.to) exportParams.to = exportForm.to
	if (exportForm.type) exportParams.type = exportForm.type
	if (exportForm.status) exportParams.status = exportForm.status
	if (exportForm.department) exportParams.department = exportForm.department
	if (exportForm.team) exportParams.team = exportForm.team
	if (exportForm.employee_id) exportParams.employee_id = exportForm.employee_id

	isExportModalOpen.value = false
	window.location.href = route("grp.org.hr.leaves.export", exportParams)

	setTimeout(() => {
		isExporting.value = false
	}, 1500)
}
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #other>
			<Button
				type="secondary"
				:icon="faDownload"
				:label="trans('Export')"
				@click="openExportModal" />
		</template>
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
					v-model="filters.type"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option :value="null">{{ trans("All Types") }}</option>
					<option v-for="type in typeOptions" :key="type.value" :value="type.value">
						{{ type.label }}
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
										v-for="leave in getLeavesForDay(employee.leaves, day)"
										:key="leave.id">
										<div
											class="flex-1 min-h-[4px] rounded w-full cursor-pointer group relative flex items-center justify-center text-[10px] text-white font-medium hover:opacity-80 transition-opacity"
											:style="{ backgroundColor: getTypeColor(leave.type) }"
											:title="`${leave.type_label} (${leave.duration_days} days)`"
											@click="openModal(leave)">
											{{ leave.duration_days }}
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
					{{ trans("Leave Details") }}
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

			<div v-if="selectedLeave" class="space-y-4">
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Employee")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">{{ selectedLeave.name }}</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Type")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">{{ selectedLeave.type_label }}</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Start Date")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">{{ selectedLeave.start_date }}</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("End Date")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">{{ selectedLeave.end_date }}</div>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-500">{{
							trans("Duration")
						}}</label>
						<div class="mt-1 text-sm text-gray-900">
							{{ selectedLeave.duration_days }} {{ trans("days") }}
						</div>
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
										selectedLeave.status === 'approved',
									'bg-yellow-100 text-yellow-800':
										selectedLeave.status === 'pending',
									'bg-red-100 text-red-800': selectedLeave.status === 'rejected',
								}">
								{{ capitalize(selectedLeave.status) }}
							</span>
						</div>
					</div>
				</div>

				<div v-if="selectedLeave.reason">
					<label class="block text-sm font-medium text-gray-500">{{
						trans("Reason")
					}}</label>
					<div
						class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md border border-gray-100">
						{{ selectedLeave.reason }}
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

	<Modal :isOpen="isExportModalOpen" @onClose="closeExportModal" width="w-full max-w-lg">
		<div class="p-6">
			<h3 class="text-lg font-semibold text-gray-900 mb-4">
				{{ trans("Export Leave Reports") }}
			</h3>
			<p class="text-sm text-gray-600 mb-4">
				{{ trans("Select filters and export format for your leave report.") }}
			</p>

			<form @submit.prevent="submitExport" class="space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("From Date")
						}}</label>
						<input
							v-model="exportForm.from"
							type="date"
							class="w-full border border-gray-300 rounded-lg px-3 py-2" />
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("To Date")
						}}</label>
						<input
							v-model="exportForm.to"
							type="date"
							class="w-full border border-gray-300 rounded-lg px-3 py-2" />
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Leave Type")
						}}</label>
						<select
							v-model="exportForm.type"
							class="w-full border border-gray-300 rounded-lg px-3 py-2">
							<option value="">{{ trans("All Types") }}</option>
							<option
								v-for="(label, value) in type_options"
								:key="value"
								:value="value">
								{{ label }}
							</option>
						</select>
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Status")
						}}</label>
						<select
							v-model="exportForm.status"
							class="w-full border border-gray-300 rounded-lg px-3 py-2">
							<option value="">{{ trans("All Statuses") }}</option>
							<option
								v-for="(label, value) in status_options"
								:key="value"
								:value="value">
								{{ label }}
							</option>
						</select>
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Department")
						}}</label>
						<input
							v-model="exportForm.department"
							type="text"
							class="w-full border border-gray-300 rounded-lg px-3 py-2"
							:placeholder="trans('Filter by department')" />
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							trans("Team")
						}}</label>
						<input
							v-model="exportForm.team"
							type="text"
							class="w-full border border-gray-300 rounded-lg px-3 py-2"
							:placeholder="trans('Filter by team')" />
					</div>
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1">{{
						trans("Employee")
					}}</label>
					<input
						v-model.number="exportForm.employee_id"
						type="number"
						class="w-full border border-gray-300 rounded-lg px-3 py-2"
						:placeholder="trans('Filter by employee ID')" />
				</div>

				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2">{{
						trans("Export Format")
					}}</label>
					<div class="flex gap-4">
						<label class="flex items-center gap-2 cursor-pointer">
							<input
								v-model="exportForm.format"
								type="radio"
								value="xlsx"
								class="text-blue-600 focus:ring-blue-500" />
							<span class="text-sm">{{ trans("Excel (XLSX)") }}</span>
						</label>
						<label class="flex items-center gap-2 cursor-pointer">
							<input
								v-model="exportForm.format"
								type="radio"
								value="csv"
								class="text-blue-600 focus:ring-blue-500" />
							<span class="text-sm">{{ trans("CSV") }}</span>
						</label>
					</div>
				</div>

				<div class="flex justify-end gap-3 pt-4">
					<Button @click="closeExportModal" :label="trans('Cancel')" type="tertiary" />
					<Button
						type="primary"
						nativeType="submit"
						:label="trans('Export')"
						:loading="isExporting"
						icon="fal fa-download" />
				</div>
			</form>
		</div>
	</Modal>
</template>
