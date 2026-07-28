<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 17 Jul 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Table from '@/Components/Table/Table.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faSpellCheck, faBoxCheck, faTrashAlt, faClipboardList, faSeedling, faTruck, faCheck, faClipboardCheck, faCheckDouble, faTimesCircle, faEquals, faDollarSign, faSave, faExclamationCircle as falExclamationCircle } from '@fal'
import { faExclamationCircle, faSpinner } from '@fas'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'

library.add(faBox, faSpellCheck, faBoxCheck, faTrashAlt, faExclamationCircle, faSpinner, faClipboardList, faSeedling, faTruck, faCheck, faClipboardCheck, faCheckDouble, faTimesCircle, faEquals, faDollarSign, faSave, falExclamationCircle)

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

const selectedLocation = reactive<Record<number, number | null>>({})

function placedAdditionalData(item: any) {
    return { location_org_stock_id: selectedLocation[item.id] ?? null }
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

async function undoSowing(sowing: any) {
    try {
        await axios.delete(route(sowing.undo_route.name, sowing.undo_route.parameters))
        notify({ title: trans('Success'), text: trans('Placement removed'), type: 'success' })
        onCheckedSaved()
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to remove placement'),
            type: 'error',
        })
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :useTopPagination="true">
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

        <template #cell(checked_unit)="{ item }">
            <NumberWithButtonSave
                v-if="item.checkedRoute"
                :modelValue="Number(item.unit_quantity_checked)"
                :min="Number(item.unit_quantity_placed)"
                :routeSubmit="item.checkedRoute"
                keySubmit="unit_quantity_checked"
                saveOnForm
                isUseAxios
                allowZero
                isWithRefreshModel
                @onSuccess="onCheckedSaved"
            />
            <span v-else>{{ formatQuantity(Number(item.unit_quantity_checked)) }}</span>
        </template>

        <template #cell(placement)="{ item }">
            <div v-if="Number(item.unit_quantity_checked) >= 1" class="space-y-1.5">
                <div v-if="item.placedRoute" class="flex items-center gap-2">
                    <select
                        v-model="selectedLocation[item.id]"
                        class="border border-gray-300 rounded text-sm py-1 px-2 max-w-[10rem]"
                    >
                        <option :value="null" disabled>{{ trans('Select location') }}</option>
                        <option v-for="loc in item.locations" :key="loc.id" :value="loc.id">
                            {{ loc.location_code }} ({{ formatQuantity(Number(loc.quantity)) }})
                        </option>
                    </select>
                    <NumberWithButtonSave
                        :key="`placement-${item.id}-${item.unit_quantity_placed}-${item.sowings?.length ?? 0}`"
                        :modelValue="0"
                        :min="0"
                        :max="Number(item.placement_remaining)"
                        :routeSubmit="item.placedRoute"
                        keySubmit="quantity"
                        :additionalData="placedAdditionalData(item)"
                        saveOnForm
                        isUseAxios
                        allowZero
                        @onSuccess="onCheckedSaved"
                    />
                </div>

                <div
                    v-for="sowing in item.sowings"
                    :key="sowing.id"
                    class="flex items-center gap-2 text-sm text-gray-600"
                >
                    <span>{{ sowing.location_code ?? trans('Unknown') }}: {{ formatQuantity(Number(sowing.quantity)) }}</span>
                    <button
                        type="button"
                        v-tooltip="trans('Remove placement')"
                        class="text-red-500 hover:text-red-700"
                        @click="undoSowing(sowing)"
                    >
                        <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>
            <span v-else class="text-gray-400 text-sm italic">{{ trans('Check items first') }}</span>
        </template>
    </Table>

    <ConfirmPopup />
</template>
