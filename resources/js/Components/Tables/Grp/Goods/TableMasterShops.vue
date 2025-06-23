<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { MasterShop } from "@/types/master-shop";

defineProps<{
  data: {}
  tab?: string
}>();

function masterShopRoute(masterShop: MasterShop) {
  return route(
    "grp.masters.master_shops.show",
    [masterShop.slug]);
}

function masterDepartmentsRoute(masterShop: MasterShop) {
  return route(
    "grp.masters.master_shops.show.master_departments.index",
    [masterShop.slug]);
}

function masterFamiliesRoute(masterShop: MasterShop) {
  return route(
    "grp.masters.master_shops.show.master_families.index",
    [masterShop.slug]);
}

function masterProductsRoute(masterShop: MasterShop) {
  return route(
    "grp.masters.master_shops.show.products.index",
    [masterShop.slug]);
}


</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(code)="{ item: masterShop }">
      <Link :href="masterShopRoute(masterShop) as string" class="primaryLink">
        {{ masterShop["code"] }}
      </Link>
    </template>
    <template #cell(name)="{ item: masterShop }">
      {{ masterShop["name"] }}
    </template>
    <template #cell(departments)="{ item: masterShop }">
      <Link :href="masterDepartmentsRoute(masterShop) as string" class="secondaryLink">
        {{ masterShop["departments"] }}
      </Link>
    </template>
    <template #cell(families)="{ item: masterShop }">
      <Link :href="masterFamiliesRoute(masterShop) as string" class="secondaryLink">
        {{ masterShop["families"] }}
      </Link>
    </template>
    <template #cell(products)="{ item: masterShop }">
      <Link :href="masterProductsRoute(masterShop) as string" class="secondaryLink">
        {{ masterShop["products"] }}
      </Link>
    </template>
  </Table>
</template>


