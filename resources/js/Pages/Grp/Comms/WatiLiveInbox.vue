<!--
  - Author: andiferdiawan (https://github.com/andiferdiawan)
  - Created: Thursday, 22 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, andiferdiawan
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { ref, computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faWhatsapp,
    faInstagram,
    faFacebookMessenger,
} from "@fortawesome/free-brands-svg-icons"
import {
    faInboxIn,
    faSearch,
    faPaperPlane,
    faPaperclip,
    faSmile,
    faPhone,
    faVideo,
    faEllipsisH,
    faUser,
    faTag,
    faStickyNote,
    faChevronDown,
    faChevronRight,
    faCircle,
    faCheckDouble,
    faCheck,
    faClock,
    faStar,
    faPhoneVolume,
} from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(
    faInboxIn, faSearch, faPaperPlane, faPaperclip, faSmile,
    faPhone, faVideo, faEllipsisH, faUser, faTag, faStickyNote,
    faChevronDown, faChevronRight, faCircle, faCheckDouble, faCheck,
    faClock, faStar, faPhoneVolume
)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
}>()

type ChannelKey = "all" | "whatsapp" | "instagram" | "messenger" | "wa_calls"
type ChatFilter = "all" | "open" | "unread" | "pending"

const activeChannel = ref<ChannelKey>("all")
const activeChatFilter = ref<ChatFilter>("all")
const activeChat = ref<number | null>(1)
const messageInput = ref("")
const showContactDetail = ref(true)
const expandedSections = ref<Record<string, boolean>>({
    salesAttributes: true,
    tags: true,
    notes: false,
})

const channels: { key: ChannelKey; label: string; icon: object | string; count: number }[] = [
    { key: "all",       label: "All Channels",      icon: ["fal", "fa-inbox-in"],   count: 12 },
    { key: "whatsapp",  label: "WhatsApp",           icon: ["fab", "fa-whatsapp"],   count: 8  },
    { key: "instagram", label: "Instagram",          icon: ["fab", "fa-instagram"],  count: 2  },
    { key: "messenger", label: "Messenger",          icon: ["fab", "fa-facebook-messenger"], count: 1 },
    { key: "wa_calls",  label: "WhatsApp Calls",     icon: ["fal", "fa-phone-volume"], count: 1 },
]

const chatFilters: { key: ChatFilter; label: string }[] = [
    { key: "all",     label: "All"     },
    { key: "open",    label: "Open"    },
    { key: "unread",  label: "Unread"  },
    { key: "pending", label: "Pending" },
]

interface Chat {
    id: number
    name: string
    phone: string
    lastMessage: string
    time: string
    unread: number
    status: "open" | "pending" | "resolved"
    channel: ChannelKey
    avatar: string | null
    online: boolean
}

const chats: Chat[] = [
    { id: 1,  name: "Andi Ferdiawan",    phone: "+62 812 3456 7890", lastMessage: "Halo, saya mau tanya tentang produk...", time: "10:23", unread: 3, status: "open",    channel: "whatsapp",  avatar: null, online: true  },
    { id: 2,  name: "Budi Santoso",      phone: "+62 813 9876 5432", lastMessage: "Oke, terima kasih infonya!",             time: "09:47", unread: 0, status: "open",    channel: "whatsapp",  avatar: null, online: false },
    { id: 3,  name: "Citra Dewi",        phone: "+62 857 1234 5678", lastMessage: "Kapan bisa dikirim?",                   time: "Kes",   unread: 1, status: "pending",  channel: "instagram", avatar: null, online: true  },
    { id: 4,  name: "Dian Permata",      phone: "+62 822 8888 9999", lastMessage: "Sudah saya bayar ya pak",               time: "Sen",   unread: 0, status: "resolved", channel: "messenger", avatar: null, online: false },
    { id: 5,  name: "Eko Prasetyo",      phone: "+62 811 2222 3333", lastMessage: "Tolong cek pesanan saya dong",          time: "Min",   unread: 2, status: "open",     channel: "whatsapp",  avatar: null, online: false },
]

interface Message {
    id: number
    chatId: number
    text: string
    time: string
    sender: "agent" | "customer"
    status: "sent" | "delivered" | "read"
}

