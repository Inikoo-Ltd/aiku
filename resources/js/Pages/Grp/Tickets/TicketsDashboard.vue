<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketForm from "@/Components/Tickets/TicketForm.vue"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"
import Icon from "@/Components/Icon.vue"

defineProps<{
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
    waiting_due?: any[]
    by_status?: { status: string; label: string; icon: any; total: number }[]
}>()

const hours = (value: number | null) => (value === null ? "-" : value >= 48 ? `${(value / 24).toFixed(1)} ${trans("days")}` : `${value} ${trans("h")}`)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="flex flex-wrap gap-x-10 gap-y-4">
            <div>
                <p class="text-4xl font-bold">{{ stats.open }}</p>
                <p class="text-sm text-gray-600">{{ trans("Open now") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold text-pink-600">{{ stats.created_week }}</p>
                <p class="text-sm text-gray-600">{{ trans("Raised this week") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold text-green-700">{{ stats.done_week }}</p>
                <p class="text-sm text-gray-600">{{ trans("Done this week") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold">{{ hours(stats.median_hours) }}</p>
                <p class="text-sm text-gray-600">{{ trans("Typical time to resolve") }}</p>
            </div>
            <template v-if="by_status">
                <div v-for="row in by_status" :key="row.status">
                    <p class="text-4xl font-bold"><Icon :data="row.icon" class="text-2xl" /> {{ row.total }}</p>
                    <p class="text-sm text-gray-600">{{ row.label }}</p>
                </div>
            </template>
        </div>

        <TicketMiniList v-if="can_qa && !can_manage" :title="trans('Waiting for a QA check, oldest first')" :tickets="qa_queue ?? []" :empty="trans('Nothing to check')" date-key="qa_requested_at" show-assignee />

        <div v-if="can_manage" class="grid gap-4 lg:grid-cols-2">
            <TicketMiniList :title="trans('Assigned to me')" :tickets="assigned ?? []" :empty="trans('Nothing on your plate')" />
            <TicketMiniList :title="trans('Waiting for a QA check')" :tickets="qa_queue ?? []" :empty="trans('Nothing to check')" date-key="qa_requested_at" show-assignee />
            <TicketMiniList :title="trans('Waiting, due now')" :tickets="waiting_due ?? []" :empty="trans('Nothing due')" date-key="waiting_until" show-assignee />
            <div class="lg:col-span-2">
                <TicketMiniList :title="trans('Todo, unassigned, oldest and most urgent first')" :tickets="queue ?? []" :empty="trans('Queue is empty')" date-key="created_at" />
                <p class="text-xs text-gray-500 text-right mt-1"><Link :href="route('grp.tickets.board')" class="primaryLink">{{ trans("Whole board") }}</Link></p>
            </div>
            <TicketMiniList :title="trans('Raised by me')" :tickets="mine" :empty="trans('You have no open tickets')" show-assignee />
            <TicketMiniList :title="trans('Recently closed, raised by me')" :tickets="recently_closed" :empty="trans('Nothing closed in the last month')" date-key="closed_at" />
        </div>

        <div v-else class="grid gap-4 lg:grid-cols-5">
            <div class="lg:col-span-3 bg-white rounded-lg shadow-sm border border-gray-300 p-4">
                <h3 class="font-semibold mb-3">{{ trans("New ticket") }}</h3>
                <TicketForm :store-route="storeRoute" :priorities="priorities" :kinds="kinds" :modules="modules" />
            </div>
            <div class="lg:col-span-2 space-y-4">
                <TicketMiniList :title="trans('My open tickets')" :tickets="mine" :empty="trans('You have no open tickets')" show-assignee />
                <TicketMiniList :title="trans('Recently closed')" :tickets="recently_closed" :empty="trans('Nothing closed in the last month')" date-key="closed_at" />
                <p class="text-xs text-gray-500 text-right"><Link :href="route('grp.tickets.list', { elements: { mine: 'reported' } })" class="primaryLink">{{ trans("All my tickets") }}</Link></p>
            </div>
        </div>
    </div>
</template>
