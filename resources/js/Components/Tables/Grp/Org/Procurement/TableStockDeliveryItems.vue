<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 17 Jul 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref, reactive, watch, inject } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Table from '@/Components/Table/Table.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import LabelPickingLocation from '@/Components/Warehouse/DeliveryNotes/LabelPickingLocation.vue'
import SelectPickingLocation from '@/Components/Warehouse/DeliveryNotes/SelectPickingLocation.vue'
import { routeType } from '@/types/route'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faSpellCheck, faBoxCheck, faTrashAlt, faClipboardList, faSeedling, faTruck, faCheck, faClipboardCheck, faCheckDouble, faTimesCircle, faEquals, faDollarSign, faSave, faUndoAlt, faHandHoldingBox, faExclamationCircle as falExclamationCircle } from '@fal'
import { faExclamationCircle, faSpinner } from '@fas'
import ConfirmPopup from 'primevue/confirmpopup'
import { Dialog } from 'primevue'
import { useConfirm } from 'primevue/useconfirm'
import { ctrans } from '@/Composables/useTrans'

library.add(faBox, faSpellCheck, faBoxCheck, faTrashAlt, faExclamationCircle, faSpinner, faClipboardList, faSeedling, faTruck, faCheck, faClipboardCheck, faCheckDouble, faTimesCircle, faEquals, faDollarSign, faSave, faUndoAlt, faHandHoldingBox, falExclamationCircle)

const props = defineProps<{
    data: { data?: any[] },
    tab?: string,
    costing?: {
        is_costed: boolean
        currency: string | null
        distributeExtraCostRoute: routeType | null
    }
}>()

const locale = useLocaleStore()
const confirm = useConfirm()
const screenType = inject('screenType', ref('desktop'))

const changingId = ref<number | null>(null)

function reloadStockDelivery() {
    router.reload({
        only: [props.tab ?? 'items', 'timelines', 'stock_delivery', 'box_stats', 'pageHead', 'tabs', 'queryBuilderProps', 'costing'],
    })
}

function confirmChangeState(event: MouseEvent, item: any, stateRoute: any, message: string, acceptLabel: string) {
    if (!stateRoute) {
        return
    }

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel,
        rejectLabel: trans('Cancel'),
        acceptClass: 'p-button-success',
        rejectClass: 'p-button-text',
        accept: () => changeState(item, stateRoute),
    })
}

async function changeState(item: any, stateRoute: any) {
    if (!stateRoute) {
        return
    }

    changingId.value = item.id
    try {
        const method = String(stateRoute.method ?? 'patch').toLowerCase()
        await axios[method](route(stateRoute.name, stateRoute.parameters))
        notify({ title: trans('Success'), text: trans('Item state updated'), type: 'success' })
        reloadStockDelivery()
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to update item state'),
            type: 'error',
        })
    } finally {
        changingId.value = null
    }
}

function formatQuantity(value: number) {
    return locale.number(Math.round(value * 1000) / 1000)
}

function skosPerCarton(item: any) {
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return carton / pack
}

function quantityBreakdown(item: any) {
    const units = Number(item.unit_quantity)
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko. | ${formatQuantity(units / carton)}C.`
}

function differenceClass(value: number | null) {
    if (value === null || Number(value) === 0) {
        return ''
    }

    return Number(value) < 0 ? 'text-red-500' : 'text-orange-500'
}

function checkedQuantityBreakdown(item: any) {
    const units = Number(item.unit_quantity_checked)
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko. | ${formatQuantity(units / carton)}C.`
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
const selectedItemValue = ref<any>(null)

function findLocation(locationsList: any[], locationCode: string | null) {
    return locationsList?.find(location => location.location_code == locationCode) || locationsList?.[0]
}

function placedAdditionalData(item: any) {
    return { location_org_stock_id: findLocation(item.locations, selectedLocationCode[item.id] ?? null)?.id }
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
const extraCostToDistribute = ref<number>(0)
const distributingType = ref<string | null>(null)

watch(() => props.data?.data, (items) => {
    for (const id of Object.keys(costDraft)) {
        delete costDraft[Number(id)]
    }

    for (const item of items ?? []) {
        if (!item.updateCostRoute) {
            continue
        }

        costDraft[item.id] = Object.fromEntries(costFields.map(field => [field, Number(item[field] ?? 0)]))
    }
}, { immediate: true })

function money(item: any, value: number | string | null) {
    return locale.currencyFormat(item.currency ?? props.costing?.currency ?? 'EUR', Number(value ?? 0))
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
        notify({ title: trans('Success'), text: trans('Item costs updated'), type: 'success' })
        reloadStockDelivery()
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to update item costs'),
            type: 'error',
        })
    } finally {
        savingCostId.value = null
    }
}

