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
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faSeedling, faChair, faExclamationTriangle, faLaugh, faCheck, faTimes, faArrowRight } from "@fal"
import { bucketQuery } from "@/Composables/bucketQuery"
import Table from '@/Components/Table/Table.vue';
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierProductLabelsDialog from "@/Components/SupplyChain/SupplierProductLabelsDialog.vue"
import { ctrans } from "@/Composables/useTrans"
import {SupplierProduct} from "@/types/supplier-product";
import { routeType } from "@/types/route"

library.add(faBarcodeRead, faSeedling, faChair, faExclamationTriangle, faLaugh, faCheck, faTimes, faArrowRight)

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
}>()

const selectedRows = ref<Record<string, boolean>>({})
const selectedSupplierProductIds = computed(() => Object.keys(selectedRows.value).filter((id) => selectedRows.value[id]).map(Number))
const isLabelsDialogOpen = ref(false)

const newStates = reactive<Record<number, string>>({})
const savingStateIds = ref<number[]>([])
const stateErrors = reactive<Record<number, string>>({})

const DISCONTINUED_STATES = ["discontinuing", "discontinued"]
const isDiscontinuedState = (state: string | null | undefined) => DISCONTINUED_STATES.includes(state ?? "")

const reasonDialogProduct = ref<SupplierProductRow | null>(null)
const discontinuedReason = ref("")

const isBulkStateDialogOpen = ref(false)
const bulkState = ref<string | null>(null)
const bulkDiscontinuedReason = ref("")
const isSavingBulkState = ref(false)
const bulkStateError = ref<string | null>(null)
const bulkStateSuccess = ref<string | null>(null)

const bulkStatePreviewRows = computed(() => (props.data?.data ?? []).filter((supplierProduct) => selectedSupplierProductIds.value.includes(supplierProduct.id)))
const bulkStateHiddenCount = computed(() => selectedSupplierProductIds.value.length - bulkStatePreviewRows.value.length)
const bulkStateNewOption = computed(() => props.stateOptions?.find((option) => option.value === bulkState.value) ?? null)
const bulkStateChangingCount = computed(() => bulkStatePreviewRows.value.filter((supplierProduct) => supplierProduct.state?.value !== bulkState.value).length)

const bulkStateEffects = computed<string[]>(() => {
    const effectsByState: Record<string, string[]> = {
        in_process: [ctrans("Cannot be added to purchase orders")],
        active: [ctrans("Can be added to purchase orders")],
        discontinuing: [ctrans("Cannot be added to new purchase orders")],
        discontinued: [ctrans("Cannot be added to purchase orders"), ctrans("Discontinued date is recorded")],
    }

    if (!bulkState.value) {
        return []
    }

    return [
        ...(effectsByState[bulkState.value] ?? []),
        ctrans("Applies in every organisation"),
        ctrans("Stock and website do not change: products still sell while there is stock"),
    ]
})

function openBulkStateDialog() {
    bulkState.value = null
    bulkDiscontinuedReason.value = ""
    bulkStateError.value = null
    bulkStateSuccess.value = null
    isBulkStateDialogOpen.value = true
}

const rowSelectPt = {
    label: { class: "!text-xs" },
    option: ({ context }: { context: { disabled: boolean } }) => ({
        class: context.disabled ? "!cursor-not-allowed !text-gray-400 !opacity-100" : "",
    }),
}

function rowStateOptions(supplierProduct: SupplierProductRow) {
    return (props.stateOptions ?? []).map((option) => ({ ...option, disabled: option.value === supplierProduct.state?.value }))
}

function requestState(supplierProduct: SupplierProductRow) {
    if (isDiscontinuedState(newStates[supplierProduct.id])) {
        discontinuedReason.value = ""
        reasonDialogProduct.value = supplierProduct
        return
    }

    saveState(supplierProduct)
}

function confirmDiscontinuedReason() {
    const supplierProduct = reasonDialogProduct.value
    reasonDialogProduct.value = null
    if (supplierProduct) {
        saveState(supplierProduct, discontinuedReason.value)
    }
}

