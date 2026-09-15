<script setup lang="ts">
import { computed, inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
import type { TicketBadgeRow, TicketRecentUpdate } from '@/types/TicketBadges'

const props = defineProps<{
    title: string
    rows: Record<string, TicketBadgeRow>
    close: () => void
    recent?: TicketRecentUpdate[]
}>()

const layout = inject('layout', layoutStructure)

const recentItems = computed<TicketRecentUpdate[]>(() => props.recent
    ?? (layout.notifications ?? [])
        .filter(notif => !notif.read && String(notif.route ?? '').includes('/tickets/'))
        .slice(0, 8)
        .map(notif => ({ id: String(notif.id), title: notif.title, body: notif.body, route: String(notif.route), read: false, created_at: notif.created_at })))

const rowHref = (row: TicketBadgeRow) => {
    const query: Record<string, string> = {}
    Object.entries(row.elements).forEach(([key, value]) => query[`elements[${key}]`] = value)
    return route('grp.tickets.index', query)
}
</script>

<template>
    <div class="w-full text-sm">
        <div class="font-semibold mb-2">{{ title }}</div>
        <ul class="divide-y divide-gray-100">
            <li v-for="(row, key) in rows" :key="key">
                <Link :href="rowHref(row)" @click="close()" class="flex justify-between py-1.5 hover:bg-gray-50 px-1 rounded" :class="row.count ? '' : 'text-gray-400'">
                    <span>{{ row.label }}</span>
                    <span class="tabular-nums font-medium" :class="key === 'overdue' && row.count ? 'text-red-600' : ''">{{ row.count }}</span>
                </Link>
            </li>
        </ul>
        <div v-if="recentItems.length" class="mt-3 pt-2 border-t border-gray-200">
            <div class="text-xs text-gray-500 mb-1">{{ trans('Recent') }}</div>
            <Link v-for="item in recentItems" :key="item.id + item.title" :href="item.route" @click="close()" class="block py-1 px-1 rounded hover:bg-gray-50 transition duration-200">
                <div class="flex justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-1.5">
                        <span v-if="!item.read" class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-500" :title="trans('Not opened yet')" />
                        <span class="truncate" :class="item.read ? 'text-gray-500' : 'font-medium text-gray-900'">{{ item.title }}</span>
                    </span>
                    <span class="text-[10px] text-gray-400 shrink-0">{{ useFormatTime(item.created_at) }}</span>
                </div>
                <div class="text-xs text-gray-500 truncate" :class="!item.read && 'pl-3'">{{ item.body }}</div>
            </Link>
        </div>
    </div>
</template>
