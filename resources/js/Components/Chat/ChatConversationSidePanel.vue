<script setup lang="ts">
import { ref, computed, inject, onMounted, watch } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faTag, faRobot, faChartLine, faCopy, faCheck, faTimes } from '@fal'
import CustomerTimeline from '@/Components/Showcases/Grp/CustomerTimeline.vue'
import ChatActivityTimeline from '@/Components/Chat/ChatActivityTimeline.vue'

library.add(faTag, faRobot, faChartLine, faCopy, faCheck, faTimes)

type SidePanelTab = 'profile' | 'statistics' | 'timeline' | 'log'

interface PanelSession {
    ulid: string
    contact_name: string
    is_guest: boolean
    shop_name?: string | null
    status: string
    priority?: string | null
    assigned_agent?: string | null
    started?: string | null
    ai_summary?: {
        summary?: string
        key_points?: string[]
        sentiment?: string
    } | null
}

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

const props = defineProps<{
    session: PanelSession
}>()

const emit = defineEmits<{ (e: 'close'): void }>()

const layout: any = inject('layout', {})
const baseUrl = layout?.appUrl ?? ''
const themePrimary = computed<string>(() => layout?.app?.theme?.[0] ?? '#16a34a')
const avatarStyle = computed(() => ({ backgroundColor: themePrimary.value + '1A', color: themePrimary.value }))
const badgeStyle = computed(() => ({ backgroundColor: themePrimary.value + '1A', color: themePrimary.value }))

const activeTab = ref<SidePanelTab>('profile')
const isCopied = ref(false)

const customerProfile = ref<{ tags: CustomerTag[]; stats: CustomerStats | null }>({ tags: [], stats: null })
const isLoadingProfile = ref(false)
const profileLoaded = ref(false)

const timelineData = ref<any>({ events: [] })
const isLoadingTimeline = ref(false)
const timelineLoaded = ref(false)
const timelineError = ref<string | null>(null)

const statusColors: Record<string, string> = {
    active:      'bg-green-100 text-green-700',
    waiting:     'bg-yellow-100 text-yellow-700',
    resolved:    'bg-blue-100 text-blue-700',
    transferred: 'bg-purple-100 text-purple-700',
    closed:      'bg-gray-100 text-gray-600',
}

const tabs: { key: SidePanelTab; label: string; onlyRegistered?: boolean }[] = [
    { key: 'profile',    label: 'Profile' },
    { key: 'statistics', label: 'Statistics', onlyRegistered: true },
    { key: 'timeline',   label: 'Timeline',   onlyRegistered: true },
    { key: 'log',        label: 'Log' },
]

const loadCustomerProfile = async () => {
    if (props.session.is_guest || profileLoaded.value || !props.session.ulid) return
    try {
        isLoadingProfile.value = true
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/customer-profile`)
        customerProfile.value = res.data
        profileLoaded.value = true
    } finally {
        isLoadingProfile.value = false
    }
}

const loadTimeline = async () => {
    if (props.session.is_guest || timelineLoaded.value || !props.session.ulid) return
    try {
        isLoadingTimeline.value = true
        timelineError.value = null
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/customer-timeline`)
        timelineData.value = res.data
        timelineLoaded.value = true
    } catch (e: any) {
        timelineError.value = e.response?.data?.message ?? e.message ?? 'Failed to load timeline'
    } finally {
        isLoadingTimeline.value = false
    }
}

const resetAndLoad = () => {
    profileLoaded.value = false
    timelineLoaded.value = false
    customerProfile.value = { tags: [], stats: null }
    activeTab.value = 'profile'
    loadCustomerProfile()
}

watch(() => props.session.ulid, () => resetAndLoad())

watch(activeTab, async (tab) => {
    if ((tab === 'profile' || tab === 'statistics') && !profileLoaded.value) await loadCustomerProfile()
    if (tab === 'timeline' && !timelineLoaded.value) await loadTimeline()
})

onMounted(() => loadCustomerProfile())

