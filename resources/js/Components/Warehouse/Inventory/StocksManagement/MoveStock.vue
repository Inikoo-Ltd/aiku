<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle } from "@fal"
import { faDotCircle as fasDotCircle } from "@fas"
import { faForklift } from "@fas"
import { faTimes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber } from 'primevue'
import { inject, ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useForm } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
library.add(faDotCircle, fasDotCircle, faForklift, faTimes)

const props = defineProps<{
    part_locations: {
        id: number
        name: string
        slug: string
        stock: number
        isAudited: boolean
    }[]
}>()

const emits = defineEmits<{
    (e: "onClickBackground"): void
}>()

const layout = inject('layout', layoutStructure)

// const dummyData = ref([
//     { id: 1, name: 'E1', lastAudit: new Date(), stock: 45, isAudited: true },
//     { id: 2, name: 'E2', lastAudit: new Date(), stock: 30, isAudited: false },
//     { id: 3, name: 'E3', lastAudit: new Date(), stock: 60, isAudited: true },
//     { id: 4, name: 'E4', lastAudit: new Date(), stock: 20, isAudited: false },
//     { id: 5, name: 'E5', lastAudit: new Date(), stock: 80, isAudited: true }
// ])

// Move stock state
const moveStock = ref({
    from: null,
    to: null,
    quantity: 0,
    isActive: false
})

const form = useForm({
    stockCheck: props.part_locations.map(item => ({
        id: item.id,
        name: item.name,
        stock: item.stock,
        isAudited: item.isAudited
    })),
    moveStock: null
})

const selectSourceWarehouse = (warehouse) => {
    moveStock.value.from = warehouse
    moveStock.value.to = null
    moveStock.value.quantity = 0
    moveStock.value.isActive = true
}

const selectDestinationWarehouse = (warehouse) => {
    moveStock.value.to = warehouse
    form.moveStock = {
        from: moveStock.value.from.name,
        to: moveStock.value.to.name,
        quantity: moveStock.value.quantity
    }
}

const closeMoveStock = () => {
    moveStock.value = {
        from: null,
        to: null,
        quantity: 0,
        isActive: false
    }
    form.moveStock = null
}

const updateMoveQuantity = (value) => {
    // Ensure value is valid and within bounds
    const validValue = value || 0
    const maxQuantity = getMaxQuantity()
    
    // Reset to 0 if value is invalid or exceeds maximum
    if (validValue < 0 || validValue > maxQuantity) {
        moveStock.value.quantity = 0
    } else {
        moveStock.value.quantity = validValue
    }
    
    if (form.moveStock) {
        form.moveStock.quantity = moveStock.value.quantity
    }
}

const getMaxQuantity = () => {
    return moveStock.value.from ? moveStock.value.from.stock : 0
}

const getCalculatedStock = (warehouse) => {
    if (!moveStock.value.isActive || moveStock.value.quantity <= 0) {
        return warehouse.stock
    }
    
    // If this is the source warehouse, subtract the quantity
    if (moveStock.value.from && moveStock.value.from.id === warehouse.id) {
        return warehouse.stock - moveStock.value.quantity
    }
    
    // If this is the destination warehouse, add the quantity
    if (moveStock.value.to && moveStock.value.to.id === warehouse.id) {
        return warehouse.stock + moveStock.value.quantity
    }
    
    return warehouse.stock
}

const getStockChangeIndicator = (warehouse) => {
    if (!moveStock.value.isActive || moveStock.value.quantity <= 0) {
        return null
    }
    
    // If this is the source warehouse, show negative change
    if (moveStock.value.from && moveStock.value.from.id === warehouse.id) {
        return -moveStock.value.quantity
    }
    
    // If this is the destination warehouse, show positive change
    if (moveStock.value.to && moveStock.value.to.id === warehouse.id) {
        return moveStock.value.quantity
    }
    
    return null
}

const handleForkliftClick = (warehouse) => {
    // If no move stock is active, start new move stock selection
    if (!moveStock.value.isActive) {
        selectSourceWarehouse(warehouse)
        return
    }
    
    // If clicking on the same warehouse as source, do nothing
    if (moveStock.value.from && moveStock.value.from.id === warehouse.id) {
        return
    }
    
    // If source is already selected, this click is for destination
    if (moveStock.value.from && !moveStock.value.to) {
        selectDestinationWarehouse(warehouse)
        return
    }
    
    // If both source and destination are selected, start new selection
    if (moveStock.value.from && moveStock.value.to) {
        selectSourceWarehouse(warehouse)
        return
    }
}

const submitCheckStock = () => {
    // form.post(route('grp.dashboard.show'), {
    //     preserveScroll: true,
    //     onStart: () => {
    //         console.log("Submitting stock check...")
    //     },
    //     onSuccess: () => {
    //         console.log("Stock check submitted successfully!")
    //         emits('onClickBackground')
    //     },
    //     onError: (errors) => {
    //         console.error("Failed to submit stock check:", errors)
    //     },
    //     onFinish: () => {
    //         console.log("Stock check submission finished.")
    //     }
    // })

    console.log("Submitting stock check data:", form)
}
</script>

