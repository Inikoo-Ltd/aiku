<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import { Intervals, Settings } from "@/types/Components/Dashboard"

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

const statCards = computed(() => [
    { label: trans("Chat Enabled Shops"), value: props.stats.chatEnabledShops },
    { label: trans("Chat Agents"), value: props.stats.chatAgents },
    { label: trans("Chat Sessions"), value: props.stats.chatSessionsTotal },
    { label: trans("Waiting Sessions"), value: props.stats.chatSessionsWaiting },
    { label: trans("Active Sessions"), value: props.stats.chatSessionsActive },
    { label: trans("Closed Sessions"), value: props.stats.chatSessionsClosed },
    { label: trans("Messages"), value: props.stats.chatMessagesTotal },
    { label: trans("Unread Messages"), value: props.stats.chatMessagesUnread },
])
</script>

<template>
    <div class="flex flex-col gap-6 px-4 pb-8 pt-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div v-for="stat in statCards" :key="stat.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-medium text-gray-500">
                    {{ stat.label }}
                </div>
                <div class="mt-2 text-2xl font-semibold text-gray-900 tabular-nums">
                    {{ locale.number(stat.value) }}
                </div>
            </div>
        </div>

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
