<script setup lang='ts'>
import Image from '@/Common/Components/Image.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBoxOpen } from '@fal'
import { faCheckCircle } from '@fas'
import { computed, inject, ref, watch, watchEffect } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { OfferAllowanceResource, OfferProduct, OfferResource, OfferSimulation } from '@/types/Catalogue/Offers'
import { InputNumber } from 'primevue'

library.add(faBoxOpen, faCheckCircle)

const UNITS_STRIP_LIMIT = 24

const props = defineProps<{
    offer: OfferResource
    offer_allowances?: OfferAllowanceResource[]
    currencyCode?: string
}>()

const simulation = defineModel<OfferSimulation | null>('simulation', { default: null })

const locale = inject('locale', aikuLocaleStructure)
const activeCurrencyCode = computed(() => props.currencyCode || '')

const convertToFloat2 = (val: unknown) => {
    const num = parseFloat(String(val ?? 0))
    if (Number.isNaN(num)) return 0
    return parseFloat(num.toFixed(2))
}

const product = computed<OfferProduct | null>(() => props.offer?.trigger_product ?? null)

const unitPrice = computed(() => convertToFloat2(product.value?.price ?? 0))

const freeItemsAllowance = computed(() =>
    props.offer_allowances?.find(offerAllowance => offerAllowance.data?.free_quantity)?.data ?? null
)

const itemQuantity = computed(() => {
    const quantity = Math.floor(Number(
        freeItemsAllowance.value?.item_quantity
        ?? props.offer?.trigger_data?.item_quantity
        ?? 0
    ))
    return Number.isNaN(quantity) || quantity < 1 ? 0 : quantity
})

const freeQuantity = computed(() => {
    const quantity = Math.floor(Number(freeItemsAllowance.value?.free_quantity ?? 0))
    return Number.isNaN(quantity) || quantity < 1 ? 0 : quantity
})

const orderedQuantity = ref(0)

watch(itemQuantity, (newItemQuantity) => {
    orderedQuantity.value = newItemQuantity > 0 ? newItemQuantity : 0
}, { immediate: true })

const maxAdjustQuantity = computed(() => itemQuantity.value > 0 ? itemQuantity.value * 3 : 30)

const sanitizedOrderedQuantity = computed({
    get: () => orderedQuantity.value,
    set: (value) => {
        const next = Math.floor(Number(value) || 0)
        if (next < 0) {
            orderedQuantity.value = 0
            return
        }
        orderedQuantity.value = next > maxAdjustQuantity.value ? maxAdjustQuantity.value : next
    }
})

const freeUnits = computed(() => {
    if (!itemQuantity.value || !freeQuantity.value) return 0

    const units = Math.floor(sanitizedOrderedQuantity.value / itemQuantity.value) * freeQuantity.value

    return Math.min(units, sanitizedOrderedQuantity.value)
})

const paidUnits = computed(() => sanitizedOrderedQuantity.value - freeUnits.value)

const isReached = computed(() => freeUnits.value > 0)

const unitsInCurrentCycle = computed(() => {
    if (!itemQuantity.value) return 0
    return sanitizedOrderedQuantity.value % itemQuantity.value
})

const unitsToNextReward = computed(() => {
    if (!itemQuantity.value) return 0
    return itemQuantity.value - unitsInCurrentCycle.value
})

const meterWidth = computed(() => {
    if (!itemQuantity.value) return 0
    if (sanitizedOrderedQuantity.value >= itemQuantity.value && unitsInCurrentCycle.value === 0) return 100

    return unitsInCurrentCycle.value / itemQuantity.value * 100
})

const grossAmount = computed(() => convertToFloat2(sanitizedOrderedQuantity.value * unitPrice.value))

const savedAmount = computed(() => convertToFloat2(freeUnits.value * unitPrice.value))

const netAmount = computed(() => convertToFloat2(grossAmount.value - savedAmount.value))

const unitsStrip = computed(() => {
    const total = Math.min(sanitizedOrderedQuantity.value, UNITS_STRIP_LIMIT)

    return Array.from({ length: total }, (_, index) => index >= paidUnits.value)
})

const hiddenUnits = computed(() => Math.max(sanitizedOrderedQuantity.value - UNITS_STRIP_LIMIT, 0))

const dealLabel = computed(() => {
    if (!itemQuantity.value || !freeQuantity.value) {
        return ctrans('No free items rule is set on this offer')
    }

    return ctrans('Buy :quantity, get :free free', {
        quantity: itemQuantity.value,
        free: freeQuantity.value,
    })
})

const progressLabel = computed(() => {
    if (!itemQuantity.value || !freeQuantity.value) {
        return ctrans('No free items rule is set on this offer')
    }

    if (!sanitizedOrderedQuantity.value) {
        return ctrans('Order :quantity to get :free free', {
            quantity: itemQuantity.value,
            free: freeQuantity.value,
        })
    }

    if (isReached.value) {
        return ctrans(':free free, order :quantity more to get :next free', {
            free: freeUnits.value,
            quantity: unitsToNextReward.value,
            next: freeQuantity.value,
        })
    }

    return ctrans('Order :quantity more to get :free free', {
        quantity: unitsToNextReward.value,
        free: freeQuantity.value,
    })
})

watchEffect(() => {
    simulation.value = {
        mode: 'quantity',
        quantity: sanitizedOrderedQuantity.value,
        isQuantityExact: true,
        freeUnits: freeUnits.value,
        percentageOff: sanitizedOrderedQuantity.value ? freeUnits.value / sanitizedOrderedQuantity.value : 0,
        grossAmount: grossAmount.value,
        netAmount: netAmount.value,
        savedAmount: savedAmount.value,
        meterCurrent: unitsInCurrentCycle.value || (isReached.value ? itemQuantity.value : 0),
        meterTarget: itemQuantity.value,
        isReached: isReached.value,
    }
})

