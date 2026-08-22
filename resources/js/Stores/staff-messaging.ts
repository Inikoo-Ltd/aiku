/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { defineStore } from "pinia"
import axios from "axios"
import { usePage } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { useLayoutStore } from "@/Stores/layout"
import { playNotificationSoundFile, buildStorageUrl } from "@/Composables/useNotificationSound"

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
}

export interface StaffParticipant {
    id: number
    name: string
    handle: string | null
    avatar: any
    last_seen_at?: string | null
}

export interface StaffConversation {
    ulid: string
    type: "dm" | "group"
    name: string | null
    participants: StaffParticipant[]
    last_message_at: string | null
    last_message: string | null
    unread_count: number
    has_mention: boolean
    context_label?: string | null
    context_url?: string | null
}

export interface StaffCoworker {
    id: number
    name: string
    avatar: any
    is_close: boolean
    in_team: boolean
    last_active_at?: number | null
}

interface WindowState {
    ulid: string
    minimised: boolean
    x: number | null
    y: number | null
}

interface ArchivedNote {
    id: string
    text: string
    created_at: string
}

const bubblePositionKey = (ulid: string) => `staff-chat-bubble-${ulid}`

export const useStaffMessaging = defineStore("staff-messaging", {
    state: () => ({
        conversations: [] as StaffConversation[],
        messagesByUlid: {} as Record<string, StaffMessage[]>,
        notesByUlid: {} as Record<string, ArchivedNote[]>,
        openWindows: [] as WindowState[],
        fullViewUlid: null as string | null,
        typingByUlid: {} as Record<string, { user_name: string; expiresAt: number }>,
        loadingMessages: {} as Record<string, boolean>,
        fetched: false,
        maxVisible: 3,
    }),

    getters: {
        totalUnread: (state) => state.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0),
        openWindowsVisible: (state) => state.openWindows.filter((w) => !w.minimised),
        openWindowsMinimised: (state) => state.openWindows.filter((w) => w.minimised),
        conversationByUlid: (state) => (ulid: string) => state.conversations.find((c) => c.ulid === ulid),
    },

    actions: {
        async fetchConversations() {
            const { data } = await axios.get(route("grp.chat.staff.conversations.index"))
            this.conversations = data.data
            this.fetched = true
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

        async openContext(contextType: string, contextId: number, audience: string) {
            const { data } = await axios.post(route("grp.chat.staff.context.open"), { context_type: contextType, context_id: contextId, audience })
            const conversation: StaffConversation = data.data
            const existingIndex = this.conversations.findIndex((c) => c.ulid === conversation.ulid)
            if (existingIndex === -1) {
                this.conversations.unshift(conversation)
            } else {
                this.conversations[existingIndex] = conversation
            }
            this.openConversation(conversation.ulid)
        },

        openConversation(ulid: string) {
            const existing = this.openWindows.find((w) => w.ulid === ulid)
            if (existing) {
                existing.minimised = false
                this.loadMessages(ulid)
                this.markRead(ulid)
                return
            }

            const visible = this.openWindows.filter((w) => !w.minimised)
            if (visible.length >= this.maxVisible) {
                const oldest = visible[0]
                oldest.minimised = true
            }

            let x: number | null = null
            let y: number | null = null
            try {
                const raw = localStorage.getItem(bubblePositionKey(ulid))
                if (raw) {
                    const parsed = JSON.parse(raw)
                    x = parsed.x
                    y = parsed.y
                }
            } catch { }

            this.openWindows.push({ ulid, minimised: false, x, y })
            this.loadMessages(ulid)
            this.markRead(ulid)
        },

        dismissWindow(ulid: string) {
            this.openWindows = this.openWindows.filter((w) => w.ulid !== ulid)
        },

        closeConversation(ulid: string) {
            this.openWindows = this.openWindows.filter((w) => w.ulid !== ulid)
            axios.post(route("grp.chat.staff.conversations.archive", ulid)).catch(() => { })
            const index = this.conversations.findIndex((c) => c.ulid === ulid)
            if (index !== -1) {
                this.conversations.splice(index, 1)
            }
            delete this.messagesByUlid[ulid]
        },

        minimiseConversation(ulid: string, minimised: boolean) {
            const w = this.openWindows.find((w) => w.ulid === ulid)
            if (w) {
                w.minimised = minimised
                if (!minimised) {
                    this.markRead(ulid)
                }
            }
        },

        setBubblePosition(ulid: string, x: number, y: number) {
            const w = this.openWindows.find((w) => w.ulid === ulid)
            if (w) {
                w.x = x
                w.y = y
            }
            try {
                localStorage.setItem(bubblePositionKey(ulid), JSON.stringify({ x, y }))
            } catch { }
        },

        async loadMessages(ulid: string, beforeId?: number) {
            if (this.loadingMessages[ulid]) return
            this.loadingMessages[ulid] = true
            try {
                const { data } = await axios.get(route("grp.chat.staff.conversations.messages.index", ulid), {
                    params: beforeId ? { before_id: beforeId } : {},
                })
                const incoming: StaffMessage[] = data.data
                const current = this.messagesByUlid[ulid] ?? []
                this.messagesByUlid[ulid] = beforeId ? [...incoming, ...current] : incoming
            } finally {
                this.loadingMessages[ulid] = false
            }
        },

        async send(ulid: string, body: string, parentId?: number | null, image?: File | null) {
            let payload: any
            let config: any = {}
            if (image) {
                const form = new FormData()
                if (body) form.append("body", body)
                if (parentId) form.append("parent_id", String(parentId))
                form.append("image", image)
                payload = form
                config = { headers: { "Content-Type": "multipart/form-data" } }
            } else {
                payload = { body, parent_id: parentId ?? undefined }
            }
            const { data } = await axios.post(route("grp.chat.staff.conversations.messages.store", ulid), payload, config)
            const message: StaffMessage = data.data
            const list = this.messagesByUlid[ulid] ?? (this.messagesByUlid[ulid] = [])
            if (!list.some((m) => m.id === message.id)) list.push(message)
            this.bumpConversation(ulid, message)
            return message
        },

        async toggleReaction(messageId: number, emoji: string) {
            await axios.post(route("grp.chat.staff.messages.reactions.toggle", messageId), { emoji })
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
            }
        },

        handleIncoming(message: StaffMessage) {
            const ulid = message.conversation_ulid
            const isOpen = this.openWindows.some((w) => w.ulid === ulid && !w.minimised) || this.fullViewUlid === ulid
            const myId = usePage().props?.auth?.user?.id

            let conversation = this.conversationByUlid(ulid)
            if (!conversation) {
                this.fetchConversations()
                return
            }

            const list = this.messagesByUlid[ulid]
            if (list && !list.some((m) => m.id === message.id)) {
                list.push(message)
            }

            this.bumpConversation(ulid, message)

            if (message.user_id !== myId) {
                const isMentioned = !!message.mentions?.includes(myId)

                if (!isOpen) {
                    conversation.unread_count = (conversation.unread_count || 0) + 1
                }
                if (isMentioned) {
                    conversation.has_mention = true
                }

                if (document.hidden || !isOpen || isMentioned) {
                    const layout: any = useLayoutStore()
                    playNotificationSoundFile(buildStorageUrl("sound/notification.mp3", layout?.appUrl)).catch(() => { })
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
                text: e.user_name + ' ' + trans('has closed the chat for now'),
                created_at: new Date().toISOString(),
            }
            if (!this.notesByUlid[e.conversation_ulid]) {
                this.notesByUlid[e.conversation_ulid] = []
            }
            this.notesByUlid[e.conversation_ulid].push(note)
        },
    },
})
