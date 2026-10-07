/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"
import axios from "axios"
import { usePage } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { alertOnce, chosenAlertSound, isOpenInFront } from "@/Composables/useNotificationSound"
import { pruneCachedChats, readCachedChat, removeCachedChat, writeCachedChat } from "@/Composables/useStaffChatCache"

export interface StaffMessageReactions {
    [emoji: string]: number[]
}

export interface StaffMessage {
    id: number
    conversation_ulid: string
    user_id: number
    user_name: string
    parent_id: number | null
    body: string
    mentions?: number[]
    mentioned_me?: boolean
    language_id: number | null
    translations: Record<string, string>
    reactions: StaffMessageReactions
    image: any
    gif_url?: string | null
    created_at: string
    client_status?: "sending" | "failed"
    client_key?: string
}

export interface StaffParticipant {
    id: number
    name: string
    handle: string | null
    avatar: any
    last_seen_at?: string | null
    last_read_at?: string | null
}

const GIF_URL = /^https:\/\/\S+\.(gif|webp)$/i

export interface StaffConversation {
    ulid: string
    type: "dm" | "group"
    name: string | null
    participants: StaffParticipant[]
    last_message_at: string | null
    last_message: string | null
    unread_count: number
    has_mention: boolean
    context_type?: string | null
    context_label?: string | null
    context_url?: string | null
    task?: StaffConversationTask | null
    my_left_at?: string | null
    is_watching?: boolean
}

export interface StaffConversationTask {
    reference: string
    is_open: boolean
    status?: string
    status_label?: string
    status_icon?: { icon: string; class: string; tooltip?: string; color?: string } | null
    requester_id: number
    assignee_id: number | null
    collaborator_ids: number[]
    subtasks: { title: string; status: "todo" | "in_progress" | "done" }[]
    description?: string | null
    model_label?: string | null
    model_url?: string | null
}

export interface StaffCoworker {
    id: number
    name: string
    avatar: any
    is_close: boolean
    organisation_ids?: number[]
    in_team: boolean
    last_active_at?: number | null
    on_call?: boolean
    on_call_since?: string | null
}

interface WindowState {
    ulid: string
    minimised: boolean
}

interface ArchivedNote {
    id: string
    text: string
    created_at: string
}

const MAX_BUBBLES = 12

const CACHE_WRITE_DELAY_MS = 400
const cacheWriteTimers = new Map<string, ReturnType<typeof setTimeout>>()
let isCachePruned = false

const currentUserId = (): number | undefined => usePage().props?.auth?.user?.id

export const bubblesStorageKey = () => `staff-chat-bubbles:${usePage().props?.auth?.user?.id ?? "guest"}`

const readStoredWindows = (): WindowState[] => {
    try {
        const stored = JSON.parse(localStorage.getItem(bubblesStorageKey()) ?? "[]")
        if (!Array.isArray(stored)) return []

        return stored
            .map((entry) => (typeof entry === "string" ? { ulid: entry, minimised: true } : entry))
            .filter((entry): entry is WindowState => typeof entry?.ulid === "string")
            .map((entry) => ({ ulid: entry.ulid, minimised: entry.minimised !== false }))
    } catch {
        return []
    }
}

export const isWorkThread = (conversation: StaffConversation) => !!conversation.context_type

export const canArchiveConversation = (conversation: StaffConversation | null | undefined) => !conversation?.task?.is_open

export const isHiddenChat = (conversation: StaffConversation) => isWorkThread(conversation) && !conversation.is_watching

const SHOW_HIDDEN_CHATS_KEY = "staff-messaging-show-hidden-chats"

const readShowHiddenChats = () => {
    try {
        return localStorage.getItem(SHOW_HIDDEN_CHATS_KEY) === "1"
    } catch {
        return false
    }
}

export const isAlerting = (conversation: StaffConversation) => conversation.has_mention || (conversation.type === "dm" && !isWorkThread(conversation))

