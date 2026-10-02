/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onMounted, onBeforeUnmount, watch, type Ref } from "vue"
import { usePage, router } from "@inertiajs/vue3"

interface TicketChangedEvent {
    id: number
    reference: string
}

let restoredFromHistory = false
globalThis.window?.addEventListener("popstate", () => (restoredFromHistory = true))

export const useLiveTickets = (only: string[], reference?: string, paused?: Ref<boolean>, ticketId?: number, skip?: (event: TicketChangedEvent) => boolean) => {
    const groupId = (usePage().props.layout as any)?.group?.id
    const channelName = ticketId ? `grp.ticket.${ticketId}` : `grp.${groupId}.general`
    const eventName = ticketId ? ".ticket-updated" : ".ticket-changed"
    const canListen = Boolean(ticketId || groupId)

    let debounceTimer: ReturnType<typeof setTimeout> | undefined
    let missedWhilePaused = false

    const reload = () => router.reload({ ...(only.length ? { only } : {}), preserveScroll: true, preserveState: true })

    if (paused) {
        watch(paused, (isPaused) => {
            if (!isPaused && missedWhilePaused) {
                missedWhilePaused = false
                reload()
            }
        })
    }

    const handler = (e: TicketChangedEvent) => {
        if (!ticketId && reference && e.reference !== reference) {
            return
        }

        if (skip?.(e)) {
            return
        }

        if (paused?.value) {
            missedWhilePaused = true
            return
        }

        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(reload, 800)
    }

    onMounted(() => {
        if (restoredFromHistory) {
            restoredFromHistory = false
            reload()
        }

        if (!canListen) {
            return
        }

        window.Echo.private(channelName).listen(eventName, handler)
    })

    onBeforeUnmount(() => {
        clearTimeout(debounceTimer)

        if (!canListen) {
            return
        }

        window.Echo.private(channelName).stopListening(eventName, handler)
    })
}
