<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLongArrowRight } from "@fal"
import { faInfoCircle, faForklift, faTimes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Textarea } from 'primevue'
import { ref, computed, onMounted } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import FractionDisplay from '@/Components/DataDisplay/FractionDisplay.vue'
import { router, useForm } from '@inertiajs/vue3'
import {formatDistanceStrict} from 'date-fns/formatDistanceStrict'
import { notify } from '@kyvg/vue3-notification'
import Multiselect from '@vueform/multiselect'
library.add(faLongArrowRight, faInfoCircle, faForklift, faTimes)

const props = defineProps<{
    part_locations: {
        id: number
        code: string
        slug?: string
        quantity: number
        isAudited?: boolean
        audited_at?: string | null
        packed_in?: number
    }[],
    replenishment_data: Record<number, {
        replenishment_stock?: number
    }>
    reasons?: {
        increase: [],
        decrease: [],
        transfer: [],
    }
}>()

const emits = defineEmits(['close'])

// Move stock state
const moveStock = ref<{
    from: any
    to: any
    quantity: number
    isActive: boolean
}>({
    from: null,
    to: null,
    quantity: 0,
    isActive: false
})

const form = useForm({
    stockCheck: props.part_locations.map(item => ({
        id: item.id,
        name: item.code,
        stock: Number(item.quantity ?? 0),
        isAudited: item.isAudited,
        audited_at: item.audited_at
    })),
    moveStock: null
})

const selectedReason = ref('')
const note = ref('')

const isSource = (location: any) => moveStock.value.from?.id === location.id
const isTarget = (location: any) => moveStock.value.to?.id === location.id

const canSave = computed(() => {
    return !!moveStock.value.from
        && !!moveStock.value.to
        // && !!selectedReason.value
        && Number(moveStock.value.quantity) > 0
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
        resetMoveQuantity()
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
        moveStock.value.to = moveStock.value.from
    }

    moveStock.value.from = location
    moveStock.value.isActive = true
    resetMoveQuantity()
    syncForm()
}

const selectTarget = (location: any) => {
    if (isTarget(location)) {
        moveStock.value.to = null
        resetMoveQuantity()
        syncForm()
        return
    }

    if (isSource(location)) {
        const swappedSource = moveStock.value.to

        if (!swappedSource) {
            return
        }

        if (!swappedSource.stock || swappedSource.stock <= 0) {
            notify({
                title: trans('Cannot swap locations'),
                text: trans(':location has no stock to move', { location: swappedSource.name }),
                type: 'warning',
            })
            return
        }

        moveStock.value.from = swappedSource
        moveStock.value.to = location
        moveStock.value.isActive = true
        resetMoveQuantity()
        syncForm()
        return
    }

    moveStock.value.to = location
    // moveStock.value.quantity = 0
    syncForm()
}

const closeMoveStock = () => {
    moveStock.value = {
        from: null,
        to: null,
        quantity: 0,
        isActive: false
    }
    wholeQuantity.value = 0
    fractionQuantity.value = 0
    inputKey.value++
    form.moveStock = null
}

const roundQuantity = (value: number) => Math.round(Number(value) * 1e6) / 1e6

// Bumping this key remounts NumberWithButtonSave so it picks up quantities set
// programmatically (move-all, replenishment, source/target reset).
const inputKey = ref(0)

// packed_in is uniform across all locations of the same org stock: a stock packed in 6 is
// stored in sixths, so 0.166… means 1/6 of a unit.
const packedIn = computed(() => {
    const value = Number(props.part_locations?.[0]?.packed_in ?? 1)
    return value > 1 ? value : 1
})

const isPackedStock = computed(() => packedIn.value > 1)

// Fraction data shape expected by FractionDisplay: [whole, [numerator, denominator]]
const toFractionData = (value: number): [number, [number, number]] => {
    const rounded = roundQuantity(Number(value ?? 0))

    if (!isPackedStock.value) {
        return [Math.round(rounded), [0, 1]]
    }

    const sign = rounded < 0 ? -1 : 1
    const units = Math.round(Math.abs(rounded) * packedIn.value)
    const whole = Math.trunc(units / packedIn.value)

    return [sign * whole, [sign * (units - whole * packedIn.value), packedIn.value]]
}

// A packed stock is moved with two inputs: whole units, plus the fraction of a unit on top of
// them. Both are bound as real quantities, so 3/8 is held as 0.375.
const wholeQuantity = ref(0)
const fractionQuantity = ref(0)

