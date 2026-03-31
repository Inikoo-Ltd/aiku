<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Stock } from "@/types/stock";
import { computed, inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { RouteParams } from "@/types/route-params";
import Icon from "@/Components/Icon.vue";
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

function stockRoute(stock: Stock) {
    switch (route().current()) {
        case "grp.goods.stocks.active_stocks.index":
            return route("grp.goods.stocks.active_stocks.show", [stock.slug]);
        case "grp.goods.stocks.in_process_stocks.index":
            return route("grp.goods.stocks.in_process_stocks.show", [stock.slug]);
        case "grp.goods.stocks.discontinuing_stocks.index":
            return route("grp.goods.stocks.discontinuing_stocks.show", [stock.slug]);
        case "grp.goods.stocks.discontinued_stocks.index":
            return route("grp.goods.stocks.discontinued_stocks.show", [stock.slug]);
        case "grp.goods.stock-families.show.stocks.index":
            return route("grp.goods.stock-families.show.stocks.show", [
                (route().params as RouteParams).stockFamily,
                stock.slug,
            ]);
        default:
            return route("grp.goods.stocks.show", [stock.slug]);
    }
}

function stockFamilyRoute(stock: Stock) {
    return route("grp.goods.stock-families.show", [stock.family_slug]);
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: stock }">
            <Link :href="stockRoute(stock)" class="primaryLink">
                {{ stock["code"] }}
            </Link>
        </template>
        <template #cell(family_code)="{ item: stock }">
            <Link v-if="stock.family_slug" :href="stockFamilyRoute(stock)" class="secondaryLink">
                {{ stock["family_code"] }}
            </Link>
        </template>
        <template #cell(description)="{ item: stock }">
            {{ stock["description"] }}
        </template>
        <template #cell(state)="{ item: stock }">
            <Icon :data="stock.state_icon" />
        </template>
        <template #cell(revenue_grp_currency)="{ item: stock }">
            {{ locale.currencyFormat(stock["grp_currency"], Number(stock["revenue_grp_currency_" + interval])) }}
        </template>
        <template #cell(number_number_org_stocks_state_active)="{ item: stock }">
            {{ stock["number_number_org_stocks_state_active"] }} / {{ stock["number_org_stocks"] }}
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
