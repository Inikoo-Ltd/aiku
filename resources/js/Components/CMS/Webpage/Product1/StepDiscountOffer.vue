<script setup lang="ts">
import { computed, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

interface StepDiscountStep {
    min_quantity: number
    percentage_off: number
    percentage_off_label: string
    price: number
    price_per_unit: number
}

const props = defineProps<{
    stepDiscount: {
        label?: string
        steps: StepDiscountStep[]
    }
    currencyCode?: string | null
    originalPrice?: number
    unit?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const steps = computed<StepDiscountStep[]>(() => props.stepDiscount?.steps ?? [])

const bestStep = computed<StepDiscountStep | null>(() => {
    if (!steps.value.length) return null
    return steps.value.reduce((best, step) => (step.percentage_off > best.percentage_off ? step : best))
})

const formatPrice = (value: number) => locale.currencyFormat(props.currencyCode ?? null, value)

const savedAmount = (step: StepDiscountStep): number => {
    if (!props.originalPrice || props.originalPrice <= step.price) {
        return 0
    }

    return props.originalPrice - step.price
}

const isBestStep = (step: StepDiscountStep): boolean =>
    Boolean(bestStep.value && step.min_quantity === bestStep.value.min_quantity)
</script>

<template>
    <div v-if="steps.length" class="step-discount w-full">
        <div class="step-discount-header flex items-center gap-3 mb-4">
            <span class="header-line" />
            <span class="header-text">{{ stepDiscount.label || trans("Buy more, save more") }}</span>
            <span class="header-line" />
        </div>

        <div class="flex flex-col gap-3">
            <div
                v-for="step in steps"
                :key="step.min_quantity"
                class="step-tier relative flex items-center gap-3 rounded-xl border px-4 py-3"
                >
                <!-- :class="{ 'is-best': isBestStep(step) }" -->
                <!-- <span v-if="isBestStep(step)" class="popular-badge">
                    {{ trans("Most popular") }}
                </span> -->

                <span class="radio">
                    <span class="radio-dot" />
                </span>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-base font-bold text-gray-900">
                            {{ step.min_quantity }}+ <template v-if="unit">{{ unit }}</template>
                        </span>
                        <span v-if="savedAmount(step) > 0" class="save-badge">
                            {{ trans("Save") }} {{ formatPrice(savedAmount(step)) }}
                        </span>
                    </div>
                    <div class="text-sm text-gray-500 mt-0.5">
                        <template v-if="step.percentage_off > 0">
                            {{ trans("You save") }} {{ step.percentage_off_label }}
                        </template>
                        <template v-else>
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
                </div>
            </div>
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
}

.step-tier.is-best {
    border-width: 2px;
    border-color: var(--theme-color-4);
    background: white;
}

.popular-badge {
    position: absolute;
    top: 0;
    right: 16px;
    transform: translateY(-50%);
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
