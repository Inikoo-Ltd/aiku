<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ExportModalActions from "@/Components/HumanResources/ExportModalActions.vue"
import EmployeeAvatar from "@/Components/HumanResources/EmployeeAvatar.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLayoutStore } from "@/Stores/layout"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { isoToDate, dateToIso, fieldFocusClass, checkboxAccentClass } from "@/Composables/useHrFormControls"
import Select from "primevue/select"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Checkbox from "primevue/checkbox"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faChevronRight, faDownload, faFileExcel, faFileCsv, faFilter, faSearch, faTimes } from "@fal"
import { notify } from "@kyvg/vue3-notification"

library.add(faChevronLeft, faChevronRight, faDownload, faFileExcel, faFileCsv, faFilter, faSearch, faTimes)

type LeaveItem = {
	id: number
	employee_name: string
	start_date: string
	end_date: string
	type: string
	type_label: string
	code: string
	color: string
	duration_days: number
	reason: string
	status: string
}

type EmployeeCalendarRow = {
	id: number
	name: string
	avatar?: string | null
	job_title?: string | null
	department?: string | null
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
	type_options?: Record<string, string | { label: string; category?: string }>
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
const exportError = ref(false)
const exportMessage = ref("")
let exportController: AbortController | null = null
let exportTimeoutId: NodeJS.Timeout | null = null

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
	include_summary: false,
	print_view: false,
})

const exportFrom = computed<Date | null>({
	get: () => isoToDate(exportForm.from),
	set: (value) => {
		exportForm.from = dateToIso(value)
	},
})

const exportTo = computed<Date | null>({
	get: () => isoToDate(exportForm.to),
	set: (value) => {
		exportForm.to = dateToIso(value)
	},
})

const parsedStatusOptions = computed(() =>
	Object.entries(props.status_options ?? {}).map(([value, label]) => ({ value, label }))
)

const parsedDepartmentOptions = computed(() => props.departmentOptions ?? [])

const viewOptions = [
	{ label: ctrans("Month"), value: "month" as const },
	{ label: ctrans("Week"), value: "week" as const },
]

const sortOptions = [
	{ value: "name", label: ctrans("Name") },
	{ value: "last_name", label: ctrans("Last Name") },
	{ value: "first_name", label: ctrans("First Name") },
	{ value: "department", label: ctrans("Team department") },
]

const layout = useLayoutStore()
const stickyTopClass = computed(() => (layout.hasTopBanner ? "top-[61px] lg:top-[68px]" : "top-[33px] lg:top-10"))
const filtersOpen = ref(false)
const collapsibleFilterClass = computed(() => (filtersOpen.value ? "" : "max-lg:!hidden"))
const activeFilterCount = computed(() => [searchQuery.value, selectedEmployeeId.value, selectedType.value, selectedDepartment.value].filter((value) => value !== null && value !== undefined && value !== "").length)
const gridHeaderScroller = ref<HTMLElement | null>(null)
const syncGridHeaderScroll = (event: Event) => {
	if (gridHeaderScroller.value) {
		gridHeaderScroller.value.scrollLeft = (event.target as HTMLElement).scrollLeft
	}
}

const toolbarSelectPt = { label: { class: "!py-[5px] !text-[13px] !leading-5" } }
const periodSelectPt = { label: { class: "!py-[5px] !text-base !leading-5 !font-semibold !text-gray-800" } }
const toolbarInputClass = "!py-[5px] !text-[13px] !leading-5 focus:!border-[--app-accent]"

const leaveColorPalette: Record<string, string> = {
	indigo: "#6366f1",
	green: "#22c55e",
	blue: "#3b82f6",
	gray: "#6b7280",
	grey: "#6b7280",
	red: "#ef4444",
	purple: "#a855f7",
	amber: "#f59e0b",
	yellow: "#eab308",
	orange: "#f97316",
	teal: "#14b8a6",
	cyan: "#06b6d4",
	pink: "#ec4899",
	lime: "#84cc16",
	emerald: "#10b981",
}
const leaveColorFallback = "#94a3b8"
const pendingStripes = "repeating-linear-gradient(135deg, rgba(255,255,255,0.45) 0 4px, transparent 4px 8px)"

