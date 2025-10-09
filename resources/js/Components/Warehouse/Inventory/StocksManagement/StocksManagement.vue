<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faForklift, faInventory, faClipboardCheck, faQuestionSquare, faDotCircle } from "@fal"
import { faShoppingBasket, faStickyNote, faShoppingCart } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, nextTick } from 'vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { now } from 'lodash'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { InputNumber, Popover } from 'primevue'
import StockCheck from './StockCheck.vue'
import MoveStock from './MoveStock.vue'
import EditLocations from './EditLocations.vue'
import { Icon as IconTS } from '@/types/Utils/Icon'
import Icon from '@/Components/Icon.vue'
import { routeType } from '@/types/route'
library.add(faForklift, faInventory, faClipboardCheck, faQuestionSquare, faDotCircle, faShoppingBasket, faStickyNote, faShoppingCart)

const props = defineProps<{
    stocks_management: {
        routes: {
            fetch_locations: routeType
            submit_audit_stocks: routeType
            update_stocks_locations: routeType
        }
        summary: {
            [key: string]: {
                icon_state: IconTS
                value: number
            }
        }
        part_locations: {
            id: number
            name: string
            slug: string
            stock: number
            isAudited: boolean
        }[]
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

// Active picking location state
const activePickingLocation = ref<number | null>(null)

// Notes state - dummy data structure
const locationNotes = ref<Record<number, string>>({
    // Example: location id as key, note as value
})

// Popover states
const notePopovers = ref<Record<number, boolean>>({})
const tempNotes = ref<Record<number, string>>({})

const isStockCheck = ref(false)
const isMoveStock = ref(false)
const isEditLocations = ref(false)

// Functions
const setActivePickingLocation = (locationId: number) => {
    activePickingLocation.value = activePickingLocation.value === locationId ? null : locationId
}

const popoverRefs = ref<Record<number, any>>({})
const questionPopoverRefs = ref<Record<number, any>>({})
const locationMinMaxStock = ref<Record<number, { min: number | null, max: number | null, replenishment: number | null }>>({})
const questionPopovers = ref<Record<number, boolean>>({})
const tempMinMaxStock = ref<Record<number, { min: number | null, max: number | null, replenishment: number | null }>>({})


const setPopoverRef = (el: any, locationId: number) => {
    if (el) {
        popoverRefs.value[locationId] = el
    }
}

const setQuestionPopoverRef = (el: any, locationId: number) => {
    if (el) {
        questionPopoverRefs.value[locationId] = el
    }
}

const toggleNotePopover = (locationId: number, event: Event) => {
    event.stopPropagation()

    // Close all other popovers first
    Object.keys(popoverRefs.value).forEach(key => {
        const keyNum = parseInt(key)
        if (keyNum !== locationId && popoverRefs.value[keyNum]) {
            popoverRefs.value[keyNum].hide()
        }
    })

    // Initialize temp note
    if (!notePopovers.value[locationId]) {
        tempNotes.value[locationId] = locationNotes.value[locationId] || ''
    }

    // Toggle current popover
    if (popoverRefs.value[locationId]) {
        popoverRefs.value[locationId].toggle(event)
        notePopovers.value[locationId] = !notePopovers.value[locationId]
    }
}

const toggleQuestionPopover = (locationId: number, event: Event) => {
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
            min: locationMinMaxStock.value[locationId]?.min || null,
            max: locationMinMaxStock.value[locationId]?.max || null,
            replenishment: locationMinMaxStock.value[locationId]?.replenishment || null
        }
    }

    // Toggle current popover
    if (questionPopoverRefs.value[locationId]) {
        questionPopoverRefs.value[locationId].toggle(event)
        questionPopovers.value[locationId] = !questionPopovers.value[locationId]
    }
}

