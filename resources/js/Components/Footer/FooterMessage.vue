<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 04 Sep 2023 11:19:39 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import axios from 'axios'
import { watchDebounced } from '@vueuse/core'
import { useLayoutStore } from "@/Stores/layout"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faMessage } from '@fortawesome/free-solid-svg-icons'
import { faUser, faEdit, faChevronDown, faSearch, faSlidersH, faPaperclip } from '@fal'
import Image from '@common/Components/Image.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import MiniChatWindow from '@/Components/Chat/MiniChatWindow.vue'
import { playNotificationSoundFile, buildStorageUrl, fetchUnreadCount, totalUnread } from "@/Composables/useNotificationSound"
import { useMiniChats } from "@/Composables/useMiniChats"

library.add(faMessage, faUser, faEdit, faChevronDown, faSearch, faSlidersH, faPaperclip)

interface ChatSessionItem {
    ulid: string
    status: string
    contact_name: string
    unread_count: number
    last_message?: {
        message?: string
        sender_type?: string | null
        created_at?: string | null
    } | null
    web_user?: { id: number; image?: any } | null
    guest_profile?: { image?: any } | null
    shop?: { id: number; name: string } | null
    organisation?: { id: number; name: string; slug: string } | null
}

const TABS = [
    { key: 'active', label: 'Active', statuses: ['active'] },
    { key: 'waiting', label: 'Waiting', statuses: ['waiting'] },
]

const layout: any = useLayoutStore()
const baseUrl = layout?.appUrl ?? ""
const myAgentId = layout?.user?.id
const agentShops: number[] = Array.isArray(layout?.user?.agent_shops) ? layout.user.agent_shops : []
const soundUrl = buildStorageUrl("sound/notification.mp3", baseUrl)

const showPopover = ref(false)
const isLoading = ref(false)
const isLoadingMore = ref(false)
const sessions = ref<ChatSessionItem[]>([])
const activeTab = ref(TABS[0].key)
const searchQuery = ref('')
const showSearch = ref(true)
const currentPage = ref(1)
const hasMore = ref(false)

const currentOrganisation = computed(() =>
    layout?.organisations?.data?.find((organisation: any) => organisation.slug === layout?.currentParams?.organisation) ?? null
)

const currentShopId = computed(() =>
    currentOrganisation.value?.authorised_shops?.find((shop: any) => shop.slug === layout?.currentParams?.shop)?.id ?? null
)

const activeStatuses = computed(() => TABS.find(tab => tab.key === activeTab.value)?.statuses ?? [])

const buildParams = (page: number) => ({
    statuses: activeStatuses.value,
    assigned_to_me: myAgentId,
    page,
    
    ...(currentOrganisation.value?.id ? { organisation_id: currentOrganisation.value.id } : {}),
    ...(currentShopId.value ? { shop_id: currentShopId.value } : {}),
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
})

const fetchSessions = async () => {
    if (!myAgentId) return
    isLoading.value = true
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params: buildParams(1) })
        sessions.value = data?.data?.sessions ?? []
        hasMore.value = data?.data?.pagination?.has_more ?? false
        currentPage.value = 1
    } catch (e) {
        console.error("Failed to fetch chat sessions", e)
    } finally {
        isLoading.value = false
    }
}

const loadMore = async () => {
    if (isLoadingMore.value || !hasMore.value) return
    isLoadingMore.value = true
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params: buildParams(currentPage.value + 1) })
        sessions.value = [...sessions.value, ...(data?.data?.sessions ?? [])]
        hasMore.value = data?.data?.pagination?.has_more ?? false
        currentPage.value += 1
    } catch (e) {
        console.error("Failed to load more chat sessions", e)
    } finally {
        isLoadingMore.value = false
    }
}

const onListScroll = (event: Event) => {
    const el = event.target as HTMLElement
    if (el.scrollHeight - el.scrollTop - el.clientHeight < 60) {
        loadMore()
    }
}

const togglePopover = async () => {
    showPopover.value = !showPopover.value
    if (showPopover.value) {
        await fetchSessions()
    }
}

const closePopover = () => {
    showPopover.value = false
}



const { miniChats, openMiniChat, closeMiniChat, toggleMiniChat, trackNavigation } = useMiniChats()

