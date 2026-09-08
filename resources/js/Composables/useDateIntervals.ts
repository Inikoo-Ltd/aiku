import { computed, type ComputedRef } from 'vue'
import { trans } from 'laravel-vue-i18n'

export interface DateInterval {
    value: string
    label: string
    getDateRange: () => [Date, Date]
}

export const useDateIntervals = (): ComputedRef<DateInterval[]> => computed<DateInterval[]>(() => [
    {
        value: 'tdy',
        label: trans('Today'),
        getDateRange: () => {
            const now = new Date()
            return [new Date(now.setHours(0, 0, 0, 0)), new Date(new Date().setHours(23, 59, 59, 999))]
        }
    },
    {
        value: 'ld',
        label: trans('Yesterday'),
        getDateRange: () => {
            const yesterday = new Date()
            yesterday.setDate(yesterday.getDate() - 1)
            return [new Date(yesterday.setHours(0, 0, 0, 0)), new Date(yesterday.setHours(23, 59, 59, 999))]
        }
    },
    {
        value: 'mtd',
        label: trans('Month to Date'),
        getDateRange: () => {
            const now = new Date()
            const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
            return [new Date(startOfMonth.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'lm',
        label: trans('Last Month'),
        getDateRange: () => {
            const now = new Date()
            const startOfLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1)
            const endOfLastMonth = new Date(now.getFullYear(), now.getMonth(), 0)
            return [new Date(startOfLastMonth.setHours(0, 0, 0, 0)), new Date(endOfLastMonth.setHours(23, 59, 59, 999))]
        }
    },
    {
        value: '3d',
        label: trans('3 Days'),
        getDateRange: () => {
            const threeDaysAgo = new Date()
            threeDaysAgo.setDate(threeDaysAgo.getDate() - 3)
            return [new Date(threeDaysAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: '1w',
        label: trans('1 Week'),
        getDateRange: () => {
            const oneWeekAgo = new Date()
            oneWeekAgo.setDate(oneWeekAgo.getDate() - 7)
            return [new Date(oneWeekAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'wtd',
        label: trans('Week to Date'),
        getDateRange: () => {
            const now = new Date()
            const startOfWeek = new Date(now)
            const day = startOfWeek.getDay()
            const diff = startOfWeek.getDate() - day + (day === 0 ? -6 : 1)
            startOfWeek.setDate(diff)
            return [new Date(startOfWeek.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'lw',
        label: trans('Last Week'),
        getDateRange: () => {
            const now = new Date()
            const startOfLastWeek = new Date(now)
            const day = startOfLastWeek.getDay()
            const diff = startOfLastWeek.getDate() - day - 6
            startOfLastWeek.setDate(diff)
            const endOfLastWeek = new Date(startOfLastWeek)
            endOfLastWeek.setDate(endOfLastWeek.getDate() + 6)
            return [new Date(startOfLastWeek.setHours(0, 0, 0, 0)), new Date(endOfLastWeek.setHours(23, 59, 59, 999))]
        }
    },
    {
        value: '1m',
        label: trans('1 Month'),
        getDateRange: () => {
            const oneMonthAgo = new Date()
            oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1)
            return [new Date(oneMonthAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: '1q',
        label: trans('1 Quarter'),
        getDateRange: () => {
            const threeMonthsAgo = new Date()
            threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3)
            return [new Date(threeMonthsAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'qtd',
        label: trans('Quarter to Date'),
        getDateRange: () => {
            const now = new Date()
            const quarter = Math.floor(now.getMonth() / 3)
            const startOfQuarter = new Date(now.getFullYear(), quarter * 3, 1)
            return [new Date(startOfQuarter.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: '1y',
        label: trans('1 Year'),
        getDateRange: () => {
            const oneYearAgo = new Date()
            oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1)
            return [new Date(oneYearAgo.setHours(0, 0, 0, 0)), new Date()]
        }
    },
    {
        value: 'ytd',
        label: trans('Year to Date'),
        getDateRange: () => {
            const now = new Date()
            const startOfYear = new Date(now.getFullYear(), 0, 1)
            return [new Date(startOfYear.setHours(0, 0, 0, 0)), new Date()]
        }
    }
])
