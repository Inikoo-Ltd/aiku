<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import ChatDashboard from "@/Components/Chat/ChatDashboard.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCommentAlt, faPencil } from "@fortawesome/free-solid-svg-icons"
library.add(faCommentAlt, faPencil)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
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
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <ChatDashboard :stats="stats" :chat-enabled-shops="chatEnabledShops" :table="table" />
</template>
