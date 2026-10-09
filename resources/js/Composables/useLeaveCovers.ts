import { ctrans } from "@/Composables/useTrans"
import { tasksRoute } from "@/Composables/useTasksRoute"
import type { LeaveCover } from "@/types/LeaveCover"

export const openWorkTotal = (cover: LeaveCover): number => cover.open_work.reduce((total, kind) => total + kind.count, 0)

export const openWorkSummary = (cover: LeaveCover): string =>
    cover.open_work.map((kind) => `${kind.label}: ${kind.count}`).join(", ") || ctrans("Nothing open is assigned to them")

export const coveredByExplanation = (): string =>
    ctrans("The colleague who stands in while this employee is away. Once the leave is approved, they get this employee's permissions for the leave dates, are notified, and are added to their open tasks and tickets.")

export const coveringRoute = (cover: LeaveCover): string => `${tasksRoute("covering")}#cover-${cover.id}`

export const isOnLeaveDuring = (periods: [string, string][] | undefined, startDate: string, endDate: string): boolean =>
    !!startDate && !!endDate && (periods ?? []).some(([start, end]) => start <= endDate && end >= startDate)
