import { ctrans } from "@/Composables/useTrans"
import { tasksRoute } from "@/Composables/useTasksRoute"
import type { LeaveCover } from "@/types/LeaveCover"

export const openWorkTotal = (cover: LeaveCover): number => cover.open_work.reduce((total, kind) => total + kind.count, 0)

export const openWorkSummary = (cover: LeaveCover): string =>
    cover.open_work.map((kind) => `${kind.label}: ${kind.count}`).join(", ") || ctrans("Nothing open is assigned to them")

export const coveringRoute = (cover: LeaveCover): string => `${tasksRoute("covering")}#cover-${cover.id}`
