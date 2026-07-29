<script setup lang='ts'>
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import Image from '@/Common/Components/Image.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faGift } from '@fal'
import { faCheckCircle, faArrowRight } from '@fas'
import { computed, inject, ref, watch } from 'vue'
import { OfferGiftProduct, OfferResource } from '@/types/Catalogue/Offers'
import { InputNumber } from 'primevue'
import { ctrans } from '@/Composables/useTrans'

library.add(faGift, faCheckCircle, faArrowRight)

const props = defineProps<{
    offer: OfferResource
    currencyCode?: string
}>()

const locale = inject('locale', aikuLocaleStructure)
const activeCurrencyCode = computed(() => props.currencyCode || 'GBP')

const convertToFloat2 = (val: unknown) => {
    const num = parseFloat(String(val ?? 0))
    if (Number.isNaN(num)) return 0
    return parseFloat(num.toFixed(2))
}

const giftData = computed(() => props.offer?.gift_data ?? null)

const giftProduct = computed<OfferGiftProduct | null>(() => giftData.value?.product ?? null)
const triggerProduct = computed<OfferGiftProduct | null>(() => giftData.value?.trigger_product ?? null)
const giftQuantity = computed(() => Math.max(Math.floor(Number(giftData.value?.quantity ?? 1)), 1))

const targetQuantity = computed(() => {
    const quantity = Math.floor(Number(giftData.value?.item_quantity ?? props.offer?.trigger_data?.item_quantity ?? 0))
    return Number.isNaN(quantity) || quantity < 0 ? 0 : quantity
})

const targetAmount = computed(() => {
    return convertToFloat2(
        giftData.value?.min_order_amount
        ?? props.offer?.trigger_data?.min_order_amount
        ?? props.offer?.trigger_data?.item_amount
        ?? 0
    )
})

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

const formatValue = (value: number) => {
    if (isQuantityTrigger.value) {
        return ctrans(':quantity items', { quantity: value })
    }

    return locale.currencyFormat(activeCurrencyCode.value, convertToFloat2(value))
}

const giftLabel = computed(() => {
    if (isReached.value) {
        return props.offer.label_got ?? props.offer.label ?? props.offer.code ?? ctrans('Gift offer reached')
    }

    return props.offer.label ?? props.offer.code ?? ctrans('Gift offer')
})

const conditionLabel = computed(() => {
    if (!targetValue.value) {
        return ctrans('No trigger condition is set on this offer')
    }

    if (isQuantityTrigger.value) {
        if (triggerProduct.value) {
            return ctrans('Order at least :quantity of :product', {
                quantity: targetQuantity.value,
                product: triggerProduct.value.code,
            })
        }

        return ctrans('Order at least :quantity items', { quantity: targetQuantity.value })
    }

    return ctrans('Spend at least :amount', {
        amount: locale.currencyFormat(activeCurrencyCode.value, targetAmount.value),
    })
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
    : ctrans('(Preview) Adjust how much customer order amount:'))
</script>

<template>
    <div class="w-full min-w-[340px]">
        <div class="mb-2 text-xs text-gray-500">
            {{ ctrans('Gift preview') }}
        </div>

        <div class="space-y-3 rounded-md border border-gray-200 bg-white p-3">
            <div class="grid grid-cols-[1fr_auto] items-center gap-x-4">
                <div class="flex w-full items-center truncate whitespace-nowrap text-ellipsis" :class="isReached ? 'text-green-700' : ''">
                    <FontAwesomeIcon icon='fal fa-gift' class='opacity-60 mr-1' fixed-width aria-hidden='true' />
                    <span class="font-bold">{{ ctrans('Gift') }}</span>:
                    <InformationIcon v-if="offer.information" :information="offer.information" class="ml-1" />
                    <span class="ml-2 truncate text-ellipsis">{{ giftLabel }}</span>
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

            <div class="text-xs text-gray-500">
                {{ conditionLabel }}
            </div>

            <div class="flex items-center gap-2">
                <div v-if="triggerProduct" class="flex min-w-0 flex-1 items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-2">
                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded border border-gray-200 bg-white">
                        <Image :src="triggerProduct.image" :alt="triggerProduct.name ?? triggerProduct.code" class="h-full w-full object-contain" />
                    </div>
                    <div class="min-w-0 text-left">
                        <div class="truncate text-xs font-semibold text-gray-700">{{ triggerProduct.code }}</div>
                        <div class="truncate text-xxs text-gray-400">{{ triggerProduct.name }}</div>
                    </div>
                    <div class="ml-auto shrink-0 text-xs font-semibold tabular-nums text-gray-500">
                        &times;{{ targetQuantity }}
                    </div>
                </div>

                <FontAwesomeIcon
                    v-if="triggerProduct && giftProduct"
                    icon="fas fa-arrow-right"
                    class="shrink-0 text-gray-300"
                    fixed-width
                    aria-hidden="true"
                />

                <div
                    v-if="giftProduct"
                    class="flex min-w-0 flex-1 items-center gap-2 rounded-md border px-2 py-2 transition-colors"
                    :class="isReached ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'"
                >
                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded border border-gray-200 bg-white">
                        <Image :src="giftProduct.image" :alt="giftProduct.name ?? giftProduct.code" class="h-full w-full object-contain" />
                    </div>
                    <div class="min-w-0 text-left">
                        <div class="truncate text-xs font-semibold" :class="isReached ? 'text-green-800' : 'text-gray-700'">
                            {{ giftProduct.code }}
                        </div>
                        <div class="truncate text-xxs text-gray-400">{{ giftProduct.name }}</div>
                    </div>
                    <div class="ml-auto shrink-0 text-right">
                        <div class="text-xs font-semibold tabular-nums" :class="isReached ? 'text-green-700' : 'text-gray-500'">
                            &times;{{ giftQuantity }}
                        </div>
                        <div v-if="giftProduct.price" class="text-xxs tabular-nums text-gray-400 line-through">
                            {{ locale.currencyFormat(activeCurrencyCode, giftProduct.price * giftQuantity) }}
                        </div>
                    </div>
                </div>

                <div v-else class="flex-1 rounded-md border border-dashed border-gray-300 px-2 py-3 text-center text-xs italic text-gray-400">
                    {{ ctrans('No gift product defined on this offer') }}
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
