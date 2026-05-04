<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, onMounted } from "vue"
import { trans } from "laravel-vue-i18n"

type LeaveItem = {
	id: number
	type: string
	type_label: string
	type_code: string
	type_color: string
	start_date: string
	end_date: string
	duration_days: number
	working_days: number
	reason?: string | null
	status: string
	status_label: string
}

type EmployeeLeaveRow = {
	id: number
	name: string
	department?: string | null
	job_title?: string | null
	leaves: LeaveItem[]
}

type HolidayItem = {
	name: string
	from: string
	to: string
}

type CalendarDay = {
	date: string
	day: number
	isWeekend: boolean
	isToday: boolean
}

type LeaveSegment = {
	id: string
	leave: LeaveItem
	startIndex: number
	endIndex: number
}

const props = defineProps<{
	title: string
	calendarData: EmployeeLeaveRow[]
	holidays: HolidayItem[]
	visibleRange: {
		start: string
		end: string
	}
	filters: {
		year: number
		month: number
	}
	organisation: {
		name: string
	}
	isDataOnly: boolean
}>()

const weekdayLabels = [
	trans("Su"),
	trans("Mo"),
	trans("Tu"),
	trans("We"),
	trans("Th"),
	trans("Fr"),
	trans("Sa"),
]
const generatedAt = new Date()

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

const visibleDays = computed<CalendarDay[]>(() => {
	const days: CalendarDay[] = []
	const cursor = parseDateKey(props.visibleRange.start)
	const end = parseDateKey(props.visibleRange.end)
	const today = toDateKey(new Date())

	while (cursor <= end) {
		const dateKey = toDateKey(cursor)
		days.push({
			date: dateKey,
			day: cursor.getDate(),
			isWeekend: cursor.getDay() === 0 || cursor.getDay() === 6,
			isToday: dateKey === today,
		})
		cursor.setDate(cursor.getDate() + 1)
	}

	return days
})

const dateIndexMap = computed(() => {
	return new Map(visibleDays.value.map((day, index) => [day.date, index]))
})

const gridTemplateColumns = computed(() => {
	return `160px repeat(${visibleDays.value.length}, minmax(0, 1fr))`
})

const monthLabel = computed(() => {
	return new Intl.DateTimeFormat("en-US", {
		month: "long",
		year: "numeric",
	}).format(
		parseDateKey(`${props.filters.year}-${String(props.filters.month).padStart(2, "0")}-01`)
	)
})

const holidayByDate = computed(() => {
	const holidayMap = new Map<string, string>()

	for (const holiday of props.holidays) {
		const cursor = parseDateKey(holiday.from)
		const end = parseDateKey(holiday.to)

		while (cursor <= end) {
			holidayMap.set(toDateKey(cursor), holiday.name)
			cursor.setDate(cursor.getDate() + 1)
		}
	}

	return holidayMap
})

const employeesWithLanes = computed(() => {
	return props.calendarData
		.filter((employee) => employee.leaves.length > 0)
		.map((employee) => {
			const segments = employee.leaves
				.map((leave) => {
					const clippedStart =
						leave.start_date < props.visibleRange.start
							? props.visibleRange.start
							: leave.start_date
					const clippedEnd =
						leave.end_date > props.visibleRange.end
							? props.visibleRange.end
							: leave.end_date
					const startIndex = dateIndexMap.value.get(clippedStart)
					const endIndex = dateIndexMap.value.get(clippedEnd)

					if (
						startIndex === undefined ||
						endIndex === undefined ||
						startIndex > endIndex
					) {
						return null
					}

					return {
						id: `${employee.id}-${leave.id}-${clippedStart}`,
						leave,
						startIndex,
						endIndex,
					} satisfies LeaveSegment
				})
				.filter((segment): segment is LeaveSegment => Boolean(segment))
				.sort((left, right) => {
					if (left.startIndex === right.startIndex) {
						return right.endIndex - left.endIndex
					}

					return left.startIndex - right.startIndex
				})

			const lanes: LeaveSegment[][] = []

			for (const segment of segments) {
				let placed = false

				for (const lane of lanes) {
					const hasCollision = lane.some((existing) => {
						return (
							existing.startIndex <= segment.endIndex &&
							segment.startIndex <= existing.endIndex
						)
					})

					if (!hasCollision) {
						lane.push(segment)
						placed = true
						break
					}
				}

				if (!placed) {
					lanes.push([segment])
				}
			}

			return {
				...employee,
				leaves: [...employee.leaves].sort((left, right) =>
					left.start_date.localeCompare(right.start_date)
				),
				lanes,
			}
		})
})

