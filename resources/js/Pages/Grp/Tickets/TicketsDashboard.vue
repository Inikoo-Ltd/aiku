<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ticketsRoute } from "@/Composables/useTicketsRoute"
import { computed, provide, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketForm from "@/Components/Tickets/TicketForm.vue"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"
import TicketQaQueue from "@/Components/Tickets/TicketQaQueue.vue"
import TicketTabsCard from "@/Components/Tickets/TicketTabsCard.vue"
import TicketRecentUpdates from "@/Components/Tickets/TicketRecentUpdates.vue"
import TicketQuickLook from "@/Components/Tickets/TicketQuickLook.vue"
import Icon from "@/Components/Icon.vue"
import { useLiveTickets } from "@/Composables/useLiveTickets"

const props = defineProps<{
    pageHead: any
    title: string
    can_manage: boolean
    can_qa: boolean
    storeRoute: { name: string; parameters?: Record<string, unknown> }
    priorities: { label: string; value: string }[]
    kinds: { label: string; value: string }[]
    modules: { label: string; value: string }[]
    mine: any[]
    recently_closed: any[]
    stats: { open: number; created_week: number; done_week: number; median_hours: number | null }
    queue?: any[]
    qa_queue?: any[]
    assigned?: any[]
    collaborating?: any[]
    waiting_due?: any[]
    by_status?: { status: string; label: string; icon: any; total: number }[]
}>()

const liveProps = ["can_manage", "can_qa", "mine", "recently_closed", "stats", "queue", "qa_queue", "assigned", "collaborating", "waiting_due", "by_status"]

const quickLook = ref<any | null>(null)

useLiveTickets(liveProps, undefined, computed(() => quickLook.value !== null))

provide("openTicketQuickLook", (ticket: any) => (quickLook.value = ticket))

const closeQuickLook = () => {
    quickLook.value = null
    router.reload({ only: liveProps, preserveScroll: true })
}

const workTabs = computed(() => [
    ...(props.can_manage ? [{ key: "tickets", label: ctrans("Tickets"), count: (props.assigned?.length ?? 0) + (props.collaborating?.length ?? 0) + (props.waiting_due?.length ?? 0) }] : []),
    ...(props.can_qa ? [{ key: "qa", label: ctrans("QA"), count: props.qa_queue?.length ?? 0 }] : []),
])

const unreadUpdates = ref(0)

const hours = (value: number | null) => (value === null ? "-" : value >= 48 ? `${(value / 24).toFixed(1)} ${ctrans("days")}` : `${value} ${ctrans("h")}`)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-300 px-4 py-3 flex flex-wrap gap-x-10 gap-y-3">
            <div>
                <p class="text-2xl font-bold">{{ stats.open }}</p>
                <p class="text-xs text-gray-600">{{ ctrans("Open now") }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-pink-600">{{ stats.created_week }}</p>
                <p class="text-xs text-gray-600">{{ ctrans("Raised this week") }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-green-700">{{ stats.done_week }}</p>
                <p class="text-xs text-gray-600">{{ ctrans("Done this week") }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold">{{ hours(stats.median_hours) }}</p>
                <p class="text-xs text-gray-600">{{ ctrans("Typical time to resolve") }}</p>
            </div>
            <template v-if="by_status">
                <div v-for="row in by_status" :key="row.status">
                    <p class="text-2xl font-bold"><Icon :data="row.icon" class="text-lg" /> {{ row.total }}</p>
                    <p class="text-xs text-gray-600">{{ row.label }}</p>
                </div>
            </template>
        </div>

        <TicketTabsCard v-if="workTabs.length" :tabs="workTabs" storage-key="tickets_dashboard_tab">
            <template #tickets>
                <div class="grid divide-y divide-gray-200 lg:divide-x lg:divide-y-0" :class="collaborating?.length ? 'lg:grid-cols-3' : 'lg:grid-cols-2'">
                    <TicketMiniList flat :title="ctrans('Assigned to me')" :tickets="assigned ?? []" :empty="ctrans('Nothing on your plate')" />
                    <TicketMiniList v-if="collaborating?.length" flat :title="ctrans('Collaborating on')" :tickets="collaborating" :empty="ctrans('Not collaborating on anything')" show-assignee />
                    <TicketMiniList flat :title="ctrans('Waiting, due now')" :tickets="waiting_due ?? []" :empty="ctrans('Nothing due')" date-key="waiting_until" show-assignee />
                </div>
            </template>
            <template #qa>
                <TicketQaQueue flat :title="ctrans('QA queue')" :tickets="qa_queue ?? []" />
            </template>
        </TicketTabsCard>

        <div v-if="can_manage" class="grid gap-4 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <TicketTabsCard
                    :tabs="[
                        { key: 'unassigned', label: ctrans('Up for grabs'), count: queue?.length ?? 0 },
                        { key: 'recent', label: ctrans('Recently Updated'), count: unreadUpdates, highlight: true },
                    ]"
                    storage-key="tickets_dashboard_todo_tab"
                >
                    <template #unassigned>
                        <TicketMiniList flat :title="ctrans('Oldest and most urgent first')" :tickets="queue ?? []" :empty="ctrans('Queue is empty')" date-key="created_at" :per-page="10" />
                    </template>
                    <template #recent>
                        <TicketRecentUpdates :refresh-on="queue" @unread="unreadUpdates = $event" />
                    </template>
                </TicketTabsCard>
                <p class="text-xs text-gray-500 text-right mt-1"><Link :href="ticketsRoute('board')" class="primaryLink">{{ ctrans("Whole board") }}</Link></p>
            </div>
            <TicketTabsCard
                class="lg:col-span-2"
                :tabs="[
                    { key: 'raised', label: ctrans('Raised by me'), count: mine.length },
                    { key: 'closed', label: ctrans('Recently closed'), count: recently_closed.length },
                ]"
                storage-key="tickets_dashboard_mine_tab"
            >
                <template #raised>
                    <TicketMiniList flat :title="ctrans('Still open')" :tickets="mine" :empty="ctrans('You have no open tickets')" show-assignee :per-page="10" />
                </template>
                <template #closed>
                    <TicketMiniList flat :title="ctrans('Closed in the last month')" :tickets="recently_closed" :empty="ctrans('Nothing closed in the last month')" date-key="closed_at" :per-page="10" />
                </template>
            </TicketTabsCard>
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-5">
            <div class="lg:col-span-3 bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden">
                <h3 class="bg-[--app-accent] text-[--app-accent-text] font-semibold px-4 py-2.5">{{ ctrans("New ticket") }}</h3>
                <div class="p-4">
                    <TicketForm :store-route="storeRoute" />
                </div>
            </div>
            <div class="lg:col-span-2 space-y-4">
                <TicketTabsCard
                    :tabs="[
                        { key: 'raised', label: ctrans('My open tickets'), count: mine.length },
                        { key: 'closed', label: ctrans('Recently closed'), count: recently_closed.length },
                        { key: 'recent', label: ctrans('Recently Updated'), count: unreadUpdates, highlight: true },
                    ]"
                    storage-key="tickets_dashboard_reporter_tab"
                >
                    <template #raised>
                        <TicketMiniList flat :title="ctrans('Still open')" :tickets="mine" :empty="ctrans('You have no open tickets')" show-assignee :per-page="10" />
                    </template>
                    <template #closed>
                        <TicketMiniList flat :title="ctrans('Closed in the last month')" :tickets="recently_closed" :empty="ctrans('Nothing closed in the last month')" date-key="closed_at" :per-page="10" />
                    </template>
                    <template #recent>
                        <TicketRecentUpdates :refresh-on="mine" @unread="unreadUpdates = $event" />
                    </template>
                </TicketTabsCard>
                <p class="text-xs text-gray-500 text-right"><Link :href="ticketsRoute('list', { elements: { mine: 'reported' } })" class="primaryLink">{{ ctrans("All my tickets") }}</Link></p>
            </div>
        </div>
    </div>
    <TicketQuickLook v-model:ticket="quickLook" @closed="closeQuickLook" />
</template>
