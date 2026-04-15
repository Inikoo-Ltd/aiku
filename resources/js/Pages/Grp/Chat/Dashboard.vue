<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import { trans } from "laravel-vue-i18n"
import { computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faStoreAlt,
    faUserHeadset,
    faComments,
    faHourglassHalf,
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
    faCheckCircle,
    faDoorClosed,
    faEnvelope,
    faEnvelopeOpenText,
)

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
                icon_data: { icon: "fal fa-store-alt", tooltip: trans("Chat Enabled Shops") },
            },
            {
                tab_slug: "chat_agents",
                label: trans("Chat Agents"),
                value: props.stats.chatAgents,
                type: "number",
                icon: "fal fa-user-headset",
                icon_data: { icon: "fal fa-user-headset", tooltip: trans("Chat Agents") },
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

    <div class="flex flex-col gap-6 px-4 pb-8 pt-8">
        <TabsBoxDisplay :tabs_box="tabsBox" />

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                <p class="text-xs text-gray-500">
                    {{ trans("Chat status per organisation") }}
                </p>
            </div>
            <DashboardTable
                v-if="table?.tableData"
                class="border-t border-gray-200"
                :idTable="table.idTable"
                :tableData="table.tableData"
                :intervals="table.intervals"
                :settings="table.settings"
                :currentTab="table.tableData.current_tab"
                :showTabs="false"
            />
            <div v-else class="px-4 py-8 text-center text-sm text-gray-500">
                {{ trans("No data available.") }}
            </div>
        </div>
    </div>
</template>
