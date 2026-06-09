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
    faCopy,
    faCheck,
    faShareSquare,
    faEye,
    faEyeSlash,
} from '@fal'
import { faSlack } from '@fortawesome/free-brands-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import TagsInput from '@/Components/Forms/Fields/TagsInput.vue'

library.add(
    faComments, faUser, faRobot, faHeadset, faCog, faPaperclip,
    faTag, faChartLine, faLongArrowLeft, faCopy, faCheck, faShareSquare, faEye, faEyeSlash, faSlack
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
    slackConfigured: boolean
    slackCurrentConfig?: { token: string; channels: string[] }
    slackUpdateRoute: { name: string; parameters: Record<string, string | number> }
}>()

const layout: any = inject('layout', {})
const baseUrl = layout?.appUrl ?? ''

const showContactDetail = ref(true)
const activeTab = ref<SidePanelTab>('profile')

const isSharingSlack = ref(false)
const slackShareStatus = ref<'idle' | 'success' | 'partial' | 'error'>('idle')
const slackShareMessage = ref('')
const isCopied = ref(false)

const showSlackModal = ref(false)
const slackModalToken = ref('')
const slackTokenVisible = ref(false)
const slackTokenAlreadySet = ref(false)
const slackModalChannels = ref<string[]>(['#general'])
const isSavingSlack = ref(false)
const slackSaveError = ref('')
const slackConfiguredLocal = ref(props.slackConfigured)
const showGuide = ref(false)

function openSlackModal(): void {
    slackModalToken.value = props.slackCurrentConfig?.token ?? ''
    slackTokenAlreadySet.value = !!props.slackCurrentConfig?.token
    slackTokenVisible.value = false
    const existing: string[] = Array.isArray(props.slackCurrentConfig?.channels)
        ? props.slackCurrentConfig!.channels.filter(Boolean)
        : []
    slackModalChannels.value = existing.length ? [...existing] : []
    slackSaveError.value = ''
    showGuide.value = false
    showSlackModal.value = true
}

async function shareToSlack(): Promise<void> {
    if (isSharingSlack.value) return
    try {
        isSharingSlack.value = true
        slackShareStatus.value = 'idle'
        const res = await axios.post(`${baseUrl}/app/api/chats/sessions/${props.chatSession.ulid}/share-to-slack`)
        slackShareStatus.value = res.data?.partial ? 'partial' : 'success'
        slackShareMessage.value = res.data?.message ?? 'Shared to Slack!'
    } catch (e: any) {
        slackShareStatus.value = 'error'
        slackShareMessage.value = e.response?.data?.message ?? 'Failed to share to Slack.'
    } finally {
        isSharingSlack.value = false
        setTimeout(() => { slackShareStatus.value = 'idle'; slackShareMessage.value = '' }, 6000)
    }
}

async function copyChatId(): Promise<void> {
    await navigator.clipboard.writeText(props.chatSession.ulid)
    isCopied.value = true
    setTimeout(() => { isCopied.value = false }, 2000)
}

