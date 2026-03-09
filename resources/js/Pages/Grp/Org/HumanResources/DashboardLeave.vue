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

type LeaveItem = {
	id: number
	employee_name: string
	start_date: string
	end_date: string
	type: string
	type_label: string
	duration_days: number
	reason: string
	status: string
}

type EmployeeCalendarRow = {
	id: number
	name: string
	leaves: LeaveItem[]
}

type CalendarDay = {
	date: string
	day_of_month: number
	is_current_month: boolean
	is_weekend: boolean
	week_index: number
}

type CalendarWeek = {
	week_index: number
	start: string
	end: string
	days: CalendarDay[]
}

type LeaveSegment = {
	id: string
	leave: LeaveItem
	weekIndex: number
	startCol: number
	endColExclusive: number
	continuesLeft: boolean
	continuesRight: boolean
	segmentStart: string
	segmentEnd: string
}

const props = defineProps<{
	title: string
	pageHead: any
	breadcrumbs: any
	filters: {
		year: number
		month: number
		employee_id?: number | null
		type?: string | null
		view: string
		week_start?: string
		department?: string | null
		search?: string | null
		sort_by?: string | null
	}
	calendarData: EmployeeCalendarRow[]
	weeks: any[]
	visibleRange: {
		start: string
		end: string
	}
	daysInMonth: number
	monthName: string
	employeeOptions: { value: number; label: string }[]
	typeOptions: { value: string; label: string }[]
	type_options?: Record<string, string>
	status_options?: Record<string, string>
	departmentOptions?: { value: string; label: string }[]
	holidays?: Array<{
		id: number
		label: string
		from: string
		to: string
		type: string
	}>
}>()

const showModal = ref(false)
const selectedLeave = ref<LeaveItem | null>(null)
const isExportModalOpen = ref(false)
const isExporting = ref(false)

const selectedYear = ref<number>(props.filters.year)
const selectedMonth = ref<number>(props.filters.month)
const selectedEmployeeId = ref<number | null>(props.filters.employee_id ?? null)
const selectedType = ref<string | null>(props.filters.type ?? null)
const selectedDepartment = ref<string | null>(props.filters.department ?? null)
const searchQuery = ref<string>(props.filters.search ?? "")
const selectedSortBy = ref<string>(props.filters.sort_by ?? "name")
const selectedView = ref<"month" | "week">(props.filters.view ?? "month")
const selectedWeekStart = ref<string>(props.filters.week_start ?? props.visibleRange.start)

const exportForm = useForm({
	from: "",
	to: "",
	type: "",
	status: "",
	department: "",
	team: "",
	employee_id: null as number | null,
	export_type: "report",
	format: "xlsx",
})

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

const weekdayLabels = [
	trans("Mo"),
	trans("Tu"),
	trans("We"),
	trans("Th"),
	trans("Fr"),
	trans("Sa"),
	trans("Su"),
]

const parseDateKey = (value: string): Date => {
	const [year, month, day] = value.split("-").map(Number)
	return new Date(year, (month || 1) - 1, day || 1)
}

const toDateKey = (date: Date): string => {
	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, "0")
	const day = String(date.getDate()).padStart(2, "0")
	return `${year}-${month}-${day}`
}

const addDays = (dateKey: string, amount: number): string => {
	const date = parseDateKey(dateKey)
	date.setDate(date.getDate() + amount)
	return toDateKey(date)
}

const diffDays = (from: string, to: string): number => {
	const fromDate = parseDateKey(from)
	const toDate = parseDateKey(to)
	const msInDay = 24 * 60 * 60 * 1000
	return Math.round((toDate.getTime() - fromDate.getTime()) / msInDay)
}

const minDateKey = (a: string, b: string): string => {
	return a <= b ? a : b
}

const maxDateKey = (a: string, b: string): string => {
	return a >= b ? a : b
}

