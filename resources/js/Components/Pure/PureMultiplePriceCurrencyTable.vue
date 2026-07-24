<!--
  - Shared multi-currency price table implementation.
  - Do not use directly: use MasterPriceCurrencyTable or MasterRrpCurrencyTable instead.
-->

<script setup lang='ts'>
import { computed, ref, watch } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import PriceCurrencyTableRow from '@/Components/Pure/Supports/PriceCurrencyTableRow.vue'
library.add(faChevronDown)

interface CurrencyRate {
    currency: string
    currency_symbol?: string
    currency_id: number
    ratio_gbp: number | null
    ratio_eur: number | null
}

interface CurrencyPrice {
    value: number | null
    independent: boolean
}

const props = withDefaults(defineProps<{
    modelValue: Record<string, CurrencyPrice> | null
    currencies: Record<string, CurrencyRate>
    label?: string
    color?: 'blue' | 'purple' | 'green' | 'amber' | 'gray'
    editOn?: 'outer' | 'unit'
    outerLabel?: string
    unitLabel?: string
    marginLabel?: string
    costLabel?: string
    showUnit?: boolean
    showMargin?: boolean
    showCost?: boolean
    costs?: Record<string, number | null>
    unitsPerOuter?: number
    readonly?: boolean
    visibleCurrencyCodes?: string[]
    autoFromCost?: boolean
    autoMultiplier?: number
    alwaysIndependentCurrencyCodes?: string[]
    selfCostCurrencyCodes?: string[]
}>(), {
    label: 'Price',
    color: 'blue',
    editOn: 'outer',
    showUnit: true,
    showMargin: true,
    showCost: false,
    unitsPerOuter: 1,
    autoFromCost: false,
    autoMultiplier: 2.4,
    visibleCurrencyCodes: () => ['GBP', 'EUR'],
    alwaysIndependentCurrencyCodes: () => ['GBP'],
    selfCostCurrencyCodes: () => []
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: Record<string, CurrencyPrice>): void
}>()

const palette = {
    blue: { header: 'bg-blue-100 text-blue-700 border-blue-400', cell: 'bg-blue-50', edge: 'border-blue-400' },
    purple: { header: 'bg-purple-100 text-purple-700 border-purple-400', cell: 'bg-purple-50', edge: 'border-purple-400' },
    green: { header: 'bg-green-100 text-green-700 border-green-400', cell: 'bg-green-50', edge: 'border-green-400' },
    amber: { header: 'bg-amber-100 text-amber-700 border-amber-400', cell: 'bg-amber-50', edge: 'border-amber-400' },
    gray: { header: 'bg-gray-100 text-gray-700 border-gray-400', cell: 'bg-gray-50', edge: 'border-gray-400' }
}

const colors = computed(() => palette[props.color])

const columns = computed(() => {
    const list: { kind: 'cost' | 'outer' | 'unit' | 'margin', label: string }[] = []

    if (props.showCost) {
        list.push({ kind: 'cost', label: props.costLabel ?? 'Cost' })
    }

    list.push({ kind: 'outer', label: props.outerLabel ?? `Outer ${props.label}` })

    if (props.showUnit || props.editOn === 'unit') {
        list.push({ kind: 'unit', label: props.unitLabel ?? `${props.label} / Unit` })
    }

    if (props.showMargin) {
        list.push({ kind: 'margin', label: props.marginLabel ?? `${props.label} Margin` })
    }

    return list
})

const currencyList = computed(
    () => Object.values(props.currencies ?? {}).map(rate => ({
        code: rate.currency,
        symbol: rate.currency_symbol,
        ratio_gbp: rate.ratio_gbp,
        ratio_eur: rate.ratio_eur
    }))
)

const baseCurrencyCode = ref('EUR')

const isAlwaysIndependent = (code: string) => props.alwaysIndependentCurrencyCodes.includes(code)

const isSelfCost = (code: string) => props.selfCostCurrencyCodes.includes(code)

const visibleCurrencies = computed(
    () => currencyList.value.filter(currency => props.visibleCurrencyCodes.includes(currency.code))
)

const baseCurrency = computed(
    () => currencyList.value.find(currency => currency.code === baseCurrencyCode.value)
)

const derivedVisibleCurrencies = computed(
    () => visibleCurrencies.value.filter(currency => currency.code !== baseCurrencyCode.value)
)

const hiddenCurrencies = computed(
    () => currencyList.value.filter(currency => !props.visibleCurrencyCodes.includes(currency.code))
)

const buildPrices = (): Record<string, CurrencyPrice> => {
    return currencyList.value.reduce((prices, currency) => {
        const existing = props.modelValue?.[currency.code]
        const rawValue = existing?.value as number | string | null | undefined

        prices[currency.code] = {
            value: rawValue == null || rawValue === '' ? null : Number(rawValue),
            independent: isAlwaysIndependent(currency.code) ? true : (existing?.independent ?? false)
        }

        return prices
    }, {} as Record<string, CurrencyPrice>)
}

const prices = ref<Record<string, CurrencyPrice>>(buildPrices())

watch(() => props.currencies, () => {
    prices.value = buildPrices()

    if (props.autoFromCost) {
        recalculateDerivedPrices()
    }
})

const getRatio = (currency: { ratio_gbp: number | null, ratio_eur: number | null }) => {
    return currency.ratio_eur
}

