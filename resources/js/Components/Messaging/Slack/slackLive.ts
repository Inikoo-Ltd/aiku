/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onUnmounted, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import type { SlackStreamMessage } from "@/Components/Messaging/Slack/SlackMessageRow.vue"

export interface SlackLiveEvent {
    channel_id: number
    kind: "new" | "changed" | "deleted" | "reaction" | "pin"
    ts: string
    at: number
    message: SlackStreamMessage | null
    parent: SlackStreamMessage | null
    reactors: Record<string, number[]>
}

const SLACK_LIVE_EVENT = ".slack-message"
const listeners = new Map<string, number>()

export const slackReloads = ref(0)

export const reloadSlack = (only: string[]) =>
    router.reload({
        only,
        reset: only.includes("slack_messages") ? ["slack_messages"] : [],
        onSuccess: () => {
            slackReloads.value++
        },
    })

export const myReactions = (reactors: Record<string, number[]> | undefined, userId: number | null | undefined): string[] =>
    Object.entries(reactors ?? {}).filter(([, ids]) => userId != null && ids.includes(userId)).map(([name]) => name)

export const isInSlackStream = (message: SlackStreamMessage) => !message.thread_ts || message.thread_ts === message.ts || message.is_broadcast

export const useSlackLive = (channelName: () => string | null | undefined, handler: (event: SlackLiveEvent) => void) => {
    let current: string | null = null
    const stop = () => {
        if (!current || !window.Echo) return
        window.Echo.private(current).stopListening(SLACK_LIVE_EVENT, handler)
        const left = (listeners.get(current) ?? 1) - 1
        if (left <= 0) {
            listeners.delete(current)
            window.Echo.leave(current)
        } else {
            listeners.set(current, left)
        }
        current = null
    }
    watch(channelName, (name) => {
        if (name === current) return
        stop()
        if (!name || !window.Echo) return
        window.Echo.private(name).listen(SLACK_LIVE_EVENT, handler)
        listeners.set(name, (listeners.get(name) ?? 0) + 1)
        current = name
    }, { immediate: true })
    onUnmounted(stop)
}
