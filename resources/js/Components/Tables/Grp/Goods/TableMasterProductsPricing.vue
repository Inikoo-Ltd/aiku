<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 26 Jul 2026 14:10:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, onUnmounted, ref, watch } from "vue"
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
import Modal from "@/Components/Utils/Modal.vue"
import PureMultiplePriceCurrency from "@/Components/Pure/PureMultiplePriceCurrency.vue"
import { useForm, router } from "@inertiajs/vue3"
import { faPencil } from "@fal"
library.add(faExclamationTriangle, faSpinnerThird, faPencil)

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
    effective_cost: string | number | null
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
    pricingCurrencies?: Record<string, any>
    masterProductCategoryId?: number
    bulkEditField?: 'master_prices' | 'master_rrps' | null
    pricingCostRates?: Record<string, number | null>
}>()

const emits = defineEmits<{
    (e: 'selectedRow', value: Record<string, boolean>): void
    (e: 'bulkEditHandled'): void
}>()

// Modal editing a row's prices or rrps with the same component as EditMasterProduct.
// In bulk mode the same values apply to every selected product (RRP entered per unit,
// scaled by each product's own units server side; independent entries never touched).
const editingProduct = ref<MasterProductPricing | null>(null)
const editingField = ref<'master_prices' | 'master_rrps'>('master_prices')
const editForm = ref<any>(null)
const bulkMode = ref(false)
const selectedIds = ref<Record<string, boolean>>({})

const compSelectedIds = computed(() =>
    Object.keys(selectedIds.value).filter(key => selectedIds.value[key]).map(Number)
)

// Cascade progress tracked at table level so it survives closing the modal
// (and several products can cascade at the same time, each on its own row)
const cascadeByAsset = ref<Record<number, { state: string, type?: string, done: number, total: number, doneAt?: string }>>({})
const subscribedAssetIds = ref<Set<number>>(new Set())

const subscribeCascade = (masterAssetId: number) => {
    if (!window.Echo) {
        return
    }

    window.Echo.private(`grp.master-asset.${masterAssetId}`)
        .listen('.prices-cascade-progress', (event: { state: string, type?: string, done: number, total: number }) => {
            cascadeByAsset.value[masterAssetId] = {
                ...event,
                doneAt: event.state === 'done' ? new Date().toLocaleString() : undefined,
            }
        })
    subscribedAssetIds.value.add(masterAssetId)
}

onUnmounted(() => {
    if (window.Echo) {
        subscribedAssetIds.value.forEach(masterAssetId => window.Echo.leave(`grp.master-asset.${masterAssetId}`))
    }
})

const cascadeFor = (masterProduct: MasterProductPricing, field: 'price' | 'rrp') => {
    const progress = cascadeByAsset.value[masterProduct.id]

    if (!progress || (progress.type && progress.type !== 'both' && progress.type !== field)) {
        return null
    }

    return progress
}

const openEdit = (masterProduct: MasterProductPricing, field: 'master_prices' | 'master_rrps') => {
    bulkMode.value = false
    editingProduct.value = masterProduct
    editingField.value = field
    editForm.value = useForm({
        [field]: JSON.parse(JSON.stringify(masterProduct[field] ?? {})),
    })
    subscribeCascade(masterProduct.id)
}

const bulkPrefilled = ref(false)
const bulkCounterpart = ref<Record<string, CurrencyValue> | null>(null)
const bulkEffectiveCost = ref<number | null>(null)

// Effective cost (group currency) converted to every currency via the page-provided rates
const costsFor = (effectiveCost: string | number | null): Record<string, number | null> | null => {
    const cost = Number(effectiveCost ?? 0)
    if (!cost || !props.pricingCostRates) {
        return null
    }

    return Object.fromEntries(
        Object.entries(props.pricingCostRates).map(([code, rate]) => [
            code,
            rate ? Math.round(cost * Number(rate) * 100) / 100 : null,
        ])
    )
}

