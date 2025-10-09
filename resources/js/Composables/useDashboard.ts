/**
 * Author: Vika Aqordi
 * Created on: 01-10-2025-15h-47m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { subYears, subMonths, subWeeks, subDays, startOfYear, startOfDay, startOfQuarter, startOfMonth, startOfWeek, endOfMonth, endOfWeek, endOfDay, format } from 'date-fns'

export const getDashboardDateRange = (intervalValue?: string) => {
    if (!intervalValue) {
        return ''
    }
    const now = new Date()

    let startDate

    // Using switch to match the interval values like the PHP match
    switch (intervalValue) {
        case '1y':
            startDate = subYears(now, 1)
            break
        case '1q':
            startDate = subMonths(now, 3)
            break
        case '1m':
            startDate = subMonths(now, 1)
            break
        case '1w':
            startDate = subWeeks(now, 1)
            break
        case '3d':
            startDate = subDays(now, 3)
            break
        case '1d':
            startDate = subDays(now, 1)
            break
        case 'ytd':
            startDate = startOfYear(now)
            break
        case 'tdy':
            startDate = startOfDay(now)
            break
        case 'qtd':
            startDate = startOfQuarter(now)
            break
        case 'mtd':
            startDate = startOfMonth(now)
            break
        case 'wtd':
            startDate = startOfWeek(now)
            break
        case 'lm':
            startDate = [startOfMonth(subMonths(now, 1)), endOfMonth(subMonths(now, 1))]
            break
        case 'lw':
            startDate = [startOfWeek(subWeeks(now, 1)), endOfWeek(subWeeks(now, 1))]
            break
        case 'ld':
            startDate = [startOfDay(subDays(now, 1)), endOfDay(subDays(now, 1))]
            break
        default:
            return '' // Return empty string for unknown intervals
    }

    if (startDate === null) {
        return ''
    }

    // Check if startDate is an array (for ranges like lm, lw, ld)
    const start = Array.isArray(startDate) ? startDate[0] : startDate
    const end = Array.isArray(startDate) ? startDate[1] : now

    // Format the dates without hyphens
    const startStr = format(start, 'yyyyMMdd')
    const endStr = format(end, 'yyyyMMdd')

    return `${startStr}-${endStr}`
}