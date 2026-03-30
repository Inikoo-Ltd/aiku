<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 28 Mar 2025 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faUserEdit,
    faStickyNote,
    faInboxIn,
    faPaperPlane,
    faTimesCircle,
    faMoneyBill,
    faEnvelope,
    faCodeBranch,
    faChevronDown,
    faChevronUp,
    faFilter,
    faGlobe,
    faEye,
    faShoppingCart,
    faUndo,
} from '@fal'
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(
    faUserEdit, faStickyNote, faInboxIn, faPaperPlane, faTimesCircle,
    faMoneyBill, faEnvelope, faCodeBranch, faChevronDown, faChevronUp, faFilter, faGlobe,
    faEye, faShoppingCart, faUndo
)

interface TimelineEvent {
    id: string
    type: string
    datetime: string
    title: string
    subtitle: string | null
    icon: string[]
    color: string
    metadata: Record<string, unknown>
}

const props = defineProps<{
    data: {
        events: TimelineEvent[]
    }
    tab?: string
}>()

const activeFilter = ref<string | null>(null)
const expandedIds = ref<Set<string>>(new Set())

const filterOptions = [
    { key: null, label: 'All' },
    { key: 'order', label: 'Orders', types: ['order_placed', 'order_dispatched', 'order_cancelled'] },
    { key: 'account_update', label: 'Account Changes', types: ['account_update', 'note'] },
    { key: 'payment', label: 'Payments', types: ['payment'] },
    { key: 'email', label: 'Emails', types: ['email'] },
    { key: 'web_activity', label: 'Website Activity', types: ['page_view', 'product_view', 'add_to_basket'] },
    { key: 'return', label: 'Returns', types: ['return'] },
]

const colorClasses: Record<string, { bg: string; icon: string }> = {
    blue:   { bg: 'bg-blue-100',   icon: 'text-blue-600' },
    green:  { bg: 'bg-green-100',  icon: 'text-green-600' },
    red:    { bg: 'bg-red-100',    icon: 'text-red-600' },
    indigo: { bg: 'bg-indigo-100', icon: 'text-indigo-600' },
    purple: { bg: 'bg-purple-100', icon: 'text-purple-600' },
    yellow: { bg: 'bg-yellow-100', icon: 'text-yellow-600' },
    teal:   { bg: 'bg-teal-100',   icon: 'text-teal-600' },
    orange: { bg: 'bg-orange-100', icon: 'text-orange-600' },
}

const filteredEvents = computed(() => {
    if (!activeFilter.value) {
        return props.data?.events ?? []
    }

    const option = filterOptions.find(f => f.key === activeFilter.value)
    if (!option?.types) {
        return props.data?.events ?? []
    }

    return (props.data?.events ?? []).filter(e => option.types!.includes(e.type))
})

const toggleExpand = (id: string) => {
    if (expandedIds.value.has(id)) {
        expandedIds.value.delete(id)
    } else {
        expandedIds.value.add(id)
    }
}

const hasExpandableData = (event: TimelineEvent): boolean => {
    if (event.type === 'note') {
        return !!event.subtitle
    }
    if (['account_update'].includes(event.type)) {
        return !!(event.metadata?.old_values || event.metadata?.new_values)
    }
    if (['order_placed', 'order_dispatched', 'order_cancelled'].includes(event.type)) {
        return !!(event.metadata?.net_amount || event.metadata?.total_amount)
    }
    if (event.type === 'payment') {
        return !!(event.metadata?.amount)
    }
    if (event.type === 'email') {
        return !!(event.metadata?.number_reads !== undefined)
    }
    if (['page_view', 'product_view'].includes(event.type)) {
        return !!(event.metadata?.duration_seconds)
    }
    if (event.type === 'add_to_basket') {
        return !!(event.metadata?.product_id || event.metadata?.quantity)
    }
    if (event.type === 'return') {
        return !!(event.metadata?.return_reason || event.metadata?.number_items)
    }
    return false
}

const getColorClasses = (color: string) => colorClasses[color] ?? colorClasses['blue']

const formatMetadataValue = (value: unknown): string => {
    if (value === null || value === undefined) return '-'
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}
</script>