// The page-head buttons trigger bulk editing from outside the table
watch(() => props.bulkEditField, (field) => {
    if (field) {
        openBulkEdit(field)
        emits('bulkEditHandled')
    }
})

const canonicalRecord = (record: Record<string, CurrencyValue> | undefined): string =>
    JSON.stringify(
        Object.keys(record ?? {}).sort().map(code => [code, Number(record![code]?.value ?? 0), !!record![code]?.independent])
    )

const openBulkEdit = (field: 'master_prices' | 'master_rrps') => {
    if (!compSelectedIds.value.length) {
        return
    }

    const rows = (((props.data as any)?.data ?? []) as MasterProductPricing[])
        .filter(row => compSelectedIds.value.includes(row.id))

    // When every selected product already shares the exact same values (and units,
    // for the per-unit rrp), prefill the form with them instead of starting blank
    const firstRecord = canonicalRecord(rows[0]?.[field])
    const allSame = rows.length > 0
        && rows.every(row => canonicalRecord(row[field]) === firstRecord)
        && (field !== 'master_rrps' || rows.every(row => Number(row.units) === Number(rows[0].units)))

    let prefill: Record<string, CurrencyValue> = {}
    if (allSame) {
        prefill = JSON.parse(JSON.stringify(rows[0][field] ?? {}))
        if (field === 'master_rrps' && Number(rows[0].units) > 0) {
            for (const code of Object.keys(prefill)) {
                if (prefill[code]?.value != null) {
                    prefill[code].value = Math.round(Number(prefill[code].value) / Number(rows[0].units) * 100) / 100
                }
            }
        }
    }

    // Margin impact needs the other field's values — only meaningful when they
    // are identical across the whole selection
    const otherField = field === 'master_prices' ? 'master_rrps' : 'master_prices'
    const firstOther = canonicalRecord(rows[0]?.[otherField])
    bulkCounterpart.value = rows.every(row => canonicalRecord(row[otherField]) === firstOther)
        ? (rows[0]?.[otherField] ?? null)
        : null

    bulkEffectiveCost.value = rows.length && rows.every(row => Number(row.effective_cost ?? 0) === Number(rows[0].effective_cost ?? 0))
        ? Number(rows[0].effective_cost ?? 0) || null
        : null

    bulkPrefilled.value = allSame
    bulkMode.value = true
    editingProduct.value = null
    editingField.value = field
    editForm.value = useForm({
        ids: compSelectedIds.value,
        rrp_per_unit: true,
        [field]: prefill,
    })
}

const closeEdit = () => {
    const closedAssetId = editingProduct.value?.id
    editingProduct.value = null
    editForm.value = null
    bulkMode.value = false

    // the modal component leaves the shared channel on unmount — resubscribe
    if (closedAssetId) {
        setTimeout(() => subscribeCascade(closedAssetId), 100)
    }
}

const submitEdit = () => {
    if (!editForm.value) {
        return
    }

    if (bulkMode.value) {
        const bulkIds: number[] = [...(editForm.value.ids ?? [])]

        editForm.value.patch(route('grp.models.master_asset.prices.bulk_update'), {
            preserveScroll: true,
            onSuccess: () => {
                bulkIds.forEach(masterAssetId => subscribeCascade(masterAssetId))
                closeEdit()
                router.reload({ only: ['pricing'] })
            },
        })

        return
    }

    if (!editingProduct.value) {
        return
    }

    editForm.value.patch(
        route('grp.models.master_asset.prices.update', { masterAsset: editingProduct.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['pricing'] })
            },
        }
    )
}

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

