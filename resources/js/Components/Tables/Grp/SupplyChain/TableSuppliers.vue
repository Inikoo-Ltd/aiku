<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { bucketQuery } from "@/Composables/bucketQuery"
import Table from "@/Components/Table/Table.vue";
import { Supplier } from "@/types/supplier";
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue";
import { useLocaleStore } from "@/Stores/locale";

defineProps<{
  data: object,
  tab?: string
}>();

const locale = useLocaleStore();

function supplierHref(supplier: Supplier) {
  const bucket = route().current()?.match(/^grp\.supply-chain\.suppliers\.(free|in_agents)$/)?.[1]

  return supplierRoute(supplier) + bucketQuery(bucket)
}

function supplierRoute(supplier: Supplier) {
    return route("grp.majordomo.redirect_supplier", [supplier.id])
}

</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">


    <template #cell(code)="{ item: supplier }">
      <Link :href="supplierHref(supplier)" class="primaryLink">
        {{ supplier["code"] }}
      </Link>
    </template>
    <template #cell(number_supplier_products)="{ item: supplier }">
      {{ locale.number(supplier.number_supplier_products) }}
    </template>
    <template #cell(location)="{ item: supplier }">
      <AddressLocation :data="supplier['location']" />
    </template>
  </Table>
</template>


