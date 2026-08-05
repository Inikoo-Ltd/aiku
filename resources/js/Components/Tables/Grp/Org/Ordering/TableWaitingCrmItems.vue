<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 13 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStickyNote, faExchangeAlt, faSearch, faSave, faTimes, faTruck, faShoppingCart, faHourglassStart } from "@fal"
import { faFragile } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import FractionDisplayFE from "@/Components/DataDisplay/FractionDisplayFE.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { computed, inject, reactive, ref, watch } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import axios from "axios"
import { debounce } from "lodash-es"
import { InputNumber } from "primevue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import Toggle from "@/Components/Pure/Toggle.vue"
import Image from "@common/Components/Image.vue"
library.add(faStickyNote, faExchangeAlt, faSearch, faSave, faTimes, faTruck, faShoppingCart, faHourglassStart, faFragile)

defineProps<{
    data: TableTS
    tab?: string
}>()

const layout = inject('layout', layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const orderRoute = (item: Record<string, any>): string | null => {
    if (!item['order_slug'] || !item['shop_slug'] || !item['organisation_slug']) {
        return null
    }
    try {
        return route('grp.org.shops.show.ordering.orders.show', [
            item['organisation_slug'],
            item['shop_slug'],
            item['order_slug'],
        ])
    } catch {
        return null
    }
}

const setAsNotPickRoute = (item: Record<string, any>): string | null => {
    if (!item['id']) {
        return null
    }

    try {
        return route('grp.models.delivery_note_item.not_picking_from_waiting_crm.store', {
            deliveryNoteItem: item['id']
        })
    } catch {
        return null
    }
}

const replaceProductRoute = (item: Record<string, any>): string | null => {
    if (!item['id']) {
        return null
    }

    try {
        return route('grp.models.delivery_note_item.waiting_items_replace_product', [
            item['id'],
        ])
    } catch {
        return null
    }
}

const productImage = (product: Record<string, any>) => product?.web_images?.main?.thumbnail ?? product?.web_images?.main?.original ?? null

// Section: Modal Replace Product
const isOpenModalReplaceProduct = ref(false)
const selectedItem = ref<Record<string, any> | null>(null)
const modalProducts = ref<any[]>([])
const modalSearchQuery = ref('')
const isModalProductsLoading = ref(false)
const productQuantities = reactive<Record<number, { quantity: number; code: string; name: string; stock: number; units: number; image: any }>>({})
const isSubmittingReplaceProduct = ref(false)

// Packs are stored as a decimal, the warehouse reads them as "2/16" of an outer.
const waitingQuantityLabel = (item: Record<string, any> | null): string => {
    const packedIn = Number(item?.packed_in) || 1
    const quantity = Number(item?.quantity_waiting_crm) || 0
    if (packedIn <= 1) {
        return String(quantity)
    }

    return `${Math.round(quantity * packedIn)}/${packedIn}`
}

// Mirrors the "_ds" fractional shape the backend sends: whole packs read as 32/16, a cut as 2/16.
const toFractionData = (quantity: number, packedIn: number) => {
    const units = Math.round(quantity * packedIn)
    if (packedIn <= 1) {
        return [units, [0, 1]]
    }
    return [0, [units, packedIn]]
}

/*
 * Section: cut view. Both sides of the swap are packed in outers and both can move a cut at a
 * time: 4/16 of jbb-02 goes out and 4/16 of jbb-04 comes in. Quantities are held in packs, the
 * decimal the backend stores, and cut view only changes how they are typed and read: single
 * items over the pack size instead of whole packs.
 */
const quantityToReplace = ref(0)
const isCutViewSelected = ref(false)

const replacePackedIn = computed(() => Number(selectedItem.value?.packed_in) || 1)
const waitingQuantity = computed(() => Number(selectedItem.value?.quantity_waiting_crm) || 0)

/*
 * An item already sitting on a cut, 14/16 left after an earlier swap, cannot be read in whole
 * packs at all, so cut view is locked on for it rather than merely starting on.
 */
const isCutViewLocked = computed(() => !Number.isInteger(waitingQuantity.value))

const isCutViewReplace = computed(() => isCutViewLocked.value || isCutViewSelected.value)

const hasCuttableProduct = computed(() => modalProducts.value.some(product => Number(product.units) > 1))
const canToggleCutView = computed(() => isCutViewLocked.value || replacePackedIn.value > 1 || hasCuttableProduct.value)

const packsToUnits = (quantity: number, packedIn: number): number => Math.round(quantity * packedIn)

// The server only accepts a whole count of single items, anything else stays expressed in packs.
const toUnitsPayload = (quantity: number, packedIn: number): number | null => {
    const units = quantity * packedIn
    return Number.isInteger(units) ? units : null
}

const unitsToPacks = (units: number, packedIn: number): number => Math.round((units / packedIn) * 1e6) / 1e6

const replaceQuantityInput = computed(() => isCutViewReplace.value
    ? packsToUnits(quantityToReplace.value, replacePackedIn.value)
    : quantityToReplace.value
)

const maxQuantityToReplace = computed(() => isCutViewReplace.value
    ? packsToUnits(waitingQuantity.value, replacePackedIn.value)
    : waitingQuantity.value
)

const quantityRemainingAfterReplace = computed(() => {
    const remaining = waitingQuantity.value - quantityToReplace.value
    return remaining < 0 ? 0 : Math.round(remaining * 1e6) / 1e6
})

const onUpdateQuantityToReplace = (value: number | null) => {
    const raw = Number(value) || 0
    const quantity = isCutViewReplace.value ? unitsToPacks(raw, replacePackedIn.value) : raw
    quantityToReplace.value = Math.min(Math.max(quantity, 0), waitingQuantity.value)
}

// Leaving cut view rounds each cut up to the pack it sits in, the only amount that view can express.
const toggleCutViewReplace = (isCutView: boolean) => {
    if (isCutViewLocked.value) {
        return
    }

    isCutViewSelected.value = isCutView
    if (isCutView) {
        return
    }

    quantityToReplace.value = Math.min(Math.ceil(quantityToReplace.value), waitingQuantity.value)
    Object.values(productQuantities).forEach(product => {
        product.quantity = Math.ceil(product.quantity)
    })
}

// Section: replacement product quantities, typed in the same view as the item being replaced
const isProductCutView = (product: Record<string, any>): boolean => isCutViewReplace.value && Number(product?.units) > 1

const productQuantityInput = (product: Record<string, any>): number => {
    const quantity = productQuantities[product.id]?.quantity ?? 0
    return isProductCutView(product) ? packsToUnits(quantity, Number(product.units)) : quantity
}

const productStockInView = (product: Record<string, any>): number => {
    const stock = Number(product?.stock) || 0
    return isProductCutView(product) ? packsToUnits(stock, Number(product.units)) : stock
}

/*
 * Stock reads as a mixed number rather than a raw count of items: 128 of a 16-pack is 8 packs, 130
 * is 8 packs and a cut of 2, which is what the picker actually finds on the shelf.
 */
const toMixedFractionData = (quantity: number, packedIn: number) => {
    const units = Math.round(quantity * packedIn)
    if (packedIn <= 1) {
        return [units, [0, 1]]
    }
    return [Math.floor(units / packedIn), [units % packedIn, packedIn]]
}

/*
 * Stock is shown but never caps the input: a cheaper product can answer the one it replaces by
 * going out in a larger quantity, and anything beyond what is on the shelf simply joins the
 * delivery note waiting for the warehouse, the same as the rest of the replacement.
 */
const onUpdateProductQuantity = (product: Record<string, any>, value: number | null) => {
    const entry = productQuantities[product.id]
    if (!entry) {
        return
    }

    const raw = Number(value) || 0
    const quantity = isProductCutView(product) ? unitsToPacks(raw, Number(product.units)) : raw
    entry.quantity = Math.max(quantity, 0)
}

const isProductOverStock = (product: Record<string, any>): boolean => (
    (productQuantities[product.id]?.quantity ?? 0) > (Number(product?.stock) || 0)
)

// Outside cut view the quantity was typed in whole packs, so it reads as a plain count.
const productQuantityLabel = (quantity: number, units: number): string => (
    isCutViewReplace.value && units > 1 ? `${packsToUnits(quantity, units)}/${units}` : String(quantity)
)

/*
 * The same quantity as the two halves of a fraction, a denominator of 1 leaving it to read as a
 * plain count the way the label does outside cut view.
 */
const productQuantityFraction = (quantity: number, units: number): { numerator: number; denominator: number } => (
    isCutViewReplace.value && units > 1
        ? { numerator: packsToUnits(quantity, units), denominator: units }
        : { numerator: quantity, denominator: 1 }
)

/*
 * Org stocks carry no image of their own, it lives on the trade unit behind them, so the picture
 * of the item being replaced is fetched per delivery note item the way the delivery note table does.
 */
const orgStockImages = reactive<Record<number, any>>({})

const orgStockImage = (item: Record<string, any> | null) => orgStockImages[item?.id] ?? null

const fetchOrgStockImage = async (deliveryNoteItemId: number) => {
    if (!deliveryNoteItemId || deliveryNoteItemId in orgStockImages) {
        return
    }

    try {
        const response = await axios.get(route('grp.json.fetch_single_delivery_note_item.image', {
            deliveryNoteItem: deliveryNoteItemId,
        }))
        orgStockImages[deliveryNoteItemId] = response.data ?? null
    } catch (error) {
        console.error('Error fetching org stock image:', error)
    }
}

const openReplaceProductModal = (item: Record<string, any>) => {
    selectedItem.value = item
    modalSearchQuery.value = ''
    Object.keys(productQuantities).forEach(key => delete productQuantities[Number(key)])
    quantityToReplace.value = Number(item.quantity_waiting_crm) || 0
    isCutViewSelected.value = false
    isOpenModalReplaceProduct.value = true
    fetchOrgStockImage(item.id)
    fetchModalProducts()
}

const closeReplaceProductModal = () => {
    isOpenModalReplaceProduct.value = false
    // selectedItem.value = null
}

const fetchModalProducts = debounce(async () => {
    if (!selectedItem.value) return
    isModalProductsLoading.value = true
    try {
        const params: Record<string, any> = { shop: selectedItem.value.shop_slug }
        if (modalSearchQuery.value.trim()) {
            params['filter[global]'] = modalSearchQuery.value.trim()
        }
        const url = route('grp.json.shop.products', params)
        const response = await axios.get(url)
        const products = response.data.data ?? []
        products.forEach((product: any) => {
            if (!(product.id in productQuantities)) {
                productQuantities[product.id] = {
                    quantity: 0,
                    code: product.code,
                    name: product.name,
                    stock: product.stock ?? 0,
                    units: Number(product.units) || 1,
                    image: productImage(product),
                }
            }
        })
        modalProducts.value = products
    } catch (error) {
        console.error('Error fetching products:', error)
    } finally {
        isModalProductsLoading.value = false
    }
}, 300)

watch(modalSearchQuery, () => {
    fetchModalProducts()
})


const selectedProducts = computed(() =>
    Object.entries(productQuantities)
        .filter(([, p]) => p.quantity > 0)
        .map(([id, p]) => ({ id: Number(id), code: p.code, name: p.name, quantity: p.quantity, units: p.units, image: p.image }))
)

const unselectProduct = (id: number) => {
    if (productQuantities[id]) {
        productQuantities[id].quantity = 0
    }
}

interface SuccessContext {
    replacedItem: Record<string, any>
    replacedQuantity: number
    remainingQuantity: number
    newProducts: { id: number; code: string; name: string; numerator: number; denominator: number; image: any }[]
}

const isModalConfirmationSuccess = ref(false)
const successContext = ref<SuccessContext | null>(null)

const submitReplaceProduct = () => {
    if (!selectedItem.value) return
    if (selectedProducts.value.length === 0) return
    if (quantityToReplace.value <= 0) return
    const submitRoute = replaceProductRoute(selectedItem.value)
    if (!submitRoute) return
    isSubmittingReplaceProduct.value = true

    const snapshotSelectedProducts = selectedProducts.value.map(({ id, code, name, quantity, units, image }) => ({
        id,
        code,
        name,
        image,
        ...productQuantityFraction(quantity, units),
    }))
    const snapshotQuantityToReplace = quantityToReplace.value
    const snapshotQuantityRemaining = quantityRemainingAfterReplace.value

    /*
     * A cut is sent as a whole count of single items so the server derives the pack fraction from
     * its own pack size, the same contract UpdateTransaction uses for units_ordered.
     */
    const replacedUnits = isCutViewReplace.value
        ? toUnitsPayload(quantityToReplace.value, replacePackedIn.value)
        : null

    const payload = {
        ...(replacedUnits === null ? { quantity: quantityToReplace.value } : { units: replacedUnits }),
        products: selectedProducts.value.map(({ id, quantity, units }) => {
            const orderedUnits = isCutViewReplace.value && units > 1 ? toUnitsPayload(quantity, units) : null

            return orderedUnits === null ? { id, quantity } : { id, units: orderedUnits }
        }),
    }

    router.post(submitRoute, payload, {
        preserveScroll: true,
        onSuccess: () => {
            isOpenModalReplaceProduct.value = false
            successContext.value = {
                replacedItem: { ...selectedItem.value },
                replacedQuantity: snapshotQuantityToReplace,
                remainingQuantity: snapshotQuantityRemaining,
                newProducts: snapshotSelectedProducts,
            }
            notify({
                title: ctrans("Success!"),
                text: ctrans('Items :itemOld has been replaced', { itemOld: selectedItem.value?.org_stock_code ?? ''}),
                type: "success",
            })
            isModalConfirmationSuccess.value = true
        },
        onFinish: () => { isSubmittingReplaceProduct.value = false },
    })
}

// Section: Modal Send Back to Waiting Warehouse
const isOpenModalSendBackWarehouse = ref(false)
const selectedItemSendBack = ref<Record<string, any> | null>(null)
const isSubmittingSendBack = ref(false)

const openSendBackWarehouseModal = (item: Record<string, any>) => {
    selectedItemSendBack.value = item
    isOpenModalSendBackWarehouse.value = true
    fetchOrgStockImage(item.id)
}

const submitSendBackWarehouse = () => {
    if (!selectedItemSendBack.value) return
    isSubmittingSendBack.value = true
    router.post(
        route('grp.models.delivery_note_item.send_back_waiting_warehouse', { deliveryNoteItem: selectedItemSendBack.value.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isOpenModalSendBackWarehouse.value = false
                notify({
                    title: ctrans('Success!'),
                    text: ctrans('Item :item has been sent back to waiting warehouse', { item: selectedItemSendBack.value?.org_stock_code ?? '' }),
                    type: 'success',
                })
            },
            onFinish: () => {
                isSubmittingSendBack.value = false
            },
        }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" rowAlignTop>
        <template #cell(delivery_note_reference)="{ item }">
            <div class="flex gap-2 flex-wrap items-center">
                <FontAwesomeIcon icon="fal fa-truck" class="opacity-60" fixed-width aria-hidden="true" />
                <span class="font-semibold">{{ item.delivery_note_reference }}</span>
                <FontAwesomeIcon
                    v-if="item.delivery_note_is_premium_dispatch"
                    v-tooltip="ctrans('Priority dispatch')"
                    icon="fas fa-star"
                    class="text-yellow-500"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-if="item.order_reference" class="mt-1 text-xs text-gray-500">
                <Link v-if="orderRoute(item)" :href="orderRoute(item)!" class="primaryLink">
                    <FontAwesomeIcon icon="fal fa-shopping-cart" class="opacity-75 mr-1" fixed-width aria-hidden="true" />
                    {{ item.order_reference }}
                </Link>
                <span v-else>{{ item.order_reference }}</span>
            </div>

            <div
                v-if="item.delivery_note_state === 'handling'"
                class="mt-1 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-700 ring-1 ring-inset ring-amber-400"
            >
                {{ ctrans('Still on picking') }}
            </div>
        </template>

        <template #cell(items)="{ item: deliveryNoteRow }">
            <div v-if="deliveryNoteRow.items?.length" class="divide-y divide-gray-100">
                <div
                    v-for="subItem in deliveryNoteRow.items"
                    :key="subItem.id"
                    class="py-3 first:pt-1 flex flex-wrap items-start gap-x-6 gap-y-2"
                >
                    <!-- Item info -->
                    <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                        <div>
                            <span class="font-semibold">{{ subItem.org_stock_code }}</span>
                            <span class="ml-1.5 text-gray-600 italic opacity-80">{{ subItem.org_stock_name }}</span>
                            <span class="ml-1.5 italic opacity-80">{{ subItem.packed_in_message }}</span>
                        </div>
                        <div class="tabular-nums text-sm text-gray-500 flex items-center gap-x-1">
                            <FractionDisplay
                                v-if="subItem.quantity_waiting_crm_fractional_ds"
                                :fractionData="subItem.quantity_waiting_crm_fractional_ds"
                            />
                            <template v-else>{{ Number(subItem.quantity_waiting_crm) }}</template>
                            {{ ctrans("items") }}
                        </div>
                        <div v-if="subItem.notes" class="text-left border border-gray-300 bg-gray-100 px-2 py-1 rounded text-xs w-fit">
                            <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                            {{ subItem.notes }}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 shrink-0 flex-wrap">
                        <ButtonWithLink
                            v-tooltip="ctrans(':itemNotPick items will not picked, and will not billed to customer', { itemNotPick: waitingQuantityLabel(subItem) })"
                            :url="setAsNotPickRoute(subItem)"
                            method="post"
                            :label="ctrans(`Don't pick :itemNotPick items`, { itemNotPick: waitingQuantityLabel(subItem) })"
                            type="negative"
                            icon="fas fa-skull"
                            size="xs"
                        />

                        <!-- Button: Send back to waiting warehouse -->
                        <Button
                            :label="ctrans('Send back to waiting warehouse')"
                            size="xs"
                            type="tertiary"
                            icon="fal fa-hourglass-start"
                            @click="openSendBackWarehouseModal({ ...subItem })"
                        />

                        <!-- Refresh Faire Data -->
                        <ButtonWithLink
                            v-if="deliveryNoteRow.shop_type == 'external' && deliveryNoteRow.shop_engine === 'faire'"
                            :label="ctrans('Refresh Faire data')"
                            size="xs"
                            type="tertiary"
                            icon="fal fa-sync-alt"
                            :routeTarget="{
                                name: 'grp.models.order.update_faire',
                                parameters: { order: deliveryNoteRow.order_id },
                                method: 'post'
                            }"
                        />

                        <!-- Replace Product -->
                        <Button
                            v-else-if="replaceProductRoute(subItem)"
                            :label="ctrans('Replace :itemNotPick items', { itemNotPick: waitingQuantityLabel(subItem) })"
                            size="xs"
                            type="positive"
                            icon="fal fa-exchange-alt"
                            @click="openReplaceProductModal({
                                ...subItem,
                                shop_slug: subItem.shop_slug ?? deliveryNoteRow.shop_slug,
                                shop_type: deliveryNoteRow.shop_type,
                                shop_engine: deliveryNoteRow.shop_engine,
                                order_id: deliveryNoteRow.order_id,
                                order_slug: deliveryNoteRow.order_slug,
                                order_reference: deliveryNoteRow.order_reference,
                                organisation_slug: deliveryNoteRow.organisation_slug,
                            })"
                        />
                    </div>
                </div>
            </div>
            <div v-else class="text-gray-400 text-sm italic py-2">
                {{ ctrans('No items') }}
            </div>
        </template>
    </Table>

    <!-- Modal: Replace product -->
    <Modal :isOpen="isOpenModalReplaceProduct" width="w-full max-w-3xl" @onClose="closeReplaceProductModal" :closeButton="true">
        <div class="flex flex-col gap-4">
            <h2 class="text-xl font-semibold text-center">{{ ctrans('Replace Product') }}</h2>

            <!-- Section: product to replace -->
            <div class="">
                <div>
                    {{ ctrans("Product to replace") }}:
                </div>
                <div class="bg-amber-50 rounded px-4 py-2 text-sm text-amber-700 border border-amber-400">
                    <div class="flex justify-between items-start gap-4">
                        <div class="flex items-start gap-x-3">
                            <Image
                                v-if="orgStockImage(selectedItem)"
                                :src="orgStockImage(selectedItem)"
                                :alt="selectedItem?.org_stock_name"
                                class="h-10 w-10 shrink-0 rounded object-cover bg-white"
                            />
                            <div>
                                <span class="font-semibold">{{ selectedItem?.org_stock_code }}</span>
                                <span class="ml-1.5 opacity-80">{{ selectedItem?.org_stock_name }}</span>
                                <span class="ml-1.5 italic opacity-80">{{ selectedItem?.packed_in_message }}</span>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="tabular-nums font-semibold flex justify-end">
                                <FractionDisplay
                                    v-if="selectedItem?.quantity_waiting_crm_fractional_ds"
                                    :fractionData="selectedItem.quantity_waiting_crm_fractional_ds"
                                />
                                <template v-else>{{ Number(selectedItem?.quantity_waiting_crm) }}</template>
                                <span class="ml-1">{{ ctrans("items") }}</span>
                            </div>
                            <div v-if="selectedItem?.net_amount" class="tabular-nums text-xs opacity-70 mt-0.5">
                                {{ locale.currencyFormat(selectedItem?.currency_code, selectedItem?.net_amount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: how much of the waiting quantity to replace -->
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        {{ ctrans("Quantity to replace") }}:
                    </div>

                    <!-- Toggle: count single items on both sides of the swap instead of whole packs -->
                    <div
                        v-if="canToggleCutView"
                        v-tooltip="isCutViewLocked
                            ? ctrans('This item is already on a cut, it cannot be counted in whole packs')
                            : ctrans('Cut view: swap part of a pack, for example 4 of a 16 pack for 4 of another 16 pack')"
                        class="flex gap-x-2"
                    >
                        <Toggle
                            :modelValue="isCutViewReplace"
                            :disabled="isCutViewLocked"
                            @update:modelValue="toggleCutViewReplace"
                        />
                        <span
                            class="flex items-center gap-x-1.5 text-sm"
                            :class="isCutViewReplace ? 'text-orange-500 opacity-100' : ''"
                        >
                            <FontAwesomeIcon icon="fas fa-fragile" class="text-lg" fixed-width aria-hidden="true" />
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <InputNumber
                        :modelValue="replaceQuantityInput"
                        @update:model-value="onUpdateQuantityToReplace"
                        @input="(e) => onUpdateQuantityToReplace(Number(e.value))"
                        :min="0"
                        :max="maxQuantityToReplace"
                        :suffix="isCutViewReplace && replacePackedIn > 1 ? `/${replacePackedIn}` : undefined"
                        :key="String(isCutViewReplace) + selectedItem?.id"
                        inputClass="w-28"
                        showButtons
                    />

                    <span class="text-sm text-gray-500">
                        {{ isCutViewReplace ? ctrans('of :max items', { max: String(maxQuantityToReplace) }) : ctrans('of :max packs', { max: String(maxQuantityToReplace) }) }}
                    </span>
                </div>

                <div vxif="quantityRemainingAfterReplace > 0" class="mt-1 flex items-center gap-x-1 text-xs text-amber-700"
                    :class="quantityRemainingAfterReplace > 0 ? 'opacity-100' : 'opacity-0'"
                >
                    <FontAwesomeIcon icon="fal fa-hourglass-start" fixed-width aria-hidden="true" />
                    {{ ctrans('Remaining') }}:
                    <FractionDisplay :fractionData="toFractionData(quantityRemainingAfterReplace, replacePackedIn)" />
                    {{ ctrans('will stay waiting for CRM') }}
                </div>
            </div>

            <!-- Section: search product -->
            <div>
                <div>
                    {{ ctrans("Select product") }}:
                </div>
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fixed-width />
                    <input
                        v-model="modalSearchQuery"
                        type="text"
                        :placeholder="ctrans('Search products...')"
                        class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <div v-if="isModalProductsLoading" class="h-96 flex justify-center py-10 text-gray-400 text-3xl">
                <LoadingIcon />
            </div>

            <!-- Section: list products -->
            <div v-else class="overflow-y-auto h-96 border border-gray-200 rounded-lg isolate">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b border-gray-200 sticky top-0 z-10">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium">{{ ctrans('Name') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ ctrans('Available') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ ctrans('Quantity') }}</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="modalProducts.length === 0">
                            <td colspan="4" class="text-center py-10 text-gray-400">{{ ctrans('No products found') }}</td>
                        </tr>
                        <tr
                            v-for="product in modalProducts"
                            :key="product.id"
                            :class="productQuantities[product.id]?.quantity > 0 ? 'bg-green-100'
                            : product.stock > 0
                                ? ''
                                : 'bg-gray-100 opacity-60'
                            "
                            class="transition-colors"
                        >
                            <td class="px-4 py-3 text-gray-700">
                                <div class="flex items-start gap-x-3">
                                    <Image
                                        :src="productImage(product)"
                                        :alt="product.name"
                                        class="h-12 w-12 shrink-0 rounded object-cover bg-white"
                                    />
                                    <div>
                                        <div class="font-bold">{{ product.code }}</div>
                                        <div class="italic opacity-75">{{ product.name }}</div>
                                        <div v-if="Number(product.units) > 1" class="italic opacity-60 text-xs">
                                            ({{ ctrans('Pack of') }}: {{ Number(product.units) }})
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap" :class="!product.stock ? 'text-red-500' : 'text-gray-600'">
                                <template v-if="product.stock > 0">
                                    <FractionDisplay
                                        v-if="isProductCutView(product)"
                                        :fractionData="toMixedFractionData(Number(product.stock), Number(product.units))"
                                        class="justify-end"
                                    />
                                    <template v-else>{{ locale.number(productStockInView(product)) }}</template>
                                </template>
                                <template v-else>{{ ctrans('Empty stock') }}</template>
                            </td>
                            <td class="px-4 py-3 flex justify-end">
                                <div class="flex flex-col items-end gap-y-1">
                                    <InputNumber
                                        :modelValue="productQuantityInput(product)"
                                        @update:model-value="(e) => onUpdateProductQuantity(product, e)"
                                        @input="(e) => onUpdateProductQuantity(product, Number(e.value))"
                                        :min="0"
                                        :suffix="isProductCutView(product) ? `/${Number(product.units)}` : undefined"
                                        :key="String(isCutViewReplace) + product.id"
                                        inputClass="w-28"
                                        showButtons
                                    />
                                    <span
                                        v-if="isProductOverStock(product)"
                                        v-tooltip="ctrans('Ordering over the available stock, the excess waits for the warehouse')"
                                        class="text-xs text-amber-600 whitespace-nowrap"
                                    >
                                        <FontAwesomeIcon icon="fal fa-hourglass-start" fixed-width aria-hidden="true" />
                                        {{ ctrans('Over stock') }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Section: Footer (list selected products, and button) -->
            <div class="flex flex-col gap-2 pt-2 border-t border-gray-200">
                <!-- Section: selected products list -->
                <div class="flex flex-wrap gap-1.5">
                    <template v-if="selectedProducts.length > 0">
                        <div
                            v-for="product in selectedProducts"
                            :key="product.id"
                            class="inline-flex items-center gap-1.5 bg-green-100 border border-green-300 text-green-800 rounded-full px-3 py-1 text-xs font-medium"
                        >
                            <Image
                                v-if="product.image"
                                :src="product.image"
                                :alt="product.name"
                                class="h-5 w-5 -ml-1.5 shrink-0 rounded-full object-cover bg-white"
                            />
                            <span class="font-bold">{{ product.code }}</span>
                            <span class="opacity-70">×{{ productQuantityLabel(product.quantity, product.units) }}</span>
                            <button
                                type="button"
                                @click="unselectProduct(product.id)"
                                class="ml-0.5 xtext-green-600 text-red-600 opacity-70 hover:opacity-100 transition-colors"
                                :aria-label="ctrans('Remove')"
                            >
                                <FontAwesomeIcon icon="fal fa-times" class="text-xs" fixed-width />
                            </button>
                        </div>
                    </template>
                    <div v-else class="border border-transparent">
                        {{ ctrans("No selected products") }}
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-400">
                        {{ selectedProducts.length }} {{ ctrans('product(s) selected') }}
                    </span>
                    <div class="flex gap-2">
                        <Button :label="ctrans('Cancel')" type="negative" @click="closeReplaceProductModal" />
                        <Button
                            :label="ctrans('Save')"
                            icon="fad fa-save"
                            :loading="isSubmittingReplaceProduct"
                            :disabled="selectedProducts.length === 0 || quantityToReplace <= 0"
                            @click="submitReplaceProduct"
                        />
                    </div>
                </div>
            </div>
        </div>
    </Modal>

    <!-- Modal: Send back to waiting warehouse confirmation -->
    <Modal :isOpen="isOpenModalSendBackWarehouse" width="w-full max-w-md" @onClose="isOpenModalSendBackWarehouse = false" :closeButton="true">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col items-center gap-2 text-center">
                <FontAwesomeIcon icon="fal fa-hourglass-start" class="text-amber-500 text-4xl" fixed-width aria-hidden="true" />
                <h2 class="text-xl font-semibold">{{ ctrans('Send back to waiting warehouse') }}</h2>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm">
                <div class="flex justify-between items-start gap-4">
                    <div class="flex items-start gap-x-3">
                        <Image
                            v-if="orgStockImage(selectedItemSendBack)"
                            :src="orgStockImage(selectedItemSendBack)"
                            :alt="selectedItemSendBack?.org_stock_name"
                            class="h-10 w-10 shrink-0 rounded object-cover bg-white"
                        />
                        <div>
                            <span class="font-semibold">{{ selectedItemSendBack?.org_stock_code }}</span>
                            <span class="ml-1.5 text-amber-700 italic opacity-80">{{ selectedItemSendBack?.org_stock_name }}</span>
                        </div>
                    </div>
                    <div class="shrink-0 tabular-nums font-semibold text-amber-800 flex items-center gap-x-1">
                        <FractionDisplay
                            v-if="selectedItemSendBack?.quantity_waiting_crm_fractional_ds"
                            :fractionData="selectedItemSendBack.quantity_waiting_crm_fractional_ds"
                        />
                        <template v-else>{{ Number(selectedItemSendBack?.quantity_waiting_crm) }}</template>
                        {{ ctrans('items') }}
                    </div>
                </div>
            </div>

            <p class="text-sm text-gray-600 text-center">
                {{ ctrans('Are you sure you want to send this item back to the waiting warehouse?') }}
            </p>

            <div class="flex gap-2 pt-2">
                <Button :label="ctrans('Cancel')" type="negative" full @click="isOpenModalSendBackWarehouse = false" />
                <Button
                    :label="ctrans('Confirm')"
                    icon="fal fa-hourglass-start"
                    :loading="isSubmittingSendBack"
                    full
                    @click="submitSendBackWarehouse"
                />
            </div>
        </div>
    </Modal>

    <!-- Modal: information after success -->
    <Modal
        :isOpen="isModalConfirmationSuccess"
        @onClose="isModalConfirmationSuccess = false"
        width="w-full max-w-xl"
        :closeButton="true">
        <div v-if="successContext" class="flex flex-col gap-3 py-2">
            <div class="flex flex-col items-center gap-2 text-center mb-8">
                <FontAwesomeIcon icon="fas fa-check-circle" class="text-green-500 text-4xl" fixed-width aria-hidden="true" />
                <h3 class="font-semibold text-xl xtext-gray-800">{{ ctrans('Product replaced successfully') }}</h3>
            </div>

            <div class="flex flex-col gap-1 border-b border-gray-300 pb-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans('Order') }}</div>
                <a
                    v-if="orderRoute(successContext.replacedItem)"
                    :href="orderRoute(successContext.replacedItem)!"
                    target="_blank"
                    class="font-semibold text-base flex justify-between items-center"
                    xclick="isModalConfirmationSuccess = false"
                >
                    <div>
                        <FontAwesomeIcon icon="fal fa-shopping-cart" class="opacity-75 mr-1" fixed-width aria-hidden="true" />
                        #{{ successContext.replacedItem.order_reference }}
                    </div>
                    <div class="underline font-normal opacity-70 italic text-xs hover:opacity-100">
                        {{ ctrans("Click to open") }} ->
                    </div>
                </a>
                <span v-else class="font-semibold text-base">{{ successContext.replacedItem.order_reference ?? '-' }}</span>
            </div>

            <!-- Section: Replaced item -->
            <div class="flex flex-col gap-1">
                <div class="text-xs xfont-semibold xuppercase tracking-wide text-gray-400">{{ ctrans('Replaced items') }}</div>
                <div class="flex justify-between items-center bg-red-100 border border-red-200 rounded-lg px-4 py-3 text-sm gap-x-4">
                    <div class="flex items-center gap-x-3">
                        <Image
                            v-if="orgStockImage(successContext.replacedItem)"
                            :src="orgStockImage(successContext.replacedItem)"
                            :alt="successContext.replacedItem.org_stock_name"
                            class="h-12 w-12 shrink-0 rounded object-cover bg-white"
                        />
                        <div>
                            <span class="font-bold text-gray-700">{{ successContext.replacedItem.org_stock_code }}</span>
                            <span class="block text-gray-500 italic">{{ successContext.replacedItem.org_stock_name }}</span>
                        </div>
                    </div>
                    <div class="text-right tabular-nums text-gray-500 shrink-0">
                        <div class="flex items-center justify-end gap-x-1">
                            <FractionDisplay
                                :fractionData="toFractionData(successContext.replacedQuantity, Number(successContext.replacedItem.packed_in) || 1)"
                            />
                            {{ ctrans('items') }}
                        </div>
                        <div v-if="successContext.replacedItem.net_amount" class="text-xs opacity-70 mt-0.5">
                            {{ locale.currencyFormat(successContext.replacedItem.currency_code, successContext.replacedItem.net_amount) }}
                        </div>
                    </div>
                </div>

                <div v-if="successContext.remainingQuantity > 0" class="flex items-center gap-x-1 text-xs text-amber-700 mt-1">
                    <FontAwesomeIcon icon="fal fa-hourglass-start" fixed-width aria-hidden="true" />
                    <FractionDisplay
                        :fractionData="toFractionData(successContext.remainingQuantity, Number(successContext.replacedItem.packed_in) || 1)"
                    />
                    {{ ctrans('still waiting for CRM, handle it with the actions on the row') }}
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <div class="text-xs x tracking-wide text-gray-400">{{ ctrans('New products') }}</div>
                <div class="flex flex-col gap-1.5">
                    <div
                        v-for="product in successContext.newProducts"
                        :key="product.id"
                        class="flex justify-between items-center bg-green-100 border border-green-200 rounded-lg px-4 py-3 text-sm gap-x-4"
                    >
                        <div class="flex items-center gap-x-3">
                            <Image
                                :src="product.image"
                                :alt="product.name"
                                class="h-12 w-12 shrink-0 rounded object-cover bg-white"
                            />
                            <div>
                                <span class="font-bold text-gray-700">{{ product.code }}</span>
                                <span class="block xml-2 text-gray-500 italic">{{ product.name }}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-x-1 tabular-nums text-gray-500 shrink-0">
                            <FractionDisplayFE :numerator="product.numerator" :denominator="product.denominator" />
                            {{ ctrans('items') }}
                        </div>
                    </div>
                </div>
            </div>

            <Button :label="ctrans('Done')" type="tertiary" full @click="isModalConfirmationSuccess = false" />
        </div>
    </Modal>
</template>
