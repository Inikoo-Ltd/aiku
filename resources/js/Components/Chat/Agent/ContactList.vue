<script setup lang="ts">
import { ref, inject, computed, watch, onMounted, onUnmounted, nextTick, watchEffect } from "vue"
import { watchDebounced } from "@vueuse/core"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { capitalize } from "@/Composables/capitalize"
import { Contact, SessionAPI, ChatMessage } from "@/types/Chat/chat"
import MessageAreaAgent from "@/Components/Chat/Agent/MessageAreaAgent.vue"
import { routeType } from "@/types/route"
import ChatSidePanel from "@/Components/Chat/ChatSidePanel.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { faUser, faCog, faSearch, faTimes } from "@far"
import { faChevronUp, faChevronDown, faChevronDoubleUp, faEquals } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@common/Components/Image.vue"
import SettingChat from "../SettingChat.vue"
import Dialog from 'primevue/dialog';
import { playNotificationSoundFile, buildStorageUrl, fetchUnreadCount } from "@/Composables/useNotificationSound"


const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const soundUrl = buildStorageUrl("sound/notification.mp3", baseUrl)

const contacts = ref<Contact[]>([])
const selectedSession = ref<SessionAPI | null>(null)
const messages = ref<ChatMessage[]>([])
const activeTab = ref("waiting")
const viewMode = ref<"my" | "team">("my")
const isAssigning = ref<Record<string, boolean>>({})
const errorPerContact = ref<Record<string, string>>({})

const currentPage = ref(1)
const hasMore = ref(false)
const isLoadingMore = ref(false)
const sentinelEl = ref<HTMLElement | null>(null)
let scrollObserver: IntersectionObserver | null = null

const showSearch = ref(false)
const searchQuery = ref("")

const toggleSearch = () => {
    showSearch.value = !showSearch.value
    if (!showSearch.value) searchQuery.value = ""
}

const sidePanelVisible = ref(false)
const sidePanelInitialTab = ref<"history" | "profile" | "message-details">("history")

const showHistoryPanel = () => {
    sidePanelInitialTab.value = "history"
    sidePanelVisible.value = true
}
const showProfilePanel = () => {
    sidePanelInitialTab.value = "profile"
    sidePanelVisible.value = true
}
const showMessageDetailsPanel = () => {
    sidePanelInitialTab.value = "message-details"
    sidePanelVisible.value = true
}

const closeSidePanel = () => {
    sidePanelVisible.value = false
}

// settingchat
const chatSettingVisible = ref(false)
const selectedContact = ref<Contact | null>(null)
const openGlobalChatSettings = () => {
    chatSettingVisible.value = true
}

const PLUS_8_HOURS = layout.app?.environment === "local" ? 8 * 60 * 60 * 1000 : 0

const mapSession = (s: SessionAPI): Contact => ({
    id: s.id,
    ulid: s.ulid,
    name: s.contact_name || s.guest_identifier || "",
    avatar: s.image,
    lastMessage: s.last_message?.message ?? "",
    lastMessageTime: s.last_message?.created_at
        ? formatTime(new Date(s.last_message.created_at).getTime() + PLUS_8_HOURS)
        : undefined,
    unread: s.unread_count,
    status: s.status,
    webUser: s.web_user,
    priority: s.priority,
    guest_profile: s.guest_profile,
    agent: s.assigned_agent,
    shop: s.shop,
    organisation: s.organisation,
})

const buildParams = (page: number) => ({
    statuses: [activeTab.value],
    assigned_to_me: layout?.user?.id,
    page,
    ...(viewMode.value === "team" ? { view_team: 1 } : {}),
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
})

const reloadContacts = async () => {
    currentPage.value = 1
    hasMore.value = false
    try {
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params: buildParams(1) })
        contacts.value = res.data.data.sessions.map(mapSession)
        hasMore.value = res.data.data.pagination?.has_more ?? false
    } catch (e) {
        console.error("Failed to reload contacts:", e)
    }
}

