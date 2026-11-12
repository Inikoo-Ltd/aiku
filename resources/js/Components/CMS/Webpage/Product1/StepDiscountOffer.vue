<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFire } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { isStepCoveredBySpecialOffer, popularStepMinQuantity, stepsBeatingSpecialOffer } from "@/Composables/stepDiscountAgainstOffer"

library.add(faFire)

interface StepDiscountStep {
    min_quantity: number
    percentage_off: number
    percentage_off_label: string
    price: number
    price_per_unit: number
    is_popular?: boolean
}

const props = defineProps<{
    stepDiscount: {
        label?: string
        steps: StepDiscountStep[]
        unit?: string
        units?: number
    }
    currencyCode?: string | null
    originalPrice?: number | string
    unit?: string
    units?: number
    quantity?: number
    isSubmitting?: boolean
    specialOfferPercentageOff?: number
}>()

const emits = defineEmits<{
    (e: "selectQuantity", value: number): void
}>()

const locale = inject("locale", aikuLocaleStructure)

const allSteps = computed<StepDiscountStep[]>(() => props.stepDiscount?.steps ?? [])

const isSpecialOfferRunning = computed<boolean>(() => (props.specialOfferPercentageOff ?? 0) > 0)

const specialOfferPercentageOff = computed<number>(() => props.specialOfferPercentageOff ?? 0)

const stepsBeatingOffer = computed<StepDiscountStep[]>(() =>
    stepsBeatingSpecialOffer(allSteps.value, specialOfferPercentageOff.value)
)

const specialOfferPercentageOffLabel = computed<string>(() => `${Math.round(specialOfferPercentageOff.value * 100)}%`)

const isCoveredStep = (step: StepDiscountStep): boolean =>
    isStepCoveredBySpecialOffer(step, specialOfferPercentageOff.value)

const headerLabel = computed<string>(() =>
    isSpecialOfferRunning.value
        ? ctrans("Even better in bulk")
        : props.stepDiscount.label || ctrans("Buy more, save more")
)

const unitsPerOuter = computed<number>(() =>
    Number(props.units ?? props.stepDiscount?.units ?? 1) || 1
)

const innerUnitLabel = computed<string>(() => props.unit ?? props.stepDiscount?.unit ?? "")

const isPackedProduct = computed<boolean>(() => unitsPerOuter.value > 1)

const popularMinQuantity = computed<number | null>(() => popularStepMinQuantity(allSteps.value, stepsBeatingOffer.value))

const activeMinQuantity = ref<number | null>(null)

const reachedMinQuantity = (quantity: number): number | null => {
    const reached = allSteps.value.filter((step) => quantity >= step.min_quantity)

    return reached.length ? reached[reached.length - 1].min_quantity : null
}

watch(
    () => props.quantity,
    (quantity) => (activeMinQuantity.value = reachedMinQuantity(quantity ?? 0)),
    { immediate: true }
)

const formatPrice = (value: number) => locale.currencyFormat(props.currencyCode ?? null, value)

const basePrice = computed<number>(() => Number(props.originalPrice ?? 0) || 0)

const priceForCustomer = (step: StepDiscountStep): number =>
    isCoveredStep(step) && basePrice.value ? basePrice.value * (1 - specialOfferPercentageOff.value) : step.price

const pricePerUnitForCustomer = (step: StepDiscountStep): number =>
    isCoveredStep(step) && basePrice.value ? priceForCustomer(step) / unitsPerOuter.value : step.price_per_unit

const savedAmount = (step: StepDiscountStep): number => {
    if (!basePrice.value || basePrice.value <= step.price) {
        return 0
    }

    return basePrice.value - step.price
}

const quantityLabel = (step: StepDiscountStep): string => {
    if (!isPackedProduct.value) {
        return `${step.min_quantity}+ ${innerUnitLabel.value}`.trim()
    }

    return `${step.min_quantity}+ ${step.min_quantity > 1 ? ctrans("Outers") : ctrans("Outer")}`
}

const innerQuantityLabel = (step: StepDiscountStep): string =>
    `${step.min_quantity * unitsPerOuter.value} ${innerUnitLabel.value}`.trim()

const isPopularStep = (step: StepDiscountStep): boolean =>
    step.min_quantity === popularMinQuantity.value

const isActiveStep = (step: StepDiscountStep): boolean =>
    step.min_quantity === activeMinQuantity.value

const onSelectStep = (step: StepDiscountStep) => {
    if (props.isSubmitting) {
        return
    }

    if (isActiveStep(step)) {
        activeMinQuantity.value = null
        emits("selectQuantity", 0)
        return
    }

    activeMinQuantity.value = step.min_quantity
    emits("selectQuantity", step.min_quantity)
}
</script>

