<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 24 Jul 2024 00:46:50 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Supplier } from "@/types/supplier";
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue";
import { useLocaleStore } from "@/Stores/locale";
import Icon from "@/Components/Icon.vue"

defineProps<{
  data: object,
  tab?: string
}>();

const locale = useLocaleStore();

const routeCurrent = route().current()
const routeParams = route().routeParams

function supplierRoute(supplier: Supplier) {
  console.log(routeCurrent)
    switch (routeCurrent) {
        case 'grp.org.procurement.org_suppliers.index':
            return route(
                'grp.org.procurement.org_suppliers.show',
                [
                  routeParams['organisation'],
                  supplier.org_supplier_slug
                ]);
        case 'grp.org.procurement.org_agents.show.suppliers.index':
            return route(
                'grp.org.procurement.org_agents.show.suppliers.show',
                [
                  routeParams['organisation'],
                  routeParams['orgAgent'],
                  supplier.org_supplier_slug
                ]);

    }
}

</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(status)="{ item: supplier }">
        <Icon :data="supplier.status_icon"> </Icon>
    </template>
    <template #cell(code)="{ item: supplier }">
      <Link :href="supplierRoute(supplier)" class="primaryLink">
        {{ supplier["code"] }}
      </Link>
    </template>
    <template #cell(number_supplier_productsz)="{ item: supplier }">
      {{ locale.number(supplier.number_supplier_products) }}
    </template>
    <template #cell(location)="{ item: supplier }">
      <AddressLocation :data="supplier['location']" />
    </template>
  </Table>
</template>


