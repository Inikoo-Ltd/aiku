<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLongArrowRight } from "@fal"
import { faInfoCircle, faForklift, faTimes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Textarea } from 'primevue'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
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

type MoveTarget = {
    location: any
    quantity: number
    wholeQuantity: number
    fractionQuantity: number
}

// Move stock state
const moveStock = ref<{
    from: any
    targets: MoveTarget[]
    isActive: boolean
}>({
    from: null,
    targets: [],
    isActive: false
})

const makeTargetEntry = (location: any): MoveTarget => ({
    location,
    quantity: 0,
    wholeQuantity: 0,
    fractionQuantity: 0
})

const form = useForm({
    stockCheck: props.part_locations.map(item => ({
        id: item.id,
        name: item.code,
        stock: Number(item.quantity ?? 0),
        isAudited: item.isAudited,
        audited_at: item.audited_at
    })),
    moveStock: null as null | { from: string, targets: { to: string, quantity: number }[] }
})

const selectedReason = ref('')
const note = ref('')

const isSource = (location: any) => moveStock.value.from?.id === location.id
const getTargetEntry = (location: any) => moveStock.value.targets.find((entry) => entry.location.id === location.id)
const isTarget = (location: any) => !!getTargetEntry(location)

const stickyRowHeights = ref<Record<number, number>>({})
let stickyRowResizeObserver: ResizeObserver | null = null

const getStickyRowResizeObserver = () => {
    if (!stickyRowResizeObserver && typeof ResizeObserver !== 'undefined') {
        stickyRowResizeObserver = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const locationId = Number((entry.target as HTMLElement).dataset.locationId)
                if (!Number.isNaN(locationId)) {
                    stickyRowHeights.value[locationId] = entry.target.getBoundingClientRect().height
                }
            }
        })
    }

    return stickyRowResizeObserver
}

const setStickyRowRef = (location: any, el: unknown) => {
    if (!(el instanceof HTMLElement)) {
        return
    }

    const observer = getStickyRowResizeObserver()
    if (!observer) {
        return
    }

    if (isSource(location) || isTarget(location)) {
        observer.observe(el)
    } else {
        observer.unobserve(el)
    }
}

onBeforeUnmount(() => {
    stickyRowResizeObserver?.disconnect()
})

const stickyRowStyle = (location: any) => {
    if (!isSource(location) && !isTarget(location)) {
        return undefined
    }

    const stickyLocationIds = form.stockCheck
        .filter((row) => isSource(row) || isTarget(row))
        .map((row) => row.id)

    const position = stickyLocationIds.indexOf(location.id)
    if (position <= 0) {
        return { top: '0px' }
    }

    const offset = stickyLocationIds
        .slice(0, position)
        .reduce((sum, id) => sum + (stickyRowHeights.value[id] ?? 0), 0)

    return { top: `${offset}px` }
}

const canSave = computed(() => {
    return !!moveStock.value.from
        && moveStock.value.targets.length > 0
        // && !!selectedReason.value
        && moveStock.value.targets.every((entry) => Number(entry.quantity) > 0)
        && totalMoveQuantity.value <= Number(moveStock.value.from?.stock ?? 0)
})

const syncForm = () => {
    if (moveStock.value.from && moveStock.value.targets.length > 0) {
        form.moveStock = {
            from: moveStock.value.from.name,
            targets: moveStock.value.targets.map((entry) => ({
                to: entry.location.name,
                quantity: entry.quantity
            }))
        }
    } else {
        form.moveStock = null
    }
}

