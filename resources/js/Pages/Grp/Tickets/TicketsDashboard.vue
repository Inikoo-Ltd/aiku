<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ticketsRoute } from "@/Composables/useTicketsRoute"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCircle, faShieldCheck, faShield, faVial } from "@fal"
import { computed, provide, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketForm from "@/Components/Tickets/TicketForm.vue"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"
import TicketQaQueue from "@/Components/Tickets/TicketQaQueue.vue"
import TicketQaChecking from "@/Components/Tickets/TicketQaChecking.vue"
import TicketTabsCard from "@/Components/Tickets/TicketTabsCard.vue"
import TicketRecentUpdates from "@/Components/Tickets/TicketRecentUpdates.vue"
import TicketQuickLook from "@/Components/Tickets/TicketQuickLook.vue"
import Icon from "@/Components/Icon.vue"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import { useLiveTicketRows } from "@/Composables/useLiveTicketRows"

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
    qa_checking?: any[]
    assigned?: any[]
    collaborating?: any[]
    waiting_due?: any[]
    by_status?: { status: string; label: string; icon: any; total: number }[]
    qa_stats?: { not_checked: number; passed: number; failed: number; requested: number }
}>()

library.add(faCircle, faShieldCheck, faShield, faVial)

const liveProps = ["can_manage", "can_qa", "mine", "recently_closed", "stats", "queue", "qa_queue", "qa_checking", "assigned", "collaborating", "waiting_due", "by_status", "qa_stats"]

const openStatuses = "open,assigned,in_progress,waiting,answered,pending_deploy"

const ticketListLink = (elements: Record<string, string> = {}, parameters: Record<string, unknown> = {}) =>
    ticketsRoute("list", { created: "all", ...parameters, elements: { mine: "", ...elements } })

const qaListLink = (elements: Record<string, string>, parameters: Record<string, unknown> = {}) =>
    ticketsRoute("qa_list", { created: "all", ...parameters, elements })

const oneWeekAgo = () => new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString()

const qaDetails = computed(() => props.qa_stats ? [
    { key: "not_checked", label: ctrans("Not Checked"), total: props.qa_stats.not_checked, icon: "fal fa-circle", class: "text-gray-400", href: qaListLink({ qa_status: "none" }) },
    { key: "passed", label: ctrans("Check Passed"), total: props.qa_stats.passed, icon: "fal fa-shield-check", class: "text-green-600", href: qaListLink({ qa_status: "passed" }) },
    { key: "failed", label: ctrans("Check Failed"), total: props.qa_stats.failed, icon: "fal fa-shield", class: "text-red-500", href: qaListLink({ qa_status: "failed" }) },
    { key: "requested", label: ctrans("Need Check Urgent"), total: props.qa_stats.requested, icon: "fal fa-vial", class: "text-amber-500", href: qaListLink({ qa_status: "" }, { filter: { qa_requested: 1 } }) },
] : [])

const quickLook = ref<any | null>(null)

const { isShown: isTicketShown } = useLiveTicketRows(["mine", "recently_closed", "queue", "qa_queue", "qa_checking", "assigned", "collaborating", "waiting_due"])
useLiveTickets(liveProps, undefined, computed(() => quickLook.value !== null), undefined, (event) => isTicketShown(event.id))

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

