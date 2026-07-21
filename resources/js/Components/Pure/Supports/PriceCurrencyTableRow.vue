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

interface PriceColumn {
    kind: 'outer' | 'unit' | 'margin'
    label: string
}

const props = withDefaults(defineProps<{
    currency: {
        code: string
        symbol?: string
    }
    columns: PriceColumn[]
    editOn: 'outer' | 'unit'
    cellClass: string
    edgeClass: string
    unitsPerOuter?: number
    cost?: number | null
    readonly?: boolean
    isBase?: boolean
}>(), {
    unitsPerOuter: 1
})

const model = defineModel<CurrencyPrice>({ required: true })

const emits = defineEmits<{
    (e: 'change'): void
}>()

const units = computed(() => props.unitsPerOuter || 1)

const unitValue = computed(
    () => model.value.value == null
        ? null
        : Math.floor((model.value.value / units.value) * 100) / 100
)

const editableValue = computed({
    get: () => props.editOn === 'outer' ? model.value.value : unitValue.value,
    set: (value: number | null) => {
        model.value.value = value == null || props.editOn === 'outer'
            ? value
            : Math.round(value * units.value * 100) / 100
    }
})

const margin = computed(() => {
    if (model.value.value == null || model.value.value === 0 || props.cost == null) {
        return null
    }

    return Math.round(((model.value.value - props.cost) / model.value.value) * 100)
})

const marginClass = computed(() => {
    if (margin.value === null) {
        return 'text-gray-400'
    }

    if (margin.value <= 0) {
        return 'text-red-600'
    }

    return margin.value < 20 ? 'text-amber-600' : 'text-green-600'
})

const derivedValue = (kind: 'outer' | 'unit') => {
    const value = kind === 'outer' ? model.value.value : unitValue.value

    if (value == null) {
        return '—'
    }

    return `${props.currency.symbol ?? ''}${value.toFixed(2)}`
}

const toggleIndependent = () => {
    if (props.readonly) {
        return
    }

    model.value.independent = !model.value.independent
    emits('change')
}
</script>

<template>
    <tr class="border-b border-gray-200 transition-colors last:border-b-0 hover:bg-gray-50/70">
        <td class="whitespace-nowrap border-l-2 px-3 py-2 font-medium text-gray-700" :class="[cellClass, edgeClass]">
            <span class="inline-flex items-center gap-x-2">
                {{ currency.code }}
                <span
                    v-if="isBase"
                    v-tooltip="ctrans('Other currencies are derived from this one')"
                    class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600"
                >
                    {{ ctrans('Base') }}
                </span>
            </span>
        </td>

        <td
            v-for="(column, index) in columns"
            :key="column.kind"
            class="px-3 py-2 align-middle"
            :class="[cellClass, index === 0 ? `border-l-2 ${edgeClass}` : '']"
        >
            <div v-if="column.kind === 'margin'" class="text-right tabular-nums">
                <span class="font-medium" :class="marginClass">
                    {{ margin === null ? '—' : `${margin}%` }}
                </span>
            </div>

            <div v-else-if="column.kind === editOn" class="flex items-center gap-x-1.5">
                <div class="min-w-0 flex-1">
                    <PureInputNumber
                        v-model="editableValue"
                        :prefix="currency.symbol"
                        :readonly="readonly"
                        :disabled="!isBase && !model.independent"
                        :minValue="0"
                        :required="isBase"
                        @update:modelValue="emits('change')"
                    />
                </div>
                <button
                    v-tooltip="isBase ? undefined : (model.independent ? ctrans('Independent price') : ctrans('Linked to exchange rate'))"
                    type="button"
                    :disabled="readonly || isBase"
                    :tabindex="isBase ? -1 : undefined"
                    :aria-hidden="isBase"
                    :aria-pressed="model.independent"
                    :aria-label="ctrans('Independent price')"
                    class="shrink-0 rounded p-1.5 transition-colors hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                    :class="[
                        isBase ? 'invisible' : '',
                        model.independent ? 'text-green-500' : 'text-gray-300 hover:text-gray-500'
                    ]"
                    @click="toggleIndependent"
                >
                    <FontAwesomeIcon :icon="model.independent ? faUnlink : faLink" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div v-else class="text-right tabular-nums text-gray-600">
                {{ derivedValue(column.kind) }}
            </div>
        </td>
    </tr>
</template>