const saveMinMaxStock = (locationId: number) => {
    // Initialize if not exists
    if (!tempMinMaxStock.value[locationId]) {
        tempMinMaxStock.value[locationId] = { min: null, max: null, replenishment: null }
    }

    // Validation: min should not be greater than max
    const min = tempMinMaxStock.value[locationId]?.min
    const max = tempMinMaxStock.value[locationId]?.max

    if (min !== null && max !== null && min > max) {
        alert(trans('Minimum stock cannot be greater than maximum stock'))
        return
    }

    locationMinMaxStock.value[locationId] = {
        min: tempMinMaxStock.value[locationId]?.min || null,
        max: tempMinMaxStock.value[locationId]?.max || null,
        replenishment: tempMinMaxStock.value[locationId]?.replenishment || null
    }
    questionPopovers.value[locationId] = false
    if (questionPopoverRefs.value[locationId]) {
        questionPopoverRefs.value[locationId].hide()
    }
}

const cancelMinMaxStock = (locationId: number) => {
    questionPopovers.value[locationId] = false
    if (tempMinMaxStock.value[locationId]) {
        tempMinMaxStock.value[locationId] = { min: null, max: null, replenishment: null }
    }
    if (questionPopoverRefs.value[locationId]) {
        questionPopoverRefs.value[locationId].hide()
    }
}

const saveNote = (locationId: number) => {
    locationNotes.value[locationId] = tempNotes.value[locationId] || ''
    notePopovers.value[locationId] = false
    if (popoverRefs.value[locationId]) {
        popoverRefs.value[locationId].hide()
    }
}

const cancelNote = (locationId: number) => {
    notePopovers.value[locationId] = false
    tempNotes.value[locationId] = ''
    if (popoverRefs.value[locationId]) {
        popoverRefs.value[locationId].hide()
    }
}

const hasNote = (locationId: number) => {
    return locationNotes.value[locationId] && locationNotes.value[locationId].trim().length > 0
}

// const hasMinMaxStock = (locationId: number) => {
//     return locationMinMaxStock.value[locationId] &&
//         (locationMinMaxStock.value[locationId].min !== null || locationMinMaxStock.value[locationId].max !== null)
// }

const getQuestionTooltip = (locationId: number) => {
    if (activePickingLocation.value === locationId) {
        return trans('Recommended min/max stock')
    } else {
        return trans('Recommended replenishment quantity')
    }
}
</script>

