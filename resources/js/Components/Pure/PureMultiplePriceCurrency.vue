<script setup lang='ts'>
import { computed, onMounted, ref, watch } from 'vue'
import { Disclosure, DisclosureButton, DisclosurePanel, Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faExclamationTriangle, faSave as falSave , faStarfighter} from '@fal'
import { faSave as fadSave, faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import PriceCurrencyRow from '@/Components/Pure/Supports/PriceCurrencyRow.vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import axios from 'axios'
library.add(faChevronDown, faExclamationTriangle, falSave, fadSave, faSpinnerThird)

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

interface PriceRebel {
    id: number
    shop_id: number
    shop_code: string
    currency_code: string
    value: number | null
}

const props = withDefaults(defineProps<{
    modelValue: Record<string, CurrencyPrice> | null
    currencies: Record<string, CurrencyRate>
    readonly?: boolean
    visibleCurrencyCodes?: string[]
    masterAsset: number | string
    type_input : string
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

const alwaysIndependentCurrencyCodes = ['GBP']

const isAlwaysIndependent = (code: string) => alwaysIndependentCurrencyCodes.includes(code)

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
            independent: isAlwaysIndependent(currency.code) ? true : (existing?.independent ?? false)
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

const priceRebels = ref<Record<string, PriceRebel>>({})

const priceRebelsList = computed(() => Object.values(priceRebels.value))

const savingRebelIds = ref<Record<number, boolean>>({})

const originalRebelValues = ref<Record<number, number | null>>({})

const isRebelEdited = (rebel: PriceRebel) => {
    return rebel.value !== originalRebelValues.value[rebel.shop_id]
}

onMounted(async () => {
    try {
        const { data } = await axios.post(
            route('grp.json.master_products.get_price_rebels', {
                masterAsset: props.masterAsset
            }),
            {
                type: props.type_input
            }
        )
        priceRebels.value = data ?? {}
        originalRebelValues.value = Object.values(priceRebels.value).reduce(
            (values, rebel) => {
                values[rebel.shop_id] = rebel.value
                return values
            },
            {} as Record<number, number | null>
        )
    } catch (error) {
        priceRebels.value = {}
        originalRebelValues.value = {}
    }
})

const saveRebel = async (rebel: PriceRebel) => {
    savingRebelIds.value[rebel.shop_id] = true

    try {
        await axios.patch(
            route('grp.models.product.update', {
                product : rebel.id
            }),
            {
                [ props.type_input ]: rebel.value
            }
        )
        originalRebelValues.value[rebel.shop_id] = rebel.value
    } finally {
        savingRebelIds.value[rebel.shop_id] = false
    }
}
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
                :showIndependent="!isAlwaysIndependent(currency.code)"
                @change="onUpdate"
            />
        </div>

        <Disclosure v-if="hiddenCurrencies.length" as="div" v-slot="{ open }">
            <div class="relative py-1 pl-8">
                <span class="absolute inset-y-0 left-3 w-px bg-gray-200" aria-hidden="true" />


                <DisclosureButton class="flex w-full items-center gap-x-2 py-1 text-sm text-gray-500 hover:text-gray-700">
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
                </DisclosureButton>
            </div>

            <DisclosurePanel>
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
                        :showIndependent="!isAlwaysIndependent(currency.code)"
                        @change="onUpdate"
                    />
                </div>
            </DisclosurePanel>
        </Disclosure>

        <Popover v-if="priceRebelsList.length" as="div" class="relative mt-2 pl-8">
            <PopoverButton
                class="flex w-fit items-center gap-x-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm text-amber-700 hover:bg-amber-100"
            >
                <FontAwesomeIcon :icon="faStarfighter" class="text-xs" fixed-width aria-hidden="true" />
                <span>
                    {{ priceRebelsList.length }} {{ ctrans('shops not following master price') }}
                </span>
            </PopoverButton>

            <transition name="headlessui">
                <PopoverPanel class="absolute left-8 z-10 mt-1 w-72 rounded-md border border-gray-200 bg-white shadow-lg">
                    <div class="border-b border-gray-100 px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-400">
                        {{ ctrans('Price rebels') }}
                    </div>
                    <ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto">
                        <li
                            v-for="rebel in priceRebelsList"
                            :key="rebel.shop_id"
                            class="flex items-center justify-between gap-x-3 px-3 py-2 text-sm"
                        >
                            <span class="font-medium text-gray-700">{{ rebel.shop_code }}</span>
                            <div class="flex items-center gap-x-1.5">
                                <div class="w-28">
                                    <PureInputNumber
                                        v-model.number="rebel.value"
                                        :prefix="rebel.currency_symbol"
                                        :readonly="readonly"
                                        :disabled="savingRebelIds[rebel.shop_id]"
                                    />
                                </div>
                                <button
                                    v-if="!readonly"
                                    type="button"
                                    class="align-bottom text-center disabled:cursor-not-allowed"
                                    :disabled="savingRebelIds[rebel.shop_id] || !isRebelEdited(rebel)"
                                    @click="saveRebel(rebel)"
                                >
                                    <FontAwesomeIcon
                                        v-if="savingRebelIds[rebel.shop_id]"
                                        icon="fad fa-spinner-third"
                                        class="animate-spin text-lg"
                                        fixed-width
                                        aria-hidden="true"
                                    />
                                    <FontAwesomeIcon
                                        v-else
                                        icon="fad fa-save"
                                        class="text-lg"
                                        :class="{ 'text-gray-300': !isRebelEdited(rebel) }"
                                        :style="isRebelEdited(rebel) ? { '--fa-secondary-color': 'rgb(0, 255, 4)' } : undefined"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </li>
                    </ul>
                </PopoverPanel>
            </transition>
        </Popover>
    </div>
</template>
