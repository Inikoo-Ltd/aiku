<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import GoogleAdsMetric from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsMetric.vue"
import { campaignTypeLabel } from "@/Composables/googleAdsCampaignType"
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: {}
    currency: string
    tab?: string
}>()

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

type Kind = "count" | "money" | "shop_money" | "percent" | "share" | "roas"
type Better = "up" | "down" | "none"

/* Spend is the one figure in the shop's currency; everything else with a currency is in the ad
   account's. `better` says which direction the comparison colours green: a cost per click going up
   is bad news, spend going up is just news. */
const metricCells: Record<string, { kind: Kind; better?: Better; strong?: boolean }> = {
    impressions: { kind: "count" },
    clicks: { kind: "count" },
    ctr: { kind: "percent" },
    avg_cpc: { kind: "money", better: "down" },
    budget_amount: { kind: "money", better: "none" },
    spend: { kind: "shop_money", better: "none", strong: true },
    conversions: { kind: "count" },
    cost_per_conversion: { kind: "money", better: "down" },
    conversions_value: { kind: "money" },
    roas: { kind: "roas" },
    all_conversions: { kind: "count" },
    all_conversions_value: { kind: "money" },
    purchases: { kind: "count" },
    cost_per_purchase: { kind: "money", better: "down" },
    purchase_rate: { kind: "percent" },
    registrations: { kind: "count" },
    cost_per_registration: { kind: "money", better: "down" },
    registration_rate: { kind: "percent" },
    search_impression_share: { kind: "share" },
    search_rank_lost_impression_share: { kind: "share", better: "down" },
    search_budget_lost_impression_share: { kind: "share", better: "down" },
    search_top_impression_share: { kind: "share" },
    search_rank_lost_top_impression_share: { kind: "share", better: "down" },
    search_budget_lost_top_impression_share: { kind: "share", better: "down" },
    search_absolute_top_impression_share: { kind: "share" },
    search_rank_lost_absolute_top_impression_share: { kind: "share", better: "down" },
    search_budget_lost_absolute_top_impression_share: { kind: "share", better: "down" },
}

const cellSlot = (key: string) => `cell(${key})`
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
            <div class="text-gray-600">{{ campaignTypeLabel(item.channel_type) ?? "—" }}</div>
        </template>

        <template v-for="(cell, key) in metricCells" :key="key" #[cellSlot(key)]="{ item }">
            <GoogleAdsMetric
                :value="item[key]"
                :kind="cell.kind === 'shop_money' ? 'money' : cell.kind"
                :currency="cell.kind === 'shop_money' ? currency : item.currency_code ?? currency"
                :previous="item.previous ? item.previous[key] : undefined"
                :better="cell.better ?? 'up'"
                :strong="cell.strong ?? false" />
        </template>
    </Table>
</template>
