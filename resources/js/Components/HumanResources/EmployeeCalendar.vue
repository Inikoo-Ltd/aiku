<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChevronLeft, faChevronRight, faDownload } from '@fal'

library.add(faChevronLeft, faChevronRight, faDownload)

type EmployeeCalendarHoliday = {
	date: string
	label: string
}

type EmployeeHolidayRange = {
	from: string
	to: string
	label: string
}

type EmployeeCalendarData = {
	year?: number
	month?: number | null
	holidays?: EmployeeCalendarHoliday[]
	holidayRanges?: EmployeeHolidayRange[]
}

const props = defineProps<{
	calendar?: EmployeeCalendarData
}>()

const calendarCurrentYear = new Date().getFullYear()

const calendarDisplayYear = computed(() => props.calendar?.year ?? calendarCurrentYear)

const calendarInitialMonth =
	props.calendar?.month && props.calendar.month >= 1 && props.calendar.month <= 12
		? String(props.calendar.month)
		: ""

const calendarFilterYear = ref<string>(String(calendarDisplayYear.value))
const calendarFilterMonth = ref<string>(calendarInitialMonth)

const calendarYearOptions = computed(() => {
	const baseYear = calendarCurrentYear

	return Array.from({ length: 7 }, (_, index) => {
		const year = baseYear - 3 + index

		return {
			value: String(year),
			label: String(year),
		}
	})
})

const calendarMonthOptions = [
	{ value: "1", label: trans("January") },
	{ value: "2", label: trans("February") },
	{ value: "3", label: trans("March") },
	{ value: "4", label: trans("April") },
	{ value: "5", label: trans("May") },
	{ value: "6", label: trans("June") },
	{ value: "7", label: trans("July") },
	{ value: "8", label: trans("August") },
	{ value: "9", label: trans("September") },
	{ value: "10", label: trans("October") },
	{ value: "11", label: trans("November") },
	{ value: "12", label: trans("December") },
]

const calendarHolidaysMap = computed<Record<string, EmployeeCalendarHoliday>>(() => {
	const map: Record<string, EmployeeCalendarHoliday> = {}

	if (props.calendar?.holidays) {
		for (const holiday of props.calendar.holidays) {
			map[holiday.date] = holiday
		}
	}

	return map
})

const calendarMonthNames = [
	trans("January"),
	trans("February"),
	trans("March"),
	trans("April"),
	trans("May"),
	trans("June"),
	trans("July"),
	trans("August"),
	trans("September"),
	trans("October"),
	trans("November"),
	trans("December"),
]

type EmployeeCalendarMonth = {
	month: number
	name: string
	weeks: {
		date: string
		dayOfMonth: number | null
		isCurrentMonth: boolean
		isHoliday: boolean
		holidayLabel: string | null
	}[][]
}

const calendarMonths = computed<EmployeeCalendarMonth[]>(() => {
	const months: EmployeeCalendarMonth[] = []
	const year = calendarDisplayYear.value

	for (let month = 0; month < 12; month += 1) {
		const firstDay = new Date(year, month, 1)
		const daysInMonth = new Date(year, month + 1, 0).getDate()

		const monthLabel = `${calendarMonthNames[month]} ${year}`

		const weeks: EmployeeCalendarMonth["weeks"] = []
		let week: EmployeeCalendarMonth["weeks"][number] = []

		const firstWeekdayIndex = (firstDay.getDay() + 6) % 7

		for (let i = 0; i < firstWeekdayIndex; i += 1) {
			week.push({
				date: "",
				dayOfMonth: null,
				isCurrentMonth: false,
				isHoliday: false,
				holidayLabel: null,
			})
		}

		for (let day = 1; day <= daysInMonth; day += 1) {
			const monthNumber = month + 1
			const dateString = [
				year.toString(),
				monthNumber.toString().padStart(2, "0"),
				day.toString().padStart(2, "0"),
			].join("-")

			const holiday = calendarHolidaysMap.value[dateString]

			week.push({
				date: dateString,
				dayOfMonth: day,
				isCurrentMonth: true,
				isHoliday: !!holiday,
				holidayLabel: holiday ? holiday.label : null,
			})

			if (week.length === 7) {
				weeks.push(week)
				week = []
			}
		}

		if (week.length > 0) {
			while (week.length < 7) {
				week.push({
					date: "",
					dayOfMonth: null,
					isCurrentMonth: false,
					isHoliday: false,
					holidayLabel: null,
				})
			}
			weeks.push(week)
		}

		months.push({
			month,
			name: monthLabel,
			weeks,
		})
	}

	return months
})

