<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:49:03 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { PurchaseOrder } from "@/types/purchase-order";
import { useFormatTime } from "@/Composables/useFormatTime";
import Icon from "@/Components/Icon.vue";
import { useLocaleStore } from "@/Stores/locale";

defineProps<{
  data: {}
  tab?: string
}>();

const locale = useLocaleStore();

function PurchaseOrderRoute(purchaseOrder: PurchaseOrder) {

  console.log(route().current());
  switch (route().current()) {
    case "grp.org.procurement.purchase_orders.index":
      return route(
        "grp.org.procurement.purchase_orders.show",
        [route().params["organisation"], purchaseOrder.slug]);
    case "grp.org.procurement.org_agents.show.purchase-orders.index":
      return route(
        "grp.org.procurement.org_agents.show.purchase-orders.show",
        [route().params["organisation"], route().params["orgAgent"], purchaseOrder.slug]);
    case "grp.org.procurement.org_suppliers.show":
      return route(
        "grp.org.procurement.org_suppliers.show.purchase-orders.show",
        [route().params["organisation"], route().params["orgSupplier"], purchaseOrder.slug]);
    case "grp.org.procurement.org_partners.show.purchase-orders.index":
      return route(
        "grp.org.procurement.org_partners.show.purchase-orders.show",
        [route().params["organisation"], route().params["orgPartner"], purchaseOrder.slug]);
    case "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show":
    case "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show":
    case "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show":
      return route(
        "grp.org.procurement.purchase_orders.show",
        [route().params["organisation"], purchaseOrder.slug]);
    default:
      return "";
  }
}

function SupplierRoute(purchaseOrder: PurchaseOrder) {
  switch (route().current()) {
    case "grp.org.procurement.purchase_orders.index":
      return route(
        "grp.org.procurement.org_suppliers.show",
        [route().params["organisation"], purchaseOrder.parent_slug]);
    case "grp.org.procurement.org_agents.show.purchase-orders.index":
      return route(
        "grp.org.procurement.org_agents.show.suppliers.show",
        [route().params["organisation"], route().params["orgAgent"], purchaseOrder.parent_slug]);
    case "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show":
    case "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show":
    case "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show":
      return route(
        "grp.supply-chain.suppliers.show",
        [purchaseOrder.supplier_slug]);
    default:
      return "";
  }
}

function AgentRoute(purchaseOrder: PurchaseOrder) {
  switch (route().current()) {
    case "grp.org.procurement.purchase_orders.index":
      return route(
        "grp.org.procurement.org_agents.show",
        [route().params["organisation"], purchaseOrder.parent_slug]);
    case "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show":
    case "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show":
    case "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show":
      return route(
        "grp.supply-chain.agents.show",
        [purchaseOrder.agent_slug]);
    default:
      return "";
  }
}

</script>

<template>

  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(reference)="{ item: purchaseOrder }">
      <Link :href="PurchaseOrderRoute(purchaseOrder)" class="primaryLink">
        {{ purchaseOrder.reference }}
      </Link>
    </template>

    <template #cell(parent_name)="{ item: purchaseOrder }">
      <Link
        :href="purchaseOrder.parent_type === 'OrgSupplier' ? SupplierRoute(purchaseOrder) : AgentRoute(purchaseOrder)"
        class="secondaryLink">
        {{ purchaseOrder.parent_name }}
      </Link>
    </template>

    <template #cell(state)="{ item: purchaseOrder }">
      <Icon :data="purchaseOrder.state_icon" />
    </template>


    <template #cell(org_total_cost)="{ item: purchaseOrder }">
      {{ useLocaleStore().currencyFormat(purchaseOrder.org_currency_code, purchaseOrder.org_total_cost) }}
    </template>

    <template #cell(date)="{ item: purchaseOrder }">
      <div class="text-right">
        {{ useFormatTime(purchaseOrder["date"], { localeCode: locale.language.code, formatTime: "aiku" }) }}
      </div>
    </template>

    <template #cell(parent)="{ item: purchaseOrder }">
      {{ purchaseOrder["parent_name"] }}
    </template>
  </Table>
</template>
