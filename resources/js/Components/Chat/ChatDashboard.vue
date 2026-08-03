<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faStoreAlt,
    faUserHeadset,
    faComments,
    faHourglassHalf,
    faPlayCircle,
    faCheckCircle,
    faDoorClosed,
    faEnvelope,
    faEnvelopeOpenText,
} from "@fal"

library.add(
    faStoreAlt,
    faUserHeadset,
    faComments,
    faHourglassHalf,
    faPlayCircle,
    faCheckCircle,
    faDoorClosed,
    faEnvelope,
    faEnvelopeOpenText,
)

const props = defineProps<{
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

const locale = useLocaleStore()

const tabsBox = computed(() => [
    {
        label: trans("Shops & Agents"),
        tabs: [
            {
                tab_slug: "chat_enabled_shops",
                label: trans("Chat Enabled Shops"),
                value: props.stats.chatEnabledShops,
                type: "number",
                icon: "fal fa-store-alt",
                icon_data: {
                    icon: "fal fa-store-alt",
                    tooltip: trans("Chat Enabled Shops"),
                },
            },
            {
                tab_slug: "chat_agents",
                label: trans("Chat Agents"),
                value: props.stats.chatAgents,
                type: "number",
                icon: "fal fa-user-headset",
                icon_data: {
                    icon: "fal fa-user-headset",
                    tooltip: trans("Chat Agents"),
                },
            },
        ],
    },
    {
        label: trans("Sessions & Waiting"),
        tabs: [
            {
                tab_slug: "chat_sessions_total",
                label: trans("Chat Sessions"),
                value: props.stats.chatSessionsTotal,
                type: "number",
                icon: "fal fa-comments",
                icon_data: {
                    icon: "fal fa-comments",
                    tooltip: trans("Total chat sessions"),
                },
            },
            {
                tab_slug: "chat_sessions_waiting",
                label: trans("Waiting Sessions"),
                value: props.stats.chatSessionsWaiting,
                type: "number",
                icon: "fal fa-hourglass-half",
                icon_data: {
                    icon: "fal fa-hourglass-half",
                    tooltip: trans("Waiting sessions"),
                },
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
                icon_data: {
                    icon: "fal fa-check-circle",
                    tooltip: trans("Active sessions"),
                },
            },
            {
                tab_slug: "chat_sessions_closed",
                label: trans("Closed Sessions"),
                value: props.stats.chatSessionsClosed,
                type: "number",
                icon: "fal fa-door-closed",
                icon_data: {
                    icon: "fal fa-door-closed",
                    tooltip: trans("Closed sessions"),
                },
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
                icon_data: {
                    icon: "fal fa-envelope",
                    tooltip: trans("Total messages"),
                },
            },
            {
                tab_slug: "chat_messages_unread",
                label: trans("Unread Messages"),
                value: props.stats.chatMessagesUnread,
                type: "number",
                icon: "fal fa-envelope-open-text",
                icon_data: {
                    icon: "fal fa-envelope-open-text",
                    tooltip: trans("Unread messages"),
                },
            },
        ],
    },
])
</script>

<template>
    <div class="flex flex-col gap-6 pb-8 pt-8">
        <TabsBoxDisplay :tabs_box="tabsBox" />

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                <p class="text-xs text-gray-500">
                    {{ trans("Chat status per shop in this organisation") }}
                </p>
            </div>
            <div v-if="chatEnabledShops.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                {{ trans("No shops found for this organisation.") }}
            </div>
            <DashboardTable
                v-else-if="table?.tableData"
                class="border-t border-gray-200"
                :idTable="table.idTable"
                :tableData="table.tableData"
                :intervals="table.intervals"
                :settings="table.settings"
                :currentTab="table.tableData.current_tab"
                :showTabs="false"
            />
            <div v-else class="px-4 py-8 text-center text-sm text-gray-500">
                {{ trans("Table data is not available.") }}
            </div>
        </div>
    </div>
</template>