const openConversation = (item: ChatSessionItem) => {
    openMiniChat({
        ulid: item.ulid,
        contactName: item.contact_name,
        avatar: sessionAvatar(item),
        status: item.status,
        organisationSlug: item.organisation?.slug ?? null,
        shopName: item.shop?.name ?? null,
    })
}

watch(activeTab, () => fetchSessions())
watchDebounced(searchQuery, () => fetchSessions(), { debounce: 400 })

const sessionAvatar = (item: ChatSessionItem) => item.web_user?.image ?? item.guest_profile?.image ?? null

const isOnline = (item: ChatSessionItem) => item.status === 'active'

const formatDate = (value?: string | null) => {
    if (!value) return ""

    const date = new Date(value)
    const now = new Date()
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())

    if (date >= startOfToday) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    }

    if (date.getFullYear() === now.getFullYear()) {
        return date.toLocaleDateString([], { day: 'numeric', month: 'short' })
    }

    return date.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' })
}

const senderPrefix = (item: ChatSessionItem) => {
    const senderType = item.last_message?.sender_type
    if (!senderType) return ""
    if (senderType === 'agent') return trans('You')
    return item.contact_name?.split(' ')[0] ?? ""
}

const isAttachment = (item: ChatSessionItem) => !item.last_message?.message

const messagePreview = (item: ChatSessionItem) => {
    const prefix = senderPrefix(item)
    const message = item.last_message?.message ?? ""

    if (isAttachment(item)) {
        return prefix ? trans(':sender sent an attachment', { sender: prefix }) : trans('Sent an attachment')
    }

    return prefix ? `${prefix}: ${message}` : message
}

const joinedChannels: string[] = []
let pollTimer: ReturnType<typeof setInterval> | null = null
let stopNavigationTracking: (() => void) | null = null

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

const refreshUnread = () => fetchUnreadCount(baseUrl, activeTab.value, myAgentId)

const handleChatListEvent = async (e: any) => {
    const msg = e?.message

    // Assignment/status-only events (assign, take-over, close, reopen) carry no
    // message: just refresh the counts, no sound.
    if (!msg) {
        await refreshUnread()
        if (showPopover.value) await fetchSessions()
        return
    }

    if (msg.sender_type === "agent") return

    // Assigned chats only notify their assigned agent; unassigned (waiting)
    // chats have no assigned_user_id and notify every agent of the shop.
    if (msg.assigned_user_id && msg.assigned_user_id !== myAgentId) return

    playNotificationSoundFile(soundUrl)
    await refreshUnread()
    if (showPopover.value) await fetchSessions()
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
    stopNavigationTracking = trackNavigation()

    if (!myAgentId) return

    refreshUnread()

    waitEchoReady(subscribeChannels)

    // Safety net: keep the badge fresh even if a broadcast is missed
    // or the agent handles shops org-wide (no per-shop channel).
    pollTimer = setInterval(() => {
        if (!showPopover.value) refreshUnread()
    }, 30000)
})

