<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import {StockFamily} from "@/types/stock-family";

const props = defineProps<{
    data: object,
    tab?: string
}>()

function stockFamilyRoute(stockFamily: StockFamily) {
    switch (route().current()) {

      case 'grp.org.warehouses.show.inventory.org_stock_families.index':
            return route(
                'grp.org.warehouses.show.inventory.org_stock_families.show',
                [route().params['organisation'], route().params['warehouse'], stockFamily.slug]);
      case 'grp.overview.inventory.org-stock-families.index':
        return route(
            "grp.org.warehouses.show.inventory.org_stock_families.show",
            [
            stockFamily.organisation_slug,
            stockFamily.warehouse_slug,
            stockFamily.slug
            ]);
      default:
            return route(
               'grp.org.warehouses.show.inventory.org_stock_families.show',
                [route().params['organisation'], route().params['warehouse'], stockFamily.slug]);
    }

}

function orgStockFamilyOrgStocksRoute(stockFamily: StockFamily) {
  switch (route().current()) {
    case 'grp.overview.inventory.org-stock-families.index':
        return route(
            "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index",
            [
            stockFamily.organisation_slug,
            stockFamily.warehouse_slug,
            stockFamily.slug
            ]);
    case 'grp.org.warehouses.show.inventory.org_stock_families.index':
      return route(
        'grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.index',
        [route().params['organisation'],route().params['warehouse'],  stockFamily.slug]);

  }

}

</script>



<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: stockFamily }">
            <Link :href="stockFamilyRoute(stockFamily)" class="primaryLink">
                {{ stockFamily['code'] }}
            </Link>
        </template>
        <template #cell(name)="{ item: stockFamily }">
                {{ stockFamily['name'] }}
        </template>
        <template #cell(number_current_org_stocks)="{ item: stockFamily }">
          <Link :href="orgStockFamilyOrgStocksRoute(stockFamily)" class="secondaryLink">
                {{ stockFamily['number_current_org_stocks'] }}
            </Link>
        </template>
    </Table>
</template>
