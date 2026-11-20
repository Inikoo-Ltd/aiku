<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { TableColumn } from "@/types/Table"
import { ctrans } from "@/Composables/useTrans"
import GoogleAdsMetric from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsMetric.vue"
import { campaignTypeLabel } from "@/Composables/googleAdsCampaignType"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBan,
    faBullseye,
    faBullseyeArrow,
    faClock,
    faExclamationTriangle,
    faEye,
    faFlagCheckered,
    faHandPointer,
    faPaperPlane,
    faPause,
    faQuestionCircle,
    faSackDollar,
    faSeedling,
    faShoppingCart,
    faSignalStream,
    faTrash,
    faUserPlus,
    faWallet,
} from "@fal"

/* The header icons and the status icons the listing draws, both resolved by name at render time, so
   both have to be in the library before the first row paints. */
library.add(
    faSignalStream,
    faEye,
    faHandPointer,
    faWallet,
    faBullseyeArrow,
    faBullseye,
    faSackDollar,
    faShoppingCart,
    faUserPlus,
    faPaperPlane,
    faPause,
    faSeedling,
    faBan,
    faClock,
    faFlagCheckered,
    faTrash,
    faQuestionCircle,
    faExclamationTriangle
)

export type GoogleAdsProfitSummary = {
    spend: number
    orders: number
    orders_without_profit: number
    sales: number
    profit: number | null
    margin: number | null
    profit_after_spend: number | null
    poas: number | null
}

const props = defineProps<{
    data: {}
    currency: string
    tab?: string
    summary?: GoogleAdsProfitSummary | null
}>()

type Kind = "count" | "money" | "shop_money" | "profit" | "percent" | "share" | "roas"
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
    ad_orders: { kind: "count" },
    ad_sales: { kind: "shop_money" },
    profit: { kind: "profit", strong: true },
    profit_after_spend: { kind: "profit" },
    poas: { kind: "roas" },
}

const totalKeys: Record<string, keyof GoogleAdsProfitSummary> = {
    spend: "spend",
    ad_orders: "orders",
    ad_sales: "sales",
    profit: "profit",
    profit_after_spend: "profit_after_spend",
    poas: "poas",
}

const isShopCurrency = (kind: Kind) => kind === "shop_money" || kind === "profit"

const metricKind = (kind: Kind) => (kind === "shop_money" ? "money" : kind)

const periodKeys = ["period", "from", "to", "compare"]

const ordersHref = (item: { route: { name: string; parameters: Record<string, string> } }) => {
    const query = new URLSearchParams(window.location.search)
    const kept = new URLSearchParams()

    for (const key of periodKeys) {
        const value = query.get(key)
        if (value !== null) {
            kept.set(key, value)
        }
    }

    const queryString = kept.toString()

    return route(item.route.name, item.route.parameters) + (queryString ? "?" + queryString : "") + "#orders"
}

const cellSlot = (key: string) => `cell(${key})`

const linkedOutCells = Object.fromEntries(Object.entries(metricCells).filter(([key]) => key !== "ad_orders"))
</script>

<template>
    <div>
        <Table :resource="data" :name="tab" class="mt-3">
            <template #topRows="{ show, columns, isCheckBox }: { show: (key: string) => boolean, columns: TableColumn[], isCheckBox: boolean }">
                <tr v-if="props.summary" class="bg-gray-100 font-semibold text-gray-800">
                    <td v-if="isCheckBox" />
                    <td
                        v-for="column in columns"
                        v-show="show(column.key)"
                        :key="`totals-${column.key}`"
                        class="whitespace-nowrap py-1 text-xs lg:py-2 lg:text-[13px]"
                        :class="column.align === 'right' ? 'pl-1.5 pr-2 text-right tabular-nums lg:pl-3' : 'px-2 lg:px-6'">
                        <span
                            v-if="column.key === 'name'"
                            v-tooltip="ctrans('Every order of the shop in the period that followed a Google ad click, including the few whose click could not be matched to a campaign.')">
                            {{ ctrans("Total") }}
                        </span>
                        <GoogleAdsMetric
                            v-else-if="totalKeys[column.key] && metricCells[column.key]"
                            :value="props.summary[totalKeys[column.key]] as number | null"
                            :kind="metricKind(metricCells[column.key].kind)"
                            :currency="currency"
                            strong />
                    </td>
                </tr>
            </template>

            <template #cell(name)="{ item }">
                <Link :href="route(item.route.name, item.route.parameters)" class="primaryLink">
                    {{ item.name }}
                </Link>
            </template>

            <template #cell(channel_type)="{ item }">
                <div class="text-gray-600">{{ campaignTypeLabel(item.channel_type) ?? "—" }}</div>
            </template>

            <template #cell(started_at)="{ item }">
                <div class="whitespace-nowrap text-gray-600">
                    {{ item.started_at ? useFormatTime(item.started_at, { formatTime: "aiku" }) : "—" }}
                </div>
            </template>

            <template #cell(ad_orders)="{ item }">
                <component
                    :is="item.ad_orders > 0 ? Link : 'div'"
                    :href="item.ad_orders > 0 ? ordersHref(item) : undefined"
                    v-tooltip="item.ad_orders > 0 ? ctrans('See these orders') : undefined"
                    :class="item.ad_orders > 0 ? 'primaryLink' : ''">
                    <GoogleAdsMetric
                        :value="item.ad_orders"
                        kind="count"
                        :previous="item.previous ? item.previous.ad_orders : undefined" />
                </component>
            </template>

            <template v-for="(cell, key) in linkedOutCells" :key="key" #[cellSlot(key)]="{ item }">
                <GoogleAdsMetric
                    :value="item[key]"
                    :kind="metricKind(cell.kind)"
                    :currency="isShopCurrency(cell.kind) ? currency : item.currency_code ?? currency"
                    :previous="item.previous ? item.previous[key] : undefined"
                    :better="cell.better ?? 'up'"
                    :strong="cell.strong ?? false" />
            </template>
        </Table>
    </div>
</template>

<style scoped>
/*
 * Every figure here is right aligned by the shared table, which then reserves 2.25rem to the right of
 * it for the sort arrow. Across the wide columns that carry a sentence of a label that space is
 * invisible, but this table is twenty narrow columns of five-character numbers, and the reserved
 * space leaves each figure sitting nearer the middle of its cell than its right edge, which reads as
 * centred however the browser aligned it.
 *
 * Trimming it to the cell's own left padding puts the figures against the right edge where a column
 * of numbers is read from. The arrow keeps its place in the heading and simply overhangs them.
 */
:deep(tbody td.text-right) {
	padding-right: 0.5rem;
}

@media (min-width: 1024px) {
	:deep(tbody td.text-right) {
		padding-right: 0.75rem;
	}
}
</style>
