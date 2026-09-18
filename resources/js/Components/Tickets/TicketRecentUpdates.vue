<!--
 Author Louis Perez
 Created on 18-09-2026-13h-11m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { onMounted, ref, watch } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faAt, faCommentLines, faExchange, faVial, faPencil, faUserPlus, faPlusCircle, faCheckDouble, faQuestionCircle, faBell } from "@fal"

library.add(faAt, faCommentLines, faExchange, faVial, faPencil, faUserPlus, faPlusCircle, faCheckDouble, faQuestionCircle, faBell)

const props = defineProps<{
    refreshOn?: unknown
}>()

const emit = defineEmits<{
    (e: "unread", count: number): void
}>()

type RecentScope = "unread" | "mentions" | "all"

const scopes: { key: RecentScope; label: string }[] = [
    { key: "unread", label: trans("Unread") },
    { key: "mentions", label: trans("Mentions") },
    { key: "all", label: trans("All") },
]

const readStoredScope = (): RecentScope => {
    try {
        const stored = localStorage.getItem("tickets_dashboard_recent_scope") as RecentScope | null
        return stored && scopes.some((scope) => scope.key === stored) ? stored : "unread"
    } catch {
        return "unread"
    }
}

const recentScope = ref<RecentScope>(readStoredScope())
const recentTickets = ref<any[] | null>(null)
const isLoading = ref(false)

const fetchScope = async (scope: RecentScope) => (await axios.get(route("grp.json.ticket.recently_updated", { scope }))).data as any[]

const loadRecent = async () => {
    const requestedScope = recentScope.value
    isLoading.value = true
    try {
        const tickets = await fetchScope(requestedScope)
        if (requestedScope !== recentScope.value) return
        recentTickets.value = tickets
        if (requestedScope === "unread") emit("unread", tickets.length)
    } finally {
        if (requestedScope === recentScope.value) isLoading.value = false
    }
}

const loadUnreadCount = async () => {
    try {
        emit("unread", (await fetchScope("unread")).length)
    } catch {
        return
    }
}

const refresh = () => {
    loadRecent()
    if (recentScope.value !== "unread") loadUnreadCount()
}

const chooseScope = (scope: RecentScope) => {
    recentScope.value = scope
    try {
        localStorage.setItem("tickets_dashboard_recent_scope", scope)
    } catch {
        return
    }
}

watch(recentScope, loadRecent)
watch(() => props.refreshOn, refresh)
onMounted(refresh)
</script>

<template>
    <div v-if="recentTickets === null" class="space-y-2 p-4">
        <div v-for="placeholder in 4" :key="placeholder" class="h-6 animate-pulse rounded bg-gray-100" />
    </div>
    <TicketMiniList v-else flat :title="trans('Latest first')" :tickets="recentTickets" :empty="recentScope === 'unread' ? trans('All caught up') : trans('Nothing here yet')" date-key="notified_at" sort-key="notified_at" :per-page="10" :class="isLoading && 'opacity-60 transition-opacity duration-200'">
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
            <span v-tooltip="ticket.notification_body || ticket.notification_title" class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs whitespace-nowrap" :class="ticket.reason === 'mention' ? 'bg-[--app-accent-soft] text-[--app-accent-strong]' : 'bg-gray-100 text-gray-600'">
                <FontAwesomeIcon :icon="ticket.reason_icon" fixed-width />
                {{ ticket.reason_label }}
            </span>
        </template>
    </TicketMiniList>
</template>
