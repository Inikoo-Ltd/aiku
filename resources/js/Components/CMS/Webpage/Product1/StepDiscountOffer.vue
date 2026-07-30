<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFire } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

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
    originalPrice?: number
    unit?: string
    units?: number
    quantity?: number
    isSubmitting?: boolean
}>()

const emits = defineEmits<{
    (e: "selectQuantity", value: number): void
}>()

const locale = inject("locale", aikuLocaleStructure)

const steps = computed<StepDiscountStep[]>(() => props.stepDiscount?.steps ?? [])

const unitsPerOuter = computed<number>(() =>
    Number(props.units ?? props.stepDiscount?.units ?? 1) || 1
)

const innerUnitLabel = computed<string>(() => props.unit ?? props.stepDiscount?.unit ?? "")

const isPackedProduct = computed<boolean>(() => unitsPerOuter.value > 1)

const popularMinQuantity = computed<number | null>(() => {
    if (!steps.value.length) return null

    const flagged = steps.value.find((step) => step.is_popular)
    if (flagged) return flagged.min_quantity

    return steps.value[Math.floor(steps.value.length / 2)].min_quantity
})

const activeMinQuantity = ref<number | null>(null)

const reachedMinQuantity = (quantity: number): number | null => {
    const reached = steps.value.filter((step) => quantity >= step.min_quantity)

    return reached.length ? reached[reached.length - 1].min_quantity : null
}

watch(
    () => props.quantity,
    (quantity) => (activeMinQuantity.value = reachedMinQuantity(quantity ?? 0)),
    { immediate: true }
)

const formatPrice = (value: number) => locale.currencyFormat(props.currencyCode ?? null, value)

const savedAmount = (step: StepDiscountStep): number => {
    if (!props.originalPrice || props.originalPrice <= step.price) {
        return 0
    }

    return props.originalPrice - step.price
}

const quantityLabel = (step: StepDiscountStep): string => {
    if (!isPackedProduct.value) {
        return `${step.min_quantity}+ ${innerUnitLabel.value}`.trim()
    }

    return `${step.min_quantity}+ ${step.min_quantity > 1 ? trans("Outers") : trans("Outer")}`
}

const innerQuantityLabel = (step: StepDiscountStep): string =>
    `${step.min_quantity * unitsPerOuter.value} ${innerUnitLabel.value}`.trim()

const isPopularStep = (step: StepDiscountStep): boolean =>
    step.min_quantity === popularMinQuantity.value

const isActiveStep = (step: StepDiscountStep): boolean =>
    step.min_quantity === activeMinQuantity.value

const onSelectStep = (step: StepDiscountStep) => {
    if (props.isSubmitting || props.quantity === step.min_quantity) {
        return
    }

    activeMinQuantity.value = step.min_quantity
    emits("selectQuantity", step.min_quantity)
}
</script>

<template>
    <div v-if="steps.length" class="step-discount w-full">
        <div class="step-discount-header flex items-center gap-3 mb-4">
            <span class="header-line" />
            <span class="header-text">{{ stepDiscount.label || trans("Buy more, save more") }}</span>
            <span class="header-line" />
        </div>

        <div class="flex flex-col gap-3">
            <button
                v-for="step in steps"
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
                    <FontAwesomeIcon :icon="faFire" />
                    {{ trans("Popular") }}
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
                        <span v-if="savedAmount(step) > 0" class="save-badge">
                            {{ trans("Save") }} {{ formatPrice(savedAmount(step)) }}
                        </span>
                    </div>
                    <div class="text-sm text-gray-500 mt-0.5">
                        <span v-if="isPackedProduct">{{ innerQuantityLabel(step) }}</span>
                        <span v-if="isPackedProduct && step.percentage_off > 0" class="mx-1">·</span>
                        <template v-if="step.percentage_off > 0">
                            {{ trans("You save") }} {{ step.percentage_off_label }}
                        </template>
                        <template v-else-if="!isPackedProduct">
                            {{ trans("Standard price") }}
                        </template>
                    </div>
                </div>

                <div class="text-right shrink-0">
                    <div class="text-base font-bold text-gray-900">
                        {{ formatPrice(step.price) }}
                    </div>
                    <div
                        v-if="originalPrice && originalPrice > step.price"
                        class="text-sm text-gray-400 line-through"
                    >
                        {{ formatPrice(originalPrice) }}
                    </div>
                    <div v-if="isPackedProduct" class="text-xs text-gray-400">
                        {{ formatPrice(step.price_per_unit) }}/{{ innerUnitLabel }}
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.header-line {
    flex: 1;
    height: 1px;
    background: color-mix(in srgb, var(--theme-color-4) 30%, #d1d5db);
}

.header-text {
    white-space: nowrap;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--theme-color-4);
}

.step-tier {
    border-color: color-mix(in srgb, var(--theme-color-4) 25%, white);
    background: color-mix(in srgb, var(--theme-color-4) 4%, white);
    transition: border-color 0.2s ease, background 0.2s ease;
    cursor: pointer;
}

.step-tier:hover:not(:disabled) {
    border-color: color-mix(in srgb, var(--theme-color-4) 60%, white);
}

.step-tier:disabled {
    cursor: default;
}

.step-tier.is-dimmed {
    opacity: 0.5;
}

.radio-loading {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    color: var(--theme-color-4);
}

.step-tier.is-popular {
    border-color: color-mix(in srgb, var(--theme-color-4) 55%, white);
}

.step-tier.is-active {
    border-width: 2px;
    border-color: var(--theme-color-4);
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
    background: var(--theme-color-4);
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
    color: var(--theme-color-4);
    background: color-mix(in srgb, var(--theme-color-4) 12%, white);
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
    border: 2px solid color-mix(in srgb, var(--theme-color-4) 40%, #9ca3af);
}

.radio-checked {
    border-color: var(--theme-color-4);
}

.radio-dot {
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    background: transparent;
}

.radio-checked .radio-dot {
    background: var(--theme-color-4);
}
</style>
