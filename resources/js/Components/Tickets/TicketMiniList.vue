<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ticketRoute } from "@/Composables/useTicketsRoute"
import { computed, inject, ref, watch } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVial, faShieldCheck, faShield, faLifeRing, faToolbox, faUserHeadset, faCircle, faUserCheck, faSpinner, faClock, faCommentDots, faRocket, faCheckCircle, faBan } from "@fal"

library.add(faLifeRing, faToolbox, faUserHeadset, faVial, faShieldCheck, faShield, faCircle, faUserCheck, faSpinner, faClock, faCommentDots, faRocket, faCheckCircle, faBan)

const props = defineProps<{
    title: string
    tickets: any[]
    empty: string
    dateKey?: string
    showAssignee?: boolean
    flat?: boolean
    sortKey?: string
    perPage?: number
}>()

const openQuickLook = inject<((ticket: any) => void) | null>("openTicketQuickLook", null)

const onRowClick = (ticket: any, event: MouseEvent) => {
    if (!openQuickLook || (event.target as HTMLElement | null)?.closest("a, button")) return
    openQuickLook(ticket)
}

const sortFields = [
    ...(props.sortKey && !["created_at", "updated_at"].includes(props.sortKey) ? [{ key: props.sortKey, label: ctrans("Latest") }] : []),
    { key: "created_at", label: ctrans("Created") },
    { key: "updated_at", label: ctrans("Updated") },
]

const sortField = ref(props.sortKey ?? "created_at")
const sortDesc = ref(true)

const cycleSortField = () => {
    sortField.value = sortFields[(sortFields.findIndex((option) => option.key === sortField.value) + 1) % sortFields.length].key
}

const sortedTickets = computed(() =>
    [...props.tickets].sort((a, b) => (new Date(a[sortField.value] ?? 0).getTime() - new Date(b[sortField.value] ?? 0).getTime()) * (sortDesc.value ? -1 : 1))
)
const page = ref(1)
const pageCount = computed(() => (props.perPage ? Math.max(1, Math.ceil(props.tickets.length / props.perPage)) : 1))
const shownTickets = computed(() => (props.perPage ? sortedTickets.value.slice((page.value - 1) * props.perPage, page.value * props.perPage) : sortedTickets.value))

watch([sortField, sortDesc], () => (page.value = 1))
watch(pageCount, (count) => {
    if (page.value > count) page.value = count
})

const daysAgo = (date?: string) => {
    const days = date ? Math.floor((Date.now() - new Date(date).getTime()) / 86400000) : -1
    return days >= 0 ? days : null
}
</script>

<template>
    <div class="bg-white" :class="!flat && 'rounded-lg shadow-sm border border-gray-300'">
        <div class="flex items-center gap-2 px-4 py-2 border-b border-gray-200">
            <h3 class="font-semibold">{{ title }}</h3>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 tabular-nums">{{ tickets.length }}</span>
            <slot name="filters" />
            <span v-if="tickets.length > 1" class="ml-auto flex items-center text-xs text-gray-500">
                <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="ctrans('Sort by')" @click="cycleSortField">
                    {{ sortFields.find((option) => option.key === sortField)?.label }}
                </button>
                <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="sortDesc ? ctrans('Newest first') : ctrans('Oldest first')" @click="sortDesc = !sortDesc">
                    {{ sortDesc ? "↓" : "↑" }}
                </button>
            </span>
        </div>
        <ul v-if="tickets.length" class="divide-y divide-gray-100 text-sm">
            <li v-for="ticket in shownTickets" :key="ticket.id" class="flex items-center gap-3 px-4 py-2" :class="openQuickLook && 'cursor-pointer transition duration-200 hover:bg-gray-50'" @click="onRowClick(ticket, $event)">
                <Icon :data="ticket.status_icon" />
                <span class="inline-flex items-center whitespace-nowrap"><Icon v-if="ticket.type_icon" :data="ticket.type_icon" class="mr-1 text-gray-400" /><Link :href="ticketRoute(ticket.reference)" class="primaryLink whitespace-nowrap">{{ ticket.reference }}</Link></span>
                <span class="truncate flex-1" :class="ticket.has_unread && 'font-semibold text-gray-900'" :title="ticket.subject">{{ ticket.subject }}</span>
                <span v-if="ticket.has_unread" v-tooltip="ctrans('Unread update')" class="size-2 shrink-0 rounded-full bg-indigo-500" />
                <Icon v-if="ticket.qa_status_icon" :data="ticket.qa_status_icon" />
                <slot name="person" :ticket="ticket">
                    <span v-if="showAssignee" class="text-xs text-gray-500 whitespace-nowrap">{{ ticket.assignee_username || "-" }}</span>
                </slot>
                <span class="text-xs text-gray-500 whitespace-nowrap" :title="useFormatTime(ticket[dateKey ?? sortField], { formatTime: 'hm' })">
                    {{ useFormatTime(ticket[dateKey ?? sortField], { formatTime: "d MMM" }) }}
                    <span v-if="daysAgo(ticket[dateKey ?? sortField]) !== null" class="text-gray-400 tabular-nums">· {{ daysAgo(ticket[dateKey ?? sortField]) }}d</span>
                </span>
            </li>
        </ul>
        <div v-if="tickets.length && pageCount > 1" class="flex items-center justify-between gap-3 border-t border-gray-200 px-4 py-1.5 text-xs text-gray-500">
            <span class="tabular-nums">{{ (page - 1) * (perPage ?? 0) + 1 }}–{{ Math.min(page * (perPage ?? 0), tickets.length) }} {{ ctrans("of") }} {{ tickets.length }}</span>
            <span class="flex items-center gap-1">
                <button type="button" class="rounded px-2 py-0.5 transition duration-200 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent" :disabled="page === 1" :aria-label="ctrans('Previous page')" @click="page--">‹</button>
                <span class="tabular-nums">{{ page }} / {{ pageCount }}</span>
                <button type="button" class="rounded px-2 py-0.5 transition duration-200 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent" :disabled="page === pageCount" :aria-label="ctrans('Next page')" @click="page++">›</button>
            </span>
        </div>
        <p v-else-if="!tickets.length" class="px-4 py-6 text-center text-sm text-gray-400">{{ empty }}</p>
    </div>
</template>
