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
    placeholder?: string
    units?: number
    impact?: {
        dirty: boolean
        delta: number | null
        marginBefore: number | null
        marginAfter: number | null
    } | null
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

const unitPrice = computed(() => {
    if (!props.units || props.units <= 1 || model.value.value == null) {
        return null
    }

    return (Number(model.value.value) / props.units).toFixed(props.currency.fraction_digits ?? 2)
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

        <div class="relative w-full">
            <!-- A currency with no price is empty, not free: never show it as a 0 -->
            <PureInputNumber
                v-model="displayValue"
                :placeholder="placeholder ?? '—'"
                :readonly="readonly"
                :required="required"
                :disabled="disabled"
                :minValue="0"
                @update:modelValue="emits('change')"
            />
            <span
                v-if="impact || unitPrice"
                class="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-x-2 text-xs tabular-nums"
                aria-hidden="true"
            >
                <span v-if="unitPrice" class="text-gray-600">
                    {{ unitPrice }}<span class="text-gray-400">/u</span>
                </span>
                <span
                    v-if="impact && impact.delta !== null"
                    class="font-medium"
                    :class="impact.delta >= 0 ? 'text-green-600' : 'text-red-600'"
                >{{ impact.delta >= 0 ? '▲' : '▼' }} {{ impact.delta >= 0 ? '+' : '' }}{{ impact.delta }}%</span>
                <span v-if="impact && impact.marginAfter !== null" class="text-gray-400">
                    {{ ctrans('M:') }}
                    <template v-if="impact.marginBefore !== null">{{ impact.marginBefore }}% → </template>
                    <span
                        v-if="impact.dirty"
                        class="font-medium"
                        :class="impact.marginBefore === null || impact.marginAfter >= impact.marginBefore ? 'text-green-600' : 'text-red-600'"
                    >{{ impact.marginAfter }}%</span>
                    <span v-else class="font-medium text-gray-500">{{ impact.marginAfter }}%</span>
                </span>
            </span>
        </div>

        <div class="w-8 shrink-0">
            <slot name="action" />
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