const messages: Message[] = [
    { id: 1,  chatId: 1, text: "Halo! Ada yang bisa kami bantu?",                                  time: "10:00", sender: "agent",    status: "read"      },
    { id: 2,  chatId: 1, text: "Halo, saya mau tanya tentang produk yang kemarin saya lihat.",     time: "10:05", sender: "customer", status: "read"      },
    { id: 3,  chatId: 1, text: "Tentu! Produk mana yang Anda maksud?",                             time: "10:06", sender: "agent",    status: "read"      },
    { id: 4,  chatId: 1, text: "Yang warna biru, ukuran M. Masih ada stoknya?",                    time: "10:10", sender: "customer", status: "read"      },
    { id: 5,  chatId: 1, text: "Untuk stok warna biru ukuran M saat ini masih tersedia. 😊",       time: "10:12", sender: "agent",    status: "read"      },
    { id: 6,  chatId: 1, text: "Oke bagus! Berapa harganya?",                                      time: "10:15", sender: "customer", status: "read"      },
    { id: 7,  chatId: 1, text: "Harganya Rp 250.000 sudah termasuk ongkos kirim ke Jawa.",         time: "10:16", sender: "agent",    status: "delivered" },
    { id: 8,  chatId: 1, text: "Halo, saya mau tanya tentang produk...",                           time: "10:23", sender: "customer", status: "read"      },
]

const filteredChats = computed(() => {
    return chats.filter(c => {
        const matchChannel = activeChannel.value === "all" || c.channel === activeChannel.value
        const matchFilter =
            activeChatFilter.value === "all"    ? true :
            activeChatFilter.value === "open"   ? c.status === "open" :
            activeChatFilter.value === "unread" ? c.unread > 0 :
            activeChatFilter.value === "pending" ? c.status === "pending" : true
        return matchChannel && matchFilter
    })
})

const selectedChat = computed(() => chats.find(c => c.id === activeChat.value) ?? null)
const chatMessages = computed(() => messages.filter(m => m.chatId === activeChat.value))

function getInitials(name: string): string {
    return name.split(" ").map(w => w[0]).join("").slice(0, 2).toUpperCase()
}

function sendMessage(): void {
    if (!messageInput.value.trim()) return
    messageInput.value = ""
}

function toggleSection(key: string): void {
    expandedSections.value[key] = !expandedSections.value[key]
}

const salesAttributes = [
    { label: "Last Order", value: "15 May 2026"   },
    { label: "Total Orders", value: "12"           },
    { label: "Total Spend", value: "Rp 3.200.000"  },
    { label: "Avg Order Value", value: "Rp 267.000" },
]

