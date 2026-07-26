<script setup lang='ts'>
import { computed } from 'vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink, faUnlink } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLink, faUnlink)

interface CurrencyPrice {
    value: number | null
    independent: boolean
}

const props = defineProps<{
    currency: {
        code: string
        symbol?: string
        fraction_digits?: number | null
    }
    readonly?: boolean
    required?: boolean
    disabled?: boolean
    showIndependent?: boolean
    highlighted?: boolean
    dirty?: boolean
}>()

const model = defineModel<CurrencyPrice>({ required: true })

const emits = defineEmits<{
    (e: 'change'): void
}>()

const toggleIndependent = () => {
    model.value.independent = !model.value.independent
    emits('change')
}

// Derived rows are display-only, so show them with the currency's own decimals
const displayValue = computed({
    get: () => {
        if (props.disabled && model.value.value != null && props.currency.fraction_digits != null) {
            return Number(model.value.value).toFixed(props.currency.fraction_digits)
        }

        return model.value.value
    },
    set: (value: number | null) => {
        model.value.value = value
    }
})
</script>

<template>
    <div class="relative flex items-center gap-x-3">
        <div
            class="w-10 shrink-0 text-sm font-medium transition-colors"
            :class="highlighted ? 'text-sky-500' : 'text-gray-600'"
        >{{ currency.code }}</div>

        <span
            v-if="dirty"
            v-tooltip="ctrans('Unsaved change')"
            class="absolute left-[2.35rem] top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-amber-400"
            aria-hidden="true"
        />

        <PureInputNumber
            v-model="displayValue"
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
