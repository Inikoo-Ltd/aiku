<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:09:00 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Stock } from "@/types/stock";
import { inject } from "vue";
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

defineProps<{
  data: object
  tab?: string
}>();

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})
console.log(layout)

function stockRoute(stock: Stock) {
  console.log(route().current());
  switch (route().current()) {
    case "grp.org.warehouses.show.inventory.org_stock_families.show":
      return route(
        "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          route().params["orgStockFamily"],
          stock.slug
        ]);
    case "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index":
      return route(
        "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          stock.slug
        ]);
    case "grp.overview.inventory.org-stocks.index":
      return route(
        "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show",
        [
          stock.organisation_slug,
          stock.warehouse_slug,
          stock.slug
        ]);
    case "grp.org.warehouses.show.inventory.org_stocks.index":
      return route(
        "grp.org.warehouses.show.inventory.org_stocks.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          stock.slug
        ]);
    case "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index":
      return route(
        "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          stock.slug
        ]);
  }
}

function stockFamilyRoute(stock: Stock) {
  switch (route().current()) {

    default:
      return route(
        "grp.org.warehouses.show.inventory.org_stock_families.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          stock.family_slug
        ]);
  }
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
      <!--suppress TypeScriptUnresolvedReference -->
      <Link v-if="stock.family_slug" :href="stockFamilyRoute(stock)" class="secondaryLink">
        {{ stock["family_code"] }}
      </Link>
    </template>
    <template #cell(type)="{ item: stock }">
      {{ stock.type ?? 'no data' }}
    </template>

    <template #cell(picking_priority)="{ item: stock }">
      {{ stock.picking_priority ?? 'no data'}}
    </template>

    <template #cell(value)="{ item: stock }">
      {{ locale.currencyFormat(layout.group.currency.code, stock.value)   }} 
    </template>

    <template #cell(dropshipping_pipe)="{ item: stock }">
      {{ stock.dropshipping_pipe ?? 'no data'}}
    </template>

    <template #cell(quantity)="{ item: stock }">
      {{ stock.quantity ?? 'no data'}}
    </template>

    <template #cell(notes)="{ item: stock }">
      {{ stock.notes ?? 'no data'}}
    </template>

  </Table>
</template>


