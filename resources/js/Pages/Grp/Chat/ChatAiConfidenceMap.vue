<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 29 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"

const props = defineProps<{
    title: string
    pageHead: object
    days: number
    dayOptions: number[]
    data: object
}>()

const days = ref(props.days)
watch(days, (value) => router.get(route("grp.chat.ai.confidence"), { days: value }, { preserveScroll: true }))

const share = (part: number, whole: number) => (whole ? Math.round((100 * part) / whole) + "%" : "–")

const RISK_CLASS: Record<string, string> = {
    information: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    account_facts: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    promise: "bg-amber-50 text-amber-700 ring-amber-200",
    order_change: "bg-red-50 text-red-700 ring-red-200",
    money: "bg-red-50 text-red-700 ring-red-200",
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 pt-3 text-sm text-gray-600">
        <SegmentedToggle v-model="days" :options="dayOptions.map((option) => ({ label: ctrans(':count days', { count: option }), value: option }))" :aria-label="ctrans('Period')" />
        <span>{{ ctrans("One row per shop, what the customer wanted and what the reply does. A row could earn being answered without a person only on its lower bound. Nothing is sent without a person yet.") }}</span>
    </div>

    <Table :resource="data" class="mt-3">
        <template #cell(intent)="{ item }">
            <span class="whitespace-nowrap text-gray-900">{{ item.intent_label }}</span>
            <span v-if="item.topic_label" class="ml-1.5 whitespace-nowrap text-xs text-gray-400">{{ item.topic_label }}</span>
            <span v-if="item.intent_disagree" class="ml-1.5 whitespace-nowrap text-xs text-amber-700" v-tooltip="ctrans('Jev read the customer differently on :count of these', { count: item.intent_disagree })">
                {{ ctrans(":count disputed", { count: item.intent_disagree }) }}
            </span>
        </template>

        <template #cell(risk)="{ item }">
            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="RISK_CLASS[item.risk] ?? 'bg-gray-50 text-gray-600 ring-gray-200'">
                {{ item.risk_label }}
            </span>
        </template>

        <template #cell(alone_clean)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ item.alone_clean }} · {{ share(item.alone_clean, item.decided) }}</span>
        </template>

        <template #cell(changes)="{ item }">
            <span class="whitespace-nowrap tabular-nums text-gray-600">{{ item.gaps_filled }} · {{ item.changed }} · {{ item.not_used }}</span>
        </template>

        <template #cell(close)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ share(item.close, item.decided) }}</span>
        </template>

        <template #cell(would_send)="{ item }">
            <span class="whitespace-nowrap tabular-nums">
                {{ item.would_send }}<span v-if="item.would_send" class="text-gray-500"> · {{ ctrans(":share right", { share: share(item.would_send_right, item.would_send) }) }}</span>
            </span>
        </template>

        <template #cell(harms)="{ item }">
            <span class="whitespace-nowrap tabular-nums" :class="item.harms ? 'text-red-700' : 'text-gray-400'">{{ item.harms }}</span>
        </template>

        <template #cell(lower_bound)="{ item }">
            <span class="whitespace-nowrap font-medium tabular-nums">{{ Math.round(item.lower_bound * 100) }}%</span>
        </template>

        <template #cell(level)="{ item }">
            <span class="whitespace-nowrap rounded-full bg-gray-50 px-2 py-0.5 text-xs text-gray-600 ring-1 ring-inset ring-gray-200">{{ ctrans("Draft") }}</span>
        </template>
    </Table>
</template>