const calendarVisibleMonths = computed<EmployeeCalendarMonth[]>(() => {
	if (!calendarFilterMonth.value) {
		return calendarMonths.value
	}

	const monthIndex = Number(calendarFilterMonth.value) - 1

	return calendarMonths.value.filter((month) => month.month === monthIndex)
})

const calendarHolidaySummariesByMonth = computed<
	Record<number, { fromDay: number; toDay: number; label: string }[]>
>(() => {
	const result: Record<number, { fromDay: number; toDay: number; label: string }[]> = {}

	if (!props.calendar?.holidayRanges || props.calendar.holidayRanges.length === 0) {
		return result
	}

	const year = calendarDisplayYear.value

	for (const range of props.calendar.holidayRanges) {
		const fromDate = new Date(range.from)
		const toDate = new Date(range.to)

		if (fromDate.getFullYear() !== year && toDate.getFullYear() !== year) {
			continue
		}

		const startMonth = fromDate.getMonth()
		const endMonth = toDate.getMonth()

		if (startMonth !== endMonth || fromDate.getFullYear() !== toDate.getFullYear()) {
			continue
		}

		const monthIndex = startMonth

		if (!result[monthIndex]) {
			result[monthIndex] = []
		}

		const fromDay = fromDate.getDate()
		const toDay = toDate.getDate()

		result[monthIndex].push({
			fromDay,
			toDay,
			label: range.label,
		})
	}

	Object.keys(result).forEach((key) => {
		const index = Number(key)
		result[index] = result[index].sort((a, b) => a.fromDay - b.fromDay)
	})

	return result
})

const calendarWeekdayLabels = [
	trans("Mo"),
	trans("Tu"),
	trans("We"),
	trans("Th"),
	trans("Fr"),
	trans("Sa"),
	trans("Su"),
]

const isCalendarWeekendColumn = (index: number): boolean => index === 5 || index === 6

const applyCalendarFilters = () => {
	const params: Record<string, unknown> = {
		...route().params,
		tab: "calendar",
		year: Number(calendarFilterYear.value),
	}

	if (calendarFilterMonth.value) {
		params.month = Number(calendarFilterMonth.value)
	}

	router.reload({
		data: params,
		only: ["calendar", "tabs"],
		preserveScroll: true,
		preserveState: true,
	})
}

const resetCalendarFilters = () => {
	calendarFilterYear.value = String(calendarDisplayYear.value)
	calendarFilterMonth.value = ""

	applyCalendarFilters()
}

const goCalendarPrevious = () => {
	if (calendarFilterMonth.value) {
		let year = Number(calendarFilterYear.value || calendarDisplayYear.value)
		let month = Number(calendarFilterMonth.value)

		month -= 1

		if (month < 1) {
			month = 12
			year -= 1
		}

		calendarFilterYear.value = String(year)
		calendarFilterMonth.value = String(month)
	} else {
		let year = Number(calendarFilterYear.value || calendarDisplayYear.value)
		year -= 1
		calendarFilterYear.value = String(year)
	}

	applyCalendarFilters()
}

const goCalendarNext = () => {
	if (calendarFilterMonth.value) {
		let year = Number(calendarFilterYear.value || calendarDisplayYear.value)
		let month = Number(calendarFilterMonth.value)

		month += 1

		if (month > 12) {
			month = 1
			year += 1
		}

		calendarFilterYear.value = String(year)
		calendarFilterMonth.value = String(month)
	} else {
		let year = Number(calendarFilterYear.value || calendarDisplayYear.value)
		year += 1
		calendarFilterYear.value = String(year)
	}

	applyCalendarFilters()
}
</script>