const recalculateDerivedPrices = () => {
    const baseEntry = prices.value[baseCurrencyCode.value]

    if (props.autoFromCost && baseEntry && !baseEntry.independent) {
        const baseCost = props.costs?.[baseCurrencyCode.value]

        baseEntry.value = baseCost == null
            ? null
            : Math.round(baseCost * props.autoMultiplier * 100) / 100
    }

    const basePrice = baseEntry?.value

    currencyList.value.forEach(currency => {
        const entry = prices.value[currency.code]

        if (!entry || entry.independent || currency.code === baseCurrencyCode.value) {
            return
        }

        if (props.autoFromCost && isSelfCost(currency.code)) {
            const cost = props.costs?.[currency.code]

            entry.value = cost == null
                ? null
                : Math.round(cost * props.autoMultiplier * 100) / 100

            return
        }

        const ratio = getRatio(currency)

        entry.value = basePrice == null || ratio == null
            ? null
            : Math.round(basePrice * ratio * 100) / 100
    })
}

const emitUpdate = () => {
    const snapshot = Object.entries(prices.value).reduce((acc, [code, entry]) => {
        acc[code] = { value: entry.value, independent: entry.independent }

        return acc
    }, {} as Record<string, CurrencyPrice>)

    emits('update:modelValue', snapshot)
}

const onUpdate = () => {
    recalculateDerivedPrices()
    emitUpdate()
}

watch(baseCurrencyCode, onUpdate)

watch(() => props.costs, () => {
    if (!props.autoFromCost) {
        return
    }

    recalculateDerivedPrices()
    emitUpdate()
}, { deep: true })

const showHiddenCurrencies = ref(false)

const filledHiddenCurrenciesCount = computed(
    () => hiddenCurrencies.value.filter(currency => prices.value[currency.code]?.independent).length
)

type CurrencyListItem = {
    code: string
    symbol?: string
    ratio_gbp: number | null
    ratio_eur: number | null
}

const rows = computed(() => {
    const list: { currency: CurrencyListItem, isBase: boolean }[] = []

    if (baseCurrency.value) {
        list.push({ currency: baseCurrency.value, isBase: true })
    }

    derivedVisibleCurrencies.value.forEach(currency => list.push({ currency, isBase: false }))

    if (showHiddenCurrencies.value) {
        hiddenCurrencies.value.forEach(currency => list.push({ currency, isBase: false }))
    }

    return list
})
</script>

<template>
    <div class="overflow-x-auto rounded-md border border-gray-200">
        <table class="w-full border-collapse text-xs">
            <colgroup>
                <col class="w-28" />
                <col v-for="column in columns" :key="column.kind" :class="column.kind === 'margin' ? 'w-20' : ''" />
            </colgroup>

            <thead>
                <tr class="text-[8px] font-semibold uppercase tracking-wide">
                    <th
                        :colspan="columns.length + 1"
                        class="border border-l-2 border-r-2 px-3 py-2 text-center"
                        :class="colors.header"
                    >
                        {{ ctrans(label) }}
                    </th>
                </tr>

                <tr class="text-[8px] font-medium uppercase tracking-wide text-gray-600">
                    <th class="border border-l-2 px-3 py-2 text-left" :class="colors.header">
                        {{ ctrans('Currency') }}
                    </th>
                    <th
                        v-for="(column, index) in columns"
                        :key="column.kind"
                        class="border px-3 py-2 text-right"
                        :class="[colors.header, index === 0 ? 'border-l-2' : '', index === columns.length - 1 ? 'border-r-2' : '']"
                    >
                        {{ ctrans(column.label) }}
                    </th>
                </tr>
            </thead>

            <tbody>
                <PriceCurrencyTableRow
                    v-for="row in rows"
                    :key="row.currency.code"
                    v-model="prices[row.currency.code]"
                    :currency="row.currency"
                    :columns="columns"
                    :editOn="editOn"
                    :cellClass="colors.cell"
                    :edgeClass="colors.edge"
                    :unitsPerOuter="unitsPerOuter"
                    :cost="costs?.[row.currency.code]"
                    :readonly="readonly"
                    :isBase="row.isBase"
                    :alwaysIndependent="isAlwaysIndependent(row.currency.code)"
                    :autoMode="autoFromCost"
                    @change="onUpdate"
                >
                    <template #cost-suffix="slotProps">
                        <slot name="cost-suffix" v-bind="slotProps" />
                    </template>
                </PriceCurrencyTableRow>

                <tr v-if="hiddenCurrencies.length" class="border-t border-gray-200 bg-gray-50/50">
                    <td :colspan="columns.length + 1" class="border-l-2 border-r-2 px-3 py-2" :class="colors.edge">
                        <button
                            type="button"
                            class="flex items-center gap-x-2 text-xs text-gray-500 transition-colors hover:text-gray-700"
                            :aria-expanded="showHiddenCurrencies"
                            @click="showHiddenCurrencies = !showHiddenCurrencies"
                        >
                            <FontAwesomeIcon
                                :icon="faChevronDown"
                                class="text-xs transition-transform duration-200"
                                :class="{ '-rotate-90': !showHiddenCurrencies }"
                                fixed-width
                                aria-hidden="true"
                            />
                            {{ showHiddenCurrencies ? ctrans('Hide other currencies') : ctrans('Other currencies') }}
                            <span class="text-gray-400">({{ hiddenCurrencies.length }})</span>
                            <span
                                v-if="filledHiddenCurrenciesCount"
                                class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-600 ring-1 ring-green-200"
                            >
                                {{ filledHiddenCurrenciesCount }} {{ ctrans('independent') }}
                            </span>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
