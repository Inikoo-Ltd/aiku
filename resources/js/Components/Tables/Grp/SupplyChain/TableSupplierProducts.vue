<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link, router} from '@inertiajs/vue3';
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faSeedling, faChair, faExclamationTriangle, faLaugh, faCheck } from "@fal"
import { bucketQuery } from "@/Composables/bucketQuery"
import Table from '@/Components/Table/Table.vue';
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierProductLabelsDialog from "@/Components/SupplyChain/SupplierProductLabelsDialog.vue"
import SupplierProductsCopyActions from "@/Components/Procurement/SupplierProductsCopyActions.vue"
import SupplierProductStateSelect from "@/Components/SupplyChain/SupplierProductStateSelect.vue"
import SupplierProductsBulkStateButton from "@/Components/SupplyChain/SupplierProductsBulkStateButton.vue"
import { CopyProducts, CopyTarget } from "@/types/supplier-products-copy"
import { ctrans } from "@/Composables/useTrans"
import {SupplierProduct} from "@/types/supplier-product";
import { routeType } from "@/types/route"

library.add(faBarcodeRead, faSeedling, faChair, faExclamationTriangle, faLaugh, faCheck)

type SupplierProductRow = Omit<SupplierProduct, "state"> & {
    state: { value: string, label: string, icon: object } | null
    goods_states?: { stock_code: string, state: string }[]
}

const goodsStateLabels: Record<string, string> = {
    active: ctrans("Active"),
    suspended: ctrans("Hold"),
    discontinuing: ctrans("Discontinued"),
    discontinued: ctrans("Retired"),
}

function mismatchedGoodsStates(supplierProduct: SupplierProductRow) {
    return (supplierProduct.goods_states ?? []).filter((goodsState) => goodsState.state !== supplierProduct.state?.value)
}

function goodsStateMismatchTooltip(supplierProduct: SupplierProductRow) {
    return mismatchedGoodsStates(supplierProduct)
        .map((goodsState) => ctrans("Goods shows :stock as :state", { stock: goodsState.stock_code, state: goodsStateLabels[goodsState.state] ?? goodsState.state }))
        .join("\n")
}

const props = defineProps<{
    data: { data: SupplierProductRow[] },
    tab?: string
    labelsPrint?: {
        plan_route: routeType
        pdf_route: routeType
    }
    stateOptions?: { value: string, label: string }[] | null
    copyProducts?: CopyProducts | null
    copyTargets?: CopyTarget[]
}>()

const _table = ref<InstanceType<typeof Table> | null>(null)

const selectedRows = ref<Record<string, boolean>>({})
const selectedSupplierProductIds = computed(() => Object.keys(selectedRows.value).filter((id) => selectedRows.value[id]).map(Number))
const isLabelsDialogOpen = ref(false)

function reloadData() {
    router.reload({ only: ["data"] })
}

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
        <Table ref="_table" :resource="data" :name="tab" class="mt-5" :isCheckBox="Boolean(labelsPrint || copyProducts)" @onSelectRow="(rows) => selectedRows = { ...rows }">
            <template v-if="labelsPrint || copyProducts" #add-on-button>
                <SupplierProductsCopyActions
                    v-if="copyProducts"
                    :copyProducts="copyProducts"
                    :copyTargets="copyTargets"
                    :selectedIds="selectedSupplierProductIds"
                    idsParameter="supplier_products"
                    @copied="_table?.clearSelection()"
                />
                <Button
                    v-if="labelsPrint"
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
                <SupplierProductsBulkStateButton
                    v-if="stateOptions?.length"
                    :selectedIds="selectedSupplierProductIds"
                    idsParameter="supplier_products"
                    :rows="data?.data ?? []"
                    :stateOptions="stateOptions"
                    @saved="reloadData"
                />
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
            <template #cell(state)="{ item: supplier_product }">
                <div class="flex items-center gap-1.5">
                    <SupplierProductStateSelect
                        v-if="stateOptions?.length"
                        :supplierProductId="supplier_product.id"
                        :code="supplier_product.code"
                        :state="supplier_product.state"
                        :stateOptions="stateOptions"
                        @saved="reloadData"
                    />
                    <span v-else-if="supplier_product.state" class="inline-flex items-center gap-x-1.5 whitespace-nowrap">
                        <Icon :data="supplier_product.state.icon" :title="supplier_product.state.label" />
                        {{ supplier_product.state.label }}
                    </span>
                    <Link
                        v-if="mismatchedGoodsStates(supplier_product).length"
                        :href="route('grp.goods.dashboard', { search: mismatchedGoodsStates(supplier_product)[0].stock_code })"
                        v-tooltip="goodsStateMismatchTooltip(supplier_product)"
                        class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                        :aria-label="goodsStateMismatchTooltip(supplier_product)"
                    >
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                        {{ ctrans("Goods: :state", { state: goodsStateLabels[mismatchedGoodsStates(supplier_product)[0].state] ?? mismatchedGoodsStates(supplier_product)[0].state }) }}
                    </Link>
                </div>
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