const getMaxQuantity = () => {
    return moveStock.value.from ? roundQuantity(Number(moveStock.value.from.stock)) : 0
}

const getMaxWholeQuantity = () => Math.floor(getMaxQuantity())

// A stock of 4 3/8 can only give away 3/8 as a fraction, never 4/8 or more.
const getMaxFractionQuantity = () => roundQuantity(getMaxQuantity() - getMaxWholeQuantity())

const hasFractionalStock = computed(() => isPackedStock.value && getMaxFractionQuantity() > 0)

const snapToFraction = (value: number) => {
    return roundQuantity(Math.round(Number(value || 0) * packedIn.value) / packedIn.value)
}

const storeQuantity = (value: number) => {
    moveStock.value.quantity = value

    if (form.moveStock) {
        form.moveStock.quantity = value
    }
}

const splitQuantityIntoInputs = (value: number) => {
    if (!isPackedStock.value) {
        wholeQuantity.value = Math.round(value)
        fractionQuantity.value = 0
        return
    }

    const units = Math.round(value * packedIn.value)
    const whole = Math.trunc(units / packedIn.value)

    wholeQuantity.value = whole
    fractionQuantity.value = roundQuantity(value - whole)
}

// Both inputs together can ask for more than the source holds, so clamp the total and
// mirror the clamped amount back into the inputs.
const updateQuantityFromInputs = () => {
    const requested = roundQuantity(wholeQuantity.value + fractionQuantity.value)
    const validValue = Math.min(Math.max(requested, 0), getMaxQuantity())

    storeQuantity(validValue)

    if (validValue !== requested) {
        splitQuantityIntoInputs(validValue)
        inputKey.value++
    }
}

const updateWholeQuantity = (value: number) => {
    wholeQuantity.value = Math.max(0, Math.floor(Number(value || 0)))
    updateQuantityFromInputs()
}

const updateFractionQuantity = (value: number) => {
    fractionQuantity.value = Math.min(Math.max(snapToFraction(value), 0), getMaxFractionQuantity())
    updateQuantityFromInputs()
}

const setMoveQuantity = (value: number) => {
    const validValue = Math.min(Math.max(roundQuantity(Number(value || 0)), 0), getMaxQuantity())

    storeQuantity(validValue)
    splitQuantityIntoInputs(validValue)
    inputKey.value++
}

const resetMoveQuantity = () => {
    setMoveQuantity(0)
}

