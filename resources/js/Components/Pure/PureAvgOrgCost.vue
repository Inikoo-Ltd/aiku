<script setup lang="ts">
import { computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faWarehouse } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'

library.add(faChevronDown, faWarehouse)

interface CurrencyRate {
    currency: string
    currency_symbol?: string
    currency_id: number
    ratio_gbp: number | null
    ratio_eur: number | null
}

interface OrgDatum {
    org_code: string
    stock: number | null
    org_currency: string
    grp_currency: string
    org_cost: number | null
    grp_cost: number | null
    base_cost: number | null
    has_org_stocks: boolean
}

const props = withDefaults(defineProps<{
    avgOrgCost: number | null
    orgData?: Record<string, OrgDatum> | null
    currencies: Record<string, CurrencyRate>
    baseCurrencyCode?: string
    visibleCurrencyCodes?: string[]
}>(), {
    baseCurrencyCode: 'EUR',
    visibleCurrencyCodes: () => ['GBP', 'EUR']
})

const locale = inject<any>('locale', {})

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

const convert = (currency: { code: string, ratio_eur: number | null }): number | null => {
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

const formatCost = (code: string, value: number | null): string => {
    if (value == null) {
        return '—'
    }

    return typeof locale?.currencyFormat === 'function'
        ? locale.currencyFormat(code, value)
        : `${value}`
}

const orgList = computed(() => Object.values(props.orgData ?? {}))
</script>

<template>
    <div class="text-sm">
        <div v-if="baseCurrency" class="relative py-3 px pl-8">
            <span class="absolute bottom-0 left-3 top-1/2 w-px bg-gray-200" aria-hidden="true" />
            <span class="absolute left-2 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-gray-300"
                v-tooltip="'Base Ratio'" aria-hidden="true" />

            <div class="flex items-center gap-x-3">
                <div class="w-10 shrink-0 font-medium text-gray-600">{{ baseCurrency.code }}</div>
                <div class="text-gray-700">{{ formatCost(baseCurrency.code, convert(baseCurrency)) }}</div>
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
                <div class="text-gray-700">{{ formatCost(currency.code, convert(currency)) }}</div>
            </div>
        </div>

        <div class="mt-0.5 flex items-center gap-x-2 pl-8">
            <VDropdown v-if="hiddenCurrencies.length" :distance="6" placement="bottom-start">
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
                                <div class="text-gray-700">{{ formatCost(currency.code, convert(currency)) }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </VDropdown>

            <VDropdown v-if="orgList.length" :distance="6" placement="bottom-start">
                <button type="button" v-tooltip="trans('Organisations breakdown')"
                    class="flex items-center gap-x-1 rounded px-1 py-0.5 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                    <FontAwesomeIcon :icon="faWarehouse" class="text-[0.6rem]" fixed-width aria-hidden="true" />
                    <span class="text-[0.7rem]">{{ orgList.length }}</span>
                </button>

                <template #popper>
                    <div class="w-80 py-1">
                        <div class="border-b border-gray-100 px-2 py-1 text-[0.65rem] font-medium uppercase tracking-wide text-gray-400">
                            {{ trans('Organisations') }}
                        </div>
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-[0.6rem] uppercase text-gray-400">
                                    <th class="px-2 py-1 text-left font-medium">{{ trans('Org') }}</th>
                                    <th class="px-2 py-1 text-right font-medium">{{ trans('Stock') }}</th>
                                    <th class="px-2 py-1 text-right font-medium">{{ trans('Cost') }}</th>
                                    <th class="px-2 py-1 text-right font-medium">{{ trans('Base') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="org in orgList" :key="org.org_code">
                                    <td class="px-2 py-1 font-medium text-gray-700">{{ org.org_code }}</td>
                                    <td class="px-2 py-1 text-right text-gray-600">{{ org.stock ?? '—' }}</td>
                                    <td class="px-2 py-1 text-right text-gray-600">{{ formatCost(org.org_currency, org.org_cost) }}</td>
                                    <td class="px-2 py-1 text-right text-gray-600">{{ formatCost(baseCurrencyCode, org.base_cost) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </VDropdown>
        </div>
    </div>
</template>
