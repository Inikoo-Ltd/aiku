<script setup lang="ts">
import { computed, inject } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingBasket } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import {
    activeLadderOption,
    basketLadderOptionKeys,
    formatLadderAmount,
    sharesAmountWithAnotherOption,
    type LadderOption
} from "@/Composables/buildPriceLadder"

const props = defineProps<{
    options: LadderOption[]
    productId: number
    quantity: number
    currencyCode?: string | null
    isSubmitting?: boolean
}>()

const emits = defineEmits<{
    (e: "selectOption", value: LadderOption): void
}>()

library.add(faShoppingBasket)

const locale = inject("locale", aikuLocaleStructure)
const customerDataByProduct = inject<any>("variantCustomerData", null)

const activeOption = computed<LadderOption | null>(() =>
    activeLadderOption(props.options, props.productId, props.quantity)
)

const hasSeveralSizes = computed<boolean>(() => new Set(props.options.map((option) => option.productId)).size > 1)

const hasSteps = computed<boolean>(() => props.options.some((option) => option.isStep))

const formatPrice = (value: number) => locale.currencyFormat(props.currencyCode ?? null, value)

const percentageLabel = (percentageOff: number): string => `${Math.round(percentageOff * 1000) / 10}%`

const optionLabel = (option: LadderOption): string => `${formatLadderAmount(option.amount, option.measure)}+`

const containersLabel = (option: LadderOption): string =>
    ctrans(":quantity × :size", { quantity: String(option.minQuantity), size: option.containerLabel })

const basketQuantity = (option: LadderOption): number =>
    Number(customerDataByProduct?.value?.[option.productId]?.quantity_ordered ?? 0) || 0

const basketOptionKeys = computed<Set<string>>(() =>
    basketLadderOptionKeys(
        props.options,
        Object.fromEntries(props.options.map((option) => [option.productId, basketQuantity(option)]))
    )
)

const isInBasket = (option: LadderOption): boolean => basketOptionKeys.value.has(option.key)

const inBasketLabel = (option: LadderOption): string =>
    ctrans("In your basket: :quantity × :size", {
        quantity: String(basketQuantity(option)),
        size: option.containerLabel || formatLadderAmount(option.containerAmount, option.measure)
    })

const isActive = (option: LadderOption): boolean => activeOption.value?.key === option.key

const onSelect = (option: LadderOption) => {
    if (props.isSubmitting || !option.isAvailable) {
        return
    }

    emits("selectOption", option)
}
</script>

<template>
    <div v-if="options.length" class="price-ladder w-full" role="radiogroup" :aria-label="ctrans('Size and quantity')">
        <div class="flex flex-col gap-2">
            <button
                v-for="option in options"
                :key="option.key"
                type="button"
                role="radio"
                class="ladder-option flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left"
                :class="{ 'is-active': isActive(option), 'is-unavailable': !option.isAvailable }"
                :aria-checked="isActive(option)"
                :disabled="isSubmitting || !option.isAvailable"
                @click="onSelect(option)"
            >
                <LoadingIcon v-if="isSubmitting && isActive(option)" class="radio-loading" />
                <span v-else class="radio" :class="{ 'radio-checked': isActive(option) }">
                    <span class="radio-dot" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="text-base font-bold text-gray-900">{{ optionLabel(option) }}</div>
                    <div class="text-sm text-gray-500">
                        <template v-if="option.isStep || sharesAmountWithAnotherOption(options, option)">{{ containersLabel(option) }}</template>
                        <template v-if="option.isStep"> · {{ ctrans("You save :percentage", { percentage: percentageLabel(option.percentageOff) }) }}</template>
                        <template v-else-if="option.isSpecialOffer">{{ sharesAmountWithAnotherOption(options, option) ? " · " : "" }}{{ ctrans("Special offer :percentage off", { percentage: percentageLabel(option.percentageOff) }) }}</template>
                        <template v-if="!option.isAvailable">
                            <span v-if="option.isStep || sharesAmountWithAnotherOption(options, option)"> · </span>{{ ctrans("Not enough stock") }}
                        </template>
                    </div>
                    <div v-if="isInBasket(option)" class="ladder-in-basket mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold">
                        <FontAwesomeIcon icon="fas fa-shopping-basket" class="text-[10px]" fixed-width aria-hidden="true" />
                        {{ inBasketLabel(option) }}
                    </div>
                </div>

                <div class="shrink-0 text-right">
                    <div class="text-base font-bold text-gray-900">{{ formatPrice(option.total) }}</div>
                    <div v-if="option.grossTotal > option.total" class="text-xs text-gray-400 line-through">{{ formatPrice(option.grossTotal) }}</div>
                    <div class="text-xs text-gray-500">({{ formatPrice(option.pricePerMeasure) }}/{{ option.measure }})</div>
                </div>
            </button>
        </div>

        <p v-if="hasSteps && hasSeveralSizes" class="mt-2 text-xs text-gray-500">
            {{ ctrans("Bulk prices count containers of one size; different sizes are not added together.") }}
        </p>
    </div>
</template>

<style scoped>
.price-ladder {
    --step-discount-color: #C48497;
}

.ladder-option {
    border-color: color-mix(in srgb, var(--step-discount-color) 25%, white);
    background: color-mix(in srgb, var(--step-discount-color) 4%, white);
    transition: border-color 0.2s ease, background 0.2s ease;
    cursor: pointer;
}

.ladder-option:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--step-discount-color) 60%, white);
}

.ladder-option:disabled {
    cursor: default;
}

.ladder-option.is-unavailable {
    opacity: 0.5;
}

.ladder-option.is-active {
    border-width: 2px;
    border-color: var(--step-discount-color);
    background: white;
}

.ladder-in-basket {
    color: color-mix(in srgb, var(--step-discount-color) 75%, black);
    background: color-mix(in srgb, var(--step-discount-color) 14%, white);
}

.radio-loading {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    color: var(--step-discount-color);
}

.radio {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border-radius: 9999px;
    border: 2px solid color-mix(in srgb, var(--step-discount-color) 40%, #9ca3af);
}

.radio-checked {
    border-color: var(--step-discount-color);
}

.radio-dot {
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    background: transparent;
}

.radio-checked .radio-dot {
    background: var(--step-discount-color);
}
</style>
