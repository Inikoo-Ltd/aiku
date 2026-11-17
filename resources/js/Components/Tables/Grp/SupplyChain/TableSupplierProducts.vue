<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link, router} from '@inertiajs/vue3';
import { computed, reactive, ref } from "vue"
import axios from "axios"
import Select from "primevue/select"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faSeedling, faChair, faExclamationTriangle, faLaugh } from "@fal"
import { bucketQuery } from "@/Composables/bucketQuery"
import Table from '@/Components/Table/Table.vue';
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierProductLabelsDialog from "@/Components/SupplyChain/SupplierProductLabelsDialog.vue"
import { ctrans } from "@/Composables/useTrans"
import {SupplierProduct} from "@/types/supplier-product";
import { routeType } from "@/types/route"

library.add(faBarcodeRead, faSeedling, faChair, faExclamationTriangle, faLaugh)

type SupplierProductRow = Omit<SupplierProduct, "state"> & {
    state: { value: string, label: string, icon: object } | null
}

const props = defineProps<{
    data: object,
    tab?: string
    labelsPrint?: {
        plan_route: routeType
        pdf_route: routeType
    }
    stateOptions?: { value: string, label: string }[] | null
}>()

const selectedRows = ref<Record<string, boolean>>({})
const selectedSupplierProductIds = computed(() => Object.keys(selectedRows.value).filter((id) => selectedRows.value[id]).map(Number))
const isLabelsDialogOpen = ref(false)

const newStates = reactive<Record<number, string>>({})
const savingStateIds = ref<number[]>([])

const rowSelectPt = {
    label: { class: "!text-xs" },
    option: ({ context }: { context: { disabled: boolean } }) => ({
        class: context.disabled ? "!cursor-not-allowed !text-gray-400 !opacity-100" : "",
    }),
}

function rowStateOptions(supplierProduct: SupplierProductRow) {
    return (props.stateOptions ?? []).map((option) => ({ ...option, disabled: option.value === supplierProduct.state?.value }))
}

async function saveState(supplierProduct: SupplierProductRow) {
    const state = newStates[supplierProduct.id]
    if (!state || state === supplierProduct.state?.value) {
        return
    }

    savingStateIds.value.push(supplierProduct.id)
    try {
        await axios.patch(route("grp.models.supplier-product.update", [supplierProduct.id]), { state })
        delete newStates[supplierProduct.id]
        notify({
            title: ctrans("State updated"),
            text: ctrans(":code is now :state", { code: supplierProduct.code, state: props.stateOptions?.find((option) => option.value === state)?.label ?? state }),
            type: "success",
        })
        router.reload({ only: ["data"] })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("Failed to update the state"),
            type: "error",
        })
    } finally {
        savingStateIds.value = savingStateIds.value.filter((id) => id !== supplierProduct.id)
    }
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
            <template #cell(state)="{ item: supplier_product }">
                <div v-if="stateOptions?.length" class="flex items-center gap-1.5 whitespace-nowrap">
                    <Icon v-if="supplier_product.state" :data="supplier_product.state.icon" :title="supplier_product.state.label" />
                    <Select
                        v-model="newStates[supplier_product.id]"
                        :options="rowStateOptions(supplier_product)"
                        optionLabel="label"
                        optionValue="value"
                        optionDisabled="disabled"
                        size="small"
                        class="w-36 text-xs [&.p-focus]:!border-[var(--app-accent)]"
                        :pt="rowSelectPt"
                        :placeholder="supplier_product.state?.label ?? ctrans('Select new')"
                        :aria-label="ctrans('Select new state')"
                        :disabled="savingStateIds.includes(supplier_product.id)"
                    >
                        <template #option="{ option }">
                            <span class="flex w-full items-center justify-between gap-2 text-xs">
                                {{ option.label }}
                                <span v-if="option.disabled" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-400">{{ ctrans("current") }}</span>
                            </span>
                        </template>
                    </Select>
                    <Button
                        type="primary"
                        size="xs"
                        :label="ctrans('Set')"
                        :disabled="!newStates[supplier_product.id] || newStates[supplier_product.id] === supplier_product.state?.value"
                        :loading="savingStateIds.includes(supplier_product.id)"
                        @click="saveState(supplier_product)"
                    />
                </div>
                <span v-else-if="supplier_product.state" class="inline-flex items-center gap-x-1.5 whitespace-nowrap">
                    <Icon :data="supplier_product.state.icon" :title="supplier_product.state.label" />
                    {{ supplier_product.state.label }}
                </span>
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