const formatRangeDate = (dateKey: string): string => {
	return parseDateKey(dateKey).toLocaleDateString("en-US", {
		month: "short",
		day: "numeric",
		year: "numeric",
	})
}

const visibleWeeks = computed<CalendarWeek[]>(() => props.weeks ?? [])

const headerDays = computed<CalendarDay[]>(() => {
	if (visibleWeeks.value.length === 0) {
		return []
	}

	return visibleWeeks.value[0].days
})

const displayPeriodLabel = computed(() => {
	if (selectedView.value === "month") {
		return `${props.monthName} ${selectedYear.value}`
	}

	if (visibleWeeks.value.length === 0) {
		return trans("Week")
	}

	const week = visibleWeeks.value[0]
	return `${formatRangeDate(week.start)} - ${formatRangeDate(week.end)}`
})

const openModal = (leave: LeaveItem) => {
	selectedLeave.value = leave
	showModal.value = true
}

const closeModal = () => {
	showModal.value = false
	selectedLeave.value = null
}

const buildRequestData = (): Record<string, any> => {
	return {
		year: selectedYear.value,
		month: selectedMonth.value,
		employee_id: selectedEmployeeId.value ?? undefined,
		type: selectedType.value ?? undefined,
		department: selectedDepartment.value ?? undefined,
		search: searchQuery.value || undefined,
		sort_by: selectedSortBy.value,
		view: selectedView.value,
		week_start: selectedView.value === "week" ? selectedWeekStart.value : undefined,
	}
}

let searchTimeout: NodeJS.Timeout | null = null
const debouncedSearch = () => {
	if (searchTimeout) {
		clearTimeout(searchTimeout)
	}

	searchTimeout = setTimeout(() => {
		updateFilter()
	}, 300) // 300ms delay
}

const updateFilter = () => {
	router.get(route("grp.org.hr.leaves.dashboard", route().params), buildRequestData(), {
		preserveState: true,
		preserveScroll: true,
	})
}

const changeView = (view: "month" | "week") => {
	if (selectedView.value === view) {
		return
	}

	selectedView.value = view

	if (view === "week" && !selectedWeekStart.value) {
		selectedWeekStart.value = props.visibleRange.start
	}

	updateFilter()
}

const prevRange = () => {
	if (selectedView.value === "month") {
		let newMonth = selectedMonth.value - 1
		let newYear = selectedYear.value

		if (newMonth < 1) {
			newMonth = 12
			newYear -= 1
		}

		selectedMonth.value = newMonth
		selectedYear.value = newYear
		updateFilter()
		return
	}

	selectedWeekStart.value = addDays(selectedWeekStart.value, -7)
	const weekStartDate = parseDateKey(selectedWeekStart.value)
	selectedYear.value = weekStartDate.getFullYear()
	selectedMonth.value = weekStartDate.getMonth() + 1
	updateFilter()
}

const nextRange = () => {
	if (selectedView.value === "month") {
		let newMonth = selectedMonth.value + 1
		let newYear = selectedYear.value

		if (newMonth > 12) {
			newMonth = 1
			newYear += 1
		}

		selectedMonth.value = newMonth
		selectedYear.value = newYear
		updateFilter()
		return
	}

	selectedWeekStart.value = addDays(selectedWeekStart.value, 7)
	const weekStartDate = parseDateKey(selectedWeekStart.value)
	selectedYear.value = weekStartDate.getFullYear()
	selectedMonth.value = weekStartDate.getMonth() + 1
	updateFilter()
}

const isHoliday = (date: string): boolean => {
	if (!props.holidays) return false
	return props.holidays.some((holiday) => {
		const holidayStart = new Date(holiday.from)
		const holidayEnd = new Date(holiday.to)
		const checkDate = new Date(date)
		return checkDate >= holidayStart && checkDate <= holidayEnd
	})
}

