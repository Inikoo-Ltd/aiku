<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { trans } from "laravel-vue-i18n";
import { Link } from "@inertiajs/vue3";
import { MasterProduct } from "@/types/master-product";
import { RouteParams } from "@/types/route-params";

defineProps<{
  data: {}
  tab?: string
}>();

function masterFamilyRoute(masterProduct: MasterProduct) {
  if (route().current() == "grp.masters.master_products.index") {
    return route(
      "grp.masters.master_families.show",
      { masterFamily: masterProduct.master_family_slug });
  } else {
    return route(
      "grp.masters.master_shops.show.master_families.show",
      { masterShop: (route().params as RouteParams).masterShop, masterFamily: masterProduct.master_family_slug });
  }
}

function masterDepartmentRoute(masterProduct: MasterProduct) {
  if (route().current() == "grp.masters.master_products.index") {
    return route(
      "grp.masters.master_departments.show",
      { masterDepartment: masterProduct.master_department_slug });
  } else {
    return route(
      "grp.masters.master_shops.show.master_departments.show",
      { masterShop: (route().params as RouteParams).masterShop, masterDepartment: masterProduct.master_department_slug });
  }
}

function masterShopRoute(masterProduct: MasterProduct) {
  return route("grp.masters.master_shops.show",
    {
      masterShop: masterProduct.master_shop_slug
    }
  );
}

</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">

    <template #cell(master_shop_code)="{ item: masterProduct }">
      <Link v-tooltip="masterProduct.master_shop_name" :href="masterShopRoute(masterProduct) as string" class="secondaryLink">
        {{ masterProduct["master_shop_code"] }}
      </Link>
    </template>

    <template #cell(master_department_code)="{ item: masterProduct }">
      <Link v-if="masterProduct.master_department_slug" v-tooltip="masterProduct.master_department_name" :href="masterDepartmentRoute(masterProduct) as string" class="secondaryLink">
        {{ masterProduct["master_department_code"] }}
      </Link>
      <span v-else class="opacity-70  text-red-500">
        {{ trans("No department") }}
      </span>
    </template>

    <template #cell(master_family_code)="{ item: masterProduct }">
      <Link v-if="masterProduct.master_family_slug" v-tooltip="masterProduct.master_family_name" :href="masterFamilyRoute(masterProduct) as string" class="secondaryLink">
        {{ masterProduct["master_family_code"] }}
      </Link>
      <span v-else class="opacity-70  text-red-500">
        {{ trans("No family") }}
      </span>
    </template>

    <template #cell(code)="{ item: masterProduct }">
      {{ masterProduct["code"] }}
    </template>
    <template #cell(name)="{ item: masterProduct }">
      {{ masterProduct["name"] }}
    </template>
  </Table>
</template>


