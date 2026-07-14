<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:09:00 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Stock } from "@/types/stock";
import { faBoxFull, faClipboardCheck, faDumpster, faHandsHelping, faInboxIn, faInboxOut, faInfoCircle, faPersonCarry, faQuestionCircle, faTilde, faTruckLoading } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import OrgStockMovements from "@/Pages/Grp/Org/Inventory/OrgStockMovements.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "@/Components/Icon.vue";

library.add(
  faTilde,
  faDumpster,
  faInfoCircle,
  faHandsHelping,
  faPersonCarry,
  faQuestionCircle
)

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

const locationRoute = (orgStockMovement, extraData = {}) => {
  return route('grp.org.warehouses.show.infrastructure.locations.show', {
    organisation: orgStockMovement.organisation_slug,
    warehouse: orgStockMovement.warehouse_slug,
    location: orgStockMovement.location_slug,
    ...extraData
  })
}

function deliveryNoteRoute(orgStockMovement) {
    return route("grp.helpers.redirect_delivery_notes", [orgStockMovement.delivery_note_id])
}

</script>

<template>

  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(user)="{ item: orgStockMovement }">
      <span :class="orgStockMovement.user?.contact_name ? 'font-semibold' : ''">
        {{ orgStockMovement.user ? `${orgStockMovement.user?.contact_name} [${orgStockMovement.user?.username}]` : 'System' }}
      </span>
    </template>

    <template #cell(type)="{ item }">
      <span v-if="item.delivery_note_reference && item.delivery_note_id">
        <Link class="primaryLink !px-2 !py-1 !border !rounded-md !border-yellow-300 font-semibold" :href="deliveryNoteRoute(item)">
          <FontAwesomeIcon 
            :icon="faTruckLoading"
            class="pr-1"
          />
          {{ item.delivery_note_reference }}
        </Link>
      </span>
      
      <span v-else>
        {{ item.type_label }}
      </span>
    </template>

    <template #cell(location_code)="{ item: orgStockMovement }">
      <div class="flex">
        <Link class="primaryLink" :href="locationRoute(orgStockMovement)">
          {{ orgStockMovement.location_code }}
        </Link>
        
        <Link class="ml-auto" :href="locationRoute(orgStockMovement, {tab: 'stock_movements'})">
          <span class="my-auto px-2 py-[0.125rem] border rounded-md border-gray-400 hover:animate-pulse" v-tooltip="ctrans('Running quantity under this location')">
            <FontAwesomeIcon 
              :icon="faBoxFull"
            />
            {{ orgStockMovement.running_quantity ?? 0 }}
          </span>
        </Link>
      </div>
    </template>

    <template #cell(class)="{item}">
      <Icon
        :data="item.class_icon"
      />
    </template>

    <template #cell(flow)="{ item: orgStockMovement }">
      <FontAwesomeIcon v-if="orgStockMovement.flow == 'in'" v-tooltip="trans('Stock Coming In')" :icon="faInboxIn" class="text-green-500"/>
      <FontAwesomeIcon v-else-if="orgStockMovement.flow == 'out'" v-tooltip="trans('Stock Coming Out')" :icon="faInboxOut" class="text-red-500"/>
      <FontAwesomeIcon v-else-if="orgStockMovement.flow == 'audit'" v-tooltip="trans('Stock Audited')" :icon="faClipboardCheck" class="text-gray-500"/>
    </template>

    <template #cell(quantity)="{ item: orgStockMovement }">
      <span :class="Number(orgStockMovement.quantity) == 0 ? 'border-gray-300' : (orgStockMovement.is_negative ? 'text-red-500 bg-red-100 border-red-300' : 'text-green-500 bg-green-100 border-green-300')" class="px-3  border rounded-md w-fit min-w-14 text-center grid justify-self-end">
        {{ Number(orgStockMovement.quantity) }}
      </span>
    </template>

    <template #cell(running_quantity_location)="{ item: orgStockMovement }">
      <span class="my-auto ml-auto px-2 py-[0.125rem] border rounded-md border-gray-400" v-tooltip="ctrans('Running quantity under this location')">
        <FontAwesomeIcon 
          :icon="faBoxFull"
        />
        {{ orgStockMovement.running_quantity ?? 0 }}
      </span>
    </template>

    <template #cell(running_quantity_org_stock)="{ item: orgStockMovement }">
      <span v-if="orgStockMovement.running_quantity_org_stock">
        {{ (orgStockMovement.type == 'location-transfer' && orgStockMovement.quantity < 0) ? (Number(orgStockMovement.running_quantity_org_stock) + -(Number(orgStockMovement.quantity)))  : Number(orgStockMovement.running_quantity_org_stock) }}
      </span>
    </template>
  </Table>
</template>


