<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ctrans, ctransChoice } from '@/Composables/useTrans'
import { isAgentOrganisation } from '@/Composables/useAgentRoutes'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Table from '@/Components/Table/Table.vue'
import Image from '@common/Components/Image.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useLocaleStore } from '@/Stores/locale'
import PurchaseOrderItemStockInfo from '@/Components/Procurement/PurchaseOrderItemStockInfo.vue'
import PurchaseOrderSuggestButton from '@/Components/Procurement/PurchaseOrderSuggestButton.vue'
import PurchaseOrderLinePriceChange from '@/Components/Procurement/PurchaseOrderLinePriceChange.vue'
import PurchaseOrderLineQuantityChange from '@/Components/Procurement/PurchaseOrderLineQuantityChange.vue'
import PurchaseOrderLineAddToContainerDialog from '@/Components/Procurement/PurchaseOrderLineAddToContainerDialog.vue'
import PingIcon from '@/Components/Utils/PingIcon.vue'
import { getOrderingLevels, unitsPerOrderingLevel, suggestionAtOrderingLevel, type OrderingLevel } from '@/Composables/useOrderingLevel'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faPallet, faStopCircle, faTrashAlt, faPeopleArrows, faBalanceScale, faExclamationTriangle, faBoxOpen, faBoxCheck, faTruckContainer, faTimes } from '@fal'
import { faExclamationCircle, faSpinner, faMinusCircle } from '@fas'
import ConfirmPopup from 'primevue/confirmpopup'
import Popover from 'primevue/popover'
import Checkbox from 'primevue/checkbox'
import { useConfirm } from 'primevue/useconfirm'
import Toggle from '@/Components/Pure/Toggle.vue'
import ProductImageViewerDialog from '@/Components/Product/ProductImageViewerDialog.vue'

library.add(faExclamationTriangle, faBalanceScale, faBox, faPallet, faStopCircle, faExclamationCircle, faTrashAlt, faSpinner, faMinusCircle, faPeopleArrows, faBoxOpen)

const confirm = useConfirm()

const props = defineProps<{
    data: object
    tab?: string
    state?: string
    isPartner?: boolean
}>()

const currentLevel = defineModel<OrderingLevel>('level', { default: 'cartons' })

const locale = useLocaleStore()

const isInProcess = computed(() => props.state === 'in_process')
const canCancelItems = computed(() => props.state === 'submitted' || props.state === 'confirmed')

const closedStates = ['settled', 'cancelled', 'not_received']
const arrivedDeliveryStates = ['received', 'checked', 'settled', 'not_received', 'cancelled']

const isTransactionClosed = (item: { state?: string; delivery_state?: string }) =>
    closedStates.includes(props.state ?? '') || closedStates.includes(item.state ?? '') || arrivedDeliveryStates.includes(item.delivery_state ?? '')

const cancelledRowClass = (item: { state?: string }) => item.state === 'cancelled' ? 'bg-red-100' : ''

const hasSupplierUnits = computed(() => !props.isPartner && ((props.data as any)?.data ?? []).some((item: any) => item.supplier_unit))

const supplierLevel = {
    key: 'supplier_units' as OrderingLevel,
    icon: 'fal fa-balance-scale',
    tab: ctrans('Ordering supplier units'),
    description: ctrans('Supplier unit description'),
    quantity: ctrans('Supplier units'),
    cost: ctrans('Supplier unit cost'),
    singular: ctrans('Supplier unit'),
}

const levels = computed(() => [
    ...getOrderingLevels().filter(l => !props.isPartner || l.key === 'skos'),
    ...(hasSupplierUnits.value ? [supplierLevel] : []),
])

const level = computed(() => levels.value.find(l => l.key === currentLevel.value) ?? levels.value[0])

function unitsPerLevel(item: any) {
    return unitsPerOrderingLevel(item, currentLevel.value)
}

function supplierUnitQuantity(item: any) {
    return item.supplier_unit ? ` | ${formatQuantity(Number(item.quantity_ordered) / (Number(item.units_per_supplier_unit) || 1))} ${item.supplier_unit}` : ''
}

function skosPerCarton(item: any) {
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return carton / pack
}

function formatQuantity(value: number) {
    return locale.number(Math.round(value * 1000) / 1000)
}



function quantityAtLevel(item: any) {
    return Number(item.quantity_ordered) / unitsPerLevel(item)
}

function levelCost(item: any) {
    return Number(item.unit_cost) * unitsPerLevel(item)
}

function levelCostLabel(item: any) {
    const supplier = locale.currencyFormat(item.net_currency ?? 'EUR', levelCost(item))

    if (!item.org_currency || item.org_currency === item.net_currency) {
        return supplier
    }

    const orgCost = levelCost(item) * (Number(item.org_exchange) || 1)

    return `${supplier} (${locale.currencyFormat(item.org_currency, orgCost)})`
}