export const useStaffMessaging = defineStore("staff-messaging", {
    state: () => ({
        conversations: [] as StaffConversation[],
        visitingConversations: [] as StaffConversation[],
        messagesByUlid: {} as Record<string, StaffMessage[]>,
        notesByUlid: {} as Record<string, ArchivedNote[]>,
        openWindows: [] as WindowState[],
        fullViewUlid: null as string | null,
        typingByUlid: {} as Record<string, { user_name: string; expiresAt: number }>,
        loadingMessages: {} as Record<string, boolean>,
        fetched: false,
        maxVisible: 1,
        instantlyMinimised: [] as string[],
        showHiddenChats: readShowHiddenChats(),
    }),

    getters: {
        totalUnread: (state) => state.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0),
        alertingUnread: (state) => state.conversations.reduce((sum, c) => sum + (isAlerting(c) ? c.unread_count || 0 : 0), 0),
        openWindowsVisible: (state) => state.openWindows.filter((w) => !w.minimised),
        openWindowsMinimised: (state) => state.openWindows.filter((w) => w.minimised),
        conversationByUlid: (state) => (ulid: string) => state.conversations.find((c) => c.ulid === ulid) ?? state.visitingConversations.find((c) => c.ulid === ulid),
    },

    actions: {
        async fetchConversations() {
            const { data } = await axios.get(route("grp.chat.staff.conversations.index"))
            this.conversations = data.data
            this.fetched = true

            const userId = currentUserId()
            if (userId && !isCachePruned) {
                isCachePruned = true
                pruneCachedChats(userId)
            }
        },

        cacheChatSoon(ulid: string) {
            const userId = currentUserId()
            if (!userId) return

            const pending = cacheWriteTimers.get(ulid)
            if (pending) clearTimeout(pending)

            cacheWriteTimers.set(ulid, setTimeout(() => {
                cacheWriteTimers.delete(ulid)
                const messages = this.messagesByUlid[ulid]
                if (messages?.length) writeCachedChat(userId, ulid, messages)
            }, CACHE_WRITE_DELAY_MS))
        },

        async openWithUser(userId: number) {
            const { data } = await axios.post(route("grp.chat.staff.conversations.store"), { user_ids: [userId] })
            const conversation: StaffConversation = data.data
            const existingIndex = this.conversations.findIndex((c) => c.ulid === conversation.ulid)
            if (existingIndex === -1) {
                this.conversations.unshift(conversation)
            } else {
                this.conversations[existingIndex] = conversation
            }
            this.openConversation(conversation.ulid)
        },

        async openContext(contextType: string, contextId: number, audience: string, message?: string) {
            const { data } = await axios.post(route("grp.chat.staff.context.open"), { context_type: contextType, context_id: contextId, audience })
            const conversation: StaffConversation = data.data
            const myId = usePage().props?.auth?.user?.id
            if (!conversation.participants.some((p) => p.id !== myId)) {
                notify({
                    title: ctrans("Nobody to ask"),
                    text: audience === "crm"
                        ? ctrans("No customer service colleague is set up for this shop. Tell a supervisor.")
                        : ctrans("No warehouse colleague is set up here. Tell a supervisor."),
                    type: "warning",
                })
                return
            }
            const existingIndex = this.conversations.findIndex((c) => c.ulid === conversation.ulid)
            if (existingIndex === -1) {
                this.conversations.unshift(conversation)
            } else {
                this.conversations[existingIndex] = conversation
            }
            this.openConversation(conversation.ulid)
            if (message) {
                await this.send(conversation.ulid, message)
            }
        },

        async openTaskThread(task: { reference: string; conversation_ulid: string | null }) {
            if (!task.conversation_ulid) return
            if (!this.fetched) await this.fetchConversations()
            if (!this.conversationByUlid(task.conversation_ulid)) {
                const { data } = await axios.get(route("grp.tasks.conversation", task.reference))
                this.visitingConversations.push(data.data)
            }
            this.openConversation(task.conversation_ulid)
        },

        makeRoomForWindow(ulid: string) {
            const visible = this.openWindows.filter((w) => !w.minimised && w.ulid !== ulid)
            const overflow = visible.length - this.maxVisible + 1
            if (overflow <= 0) return

            const makingRoom = visible.slice(0, overflow)
            this.instantlyMinimised = makingRoom.map((w) => w.ulid)
            makingRoom.forEach((w) => (w.minimised = true))
        },

        openConversation(ulid: string) {
            this.makeRoomForWindow(ulid)

            const existing = this.openWindows.find((w) => w.ulid === ulid)
            if (existing) {
                existing.minimised = false
                this.loadMessages(ulid)
                this.markRead(ulid)
                return
            }

            this.openWindows.push({ ulid, minimised: false })
            this.loadMessages(ulid)
            this.markRead(ulid)
        },

        forgetCachedChat(ulid: string) {
            const pending = cacheWriteTimers.get(ulid)
            if (pending) clearTimeout(pending)
            cacheWriteTimers.delete(ulid)

            const userId = currentUserId()
            if (userId) removeCachedChat(userId, ulid)
        },

        dismissWindow(ulid: string) {
            this.openWindows = this.openWindows.filter((w) => w.ulid !== ulid)
            this.forgetCachedChat(ulid)
        },

        handleArchived(e: { conversation_ulid: string; user_id: number }) {
            const myId = usePage().props?.auth?.user?.id
            if (e.user_id !== myId) return
            this.openWindows = this.openWindows.filter((w) => w.ulid !== e.conversation_ulid)
            const index = this.conversations.findIndex((c) => c.ulid === e.conversation_ulid)
            if (index !== -1) {
                this.conversations.splice(index, 1)
            }
        },

        toggleShowHiddenChats() {
            this.showHiddenChats = !this.showHiddenChats
            try {
                localStorage.setItem(SHOW_HIDDEN_CHATS_KEY, this.showHiddenChats ? "1" : "0")
            } catch { }
        },

        async toggleWatch(ulid: string) {
            const conversation = this.conversationByUlid(ulid)
            if (!conversation) return

            const isWatching = !conversation.is_watching
            conversation.is_watching = isWatching
            try {
                await axios.post(route("grp.chat.staff.conversations.watch", ulid), { is_watching: isWatching })
            } catch {
                conversation.is_watching = !isWatching
            }
        },

        closeConversation(ulid: string) {
            if (!canArchiveConversation(this.conversationByUlid(ulid))) return
            this.openWindows = this.openWindows.filter((w) => w.ulid !== ulid)
            this.forgetCachedChat(ulid)
            axios.post(route("grp.chat.staff.conversations.archive", ulid)).catch(() => { })
            const index = this.conversations.findIndex((c) => c.ulid === ulid)
            if (index !== -1) {
                this.conversations.splice(index, 1)
            }
            delete this.messagesByUlid[ulid]
        },

        persistBubbles() {
            try {
                localStorage.setItem(bubblesStorageKey(), JSON.stringify(this.openWindows.map((w) => ({ ulid: w.ulid, minimised: w.minimised }))))
            } catch { }
        },

        restoreBubbles({ reopenWindows = false }: { reopenWindows?: boolean } = {}) {
            const stored = readStoredWindows().filter((entry) => !!this.conversationByUlid(entry.ulid))
            const openHere = this.openWindows.filter((w) => !w.minimised)
            const openUlids = new Set(openHere.map((w) => w.ulid))

            const reopened = reopenWindows
                ? stored.filter((entry) => !entry.minimised && !openUlids.has(entry.ulid)).slice(-Math.max(0, this.maxVisible - openHere.length))
                : []
            const reopenedUlids = new Set(reopened.map((entry) => entry.ulid))

            const bubbles = stored
                .filter((entry) => !openUlids.has(entry.ulid) && !reopenedUlids.has(entry.ulid))
                .slice(-MAX_BUBBLES)
                .map((entry) => ({ ulid: entry.ulid, minimised: true }))

            this.openWindows = [...openHere, ...reopened.map((entry) => ({ ulid: entry.ulid, minimised: false })), ...bubbles]
            reopened.forEach((entry) => this.loadMessages(entry.ulid))
        },

        showAsBubble(ulid: string) {
            if (this.openWindows.some((w) => w.ulid === ulid) || this.fullViewUlid === ulid) return

            const bubbles = this.openWindows.filter((w) => w.minimised)
            if (bubbles.length >= MAX_BUBBLES) {
                const quietest = bubbles.find((w) => !(this.conversationByUlid(w.ulid)?.unread_count ?? 0)) ?? bubbles[0]
                this.openWindows = this.openWindows.filter((w) => w !== quietest)
            }

            this.openWindows.push({ ulid, minimised: true })
        },

        minimiseConversation(ulid: string, minimised: boolean) {
            const w = this.openWindows.find((w) => w.ulid === ulid)
            if (!w) return

            if (!minimised) this.makeRoomForWindow(ulid)
            w.minimised = minimised
            if (!minimised) this.markRead(ulid)
        },

        async loadMessages(ulid: string, beforeId?: number) {
            if (this.loadingMessages[ulid]) return
            this.loadingMessages[ulid] = true

            const request = axios.get(route("grp.chat.staff.conversations.messages.index", ulid), {
                params: beforeId ? { before_id: beforeId } : {},
            })

            const userId = currentUserId()
            if (!beforeId && !this.messagesByUlid[ulid] && userId) {
                readCachedChat(userId, ulid).then((cached) => {
                    if (cached && this.loadingMessages[ulid] && !this.messagesByUlid[ulid]) this.messagesByUlid[ulid] = cached
                })
            }

            try {
                const { data } = await request
                const incoming: StaffMessage[] = data.data
                const current = this.messagesByUlid[ulid] ?? []
                this.messagesByUlid[ulid] = beforeId ? [...incoming, ...current] : [...incoming, ...current.filter((m) => !!m.client_status)]
                if (!beforeId) this.cacheChatSoon(ulid)
            } finally {
                this.loadingMessages[ulid] = false
            }
        },

        messageListOf(ulid: string): StaffMessage[] {
            if (!this.messagesByUlid[ulid]) this.messagesByUlid[ulid] = []

            return this.messagesByUlid[ulid]
        },

        async send(ulid: string, body: string, parentId?: number | null, image?: File | null) {
            if (!image) {
                return this.sendOptimistically(ulid, body, parentId ?? null)
            }

            const form = new FormData()
            if (body) form.append("body", body)
            if (parentId) form.append("parent_id", String(parentId))
            form.append("image", image)

            const { data } = await axios.post(route("grp.chat.staff.conversations.messages.store", ulid), form, { headers: { "Content-Type": "multipart/form-data" } })
            const message: StaffMessage = data.data
            const list = this.messageListOf(ulid)
            if (!list.some((m) => m.id === message.id)) list.push(message)
            this.bumpConversation(ulid, message)
            return message
        },

        async sendOptimistically(ulid: string, body: string, parentId: number | null, retryKey?: string) {
            const me = usePage().props?.auth?.user
            const list = this.messageListOf(ulid)
            const clientKey = retryKey ?? `pending-${Date.now()}-${Math.random().toString(36).slice(2)}`
            const isGif = GIF_URL.test(body)

            const pending: StaffMessage = {
                id: -Date.now(),
                conversation_ulid: ulid,
                user_id: me?.id,
                user_name: me?.username ?? "",
                parent_id: parentId,
                body,
                mentions: [],
                language_id: null,
                translations: {},
                reactions: {},
                image: null,
                gif_url: isGif ? body : null,
                created_at: new Date().toISOString(),
                client_status: "sending",
                client_key: clientKey,
            }

            const existingIndex = list.findIndex((m) => m.client_key === clientKey)
            if (existingIndex === -1) {
                list.push(pending)
            } else {
                list[existingIndex] = pending
            }
            this.bumpConversation(ulid, pending)

            try {
                const { data } = await axios.post(route("grp.chat.staff.conversations.messages.store", ulid), { body, parent_id: parentId ?? undefined })
                const message: StaffMessage = data.data
                const current = this.messageListOf(ulid)
                const pendingIndex = current.findIndex((m) => m.client_key === clientKey)
                const alreadyListed = current.some((m) => m.id === message.id)

                if (pendingIndex !== -1) {
                    if (alreadyListed) {
                        current.splice(pendingIndex, 1)
                    } else {
                        current[pendingIndex] = message
                    }
                } else if (!alreadyListed) {
                    current.push(message)
                }
                this.bumpConversation(ulid, message)
                this.cacheChatSoon(ulid)

                return message
            } catch {
                const current = this.messageListOf(ulid)
                const failedIndex = current.findIndex((m) => m.client_key === clientKey)
                if (failedIndex !== -1) current[failedIndex] = { ...current[failedIndex], client_status: "failed" }

                return null
            }
        },

        retrySend(message: StaffMessage) {
            if (message.client_status !== "failed" || !message.client_key) return

            return this.sendOptimistically(message.conversation_ulid, message.body, message.parent_id, message.client_key)
        },

        discardFailed(message: StaffMessage) {
            const list = this.messagesByUlid[message.conversation_ulid]
            if (!list) return
            this.messagesByUlid[message.conversation_ulid] = list.filter((m) => m.client_key !== message.client_key)
        },

        mergeConversation(fresh: StaffConversation) {
            const existing = this.conversationByUlid(fresh.ulid)
            if (!existing) return
            Object.assign(existing, { participants: fresh.participants, my_left_at: fresh.my_left_at ?? null, task: fresh.task ?? existing.task, name: fresh.name })
        },

        handleRead(e: { conversation_ulid: string; user_id: number; last_read_at: string }) {
            const participant = this.conversationByUlid(e.conversation_ulid)?.participants.find((p) => p.id === e.user_id)
            if (participant) participant.last_read_at = e.last_read_at
        },

        async toggleReaction(message: StaffMessage, emoji: string, userId: number) {
            const isAdding = !(message.reactions?.[emoji] ?? []).includes(userId)
            this.setOwnReaction(message.conversation_ulid, message.id, emoji, userId, isAdding)

            try {
                await axios.post(route("grp.chat.staff.messages.reactions.toggle", message.id), { emoji })
            } catch {
                this.setOwnReaction(message.conversation_ulid, message.id, emoji, userId, !isAdding)
                notify({ title: isAdding ? ctrans("Could not add reaction") : ctrans("Could not remove reaction"), type: "error" })
            }
        },

        setOwnReaction(ulid: string, messageId: number, emoji: string, userId: number, isPresent: boolean) {
            const message = this.messageListOf(ulid).find((m) => m.id === messageId)
            if (!message) return

            const reactors = (message.reactions?.[emoji] ?? []).filter((id) => id !== userId)
            const reactions = { ...(message.reactions ?? {}), [emoji]: isPresent ? [...reactors, userId] : reactors }
            if (!reactions[emoji].length) delete reactions[emoji]
            message.reactions = reactions
        },

        applyReactionBroadcast(incoming: StaffMessage) {
            const userId = currentUserId()
            const local = this.messagesByUlid[incoming.conversation_ulid]?.find((m) => m.id === incoming.id)
            if (!local || !userId) {
                this.replaceMessage(incoming)
                return
            }

            const emojis = new Set([...Object.keys(local.reactions ?? {}), ...Object.keys(incoming.reactions ?? {})])
            const reactions: StaffMessageReactions = {}
            emojis.forEach((emoji) => {
                const others = (incoming.reactions?.[emoji] ?? []).filter((id) => id !== userId)
                const reactors = (local.reactions?.[emoji] ?? []).includes(userId) ? [...others, userId] : others
                if (reactors.length) reactions[emoji] = reactors
            })

            this.replaceMessage({ ...incoming, reactions })
        },

        async markRead(ulid: string) {
            const conversation = this.conversationByUlid(ulid)
            if (conversation) {
                conversation.unread_count = 0
                conversation.has_mention = false
            }
            try {
                await axios.post(route("grp.chat.staff.conversations.read", ulid))
            } catch { }
        },

        bumpConversation(ulid: string, message: StaffMessage) {
            const conversation = this.conversationByUlid(ulid)
            if (!conversation) return
            conversation.last_message_at = message.created_at
            conversation.last_message = message.body
            const index = this.conversations.indexOf(conversation)
            if (index > 0) {
                this.conversations.splice(index, 1)
                this.conversations.unshift(conversation)
            }
        },

        replaceMessage(message: StaffMessage) {
            const list = this.messagesByUlid[message.conversation_ulid]
            if (!list) return
            const index = list.findIndex((m) => m.id === message.id)
            if (index !== -1) {
                list[index] = message
                this.cacheChatSoon(message.conversation_ulid)
            }
        },

        handleIncoming(message: StaffMessage) {
            const ulid = message.conversation_ulid
            const isOpen = this.openWindows.some((w) => w.ulid === ulid && !w.minimised) || this.fullViewUlid === ulid
            const myId = usePage().props?.auth?.user?.id

            const alertMessage = () => alertOnce({
                key: `staff:${message.id}`,
                title: message.user_name,
                body: message.body ?? "",
                tag: `staff-${ulid}`,
                sound: chosenAlertSound("colleague"),
                spoken: ctrans("You have a message from :name", { name: message.user_name }),
                onOpen: () => this.openConversation(ulid),
            })

            let conversation = this.conversationByUlid(ulid)
            if (!conversation) {
                this.fetchConversations().then(() => {
                    const fetched = this.conversationByUlid(ulid)
                    if (fetched && message.user_id !== myId) {
                        this.showAsBubble(ulid)
                        if (isAlerting(fetched)) alertMessage()
                    }
                })
                return
            }

            if (isHiddenChat(conversation) && message.user_id !== myId) {
                this.fetchConversations().catch(() => { })
            }

            const list = this.messagesByUlid[ulid]
            if (list && !list.some((m) => m.id === message.id)) {
                const pendingIndex = message.user_id === myId
                    ? list.findIndex((m) => m.client_status === "sending" && m.body === message.body)
                    : -1

                if (pendingIndex === -1) {
                    list.push(message)
                } else {
                    list[pendingIndex] = message
                }
                this.cacheChatSoon(ulid)
            }

            this.bumpConversation(ulid, message)

            if (message.user_id !== myId && isOpen && document.hasFocus()) {
                this.markRead(ulid)
            }

            if (message.user_id !== myId) {
                const isMentioned = !!message.mentions?.includes(myId)

                if (!isOpen) {
                    conversation.unread_count = (conversation.unread_count || 0) + 1
                    this.showAsBubble(ulid)
                }
                if (isMentioned) {
                    conversation.has_mention = true
                }

                const isSeen = (isOpen && document.hasFocus()) || isOpenInFront(ulid)
                if (isAlerting(conversation) && !isSeen) {
                    alertMessage()
                }
            }
        },

        handleTyping(payload: { conversation_ulid: string; user_id: number; user_name: string }) {
            const myId = usePage().props?.auth?.user?.id
            if (payload.user_id === myId) return
            this.typingByUlid[payload.conversation_ulid] = {
                user_name: payload.user_name,
                expiresAt: Date.now() + 3000,
            }
            setTimeout(() => {
                const entry = this.typingByUlid[payload.conversation_ulid]
                if (entry && entry.expiresAt <= Date.now()) {
                    delete this.typingByUlid[payload.conversation_ulid]
                }
            }, 3000)
        },

        handleArchivedByOther(e: { conversation_ulid: string; user_id: number; user_name: string }) {
            const note: ArchivedNote = {
                id: 'note-' + Date.now(),
                text: e.user_name + ' ' + ctrans('has closed the chat for now'),
                created_at: new Date().toISOString(),
            }
            if (!this.notesByUlid[e.conversation_ulid]) {
                this.notesByUlid[e.conversation_ulid] = []
            }
            this.notesByUlid[e.conversation_ulid].push(note)
        },
    },
})
