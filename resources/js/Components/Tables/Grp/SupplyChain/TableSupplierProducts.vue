<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import {SupplierProduct} from "@/types/supplier-product";

const props = defineProps<{
    data: object,
    tab?: string
}>()


function supplierProductRoute(supplierProduct: SupplierProduct) {
    switch (route().current()) {
        case 'grp.org.procurement.suppliers.show':
            return route(
                'grp.org.procurement.suppliers.show.supplier_products.show',
                [supplierProduct.supplier_slug, supplierProduct.slug])
        case 'grp.org.procurement.agents.show':
            return route(
                'grp.org.procurement.agents.show.supplier_products.show',
                [supplierProduct.agent_slug, supplierProduct.slug]);
        case 'grp.org.procurement.agents.show.suppliers.show':
            return route(
                'grp.org.procurement.agents.show.suppliers.show.supplier_products.show',
                [supplierProduct.agent_slug, supplierProduct.supplier_slug, supplierProduct.slug]);
        case 'grp.supply-chain.suppliers.supplier_products.index':
            return route(
                'grp.supply-chain.suppliers.supplier_products.show',
                [route().params["supplier"], supplierProduct.slug]);
      case 'grp.org.warehouses.show.agent_inventory.supplier_products.index':
        return route(
          'grp.org.warehouses.show.agent_inventory.supplier_products.show',
          [route().params["organisation"],route().params["warehouse"], supplierProduct.slug]);

        case 'grp.supply-chain.agents.show.supplier_products.index':
        case 'grp.supply-chain.supplier_products.index':
        case 'grp.supply-chain.supplier_products.free':
        case 'grp.supply-chain.supplier_products.in_agents':
        case 'grp.overview.procurement.supplier-products.index':
            return route('grp.majordomo.redirect_supplier_product', [supplierProduct.id]);
        default:
            return route(
                'grp.org.procurement.org_supplier_products.show',
                [supplierProduct.slug]);
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: supplier_product }">
            <Link :href="supplierProductRoute(supplier_product)" class="primaryLink">
                {{ supplier_product['code'] }}
            </Link>
        </template>
    </Table>
</template>