const priceMarginPct = (masterProduct: MasterProductPricing, code: string): string | null => {
    const cost  = costsFor(masterProduct.effective_cost)?.[code] ?? null
    const price = Number(masterProduct.master_prices?.[code]?.value ?? 0)

    if (!cost || !price) {
        return null
    }

    return Math.round(((price - cost) / price) * 100) + '%'
}

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
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="true"
        @onSelectRow="(items: Record<string, boolean>) => { selectedIds = items; emits('selectedRow', items) }">
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
            <div v-if="majorCurrencies?.length" class="grid grid-cols-[auto_auto_auto] justify-end gap-x-3 gap-y-0.5">
                <template v-for="(code, index) in majorCurrencies" :key="code">
                    <span class="flex items-center justify-end gap-x-1.5 self-center">
                        <template v-if="index === 0">
                            <span v-if="cascadeFor(masterProduct, 'price')" class="whitespace-nowrap text-xs tabular-nums" v-tooltip="cascadeFor(masterProduct, 'price')!.doneAt ? trans('Websites updated at :time', { time: cascadeFor(masterProduct, 'price')!.doneAt! }) : ''" :class="cascadeFor(masterProduct, 'price')!.state === 'done' ? 'text-green-600' : 'text-gray-500'">
                                <template v-if="cascadeFor(masterProduct, 'price')!.state !== 'done'">
                                    <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
                                    {{ cascadeFor(masterProduct, 'price')!.done }}/{{ cascadeFor(masterProduct, 'price')!.total }}
                                </template>
                                <template v-else>✓</template>
                            </span>
                            <button
                                type="button"
                                class="text-sm text-gray-400 hover:text-indigo-600"
                                v-tooltip="trans('Edit prices')"
                                @click="openEdit(masterProduct, 'master_prices')"
                            >
                                <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                            </button>
                        </template>
                    </span>
                    <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs effective cost')">{{ priceMarginPct(masterProduct, code) ?? '' }}</span>
                    <span class="tabular-nums text-right">{{ formatMoney(masterProduct.master_prices?.[code]?.value ?? null, code) }}</span>
                </template>
                <template v-for="code in independentMinors(masterProduct.master_prices)" :key="code">
                    <span />
                    <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs effective cost')">{{ priceMarginPct(masterProduct, code) ?? '' }}</span>
                    <span class="tabular-nums text-right text-green-600" v-tooltip="trans('Independent price')">
                        {{ formatMoney(masterProduct.master_prices?.[code]?.value ?? null, code) }}
                    </span>
                </template>
                <button
                    v-if="derivedMinors(masterProduct.master_prices).length"
                    type="button"
                    class="col-span-3 text-right text-xs text-gray-400 hover:text-gray-600"
                    @click="toggleMinors(`${masterProduct.id}-price`)"
                >
                    {{ trans('Minor currencies') }} ({{ derivedMinors(masterProduct.master_prices).length }})
                </button>
                <template v-if="expandedMinors[`${masterProduct.id}-price`]">
                    <template v-for="code in derivedMinors(masterProduct.master_prices)" :key="code">
                        <span />
                        <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs effective cost')">{{ priceMarginPct(masterProduct, code) ?? '' }}</span>
                        <span class="tabular-nums text-right text-gray-400">
                            {{ formatMoney(masterProduct.master_prices?.[code]?.value ?? null, code) }}
                        </span>
                    </template>
                </template>
            </div>
            <span v-else class="tabular-nums">{{ formatMoney(masterProduct.price, masterProduct.currency_code) }}</span>
        </template>

        <template #cell(rrp)="{ item: masterProduct }">
            <div v-if="majorCurrencies?.length" class="grid grid-cols-[auto_auto_auto] justify-end gap-x-3 gap-y-0.5">
                <template v-for="(code, index) in majorCurrencies" :key="code">
                    <span class="flex items-center justify-end gap-x-1.5 self-center">
                        <template v-if="index === 0">
                            <span v-if="cascadeFor(masterProduct, 'rrp')" class="whitespace-nowrap text-xs tabular-nums" v-tooltip="cascadeFor(masterProduct, 'rrp')!.doneAt ? trans('Websites updated at :time', { time: cascadeFor(masterProduct, 'rrp')!.doneAt! }) : ''" :class="cascadeFor(masterProduct, 'rrp')!.state === 'done' ? 'text-green-600' : 'text-gray-500'">
                                <template v-if="cascadeFor(masterProduct, 'rrp')!.state !== 'done'">
                                    <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
                                    {{ cascadeFor(masterProduct, 'rrp')!.done }}/{{ cascadeFor(masterProduct, 'rrp')!.total }}
                                </template>
                                <template v-else>✓</template>
                            </span>
                            <button
                                type="button"
                                class="text-sm text-gray-400 hover:text-indigo-600"
                                v-tooltip="trans('Edit RRPs')"
                                @click="openEdit(masterProduct, 'master_rrps')"
                            >
                                <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                            </button>
                        </template>
                    </span>
                    <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs price')">{{ marginPct(masterProduct, code) ?? '' }}</span>
                    <span class="tabular-nums text-right">{{ formatRrpPerUnit(masterProduct.master_rrps?.[code]?.value ?? null, code, masterProduct.units) }}</span>
                </template>
                <template v-for="code in independentMinors(masterProduct.master_rrps)" :key="code">
                    <span />
                    <span class="tabular-nums text-right text-xs self-center text-gray-400" v-tooltip="trans('Margin vs price')">{{ marginPct(masterProduct, code) ?? '' }}</span>
                    <span class="tabular-nums text-right text-green-600" v-tooltip="trans('Independent price')">
                        {{ formatRrpPerUnit(masterProduct.master_rrps?.[code]?.value ?? null, code, masterProduct.units) }}
                    </span>
                </template>
                <button
                    v-if="derivedMinors(masterProduct.master_rrps).length"
                    type="button"
                    class="col-span-3 text-right text-xs text-gray-400 hover:text-gray-600"
                    @click="toggleMinors(`${masterProduct.id}-rrp`)"
                >
                    {{ trans('Minor currencies') }} ({{ derivedMinors(masterProduct.master_rrps).length }})
                </button>
                <template v-if="expandedMinors[`${masterProduct.id}-rrp`]">
                    <template v-for="code in derivedMinors(masterProduct.master_rrps)" :key="code">
                        <span />
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

    <Modal :isOpen="!!editingProduct || bulkMode" @onClose="closeEdit" width="w-full max-w-xl">
        <div v-if="editForm">
            <div class="mb-3 text-sm font-medium text-gray-700">
                <template v-if="bulkMode">
                    {{ trans('Bulk edit') }} — {{ editingField === 'master_prices' ? `${trans('Price')} / ${trans('Outer')}` : `${trans('RRP')} / ${trans('Unit')}` }}
                    <span class="ml-1 font-normal text-gray-400">{{ trans(':n products', { n: `${editForm.ids?.length}` }) }}</span>
                </template>
                <template v-else-if="editingProduct">
                    {{ editingProduct.code }} — {{ editingField === 'master_prices' ? `${trans('Price')} / ${trans('Outer')}` : `${trans('RRP')} / ${trans('Unit')}` }}
                    <span class="ml-1 font-normal text-gray-400">{{ editingProduct.name }}</span>
                </template>
            </div>
            <div v-if="bulkMode && !bulkPrefilled" class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-700">
                {{ trans('Only currencies you fill will be applied; independent prices are never overwritten.') }}
                <template v-if="editingField === 'master_rrps'">{{ trans('RRP is entered per unit and scaled by each product\'s units.') }}</template>
            </div>
            <PureMultiplePriceCurrency
                v-model="editForm[editingField]"
                :currencies="pricingCurrencies ?? {}"
                :masterAsset="editingProduct?.id ?? 0"
                :type_input="editingField === 'master_prices' ? 'price' : 'rrp'"
                :perUnits="!bulkMode && editingField === 'master_rrps' ? editingProduct?.units : undefined"
                :form="editForm"
                :submitForm="submitEdit"
                :inputPlaceholder="bulkMode && !bulkPrefilled ? '' : undefined"
                :counterpartRecord="bulkMode
                    ? bulkCounterpart
                    : (editingField === 'master_prices' ? editingProduct?.master_rrps : editingProduct?.master_prices)"
                :costs="costsFor(bulkMode ? bulkEffectiveCost : editingProduct?.effective_cost ?? null)"
            />
        </div>
    </Modal>
</template>
