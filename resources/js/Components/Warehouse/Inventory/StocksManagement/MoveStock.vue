<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLongArrowRight } from "@fal"
import { faInfoCircle, faForklift, faTimes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber } from 'primevue'
import { ref, computed, onMounted } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router, useForm } from '@inertiajs/vue3'
import {formatDistanceStrict} from 'date-fns/formatDistanceStrict'
import { notify } from '@kyvg/vue3-notification'
library.add(faLongArrowRight, faInfoCircle, faForklift, faTimes)

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

const isSource = (location: any) => moveStock.value.from?.id === location.id
const isTarget = (location: any) => moveStock.value.to?.id === location.id

const canSave = computed(() => {
    return !!moveStock.value.from
        && !!moveStock.value.to
        && Number(moveStock.value.quantity) >= 1
        && Number(moveStock.value.quantity) <= Number(moveStock.value.from?.stock ?? 0)
})

const syncForm = () => {
    if (moveStock.value.from && moveStock.value.to) {
        form.moveStock = {
            from: moveStock.value.from.name,
            to: moveStock.value.to.name,
            quantity: moveStock.value.quantity
        }
    } else {
        form.moveStock = null
    }
}

const selectSource = (location: any) => {
    if (isSource(location)) {
        moveStock.value.from = null
        moveStock.value.isActive = false
        moveStock.value.quantity = 0
        syncForm()
        return
    }

    if (!location.stock || location.stock <= 0) {
        notify({
            title: trans('Cannot select source'),
            text: trans('This location has no stock to move'),
            type: 'warning',
        })
        return
    }

    if (isTarget(location)) {
        moveStock.value.to = null
    }

    moveStock.value.from = location
    moveStock.value.isActive = true
    moveStock.value.quantity = 0
    syncForm()
}

const selectTarget = (location: any) => {
    if (isTarget(location)) {
        moveStock.value.to = null
        moveStock.value.quantity = 0
        syncForm()
        return
    }

    if (isSource(location)) {
        return
    }

    moveStock.value.to = location
    moveStock.value.quantity = 0
    syncForm()
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
    const validValue = Number(value || 0)
    const maxQuantity = Number(getMaxQuantity())

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
                title: trans("Success"),
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
        }
    })
}

const applyReplenishment = (location: any) => {
    if (!isTarget(location)) {
        return
    }

    const replenishment = props.replenishment_data?.[location.id]?.replenishment_stock ?? 0
    const maxQty = Number(getMaxQuantity())
    const nextValue = Number(moveStock.value.quantity) + Number(replenishment)

    updateMoveQuantity(Math.min(nextValue, maxQty))
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

    syncForm()
})

</script>

