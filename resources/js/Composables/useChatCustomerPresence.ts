/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { computed, onUnmounted, ref, watch, type Ref } from "vue"
import { ctrans } from "@/Composables/useTrans"

type Presence = { seen_at: string; left: boolean }

// The widget beats every minute, but a background tab is throttled to a beat a minute or less,
// so the customer counts as gone only after three missed beats, or at once when the page closes.
const ONLINE_FOR_MS = 180_000

export function useChatCustomerPresence(session: Ref<any>) {
    const presence = ref<Presence | null>(session.value?.customer_presence ?? null)
    const now = ref(Date.now())
    const ticker = setInterval(() => (now.value = Date.now()), 30_000)

    onUnmounted(() => clearInterval(ticker))

    watch(() => session.value?.ulid, () => {
        presence.value = session.value?.customer_presence ?? null
    })

    const customerPresence = computed<{ online: boolean; label: string } | null>(() => {
        if (!presence.value) return null

        const ago = now.value - new Date(presence.value.seen_at).getTime()

        if (!presence.value.left && ago < ONLINE_FOR_MS) {
            return { online: true, label: ctrans("Customer online") }
        }

        const minutes = Math.max(1, Math.round(ago / 60_000))

        return {
            online: false,
            label: minutes < 60
                ? ctrans("Customer left :minutes m ago", { minutes })
                : ctrans("Customer left :hours h ago", { hours: Math.round(minutes / 60) }),
        }
    })

    const onPresence = (payload: Presence) => {
        now.value = Date.now()
        presence.value = payload
    }

    const onCustomerMessage = (message: any) => {
        if (["user", "guest"].includes(message?.sender_type)) {
            onPresence({ seen_at: new Date().toISOString(), left: false })
        }
    }

    return { customerPresence, onPresence, onCustomerMessage }
}
