<script setup lang='ts'>
import Image from '@/Common/Components/Image.vue'
import Discount from '@/Components/Utils/Label/Discount.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faGift } from '@fal'
import { faCheckCircle } from '@fas'
import { computed, inject } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { OfferAllowanceResource, OfferProduct, OfferResource, OfferSimulation } from '@/types/Catalogue/Offers'

library.add(faGift, faCheckCircle)

const props = defineProps<{
    offer: OfferResource
    offer_allowances?: OfferAllowanceResource[]
    currency_code?: string
    simulation?: OfferSimulation | null
}>()

const locale = inject('locale', aikuLocaleStructure)
const activeCurrencyCode = computed(() => props.currency_code || '')

const convertToFloat2 = (val: unknown) => {
    const num = parseFloat(String(val ?? 0))
    if (Number.isNaN(num)) return 0
    return parseFloat(num.toFixed(2))
}

const product = computed<OfferProduct | null>(() => props.offer?.trigger_product ?? null)

const unitPrice = computed(() => convertToFloat2(product.value?.price ?? 0))

const giftProduct = computed<OfferProduct | null>(() => props.offer?.gift_data?.product ?? null)

const giftQuantity = computed(() => Math.max(Math.floor(Number(props.offer?.gift_data?.quantity ?? 1)), 1))

const freeItemsAllowance = computed(() =>
    props.offer_allowances?.find(offerAllowance => offerAllowance.data?.free_quantity)?.data ?? null
)

const percentageAllowance = computed(() =>
    props.offer_allowances?.find(offerAllowance => offerAllowance.data?.percentage_off)?.data ?? null
)

const triggerQuantity = computed(() => {
    const quantity = Math.floor(Number(props.offer?.trigger_data?.item_quantity ?? 0))
    return Number.isNaN(quantity) || quantity < 1 ? 0 : quantity
})

const triggerAmount = computed(() => convertToFloat2(
    props.offer?.trigger_data?.item_amount
    ?? props.offer?.trigger_data?.min_order_amount
    ?? 0
))

const fallbackQuantity = computed(() => {
    if (triggerQuantity.value) {
        return triggerQuantity.value
    }

    if (triggerAmount.value && unitPrice.value) {
        return Math.max(Math.ceil(triggerAmount.value / unitPrice.value), 1)
    }

    return 1
})

const fallbackFreeUnits = computed(() => {
    const itemQuantity = Math.floor(Number(freeItemsAllowance.value?.item_quantity ?? triggerQuantity.value ?? 0))
    const freeQuantity = Math.floor(Number(freeItemsAllowance.value?.free_quantity ?? 0))

    if (!itemQuantity || !freeQuantity) return 0

    return Math.min(Math.floor(fallbackQuantity.value / itemQuantity) * freeQuantity, fallbackQuantity.value)
})

const fallbackPercentageOff = computed(() => {
    if (fallbackFreeUnits.value) {
        return fallbackFreeUnits.value / fallbackQuantity.value
    }

    const fromAllowance = percentageAllowance.value?.percentage_off
    const fromSignature = props.offer?.data_allowance_signature?.percentage_off

    return parseFloat(String(fromAllowance ?? fromSignature ?? 0)) || 0
})

const orderedQuantity = computed(() => props.simulation?.quantity ?? fallbackQuantity.value)

const isQuantityExact = computed(() => props.simulation?.isQuantityExact ?? true)

const freeUnits = computed(() => props.simulation?.freeUnits ?? fallbackFreeUnits.value)

const percentageOff = computed(() => props.simulation
    ? props.simulation.percentageOff
    : fallbackPercentageOff.value
)

const grossAmount = computed(() => props.simulation
    ? props.simulation.grossAmount
    : convertToFloat2(fallbackQuantity.value * unitPrice.value)
)

const discountedAmount = computed(() => props.simulation
    ? props.simulation.savedAmount
    : convertToFloat2(grossAmount.value * fallbackPercentageOff.value)
)

const netAmount = computed(() => convertToFloat2(grossAmount.value - discountedAmount.value))

const isReached = computed(() => props.simulation?.isReached ?? true)

const meterTarget = computed(() => convertToFloat2(props.simulation?.meterTarget ?? triggerAmount.value))

const meterCurrent = computed(() => convertToFloat2(props.simulation?.meterCurrent ?? meterTarget.value))

const meterWidth = computed(() => {
    if (!meterTarget.value) return isReached.value ? 100 : 0
    const width = meterCurrent.value / meterTarget.value * 100
    return width > 100 ? 100 : width
})

const isAmountMeter = computed(() => (props.simulation?.mode ?? (triggerQuantity.value ? 'quantity' : 'amount')) === 'amount')

const formatMeterValue = (value: number) => isAmountMeter.value
    ? String(locale.currencyFormat(activeCurrencyCode.value, value))
    : ctrans(':quantity items', { quantity: String(value) })

const offerLabel = computed(() => props.offer.name ?? props.offer.label ?? props.offer.code ?? '')

const offersData = computed(() => ({
    v: 1,
    o: {
        oc: props.offer.offer_campaign_id ?? null,
        o: props.offer.id ?? null,
        oa: null,
        t: freeUnits.value ? 'free_items' : 'percentage',
        p: `${(percentageOff.value * 100).toFixed(1)}%`,
        l: offerLabel.value,
        st: null,
        sto: null,
        f: discountedAmount.value,
        nf: freeUnits.value,
    }
}))

const isGiftOffer = computed(() => !!giftProduct.value)

const hasProductLine = computed(() => !!product.value && orderedQuantity.value > 0)

const hasDiscountLine = computed(() => hasProductLine.value && percentageOff.value > 0)

