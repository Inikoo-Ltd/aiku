<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faCommentDots,
    faHourglassHalf,
    faCheckCircle,
    faExchangeAlt,
    faStoreAlt,
    faRobot,
} from '@fal'

library.add(faCommentDots, faHourglassHalf, faCheckCircle, faExchangeAlt, faStoreAlt, faRobot)

defineProps<{
    data: {}
    tab?: string
}>()

const statusConfig: Record<string, { icon: string; color: string; label: string }> = {
    active:      { icon: 'fal fa-comment-dots',   color: 'text-green-500',  label: 'Active' },
    waiting:     { icon: 'fal fa-hourglass-half',  color: 'text-yellow-500', label: 'Waiting' },
    closed:      { icon: 'fal fa-check-circle',    color: 'text-gray-400',   label: 'Closed' },
    transferred: { icon: 'fal fa-exchange-alt',    color: 'text-purple-500', label: 'Transferred' },
    resolved:    { icon: 'fal fa-check-circle',    color: 'text-blue-400',   label: 'Resolved' },
}

function formatDate(dateObj: any): string {
    if (!dateObj) return '-'
    return dateObj.formatted ?? dateObj
}

const sentimentColor: Record<string, string> = {
    positive: 'text-green-600',
    negative: 'text-red-500',
    neutral:  'text-gray-500',
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item }">
            <div class="flex items-center gap-x-1.5 whitespace-nowrap">
                <FontAwesomeIcon
                    :icon="statusConfig[item.status]?.icon ?? 'fal fa-circle'"
                    :class="statusConfig[item.status]?.color ?? 'text-gray-400'"
                    class="text-sm"
                    fixed-width />
                <span class="capitalize text-sm">
                    {{ statusConfig[item.status]?.label ?? item.status }}
                </span>
            </div>
        </template>

        <template #cell(contact)="{ item }">
            <Link
                v-if="item.route && item.route.parameters?.shop"
                :href="route(item.route.name, item.route.parameters)"
                class="primaryLink">
                {{ item.contact_name }}
            </Link>
            <span v-else>{{ item.contact_name }}</span>
        </template>

        <template #cell(shop_name)="{ item }">
            <div class="flex items-center gap-x-1.5 text-sm text-gray-600">
                <FontAwesomeIcon icon="fal fa-store-alt" class="text-gray-400" fixed-width />
                {{ item.shop_name ?? '—' }}
            </div>
        </template>

        <template #cell(assigned_agent)="{ item }">
            <span v-if="item.assigned_agent" class="text-sm">{{ item.assigned_agent }}</span>
            <span v-else class="text-sm text-gray-400">—</span>
        </template>

        <template #cell(ai_summary)="{ item }">
            <div v-if="item.ai_summary?.summary" class="relative group inline-block">
                <!-- Trigger -->
                <div class="flex items-center gap-1.5 cursor-default">
                    <FontAwesomeIcon
                        icon="fal fa-robot"
                        class="text-indigo-400 text-sm"
                        fixed-width />
                    <span
                        class="text-xs text-gray-600 max-w-[160px] truncate"
                        :class="sentimentColor[item.ai_summary.sentiment] ?? ''">
                        {{ item.ai_summary.summary }}
                    </span>
                </div>

                <!-- Popover -->
                <div class="absolute z-50 bottom-full left-0 mb-2 w-80 opacity-0 invisible
                            group-hover:opacity-100 group-hover:visible
                            transition-all duration-150 pointer-events-none">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-xl p-4 text-left">
                        <!-- Header -->
                        <div class="flex items-center gap-2 mb-3">
                            <FontAwesomeIcon icon="fal fa-robot" class="text-indigo-400 text-sm" fixed-width />
                            <span class="text-xs font-semibold text-gray-700">AI Summary</span>
                            <span
                                v-if="item.ai_summary.sentiment"
                                class="ml-auto text-[10px] font-medium capitalize px-1.5 py-0.5 rounded-full"
                                :class="{
                                    'bg-green-100 text-green-700': item.ai_summary.sentiment === 'positive',
                                    'bg-red-100 text-red-600': item.ai_summary.sentiment === 'negative',
                                    'bg-gray-100 text-gray-500': item.ai_summary.sentiment === 'neutral',
                                }">
                                {{ item.ai_summary.sentiment }}
                            </span>
                        </div>

                        <!-- Summary text -->
                        <p class="text-xs text-gray-700 leading-relaxed mb-3">
                            {{ item.ai_summary.summary }}
                        </p>

                        <!-- Key points -->
                        <template v-if="item.ai_summary.key_points?.length">
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">
                                Key Points
                            </p>
                            <ul class="space-y-1">
                                <li
                                    v-for="(point, i) in item.ai_summary.key_points"
                                    :key="i"
                                    class="flex items-start gap-1.5 text-xs text-gray-600">
                                    <span class="mt-1 w-1 h-1 rounded-full bg-indigo-400 shrink-0"></span>
                                    {{ point }}
                                </li>
                            </ul>
                        </template>

                        <!-- Arrow -->
                        <div class="absolute -bottom-1.5 left-4 w-3 h-3 bg-white border-b border-r border-gray-200 rotate-45"></div>
                    </div>
                </div>
            </div>
            <span v-else class="text-sm text-gray-400">—</span>
        </template>

        <template #cell(created_at)="{ item }">
            <span class="text-sm text-gray-600">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #cell(closed_at)="{ item }">
            <span v-if="item.closed_at" class="text-sm text-gray-600">{{ formatDate(item.closed_at) }}</span>
            <span v-else class="text-sm text-gray-400">—</span>
        </template>
    </Table>
</template>
