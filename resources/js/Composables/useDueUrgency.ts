import { differenceInCalendarDays, parseISO } from "date-fns"
import { ctrans } from "@/Composables/useTrans"

export type DueUrgency = "overdue" | "critical" | "soon" | "planned" | "closed"

const PRESSING_PRIORITIES = ["high", "urgent"]

export const dueUrgencyClasses: Record<DueUrgency, string> = {
    overdue: "bg-red-50 text-red-700 ring-red-200",
    critical: "bg-orange-50 text-orange-700 ring-orange-200",
    soon: "bg-amber-50 text-amber-700 ring-amber-200",
    planned: "bg-green-50 text-green-700 ring-green-200",
    closed: "bg-gray-50 text-gray-500 ring-gray-200",
}

export const dueDaysLeft = (dueAt: string) => differenceInCalendarDays(parseISO(dueAt), new Date())

export const dueUrgency = (dueAt: string, priority: string, isOpen = true): DueUrgency => {
    if (!isOpen) return "closed"

    const daysLeft = dueDaysLeft(dueAt)

    if (daysLeft < 0) return "overdue"
    if (daysLeft <= 1) return "critical"
    if (daysLeft <= 3 || (PRESSING_PRIORITIES.includes(priority) && daysLeft <= 7)) return "soon"

    return "planned"
}

export const dueRelativeLabel = (dueAt: string) => {
    const daysLeft = dueDaysLeft(dueAt)

    if (daysLeft === 0) return ctrans("Today")
    if (daysLeft === 1) return ctrans("Tomorrow")
    if (daysLeft === -1) return ctrans("1 day late")
    if (daysLeft < 0) return ctrans(":days days late", { days: String(-daysLeft) })

    return ctrans("in :days days", { days: String(daysLeft) })
}
