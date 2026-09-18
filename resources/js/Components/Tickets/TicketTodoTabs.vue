<!--
 Author Louis Perez
 Created on 18-09-2026-10h-36m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAt, faCommentLines, faExchange, faVial, faPencil, faUserPlus, faPlusCircle, faCheckDouble, faQuestionCircle, faBell } from "@fal"

library.add(faAt, faCommentLines, faExchange, faVial, faPencil, faUserPlus, faPlusCircle, faCheckDouble, faQuestionCircle, faBell)

const props = defineProps<{
    queue: any[]
}>()

type TodoTab = "unassigned" | "recent"
type RecentScope = "unread" | "mentions" | "all"

const tabs: { key: TodoTab; label: string }[] = [
    { key: "unassigned", label: trans("Up for grabs") },
    { key: "recent", label: trans("Recently Updated") },
]

const scopes: { key: RecentScope; label: string }[] = [
    { key: "unread", label: trans("Unread") },
    { key: "mentions", label: trans("Mentions") },
    { key: "all", label: trans("All") },
]

const readStored = <T extends string>(key: string, allowed: T[], fallback: T): T => {
    try {
        const stored = localStorage.getItem(key) as T | null
        return stored && allowed.includes(stored) ? stored : fallback
    } catch {
        return fallback
    }
}

const store = (key: string, value: string) => {
    try {
        localStorage.setItem(key, value)
    } catch {
        return
    }
}

const activeTab = ref<TodoTab>(readStored("tickets_dashboard_todo_tab", ["unassigned", "recent"], "unassigned"))
const recentScope = ref<RecentScope>(readStored("tickets_dashboard_recent_scope", ["unread", "mentions", "all"], "unread"))
const recentTickets = ref<any[] | null>(null)
const isLoadingRecent = ref(false)
const unreadCount = ref(0)

const loadUnreadCount = async () => {
    try {
        const { data } = await axios.get(route("grp.json.ticket.recently_updated", { scope: "unread" }))
        unreadCount.value = data.length
    } catch {
        return
    }
}

const loadRecent = async () => {
    if (activeTab.value !== "recent") return
    const requestedScope = recentScope.value
    isLoadingRecent.value = true
    try {
        const { data } = await axios.get(route("grp.json.ticket.recently_updated", { scope: requestedScope }))
        if (requestedScope === recentScope.value) recentTickets.value = data
        if (requestedScope === "unread") unreadCount.value = data.length
    } finally {
        if (requestedScope === recentScope.value) isLoadingRecent.value = false
    }
}

const chooseTab = (tab: TodoTab) => {
    activeTab.value = tab
    store("tickets_dashboard_todo_tab", tab)
}

const chooseScope = (scope: RecentScope) => {
    recentScope.value = scope
    store("tickets_dashboard_recent_scope", scope)
}

watch([activeTab, recentScope], loadRecent, { immediate: true })
watch(() => props.queue, () => {
    loadRecent()
    if (activeTab.value !== "recent" || recentScope.value !== "unread") loadUnreadCount()
})

if (activeTab.value !== "recent" || recentScope.value !== "unread") loadUnreadCount()
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden">
        <div class="flex gap-1 border-b border-gray-200 px-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold transition duration-200"
                :class="activeTab === tab.key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                @click="chooseTab(tab.key)"
            >
                {{ tab.label }}
                <span v-if="tab.key === 'recent' && unreadCount" class="rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-normal text-white tabular-nums">{{ unreadCount }}</span>
                <span v-if="tab.key === 'unassigned'" class="rounded-full px-2 py-0.5 text-xs font-normal tabular-nums" :class="activeTab === tab.key ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600'">{{ queue.length }}</span>
            </button>
        </div>
        <TicketMiniList v-if="activeTab === 'unassigned'" flat :title="trans('Unassigned, oldest and most urgent first')" :tickets="queue" :empty="trans('Queue is empty')" date-key="created_at" :per-page="10" />
        <div v-else-if="recentTickets === null" class="space-y-2 p-4">
            <div v-for="placeholder in 4" :key="placeholder" class="h-6 animate-pulse rounded bg-gray-100" />
        </div>
        <TicketMiniList v-else flat :title="trans('Latest first')" :tickets="recentTickets" :empty="recentScope === 'unread' ? trans('All caught up') : trans('Nothing here yet')" date-key="notified_at" sort-key="notified_at" :per-page="10" :class="isLoadingRecent && 'opacity-60 transition-opacity duration-200'">
            <template #filters>
                <span class="inline-flex rounded-full bg-gray-100 p-0.5 text-xs">
                    <button
                        v-for="option in scopes"
                        :key="option.key"
                        type="button"
                        class="rounded-full px-2 py-0.5 transition duration-200"
                        :class="recentScope === option.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        @click="chooseScope(option.key)"
                    >
                        {{ option.label }}
                    </button>
                </span>
            </template>
            <template #person="{ ticket }">
                <span v-tooltip="ticket.notification_body || ticket.notification_title" class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs whitespace-nowrap" :class="ticket.reason === 'mention' ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600'">
                    <FontAwesomeIcon :icon="ticket.reason_icon" fixed-width />
                    {{ ticket.reason_label }}
                </span>
            </template>
        </TicketMiniList>
    </div>
</template>