onUnmounted(() => {
    joinedChannels.forEach((channel) => window.Echo?.leave(channel))
    if (pollTimer) clearInterval(pollTimer)
    stopNavigationTracking?.()
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
                    <span v-if="totalUnread > 0" class="absolute -top-5 left-1/2 -translate-x-1/2 px-2 py-[2px]
                        bg-red-500 text-white text-[9px] font-semibold rounded-full whitespace-nowrap animate-pulse">
                        {{ trans('New Messages') }} ({{ totalUnread }})
                    </span>
                </div>
                <span>{{ trans('Message') }}</span>
            </div>
        </div>

        <!-- Mini chats docked above the footer -->
        <div v-if="miniChats.length" class="absolute bottom-full right-0 z-[9999] flex items-end gap-2 transition-[margin] duration-200"
            :style="{ marginRight: showPopover ? '348px' : '0px' }">
            <MiniChatWindow v-for="chat in miniChats" :key="chat.ulid" :chat="chat"
                @close="closeMiniChat(chat.ulid)" @toggle="toggleMiniChat(chat.ulid)" @read="refreshUnread" />
        </div>

        <!-- Backdrop -->
        <div v-if="showPopover" class="fixed inset-0 z-[9998]" @click="closePopover" />

        <!-- Popover (opens upward from the footer) -->
        <div v-if="showPopover"
            class="absolute bottom-full right-0 mb-2 z-[9999] w-[340px] max-w-[92vw] h-[520px] max-h-[80vh] bg-white text-gray-800 rounded-t-lg shadow-2xl border border-gray-200 flex flex-col overflow-hidden"
            @click.stop>
            <!-- Header -->
            <div class="flex items-center justify-between gap-2 px-3 py-2.5 shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="relative w-8 h-8 shrink-0">
                        <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center text-gray-400">
                            <Image v-if="layout?.avatar_thumbnail" :src="layout.avatar_thumbnail"
                                class="w-full h-full object-cover" />
                            <FontAwesomeIcon v-else :icon="faUser" class="text-xs" />
                        </div>
                    </div>
                    <span class="text-base font-semibold text-gray-900 truncate">{{ trans('Messages') }}</span>
                </div>

                <div class="flex items-center gap-1 shrink-0 text-gray-500">
                    <button class="w-8 h-8 rounded-full hover:bg-gray-100" v-tooltip="trans('Close')"
                        @click="closePopover">
                        <FontAwesomeIcon :icon="faChevronDown" class="text-sm" />
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div class="px-3 pb-2 shrink-0">
                <div class="relative">
                    <FontAwesomeIcon :icon="faSearch"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none" />
                    <input v-model="searchQuery" type="text" :placeholder="trans('Search messages')"
                        class="w-full pl-8 pr-9 py-1.5 text-xs border border-gray-300 rounded bg-white focus:outline-none focus:border-gray-400 focus:ring-0" />
                   <!--  <button class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded hover:bg-gray-100 text-gray-500"
                        v-tooltip="trans('Filter by shop')" @click="showSearch = !showSearch">
                        <FontAwesomeIcon :icon="faSlidersH" class="text-xs" />
                    </button> -->
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 text-sm shrink-0">
                <button v-for="tab in TABS" :key="tab.key" class="flex-1 py-2 border-b-2 -mb-px transition-colors"
                    :class="activeTab === tab.key
                        ? 'font-semibold'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                    :style="activeTab === tab.key
                        ? { color: 'var(--theme-color-4)', borderBottomColor: 'var(--theme-color-4)' }
                        : {}"
                    @click="activeTab = tab.key">
                    {{ trans(tab.label) }}
                </button>
            </div>

            <!-- Conversations -->
            <div class="flex-1 min-h-0 overflow-y-auto" @scroll="onListScroll">
                <div v-if="isLoading && sessions.length === 0" class="h-full flex items-center justify-center">
                    <LoadingIcon class="w-6 h-6 text-gray-400" />
                </div>

                <div v-else-if="sessions.length === 0"
                    class="h-full flex flex-col items-center justify-center px-3 text-center">
                    <div class="text-2xl">💬</div>
                    <div class="text-sm font-medium text-gray-700 mt-1">{{ trans('No conversations') }}</div>
                    <div class="text-xs text-gray-500">{{ trans('You are all caught up') }}</div>
                </div>

                <template v-else>
                    <button v-for="item in sessions" :key="item.ulid" type="button"
                        class="w-full flex items-center gap-3 px-3 py-2.5 text-left border-b border-gray-100 hover:bg-gray-50"
                        @click="openConversation(item)">
                        <div class="relative w-12 h-12 shrink-0">
                            <div class="w-12 h-12 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center text-gray-400">
                                <Image v-if="sessionAvatar(item)" :src="sessionAvatar(item)"
                                    class="w-full h-full object-cover" />
                                <FontAwesomeIcon v-else :icon="faUser" class="text-base" />
                            </div>
                           
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-sm truncate"
                                    :class="item.unread_count ? 'font-semibold text-gray-900' : 'text-gray-800'">
                                    {{ item.contact_name }}
                                </span>
                                <span class="text-[11px] text-gray-400 shrink-0">
                                    {{ formatDate(item.last_message?.created_at) }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1 text-xs"
                                :class="item.unread_count ? 'text-gray-900 font-medium' : 'text-gray-500'">
                                <FontAwesomeIcon v-if="isAttachment(item)" :icon="faPaperclip"
                                    class="text-[10px] shrink-0" />
                                <span class="truncate">{{ messagePreview(item) }}</span>
                            </div>
                        </div>
                    </button>

                    <div v-if="isLoadingMore" class="flex justify-center py-3">
                        <LoadingIcon class="w-5 h-5 text-gray-400" />
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
