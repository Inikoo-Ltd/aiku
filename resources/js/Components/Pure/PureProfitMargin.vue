<script setup lang="ts">
import { computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'

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
    avgOrgCost: number | null
    prices?: Record<string, CurrencyPrice> | null
    costPrices?: Record<string, CurrencyPrice> | null
    currencies: Record<string, CurrencyRate>
    baseCurrencyCode?: string
    visibleCurrencyCodes?: string[]
}>(), {
    baseCurrencyCode: 'EUR',
    visibleCurrencyCodes: () => ['GBP', 'EUR']
})

const currencyList = computed(
    () => Object.values(props.currencies ?? {}).map(rate => ({
        code: rate.currency,
        symbol: rate.currency_symbol,
        ratio_gbp: rate.ratio_gbp,
        ratio_eur: rate.ratio_eur
    }))
)

const baseCurrency = computed(
    () => currencyList.value.find(currency => currency.code === props.baseCurrencyCode)
)

const visibleCurrencies = computed(
    () => currencyList.value.filter(currency => props.visibleCurrencyCodes.includes(currency.code))
)

const derivedVisibleCurrencies = computed(
    () => visibleCurrencies.value.filter(currency => currency.code !== props.baseCurrencyCode)
)

const hiddenCurrencies = computed(
    () => currencyList.value.filter(currency => !props.visibleCurrencyCodes.includes(currency.code))
)

const costInCurrency = (currency: { code: string, ratio_eur: number | null }): number | null => {
    if (props.costPrices) {
        return props.costPrices[currency.code]?.value ?? null
    }

    if (props.avgOrgCost == null) {
        return null
    }

    if (currency.code === props.baseCurrencyCode) {
        return props.avgOrgCost
    }

    return currency.ratio_eur == null
        ? null
        : Math.round(props.avgOrgCost * currency.ratio_eur * 100) / 100
}

const marginFor = (currency: { code: string, ratio_eur: number | null }): number | null => {
    const price = props.prices?.[currency.code]?.value
    const cost = costInCurrency(currency)

    if (price == null || price === 0 || cost == null) {
        return null
    }

    return Math.round(((price - cost) / price) * 1000) / 10
}

const formatMargin = (margin: number | null): string => {
    return margin == null ? '—' : `${margin}%`
}

const marginClass = (margin: number | null): string => {
    if (margin == null) {
        return 'text-gray-400'
    }

    return margin >= 0 ? 'text-green-600' : 'text-red-600'
}
</script>

<template>
    <div class="text-sm">
        <div v-if="baseCurrency" class="relative py-3 px pl-8">
            <span class="absolute bottom-0 left-3 top-1/2 w-px bg-gray-200" aria-hidden="true" />
            <span class="absolute left-2 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-gray-300"
                v-tooltip="'Base Ratio'" aria-hidden="true" />

            <div class="flex items-center gap-x-3">
                <div class="w-10 shrink-0 font-medium text-gray-600">{{ baseCurrency.code }}</div>
                <div :class="marginClass(marginFor(baseCurrency))">{{ formatMargin(marginFor(baseCurrency)) }}</div>
            </div>
        </div>

        <div v-for="currency in derivedVisibleCurrencies" :key="currency.code" class="relative py-3 px pl-8">
            <span class="absolute inset-y-0 left-3 w-px bg-gray-200" aria-hidden="true" />
            <span class="absolute left-3 top-1/2 h-px w-3 bg-gray-200" aria-hidden="true" />
            <span
                class="absolute left-[1.25rem] top-1/2 h-0 w-0 -translate-y-1/2 border-y-[3px] border-l-[4px] border-y-transparent border-l-gray-300"
                aria-hidden="true" />

            <div class="flex items-center gap-x-3">
                <div class="w-10 shrink-0 font-medium text-gray-600">{{ currency.code }}</div>
                <div :class="marginClass(marginFor(currency))">{{ formatMargin(marginFor(currency)) }}</div>
            </div>
        </div>

        <div v-if="hiddenCurrencies.length" class="mt-0.5 flex items-center gap-x-2 pl-8">
            <VDropdown :distance="6" placement="bottom-start">
                <template #default="{ shown }">
                    <button type="button"
                        v-tooltip="`${trans('Other currencies')} (${hiddenCurrencies.length})`"
                        class="flex items-center gap-x-1 rounded px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                        <FontAwesomeIcon :icon="faChevronDown" class="text-[0.6rem] transition-transform duration-200"
                            :class="{ '-rotate-90': !shown }" fixed-width aria-hidden="true" />
                        <span class="text-[0.7rem]">{{ hiddenCurrencies.length }}</span>
                    </button>
                </template>

                <template #popper>
                    <div class="w-64 py-1">
                        <div v-for="(currency, index) in hiddenCurrencies" :key="currency.code" class="relative py-px pl-8">
                            <span class="absolute left-3 top-0 w-px bg-gray-200"
                                :class="index === hiddenCurrencies.length - 1 ? 'h-1/2' : 'bottom-0'" aria-hidden="true" />
                            <span class="absolute left-3 top-1/2 h-px w-3 bg-gray-200" aria-hidden="true" />
                            <span
                                class="absolute left-[1.25rem] top-1/2 h-0 w-0 -translate-y-1/2 border-y-[3px] border-l-[4px] border-y-transparent border-l-gray-300"
                                aria-hidden="true" />

                            <div class="flex items-center gap-x-3">
                                <div class="w-10 shrink-0 font-medium text-gray-600">{{ currency.code }}</div>
                                <div :class="marginClass(marginFor(currency))">{{ formatMargin(marginFor(currency)) }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </VDropdown>
        </div>
    </div>
</template>
