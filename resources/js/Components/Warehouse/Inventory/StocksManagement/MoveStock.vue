<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowRight, faDotCircle, faQuestionSquare } from "@fal"
import { faInfoCircle, faDotCircle as fasDotCircle } from "@fas"
import { faForklift } from "@fas"
import { faTimes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber } from 'primevue'
import { inject, ref, nextTick, onMounted } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router, useForm } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import formatDistanceStrict from 'date-fns/formatDistanceStrict'
import { notify } from '@kyvg/vue3-notification'
library.add(faDotCircle, fasDotCircle, faForklift, faTimes)

const props = defineProps<{
    part_locations: {
        id: number
        code: string
        slug: string
        stock: number
        isAudited: boolean
    }[],
    replenishment_data: Record<number, {
        replenishment_stock?: number
    }>
}>()

const emits = defineEmits(['close'])

const layout = inject('layout', layoutStructure)

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
        name: item.code,
        stock: Number(item.quantity ?? 0) ,
        isAudited: item.isAudited,
        audited_at: item.audited_at
    })),
    moveStock: null
})

const selectSourceWarehouse = (warehouse: any) => {
    if (!warehouse.stock || warehouse.stock <= 0) {
        console.warn('❌ Cannot select source with 0 stock', warehouse)
        return
    }

    
    moveStock.value.from = warehouse
    moveStock.value.to = null
    moveStock.value.quantity = 0
    moveStock.value.isActive = true
}