const getInitials = (name: string): string =>
    (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()

const formatStatDate = (date: string | null): string => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatCurrency = (amount: number | null | undefined, symbol: string): string => {
    if (amount == null) return '-'
    return `${symbol} ${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
}

const copyChatId = async () => {
    await navigator.clipboard.writeText(props.session.ulid)
    isCopied.value = true
    setTimeout(() => { isCopied.value = false }, 2000)
}
</script>

<template>
    <div class="w-96 shrink-0 flex flex-col border-l border-gray-200 bg-white overflow-hidden">
        <!-- Contact Header -->
        <div class="relative flex flex-col items-center px-4 py-4 border-b border-gray-100 text-center shrink-0">
            <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-600" @click="emit('close')">
                <FontAwesomeIcon :icon="['fal', 'fa-times']" class="text-sm" />
            </button>
            <div class="w-12 h-12 rounded-full flex items-center justify-center text-base font-bold mb-2" :style="avatarStyle">
                {{ getInitials(session.contact_name) }}
            </div>
            <p class="text-sm font-semibold text-gray-800">{{ session.contact_name }}</p>
            <span class="mt-1 inline-flex items-center justify-center px-2 py-0.5 rounded-sm text-[11px] font-medium"
                :class="session.is_guest ? 'bg-blue-100 text-blue-800' : ''"
                :style="!session.is_guest ? badgeStyle : {}">
                {{ session.is_guest ? 'Guest' : 'Customer' }}
            </span>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-100 shrink-0 text-xs">
            <template v-for="tab in tabs" :key="tab.key">
                <button v-if="!tab.onlyRegistered || !session.is_guest"
                    class="flex-1 py-2.5 font-medium transition-colors"
                    :class="activeTab === tab.key ? 'border-b-2' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    :style="activeTab === tab.key ? { color: themePrimary, borderBottomColor: themePrimary } : {}"
                    @click="activeTab = tab.key">
                    {{ tab.label }}
                </button>
            </template>
        </div>

        <!-- Tab Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Profile -->
            <div v-if="activeTab === 'profile'" class="divide-y divide-gray-100">
                <div class="px-4 py-3 space-y-2.5">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Contact</p>
                    <div class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">Name</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">{{ session.contact_name || '-' }}</div>
                    </div>
                    <div v-if="session.shop_name" class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">Shop</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">{{ session.shop_name }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Chat ID</div>
                        <div class="col-span-2 flex items-center gap-1">
                            <code class="text-[11px] font-mono text-gray-700 bg-gray-100 rounded px-1.5 py-0.5 truncate">{{ session.ulid }}</code>
                            <button class="shrink-0 text-gray-400 hover:text-gray-600" @click="copyChatId">
                                <FontAwesomeIcon :icon="isCopied ? ['fal', 'fa-check'] : ['fal', 'fa-copy']" class="text-xs" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="px-4 py-3 space-y-2.5">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Session</p>
                    <div class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Status</div>
                        <div class="col-span-2">
                            <span class="text-xs font-medium capitalize rounded-full px-2 py-0.5"
                                :class="statusColors[session.status] ?? 'bg-gray-100 text-gray-600'">
                                {{ session.status }}
                            </span>
                        </div>
                    </div>
                    <div v-if="session.priority" class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Priority</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800 capitalize">{{ session.priority }}</div>
                    </div>
                    <div v-if="session.assigned_agent" class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Agent</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">{{ session.assigned_agent }}</div>
                    </div>
                    <div v-if="session.started" class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Started</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">{{ session.started }}</div>
                    </div>
                </div>

                <div v-if="!session.is_guest" class="px-4 py-3">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Tags</p>
                    <div v-if="isLoadingProfile" class="text-xs text-gray-400">Loading...</div>
                    <div v-else-if="customerProfile.tags.length" class="flex flex-wrap gap-1.5">
                        <span v-for="tag in customerProfile.tags" :key="tag.id"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border font-medium bg-indigo-50 text-indigo-700 border-indigo-200">
                            <FontAwesomeIcon :icon="['fal', 'fa-tag']" class="text-[9px] opacity-70" />
                            {{ tag.name }}
                        </span>
                    </div>
                    <div v-else class="text-xs text-gray-400 italic">No tags</div>
                </div>

                <div v-if="session.ai_summary?.summary" class="px-4 py-3">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="text-indigo-400" />
                        AI Summary
                    </p>
                    <p class="text-xs text-gray-700 leading-relaxed">{{ session.ai_summary.summary }}</p>
                </div>
            </div>

            <!-- Statistics -->
            <div v-if="activeTab === 'statistics'" class="p-4">
                <div v-if="isLoadingProfile" class="flex items-center justify-center py-10 text-gray-400 text-xs">Loading...</div>
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
                </div>
            </div>

            <!-- Timeline -->
            <div v-if="activeTab === 'timeline'">
                <div v-if="isLoadingTimeline" class="p-4 space-y-2">
                    <div class="h-4 bg-gray-100 rounded animate-pulse w-3/4" />
                    <div class="h-4 bg-gray-100 rounded animate-pulse w-1/2" />
                </div>
                <div v-else-if="timelineError" class="flex flex-col items-center justify-center py-10 gap-2 text-center px-4">
                    <p class="text-xs text-red-500">{{ timelineError }}</p>
                </div>
                <CustomerTimeline v-else :data="timelineData" />
            </div>

            <!-- Log -->
            <div v-if="activeTab === 'log'" class="px-4">
                <ChatActivityTimeline :sessionUlid="session.ulid" :baseUrl="baseUrl" />
            </div>
        </div>
    </div>
</template>
