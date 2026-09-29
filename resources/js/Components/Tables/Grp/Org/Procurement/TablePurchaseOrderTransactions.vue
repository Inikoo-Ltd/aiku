<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Table from '@/Components/Table/Table.vue'
import Image from '@common/Components/Image.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useLocaleStore } from '@/Stores/locale'
import { getOrderingLevels, unitsPerOrderingLevel, type OrderingLevel } from '@/Composables/useOrderingLevel'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faPallet, faStopCircle, faTrashAlt, faHandHoldingBox, faPeopleArrows } from '@fal'
import { faExclamationCircle, faSpinner, faMinusCircle } from '@fas'
import ConfirmPopup from 'primevue/confirmpopup'
import Popover from 'primevue/popover'
import { useConfirm } from 'primevue/useconfirm'

library.add(faBox, faPallet, faStopCircle, faExclamationCircle, faTrashAlt, faSpinner, faHandHoldingBox, faMinusCircle, faPeopleArrows)

const confirm = useConfirm()

const props = defineProps<{
    data: object
    tab?: string
    state?: string
    isOrgAgent?: boolean
    orgAgentSlug?: string
}>()

function supplierRoute(item: any): string {
    if (!props.isOrgAgent || !props.orgAgentSlug || !item.supplier_slug) {
        return ''
    }

    return route('grp.org.procurement.org_agents.show.suppliers.show', [
        route().params.organisation,
        props.orgAgentSlug,
        item.supplier_slug,
    ])
}

const currentLevel = defineModel<OrderingLevel>('level', { default: 'cartons' })

const locale = useLocaleStore()

const isInProcess = computed(() => props.state === 'in_process')

const levels = computed(() => getOrderingLevels())

const level = computed(() => levels.value.find(l => l.key === currentLevel.value) ?? levels.value[0])

function unitsPerLevel(item: any) {
    return unitsPerOrderingLevel(item, currentLevel.value)
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

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko. | ${formatQuantity(units / carton)}C.`
}

function amount(item: any) {
    const net = locale.currencyFormat(item.net_currency ?? 'EUR', item.net_amount ?? 0)

    if (item.org_net_amount === null || item.org_currency === item.net_currency) {
        return `${net}`
    }

    return `${net} (${locale.currencyFormat(item.org_currency ?? 'EUR', item.org_net_amount)})`
}

const savingId = ref<number | null>(null)

const isPriceEditable = computed(() => ['in_process', 'submitted'].includes(props.state ?? ''))
const alsoUpdateSupplierPrice = ref<Record<number, boolean>>({})
const savingPriceId = ref<number | null>(null)

function canEditPrice(item: any) {
    return isPriceEditable.value && item.updateRoute && ['in_process', 'submitted'].includes(item.state)
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

async function onSaveQuantity(item: any, form: any) {
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
    } catch (error: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: error?.response?.data?.message || ctrans('Failed to update quantity'),
            type: 'error',
        })
    } finally {
        savingId.value = null
    }
}

const deletingId = ref<number | null>(null)

function confirmDeleteItem(event: MouseEvent, item: any) {
    if (!item.deleteRoute) {
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

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: ctrans('Cancel this item?'),
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
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template v-if="isInProcess" #before-table>
            <div class="flex items-end gap-1 border-b border-gray-200 px-3 sm:px-4">
                <button
                    v-for="item in levels"
                    :key="item.key"
                    type="button"
                    class="px-3 py-1.5 text-sm border-b-2 -mb-px transition"
                    :class="item.key === currentLevel
                        ? 'border-indigo-500 text-indigo-600 font-medium'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="currentLevel = item.key"
                >
                    <FontAwesomeIcon :icon="item.icon" aria-hidden="true" fixed-width />
                    {{ item.tab }}
                </button>
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

                    <Link
                        v-if="supplierProductRoute(item) && orgStockRoute(item)"
                        v-tooltip="ctrans('Part reference is same as supplier product code')"
                        :href="orgStockRoute(item)"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <FontAwesomeIcon icon="fal fa-box" aria-hidden="true" fixed-width />
                    </Link>
                </div>

                <div
                    v-if="isOrgAgent && item.supplier_name"
                    class="flex items-center gap-1 text-xs text-gray-500"
                >
                    <FontAwesomeIcon icon="fal fa-hand-holding-box" aria-hidden="true" fixed-width />
                    <Link
                        v-if="supplierRoute(item)"
                        v-tooltip="ctrans('Supplier')"
                        :href="supplierRoute(item)"
                        class="primaryLink"
                    >
                        {{ item.supplier_name }}
                    </Link>
                    <span v-else>{{ item.supplier_name }}</span>
                </div>
            </div>
        </template>

        <template #cell(image_thumbnail)="{ item }">
            <div
                class="h-20 w-20 flex-none overflow-hidden rounded border border-gray-100"
                :class="{ 'cursor-zoom-in': item.image_preview }"
                @mouseenter="showImagePreview($event, item)"
                @mouseleave="hideImagePreview"
            >
                <Image :src="item['image_thumbnail']" imageCover class="h-20 w-20" />
            </div>
        </template>

        <template #cell(description)="{ item }">
            <div class="space-y-0.5">
                <div>
                    <span v-if="isInProcess && currentLevel !== 'units'" class="font-medium">
                        {{ formatQuantity(unitsPerLevel(item)) }}x
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
                    <label v-if="item.can_update_supplier_cost" class="flex items-center gap-1 cursor-pointer select-none">
                        <input v-model="alsoUpdateSupplierPrice[item.id]" type="checkbox" class="rounded border-gray-300" />
                        {{ ctrans('Also update supplier price') }}
                    </label>
                </div>
                <div v-else-if="isInProcess" class="text-xs text-gray-500">
                    {{ level.cost }}: {{ levelCostLabel(item) }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ ctrans('Packed in') }} {{ formatQuantity(Number(item.units_per_pack) || 1) }}s ,
                    {{ ctrans('sko/C') }}: {{ formatQuantity(skosPerCarton(item)) }}
                </div>
                <div v-if="item.stock_in_locations !== undefined && item.stock_in_locations !== null" class="text-xs text-gray-500">
                    {{ ctrans('Stock') }}: <span class="font-medium">{{ formatQuantity(Number(item.stock_in_locations)) }}</span> {{ ctrans('SKOs') }}
                </div>
                <div v-if="item.quarterly_usage?.length" class="text-xs text-gray-500">
                    {{ ctrans('Usage (SKOs)') }}:
                    <span v-for="record in item.quarterly_usage" :key="record.period" class="mr-2">
                        {{ record.period }}: <span class="font-medium">{{ formatQuantity(record.sales) }}</span>
                    </span>
                </div>
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
            <div v-if="isInProcess" class="flex justify-end items-center">
                <NumberWithButtonSave
                    :key="`${item.id}-${currentLevel}`"
                    isWithRefreshModel
                    :modelValue="quantityAtLevel(item)"
                    :min="0"
                    :isLoading="savingId === item.id"
                    @onSave="(form) => onSaveQuantity(item, form)"
                />
            </div>
            <span v-else class="text-gray-500">{{ quantityBreakdown(item) }}</span>
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex justify-end items-center gap-2">
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
                    v-if="state === 'submitted' && item.cancelRoute"
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

    <Popover ref="imagePreviewPopover" class="pointer-events-none">
        <Image v-if="imagePreview" :src="imagePreview" class="flex h-80 w-80 items-center justify-center [&>img]:max-h-full [&>img]:max-w-full" />
    </Popover>
</template>
