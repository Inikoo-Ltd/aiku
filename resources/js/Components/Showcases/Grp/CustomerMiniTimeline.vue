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
    faGlobe,
    faEye,
    faShoppingCart,
    faUndo,
} from '@fal'
import { trans } from 'laravel-vue-i18n'
import { useFormatTime } from '@/Composables/useFormatTime'

library.add(
    faUserEdit, faStickyNote, faInboxIn, faPaperPlane, faTimesCircle,
    faMoneyBill, faEnvelope, faCodeBranch, faChevronDown, faChevronUp, faGlobe,
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
    events: TimelineEvent[]
    onViewAll?: () => void
}>()

const expandedIds = ref<Set<string>>(new Set())

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

const recentEvents = computed(() => (props.events ?? []).slice(0, 10))

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
    <div class="flex flex-col">
        <!-- Empty State -->
        <div v-if="recentEvents.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
            <div class="mb-3 animate-pulse">
                <FontAwesomeIcon :icon="['fal', 'fa-code-branch']" class="text-gray-300 text-4xl" />
            </div>
            <p class="text-gray-500 text-sm font-medium">{{ trans('No activity found') }}</p>
            <p class="text-gray-400 text-xs mt-1">{{ trans('No activity in the last 12 months.') }}</p>
        </div>

        <!-- Timeline Feed -->
        <div v-else class="relative">
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200" />

            <ol class="space-y-0">
                <li
                    v-for="event in recentEvents"
                    :key="event.id"
                    class="relative flex gap-3 pb-5"
                >
                    <!-- Icon Bubble -->
                    <div
                        class="relative z-10 flex h-8 w-8 flex-none items-center justify-center rounded-full ring-2 ring-white"
                        :class="getColorClasses(event.color).bg"
                    >
                        <FontAwesomeIcon
                            :icon="event.icon"
                            class="text-xs"
                            :class="getColorClasses(event.color).icon"
                        />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0 pt-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-900 leading-tight">
                                    {{ event.title }}
                                </p>
                                <p v-if="event.subtitle && !(event.type === 'note' && expandedIds.has(event.id))" class="text-xs text-gray-500 mt-0.5 truncate">
                                    {{ event.subtitle }}
                                </p>
                            </div>

                            <div class="flex items-center gap-1.5 flex-none">
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
                                class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-2.5 text-xs text-gray-600"
                            >
                                <template v-if="event.type === 'note'">
                                    <p class="whitespace-pre-wrap leading-relaxed">{{ event.subtitle }}</p>
                                </template>

                                <template v-else-if="event.type === 'account_update' && (event.metadata.old_values || event.metadata.new_values)">
                                    <div
                                        v-for="key in Object.keys({ ...(event.metadata.old_values as object ?? {}), ...(event.metadata.new_values as object ?? {}) })"
                                        :key="key"
                                        class="flex items-start gap-2 py-0.5"
                                    >
                                        <span class="font-medium capitalize text-gray-700 w-24 flex-none">{{ key.replace(/_/g, ' ') }}</span>
                                        <span class="text-red-500 line-through">{{ formatMetadataValue((event.metadata.old_values as Record<string, unknown>)?.[key]) }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="text-green-600">{{ formatMetadataValue((event.metadata.new_values as Record<string, unknown>)?.[key]) }}</span>
                                    </div>
                                </template>

                                <template v-else-if="['order_placed', 'order_dispatched', 'order_cancelled'].includes(event.type)">
                                    <div v-if="event.metadata.net_amount" class="flex gap-2">
                                        <span class="font-medium text-gray-700 text-gray-700">Net:</span>
                                        <span>{{ event.metadata.currency_code }} {{ event.metadata.net_amount }}</span>
                                    </div>
                                    <div v-if="event.metadata.total_amount" class="flex gap-2">
                                        <span class="font-medium text-gray-700 text-gray-700">Total:</span>
                                        <span>{{ event.metadata.currency_code }} {{ event.metadata.total_amount }}</span>
                                    </div>
                                    <div v-if="event.metadata.state" class="flex gap-2">
                                        <span class="font-medium text-gray-700 text-gray-700">State:</span>
                                        <span>{{ String(event.metadata.state).replace(/_/g, ' ') }}</span>
                                    </div>
                                </template>

                                <template v-else-if="event.type === 'payment'">
                                    <div v-if="event.metadata.amount" class="flex gap-2">
                                        <span class="font-medium text-gray-700 text-gray-700">Amount:</span>
                                        <span>{{ event.metadata.amount }}</span>
                                    </div>
                                    <div v-if="event.metadata.state" class="flex gap-2">
                                        <span class="font-medium text-gray-700 text-gray-700">State:</span>
                                        <span>{{ String(event.metadata.state).replace(/_/g, ' ') }}</span>
                                    </div>
                                </template>

                                <template v-else-if="event.type === 'email'">
                                    <div class="flex gap-4">
                                        <div class="flex gap-2">
                                            <span class="font-medium text-gray-700 text-gray-700">Reads:</span>
                                            <span>{{ event.metadata.number_reads ?? 0 }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="font-medium text-gray-700 text-gray-700">Clicks:</span>
                                            <span>{{ event.metadata.number_clicks ?? 0 }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </Transition>
                    </div>
                </li>
            </ol>
        </div>

        <!-- View All Link -->
        <div v-if="onViewAll && (events?.length ?? 0) > 0" class="pt-2 border-t border-gray-200 text-center">
            <button
                @click="onViewAll"
                class="text-xs text-indigo-600 hover:underline"
            >
                {{ trans('View all activity') }}
            </button>
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
