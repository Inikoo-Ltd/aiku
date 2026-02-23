<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChevronLeft, faChevronRight } from '@fal'

library.add(faChevronLeft, faChevronRight)

type CalendarHoliday = {
    date: string
    label: string
}

type CalendarDay = {
    date: string
    dayOfMonth: number | null
    isCurrentMonth: boolean
    isHoliday: boolean
    holidayLabel: string | null
}

type CalendarMonth = {
    month: number
    name: string
    weeks: CalendarDay[][]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    year?: number
    month?: number | null
    holidays?: CalendarHoliday[]
    tabs?: unknown
}>()

const currentYear = new Date().getFullYear()

const displayYear = computed(() => props.year ?? currentYear)

const initialMonth = props.month && props.month >= 1 && props.month <= 12 ? String(props.month) : ''

const filterYear = ref<string>(String(displayYear.value))
const filterMonth = ref<string>(initialMonth)

const yearOptions = computed(() => {
    const baseYear = currentYear

    return Array.from({ length: 7 }, (_, index) => {
        const year = baseYear - 3 + index

        return {
            value: String(year),
            label: String(year),
        }
    })
})

const monthOptions = [
    { value: '1', label: trans('January') },
    { value: '2', label: trans('February') },
    { value: '3', label: trans('March') },
    { value: '4', label: trans('April') },
    { value: '5', label: trans('May') },
    { value: '6', label: trans('June') },
    { value: '7', label: trans('July') },
    { value: '8', label: trans('August') },
    { value: '9', label: trans('September') },
    { value: '10', label: trans('October') },
    { value: '11', label: trans('November') },
    { value: '12', label: trans('December') },
]

const holidaysMap = computed<Record<string, CalendarHoliday>>(() => {
    const map: Record<string, CalendarHoliday> = {}

    if (props.holidays) {
        for (const holiday of props.holidays) {
            map[holiday.date] = holiday
        }
    }

    return map
})

const monthNames = [
    trans('January'),
    trans('February'),
    trans('March'),
    trans('April'),
    trans('May'),
    trans('June'),
    trans('July'),
    trans('August'),
    trans('September'),
    trans('October'),
    trans('November'),
    trans('December'),
]

const calendarMonths = computed<CalendarMonth[]>(() => {
    const months: CalendarMonth[] = []
    const year = displayYear.value

    for (let month = 0; month < 12; month += 1) {
        const firstDay = new Date(year, month, 1)
        const daysInMonth = new Date(year, month + 1, 0).getDate()

        const monthLabel = `${monthNames[month]} ${year}`

        const weeks: CalendarDay[][] = []
        let week: CalendarDay[] = []

        const firstWeekdayIndex = (firstDay.getDay() + 6) % 7

        for (let i = 0; i < firstWeekdayIndex; i += 1) {
            week.push({
                date: '',
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
                monthNumber.toString().padStart(2, '0'),
                day.toString().padStart(2, '0'),
            ].join('-')

            const holiday = holidaysMap.value[dateString]

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
                    date: '',
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

const visibleMonths = computed<CalendarMonth[]>(() => {
    if (!filterMonth.value) {
        return calendarMonths.value
    }

    const monthIndex = Number(filterMonth.value) - 1

    return calendarMonths.value.filter((month) => month.month === monthIndex)
})

const weekdayLabels = [
    trans('Mo'),
    trans('Tu'),
    trans('We'),
    trans('Th'),
    trans('Fr'),
    trans('Sa'),
    trans('Su'),
]

const applyFilters = () => {
    const params: Record<string, unknown> = {
        ...route().params,
        year: Number(filterYear.value),
    }

    if (filterMonth.value) {
        params.month = Number(filterMonth.value)
    }

    router.get(route('grp.org.hr.calendars.index', params), {}, {
        preserveScroll: true,
        preserveState: true,
    })
}

const resetFilters = () => {
    filterYear.value = String(displayYear.value)
    filterMonth.value = ''

    applyFilters()
}

const goPrevious = () => {
    if (filterMonth.value) {
        let year = Number(filterYear.value || displayYear.value)
        let month = Number(filterMonth.value)

        month -= 1

        if (month < 1) {
            month = 12
            year -= 1
        }

        filterYear.value = String(year)
        filterMonth.value = String(month)
    } else {
        let year = Number(filterYear.value || displayYear.value)
        year -= 1
        filterYear.value = String(year)
    }

    applyFilters()
}

const goNext = () => {
    if (filterMonth.value) {
        let year = Number(filterYear.value || displayYear.value)
        let month = Number(filterMonth.value)

        month += 1

        if (month > 12) {
            month = 1
            year += 1
        }

        filterYear.value = String(year)
        filterMonth.value = String(month)
    } else {
        let year = Number(filterYear.value || displayYear.value)
        year += 1
        filterYear.value = String(year)
    }

    applyFilters()
}
</script>

<template layout="Grp">
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-6 pb-10 pt-5">
        <div class="mt-5 bg-white shadow-sm rounded-lg p-4">
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between mb-6">
                <div class="flex gap-2 items-center">
                    <Button
                        type="secondary"
                        :icon="faChevronLeft"
                        size="sm"
                        @click="goPrevious"
                    />

                    <h2 class="text-xl font-bold text-gray-800 w-48 text-center">
                        <template v-if="filterMonth">
                            {{ monthNames[Number(filterMonth) - 1] }} {{ filterYear }}
                        </template>
                        <template v-else>
                            {{ trans('Year') }} {{ filterYear }}
                        </template>
                    </h2>

                    <Button
                        type="secondary"
                        :icon="faChevronRight"
                        size="sm"
                        @click="goNext"
                    />
                </div>

                <div class="flex gap-2 items-center flex-wrap">
                    <select
                        v-model="filterYear"
                        @change="applyFilters"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option
                            v-for="option in yearOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <select
                        v-model="filterMonth"
                        @change="applyFilters"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    >
                        <option value="">
                            {{ trans('All months') }}
                        </option>
                        <option
                            v-for="option in monthOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="month in visibleMonths"
                    :key="month.month"
                    class="space-y-2"
                >
                    <div class="text-sm font-semibold text-gray-800">
                        {{ month.name }}
                    </div>

                    <div class="grid grid-cols-7 text-[11px] text-gray-400">
                        <div
                            v-for="label in weekdayLabels"
                            :key="label"
                            class="py-1 text-center"
                        >
                            {{ label }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-1 rounded-lg bg-white p-2 shadow-sm ring-1 ring-gray-100">
                        <div
                            v-for="(week, weekIndex) in month.weeks"
                            :key="weekIndex"
                            class="grid grid-cols-7 gap-1"
                        >
                            <div
                                v-for="(day, dayIndex) in week"
                                :key="dayIndex"
                                class="h-7 text-[11px]"
                            >
                                <div
                                    v-if="day.isCurrentMonth && day.dayOfMonth"
                                    class="flex h-full w-full items-center justify-center rounded"
                                    :class="day.isHoliday ? 'bg-red-100 text-red-700 hover:bg-red-200 cursor-pointer' : 'text-gray-700 hover:bg-gray-100'"
                                    v-tooltip="day.isHoliday && day.holidayLabel ? day.holidayLabel : undefined"
                                >
                                    <span class="leading-none">
                                        {{ day.dayOfMonth }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
