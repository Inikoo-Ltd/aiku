/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Sep 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onMounted, onBeforeUnmount } from "vue"
import { usePage, router } from "@inertiajs/vue3"

interface TicketChangedEvent {
    id: number
    reference: string
}

export const useLiveTickets = (only: string[], reference?: string) => {
    const groupId = (usePage().props.layout as any)?.group?.id
    const channelName = `grp.${groupId}.general`

    let debounceTimer: ReturnType<typeof setTimeout> | undefined

    const handler = (e: TicketChangedEvent) => {
        if (reference && e.reference !== reference) {
            return
        }

        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.reload({ only, preserveScroll: true, preserveState: true })
        }, 800)
    }

    onMounted(() => {
        if (!groupId) {
            return
        }

        window.Echo.private(channelName).listen(".ticket-changed", handler)
    })

    onBeforeUnmount(() => {
        clearTimeout(debounceTimer)

        if (!groupId) {
            return
        }

        window.Echo.private(channelName).stopListening(".ticket-changed", handler)
    })
}
