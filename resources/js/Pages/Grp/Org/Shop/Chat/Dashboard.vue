<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import TableAgents from "@/Pages/Grp/Agent/Agents.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faUserHeadset,
    faComments,
    faHourglassHalf,
    faCheckCircle,
    faDoorClosed,
    faEnvelope,
    faEnvelopeOpenText,
} from "@fal"

library.add(
    faUserHeadset,
    faComments,
    faHourglassHalf,
    faCheckCircle,
    faDoorClosed,
    faEnvelope,
    faEnvelopeOpenText,
)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: Record<string, { name: string; icon: string; label: string }>
    }
    stats: {
        chatEnabled: boolean
        chatAgents: number
        chatSessionsTotal: number
        chatSessionsWaiting: number
        chatSessionsActive: number
        chatSessionsClosed: number
        chatMessagesTotal: number
        chatMessagesUnread: number
    }
    agents: any
}>()

const currentTab = ref(props.tabs.current ?? "dashboard")
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabsBox = computed(() => [
    {
        label: trans("Sessions & Waiting"),
        tabs: [
            {
                tab_slug: "chat_sessions_total",
                label: trans("Chat Sessions"),
                value: props.stats.chatSessionsTotal,
                type: "number",
                icon: "fal fa-comments",
                icon_data: { icon: "fal fa-comments", tooltip: trans("Total chat sessions") },
            },
            {
                tab_slug: "chat_sessions_waiting",
                label: trans("Waiting Sessions"),
                value: props.stats.chatSessionsWaiting,
                type: "number",
                icon: "fal fa-hourglass-half",
                icon_data: { icon: "fal fa-hourglass-half", tooltip: trans("Waiting sessions") },
            },
        ],
    },
    {
        label: trans("Active & Closed Sessions"),
        tabs: [
            {
                tab_slug: "chat_sessions_active",
                label: trans("Active Sessions"),
                value: props.stats.chatSessionsActive,
                type: "number",
                icon: "fal fa-check-circle",
                icon_data: { icon: "fal fa-check-circle", tooltip: trans("Active sessions") },
            },
            {
                tab_slug: "chat_sessions_closed",
                label: trans("Closed Sessions"),
                value: props.stats.chatSessionsClosed,
                type: "number",
                icon: "fal fa-door-closed",
                icon_data: { icon: "fal fa-door-closed", tooltip: trans("Closed sessions") },
            },
        ],
    },
    {
        label: trans("Messages & Unread"),
        tabs: [
            {
                tab_slug: "chat_messages_total",
                label: trans("Messages"),
                value: props.stats.chatMessagesTotal,
                type: "number",
                icon: "fal fa-envelope",
                icon_data: { icon: "fal fa-envelope", tooltip: trans("Total messages") },
            },
            {
                tab_slug: "chat_messages_unread",
                label: trans("Unread Messages"),
                value: props.stats.chatMessagesUnread,
                type: "number",
                icon: "fal fa-envelope-open-text",
                icon_data: { icon: "fal fa-envelope-open-text", tooltip: trans("Unread messages") },
            },
        ],
    },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div v-if="currentTab === 'dashboard'" class="px-4 pb-8 pt-8">
        <TabsBoxDisplay :tabs_box="tabsBox" />
    </div>

    <div v-else-if="currentTab === 'agents'">
        <TableAgents :title="title" :pageHeading="({} as any)" :data="agents" />
    </div>
</template>
