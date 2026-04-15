<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import ChatDashboard from "@/Components/Chat/ChatDashboard.vue"
import TableAgents from "@/Pages/Grp/Agent/Agents.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: Record<string, { name: string; icon: string; label: string }>
    }
    stats: {
        chatEnabledShops: number
        chatAgents: number
        chatSessionsTotal: number
        chatSessionsWaiting: number
        chatSessionsActive: number
        chatSessionsClosed: number
        chatMessagesTotal: number
        chatMessagesUnread: number
    }
    chatEnabledShops: Array<{
        id: number
        slug: string
        code: string | null
        name: string
        chatActive: boolean
        chatAgentsCount: number
        sessionsTotal: number
        sessionsActive: number
        sessionsWaiting: number
        sessionsClosed: number
    }>
    table: {
        idTable: string
        tableData: {
            charts: []
            current_tab: string
            id: string
            tables: Record<string, unknown>
            tabs: Record<string, { icon: string | null; title: string }>
        }
        intervals: Intervals
        settings: Settings
    }
    agents: any
}>()

const currentTab = ref(props.tabs.current ?? "dashboard")
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div v-if="currentTab === 'dashboard'">
        <ChatDashboard :stats="stats" :chat-enabled-shops="chatEnabledShops" :table="table" />
    </div>

    <div v-else-if="currentTab === 'agents'">
        <TableAgents :title="title" :pageHeading="({}  as any)" :data="agents" />
    </div>
</template>