const selectSource = (location: any) => {
    if (isSource(location)) {
        moveStock.value.from = null
        moveStock.value.isActive = false
        resetAllTargetQuantities()
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

    const previousSource = moveStock.value.from

    if (isTarget(location)) {
        moveStock.value.targets = moveStock.value.targets.filter((entry) => entry.location.id !== location.id)

        if (previousSource) {
            moveStock.value.targets.push(makeTargetEntry(previousSource))
        }
    }

    moveStock.value.from = location
    moveStock.value.isActive = true
    resetAllTargetQuantities()
    syncForm()
}

const selectTarget = (location: any) => {
    if (isTarget(location)) {
        moveStock.value.targets = moveStock.value.targets.filter((entry) => entry.location.id !== location.id)
        syncForm()
        return
    }

    if (isSource(location)) {
        if (moveStock.value.targets.length !== 1) {
            return
        }

        const swappedSource = moveStock.value.targets[0].location

        if (!swappedSource.stock || swappedSource.stock <= 0) {
            notify({
                title: trans('Cannot swap locations'),
                text: trans(':location has no stock to move', { location: swappedSource.name }),
                type: 'warning',
            })
            return
        }

        moveStock.value.from = swappedSource
        moveStock.value.targets = [makeTargetEntry(location)]
        moveStock.value.isActive = true
        resetAllTargetQuantities()
        syncForm()
        return
    }

    moveStock.value.targets.push(makeTargetEntry(location))
    syncForm()
}

const closeMoveStock = () => {
    moveStock.value = {
        from: null,
        targets: [],
        isActive: false
    }
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

const snapToFraction = (value: number) => {
    return roundQuantity(Math.round(Number(value || 0) * packedIn.value) / packedIn.value)
}

// Stored quantities are rounded to six decimals, so 5/6 arrives as 4.999998 units: a plain floor
// would eat a whole fraction, hence anything within a hair of a unit counts as that unit.
const snapDownToFraction = (value: number) => {
    const units = Number(value || 0) * packedIn.value
    const closestUnits = Math.round(units)
    const flooredUnits = Math.abs(units - closestUnits) < 1e-4 ? closestUnits : Math.floor(units)

    return roundQuantity(flooredUnits / packedIn.value)
}

const maxQuantity = computed(() => {
    return moveStock.value.from ? roundQuantity(Number(moveStock.value.from.stock ?? 0)) : 0
})

const totalMoveQuantity = computed(() => {
    return roundQuantity(moveStock.value.targets.reduce((sum, entry) => sum + Number(entry.quantity || 0), 0))
})

// Each target can take at most what the other targets leave from the source stock.
const availableForTarget = (entry: MoveTarget | undefined) => {
    if (!entry) {
        return 0
    }

    const otherTargetsTotal = moveStock.value.targets.reduce((sum, other) => {
        return other === entry ? sum : sum + Number(other.quantity || 0)
    }, 0)

    return Math.max(0, roundQuantity(maxQuantity.value - otherTargetsTotal))
}

// Whole units already asked for leave less room for the fraction, and the other way around.
const maxWholeQuantityFor = (entry: MoveTarget | undefined) => {
    if (!entry) {
        return 0
    }

    return Math.max(0, Math.floor(roundQuantity(availableForTarget(entry) - entry.fractionQuantity)))
}

// A packed stock can always be broken open, so a location holding 4 whole units can still give
// away 3/8: the cap is what is left after the whole units, never a full unit.
const maxFractionQuantityFor = (entry: MoveTarget | undefined) => {
    if (!entry) {
        return 0
    }

    const largestFraction = (packedIn.value - 1) / packedIn.value
    const availableForFraction = availableForTarget(entry) - entry.wholeQuantity

    return Math.max(0, snapDownToFraction(Math.min(largestFraction, availableForFraction)))
}

const splitQuantityIntoInputs = (entry: MoveTarget, value: number) => {
    if (!isPackedStock.value) {
        entry.wholeQuantity = Math.round(value)
        entry.fractionQuantity = 0
        return
    }

    const units = Math.round(value * packedIn.value)
    const whole = Math.trunc(units / packedIn.value)

    entry.wholeQuantity = whole
    entry.fractionQuantity = roundQuantity(value - whole)
}

// The edited input is clamped to what the other one leaves available, and remounted when it
// asked for more than that so the box never shows a quantity we did not keep.
const updateTargetWholeQuantity = (entry: MoveTarget | undefined, value: number) => {
    if (!entry) {
        return
    }

    const requested = Math.max(0, Math.floor(Number(value || 0)))
    const validValue = Math.min(requested, maxWholeQuantityFor(entry))

    entry.wholeQuantity = validValue
    entry.quantity = roundQuantity(validValue + entry.fractionQuantity)
    syncForm()

    if (validValue !== requested) {
        inputKey.value++
    }
}

const updateTargetFractionQuantity = (entry: MoveTarget | undefined, value: number) => {
    if (!entry) {
        return
    }

    const requested = Math.max(0, snapToFraction(value))
    const validValue = Math.min(requested, maxFractionQuantityFor(entry))

    entry.fractionQuantity = validValue
    entry.quantity = roundQuantity(entry.wholeQuantity + validValue)
    syncForm()

    if (validValue !== requested) {
        inputKey.value++
    }
}

const setTargetQuantity = (entry: MoveTarget | undefined, value: number) => {
    if (!entry) {
        return
    }

    const validValue = Math.min(Math.max(roundQuantity(Number(value || 0)), 0), availableForTarget(entry))

    entry.quantity = validValue
    splitQuantityIntoInputs(entry, validValue)
    inputKey.value++
    syncForm()
}

const resetAllTargetQuantities = () => {
    for (const entry of moveStock.value.targets) {
        entry.quantity = 0
        entry.wholeQuantity = 0
        entry.fractionQuantity = 0
    }
    inputKey.value++
}

// Clicking the source chip with a single target keeps the old move-all shortcut; clicking a
// target chip fills that target with everything the other targets left available.
const onStockChipClick = (location: any) => {
    if (isSource(location) && moveStock.value.targets.length === 1) {
        setTargetQuantity(moveStock.value.targets[0], maxQuantity.value)
        return
    }

    const entry = getTargetEntry(location)
    if (entry) {
        setTargetQuantity(entry, availableForTarget(entry))
    }
}

const getCalculatedStock = (warehouse: { stock: number; id: any }) => {
    if (!moveStock.value.isActive || totalMoveQuantity.value <= 0) {
        return warehouse.stock
    }

    if (moveStock.value.from?.id === warehouse.id) {
        return roundQuantity(warehouse.stock - totalMoveQuantity.value)
    }

    const entry = getTargetEntry(warehouse)
    if (entry) {
        return roundQuantity(warehouse.stock + Number(entry.quantity || 0))
    }

    return warehouse.stock
}

const isLoadingSubmit = ref(false);

const submitCheckStock = () => {
    if (!canSave.value) return;

    router.patch(route('grp.models.location_org_stock.multi_move', {
        locationOrgStock: moveStock.value.from.id
    }), {
        targets: moveStock.value.targets.map((entry) => ({
            location_org_stock_id: entry.location.id,
            quantity: entry.quantity
        })),
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
                text: Object.values(errors ?? {})[0] ?? trans('Unable to move stock. An error occured.'),
                type: "error",
            })
        },
        onFinish: () => {
            isLoadingSubmit.value = false;
        }
    })
}

