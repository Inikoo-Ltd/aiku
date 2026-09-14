<script setup lang="ts">
import { computed, inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
import type { TicketBadgeRow } from '@/types/TicketBadges'

const props = defineProps<{
    title: string
    rows: Record<string, TicketBadgeRow>
    close: () => void
}>()

const layout = inject('layout', layoutStructure)

const ticketNotifications = computed(() => (layout.notifications ?? []).filter(notif => !notif.read && String(notif.route ?? '').includes('/tickets/')).slice(0, 8))

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
        <div v-if="ticketNotifications.length" class="mt-3 pt-2 border-t border-gray-200">
            <div class="text-xs text-gray-500 mb-1">{{ trans('Recent') }}</div>
            <Link v-for="notif in ticketNotifications" :key="notif.id + notif.title" :href="String(notif.route)" @click="close()" class="block py-1 px-1 rounded hover:bg-gray-50">
                <div class="flex justify-between gap-2">
                    <span class="truncate">{{ notif.title }}</span>
                    <span class="text-[10px] text-gray-400 shrink-0">{{ useFormatTime(notif.created_at) }}</span>
                </div>
                <div class="text-xs text-gray-500 truncate">{{ notif.body }}</div>
            </Link>
        </div>
    </div>
</template>
