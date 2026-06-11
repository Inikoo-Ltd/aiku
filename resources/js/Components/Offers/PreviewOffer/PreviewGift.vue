<script setup lang='ts'>
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faGift } from '@fal'
import { faCheckCircle } from '@fas'
import { computed, inject, ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { OfferResource } from '@/types/Catalogue/Offers'
import { InputNumber } from 'primevue'

library.add(faGift, faCheckCircle)

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

const targetAmount = computed(() => {
    return convertToFloat2(
        props.offer?.trigger_data?.min_order_amount
        ?? 0
    )
})

const currentAmount = ref(0)

watch(targetAmount, (newTarget) => {
    currentAmount.value = newTarget > 0 ? convertToFloat2(newTarget * 0.6) : 0
}, { immediate: true })

const maxAdjustAmount = computed(() => {
    return targetAmount.value > 0 ? convertToFloat2(targetAmount.value * 1.5) : 100
})

const sanitizedCurrentAmount = computed({
    get: () => currentAmount.value,
    set: (value) => {
        const next = convertToFloat2(value)
        if (next < 0) {
            currentAmount.value = 0
            return
        }
        currentAmount.value = next > maxAdjustAmount.value ? maxAdjustAmount.value : next
    }
})

const isReached = computed(() => {
    if (!targetAmount.value) return false
    return convertToFloat2(sanitizedCurrentAmount.value) >= targetAmount.value
})

const meterWidth = computed(() => {
    if (!targetAmount.value) return 0
    const value = convertToFloat2(sanitizedCurrentAmount.value) / targetAmount.value * 100
    return value > 100 ? 100 : value
})

const meterTooltip = computed(() => {
    if (!targetAmount.value) return trans('Target amount is not set on this offer')
    if (isReached.value) return trans('Offer activated')

    return trans(':current / :target (Spend at least :target to get the offer)', {
        current: locale.currencyFormat(activeCurrencyCode.value, convertToFloat2(sanitizedCurrentAmount.value)),
        target: locale.currencyFormat(activeCurrencyCode.value, targetAmount.value),
    })
})
</script>

<template>
    <div class="w-full min-w-[340px] ">
        <div class="mb-2 text-xs text-gray-500">
            {{ ctrans('Gift meter preview') }}
        </div>

        <div class="rounded-md border border-gray-200 bg-white p-3">
            <div class="mb-3 grid grid-cols-[1fr_auto] items-center gap-x-4">
                <div class="flex items-center whitespace-nowrap text-ellipsis truncate w-full" :class="isReached ? 'text-green-700' : ''">
                    <FontAwesomeIcon icon='fal fa-gift' class='opacity-60 mr-1' fixed-width aria-hidden='true' />
                    <span class="font-bold">{{ ctrans('Gift') }}</span>:
                    <InformationIcon v-if="offer.information" :information="offer.information" class="ml-1" />
                    <span class="ml-2 text-ellipsis truncate">
                        {{ isReached ? (offer.label_got ?? offer.label ?? offer.code ?? ctrans('Gift offer reached')) : (offer.label ?? offer.code ?? ctrans('Gift offer')) }}
                    </span>
                    <FontAwesomeIcon v-if="isReached" icon="fas fa-check-circle" class="ml-1 text-green-600" fixed-width aria-hidden="true" />
                </div>
                <div class="text-xs tabular-nums" :class="isReached ? 'text-green-700' : 'text-gray-500'">
                    {{ locale.currencyFormat(activeCurrencyCode, sanitizedCurrentAmount) }} / {{ locale.currencyFormat(activeCurrencyCode, targetAmount) }}
                </div>
            </div>
            <div v-tooltip="meterTooltip" class="mb-4 w-full flex items-center">
                <div class="w-full rounded-full h-2 bg-gray-200 relative overflow-hidden">
                    <div
                        class="absolute left-0 top-0 h-full transition-all duration-500 ease-in-out"
                        :class="isReached ? 'bg-green-500' : 'shimmer bg-green-400'"
                        :style="{ width: meterWidth + '%' }"
                    />
                </div>
            </div>
        </div>

        <div class="mt-3 grid gap-2">
            <label class="text-xs text-gray-500">{{ ctrans('(Preview) Adjust how much customer order amount:') }}</label>
            <input
                v-model.number="sanitizedCurrentAmount"
                type="range"
                min="0"
                :max="maxAdjustAmount"
                step="0.01"
                class="w-full"
            >
            <InputNumber
                v-model.number="sanitizedCurrentAmount"
                mode="currency"
                :currency="currencyCode"
                :min="0"
                :max="maxAdjustAmount"
                showButtons
                :step="0.5"
                xclass="w-full rounded border border-gray-300 px-2 py-1 text-sm"
            />
        </div>
    </div>
</template>
