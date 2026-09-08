<script setup lang='ts'>
import Image from '@/Common/Components/Image.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBadgePercent } from '@fal'
import { faCheckCircle } from '@fas'
import { computed, inject, ref, watch, watchEffect } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { OfferAllowanceResource, OfferProduct, OfferResource, OfferSimulation } from '@/types/Catalogue/Offers'
import { InputNumber } from 'primevue'

library.add(faBadgePercent, faCheckCircle)

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

const percentageOff = computed(() => {
    const fromAllowance = props.offer_allowances?.find(offerAllowance => offerAllowance.data?.percentage_off)?.data?.percentage_off
    const fromSignature = props.offer?.data_allowance_signature?.percentage_off

    return parseFloat(String(fromAllowance ?? fromSignature ?? 0)) || 0
})

const percentageLabel = computed(() => {
    const value = percentageOff.value * 100
    return `${Number.isInteger(value) ? value : value.toFixed(1)}%`
})

const targetQuantity = computed(() => {
    const quantity = Math.floor(Number(props.offer?.trigger_data?.item_quantity ?? 0))
    return Number.isNaN(quantity) || quantity < 0 ? 0 : quantity
})

const targetAmount = computed(() => convertToFloat2(props.offer?.trigger_data?.item_amount ?? 0))

const isQuantityTrigger = computed(() => targetQuantity.value > 0)

const targetValue = computed(() => isQuantityTrigger.value ? targetQuantity.value : targetAmount.value)

const currentValue = ref(0)

watch(targetValue, (newTarget) => {
    if (newTarget <= 0) {
        currentValue.value = 0
        return
    }

    currentValue.value = isQuantityTrigger.value
        ? Math.floor(newTarget * 0.6)
        : convertToFloat2(newTarget * 0.6)
}, { immediate: true })

const maxAdjustValue = computed(() => {
    if (targetValue.value <= 0) {
        return isQuantityTrigger.value ? 10 : 100
    }

    return isQuantityTrigger.value
        ? Math.max(targetValue.value * 2, 2)
        : convertToFloat2(targetValue.value * 1.5)
})

const sanitizedCurrentValue = computed({
    get: () => currentValue.value,
    set: (value) => {
        const next = isQuantityTrigger.value ? Math.floor(Number(value) || 0) : convertToFloat2(value)
        if (next < 0) {
            currentValue.value = 0
            return
        }
        currentValue.value = next > maxAdjustValue.value ? maxAdjustValue.value : next
    }
})

const isReached = computed(() => {
    if (!targetValue.value) return false
    return sanitizedCurrentValue.value >= targetValue.value
})

const meterWidth = computed(() => {
    if (!targetValue.value) return 0
    const value = sanitizedCurrentValue.value / targetValue.value * 100
    return value > 100 ? 100 : value
})

const grossAmount = computed(() => {
    if (!isQuantityTrigger.value) {
        return convertToFloat2(sanitizedCurrentValue.value)
    }

    return convertToFloat2(sanitizedCurrentValue.value * unitPrice.value)
})

const savedAmount = computed(() => isReached.value ? convertToFloat2(grossAmount.value * percentageOff.value) : 0)

const netAmount = computed(() => convertToFloat2(grossAmount.value - savedAmount.value))

const simulatedQuantity = computed(() => {
    if (isQuantityTrigger.value) {
        return sanitizedCurrentValue.value
    }

    return unitPrice.value ? Math.max(Math.round(grossAmount.value / unitPrice.value), 1) : 0
})

watchEffect(() => {
    simulation.value = {
        mode: isQuantityTrigger.value ? 'quantity' : 'amount',
        quantity: simulatedQuantity.value,
        isQuantityExact: isQuantityTrigger.value,
        freeUnits: 0,
        percentageOff: isReached.value ? percentageOff.value : 0,
        grossAmount: grossAmount.value,
        netAmount: netAmount.value,
        savedAmount: savedAmount.value,
        meterCurrent: sanitizedCurrentValue.value,
        meterTarget: targetValue.value,
        isReached: isReached.value,
    }
})

const formatValue = (value: number) => {
    if (isQuantityTrigger.value) {
        return ctrans(':quantity items', { quantity: value })
    }

    return locale.currencyFormat(activeCurrencyCode.value, convertToFloat2(value))
}

const conditionLabel = computed(() => {
    if (!targetValue.value) {
        return ctrans('No trigger condition is set on this offer')
    }

    const productCode = product.value?.code

    if (isQuantityTrigger.value) {
        return productCode
            ? ctrans('Order at least :quantity of :product', { quantity: targetQuantity.value, product: productCode })
            : ctrans('Order at least :quantity items', { quantity: targetQuantity.value })
    }

    const amount = locale.currencyFormat(activeCurrencyCode.value, targetAmount.value)

    return productCode
        ? ctrans('Order at least :amount of :product', { amount, product: productCode })
        : ctrans('Order at least :amount', { amount })
})

const meterTooltip = computed(() => {
    if (!targetValue.value) return ctrans('No trigger condition is set on this offer')
    if (isReached.value) return ctrans('Offer activated')

    return ctrans(':current / :target (:condition to get the offer)', {
        current: formatValue(sanitizedCurrentValue.value),
        target: formatValue(targetValue.value),
        condition: conditionLabel.value,
    })
})

const adjustLabel = computed(() => isQuantityTrigger.value
    ? ctrans('(Preview) Adjust how many items customer orders:')
    : ctrans('(Preview) Adjust how much customer orders of this product:'))
</script>

<template>
    <div class="w-full min-w-[340px]">
        <div class="mb-2 text-xs text-gray-500">
            {{ ctrans('Product discount preview') }}
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
                    <span class="text-xl font-black tabular-nums">{{ percentageLabel }}</span>
                    <span class="mt-1 text-xxs uppercase tracking-[0.15em] opacity-70">{{ ctrans('Off') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-[1fr_auto] items-center gap-x-4">
                <div class="flex items-center truncate text-xs" :class="isReached ? 'text-green-700' : 'text-gray-500'">
                    <FontAwesomeIcon icon="fal fa-badge-percent" class="mr-1 opacity-60" fixed-width aria-hidden="true" />
                    <span class="truncate">{{ conditionLabel }}</span>
                    <FontAwesomeIcon v-if="isReached" icon="fas fa-check-circle" class="ml-1 text-green-600" fixed-width aria-hidden="true" />
                </div>
                <div class="text-xs tabular-nums" :class="isReached ? 'text-green-700' : 'text-gray-500'">
                    {{ formatValue(sanitizedCurrentValue) }} / {{ formatValue(targetValue) }}
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

            <div
                v-if="grossAmount"
                class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                :class="isReached ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-gray-50'"
            >
                <div class="text-xs text-gray-500">
                    {{ isReached ? ctrans('Customer pays') : ctrans('Current total') }}
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
            <label class="text-xs text-gray-500">{{ adjustLabel }}</label>
            <input
                v-model.number="sanitizedCurrentValue"
                type="range"
                min="0"
                :max="maxAdjustValue"
                :step="isQuantityTrigger ? 1 : 0.01"
                class="w-full"
            >
            <InputNumber
                v-model.number="sanitizedCurrentValue"
                :mode="isQuantityTrigger ? 'decimal' : 'currency'"
                :currency="activeCurrencyCode"
                :min="0"
                :max="maxAdjustValue"
                showButtons
                :step="isQuantityTrigger ? 1 : 0.5"
                :suffix="isQuantityTrigger ? ' ' + (sanitizedCurrentValue > 1 ? ctrans('items') : ctrans('item')) : undefined"
            />
        </div>
    </div>
</template>