const loadMore = async () => {
    if (isLoadingMore.value || !hasMore.value) return
    isLoadingMore.value = true
    try {
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params: buildParams(currentPage.value + 1) })
        const newContacts = res.data.data.sessions.map(mapSession)
        contacts.value = [...contacts.value, ...newContacts]
        currentPage.value += 1
        hasMore.value = res.data.data.pagination?.has_more ?? false
    } catch (e) {
        console.error("Failed to load more contacts:", e)
    } finally {
        isLoadingMore.value = false
    }
}

watchDebounced(searchQuery, () => reloadContacts(), { debounce: 400 })
const waitEchoReady = (callback: Function) => {
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        callback()
        return
    }

    const interval = setInterval(() => {
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            clearInterval(interval)
            callback()
        }
    }, 300)
}

const notifiedMessages = new Set<string>()
const myAgentId = layout.user?.id
const myAgentShop = layout.user?.agent_shops ?? []
const processedUnreadIds = new Set<number>()

onMounted(async () => {
    waitEchoReady(() => {
        window.Echo.join("chat-list").listen(".chatlist", async (e: any) => {
            const msg = e.message
            if (!msg) return
            if (msg.sender_type === "agent") return
            if (msg.shop_id && Array.isArray(myAgentShop) && !myAgentShop.includes(msg.shop_id)) {
                return
            }
            if (msg.assigned_user_id && myAgentId && msg.assigned_user_id !== myAgentId) return

            const senderDisplay =
                msg.sender_name?.trim() ||
                (msg.sender_type === "guest" ? "Guest" : "User")

            const duplicate = `${msg.sender_name}-${msg.text}`

            if (notifiedMessages.has(duplicate)) return

            playNotificationSoundFile(soundUrl)
            await fetchUnreadCount(baseUrl, activeTab.value, myAgentId)

            if (Notification.permission === "granted") {
                new Notification(senderDisplay, {
                    body: msg.text ?? "New message",
                    tag: duplicate
                })

                // notifiedMessages.add(duplicate)
            }
            reloadContacts()
        })
    })
})

onUnmounted(() => {
    window.Echo.leave("chat-list")
    scrollObserver?.disconnect()
})

watchEffect(() => {
    scrollObserver?.disconnect()
    if (!sentinelEl.value) return
    scrollObserver = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) loadMore() },
        { threshold: 0.1 }
    )
    scrollObserver.observe(sentinelEl.value)
})

const formatTime = (timestamp: string) => {
    if (!timestamp) return ""

    const date = new Date(timestamp)
    const now = new Date()

    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
    const startOfYesterday = new Date(startOfToday.getTime() - 86400000)
    const threeDaysAgo = new Date(startOfToday.getTime() - 3 * 86400000)

    if (date >= startOfToday) {
        return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
    } else if (date >= startOfYesterday) {
        return trans("Yesterday")
    } else if (date >= threeDaysAgo) {
        return date.toLocaleDateString([], { weekday: "short" })
    } else {
        return date.toLocaleDateString([], { day: "2-digit", month: "short" })
    }
}

const priorityConfig = (p?: string) => {
    switch (String(p || "").toLowerCase()) {
        case "urgent": return { icon: faChevronDoubleUp, color: "text-red-500",    label: "Urgent" }
        case "high":   return { icon: faChevronUp,  color: "text-yellow-500", label: "High" }
        case "normal": return { icon: faEquals, color: "text-gray-400",  label: "Normal" }
        case "low":    return { icon: faChevronDown, color: "text-blue-400",  label: "Low" }
        default:       return null
    }
}

watch(viewMode, (mode) => {
    selectedSession.value = null
    messages.value = []
    if (mode === "team" && activeTab.value === "waiting") {
        activeTab.value = "active"
    } else {
        reloadContacts()
    }
})

watch(activeTab, () => {
    selectedSession.value = null
    messages.value = []
    reloadContacts()
})

const filteredContacts = computed(() => contacts.value.filter((c) => c.status === activeTab.value))

const openChat = (c: Contact) => {
    selectedSession.value = {
        ulid: String(c.ulid),
        guest_identifier: c.name,
        status: c.status,
        priority: c.priority,
        web_user: c.webUser,
        guest_profile: c.guest_profile,
        assigned_agent: c.agent,
        shop: c.shop,
        organisation: c.organisation,
    } as SessionAPI
    messages.value = c.messages ?? []
}

