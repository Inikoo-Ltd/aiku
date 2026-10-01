/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import axios from "axios"
import { computed, ref, watch, type Ref } from "vue"
import { ctrans } from "@/Composables/useTrans"

export const waitingOptions = [
    { hours: 24, label: () => ctrans("1 day") },
    { hours: 72, label: () => ctrans("3 days") },
    { hours: 168, label: () => ctrans("7 days") },
]

export function waitingLeft(until: string | null | undefined): string | null {
    if (!until) return null

    const hours = Math.max(0, Math.round((new Date(until).getTime() - Date.now()) / 3600000))

    return hours >= 24 ? ctrans(":days d", { days: Math.round(hours / 24) }) : ctrans(":hours h", { hours })
}

export function useChatWaitingForCustomer(session: Ref<any>, url: () => string | null) {
    const waitingUntil = ref<string | null>(session.value?.waiting_until ?? null)

    watch(() => session.value?.ulid, () => {
        waitingUntil.value = session.value?.waiting_until ?? null
    })

    const waitingIn = computed(() => waitingLeft(waitingUntil.value))

    const setWaiting = async (hours: number | null) => {
        const target = url()
        if (!target) return

        try {
            const { data } = await axios.patch(target, { hours }, { withCredentials: true })
            waitingUntil.value = data?.waiting_until ?? null
        } catch (e) {
            console.error("Failed to change waiting for the customer", e)
        }
    }

    const onCustomerMessage = (message: any) => {
        if (["user", "guest"].includes(message?.sender_type)) waitingUntil.value = null
    }

    return { waitingUntil, waitingIn, setWaiting, onCustomerMessage }
}
