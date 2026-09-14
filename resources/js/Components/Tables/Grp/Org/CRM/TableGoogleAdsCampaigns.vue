<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: {}
    currency: string
    tab?: string
}>()

const locale = useLocaleStore()

/**
 * Google's own labels, which are shouted enums. PAUSED and ENDED are settled states and stay quiet;
 * the two worth a colour are the ones that cost money or waste it: LIMITED means the budget is
 * capping delivery, NOT_ELIGIBLE means it is not running at all despite being switched on.
 */
const statusLabels: Record<string, string> = {
    ELIGIBLE: "Eligible",
    LIMITED: "Budget limited",
    NOT_ELIGIBLE: "Not serving",
    PENDING: "Pending",
    PAUSED: "Paused",
    ENDED: "Ended",
    REMOVED: "Removed",
    ENABLED: "Enabled",
}

const statusClass = (status: string | null) => {
    if (status === "LIMITED") return "text-[#a15c00]"
    if (status === "NOT_ELIGIBLE") return "text-[#d03b3b]"
    if (status === "ELIGIBLE" || status === "ENABLED") return "text-[#006300]"
    return "text-gray-500"
}

const channelLabel = (channel: string | null) =>
    channel ? channel.replace(/_/g, " ").toLowerCase() : null
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-3">
        <template #cell(name)="{ item }">
            <Link :href="route(item.route.name, item.route.parameters)" class="primaryLink">
                {{ item.name }}
            </Link>
        </template>

        <template #cell(status)="{ item }">
            <div v-if="!item.status" class="text-gray-500">{{ trans("Not read yet") }}</div>
            <div v-else :class="statusClass(item.status)">
                {{ trans(statusLabels[item.status] ?? item.status) }}
            </div>
        </template>

        <template #cell(channel_type)="{ item }">
            <div class="capitalize text-gray-600">{{ channelLabel(item.channel_type) ?? "—" }}</div>
        </template>

        <template #cell(impressions)="{ item }">
            <div class="tabular-nums text-gray-600">{{ locale.number(item.impressions) }}</div>
        </template>

        <template #cell(clicks)="{ item }">
            <div class="tabular-nums text-gray-600">{{ locale.number(item.clicks) }}</div>
        </template>

        <!-- A dash, not 0%, where there were no impressions: the question has no answer, and a zero
             would read as the answer "nobody clicked". -->
        <template #cell(ctr)="{ item }">
            <div v-if="item.ctr === null" class="text-gray-400">—</div>
            <div v-else class="tabular-nums text-gray-600">{{ item.ctr.toFixed(2) }}%</div>
        </template>

        <template #cell(avg_cpc)="{ item }">
            <div v-if="item.avg_cpc === null" class="text-gray-400">—</div>
            <div v-else class="tabular-nums text-gray-600">
                {{ locale.currencyFormat(item.currency_code ?? currency, item.avg_cpc) }}
            </div>
        </template>

        <template #cell(budget_amount)="{ item }">
            <div v-if="item.budget_amount === null" class="text-gray-400">—</div>
            <div v-else class="tabular-nums text-gray-600">
                {{ locale.currencyFormat(item.currency_code ?? currency, item.budget_amount) }}
            </div>
        </template>

        <template #cell(spend)="{ item }">
            <div class="tabular-nums text-gray-900">{{ locale.currencyFormat(currency, item.spend) }}</div>
        </template>

        <template #cell(conversions)="{ item }">
            <div class="tabular-nums text-gray-600">{{ locale.number(item.conversions) }}</div>
        </template>

        <!-- Green above break-even, red below, so the row that is losing money is findable by colour
             in a list of fifty. Never both an arrow and a colour; the colour carries it alone. -->
        <template #cell(roas)="{ item }">
            <div v-if="item.roas === null" class="text-gray-400">—</div>
            <div v-else class="tabular-nums" :class="item.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                {{ item.roas.toFixed(2) }}×
            </div>
        </template>
    </Table>
</template>