const back = () => {
    selectedSession.value = null
}

const handleSendMessage = async ({ text, image, message_type, tempId, is_email_notif }: {
    text: string
    image?: File | null
    message_type: "text" | "image" | "file"
    tempId: number
    is_email_notif: boolean
}) => {
    if (!selectedSession.value?.ulid) return

    try {
        const organisation = route().params?.organisation ?? "aw"

        const formData = new FormData()
        formData.append("message_text", text ?? "")
        formData.append("message_type", message_type)
        formData.append("sender_type", "agent")
        formData.append("is_email_notif", is_email_notif ?? false)

        if (image) {
            formData.append(
                message_type === "image" ? "image" : "file",
                image
            )
        }

        const assignRoute: routeType = {
            name: "grp.org.chat.agents.messages.send",
            parameters: [organisation, selectedSession.value.ulid],
            method: "post",
        }

        await axios.post(route(assignRoute.name, assignRoute.parameters), formData, {
            headers: { "Content-Type": "multipart/form-data" },
            withCredentials: true,
        })
    } catch (error) {
        console.error("Error sending message:", error)
    }
}

const assignToSelf = async (ulid: string) => {
    if (isAssigning.value[ulid]) return { success: false }

    isAssigning.value[ulid] = true

    try {
        const organisation = route().params?.organisation ?? "aw"

        const assignRoute: routeType = {
            name: "grp.org.chat.agents.assign.self",
            parameters: [organisation, ulid],
            method: "post",
        }

        const response = await axios.post(
            route(assignRoute.name, assignRoute.parameters),
            {},
            { withCredentials: true }
        )
        return { success: true, data: response.data }
    } catch (error: any) {
        return {
            success: false,
            error: error?.response?.data?.message ?? trans("Failed to assign chat"),
        }
    } finally {
        isAssigning.value[ulid] = false
    }
}

const handleClickContact = async (c: Contact) => {
    errorPerContact.value[c.ulid] = ""

    if (activeTab.value === "waiting" && viewMode.value === "my") {
        const result = await assignToSelf(String(c.ulid))

        if (!result.success) {
            errorPerContact.value[c.ulid] = result.error
            return
        }

        openChat(c)
        await nextTick()
        reloadContacts()
    } else {
        openChat(c)
    }
}

const onSyncSuccess = async () => {
    await reloadContacts()
    if (selectedSession.value?.ulid) {
        const c = contacts.value.find(
            (ct) => String(ct.ulid) === String(selectedSession.value?.ulid)
        )
        if (c) {
            openChat(c)
        }
    }
}

const onTransferAgentSuccess = async () => {
    sidePanelVisible.value = false
    selectedSession.value = null
    await reloadContacts()
}

const formatLastMessage = (msg: string) => {
    if (!msg) return ""
    return msg.length > 10 ? msg.substring(0, 10) + "..." : msg
}

const tabClass = (tab: string) => {
    return activeTab.value === tab ? "tabPrimary" : "tabInactive"
}
onMounted(async () => {
    await reloadContacts()
})
</script>