const applyReplenishment = (location: any) => {
    const entry = getTargetEntry(location)

    if (!entry) {
        return
    }

    const replenishment = props.replenishment_data?.[location.id]?.replenishment_stock ?? 0

    setTargetQuantity(entry, Number(entry.quantity) + Number(replenishment))
}

onMounted(() => {
    const locations = form.stockCheck
    // selectedReason.value = Object.keys(props.reasons?.transfer ?? [])?.[0] ?? '';

    if (locations.length === 2) {
        const [loc1, loc2] = locations

        // tentukan source = yang punya stock > 0
        if (loc1.stock > 0) {
            moveStock.value.from = loc1
            moveStock.value.targets = [makeTargetEntry(loc2)]
        } else if (loc2.stock > 0) {
            moveStock.value.from = loc2
            moveStock.value.targets = [makeTargetEntry(loc1)]
        }

        // aktifkan mode
        if (moveStock.value.from && moveStock.value.targets.length > 0) {
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
    <div class="flex flex-col min-h-0 max-h-[70vh]">
        <!-- Section: Move summary + instructions -->
        <div class="shrink-0 border border-gray-200 rounded p-3 bg-gray-50 relative">
            <button
                v-if="moveStock.from || moveStock.targets.length > 0"
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
                        <template v-if="totalMoveQuantity > 0">
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
                        <FractionDisplay v-if="totalMoveQuantity" :fractionData="toFractionData(totalMoveQuantity)" />
                        <template v-else>......</template>
                    </div>
                </div>

                <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />

                <div
                    class="group text-center border rounded-lg px-4 py-2 transition"
                    :class="moveStock.targets.length ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white'"
                >
                    <div
                        class="font-bold text-xs uppercase tracking-wide flex items-center justify-center gap-x-1.5 transition"
                        :class="moveStock.targets.length ? 'text-blue-600' : 'text-blue-500'"
                    >
                        <FontAwesomeIcon icon="fas fa-forklift" fixed-width />
                        {{ trans('Destination') }}
                    </div>
                    <template v-if="moveStock.targets.length">
                        <div
                            v-for="entry in moveStock.targets"
                            :key="entry.location.id"
                            class="flex items-center justify-center gap-x-2"
                        >
                            <span class="font-medium text-blue-700">{{ entry.location.name }}</span>
                            <span class="tabular-nums text-xs flex items-center gap-x-1">
                                <span v-tooltip="trans('Current stock in this location')" class="text-gray-500">
                                    <FractionDisplay :fractionData="toFractionData(entry.location.stock)" />
                                </span>
                                <template v-if="entry.quantity > 0">
                                    <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />
                                    <span v-tooltip="trans('Stock preview after move')" class="font-semibold text-blue-700">
                                        <FractionDisplay :fractionData="toFractionData(getCalculatedStock(entry.location))" />
                                    </span>
                                </template>
                            </span>
                        </div>
                    </template>
                    <div v-else class="font-medium text-gray-400 italic">—</div>
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

        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden space-y-1 pr-4 pb-3 mt-4">
        <template v-if="form.stockCheck.length > 0">
            <div v-for="location in form.stockCheck" :key="location.id"
                :ref="(el) => setStickyRowRef(location, el)"
                :data-location-id="location.id"
                :style="stickyRowStyle(location)"
                :class="[
                    'flex items-center gap-x-3 ps-2 pe-2 py-2 rounded transition',
                    isSource(location) ? 'bg-green-50 border border-green-100' :
                    isTarget(location) ? 'bg-blue-50 border border-blue-100' :
                    'border border-[rgba(255,255,255,0)] hover:bg-gray-50',
                    isSource(location) || isTarget(location) ? 'sticky z-10' : ''
                ]">

                <!-- Left: Source forklift -->
                <FontAwesomeIcon
                    icon="fas fa-forklift"
                    v-tooltip="isSource(location) ? trans('Unset as source') : ctrans('Set as source location')"
                    :class="[
                        'text-xl transition shrink-0',
                        isSource(location)
                            ? 'cursor-pointer text-green-600 scale-110' :
                        isTarget(location)
                            ? 'text-gray-400 opacity-70 cursor-pointer' :
                        location.stock <= 0
                            ? 'text-gray-400 opacity-90 cursor-not-allowed' :
                        moveStock.from
                            ? 'cursor-pointer text-gray-400 opacity-30 hover:opacity-80' :
                        'cursor-pointer opacity-50 hover:opacity-100 text-green-600'
                    ]"
                    fixed-width
                    aria-hidden="true"
                    @click="selectSource(location)"
                />

                <!-- Name + stock number -->
                <div class="flex-1 min-w-0 flex items-center gap-x-2 flex-wrap">
                    <span class="font-medium truncate">{{ location.name }}</span>

                    <!-- Preview: original + change -> result -->
                    <span
                        v-if="isSource(location) || isTarget(location)"
                        class="tabular-nums text-xs flex items-center gap-x-1"
                    >
                        <span
                            v-tooltip="isSource(location)
                                ? (moveStock.targets.length === 1 ? ctrans('Click to move all stock (empties this location)') : ctrans('Current stock in this location'))
                                : ctrans('Click to receive all remaining stock')"
                            :class="[
                                'border rounded px-1.5 py-0.5 border-gray-300 text-gray-600',
                                isSource(location) && moveStock.targets.length === 1
                                    ? 'cursor-pointer hover:border-red-300 hover:text-red-600 hover:bg-red-50 transition' :
                                isTarget(location)
                                    ? 'cursor-pointer hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition' : ''
                            ]"
                            @click="onStockChipClick(location)"
                        ><FractionDisplay :fractionData="toFractionData(location.stock)" /></span>
                        <template v-if="isSource(location)">
                            <span class="text-red-500 font-semibold">−</span>
                            <span class="flex items-center text-red-500">
                                <FractionDisplay :fractionData="toFractionData(totalMoveQuantity)" />
                            </span>
                        </template>
                        <div v-else class="shrink-0 flex items-center gap-x-1">
                            <span class="text-green-600 font-semibold">+</span>
                            <NumberWithButtonSave
                                :key="`whole-${location.id}-${inputKey}`"
                                v-tooltip="ctrans('Whole units to move to this location')"
                                :modelValue="getTargetEntry(location)?.wholeQuantity ?? 0"
                                @update:modelValue="(val: number) => updateTargetWholeQuantity(getTargetEntry(location), val)"
                                :min="0"
                                :max="maxWholeQuantityFor(getTargetEntry(location))"
                                noSaveButton
                                noUndoButton
                            />
                            <NumberWithButtonSave
                                v-if="isPackedStock"
                                :key="`fraction-${location.id}-${inputKey}`"
                                v-tooltip="ctrans('Fraction of a unit to move (:packedIn per unit)', { packedIn: packedIn })"
                                :modelValue="getTargetEntry(location)?.fractionQuantity ?? 0"
                                @update:modelValue="(val: number) => updateTargetFractionQuantity(getTargetEntry(location), val)"
                                :min="0"
                                :max="maxFractionQuantityFor(getTargetEntry(location))"
                                :denominator="packedIn"
                                noSaveButton
                                noUndoButton
                            />
                        </div>
                        <FontAwesomeIcon :icon="faLongArrowRight" class="text-gray-400" />
                        <span
                            v-tooltip="trans('Stock preview after move')"
                            class="font-semibold"
                            :class="isSource(location) ? 'text-green-700' : 'text-blue-700'"
                        >
                            <FractionDisplay :fractionData="toFractionData(getCalculatedStock(location))" />
                        </span>
                    </span>

                    <!-- Static stock (no pending change on this row) -->
                    <span
                        v-else
                        v-tooltip="trans('Stock in this location')"
                        class="tabular-nums text-xs border rounded px-1.5 py-0.5 border-gray-300 text-gray-600"
                    >
                        <FractionDisplay :fractionData="toFractionData(location.stock)" />
                    </span>
                </div>

                <!-- Audit info -->
                <div v-if="location.audited_at" v-tooltip="trans('Last audit :date', { date: useFormatTime(location.audited_at) })" class="text-right text-sm whitespace-nowrap hidden sm:block">
                    {{ formatDistanceStrict(new Date(location.audited_at), new Date()) }}
                    <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                </div>
                <div v-else class="text-right text-sm italic opacity-60 whitespace-nowrap hidden sm:block">
                    {{ trans("Never audited") }}
                </div>

                <!-- Replenishment suggestion -->
                <span
                    v-tooltip="trans('Apply suggested replenishment')"
                    class="text-sm text-blue-500 cursor-pointer hover:underline whitespace-nowrap"
                    :class="isTarget(location) ? '' : 'opacity-40 cursor-not-allowed'"
                    @click="isTarget(location) && applyReplenishment(location)"
                >
                    ({{ replenishment_data[location.id]?.replenishment_stock ?? '0' }})
                </span>

                <!-- Right: Target forklift -->
                <FontAwesomeIcon
                    icon="fas fa-forklift"
                    v-tooltip="isTarget(location) ? ctrans('Unset as destination') : ctrans('Add as destination location')"
                    :class="[
                        'text-xl transition shrink-0',
                        isTarget(location)
                            ? 'cursor-pointer text-blue-600 scale-110' :
                        isSource(location)
                            ? 'text-gray-300 opacity-20 cursor-not-allowed' :
                        moveStock.targets.length
                            ? 'cursor-pointer text-gray-400 opacity-30 hover:opacity-80' :
                        'cursor-pointer opacity-50 hover:opacity-100 text-blue-600'
                    ]"
                    fixed-width
                    aria-hidden="true"
                    @click="selectTarget(location)"
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
        </div>
        <!-- Section: buttons -->
        <div class="shrink-0 relative flex gap-x-2 z-40 pt-3 mt-2 border-t bg-white">
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