async function saveState(supplierProduct: SupplierProductRow, reason?: string) {
    const state = newStates[supplierProduct.id]
    if (!state || state === supplierProduct.state?.value) {
        return
    }

    savingStateIds.value.push(supplierProduct.id)
    delete stateErrors[supplierProduct.id]
    try {
        await axios.patch(route("grp.models.supplier-product.update", [supplierProduct.id]), {
            state,
            ...(isDiscontinuedState(state) ? { discontinued_reason: reason?.trim() || null } : {}),
        })
        delete newStates[supplierProduct.id]
        router.reload({ only: ["data"] })
    } catch (error: any) {
        stateErrors[supplierProduct.id] = error?.response?.data?.message ?? ctrans("Failed to update the state")
    } finally {
        savingStateIds.value = savingStateIds.value.filter((id) => id !== supplierProduct.id)
    }
}

async function saveBulkState() {
    if (!bulkState.value || !selectedSupplierProductIds.value.length) {
        bulkStateError.value = ctrans("Choose the new state first")
        return
    }

    isSavingBulkState.value = true
    bulkStateError.value = null
    try {
        const response = await axios.patch(route("grp.models.supplier-products.state.update"), {
            supplier_products: selectedSupplierProductIds.value,
            state: bulkState.value,
            ...(isDiscontinuedState(bulkState.value) ? { discontinued_reason: bulkDiscontinuedReason.value.trim() || null } : {}),
        })
        bulkStateSuccess.value = ctrans(":count products set to :state", {
            count: response.data.updated,
            state: bulkStateNewOption.value?.label ?? bulkState.value,
        })
        isBulkStateDialogOpen.value = false
        router.reload({ only: ["data"] })
    } catch (error: any) {
        bulkStateError.value = error?.response?.data?.message ?? ctrans("Failed to update the state")
    } finally {
        isSavingBulkState.value = false
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
                <div v-if="stateOptions?.length" class="flex items-center gap-1.5 whitespace-nowrap">
                    <Button
                        type="tertiary"
                        :label="ctrans('Set state (:count)', { count: selectedSupplierProductIds.length })"
                        :disabled="!selectedSupplierProductIds.length"
                        :tooltip="selectedSupplierProductIds.length ? undefined : ctrans('Tick the products to change their state')"
                        @click="openBulkStateDialog"
                    >
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <span v-if="bulkStateSuccess" class="text-xs text-green-600">{{ bulkStateSuccess }}</span>
                </div>
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
                            @click="requestState(supplier_product)"
                        />
                        <span v-if="stateErrors[supplier_product.id]" class="text-xs text-red-500">{{ stateErrors[supplier_product.id] }}</span>
                    </div>
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
        <Dialog
            v-if="stateOptions?.length"
            v-model:visible="isBulkStateDialogOpen"
            modal
            :header="ctrans('Set state of :count products', { count: selectedSupplierProductIds.length })"
            :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }"
            :draggable="false"
        >
            <div class="space-y-4 text-sm">
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-gray-600">{{ ctrans("New state") }} <span class="text-red-500">*</span></span>
                    <Select
                        v-model="bulkState"
                        :options="stateOptions"
                        optionLabel="label"
                        optionValue="value"
                        size="small"
                        class="w-full text-xs [&.p-focus]:!border-[var(--app-accent)]"
                        :pt="rowSelectPt"
                        :placeholder="ctrans('Choose the new state')"
                        :disabled="isSavingBulkState"
                        @change="bulkStateError = null"
                    />
                </label>

                <label v-if="isDiscontinuedState(bulkState)" class="block space-y-1">
                    <span class="text-xs font-semibold text-gray-600">{{ ctrans("Reason") }}</span>
                    <Textarea
                        v-model="bulkDiscontinuedReason"
                        rows="2"
                        maxlength="500"
                        autoResize
                        class="w-full text-xs"
                        :placeholder="ctrans('Optional, shown to the supplier on their portal')"
                        :disabled="isSavingBulkState"
                    />
                </label>

                <div v-if="bulkStateEffects.length" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <div class="mb-1 flex items-center gap-1 font-semibold">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                        {{ ctrans("What will happen:") }}
                    </div>
                    <ul class="list-disc space-y-0.5 pl-4">
                        <li v-for="effect in bulkStateEffects" :key="effect">{{ effect }}</li>
                    </ul>
                </div>

                <div class="max-h-[50vh] overflow-y-auto rounded border border-gray-200">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 whitespace-nowrap bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("Code") }}</th>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("Name") }}</th>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("Current") }}</th>
                                <th class="px-3 py-2 font-semibold">{{ ctrans("New") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="supplierProduct in bulkStatePreviewRows"
                                :key="supplierProduct.id"
                                :class="bulkState && supplierProduct.state?.value === bulkState ? 'text-gray-400' : ''"
                            >
                                <td class="whitespace-nowrap px-3 py-1.5 font-medium">{{ supplierProduct.code }}</td>
                                <td class="px-3 py-1.5">{{ supplierProduct.name }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5">
                                    <span v-if="supplierProduct.state" class="inline-flex items-center gap-1">
                                        <Icon :data="supplierProduct.state.icon" />
                                        {{ supplierProduct.state.label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-1.5">
                                    <span v-if="!bulkStateNewOption" class="text-gray-400">-</span>
                                    <span v-else-if="supplierProduct.state?.value === bulkState" class="italic">{{ ctrans("no change") }}</span>
                                    <span v-else class="inline-flex items-center gap-1 font-semibold text-[var(--app-accent)]">
                                        <FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" />
                                        {{ bulkStateNewOption.label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="bulkStateHiddenCount > 0" class="text-xs text-gray-500">
                    {{ ctrans(":count more ticked products on other pages are not shown here", { count: bulkStateHiddenCount }) }}
                </p>
                <p v-if="bulkStateNewOption" class="text-xs text-gray-600">
                    {{ ctrans(":changing of :total products will change to :state", { changing: bulkStateChangingCount + Math.max(bulkStateHiddenCount, 0), total: selectedSupplierProductIds.length, state: bulkStateNewOption.label }) }}
                </p>
                <p v-if="bulkStateError" class="text-xs text-red-500">{{ bulkStateError }}</p>
            </div>

            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isBulkStateDialogOpen = false">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button
                        type="primary"
                        full
                        :label="ctrans('Set state')"
                        :loading="isSavingBulkState"
                        :disabled="!bulkState || (bulkStateChangingCount === 0 && bulkStateHiddenCount <= 0)"
                        @click="saveBulkState"
                    >
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>
        </Dialog>
        <Dialog
            :visible="Boolean(reasonDialogProduct)"
            modal
            :header="ctrans('Set :code to :state', { code: reasonDialogProduct?.code ?? '', state: stateOptions?.find((option) => option.value === newStates[reasonDialogProduct?.id ?? 0])?.label ?? '' })"
            :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
            :draggable="false"
            @update:visible="(visible) => { if (!visible) reasonDialogProduct = null }"
        >
            <label class="block space-y-1 text-sm">
                <span class="text-xs font-semibold text-gray-600">{{ ctrans("Reason") }}</span>
                <Textarea
                    v-model="discontinuedReason"
                    rows="3"
                    maxlength="500"
                    autoResize
                    autofocus
                    class="w-full text-xs"
                    :placeholder="ctrans('Optional, shown to the supplier on their portal')"
                />
            </label>

            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="reasonDialogProduct = null">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Set state')" @click="confirmDiscontinuedReason">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>
        </Dialog>
        <SupplierProductLabelsDialog
            v-if="labelsPrint"
            v-model:visible="isLabelsDialogOpen"
            :selectedIds="selectedSupplierProductIds"
            :planRoute="labelsPrint.plan_route"
            :pdfRoute="labelsPrint.pdf_route"
        />
    </div>
</template>