<template>
    <div class="max-w-xl mx-auto bg-white shadow-md rounded-lg p-4 space-y-4">
        
        <!-- Header Section -->
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <i class="fas fa-box"></i> Active
            </h2>
            <button class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-plus-circle text-xl"></i>
            </button>
        </div>

        <!-- Section: Summary Stats -->
        <div class="grid grid-cols-4 gap-2 text-center">
            <div v-for="(item, key) in stocks_management.summary" class="bg-gray-100 p-2 rounded">
                <span>
                    <!-- <FontAwesomeIcon :icon="item.icon_state" class="text-gray-500" fixed-width aria-hidden="true" /> -->
                    <Icon :data="item.icon_state" />
                </span>
                <span class="ml-2 text-lg font-bold">
                    {{ locale.number(item.value ?? 0) }}
                </span>
            </div>
        </div>

        <!-- Out of Stock -->
        <div class="border-t pt-2">
            <p class="font-semibold text-gray-600">Out of stock</p>
        </div>

        <!-- Stock Value Section -->
        <div class="border-t pt-2">
            <div class="grid grid-cols-7 gap-x-3">
                <div class="col-span-2 font-semibold text-gray-600">Stock value:</div>
                <div class="col-span-3 text-right">
                    4720000
                </div>
                <div class="col-span-2 text-right">
                    8000 /SKO
                </div>
            </div>
            <div class="grid grid-cols-7 gap-x-3">
                <div class="col-span-2 font-semibold text-gray-600">Current cost:</div>
                <div class="col-span-3 text-right">
                    <!-- 4720000 -->
                </div>
                <div class="col-span-2 text-right">
                    8000 /Unit
                </div>
            </div>
        </div>

        <!-- Section: Location Grid -->
        <div class="border-t pt-2 gap-2 items-center text-gray-700">
            <KeepAlive>
                <template v-if="isStockCheck">
                    <StockCheck
                        :part_locations="props.stocks_management.part_locations"
                        @onClickBackground="isStockCheck = false"
                    />
                </template>
                <template v-else-if="isMoveStock">
                    <MoveStock
                        :part_locations="props.stocks_management.part_locations"
                        @onClickBackground="isMoveStock = false"
                    />
                </template>
                <template v-else-if="isEditLocations">
                    <EditLocations
                        :part_locations="props.stocks_management.part_locations"
                        @onClickBackground="isEditLocations = false"
                    />
                </template>
                <div v-else>
                    <div v-for="(loc, idx) in props.stocks_management.part_locations" :key="loc.id"
                        class="grid grid-cols-7 gap-x-3 items-center gap-2 p-2 rounded transition-colors duration-200"
                        :class="{
                            'bg-blue-50 border border-blue-200': activePickingLocation === loc.id,
                            'hover:bg-gray-50': activePickingLocation !== loc.id
                        }">
                        <div class="col-span-5 flex items-center gap-x-2">
                            <!-- Note Icon with Popover -->
                            <div class="relative">
                                <div @click="(event) => toggleNotePopover(loc.id, event)"
                                    v-tooltip="trans('Add part\'s location note')"
                                    class="cursor-pointer transition-colors duration-200" :class="{
                                        'text-orange-600': hasNote(loc.id),
                                        'text-gray-400 hover:text-gray-700': !hasNote(loc.id)
                                    }">
                                    <FontAwesomeIcon
                                        :icon="hasNote(loc.id) ? 'fas fa-sticky-note' : 'fal fa-sticky-note'" class=""
                                        fixed-width aria-hidden="true" />
                                </div>

                                <!-- Note Popover -->
                                <Popover :ref="(el) => setPopoverRef(el, loc.id)">
                                    <div class="w-80 p-2">
                                        <div class="mb-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                {{ trans('Location Note') }} - {{ loc.name }}
                                            </label>
                                            <textarea v-model="tempNotes[loc.id]"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                                rows="4"
                                                :placeholder="trans('Enter note for this location...')"></textarea>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <button @click="() => cancelNote(loc.id)"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                                {{ trans('Cancel') }}
                                            </button>
                                            <!-- <button @click="() => saveNote(loc.id)"
                                                class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                {{ trans('Save') }}
                                            </button> -->
                                        </div>
                                    </div>
                                </Popover>
                            </div>

                            <!-- Shopping Basket Icon -->
                            <div @click="() => setActivePickingLocation(loc.id)"
                                v-tooltip="trans('Set as active picking location')"
                                class="cursor-pointer transition-colors duration-200" :class="{
                                    'text-blue-700': activePickingLocation === loc.id,
                                    'text-gray-400 hover:text-gray-700': activePickingLocation !== loc.id
                                }">
                                <FontAwesomeIcon
                                    :icon="activePickingLocation === loc.id ? 'fas fa-shopping-basket' : 'fal fa-shopping-basket'"
                                    class="" fixed-width aria-hidden="true" />
                            </div>

                            <span class="font-medium">{{ loc.name }}</span>

                            <!-- Question Icon(s) -->
                            <div @click="(event) => toggleQuestionPopover(loc.id, event)"
                                v-tooltip="getQuestionTooltip(loc.id)"
                                class="cursor-pointer text-gray-400 hover:text-gray-700 flex gap-1">
                                <span v-if="(tempMinMaxStock[loc?.id]?.min || tempMinMaxStock[loc?.id]?.max) && activePickingLocation === loc.id">( {{ tempMinMaxStock[loc?.id]?.min }}, {{ tempMinMaxStock[loc?.id]?.max }}
                                    )</span>
                                <span v-else-if="tempMinMaxStock[loc?.id]?.replenishment && activePickingLocation !== loc.id">( {{ tempMinMaxStock[loc?.id]?.replenishment }} )</span>
                                <div v-else>
                                    <FontAwesomeIcon icon="fal fa-question-square" class="" fixed-width
                                        aria-hidden="true" />
                                    <!-- Show second question icon only when location is active -->
                                    <FontAwesomeIcon v-if="activePickingLocation === loc.id"
                                        icon="fal fa-question-square" class="" fixed-width aria-hidden="true" />
                                </div>

                            </div>

                            <!-- Question Popover -->
                            <Popover :ref="(el) => setQuestionPopoverRef(el, loc.id)">
                                <div class="w-80 p-2">
                                    <div class="mb-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ activePickingLocation === loc.id ? trans('Min/Max Stock') :
                                            trans('Replenishment Quantity') }} - {{ loc.name }}
                                        </label>

                                        <!-- Show Min/Max inputs when location is active -->
                                        <div v-if="activePickingLocation === loc.id" class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                                    {{ trans('Min') }}
                                                </label>
                                                <InputNumber :modelValue="tempMinMaxStock[loc.id]?.min || null"
                                                    @update:modelValue="(val) => {
                                                        if (!tempMinMaxStock[loc.id]) tempMinMaxStock[loc.id] = { min: null, max: null }
                                                        tempMinMaxStock[loc.id].min = val
                                                    }" class="w-full" :placeholder="trans('Enter minimum stock')"
                                                    :min="0" />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                                    {{ trans('Max') }}
                                                </label>
                                                <InputNumber :modelValue="tempMinMaxStock[loc.id]?.max || null"
                                                    @update:modelValue="(val) => {
                                                        if (!tempMinMaxStock[loc.id]) tempMinMaxStock[loc.id] = { min: null, max: null }
                                                        tempMinMaxStock[loc.id].max = val
                                                    }" class="w-full" :placeholder="trans('Enter maximum stock')"
                                                    :min="0" />
                                            </div>
                                        </div>

                                        <!-- Show Replenishment input when location is not active -->
                                        <div v-else>
                                            <InputNumber :modelValue="tempMinMaxStock[loc.id]?.replenishment || null"
                                                @update:modelValue="(val) => {
                                                if (!tempMinMaxStock[loc.id]) tempMinMaxStock[loc.id] = { min: null, max: null, replenishment: null }
                                                tempMinMaxStock[loc.id].replenishment = val
                                                }" class="w-full" :placeholder="trans('Enter replenishment quantity')"
                                                :min="0" />
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button @click="() => cancelMinMaxStock(loc.id)"
                                            class="px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                            {{ trans('Cancel') }}
                                        </button>
                                        <!-- <button @click="() => saveMinMaxStock(loc.id)"
                                            class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            {{ trans('Save') }}
                                        </button> -->
                                    </div>
                                </div>
                            </Popover>
                        </div>
                        <div v-tooltip="trans('Last audit :date', { date: useFormatTime(new Date()) })"
                            class="text-right text-sm">
                            0
                            <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400 ml-1" fixed-width
                                aria-hidden="true" />
                        </div>
                        <div class="text-right font-semibold">
                            {{ loc.stock }}
                        </div>
                    </div>
                </div>
            </KeepAlive>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between border-t pt-3">
            <Button @click="() => isStockCheck = !isStockCheck" iconRight="fal fa-clipboard-check"
                :label="trans('Stock check')" size="sm" type="tertiary" />

            <Button @click="() => isMoveStock = !isMoveStock" iconRight="fal fa-forklift" :label="trans('Move stock')"
                size="sm" type="tertiary" />

            <Button @click="() => isEditLocations = !isEditLocations" iconRight="fal fa-edit"
                :label="trans('Edit locations')" size="sm" type="tertiary" />
        </div>
    </div>
</template>