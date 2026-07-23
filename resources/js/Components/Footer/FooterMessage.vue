<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 04 Sep 2023 11:19:39 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { trans } from 'laravel-vue-i18n'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { useLayoutStore } from "@/Stores/layout"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faMessage } from '@fortawesome/free-solid-svg-icons'
import { faUser, faInbox, faHeadset, faLockOpen } from '@fal'
import { playNotificationSoundFile, buildStorageUrl } from "@/Composables/useNotificationSound"

library.add(faMessage, faUser, faInbox, faHeadset, faLockOpen)

interface NotificationItem {
    ulid: string
    status: string
    contact_name: string
    last_message?: { message?: string } | null
    unread_count: number
    shop?: { id: number; name: string } | null
    organisation?: { id: number; name: string; slug: string } | null
}

const layout: any = useLayoutStore()
const baseUrl = layout?.appUrl ?? ""
const myAgentId = layout?.user?.id
const agentShops: number[] = Array.isArray(layout?.user?.agent_shops) ? layout.user.agent_shops : []
const soundUrl = buildStorageUrl("sound/notification.mp3", baseUrl)

const showPopover = ref(false)
const isLoading = ref(false)
const waiting = ref<NotificationItem[]>([])
const active = ref<NotificationItem[]>([])
const reopen = ref<NotificationItem[]>([])

const totalCount = computed(() => waiting.value.length + active.value.length + reopen.value.length)

const totalUnreadMessages = computed(() =>
    [...waiting.value, ...active.value, ...reopen.value]
        .reduce((sum, item) => sum + (item.unread_count ?? 0), 0)
)

const groups = computed(() => [
    {
        key: 'waiting',
        label: trans('New — unassigned'),
        icon: faInbox,
        color: 'text-amber-500',
        items: waiting.value,
    },
    {
        key: 'active',
        label: trans('Assigned to me'),
        icon: faHeadset,
        color: 'text-green-500',
        items: active.value,
    },
    {
        key: 'reopen',
        label: trans('Closed — new message'),
        icon: faLockOpen,
        color: 'text-indigo-500',
        items: reopen.value,
    },
])

