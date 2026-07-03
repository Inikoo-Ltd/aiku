<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 13 September 2024 11:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import Icon from "@/Components/Icon.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { debounce, get, set } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { ref, onMounted, reactive, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHandPaper, faChair, faBoxCheck, faCheckDouble, faTimes } from "@fal"
import { faSkull, faStickyNote, faPeopleArrows} from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Modal from "@/Components/Utils/Modal.vue"
import { RadioButton } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import { faPencil } from "@far"
import MiniDeliveryNote from "@/Components/MiniDeliveryNote.vue"
import { twBreakPoint } from "@/Composables/useWindowSize"
import { DeliveryNoteItem } from "@/types/delivery-note-item"
import { RouteParams } from "@/types/route-params"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import SelectPickingLocation from "../DeliveryNotes/SelectPickingLocation.vue"
import LabelPickingLocation from "../DeliveryNotes/LabelPickingLocation.vue"
import { notify } from "@kyvg/vue3-notification"

library.add(faSkull, faStickyNote, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHandPaper, faChair, faBoxCheck, faCheckDouble, faTimes, faPeopleArrows)


const props = defineProps<{
    data: TableTS
    tab?: string
    pickingSession: {
        id: number
        state: string
        [key: string]: any
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const modalDetail = ref(false)

const currentRouteParams = route().params as RouteParams

const orgStockRouteCache = new Map<string, string>()
function showOrgStockRoute(deliveryNoteItem: DeliveryNoteItem) {
    if (!deliveryNoteItem.org_stock_slug) {
        return ''
    }

    if (!orgStockRouteCache.has(deliveryNoteItem.org_stock_slug)) {
        orgStockRouteCache.set(
            deliveryNoteItem.org_stock_slug,
            route(
                "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show",
                [currentRouteParams.organisation, currentRouteParams.warehouse, deliveryNoteItem.org_stock_slug]
            )
        )
    }

    return orgStockRouteCache.get(deliveryNoteItem.org_stock_slug)!
}

const deliveryNoteRouteCache = new Map<string, string>()
function showDeliveryNoteRoute(deliveryNoteItem: DeliveryNoteItem) {
    if (!deliveryNoteItem.delivery_note_slug) {
        return ''
    }

    if (!deliveryNoteRouteCache.has(deliveryNoteItem.delivery_note_slug)) {
        deliveryNoteRouteCache.set(
            deliveryNoteItem.delivery_note_slug,
            route(
                "grp.org.warehouses.show.dispatching.delivery_notes.show",
                [currentRouteParams.organisation, currentRouteParams.warehouse, deliveryNoteItem.delivery_note_slug]
            )
        )
    }

    return deliveryNoteRouteCache.get(deliveryNoteItem.delivery_note_slug)!
}

const isMounted = ref(false)
onMounted(() => {
    isMounted.value = true
})

const locationRouteCache = new Map<string, string>()
const generateLocationRoute = (location: any) => {
    if (!location.location_slug) {
        return "#"
    }

    if (!locationRouteCache.has(location.location_slug)) {
        locationRouteCache.set(
            location.location_slug,
            route(
                "grp.org.warehouses.show.infrastructure.locations.show",
                [currentRouteParams.organisation, currentRouteParams.warehouse, location.location_slug]
            )
        )
    }

    return locationRouteCache.get(location.location_slug)!
}

// Section: Axios row actions (same pattern as TableDeliveryNoteItems): the action goes via
// axios, then only the affected row is re-fetched and replaced with a new object so v-memo
// re-renders just that row. In the grouped tab a row is a delivery note (rowId = delivery
// note id, its `id` field); in the other tabs a row is a delivery note item.
const rowActionLoading = reactive<{ [rowId: string]: string | null }>({})

const replaceRowInTable = (rowId: number | string, newRow: any) => {
    const rows = props.data?.data
    if (!Array.isArray(rows)) {
        return
    }

    const index = rows.findIndex((row: any) => row.id == rowId)
    if (index === -1) {
        return
    }

    const newRows = [...rows]
    if (newRow) {
        newRows[index] = newRow
    } else {
        newRows.splice(index, 1)
        if (props.data.meta?.total > 0) {
            props.data.meta.total--
        }
    }
    props.data.data = newRows
}

const refreshRow = async (rowId: number | string) => {
    try {
        const response = await axios.get(
            route('grp.json.picking_session_item_row', { pickingSession: props.pickingSession.id }),
            { params: { tab: props.tab, row_id: rowId } }
        )
        replaceRowInTable(rowId, response.data?.data ?? null)
    } catch (error) {
        console.error('Failed to refresh row, falling back to full reload', error)
        router.reload({ preserveScroll: true })
    }
}

const itemsPropsToSkipOnReload = ['items', 'itemized', 'grouped']
const reloadPageExceptItems = debounce(() => {
    const stateBeforeReload = props.pickingSession?.state
    router.reload({
        except: itemsPropsToSkipOnReload,
        preserveScroll: true,
        onSuccess: () => {
            if (props.pickingSession?.state !== stateBeforeReload) {
                router.reload({ preserveScroll: true })
            }
        },
    })
}, 400)

const onRowAction = async (
    rowId: number | string,
    routeTarget: routeType,
    actionKey: string,
    body: Record<string, any> = {},
): Promise<boolean> => {
    if (!routeTarget?.name) {
        return false
    }

    const method = (routeTarget.method || 'post').toLowerCase()
    const url = route(routeTarget.name, routeTarget.parameters)

    console.log('wwwwwwwwwwwwwwww', routeTarget.name)
    rowActionLoading[rowId] = actionKey
    try {
        if (method === 'delete') {
            await axios.delete(url, { data: body })
        } else {
            await axios[method](url, body)
        }

        await refreshRow(rowId)
        reloadPageExceptItems()

        return true
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to process this action. Try again"),
            type: "error",
        })
        return false
    } finally {
        rowActionLoading[rowId] = null
    }
}

