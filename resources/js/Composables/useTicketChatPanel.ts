/*
 * Author Louis Perez
 * Created on 21-09-2026-16h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { computed, reactive } from "vue"

/**
 * Whether a ticket's conversation is open, shared between the thread and the control panel:
 * the source details live in the panel until the conversation is opened, then move into it as
 * its header, so the same facts are never on screen twice.
 *
 * Keyed by ticket, because a quick look can sit over a ticket that is already open.
 */
const openTickets = reactive<Set<string>>(new Set())

const key = (ticketId: number | string) => String(ticketId)

export const useTicketChatPanel = (ticketId: number | string) => {
    const isOpen = computed(() => openTickets.has(key(ticketId)))

    const setOpen = (open: boolean) => {
        if (open) {
            openTickets.add(key(ticketId))
        } else {
            openTickets.delete(key(ticketId))
        }
    }

    return { isOpen, setOpen }
}