const fetchNotifications = async () => {
    if (!myAgentId) return
    isLoading.value = true
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/users/${myAgentId}/agent-notifications`)
        waiting.value = data?.data?.waiting ?? []
        active.value = data?.data?.active ?? []
        reopen.value = data?.data?.reopen ?? []
    } catch (e) {
        console.error("Failed to fetch chat notifications", e)
    } finally {
        isLoading.value = false
    }
}

const togglePopover = async () => {
    showPopover.value = !showPopover.value
    if (showPopover.value) {
        await fetchNotifications()
    }
}

const closePopover = () => {
    showPopover.value = false
}

const openConversation = (item: NotificationItem) => {
    const orgSlug = item.organisation?.slug
    if (!orgSlug) return
    closePopover()
    router.visit(route('grp.org.chat.inbox.conversation', [orgSlug, item.ulid]))
}

const lastMessagePreview = (item: NotificationItem) => {
    const msg = item.last_message?.message ?? ""
    return msg.length > 40 ? msg.slice(0, 40) + "…" : msg
}

const joinedChannels: string[] = []
let pollTimer: ReturnType<typeof setInterval> | null = null

const isEchoReady = () =>
    typeof window !== "undefined" &&
    (window as any).Echo?.connector?.pusher

const waitEchoReady = (callback: () => void) => {
    if (isEchoReady()) {
        callback()
        return
    }
    const interval = setInterval(() => {
        if (isEchoReady()) {
            clearInterval(interval)
            callback()
        }
    }, 300)
}

const handleChatListEvent = async (e: any) => {
    const msg = e?.message

    // Assignment/status-only events (assign, take-over, close, reopen) carry no
    // message: just refresh the counts, no sound.
    if (!msg) {
        await fetchNotifications()
        return
    }

    if (msg.sender_type === "agent") return

    // Assigned chats only notify their assigned agent; unassigned (waiting)
    // chats have no assigned_user_id and notify every agent of the shop.
    if (msg.assigned_user_id && msg.assigned_user_id !== myAgentId) return

    playNotificationSoundFile(soundUrl)
    await fetchNotifications()
}

const subscribeChannels = () => {
    agentShops
        .filter((shopId) => shopId !== null && shopId !== undefined)
        .forEach((shopId) => {
            const channel = `chat-list.${shopId}`
            if (joinedChannels.includes(channel)) return
            joinedChannels.push(channel)
            window.Echo.join(channel).listen(".chatlist", handleChatListEvent)
        })
}

onMounted(() => {
    if (!myAgentId) return

    fetchNotifications()

    waitEchoReady(subscribeChannels)

    // Safety net: keep the badge fresh even if a broadcast is missed
    // or the agent handles shops org-wide (no per-shop channel).
    pollTimer = setInterval(() => {
        if (!showPopover.value) fetchNotifications()
    }, 30000)
})

onUnmounted(() => {
    joinedChannels.forEach((channel) => window.Echo?.leave(channel))
    if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
    <div class="relative h-full">
        <!-- Trigger -->
        <div class="group inline-flex items-center px-3 h-full font-medium hover:bg-gray-800 text-gray-200 cursor-pointer"
            :class="showPopover ? 'bg-gray-800' : ''" @click="togglePopover">
            <div class="relative flex items-center gap-2 text-xs">
                <div class="relative flex items-center justify-center w-4 h-4">
                    <FontAwesomeIcon :icon="faMessage" class="text-[12px]" />
                    <span v-if="totalUnreadMessages > 0" class="absolute -top-5 left-1/2 -translate-x-1/2 px-2 py-[2px]
                        bg-red-500 text-white text-[9px] font-semibold rounded-full whitespace-nowrap animate-pulse">
                        {{ trans('New Messages') }} ({{ totalUnreadMessages }})
                    </span>
                </div>
                <span>{{ trans('Message') }}</span>
            </div>
        </div>

        <!-- Backdrop -->
        <div v-if="showPopover" class="fixed inset-0 z-[9998]" @click="closePopover" />

        <!-- Popover (opens upward from the footer) -->
        <div v-if="showPopover"
            class="absolute bottom-full right-0 mb-2 z-[9999] w-[360px] max-w-[92vw] bg-white text-gray-800 rounded-lg shadow-2xl border border-gray-200 flex flex-col overflow-hidden"
            @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between px-3 py-2 border-b bg-gray-50">
                <span class="text-sm font-semibold text-gray-700">{{ trans('Incoming chats') }}</span>
                <span class="text-[11px] text-gray-400">{{ totalCount }}</span>
            </div>

            <!-- Scrollable list -->
            <div class="max-h-[60vh] overflow-y-auto">
                <div v-if="isLoading && totalCount === 0" class="px-3 py-6 text-center text-xs text-gray-400">
                    {{ trans('Loading…') }}
                </div>

                <div v-else-if="totalCount === 0" class="px-3 py-8 text-center">
                    <div class="text-2xl">💬</div>
                    <div class="text-sm font-medium text-gray-700 mt-1">{{ trans('No new chats') }}</div>
                    <div class="text-xs text-gray-500">{{ trans('You are all caught up') }}</div>
                </div>

                <template v-else>
                    <div v-for="group in groups" :key="group.key">
                        <div v-if="group.items.length" class="border-b">
                            <!-- Group header -->
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 sticky top-0 z-[1]">
                                <FontAwesomeIcon :icon="group.icon" :class="group.color" class="text-[11px]" />
                                <span class="text-[11px] font-semibold text-gray-600 uppercase tracking-wide">
                                    {{ group.label }}
                                </span>
                                <span class="text-[10px] text-gray-400">({{ group.items.length }})</span>
                            </div>

                            <!-- Items -->
                            <div v-for="item in group.items" :key="item.ulid"
                                class="flex items-center gap-3 px-3 py-2 border-b last:border-b-0 hover:bg-gray-50 cursor-pointer"
                                @click="openConversation(item)">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-gray-100 text-gray-500">
                                    <FontAwesomeIcon :icon="faUser" class="text-sm" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-gray-800 truncate">
                                            {{ item.contact_name }}
                                        </span>
                                        <span v-if="item.unread_count"
                                            class="min-w-[16px] px-1.5 text-[10px] leading-4 text-white rounded-full text-center shrink-0 bg-red-500">
                                            {{ item.unread_count }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] text-gray-400">
                                        <span v-if="item.shop?.name" class="truncate">{{ item.shop.name }}</span>
                                        <span v-if="item.organisation?.name" class="truncate">· {{ item.organisation.name }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 truncate">{{ lastMessagePreview(item) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
