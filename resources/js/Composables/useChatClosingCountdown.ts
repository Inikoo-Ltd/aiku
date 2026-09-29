/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import axios from "axios"
import { computed, onUnmounted, ref, watch, type Ref } from "vue"

// When a customer only thanked us and an agent has the chat open, it gets a 👍 and closes at
// closingAt unless the agent types or keeps it open.
export function useChatClosingCountdown(session: Ref<any>, keepOpenUrl: () => string | null) {
    const closingAt = ref<string | null>(session.value?.closing_at ?? null)
    const now = ref(Date.now())
    let ticker: ReturnType<typeof setInterval> | null = null

    const stopTicker = () => {
        if (ticker) clearInterval(ticker)
        ticker = null
    }

    watch(closingAt, (at) => {
        stopTicker()
        if (at) {
            now.value = Date.now()
            ticker = setInterval(() => (now.value = Date.now()), 1000)
        }
    }, { immediate: true })

    watch(() => session.value?.ulid, () => {
        closingAt.value = session.value?.closing_at ?? null
    })

    onUnmounted(stopTicker)

    const closingIn = computed(() => {
        if (!closingAt.value) return null

        const seconds = Math.max(0, Math.round((new Date(closingAt.value).getTime() - now.value) / 1000))

        return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`
    })

    const onClosing = (payload: any) => {
        closingAt.value = payload?.closing_at ?? null
    }

    const keepOpen = async () => {
        if (!closingAt.value) return

        closingAt.value = null
        const url = keepOpenUrl()

        if (url) {
            try {
                await axios.patch(url, {}, { withCredentials: true })
            } catch (e) {
                console.error("Failed to keep the chat open", e)
            }
        }
    }

    return { closingAt, closingIn, onClosing, keepOpen }
}
