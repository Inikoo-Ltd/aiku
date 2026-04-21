<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject, onMounted, nextTick } from 'vue'
import formatDistanceStrict from 'date-fns/formatDistanceStrict'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faForklift, faInventory, faClipboardCheck, faQuestionSquare, faDotCircle, faDollyFlatbedEmpty as faDollyFlatbedEmptyFal } from "@fal"
import { faShoppingBasket, faStickyNote, faShoppingCart, faPlusCircle, faBox, faBan, faDollyFlatbedEmpty as faDollyFlatbedEmptyFas } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from 'vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { InputNumber, Popover, Dialog } from 'primevue'
import StockCheck from './StockCheck.vue'
import MoveStock from './MoveStock.vue'
import { Icon as IconTS } from '@/types/Utils/Icon'
import Icon from '@/Components/Icon.vue'
import { routeType } from '@/types/route'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { StockLocation, StocksManagementTS } from '@/types/Inventory/StocksManagement'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import AddLocations from './AddLocations.vue'
import EditLocationsModal from './EditLocationsModal.vue'
import { WINDOW } from '@sentry/vue'
library.add(faForklift, faInventory, faClipboardCheck, faQuestionSquare, faDotCircle, faDollyFlatbedEmptyFal, faShoppingBasket, faStickyNote, faShoppingCart, faDollyFlatbedEmptyFas)

const props = defineProps<{
    stocks_management: StocksManagementTS
    trade_units: any
}>()