const simulationNote = computed(() => isAmountMeter.value
    ? ctrans('Simulated with an order of :amount', { amount: String(locale.currencyFormat(activeCurrencyCode.value, meterCurrent.value)) })
    : ctrans('Simulated with :quantity items in the basket', { quantity: String(orderedQuantity.value) })
)
</script>

<template>
    <div class="rounded-md border border-gray-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2">
            <div class="font-semibold text-gray-700">
                {{ ctrans("What customers see") }}
                <InformationIcon :information="ctrans('How this offer is shown in the customer basket, following the preview simulator above')" />
            </div>
            <div class="text-xs italic text-gray-400">
                {{ simulationNote }}
            </div>
        </div>

        <!-- Basket line simulation -->
        <div v-if="hasProductLine || (isGiftOffer && isReached)" class="divide-y divide-gray-200">
            <div v-if="hasProductLine && product" class="grid grid-cols-[5rem_1fr_auto_auto] items-center gap-4 px-4 py-3">
                <div class="relative flex aspect-square w-20 overflow-hidden">
                    <Image :src="product.image" :alt="product.name ?? product.code" class="h-full w-full object-contain" />
                </div>

                <div class="min-w-0 text-left">
                    <div class="text-xs italic text-gray-500">{{ product.code }}</div>
                    <div class="truncate text-base text-gray-700">{{ product.name }}</div>
                    <Discount v-if="hasDiscountLine" :offers_data="offersData" class="mt-1" />
                </div>

                <div class="text-center">
                    <div class="rounded xborder border-gray-300 px-3 py-1 xtext-sm tabular-nums text-gray-600">
                        <span v-if="!isQuantityExact" class="text-gray-400">&asymp;</span>{{ orderedQuantity }}
                    </div>
                    <!-- <div v-if="freeUnits" class="mt-1 text-xxs text-green-600">
                        {{ ctrans(':quantity free', { quantity: freeUnits }) }}
                    </div> -->
                </div>

                <div class="text-right">
                    <p :class="discountedAmount ? 'text-green-500' : ''">
                        <span v-if="discountedAmount" class="mr-1 text-gray-500 line-through opacity-70">
                            {{ locale.currencyFormat(activeCurrencyCode, grossAmount) }}
                        </span>
                        <span>{{ locale.currencyFormat(activeCurrencyCode, netAmount) }}</span>
                    </p>
                </div>
            </div>

            <!-- Gift line, only once the offer is triggered -->
            <div v-if="isGiftOffer && isReached && giftProduct" class="grid grid-cols-[5rem_1fr_auto_auto] items-center gap-4 px-4 py-3">
                <div class="relative flex aspect-square w-20 overflow-hidden">
                    <Image :src="giftProduct.image" :alt="giftProduct.name ?? giftProduct.code" class="h-full w-full object-contain" />
                </div>

                <div class="min-w-0 text-left">
                    <div class="text-xs italic text-gray-500">{{ giftProduct.code }}</div>
                    <div class="truncate text-base text-gray-700">{{ giftProduct.name }}</div>
                    <div class="mt-1 flex w-fit items-center rounded-sm border border-[#2a919e] bg-[#2a919e] px-1 py-0.5 text-xs text-white">
                        <FontAwesomeIcon icon="fal fa-gift" class="mr-1" fixed-width aria-hidden="true" />
                        {{ ctrans("Free gift") }}
                    </div>
                </div>

                <div class="text-center">
                    <div class="rounded border border-gray-300 px-3 py-1 text-sm tabular-nums text-gray-600">
                        {{ giftQuantity }}
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-green-500">
                        <span v-if="giftProduct.price" class="mr-1 text-gray-500 line-through opacity-70">
                            {{ locale.currencyFormat(activeCurrencyCode, giftProduct.price * giftQuantity) }}
                        </span>
                        <span>{{ locale.currencyFormat(activeCurrencyCode, 0) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="px-4 py-6 text-center text-sm italic text-gray-400">
            {{ isGiftOffer
                ? ctrans("The gift is not in the basket yet, the customer has not reached the offer")
                : ctrans("This offer has no product line to show in the customer basket") }}
        </div>

        <!-- Offer meter, as shown in the checkout summary -->
        <div v-if="isGiftOffer" class="border-t border-gray-200 px-4 py-3">
            <div class="grid grid-cols-2 gap-x-4">
                <div
                    class="flex w-full items-center truncate whitespace-nowrap text-ellipsis"
                    :class="isReached ? 'text-green-700' : ''"
                >
                    <FontAwesomeIcon icon="fal fa-gift" class="mr-1 opacity-60" fixed-width aria-hidden="true" />
                    <span class="font-bold">{{ ctrans('Gift') }}</span>:
                    <InformationIcon v-if="offer.information" :information="offer.information" class="ml-1" />
                    <span class="ml-2">
                        {{ isReached ? (offer.label_got ?? offer.label ?? offerLabel) : (offer.label ?? offerLabel) }}
                    </span>
                    <FontAwesomeIcon v-if="isReached" icon="fas fa-check-circle" class="ml-1" fixed-width aria-hidden="true" />
                </div>
                <div
                    v-tooltip="isReached
                        ? ctrans('Offer activated')
                        : ctrans(':current / :target', { current: formatMeterValue(meterCurrent), target: formatMeterValue(meterTarget) })"
                    class="flex w-full items-center"
                >
                    <div class="relative h-2 w-full overflow-hidden rounded-full bg-gray-200">
                        <div
                            class="absolute left-0 top-0 h-full transition-all duration-500 ease-in-out"
                            :class="isReached ? 'bg-green-500' : 'shimmer bg-green-400'"
                            :style="{ width: meterWidth + '%' }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