const getCalculatedStock = (warehouse: { stock: number; id: any }) => {
    if (!moveStock.value.isActive || moveStock.value.quantity <= 0) {
        return warehouse.stock
    }
    
    // If this is the source warehouse, subtract the quantity
    if (moveStock.value.from?.id === warehouse.id) {
        const result = roundQuantity(warehouse.stock - moveStock.value.quantity)

        return result
    }

    if (moveStock.value.to?.id === warehouse.id) {
        const result = roundQuantity(warehouse.stock + moveStock.value.quantity)

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
    if (!canSave.value) return;

    router.patch(route('grp.models.location_org_stock.move', {
        locationOrgStock: moveStock.value.from.id,
        targetLocationOrgStock: moveStock.value.to.id
    }), {
        quantity: moveStock.value.quantity,
        // reason: selectedReason.value,
        // note: note.value
    }, {
        preserveScroll: true,
        onStart: () => {
            isLoadingSubmit.value = true;
        },
        onSuccess: () => {
            // notify({
            //     title: trans("Success"),
            //     text: trans('Moved :_qtyItem stocks from :_locationSource to :_locationDestination', {
            //         _qtyItem: moveStock.value.quantity.toString(),
            //         _locationSource: moveStock.value.from?.name ?? 'A',
            //         _locationDestination: moveStock.value.to?.name ?? 'B',
            //     }),
            //     type: "success",
            // })
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

    setMoveQuantity(Math.min(nextValue, maxQty))
}

onMounted(() => {
    const locations = form.stockCheck
    // selectedReason.value = Object.keys(props.reasons?.transfer ?? [])?.[0] ?? '';

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
<!-- <style scoped lang="scss">
    .child-text-sm {
        font-size: 0.75rem;

        > * {
            font-size: 0.75rem;
        }
    }
</style> -->
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
                <div
                    class="group text-center border rounded-lg px-4 py-2 transition"
                    :class="moveStock.from ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'"
                >
                    <div
                        class="font-bold text-xs uppercase tracking-wide flex items-center justify-center gap-x-1.5 transition w-32"
                        :class="moveStock.from ? 'text-green-600' : 'text-green-500'"
                    >
                        <FontAwesomeIcon icon="fas fa-forklift" fixed-width />
                        {{ trans('Source') }}
                    </div>
                    <div class="font-medium" :class="moveStock.from ? 'text-green-700' : 'text-gray-400 italic'">
                        {{ moveStock.from?.name || '—' }}
                    </div>
                    <div v-if="moveStock.from" class="mt-0.5 tabular-nums text-xs flex items-center justify-center gap-x-1">
                        <span v-tooltip="trans('Current stock in this location')" class="text-gray-500">
                            <FractionDisplay :fractionData="toFractionData(moveStock.from.stock)" />
                        </span>
                        <template v-if="moveStock.quantity > 0">
                            <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />
                            <span v-tooltip="trans('Stock preview after move')" class="font-semibold text-green-700">
                                <FractionDisplay :fractionData="toFractionData(getCalculatedStock(moveStock.from))" />
                            </span>
                        </template>
                    </div>
                </div>

                <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />

                <div class="text-center">
                    <div class="font-bold text-xs uppercase tracking-wide text-gray-500">{{ trans('Quantity') }}</div>
                    <div class="font-medium tabular-nums text-gray-700 flex justify-center">
                        <FractionDisplay v-if="moveStock.quantity" :fractionData="toFractionData(moveStock.quantity)" />
                        <template v-else>......</template>
                    </div>
                </div>

                <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />

                <div
                    class="group text-center border rounded-lg px-4 py-2 transition"
                    :class="moveStock.to ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white'"
                >
                    <div
                        class="font-bold text-xs uppercase tracking-wide flex items-center justify-center gap-x-1.5 transition"
                        :class="moveStock.to ? 'text-blue-600' : 'text-blue-500'"
                    >
                        <FontAwesomeIcon icon="fas fa-forklift" fixed-width />
                        {{ trans('Destination') }}
                    </div>
                    <div class="font-medium" :class="moveStock.to ? 'text-blue-700' : 'text-gray-400 italic'">
                        {{ moveStock.to?.name || '—' }}
                    </div>
                    <div v-if="moveStock.to" class="mt-0.5 tabular-nums text-xs flex items-center justify-center gap-x-1">
                        <span v-tooltip="trans('Current stock in this location')" class="text-gray-500">
                            <FractionDisplay :fractionData="toFractionData(moveStock.to.stock)" />
                        </span>
                        <template v-if="moveStock.quantity > 0">
                            <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />
                            <span v-tooltip="trans('Stock preview after move')" class="font-semibold text-blue-700">
                                <FractionDisplay :fractionData="toFractionData(getCalculatedStock(moveStock.to))" />
                            </span>
                        </template>
                    </div>
                </div>
            </div>
            <!-- <div class="text-yellow-600 text-xs text-center mt-2 h-[16px]">
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
                    {{ trans('Enter the quantity to move from the source') }}
                </span>
            </div> -->
        </div>

        <!-- Disabled due to Tomas request -->
        <!-- <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-2">
            <div class="min-w-0">
                <label class="block mb-1 text-xs font-bold uppercase tracking-wide text-gray-500">
                    {{ ctrans('Transfer reason') }}
                    <span class="text-red-500">*</span>
                </label>
                <Multiselect
                    v-model="selectedReason"
                    :options="reasons?.transfer ?? []"
                    :placeholder="ctrans('Select your reason')"
                    :canClear="false"
                    :mode="'single'"
                    :closeOnSelect="true"
                    :canDeselect="false"
                    :hideSelected="false"
                    :searchable="true"
                    :filter-results="false"
                    :classes="{ container: selectedReason ? 'multiselect' : 'multiselect !border-red-300' }"
                />
                <div v-if="!selectedReason" class="mt-1 text-xs h-4" :class="selectedReason ? 'invisible' : 'text-red-500'">
                    <FontAwesomeIcon :icon="faInfoCircle" fixed-width aria-hidden="true" />
                    {{ ctrans('Select a reason before saving') }}
                </div>
            </div>

            <div class="min-w-0">
                <label class="block mb-1 text-xs font-bold uppercase tracking-wide text-gray-500">
                    {{ ctrans('Note') }}
                    <span class="font-normal normal-case tracking-normal text-gray-400 italic">
                        {{ ctrans('optional') }}
                    </span>
                </label>
                <Textarea v-model.trim="note" rows="1" :autoResize="true"
                :placeholder="ctrans('Add more details about this move')" class="w-full rounded-xl" />
            </div>
        </div> -->

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
                    v-tooltip="isSource(form) ? trans('Unset as source') : ctrans('Set as source location')"
                    :class="[
                        'text-xl transition shrink-0',
                        isSource(form)
                            ? 'cursor-pointer text-green-600 scale-110' :
                        isTarget(form)
                            ? 'text-gray-400 opacity-70 cursor-pointer' :
                        form.stock <= 0
                            ? 'text-gray-400 opacity-90 cursor-not-allowed' :
                        moveStock.from
                            ? 'cursor-pointer text-gray-400 opacity-30 hover:opacity-80' :
                        'cursor-pointer opacity-50 hover:opacity-100 text-green-600'
                    ]"
                    fixed-width
                    aria-hidden="true"
                    @click="selectSource(form)"
                />

                <!-- Name + stock number -->
                <div class="flex-1 min-w-0 flex items-center gap-x-2 flex-wrap">
                    <span class="font-medium truncate">{{ form.name }}</span>

                    <!-- Preview: original + change -> result -->
                    <span
                        v-if="isSource(form) || getStockChangeIndicator(form) !== null"
                        class="tabular-nums text-xs flex items-center gap-x-1"
                    >
                        <span
                            v-tooltip="isSource(form) ? trans('Click to move all stock (empties this location)') : trans('Current stock in this location')"
                            :class="[
                                'border rounded px-1.5 py-0.5 border-gray-300 text-gray-600',
                                isSource(form) ? 'cursor-pointer hover:border-red-300 hover:text-red-600 hover:bg-red-50 transition' : ''
                            ]"
                            @click="isSource(form) && setMoveQuantity(getMaxQuantity())"
                        ><FractionDisplay :fractionData="toFractionData(form.stock)" /></span>
                        <span v-if="isSource(form)" class="text-red-500 font-semibold">−</span>
                        <div v-if="isSource(form)" class="shrink-0 flex items-center gap-x-1">
                            <NumberWithButtonSave
                                :key="`whole-${inputKey}`"
                                v-tooltip="ctrans('Whole units to move')"
                                :modelValue="wholeQuantity"
                                @update:modelValue="(val: number) => updateWholeQuantity(val)"
                                :min="0"
                                :max="getMaxWholeQuantity()"
                                noSaveButton
                                noUndoButton
                            />
                            <NumberWithButtonSave
                                v-if="hasFractionalStock"
                                :key="`fraction-${inputKey}`"
                                v-tooltip="ctrans('Fraction of a unit to move (:packedIn per unit)', { packedIn: packedIn })"
                                :modelValue="fractionQuantity"
                                @update:modelValue="(val: number) => updateFractionQuantity(val)"
                                :min="0"
                                :max="getMaxFractionQuantity()"
                                :denominator="packedIn"
                                noSaveButton
                                noUndoButton
                            />
                        </div>
                        <span v-else class="flex items-center" :class="getStockChangeIndicator(form) > 0 ? 'text-green-600' : 'text-red-500'">
                            {{ getStockChangeIndicator(form) > 0 ? '+' : '−' }}
                            <FractionDisplay :fractionData="toFractionData(Math.abs(getStockChangeIndicator(form)))" />
                        </span>
                        <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />
                        <span
                            v-tooltip="trans('Stock preview after move')"
                            class="font-semibold"
                            :class="isSource(form) ? 'text-green-700' : 'text-blue-700'"
                        >
                            <FractionDisplay :fractionData="toFractionData(getCalculatedStock(form))" />
                        </span>
                    </span>

                    <!-- Static stock (no pending change on this row) -->
                    <span
                        v-else
                        v-tooltip="trans('Stock in this location')"
                        class="tabular-nums text-xs border rounded px-1.5 py-0.5 border-gray-300 text-gray-600"
                    >
                        <FractionDisplay :fractionData="toFractionData(form.stock)" />
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
                        'cursor-pointer opacity-50 hover:opacity-100 text-blue-600'
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
                type="tertiary" icon="far fa-arrow-left"
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