<template>
	<div class="px-6 pb-10 pt-5">
		<div class="mt-5 bg-white shadow-sm rounded-lg p-4">
			<div class="flex flex-col sm:flex-row gap-4 items-center justify-between mb-6">
				<div class="flex gap-2 items-center">
					<Button
						type="secondary"
						:icon="faChevronLeft"
						size="sm"
						@click="goCalendarPrevious" />

					<h2 class="text-xl font-bold text-gray-800 w-48 text-center">
						<template v-if="calendarFilterMonth">
							{{ calendarMonthNames[Number(calendarFilterMonth) - 1] }} {{ calendarFilterYear }}
						</template>
						<template v-else>
							{{ trans("Year") }} {{ calendarFilterYear }}
						</template>
					</h2>

					<Button
						type="secondary"
						:icon="faChevronRight"
						size="sm"
						@click="goCalendarNext" />
				</div>

				<div class="flex gap-2 items-center flex-wrap">
					<select
						v-model="calendarFilterYear"
						@change="applyCalendarFilters"
						class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
						<option
							v-for="option in calendarYearOptions"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>

					<select
						v-model="calendarFilterMonth"
						@change="applyCalendarFilters"
						class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
						<option value="">
							{{ trans("All months") }}
						</option>
						<option
							v-for="option in calendarMonthOptions"
							:key="option.value"
							:value="option.value">
							{{ option.label }}
						</option>
					</select>

					<Button
						type="secondary"
						size="sm"
						:label="trans('Reset')"
						@click="resetCalendarFilters" />
				</div>
			</div>

			<div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
				<div
					v-for="month in calendarVisibleMonths"
					:key="month.month"
					class="space-y-3">
					<div class="text-sm font-semibold text-gray-800">
						{{ month.name }}
					</div>

					<div class="grid grid-cols-7 text-[11px] text-gray-400">
						<div
							v-for="(label, labelIndex) in calendarWeekdayLabels"
							:key="label"
							class="py-1 text-center"
							:class="isCalendarWeekendColumn(labelIndex) ? 'text-gray-400 bg-gray-50' : ''">
							{{ label }}
						</div>
					</div>

					<div class="flex flex-col gap-1 rounded-lg bg-white p-2 shadow-sm ring-1 ring-gray-100">
						<div
							v-for="(week, weekIndex) in month.weeks"
							:key="weekIndex"
							class="grid grid-cols-7 gap-1">
							<div
								v-for="(day, dayIndex) in week"
								:key="dayIndex"
								class="h-7 text-[11px]">
								<div
									v-if="day.isCurrentMonth && day.dayOfMonth"
									class="flex h-full w-full items-center justify-center rounded"
									:class="
										day.isHoliday
											? 'bg-red-100 text-red-700 hover:bg-red-200 cursor-pointer'
											: isCalendarWeekendColumn(dayIndex)
												? 'bg-gray-50 text-gray-500 hover:bg-gray-100'
												: 'text-gray-700 hover:bg-gray-100'
									">
									<span class="leading-none">
										{{ day.dayOfMonth }}
									</span>
								</div>
							</div>
						</div>
					</div>

					<div
						v-if="
							calendarHolidaySummariesByMonth[month.month] &&
							calendarHolidaySummariesByMonth[month.month].length
						"
						class="mt-2 border-t border-gray-200 pt-2 text-[11px]">
						<div
							v-for="summary in calendarHolidaySummariesByMonth[month.month]"
							:key="`${summary.fromDay}-${summary.toDay}-${summary.label}`"
							class="flex gap-1 text-red-600">
							<span class="font-semibold">
								<span v-if="summary.fromDay === summary.toDay">
									{{ summary.fromDay }}
								</span>
								<span v-else>
									{{ summary.fromDay }}-{{ summary.toDay }}
								</span>
								:
							</span>
							<span>
								{{ summary.label }}
							</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