const formatShortDate = (date: string): string => {
	return parseDateKey(date).toLocaleDateString("en-US")
}

const formatGeneratedAt = (date: Date): string => {
	return date.toLocaleString("en-US")
}

const getWeekdayLabel = (date: string): string => {
	return weekdayLabels[parseDateKey(date).getDay()] || ""
}

const getHolidayLabel = (date: string): string => {
	const holidayName = holidayByDate.value.get(date)
	return holidayName ? holidayName.slice(0, 3).toUpperCase() : ""
}

const getEmployeeSubtitle = (employee: EmployeeLeaveRow): string => {
	if (employee.job_title && employee.department) {
		return `${employee.job_title} / ${employee.department}`
	}

	return employee.job_title || employee.department || trans("No role assigned")
}

const getRowHeight = (laneCount: number): string => {
	return `${Math.max(68, laneCount * 28 + 16)}px`
}

const getLeaveSegmentStyle = (segment: LeaveSegment, laneIndex: number): Record<string, string> => {
	const isPending = segment.leave.status === "pending"

	return {
		gridColumn: `${segment.startIndex + 2} / ${segment.endIndex + 3}`,
		gridRow: "1",
		marginTop: `${laneIndex * 28 + 8}px`,
		backgroundColor: segment.leave.type_color,
		border: isPending ? "1px dashed rgba(255, 255, 255, 0.9)" : "none",
	}
}

const getStatusClass = (status: string): string => {
	if (status === "approved") {
		return "bg-green-50 text-green-700"
	}

	if (status === "pending") {
		return "bg-orange-50 text-orange-700"
	}

	if (status === "rejected") {
		return "bg-red-50 text-red-700"
	}

	return "bg-gray-50 text-gray-600"
}

onMounted(() => {
	window.print()
})
</script>