async function saveSlackConfig(): Promise<void> {
    const tokenRequired = !slackTokenAlreadySet.value
    if (tokenRequired && !slackModalToken.value.trim()) {
        slackSaveError.value = 'Bot token is required.'
        return
    }
    if (!slackModalChannels.value.length) {
        slackSaveError.value = 'At least one channel is required.'
        return
    }
    try {
        isSavingSlack.value = true
        slackSaveError.value = ''

        const payload: Record<string, unknown> = {
            chat_slack_channels: slackModalChannels.value.map(c => c.trim()).filter(Boolean),
            chat_slack_token:    slackModalToken.value.trim(),
        }

        await axios.patch(
            route(props.slackUpdateRoute.name, props.slackUpdateRoute.parameters),
            payload
        )
        slackConfiguredLocal.value = true
        if (props.slackCurrentConfig) {
            props.slackCurrentConfig.channels = slackModalChannels.value
        }
        showSlackModal.value = false
    } catch (e: any) {
        slackSaveError.value = e.response?.data?.message ?? 'Failed to save Slack configuration.'
    } finally {
        isSavingSlack.value = false
    }
}

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
            <div class="border-b border-gray-200 bg-white">
                <div class="flex items-center justify-between px-4 py-3">
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
                    <div class="flex items-center gap-1">
                        <!-- Share to Slack: not configured -->
                        <button
                            v-if="!slackConfiguredLocal"
                            v-tooltip="'Slack is not configured. Click to set up.'"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-md text-amber-600 bg-amber-50 hover:bg-amber-100 transition-colors"
                            @click="openSlackModal()"
                        >
                            <FontAwesomeIcon :icon="['fab', 'fa-slack']" class="text-sm" />
                            <span>Setup Slack</span>
                            <FontAwesomeIcon :icon="['fal', 'fa-cog']" class="text-xs opacity-70" />
                        </button>

                        <!-- Share to Slack: configured → button group -->
                        <div v-else class="inline-flex rounded-md border divide-x overflow-hidden"
                            :class="slackShareStatus === 'error' ? 'border-red-200 divide-red-200' : slackShareStatus === 'partial' ? 'border-amber-200 divide-amber-200' : 'border-gray-200 divide-gray-200'"
                        >
                            <button
                                class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium transition-colors"
                                :class="slackShareStatus === 'success'
                                    ? 'bg-green-50 text-green-700'
                                    : slackShareStatus === 'partial'
                                        ? 'bg-amber-50 text-amber-700'
                                        : slackShareStatus === 'error'
                                            ? 'bg-red-50 text-red-600'
                                            : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                                :disabled="isSharingSlack"
                                @click="shareToSlack"
                            >
                                <FontAwesomeIcon
                                    :icon="slackShareStatus === 'success' || slackShareStatus === 'partial' ? ['fal', 'fa-check'] : ['fab', 'fa-slack']"
                                    class="text-sm"
                                />
                                <span>{{ isSharingSlack ? 'Sharing...' : slackShareStatus === 'success' ? 'Shared!' : slackShareStatus === 'partial' ? 'Partial' : slackShareStatus === 'error' ? 'Failed' : 'Share to Slack' }}</span>
                            </button>
                            <button
                                v-tooltip="'Update Slack configuration'"
                                class="px-2 py-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-colors"
                                @click="openSlackModal()"
                            >
                                <FontAwesomeIcon :icon="['fal', 'fa-cog']" class="text-xs" />
                            </button>
                        </div>
                        <!-- Toggle side panel -->
                        <button
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors"
                            @click="showContactDetail = !showContactDetail"
                        >
                            <FontAwesomeIcon :icon="['fal', 'fa-user']" class="text-sm" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Slack share result message -->
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="slackShareMessage && slackShareStatus !== 'idle'"
                    class="flex items-start gap-2 px-4 py-2.5 text-xs border-b"
                    :class="slackShareStatus === 'success'
                        ? 'bg-green-50 text-green-700 border-green-100'
                        : slackShareStatus === 'partial'
                            ? 'bg-amber-50 text-amber-700 border-amber-100'
                            : 'bg-red-50 text-red-600 border-red-100'"
                >
                    <FontAwesomeIcon
                        :icon="['fab', 'fa-slack']"
                        class="text-sm mt-0.5 shrink-0"
                    />
                    <span>{{ slackShareMessage }}</span>
                </div>
            </Transition>

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
                            <div class="grid grid-cols-3 gap-2 items-center">
                                <div class="text-gray-500 text-xs">Chat ID</div>
                                <div class="col-span-2 flex items-center gap-1">
                                    <code class="text-[11px] font-mono text-gray-700 bg-gray-100 rounded px-1.5 py-0.5 truncate">{{ chatSession.ulid }}</code>
                                    <button class="shrink-0 text-gray-400 hover:text-gray-600 transition-colors" @click="copyChatId">
                                        <FontAwesomeIcon :icon="isCopied ? ['fal', 'fa-check'] : ['fal', 'fa-copy']" class="text-xs" />
                                    </button>
                                </div>
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

    <!-- Slack Setup Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showSlackModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showSlackModal = false" />

                <div class="relative w-full max-w-lg bg-white rounded-xl shadow-2xl ring-1 ring-gray-200 overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-2.5">
                            <FontAwesomeIcon :icon="['fab', 'fa-slack']" class="text-lg text-[#4A154B]" />
                            <h2 class="text-sm font-semibold text-gray-800">Setup Slack Integration</h2>
                        </div>
                        <button class="p-1 text-gray-400 hover:text-gray-600 rounded-md hover:bg-gray-100" @click="showSlackModal = false">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Slack Bot Token <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="slackModalToken"
                                    :type="slackTokenVisible ? 'text' : 'password'"
                                    placeholder="Paste your Slack bot token here"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 pr-9 text-sm font-mono focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300"
                                />
                                <button
                                    type="button"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                    v-tooltip="slackTokenVisible ? 'Hide token' : 'Show token'"
                                    @click="slackTokenVisible = !slackTokenVisible"
                                >
                                    <FontAwesomeIcon :icon="['fal', slackTokenVisible ? 'fa-eye-slash' : 'fa-eye']" class="text-sm" />
                                </button>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">Bot User OAuth Token from your Slack App (starts with <code class="bg-gray-100 px-1 rounded">xoxb-</code>)</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Slack Channels <span class="text-red-500">*</span>
                            </label>
                            <div class="rounded-lg border border-gray-200 focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-300">
                                <TagsInput
                                    v-model="slackModalChannels"
                                    :field-data="{ placeholder: 'Type channel and press Enter' }"
                                />
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">Type a channel name and press <kbd class="bg-gray-100 border border-gray-200 rounded px-1 text-[10px]">Enter</kbd> to add. Multiple channels supported.</p>
                        </div>

                        <p v-if="slackSaveError" class="text-xs text-red-500 bg-red-50 rounded-lg px-3 py-2">{{ slackSaveError }}</p>

                        <!-- Guide -->
                        <div class="border border-gray-100 rounded-lg overflow-hidden">
                            <button
                                class="w-full flex items-center justify-between px-3 py-2.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors text-left"
                                @click="showGuide = !showGuide"
                            >
                                <span class="flex items-center gap-1.5">
                                    <FontAwesomeIcon :icon="['fab', 'fa-slack']" class="text-[#4A154B]" />
                                    How to get a Bot Token
                                </span>
                                <FontAwesomeIcon :icon="showGuide ? ['fal', 'fa-chevron-down'] : ['fal', 'fa-chevron-right']" class="text-xs text-gray-400" />
                            </button>
                            <div v-if="showGuide" class="px-3 pb-3 bg-gray-50 border-t border-gray-100">
                                <ol class="space-y-2 mt-2.5">
                                    <li class="flex gap-2 text-xs text-gray-600">
                                        <span class="shrink-0 w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[10px]">1</span>
                                        <span>Go to <a href="https://api.slack.com/apps" target="_blank" class="text-indigo-600 underline hover:text-indigo-800">api.slack.com/apps</a> → Create New App → From scratch</span>
                                    </li>
                                    <li class="flex gap-2 text-xs text-gray-600">
                                        <span class="shrink-0 w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[10px]">2</span>
                                        <span>Under <strong>OAuth &amp; Permissions</strong>, add Bot Token Scopes: <code class="bg-gray-200 px-1 rounded">chat:write</code> and <code class="bg-gray-200 px-1 rounded">chat:write.public</code></span>
                                    </li>
                                    <li class="flex gap-2 text-xs text-gray-600">
                                        <span class="shrink-0 w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[10px]">3</span>
                                        <span>Click <strong>Install to Workspace</strong> and approve the permissions</span>
                                    </li>
                                    <li class="flex gap-2 text-xs text-gray-600">
                                        <span class="shrink-0 w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[10px]">4</span>
                                        <span>Copy the <strong>Bot User OAuth Token</strong> (starts with <code class="bg-gray-200 px-1 rounded">xoxb-</code>) and paste it above</span>
                                    </li>
                                    <li class="flex gap-2 text-xs text-gray-600">
                                        <span class="shrink-0 w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[10px]">5</span>
                                        <span>In Slack, type <code class="bg-gray-200 px-1 rounded">/invite @YourBotName</code> in the target channel</span>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                        <button
                            class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors"
                            @click="showSlackModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            class="px-4 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-md transition-colors flex items-center gap-1.5"
                            :disabled="isSavingSlack || !slackModalToken.trim() || !slackModalChannels.length"
                            @click="saveSlackConfig"
                        >
                            <FontAwesomeIcon v-if="isSavingSlack" :icon="['fal', 'fa-cog']" class="text-xs animate-spin" />
                            {{ isSavingSlack ? 'Saving...' : 'Save Configuration' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
