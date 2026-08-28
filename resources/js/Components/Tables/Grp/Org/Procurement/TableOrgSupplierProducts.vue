<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 11 Aug 2024 10:11:50 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { OrgSupplierProduct } from "@/types/org-supplier-product"
import { trans } from "laravel-vue-i18n"

defineProps<{
  data: object
  tab?: string
}>()

function addToShoppingList(supplierProduct: OrgSupplierProduct & { units_per_carton?: number }) {
  router.post(
    route("grp.org.procurement.shopping_list.store", [route().params["organisation"], supplierProduct.slug]),
    { quantity_units: supplierProduct.units_per_carton ?? 1 },
    { preserveScroll: true }
  )
}

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
    <template #cell(add)="{ item: supplier_product }">
      <button type="button" class="secondaryLink" @click="addToShoppingList(supplier_product)">
        {{ trans("Add to shopping list") }}
      </button>
    </template>
  </Table>
</template>