const getHolidayLabel = (date: string): string => {
	if (!props.holidays) return ""
	const holiday = props.holidays.find((holiday) => {
		const holidayStart = new Date(holiday.from)
		const holidayEnd = new Date(holiday.to)
		const checkDate = new Date(date)
		return checkDate >= holidayStart && checkDate <= holidayEnd
	})
	return holiday ? holiday.label : ""
}

const getLeaveColor = (type: string): string => {
	switch (type) {
		case "annual":
			return "#16A34A" // Green
		case "medical":
			return "#EA580C" // Orange
		case "unpaid":
			return "#000000" // Black
		case "halfday-morning":
		case "halfday-afternoon":
			return "#16A34A" // Green
		case "training":
			return "#9333EA" // Purple
		case "leave-of-absence":
			return "#EA580C" // Orange
		case "compassionate":
			return "#DB2777" // Pink
		case "parental":
			return "#0891B2" // Cyan
		case "sabbatical":
			return "#4F46E5" // Indigo
		default:
			return "#4F46E5" // Indigo
	}
}

const getLeaveShortCode = (type: string): string => {
	switch (type) {
		case "annual":
			return "H"
		case "medical":
			return "S"
		case "unpaid":
			return "U"
		case "halfday-morning":
			return "HM"
		case "halfday-afternoon":
			return "HA"
		case "training":
			return "T"
		case "leave-of-absence":
			return "LA"
		case "compassionate":
			return "C"
		case "parental":
			return "P"
		case "sabbatical":
			return "SA"
		default:
			return "H"
	}
}

const createLeaveSegments = (employee: EmployeeCalendarRow): Record<number, LeaveSegment[]> => {
	const weekSegments: Record<number, LeaveSegment[]> = {}

	for (const week of visibleWeeks.value) {
		weekSegments[week.week_index] = []
	}

	const sortedLeaves = [...employee.leaves].sort((a, b) => {
		if (a.start_date === b.start_date) {
			return b.duration_days - a.duration_days
		}
		return a.start_date.localeCompare(b.start_date)
	})

	for (const leave of sortedLeaves) {
		if (!leave.start_date || !leave.end_date) {
			continue
		}

		const clippedStart = maxDateKey(leave.start_date, props.visibleRange.start)
		const clippedEnd = minDateKey(leave.end_date, props.visibleRange.end)

		if (clippedStart > clippedEnd) {
			continue
		}

		for (const week of visibleWeeks.value) {
			if (clippedStart > week.end || clippedEnd < week.start) {
				continue
			}

			const segmentStart = maxDateKey(clippedStart, week.start)
			const segmentEnd = minDateKey(clippedEnd, week.end)
			const startCol = diffDays(week.start, segmentStart) + 1
			const endColExclusive = diffDays(week.start, segmentEnd) + 2

			weekSegments[week.week_index].push({
				id: `${leave.id}-${week.week_index}-${segmentStart}`,
				leave,
				weekIndex: week.week_index,
				startCol,
				endColExclusive,
				continuesLeft: leave.start_date < segmentStart,
				continuesRight: leave.end_date > segmentEnd,
				segmentStart,
				segmentEnd,
			})
		}
	}

	return weekSegments
}

const isCollision = (left: LeaveSegment, right: LeaveSegment): boolean => {
	return left.startCol < right.endColExclusive && right.startCol < left.endColExclusive
}

const buildWeekLanes = (segments: LeaveSegment[]): LeaveSegment[][] => {
	const lanes: LeaveSegment[][] = []

	const sortedSegments = [...segments].sort((a, b) => {
		if (a.startCol === b.startCol) {
			return b.endColExclusive - a.endColExclusive
		}
		return a.startCol - b.startCol
	})

	for (const segment of sortedSegments) {
		let placed = false

		for (const lane of lanes) {
			if (lane.every((existing) => !isCollision(existing, segment))) {
				lane.push(segment)
				placed = true
				break
			}
		}

		if (!placed) {
			lanes.push([segment])
		}
	}

	return lanes
}

