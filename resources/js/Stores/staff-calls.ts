/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"
import axios from "axios"
import { usePage } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"

export interface StaffCall {
    ulid: string
    conversation_ulid: string
    conversation_type: "dm" | "group"
    kind: StaffCallKind
    conversation_name: string | null
    started_by_user_id: number
    started_by_name: string
    started_at: string
    elapsed_seconds: number
    local_started_at?: number
    answered_at: string | null
    ended_at: string | null
    outcome: "answered" | "declined" | "missed" | "cancelled" | null
    present_user_ids: number[]
    joined_user_ids: number[]
}

export type StaffCallKind = "voice" | "video" | "screen"

export interface StaffCallJoin {
    call: StaffCall
    token: string
    url: string
    withCamera: boolean
    screen?: MediaStream | null
}

export const canShareScreen = (): boolean =>
    typeof navigator !== "undefined" && !!navigator.mediaDevices?.getDisplayMedia && !window.matchMedia("(pointer: coarse)").matches

/**
 * Opens the browser's own screen picker. Call it straight from a click handler, before anything is awaited: browsers
 * only allow the picker during the click.
 */
export const pickScreen = (): Promise<MediaStream> =>
    navigator.mediaDevices.getDisplayMedia({
        video: { width: { ideal: 1920 }, height: { ideal: 1080 }, frameRate: { ideal: 15, max: 15 } },
        audio: true,
        selfBrowserSurface: "exclude",
        surfaceSwitching: "include",
        systemAudio: "include",
    } as DisplayMediaStreamOptions).then((stream) => {
        stream.getVideoTracks().forEach((track) => (track.contentHint = "text"))
        return stream
    })

const stopStream = (stream?: MediaStream | null) => stream?.getTracks().forEach((track) => track.stop())

const warmCallCode = () => {
    import("livekit-client").catch(() => { })
    import("@/Components/Messaging/Call/StaffCallPanel.vue").catch(() => { })
}

type LeaveReason = "ring_timeout" | null

const xsrfToken = () => decodeURIComponent(document.cookie.split("; ").find((cookie) => cookie.startsWith("XSRF-TOKEN="))?.slice("XSRF-TOKEN=".length) ?? "")

const postLeave = (ulid: string, reason: LeaveReason, keepalive: boolean) => fetch(route("grp.chat.staff.calls.leave", ulid), {
    method: "POST",
    keepalive,
    credentials: "same-origin",
    headers: { "Content-Type": "application/json", Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": xsrfToken() },
    body: JSON.stringify(reason === "ring_timeout" ? { reason } : {}),
})

const errorText = (error: any): string => {
    const status = error?.response?.status
    if (status === 403) return ctrans("You can no longer join this call.")
    if (status === 404) return ctrans("Calls are not available.")
    if (status === 429) return ctrans("Too many tries. Wait a moment and try again.")
    return error?.response?.data?.message || ctrans("The call could not be reached. Check your connection and try again.")
}

export const useStaffCalls = defineStore("staff-calls", {
    state: () => ({
        enabled: false,
        left: {} as Record<string, boolean>,
        calls: {} as Record<string, StaffCall>,
        dismissed: {} as Record<string, boolean>,
        joining: null as string | null,
        active: null as StaffCallJoin | null,
        error: null as { conversationUlid: string | null; text: string } | null,
        pingSeconds: 20,
        ringSeconds: 30,
    }),
    getters: {
        myId: () => usePage().props?.auth?.user?.id as number | undefined,
        liveCallIn: (state) => (conversationUlid: string): StaffCall | null =>
            Object.values(state.calls).find((call) => call.conversation_ulid === conversationUlid && !call.ended_at && !(call.conversation_type === "dm" && state.left[call.ulid])) ?? null,
    },
    actions: {
        async init() {
            try {
                const { data } = await axios.get(route("grp.chat.staff.calls.live"), { headers: { "X-Background-Request": "1" } })
                this.enabled = true
                this.pingSeconds = data.ping_seconds
                this.ringSeconds = data.ring_seconds
                data.data.forEach((call: StaffCall) => this.handleBroadcast(call))
            } catch {
                this.enabled = false
            }
        },

        handleBroadcast(call: StaffCall) {
            call.local_started_at = this.calls[call.ulid]?.local_started_at ?? Date.now() - (call.elapsed_seconds ?? 0) * 1000
            this.calls[call.ulid] = call
            if (this.active?.call.ulid === call.ulid) {
                this.active.call = call
            }
        },

        isRingingMe(call: StaffCall): boolean {
            return !call.ended_at
                && call.started_by_user_id !== this.myId
                && !call.present_user_ids.includes(this.myId as number)
                && !this.dismissed[call.ulid]
                && (call.conversation_type === "group" || !call.answered_at)
        },

        async start(conversationUlid: string, kind: StaffCallKind, screen: MediaStream | null = null) {
            if (this.active) {
                stopStream(screen)
                return
            }
            this.error = null
            this.joining = conversationUlid
            warmCallCode()
            try {
                const { data } = await axios.post(route("grp.chat.staff.conversations.calls.store", conversationUlid), { kind })
                this.handleBroadcast(data.call)
                this.active = { ...data, withCamera: kind === "video", screen }
            } catch (error) {
                stopStream(screen)
                this.error = { conversationUlid, text: errorText(error) }
            } finally {
                this.joining = null
            }
        },

        async accept(call: StaffCall, withCamera: boolean) {
            if (this.active?.call.ulid === call.ulid) return
            if (this.active) await this.leave()
            this.error = null
            this.joining = call.conversation_ulid
            warmCallCode()
            try {
                const { data } = await axios.post(route("grp.chat.staff.calls.join", call.ulid))
                this.handleBroadcast(data.call)
                this.active = { ...data, withCamera }
            } catch (error) {
                this.error = { conversationUlid: call.conversation_ulid, text: errorText(error) }
                this.dismissed[call.ulid] = true
            } finally {
                this.joining = null
            }
        },

        async decline(call: StaffCall) {
            this.dismissed[call.ulid] = true
            if (call.conversation_type !== "dm") return
            try {
                const { data } = await axios.post(route("grp.chat.staff.calls.decline", call.ulid))
                this.handleBroadcast(data.call)
            } catch { }
        },

        async leave(reason: LeaveReason = null) {
            const active = this.active
            if (!active) return
            this.active = null
            this.left[active.call.ulid] = true
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    const response = await postLeave(active.call.ulid, attempt === 0 ? reason : null, false)
                    if (response.ok) {
                        this.handleBroadcast((await response.json()).call)
                        return
                    }
                    if (response.status === 403 || response.status === 404) return
                } catch { }
                await new Promise((resolve) => setTimeout(resolve, 1000 * (attempt + 1)))
            }
        },

        leaveOnPageHide() {
            const active = this.active
            if (!active) return
            this.active = null
            postLeave(active.call.ulid, null, true).catch(() => { })
        },

        async ping() {
            const active = this.active
            if (!active) return
            try {
                const { data } = await axios.post(route("grp.chat.staff.calls.ping", active.call.ulid), {}, { headers: { "X-Background-Request": "1" } })
                this.handleBroadcast(data.call)
            } catch (error: any) {
                if (error?.response?.status === 403 || error?.response?.status === 404) {
                    this.active = null
                }
            }
        },
    },
})