const leaveColorHex = (color: string | null | undefined): string => {
	if (!color) {
		return leaveColorFallback
	}

	if (color.startsWith("#")) {
		return color
	}

	return leaveColorPalette[color.toLowerCase()] ?? leaveColorFallback
}

const leaveTextColor = (hex: string): string => {
	const match = /^#([0-9a-f]{6})$/i.exec(hex)

	if (!match) {
		return "#ffffff"
	}

	const value = parseInt(match[1], 16)
	const luminance = (0.299 * ((value >> 16) & 255) + 0.587 * ((value >> 8) & 255) + 0.114 * (value & 255)) / 255

	return luminance > 0.62 ? "#1f2937" : "#ffffff"
}

const leaveLegend = computed(() => {
	const legend = new Map<string, { label: string; color: string }>()

	for (const employee of props.calendarData) {
		for (const leave of Object.values(employee.leaves ?? {})) {
			const label = formatTypeLabel(leave.type_label) || leave.code

			if (!legend.has(label)) {
				legend.set(label, { label, color: leaveColorHex(leave.color) })
			}
		}
	}

	return [...legend.values()].sort((a, b) => a.label.localeCompare(b.label))
})

const hasPendingLeaves = computed(() =>
	props.calendarData.some((employee) => Object.values(employee.leaves ?? {}).some((leave) => leave.status === "pending"))
)

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
	const years = new Set(Array.from({ length: 5 }, (_, i) => currentYear - 2 + i))
	years.add(selectedYear.value)

	return [...years].sort((a, b) => a - b).map((year) => ({ value: year, label: String(year) }))
})

const weekdayLabels = [
	ctrans("Mo"),
	ctrans("Tu"),
	ctrans("We"),
	ctrans("Th"),
	ctrans("Fr"),
	ctrans("Sa"),
	ctrans("Su"),
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
		return ctrans("Week")
	}

	const week = visibleWeeks.value[0]
	return `${formatRangeDate(week.start)} - ${formatRangeDate(week.end)}`
})

const parsedTypeOptions = computed(() => {
	if (!props.type_options) return []

	if (Array.isArray(props.type_options)) {
		return props.type_options
	}

	if (typeof props.type_options === "object") {
		return Object.entries(props.type_options).map(([value, label]) => ({
			value,
			label: typeof label === "string" ? label : label.label || value,
		}))
	}

	// If it's a JSON string, parse it
	if (typeof props.type_options === "string") {
		try {
			const parsed = JSON.parse(props.type_options)
			return Object.entries(parsed).map(([value, label]) => ({
				value,
				label: typeof label === "string" ? label : label.label || value,
			}))
		} catch (e) {
			console.error("Failed to parse type_options:", e)
			return []
		}
	}

	return []
})

const openModal = (leave: LeaveItem) => {
	selectedLeave.value = leave
	showModal.value = true
}

const closeModal = () => {
	showModal.value = false
}