<template>
    <div class="space-y-4">
        <!-- Section: Move summary + instructions -->
        <div class="border border-gray-200 rounded p-3 bg-gray-50 relative">
            <button
                v-if="moveStock.from || moveStock.to"
                @click="closeMoveStock"
                v-tooltip="trans('Reset selection')"
                class="absolute top-2 right-2 text-gray-400 hover:text-red-500 underline text-xs"
            >
                <!-- <FontAwesomeIcon icon="fas fa-times" class="text-xs" /> -->
                {{ ctrans("clear") }}
            </button>

            <div class="flex items-center justify-center gap-3 flex-wrap text-sm sm:text-base">
                <div class="text-center">
                    <div class="font-bold text-xs uppercase tracking-wide text-gray-500">{{ trans('Source') }}</div>
                    <div class="font-medium" :class="moveStock.from ? 'text-green-600' : 'text-gray-400 italic'">
                        {{ moveStock.from?.name || '—' }}
                    </div>
                </div>

                <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />

                <div class="text-center">
                    <div class="font-bold text-xs uppercase tracking-wide text-gray-500">{{ trans('Quantity') }}</div>
                    <div class="font-medium tabular-nums text-gray-700">
                        {{ moveStock.quantity || '—' }} <span class="text-gray-400">/ {{ getMaxQuantity() }}</span>
                    </div>
                </div>

                <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />

                <div class="text-center">
                    <div class="font-bold text-xs uppercase tracking-wide text-gray-500">{{ trans('Destination') }}</div>
                    <div class="font-medium" :class="moveStock.to ? 'text-blue-600' : 'text-gray-400 italic'">
                        {{ moveStock.to?.name || '—' }}
                    </div>
                </div>
            </div>

            <div class="text-yellow-600 text-xs text-center mt-2 h-[16px]">
                <span v-if="!moveStock.from">
                    <FontAwesomeIcon :icon="faInfoCircle" />
                    {{ trans('Select the source location by clicking the forklift icon on the left') }}
                </span>
                <span v-else-if="!moveStock.to">
                    <FontAwesomeIcon :icon="faInfoCircle" />
                    {{ trans('Select the destination location by clicking the forklift icon on the right') }}
                </span>
                <span v-else-if="!moveStock.quantity">
                    <FontAwesomeIcon :icon="faInfoCircle" />
                    {{ trans('Enter the quantity to move into the destination') }}
                </span>
            </div>
        </div>

        <template v-if="form.stockCheck.length > 0">
            <div v-for="(form, idx) in form.stockCheck" :key="form.id"
                :class="[
                    'flex items-center gap-x-3 ps-2 pe-2 py-2 rounded transition',
                    isSource(form) ? 'bg-green-50 border border-green-100' :
                    isTarget(form) ? 'bg-blue-50 border border-blue-100' :
                    'border border-[rgba(255,255,255,0)] hover:bg-gray-50'
                ]">

                <!-- Left: Source forklift -->
                <FontAwesomeIcon
                    icon="fas fa-forklift"
                    v-tooltip="isSource(form) ? trans('Unset as source') : trans('Set as source location')"
                    :class="[
                        'text-xl transition shrink-0',
                        isSource(form)
                            ? 'cursor-pointer text-green-600 scale-110' :
                        isTarget(form)
                            ? 'text-gray-300 opacity-20 cursor-not-allowed' :
                        form.stock <= 0
                            ? 'text-gray-300 opacity-40 cursor-not-allowed' :
                        moveStock.from
                            ? 'cursor-pointer text-gray-400 opacity-30 hover:opacity-80' :
                        'cursor-pointer text-gray-400 hover:text-green-600'
                    ]"
                    fixed-width
                    aria-hidden="true"
                    @click="selectSource(form)"
                />

                <!-- Name + stock number -->
                <div class="flex-1 min-w-0 flex items-center gap-x-2 flex-wrap">
                    <span class="font-medium truncate">{{ form.name }}</span>

                    <!-- Preview: original + change --> result -->
                    <span
                        v-if="getStockChangeIndicator(form) !== null"
                        v-tooltip="trans('Stock preview after move')"
                        class="tabular-nums text-xs flex items-center gap-x-1"
                    >
                        <span class="text-gray-500">{{ form.stock }}</span>
                        <span :class="getStockChangeIndicator(form) > 0 ? 'text-green-600' : 'text-red-500'">
                            {{ getStockChangeIndicator(form) > 0 ? '+' : '−' }}{{ Math.abs(getStockChangeIndicator(form)) }}
                        </span>
                        <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />
                        <span
                            class="border rounded px-1.5 py-0.5 font-semibold"
                            :class="isSource(form) ? 'border-green-300 text-green-700 bg-green-50' : 'border-blue-300 text-blue-700 bg-blue-50'"
                        >
                            {{ getCalculatedStock(form) }}
                        </span>
                    </span>

                    <!-- Static stock (no pending change on this row) -->
                    <span
                        v-else
                        v-tooltip="trans('Stock in this location')"
                        class="tabular-nums text-xs border rounded px-1.5 py-0.5 border-gray-300 text-gray-600"
                    >
                        {{ form.stock }}
                    </span>
                </div>

                <!-- Audit info -->
                <div v-if="form.audited_at" v-tooltip="trans('Last audit :date', { date: useFormatTime(form.audited_at) })" class="text-right text-sm whitespace-nowrap hidden sm:block">
                    {{ formatDistanceStrict(new Date(form.audited_at), new Date()) }}
                    <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                </div>
                <div v-else class="text-right text-sm italic opacity-60 whitespace-nowrap hidden sm:block">
                    {{ trans("Never audited") }}
                </div>

                <!-- Replenishment suggestion -->
                <span
                    v-tooltip="trans('Apply suggested replenishment')"
                    class="text-sm text-blue-500 cursor-pointer hover:underline whitespace-nowrap"
                    :class="isTarget(form) ? '' : 'opacity-40 cursor-not-allowed'"
                    @click="isTarget(form) && applyReplenishment(form)"
                >
                    ({{ replenishment_data[form.id]?.replenishment_stock ?? '0' }})
                </span>

                <!-- Target: quantity input (only on target row) -->
                <div class="w-24 shrink-0">
                    <InputNumber
                        v-if="isTarget(form)"
                        :modelValue="moveStock.quantity"
                        @input="(event: { value: any }) => updateMoveQuantity(event.value)"
                        :min="0"
                        :max="getMaxQuantity()"
                        :step="1"
                        size="small"
                        fluid
                        inputClass="!py-0 !text-blue-600"
                    />
                </div>

                <!-- Right: Target forklift -->
                <FontAwesomeIcon
                    icon="fas fa-forklift"
                    v-tooltip="isTarget(form) ? trans('Unset as destination') : trans('Set as destination location')"
                    :class="[
                        'text-xl transition shrink-0',
                        isTarget(form)
                            ? 'cursor-pointer text-blue-600 scale-110' :
                        isSource(form)
                            ? 'text-gray-300 opacity-20 cursor-not-allowed' :
                        moveStock.to
                            ? 'cursor-pointer text-gray-400 opacity-30 hover:opacity-80' :
                        'cursor-pointer text-gray-400 hover:text-blue-600'
                    ]"
                    fixed-width
                    aria-hidden="true"
                    @click="selectTarget(form)"
                />
            </div>
        </template>
        <div
            v-else
            class="flex flex-col items-center justify-center text-center py-10 border border-dashed border-gray-300 rounded-lg"
        >
            <div class="text-gray-600 font-medium">
                {{ ctrans("No locations available") }}
            </div>

            <div class="text-sm text-gray-400 mt-1">
                {{ ctrans("You haven't added any locations yet") }}
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
                :loading="isLoadingSubmit"
                :disabled="!canSave"
                label="Save"
                full
                @click="() => submitCheckStock()"
            />

        </div>
    </div>
</template>