const ticketTiles = computed(() => [
    { key: "open", label: ctrans("Open now"), value: props.stats.open, class: "text-gray-900", href: ticketListLink({ status: openStatuses }) },
    { key: "raised", label: ctrans("Raised this week"), value: props.stats.created_week, class: "text-pink-600", href: ticketListLink({}, { created: "1w" }) },
    { key: "done", label: ctrans("Done this week"), value: props.stats.done_week, class: "text-green-700", href: ticketListLink({}, { filter: { resolved_since: oneWeekAgo() } }) },
    { key: "resolve", label: ctrans("Typical time to resolve"), value: hours(props.stats.median_hours), class: "text-gray-900", href: ticketsRoute("reports") },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="grid grid-cols-1 gap-4" :class="qaDetails.length && 'xl:grid-cols-3'">
            <section class="rounded-lg border border-gray-300 bg-white p-4 shadow-sm xl:col-span-2">
                <h2 class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Ticket Details") }}</h2>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <Link v-for="tile in ticketTiles" :key="tile.key" :href="tile.href" class="group rounded-lg border border-gray-200 bg-gray-50/60 px-3 py-2.5 transition duration-200 hover:border-gray-300 hover:bg-white hover:shadow-sm">
                        <p class="text-xs text-gray-500">{{ tile.label }}</p>
                        <p class="mt-0.5 text-2xl font-bold tabular-nums" :class="tile.class">{{ tile.value }}</p>
                    </Link>
                </div>
                <div v-if="by_status?.length" class="mt-3 border-t border-gray-100 pt-3">
                    <p class="mb-2 text-xs text-gray-500">{{ ctrans("Open by status") }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <Link
                            v-for="row in by_status"
                            :key="row.status"
                            :href="ticketListLink({ status: row.status })"
                            class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 py-1 pl-2 pr-1 text-xs text-gray-700 transition duration-200 hover:border-gray-300 hover:bg-gray-50"
                            :class="!row.total && 'opacity-50'">
                            <Icon :data="row.icon" />
                            <span>{{ row.label }}</span>
                            <span class="min-w-[1.5rem] rounded-full bg-gray-100 px-1.5 text-center font-semibold tabular-nums text-gray-800">{{ row.total }}</span>
                        </Link>
                    </div>
                </div>
            </section>

            <section v-if="qaDetails.length" class="rounded-lg border border-gray-300 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("QA Details") }}</h2>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-2">
                    <Link
                        v-for="row in qaDetails"
                        :key="row.key"
                        :href="row.href"
                        class="rounded-lg border px-3 py-2.5 transition duration-200 hover:shadow-sm"
                        :class="row.key === 'requested' && row.total > 0 ? 'border-amber-300 bg-amber-50 hover:bg-amber-100/60' : 'border-gray-200 bg-gray-50/60 hover:border-gray-300 hover:bg-white'">
                        <p class="flex items-center gap-1.5 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="row.icon" :class="row.class" fixed-width aria-hidden="true" />
                            {{ row.label }}
                        </p>
                        <p class="mt-0.5 text-2xl font-bold tabular-nums">{{ row.total }}</p>
                    </Link>
                </div>
            </section>
        </div>

        <TicketTabsCard v-if="workTabs.length" :tabs="workTabs" storage-key="tickets_dashboard_tab">
            <template #tickets>
                <div class="grid divide-y divide-gray-200 lg:divide-x lg:divide-y-0 [&>*]:min-w-0" :class="collaborating?.length ? 'lg:grid-cols-3' : 'lg:grid-cols-2'">
                    <TicketMiniList flat :title="ctrans('Assigned to me')" :tickets="assigned ?? []" :empty="ctrans('Nothing on your plate')" />
                    <TicketMiniList v-if="collaborating?.length" flat :title="ctrans('Collaborating on')" :tickets="collaborating" :empty="ctrans('Not collaborating on anything')" show-assignee />
                    <TicketMiniList flat :title="ctrans('Waiting, due now')" :tickets="waiting_due ?? []" :empty="ctrans('Nothing due')" date-key="waiting_until" show-assignee />
                </div>
            </template>
            <template #qa>
                <div class="grid divide-y divide-gray-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0 [&>*]:min-w-0">
                    <TicketQaQueue flat :title="ctrans('QA queue')" :tickets="qa_queue ?? []" />
                    <TicketQaChecking flat :title="ctrans('Currently checking')" :tickets="qa_checking ?? []" />
                </div>
            </template>
        </TicketTabsCard>

        <div v-if="can_manage" class="grid gap-4 lg:grid-cols-2 [&>*]:min-w-0">
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

        <div v-else class="grid gap-4 lg:grid-cols-5 [&>*]:min-w-0">
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
