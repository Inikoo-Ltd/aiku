<script setup lang="ts">
import { ticketsRoute } from "@/Composables/useTicketsRoute"
import { computed, inject } from 'vue'
import { ctrans } from "@/Composables/useTrans"
import { Link } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useFormatTime } from '@/Composables/useFormatTime'
import type { TicketBadgeRow, TicketRecentUpdate } from '@/types/TicketBadges'

const props = defineProps<{
    title: string
    rows: Record<string, TicketBadgeRow>
    close: () => void
    recent?: TicketRecentUpdate[]
    openAll?: Record<string, string>
    waitingForMe?: TicketBadgeRow | null
}>()

const elementsHref = (elements: Record<string, string>) => {
    const query: Record<string, string> = {}
    Object.entries(elements).forEach(([key, value]) => query[`elements[${key}]`] = value)
    return ticketsRoute('index', query)
}

const layout = inject('layout', layoutStructure)

const sectionTitles: Record<string, string> = {
    team: ctrans('Team queue'),
    mine: ctrans('My work'),
    qa: ctrans('QA'),
}

const alertRows: Record<string, string> = {
    overdue: 'bg-red-100 text-red-700',
    qa_failed: 'bg-red-100 text-red-700',
    replied: 'bg-amber-100 text-amber-800',
    qa_to_check: 'bg-amber-100 text-amber-800',
    qa_passed: 'bg-green-100 text-green-700',
}

const sections = computed(() => {
    const grouped: { key: string; title: string | null; rows: [string, TicketBadgeRow][] }[] = []
    Object.entries(props.rows ?? {}).forEach(([key, row]) => {
        const sectionKey = row.section ?? ''
        let section = grouped.find((candidate) => candidate.key === sectionKey)
        if (!section) {
            section = { key: sectionKey, title: sectionTitles[sectionKey] ?? null, rows: [] }
            grouped.push(section)
        }
        section.rows.push([key, row])
    })

    return grouped
})

const recentItems = computed<TicketRecentUpdate[]>(() => props.recent
    ?? (layout.notifications ?? [])
        .filter(notif => !notif.read && String(notif.route ?? '').includes('/tickets/'))
        .slice(0, 8)
        .map(notif => ({ id: String(notif.id), title: notif.title, body: notif.body, route: String(notif.route), read: false, created_at: notif.created_at })))

const rowHref = (row: TicketBadgeRow) => elementsHref(row.elements)
</script>

<template>
    <div class="w-full text-sm">
        <div class="mb-2 flex items-center justify-between" :class="openAll ? 'border-b border-gray-100 pb-2' : ''">
            <span class="font-semibold text-gray-900">{{ title }}</span>
            <Link v-if="openAll" :href="elementsHref(openAll)" class="text-xs text-[--app-accent-strong] hover:underline" @click="close()">{{ ctrans("Open all") }}</Link>
        </div>
        <Link v-if="waitingForMe?.count" :href="rowHref(waitingForMe)" class="mb-2 block rounded-md bg-amber-50 px-2 py-1.5 text-xs text-amber-800 hover:bg-amber-100" @click="close()">
            {{ ctrans(":count waiting for your answer", { count: String(waitingForMe.count) }) }}
        </Link>
        <div v-for="(section, index) in sections" :key="section.key" :class="index ? 'mt-2 border-t border-gray-100 pt-2' : ''">
            <div v-if="section.title" class="mb-1 px-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ section.title }}</div>
            <ul>
                <li v-for="[key, row] in section.rows" :key="key">
                    <Link :href="rowHref(row)" @click="close()" class="flex items-center justify-between gap-x-3 rounded px-1 py-1.5 hover:bg-gray-50" :class="row.count ? 'text-gray-800' : 'text-gray-400'">
                        <span>{{ row.label }}</span>
                        <span class="min-w-6 rounded-full px-1.5 text-center text-xs font-semibold tabular-nums"
                            :class="row.count ? (alertRows[key] ?? 'bg-gray-100 text-gray-700') : 'text-gray-300'">
                            {{ row.count }}
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
        <div v-if="recentItems.length" class="mt-3 pt-2 border-t border-gray-200">
            <div class="text-xs text-gray-500 mb-1">{{ ctrans('Recent') }}</div>
            <Link v-for="item in recentItems" :key="item.id + item.title" v-tooltip="[item.title, item.body].filter(Boolean).join(' — ')" :href="item.route" @click="close()" class="block py-1 px-1 rounded hover:bg-gray-50 transition duration-200">
                <div class="flex justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-1.5">
                        <span v-if="!item.read" class="h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-500" :title="ctrans('Not opened yet')" />
                        <span class="truncate" :class="item.read ? 'text-gray-500' : 'font-medium text-gray-900'">{{ item.title }}</span>
                    </span>
                    <span class="text-[10px] text-gray-400 shrink-0">{{ useFormatTime(item.created_at) }}</span>
                </div>
                <div class="text-xs text-gray-500 truncate" :class="!item.read && 'pl-3'">{{ item.body }}</div>
            </Link>
        </div>
    </div>
</template>
