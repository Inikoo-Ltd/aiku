<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
// import PureInput from "@/Components/Pure/PureInput.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle, faPlus, faMinus } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from 'lodash-es'

library.add(faExclamationCircle, faCheckCircle, faPlus, faMinus, faSpinnerThird, faCopy)
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { InputNumber } from "primevue"
defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        bind: {}
    }
}>()

const weightComparisons: [number, string, string][] = [
    [5, '📄', ctrans('an A4 sheet of paper')],
    [60, '🥚', ctrans('an egg')],
    [120, '🍌', ctrans('a banana')],
    [200, '📱', ctrans('a smartphone')],
    [430, '⚽', ctrans('a football')],
    [1000, '🍍', ctrans('a pineapple')],
    [1500, '💻', ctrans('a laptop')],
    [2500, '🐔', ctrans('a chicken')],
    [3500, '👶', ctrans('a newborn baby')],
    [4500, '🐈', ctrans('a house cat')],
    [7000, '🎳', ctrans('a bowling ball')],
    [12000, '🧒', ctrans('a 2 year old child')],
    [23000, '🧳', ctrans('a full suitcase')],
    [30000, '🐕', ctrans('a Labrador')],
    [70000, '🧍', ctrans('an adult person')],
    [250000, '🏍️', ctrans('a motorbike')],
    [1500000, '🚗', ctrans('a car')],
]

const weightComparison = computed(() => {
    const grams = Number(props.form[props.fieldName])
    if (props.fieldData?.bind?.suffix !== 'g' || !(grams > 0)) {
        return null
    }
    const [reference, emoji, label] = weightComparisons.reduce((closest, candidate) =>
        Math.abs(Math.log(grams / candidate[0])) < Math.abs(Math.log(grams / closest[0])) ? candidate : closest
    )
    const ratio = grams / reference
    if (ratio < 0.75 || ratio > 1.35) {
        return null
    }
    return `${emoji} ${ctrans('about the weight of :thing', { thing: label })}`
})

const inputNumberBind = computed(() => {
    const bind = props.fieldData?.bind as Record<string, unknown> | undefined
    return bind?.step === undefined ? bind : { ...bind, step: Number(bind.step) }
})

</script>
<template>
    <div class="relative">
        <div class="relative">
            <InputNumber
                v-model="form[fieldName]"
                @input="(e) => form[fieldName] = e.value"
                inputId="horizontal-buttons"
                :minFractionDigits="0"
                :maxFractionDigits="2"
                v-bind="inputNumberBind"
                showButtons
            >
                <!--                <template #incrementbuttonicon>
                                    <FontAwesomeIcon :icon="faPlus" class="" fixed-width aria-hidden="true" />
                                </template>
                                <template #decrementbuttonicon>
                                    <FontAwesomeIcon :icon="faMinus" class="" fixed-width aria-hidden="true" />
                                </template>-->
            </InputNumber>

            <div class="absolute top-1/2 -translate-y-1/2 pointer-events-none right-6">
                <FontAwesomeIcon v-if="get(form, ['errors', `${fieldName}`])" fixed-width
                                 icon="fas fa-exclamation-circle"
                                 class="h-5 w-5 text-red-500" aria-hidden="true"/>
                <FontAwesomeIcon v-if="form.recentlySuccessful" fixed-width icon="fas fa-check-circle"
                                 class="h-5 w-5 text-green-500" aria-hidden="true"/>
                <FontAwesomeIcon v-if="form.processing" fixed-width icon="fad fa-spinner-third"
                                 class="h-5 w-5 animate-spin"/>
            </div>
        </div>
    </div>
    <p v-if="fieldData?.bind?.suffix === 'g' && (form[fieldName] >= 1000 || weightComparison)" class="mt-1 text-sm text-gray-500">
        <template v-if="form[fieldName] >= 1000">= {{ (form[fieldName] / 1000).toLocaleString(undefined, { maximumFractionDigits: 3 }) }} kg</template>
        <span v-if="weightComparison" class="ml-2 text-gray-400">{{ weightComparison }}</span>
    </p>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>