// Section: Modal for a location list
const isModalLocation = ref(false)
const selectedItemValue = ref()
const selectedItemProxy = ref()
const onCloseModal = () => {
    isModalLocation.value = false

    setTimeout(() => {
        selectedItemValue.value = null
    }, 300)
}

// Method: to find the location that Alt ed, fallback is index 0
const selectedLocationCode = reactive({})
const findLocation = (locationsList: { location_code: string }[], locationCode: string) => {
    return locationsList.find(x => x.location_code == locationCode) || locationsList[0]
}

const DeliveryNoteInModal = ref(null)

const onCloseModalDetail = () => {
    modalDetail.value = false
    DeliveryNoteInModal.value = null
    router.reload()
}

const onOpenModalDetail = (deliveryNote) => {
    modalDetail.value = true
    DeliveryNoteInModal.value = deliveryNote
}

const innerWidth = ref(0)
onMounted(() => {
    innerWidth.value = window.innerWidth
})

const GetQuantityToPickFractional = (item) => {
    if(item.delivery_note_shop_type == 'dropshipping'){
        return item.quantity_to_pick_fractional_ds
    }else return item.quantity_to_pick_fractional
}

const returnStoredItemsRouteCache = new Map<string, string>()
const showReturnStoredItemsRoute = (item: any) => {
    if (!item?.slug) {
        return "#"
    }

    const cacheKey = `${item?.type}-${item.slug}`
    if (!returnStoredItemsRouteCache.has(cacheKey)) {
        const routeName = item?.type === 'pallet'
            ? "grp.org.warehouses.show.dispatching.pallet-returns.show"
            : "grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show"

        returnStoredItemsRouteCache.set(
            cacheKey,
            route(routeName, [currentRouteParams.organisation, currentRouteParams.warehouse, item.slug])
        )
    }

    return returnStoredItemsRouteCache.get(cacheKey)!
}

