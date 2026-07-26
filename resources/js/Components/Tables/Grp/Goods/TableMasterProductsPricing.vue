<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 26 Jul 2026 14:10:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle } from "@fal"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { RouteParams } from "@/types/route-params"
import axios from "axios"
import Image from "@common/Components/Image.vue"
library.add(faExclamationTriangle, faSpinnerThird)

interface CurrencyValue {
    value: string | number | null
    independent?: boolean
}

interface MasterProductPricing {
    id: number
    slug: string
    code: string
    name: string
    units: number
    unit: string | null
    image_thumbnail: object | null
    trade_units_label: string | null
    price: string | number | null
    rrp: string | number | null
    currency_code: string
    units_review: string | null
    master_prices: Record<string, CurrencyValue>
    master_rrps: Record<string, CurrencyValue>
    used_in: number
    favourites: number
    price_rebels: number
    stock_min: number | null
    stock_max: number | null
    orgs_out_of_stock: number
    orgs_with_stock: number
    stock_by_org: { code: string, qty: number }[]
    sales: string | number | null
    sold: number
    customers: number
    sales_ly: string | number | null
}

interface SalesFigures {
    sales: string | number | null
    sold: number
    customers: number
    sales_ly: string | number | null
}

const props = defineProps<{
    data: object
    tab?: string
    majorCurrencies?: string[]
    masterProductCategoryId?: number
}>()

const majorSet = computed(() => new Set(props.majorCurrencies ?? []))

const independentMinors = (record: Record<string, CurrencyValue> | undefined) =>
    Object.entries(record ?? {})
        .filter(([code, entry]) => !majorSet.value.has(code) && entry?.independent)
        .map(([code]) => code)
        .sort()

const derivedMinors = (record: Record<string, CurrencyValue> | undefined) =>
    Object.entries(record ?? {})
        .filter(([code, entry]) => !majorSet.value.has(code) && !entry?.independent)
        .map(([code]) => code)
        .sort()

const expandedMinors = ref<Record<string, boolean>>({})

const toggleMinors = (key: string) => {
    expandedMinors.value[key] = !expandedMinors.value[key]
}

// Sales interval: last 12 months comes precomputed with the page, others fetched on demand
const salesInterval = ref<'month' | 'quarter' | 'year'>('year')
const salesOverrides = ref<Record<number, SalesFigures>>({})
const isLoadingSales = ref(false)

const intervalLabels: Record<string, string> = {
    month: trans('Last month'),
    quarter: trans('Last quarter'),
    year: trans('Last 12 months'),
}

const salesFor = (masterProduct: MasterProductPricing): SalesFigures =>
    salesInterval.value === 'year'
        ? masterProduct
        : (salesOverrides.value[masterProduct.id] ?? { sales: null, sold: 0, customers: 0, sales_ly: null })

const salesDelta = (figures: SalesFigures): number | null => {
    const current = Number(figures.sales ?? 0)
    const previous = Number(figures.sales_ly ?? 0)

    if (!previous) {
        return null
    }

    return Math.round(((current - previous) / previous) * 100)
}

const rowIds = computed(() => (((props.data as any)?.data ?? []) as MasterProductPricing[]).map(row => row.id))

const nextInterval: Record<string, 'month' | 'quarter' | 'year'> = {
    year: 'quarter',
    quarter: 'month',
    month: 'year',
}

const cycleInterval = async () => {
    const interval = nextInterval[salesInterval.value]

    if (interval === 'year') {
        salesInterval.value = interval

        return
    }

    isLoadingSales.value = true
    try {
        const { data } = await axios.post(
            route('grp.json.master_product_category.pricing_sales', {
                masterProductCategory: props.masterProductCategoryId,
            }),
            {
                interval: interval,
                ids: rowIds.value,
            }
        )
        salesOverrides.value = data ?? {}
        salesInterval.value = interval
    } finally {
        isLoadingSales.value = false
    }
}

const locale = inject("locale", aikuLocaleStructure)

const productRoute = (masterProduct: MasterProductPricing) => {
    return route("grp.masters.master_shops.show.master_products.show", {
        masterShop: (route().params as RouteParams).masterShop,
        masterProduct: masterProduct.slug,
    })
}

const formatMoney = (value: string | number | null, currencyCode: string) => {
    if (value == null) {
        return "-"
    }

    return locale.currencyFormat(currencyCode, Number(value))
}

