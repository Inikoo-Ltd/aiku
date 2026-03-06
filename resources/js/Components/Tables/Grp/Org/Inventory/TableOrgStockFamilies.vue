<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { StockFamily } from "@/types/stock-family";
import { RouteParams } from "@/types/route-params";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { library } from "@fortawesome/fontawesome-svg-core";
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

function stockFamilyRoute(stockFamily: StockFamily) {
  return route(
    "grp.org.warehouses.show.inventory.org_stock_families.show",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).warehouse,
      stockFamily.slug]);

}

function orgStockFamilyOrgStocksRoute(stockFamily: StockFamily) {
  return route(
    "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).warehouse,
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
            <Link :href="orgStockFamilyOrgStocksRoute(stockFamily)" class="secondaryLink">
                {{ stockFamily["number_current_org_stocks"] }}
            </Link>
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', item.sales_grp_currency_external) }}</span>
        </template>

        <template #cell(sales_grp_currency_external_delta)="{ item }">
            <div v-if="item.sales_grp_currency_external_delta">
                <span>{{ item.sales_grp_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_grp_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(invoices)="{ item }">
            <span class="tabular-nums">{{ item.invoices }}</span>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta">
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
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>
    </Table>
</template>