// Grouped rows hold one delivery note with its nested items; the other tabs hold one
// delivery note item per row
const estimateRowHeight = (item: any) => {
    if (Array.isArray(item.items)) {
        return 60 + Math.max(item.items.length, 1) * 110
    }

    let height = 100
    const pickingRowsCount = item.pickings?.length ?? 0
    if (pickingRowsCount > 1) {
        height += (pickingRowsCount - 1) * 28
    }
    return height
}

const rowSelectedLocationSignature = (item: any) => {
    if (Array.isArray(item.items)) {
        return item.items.map((nested: any) => get(selectedLocationCode, [nested.id], '')).join('|')
    }
    return get(selectedLocationCode, [item.id], null)
}

// Every external reactive value a row's cells read must be listed here (see virtualRowMemo
// in Table.vue): a row only re-renders when one of these values changes.
const virtualRowMemo = (item: any) => [
    item,
    item.errors,
    rowSelectedLocationSignature(item),
    rowActionLoading[item.id],
    twBreakPoint().join(','),
    innerWidth.value,
    props.pickingSession?.state,
]

</script>

<template>
    <Table :resource="data" class="mt-5" rowAlignTop :name="tab" virtualScroll :virtualItemHeight="estimateRowHeight" :virtualRowMemo="virtualRowMemo">
        <!-- Column: state -->
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <template #cell(delivery_note_state)="{ item }">
            <Icon :data="item.delivery_note_state_icon" />
        </template>

        <template #cell(org_stock_code)="{ item }">
            <Link :href="showOrgStockRoute(item)" class="secondaryLink">
            {{ item.org_stock_code }}
            </Link>
        </template>

        <template #cell(org_stock_name)="{ item: deliveryNoteItem }">
            <div>{{ deliveryNoteItem.org_stock_name }} <span class="italic opacity-80">{{deliveryNoteItem.packed_in_message}}</span></div>

        </template>

        <template #cell(delivery_note_reference)="{ item }">
            <div class="flex gap-2 flex-wrap items-center">
                <Link :href="showDeliveryNoteRoute(item)" class="primaryLink">
                {{ item?.delivery_note_reference }}
                </Link>
                <FontAwesomeIcon v-if="item.delivery_note_is_premium_dispatch" v-tooltip="trans('Priority dispatch')" icon="fas fa-star" class="text-yellow-500 animate-bounce" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="item.delivery_note_has_extra_packing" v-tooltip="trans('Extra packing')" icon="fas fa-box-heart" class="text-yellow-500 animate-bounce" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="item.delivery_note_is_for_collection" v-tooltip="trans('For Collection')" icon="fas fa-people-arrows" class="text-purple-500 animate-bounce" fixed-width aria-hidden="true" />




                <NotesDisplay reference-field="delivery_note_reference" :item="item" :note-fields="{
                    shipping: 'delivery_note_shipping_notes',
                    customer: 'delivery_note_customer_notes',
                    internal: 'delivery_note_internal_notes',
                    public: 'delivery_note_public_notes'
                }" />
            </div>
        </template>

        <template #cell(reference)="{ item }">
            <Link :href="showReturnStoredItemsRoute(item)" class="primaryLink">
                {{ item?.reference }}
            </Link>
        </template>


        <!-- Column: Quantity Required -->
        <template #cell(quantity_required)="{ item }">
            <span v-tooltip="item.quantity_required">
                <FractionDisplay v-if="item.quantity_required_fractional"
                    :fractionData="item.quantity_required_fractional" />
                <span v-else>{{ item.quantity_required }}</span>

            </span>

            <template v-if="pickingSession.state === 'handling'">
                <span v-if="item.quantity_to_pick > 0" class="whitespace-nowrap space-x-2">

                    <Button v-if="!item.is_handled" type="negative"
                        :label="locale.number(item.quantity_to_pick)" v-tooltip="trans('Set as not picked')" icon="fal fa-debug"
                        size="xs"
                        :loading="rowActionLoading[item.id] === 'not-pick'"
                        @click="() => onRowAction(item.id, item.not_picking_route, 'not-pick')" />
                </span>

                <div v-else v-tooltip="trans('Quantity not gonna be picked')" class="text-red-500 w-fit ml-auto">
                    <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                    {{ item.quantity_not_picked }}
                </div>
            </template>

        </template>

        <template #cell(quantity_picked)="{ item: item, proxyItem }">
            <FractionDisplay v-if="item.quantity_picked_fractional" :fractionData="item.quantity_picked_fractional" />
            <span v-else>{{ item.quantity_picked }}</span>
        </template>


        <template #cell(quantity_to_pick)="{ item: item }">
            {{ item.quantity_to_pick }}
        </template>


        <!-- Column: Pickings -->
        <template #cell(pickings)="{ item }">
            <div v-if="item.pickings?.length" class="space-y-1">
                <div v-for="picking in item.pickings" :key="picking.id" class="flex gap-x-2 w-fit">
                    <!-- {{ picking.location_code }} -->
                    <div v-if="picking.type === 'pick'" class="flex gap-x-2 items-center">
                        <Link :href="generateLocationRoute(picking)" class="secondaryLink">
                        {{ picking.location_code }}
                        </Link>

                        <div v-tooltip="trans('Total picked quantity in this location')"
                            class="text-gray-500 whitespace-nowrap">
                            <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr text-gray-500" fixed-width
                                aria-hidden="true" />
                            <FractionDisplay v-if="picking.quantity_picked_fractional"
                                :fractionData="picking.quantity_picked_fractional" />
                            <span v-else>
                                {{ picking.quantity_picked }}
                            </span>
                        </div>
                    </div>

                    <div v-if="picking.type === 'not-pick'" v-tooltip="trans('Quantity not gonna be picked')"
                        class="text-red-500 w-fit mr-auto whitespace-nowrap">
                        <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                        <FractionDisplay v-if="picking.quantity_picked_fractional"
                            :fractionData="picking.quantity_picked_fractional" />
                        <span v-else>
                            {{ picking.quantity_picked }}
                        </span>
                    </div>

                    <!-- Button: Undo -->
                    <Button v-if="!item.is_packed && pickingSession.state == 'handling'"
                        v-tooltip="trans('Undo')" type="negative" :size="twBreakPoint().includes('lg') ? 'xxs' : 'sm'"
                        icon="fal fa-undo-alt"
                        @click="() => onRowAction(item.id, picking.undo_picking_route, `undo-pick-${picking.id}`)"
                        :loading="rowActionLoading[item.id] === `undo-pick-${picking.id}`" />
                </div>

            </div>

            <div v-else class="text-xs text-gray-400 italic">
                {{ trans("No item picked yet") }}
            </div>
        </template>

        <!-- Column: 'Items' (grouped) -->
        <template #cell(items)="{ item: itemValue, proxyItem }">
            <div v-if="itemValue.items.length" v-for="(deliveryItem, index) in itemValue.items"
                :key="deliveryItem.id || index" class="min-h-11">

                <div class="flex justify-between items-center">
                    <div>
                        <Link :href="showOrgStockRoute(deliveryItem)" class="secondaryLink">
                        {{ deliveryItem.org_stock_code }}
                        </Link> <span class="opacity-70">{{ deliveryItem.org_stock_name}} <span class="italic">{{ deliveryItem.packed_in_message}}</span></span>
                    </div>

                    <template v-if="deliveryItem.quantity_to_pick > 0 && deliveryItem.state == 'handling'">
                        <div v-if="findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null))"
                            class="rounded p-1 flex flex-col justify-between gap-x-6 items-center">
                            <div class="xmb-3 w-full flex justify-between gap-x-6 items-center">
                                <!-- Section: Locations -->
                                <LabelPickingLocation
                                    :locations="deliveryItem.locations"
                                    :selectedOrgStockId="get(selectedLocationCode, [deliveryItem.id], null)"
                                    :warehouseArea="deliveryItem.warehouse_area"
                                    @openLocationModal="() => { isModalLocation = true; selectedItemValue = deliveryItem; selectedItemProxy = proxyItem; }"
                                />

                                <!-- Quantity Picker -->
                                <div class="flex items-center flex-nowrap gap-x-2">
                                    <NumberWithButtonSave
                                        v-if="!deliveryItem.is_handled && findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).quantity > 0"
                                        :key="findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).location_code"
                                        noUndoButton
                                        @onError="(error: any) => proxyItem.errors = error?.errors ? Object.values(error.errors).flat() : [error?.message || trans('Failed to save quantity')]"
                                        :modelValue="findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).quantity_picked"
                                        @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                        @onSuccess="() => { refreshRow(itemValue.id); reloadPageExceptItems() }"
                                        saveOnForm isUseAxios :routeSubmit="{
                                            name: deliveryItem.upsert_picking_route.name,
                                            parameters: deliveryItem.upsert_picking_route.parameters
                                        }" :bindToTarget="{
                                            step: 1,
                                            min: 0,
                                            max: Math.min(
                                                findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).quantity,
                                                deliveryItem.quantity_required,
                                                deliveryItem.quantity_to_pick + findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).quantity_picked
                                            )
                                        }" :additionalData="{
                                            location_org_stock_id: findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).id,
                                            picking_id: deliveryItem.pickings.find(p => p.location_id === findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).location_id)?.id
                                        }" autoSave xxisWithRefreshModel
                                        :readonly="deliveryItem.is_handled || deliveryItem.quantity_required === deliveryItem.quantity_picked">
                                        <template #save="{ isProcessing }">
                                            <Button
                                                v-tooltip="trans('Pick all required quantity in this location')"
                                                icon="fal fa-clipboard-list-check"
                                                :disabled="deliveryItem.is_handled || deliveryItem.quantity_required === deliveryItem.quantity_picked"
                                                size="xs" type="secondary"
                                                :loading="isProcessing || rowActionLoading[itemValue.id] === `pick-all-${deliveryItem.id}`"
                                                @click="() => onRowAction(itemValue.id, deliveryItem.picking_all_route, `pick-all-${deliveryItem.id}`, {
                                                    location_org_stock_id: findLocation(deliveryItem.locations, get(selectedLocationCode, [deliveryItem.id], null)).id
                                                })">
                                                <template #label>
                                                    <FractionDisplay v-if="GetQuantityToPickFractional(deliveryItem)"
                                                        :fractionData="GetQuantityToPickFractional(deliveryItem)" />
                                                    <span v-else>{{ locale.number(deliveryItem.quantity_to_pick ?? 0)
                                                        }}</span>
                                                </template>
                                            </Button>
                                        </template>
                                    </NumberWithButtonSave>

                                    <!-- Not Picked Button -->
                                    <Button v-if="!deliveryItem.is_handled" type="negative"
                                        v-tooltip="trans('Set as not picked')" icon="fal fa-debug" size="xs"
                                        :loading="rowActionLoading[itemValue.id] === `not-pick-${deliveryItem.id}`"
                                        @click="() => onRowAction(itemValue.id, deliveryItem.not_picking_route, `not-pick-${deliveryItem.id}`)" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <Button
                        v-if="pickingSession.state === 'picking_finished' && deliveryItem.delivery_note_state === 'handling'"
                        type="save" label="Set as packed" size="sm" @click="onOpenModalDetail(deliveryItem)" />

                    <div v-if="deliveryItem.pickings?.length && deliveryItem.state == 'handling'" class="space-y-1">
                        <div v-for="picking in deliveryItem.pickings" :key="picking.id" class="flex gap-x-2 w-fit">
                            <!-- {{ picking.location_code }} -->
                            <div v-if="picking.type === 'pick'" class="flex gap-x-2 items-center">
                                <Link :href="generateLocationRoute(picking)" class="secondaryLink">
                                {{ picking.location_code }}
                                </Link>

                                <div v-tooltip="trans('Total picked quantity in this location')"
                                    class="text-gray-500 whitespace-nowrap">
                                    <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr text-gray-500" fixed-width
                                        aria-hidden="true" />
                                    <FractionDisplay v-if="picking.quantity_picked_fractional"
                                        :fractionData="picking.quantity_picked_fractional" />
                                    <span v-else>
                                        {{ picking.quantity_picked }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="picking.type === 'not-pick'" v-tooltip="trans('Quantity not gonna be picked')"
                                class="text-red-500 w-fit mr-auto">
                                <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                                <FractionDisplay v-if="picking.quantity_picked_fractional"
                                    :fractionData="picking.quantity_picked_fractional" />
                                <span v-else>
                                    {{ picking.quantity_picked }}
                                </span>
                            </div>

                            <Button v-if="!deliveryItem.is_packed && deliveryItem.state == 'handling'"
                                v-tooltip="trans('Undo')" type="negative" size="xxs" icon="fal fa-undo-alt"
                                @click="() => onRowAction(itemValue.id, picking.undo_picking_route, `undo-pick-${picking.id}`)"
                                :loading="rowActionLoading[itemValue.id] === `undo-pick-${picking.id}`" />
                        </div>

                    </div>

                </div>
            </div>

            <div v-else>
                <div class="text-xs text-gray-400 italic">No items</div>
            </div>

        </template>


        <!-- Column: actions -->
        <template #cell(picking_position)="{ item: itemValue, proxyItem }">
            <div v-if="itemValue.quantity_to_pick > 0 && pickingSession.state == 'handling'">
                <div v-if="findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null))"
                    class="rounded p-1 flex flex-col justify-between gap-x-6 items-center even:bg-black/5">
                    <!-- Action: decrease and increase quantity -->
                    <div class="xmb-3 w-full flex justify-between gap-x-6 items-center">
                        <!-- Section: Location list and their stocks -->
                        <LabelPickingLocation
                            :locations="itemValue.locations"
                            :selectedOrgStockId="get(selectedLocationCode, [itemValue.id], null)"
                            :warehouseArea="itemValue.warehouse_area"
                            @openLocationModal="() => { isModalLocation = true; selectedItemValue = itemValue; selectedItemProxy = proxyItem; }"
                        />

                        <div>
                            <div class="flex items-center flex-nowrap gap-x-2">
                                <NumberWithButtonSave
                                    v-if="!itemValue.is_handled && findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity > 0"
                                    :key="findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_code" noUndoButton
                                    @onError="(error: any) => {
                                        proxyItem.errors = error?.errors ? Object.values(error.errors).flat() : [error?.message || trans('Failed to save quantity')]
                                    }" :modelValue="findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity_picked"
                                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                    @onSuccess="() => { refreshRow(itemValue.id); reloadPageExceptItems() }"
                                    saveOnForm isUseAxios :routeSubmit="{
                                        name: itemValue.upsert_picking_route.name,
                                        parameters: itemValue.upsert_picking_route.parameters,
                                    }" :bindToTarget="{
                                        class: proxyItem.errors?.length ? 'errorShake' : undefined,
                                        step: 1,
                                        min: 0,
                                        max: Math.min(findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity, itemValue.quantity_required, (itemValue.quantity_to_pick + findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity_picked))
                                    }" :additionalData="{
                                        location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id,
                                        picking_id: itemValue.pickings.find(picking => picking.location_id == findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_id)?.id,
                                    }" autoSave xxisWithRefreshModel
                                    :readonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked">
                                    <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                        <div class="hidden lg:flex gap-x-8 w-fit">
                                            <Button
                                                v-tooltip="trans('Pick all required quantity in this location')"
                                                icon="fal fa-clipboard-list-check"
                                                :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                                size="xs" type="secondary" class="py-0"
                                                :loading="isProcessing || rowActionLoading[itemValue.id] === 'pick-all'"
                                                @click="() => onRowAction(itemValue.id, itemValue.picking_all_route, 'pick-all', {
                                                    location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id
                                                })">
                                                <template #label>
                                                    <div>
                                                        <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)"
                                                            :fractionData="GetQuantityToPickFractional(itemValue)" />
                                                        <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0)
                                                            }}</span>
                                                    </div>
                                                </template>
                                            </Button>
                                        </div>
                                        <div class="lg:hidden space-y-1">
                                            <Button
                                                v-tooltip="trans('Pick all required quantity in this location')"
                                                icon="fal fa-clipboard-list-check"
                                                :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                                type="secondary" class="py-0" full
                                                :loading="isProcessing || rowActionLoading[itemValue.id] === 'pick-all'"
                                                @click="() => onRowAction(itemValue.id, itemValue.picking_all_route, 'pick-all', {
                                                    location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id
                                                })">
                                                <template #label>
                                                    <div>
                                                        <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)"
                                                            :fractionData="GetQuantityToPickFractional(itemValue)" />
                                                        <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0)
                                                            }}</span>
                                                    </div>
                                                </template>
                                            </Button>
                                        </div>
                                    </template>
                                </NumberWithButtonSave>

                                <div class="md:hidden">
                                    <Button v-if="!itemValue.is_handled" type="negative"
                                        v-tooltip="trans('Set as not picked')" icon="fal fa-debug" size="lg"
                                        :loading="rowActionLoading[itemValue.id] === 'not-pick'"
                                        @click="() => onRowAction(itemValue.id, itemValue.not_picking_route, 'not-pick')" />
                                </div>
                                <div class="hidden md:block">
                                    <Button v-if="!itemValue.is_handled" type="negative"
                                        v-tooltip="trans('Set as not picked')" icon="fal fa-debug"
                                        :loading="rowActionLoading[itemValue.id] === 'not-pick'"
                                        @click="() => onRowAction(itemValue.id, itemValue.not_picking_route, 'not-pick')" />
                                </div>
                            </div>

                            <!-- Section: Errors list -->
                            <div v-if="proxyItem.errors?.length">
                                <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">
                                    *{{ error }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!itemValue.locations.every(location => { return location.quantity > 0 })" class="">
                </div>
            </div>

            <div v-else-if="pickingSession.state == 'handling'">
                <Button v-if="!itemValue.is_handled" type="negative" v-tooltip="trans('Set as not picked')"
                    icon="fal fa-debug" :size="innerWidth > 768 ? undefined : 'lg'"
                    :loading="rowActionLoading[itemValue.id] === 'not-pick'"
                    @click="() => onRowAction(itemValue.id, itemValue.not_picking_route, 'not-pick')" />
            </div>

            <Button v-if="pickingSession.state === 'picking_finished' && (itemValue.delivery_note_state === 'handling' || itemValue.delivery_note_state === 'packing')"
                type="save" label="Set as packed" size="sm" @click="onOpenModalDetail(itemValue)" />


            <Button v-if="itemValue.delivery_note_state == 'packed'" :icon="faPencil" label="Edit Detail" size="sm"
                @click="onOpenModalDetail(itemValue)" />
            <div>
                <!-- Empty div to avoid print unexpected from BE -->
            </div>
        </template>
    </Table>

    <!-- Modal: Location list -->
    <Modal :isOpen="isModalLocation" @onClose="() => onCloseModal()" width="w-full max-w-2xl" :dialogStyle="{
        background: '#ffffffcc'
    }">
        <SelectPickingLocation
            :item="selectedItemValue"
            :selectedLocationCode="get(selectedLocationCode, [selectedItemValue?.id], null)"
            @select="(code) => { set(selectedLocationCode, [selectedItemValue?.id], code); isModalLocation = false; }"
        />
    </Modal>

    <Modal :isOpen="modalDetail" @onClose="() => onCloseModalDetail()" width="w-1/2">
        <MiniDeliveryNote :deliveryNote="DeliveryNoteInModal"
                          @SuccsesUpdateState="() => { onCloseModalDetail() }" />
    </Modal>


</template>