const formatTypeLabel = (typeLabel: string | Record<string, any> | undefined | null): string => {
	if (!typeLabel) return ""
	if (typeof typeLabel === "string") return typeLabel
	if (typeof typeLabel === "object" && typeLabel.label) return typeLabel.label
	return String(typeLabel)
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

const viewModel = computed<"month" | "week">({
	get: () => selectedView.value,
	set: (view) => changeView(view),
})

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

const findHoliday = (date: string) =>
	Object.values(props.holidays ?? {}).find((holiday) => date >= holiday.from && date <= holiday.to)

const isHoliday = (date: string): boolean => !!findHoliday(date)

const getHolidayLabel = (date: string): string => findHoliday(date)?.label ?? ""

const getDayCellClass = (day: CalendarDay): string => {
	if (isHoliday(day.date)) {
		return day.is_weekend ? "bg-red-100 border-red-200" : "bg-red-50 border-red-200"
	}

	if (day.is_weekend) {
		return "bg-gray-100 border-gray-200"
	}

	if (selectedView.value === "month" && !day.is_current_month) {
		return "bg-gray-50 border-gray-100"
	}

	return "border-gray-100"
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
	const color = leaveColorHex(segment.leave.color)

	return {
		gridColumn: `${segment.startCol} / ${segment.endColExclusive}`,
		backgroundColor: color,
		color: leaveTextColor(color),
		backgroundImage: isPending ? pendingStripes : "none",
		opacity: isPending ? 0.8 : 1,
		borderTopLeftRadius: segment.continuesLeft ? "0" : "9999px",
		borderBottomLeftRadius: segment.continuesLeft ? "0" : "9999px",
		borderTopRightRadius: segment.continuesRight ? "0" : "9999px",
		borderBottomRightRadius: segment.continuesRight ? "0" : "9999px",
	}
}

const getLeaveTooltip = (segment: LeaveSegment): string => {
	return `${segment.leave.employee_name} - ${formatTypeLabel(segment.leave.type_label)} (${segment.segmentStart} to ${segment.segmentEnd})`
}

const openExportModal = () => {
	exportForm.reset()
	exportForm.export_type = "calendar"
	exportError.value = false
	exportMessage.value = ""
	isExporting.value = false
	isExportModalOpen.value = true
}

const closeExportModal = () => {
	// Cancel any pending export
	if (exportController) {
		exportController.abort()
		exportController = null
	}

	// Clear any pending timeout
	if (exportTimeoutId) {
		clearTimeout(exportTimeoutId)
		exportTimeoutId = null
	}

	isExportModalOpen.value = false
	exportForm.reset()
	isExporting.value = false
}

const submitExport = () => {
	exportError.value = false
	exportMessage.value = ""
	isExporting.value = true

	// Create new abort controller for this export
	exportController = new AbortController()

	// Build export parameters FIRST
	const exportParams: Record<string, any> = {
		year: selectedYear.value,
		month: selectedMonth.value,
		employee_id: selectedEmployeeId.value,
		type: selectedType.value,
		department: selectedDepartment.value,
		search: searchQuery.value,
	}

	// Add form filters
	if (exportForm.from) exportParams.from = exportForm.from
	if (exportForm.to) exportParams.to = exportForm.to
	if (exportForm.type) exportParams.type = exportForm.type
	if (exportForm.status) exportParams.status = exportForm.status
	if (exportForm.department) exportParams.department = exportForm.department
	if (exportForm.team) exportParams.team = exportForm.team
	if (exportForm.employee_id) exportParams.employee_id = exportForm.employee_id

	// Set up timeout for long exports with stored ID
	exportTimeoutId = setTimeout(() => {
		if (exportController) {
			// Check if not cancelled
			isExporting.value = false
			notify({
				title: "Export taking longer than expected",
				text: "The Export is taking longer than expected",
				type: "warning",
			})
		}
	}, 30000)

	try {
		if (exportController?.signal.aborted) {
			return
		}

		isExportModalOpen.value = false

		if (exportForm.export_type === "calendar" && exportForm.format === "print") {
			// Handle print export
			const printUrl = route("grp.org.hr.leaves.print", {
				...route().params,
				...exportParams,
			})
			window.open(printUrl, "_blank")

			setTimeout(() => {
				if (exportController && !exportController.signal.aborted) {
					isExporting.value = false
				}
			}, 1000)
		} else {
			// Handle file export
			exportParams.format = exportForm.format
			const exportRoute =
				exportForm.export_type === "calendar"
					? "grp.org.hr.leaves.export.calendar"
					: "grp.org.hr.leaves.export"

			window.location.href = route(exportRoute, { ...route().params, ...exportParams })
			setTimeout(() => {
				if (exportController && !exportController.signal.aborted) {
					isExporting.value = false
				}
			}, 1500)
		}

		clearTimeout(exportTimeoutId)
	} catch (error) {
		clearTimeout(exportTimeoutId)
		isExporting.value = false
		exportError.value = true
		exportMessage.value = "Export failed. Please try again."
		console.error("Export failed:", error)
		notify({
			title: "Export Failed",
			text: "Unable to export calendar. Please try again.",
			type: "error",
		})
	}
}
</script>

<template>
	<Head :title="capitalize(title)" />

	<PageHeading :data="pageHead">
		<template #other>
			<Button type="tertiary" icon="fal fa-download" :label="ctrans('Export')" @click="openExportModal" />
		</template>
	</PageHeading>

	<div class="mt-5 bg-white shadow-sm rounded-lg p-4">
		<div class="sticky z-10 -mx-4 -mt-4 rounded-t-lg bg-white px-4 pt-4" :class="stickyTopClass">
		<div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
			<div class="flex flex-wrap items-center gap-2">
				<Button type="tertiary" size="s" icon="fal fa-chevron-left" :tooltip="ctrans('Previous')" @click="prevRange" />
				<h2
					aria-live="polite"
					:class="selectedView === 'week' ? 'min-w-[14rem] text-center text-lg font-semibold text-gray-800 tabular-nums' : 'sr-only'">
					{{ displayPeriodLabel }}
				</h2>
				<Select
					v-model="selectedMonth"
					:options="monthOptions"
					optionLabel="label"
					optionValue="value"
					:aria-label="ctrans('Month')"
					class="shrink-0"
					:class="[fieldFocusClass, selectedView === 'week' ? ['order-1 w-32', collapsibleFilterClass] : 'w-40']"
					:pt="selectedView === 'week' ? toolbarSelectPt : periodSelectPt"
					@change="updateFilter" />
				<Select
					v-model="selectedYear"
					:options="yearOptions"
					optionLabel="label"
					optionValue="value"
					:aria-label="ctrans('Year')"
					class="w-28 shrink-0"
					:class="[fieldFocusClass, selectedView === 'week' ? ['order-1', collapsibleFilterClass] : '']"
					:pt="selectedView === 'week' ? toolbarSelectPt : periodSelectPt"
					@change="updateFilter" />
				<Button type="tertiary" size="s" icon="fal fa-chevron-right" :tooltip="ctrans('Next')" @click="nextRange" />
			</div>

			<div class="flex flex-wrap items-center gap-2 lg:justify-end">
				<div class="relative max-lg:order-first lg:hidden">
					<Button type="tertiary" size="s" icon="fal fa-filter" :label="ctrans('Filters')" :aria-expanded="filtersOpen" @click="filtersOpen = !filtersOpen" />
					<span
						v-if="activeFilterCount"
						class="pointer-events-none absolute -right-1.5 -top-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-[--app-accent] px-1 text-[10px] font-semibold leading-none tabular-nums text-[--app-accent-text]">
						{{ activeFilterCount }}
					</span>
				</div>

				<IconField class="w-full sm:w-52" :class="collapsibleFilterClass">
					<InputIcon>
						<FontAwesomeIcon icon="fal fa-search" class="text-gray-400" fixed-width aria-hidden="true" />
					</InputIcon>
					<InputText
						v-model="searchQuery"
						:placeholder="ctrans('Search employees...')"
						:aria-label="ctrans('Search employees...')"
						fluid
						:class="toolbarInputClass"
						@update:modelValue="debouncedSearch" />
				</IconField>

				<div class="flex max-lg:order-first">
					<SegmentedToggle v-model="viewModel" :options="viewOptions" :ariaLabel="ctrans('View')" />
				</div>

				<Select
					v-model="selectedEmployeeId"
					:options="employeeOptions"
					optionLabel="label"
					optionValue="value"
					:placeholder="ctrans('All Employees')"
					:aria-label="ctrans('Employee')"
					filter
					showClear
					class="w-44"
					:class="[fieldFocusClass, collapsibleFilterClass]"
					:pt="toolbarSelectPt"
					@change="updateFilter" />

				<Select
					v-model="selectedType"
					:options="typeOptions"
					optionLabel="label"
					optionValue="value"
					:placeholder="ctrans('All Types')"
					:aria-label="ctrans('Leave Type')"
					showClear
					class="w-40"
					:class="[fieldFocusClass, collapsibleFilterClass]"
					:pt="toolbarSelectPt"
					@change="updateFilter" />

				<Select
					v-model="selectedDepartment"
					:options="parsedDepartmentOptions"
					optionLabel="label"
					optionValue="value"
					:placeholder="ctrans('All Departments')"
					:aria-label="ctrans('Team department')"
					showClear
					class="w-44"
					:class="[fieldFocusClass, collapsibleFilterClass]"
					:pt="toolbarSelectPt"
					@change="updateFilter" />

				<Select
					v-model="selectedSortBy"
					:options="sortOptions"
					optionLabel="label"
					optionValue="value"
					:aria-label="ctrans('Sort by')"
					class="w-44"
					:class="[fieldFocusClass, collapsibleFilterClass]"
					:pt="toolbarSelectPt"
					@change="updateFilter">
					<template #value="{ value }">
						<span class="text-gray-500">{{ ctrans("Sort by") }}:</span>
						{{ sortOptions.find((option) => option.value === value)?.label }}
					</template>
				</Select>
			</div>
		</div>

		<div class="mb-2 flex items-center gap-x-4 gap-y-1.5 whitespace-nowrap text-xs text-gray-600 max-lg:overflow-x-auto max-lg:[scrollbar-width:none] max-lg:[&::-webkit-scrollbar]:hidden max-lg:[&>*]:shrink-0 lg:mb-4 lg:flex-wrap">
			<span v-for="item in leaveLegend" :key="item.label" class="inline-flex items-center gap-1.5">
				<span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.color }" aria-hidden="true" />
				{{ item.label }}
			</span>
			<span v-if="hasPendingLeaves" class="inline-flex items-center gap-1.5">
				<span
					class="h-2.5 w-5 rounded-full"
					:style="{ backgroundColor: leaveColorFallback, backgroundImage: pendingStripes }"
					aria-hidden="true" />
				{{ ctrans("Pending approval") }}
			</span>
			<span class="inline-flex items-center gap-1.5">
				<span class="h-2.5 w-2.5 rounded-sm border border-red-200 bg-red-50" aria-hidden="true" />
				{{ ctrans("Public holiday") }}
			</span>
			<span class="inline-flex items-center gap-1.5">
				<span class="h-2.5 w-2.5 rounded-sm border border-gray-200 bg-gray-100" aria-hidden="true" />
				{{ ctrans("Weekend") }}
			</span>
		</div>

		<div ref="gridHeaderScroller" class="overflow-hidden">
			<div class="min-w-[56rem]">
				<div
					class="grid grid-cols-[8rem_minmax(0,1fr)] sm:grid-cols-[12rem_minmax(0,1fr)] border border-gray-200 rounded-t-lg">
					<div
						class="bg-gray-50 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 z-10 border-r border-gray-200 rounded-tl-lg">
						{{ ctrans("Employee") }}
					</div>
					<div class="grid grid-cols-7 bg-gray-50 rounded-tr-lg overflow-hidden">
						<div
							v-for="(day, index) in headerDays"
							:key="day.date"
							class="px-2 py-1 text-center border-r border-gray-200 last:border-r-0"
							:class="day.is_weekend ? 'bg-gray-200/70 text-gray-500' : 'text-gray-700'">
							<div class="text-xs font-semibold">
								{{ weekdayLabels[index] }}
							</div>
							<div v-if="selectedView === 'week'" class="text-[10px] text-gray-500 tabular-nums">
								{{ day.day_of_month }}
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		</div>

		<div class="relative z-0 overflow-x-auto" @scroll.passive="syncGridHeaderScroll">
			<div class="min-w-[56rem]">
				<div
					v-if="employeeLaneData.length === 0"
					class="border-x border-b border-gray-200 p-8 text-center text-gray-500 rounded-b-lg">
					{{ ctrans("No employees found.") }}
				</div>

				<div
					v-for="employee in employeeLaneData"
					:key="employee.id"
					class="grid grid-cols-[8rem_minmax(0,1fr)] sm:grid-cols-[12rem_minmax(0,1fr)] border-x border-b border-gray-200 bg-white">
					<div
						class="px-3 py-3 text-sm font-medium text-gray-900 border-r border-gray-200 sticky left-0 z-10 bg-white">
						<div class="flex items-center gap-2">
							<EmployeeAvatar :name="employee.name" :image="employee.avatar" size="sm" class="shrink-0" />
							<div class="min-w-0">
								<div class="font-medium text-gray-900 truncate">{{ employee.name }}</div>
								<div class="text-xs text-gray-500 truncate">
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
									class="relative border-r last:border-r-0"
									:class="getDayCellClass(day)">
									<div
										class="absolute top-1 right-1 text-[10px] tabular-nums"
										:class="selectedView === 'month' && !day.is_current_month ? 'text-gray-300' : 'text-gray-400'">
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
										class="h-6 px-2 text-left text-[10px] font-semibold hover:brightness-95 transition-all truncate focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent] focus-visible:ring-offset-1"
										:style="getLeaveSegmentStyle(segment)"
										:title="getLeaveTooltip(segment)"
										@click="openModal(segment.leave)">
										<span class="truncate block">{{
											segment.leave.code
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

	<Dialog
		v-model:visible="showModal"
		modal
		:draggable="false"
		:header="ctrans('Leave Details')"
		:style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }"
		@hide="selectedLeave = null">
		<div v-if="selectedLeave" class="space-y-4">
			<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
				<div>
					<div class="text-sm font-medium text-gray-500">{{ ctrans("Employee") }}</div>
					<div class="mt-1 text-sm text-gray-900">
						{{ selectedLeave.employee_name }}
					</div>
				</div>
				<div>
					<div class="text-sm font-medium text-gray-500">{{ ctrans("Type") }}</div>
					<div class="mt-1 flex items-center gap-1.5 text-sm text-gray-900">
						<span
							class="h-2.5 w-2.5 rounded-full"
							:style="{ backgroundColor: leaveColorHex(selectedLeave.color) }"
							aria-hidden="true" />
						{{ formatTypeLabel(selectedLeave.type_label) }}
					</div>
				</div>
				<div>
					<div class="text-sm font-medium text-gray-500">{{ ctrans("Start Date") }}</div>
					<div class="mt-1 text-sm text-gray-900 tabular-nums">{{ selectedLeave.start_date }}</div>
				</div>
				<div>
					<div class="text-sm font-medium text-gray-500">{{ ctrans("End Date") }}</div>
					<div class="mt-1 text-sm text-gray-900 tabular-nums">{{ selectedLeave.end_date }}</div>
				</div>
				<div>
					<div class="text-sm font-medium text-gray-500">{{ ctrans("Duration") }}</div>
					<div class="mt-1 text-sm text-gray-900">
						{{ selectedLeave.duration_days }} {{ ctrans("days") }}
					</div>
				</div>
				<div>
					<div class="text-sm font-medium text-gray-500">{{ ctrans("Status") }}</div>
					<div class="mt-1">
						<span
							class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
							:class="{
								'bg-green-100 text-green-800': selectedLeave.status === 'approved',
								'bg-amber-100 text-amber-800': selectedLeave.status === 'pending',
								'bg-red-100 text-red-800': selectedLeave.status === 'rejected',
							}">
							{{ capitalize(selectedLeave.status) }}
						</span>
					</div>
				</div>
			</div>

			<div v-if="selectedLeave.reason">
				<div class="text-sm font-medium text-gray-500">{{ ctrans("Reason") }}</div>
				<div class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded-md border border-gray-100">
					{{ selectedLeave.reason }}
				</div>
			</div>
		</div>

		<div class="mt-6 flex justify-end">
			<Button type="tertiary" :label="ctrans('Close')" @click="closeModal">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</div>
	</Dialog>

	<Modal :isOpen="isExportModalOpen" @onClose="closeExportModal" width="w-full max-w-lg">
		<div class="p-6">
			<h3 class="text-lg font-semibold text-gray-900 mb-4">
				{{ ctrans("Export Leave Reports") }}
			</h3>
			<p class="text-sm text-gray-600 mb-4">
				{{ ctrans("Select filters and export format for your leave report.") }}
			</p>
			<div v-if="exportError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
				<p class="text-sm text-red-800">{{ exportMessage }}</p>
			</div>

			<form @submit.prevent="submitExport" class="space-y-4">
				<div class="grid grid-cols-2 gap-4">
					<div>
						<label for="leave-export-from" class="block text-sm font-medium text-gray-700 mb-1">{{
							ctrans("From Date")
						}}</label>
						<DatePicker
							v-model="exportFrom"
							inputId="leave-export-from"
							dateFormat="d M yy"
							:maxDate="exportTo ?? undefined"
							:manualInput="false"
							showIcon
							showButtonBar
							iconDisplay="input"
							fluid
							:class="fieldFocusClass" />
					</div>
					<div>
						<label for="leave-export-to" class="block text-sm font-medium text-gray-700 mb-1">{{
							ctrans("To Date")
						}}</label>
						<DatePicker
							v-model="exportTo"
							inputId="leave-export-to"
							dateFormat="d M yy"
							:minDate="exportFrom ?? undefined"
							:manualInput="false"
							showIcon
							showButtonBar
							iconDisplay="input"
							fluid
							:class="fieldFocusClass" />
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							ctrans("Leave Type")
						}}</label>
						<Select
							v-model="exportForm.type"
							:options="typeOptions"
							optionLabel="label"
							optionValue="value"
							:placeholder="ctrans('All Types')"
							showClear
							class="w-full"
							:class="fieldFocusClass" />
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							ctrans("Status")
						}}</label>
						<Select
							v-model="exportForm.status"
							:options="parsedStatusOptions"
							optionLabel="label"
							optionValue="value"
							:placeholder="ctrans('All Statuses')"
							showClear
							class="w-full"
							:class="fieldFocusClass" />
					</div>
				</div>

				<div class="grid grid-cols-2 gap-4">
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							ctrans("Team department")
						}}</label>
						<Select
							v-model="exportForm.department"
							:options="parsedDepartmentOptions"
							optionLabel="label"
							optionValue="value"
							:placeholder="ctrans('All Departments')"
							showClear
							class="w-full"
							:class="fieldFocusClass" />
					</div>
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1">{{
							ctrans("Format")
						}}</label>
						<Select
							v-model="exportForm.format"
							:options="[
								{ label: 'Excel (XLSX)', value: 'xlsx' },
								{ label: 'CSV', value: 'csv' },
							]"
							optionLabel="label"
							optionValue="value"
							class="w-full"
							:class="fieldFocusClass" />
					</div>
				</div>

				<div class="flex items-center gap-2">
					<Checkbox
						v-model="exportForm.include_summary"
						inputId="leave-export-include-summary"
						binary
						:class="checkboxAccentClass" />
					<label for="leave-export-include-summary" class="cursor-pointer text-sm text-gray-700">
						{{ ctrans("Include Summary") }}
					</label>
				</div>

				<div class="flex items-center gap-2">
					<Checkbox
						v-model="exportForm.print_view"
						inputId="leave-export-print-view"
						binary
						:class="checkboxAccentClass" />
					<label for="leave-export-print-view" class="cursor-pointer text-sm text-gray-700">
						{{ ctrans("Print View") }}
					</label>
				</div>
			</form>
			<ExportModalActions
				:loading="isExporting"
				:export-icon="exportForm.format === 'xlsx' ? faFileExcel : faFileCsv"
				@cancel="closeExportModal"
				@export="submitExport" />
		</div>
	</Modal>
</template>