// DB stores RRP per outer; the UI always shows RRP per unit
const formatRrpPerUnit = (value: string | number | null, currencyCode: string, units: number) => {
    if (value == null) {
        return "-"
    }

    return locale.currencyFormat(currencyCode, units > 0 ? Number(value) / units : Number(value))
}

const stockByOrgTooltip = (masterProduct: MasterProductPricing): string =>
    (masterProduct.stock_by_org ?? [])
        .map(orgStock => `${orgStock.code}: ${locale.number(Math.floor(Number(orgStock.qty)))}`)
        .join(' · ')

const marginPct = (masterProduct: MasterProductPricing, code: string): string | null => {
    const price = Number(masterProduct.master_prices?.[code]?.value ?? 0)
    const rrp   = Number(masterProduct.master_rrps?.[code]?.value ?? 0)

    if (!price || !rrp) {
        return null
    }

    return Math.round(((rrp - price) / rrp) * 100) + '%'
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: masterProduct }">
            <Link :href="productRoute(masterProduct)" class="primaryLink">
                {{ masterProduct.code }}
            </Link>
            <FontAwesomeIcon
                v-if="masterProduct.units_review"
                :icon="faExclamationTriangle"
                class="ml-1 text-amber-500"
                v-tooltip="trans('Units mismatch detected (:bucket) — per-unit prices may be wrong', { bucket: masterProduct.units_review })"
                fixed-width
                aria-hidden="true"
            />
        </template>

        <template #cell(name)="{ item: masterProduct }">
            <div class="flex items-start gap-x-2">
                <Image
                    v-if="masterProduct.image_thumbnail"
                    :src="masterProduct.image_thumbnail"
                    class="mt-0.5 w-9 aspect-square shrink-0 rounded overflow-hidden shadow"
                />
                <div class="flex flex-col gap-y-0.5">
                <span class="font-medium">
                    {{ masterProduct.name }}
                    <span class="ml-1 text-xs font-normal text-gray-400">{{ trans('In :n shops', { n: `${masterProduct.used_in ?? 0}` }) }}</span>
                    <span
                        v-if="masterProduct.trade_units_label"
                        class="ml-1 whitespace-nowrap rounded border border-emerald-300 px-1 py-px text-xs font-normal text-emerald-700 tabular-nums"
                        v-tooltip="trans('Trade units')"
                    >
                        {{ masterProduct.trade_units_label }}
                        <span class="text-gray-600">| {{ locale.number(masterProduct.units) }} {{ masterProduct.unit }}</span>
                    </span>
                </span>

                <span class="text-xs text-gray-500 tabular-nums">
                    <button
                        type="button"
                        class="underline decoration-dotted underline-offset-2 hover:text-gray-700"
                        v-tooltip="trans('Click to change interval')"
                        :disabled="isLoadingSales"
                        @click="cycleInterval"
                    >{{ intervalLabels[salesInterval] }}</button>:
                    <template v-if="isLoadingSales">
                        <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
                    </template>
                    <template v-else>
                        <span class="font-medium text-gray-700">{{ formatMoney(salesFor(masterProduct).sales ?? 0, masterProduct.currency_code) }}</span>
                        · {{ locale.number(salesFor(masterProduct).sold ?? 0) }} {{ trans('sold') }}
                        · {{ locale.number(salesFor(masterProduct).customers ?? 0) }} {{ trans('customers') }}
                        <span
                            v-if="salesDelta(salesFor(masterProduct)) !== null"
                            class="ml-1 font-medium"
                            :class="salesDelta(salesFor(masterProduct))! >= 0 ? 'text-green-600' : 'text-red-600'"
                            v-tooltip="trans('vs same period a year earlier')"
                        >
                            {{ salesDelta(salesFor(masterProduct))! >= 0 ? '+' : '' }}{{ salesDelta(salesFor(masterProduct)) }}%
                            {{ salesDelta(salesFor(masterProduct))! >= 0 ? '▲' : '▼' }}
                        </span>
                    </template>
                </span>

                <span class="text-xs text-gray-500 tabular-nums">
                    <span v-if="masterProduct.orgs_with_stock" v-tooltip="stockByOrgTooltip(masterProduct)">
                        {{ trans('Stock') }}
                        <span class="font-medium text-gray-700">
                            <template v-if="masterProduct.stock_min === masterProduct.stock_max">{{ locale.number(masterProduct.stock_min ?? 0) }}</template>
                            <template v-else>{{ locale.number(masterProduct.stock_min ?? 0) }}–{{ locale.number(masterProduct.stock_max ?? 0) }}</template>
                        </span>
                    </span>
                    <span v-if="masterProduct.orgs_out_of_stock" class="font-medium text-red-600" v-tooltip="stockByOrgTooltip(masterProduct)">
                        <template v-if="masterProduct.orgs_with_stock">· </template>{{ trans(':n Org no stock', { n: `${masterProduct.orgs_out_of_stock}` }) }}
                    </span>
                    <template v-if="masterProduct.favourites">
                        · ❤ {{ locale.number(masterProduct.favourites) }}
                    </template>
                    <span v-if="masterProduct.price_rebels" class="text-amber-600">
                        · {{ masterProduct.price_rebels }} {{ trans('price rebels') }}
                    </span>
                </span>
                </div>
            </div>
        </template>

        <template #cell(price)="{ item: masterProduct }">
            <div v-if="majorCurrencies?.length" class="flex flex-col items-end gap-y-0.5">
                <span v-for="code in majorCurrencies" :key="code" class="tabular-nums">
                    {{ formatMoney(masterProduct.master_prices?.[code]?.value ?? null, code) }}
                </span>
                <span
                    v-for="code in independentMinors(masterProduct.master_prices)"
                    :key="code"
                    class="tabular-nums text-green-600"
                    v-tooltip="trans('Independent price')"
                >
                    {{ formatMoney(masterProduct.master_prices?.[code]?.value ?? null, code) }}
                </span>
                <button
                    v-if="derivedMinors(masterProduct.master_prices).length"
                    type="button"
                    class="text-xs text-gray-400 hover:text-gray-600"
                    @click="toggleMinors(`${masterProduct.id}-price`)"
                >
                    {{ trans('Minor currencies') }} ({{ derivedMinors(masterProduct.master_prices).length }})
                </button>
                <template v-if="expandedMinors[`${masterProduct.id}-price`]">
                    <span
                        v-for="code in derivedMinors(masterProduct.master_prices)"
                        :key="code"
                        class="tabular-nums text-gray-400"
                    >
                        {{ formatMoney(masterProduct.master_prices?.[code]?.value ?? null, code) }}
                    </span>
                </template>
            </div>
            <span v-else class="tabular-nums">{{ formatMoney(masterProduct.price, masterProduct.currency_code) }}</span>
        </template>

        <template #cell(rrp)="{ item: masterProduct }">
            <div v-if="majorCurrencies?.length" class="grid grid-cols-[auto_auto] justify-end gap-x-3 gap-y-0.5">
                <template v-for="code in majorCurrencies" :key="code">
                    <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs price')">{{ marginPct(masterProduct, code) ?? '' }}</span>
                    <span class="tabular-nums text-right">{{ formatRrpPerUnit(masterProduct.master_rrps?.[code]?.value ?? null, code, masterProduct.units) }}</span>
                </template>
                <template v-for="code in independentMinors(masterProduct.master_rrps)" :key="code">
                    <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs price')">{{ marginPct(masterProduct, code) ?? '' }}</span>
                    <span class="tabular-nums text-right text-green-600" v-tooltip="trans('Independent price')">
                        {{ formatRrpPerUnit(masterProduct.master_rrps?.[code]?.value ?? null, code, masterProduct.units) }}
                    </span>
                </template>
                <button
                    v-if="derivedMinors(masterProduct.master_rrps).length"
                    type="button"
                    class="col-span-2 text-right text-xs text-gray-400 hover:text-gray-600"
                    @click="toggleMinors(`${masterProduct.id}-rrp`)"
                >
                    {{ trans('Minor currencies') }} ({{ derivedMinors(masterProduct.master_rrps).length }})
                </button>
                <template v-if="expandedMinors[`${masterProduct.id}-rrp`]">
                    <template v-for="code in derivedMinors(masterProduct.master_rrps)" :key="code">
                        <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs price')">{{ marginPct(masterProduct, code) ?? '' }}</span>
                        <span class="tabular-nums text-right text-gray-400">
                            {{ formatRrpPerUnit(masterProduct.master_rrps?.[code]?.value ?? null, code, masterProduct.units) }}
                        </span>
                    </template>
                </template>
            </div>
            <span v-else class="tabular-nums">{{ formatMoney(masterProduct.rrp, masterProduct.currency_code) }}</span>
        </template>
    </Table>
</template>
