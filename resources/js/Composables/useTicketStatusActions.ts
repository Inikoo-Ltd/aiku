/*
 * Author Louis Perez
 * Created on 15-09-2026-10h-09m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { ctrans } from "@/Composables/useTrans"

export type TicketStatusAction = { status: string; label: string; icon: string; class: string }

export const useTicketStatusActions = () => {
    const start: TicketStatusAction = { status: "in_progress", label: ctrans("Start"), icon: "fal fa-play", class: "text-blue-600" }
    const done: TicketStatusAction = { status: "resolved", label: ctrans("Done"), icon: "fal fa-check", class: "text-green-600" }
    const cancel: TicketStatusAction = { status: "cancelled", label: ctrans("Cancel"), icon: "fal fa-ban", class: "text-red-500" }
    const resume: TicketStatusAction = { ...start, label: ctrans("Resume") }
    const askReporter: TicketStatusAction = { status: "waiting", label: ctrans("Ask reporter"), icon: "fal fa-question-circle", class: "text-blue-500" }
    const reopen: TicketStatusAction = { status: "open", label: ctrans("Reopen"), icon: "fal fa-undo", class: "text-gray-600" }

    const reopenAsReporter: TicketStatusAction = { ...reopen, status: "answered" }

    const statusActions: Record<string, TicketStatusAction[]> = {
        open: [start, done, cancel],
        assigned: [start, done, cancel],
        in_progress: [
            askReporter,
            { status: "assigned", label: ctrans("Stop, back to assigned"), icon: "fal fa-stop", class: "text-gray-600" },
            done,
            cancel,
        ],
        waiting: [resume, done, cancel],
        answered: [resume, { ...askReporter, label: ctrans("Ask again") }, done, cancel],
        pending_deploy: [{ ...start, label: ctrans("Back to in progress") }, done, cancel],
        resolved: [reopen],
        cancelled: [reopen],
    }

    const assigneeStatusActions: Record<string, TicketStatusAction[]> = {
        open: [start, done, cancel],
        assigned: [start, done, cancel],
        in_progress: [askReporter, done, cancel],
        waiting: [resume, done, cancel],
        answered: [resume, done, cancel],
    }

    const actionsFor = (
        actions: Record<string, TicketStatusAction[]>,
        ticket: { status: string; assignee_id: number | null }
    ): TicketStatusAction[] => (ticket.status === "open" && !ticket.assignee_id ? [] : (actions[ticket.status] ?? []))

    return { start, done, cancel, reopenAsReporter, statusActions, assigneeStatusActions, actionsFor }
}
