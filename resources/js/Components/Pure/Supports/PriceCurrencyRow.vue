<script setup lang='ts'>
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink, faUnlink } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLink, faUnlink)

interface CurrencyPrice {
    value: number | null
    independent: boolean
}

defineProps<{
    currency: {
        code: string
        symbol?: string
    }
    readonly?: boolean
    required?: boolean
    disabled?: boolean
    showIndependent?: boolean
}>()

const model = defineModel<CurrencyPrice>({ required: true })

const emits = defineEmits<{
    (e: 'change'): void
}>()

const toggleIndependent = () => {
    model.value.independent = !model.value.independent
    emits('change')
}
</script>

<template>
    <div class="flex items-center gap-x-3">
        <div class="w-10 shrink-0 text-sm font-medium text-gray-600">{{ currency.code }}</div>

        <PureInputNumber
            v-model="model.value"
            :prefix="currency.symbol"
            :readonly="readonly"
            :required="required"
            :disabled="disabled"
            :minValue="0"
            @update:modelValue="emits('change')"
        />

        <div class="w-8 shrink-0">
            <button
                v-if="showIndependent"
                v-tooltip="model.independent ? ctrans('Independent price') : ctrans('Linked to exchange rate')"
                type="button"
                :disabled="readonly"
                :aria-pressed="model.independent"
                :aria-label="ctrans('Independent price')"
                class="w-full rounded p-1.5 transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                :class="model.independent ? 'text-green-500' : 'text-gray-300 hover:text-gray-500'"
                @click="toggleIndependent"
            >
                <FontAwesomeIcon :icon="model.independent ? faUnlink : faLink" fixed-width aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