<template>
    <div class="p-4 max-w-3xl mx-auto">
        <!-- Filter Bar -->
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <FontAwesomeIcon :icon="['fal', 'fa-filter']" class="text-gray-400 text-sm" />
            <button
                v-for="filter in filterOptions"
                :key="String(filter.key)"
                @click="activeFilter = filter.key"
                class="px-3 py-1 rounded-full text-sm font-medium border transition-colors"
                :class="activeFilter === filter.key
                    ? 'bg-gray-800 text-white border-gray-800'
                    : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'"
            >
                {{ filter.label }}
            </button>
        </div>

        <!-- Empty State -->
        <div v-if="filteredEvents.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
            <div class="mb-4 animate-pulse">
                <FontAwesomeIcon :icon="['fal', 'fa-code-branch']" class="text-gray-300 text-5xl" />
            </div>
            <p class="text-gray-500 text-base font-medium">No activity found</p>
            <p class="text-gray-400 text-sm mt-1">
                {{ activeFilter ? 'Try selecting a different filter.' : 'No activity in the last 12 months.' }}
            </p>
        </div>

        <!-- Timeline Feed -->
        <div v-else class="relative">
            <!-- Vertical line -->
            <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-200" />

            <ol class="space-y-0">
                <li
                    v-for="event in filteredEvents"
                    :key="event.id"
                    class="relative flex gap-4 pb-6"
                >
                    <!-- Icon Bubble -->
                    <div
                        class="relative z-10 flex h-10 w-10 flex-none items-center justify-center rounded-full ring-2 ring-white"
                        :class="getColorClasses(event.color).bg"
                    >
                        <FontAwesomeIcon
                            :icon="event.icon"
                            class="text-sm"
                            :class="getColorClasses(event.color).icon"
                        />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0 pt-1.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 leading-tight">
                                    {{ event.title }}
                                </p>
                                <p v-if="event.subtitle && !(event.type === 'note' && expandedIds.has(event.id))" class="text-sm text-gray-500 mt-0.5 truncate">
                                    {{ event.subtitle }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 flex-none">
                                <time
                                    :datetime="event.datetime"
                                    :title="useFormatTime(event.datetime, { formatTime: 'hm' })"
                                    class="text-xs text-gray-400 whitespace-nowrap"
                                >
                                    {{ useFormatTime(event.datetime, { formatTime: 'short-datetime' }) }}
                                </time>

                                <button
                                    v-if="hasExpandableData(event)"
                                    @click="toggleExpand(event.id)"
                                    class="text-gray-400 hover:text-gray-600 transition-colors"
                                >
                                    <FontAwesomeIcon
                                        :icon="expandedIds.has(event.id) ? ['fal', 'fa-chevron-up'] : ['fal', 'fa-chevron-down']"
                                        class="text-xs"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Expanded Metadata -->
                        <Transition name="expand">
                            <div
                                v-if="expandedIds.has(event.id)"
                                class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600"
                            >
                                <!-- Note: full text -->
                                <template v-if="event.type === 'note'">
                                    <p class="whitespace-pre-wrap leading-relaxed">{{ event.subtitle }}</p>
                                </template>

                                <!-- Account Update: old → new values -->
                                <template v-else-if="event.type === 'account_update' && (event.metadata.old_values || event.metadata.new_values)">
                                    <div
                                        v-for="key in Object.keys({ ...(event.metadata.old_values as object ?? {}), ...(event.metadata.new_values as object ?? {}) })"
                                        :key="key"
                                        class="flex items-start gap-2 py-0.5"
                                    >
                                        <span class="font-medium capitalize text-gray-700 w-28 flex-none">{{ key.replace(/_/g, ' ') }}</span>
                                        <span class="text-red-500 line-through">{{ formatMetadataValue((event.metadata.old_values as Record<string, unknown>)?.[key]) }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="text-green-600">{{ formatMetadataValue((event.metadata.new_values as Record<string, unknown>)?.[key]) }}</span>
                                    </div>
                                </template>

                                <!-- Order: amount details -->
                                <template v-else-if="['order_placed', 'order_dispatched', 'order_cancelled'].includes(event.type)">
                                    <div v-if="event.metadata.net_amount" class="flex gap-2">
                                        <span class="font-medium text-gray-700">Net:</span>
                                        <span>{{ event.metadata.currency_code }} {{ event.metadata.net_amount }}</span>
                                    </div>
                                    <div v-if="event.metadata.total_amount" class="flex gap-2">
                                        <span class="font-medium text-gray-700">Total:</span>
                                        <span>{{ event.metadata.currency_code }} {{ event.metadata.total_amount }}</span>
                                    </div>
                                    <div v-if="event.metadata.state" class="flex gap-2">
                                        <span class="font-medium text-gray-700">State:</span>
                                        <span>{{ String(event.metadata.state).replace(/_/g, ' ') }}</span>
                                    </div>
                                </template>

                                <!-- Payment: amount, state -->
                                <template v-else-if="event.type === 'payment'">
                                    <div v-if="event.metadata.amount" class="flex gap-2">
                                        <span class="font-medium text-gray-700">Amount:</span>
                                        <span>{{ event.metadata.amount }}</span>
                                    </div>
                                    <div v-if="event.metadata.state" class="flex gap-2">
                                        <span class="font-medium text-gray-700">State:</span>
                                        <span>{{ String(event.metadata.state).replace(/_/g, ' ') }}</span>
                                    </div>
                                </template>

                                <!-- Email: reads, clicks -->
                                <template v-else-if="event.type === 'email'">
                                    <div class="flex gap-4">
                                        <div class="flex gap-2">
                                            <span class="font-medium text-gray-700">Reads:</span>
                                            <span>{{ event.metadata.number_reads ?? 0 }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="font-medium text-gray-700">Clicks:</span>
                                            <span>{{ event.metadata.number_clicks ?? 0 }}</span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Page view / Product view: duration -->
                                <template v-else-if="['page_view', 'product_view'].includes(event.type)">
                                    <div class="space-y-0.5">
                                        <div v-if="event.metadata.duration_seconds" class="flex gap-2">
                                            <span class="font-medium text-gray-700 w-24 flex-none">{{ trans('Duration') }}:</span>
                                            <span>{{ event.metadata.duration_seconds }}s</span>
                                        </div>
                                        <div v-if="event.metadata.page_sub_type" class="flex gap-2">
                                            <span class="font-medium text-gray-700 w-24 flex-none">{{ trans('Type') }}:</span>
                                            <span class="capitalize">{{ String(event.metadata.page_sub_type).replace(/_/g, ' ') }}</span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Add to basket: product, quantity -->
                                <template v-else-if="event.type === 'add_to_basket'">
                                    <div class="space-y-0.5">
                                        <div v-if="event.metadata.quantity" class="flex gap-2">
                                            <span class="font-medium text-gray-700 w-24 flex-none">{{ trans('Quantity') }}:</span>
                                            <span>{{ event.metadata.quantity }}</span>
                                        </div>
                                        <div v-if="event.metadata.product_id" class="flex gap-2">
                                            <span class="font-medium text-gray-700 w-24 flex-none">Product ID:</span>
                                            <span>{{ event.metadata.product_id }}</span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Return: reason, items -->
                                <template v-else-if="event.type === 'return'">
                                    <div class="space-y-0.5">
                                        <div v-if="event.metadata.number_items" class="flex gap-2">
                                            <span class="font-medium text-gray-700 w-24 flex-none">{{ trans('Items') }}:</span>
                                            <span>{{ event.metadata.number_items }}</span>
                                        </div>
                                        <div v-if="event.metadata.state" class="flex gap-2">
                                            <span class="font-medium text-gray-700 w-24 flex-none">{{ trans('State') }}:</span>
                                            <span class="capitalize">{{ String(event.metadata.state).replace(/_/g, ' ') }}</span>
                                        </div>
                                        <div v-if="event.metadata.return_reason" class="flex gap-2 mt-1">
                                            <span class="font-medium text-gray-700 w-24 flex-none">{{ trans('Reason') }}:</span>
                                            <span class="whitespace-pre-wrap">{{ event.metadata.return_reason }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </Transition>
                    </div>
                </li>
            </ol>
        </div>
    </div>
</template>

<style scoped>
.expand-enter-active,
.expand-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
    max-height: 0;
    opacity: 0;
}

.expand-enter-to,
.expand-leave-from {
    max-height: 200px;
    opacity: 1;
}
</style>