function quantityBreakdown(item: any) {
    const units = Number(item.quantity_ordered)
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    if (props.isPartner) {
        return `${formatQuantity(units / pack)}sko.`
    }

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko. | ${formatQuantity(units / carton)}C.${supplierUnitQuantity(item)}`
}

function orderedCartons(item: any) {
    return Number(item.quantity_ordered) / (Number(item.units_per_carton) || 1)
}

function isBelowMinimumCartonOrder(item: any) {
    return !!item.minimum_carton_order
        && item.state !== 'cancelled'
        && Number(item.quantity_ordered) > 0
        && orderedCartons(item) < item.minimum_carton_order
}

function cartonsLabel(cartons: number) {
    const rounded = Math.round(cartons * 100) / 100

    return ctransChoice(':number carton|:number cartons', rounded, { number: locale.number(rounded) })
}

function belowMinimumCartonOrderTooltip(item: any) {
    return ctrans("Ordered :ordered, below the supplier's minimum of :moq (short by :short)", {
        ordered: cartonsLabel(orderedCartons(item)),
        moq: cartonsLabel(item.minimum_carton_order),
        short: cartonsLabel(item.minimum_carton_order - orderedCartons(item)),
    })
}

function amount(item: any) {
    const net = locale.currencyFormat(item.net_currency ?? 'EUR', item.net_amount ?? 0)

    if (item.org_net_amount === null || item.org_currency === item.net_currency) {
        return `${net}`
    }

    return `${net} (${locale.currencyFormat(item.org_currency ?? 'EUR', item.org_net_amount)})`
}

const savingId = ref<number | null>(null)

const isPriceEditable = computed(() => !props.isPartner && ['in_process', 'submitted'].includes(props.state ?? ''))
const alsoUpdateSupplierPrice = ref<Record<number, boolean>>({})
const savingPriceId = ref<number | null>(null)

function canEditPrice(item: any) {
    return isPriceEditable.value && !isInAgentOrganisation && item.updateRoute && ['in_process', 'submitted'].includes(item.state)
}

function isPriceQueryAnswered(item: any) {
    return !!item.price_query && item.price_query.state !== 'pending'
}

function supplierLevelCostLabel(item: any) {
    return locale.currencyFormat(item.net_currency ?? 'EUR', Number(item.supplier_unit_cost) * unitsPerLevel(item))
}

function isPriceChanged(item: any) {
    return item.supplier_unit_cost !== undefined && item.supplier_unit_cost !== null && Number(item.supplier_unit_cost) !== Number(item.unit_cost)
}

async function savePrice(item: any, unitCost: number, updateSupplierCost: boolean, form?: any) {
    savingPriceId.value = item.id
    try {
        await axios.patch(
            route(item.updateRoute.name, item.updateRoute.parameters),
            {
                unit_cost: Number(unitCost.toFixed(6)),
                update_supplier_cost: updateSupplierCost,
            }
        )
        form?.defaults()
        alsoUpdateSupplierPrice.value[item.id] = false
        notify({ title: ctrans('Success'), text: ctrans('Price updated'), type: 'success' })
        router.reload({ only: [props.tab ?? 'items', 'box_stats', 'pageHead'] })
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to update price'),
            type: 'error',
        })
    } finally {
        savingPriceId.value = null
    }
}

function onSavePrice(item: any, form: any) {
    savePrice(item, Number(form.quantity) / unitsPerLevel(item), !!alsoUpdateSupplierPrice.value[item.id], form)
}

const typedSkos = ref<Record<number, number>>({})

const quantityInputVersion = ref<Record<number, number>>({})

watch(currentLevel, () => (typedSkos.value = {}))

function refreshQuantityInput(item: any, quantityOrdered: number) {
    item.quantity_ordered = quantityOrdered
    quantityInputVersion.value[item.id] = (quantityInputVersion.value[item.id] ?? 0) + 1
}

function suggestionLabel(item: any, skos: number) {
    const quantity = suggestionAtOrderingLevel(item, skos, currentLevel.value)

    return `${locale.number(quantity)} ${quantity === 1 ? level.value.singular : level.value.quantity}`
}

async function applySuggestion(item: any, skos: number) {
    const previousQuantityOrdered = item.quantity_ordered
    const quantityOrdered = suggestionAtOrderingLevel(item, skos, currentLevel.value) * unitsPerLevel(item)
    typedSkos.value[item.id] = quantityOrdered / (Number(item.units_per_pack) || 1)
    refreshQuantityInput(item, quantityOrdered)

    const isSaved = await onSaveQuantity(item, { quantity: Number(item.quantity_ordered) / unitsPerLevel(item), defaults: () => {} })
    if (!isSaved) {
        delete typedSkos.value[item.id]
        refreshQuantityInput(item, previousQuantityOrdered)
    }
}

async function onSaveQuantity(item: any, form: any): Promise<boolean> {
    const quantityOrdered = Number(form.quantity) * unitsPerLevel(item)
    const saveRoute = item.saveRoute ?? item.updateRoute
    const method = String(saveRoute?.method ?? 'patch').toLowerCase()

    savingId.value = item.id
    try {
        await axios[method](
            route(saveRoute.name, saveRoute.parameters),
            { quantity_ordered: quantityOrdered }
        )
        form.defaults()
        notify({ title: ctrans('Success'), text: ctrans('Quantity updated'), type: 'success' })
        router.reload({ only: [props.tab ?? 'items', 'box_stats', 'pageHead'] })

        return true
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to update quantity'),
            type: 'error',
        })

        return false
    } finally {
        savingId.value = null
    }
}

const deletingId = ref<number | null>(null)

const BRAVE_MODE_STORAGE_KEY = 'purchase-order-brave-mode-expires-at'
const BRAVE_MODE_LIFETIME_MS = 8 * 60 * 60 * 1000

function readBraveMode(): boolean {
    try {
        const expiresAt = Number(localStorage.getItem(BRAVE_MODE_STORAGE_KEY))

        return expiresAt > Date.now()
    } catch {
        return false
    }
}

const isBraveMode = ref(readBraveMode())

watch(isBraveMode, (value) => {
    try {
        if (value) {
            localStorage.setItem(BRAVE_MODE_STORAGE_KEY, String(Date.now() + BRAVE_MODE_LIFETIME_MS))
        } else {
            localStorage.removeItem(BRAVE_MODE_STORAGE_KEY)
        }
    } catch {
        return
    }
})

function isBraveModeActive(): boolean {
    if (isBraveMode.value && !readBraveMode()) {
        isBraveMode.value = false
    }

    return isBraveMode.value
}

const packingId = ref<number | null>(null)

function isFullyPacked(item: any) {
    const warehouse = item.agent_warehouse
    return !!warehouse && warehouse.quantity_packed > 0 && warehouse.quantity_packed >= Number(item.quantity_ordered) - 0.001
}

function isAllInContainers(item: any) {
    const warehouse = item.agent_warehouse
    return !!warehouse?.containers?.length && warehouse.quantity_awaiting_delivery <= 0.01
}

const isLoadedBeforePacking = (item: any) => isAllInContainers(item) && !isFullyPacked(item)

function packItem(item: any) {
    const packRoute = item.agent_warehouse?.pack_route
    if (!packRoute) {
        return
    }

    router.patch(route(packRoute.name, packRoute.parameters), {}, {
        preserveScroll: true,
        onStart: () => (packingId.value = item.id),
        onFinish: () => (packingId.value = null),
        onSuccess: () => notify({ title: ctrans('Success'), text: ctrans(':code packed', { code: item.code }), type: 'success' }),
        onError: (errors: Record<string, string>) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors ?? {})[0], type: 'error' }),
    })
}

function confirmPackItem(event: MouseEvent, item: any) {
    const warehouse = item.agent_warehouse
    if (!warehouse?.pack_route) {
        return
    }

    if (warehouse.quantity_to_check <= 0 || isBraveModeActive()) {
        packItem(item)
        return
    }

    confirm.require({
        group: 'pack-item',
        target: event.currentTarget as HTMLElement,
        message: ctrans(':units of the :total units of :code are not checked yet. Once packed they can no longer be checked. Pack them anyway?', {
            units: locale.number(warehouse.quantity_to_check),
            total: locale.number(warehouse.quantity_packable),
            code: item.code,
        }),
        acceptLabel: ctrans('Pack anyway'),
        rejectLabel: ctrans('Cancel'),
        accept: () => packItem(item),
    })
}

function packTooltip(item: any) {
    const warehouse = item.agent_warehouse
    const tooltip = ctrans('Click to mark the :units received units as packed', { units: locale.number(warehouse.quantity_packable) })

    return warehouse.quantity_to_check > 0
        ? `${tooltip}. ${ctrans(':units of them are not checked yet', { units: locale.number(warehouse.quantity_to_check) })}`
        : tooltip
}

const _table = ref<InstanceType<typeof Table> | null>(null)
const containerDialogItems = ref<any[]>([])
const isContainerDialogVisible = ref(false)
const selectedRows = ref<Record<string, boolean>>({})

const tableRows = computed<any[]>(() => (props.data as any)?.data ?? [])
const canAddToContainer = (item: any) => !!item.agent_warehouse?.containers_route
const canPack = (item: any) => !!item.agent_warehouse?.pack_route && !isAllInContainers(item)
const isSelectable = (item: any) => canAddToContainer(item) || canPack(item)
const hasSelectableRows = computed(() => tableRows.value.some(isSelectable))
const rowsSelectedForContainer = computed(() => tableRows.value.filter((item) => selectedRows.value[item.id] && canAddToContainer(item)))
const rowsSelectedForPacking = computed(() => tableRows.value.filter((item) => selectedRows.value[item.id] && canPack(item)))
const isPackingSelected = ref(false)

function packSelected() {
    const rows = rowsSelectedForPacking.value
    if (!rows.length) {
        return
    }

    router.patch(route('grp.models.purchase-order.transaction.pack_many', { purchaseOrder: rows[0].agent_warehouse.pack_route.parameters.purchaseOrder }), { transactions: rows.map((item) => item.id) }, {
        preserveScroll: true,
        onStart: () => (isPackingSelected.value = true),
        onFinish: () => (isPackingSelected.value = false),
        onSuccess: () => {
            _table.value?.clearSelection()
            selectedRows.value = {}
        },
        onError: (errors: Record<string, string>) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors ?? {})[0], type: 'error' }),
    })
}

function confirmPackSelected(event: MouseEvent) {
    const rows = rowsSelectedForPacking.value
    const toCheck = rows.reduce((sum, item) => sum + Number(item.agent_warehouse.quantity_to_check ?? 0), 0)

    if (toCheck <= 0 || isBraveModeActive()) {
        packSelected()
        return
    }

    confirm.require({
        group: 'pack-item',
        target: event.currentTarget as HTMLElement,
        message: ctrans(':units received units of the :count selected items are not checked yet. Once packed they can no longer be checked. Pack them anyway?', {
            units: locale.number(toCheck),
            count: rows.length,
        }),
        acceptLabel: ctrans('Pack anyway'),
        rejectLabel: ctrans('Cancel'),
        accept: packSelected,
    })
}

function onSelectRow(rows: Record<string, boolean>) {
    selectedRows.value = { ...rows }
}

function openContainerDialog(items: any[]) {
    containerDialogItems.value = items
    isContainerDialogVisible.value = true
}

function onAddedToContainer() {
    _table.value?.clearSelection()
    selectedRows.value = {}
}

const removingContainerKey = ref<string | null>(null)

function removeFromContainer(item: any, container: any) {
    const removeRoute = container.remove_route
    router.delete(route(removeRoute.name, removeRoute.parameters), {
        preserveScroll: true,
        onStart: () => (removingContainerKey.value = `${item.id}-${container.id}`),
        onFinish: () => (removingContainerKey.value = null),
        onSuccess: () => notify({ title: ctrans('Success'), text: ctrans(':code removed from :container', { code: item.code, container: container.reference }), type: 'success' }),
        onError: (errors: Record<string, string>) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors ?? {})[0], type: 'error' }),
    })
}

function confirmRemoveFromContainer(event: MouseEvent, item: any, container: any) {
    if (!container.remove_route) {
        return
    }

    if (isBraveModeActive()) {
        removeFromContainer(item, container)
        return
    }

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: ctrans('Take the :units units of :code out of :container?', { units: locale.number(container.unit_quantity), code: item.code, container: container.reference }),
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: ctrans('Remove'),
        rejectLabel: ctrans('Cancel'),
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-text',
        accept: () => removeFromContainer(item, container),
    })
}

function confirmDeleteItem(event: MouseEvent, item: any) {
    if (!item.deleteRoute) {
        return
    }

    if (isBraveModeActive()) {
        onDeleteItem(item)
        return
    }

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: ctrans('Remove this product from the purchase order?'),
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: ctrans('Delete'),
        rejectLabel: ctrans('Cancel'),
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-text',
        accept: () => onDeleteItem(item),
    })
}

async function onDeleteItem(item: any) {
    deletingId.value = item.id
    try {
        await axios.delete(route(item.deleteRoute.name, item.deleteRoute.parameters))
        notify({ title: ctrans('Success'), text: ctrans('Item removed'), type: 'success' })
        router.reload({ only: [props.tab ?? 'items', 'box_stats', 'pageHead'] })
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to remove item'),
            type: 'error',
        })
    } finally {
        deletingId.value = null
    }
}

const cancellingId = ref<number | null>(null)

function confirmCancelItem(event: MouseEvent, item: any) {
    if (!item.cancelRoute) {
        return
    }

    if (isBraveModeActive()) {
        onCancelItem(item)
        return
    }

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: props.state === 'confirmed'
            ? ctrans('Only cancel if the supplier has not sent it. If it is already on its way, wait for it and deal with it on the delivery. Cancel this item?')
            : ctrans('Cancel this item?'),
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: ctrans('Cancel item'),
        rejectLabel: ctrans('Keep'),
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-text',
        accept: () => onCancelItem(item),
    })
}

async function onCancelItem(item: any) {
    cancellingId.value = item.id
    try {
        await axios.patch(route(item.cancelRoute.name, item.cancelRoute.parameters))
        notify({ title: ctrans('Success'), text: ctrans('Item cancelled'), type: 'success' })
        router.reload({ only: [props.tab ?? 'items', 'box_stats', 'pageHead'] })
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to cancel item'),
            type: 'error',
        })
    } finally {
        cancellingId.value = null
    }
}

const imagePreviewPopover = ref()
const imagePreview = ref<any>(null)

function showImagePreview(event: MouseEvent, item: any) {
    if (!item.image_preview) {
        return
    }

    imagePreview.value = item.image_preview
    imagePreviewPopover.value?.show(event, event.currentTarget)
}

function hideImagePreview() {
    imagePreviewPopover.value?.hide()
}

const isImageViewerVisible = ref(false)
const viewedImages = ref<{ source: any; alt?: string; original_url?: string | null; download_url?: string | null }[]>([])

function openImageViewer(item: any) {
    if (!item.image_full) {
        return
    }

    hideImagePreview()
    viewedImages.value = [{
        source: item.image_preview ?? item.image_thumbnail,
        alt: item.code,
        original_url: item.image_full.original_url,
        download_url: item.image_full.download_url,
    }]
    isImageViewerVisible.value = true
}

const currentOrganisation = route().params.organisation as string | undefined
const isInAgentOrganisation = isAgentOrganisation(currentOrganisation)
const currentPurchaseOrder = route().params.purchaseOrder as string | undefined

function packingInstructionsUrl(item: { id: number, has_packing_instructions?: boolean }) {
    if (!item.has_packing_instructions || !currentOrganisation || !currentPurchaseOrder) {
        return ''
    }

    return route('grp.org.procurement.purchase_orders.packing_instructions', { organisation: currentOrganisation, purchaseOrder: currentPurchaseOrder, transaction: item.id })
}

function supplierProductRoute(item: { slug?: string, org_supplier_product_slug?: string }) {
    if (item.org_supplier_product_slug && isInAgentOrganisation) {
        return route('grp.org.procurement.org_supplier_products.show', [currentOrganisation, item.org_supplier_product_slug])
    }

    if (!item.slug) {
        return ''
    }

    return route('grp.supply-chain.supplier_products.show', [item.slug])
}


function hasOwnSkuCode(item: { code?: string, org_stock_code?: string | null }) {
    return !!item.org_stock_code && item.org_stock_code !== item.code
}

function orgStockRoute(item: { org_stock_id?: number }) {
    if (!item.org_stock_id || isInAgentOrganisation) {
        return ''
    }

    return route('grp.majordomo.redirect_org_stock', [item.org_stock_id])
}
</script>

<template>
    <Table
        ref="_table"
        :resource="data"
        :name="tab"
        class="mt-5"
        :rowColorFunction="cancelledRowClass"
        :isCheckBox="hasSelectableRows"
        checkboxKey="id"
        :disabledCheckbox="(item: any) => !isSelectable(item)"
        @onSelectRow="onSelectRow"
    >
        <template #before-table>
            <div class="flex items-end justify-between gap-3 border-b border-gray-200 px-3 sm:px-4">
                <div class="flex items-end gap-1">
                    <template v-if="isInProcess && levels.length > 1">
                        <button
                            v-for="item in levels"
                            :key="item.key"
                            type="button"
                            class="px-3 py-1.5 text-sm border-b-2 -mb-px transition"
                            :class="item.key === currentLevel
                                ? 'border-[--app-accent] text-[--app-accent] font-medium'
                                : 'border-transparent text-gray-500 hover:text-gray-700'"
                            @click="currentLevel = item.key"
                        >
                            <FontAwesomeIcon :icon="item.icon" aria-hidden="true" fixed-width />
                            {{ item.tab }}
                        </button>
                    </template>
                </div>
                <div v-if="rowsSelectedForPacking.length || rowsSelectedForContainer.length" class="mb-1.5 flex items-center gap-2">
                    <Button
                        v-if="rowsSelectedForPacking.length"
                        :label="ctransChoice('Mark :count item packed|Mark :count items packed', rowsSelectedForPacking.length)"
                        :tooltip="ctrans('Click to mark what was received of the selected products as packed for the next shipment')"
                        type="tertiary"
                        size="xs"
                        :loading="isPackingSelected"
                        @click="confirmPackSelected"
                    >
                        <template #icon>
                            <FontAwesomeIcon :icon="faBoxCheck" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <span v-if="rowsSelectedForContainer.length" class="relative inline-flex">
                        <Button
                            :label="ctransChoice('Add :count item to shipment|Add :count items to shipment', rowsSelectedForContainer.length)"
                            :tooltip="ctrans('Click to put the selected products in a shipment in process')"
                            type="secondary"
                            size="xs"
                            @click="openContainerDialog(rowsSelectedForContainer)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faTruckContainer" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <PingIcon class="!absolute -right-0.5 -top-0.5 text-[5px] text-amber-500" aria-hidden="true" />
                    </span>
                </div>
                <label
                    v-tooltip="ctrans('When on, Remove and Cancel act straight away without asking to confirm')"
                    class="mb-1.5 flex cursor-pointer items-center gap-2 text-sm"
                    :class="isBraveMode ? 'font-medium text-red-600' : 'text-gray-500'"
                >
                    <Toggle v-model="isBraveMode" />
                    {{ ctrans('Brave mode') }}
                </label>
            </div>
        </template>

        <template #header(description)="{ header }">
            <th class="font-normal px-6 w-auto text-left">
                {{ isInProcess ? level.description : header.label }}
            </th>
        </template>

        <template #header(quantity)="{ header }">
            <th class="font-normal px-6 w-auto" :class="isInProcess ? 'text-right' : 'text-left'">
                {{ isInProcess ? level.quantity : header.label }}
            </th>
        </template>

        <template #cell(code)="{ item }">
            <div class="flex flex-col gap-0.5">
                <div class="flex items-center gap-1.5">
                    <Link
                        v-if="supplierProductRoute(item)"
                        v-tooltip="ctrans('Supplier product code')"
                        :href="supplierProductRoute(item)"
                        class="primaryLink"
                    >
                        {{ item.code }}
                    </Link>
                    <Link
                        v-else-if="orgStockRoute(item)"
                        v-tooltip="ctrans('SKU code')"
                        :href="orgStockRoute(item)"
                        class="primaryLink"
                    >
                        {{ item.code }}
                    </Link>
                    <span v-else>{{ item.code }}</span>
                    <span
                        v-if="item.hub_name"
                        v-tooltip="ctrans(':hub makes this. Order it from :hub unless it is urgent and they cannot ship in time', { hub: item.hub_name })"
                        class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-red-500" />

                    <Link
                        v-if="supplierProductRoute(item) && orgStockRoute(item) && !hasOwnSkuCode(item)"
                        v-tooltip="ctrans('Part reference is same as supplier product code')"
                        :href="orgStockRoute(item)"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <FontAwesomeIcon icon="fal fa-box" aria-hidden="true" fixed-width />
                    </Link>
                </div>
                <template v-if="hasOwnSkuCode(item)">
                    <Link
                        v-if="orgStockRoute(item)"
                        v-tooltip="ctrans('SKU code')"
                        :href="orgStockRoute(item)"
                        class="whitespace-nowrap text-xs text-gray-500 hover:text-gray-700"
                    >
                        <FontAwesomeIcon icon="fal fa-box" aria-hidden="true" fixed-width />
                        {{ item.org_stock_code }}
                    </Link>
                    <span v-else v-tooltip="ctrans('SKU code')" class="whitespace-nowrap text-xs text-gray-500">
                        <FontAwesomeIcon icon="fal fa-box" aria-hidden="true" fixed-width />
                        {{ item.org_stock_code }}
                    </span>
                </template>
            </div>
        </template>

        <template #cell(image_thumbnail)="{ item }">
            <div
                class="h-20 w-20 flex-none overflow-hidden rounded border border-gray-100"
                :class="{ 'cursor-zoom-in': item.image_preview || item.image_full }"
                v-tooltip="item.image_full ? ctrans('Click to enlarge and download in full resolution') : undefined"
                @mouseenter="showImagePreview($event, item)"
                @mouseleave="hideImagePreview"
                @click="openImageViewer(item)"
            >
                <Image :src="item['image_thumbnail']" imageCover class="h-20 w-20" />
            </div>
        </template>

        <template #cell(description)="{ item }">
            <div class="space-y-0.5">
                <div>
                    <span v-if="isInProcess && currentLevel === 'supplier_units' && !item.supplier_unit" v-tooltip="ctrans('The supplier counts this one in our units')" class="font-medium text-gray-400">
                        1x
                    </span>
                    <span v-else-if="isInProcess && currentLevel !== 'units'" class="font-medium">
                        {{ formatQuantity(unitsPerLevel(item)) }}x<template v-if="currentLevel === 'supplier_units'"> ({{ item.supplier_unit }})</template>
                    </span>
                    {{ item.name }}
                </div>
                <div v-if="canEditPrice(item)" class="text-xs text-gray-500 space-y-1">
                    <div class="flex items-center gap-2">
                        <span>{{ level.cost }}<template v-if="item.net_currency"> ({{ item.net_currency }})</template>:</span>
                        <NumberWithButtonSave
                            :key="`price-${item.id}-${currentLevel}`"
                            isWithRefreshModel
                            noUndoButton
                            :modelValue="levelCost(item)"
                            :min="0"
                            :bindToTarget="{ min: 0, maxFractionDigits: 4 }"
                            :isLoading="savingPriceId === item.id"
                            @onSave="(form) => onSavePrice(item, form)"
                        />
                    </div>
                    <div v-if="isPriceChanged(item)" class="flex items-center gap-2 text-orange-600">
                        <span>{{ ctrans('Supplier price') }}: {{ supplierLevelCostLabel(item) }}</span>
                        <button
                            v-if="item.can_update_supplier_cost"
                            type="button"
                            class="underline hover:text-orange-800 disabled:opacity-50"
                            :disabled="savingPriceId === item.id"
                            @click="savePrice(item, Number(item.unit_cost), true)"
                        >
                            {{ ctrans('Use this price as supplier price') }}
                        </button>
                    </div>
                    <label
                        v-if="item.can_update_supplier_cost"
                        class="flex items-center gap-1 select-none"
                        :class="isPriceQueryAnswered(item) ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
                        v-tooltip="isPriceQueryAnswered(item) ? ctrans('The supplier price change was already answered') : undefined"
                    >
                        <Checkbox v-model="alsoUpdateSupplierPrice[item.id]" binary size="small" :disabled="isPriceQueryAnswered(item)" />
                        {{ ctrans('Also update supplier price') }}
                    </label>
                </div>
                <div v-else-if="isInProcess" class="text-xs text-gray-500">
                    {{ level.cost }}: {{ levelCostLabel(item) }}
                </div>
                <PurchaseOrderLinePriceChange v-if="!isPartner" :item="item" :unitsPerLevel="unitsPerLevel(item)" :costLabel="level.cost" :updateSupplierCost="!!alsoUpdateSupplierPrice[item.id]" />
                <div v-if="!isPartner" class="text-xs text-gray-500">
                    {{ ctrans('Packed in') }} {{ formatQuantity(Number(item.units_per_pack) || 1) }}s ,
                    {{ ctrans('sko/C') }}: {{ formatQuantity(skosPerCarton(item)) }}
                </div>
                <div v-else-if="item.partner_stock !== null || item.partner_units_per_carton" class="text-xs text-gray-500">
                    <template v-if="item.partner_stock !== null">
                        {{ ctrans('Partner stock') }}: <span class="font-semibold text-gray-700">{{ locale.number(Math.round(item.partner_stock)) }}</span> SKOs
                    </template>
                    <span
                        v-if="item.partner_units_per_carton"
                        v-tooltip="ctrans('How the partner buys it from its supplier. Often out of date and different in each organisation: a guide only, check before ordering in cartons')"
                        class="cursor-help">
                        <template v-if="item.partner_stock !== null"> | </template>{{ ctrans('their carton ~:count sko', { count: formatQuantity(skosPerCarton({ units_per_pack: item.units_per_pack, units_per_carton: item.partner_units_per_carton })) }) }}
                    </span>
                </div>
                <PurchaseOrderItemStockInfo :item="item" :isPartner="isPartner" :typedSkosById="typedSkos" :isOrderClosed="isTransactionClosed(item)" :isOrderLocked="!!state && state !== 'in_process'" :inCartons="currentLevel === 'cartons'" />
            </div>
        </template>

        <template #cell(subtotals)="{ item }">
            <span v-if="!Number(item.quantity_ordered)" class="text-gray-300">-</span>
            <div v-else class="space-y-0.5">
                <div class="text-gray-500">{{ quantityBreakdown(item) }}</div>
                <div class="flex items-center gap-1.5">
                    <span>{{ amount(item) }}</span>
                    <span v-if="item.weight !== null" class="text-gray-500">
                        {{ locale.number(item.weight) }}Kg
                    </span>
                    <FontAwesomeIcon
                        v-else
                        v-tooltip="ctrans('Unknown weight')"
                        icon="fas fa-exclamation-circle"
                        class="text-orange-500"
                        fixed-width aria-hidden="true"
                    />
                </div>
            </div>
        </template>

        <template #cell(quantity)="{ item }">
            <div v-if="isInProcess" class="flex flex-col items-end">
                <NumberWithButtonSave
                    :key="`${item.id}-${currentLevel}-${quantityInputVersion[item.id] ?? 0}`"
                    isWithRefreshModel
                    :modelValue="quantityAtLevel(item)"
                    :min="0"
                    :isLoading="savingId === item.id"
                    @update:modelValue="(value) => (typedSkos[item.id] = (Number(value) * unitsPerLevel(item)) / (Number(item.units_per_pack) || 1))"
                    @onSave="(form) => onSaveQuantity(item, form)"
                />
                <PurchaseOrderSuggestButton :item="item" :isPartner="isPartner" :typedSkosById="typedSkos" :quantityLabel="(skos: number) => suggestionLabel(item, skos)" @suggest="(skos) => applySuggestion(item, skos)" />
            </div>
            <span v-else class="text-gray-500">{{ quantityBreakdown(item) }}</span>
            <PurchaseOrderLineQuantityChange v-if="!isInProcess && !isPartner" :item="item" :unitsPerLevel="unitsPerLevel(item)" :quantityLabel="level.quantity" />
            <div
                v-if="isBelowMinimumCartonOrder(item)"
                v-tooltip="belowMinimumCartonOrderTooltip(item)"
                class="flex items-center gap-1 pt-1 text-xs font-medium text-amber-600 tabular-nums whitespace-nowrap"
                :class="isInProcess ? 'justify-end' : ''"
            >
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ ctrans('Below MOQ: :ordered / :moq', { ordered: locale.number(Math.round(orderedCartons(item) * 100) / 100), moq: cartonsLabel(item.minimum_carton_order) }) }}
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex flex-col items-end gap-1.5">
            <div class="flex justify-end items-center gap-2">
                <span
                    v-if="isLoadedBeforePacking(item)"
                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200"
                    v-tooltip="ctrans('Loaded in a shipment before it was packed: take it out of the shipment to pack it')"
                >
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                    <template v-if="item.agent_warehouse.quantity_packed > 0">{{ ctrans('Packed :packed/:ordered', { packed: locale.number(item.agent_warehouse.quantity_packed), ordered: locale.number(Number(item.quantity_ordered)) }) }}</template>
                    <template v-else>{{ ctrans('Not packed yet') }}</template>
                </span>

                <span
                    v-else-if="item.agent_warehouse?.quantity_packed > 0"
                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                    :class="isFullyPacked(item) ? 'bg-green-50 text-green-700 ring-green-200' : 'bg-amber-50 text-amber-700 ring-amber-200'"
                    v-tooltip="isFullyPacked(item) ? ctrans('Every unit of this product is packed') : ctrans(':packed of the :ordered units are packed', { packed: locale.number(item.agent_warehouse.quantity_packed), ordered: locale.number(Number(item.quantity_ordered)) })"
                >
                    <FontAwesomeIcon :icon="faBoxCheck" fixed-width aria-hidden="true" />
                    <template v-if="isFullyPacked(item)">{{ ctrans('Packed') }}</template>
                    <template v-else>{{ ctrans('Packed :packed/:ordered', { packed: locale.number(item.agent_warehouse.quantity_packed), ordered: locale.number(Number(item.quantity_ordered)) }) }}</template>
                </span>

                <a v-if="packingInstructionsUrl(item) && !isFullyPacked(item) && !isAllInContainers(item)" :href="packingInstructionsUrl(item)" target="_blank" rel="noopener">
                    <Button
                        :label="ctrans('Packing')"
                        :tooltip="ctrans('Print the packing instructions of this product')"
                        icon="fal fa-file-pdf"
                        type="tertiary"
                        size="xs"
                    />
                </a>

                <Button
                    v-if="item.agent_warehouse?.pack_route && !isAllInContainers(item)"
                    :label="ctrans('Mark packed')"
                    :tooltip="packTooltip(item)"
                    type="tertiary"
                    size="xs"
                    :loading="packingId === item.id"
                    :disabled="packingId === item.id"
                    @click="confirmPackItem($event, item)"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="faBoxCheck" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>

                <Button
                    v-if="item.agent_warehouse?.containers_route"
                    :label="ctrans('Add to shipment')"
                    :tooltip="ctrans('Click to put units of this product in a shipment in process')"
                    type="tertiary"
                    size="xs"
                    @click="openContainerDialog([item])"
                >
                    <template #icon>
                        <FontAwesomeIcon :icon="faTruckContainer" class="text-gray-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>

                <Button
                    v-if="item.deleteRoute && state === 'in_process'"
                    :label="ctrans('Remove')"
                    :tooltip="ctrans('Remove this product from the purchase order')"
                    icon="fal fa-trash-alt"
                    type="delete"
                    size="xs"
                    :loading="deletingId === item.id"
                    :disabled="deletingId === item.id"
                    @click="confirmDeleteItem($event, item)"
                />

                <Button
                    v-if="canCancelItems && item.cancelRoute"
                    :label="ctrans('Cancel')"
                    :tooltip="ctrans('Cancel this item')"
                    icon="fas fa-minus-circle"
                    type="delete"
                    size="xs"
                    :loading="cancellingId === item.id"
                    :disabled="cancellingId === item.id"
                    @click="confirmCancelItem($event, item)"
                />

            </div>

            <div v-if="item.agent_warehouse?.containers?.length" class="flex flex-wrap justify-end gap-1">
                <span
                    v-for="container in item.agent_warehouse.containers"
                    :key="container.id"
                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-gray-50 px-2 py-0.5 text-xs text-gray-700 ring-1 ring-inset ring-gray-200"
                >
                    <FontAwesomeIcon :icon="faTruckContainer" class="text-gray-400" fixed-width aria-hidden="true" />
                    <Link v-if="container.show_route" :href="route(container.show_route.name, container.show_route.parameters)" class="secondaryLink">
                        {{ container.reference }}
                    </Link>
                    <span v-else>{{ container.reference }}</span>
                    <span class="tabular-nums text-gray-500">· {{ locale.number(container.unit_quantity) }}</span>
                    <button
                        v-if="container.remove_route"
                        type="button"
                        class="ml-0.5 text-gray-400 hover:text-red-600 disabled:opacity-50"
                        :disabled="removingContainerKey === `${item.id}-${container.id}`"
                        v-tooltip="ctrans('Click to take this product out of :container', { container: container.reference })"
                        :aria-label="ctrans('Remove from :container', { container: container.reference })"
                        @click="confirmRemoveFromContainer($event, item, container)"
                    >
                        <FontAwesomeIcon :icon="removingContainerKey === `${item.id}-${container.id}` ? faSpinner : faTimes" :spin="removingContainerKey === `${item.id}-${container.id}`" fixed-width aria-hidden="true" />
                    </button>
                </span>
            </div>
            </div>
        </template>

        <template #cell(weight)="{ item }">
            <span v-if="item.weight !== null">{{ locale.number(item.weight) }}Kg</span>
            <FontAwesomeIcon
                v-else
                v-tooltip="ctrans('Unknown weight')"
                icon="fas fa-exclamation-circle"
                class="text-orange-500"
                fixed-width aria-hidden="true"
            />
        </template>

        <template #cell(volume)="{ item }">
            <span v-if="item.volume !== null">{{ locale.number(item.volume) }} m³</span>
            <FontAwesomeIcon
                v-else
                v-tooltip="ctrans('Unknown CBM')"
                icon="fas fa-exclamation-circle"
                class="text-orange-500"
                fixed-width aria-hidden="true"
            />
        </template>

        <template #cell(amount)="{ item }">
            {{ amount(item) }}
        </template>

        <template #cell(state)="{ item }">
            <div class="flex items-center gap-1.5">
                 <FontAwesomeIcon
                    v-if="item.state_icon"
                    v-tooltip="item.state_icon.tooltip"
                    :icon="item.state_icon.icon"
                    :class="item.state_icon.class"
                    aria-hidden="true"
                    fixed-width
                />
            </div>
        </template>

        <template #cell(delivery_state)="{ item }">
            <div class="flex justify-center items-center gap-1.5">
                <FontAwesomeIcon
                    v-tooltip="item.delivery_state_icon?.tooltip"
                    :icon="item.delivery_state_icon?.icon"
                    :class="item.delivery_state_icon?.class"
                    aria-hidden="true"
                    fixed-width
                />
            </div>
        </template>
    </Table>

    <ConfirmPopup />

    <ConfirmPopup group="pack-item">
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="w-72 p-3 text-sm">
                <div class="flex gap-2 text-gray-700">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
                    <p>{{ message.message }}</p>
                </div>
                <div class="mt-3 flex justify-end gap-2">
                    <Button type="negative" size="xs" :label="message.rejectLabel" @click="rejectCallback" />
                    <Button type="primary" size="xs" :label="message.acceptLabel" @click="acceptCallback" />
                </div>
            </div>
        </template>
    </ConfirmPopup>

    <PurchaseOrderLineAddToContainerDialog
        v-if="containerDialogItems.length"
        v-model:visible="isContainerDialogVisible"
        :items="containerDialogItems"
        @added="onAddedToContainer"
    />

    <ProductImageViewerDialog v-if="viewedImages.length" v-model:visible="isImageViewerVisible" :images="viewedImages" />

    <Popover ref="imagePreviewPopover" class="pointer-events-none">
        <Image v-if="imagePreview" :src="imagePreview" class="flex h-80 w-80 items-center justify-center [&>img]:max-h-full [&>img]:max-w-full" />
    </Popover>
</template>
