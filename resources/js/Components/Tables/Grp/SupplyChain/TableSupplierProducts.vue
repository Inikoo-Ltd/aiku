<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead } from "@fal"
import { bucketQuery } from "@/Composables/bucketQuery"
import Table from '@/Components/Table/Table.vue';
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierProductLabelsDialog from "@/Components/SupplyChain/SupplierProductLabelsDialog.vue"
import { ctrans } from "@/Composables/useTrans"
import {SupplierProduct} from "@/types/supplier-product";
import { routeType } from "@/types/route"

library.add(faBarcodeRead)

const props = defineProps<{
    data: object,
    tab?: string
    labelsPrint?: {
        plan_route: routeType
        pdf_route: routeType
    }
}>()

const selectedRows = ref<Record<string, boolean>>({})
const selectedSupplierProductIds = computed(() => Object.keys(selectedRows.value).filter((id) => selectedRows.value[id]).map(Number))
const isLabelsDialogOpen = ref(false)


function supplierProductHref(supplierProduct: SupplierProduct) {
  const bucket = route().current()?.match(/^grp\.supply-chain\.supplier_products\.(free|in_agents)$/)?.[1]

  return supplierProductRoute(supplierProduct) + bucketQuery(bucket)
}

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
    <div>
        <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="Boolean(labelsPrint)" @onSelectRow="(rows) => selectedRows = { ...rows }">
            <template v-if="labelsPrint" #add-on-button>
                <Button
                    type="tertiary"
                    :label="ctrans('Print barcodes (:count)', { count: selectedSupplierProductIds.length })"
                    :disabled="!selectedSupplierProductIds.length"
                    :tooltip="selectedSupplierProductIds.length ? undefined : ctrans('Tick the products to print their barcodes')"
                    @click="isLabelsDialogOpen = true"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-barcode-read" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </template>
            <template #cell(code)="{ item: supplier_product }">
                <Link :href="supplierProductHref(supplier_product)" class="primaryLink whitespace-nowrap">
                    {{ supplier_product['code'] }}
                </Link>
            </template>
            <template #cell(supplier_name)="{ item: supplier_product }">
                <Link v-if="supplier_product.supplier_slug" :href="route('grp.supply-chain.suppliers.show', [supplier_product.supplier_slug])" class="secondaryLink">
                    {{ supplier_product.supplier_name }}
                </Link>
            </template>
        </Table>
        <SupplierProductLabelsDialog
            v-if="labelsPrint"
            v-model:visible="isLabelsDialogOpen"
            :selectedIds="selectedSupplierProductIds"
            :planRoute="labelsPrint.plan_route"
            :pdfRoute="labelsPrint.pdf_route"
        />
    </div>
</template>


