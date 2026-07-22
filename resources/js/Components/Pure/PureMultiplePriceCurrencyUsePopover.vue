<script setup lang='ts'>
import { computed, ref, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import PriceCurrencyRow from '@/Components/Pure/Supports/PriceCurrencyRow.vue'
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
    readonly?: boolean
    visibleCurrencyCodes?: string[]
}>(), {
    visibleCurrencyCodes: () => ['GBP', 'EUR']
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: Record<string, CurrencyPrice>): void
}>()

const currencyList = computed(
    () => Object.values(props.currencies ?? {}).map(rate => ({
        code: rate.currency,
        symbol: rate.currency_symbol,
        ratio_gbp: rate.ratio_gbp,
        ratio_eur: rate.ratio_eur
    }))
)

const baseCurrencyCode = ref('EUR')

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

        prices[currency.code] = {
            value: existing?.value ?? null,
            independent: existing?.independent ?? false
        }

        return prices
    }, {} as Record<string, CurrencyPrice>)
}

const prices = ref<Record<string, CurrencyPrice>>(buildPrices())

watch(() => props.currencies, () => {
    prices.value = buildPrices()
})

const getRatio = (currency: { ratio_gbp: number | null, ratio_eur: number | null }) => {
    return currency.ratio_eur
}

const recalculateDerivedPrices = () => {
    const basePrice = prices.value[baseCurrencyCode.value]?.value

    currencyList.value.forEach(currency => {
        const entry = prices.value[currency.code]
        const ratio = getRatio(currency)

        if (entry.independent) {
            return
        }

        entry.value = basePrice == null || ratio == null
            ? null
            : Math.round(basePrice * ratio * 100) / 100
    })
}

const onUpdate = () => {
    recalculateDerivedPrices()
    emits('update:modelValue', prices.value)
}

watch(baseCurrencyCode, onUpdate)

const filledHiddenCurrenciesCount = computed(
    () => hiddenCurrencies.value.filter(currency => prices.value[currency.code]?.independent).length
)
</script>

<template>
    <div>
        <div v-if="baseCurrency" class="relative py-1 pl-8">
            <span class="absolute bottom-0 left-3 top-1/2 w-px bg-gray-200" aria-hidden="true" />
            <span class="absolute left-2 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-gray-300" v-tooltip="'Base Ratio'" aria-hidden="true" />

            <PriceCurrencyRow
                v-model="prices[baseCurrency.code]"
                :currency="baseCurrency"
                :readonly="readonly"
                required
                @change="onUpdate"
            />
        </div>

        <div
            v-for="currency in derivedVisibleCurrencies"
            :key="currency.code"
            class="relative py-1 pl-8"
        >
            <template v-if="!prices[currency.code].independent">
                <span class="absolute inset-y-0 left-3 w-px bg-gray-200" aria-hidden="true" />
                <span class="absolute left-3 top-1/2 h-px w-3 bg-gray-200" aria-hidden="true" />
                <span
                    class="absolute left-[1.25rem] top-1/2 h-0 w-0 -translate-y-1/2 border-y-[3px] border-l-[4px] border-y-transparent border-l-gray-300"
                    aria-hidden="true"
                />
            </template>

            <PriceCurrencyRow
                v-model="prices[currency.code]"
                :currency="currency"
                :readonly="readonly"
                :disabled="!prices[currency.code].independent"
                required
                showIndependent
                @change="onUpdate"
            />
        </div>

        <Popover v-if="hiddenCurrencies.length" as="div" class="relative" v-slot="{ open }">
            <div class="relative py-1 pl-8">
                <span class="absolute inset-y-0 left-3 w-px bg-gray-200" aria-hidden="true" />


                <PopoverButton class="flex w-full items-center gap-x-2 py-1 text-sm text-gray-500 hover:text-gray-700">
                    <FontAwesomeIcon
                        :icon="faChevronDown"
                        class="text-xs transition-transform duration-200"
                        :class="{ '-rotate-90': !open }"
                        fixed-width
                        aria-hidden="true"
                    />
                    {{ ctrans('Other currencies') }}
                    <span class="text-gray-400">({{ hiddenCurrencies.length }})</span>
                    <span
                        v-if="filledHiddenCurrenciesCount"
                        class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-600 ring-1 ring-green-200"
                    >
                        {{ filledHiddenCurrenciesCount }} {{ ctrans('independent') }}
                    </span>
                </PopoverButton>
            </div>

            <transition name="headlessui">
                <PopoverPanel class="absolute left-0 z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg">
                    <div
                        v-for="(currency, index) in hiddenCurrencies"
                        :key="currency.code"
                        class="relative py-1 pl-8"
                    >
                        <span
                            class="absolute left-3 top-0 w-px bg-gray-200"
                            :class="index === hiddenCurrencies.length - 1 ? 'h-1/2' : 'bottom-0'"
                            aria-hidden="true"
                        />
                        <template v-if="!prices[currency.code].independent">
                            <span class="absolute left-3 top-1/2 h-px w-3 bg-gray-200" aria-hidden="true" />
                            <span
                                class="absolute left-[1.25rem] top-1/2 h-0 w-0 -translate-y-1/2 border-y-[3px] border-l-[4px] border-y-transparent border-l-gray-300"
                                aria-hidden="true"
                            />
                        </template>

                        <PriceCurrencyRow
                            v-model="prices[currency.code]"
                            :currency="currency"
                            :readonly="readonly"
                            :disabled="!prices[currency.code].independent"
                            showIndependent
                            @change="onUpdate"
                        />
                    </div>
                </PopoverPanel>
            </transition>
        </Popover>
    </div>
</template>
