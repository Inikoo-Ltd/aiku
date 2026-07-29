<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 11 Aug 2024 10:11:50 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { OrgSupplierProduct } from "@/types/org-supplier-product"

defineProps<{
  data: object
  tab?: string
}>()

function supplierProductRoute(supplierProduct: OrgSupplierProduct) {
  switch (route().current()) {
    case "grp.org.procurement.org_agents.show.supplier_products.index":
      return route(
        "grp.org.procurement.org_agents.show.supplier_products.show",
        [route().params["organisation"], route().params["orgAgent"], supplierProduct.slug])

    case "grp.org.procurement.org_suppliers.show":
    case "grp.org.procurement.org_suppliers.show.supplier_products.index":
      return route(
        "grp.org.procurement.org_suppliers.show.supplier_products.show",
        [route().params["organisation"], route().params["orgSupplier"], supplierProduct.slug])

    default:
      return route(
        "grp.org.procurement.org_supplier_products.show",
        [route().params["organisation"], supplierProduct.slug])
  }
}
</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(code)="{ item: supplier_product }">
      <Link :href="supplierProductRoute(supplier_product)" class="primaryLink">
        {{ supplier_product["code"] }}
      </Link>
    </template>
  </Table>
</template>
