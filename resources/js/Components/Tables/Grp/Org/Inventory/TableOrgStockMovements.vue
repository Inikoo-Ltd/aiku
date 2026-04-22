<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:09:00 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Stock } from "@/types/stock";
import { faClipboardCheck, faInboxIn, faInboxOut } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import OrgStockMovements from "@/Pages/Grp/Org/Inventory/OrgStockMovements.vue";

defineProps<{
  data: object
  tab?: string
}>();


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
    default:
        return null
  }
}

const locationRoute = (orgStockMovement) => {
  return route('grp.org.warehouses.show.infrastructure.locations.show', {
    organisation: orgStockMovement.organisation_slug,
    warehouse: orgStockMovement.warehouse_slug,
    location: orgStockMovement.location_slug,
  })
}

</script>

<template>

  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(user)="{item: orgStockMovement}">
      <span :class="orgStockMovement.user?.contact_name ? 'font-semibold' : ''">
        {{ orgStockMovement.user ? `${orgStockMovement.user?.contact_name} [${orgStockMovement.user?.username}]` : 'System' }}
      </span>
    </template>

    <template #cell(location_code)="{item: orgStockMovement}">
      <Link class="primaryLink" :href="locationRoute(orgStockMovement)">
        {{ orgStockMovement.location_code }}
      </Link>
    </template>

    <template #cell(flow)="{item: orgStockMovement}">
      <FontAwesomeIcon v-if="orgStockMovement.flow == 'in'" v-tooltip="trans('Stock Coming In')" :icon="faInboxIn" class="text-green-500"/>
      <FontAwesomeIcon v-else-if="orgStockMovement.flow == 'out'" v-tooltip="trans('Stock Coming Out')" :icon="faInboxOut" class="text-red-500"/>
      <FontAwesomeIcon v-else-if="orgStockMovement.flow == 'audit'" v-tooltip="trans('Stock Audited')" :icon="faClipboardCheck" class="text-gray-500"/>
    </template>

    <template #cell(quantity)="{item: orgStockMovement}">
      <span :class="orgStockMovement.quantity == 0 ? 'border-gray-300' : (orgStockMovement.is_negative ? 'text-red-500 bg-red-100 border-red-300' : 'text-green-500 bg-green-100 border-green-300')" class="px-3  border rounded-md w-fit min-w-14 text-center grid justify-self-end">
        {{ orgStockMovement.quantity }}
      </span>
    </template>
  </Table>
</template>


