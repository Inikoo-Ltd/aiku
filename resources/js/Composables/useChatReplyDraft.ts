/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 19:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import axios from "axios"
import { onBeforeUnmount, onMounted, Ref, ref, watch } from "vue"
import { stripPictureTokens } from "@/Composables/useWhatsappMarkup"

/**
 * HELP-4523: the reply an agent has started is kept on the server, so a colleague who opens
 * the conversation sees it and can carry it on, and it follows the agent to another computer.
 * Replaces the browser-only copy (useComposerDraft); one left in this browser is moved up once.
 *
 * Saves go one at a time, so a late one never lands after a newer one. A save that carries on a
 * draft the server no longer has (a colleague took it over, or it was sent from another tab) is
 * refused rather than bringing it back, and the agent is told before they send it twice.
 */
const SAVE_AFTER_MS = 1000
const NOT_SAVED = "\u0000"

export interface ColleagueReplyDraft {
    id: number
    agent_id: number
    name: string
    text: string
    updated_at: string
}

interface ReplyDrafts {
    mine: { text: string } | null
    colleague: ColleagueReplyDraft | null
}

const organisation = () => String((route().params as Record<string, any>)?.organisation ?? "aw")
const draftRoute = (name: string, sessionUlid: string) => route(`grp.org.chat.agents.sessions.reply_draft.${name}`, [organisation(), sessionUlid])

const takeBrowserDraft = (sessionUlid: string): string => {
    try {
        const key = `composer-draft:${sessionUlid}`
        const text = localStorage.getItem(key) ?? ""
        localStorage.removeItem(key)
        return text
    } catch {
        return ""
    }
}

/**
 * @param isUntouchedAiDraft true while the box holds Aiku's draft as offered: that is not the
 * agent's reply yet, so it is neither saved nor shown to colleagues.
 */
export const useChatReplyDraft = (sessionUlid: () => string | null | undefined, text: Ref<string>, isUntouchedAiDraft: () => boolean) => {
    const colleagueDraft = ref<ColleagueReplyDraft | null>(null)
    const isLoaded = ref(false)
    const saveFailed = ref(false)
    /** Set when the server no longer holds the draft this tab was writing: the colleague's name, or "" when unknown. */
    const carriedOnBy = ref<string | null>(null)

    let loadedFor: string | null = null
    let savedText = ""
    let pending: { sessionUlid: string, text: string } | null = null
    let timer: ReturnType<typeof setTimeout> | undefined
    let queue: Promise<unknown> = Promise.resolve()
    const serverHasMine = new Map<string, boolean>()

    const put = async (ulid: string, value: string) => {
        try {
            const { data } = await axios.put(draftRoute("update", ulid), { text: value, existed: serverHasMine.get(ulid) ?? false })
            serverHasMine.set(ulid, !!data?.saved)
            if (loadedFor === ulid) saveFailed.value = false
        } catch (error: any) {
            if (loadedFor !== ulid) return

            if (error?.response?.status === 409) {
                serverHasMine.set(ulid, false)
                carriedOnBy.value = error.response.data?.name ?? ""
                savedText = value
                return
            }

            saveFailed.value = true
            savedText = NOT_SAVED
            pending ??= { sessionUlid: ulid, text: value }
        }
    }

    const flush = (leavingPage = false) => {
        clearTimeout(timer)
        if (!pending) return

        const { sessionUlid: ulid, text: value } = pending
        pending = null

        if (leavingPage) {
            // ponytail: browsers cap a keepalive body at 64 KB; a longer reply closed within a second of typing loses those last keystrokes
            const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? "")
            fetch(draftRoute("update", ulid), {
                method: "PUT",
                keepalive: true,
                headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": token },
                body: JSON.stringify({ text: value, existed: serverHasMine.get(ulid) ?? false }),
            }).catch(() => {})
            return
        }

        queue = queue.then(() => put(ulid, value))
    }

    const consider = () => {
        const ulid = sessionUlid()
        if (!ulid || loadedFor !== ulid || isUntouchedAiDraft()) return

        const value = text.value ?? ""
        if (!value.trim()) carriedOnBy.value = null
        if (value === savedText || (!value.trim() && !savedText.trim())) return

        savedText = value
        pending = { sessionUlid: ulid, text: value }
        clearTimeout(timer)
        timer = setTimeout(() => flush(), SAVE_AFTER_MS)
    }

    watch(text, consider)

    const fetchDrafts = async (ulid: string): Promise<ReplyDrafts | null> => {
        try {
            return (await axios.get(draftRoute("show", ulid))).data
        } catch {
            return null
        }
    }

    watch(sessionUlid, async (ulid) => {
        flush()
        loadedFor = null
        isLoaded.value = false
        saveFailed.value = false
        carriedOnBy.value = null
        colleagueDraft.value = null
        text.value = ""
        if (!ulid) return

        await queue
        const drafts = await fetchDrafts(ulid)
        if (sessionUlid() !== ulid) return

        savedText = drafts?.mine?.text ?? ""
        serverHasMine.set(ulid, !!savedText)
        const mine = savedText || takeBrowserDraft(ulid)
        colleagueDraft.value = drafts?.colleague ?? null
        loadedFor = ulid
        // Whatever was typed while the draft was loading goes after it, never over it.
        const typedMeanwhile = (text.value ?? "").trim() && !isUntouchedAiDraft() ? text.value : ""
        if (mine) text.value = typedMeanwhile ? `${mine}\n\n${typedMeanwhile}` : mine
        isLoaded.value = true
        consider()
    }, { immediate: true })

    /** A colleague's draft may have been sent or carried on by someone else since it was shown. */
    const refreshColleagueDraft = async () => {
        const ulid = sessionUlid()
        if (!ulid || loadedFor !== ulid) return

        const drafts = await fetchDrafts(ulid)
        if (drafts && sessionUlid() === ulid) colleagueDraft.value = drafts.colleague
    }

    /** The colleague's draft becomes this agent's reply; theirs is removed so it is not finished twice. */
    const takeOverColleagueDraft = async (): Promise<string | null> => {
        const ulid = sessionUlid()
        const draft = colleagueDraft.value
        if (!ulid || !draft) return null

        flush()
        await queue

        let data: { text: string }
        try {
            data = (await axios.patch(draftRoute("take_over", ulid), { draft_id: draft.id, agent_id: draft.agent_id })).data
        } catch (error: any) {
            if (error?.response?.status === 404) await refreshColleagueDraft()
            throw error
        }
        if (sessionUlid() !== ulid) return null

        // Pictures pasted into the draft stay in the colleague's browser; only the words come across.
        const words = stripPictureTokens(data.text)
        serverHasMine.set(ulid, true)
        colleagueDraft.value = null
        savedText = data.text
        text.value = words
        return words
    }

    const onPageHide = () => {
        const ulid = sessionUlid()
        if (!pending && ulid && loadedFor === ulid && savedText === NOT_SAVED) {
            pending = { sessionUlid: ulid, text: text.value ?? "" }
        }
        flush(true)
    }
    onMounted(() => window.addEventListener("pagehide", onPageHide))
    onBeforeUnmount(() => {
        window.removeEventListener("pagehide", onPageHide)
        flush()
    })

    return { colleagueDraft, isLoaded, saveFailed, carriedOnBy, takeOverColleagueDraft, refreshColleagueDraft }
}
