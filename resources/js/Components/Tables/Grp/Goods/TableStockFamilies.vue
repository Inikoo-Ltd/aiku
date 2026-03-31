<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:16:55 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { StockFamily } from "@/types/stock-family";
import { computed, inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faEquals, faMinus, faTriangle } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

library.add(faTriangle, faEquals, faMinus)

defineProps<{
    data: object;
    tab?: string;
}>();

const locale = inject("locale", aikuLocaleStructure);

const interval = computed(() => {
    const url = usePage().url;
    const params = new URLSearchParams(url.split("?")[1]);
    return params.get("dateInterval") ?? "all";
});

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return { icon: faTriangle };
    } else {
        return { icon: faTriangle, class: "rotate-180" };
    }
};

const getIntervalStateColor = (isPositive: boolean) => {
    return isPositive ? "text-green-500" : "text-red-500";
};

function stockFamilyRoute(stockFamily: StockFamily) {
    return route("grp.goods.stock-families.show", [stockFamily.slug]);
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
        <template #cell(number_stocks)="{ item: stockFamily }">
            <Link :href="route('grp.goods.stock-families.show.stocks.index', stockFamily['slug'])">
                {{ stockFamily["number_stocks"] }}
            </Link>
        </template>
        <template #cell(revenue_grp_currency)="{ item: stockFamily }">
            {{ locale.currencyFormat(stockFamily["grp_currency"], Number(stockFamily["revenue_grp_currency_" + interval])) }}
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.grp_currency, item.sales_grp_currency_external) }}</span>
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