<template>
	<Head :title="title" />

	<!-- Data-only version -->
	<div v-if="isDataOnly" class="data-only-print">
		<section class="leave-data-section">
			<div
				v-for="employee in employeesWithLanes"
				:key="`details-${employee.id}`"
				class="employee-leave-block">
				<h3 class="employee-name">{{ employee.name }}</h3>
				<table class="leave-table">
					<thead>
						<tr>
							<th class="table-header">{{ trans("Type") }}</th>
							<th class="table-header">{{ trans("Start") }}</th>
							<th class="table-header">{{ trans("End") }}</th>
							<th class="table-header">{{ trans("Days") }}</th>
							<th class="table-header">{{ trans("Status") }}</th>
							<th class="table-header">{{ trans("Reason") }}</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="(leave, index) in employee.leaves"
							:key="leave.id"
							:class="{
								'row-even': index % 2 === 0,
								'row-odd': index % 2 !== 0,
							}">
							<td class="table-cell">
								{{ leave.type_label }}
							</td>
							<td class="table-cell">
								{{ leave.start_date }}
							</td>
							<td class="table-cell">
								{{ leave.end_date }}
							</td>
							<td class="table-cell">
								{{ leave.working_days }}
							</td>
							<td class="table-cell">
								{{ leave.status_label }}
							</td>
							<td class="table-cell">
								{{ leave.reason || "-" }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</div>

	<!-- Full version -->
	<div v-else class="min-h-screen bg-[#F3F4F6] print:bg-white">
		<div class="mx-auto max-w-[1400px] px-4 py-6 print:max-w-none print:px-0 print:py-0">
			<div
				class="rounded-2xl bg-white p-6 shadow-sm print:rounded-none print:p-0 print:shadow-none">
				<header class="border-b border-[#D6D6D6] pb-5">
					<p class="text-[20px] font-bold text-[#111111]">
						{{ organisation.name }}
					</p>
					<h1 class="mt-1 text-[16px] font-semibold text-[#1F2937]">
						{{ trans("Leave Calendar") }}
					</h1>
					<p class="mt-2 text-[13px] text-[#555555]">{{ monthLabel }}</p>
					<p class="mt-1 text-[13px] text-[#555555]">
						{{ trans("From") }}: {{ formatShortDate(visibleRange.start) }} -
						{{ trans("To") }}: {{ formatShortDate(visibleRange.end) }}
					</p>
				</header>

				<section class="mt-6">
					<div class="overflow-x-auto print:overflow-visible">
						<div class="min-w-[900px] print:min-w-0">
							<div
								class="calendar-grid border border-[#D9D9D9] border-b-0"
								:style="{ gridTemplateColumns }">
								<div
									class="sticky left-0 z-20 border-b border-r border-[#D9D9D9] bg-[#F0F0F0] px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.04em] text-[#374151] print:static">
									{{ trans("Employee") }}
								</div>
								<div
									v-for="day in visibleDays"
									:key="day.date"
									class="border-b border-r border-[#D9D9D9] px-1 py-2 text-center"
									:class="{
										'bg-[#F5F5F5]': day.isWeekend,
										'bg-[#EAF3FF] ring-1 ring-inset ring-[#BFDBFE] print:bg-transparent print:ring-0':
											day.isToday && !day.isWeekend,
										'bg-[#ECECEC] print:bg-[#F5F5F5]':
											day.isToday && day.isWeekend,
									}">
									<div
										class="text-[11px] font-semibold leading-none text-[#1F2937]">
										{{ day.day }}
									</div>
									<div class="mt-1 text-[11px] leading-none text-[#6B7280]">
										{{ getWeekdayLabel(day.date) }}
									</div>
									<div
										v-if="holidayByDate.has(day.date)"
										class="mt-1 text-[9px] font-semibold leading-none text-[#2563EB]">
										{{ getHolidayLabel(day.date) }}
									</div>
								</div>
							</div>

							<div
								v-for="employee in employeesWithLanes"
								:key="employee.id"
								class="calendar-grid border border-t-0 border-[#D9D9D9]"
								:style="{
									gridTemplateColumns,
									minHeight: getRowHeight(employee.lanes.length || 1),
								}">
								<div
									class="sticky left-0 z-10 flex flex-col justify-center border-r border-[#D9D9D9] bg-white px-4 py-3 print:static">
									<div class="text-[13px] font-medium text-[#111827]">
										{{ employee.name }}
									</div>
									<div class="mt-1 text-[11px] text-[#888888]">
										{{ getEmployeeSubtitle(employee) }}
									</div>
								</div>
								<div
									v-for="day in visibleDays"
									:key="`${employee.id}-${day.date}`"
									class="border-r border-[#E5E7EB]"
									:class="{
										'bg-[#F5F5F5]': day.isWeekend,
										'bg-[#F9FBFF] print:bg-transparent':
											day.isToday && !day.isWeekend,
									}"></div>
								<template
									v-for="(lane, laneIndex) in employee.lanes"
									:key="`${employee.id}-lane-${laneIndex}`">
									<div
										v-for="segment in lane"
										:key="segment.id"
										class="relative z-10 flex h-[22px] items-center justify-center overflow-hidden rounded-full px-1 text-[10px] font-semibold leading-none text-white shadow-sm"
										:style="getLeaveSegmentStyle(segment, laneIndex)">
										<span class="truncate">{{ segment.leave.type_code }}</span>
									</div>
								</template>
							</div>
						</div>
					</div>
				</section>

				<section class="mt-8 border-t border-[#D6D6D6] pt-5">
					<h2 class="text-[16px] font-semibold text-[#1F2937]">
						{{ trans("Leave Details") }}
					</h2>

					<div
						v-for="employee in employeesWithLanes"
						:key="`details-${employee.id}`"
						class="employee-leave-block mt-5">
						<h3 class="text-[13px] font-semibold text-[#111827]">
							{{ employee.name }}
						</h3>
						<table
							class="mt-3 w-full table-fixed border-collapse border border-[#D9D9D9]">
							<thead>
								<tr class="bg-[#F0F0F0]">
									<th
										class="border border-[#D9D9D9] px-3 py-2 text-left text-[12px] font-semibold">
										{{ trans("Type") }}
									</th>
									<th
										class="border border-[#D9D9D9] px-3 py-2 text-left text-[12px] font-semibold">
										{{ trans("Start") }}
									</th>
									<th
										class="border border-[#D9D9D9] px-3 py-2 text-left text-[12px] font-semibold">
										{{ trans("End") }}
									</th>
									<th
										class="border border-[#D9D9D9] px-3 py-2 text-left text-[12px] font-semibold">
										{{ trans("Days") }}
									</th>
									<th
										class="border border-[#D9D9D9] px-3 py-2 text-left text-[12px] font-semibold">
										{{ trans("Status") }}
									</th>
									<th
										class="border border-[#D9D9D9] px-3 py-2 text-left text-[12px] font-semibold">
										{{ trans("Reason") }}
									</th>
								</tr>
							</thead>
							<tbody>
								<tr
									v-for="(leave, index) in employee.leaves"
									:key="leave.id"
									:class="{
										'bg-[#FAFAFA]': index % 2 === 0,
										'bg-white': index % 2 !== 0,
									}">
									<td
										class="border border-[#D9D9D9] px-3 py-2 text-[12px] text-[#111827]">
										<div class="flex items-center gap-2">
											<span
												class="h-2.5 w-2.5 rounded-full"
												:style="{
													backgroundColor: leave.type_color,
												}"></span>
											<span>{{ leave.type_label }}</span>
										</div>
									</td>
									<td
										class="border border-[#D9D9D9] px-3 py-2 text-[12px] text-[#111827]">
										{{ leave.start_date }}
									</td>
									<td
										class="border border-[#D9D9D9] px-3 py-2 text-[12px] text-[#111827]">
										{{ leave.end_date }}
									</td>
									<td
										class="border border-[#D9D9D9] px-3 py-2 text-[12px] text-[#111827]">
										{{ leave.working_days }}
									</td>
									<td class="border border-[#D9D9D9] px-3 py-2 text-[12px]">
										<span
											class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
											:class="getStatusClass(leave.status)">
											{{ leave.status_label }}
										</span>
									</td>
									<td
										class="border border-[#D9D9D9] px-3 py-2 text-[12px] text-[#111827]">
										{{ leave.reason || "-" }}
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</section>

				<footer
					class="mt-8 border-t border-[#D6D6D6] pt-4 text-left text-[11px] text-[#AAAAAA]">
					{{ trans("Generated on") }}: {{ formatGeneratedAt(generatedAt) }}
				</footer>
			</div>
		</div>
	</div>