const contactTags = ["VIP", "Repeat Buyer", "Jawa"]
const channelColorMap: Record<ChannelKey, string> = {
    all:       "bg-gray-100 text-gray-600",
    whatsapp:  "bg-green-100 text-green-700",
    instagram: "bg-pink-100 text-pink-700",
    messenger: "bg-blue-100 text-blue-700",
    wa_calls:  "bg-emerald-100 text-emerald-700",
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="flex h-[calc(100vh-10rem)] overflow-hidden border-t border-gray-200">

        <!-- Panel 1: Team Inbox + Channels -->
        <div class="w-56 shrink-0 flex flex-col border-r border-gray-200 bg-gray-50">
            <div class="px-4 py-3 border-b border-gray-200">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Team Inbox</p>
            </div>
            <nav class="flex-1 overflow-y-auto py-2">
                <button
                    v-for="channel in channels"
                    :key="channel.key"
                    class="w-full flex items-center gap-2.5 px-4 py-2 text-sm transition-colors rounded-none"
                    :class="activeChannel === channel.key
                        ? 'bg-green-50 text-green-700 font-semibold border-r-2 border-green-600'
                        : 'text-gray-600 hover:bg-gray-100'"
                    @click="activeChannel = channel.key"
                >
                    <span class="w-4 text-center">
                        <FontAwesomeIcon :icon="channel.icon" fixed-width class="text-xs" />
                    </span>
                    <span class="flex-1 truncate text-left">{{ channel.label }}</span>
                    <span
                        v-if="channel.count"
                        class="rounded-full px-1.5 py-0.5 text-xs font-bold leading-none"
                        :class="activeChannel === channel.key ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-600'"
                    >
                        {{ channel.count }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- Panel 2: Active Chat List -->
        <div class="w-72 shrink-0 flex flex-col border-r border-gray-200 bg-white">
            <!-- Search -->
            <div class="px-3 py-2.5 border-b border-gray-100">
                <div class="relative">
                    <FontAwesomeIcon :icon="['fal', 'fa-search']" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs" />
                    <input
                        type="text"
                        placeholder="Search conversations..."
                        class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-md bg-gray-50 focus:outline-none focus:border-green-400 focus:ring-1 focus:ring-green-300"
                    />
                </div>
            </div>

            <!-- Filters -->
            <div class="flex border-b border-gray-100">
                <button
                    v-for="filter in chatFilters"
                    :key="filter.key"
                    class="flex-1 py-2 text-xs font-medium transition-colors"
                    :class="activeChatFilter === filter.key
                        ? 'text-green-700 border-b-2 border-green-600 bg-green-50'
                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    @click="activeChatFilter = filter.key"
                >
                    {{ filter.label }}
                </button>
            </div>

            <!-- Chat List -->
            <div class="flex-1 overflow-y-auto">
                <button
                    v-for="chat in filteredChats"
                    :key="chat.id"
                    class="w-full flex items-start gap-2.5 px-3 py-3 text-left border-b border-gray-50 transition-colors"
                    :class="activeChat === chat.id ? 'bg-green-50' : 'hover:bg-gray-50'"
                    @click="activeChat = chat.id"
                >
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        <div class="w-9 h-9 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs font-bold">
                            {{ getInitials(chat.name) }}
                        </div>
                        <span
                            v-if="chat.online"
                            class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-white"
                        />
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1 mb-0.5">
                            <span class="text-xs font-semibold text-gray-800 truncate">{{ chat.name }}</span>
                            <span class="text-xs text-gray-400 shrink-0">{{ chat.time }}</span>
                        </div>
                        <p class="text-xs text-gray-500 truncate leading-snug">{{ chat.lastMessage }}</p>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs leading-none font-medium" :class="channelColorMap[chat.channel]">
                                {{ chat.channel === "wa_calls" ? "Call" : chat.channel }}
                            </span>
                            <span
                                v-if="chat.status === 'pending'"
                                class="inline-flex items-center rounded-full px-1.5 py-0.5 text-xs leading-none font-medium bg-amber-100 text-amber-700"
                            >
                                Pending
                            </span>
                        </div>
                    </div>

                    <!-- Unread badge -->
                    <span
                        v-if="chat.unread > 0"
                        class="shrink-0 mt-0.5 w-4 h-4 rounded-full bg-green-600 text-white text-xs flex items-center justify-center font-bold"
                    >
                        {{ chat.unread }}
                    </span>
                </button>

                <div v-if="!filteredChats.length" class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <FontAwesomeIcon :icon="['fal', 'fa-inbox-in']" class="text-3xl mb-2" />
                    <p class="text-xs">No conversations</p>
                </div>
            </div>
        </div>

        <!-- Panel 3: Chat Messages -->
        <div class="flex-1 flex flex-col bg-white min-w-0">
            <template v-if="selectedChat">
                <!-- Chat Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-white">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-9 h-9 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-xs font-bold">
                                {{ getInitials(selectedChat.name) }}
                            </div>
                            <span v-if="selectedChat.online" class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-green-500 border-2 border-white" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ selectedChat.name }}</p>
                            <p class="text-xs text-gray-500">{{ selectedChat.phone }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
                            <FontAwesomeIcon :icon="['fal', 'fa-phone']" class="text-sm" />
                        </button>
                        <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
                            <FontAwesomeIcon :icon="['fal', 'fa-video']" class="text-sm" />
                        </button>
                        <button
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors"
                            @click="showContactDetail = !showContactDetail"
                        >
                            <FontAwesomeIcon :icon="['fal', 'fa-user']" class="text-sm" />
                        </button>
                        <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
                            <FontAwesomeIcon :icon="['fal', 'fa-ellipsis-h']" class="text-sm" />
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-[#f0f4f8]">
                    <div
                        v-for="msg in chatMessages"
                        :key="msg.id"
                        class="flex"
                        :class="msg.sender === 'agent' ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[70%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-sm"
                            :class="msg.sender === 'agent'
                                ? 'bg-green-600 text-white rounded-br-sm'
                                : 'bg-white text-gray-800 rounded-bl-sm'"
                        >
                            <p>{{ msg.text }}</p>
                            <div
                                class="flex items-center justify-end gap-1 mt-1"
                                :class="msg.sender === 'agent' ? 'text-green-200' : 'text-gray-400'"
                            >
                                <span class="text-xs">{{ msg.time }}</span>
                                <template v-if="msg.sender === 'agent'">
                                    <FontAwesomeIcon
                                        :icon="msg.status === 'read' ? ['fal', 'fa-check-double'] : ['fal', 'fa-check']"
                                        class="text-xs"
                                        :class="msg.status === 'read' ? 'text-blue-200' : ''"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-end gap-2">
                        <button class="p-2 text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                            <FontAwesomeIcon :icon="['fal', 'fa-paperclip']" />
                        </button>
                        <button class="p-2 text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                            <FontAwesomeIcon :icon="['fal', 'fa-smile']" />
                        </button>
                        <textarea
                            v-model="messageInput"
                            rows="1"
                            placeholder="Type a message..."
                            class="flex-1 resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-green-400 focus:ring-1 focus:ring-green-300 max-h-28"
                            @keydown.enter.exact.prevent="sendMessage"
                        />
                        <button
                            class="p-2 rounded-full bg-green-600 text-white hover:bg-green-700 disabled:opacity-40 transition-colors shrink-0"
                            :disabled="!messageInput.trim()"
                            @click="sendMessage"
                        >
                            <FontAwesomeIcon :icon="['fal', 'fa-paper-plane']" />
                        </button>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400">
                <FontAwesomeIcon :icon="['fal', 'fa-inbox-in']" class="text-5xl mb-3" />
                <p class="text-sm">Select a conversation to start chatting</p>
            </div>
        </div>

        <!-- Panel 4: Contact Detail -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div v-if="showContactDetail && selectedChat" class="w-72 shrink-0 flex flex-col border-l border-gray-200 bg-white overflow-y-auto">
                <!-- Contact Header -->
                <div class="flex flex-col items-center px-4 py-5 border-b border-gray-100 text-center">
                    <div class="w-14 h-14 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-lg font-bold mb-2">
                        {{ getInitials(selectedChat.name) }}
                    </div>
                    <p class="text-sm font-semibold text-gray-800">{{ selectedChat.name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ selectedChat.phone }}</p>
                    <div class="flex items-center gap-1 mt-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 text-green-700">
                            <FontAwesomeIcon :icon="['fab', 'fa-whatsapp']" class="mr-1" />
                            WhatsApp
                        </span>
                    </div>
                </div>

                <!-- CX Score -->
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">CX Score</p>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-100 rounded-full h-2">
                            <div class="bg-green-500 rounded-full h-2" style="width: 72%" />
                        </div>
                        <span class="text-sm font-bold text-green-700">72</span>
                    </div>
                    <div class="flex justify-between mt-1.5">
                        <span class="text-xs text-gray-400">Poor</span>
                        <span class="text-xs text-gray-400">Excellent</span>
                    </div>
                </div>

                <!-- Sales Attributes -->
                <div class="border-b border-gray-100">
                    <button
                        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                        @click="toggleSection('salesAttributes')"
                    >
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sales Attributes</span>
                        <FontAwesomeIcon
                            :icon="expandedSections.salesAttributes ? ['fal', 'fa-chevron-down'] : ['fal', 'fa-chevron-right']"
                            class="text-xs text-gray-400"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition ease-out duration-150"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-100"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <div v-if="expandedSections.salesAttributes" class="px-4 pb-3 space-y-2">
                            <div v-for="attr in salesAttributes" :key="attr.label" class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">{{ attr.label }}</span>
                                <span class="text-xs font-medium text-gray-800">{{ attr.value }}</span>
                            </div>
                        </div>
                    </Transition>
                </div>

                <!-- Tags -->
                <div class="border-b border-gray-100">
                    <button
                        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                        @click="toggleSection('tags')"
                    >
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tags</span>
                        <FontAwesomeIcon
                            :icon="expandedSections.tags ? ['fal', 'fa-chevron-down'] : ['fal', 'fa-chevron-right']"
                            class="text-xs text-gray-400"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition ease-out duration-150"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                    >
                        <div v-if="expandedSections.tags" class="px-4 pb-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in contactTags"
                                :key="tag"
                                class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700"
                            >
                                <FontAwesomeIcon :icon="['fal', 'fa-tag']" class="text-xs" />
                                {{ tag }}
                            </span>
                        </div>
                    </Transition>
                </div>

                <!-- Notes -->
                <div>
                    <button
                        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                        @click="toggleSection('notes')"
                    >
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Notes</span>
                        <FontAwesomeIcon
                            :icon="expandedSections.notes ? ['fal', 'fa-chevron-down'] : ['fal', 'fa-chevron-right']"
                            class="text-xs text-gray-400"
                        />
                    </button>
                    <Transition
                        enter-active-class="transition ease-out duration-150"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                    >
                        <div v-if="expandedSections.notes" class="px-4 pb-3">
                            <textarea
                                rows="3"
                                placeholder="Add a note..."
                                class="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-xs focus:outline-none focus:border-green-400 focus:ring-1 focus:ring-green-300"
                            />
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </div>
</template>
