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


defineProps<{
  data: object
  tab?: string
}>();


const locale = inject("locale", aikuLocaleStructure);

const interval = computed(() => {
  const url = usePage().url;
  const params = new URLSearchParams(url.split("?")[1]);
  return params.get("dateInterval") ?? "all";
});

function stockRoute(stock: Stock) {
  console.log(route().current());
  switch (route().current()) {
    case "grp.goods.stocks.active_stocks.index":
      return route(
        "grp.goods.stocks.active_stocks.show",
        [stock.slug]);
    case "grp.goods.stocks.in_process_stocks.index":
      return route(
        "grp.goods.stocks.in_process_stocks.show",
        [stock.slug]);
    case "grp.goods.stocks.discontinuing_stocks.index":
      return route(
        "grp.goods.stocks.discontinuing_stocks.show",
        [stock.slug]);
    case "grp.goods.stocks.discontinued_stocks.index":
      return route(
        "grp.goods.stocks.discontinued_stocks.show",
        [stock.slug]);
    case "grp.goods.stock-families.show.stocks.index":
      return route(
        "grp.goods.stock-families.show.stocks.show",
        [(route().params as RouteParams).stockFamily, stock.slug]);
    default:
      return route(
        "grp.goods.stocks.show",
        [
          stock.slug
        ]);
  }
}

function stockFamilyRoute(stock: Stock) {
  return route(
    "grp.goods.stock-families.show",
    [stock.family_slug]);
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
    <template #cell(unit_value)="{ item: stock }">
      {{ stock["unit_value"] }}
    </template>
    <template #cell(revenue_grp_currency)="{ item: stockFamily }">
      {{ locale.currencyFormat(stockFamily["grp_currency"], Number(stockFamily["revenue_grp_currency_" + interval])) }}
    </template>
  </Table>
</template>


