/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 18:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { reactive } from "vue"
import { usePage } from "@inertiajs/vue3"

/**
 * HELP-4491, HELP-4896: colleagues see who has a conversation open and who is writing a reply to
 * it, so two people do not answer the same customer. The open conversation is whispered on the
 * shop's chat-list presence channel, which only the shop's chat staff can join; each tab announces
 * only itself and nobody is asked to answer (INI-151). A newcomer learns of it at the next
 * announcement.
 */
const ANNOUNCE_EVERY_MS = 15_000
const FORGET_AFTER_MS = 40_000
const STOP_AFTER_IDLE_MS = 5 * 60_000

export type ChatPresenceMode = "viewing" | "writing"

/** `drafting` keeps tabs still on the HELP-4491 build reading writers right and ignoring viewers. */
interface DraftingWhisper {
    session_ulid: string
    user_id: number
    name: string
    drafting: boolean
    viewing?: boolean
}

export interface ChatColleague {
    id: number
    name: string
    mode: ChatPresenceMode
}

/** session ulid → user id → the colleague with that conversation open */
const presence = reactive<Record<string, Record<number, ChatColleague>>>({})
const forgetTimers = new Map<string, ReturnType<typeof setTimeout>>()

const me = () => usePage().props?.auth?.user as { id: number, username: string, contact_name?: string } | undefined

const forget = (sessionUlid: string, userId: number) => {
    clearTimeout(forgetTimers.get(`${sessionUlid}:${userId}`))
    forgetTimers.delete(`${sessionUlid}:${userId}`)
    if (!presence[sessionUlid]) return
    delete presence[sessionUlid][userId]
    if (!Object.keys(presence[sessionUlid]).length) delete presence[sessionUlid]
}

/** Writers first, so the one most likely to answer is the one named. */
export const chatColleaguesOn = (sessionUlid: string | null | undefined): ChatColleague[] =>
    sessionUlid && presence[sessionUlid]
        ? Object.values(presence[sessionUlid]).sort((a, b) => (a.mode === b.mode ? 0 : a.mode === "writing" ? -1 : 1))
        : []

export const chatWriterOn = (sessionUlid: string | null | undefined): ChatColleague | undefined =>
    chatColleaguesOn(sessionUlid).find((colleague) => colleague.mode === "writing")

export const onChatDraftingWhisper = (e: DraftingWhisper) => {
    if (!e?.session_ulid || !e.user_id || e.user_id === me()?.id) return

    const mode: ChatPresenceMode | null = e.drafting ? "writing" : e.viewing ? "viewing" : null
    if (!mode) {
        forget(e.session_ulid, e.user_id)
        return
    }

    const key = `${e.session_ulid}:${e.user_id}`
    clearTimeout(forgetTimers.get(key))
    presence[e.session_ulid] ??= {}
    presence[e.session_ulid][e.user_id] = { id: e.user_id, name: e.name, mode }
    forgetTimers.set(key, setTimeout(() => forget(e.session_ulid, e.user_id), FORGET_AFTER_MS))
}

let lastActivityAt = Date.now()
let isWatchingActivity = false
const noteActivity = () => { lastActivityAt = Date.now() }
const watchActivity = () => {
    if (isWatchingActivity || typeof window === "undefined") return
    isWatchingActivity = true
    for (const event of ["pointerdown", "keydown", "wheel", "pointermove"]) {
        window.addEventListener(event, noteActivity, { passive: true, capture: true })
    }
}

let announced: { shopId: number, sessionUlid: string, mode: ChatPresenceMode, lastKeyAt: number, timer: ReturnType<typeof setInterval> } | null = null

const whisper = (shopId: number, sessionUlid: string, mode: ChatPresenceMode | null) => {
    const user = me()
    // ponytail: only on a shop channel this page already joined; joining another one here would leave it open
    if (!user || !window.Echo?.connector?.channels?.[`presence-chat-list.${shopId}`]) return

    window.Echo.join(`chat-list.${shopId}`).whisper("chatDrafting", {
        session_ulid: sessionUlid,
        user_id: user.id,
        name: user.contact_name || user.username,
        drafting: mode === "writing",
        viewing: mode === "viewing",
    } satisfies DraftingWhisper)
}

/**
 * Writing lasts STOP_AFTER_IDLE_MS after the last keystroke, then drops back to viewing; viewing is
 * announced only while the tab is in front and somebody used it in the last STOP_AFTER_IDLE_MS, so
 * a screen left on a conversation does not hold it.
 */
const tick = () => {
    if (!announced) return
    if (announced.mode === "writing" && Date.now() - announced.lastKeyAt > STOP_AFTER_IDLE_MS) {
        announced.mode = "viewing"
    }
    const isAway = document.visibilityState !== "visible" || Date.now() - lastActivityAt > STOP_AFTER_IDLE_MS
    whisper(announced.shopId, announced.sessionUlid, announced.mode === "viewing" && isAway ? null : announced.mode)
}

/** Stops only the announcement for that conversation, so a second reply box does not end another's. */
export const stopChatDrafting = (sessionUlid: string | null | undefined) => {
    if (!announced || !sessionUlid || announced.sessionUlid !== sessionUlid) return

    clearInterval(announced.timer)
    whisper(announced.shopId, announced.sessionUlid, null)
    announced = null
}

const announce = (shopId: number, sessionUlid: string, mode: ChatPresenceMode) => {
    watchActivity()
    noteActivity()

    if (announced?.sessionUlid === sessionUlid) {
        if (mode === "writing") announced.lastKeyAt = Date.now()
        if (announced.mode === mode) return
        announced.mode = mode
        whisper(shopId, sessionUlid, mode)
        return
    }

    if (announced) stopChatDrafting(announced.sessionUlid)
    whisper(shopId, sessionUlid, mode)
    announced = { shopId, sessionUlid, mode, lastKeyAt: Date.now(), timer: setInterval(tick, ANNOUNCE_EVERY_MS) }
}

/** Called when the agent opens a conversation, and when their reply box is emptied or sent. */
export const noteChatViewing = (shopId: number | null | undefined, sessionUlid: string | null | undefined) => {
    if (!shopId || !sessionUlid) return
    announce(shopId, sessionUlid, "viewing")
}

/** Called on the agent's own keystrokes. */
export const noteChatDrafting = (shopId: number | null | undefined, sessionUlid: string | null | undefined) => {
    if (!shopId || !sessionUlid) return
    announce(shopId, sessionUlid, "writing")
}