const employeeLaneData = computed(() => {
	return props.calendarData.map((employee) => {
		const segmentsByWeek = createLeaveSegments(employee)
		const lanesByWeek: Record<number, LeaveSegment[][]> = {}

		for (const week of visibleWeeks.value) {
			lanesByWeek[week.week_index] = buildWeekLanes(segmentsByWeek[week.week_index] ?? [])
		}

		return {
			...employee,
			lanesByWeek,
		}
	})
})

const getLanesForWeek = (
	employee: { lanesByWeek: Record<number, LeaveSegment[][]> },
	weekIndex: number
) => {
	return employee.lanesByWeek[weekIndex] ?? []
}

const getWeekContainerHeight = (laneCount: number): string => {
	const minHeight = 40
	const laneHeight = 28
	const padding = 8
	return `${Math.max(minHeight, laneCount * laneHeight + padding)}px`
}

const getLeaveSegmentStyle = (segment: LeaveSegment): Record<string, string | number> => {
	const isPending = segment.leave.status === "pending"

	return {
		gridColumn: `${segment.startCol} / ${segment.endColExclusive}`,
		backgroundColor: getLeaveColor(segment.leave.type),
		opacity: isPending ? 0.7 : 1,
		border: isPending ? "1px dashed rgba(255,255,255,0.9)" : "none",
		borderTopLeftRadius: segment.continuesLeft ? "0" : "9999px",
		borderBottomLeftRadius: segment.continuesLeft ? "0" : "9999px",
		borderTopRightRadius: segment.continuesRight ? "0" : "9999px",
		borderBottomRightRadius: segment.continuesRight ? "0" : "9999px",
	}
}