<template>
    <div>
        <div @click="() => emits('onClickBackground')" class="cursor-pointer fixed inset-0 bg-black/40 z-30" />
        <div class="relative bg-white z-40 py-2 px-3 space-y-1">
            <div class="text-center">Move stock</div>
            
            <!-- Move Stock Section -->
            <div v-if="moveStock.isActive" class="border border-gray-200 rounded p-3 bg-gray-50 relative">
                <button 
                    @click="closeMoveStock"
                    class="absolute top-2 right-2 text-gray-400 hover:text-gray-600"
                >
                    <FontAwesomeIcon icon="fas fa-times" class="text-xs" />
                </button>
                
                <div class="flex items-center gap-2 mb-3">
                    <span class="font-medium">{{ moveStock.from?.name || '?' }}</span>
                    <FontAwesomeIcon icon="fas fa-forklift" class="text-gray-600" />
                    <span class="font-medium">{{ moveStock.to?.name || '?' }}</span>
                </div>
                
                <div v-if="moveStock.to" class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Quantity:</label>
                    <div class="w-20">
                        <InputNumber
                            :modelValue="moveStock.quantity"
                            @input="e => updateMoveQuantity(e.value)"
                            :min="0"
                            :max="getMaxQuantity()"
                            :step="1"
                            size="small"
                            fluid
                            inputClass="!py-0"
                        />
                    </div>
                    <span class="text-xs text-gray-500">/ {{ getMaxQuantity() }}</span>
                </div>
                
                <div v-else class="text-sm text-gray-500">
                    Select destination warehouse by clicking forklift icon
                </div>
            </div>
            
            <div v-for="(forrrmm, idx) in form.stockCheck" class="grid grid-cols-7 gap-x-3 items-center gap-2">
                <div class="col-span-4 flex items-center gap-x-2">
                    {{ forrrmm.name }}
                </div>
                <div v-tooltip="trans('Last audit :date', { date: useFormatTime(new Date()) })" class="text-right">
                    0
                    <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                </div>
                <div class="col-span-2 text-right flex items-center justify-end gap-x-1">
                    <!-- Stock change indicator for move stock -->
                    <div v-if="getStockChangeIndicator(forrrmm) !== null">
                        <span v-if="getStockChangeIndicator(forrrmm) > 0" class="text-green-600">
                            +{{ getStockChangeIndicator(forrrmm) }}
                        </span>
                        <span v-else class="text-red-500">
                            {{ getStockChangeIndicator(forrrmm) }}
                        </span>
                    </div>
                    <!-- Original stock change indicator -->
                    <div v-else-if="forrrmm.stock != part_locations[idx].stock">
                        <span v-if="forrrmm.stock > part_locations[idx].stock" class="text-green-600">
                            +{{ forrrmm.stock - part_locations[idx].stock }}
                        </span>
                        <span v-else class="text-red-500">
                            -{{ part_locations[idx].stock - forrrmm.stock }}
                        </span>
                    </div>
                    <!-- <div v-else @click="() => forrrmm.isAudited = !forrrmm.isAudited" class="cursor-pointer" :class="forrrmm.isAudited ? 'text-green-500' : 'text-gray-400 hover:text-green-500'">
                        <FontAwesomeIcon
                            :icon="forrrmm.isAudited ? 'fas fa-dot-circle' : 'fal fa-dot-circle'"
                            fixed-width
                            aria-hidden="true"
                        />
                    </div> -->

                    <div class="w-20 relative flex items-center gap-2">
                        <InputNumber
                            :modelValue="getCalculatedStock(forrrmm)"
                            @input="e => forrrmm.stock = e.value"
                            :min="0"
                            :disabled="true"
                            :step="1"
                            size="small"
                            fluid
                            inputClass="!py-0 !pr-6"
                        />
                        <FontAwesomeIcon
                            icon="fas fa-forklift"
                            :class="[
                                'text-xs cursor-pointer',
                                !moveStock.isActive ? 'text-gray-400 hover:text-gray-600' : 
                                moveStock.from && moveStock.from.id === forrrmm.id ? 'text-gray-300 cursor-not-allowed' :
                                !moveStock.from ? 'text-gray-400 hover:text-gray-600' :
                                'text-blue-500 hover:text-blue-700'
                            ]"
                            fixed-width
                            aria-hidden="true"
                            @click="handleForkliftClick(forrrmm)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 z-40 mt-4">
            <Button
                label="Cancel"
                type="cancel"
                key="2"
                class="bg-red-100"
                @click="() => emits('onClickBackground')"
            />

            <Button
                v-if="layout.app.environment === 'local'"
                :disabled="!form.isDirty"
                label="Save"
                full
                @click="() => submitCheckStock()"
            />

        </div>
        <pre v-if="layout.app.environment === 'local'">{{ form }}</pre>
    </div>
</template>