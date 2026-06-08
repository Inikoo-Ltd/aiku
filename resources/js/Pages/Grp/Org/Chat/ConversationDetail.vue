<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, inject, onMounted, watch } from 'vue'
import axios from 'axios'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import CustomerTimeline from '@/Components/Showcases/Grp/CustomerTimeline.vue'
import ChatActivityTimeline from '@/Components/Chat/ChatActivityTimeline.vue'
import { capitalize } from '@/Composables/capitalize'
import { PageHeadingTypes } from '@/types/PageHeading'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faComments,
    faUser,
    faRobot,
    faHeadset,
    faCog,
    faPaperclip,
    faTag,
    faChartLine,
    faLongArrowLeft,
} from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(
    faComments, faUser, faRobot, faHeadset, faCog, faPaperclip,
    faTag, faChartLine, faLongArrowLeft
)

type SidePanelTab = 'profile' | 'statistics' | 'timeline' | 'log'

interface CustomerTag {
    id: number
    name: string
    slug: string
}

interface CustomerStats {
    currency_symbol: string
    number_orders: number
    sales_all: number
    average_order_value: number | null
    last_invoiced_at: string | null
    first_order_date: string | null
    number_invoices: number
    number_returns: number
    number_orders_state_creating: number
}

interface ChatSessionProp {
    ulid: string
    status: string
    contact_name: string
    assigned_agent: string | null
    shop_name: string | null
    created_at: { formatted: string; diff: string } | null
    closed_at: { formatted: string; diff: string } | null
    priority: string
    is_guest: boolean
    ai_summary: { summary?: string; sentiment?: string; key_points?: string[] } | null
}

interface MessageProp {
    id: number
    message_text: string | null
    message_type: string
    sender_type: string
    sender_name: string | null
    is_agent: boolean
    is_guest: boolean
    is_user: boolean
    is_system: boolean
    is_ai: boolean
    is_read: boolean
    media_url: string | null
    file_name: string | null
    file_size: number | null
    file_mime: string | null
    download_route: { url: string } | null
    created_at: string
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    chatSession: ChatSessionProp
    messages: MessageProp[]
}>()

const layout: any = inject('layout', {})
const baseUrl = layout?.appUrl ?? ''

const showContactDetail = ref(true)
const activeTab = ref<SidePanelTab>('profile')

const customerProfile = ref<{ tags: CustomerTag[]; stats: CustomerStats | null }>({ tags: [], stats: null })
const isLoadingProfile = ref(false)
const profileLoaded = ref(false)

const timelineData = ref<any>({ events: [] })
const isLoadingTimeline = ref(false)
const timelineLoaded = ref(false)
const timelineError = ref<string | null>(null)

const loadCustomerProfile = async () => {
    if (props.chatSession.is_guest || profileLoaded.value) return
    try {
        isLoadingProfile.value = true
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions/${props.chatSession.ulid}/customer-profile`)
        customerProfile.value = res.data
        profileLoaded.value = true
    } finally {
        isLoadingProfile.value = false
    }
}

const loadTimeline = async () => {
    if (props.chatSession.is_guest || timelineLoaded.value) return
    try {
        isLoadingTimeline.value = true
        timelineError.value = null
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions/${props.chatSession.ulid}/customer-timeline`)
        timelineData.value = res.data
        timelineLoaded.value = true
    } catch (e: any) {
        timelineError.value = e.response?.data?.message ?? e.message ?? 'Failed to load timeline'
    } finally {
        isLoadingTimeline.value = false
    }
}

watch(activeTab, async (tab) => {
    if ((tab === 'profile' || tab === 'statistics') && !profileLoaded.value) await loadCustomerProfile()
    if (tab === 'timeline' && !timelineLoaded.value) await loadTimeline()
})

onMounted(async () => {
    await loadCustomerProfile()
})

const statusColors: Record<string, string> = {
    active:      'bg-green-100 text-green-700',
    waiting:     'bg-yellow-100 text-yellow-700',
    resolved:    'bg-blue-100 text-blue-700',
    transferred: 'bg-purple-100 text-purple-700',
    closed:      'bg-gray-100 text-gray-600',
}

function isFromAgent(msg: MessageProp): boolean {
    return msg.is_agent
}

