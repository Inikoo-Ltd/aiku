<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 11 Aug 2024 10:11:50 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { OrgSupplierProduct } from "@/types/org-supplier-product"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
  data: object
  tab?: string
}>()

const routeParams = route().params
const currentRouteName = route().current()

function addToShoppingList(supplierProduct: OrgSupplierProduct & { units_per_carton?: number }) {
  router.post(
    route("grp.org.procurement.shopping_list.store", [routeParams["organisation"], supplierProduct.slug]),
    { quantity_units: supplierProduct.units_per_carton ?? 1 },
    { preserveScroll: true }
  )
}

function supplierProductRoute(supplierProduct: OrgSupplierProduct) {
  switch (currentRouteName) {
    case "grp.org.procurement.org_agents.show.supplier_products.index":
      return route(
        "grp.org.procurement.org_agents.show.supplier_products.show",
        [routeParams["organisation"], routeParams["orgAgent"], supplierProduct.slug])

    case "grp.org.procurement.org_suppliers.show":
    case "grp.org.procurement.org_suppliers.show.supplier_products.index":
      return route(
        "grp.org.procurement.org_suppliers.show.supplier_products.show",
        [routeParams["organisation"], routeParams["orgSupplier"], supplierProduct.slug])

    default:
      return route(
        "grp.org.procurement.org_supplier_products.show",
        [routeParams["organisation"], supplierProduct.slug])
  }
}

function orgSupplierRoute(orgSupplierSlug: string) {
  return route("grp.org.procurement.org_suppliers.show", [routeParams["organisation"], orgSupplierSlug])
}
</script>

<template>
  <div>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(code)="{ item: supplier_product }">
      <Link :href="supplierProductRoute(supplier_product)" class="primaryLink">
        {{ supplier_product["code"] }}
      </Link>
    </template>
    <template #cell(supplier_name)="{ item: supplier_product }">
      <Link
        v-if="supplier_product.org_supplier_slug"
        :href="orgSupplierRoute(supplier_product.org_supplier_slug)"
        class="secondaryLink">
        {{ supplier_product.supplier_name }}
      </Link>
      <span v-else>{{ supplier_product.supplier_name }}</span>
    </template>
    <template #cell(add)="{ item: supplier_product }">
      <button type="button" class="secondaryLink" @click="addToShoppingList(supplier_product)">
        {{ ctrans("Add to shopping list") }}
      </button>
    </template>
  </Table>
  </div>
</template>