const meterTooltip = computed(() => {
    if (!itemQuantity.value || !freeQuantity.value) return ctrans('No free items rule is set on this offer')

    return ctrans('For every :quantity items ordered, :free are free', {
        quantity: itemQuantity.value,
        free: freeQuantity.value,
    })
})
</script>

<template>
    <div class="w-full min-w-[340px]">
        <div class="mb-2 text-xs text-gray-500">
            {{ ctrans('Free items preview') }}
        </div>

        <div class="space-y-3 rounded-md border border-gray-200 bg-white p-3">
            <div class="flex items-center gap-3">
                <div v-if="product" class="h-12 w-12 shrink-0 overflow-hidden rounded border border-gray-200 bg-white">
                    <Image :src="product.image" :alt="product.name ?? product.code" class="h-full w-full object-contain" />
                </div>

                <div class="min-w-0 flex-1 text-left">
                    <div class="truncate text-sm font-semibold text-gray-700">
                        {{ product?.code ?? offer.code }}
                    </div>
                    <div v-if="product?.name" class="truncate text-xs text-gray-400">{{ product.name }}</div>
                    <div v-if="unitPrice" class="text-xs tabular-nums text-gray-500">
                        {{ locale.currencyFormat(activeCurrencyCode, unitPrice) }} {{ ctrans('each') }}
                    </div>
                </div>

                <div
                    class="flex shrink-0 flex-col items-center justify-center rounded-md border px-3 py-2 leading-none"
                    :class="isReached ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-200 bg-gray-50 text-gray-600'"
                >
                    <span class="text-xl font-black tabular-nums">{{ itemQuantity }} &rarr; {{ freeQuantity }}</span>
                    <span class="mt-1 text-xxs uppercase tracking-[0.15em] opacity-70">{{ ctrans('Free') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-[1fr_auto] items-center gap-x-4">
                <div class="flex items-center truncate text-xs" :class="isReached ? 'text-green-700' : 'text-gray-500'">
                    <FontAwesomeIcon icon="fal fa-box-open" class="mr-1 opacity-60" fixed-width aria-hidden="true" />
                    <span class="truncate">{{ dealLabel }}</span>
                    <FontAwesomeIcon v-if="isReached" icon="fas fa-check-circle" class="ml-1 text-green-600" fixed-width aria-hidden="true" />
                </div>
                <div class="text-xs tabular-nums" :class="isReached ? 'text-green-700' : 'text-gray-500'">
                    {{ ctrans(':quantity items', { quantity: sanitizedOrderedQuantity }) }}
                </div>
            </div>

            <div v-tooltip="meterTooltip" class="flex w-full items-center">
                <div class="relative h-2 w-full overflow-hidden rounded-full bg-gray-200">
                    <div
                        class="absolute left-0 top-0 h-full transition-all duration-500 ease-in-out"
                        :class="isReached ? 'bg-green-500' : 'shimmer bg-green-400'"
                        :style="{ width: meterWidth + '%' }"
                    />
                </div>
            </div>

            <div class="text-xs text-gray-500">
                {{ progressLabel }}
            </div>

            <div v-if="unitsStrip.length" class="flex flex-wrap items-center gap-1">
                <div
                    v-for="(isFree, index) in unitsStrip"
                    :key="index"
                    v-tooltip="isFree ? ctrans('Free item') : ctrans('Paid item')"
                    class="h-4 w-4 rounded-sm border"
                    :class="isFree ? 'border-green-400 bg-green-200' : 'border-gray-300 bg-gray-100'"
                />
                <span v-if="hiddenUnits" class="text-xxs text-gray-400">+{{ hiddenUnits }}</span>
            </div>

            <div
                v-if="grossAmount"
                class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                :class="isReached ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-gray-50'"
            >
                <div class="text-xs text-gray-500">
                    {{ ctrans('Pays :paid of :total items', { paid: paidUnits, total: sanitizedOrderedQuantity }) }}
                </div>
                <div class="flex items-center gap-2 tabular-nums">
                    <span v-if="isReached" class="text-xs text-gray-400 line-through">
                        {{ locale.currencyFormat(activeCurrencyCode, grossAmount) }}
                    </span>
                    <span class="font-semibold" :class="isReached ? 'text-green-700' : 'text-gray-700'">
                        {{ locale.currencyFormat(activeCurrencyCode, netAmount) }}
                    </span>
                    <span v-if="isReached" class="rounded bg-green-100 px-1.5 py-0.5 text-xxs font-semibold text-green-700">
                        {{ ctrans('Save :amount', { amount: locale.currencyFormat(activeCurrencyCode, savedAmount) }) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-3 grid gap-2">
            <label class="text-xs text-gray-500">{{ ctrans('(Preview) Adjust how many items customer orders:') }}</label>
            <input
                v-model.number="sanitizedOrderedQuantity"
                type="range"
                min="0"
                :max="maxAdjustQuantity"
                step="1"
                class="w-full"
            >
            <InputNumber
                v-model.number="sanitizedOrderedQuantity"
                mode="decimal"
                :min="0"
                :max="maxAdjustQuantity"
                showButtons
                :step="1"
                :suffix="' ' + (sanitizedOrderedQuantity > 1 ? ctrans('items') : ctrans('item'))"
            />
        </div>
    </div>
</template>
