<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 17 Jul 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, reactive, watch, inject, nextTick } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { debounce, type DebouncedFunc } from 'lodash-es'
import Table from '@/Components/Table/Table.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import LabelPickingLocation from '@/Components/Warehouse/DeliveryNotes/LabelPickingLocation.vue'
import SelectPickingLocation from '@/Components/Warehouse/DeliveryNotes/SelectPickingLocation.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import StockDeliveryItemBatches from '@/Components/Procurement/StockDeliveryItemBatches.vue'
import { routeType } from '@/types/route'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faBox, faSpellCheck, faBoxCheck, faTrashAlt, faClipboardList, faSeedling, faTruck, faCheck, faClipboardCheck, faCheckDouble, faTimesCircle, faSave, faUndoAlt, faHandHoldingBox, faBoxOpen, faTimes, faExclamationCircle as falExclamationCircle } from '@fal'
import { faExclamationCircle, faSpinner } from '@fas'
import ConfirmPopup from 'primevue/confirmpopup'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import { Dialog } from 'primevue'
import { useConfirm } from 'primevue/useconfirm'
import { ctrans, ctransChoice } from '@/Composables/useTrans'

library.add(faInventory, faBox, faSpellCheck, faBoxCheck, faTrashAlt, faExclamationCircle, faSpinner, faClipboardList, faSeedling, faTruck, faCheck, faClipboardCheck, faCheckDouble, faTimesCircle, faSave, faUndoAlt, faHandHoldingBox, faBoxOpen, faTimes, falExclamationCircle)

import DiscontinueSuggestion from "@/Components/Procurement/DiscontinueSuggestion.vue"
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
const props = defineProps<{
    data: { data?: any[], container_lines?: { id: number, code: string | null, packed_inside_stock_delivery_item_id: number | null }[] },
    tab?: string,
    costing?: {
        is_costed: boolean
        can_edit: boolean
        currency: string | null
        distributeExtraCostRoute: routeType | null
    }
}>()

const locale = useLocaleStore()
const confirm = useConfirm()
const screenType = inject('screenType', ref('desktop'))

const changingId = ref<number | null>(null)

function reloadStockDelivery(): Promise<void> {
    return new Promise(resolve => router.reload({
        only: [props.tab ?? 'items', 'timelines', 'stock_delivery', 'box_stats', 'pageHead', 'tabs', 'queryBuilderProps', 'costing'],
        onFinish: () => resolve(),
    }))
}

function confirmChangeState(event: MouseEvent, item: any, stateRoute: any, message: string, acceptLabel: string, body: Record<string, unknown> = {}, isDestructive = false) {
    if (!stateRoute) {
        return
    }

    confirm.require({
        group: 'stock-delivery-items',
        target: event.currentTarget as HTMLElement,
        message,
        acceptProps: { label: acceptLabel, severity: isDestructive ? 'danger' : 'success' },
        rejectProps: { label: ctrans('Cancel') },
        accept: () => changeState(item, stateRoute, body),
    })
}

async function changeState(item: any, stateRoute: any, body: Record<string, unknown> = {}) {
    if (!stateRoute) {
        return
    }

    changingId.value = item.id
    try {
        const method = String(stateRoute.method ?? 'patch').toLowerCase()
        await axios[method](route(stateRoute.name, stateRoute.parameters), body)
        notify({ title: ctrans('Success'), text: ctrans('Item state updated'), type: 'success' })
        reloadStockDelivery()
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to update item state'),
            type: 'error',
        })
    } finally {
        changingId.value = null
    }
}

const _table = ref<InstanceType<typeof Table> | null>(null)
const selectedRows = ref<Record<string, boolean>>({})
const removingIds = ref<number[]>([])

const tableRows = computed<any[]>(() => props.data?.data ?? [])
const hasRemovableRows = computed(() => tableRows.value.some(item => item.removeRoute))
const rowsSelectedForRemoval = computed(() => tableRows.value.filter(item => selectedRows.value[item.id] && item.removeRoute))

function onSelectRow(rows: Record<string, boolean>) {
    selectedRows.value = { ...rows }
}

function removeFromContainer(items: any[]) {
    const removeRoute = items[0]?.removeRoute
    if (!removeRoute) {
        return
    }

    router.delete(route(removeRoute.name, removeRoute.parameters), {
        data: { items: items.map(item => item.id) },
        preserveScroll: true,
        onStart: () => (removingIds.value = items.map(item => item.id)),
        onFinish: () => (removingIds.value = []),
        onSuccess: () => {
            _table.value?.clearSelection()
            selectedRows.value = {}
            notify({
                title: ctrans('Success'),
                text: items.length === 1
                    ? ctrans(':code removed from the container', { code: items[0].code })
                    : ctransChoice(':count item removed from the container|:count items removed from the container', items.length),
                type: 'success',
            })
        },
        onError: (errors: Record<string, string>) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors ?? {})[0], type: 'error' }),
    })
}

function confirmRemoveFromContainer(event: MouseEvent, items: any[]) {
    if (!items.length) {
        return
    }

    confirm.require({
        group: 'stock-delivery-items',
        target: event.currentTarget as HTMLElement,
        message: items.length === 1
            ? ctrans('Take the :units units of :code out of this container? They go back to awaiting delivery on their purchase order.', { units: formatQuantity(Number(items[0].unit_quantity)), code: items[0].code })
            : ctransChoice('Take :count item out of this container? Its units go back to awaiting delivery on their purchase order.|Take :count items out of this container? Their units go back to awaiting delivery on their purchase orders.', items.length),
        acceptProps: { label: ctrans('Remove'), severity: 'danger' },
        rejectProps: { label: ctrans('Cancel') },
        accept: () => removeFromContainer(items),
    })
}

