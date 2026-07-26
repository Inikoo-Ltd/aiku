<script setup lang='ts'>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faChevronDown, faExclamationTriangle, faSave as falSave , faStarfighter} from '@fal'
import { faSave as fadSave, faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import PriceCurrencyRow from '@/Components/Pure/Supports/PriceCurrencyRow.vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import axios from 'axios'
library.add(faCheck, faChevronDown, faExclamationTriangle, falSave, fadSave, faSpinnerThird)

interface CurrencyRate {
    currency: string
    currency_symbol?: string
    currency_id: number
    ratio_eur: number | null
    is_major?: boolean
    major?: string | null
    fraction_digits?: number | null
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
    units?: number
}

const props = defineProps<{
    modelValue: Record<string, CurrencyPrice> | null
    currencies: Record<string, CurrencyRate>
    readonly?: boolean
    visibleCurrencyCodes?: string[]
    masterAsset: number | string
    type_input : string
    unitsReview?: {
        master: string | null
        products: Record<string, string>
    } | null
    perUnits?: number
    form?: any
    submitForm?: () => void
}>()

// DB stores values per outer; when perUnits is set the user edits per unit,
// so values are divided for display and multiplied back on save.
const toDisplay = (value: number | null): number | null => {
    if (value == null || !props.perUnits || props.perUnits <= 0) {
        return value
    }

    return Math.round(value / props.perUnits * 100) / 100
}

const toStored = (value: number | null): number | null => {
    if (value == null || !props.perUnits || props.perUnits <= 0) {
        return value
    }

    return Math.round(value * props.perUnits * 100) / 100
}

const unitsReviewSummary = computed(() => {
    if (!props.unitsReview) {
        return null
    }

    const parts: string[] = []
    if (props.unitsReview.master) {
        parts.push(`master: ${props.unitsReview.master}`)
    }
    for (const [shopCode, bucket] of Object.entries(props.unitsReview.products ?? {})) {
        parts.push(`${shopCode}: ${bucket}`)
    }

    return parts.join(', ')
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: Record<string, CurrencyPrice>): void
}>()

const currencyList = computed(
    () => Object.values(props.currencies ?? {}).map(rate => ({
        code: rate.currency,
        symbol: rate.currency_symbol,
        fraction_digits: rate.fraction_digits ?? null,
        ratio_eur: rate.ratio_eur,
        is_major: rate.is_major ?? false,
        major: rate.major ?? null
    }))
)

// Major currencies come from the master shop's own price_exchanges config
// (see GetMasterShopCurrenciesRate). They're entered independently, never derived.
const majorCurrencyCodes = computed(
    () => currencyList.value.filter(currency => currency.is_major).map(currency => currency.code)
)

// The base is the major the minors actually follow, so edits to it recalculate
// them live; other majors are edited independently and drive nothing.
const baseCurrencyCode = computed(() => {
    const followCounts: Record<string, number> = {}
    currencyList.value.forEach(currency => {
        if (currency.major) {
            followCounts[currency.major] = (followCounts[currency.major] ?? 0) + 1
        }
    })

    const mostFollowed = Object.entries(followCounts).sort((a, b) => b[1] - a[1])[0]?.[0]

    return (mostFollowed && majorCurrencyCodes.value.includes(mostFollowed))
        ? mostFollowed
        : (majorCurrencyCodes.value[0] ?? currencyList.value[0]?.code)
})

const alwaysIndependentCurrencyCodes = computed(
    () => majorCurrencyCodes.value.filter(code => code !== baseCurrencyCode.value)
)

const isAlwaysIndependent = (code: string) => alwaysIndependentCurrencyCodes.value.includes(code)

const effectiveVisibleCurrencyCodes = computed(
    () => props.visibleCurrencyCodes ?? majorCurrencyCodes.value
)

const visibleCurrencies = computed(
    () => currencyList.value.filter(currency => effectiveVisibleCurrencyCodes.value.includes(currency.code))
)

const baseCurrency = computed(
    () => currencyList.value.find(currency => currency.code === baseCurrencyCode.value)
)

const derivedVisibleCurrencies = computed(
    () => visibleCurrencies.value.filter(currency => currency.code !== baseCurrencyCode.value)
)

const minorCurrencies = computed(
    () => currencyList.value.filter(currency => !effectiveVisibleCurrencyCodes.value.includes(currency.code))
)

// A minor currency the user has manually detached from the ratio is now their
// responsibility to maintain, same as a major — show it as its own block
// instead of burying it inside the collapsed "Minor currencies" list.
const independentMinorCurrencies = computed(
    () => minorCurrencies.value.filter(currency => prices.value[currency.code]?.independent)
)

const hiddenCurrencies = computed(
    () => minorCurrencies.value.filter(currency => !prices.value[currency.code]?.independent)
)

const buildPrices = (): Record<string, CurrencyPrice> => {
    return currencyList.value.reduce((prices, currency) => {
        const existing = props.modelValue?.[currency.code]

        prices[currency.code] = {
            value: toDisplay(existing?.value != null ? Number(existing.value) : null),
            independent: isAlwaysIndependent(currency.code) ? true : (existing?.independent ?? false)
        }

        return prices
    }, {} as Record<string, CurrencyPrice>)
}

const prices = ref<Record<string, CurrencyPrice>>(buildPrices())

const snapshotPrices = (source: Record<string, CurrencyPrice>): Record<string, CurrencyPrice> =>
    Object.fromEntries(Object.entries(source).map(([code, entry]) => [code, { ...entry }]))

const originalPrices = ref<Record<string, CurrencyPrice>>(snapshotPrices(prices.value))

watch(() => props.currencies, () => {
    prices.value = buildPrices()
    originalPrices.value = snapshotPrices(prices.value)
})

const isDirty = (code: string) => {
    const original = originalPrices.value[code]
    const current  = prices.value[code]

    return !!current && !!original
        && (current.value !== original.value || current.independent !== original.independent)
}

const hasDirtyHiddenCurrency = computed(
    () => hiddenCurrencies.value.some(currency => isDirty(currency.code))
)

const showHiddenCurrencies = ref(false)

// Hovering a derived minor highlights the major it follows; hovering a major
// highlights it plus every minor following it (arrows + major's dot/code).
// A hovered major only lights up when at least one follower is on screen.
const hoveredMinorCode = ref<string | null>(null)
const hoveredMajorCode = ref<string | null>(null)

const majorHasVisibleFollowers = (code: string) => {
    return currencyList.value.some(currency =>
        currency.major === code
        && !prices.value[currency.code]?.independent
        && (effectiveVisibleCurrencyCodes.value.includes(currency.code) || showHiddenCurrencies.value)
    )
}

const highlightedMajorCode = computed(() => {
    const followedMajor = currencyList.value.find(currency => currency.code === hoveredMinorCode.value)?.major
    if (followedMajor) {
        return followedMajor
    }

    return hoveredMajorCode.value && majorHasVisibleFollowers(hoveredMajorCode.value)
        ? hoveredMajorCode.value
        : null
})

const isHighlightedArrow = (code: string) => {
    if (hoveredMinorCode.value === code) {
        return true
    }

    const major = currencyList.value.find(currency => currency.code === code)?.major

    return major != null && major === hoveredMajorCode.value
}

const onRowHover = (currency: { code: string, is_major: boolean }) => {
    if (currency.is_major) {
        hoveredMajorCode.value = currency.code
    } else if (!prices.value[currency.code]?.independent) {
        hoveredMinorCode.value = currency.code
    }
}

const clearRowHover = () => {
    hoveredMinorCode.value = null
    hoveredMajorCode.value = null
}

// Only the followers of the edited currency get recalculated — editing a
// major nobody follows (or a minor) must leave every other value untouched.
const recalculateDerivedPrices = (changedCode: string) => {
    currencyList.value.forEach(currency => {
        const entry = prices.value[currency.code]
        const ratio = currency.ratio_eur

        if (entry.independent || currency.major !== changedCode) {
            return
        }

        const majorPrice = prices.value[changedCode]?.value

        entry.value = majorPrice == null || ratio == null
            ? null
            : Math.round(majorPrice * ratio * 100) / 100
    })
}

const onUpdate = (changedCode: string) => {
    recalculateDerivedPrices(changedCode)
    emits('update:modelValue', Object.fromEntries(
        Object.entries(prices.value).map(([code, entry]) => [code, {
            value: toStored(entry.value),
            // majors are forced independent for display only — persist the stored
            // flag so a future major→minor demotion still makes it a follower
            independent: isAlwaysIndependent(code)
                ? (props.modelValue?.[code]?.independent ?? false)
                : entry.independent
        }])
    ))
}

const priceRebels = ref<Record<string, PriceRebel>>({})

const priceRebelsList = computed(() => Object.values(priceRebels.value))

const savingRebelIds = ref<Record<number, boolean>>({})

const originalRebelValues = ref<Record<number, number | null>>({})

const isRebelEdited = (rebel: PriceRebel) => {
    return rebel.value !== originalRebelValues.value[rebel.shop_id]
}

// Cascade progress broadcast by CascadeMasterAssetPricesToChildren after a save:
// "n/total products updated" while running, then "Website updated".
const cascadeProgress = ref<{ state: string, done: number, total: number } | null>(null)

onMounted(() => {
    if (window.Echo) {
        window.Echo.private(`grp.master-asset.${props.masterAsset}`)
            .listen('.prices-cascade-progress', (event: { state: string, type?: string, done: number, total: number }) => {
                if (event.type && event.type !== 'both' && event.type !== props.type_input) {
                    return
                }
                cascadeProgress.value = event
            })
    }
})

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave(`grp.master-asset.${props.masterAsset}`)
    }
})

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
        if (props.perUnits) {
            for (const rebel of Object.values(priceRebels.value)) {
                if (rebel.value != null && rebel.units && rebel.units > 0) {
                    rebel.value = Math.round(Number(rebel.value) / rebel.units * 100) / 100
                }
            }
        }
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

    const storedValue = props.perUnits && rebel.value != null && rebel.units && rebel.units > 0
        ? Math.round(rebel.value * rebel.units * 100) / 100
        : rebel.value

    try {
        await axios.patch(
            route('grp.models.product.update', {
                product : rebel.id
            }),
            {
                [ props.type_input ]: storedValue
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
        <div
            class="flex h-5 items-center justify-end gap-x-1.5 pr-11 text-xs"
            :class="cascadeProgress?.state === 'done' && !form?.processing ? 'text-green-600' : 'text-gray-500'"
        >
            <template v-if="form?.processing">
                <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
            </template>
            <template v-else-if="cascadeProgress">
                <FontAwesomeIcon
                    v-if="cascadeProgress.state !== 'done'"
                    icon="fad fa-spinner-third"
                    class="animate-spin"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon v-else :icon="faCheck" fixed-width aria-hidden="true" />
                <span v-if="cascadeProgress.state !== 'done'">
                    {{ cascadeProgress.done }}/{{ cascadeProgress.total }} {{ ctrans('products updated') }}
                </span>
                <span v-else-if="cascadeProgress.total > 1">
                    {{ ctrans('Websites updated') }} ({{ cascadeProgress.total }} {{ ctrans('products') }})
                </span>
                <span v-else>
                    {{ ctrans('Website updated') }}
                </span>
            </template>
        </div>

        <div
            v-if="unitsReviewSummary"
            class="mb-2 flex items-center gap-x-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm text-amber-700"
        >
            <FontAwesomeIcon
                :icon="faExclamationTriangle"
                class="shrink-0"
                fixed-width
                v-tooltip="unitsReviewSummary"
                aria-hidden="true"
            />
            {{ ctrans('Units mismatch detected — per-unit prices may be wrong, review units before editing') }}
        </div>

        <div
            v-if="baseCurrency"
            class="relative py-1 pl-8"
            @mouseenter="onRowHover(baseCurrency)"
            @mouseleave="clearRowHover"
        >
            <span class="absolute bottom-0 left-3 top-1/2 w-px bg-gray-200" aria-hidden="true" />
            <span
                class="absolute left-2 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full transition-colors"
                :class="highlightedMajorCode === baseCurrency.code ? 'bg-sky-400' : 'bg-gray-300'"
                v-tooltip="'Base Ratio'"
                aria-hidden="true"
            />

            <PriceCurrencyRow
                v-model="prices[baseCurrency.code]"
                :dirty="isDirty(baseCurrency.code)"
                :currency="baseCurrency"
                :readonly="readonly"
                required
                :highlighted="highlightedMajorCode === baseCurrency.code"
                @change="onUpdate(baseCurrency.code)"
            >
                <template v-if="form && submitForm" #action>
                    <button
                        type="button"
                        class="w-full text-center disabled:cursor-not-allowed"
                        :disabled="form.processing || !form.isDirty"
                        v-tooltip="ctrans('Save')"
                        @click="submitForm()"
                    >
                        <FontAwesomeIcon
                            v-if="form.processing"
                            icon="fad fa-spinner-third"
                            class="animate-spin text-xl"
                            fixed-width
                            aria-hidden="true"
                        />
                        <FontAwesomeIcon
                            v-else
                            icon="fad fa-save"
                            class="text-xl"
                            :class="{ 'text-gray-300': !form.isDirty }"
                            :style="form.isDirty ? { '--fa-secondary-color': 'rgb(0, 255, 4)' } : undefined"
                            fixed-width
                            aria-hidden="true"
                        />
                    </button>
                </template>
            </PriceCurrencyRow>
        </div>

        <div
            v-for="currency in derivedVisibleCurrencies"
            :key="currency.code"
            class="relative py-1 pl-8"
            @mouseenter="onRowHover(currency)"
            @mouseleave="clearRowHover"
        >
            <span class="absolute inset-y-0 left-3 w-px bg-gray-200" aria-hidden="true" />
            <template v-if="!prices[currency.code].independent">
                <span
                    class="absolute left-3 top-1/2 h-px w-3 transition-colors"
                    :class="isHighlightedArrow(currency.code) ? 'bg-sky-400' : 'bg-gray-200'"
                    aria-hidden="true"
                />
                <span
                    class="absolute left-[1.25rem] top-1/2 h-0 w-0 -translate-y-1/2 border-y-[3px] border-l-[4px] border-y-transparent transition-colors"
                    :class="isHighlightedArrow(currency.code) ? 'border-l-sky-400' : 'border-l-gray-300'"
                    aria-hidden="true"
                />
            </template>

            <PriceCurrencyRow
                v-model="prices[currency.code]"
                :dirty="isDirty(currency.code)"
                :currency="currency"
                :readonly="readonly"
                :disabled="!prices[currency.code].independent"
                required
                :showIndependent="!isAlwaysIndependent(currency.code)"
                :highlighted="highlightedMajorCode === currency.code"
                @change="onUpdate(currency.code)"
            />
        </div>

        <div v-if="independentMinorCurrencies.length" class="relative py-1 pl-8">
            <span class="absolute inset-y-0 left-3 w-px bg-gray-200" aria-hidden="true" />

            <div
                v-for="currency in independentMinorCurrencies"
                :key="currency.code"
                class="relative py-1"
            >
                <PriceCurrencyRow
                    v-model="prices[currency.code]"
                    :dirty="isDirty(currency.code)"
                    :currency="currency"
                    :readonly="readonly"
                    :showIndependent="true"
                    @change="onUpdate(currency.code)"
                />
            </div>
        </div>

        <div v-if="hiddenCurrencies.length">
            <div class="relative py-1 pl-8">
                <span class="absolute inset-y-0 left-3 w-px bg-gray-200" aria-hidden="true" />
                <template v-if="!showHiddenCurrencies">
                    <span class="absolute left-3 top-1/2 h-px w-3 bg-gray-200" aria-hidden="true" />
                    <span
                        class="absolute left-[1.25rem] top-1/2 h-0 w-0 -translate-y-1/2 border-y-[3px] border-l-[4px] border-y-transparent border-l-gray-300"
                        aria-hidden="true"
                    />
                </template>


                <button
                    type="button"
                    class="flex w-full items-center gap-x-2 py-1 text-sm text-gray-500 hover:text-gray-700"
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
                    {{ ctrans('Minor currencies') }}
                    <span class="text-gray-400">({{ hiddenCurrencies.length }})</span>
                    <span
                        v-if="!showHiddenCurrencies && hasDirtyHiddenCurrency"
                        v-tooltip="ctrans('Unsaved changes inside')"
                        class="h-2 w-2 rounded-full bg-amber-400"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <div v-if="showHiddenCurrencies">
                <div
                    v-for="(currency, index) in hiddenCurrencies"
                    :key="currency.code"
                    class="relative py-1 pl-8"
                    @mouseenter="onRowHover(currency)"
                    @mouseleave="clearRowHover"
                >
                    <span
                        class="absolute left-3 top-0 w-px bg-gray-200"
                        :class="index === hiddenCurrencies.length - 1 ? 'h-1/2' : 'bottom-0'"
                        aria-hidden="true"
                    />
                    <template v-if="!prices[currency.code].independent">
                        <span
                            class="absolute left-3 top-1/2 h-px w-3 transition-colors"
                            :class="isHighlightedArrow(currency.code) ? 'bg-sky-400' : 'bg-gray-200'"
                            aria-hidden="true"
                        />
                        <span
                            class="absolute left-[1.25rem] top-1/2 h-0 w-0 -translate-y-1/2 border-y-[3px] border-l-[4px] border-y-transparent transition-colors"
                            :class="isHighlightedArrow(currency.code) ? 'border-l-sky-400' : 'border-l-gray-300'"
                            aria-hidden="true"
                        />
                    </template>

                    <PriceCurrencyRow
                        v-model="prices[currency.code]"
                        :dirty="isDirty(currency.code)"
                        :currency="currency"
                        :readonly="readonly"
                        :disabled="!prices[currency.code].independent"
                        :showIndependent="!isAlwaysIndependent(currency.code)"
                        @change="onUpdate(currency.code)"
                    />
                </div>
            </div>
        </div>

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