const selectDestinationWarehouse = (warehouse: any) => {
    

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

const updateMoveQuantity = (value: number) => {
    
    // Ensure value is valid and within bounds
    const validValue = Number(value || 0)
    const maxQuantity = getMaxQuantity()
    
    // Reset to 0 if value is invalid or exceeds maximum
    if (validValue < 0 || validValue > maxQuantity) {
         
        moveStock.value.quantity = 0
    } else {
        moveStock.value.quantity = validValue
    }
    
    console.warn('Invalid quantity:', validValue)

    if (form.moveStock) {
        form.moveStock.quantity = moveStock.value.quantity
    }
}

const getMaxQuantity = () => {
    return moveStock.value.from ? moveStock.value.from.stock : '?'
}

const getCalculatedStock = (warehouse: { stock: number; id: any }) => {
    if (!moveStock.value.isActive || moveStock.value.quantity <= 0) {
        return warehouse.stock
    }
    
    // If this is the source warehouse, subtract the quantity
    if (moveStock.value.from?.id === warehouse.id) {
        const result = warehouse.stock - moveStock.value.quantity
        
        return result
    }

    if (moveStock.value.to?.id === warehouse.id) {
        const result = warehouse.stock + moveStock.value.quantity
        
        return result
    }
    
    return warehouse.stock
}

const getStockChangeIndicator = (warehouse: { id: any }) => {
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

const handleForkliftClick = (warehouse: { id: any }) => {
    
    // Cancel selection from
    if (moveStock.value.from?.id == warehouse.id) {
        resetForm()
        return;
    }

    // Cancel selection to
    if (moveStock.value.to?.id == warehouse.id) {
        resetForm('to')
        return;
    }

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

const isLoadingSubmit = ref(false);

const submitCheckStock = () => {
    if (!moveStock.value.from?.id || !moveStock.value.to?.id) return;

    router.patch(route('grp.models.location_org_stock.move', {
        locationOrgStock: moveStock.value.from.id,
        targetLocationOrgStock: moveStock.value.to.id
    }), {
        quantity: moveStock.value.quantity
    }, {
        preserveScroll: true,
        onStart: () => {
            isLoadingSubmit.value = true;
        },
        onSuccess: () => {            
            notify({
                title: trans("Something went wrong"),
                text: trans('Moved :_qtyItem stocks from :_locationSource to :_locationDestination', {
                    _qtyItem: moveStock.value.quantity.toString(),
                    _locationSource: moveStock.value.from?.name ?? 'A',
                    _locationDestination: moveStock.value.to?.name ?? 'B',
                }),
                type: "success",
            })
            emits('close');
        },
        onError: (errors) => {
            notify({
                title: trans("Something went wrong"),
                text: trans('Unable to move stock. An error occured.'),
                type: "error",
            })
        },
        onFinish: () => {
            isLoadingSubmit.value = false;
            console.log("Stock check submission finished.")
        }
    })

    console.log("Submitting stock check data:", form)
}

const resetForm = (scope: string = 'all') => {
    if (scope == 'all') {
        moveStock.value.from = null; 
        moveStock.value.isActive = false; 
        moveStock.value.to = null;
    } else if (scope == 'to') {
        moveStock.value.to = null;
    }
    moveStock.value.quantity = 0;
}

const applyReplenishment = (form) => {
    const replenishment = props.replenishment_data?.[form.id]?.replenishment_stock ?? 0
    const maxQty = getMaxQuantity()

    const nextValue = moveStock.value.quantity + replenishment

    moveStock.value.quantity = Math.min(nextValue, maxQty)
}

onMounted(() => {
    const locations = form.stockCheck

    if (locations.length === 2) {
        const [loc1, loc2] = locations

        // tentukan source = yang punya stock > 0
        if (loc1.stock > 0) {
            moveStock.value.from = loc1
            moveStock.value.to = loc2
        } else if (loc2.stock > 0) {
            moveStock.value.from = loc2
            moveStock.value.to = loc1
        }

        // aktifkan mode
        if (moveStock.value.from && moveStock.value.to) {
            moveStock.value.isActive = true
        }
    }
})

</script>

<template>
    <div class="space-y-4">
        <!-- Move Stock Section -->
        <div  class="border border-gray-200 rounded p-3 bg-gray-50 relative">
            <div class="text-sm">
                <button 
                    @click="closeMoveStock"
                    class="absolute top-2 right-2 text-gray-400 hover:text-gray-600"
                >
                    <FontAwesomeIcon icon="fas fa-times" class="text-xs" />
                </button>
            </div>
            <div class="flex items-center lg:text-xl text-lg justify-self-center grid grid-cols-8 w-full pt-4 pb-5">
                <div class="grid col-span-3" :class="[
                    moveStock.from?.id !== form.id ? 'bg-red-50 border border-red-100 p-2' :
                    'border border-[rgba(255,255,255,0)] hover:bg-gray-50'
                ]">
                    <span class="font-bold h-[30px]">
                        {{ trans('Source Location') }}
                    </span>
                    <span class="font-medium underline h-[30px] transition-all" :class="moveStock.from ? 'cursor-pointer hover:opacity-80' : ''" @click="resetForm()">
                        {{ moveStock.from?.name || '?' }}
                    </span>
                    <div class="text-xs text-yellow-600 h-[16px]">
                        <span v-if="!moveStock.isActive">
                            <FontAwesomeIcon :icon="faInfoCircle" /> 
                            {{ trans('Select') }}
                            <span class="underline">
                                {{ trans('Source location') }}
                            </span>
                            {{ trans('by clicking forklift icon!') }}
                        </span>
                    </div>
                </div>
                <div class="grid px-2 py-auto col-span-2">
                    <span class="justify-self-center h-[30px]">
                        <FontAwesomeIcon icon="fas fa-forklift" class="text-gray-600 mr-2 text-lg" />
                        <FontAwesomeIcon :icon="faArrowRight" class="text-gray-600 text-sm" />
                    </span>
                    <div class="flex items-center gap-2 justify-self-center h-[30px] w-fit">
                        <label class="text-lg text-gray-600">Quantity:</label>
                        <div class="w-full max-w-20">
                            <InputNumber
                                    v-if="moveStock.to" 
                                :modelValue="moveStock.quantity"
                                @input="(event: { value: any }) => updateMoveQuantity(event.value)"
                                :min="0"
                                :max="getMaxQuantity()"
                                :step="1"                                
                                fluid
                                inputClass="!py-0"
                            />
                            <div v-else class="text-lg text-gray-500 text-nowrap">
                                ?
                            </div>
                        </div>
                        <span class="text-lg text-gray-500 text-nowrap" >
                            / {{ getMaxQuantity() }}
                        </span>
                    </div>
                    <div class="text-yellow-600 text-xs text-center h-[16px]">
                        <span v-if="moveStock.isActive && moveStock.from && moveStock.to && !moveStock.quantity">
                            <FontAwesomeIcon :icon="faInfoCircle" /> {{ trans('Enter quantity to move') }}
                        </span>
                    </div>
                </div>
                <div class="grid col-span-3 text-end" :class="[
                    moveStock.to?.id !== form.id ? 'bg-green-50 border border-green-100 p-2' :
                    'border border-[rgba(255,255,255,0)] hover:bg-gray-50'
                ]">
                    <div class="font-bold h-[30px]">
                        {{ trans('Destination Location') }}
                    </div>
                    <div class="font-medium underline h-[30px] transition-all" :class="moveStock.to ? 'cursor-pointer hover:opacity-80' : ''" @click="resetForm('to')">
                        {{ moveStock.to?.name || '?' }}
                    </div>
                    <div class="text-xs text-yellow-600 h-[16px]">
                        <span v-if="moveStock.from && !moveStock.to">
                            <FontAwesomeIcon :icon="faInfoCircle" />
                            {{ trans('Select') }}
                            <span class="underline">
                                {{ trans('Destination Location') }}
                            </span>
                            {{ trans('by clicking forklift icon!') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-sm text-yellow-600 mb-2 text-end">
        </div>
        <template v-if="form.stockCheck.length > 0">
            <div v-for="(form, idx) in form.stockCheck" :key="form.id"
                :class="[
                    'grid grid-cols-7 gap-x-3 items-center gap-2 ps-2 pe-2 py-2 rounded transition',
                    moveStock.from?.id === form.id ? 'bg-red-50 border border-red-100' :
                    moveStock.to?.id === form.id ? 'bg-green-50 border border-green-100' :
                    'border border-[rgba(255,255,255,0)] hover:bg-gray-50'
                ]">
                <div class="col-span-3 flex items-center gap-x-2">
                    {{ form.name }}
                </div>
                <div v-if="form.audited_at" v-tooltip="trans('Last audit :date', { date: useFormatTime(form.audited_at) })" class="text-right col-span-1 flex grid">
                    <span class="justify-self-end">
                        {{ formatDistanceStrict(new Date(form.audited_at), new Date()) }}
                        <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                    </span>
                </div>
                <div v-else
                    class="text-right col-span-1 flex grid text-sm italic opacity-60 whitespace-nowrap">
                    {{ trans("Never audited") }}
                </div>
                <span 
                    class="text-md text-blue-500 justify-self-end cursor-pointer hover:underline col-span-1"
                    @click="applyReplenishment(form)"
                >
                    ({{ replenishment_data[form.id]?.replenishment_stock ?? '0' }})
                </span>
                <div class="col-span-2 text-right flex items-center justify-end gap-x-1">
                    <!-- Stock change indicator for move stock -->
                    <div v-if="getStockChangeIndicator(form) !== null" class="mr-2">
                        <span v-if="getStockChangeIndicator(form) > 0" class="text-green-600">
                            +{{ getStockChangeIndicator(form) }}
                        </span>
                        <span v-else class="text-red-500">
                            {{ getStockChangeIndicator(form) }}
                        </span>
                    </div>
                    <!-- Original stock change indicator -->
                    <div v-else-if="form.stock != part_locations[idx].quantity" class="mr-2">
                        {{ part_locations[idx].quantity }}
                        <span v-if="form.stock > part_locations[idx].quantity" class="text-green-600">
                            +{{ form.stock - part_locations[idx].quantity }}
                        </span>
                        <span v-else class="text-red-500">
                            -{{ part_locations[idx].quantity - form.stock }}
                        </span>
                    </div>
                    <div class="relative flex items-center gap-3" style="width: 7rem">
                        <InputNumber
                            :modelValue="getCalculatedStock(form)"
                            @input="(event: { value: any }) => form.stock = event.value"
                            :min="0"
                            disabled
                            :step="1"
                            size="small"
                            fluid
                            inputClass="!py-0 !pr-6"
                            :inputClass="[
                                moveStock.from?.id === form.id ? '!text-red-500' :
                                moveStock.to?.id === form.id ? '!text-green-600' :
                                ''
                            ]"
                        />
                        <FontAwesomeIcon
                            icon="fas fa-forklift"
                            :class="[
                                'text-xl transition',
                                
                                // SOURCE
                                moveStock.from?.id === form.id 
                                    ? 'cursor-pointer text-red-500 scale-110' :

                                // DESTINATION
                                moveStock.to?.id === form.id 
                                    ? 'cursor-pointer text-green-600 scale-110' :

                                // NORMAL
                                !moveStock.isActive 
                                    ? 'cursor-pointer text-gray-400 hover:text-gray-600' :

                                // ACTIVE MODE
                                moveStock.from && !moveStock.to
                                    ? 'cursor-pointer text-blue-500 hover:text-blue-700' :

                                'text-gray-400 cursor-not-allowed'
                            ]"
                            fixed-width
                            aria-hidden="true"
                            @click="handleForkliftClick(form)"
                        />
                    </div>
                </div>
            </div>
        </template>
        <div
            v-else
            class="flex flex-col items-center justify-center text-center py-10 border border-dashed border-gray-300 rounded-lg"
        >
            <div class="text-gray-600 font-medium">
                {{ trans("No locations available") }}
            </div>

            <div class="text-sm text-gray-400 mt-1">
                {{ trans("You haven't added any locations yet") }}
            </div>
        </div>
        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 z-40 mt-4">
            <Button
                label="Cancel"
                type="cancel"
                @click="() => emits('close')"
            />

            <Button
                v-if="layout.app.environment === 'local'"
                :loading="isLoadingSubmit"
                :disabled="!form.isDirty"
                label="Save"
                full
                @click="() => submitCheckStock()"
            />

        </div>
        <!-- <pre v-if="layout.app.environment === 'local'">{{ form }}</pre> -->
    </div>
</template>