<template>
    <div v-if="stepsBeatingOffer.length" class="step-discount w-full">
        <div class="step-discount-header flex items-center gap-3 mb-4">
            <span class="header-line" />
            <span class="header-text">{{ headerLabel }}</span>
            <span class="header-line" />
        </div>

        <div class="flex flex-col gap-3">
            <button
                v-for="step in allSteps"
                :key="step.min_quantity"
                type="button"
                class="step-tier relative flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left"
                :class="{
                    'is-active': isActiveStep(step),
                    'is-popular': isPopularStep(step),
                    'is-dimmed': isSubmitting && !isActiveStep(step),
                }"
                :aria-pressed="isActiveStep(step)"
                :aria-busy="isSubmitting && isActiveStep(step)"
                :disabled="isSubmitting"
                @click="onSelectStep(step)"
                >
                <span v-if="isPopularStep(step)" class="popular-badge">
                    <FontAwesomeIcon :icon="faFire" fixed-width />
                    {{ ctrans("Popular") }}
                </span>

                <LoadingIcon v-if="isSubmitting && isActiveStep(step)" class="radio-loading" />
                <span v-else class="radio" :class="{ 'radio-checked': isActiveStep(step) }">
                    <span class="radio-dot" />
                </span>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-base font-bold text-gray-900">
                            {{ quantityLabel(step) }}
                        </span>
                        <span v-if="isCoveredStep(step)" class="covered-badge">
                            {{ ctrans("You already have this price") }}
                        </span>
                        <span v-else-if="savedAmount(step) > 0" class="save-badge">
                            {{ ctrans("Save :amount", { amount: formatPrice(savedAmount(step)) }) }}
                        </span>
                    </div>
                    <div class="text-sm text-gray-500 mt-0.5">
                        <span v-if="isPackedProduct">{{ innerQuantityLabel(step) }}</span>
                        <span v-if="isPackedProduct && step.percentage_off > 0" class="mx-1">·</span>
                        <template v-if="isCoveredStep(step)">
                            {{ ctrans("You save") }} {{ specialOfferPercentageOffLabel }}
                        </template>
                        <template v-else-if="step.percentage_off > 0">
                            {{ ctrans("You save") }} {{ step.percentage_off_label }}
                        </template>
                        <template v-else-if="!isPackedProduct">
                            {{ ctrans("Standard price") }}
                        </template>
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <div class="text-base font-bold text-gray-900">
                        {{ formatPrice(priceForCustomer(step)) }}
                    </div>
                    <div
                        v-if="basePrice && basePrice > priceForCustomer(step)"
                        class="text-sm text-gray-400 line-through"
                    >
                        {{ formatPrice(basePrice) }}
                    </div>
                    <div v-if="isPackedProduct" class="text-xs text-gray-400">
                        {{ formatPrice(pricePerUnitForCustomer(step)) }}/{{ innerUnitLabel }}
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.step-discount {
    --step-discount-color: #C48497;
}

.header-line {
    flex: 1;
    height: 1px;
    background: color-mix(in srgb, var(--step-discount-color) 30%, #d1d5db);
}

.header-text {
    white-space: nowrap;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--step-discount-color);
}

.step-tier {
    border-color: color-mix(in srgb, var(--step-discount-color) 25%, white);
    background: color-mix(in srgb, var(--step-discount-color) 4%, white);
    transition: border-color 0.2s ease, background 0.2s ease;
    cursor: pointer;
}

.step-tier:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--step-discount-color) 60%, white);
}

.step-tier:disabled {
    cursor: default;
}

.covered-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    font-size: 13px;
    font-weight: 700;
    color: var(--step-discount-color);
    border: 1px solid color-mix(in srgb, var(--step-discount-color) 40%, white);
    border-radius: 9999px;
}

.step-tier.is-dimmed {
    opacity: 0.5;
}

.radio-loading {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    color: var(--step-discount-color);
}

.step-tier.is-popular {
    border-color: color-mix(in srgb, var(--step-discount-color) 55%, white);
}

.step-tier.is-active {
    border-width: 2px;
    border-color: var(--step-discount-color);
    background: white;
}

.popular-badge {
    position: absolute;
    top: 0;
    right: 16px;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: white;
    background: var(--step-discount-color);
    border-radius: 9999px;
}

.save-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--step-discount-color);
    background: color-mix(in srgb, var(--step-discount-color) 12%, white);
    border-radius: 9999px;
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