const layout = inject('layout', layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

// Active picking location state
const activePickingLocationWholesale = ref<number | null>(null)
const isLoadingActiveLocationWholesale = ref<number | null>(null)
const activePickingLocationDropshipping = ref<number | null>(null)
const isLoadingActiveLocationDropshipping = ref<number | null>(null)

// Notes state - dummy data structure
const locationNotes = ref<Record<number, string>>({
    // Example: location id as key, note as value
})

// Popover states
const notePopovers = ref<Record<number, string>>({})
const tempNotes = ref<Record<number, string>>({})

const isStockCheck = ref(false)
const isMoveStock = ref(false)
const isEditLocations = ref(false)

// Functions
const setActivePickingLocation = (location: StockLocation, scope: string) => {
    // Leave it disabled for now. Always have active location. Checked through DB & Logic across actions. Need to ask Raul next
    // activePickingLocationWholesale.value = activePickingLocationWholesale.value === location.id ? null : location.id;
    if (scope == 'wholesale') {
        if (activePickingLocationWholesale.value === location.id) return;
        activePickingLocationWholesale.value = location.id;
    
        updateStockLocation(location, {
            set_as_priority_wholesale: true
        });
    }else {

        if (activePickingLocationDropshipping.value === location.id) return;
        activePickingLocationDropshipping.value = location.id;
    
        updateStockLocation(location, {
            set_as_priority_dropshipping: true
        });
    }
}

const questionPopoverRefs = ref<Record<number, any>>({})
const locationMinMaxStock = ref<Record<number, { min_stock: number | null, max_stock: number | null, replenishment_stock: number | null }>>({})
const questionPopovers = ref<Record<number, boolean>>({})
const tempMinMaxStock = ref<Record<number, { min_stock: number | null, max_stock: number | null, replenishment_stock: number | null }>>({})


// const setPopoverRef = (el: any, locationId: number) => {
//     if (el) {
//         popoverRefs.value[locationId] = el
//     }
// }

const setQuestionPopoverRef = (el: any, locationId: number) => {
    if (el) {
        questionPopoverRefs.value[locationId] = el
    }
}


const toggleQuestionPopover = async (locationId: number, event: Event) => {
    event.stopPropagation()

    // Initialize tempMinMaxStock for this location if not exists
    if (!tempMinMaxStock.value[locationId]) {
        tempMinMaxStock.value[locationId] = { min: null, max: null, replenishment: null }
    }

    // Close all other question popovers first
    Object.keys(questionPopoverRefs.value).forEach(key => {
        const keyNum = parseInt(key)
        if (keyNum !== locationId && questionPopoverRefs.value[keyNum]) {
            questionPopoverRefs.value[keyNum].hide()
        }
    })

    // Initialize temp min/max
    if (!questionPopovers.value[locationId]) {
        tempMinMaxStock.value[locationId] = {
            min_stock: locationMinMaxStock.value[locationId]?.min_stock || null,
            max_stock: locationMinMaxStock.value[locationId]?.max_stock || null,
            replenishment_stock: locationMinMaxStock.value[locationId]?.replenishment_stock || null
        }
    }

    // Toggle current popover
    if (questionPopoverRefs.value[locationId]) {
        questionPopoverRefs.value[locationId].toggle(event)
        questionPopovers.value[locationId] = !questionPopovers.value[locationId]

        const key = isActivePickingLocation(locationId)
            ? `min-${locationId}`
            : `replenish-${locationId}`

        focusWithRetry(key)
    }
}

const saveMinMaxStock = (location: StockLocation) => {
    // Initialize if not exists
    const locationId = location.id; 

    if (!tempMinMaxStock.value[locationId]) {
        tempMinMaxStock.value[locationId] = { min_stock: null, max_stock: null, replenishment_stock: null }
    }

    // Validation: min should not be greater than max
    const min = tempMinMaxStock.value[locationId]?.min_stock
    const max = tempMinMaxStock.value[locationId]?.max_stock

    if (min !== null && max !== null && min > max) {
        alert(trans('Minimum stock cannot be greater than maximum stock'))
        return
    }

    locationMinMaxStock.value[locationId] = {
        min_stock: tempMinMaxStock.value[locationId]?.min_stock || null,
        max_stock: tempMinMaxStock.value[locationId]?.max_stock || null,
        replenishment_stock: tempMinMaxStock.value[locationId]?.replenishment_stock || null
    }
    questionPopovers.value[locationId] = false
    if (questionPopoverRefs.value[locationId]) {
        questionPopoverRefs.value[locationId].hide()
    }

    updateStockLocation(location, {
        ...locationMinMaxStock.value[locationId]
    })
}

const cancelMinMaxStock = (locationId: number) => {
    questionPopovers.value[locationId] = false
    if (tempMinMaxStock.value[locationId]) {

        let currLocSetting = props.stocks_management.locations.find((data) => data.id == locationId)?.settings ?? null;

        tempMinMaxStock.value[locationId] = { 
            min_stock: currLocSetting?.min_stock ?? null, 
            max_stock: currLocSetting?.max_stock ?? null, 
            replenishment_stock: currLocSetting?.replenishment_stock ?? null 
        }
    }

    if (questionPopoverRefs.value[locationId]) {
        questionPopoverRefs.value[locationId].hide()
    }
}

// Section: Notes
const _popoverNotes = ref<Record<number, any>>({})
const tempLocToEdit = ref<StockLocation | null>(null)
const onNotePopoverShow = () => {
    focusWithRetry('note')
}
const toggleNotePopover = async(event: Event, loc: StockLocation) => {
    if(isLoadingNoteUpdate.value === loc.id) return;

    event.stopPropagation()

    tempLocToEdit.value = {...loc}

    _popoverNotes.value?.toggle(event)

}
const onSaveNote = (editedLoc: StockLocation) => {
    // locationNotes.value[locationId] = tempNotes.value[locationId] || ''
    // notePopovers.value[locationId] = false
    if (_popoverNotes.value) {
        _popoverNotes.value.hide()
    }

    updateStockLocation(editedLoc, {
        notes: editedLoc.notes
    })
}

// const cancelNote = (locationId: number) => {
//     notePopovers.value[locationId] = false
//     tempNotes.value[locationId] = ''
//     if (_popoverNotes.value[locationId]) {
//         _popoverNotes.value[locationId].hide()
//     }
// }

// const hasNote = (locationId: number) => {
//     return locationNotes.value[locationId] && locationNotes.value[locationId].trim().length > 0
// }

// const hasMinMaxStock = (locationId: number) => {
//     return locationMinMaxStock.value[locationId] &&
//         (locationMinMaxStock.value[locationId].min !== null || locationMinMaxStock.value[locationId].max !== null)
// }

const getQuestionTooltip = (locationId: number) => {
    if (isActivePickingLocation(locationId)) {
        return trans('Recommended min/max stock')
    } else {
        return trans('Recommended replenishment quantity')
    }
}

onMounted(() => {
    // Initialize activePickingLocationWholesale
    activePickingLocationWholesale.value = props.stocks_management.locations.find((data) => data.default_wholesale_picking_location == true)?.id ?? null;
    activePickingLocationDropshipping.value = props.stocks_management.locations.find((data) => data.default_dropshipping_picking_location == true)?.id ?? null;

    // Initialize Min/Max or Replenishment values
    tempMinMaxStock.value = Object.fromEntries(
        props.stocks_management.locations.map((data) => [
            data.id,
            {
                min_stock: data.settings.min_stock ?? null,
                max_stock: data.settings.max_stock ?? null,
                replenishment_stock: data.settings.replenishment_stock ?? null,
            }
        ])
    )

    // Initialize notePopovers values
    notePopovers.value = Object.fromEntries(
        props.stocks_management.locations.map((data) => [
            data.id,
            data.notes ?? ""
        ])
    )
})

const isLoadingNoteUpdate = ref<Number|null>(null)
const isLoadingQtyUpdate = ref<Number|null>(null)

const updateStockLocation = async (stockLoc: StockLocation, body: {}) => {
    console.info(Object.keys(body));
    let successMsg = `Successfully modified ${stockLoc.code} data`
    let failMsg = `Failed to modify ${stockLoc.code} data`

    if (Object.keys(body).includes('notes')) {
        successMsg = `Successfully updated '${stockLoc.code}' Note`
        failMsg = `There was an error updating ${stockLoc.code} Note`
        isLoadingNoteUpdate.value = stockLoc.id;
    } else if (Object.keys(body).find(key =>
        ['min_stock', 'max_stock', 'replenishment_stock'].includes(key)
    )) {
        isLoadingQtyUpdate.value = stockLoc.id;
    } else if (Object.keys(body).includes('set_as_priority_wholesale')) {
        isLoadingActiveLocationWholesale.value = stockLoc.id;
    } else if (Object.keys(body).includes('set_as_priority_dropshipping')) {
        isLoadingActiveLocationDropshipping.value = stockLoc.id;
    }


    router.patch(route('grp.models.location_org_stock.update', {
        locationOrgStock: stockLoc.id
    }), body, 
    {
        preserveScroll: true,
        onSuccess: () => {
            notify({
                title: "Success",
                text: successMsg,
                type: "success"
            })
        },
        onError: (err) => {
            notify({
                title: "Failed",
                text: failMsg,
                type: "error"
            })
        },
        onFinish: () => {
            isLoadingNoteUpdate.value = null;
            isLoadingQtyUpdate.value = null;
            isLoadingActiveLocationWholesale.value = null;
            isLoadingActiveLocationDropshipping.value = null;
        }
    })
}

const isActivePickingLocation = (stockLocId: number) => {
    return activePickingLocationDropshipping.value === stockLocId || activePickingLocationWholesale.value === stockLocId;
}

const activeModal = ref<string | null>(null)

const MODALS = {
    STOCK_CHECK: 'stock_check',
    MOVE_STOCK: 'move_stock',
    EDIT_LOCATION: 'edit_location',
    ADD_LOCATION: 'add_location'
}

const isStockCheckModalOpen = ref(false)
const isMoveStockModalOpen = ref(false)
const isEditLocationModalOpen = ref(false)
const isAddLocationModalOpen = ref(false)
const selectedLocationId = ref<number | null>(null)
const openModal = (type: string, payload: number | null = null) => {
    activeModal.value = type

    // For stock check modal we want to re-trigger focus even when the same location is selected again.
    if (type === MODALS.STOCK_CHECK) {
        selectedLocationId.value = null
        nextTick(() => {
            selectedLocationId.value = payload
        })
        isStockCheckModalOpen.value = true
        return
    }

    selectedLocationId.value = payload

    switch(type) {
        case MODALS.MOVE_STOCK:
            isMoveStockModalOpen.value = true
            break
        case MODALS.EDIT_LOCATION:
            isEditLocationModalOpen.value = true
            break
        case MODALS.ADD_LOCATION:
            isAddLocationModalOpen.value = true
            break
    }
}
const inputRefs = ref<Record<string | number, any>>({})

const setInputRef = (el: any, id: string | number) => {
    if (el) inputRefs.value[id] = el
}

const focusWithRetry = async (key: string, attempt = 0) => {
    await nextTick()

    requestAnimationFrame(() => {
        const comp = inputRefs.value[key]
        const el =
            comp?.$el?.querySelector?.('input, textarea') ??
            comp?.$el ??
            comp

        if (el?.focus) {
            el.focus()
            el.select?.()

            if (document.activeElement === el || attempt >= 10) return
        }

        if (attempt < 10) {
            setTimeout(() => focusWithRetry(key, attempt + 1), 80)
        }
    })
}

const addLocationRef = ref()

const onAddLocationShow = () => {
    nextTick(() => {
        addLocationRef.value?.focusLocationSelect()
    })
}
</script>

<template>
    <div class="mx-auto bg-white shadow-md rounded-lg p-4 space-y-4">
        
        <!-- Header Section -->
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <!-- <FontAwesomeIcon :icon="faBox"></FontAwesomeIcon> Active -->
            </h2>
            <button class="text-gray-500 hover:text-gray-700">
                <FontAwesomeIcon :icon="faPlusCircle" class="text-xl"></FontAwesomeIcon>
            </button>
        </div>
        <!-- Section: Summary Stats -->
        <div class="grid grid-cols-7 gap-x-3 gap-2 p-2">
            <div class="grid grid-cols-3 gap-2 text-center col-span-6">
                <div v-for="(item, key) in stocks_management.summary" class="bg-gray-100 p-2 rounded" v-tooltip="item.icon_state.tooltip">
                    <span>
                        <Icon :data="{...item.icon_state, tooltip : null}" />
                    </span>
                    <span class="ml-2 text-lg font-bold">
                        {{ locale.number(item.value ?? 0) }}
                    </span>
                </div>
            </div>
            <div class="grid align-item-middle border-l">
                <span class="my-auto text-center font-semibold" v-tooltip="trans('Stock in Location')">
                    {{ locale.number(stocks_management.qty_in_location ?? 0) }}
                </span>
            </div>
        </div>

        <!-- Section: Location Grid -->
        <div class="border-t pt-2 gap-2 items-center text-gray-700">
            
                <Dialog
                    v-model:visible="isStockCheckModalOpen"
                    :header="`${trans('Audit Stock')} - ${props.trade_units[0]?.code}`"
                    modal
                    :dismissableMask="true"
                    :closeOnEscape="true"
                    :focusOnShow="false"
                    :style="{ width: '50vw' }"
                    :breakpoints="{
                        '1200px': '75vw',
                        '992px': '80vw',
                        '768px': '90vw',
                        '576px': '95vw'
                    }"
                    :contentStyle="{ maxHeight: '70vh', overflow: 'auto' }"
                >
                    <StockCheck                        
                        :selectedLocationId="selectedLocationId"
                        :locations="props.stocks_management.locations"
                        @close="isStockCheckModalOpen = false"
                        :auditRoute="props.stocks_management?.routes?.audit_route"
                    />
                </Dialog>
                
                 <Dialog v-model:visible="isMoveStockModalOpen" modal :header="trans('Move Stock')"
                    :style="{ width: '50vw' }"
                    :dismissableMask="true"
                    :closeOnEscape="true"
                    :breakpoints="{
                        '1200px': '75vw',
                        '992px': '80vw',
                        '768px': '90vw',
                        '576px': '95vw'
                    }">
                    <MoveStock
                        :part_locations="props.stocks_management.locations"
                        :replenishment_data="tempMinMaxStock"
                        @close="isMoveStockModalOpen = false"
                    />
                 </Dialog>

                <EditLocationsModal
                    v-model="isEditLocationModalOpen"
                    :locations="props.stocks_management.locations"
                    :routes="props.stocks_management.routes"
                />

                <Dialog v-model:visible="isAddLocationModalOpen" modal :header="trans('Add Location')"
                    @show="onAddLocationShow"
                    :style="{ width: '50vw' }"
                    :dismissableMask="true"
                    :closeOnEscape="true"
                    :breakpoints="{
                        '1200px': '75vw',
                        '992px': '80vw',
                        '768px': '90vw',
                        '576px': '95vw'
                    }"
                    :contentStyle="{ overflow: 'visible' }">
                     <AddLocations
                        ref="addLocationRef"
                        :locations="props.stocks_management.locations"
                        @close="isAddLocationModalOpen = false"
                        :routes="props.stocks_management?.routes"
                    />
                </Dialog>

                <div v-for="(loc, idx) in props.stocks_management.locations" :key="loc.id"
                        class="grid grid-cols-7 gap-x-3 items-center gap-2 p-2 rounded transition-colors duration-200 mb-1"
                        :class="{
                            'bg-blue-50 border border-blue-200': activePickingLocationDropshipping === loc.id && activePickingLocationWholesale !== loc.id,
                            'bg-orange-50 border border-orange-200': activePickingLocationDropshipping !== loc.id && activePickingLocationWholesale === loc.id,
                            'bg-purple-50 border border-purple-200': activePickingLocationDropshipping === loc.id && activePickingLocationWholesale === loc.id,
                            'hover:bg-gray-50': activePickingLocationDropshipping !== loc.id && activePickingLocationWholesale !== loc.id,

                        }">
                        <div class="col-span-4 flex items-center gap-x-3">
                            <!-- Note Icon with Popover -->
                            <div class="relative">
                                <div @click="(event) => toggleNotePopover(event, loc)"
                                    v-tooltip="trans(`Add part's location note`)"
                                    class="cursor-pointer transition-colors duration-200"
                                    :class="loc.notes ? 'text-orange-600' : 'text-gray-400 hover:text-gray-700'"
                                >
                                    <LoadingIcon v-if="isLoadingNoteUpdate === loc.id"/>
                                    <FontAwesomeIcon v-else :icon="loc.notes ? 'fas fa-sticky-note' : 'fal fa-sticky-note'" class="" fixed-width aria-hidden="true" />
                                </div>
                            </div>

                            <!-- TODO ENABLE ON PRODUCTION  -->
                            <!-- Wholesale Icon -->
                            <div v-if="layout.app.environment === 'local'" @click="() => setActivePickingLocation(loc, 'wholesale')"
                                v-tooltip="trans('Set as active picking location [Wholesale]')"
                                class="cursor-pointer transition-colors duration-200" :class="{
                                    'text-orange-500': activePickingLocationWholesale === loc.id,
                                    'text-gray-600 hover:text-orange-400 opacity-30 hover:opacity-60': activePickingLocationWholesale !== loc.id
                                }">
                                <LoadingIcon v-if="isLoadingActiveLocationWholesale === loc.id" />
                                <FontAwesomeIcon v-else :icon="activePickingLocationWholesale === loc.id ? 'fas fa-dolly-flatbed-empty' : 'fal fa-dolly-flatbed-empty'"
                                    class="" fixed-width aria-hidden="true" />
                            </div>
                            <div v-else>
                                <FontAwesomeIcon :icon="faBan" class="text-red-500" v-tooltip="'Work in Progress. Remember to disable this on Production when done'"/>
                            </div>

                            <!-- TODO ENABLE ON PRODUCTION  -->
                            <div v-if="layout.app.environment === 'local'" @click="() => setActivePickingLocation(loc, 'dropshipping')"
                                v-tooltip="trans('Set as active picking location [Dropshipping]')"
                                class="transition-colors duration-200 cursor-pointer" :class="{
                                    'text-gray-400': activePickingLocationDropshipping !== loc.id,
                                    'text-gray-600  opacity-30 hover:opacity-60': activePickingLocationDropshipping !== loc.id,
                                    'text-blue-700': activePickingLocationDropshipping === loc.id,
                                    'hover:text-blue-500': activePickingLocationDropshipping !== loc.id
                                }">
                                <LoadingIcon v-if="isLoadingActiveLocationDropshipping === loc.id" />
                                <FontAwesomeIcon v-else :icon="activePickingLocationDropshipping === loc.id ? 'fas fa-shopping-basket' : 'fal fa-shopping-basket'"
                                    class="" fixed-width aria-hidden="true" />
                            </div>
                            <div v-else>
                                <FontAwesomeIcon :icon="faBan" class="text-red-500" v-tooltip="'Work in Progress. Remember to disable this on Production when done'"/>
                            </div>

                            <span class="font-medium">
                                <Link :href="route('grp.org.warehouses.show.infrastructure.locations.show', {
                                    organisation: loc.location.organisation_slug,
                                    warehouse: loc.location.warehouse_slug,
                                    location: loc.location.slug,
                                })" :class="'primaryLink'">
                                    {{ loc.code }}
                                </Link>
                            </span>

                            <!-- Question Icon(s) -->
                            <div @click="(event) => toggleQuestionPopover(loc.id, event)"
                                v-tooltip="getQuestionTooltip(loc.id)"
                                class="cursor-pointer text-gray-400 hover:text-gray-700 flex gap-1">
                                <LoadingIcon v-if="isLoadingQtyUpdate === loc.id"/>
                                <span v-else-if="(tempMinMaxStock[loc?.id]?.min_stock || tempMinMaxStock[loc?.id]?.max_stock) && isActivePickingLocation(loc.id)">( {{ tempMinMaxStock[loc?.id]?.min_stock }}, {{ tempMinMaxStock[loc?.id]?.max_stock }}
                                    )</span>
                                <span v-else-if="tempMinMaxStock[loc?.id]?.replenishment_stock && !isActivePickingLocation(loc.id)">( {{ tempMinMaxStock[loc?.id]?.replenishment_stock }} )</span>
                                <div v-else>
                                    <FontAwesomeIcon icon="fal fa-question-square" class="" fixed-width
                                        aria-hidden="true" />
                                    <!-- Show second question icon only when location is active -->
                                    <FontAwesomeIcon v-if="isActivePickingLocation(loc.id)"
                                        icon="fal fa-question-square" class="" fixed-width aria-hidden="true" />
                                </div>

                            </div>

                            <!-- Question Popover -->
                            <Popover :ref="(el) => setQuestionPopoverRef(el, loc.id)">
                                <div class="w-80 p-2">
                                    <div class="mb-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ isActivePickingLocation(loc.id) ? trans('Min/Max Stock') :
                                            trans('Replenishment Quantity') }} - {{ loc.code }}
                                        </label>

                                        <!-- Show Min/Max inputs when location is active -->
                                        <div v-if="isActivePickingLocation(loc.id)" class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                                    {{ trans('Min') }}
                                                </label>
                                                <InputNumber :modelValue="tempMinMaxStock[loc.id]?.min_stock || null"
                                                    :ref="(el) => setInputRef(el, `min-${loc.id}`)"
                                                    @update:modelValue="(val) => {
                                                        if (!tempMinMaxStock[loc.id]) tempMinMaxStock[loc.id] = { min_stock: null, max_stock: null }
                                                        tempMinMaxStock[loc.id].min_stock = val
                                                    }" class="w-full" :placeholder="trans('Enter minimum stock')"
                                                    :min="0" autofocus/>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                                    {{ trans('Max') }}
                                                </label>
                                                <InputNumber :modelValue="tempMinMaxStock[loc.id]?.max_stock || null"
                                                    @update:modelValue="(val) => {
                                                        if (!tempMinMaxStock[loc.id]) tempMinMaxStock[loc.id] = { min_stock: null, max_stock: null }
                                                        tempMinMaxStock[loc.id].max_stock = val
                                                    }" class="w-full" :placeholder="trans('Enter maximum stock')"
                                                    :min="0" />
                                            </div>
                                        </div>

                                        <!-- Show Replenishment input when location is not active -->
                                        <div v-else>
                                            <InputNumber :ref="(el) => setInputRef(el, `replenish-${loc.id}`)" autofocus :modelValue="tempMinMaxStock[loc.id]?.replenishment_stock || null"
                                                @update:modelValue="(val) => {
                                                if (!tempMinMaxStock[loc.id]) tempMinMaxStock[loc.id] = { min_stock: null, max_stock: null, replenishment_stock: null }
                                                tempMinMaxStock[loc.id].replenishment_stock = val
                                                }" class="w-full" :placeholder="trans('Enter replenishment quantity')"
                                                :min="0" />
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button @click="() => cancelMinMaxStock(loc.id)"
                                            class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            {{ trans('Cancel') }}
                                        </button>
                                        <button @click="() => saveMinMaxStock(loc)"
                                            class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            {{ trans('Save') }}
                                        </button>
                                    </div>
                                </div>
                            </Popover>
                        </div>

                        <div v-if="loc.audited_at"
                            v-tooltip="trans('Last audit :xdate', { xdate: useFormatTime(new Date(loc.audited_at)) })"
                            class="col-span-2 text-center text-sm whitespace-nowrap">
                            {{ formatDistanceStrict(new Date(loc.audited_at), new Date()) }}
                            <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400 ml-1" fixed-width aria-hidden="true" />
                        </div>
                        <div v-else
                            class="col-span-2 text-center text-sm italic opacity-60 whitespace-nowrap">
                            {{ trans("Never audited") }}
                        </div>
                        
                        <div class="text-center font-semibold border-l">
                            <span
                                v-tooltip="trans('Stock quantity')"
                                class="cursor-pointer hover:text-blue-500 transition"
                                @dblclick="openModal(MODALS.STOCK_CHECK, loc.id)"
                            >
                                {{ Number(loc.quantity) }}
                            </span>
                        </div>
                </div>            
        </div>

        <!-- Action Buttons -->
        <!-- TODO ENABLE ON PRODUCTION  -->
        <div class="flex justify-between border-t pt-3 gap-2" v-if="layout.app.environment === 'local'">
            <Button @click="openModal(MODALS.STOCK_CHECK)" iconRight="fal fa-clipboard-check" :label="trans('Audit Stock')" size="sm" type="tertiary" />
            <Button @click="openModal(MODALS.MOVE_STOCK)" iconRight="fal fa-forklift" :label="trans('Move Stock')" size="sm" type="tertiary" />
            <Button @click="openModal(MODALS.EDIT_LOCATION)" iconRight="fal fa-edit" :label="trans('Edit Locations')" size="sm" type="tertiary" />
            <Button @click="openModal(MODALS.ADD_LOCATION)" iconRight="fal fa-plus" :label="trans('Add Location')" size="sm" type="tertiary" />
        </div>
        <div class="flex justify-between border-t pt-3 gap-2" v-else>
            <Button @click="() => { WINDOW.alert('Work in Progres') }" iconRight="fal fa-clipboard-check" :label="trans('Audit Stock')" size="sm" type="tertiary" />
            <Button @click="() => { WINDOW.alert('Work in Progres') }" iconRight="fal fa-forklift" :label="trans('Move Stock')" size="sm" type="tertiary" v-if="layout.app.environment === 'local'"/>
            <Button @click="() => { WINDOW.alert('Work in Progres') }" iconRight="fal fa-edit" :label="trans('Edit Locations')" size="sm" type="tertiary" />
            <Button @click="() => { WINDOW.alert('Work in Progres') }" iconRight="fal fa-plus" :label="trans('Add Location')" size="sm" type="tertiary" v-if="layout.app.environment === 'local'"/>
        </div>

        <!-- Popover: Notes -->
        <Popover ref="_popoverNotes" @show="onNotePopoverShow">
            <div class="w-80 p-2">
                <div class="mb-3">
                    <label class="block text-sm mb-2">
                        {{ trans('Location Note') }} - <span class="font-bold">{{ tempLocToEdit?.code }}</span>
                    </label>

                    <PureTextarea
                        :ref="(el) => setInputRef(el, 'note')"
                        :modelValue="tempLocToEdit?.notes || ''"
                        @update:modelValue="(val) => {
                            if (tempLocToEdit) {
                                tempLocToEdit.notes = val
                            }
                        }"
                        autofocus
                        :placeholder="trans('Enter note for this location...')"
                        class="resize-none"
                        rows="4"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        @click="() => _popoverNotes?.hide()"
                        type="negative"
                        label="Cancel"
                    />
                    <Button
                        @click="() => onSaveNote(tempLocToEdit)"
                        label="Save"
                        full
                    />
                </div>
            </div>
        </Popover>
    </div>
</template>
