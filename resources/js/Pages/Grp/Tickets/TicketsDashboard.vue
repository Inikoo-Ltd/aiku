<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, provide, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketForm from "@/Components/Tickets/TicketForm.vue"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"
import TicketQaQueue from "@/Components/Tickets/TicketQaQueue.vue"
import TicketTodoTabs from "@/Components/Tickets/TicketTodoTabs.vue"
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

type WorkTab = "tickets" | "qa"

const workTabs = computed(() => [
    ...(props.can_manage ? [{ key: "tickets" as WorkTab, label: trans("Tickets"), total: (props.assigned?.length ?? 0) + (props.collaborating?.length ?? 0) + (props.waiting_due?.length ?? 0) }] : []),
    ...(props.can_qa ? [{ key: "qa" as WorkTab, label: trans("QA"), total: props.qa_queue?.length ?? 0 }] : []),
])

const readWorkTab = (): WorkTab | null => {
    try {
        return localStorage.getItem("tickets_dashboard_tab") as WorkTab | null
    } catch {
        return null
    }
}

const chosenWorkTab = ref<WorkTab | null>(readWorkTab())

const activeWorkTab = computed(() => workTabs.value.find((tab) => tab.key === chosenWorkTab.value)?.key ?? workTabs.value[0]?.key ?? null)

const chooseWorkTab = (tab: WorkTab) => {
    chosenWorkTab.value = tab
    try {
        localStorage.setItem("tickets_dashboard_tab", tab)
    } catch {
        return
    }
}

const hours = (value: number | null) => (value === null ? "-" : value >= 48 ? `${(value / 24).toFixed(1)} ${trans("days")}` : `${value} ${trans("h")}`)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-300 px-4 py-3 flex flex-wrap gap-x-10 gap-y-3">
            <div>
                <p class="text-2xl font-bold">{{ stats.open }}</p>
                <p class="text-xs text-gray-600">{{ trans("Open now") }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-pink-600">{{ stats.created_week }}</p>
                <p class="text-xs text-gray-600">{{ trans("Raised this week") }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-green-700">{{ stats.done_week }}</p>
                <p class="text-xs text-gray-600">{{ trans("Done this week") }}</p>
            </div>
            <div>
                <p class="text-2xl font-bold">{{ hours(stats.median_hours) }}</p>
                <p class="text-xs text-gray-600">{{ trans("Typical time to resolve") }}</p>
            </div>
            <template v-if="by_status">
                <div v-for="row in by_status" :key="row.status">
                    <p class="text-2xl font-bold"><Icon :data="row.icon" class="text-lg" /> {{ row.total }}</p>
                    <p class="text-xs text-gray-600">{{ row.label }}</p>
                </div>
            </template>
        </div>

        <div v-if="workTabs.length" class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden">
            <div class="flex gap-1 border-b border-gray-200 px-2">
                <button
                    v-for="tab in workTabs"
                    :key="tab.key"
                    type="button"
                    class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold transition duration-200"
                    :class="activeWorkTab === tab.key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                    @click="chooseWorkTab(tab.key)"
                >
                    {{ tab.label }}
                    <span class="rounded-full px-2 py-0.5 text-xs font-normal tabular-nums" :class="activeWorkTab === tab.key ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600'">{{ tab.total }}</span>
                </button>
            </div>
            <div v-if="activeWorkTab === 'tickets'" class="grid divide-y divide-gray-200 lg:divide-x lg:divide-y-0" :class="collaborating?.length ? 'lg:grid-cols-3' : 'lg:grid-cols-2'">
                <TicketMiniList flat :title="trans('Assigned to me')" :tickets="assigned ?? []" :empty="trans('Nothing on your plate')" />
                <TicketMiniList v-if="collaborating?.length" flat :title="trans('Collaborating on')" :tickets="collaborating" :empty="trans('Not collaborating on anything')" show-assignee />
                <TicketMiniList flat :title="trans('Waiting, due now')" :tickets="waiting_due ?? []" :empty="trans('Nothing due')" date-key="waiting_until" show-assignee />
            </div>
            <TicketQaQueue v-else-if="activeWorkTab === 'qa'" flat :title="trans('QA queue')" :tickets="qa_queue ?? []" />
        </div>

        <div v-if="can_manage" class="grid gap-4 lg:grid-cols-2">
            <div class="lg:col-span-2">
                <TicketTodoTabs :queue="queue ?? []" />
                <p class="text-xs text-gray-500 text-right mt-1"><Link :href="route('grp.tickets.board')" class="primaryLink">{{ trans("Whole board") }}</Link></p>
            </div>
            <TicketMiniList :title="trans('Raised by me')" :tickets="mine" :empty="trans('You have no open tickets')" show-assignee />
            <TicketMiniList :title="trans('Recently closed, raised by me')" :tickets="recently_closed" :empty="trans('Nothing closed in the last month')" date-key="closed_at" />
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-5">
            <div class="lg:col-span-3 bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden">
                <h3 class="bg-indigo-600 text-white font-semibold px-4 py-2.5">{{ trans("New ticket") }}</h3>
                <div class="p-4">
                    <TicketForm :store-route="storeRoute" />
                </div>
            </div>
            <div class="lg:col-span-2 space-y-4">
                <TicketMiniList :title="trans('My open tickets')" :tickets="mine" :empty="trans('You have no open tickets')" show-assignee />
                <TicketMiniList :title="trans('Recently closed')" :tickets="recently_closed" :empty="trans('Nothing closed in the last month')" date-key="closed_at" />
                <p class="text-xs text-gray-500 text-right"><Link :href="route('grp.tickets.list', { elements: { mine: 'reported' } })" class="primaryLink">{{ trans("All my tickets") }}</Link></p>
            </div>
        </div>
    </div>
    <TicketQuickLook v-model:ticket="quickLook" @closed="closeQuickLook" />
</template>