const getLeaveTooltip = (segment: LeaveSegment): string => {
	return `${segment.leave.employee_name} - ${segment.leave.type_label} (${segment.segmentStart} to ${segment.segmentEnd})`
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
	isExporting.value = true

	const exportParams: Record<string, any> = {}

	exportParams.year = selectedYear.value
	exportParams.month = selectedMonth.value
	exportParams.employee_id = selectedEmployeeId.value
	exportParams.type = selectedType.value
	exportParams.department = selectedDepartment.value
	exportParams.search = searchQuery.value

	if (exportForm.from) exportParams.from = exportForm.from
	if (exportForm.to) exportParams.to = exportForm.to
	if (exportForm.type) exportParams.type = exportForm.type
	if (exportForm.status) exportParams.status = exportForm.status
	if (exportForm.department) exportParams.department = exportForm.department
	if (exportForm.team) exportParams.team = exportForm.team
	if (exportForm.employee_id) exportParams.employee_id = exportForm.employee_id

	isExportModalOpen.value = false

	if (exportForm.export_type === "calendar" && exportForm.format === "print") {
		const printUrl = route("grp.org.hr.leaves.print", exportParams)
		window.open(printUrl, "_blank")
	} else {
		exportParams.format = exportForm.format
		const exportRoute =
			exportForm.export_type === "calendar"
				? "grp.org.hr.leaves.export.calendar"
				: "grp.org.hr.leaves.export"

		window.location.href = route(exportRoute, { ...route().params, ...exportParams })
	}

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
				<Button type="secondary" :icon="faChevronLeft" size="sm" @click="prevRange" />
				<h2 class="text-xl font-bold text-gray-800 min-w-[20rem] text-center">
					{{ displayPeriodLabel }}
				</h2>
				<Button type="secondary" :icon="faChevronRight" size="sm" @click="nextRange" />
			</div>

			<div class="flex gap-2 items-center flex-wrap">
				<div class="relative">
					<input
						v-model="searchQuery"
						@input="debouncedSearch"
						type="text"
						:placeholder="trans('Search employees...')"
						class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pl-8 pr-3 py-2" />
					<div
						class="absolute inset-y-0 left-0 flex items-center pl-2 pointer-events-none">
						<svg
							class="h-4 w-4 text-gray-400"
							fill="none"
							stroke="currentColor"
							viewBox="0 0 24 24">
							<path
								stroke-linecap="round"
								stroke-linejoin="round"
								stroke-width="2"
								d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
						</svg>
					</div>
				</div>

				<div
					class="inline-flex rounded-md shadow-sm overflow-hidden border border-gray-200">
					<button
						type="button"
						class="px-3 py-2 text-sm"
						:class="
							selectedView === 'month'
								? 'bg-indigo-600 text-white'
								: 'bg-white text-gray-700 hover:bg-gray-50'
						"
						@click="changeView('month')">
						{{ trans("Month") }}
					</button>
					<button
						type="button"
						class="px-3 py-2 text-sm border-l border-gray-200"
						:class="
							selectedView === 'week'
								? 'bg-indigo-600 text-white'
								: 'bg-white text-gray-700 hover:bg-gray-50'
						"
						@click="changeView('week')">
						{{ trans("Week") }}
					</button>
				</div>

				<select
					v-model="selectedEmployeeId"
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
					v-model="selectedType"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option :value="null">{{ trans("All Types") }}</option>
					<option v-for="type in typeOptions" :key="type.value" :value="type.value">
						{{ type.label }}
					</option>
				</select>

				<select
					v-model="selectedDepartment"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option :value="null">{{ trans("All Departments") }}</option>
					<option v-for="dept in departmentOptions" :key="dept.value" :value="dept.value">
						{{ dept.label }}
					</option>
				</select>

				<select
					v-model="selectedYear"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option v-for="year in yearOptions" :key="year.value" :value="year.value">
						{{ year.label }}
					</option>
				</select>

				<select
					v-model="selectedSortBy"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option value="name">{{ trans("Name") }}</option>
					<option value="last_name">{{ trans("Last Name") }}</option>
					<option value="first_name">{{ trans("First Name") }}</option>
					<option value="department">{{ trans("Department") }}</option>
				</select>

				<select
					v-model="selectedMonth"
					@change="updateFilter"
					class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
					<option v-for="month in monthOptions" :key="month.value" :value="month.value">
						{{ month.label }}
					</option>
				</select>
			</div>
		</div>

		<div class="overflow-x-auto">
			<div class="min-w-[56rem]">
				<div
					class="grid grid-cols-[12rem_minmax(0,1fr)] border border-gray-200 rounded-t-lg overflow-hidden">
					<div
						class="bg-gray-50 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 z-10 border-r border-gray-200">
						{{ trans("Employee") }}
					</div>
					<div class="grid grid-cols-7 bg-gray-50">
						<div
							v-for="(day, index) in headerDays"
							:key="day.date"
							class="px-2 py-1 text-center border-r border-gray-200 last:border-r-0"
							:class="{ 'bg-gray-100': day.is_weekend }">
							<div class="text-xs font-semibold text-gray-700">
								{{ weekdayLabels[index] }}
							</div>
							<div v-if="selectedView === 'week'" class="text-[10px] text-gray-500">
								{{ day.day_of_month }}
							</div>
						</div>
					</div>
				</div>

				<div
					v-if="employeeLaneData.length === 0"
					class="border-x border-b border-gray-200 p-8 text-center text-gray-500 rounded-b-lg">
					{{ trans("No employees found.") }}
				</div>

				<div
					v-for="employee in employeeLaneData"
					:key="employee.id"
					class="grid grid-cols-[12rem_minmax(0,1fr)] border-x border-b border-gray-200 bg-white">
					<div
						class="px-3 py-3 text-sm font-medium text-gray-900 border-r border-gray-200 sticky left-0 z-10 bg-white">
						<div class="flex items-center gap-2">
							<div
								class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-semibold text-indigo-600">
								{{
									employee.name
										.split(" ")
										.map((n) => n[0])
										.join("")
										.substring(0, 2)
										.toUpperCase()
								}}
							</div>
							<div>
								<div class="font-medium text-gray-900">{{ employee.name }}</div>
								<div class="text-xs text-gray-500">
									<span v-if="employee.job_title">{{ employee.job_title }}</span>
									<span v-if="employee.job_title && employee.department">
										•
									</span>
									<span v-if="employee.department">{{
										employee.department
									}}</span>
								</div>
							</div>
						</div>
					</div>

					<div class="p-2 space-y-2">
						<div
							v-for="week in visibleWeeks"
							:key="`${employee.id}-${week.week_index}`"
							class="relative border border-gray-100 rounded overflow-hidden"
							:style="{
								minHeight: getWeekContainerHeight(
									getLanesForWeek(employee, week.week_index).length
								),
							}">
							<div class="absolute inset-0 grid grid-cols-7">
								<div
									v-for="day in week.days"
									:key="`${employee.id}-${week.week_index}-${day.date}`"
									class="relative border-r border-gray-100 last:border-r-0"
									:class="{
										'bg-gray-50': day.is_weekend,
										'bg-gray-100/50':
											selectedView === 'month' && !day.is_current_month,
										'bg-red-50 border-red-200':
											isHoliday(day.date) && !day.is_weekend,
										'bg-red-100': isHoliday(day.date) && day.is_weekend,
									}">
									<div class="absolute top-1 right-1 text-[10px] text-gray-400">
										{{ day.day_of_month }}
									</div>
									<div
										v-if="isHoliday(day.date)"
										class="absolute bottom-1 left-1 right-1 text-[8px] text-red-600 font-medium truncate"
										:title="getHolidayLabel(day.date)">
										{{ getHolidayLabel(day.date) }}
									</div>
								</div>
							</div>

							<div class="relative p-1 space-y-1">
								<div
									v-for="(lane, laneIndex) in getLanesForWeek(
										employee,
										week.week_index
									)"
									:key="`${employee.id}-${week.week_index}-${laneIndex}`"
									class="grid grid-cols-7 gap-1 h-6">
									<button
										v-for="segment in lane"
										:key="segment.id"
										type="button"
										class="h-6 px-2 text-left text-[10px] text-white font-medium hover:brightness-95 transition-all truncate"
										:style="getLeaveSegmentStyle(segment)"
										:title="getLeaveTooltip(segment)"
										@click="openModal(segment.leave)">
										<span class="truncate block">{{
											getLeaveShortCode(segment.leave.type)
										}}</span>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
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
						<div class="mt-1 text-sm text-gray-900">
							{{ selectedLeave.employee_name }}
						</div>
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
						trans("Export Type")
					}}</label>
					<div class="space-y-2">
						<div class="flex gap-4">
							<label class="flex items-center gap-2 cursor-pointer">
								<input
									v-model="exportForm.export_type"
									type="radio"
									value="report"
									class="text-blue-600 focus:ring-blue-500" />
								<span class="text-sm">{{ trans("Leave Report") }}</span>
							</label>
							<label class="flex items-center gap-2 cursor-pointer">
								<input
									v-model="exportForm.export_type"
									type="radio"
									value="calendar"
									class="text-blue-600 focus:ring-blue-500" />
								<span class="text-sm">{{ trans("Calendar View") }}</span>
							</label>
						</div>
					</div>
				</div>

				<div v-if="exportForm.export_type === 'report'">
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

				<div v-if="exportForm.export_type === 'calendar'">
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
							<span class="text-sm">{{ trans("Excel Calendar") }}</span>
						</label>
						<label class="flex items-center gap-2 cursor-pointer">
							<input
								v-model="exportForm.format"
								type="radio"
								value="print"
								class="text-blue-600 focus:ring-blue-500" />
							<span class="text-sm">{{ trans("Print View") }}</span>
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
						:icon="exportForm.format === 'xlsx' ? faFileExcel : faFileCsv" />
				</div>
			</form>
		</div>
	</Modal>
</template>