</template>

<style scoped>
.calendar-grid {
	display: grid;
	position: relative;
}

/* Data-only styles */
.data-only-print {
	@apply print:block;
}

.leave-data-section {
	@apply print:p-0 print:m-0;
}

.employee-leave-block {
	@apply print:mb-6;
	page-break-inside: avoid;
	break-inside: avoid;
}

.employee-name {
	@apply text-[14px] font-bold mb-3 text-[#000000] print:text-[12px];
}

.leave-table {
	@apply w-full table-fixed border-collapse border border-[#000000] print:border-collapse print:border;
	width: 100%;
}

.table-header {
	@apply border border-[#000000] px-2 py-2 text-left text-[12px] font-semibold bg-[#f5f5f5] print:border print:px-2 print:py-2 print:text-[10px] print:bg-transparent print:font-semibold;
}

.table-cell {
	@apply border border-[#000000] px-2 py-2 text-[11px] text-[#000000] print:border print:px-2 print:py-2 print:text-[9px];
}

.row-even {
	@apply bg-[#f9f9f9] print:bg-transparent;
}

.row-odd {
	@apply bg-white;
}

@media print {
	/* Full print styles */
	@page {
		size: A4 landscape;
		margin: 15mm;
	}

	* {
		-webkit-print-color-adjust: exact;
		print-color-adjust: exact;
	}

	.employee-leave-block {
		page-break-inside: avoid;
		break-inside: avoid;
	}

	.no-print {
		display: none;
	}

	/* Data-only specific print styles */
	.data-only-print .employee-leave-block {
		margin-bottom: 20px;
	}

	.data-only-print .leave-table {
		border-collapse: collapse;
		width: 100%;
	}

	.data-only-print .table-header,
	.data-only-print .table-cell {
		border: 1px solid #000;
		padding: 4px 6px;
		font-size: 9pt;
		text-align: left;
		vertical-align: top;
	}

	.data-only-print .table-header {
		font-weight: 600;
		background-color: transparent;
	}

	.data-only-print .employee-name {
		font-size: 12pt;
		font-weight: bold;
		margin-bottom: 8px;
		color: #000;
	}

	/* Ensure no background colors in data-only mode */
	.data-only-print .row-even,
	.data-only-print .row-odd {
		background-color: transparent !important;
	}
}

/* Original full print styles for backward compatibility */
@media not print {
	.calendar-grid {
		display: grid;
		position: relative;
	}
}
</style>
