/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 18:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { reactive } from "vue"
import { usePage } from "@inertiajs/vue3"

/**
 * HELP-4491: colleagues see who is writing a reply to a conversation, so two people do not
 * answer the same customer. The writer whispers on the shop's chat-list presence channel, which
 * only the shop's chat staff can join; each tab announces only itself and nobody is asked to
 * answer (INI-151). A newcomer learns of a reply in progress at the next announcement.
 */
const ANNOUNCE_EVERY_MS = 15_000
const FORGET_AFTER_MS = 40_000
const STOP_AFTER_IDLE_MS = 5 * 60_000

interface DraftingWhisper {
    session_ulid: string
    user_id: number
    name: string
    drafting: boolean
}

/** session ulid → the colleague writing a reply to it */
export const chatDrafters = reactive<Record<string, { id: number, name: string }>>({})
const forgetTimers = new Map<string, ReturnType<typeof setTimeout>>()

const me = () => usePage().props?.auth?.user as { id: number, username: string, contact_name?: string } | undefined

export const onChatDraftingWhisper = (e: DraftingWhisper) => {
    if (!e?.session_ulid || e.user_id === me()?.id) return

    if (!e.drafting) {
        if (chatDrafters[e.session_ulid]?.id === e.user_id) {
            clearTimeout(forgetTimers.get(e.session_ulid))
            delete chatDrafters[e.session_ulid]
        }
        return
    }

    clearTimeout(forgetTimers.get(e.session_ulid))
    chatDrafters[e.session_ulid] = { id: e.user_id, name: e.name }
    forgetTimers.set(e.session_ulid, setTimeout(() => delete chatDrafters[e.session_ulid], FORGET_AFTER_MS))
}

let drafting: { shopId: number, sessionUlid: string, lastKeyAt: number, timer: ReturnType<typeof setInterval> } | null = null

const whisper = (shopId: number, sessionUlid: string, isDrafting: boolean) => {
    const user = me()
    // ponytail: only on a shop channel this page already joined; joining another one here would leave it open
    if (!user || !window.Echo?.connector?.channels?.[`presence-chat-list.${shopId}`]) return

    window.Echo.join(`chat-list.${shopId}`).whisper("chatDrafting", {
        session_ulid: sessionUlid,
        user_id: user.id,
        name: user.contact_name || user.username,
        drafting: isDrafting,
    } satisfies DraftingWhisper)
}

/** Stops only the announcement for that conversation, so a second reply box does not end another's. */
export const stopChatDrafting = (sessionUlid: string | null | undefined) => {
    if (!drafting || !sessionUlid || drafting.sessionUlid !== sessionUlid) return

    clearInterval(drafting.timer)
    whisper(drafting.shopId, drafting.sessionUlid, false)
    drafting = null
}

/**
 * Called on the agent's own keystrokes. Announces at once, then every ANNOUNCE_EVERY_MS while
 * the reply is open, so a pause to look something up does not hide it; a reply nobody has
 * touched for STOP_AFTER_IDLE_MS stops being announced.
 */
export const noteChatDrafting = (shopId: number | null | undefined, sessionUlid: string | null | undefined) => {
    if (!shopId || !sessionUlid) return

    if (drafting?.sessionUlid === sessionUlid) {
        drafting.lastKeyAt = Date.now()
        return
    }

    if (drafting) stopChatDrafting(drafting.sessionUlid)
    whisper(shopId, sessionUlid, true)
    drafting = {
        shopId,
        sessionUlid,
        lastKeyAt: Date.now(),
        timer: setInterval(() => {
            if (!drafting) return
            if (Date.now() - drafting.lastKeyAt > STOP_AFTER_IDLE_MS) {
                stopChatDrafting(drafting.sessionUlid)
                return
            }
            whisper(drafting.shopId, drafting.sessionUlid, true)
        }, ANNOUNCE_EVERY_MS),
    }
}