function formatQuantity(value: number) {
    return locale.number(Math.round(value * 1000) / 1000)
}

function skosPerCarton(item: any) {
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return carton / pack
}

function supplierUnitQuantity(item: any, units: number) {
    return item.supplier_unit ? ` | ${formatQuantity(units / (Number(item.units_per_supplier_unit) || 1))} ${item.supplier_unit}` : ''
}

function quantityBreakdown(item: any) {
    const units = Number(item.unit_quantity)
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko. | ${formatQuantity(units / carton)}C.${supplierUnitQuantity(item, units)}`
}

function differenceClass(value: number | null) {
    if (value === null || Number(value) === 0) {
        return ''
    }

    return Number(value) < 0 ? 'text-red-500' : 'text-orange-500'
}

function checkedQuantityBreakdown(item: any) {
    return unitsBreakdown(item, Number(item.unit_quantity_checked))
}

function placedQuantityBreakdown(item: any) {
    return unitsBreakdown(item, Number(item.unit_quantity_placed))
}

function unitsBreakdown(item: any, units: number) {
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko. | ${formatQuantity(units / carton)}C.${supplierUnitQuantity(item, units)}`
}

function amount(item: any) {
    const net = locale.currencyFormat(item.net_currency ?? 'EUR', item.net_amount ?? 0)

    if (item.org_net_amount === null || item.org_currency === item.net_currency) {
        return `${net}`
    }

    return `${net} (${locale.currencyFormat(item.org_currency ?? 'EUR', item.org_net_amount)})`
}

function supplierProductRoute(item: { slug?: string }) {
    if (!item.slug) {
        return ''
    }

    return route('grp.supply-chain.supplier_products.show', [item.slug])
}

function orgStockRoute(item: { org_stock_id?: number }) {
    if (!item.org_stock_id) {
        return ''
    }

    return route('grp.majordomo.redirect_org_stock', [item.org_stock_id])
}

function onCheckedSaved() {
    reloadStockDelivery()
}

const selectedLocationCode = reactive<Record<number, string | null>>({})
const isModalLocation = ref(false)
const selectedOtherLocation = reactive<Record<number, { id: number, code: string } | null>>({})
const selectedItemValue = ref<any>(null)
const otherLocationItemId = ref<number | null>(null)
const otherLocationSelect = ref<InstanceType<typeof PureMultiselectInfiniteScroll> | null>(null)

async function startOtherLocation(item: any) {
    otherLocationItemId.value = item.id
    await nextTick()
    otherLocationSelect.value?.multiselectRef?.focus()
    otherLocationSelect.value?.multiselectRef?.open()
}

function selectOtherLocation(item: any, location: { id: number, code: string } | null) {
    if (!location) {
        return
    }
    selectedOtherLocation[item.id] = { id: location.id, code: location.code }
    otherLocationItemId.value = null
}

function findLocation(locationsList: any[], locationCode: string | null) {
    return locationsList?.find(location => location.location_code == locationCode) || locationsList?.[0]
}

const setAsPickingLocationChoice = reactive<Record<number, boolean>>({})

function isOnlyLocationOfSko(item: any) {
    const locationsCount = item.locations?.length ?? 0
    return selectedOtherLocation[item.id] ? locationsCount === 0 : locationsCount === 1
}

function isSetAsPickingLocation(item: any) {
    return !item.has_picking_location && (setAsPickingLocationChoice[item.id] ?? isOnlyLocationOfSko(item))
}

function placedAdditionalData(item: any) {
    const pickingLocationData = isSetAsPickingLocation(item) ? { set_as_picking_location: true } : {}
    if (selectedOtherLocation[item.id]) {
        return { location_id: selectedOtherLocation[item.id]?.id, ...pickingLocationData }
    }
    return { location_org_stock_id: findLocation(item.locations, selectedLocationCode[item.id] ?? null)?.id, ...pickingLocationData }
}

function sowingLocationRoute(sowing: any, item: any) {
    const warehouse = sowing.warehouse_slug || item.warehouse_slug

    if (!sowing.location_slug || !warehouse) {
        return ''
    }

    return route('grp.org.warehouses.show.infrastructure.locations.show', [
        route().params['organisation'],
        warehouse,
        sowing.location_slug,
    ])
}

const costFields = ['cost_items', 'cost_extra', 'cost_shipping', 'cost_duties', 'cost_tax'] as const

const costDraft = reactive<Record<number, Record<string, number>>>({})
const savingCostId = ref<number | null>(null)

watch(() => props.data?.data, (items) => {
    for (const id of Object.keys(costDraft)) {
        delete costDraft[Number(id)]
    }

    for (const item of items ?? []) {
        if (!item.updateCostRoute || props.costing?.can_edit === false) {
            continue
        }

        costDraft[item.id] = Object.fromEntries(costFields.map(field => [field, Number(item[field] ?? 0)]))
    }
}, { immediate: true })