async function distributeExtraCost(type: 'equally' | 'by_value') {
    const distributeRoute = props.costing?.distributeExtraCostRoute

    if (!distributeRoute) {
        return
    }

    distributingType.value = type

    try {
        await axios.patch(route(distributeRoute.name, distributeRoute.parameters), {
            amount: Number(extraCostToDistribute.value) || 0,
            type,
        })
        notify({ title: trans('Success'), text: trans('Extra costs distributed'), type: 'success' })
        reloadStockDelivery()
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to distribute the extra costs'),
            type: 'error',
        })
    } finally {
        distributingType.value = null
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #before-table>
            <div v-if="costing?.distributeExtraCostRoute" class="flex flex-wrap items-center gap-3 px-6 py-3">
                <label for="extra-cost-to-distribute" class="text-sm text-gray-600">
                    {{ trans('Set extra costs') }} <span v-if="costing.currency">({{ costing.currency }})</span>
                </label>
                <input
                    id="extra-cost-to-distribute"
                    v-model="extraCostToDistribute"
                    type="number"
                    step="0.01"
                    min="0"
                    class="border border-gray-300 rounded text-sm py-1 px-2 w-32"
                />

                <span class="text-sm text-gray-600">{{ trans('Distribute') }}:</span>

                <Button
                    :tooltip="trans('Distribute equally each items')"
                    icon="fal fa-equals"
                    type="tertiary"
                    size="xs"
                    :loading="distributingType === 'equally'"
                    :disabled="distributingType !== null"
                    @click="distributeExtraCost('equally')"
                />

                <Button
                    :tooltip="trans('Distribute depending on value')"
                    icon="fal fa-dollar-sign"
                    type="tertiary"
                    size="xs"
                    :loading="distributingType === 'by_value'"
                    :disabled="distributingType !== null"
                    @click="distributeExtraCost('by_value')"
                />
            </div>
        </template>

        <template #cell(units_in)="{ item }">
            <span class="text-gray-500">{{ formatQuantity(Number(item.unit_quantity_placed)) }}</span>
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
        </template>

        <template #cell(code)="{ item }">
            <div class="flex items-center gap-1.5">
                <Link
                    v-if="supplierProductRoute(item)"
                    v-tooltip="trans('Supplier product code')"
                    :href="supplierProductRoute(item)"
                    class="primaryLink"
                >
                    {{ item.code }}
                </Link>
                <span v-else>{{ item.code }}</span>

                <Link
                    v-if="orgStockRoute(item)"
                    v-tooltip="trans('Part reference is same as supplier product code')"
                    :href="orgStockRoute(item)"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <FontAwesomeIcon icon="fal fa-box" aria-hidden="true" fixed-width />
                </Link>
            </div>
        </template>

        <template #cell(description)="{ item }">
            <div class="space-y-0.5">
                <div>{{ item.name }}</div>
                <div class="text-xs text-gray-500">
                    {{ trans('Packed in') }} {{ formatQuantity(Number(item.units_per_pack) || 1) }}s ,
                    {{ trans('sko/C') }}: {{ formatQuantity(skosPerCarton(item)) }}
                </div>
            </div>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="text-gray-500">{{ quantityBreakdown(item) }}</span>
        </template>

        <template #cell(weight)="{ item }">
            <span v-if="item.weight !== null">{{ locale.number(item.weight) }}Kg</span>
            <FontAwesomeIcon
                v-else
                v-tooltip="trans('Unknown weight')"
                icon="fas fa-exclamation-circle"
                class="text-orange-500"
                aria-hidden="true"
            />
        </template>

        <template #cell(volume)="{ item }">
            <span v-if="item.volume !== null">{{ locale.number(item.volume) }} m³</span>
            <FontAwesomeIcon
                v-else
                v-tooltip="trans('Unknown CBM')"
                icon="fas fa-exclamation-circle"
                class="text-orange-500"
                aria-hidden="true"
            />
        </template>

        <template #cell(amount)="{ item }">
            {{ amount(item) }}
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex justify-end items-center gap-2">
                <Button
                    v-if="item.updateCostRoute"
                    :label="trans('Save')"
                    :tooltip="trans('Save the costs of this item')"
                    icon="fal fa-save"
                    type="save"
                    size="xs"
                    :loading="savingCostId === item.id"
                    :disabled="savingCostId === item.id"
                    @click="saveCost(item)"
                />

                <Button
                    v-else-if="item.confirmRoute"
                    :label="trans('Confirm')"
                    :tooltip="trans('Confirm item')"
                    icon="fal fa-spell-check"
                    type="positive"
                    size="xs"
                    :loading="changingId === item.id"
                    :disabled="changingId === item.id"
                    @click="confirmChangeState($event, item, item.confirmRoute, trans('Confirm this item?'), trans('Confirm'))"
                />

                <Button
                    v-else-if="item.readyToShipRoute"
                    :label="trans('Ready to ship')"
                    :tooltip="trans('Set ready to ship')"
                    icon="fal fa-box-check"
                    type="secondary"
                    size="xs"
                    :loading="changingId === item.id"
                    :disabled="changingId === item.id"
                    @click="confirmChangeState($event, item, item.readyToShipRoute, trans('Set this item as ready to ship?'), trans('Ready to ship'))"
                />

                <span v-if="!item.updateCostRoute && !item.confirmRoute && !item.readyToShipRoute" class="text-gray-400 text-sm">
                    {{ trans('No actions needed') }}
                </span>
            </div>
        </template>

        <template #cell(part)="{ item }">
            <div class="flex items-center gap-1.5">
                <Link
                    v-if="orgStockRoute(item)"
                    v-tooltip="trans('Part reference')"
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

        <template #cell(difference_percentage)="{ item }">
            <span :class="differenceClass(item.difference_percentage)">
                {{ item.difference_percentage === null ? '-' : `${locale.number(item.difference_percentage)}%` }}
            </span>
        </template>

        <template #cell(difference_units)="{ item }">
            <span :class="differenceClass(item.difference_units)">{{ formatQuantity(Number(item.difference_units)) }}</span>
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
                    :modelValue="Number(item.unit_quantity_checked)"
                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                    saveOnForm
                    isUseAxios
                    isWithRefreshModel
                    :routeSubmit="item.checkedRoute"
                    keySubmit="unit_quantity_checked"
                    :bindToTarget="{
                        step: 1,
                        min: Number(item.unit_quantity_placed)
                    }"
                    autoSave
                    @onSuccess="onCheckedSaved"
                >
                    <template #save="{ isProcessing }">
                        <div class="flex gap-x-8 w-fit">
                            <ButtonWithLink
                                v-tooltip="trans('Check all the delivered quantity')"
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
                                        {{ formatQuantity(Number(item.unit_quantity)) }}
                                    </div>
                                </template>
                            </ButtonWithLink>
                        </div>
                    </template>
                </NumberWithButtonSave>
            </div>
            <span v-else>{{ formatQuantity(Number(item.unit_quantity_checked)) }}</span>
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
                            {{ sowing.location_code ?? trans('Unknown') }}
                        </span>
                        <div v-tooltip="trans('Total placed quantity in this location')" class="text-gray-500 whitespace-nowrap">
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
                                v-tooltip="trans('Place all remaining quantity in location :xlocation', { xlocation: findLocation(item.locations, selectedLocationCode[item.id] ?? null)?.location_code || '-' })"
                                icon="fal fa-check"
                                :size="screenType != 'mobile' ? 'xs' : 'md'"
                                type="positive"
                                :loading="isProcessing"
                                class="py-0"
                                :routeTarget="item.placeAllRoute"
                                :body="{
                                    location_org_stock_id: findLocation(item.locations, selectedLocationCode[item.id] ?? null)?.id
                                }"
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
                <LabelPickingLocation
                    :locations="item.locations"
                    :selectedOrgStockId="selectedLocationCode[item.id] ?? null"
                    :warehouseArea="item.warehouse_area"
                    :warehouse_slug="item.warehouse_slug"
                    @openLocationModal="() => {
                        isModalLocation = true; selectedItemValue = item;
                    }"
                />
            </div>
            <span v-else-if="Number(item.unit_quantity_placed) > 0" class="text-green-500">
                {{ formatQuantity(Number(item.unit_quantity_placed)) }}
            </span>
            <span v-else>
            </span>
        </template>
    </Table>

    <Dialog
        v-model:visible="isModalLocation"
        modal
        :draggable="false"
        dismissableMask
        :style="{ width: '48rem' }"
        :breakpoints="{ '1280px': '70vw', '992px': '80vw', '768px': '90vw', '576px': '95vw' }"
        :contentStyle="{ maxHeight: '80vh', overflow: 'auto' }"
        :header="ctrans('Location list for :itemCode', { itemCode: selectedItemValue?.org_stock_code ?? '' })"
    >
        <SelectPickingLocation
            :item="selectedItemValue"
            :selectedLocationCode="selectedLocationCode[selectedItemValue?.id] ?? null"
            @select="(code) => { selectedLocationCode[selectedItemValue?.id] = code; isModalLocation = false; }"
            :ignoreNoQty="true"
        />
    </Dialog>

    <ConfirmPopup />
</template>
