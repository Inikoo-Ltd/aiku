<script setup lang='ts'>
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLayerGroup } from '@fal'
import { computed } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import { OfferAllowanceResource, OfferDiscountStep, OfferResource } from '@/types/Catalogue/Offers'

library.add(faLayerGroup)

const props = defineProps<{
    offer: OfferResource
    offer_allowances: OfferAllowanceResource[]
}>()

const steps = computed<OfferDiscountStep[]>(() => {
    const rawSteps = props.offer_allowances?.flatMap(offerAllowance => offerAllowance.data?.steps ?? []) ?? []

    return rawSteps
        .map(step => ({
            min_quantity: Number(step.min_quantity ?? 0),
            percentage_off: Number(step.percentage_off ?? 0),
        }))
        .filter(step => step.min_quantity > 0)
        .sort((a, b) => a.min_quantity - b.min_quantity)
})

const formatPercentage = (percentageOff: number) => {
    const value = percentageOff * 100
    return `${Number.isInteger(value) ? value : value.toFixed(1)}%`
}

const quantityLabel = (index: number) => {
    const step = steps.value[index]
    const nextStep = steps.value[index + 1]

    if (!nextStep) {
        return ctrans(':quantity+ items', { quantity: step.min_quantity })
    }

    if (nextStep.min_quantity - step.min_quantity === 1) {
        return ctrans(':quantity items', { quantity: step.min_quantity })
    }

    return ctrans(':from - :to items', { from: step.min_quantity, to: nextStep.min_quantity - 1 })
}
</script>

<template>
    <div class="w-full max-w-md">
        <div class="mb-2 flex items-center gap-2 text-xs text-gray-500">
            <FontAwesomeIcon icon="fal fa-layer-group" fixed-width aria-hidden="true" />
            {{ ctrans('Quantity steps') }}
        </div>

        <div v-if="steps.length" class="grid grid-cols-2 gap-2">
            <div
                v-for="(step, index) in steps"
                :key="step.min_quantity"
                class="flex flex-col items-center justify-center rounded-md border px-2 py-3 text-center"
                :class="index === steps.length - 1
                    ? 'border-purple-300 bg-purple-50 text-purple-700'
                    : 'border-gray-200 bg-gray-50 text-gray-600'"
            >
                <span class="text-2xl font-black leading-none tabular-nums">
                    {{ formatPercentage(step.percentage_off) }}
                </span>
                <span class="mt-1 text-xxs uppercase tracking-[0.15em] opacity-70">
                    {{ ctrans('Off') }}
                </span>
                <span class="mt-2 text-xs tabular-nums">
                    {{ quantityLabel(index) }}
                </span>
            </div>
        </div>

        <div v-else class="rounded-md border border-dashed border-gray-300 px-3 py-4 text-center text-sm italic text-gray-400">
            {{ ctrans('No discount steps defined on this offer') }}
        </div>
    </div>
</template>