function money(item: any, value: number | string | null) {
    return locale.currencyFormat(item.currency ?? props.costing?.currency ?? 'EUR', Number(value ?? 0))
}

function isShortOrOver(item: any) {
    return Number(item.unit_quantity) > 0 && item.unit_quantity_placed != null && Number(item.unit_quantity_placed) !== Number(item.unit_quantity)
}

function receivedShare(item: any) {
    return Number(item.unit_quantity_placed) / Number(item.unit_quantity)
}

function rowTotal(item: any) {
    const draft = costDraft[item.id]

    if (!draft) {
        return Number(item.cost_total ?? 0)
    }

    return costFields.reduce((total, field) => total + (Number(draft[field]) || 0), 0)
}

async function saveCost(item: any) {
    savingCostId.value = item.id

    try {
        await axios.patch(route(item.updateCostRoute.name, item.updateCostRoute.parameters), costDraft[item.id])
        notify({ title: ctrans('Success'), text: ctrans('Item costs updated'), type: 'success' })
        reloadStockDelivery()
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to update item costs'),
            type: 'error',
        })
    } finally {
        savingCostId.value = null
    }
}

const isSavingAllCosts = ref(false)

function changedCostItems() {
    return (props.data?.data ?? []).filter(item =>
        item.updateCostRoute && costDraft[item.id]
        && costFields.some(field => Number(costDraft[item.id][field]) !== Number(item[field] ?? 0))
    )
}

async function saveAllCosts() {
    const items = changedCostItems()

    if (!items.length) {
        notify({ title: ctrans('Nothing to save'), text: ctrans('No item costs were changed'), type: 'info' })
        return
    }

    isSavingAllCosts.value = true
    let savedCount = 0

    try {
        for (const item of items) {
            await axios.patch(route(item.updateCostRoute.name, item.updateCostRoute.parameters), costDraft[item.id])
            savedCount++
        }
        notify({ title: ctrans('Success'), text: ctrans(':count items costs updated', { count: String(savedCount) }), type: 'success' })
        reloadStockDelivery()
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to update item costs'),
            type: 'error',
        })
    } finally {
        isSavingAllCosts.value = false
    }
}

const cbmInContainerDraft = reactive<Record<number, number | null>>({})
const cbmInContainerSaved = reactive<Record<number, { value: number | null, isSet: boolean }>>({})
const savingCbmInContainerId = ref<number | null>(null)
const cbmInContainerSaves = new Map<number, DebouncedFunc<() => void>>()

watch(() => props.data?.data, (items) => {
    cbmInContainerSaves.forEach(save => save.cancel())
    cbmInContainerSaves.clear()

    for (const id of Object.keys(cbmInContainerDraft)) {
        delete cbmInContainerDraft[Number(id)]
        delete cbmInContainerSaved[Number(id)]
    }

    for (const item of items ?? []) {
        if (item.cbmInContainerRoute) {
            const value = item.cbm_in_container === null ? null : Number(item.cbm_in_container)
            cbmInContainerDraft[item.id] = value
            cbmInContainerSaved[item.id] = { value, isSet: item.is_cbm_in_container_set }
        }
    }
}, { immediate: true })

function isCbmInContainerChanged(item: any, value: number | null) {
    const saved = cbmInContainerSaved[item.id]

    if (value === null) {
        return saved.isSet
    }

    return !saved.isSet || saved.value === null || Number(value) !== Number(saved.value)
}

function queueCbmInContainerSave(item: any, value: number | null) {
    cbmInContainerDraft[item.id] = value

    if (!cbmInContainerSaves.has(item.id)) {
        cbmInContainerSaves.set(item.id, debounce(() => saveCbmInContainer(item, cbmInContainerDraft[item.id] ?? null), 800))
    }

    cbmInContainerSaves.get(item.id)?.()
}

function flushCbmInContainerSave(item: any) {
    cbmInContainerSaves.get(item.id)?.flush()
}

async function saveCbmInContainer(item: any, value: number | null) {
    cbmInContainerSaves.get(item.id)?.cancel()

    const itemCbm = item.volume === null ? null : Number(item.volume)
    if (value !== null && value === itemCbm) {
        value = null
    }

    if (!item.cbmInContainerRoute || !isCbmInContainerChanged(item, value)) {
        return
    }

    savingCbmInContainerId.value = item.id

    try {
        await axios.patch(route(item.cbmInContainerRoute.name, item.cbmInContainerRoute.parameters), { cbm_in_container: value })
        cbmInContainerSaved[item.id] = { value: value ?? itemCbm, isSet: value !== null }
        if (value === null) {
            cbmInContainerDraft[item.id] = itemCbm
        }
        router.reload({ only: ['box_stats'] })
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to update the CBM in container'),
            type: 'error',
        })
    } finally {
        savingCbmInContainerId.value = null
    }
}

const containerLines = computed(() => props.data?.container_lines ?? [])

const lineCodeById = computed(() => new Map(containerLines.value.map(line => [line.id, line.code ?? '-'])))

function packedInsideCode(item: any) {
    return item.packed_inside_stock_delivery_item_id ? lineCodeById.value.get(item.packed_inside_stock_delivery_item_id) ?? null : null
}

function packedItemCodes(item: any) {
    return containerLines.value
        .filter(line => line.packed_inside_stock_delivery_item_id === item.id)
        .map(line => line.code ?? '-')
}