<template>
    <div class="w-full h-full flex flex-col bg-white  z-[10000]">
        <!-- Header -->
        <div class="px-3 py-2 border-b flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-700">
                Contacts
            </span>

            <button class="p-1.5 rounded hover:bg-gray-100 text-gray-500" @click="openGlobalChatSettings"
                title="Chat settings">
                <FontAwesomeIcon :icon="faCog" class="text-sm" />
            </button>
        </div>

        <Dialog v-model:visible="chatSettingVisible" modal header="Chat Settings"
            :style="{ width: '90vw', maxWidth: '560px' }">
            <div class="p-2">
                <SettingChat :contact="selectedContact" @close="chatSettingVisible = false" />
            </div>
        </Dialog>

        <!-- My Chats / Team Chats toggle -->
        <div class="flex border-b text-xs bg-gray-50">
            <div
                class="tabItem flex-1 text-center"
                :class="viewMode === 'my' ? 'tabPrimary' : 'tabInactive'"
                @click="viewMode = 'my'">
                {{ trans("My Chats") }}
            </div>
            <div
                class="tabItem flex-1 text-center"
                :class="viewMode === 'team' ? 'tabPrimary' : 'tabInactive'"
                @click="viewMode = 'team'">
                {{ trans("Team Chats") }}
            </div>
        </div>

        <!-- Status tabs -->
        <div class="flex items-center border-b text-xs">
            <div v-if="viewMode === 'my'" class="tabItem" :class="tabClass('waiting')" @click="activeTab = 'waiting'">
                {{ trans("Waiting") }}
            </div>
            <div class="tabItem" :class="tabClass('active')" @click="activeTab = 'active'">
                {{ trans("Active") }}
            </div>
            <div class="tabItem" :class="tabClass('closed')" @click="activeTab = 'closed'">
                {{ trans("Closed") }}
            </div>
            <div class="ml-auto pr-2">
                <button
                    @click="toggleSearch"
                    class="p-1.5 rounded hover:bg-gray-100 transition-colors"
                    :class="showSearch ? 'text-indigo-500' : 'text-gray-400'">
                    <FontAwesomeIcon :icon="faSearch" class="text-xs" />
                </button>
            </div>
        </div>

        <!-- Search input (collapsible) -->
        <Transition name="slide-down">
            <div v-if="showSearch" class="px-3 py-2 border-b bg-gray-50">
                <div class="relative">
                    <FontAwesomeIcon :icon="faSearch" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        autofocus
                        :placeholder="trans('Search by name...')"
                        class="w-full pl-7 pr-7 py-1.5 text-xs border border-gray-200 rounded bg-white focus:outline-none focus:border-indigo-300 focus:ring-1 focus:ring-indigo-200"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon :icon="faTimes" class="text-xs" />
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Content -->
        <div class="flex-1">
            <div v-if="!selectedSession" class="overflow-y-auto h-[calc(100vh-172px)]">
                <div v-if="filteredContacts.length === 0"
                    class="h-full flex flex-col items-center justify-center gap-2 text-center px-4">
                    <div class="text-2xl font-semibold" :style="{ color: 'var(--theme-color-4)' }">
                        💬
                    </div>

                    <div class="text-sm font-medium text-gray-700">
                        {{ trans("No conversations") }}
                    </div>

                    <div class="text-xs text-gray-500">
                        {{ trans("There are no chats at the moment") }}
                    </div>
                </div>

                <!-- LIST -->
                <div v-else>
                    <div v-for="c in filteredContacts" :key="c.ulid">
                        <div class="relative flex items-center gap-3 px-3 py-2 border-b hover:bg-gray-50 cursor-pointer"
                            @click="handleClickContact(c)">
                            <!-- Loading overlay -->
                            <div v-if="isAssigning[c.ulid]"
                                class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
                                <LoadingIcon class="w-10 h-10 text-white" />
                            </div>

                            <!-- Avatar -->
                            <div
                                class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-gray-100 text-gray-500">
                                <Image v-if="c.avatar" :src="c.avatar"
                                    class="w-full h-full rounded-full object-cover" />

                                <FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
                            </div>

                            <!-- Main content -->
                            <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                                <!-- Row 1: Name + Time -->
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-gray-800 truncate">
                                        {{ capitalize(c.name) }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 shrink-0">
                                        {{ c.lastMessageTime }}
                                    </span>
                                </div>

                                <!-- Row 2: Shop · Agent + Priority + Unread -->
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1 min-w-0 text-[11px] text-gray-400">
                                        <span v-if="c.shop?.name" class="truncate">{{ c.shop.name }}</span>
                                        <span
                                            v-if="c.agent?.name"
                                            class="inline-flex items-center gap-1 shrink-0 px-1 py-0.5 text-[10px] font-medium"
                                            :class="c.agent.name === layout?.user?.contact_name
                                                ? 'border-green-400 text-green-500'
                                                : 'border-indigo-300 text-indigo-400'">
                                            <FontAwesomeIcon :icon="faUser" class="text-[9px]" />
                                            {{ c.agent.name.split(' ')[0] }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <span v-if="c.unread && activeTab !== 'closed'"
                                            class="min-w-[16px] px-1.5 text-[10px] leading-4 text-white rounded-full text-center"
                                            :style="{ backgroundColor: 'var(--theme-color-4)' }">
                                            {{ c.unread }}
                                        </span>
                                        <FontAwesomeIcon v-if="priorityConfig(c.priority)"
                                            :icon="priorityConfig(c.priority)!.icon"
                                            :class="priorityConfig(c.priority)!.color"
                                            v-tooltip="priorityConfig(c.priority)!.label" class="text-[11px]"
                                            fixed-width />
                                    </div>
                                </div>

                                <!-- Row 3: Last message + Customer/Guest tag -->
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-gray-500 truncate flex-1 leading-snug">
                                        {{ formatLastMessage(c.lastMessage) }}
                                    </span>
                                    <span
                                        class="shrink-0 text-[9px] px-1 py-0.5 border leading-none"
                                        :class="c.webUser?.id
                                            ? 'border-green-400 text-green-500'
                                            : 'border-blue-300 text-blue-400'"
                                        v-tooltip="c.webUser?.id ? trans('Customer') : trans('Guest')">
                                        {{ c.webUser?.id ? 'C' : 'G' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Error -->
                        <div v-if="errorPerContact[c.ulid]" class="px-3 py-1 text-xs text-red-600 bg-red-50 border-b">
                            {{ errorPerContact[c.ulid] }}
                        </div>
                    </div>

                    <!-- Sentinel for infinite scroll -->
                    <div ref="sentinelEl" class="flex justify-center py-3">
                        <LoadingIcon v-if="isLoadingMore" class="w-5 h-5 text-gray-400" />
                    </div>
                </div>
            </div>

            <!-- Chat view tetap -->
            <div v-else class="relative h-[calc(100vh-172px)]">
                <div v-if="sidePanelVisible" class="absolute z-[9999] right-[420px] bottom-0 w-[350px]">
                    <ChatSidePanel :session="selectedSession" :initialTab="sidePanelInitialTab" @close="closeSidePanel"
                        @sync-success="onSyncSuccess" @transfer-agent-success="onTransferAgentSuccess" />
                </div>

                <div class="h-full">
                    <MessageAreaAgent :messages="messages" :session="selectedSession" @back="back"
                        @send-message="handleSendMessage" @close-session="closeSession" @view-history="showHistoryPanel"
                        @view-user-profile="showProfilePanel" @view-message-details="showMessageDetailsPanel"
                        @transfer-agent-success="onTransferAgentSuccess" />
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.15s ease;
    overflow: hidden;
}
.slide-down-enter-from,
.slide-down-leave-to {
    max-height: 0;
    opacity: 0;
    padding-top: 0;
    padding-bottom: 0;
}
.slide-down-enter-to,
.slide-down-leave-from {
    max-height: 60px;
    opacity: 1;
}

/* Tabs */
.tabItem {
    padding: 6px 12px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition:
        color 0.15s ease,
        border-color 0.15s ease;
}

.tabPrimary {
    color: var(--theme-color-4);
    border-bottom-color: var(--theme-color-4);
    font-weight: 600;
}

.tabInactive {
    color: #6b7280;
}

.tabInactive:hover {
    color: var(--theme-color-4);
}

/* Contact item */
.contactItem {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-bottom: 1px solid #e5e7eb;
    cursor: pointer;
    transition: background 0.15s ease;
}

.contactItem:hover {
    background: color-mix(in srgb, var(--theme-color-4) 6%, white);
}

.badge {
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    line-height: 1;
}

.badgeCustomer {
    background: color-mix(in srgb, var(--theme-color-4) 15%, white);
    color: var(--theme-color-4);
}

.badgeGuest {
    background: #eff6ff;
    color: #2563eb;
}

/* Unread */
.unreadBadge {
    background: var(--theme-color-4);
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 999px;
}
</style>
