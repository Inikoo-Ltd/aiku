/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onMounted, onUnmounted } from "vue"

// A customer message that reaches a conversation open in a tab nobody is looking at stays
// unread until that tab is in front again, so the unread count, the tab title and the
// "customer waiting" alarm keep going instead of being silenced by a hidden tab.
export function readWhenWatchedQueue(markRead: () => void, isWatched: () => boolean) {
    let pending = false

    const readWhenWatched = () => {
        if (isWatched()) {
            pending = false
            markRead()
        } else {
            pending = true
        }
    }

    const onBackInFront = () => {
        if (pending) readWhenWatched()
    }

    return { readWhenWatched, onBackInFront }
}

export function useReadWhenWatched(markRead: () => void) {
    const { readWhenWatched, onBackInFront } = readWhenWatchedQueue(markRead, () => document.visibilityState === "visible" && document.hasFocus())

    onMounted(() => {
        window.addEventListener("focus", onBackInFront)
        document.addEventListener("visibilitychange", onBackInFront)
    })
    onUnmounted(() => {
        window.removeEventListener("focus", onBackInFront)
        document.removeEventListener("visibilitychange", onBackInFront)
    })

    return { readWhenWatched }
}