function outerLineOptions(item: any) {
    return containerLines.value
        .filter(line => line.id !== item.id && !line.packed_inside_stock_delivery_item_id)
        .map(line => ({ label: line.code ?? '-', value: line.id }))
}

const savingPackedInsideId = ref<number | null>(null)

async function savePackedInside(item: any, outerItemId: number | null) {
    if (!item.packedInsideRoute) {
        return
    }

    savingPackedInsideId.value = item.id

    try {
        await axios.patch(route(item.packedInsideRoute.name, item.packedInsideRoute.parameters), { packed_inside_stock_delivery_item_id: outerItemId })
        await reloadStockDelivery()
        notify({
            title: ctrans('Success'),
            text: outerItemId ? ctrans(':code is packed inside :outer', { code: item.code, outer: lineCodeById.value.get(outerItemId) ?? '' }) : ctrans(':code is not packed inside another product', { code: item.code }),
            type: 'success',
        })
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to change what the product is packed inside'),
            type: 'error',
        })
    } finally {
        savingPackedInsideId.value = null
    }
}

</script>

<template>
    <Table
        ref="_table"
        :resource="data"
        :name="tab"
        class="mt-5"
        :isCheckBox="hasRemovableRows"
        checkboxKey="id"
        :disabledCheckbox="(item: any) => !item.removeRoute"
        @onSelectRow="onSelectRow"
    >
        <template #before-table>
            <div v-if="rowsSelectedForRemoval.length" class="flex flex-wrap items-center gap-3 px-6 py-3">
                <Button
                    :label="ctransChoice('Remove :count item from container|Remove :count items from container', rowsSelectedForRemoval.length)"
                    :tooltip="ctrans('Take the selected items out of this container, their units go back to awaiting delivery')"
                    type="tertiary"
                    size="xs"
                    class="ml-auto"
                    :loading="removingIds.length > 1"
                    :disabled="removingIds.length > 0"
                    @click="confirmRemoveFromContainer($event, rowsSelectedForRemoval)"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <div v-if="costing?.can_edit" class="flex flex-wrap items-center gap-3 px-6 py-3">
                <Button
                    :label="ctrans('Save all')"
                    :tooltip="ctrans('Save the costs of every changed item')"
                    type="tertiary"
                    size="xs"
                    class="ml-auto"
                    :loading="isSavingAllCosts"
                    :disabled="isSavingAllCosts || savingCostId !== null"
                    @click="saveAllCosts"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>

        <template #cell(units_in)="{ item }">
            <Button
                v-if="item.receivedAfterAllRoute"
                :label="ctrans('Received after all')"
                :tooltip="ctrans('Check the delivered quantity and take the delivery back to booking in')"
                icon="fal fa-check"
                type="secondary"
                size="xs"
                :loading="changingId === item.id"
                :disabled="changingId === item.id"
                @click="confirmChangeState($event, item, item.receivedAfterAllRoute, ctrans('Check :code as received? The delivery goes back to booking in so it can be placed.', { code: item.code }), ctrans('Received'))"
            />
            <span v-else class="text-gray-500">{{ formatQuantity(Number(item.unit_quantity_placed)) }}</span>
        </template>

        <template v-for="field in costFields" :key="field" #[`cell(${field})`]="{ item }">
            <input
                v-if="costDraft[item.id]"
                v-model="costDraft[item.id][field]"
                type="number"
                step="0.01"
                min="0"
                class="border border-gray-300 rounded text-sm py-1 px-2 w-28"
            />
            <span v-else>{{ money(item, item[field]) }}</span>
        </template>

        <template #cell(cost_total)="{ item }">
            <span class="font-semibold text-gray-700">{{ money(item, rowTotal(item)) }}</span>
            <div
                v-if="isShortOrOver(item)"
                class="whitespace-nowrap text-xs text-gray-500"
                v-tooltip="ctrans('Costs are for the :ordered ordered; only the units that arrived go into stock, each at the same cost per unit', { ordered: formatQuantity(Number(item.unit_quantity)) })"
            >
                {{ ctrans(':placed in stock: items :items · total :total', {
                    placed: formatQuantity(Number(item.unit_quantity_placed)),
                    items: money(item, Number(item.cost_items ?? 0) * receivedShare(item)),
                    total: money(item, rowTotal(item) * receivedShare(item)),
                }) }}
            </div>
        </template>

        <template #cell(cost_per_sko_org)="{ item }">
            <span v-if="item.cost_per_sko_org !== null" class="font-semibold text-gray-700 tabular-nums">{{ locale.currencyFormat(item.org_currency ?? 'GBP', Number(item.cost_per_sko_org)) }}</span>
        </template>

        <template #cell(code)="{ item }">
            <div class="flex items-center gap-1.5">
                <Link
                    v-if="supplierProductRoute(item)"
                    v-tooltip="ctrans('Supplier product code')"
                    :href="supplierProductRoute(item)"
                    class="primaryLink"
                >
                    {{ item.code }}
                </Link>
                <span v-else>{{ item.code }}</span>

                <Link
                    v-if="orgStockRoute(item)"
                    v-tooltip="ctrans('Part reference is same as supplier product code')"
                    :href="orgStockRoute(item)"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <FontAwesomeIcon icon="fal fa-box" aria-hidden="true" fixed-width />
                </Link>

                <span v-if="item.is_new_org_stock"
                    v-tooltip="ctrans('Never been in stock')"
                    class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    {{ ctrans('New') }}
                </span>
            </div>
        </template>

        <template #cell(description)="{ item }">
            <div class="space-y-0.5">
                <div>{{ item.name }}</div>
                <div v-if="packedInsideCode(item)" class="flex w-fit items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
                    <FontAwesomeIcon icon="fal fa-box-open" fixed-width aria-hidden="true" />
                    {{ ctrans('Packed inside :code, takes no space of its own', { code: packedInsideCode(item) }) }}
                </div>
                <div v-if="packedItemCodes(item).length" class="flex w-fit items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
                    <FontAwesomeIcon icon="fal fa-box-open" fixed-width aria-hidden="true" />
                    {{ ctrans('Contains: :codes', { codes: packedItemCodes(item).join(', ') }) }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ ctrans('Packed in') }} {{ formatQuantity(Number(item.units_per_pack) || 1) }}s ,
                    {{ ctrans('sko/C') }}: {{ formatQuantity(skosPerCarton(item)) }}
                </div>
                <div v-if="item.locations?.length" class="mt-1 w-fit min-w-40 divide-y divide-gray-100 border-t border-gray-100 text-xs">
                    <div v-for="location in item.locations" :key="location.id" class="flex justify-between gap-x-6 py-0.5">
                        <Link
                            v-if="sowingLocationRoute(location, item)"
                            :href="sowingLocationRoute(location, item)"
                            class="secondaryLink"
                        >
                            {{ location.location_code }}
                        </Link>
                        <span v-else>{{ location.location_code }}</span>
                        <span class="text-gray-500">{{ formatQuantity(Number(location.quantity)) }}</span>
                    </div>
                </div>
                <div v-else-if="item.org_stock_id" class="mt-1 text-xs italic text-red-500">{{ ctrans('No location yet') }}</div>
                <DiscontinueSuggestion v-if="item.org_stock_id" :orgStockId="item.org_stock_id" :code="item.org_stock_code ?? item.code" source="goods_in" :label="ctrans('Report a bad product')" />
            </div>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="text-gray-500">{{ quantityBreakdown(item) }}</span>
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
            <div class="grid w-max grid-cols-[auto_auto] items-center gap-x-2 gap-y-1">
                <span class="text-xs text-gray-500">{{ ctrans('Item') }}</span>
                <span class="whitespace-nowrap tabular-nums">
                    <template v-if="item.volume !== null">{{ locale.number(item.volume) }} m³</template>
                    <FontAwesomeIcon
                        v-else
                        v-tooltip="ctrans('Unknown CBM')"
                        icon="fas fa-exclamation-circle"
                        class="text-orange-500"
                        fixed-width aria-hidden="true"
                    />
                </span>

                <template v-if="packedInsideCode(item) || item.cbmInContainerRoute || item.is_cbm_in_container_set">
                    <span class="text-xs text-gray-500">{{ ctrans('In container') }}</span>
                    <span v-if="packedInsideCode(item)" class="whitespace-nowrap text-xs tabular-nums text-amber-700">0 m³</span>
                    <div v-else-if="item.cbmInContainerRoute" class="flex items-center gap-1">
                        <InputNumber
                            :modelValue="cbmInContainerDraft[item.id]"
                            :aria-label="ctrans('CBM in container')"
                            :min="0"
                            :maxFractionDigits="4"
                            suffix=" m³"
                            size="small"
                            :placeholder="item.volume !== null ? `${locale.number(item.volume)} m³` : undefined"
                            inputClass="w-24 text-right"
                            @input="(event: { value: number | string | null }) => queueCbmInContainerSave(item, typeof event.value === 'number' ? event.value : null)"
                            @blur="flushCbmInContainerSave(item)"
                            @keydown.enter="flushCbmInContainerSave(item)"
                        />
                        <LoadingIcon v-if="savingCbmInContainerId === item.id" />
                        <Button
                            v-else-if="cbmInContainerSaved[item.id]?.isSet"
                            :tooltip="ctrans('Use the CBM of the item again')"
                            type="tertiary"
                            size="xs"
                            :loading="savingCbmInContainerId === item.id"
                            :disabled="savingCbmInContainerId === item.id"
                            @click="saveCbmInContainer(item, null)"
                            icon="fal fa-undo-alt"
                        >
                            <!-- <template #icon>
                                <FontAwesomeIcon icon="fal fa-undo-alt" class="text-gray-500" fixed-width aria-hidden="true" />
                            </template> -->
                        </Button>
                    </div>
                    <span v-else class="whitespace-nowrap tabular-nums">{{ locale.number(item.cbm_in_container) }} m³</span>
                </template>

                <template v-if="item.packedInsideRoute && !packedItemCodes(item).length && (item.packed_inside_stock_delivery_item_id || outerLineOptions(item).length)">
                    <span class="text-xs text-gray-500">{{ ctrans('Packed inside') }}</span>
                    <Select
                        :modelValue="item.packed_inside_stock_delivery_item_id"
                        :options="outerLineOptions(item)"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="ctrans('None')"
                        :aria-label="ctrans('Packed inside another product')"
                        filter
                        showClear
                        size="small"
                        :loading="savingPackedInsideId === item.id"
                        :disabled="savingPackedInsideId === item.id"
                        class="w-40"
                        @update:modelValue="(value: number | null) => savePackedInside(item, value ?? null)"
                    />
                </template>
            </div>
        </template>

        <template #cell(amount)="{ item }">
            {{ amount(item) }}
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex justify-end items-center gap-2">
                <Button
                    v-if="item.updateCostRoute && costDraft[item.id]"
                    :label="ctrans('Save')"
                    :tooltip="ctrans('Save the costs of this item')"
                    type="tertiary"
                    size="xs"
                    :loading="savingCostId === item.id"
                    :disabled="savingCostId === item.id"
                    @click="saveCost(item)"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-save" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>

                <Button
                    v-else-if="item.confirmRoute"
                    :label="ctrans('Confirm')"
                    :tooltip="ctrans('Step 1 before shipping: confirm this item goes in the container')"
                    icon="fal fa-spell-check"
                    type="positive"
                    size="xs"
                    :loading="changingId === item.id"
                    :disabled="changingId === item.id"
                    @click="confirmChangeState($event, item, item.confirmRoute, ctrans('Confirm this item?'), ctrans('Confirm'))"
                />

                <Button
                    v-else-if="item.readyToShipRoute"
                    :label="ctrans('Ready to ship')"
                    :tooltip="ctrans('Step 2 before shipping: this item is packed and ready to dispatch')"
                    icon="fal fa-box-check"
                    type="secondary"
                    size="xs"
                    :loading="changingId === item.id"
                    :disabled="changingId === item.id"
                    @click="confirmChangeState($event, item, item.readyToShipRoute, ctrans('Set this item as ready to ship?'), ctrans('Ready to ship'))"
                />

                <Button
                    v-if="item.removeRoute"
                    :tooltip="ctrans('Take this item out of the container, its units go back to awaiting delivery')"
                    type="tertiary"
                    size="xs"
                    :loading="removingIds.length === 1 && removingIds.includes(item.id)"
                    :disabled="removingIds.length > 0"
                    @click="confirmRemoveFromContainer($event, [item])"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>

                <span v-if="!item.updateCostRoute && !item.confirmRoute && !item.readyToShipRoute && !item.removeRoute" class="text-gray-400 text-sm">
                    {{ ctrans('No actions needed') }}
                </span>
            </div>
        </template>

        <template #cell(part)="{ item }">
            <div class="flex items-center gap-1.5">
                <Link
                    v-if="orgStockRoute(item)"
                    v-tooltip="ctrans('Part reference')"
                    :href="orgStockRoute(item)"
                    class="primaryLink"
                >
                    {{ item.org_stock_code }}
                </Link>
                <span v-else>{{ item.org_stock_code }}</span>
            </div>
        </template>

        <template #cell(delivered_quantity)="{ item }">
            <span class="text-gray-500">{{ quantityBreakdown(item) }}</span>
        </template>

        <template #cell(checked_quantity)="{ item }">
            <span class="text-gray-500">{{ checkedQuantityBreakdown(item) }}</span>
        </template>

        <template #cell(placed_quantity)="{ item }">
            <span class="text-gray-500">{{ placedQuantityBreakdown(item) }}</span>
        </template>

        <template #cell(difference_percentage)="{ item }">
            <span :class="differenceClass(item.difference_percentage)">
                {{ item.difference_percentage === null ? '-' : `${locale.number(item.difference_percentage)}%` }}
            </span>
        </template>

        <template #cell(difference_units)="{ item }">
            <span :class="differenceClass(item.difference_units)">{{ formatQuantity(Number(item.difference_units)) }}</span>
        </template>

        <template #cell(difference_amount)="{ item }">
            <span :class="differenceClass(item.difference_amount)">
                {{ item.difference_amount === null ? '-' : locale.currencyFormat(item.currency_code ?? 'EUR', Number(item.difference_amount)) }}
            </span>
        </template>

        <template #cell(difference_skos)="{ item }">
            <span :class="differenceClass(item.difference_skos)">
                {{ item.difference_skos === null ? '-' : formatQuantity(Number(item.difference_skos)) }}
            </span>
        </template>

        <template #cell(checked_unit)="{ item, proxyItem }">
            <div class="grid justify-items-end gap-y-2" v-if="item.checkedRoute">
                <NumberWithButtonSave
                    noUndoButton
                    @onError="(error: any) => {
                        proxyItem.errors = Object.values(error || {})
                    }"
                    :modelValue="Number(item.sko_quantity_checked)"
                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                    saveOnForm
                    isUseAxios
                    isWithRefreshModel
                    :routeSubmit="item.checkedRoute"
                    keySubmit="sko_quantity_checked"
                    :bindToTarget="{
                        step: 1,
                        min: Number(item.sko_quantity_placed)
                    }"
                    autoSave
                    @onSuccess="onCheckedSaved"
                >
                    <template #save="{ isProcessing }">
                        <div class="flex gap-x-8 w-fit">
                            <ButtonWithLink
                                v-tooltip="ctrans('Check all the delivered quantity')"
                                icon="fal fa-check"
                                :size="screenType != 'mobile' ? 'xs' : 'md'"
                                type="positive"
                                :loading="isProcessing"
                                class="py-0"
                                :routeTarget="item.checkAllRoute"
                                :bind-to-link="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                isWithError
                            >
                                <template #label>
                                    <div>
                                        {{ formatQuantity(Number(item.sko_quantity)) }}
                                    </div>
                                </template>
                            </ButtonWithLink>
                            <Button
                                v-if="item.state === 'received'"
                                v-tooltip="ctrans('Not received')"
                                icon="fal fa-times"
                                :size="screenType != 'mobile' ? 'xs' : 'md'"
                                type="negative"
                                :loading="isProcessing || changingId === item.id"
                                class="py-0"
                                @click="confirmChangeState($event, item, item.checkedRoute, ctrans('Mark :code as not received? The delivery closes without it once everything else is booked in.', { code: item.code }), ctrans('Not received'), { sko_quantity_checked: 0 }, true)"
                            />
                        </div>
                    </template>
                </NumberWithButtonSave>
                <StockDeliveryItemBatches
                    v-if="item.batchesRoute || item.batches?.length || item.is_batch_tracked"
                    :batches="item.batches ?? []"
                    :checkedSkos="Number(item.sko_quantity_checked)"
                    :isBatchTracked="item.is_batch_tracked"
                    :route="item.batchesRoute"
                    @saved="reloadStockDelivery" />
            </div>
            <div v-else class="grid justify-items-end gap-y-1">
                <span>{{ formatQuantity(Number(item.sko_quantity_checked)) }}</span>
                <StockDeliveryItemBatches
                    v-if="item.batches?.length"
                    :batches="item.batches"
                    :checkedSkos="Number(item.sko_quantity_checked)"
                    :isBatchTracked="item.is_batch_tracked"
                    :route="null" />
            </div>
        </template>

        <template #cell(sowings)="{ item }">
            <div v-if="item.sowings?.length" class="space-y-1 grid pt-2">
                <div v-for="sowing in item.sowings" :key="sowing.id" class="flex gap-x-2 w-fit">
                    <div class="flex gap-x-2 items-center flex-wrap">
                        <Link
                            v-if="!!sowingLocationRoute(sowing, item)"
                            :href="sowingLocationRoute(sowing, item)"
                            class="secondaryLink"
                        >
                            {{ sowing.location_code }}
                        </Link>
                        <span v-else>
                            {{ sowing.location_code ?? ctrans('Unknown') }}
                        </span>
                        <div v-tooltip="ctrans('Total placed quantity in this location')" class="text-gray-500 whitespace-nowrap">
                            <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr-1 text-gray-500" fixed-width aria-hidden="true" />
                            {{ formatQuantity(Number(sowing.quantity)) }}
                        </div>
                    </div>

                    <div v-if="item.is_editable">
                        <ButtonWithLink
                            v-if="sowing.quantity"
                            v-tooltip="ctrans('Undo sowing :qtyPicked items', { qtyPicked: Number(sowing.quantity).toString() })"
                            type="negative"
                            :size="screenType != 'mobile' ? 'xxs' : 'md'"
                            :icon="faUndoAlt"
                            :routeTarget="sowing.undo_sowing_route"
                            :bindToLink="{ preserveScroll: true }"
                        />
                    </div>
                </div>
            </div>
            <span v-else>
            </span>
        </template>

        <template #cell(placement)="{ item, proxyItem }">
            <div class="grid justify-items-end gap-y-2" v-if="item.has_available_qty && item.placedRoute">
                <NumberWithButtonSave
                    :key="`placement-${item.id}-${item.unit_quantity_placed}-${item.sowings?.length ?? 0}`"
                    noUndoButton
                    @onError="(error: any) => {
                        proxyItem.errors = Object.values(error || {})
                    }"
                    :modelValue="0"
                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                    saveOnForm
                    isUseAxios
                    :routeSubmit="item.placedRoute"
                    keySubmit="quantity"
                    :bindToTarget="{
                        step: 1,
                        min: 0,
                        max: Number(item.placement_remaining)
                    }"
                    :additionalData="placedAdditionalData(item)"
                    autoSave
                    @onSuccess="onCheckedSaved"
                >
                    <template #save="{ isProcessing }">
                        <div class="flex gap-x-8 w-fit">
                            <ButtonWithLink
                                v-tooltip="ctrans('Place all remaining quantity in location :xlocation', { xlocation: selectedOtherLocation[item.id]?.code || findLocation(item.locations, selectedLocationCode[item.id] ?? null)?.location_code || '-' })"
                                icon="fal fa-check"
                                :size="screenType != 'mobile' ? 'xs' : 'md'"
                                type="positive"
                                :loading="isProcessing"
                                class="py-0"
                                :routeTarget="item.placeAllRoute"
                                :body="placedAdditionalData(item)"
                                :bind-to-link="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                isWithError
                            >
                                <template #label>
                                    <div>
                                        {{ formatQuantity(Number(item.placement_remaining)) }}
                                    </div>
                                </template>
                            </ButtonWithLink>
                        </div>
                    </template>
                </NumberWithButtonSave>
                <div class="flex items-center gap-x-2">
                    <span v-if="selectedOtherLocation[item.id]" class="text-base bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 px-1">
                        {{ selectedOtherLocation[item.id]?.code }}
                    </span>
                    <LabelPickingLocation
                        v-else-if="item.locations?.length"
                        :locations="item.locations"
                        :selectedOrgStockId="selectedLocationCode[item.id] ?? null"
                        :warehouseArea="item.warehouse_area"
                        :warehouse_slug="item.warehouse_slug"
                        @openLocationModal="() => {
                            isModalLocation = true; selectedItemValue = item;
                        }"
                    />
                    <span v-else class="text-red-500 italic text-xs">{{ ctrans('No location yet') }}</span>
                    <Button
                        v-if="otherLocationItemId !== item.id || !item.searchLocationsRoute"
                        v-tooltip="ctrans('Book in to any location in the warehouse')"
                        :label="item.locations?.length || selectedOtherLocation[item.id] ? ctrans('Other location') : ctrans('Choose location')"
                        icon="fal fa-inventory"
                        :type="item.locations?.length || selectedOtherLocation[item.id] ? 'tertiary' : 'secondary'"
                        size="xs"
                        @click="() => item.searchLocationsRoute ? startOtherLocation(item) : (isModalLocation = true, selectedItemValue = item)"
                    />
                </div>
                <div v-if="otherLocationItemId === item.id && item.searchLocationsRoute" class="flex items-center gap-x-1 w-64" @keydown.esc="otherLocationItemId = null">
                    <PureMultiselectInfiniteScroll
                        :ref="(el: any) => { otherLocationSelect = el }"
                        :modelValue="selectedOtherLocation[item.id]?.id ?? null"
                        :fetchRoute="item.searchLocationsRoute"
                        labelProp="code"
                        valueProp="id"
                        fetchOnOpen
                        :placeholder="ctrans('Search location code')"
                        @selectedObject="(location: any) => selectOtherLocation(item, location)"
                    />
                    <button type="button" class="h-7 w-7 shrink-0 text-gray-400 hover:text-gray-600" :aria-label="ctrans('Cancel')" @click="otherLocationItemId = null">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </button>
                </div>
                <label
                    v-if="!item.has_picking_location && (item.locations?.length || selectedOtherLocation[item.id])"
                    class="flex items-center gap-1 cursor-pointer select-none text-xs text-amber-700"
                    v-tooltip="ctrans('This SKO has no picking location yet')"
                >
                    <input
                        :checked="isSetAsPickingLocation(item)"
                        @change="(event) => setAsPickingLocationChoice[item.id] = (event.target as HTMLInputElement).checked"
                        type="checkbox"
                        class="rounded border-gray-300"
                    />
                    {{ ctrans('Set as picking location') }}
                </label>
            </div>
            <span v-else-if="Number(item.sko_quantity_placed) > 0" class="text-green-500">
                {{ formatQuantity(Number(item.sko_quantity_placed)) }}
            </span>
            <span v-else>
            </span>
        </template>
    </Table>

    <Dialog
        v-model:visible="isModalLocation"
        modal
        :draggable="false"
        :dismissableMask="screenType === 'desktop'"
        :style="{ width: '48rem' }"
        :breakpoints="{ '1280px': '70vw', '992px': '80vw', '768px': '90vw', '576px': '95vw' }"
        :contentStyle="{ overflow: 'visible' }"
        :header="ctrans('Where to put :itemCode', { itemCode: selectedItemValue?.org_stock_code ?? '' })"
    >
        <SelectPickingLocation
            v-if="selectedItemValue?.locations?.length"
            :item="selectedItemValue"
            :selectedLocationCode="selectedLocationCode[selectedItemValue?.id] ?? null"
            @select="(code) => { selectedLocationCode[selectedItemValue?.id] = code; selectedOtherLocation[selectedItemValue?.id] = null; isModalLocation = false; }"
            :ignoreNoQty="true"
        />
        <div v-if="selectedItemValue?.searchLocationsRoute" class="mt-4">
            <div class="text-sm text-gray-700 mb-1">{{ selectedItemValue?.locations?.length ? ctrans('Or book in to any other location in the warehouse') : ctrans('Book in to any location in the warehouse') }}</div>
            <PureMultiselectInfiniteScroll
                :key="`other-location-${selectedItemValue?.id}`"
                :modelValue="selectedOtherLocation[selectedItemValue?.id]?.id ?? null"
                :fetchRoute="selectedItemValue.searchLocationsRoute"
                labelProp="code"
                valueProp="id"
                fetchOnOpen
                :placeholder="ctrans('Search location code')"
                @selectedObject="(location) => { if (location) { selectedOtherLocation[selectedItemValue?.id] = { id: location.id, code: location.code }; isModalLocation = false } }"
            />
        </div>
    </Dialog>

    <ConfirmPopup group="stock-delivery-items" :style="{ width: '24rem', maxWidth: 'calc(100vw - 2rem)' }">
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="p-4">
                <p class="text-sm text-gray-700">{{ message.message }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button type="tertiary" size="xs" :label="message.rejectProps?.label" @click="rejectCallback">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-undo-alt" class="text-gray-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button
                        type="tertiary"
                        size="xs"
                        :label="message.acceptProps?.label"
                        :class="message.acceptProps?.severity === 'danger' ? '!border-red-500 !text-red-600 hover:!bg-red-50' : ''"
                        @click="acceptCallback"
                    >
                        <template #icon>
                            <FontAwesomeIcon
                                :icon="message.acceptProps?.severity === 'danger' ? 'fal fa-times' : 'fal fa-check'"
                                :class="message.acceptProps?.severity === 'danger' ? 'text-red-600' : 'text-green-600'"
                                fixed-width
                                aria-hidden="true"
                            />
                        </template>
                    </Button>
                </div>
            </div>
        </template>
    </ConfirmPopup>
</template>
