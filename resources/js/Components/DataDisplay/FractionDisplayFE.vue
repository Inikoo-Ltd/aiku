<!--
    * Author: Vika Aqordi
    * Created on: 2026-08-05 10:28
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->


<script setup lang="ts">
import { computed, inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

defineOptions({ name: 'FractionDisplay' })

const props = withDefaults(defineProps<{
    numerator: number  // Pembilang (top)
    denominator: number  // Penyebut (bottom)

    showPlus?: boolean
    simplify?: boolean
    strikethrough?: boolean
}>(), {
    showPlus: false,
    simplify: false,
    strikethrough: false
})

const locale = inject<typeof aikuLocaleStructure>('locale', aikuLocaleStructure)

/**
 * Find the greatest common divisor (GCD) of two numbers
 */
const findGCD = (a: number, b: number): number => {
    if (b === 0) {
        return a
    }
    return findGCD(b, a % b)
}

/**
 * Drop the trailing decimal zeros a rescaled numerator can carry
 */
const trimDecimals = (value: number): number | string => {
    return value % 1 === 0 ? value : value.toFixed(3).replace(/\.?0+$/, '')
}

const isNegative = computed(() => (props.numerator < 0) !== (props.denominator < 0))

const absoluteNumerator = computed(() => Math.abs(props.numerator))

const absoluteDenominator = computed(() => Math.abs(props.denominator))

/**
 * The whole number carried out of an improper fraction, 22/8 gives 2
 */
const wholePart = computed(() => {
    if (absoluteDenominator.value === 0) {
        return absoluteNumerator.value
    }
    return Math.floor(absoluteNumerator.value / absoluteDenominator.value)
})

/**
 * What is left over once the whole part is taken out, 22/8 gives 6/8
 */
const remainder = computed(() => {
    if (absoluteDenominator.value === 0) {
        return { numerator: 0, denominator: 0 }
    }

    const numerator = absoluteNumerator.value - wholePart.value * absoluteDenominator.value
    const denominator = absoluteDenominator.value

    if (!props.simplify || numerator === 0 || !Number.isInteger(numerator) || !Number.isInteger(denominator)) {
        return { numerator, denominator }
    }

    const gcd = findGCD(numerator, denominator)

    return { numerator: numerator / gcd, denominator: denominator / gcd }
})

const hasFraction = computed(() => remainder.value.numerator !== 0)

const showWholePart = computed(() => wholePart.value !== 0 || !hasFraction.value)

const signPrefix = computed(() => {
    if (isNegative.value) {
        return '-'
    }
    return props.showPlus ? '+' : ''
})

const formattedWholePart = computed(() => locale.number(wholePart.value))

const formattedNumerator = computed(() => trimDecimals(remainder.value.numerator))

const formattedDenominator = computed(() => trimDecimals(remainder.value.denominator))
</script>


<template>
    <div class="fraction-display" :class="{ 'fraction-display--strikethrough': strikethrough }">
        <span v-if="signPrefix" class="sign">{{ signPrefix }}</span>

        <span v-if="showWholePart" class="quotient">{{ formattedWholePart }}</span>

        <span v-if="hasFraction" class="fraction">
            <span class="numerator">{{ formattedNumerator }}</span>
            <svg class="fraction-slash" viewBox="0 0 12 12" width="0.6em" height="0.8em" preserveAspectRatio="none">
                <line x1="1" y1="11" x2="11" y2="1" stroke="currentColor" stroke-width="1.5" />
            </svg>
            <span class="denominator">{{ formattedDenominator }}</span>
        </span>
    </div>
</template>

<style scoped>
.fraction-display {
    display: inline-flex;
    align-items: center;
    font-family: sans-serif;
    height: 1em;
    line-height: 1;
}

.sign {
    margin-right: 0.15em;
}

.quotient {
    margin-right: 0.2em;
}

.fraction {
    display: inline-flex;
    align-items: center;
    position: relative;
    height: 1em;
}

.numerator {
    position: relative;
    top: -0.25em;
    left: 0.05em;
    font-size: 0.8em;
}

.denominator {
    position: relative;
    bottom: -0.25em;
    right: 0.05em;
    font-size: 0.8em;
}

.fraction-slash {
    margin: 0 0.03em;
    position: relative;
    transform: scale(1, 1.2);
}

/* Adjust spacing for mixed numbers */
.quotient+.fraction {
    margin-left: 0.1em;
}

.fraction-display--strikethrough {
    position: relative;
}

.fraction-display--strikethrough::after {
    content: '';
    position: absolute;
    left: -2px;
    right: -2px;
    top: 50%;
    opacity: 0.5;
    transform: translateY(-50%);
    rotate: 35deg;
    height: 1px;
    background: currentColor;
    pointer-events: none;
}
</style>
