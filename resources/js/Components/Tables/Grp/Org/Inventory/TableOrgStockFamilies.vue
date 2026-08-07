<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue";
import { StockFamily } from "@/types/stock-family";
import { RouteParams } from "@/types/route-params";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n";
import { faEquals, faMinus, faTriangle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

library.add(faTriangle, faEquals, faMinus)

const locale = inject("locale", aikuLocaleStructure)

defineProps<{
    data: object;
    tab?: string;
}>();

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return { icon: faTriangle }
    } else {
        return { icon: faTriangle, class: "rotate-180" }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    return isPositive ? "text-green-500" : "text-red-500"
}

const stockTurnColor = (stockTurn: number) => {
    if (stockTurn >= 4) return "text-green-500"
    if (stockTurn >= 2.5) return "text-blue-500"
    if (stockTurn >= 1.5) return "text-amber-500"
    return "text-red-500"
}

const routeParams = route().params as RouteParams

function stockFamilyRoute(stockFamily: StockFamily) {
  return route(
    "grp.org.warehouses.show.inventory.org_stock_families.show",
    [
      routeParams.organisation,
      routeParams.warehouse,
      stockFamily.slug]);

}

function orgStockFamilyOrgStocksRoute(stockFamily: StockFamily) {
  return route(
    "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index",
    [
      routeParams.organisation,
      routeParams.warehouse,
      stockFamily.slug]);
}

</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: stockFamily }">
            <Link :href="stockFamilyRoute(stockFamily)" class="primaryLink">
                {{ stockFamily["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: stockFamily }">
            {{ stockFamily["name"] }}
        </template>
        <template #cell(number_current_org_stocks)="{ item: stockFamily }">
            <span class="tabular-nums whitespace-nowrap">
                <Link :href="orgStockFamilyOrgStocksRoute(stockFamily)" class="secondaryLink">
                    {{ stockFamily["number_current_org_stocks"] }}
                </Link>
                <span
                    v-if="stockFamily['number_out_of_stock_org_stocks'] > 0"
                    v-tooltip="trans('SKOs out of stock')"
                    class="text-gray-400 cursor-help">
                    ({{ stockFamily["number_out_of_stock_org_stocks"] }})
                </span>
            </span>
        </template>

        <template #cell(stock_value)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.stock_value) }}</span>
        </template>

        <template #cell(potential_sales)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.potential_sales) }}</span>
        </template>

        <template #cell(on_the_way_po_value)="{ item }">
            <span class="tabular-nums">
                {{ locale.currencyFormat(item.currency_code, item.on_the_way_po_value) }}
                <span v-if="item.on_the_way_po_count > 0" class="text-gray-400">({{ item.on_the_way_po_count }})</span>
            </span>
        </template>

        <template #cell(gross_profit)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.gross_profit) }}</span>
        </template>

        <template #cell(stock_turn)="{ item }">
            <span v-if="item.stock_turn !== null" class="tabular-nums" :class="stockTurnColor(item.stock_turn)">{{ item.stock_turn.toFixed(1) }}&times;</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(stock_cover)="{ item }">
            <span v-if="item.stock_cover !== null" class="tabular-nums whitespace-nowrap">{{ item.stock_cover.toFixed(1) }} mo</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(sales_org_currency_external)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.sales_org_currency_external) }}</span>
        </template>

        <template #cell(sales_org_currency_external_delta)="{ item }">
            <div v-if="item.sales_org_currency_external_delta" class="whitespace-nowrap">
                <span>{{ item.sales_org_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_org_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_org_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_org_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else class="whitespace-nowrap">
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(invoices)="{ item }">
            <Link v-if="item.invoices_route" :href="route(item.invoices_route.name, item.invoices_route.parameters)" class="secondaryLink tabular-nums">
                {{ item.invoices }}
            </Link>
            <span v-else class="tabular-nums">{{ item.invoices }}</span>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta" class="whitespace-nowrap">
                <span>{{ item.invoices_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.invoices_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.invoices_delta.is_positive).class,
                        getIntervalStateColor(item.invoices_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else class="whitespace-nowrap">
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>
    </Table>
</template>