function senderLabel(msg: MessageProp): string {
    if (msg.is_agent) return msg.sender_name ?? 'Agent'
    if (msg.is_ai) return 'AI'
    if (msg.is_system) return 'System'
    return props.chatSession.contact_name || 'Customer'
}

function formatTimestamp(raw: string): string {
    return new Date(raw).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatFileSize(bytes: number | null): string {
    if (!bytes) return ''
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatStatDate(date: string | null): string {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatCurrency(amount: number | null | undefined, symbol: string): string {
    if (amount == null) return '-'
    return `${symbol} ${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
}

function getInitials(name: string): string {
    return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

const tabs: { key: SidePanelTab; label: string; onlyRegistered?: boolean }[] = [
    { key: 'profile',    label: 'Profile' },
    { key: 'statistics', label: 'Statistics', onlyRegistered: true },
    { key: 'timeline',   label: 'Timeline',   onlyRegistered: true },
    { key: 'log',        label: 'Log' },
]
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="flex h-[calc(100vh-10rem)] overflow-hidden border-t border-gray-200">

        <!-- Chat Messages Panel -->
        <div class="flex-1 flex flex-col bg-white min-w-0">
            <!-- Chat Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-white">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-sm font-bold shrink-0">
                        {{ getInitials(chatSession.contact_name) }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">{{ chatSession.contact_name }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="statusColors[chatSession.status] ?? 'bg-gray-100 text-gray-600'"
                            >
                                {{ chatSession.status }}
                            </span>
                            <span v-if="chatSession.shop_name" class="text-xs text-gray-400">{{ chatSession.shop_name }}</span>
                        </div>
                    </div>
                </div>
                <button
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors"
                    @click="showContactDetail = !showContactDetail"
                >
                    <FontAwesomeIcon :icon="['fal', 'fa-user']" class="text-sm" />
                </button>
            </div>

            <!-- Messages Area -->
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-[#f0f4f8]">
                <template v-for="msg in messages" :key="msg.id">
                    <div
                        v-if="!msg.is_system && !msg.is_ai"
                        class="flex"
                        :class="isFromAgent(msg) ? 'justify-end' : 'justify-start'"
                    >
                        <div
                            class="max-w-[70%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-sm"
                            :class="isFromAgent(msg)
                                ? 'bg-green-600 text-white rounded-br-sm'
                                : 'bg-white text-gray-800 rounded-bl-sm'"
                        >
                            <div class="text-[11px] font-semibold mb-0.5 opacity-70">
                                {{ senderLabel(msg) }}
                            </div>

                            <template v-if="msg.message_type === 'image' && msg.media_url">
                                <img :src="msg.media_url" class="rounded-lg max-w-full max-h-64 object-contain" alt="image" />
                            </template>

                            <template v-else-if="msg.message_type === 'file' && msg.download_route">
                                <a
                                    :href="msg.download_route.url"
                                    target="_blank"
                                    class="flex items-center gap-x-2 text-sm underline"
                                    :class="isFromAgent(msg) ? 'text-white/90' : 'text-green-700'"
                                >
                                    <FontAwesomeIcon :icon="['fal', 'fa-paperclip']" />
                                    <span>{{ msg.file_name || 'Download file' }}</span>
                                    <span v-if="msg.file_size" class="text-xs opacity-60">({{ formatFileSize(msg.file_size) }})</span>
                                </a>
                            </template>

                            <template v-else>
                                <p class="whitespace-pre-wrap break-words">{{ msg.message_text || '—' }}</p>
                            </template>

                            <div
                                class="text-[10px] mt-1 text-right"
                                :class="isFromAgent(msg) ? 'text-white/60' : 'text-gray-400'"
                            >
                                {{ formatTimestamp(msg.created_at) }}
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex justify-center">
                        <span class="text-xs text-gray-400 bg-gray-50 rounded-full px-3 py-1 border border-gray-100">
                            <FontAwesomeIcon v-if="msg.is_ai" :icon="['fal', 'fa-robot']" class="mr-1" />
                            <FontAwesomeIcon v-else :icon="['fal', 'fa-cog']" class="mr-1" />
                            {{ msg.message_text }}
                        </span>
                    </div>
                </template>

                <div v-if="!messages.length" class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <FontAwesomeIcon :icon="['fal', 'fa-comments']" class="text-4xl mb-2" />
                    <p class="text-sm">No messages in this conversation</p>
                </div>
            </div>
        </div>

        <!-- Contact Detail Panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div v-if="showContactDetail" class="w-96 shrink-0 flex flex-col border-l border-gray-200 bg-white overflow-hidden">
                <!-- Contact Header -->
                <div class="flex flex-col items-center px-4 py-4 border-b border-gray-100 text-center shrink-0">
                    <div class="w-12 h-12 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-base font-bold mb-2">
                        {{ getInitials(chatSession.contact_name) }}
                    </div>
                    <p class="text-sm font-semibold text-gray-800">{{ chatSession.contact_name }}</p>
                    <span
                        class="mt-1 inline-flex items-center justify-center px-2 py-0.5 rounded-sm text-[11px] font-medium"
                        :class="chatSession.is_guest ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'"
                    >
                        {{ chatSession.is_guest ? 'Guest' : 'Customer' }}
                    </span>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-gray-100 shrink-0 text-xs">
                    <template v-for="tab in tabs" :key="tab.key">
                        <button
                            v-if="!tab.onlyRegistered || !chatSession.is_guest"
                            class="flex-1 py-2.5 font-medium transition-colors"
                            :class="activeTab === tab.key
                                ? 'text-green-700 border-b-2 border-green-600'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </template>
                </div>

                <!-- Tab Content -->
                <div class="flex-1 overflow-y-auto">

                    <!-- Profile Tab (merged with Details) -->
                    <div v-if="activeTab === 'profile'" class="divide-y divide-gray-100">

                        <!-- Contact Info -->
                        <div class="px-4 py-3 space-y-2.5">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Contact</p>
                            <div class="grid grid-cols-3 gap-2 items-start">
                                <div class="text-gray-500 text-xs">Name</div>
                                <div class="col-span-2 text-xs font-medium text-gray-800">{{ chatSession.contact_name || '-' }}</div>
                            </div>
                            <div v-if="chatSession.shop_name" class="grid grid-cols-3 gap-2 items-start">
                                <div class="text-gray-500 text-xs">Shop</div>
                                <div class="col-span-2 text-xs font-medium text-gray-800">{{ chatSession.shop_name }}</div>
                            </div>
                        </div>

                        <!-- Session Info -->
                        <div class="px-4 py-3 space-y-2.5">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Session</p>
                            <div class="grid grid-cols-3 gap-2 items-center">
                                <div class="text-gray-500 text-xs">Status</div>
                                <div class="col-span-2">
                                    <span
                                        class="text-xs font-medium capitalize rounded-full px-2 py-0.5"
                                        :class="statusColors[chatSession.status] ?? 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ chatSession.status }}
                                    </span>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2 items-center">
                                <div class="text-gray-500 text-xs">Priority</div>
                                <div class="col-span-2 text-xs font-medium text-gray-800 capitalize">{{ chatSession.priority }}</div>
                            </div>
                            <div v-if="chatSession.assigned_agent" class="grid grid-cols-3 gap-2 items-center">
                                <div class="text-gray-500 text-xs">Agent</div>
                                <div class="col-span-2 text-xs font-medium text-gray-800">{{ chatSession.assigned_agent }}</div>
                            </div>
                            <div v-if="chatSession.created_at" class="grid grid-cols-3 gap-2 items-center">
                                <div class="text-gray-500 text-xs">Started</div>
                                <div class="col-span-2 text-xs font-medium text-gray-800">{{ chatSession.created_at.formatted }}</div>
                            </div>
                            <div v-if="chatSession.closed_at" class="grid grid-cols-3 gap-2 items-center">
                                <div class="text-gray-500 text-xs">Closed</div>
                                <div class="col-span-2 text-xs font-medium text-gray-800">{{ chatSession.closed_at.formatted }}</div>
                            </div>
                        </div>

                        <!-- Tags (registered customers only) -->
                        <div v-if="!chatSession.is_guest" class="px-4 py-3">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Tags</p>
                            <div v-if="isLoadingProfile" class="text-xs text-gray-400">Loading...</div>
                            <div v-else-if="customerProfile.tags.length" class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="tag in customerProfile.tags"
                                    :key="tag.id"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border font-medium bg-indigo-50 text-indigo-700 border-indigo-200"
                                >
                                    <FontAwesomeIcon :icon="['fal', 'fa-tag']" class="text-[9px] opacity-70" />
                                    {{ tag.name }}
                                </span>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic">No tags</div>
                        </div>

                        <!-- AI Summary -->
                        <div v-if="chatSession.ai_summary?.summary" class="px-4 py-3">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="text-indigo-400" />
                                AI Summary
                                <span
                                    v-if="chatSession.ai_summary.sentiment"
                                    class="ml-auto text-[10px] font-medium capitalize px-1.5 py-0.5 rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-700': chatSession.ai_summary.sentiment === 'positive',
                                        'bg-red-100 text-red-600': chatSession.ai_summary.sentiment === 'negative',
                                        'bg-gray-100 text-gray-500': chatSession.ai_summary.sentiment === 'neutral',
                                    }"
                                >
                                    {{ chatSession.ai_summary.sentiment }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-700 leading-relaxed">{{ chatSession.ai_summary.summary }}</p>
                            <template v-if="chatSession.ai_summary.key_points?.length">
                                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mt-3 mb-1.5">Key Points</p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="(point, i) in chatSession.ai_summary.key_points"
                                        :key="i"
                                        class="flex items-start gap-1.5 text-xs text-gray-600"
                                    >
                                        <span class="mt-1.5 w-1 h-1 rounded-full bg-indigo-400 shrink-0"></span>
                                        {{ point }}
                                    </li>
                                </ul>
                            </template>
                        </div>

                    </div>

                    <!-- Statistics Tab -->
                    <div v-if="activeTab === 'statistics'" class="p-4">
                        <div v-if="isLoadingProfile" class="flex items-center justify-center py-10 text-gray-400 text-xs">
                            Loading...
                        </div>
                        <div v-else-if="!customerProfile.stats" class="flex flex-col items-center justify-center py-10 text-gray-400">
                            <FontAwesomeIcon :icon="['fal', 'fa-chart-line']" class="text-2xl mb-2 opacity-30" />
                            <p class="text-xs">No statistics available</p>
                        </div>
                        <div v-else class="space-y-2.5">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Sales Attributes</div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Last Invoice</span>
                                <span class="text-xs font-medium text-gray-800">{{ formatStatDate(customerProfile.stats.last_invoiced_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">First Order</span>
                                <span class="text-xs font-medium text-gray-800">{{ formatStatDate(customerProfile.stats.first_order_date) }}</span>
                            </div>
                            <div v-if="customerProfile.stats.number_returns > 0" class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Returns</span>
                                <span class="text-xs font-medium text-gray-800">{{ customerProfile.stats.number_returns.toLocaleString() }}</span>
                            </div>
                            <div v-if="customerProfile.stats.number_invoices > 0" class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Invoices</span>
                                <span class="text-xs font-medium text-gray-800">{{ customerProfile.stats.number_invoices.toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Total Orders</span>
                                <span class="text-xs font-semibold text-gray-800">{{ customerProfile.stats.number_orders.toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Total Spend</span>
                                <span class="text-xs font-semibold text-gray-800">{{ formatCurrency(customerProfile.stats.sales_all, customerProfile.stats.currency_symbol) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Avg Order Value</span>
                                <span class="text-xs font-medium text-gray-800">{{ formatCurrency(customerProfile.stats.average_order_value, customerProfile.stats.currency_symbol) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">Orders in Basket</span>
                                <span class="text-xs font-medium text-gray-800">{{ customerProfile.stats.number_orders_state_creating.toLocaleString() }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Tab -->
                    <div v-if="activeTab === 'timeline'">
                        <div v-if="isLoadingTimeline" class="p-4 space-y-2">
                            <div class="h-4 bg-gray-100 rounded animate-pulse w-3/4" />
                            <div class="h-4 bg-gray-100 rounded animate-pulse w-1/2" />
                            <div class="h-4 bg-gray-100 rounded animate-pulse w-2/3" />
                        </div>
                        <div v-else-if="timelineError" class="flex flex-col items-center justify-center py-10 gap-2 text-center px-4">
                            <p class="text-xs text-red-500">{{ timelineError }}</p>
                        </div>
                        <CustomerTimeline v-else :data="timelineData" />
                    </div>

                    <!-- Log Tab -->
                    <div v-if="activeTab === 'log'" class="px-4">
                        <ChatActivityTimeline :sessionUlid="chatSession.ulid" :baseUrl="baseUrl" />
                    </div>

                </div>
            </div>
        </Transition>
    </div>
</template>
