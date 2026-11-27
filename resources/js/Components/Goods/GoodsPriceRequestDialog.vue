<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-17 10:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import Checkbox from "primevue/checkbox"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faPen, faTags, faExternalLink, faLayerGroup, faCube, faInfoCircle, faIndustry } from "@fal"
import { faStar } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import ProductDialogHeader from "@/Components/Product/ProductDialogHeader.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Answer = "accepted" | "rejected" | "adjusted"

interface PriceRequest {
    id: number
    organisation: string
    purchase_order: { reference: string; state: string; url: string }
    agent: string | null
    supplier: string | null
    code: string
    units_per_carton: number
    quantity_ordered: number
    currency: string | null
    unit_cost: number
    asked_unit_cost: number
    previous_unit_cost: number | null
    note: string | null
    asked_by: string | null
    asked_at: string | null
    master_costs_at_new_price: Record<number, number>
    can_update_supplier_cost: boolean
    answer_route: routeType | null
}

interface SupplierPriceChange {
    id: number
    state: string
    supplier: string | null
    supplier_code: string | null
    code: string | null
    currency: string | null
    cost: number | null
    proposed_cost: number
    units_per_carton: number
    minimum_carton_order: number | null
    proposed_moq: number | null
    effective_from: string | null
    effective_until: string | null
    reason: string | null
    submitted_at: string | null
    url: string
    master_costs_at_new_price: Record<number, number>
    open_purchase_order_lines: OpenPurchaseOrderLine[]
    decide_route: routeType | null
}

interface OpenPurchaseOrderLine {
    id: number
    organisation: string
    reference: string
    state: string
    url: string
    quantity_ordered: number
    unit_cost: number
}

type SupplierDecision = "accepted" | "not_proceeding"

interface MasterProduct {
    id: number
    code: string
    name: string | null
    master_shop: string | null
    units: number
    stocks_in_product: number
    stock_quantity: number
    cost: number | null
    cost_at_new_price: number | null
    cost_currency: string
    main_currencies: string[]
    following_currencies: { currency: string; major: string; exchange: number }[]
    other_currencies: string[]
    cost_exchanges: Record<string, number | null>
    prices: Record<string, number | null>
    rrps: Record<string, number | null>
    url: string | null
    master_family: { code: string; pricing_url: string } | null
    update_route: routeType
}

interface NewPrice {
    state: "pending" | "accepted" | "adjusted"
    organisation: string
    purchase_order: string
    currency: string | null
    unit_cost: number
    previous_unit_cost: number
}

interface PriceRequestData {
    code: string
    name: string | null
    image: object | null
    requests: PriceRequest[]
    supplier_price_changes: SupplierPriceChange[]
    new_price: NewPrice | null
    master_products: MasterProduct[]
    can_edit_master_prices: boolean
}

const props = defineProps<{
    stockSlug: string | null
    reloadProps: string[]
}>()

const visible = defineModel<boolean>("visible", { required: true })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const data = ref<PriceRequestData | null>(null)
const isLoading = ref(false)
const loadError = ref<string | null>(null)

const adjustingId = ref<number | null>(null)
const adjustedCosts = reactive<Record<number, number | null>>({})
const updateSupplierCost = reactive<Record<number, boolean>>({})
const answering = ref<{ id: number; answer: Answer } | null>(null)
const requestErrors = reactive<Record<number, string | null>>({})

const rejectingSupplierChangeId = ref<number | null>(null)
const supplierRejectFeedback = reactive<Record<number, string>>({})
const decidingSupplierChange = ref<{ id: number; decision: SupplierDecision } | null>(null)
const supplierChangeErrors = reactive<Record<number, string | null>>({})
const updateOpenPurchaseOrders = reactive<Record<number, boolean>>({})

interface AcceptedPrice {
    currency: string | null
    old: number
    new: number
}

const acceptedPrice = ref<AcceptedPrice | null>(null)
const suggestedPrices = reactive<Record<number, Record<string, { old: number; new: number }>>>({})

function acceptedRatio(): number | null {
    return acceptedPrice.value && acceptedPrice.value.old > 0 ? acceptedPrice.value.new / acceptedPrice.value.old : null
}

type PriceSuggestions = Record<number, Record<string, { old: number; new: number }>>

function priceSuggestions(costsAtNewPrice: Record<number, number | null>): PriceSuggestions {
    const result: PriceSuggestions = {}
    for (const masterProduct of data.value?.master_products ?? []) {
        const costAtNewPrice = costsAtNewPrice[masterProduct.id]
        if (!masterProduct.cost || !costAtNewPrice || costAtNewPrice === masterProduct.cost) {
            continue
        }

        const factor = costAtNewPrice / masterProduct.cost
        const suggestions: Record<string, { old: number; new: number }> = {}
        for (const currency of masterProduct.main_currencies) {
            const oldPrice = masterProduct.prices[currency]
            if (!oldPrice) {
                continue
            }

            const newPrice = Math.round(oldPrice * factor * 100) / 100
            if (newPrice !== oldPrice) {
                suggestions[currency] = { old: oldPrice, new: newPrice }
            }
        }

        if (Object.keys(suggestions).length) {
            result[masterProduct.id] = suggestions
        }
    }

    return result
}

function suggestMasterPrices(): void {
    const costsAtNewPrice = Object.fromEntries((data.value?.master_products ?? []).map((masterProduct) => [masterProduct.id, masterProduct.cost_at_new_price]))
    Object.assign(suggestedPrices, priceSuggestions(costsAtNewPrice))
}

const alsoSaveSellingPrices = reactive<Record<string, boolean>>({})

function previewFor(costsAtNewPrice: Record<number, number>): { masterProduct: MasterProduct; currencies: { currency: string; old: number; new: number }[] }[] {
    if (!data.value?.can_edit_master_prices) {
        return []
    }

    const suggestions = priceSuggestions(costsAtNewPrice)

    return (data.value.master_products ?? [])
        .filter((masterProduct) => suggestions[masterProduct.id])
        .map((masterProduct) => ({
            masterProduct,
            currencies: Object.entries(suggestions[masterProduct.id]).map(([currency, suggestion]) => ({ currency, ...suggestion })),
        }))
}

async function acceptAndMaybeSave(price: AcceptedPrice, saveKey: string): Promise<void> {
    await acceptPrice(price)
    if (alsoSaveSellingPrices[saveKey] && suggestionCount.value) {
        await saveAllSuggested()
    }
}

function fillSuggestedPrices(): void {
    for (const [masterProductId, suggestions] of Object.entries(suggestedPrices)) {
        const form = masterForms[Number(masterProductId)]
        if (!form) {
            continue
        }

        for (const [currency, suggestion] of Object.entries(suggestions)) {
            form.prices[currency] = suggestion.new
        }
    }
}

async function acceptPrice(price: AcceptedPrice): Promise<void> {
    acceptedPrice.value = price
    for (const masterProductId of Object.keys(suggestedPrices)) {
        delete suggestedPrices[Number(masterProductId)]
    }

    await fetchData()
    if (data.value?.can_edit_master_prices) {
        suggestMasterPrices()
        fillSuggestedPrices()
    }
}

function hasSuggestion(masterProduct: MasterProduct, currency?: string): boolean {
    const suggestions = suggestedPrices[masterProduct.id]

    return !!suggestions && (currency === undefined || !!suggestions[currency])
}

const suggestionCount = computed(() => Object.keys(suggestedPrices).length)

const acceptedPriceSummary = computed(() => {
    const price = acceptedPrice.value
    if (!price) {
        return null
    }

    const ratio = acceptedRatio()
    const percent = ratio === null ? null : Math.round((ratio - 1) * 1000) / 10

    return ctrans("You accepted :new per unit, was :old (:percent).", {
        new: unitCost(price.currency, price.new),
        old: unitCost(price.currency, price.old),
        percent: percent === null ? "—" : `${percent > 0 ? "+" : ""}${percent}%`,
    })
})

const masterForms = reactive<Record<number, { prices: Record<string, number | null>; rrps: Record<string, number | null> }>>({})
const savingMasterId = ref<number | null>(null)
const masterErrors = reactive<Record<number, string | null>>({})
const savedMasterId = ref<number | null>(null)
const copyRrps = reactive<Record<number, boolean>>({})

function rrpsPerUnit(masterProduct: MasterProduct): Record<string, number | null> {
    const units = masterProduct.units || 1
    return Object.fromEntries(Object.entries(masterProduct.rrps).map(([currency, rrp]) => [currency, rrp === null ? null : rrp / units]))
}

function rrpsPerOuter(rrps: Record<string, number>, units: number): Record<string, number> {
    return Object.fromEntries(Object.entries(rrps).map(([currency, rrp]) => [currency, Math.round(rrp * (units || 1) * 100) / 100]))
}

async function fetchData(): Promise<void> {
    if (!props.stockSlug) {
        return
    }

    isLoading.value = true
    loadError.value = null
    try {
        const ratio = acceptedRatio()
        const response = await axios.get(route("grp.goods.products.price_requests", { stock: props.stockSlug }), { params: ratio === null ? {} : { price_ratio: ratio } })
        data.value = response.data
        for (const masterProduct of data.value?.master_products ?? []) {
            masterForms[masterProduct.id] = { prices: { ...masterProduct.prices }, rrps: rrpsPerUnit(masterProduct) }
        }
        fillSuggestedPrices()
        for (const change of data.value?.supplier_price_changes ?? []) {
            updateOpenPurchaseOrders[change.id] ??= change.open_purchase_order_lines.length > 0
            alsoSaveSellingPrices[`supplier-${change.id}`] ??= true
        }
        for (const request of data.value?.requests ?? []) {
            alsoSaveSellingPrices[`agent-${request.id}`] ??= true
        }
        for (const request of data.value?.requests ?? []) {
            updateSupplierCost[request.id] ??= request.can_update_supplier_cost
        }
    } catch {
        loadError.value = ctrans("The price requests could not be loaded, try again")
    } finally {
        isLoading.value = false
    }
}

watch(
    () => [visible.value, props.stockSlug],
    () => {
        if (visible.value && props.stockSlug) {
            data.value = null
            adjustingId.value = null
            savedMasterId.value = null
            answeredHere.value = false
            rejectingSupplierChangeId.value = null
            acceptedPrice.value = null
            for (const masterProductId of Object.keys(suggestedPrices)) {
                delete suggestedPrices[Number(masterProductId)]
            }
            fetchData()
        }
    },
    { immediate: true }
)

const answeredHere = ref(false)

const header = computed(() => {
    if (!data.value) {
        return ctrans("Prices")
    }

    return data.value.requests.length || data.value.supplier_price_changes.length || answeredHere.value ? ctrans("Price requests for :code", { code: data.value.code }) : ctrans("Price and RRP of :code", { code: data.value.code })
})

const unitCost = (currency: string | null, amount: number | null): string => locale.currencyFormatUnitCost(currency, amount ?? 0)

const money = (currency: string, amount: number | null): string => (amount === null ? "—" : String(locale.currencyFormat(currency, amount)))

function changePercent(request: PriceRequest): number | null {
    const before = request.previous_unit_cost ?? request.unit_cost
    if (!before) {
        return null
    }

    return Math.round((request.asked_unit_cost / before - 1) * 1000) / 10
}

function openAdjust(request: PriceRequest): void {
    adjustingId.value = request.id
    adjustedCosts[request.id] = request.asked_unit_cost
    requestErrors[request.id] = null
}

function firstError(errors: Record<string, string>): string {
    return Object.values(errors)[0] ?? ctrans("Something went wrong, try again")
}

function answer(request: PriceRequest, answerValue: Answer): void {
    if (!request.answer_route) {
        return
    }

    answering.value = { id: request.id, answer: answerValue }
    requestErrors[request.id] = null

    router.patch(
        route(request.answer_route.name, request.answer_route.parameters),
        {
            answer: answerValue,
            update_supplier_cost: answerValue !== "rejected" && !!updateSupplierCost[request.id],
            ...(answerValue === "adjusted" ? { unit_cost: adjustedCosts[request.id] } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: props.reloadProps,
            onSuccess: () => {
                adjustingId.value = null
                answeredHere.value = true
                const oldCost = request.previous_unit_cost ?? request.unit_cost
                const newCost = answerValue === "adjusted" ? adjustedCosts[request.id] : request.asked_unit_cost
                if (answerValue !== "rejected" && newCost && oldCost && newCost !== oldCost) {
                    acceptAndMaybeSave({ currency: request.currency, old: oldCost, new: newCost }, `agent-${request.id}`)
                } else {
                    fetchData()
                }
            },
            onError: (errors) => (requestErrors[request.id] = firstError(errors)),
            onFinish: () => (answering.value = null),
        }
    )
}

function isAnswering(request: PriceRequest, answerValue: Answer): boolean {
    return answering.value?.id === request.id && answering.value.answer === answerValue
}

function supplierChangePercent(change: SupplierPriceChange): number | null {
    if (!change.cost) {
        return null
    }

    return Math.round((change.proposed_cost / change.cost - 1) * 1000) / 10
}

function decideSupplierChange(change: SupplierPriceChange, decision: SupplierDecision): void {
    if (!change.decide_route) {
        return
    }

    decidingSupplierChange.value = { id: change.id, decision }
    supplierChangeErrors[change.id] = null

    router.post(
        route(change.decide_route.name, change.decide_route.parameters),
        {
            state: decision,
            ...(decision === "accepted" ? { update_open_purchase_orders: change.open_purchase_order_lines.length > 0 && !!updateOpenPurchaseOrders[change.id] } : {}),
            ...(decision === "not_proceeding" ? { feedback: supplierRejectFeedback[change.id] } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: props.reloadProps,
            onSuccess: () => {
                rejectingSupplierChangeId.value = null
                answeredHere.value = true
                if (decision === "accepted" && change.cost && change.proposed_cost !== change.cost) {
                    acceptAndMaybeSave({ currency: change.currency, old: change.cost, new: change.proposed_cost }, `supplier-${change.id}`)
                } else {
                    fetchData()
                }
            },
            onError: (errors) => (supplierChangeErrors[change.id] = firstError(errors)),
            onFinish: () => (decidingSupplierChange.value = null),
        }
    )
}

function isDecidingSupplierChange(change: SupplierPriceChange, decision: SupplierDecision): boolean {
    return decidingSupplierChange.value?.id === change.id && decidingSupplierChange.value.decision === decision
}

function openSupplierReject(change: SupplierPriceChange): void {
    rejectingSupplierChangeId.value = change.id
    supplierRejectFeedback[change.id] ??= ""
    supplierChangeErrors[change.id] = null
}

function marginCost(masterProduct: MasterProduct): number | null {
    return masterProduct.cost_at_new_price ?? masterProduct.cost
}

function hasCostAtNewPrice(masterProduct: MasterProduct): boolean {
    return masterProduct.cost !== null && masterProduct.cost_at_new_price !== null && masterProduct.cost_at_new_price !== masterProduct.cost
}

const newPriceNote = computed(() => {
    const newPrice = data.value?.new_price
    if (!newPrice || !data.value?.master_products.some(hasCostAtNewPrice)) {
        return null
    }

    const replacements = { price: unitCost(newPrice.currency, newPrice.unit_cost), reference: newPrice.purchase_order }

    return newPrice.state === "pending"
        ? ctrans("Margins use the cost at the asked price of :price per unit (:reference) until it is answered", replacements)
        : ctrans("Margins use the cost at the agreed price of :price per unit (:reference) until those goods are booked in", replacements)
})

function followingPrice(masterProduct: MasterProduct, following: MasterProduct["following_currencies"][number]): number | null {
    const majorPrice = masterForms[masterProduct.id]?.prices[following.major]
    if (!majorPrice) {
        return masterProduct.prices[following.currency] ?? null
    }

    return Math.round(majorPrice * following.exchange * 100) / 100
}

type FollowingCurrency = MasterProduct["following_currencies"][number]

interface CurrencyRow {
    currency: string
    following: FollowingCurrency | null
}

const currencyOrder = ["GBP", "EUR"]

function currencyRank(masterProduct: MasterProduct, currency: string): number {
    if (currency === masterProduct.cost_currency) {
        return -1
    }

    const index = currencyOrder.indexOf(currency)

    return index === -1 ? currencyOrder.length : index
}

function currencyRows(masterProduct: MasterProduct): CurrencyRow[] {
    return [
        ...masterProduct.main_currencies.map((currency) => ({ currency, following: null })),
        ...masterProduct.following_currencies.map((following) => ({ currency: following.currency, following })),
    ].sort((first, second) => currencyRank(masterProduct, first.currency) - currencyRank(masterProduct, second.currency) || first.currency.localeCompare(second.currency))
}

function priceIn(masterProduct: MasterProduct, currency: string): number | null {
    const following = masterProduct.following_currencies.find((item) => item.currency === currency)

    return following ? followingPrice(masterProduct, following) : masterForms[masterProduct.id]?.prices[currency] ?? null
}

function margin(masterProduct: MasterProduct, currency: string): number | null {
    const price = priceIn(masterProduct, currency)
    const cost = marginCost(masterProduct)
    const exchange = masterProduct.cost_exchanges[currency]
    if (cost === null || !exchange || !price) {
        return null
    }

    return Math.round(((price - cost * exchange) / price) * 1000) / 10
}

function marginTooltip(masterProduct: MasterProduct, currency: string): string {
    const price = priceIn(masterProduct, currency)
    const cost = marginCost(masterProduct)
    const exchange = masterProduct.cost_exchanges[currency]
    const formula = ctrans("Our margin = (price per outer − cost) ÷ price per outer")
    if (cost === null || !exchange || !price) {
        return formula
    }

    return `${formula}: ${ctrans("(:price − :cost) ÷ :price", { price: money(currency, price), cost: money(currency, cost * exchange) })}`
}

interface RrpCopy {
    masterProduct: MasterProduct
    currencies: string[]
}

function changedRrpCurrencies(masterProduct: MasterProduct): string[] {
    const form = masterForms[masterProduct.id]
    const savedRrps = rrpsPerUnit(masterProduct)

    return masterProduct.main_currencies.filter((currency) => form?.rrps[currency] && form.rrps[currency] !== savedRrps[currency])
}

function rrpCopies(masterProduct: MasterProduct): RrpCopy[] {
    if (!data.value?.can_edit_master_prices || masterProduct.stocks_in_product > 1) {
        return []
    }

    const changedCurrencies = changedRrpCurrencies(masterProduct)

    return data.value.master_products
        .filter((other) => other.id !== masterProduct.id && other.stocks_in_product <= 1)
        .map((other) => ({ masterProduct: other, currencies: changedCurrencies.filter((currency) => other.main_currencies.includes(currency)) }))
        .filter((copy) => copy.currencies.length)
}

function copiedRrpPerUnit(masterProduct: MasterProduct, currency: string): number | null {
    for (const source of data.value?.master_products ?? []) {
        if (copyRrps[source.id] && rrpCopies(source).some((copy) => copy.masterProduct.id === masterProduct.id && copy.currencies.includes(currency))) {
            return masterForms[source.id].rrps[currency] ?? null
        }
    }

    return null
}

function rrpPerOuter(masterProduct: MasterProduct, currency: string): number | null {
    const rrpPerUnit = copiedRrpPerUnit(masterProduct, currency) ?? masterForms[masterProduct.id]?.rrps[currency]

    return rrpPerUnit ? rrpPerUnit * (masterProduct.units || 1) : null
}

function retailMargin(masterProduct: MasterProduct, currency: string): number | null {
    const price = priceIn(masterProduct, currency)
    const rrp = rrpPerOuter(masterProduct, currency)
    if (!price || !rrp) {
        return null
    }

    return Math.round(((rrp - price) / rrp) * 1000) / 10
}

function retailMarginTooltip(masterProduct: MasterProduct, currency: string): string {
    const price = priceIn(masterProduct, currency)
    const rrp = rrpPerOuter(masterProduct, currency)
    const formula = ctrans("Retail margin = (RRP per unit × units per outer − price per outer) ÷ (RRP per unit × units per outer)")
    if (!price || !rrp) {
        return formula
    }

    return `${formula}: ${ctrans("(:rrp − :price) ÷ :rrp", { rrp: money(currency, rrp), price: money(currency, price) })}`
}

function isMasterChanged(masterProduct: MasterProduct): boolean {
    const form = masterForms[masterProduct.id]
    if (!form) {
        return false
    }

    const savedRrps = rrpsPerUnit(masterProduct)
    return masterProduct.main_currencies.some(
        (currency) => (form.prices[currency] ?? null) !== (masterProduct.prices[currency] ?? null) || (form.rrps[currency] ?? null) !== (savedRrps[currency] ?? null)
    )
}

function changedValues(current: Record<string, number | null>, saved: Record<string, number | null>): Record<string, number> {
    return Object.fromEntries(Object.entries(current).filter(([currency, value]) => value !== null && value !== saved[currency])) as Record<string, number>
}

const isSavingAll = ref(false)

async function saveAllSuggested(): Promise<void> {
    isSavingAll.value = true
    try {
        for (const masterProduct of data.value?.master_products ?? []) {
            if (hasSuggestion(masterProduct) && isMasterChanged(masterProduct)) {
                const saved = await saveMaster(masterProduct)
                if (!saved) {
                    return
                }
            }
        }
    } finally {
        isSavingAll.value = false
    }
}

function saveMaster(masterProduct: MasterProduct): Promise<boolean> {
    const form = masterForms[masterProduct.id]
    savingMasterId.value = masterProduct.id
    masterErrors[masterProduct.id] = null
    savedMasterId.value = null

    return new Promise((resolve) => router.patch(
        route(masterProduct.update_route.name, masterProduct.update_route.parameters),
        {
            master_prices: changedValues(form.prices, masterProduct.prices),
            master_rrps: rrpsPerOuter(changedValues(form.rrps, rrpsPerUnit(masterProduct)), masterProduct.units),
            copy_rrps_to: copyRrps[masterProduct.id] ? rrpCopies(masterProduct).map((copy) => copy.masterProduct.id) : [],
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: props.reloadProps,
            onSuccess: async () => {
                copyRrps[masterProduct.id] = false
                delete suggestedPrices[masterProduct.id]
                await fetchData()
                savedMasterId.value = masterProduct.id
                resolve(true)
            },
            onError: (errors) => {
                masterErrors[masterProduct.id] = firstError(errors)
                resolve(false)
            },
            onCancel: () => resolve(false),
            onFinish: () => (savingMasterId.value = null),
        }
    ))
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="header" :style="{ width: '54rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div v-if="isLoading && !data" class="space-y-3">
            <div class="skeleton h-28 rounded" />
            <div class="skeleton h-40 rounded" />
        </div>
        <p v-else-if="loadError" class="text-sm text-red-600">{{ loadError }}</p>

        <div v-else-if="data" class="space-y-5 text-sm">
            <ProductDialogHeader :code="data.code" :name="data.name" :image="data.image" />

            <section v-if="data.requests.length || answeredHere" class="space-y-2">
                <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <FontAwesomeIcon :icon="faTags" class="text-amber-600" fixed-width aria-hidden="true" />
                    {{ ctrans("New supplier prices waiting for your answer") }}
                    <LoadingIcon v-if="isLoading" />
                </h3>

                <p v-if="!data.requests.length" class="rounded border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800">
                    {{ ctrans("Every price request of this product is answered") }}
                </p>

                <div v-for="request in data.requests" :key="request.id" class="space-y-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-xs text-gray-600">
                        <span class="rounded bg-white px-1.5 py-0.5 font-semibold text-gray-700">{{ request.organisation }}</span>
                        <a :href="request.purchase_order.url" target="_blank" class="flex items-center gap-1 whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline">
                            {{ request.purchase_order.reference }}
                            <FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" aria-hidden="true" />
                        </a>
                        <span class="whitespace-nowrap font-medium text-gray-800">{{ request.code }}</span>
                        <span v-if="request.supplier">{{ request.supplier }}</span>
                        <span v-if="request.agent" class="text-gray-500">{{ ctrans("via :agent", { agent: request.agent }) }}</span>
                        <span class="ml-auto text-gray-500">
                            <template v-if="request.asked_by">{{ ctrans("Asked by :name", { name: request.asked_by }) }}</template>
                            <template v-if="request.asked_at"> · {{ useFormatTime(request.asked_at) }}</template>
                        </span>
                    </div>

                    <div class="flex flex-wrap items-baseline gap-x-3 tabular-nums text-amber-900">
                        <span>
                            {{ ctrans("The supplier asks :new per unit, was :old", { new: unitCost(request.currency, request.asked_unit_cost), old: unitCost(request.currency, request.previous_unit_cost ?? request.unit_cost) }) }}
                        </span>
                        <span v-if="changePercent(request) !== null" class="font-semibold" :class="(changePercent(request) as number) > 0 ? 'text-red-600' : 'text-green-700'">
                            {{ (changePercent(request) as number) > 0 ? "+" : "" }}{{ changePercent(request) }}%
                        </span>
                        <span v-if="request.units_per_carton > 1" class="text-xs text-gray-500">
                            {{ ctrans(":new per carton of :units", { new: unitCost(request.currency, request.asked_unit_cost * request.units_per_carton), units: request.units_per_carton }) }}
                        </span>
                    </div>
                    <p v-if="request.note" class="text-xs italic text-gray-600">{{ request.note }}</p>

                    <template v-if="request.answer_route">
                        <label v-if="request.can_update_supplier_cost" class="flex w-fit cursor-pointer items-center gap-2 text-xs text-gray-700">
                            <Checkbox v-model="updateSupplierCost[request.id]" binary size="small" />
                            {{ ctrans("Also save the new price as the supplier product cost") }}
                        </label>

                        <div v-if="previewFor(request.master_costs_at_new_price).length" class="space-y-1 rounded border border-orange-200 bg-white px-2 py-1.5">
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-orange-800">
                                <PingIcon class="text-[6px] text-orange-500" aria-hidden="true" />
                                {{ previewFor(request.master_costs_at_new_price).length === 1 ? ctrans("To do: new selling price for 1 master product") : ctrans("To do: new selling prices for :count master products", { count: previewFor(request.master_costs_at_new_price).length }) }}
                            </p>
                            <ul class="space-y-0.5 pl-4 text-xs text-gray-600">
                                <li v-for="item in previewFor(request.master_costs_at_new_price)" :key="item.masterProduct.id" class="flex flex-wrap items-baseline gap-x-2 tabular-nums">
                                    <span class="whitespace-nowrap font-semibold text-gray-700">{{ item.masterProduct.master_shop?.toUpperCase() }} {{ item.masterProduct.code }}</span>
                                    <span v-for="price in item.currencies" :key="price.currency" class="whitespace-nowrap">
                                        {{ ctrans(":old → :new", { old: money(price.currency, price.old), new: money(price.currency, price.new) }) }}
                                    </span>
                                </li>
                            </ul>
                            <label class="flex w-fit cursor-pointer items-center gap-2 text-xs text-gray-700">
                                <Checkbox v-model="alsoSaveSellingPrices[`agent-${request.id}`]" binary size="small" />
                                {{ ctrans("Also save these selling prices when I accept") }}
                                <FontAwesomeIcon
                                    v-tooltip="ctrans('Each price keeps the product\'s current margin: price × cost at the new price ÷ current cost. A bundle moves only by this SKU\'s share of its cost. Unticked, the prices are only filled in below for you to check and save. The RRPs stay as they are.')"
                                    :icon="faInfoCircle"
                                    class="cursor-help text-gray-400"
                                    fixed-width
                                    aria-hidden="true"
                                />
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <Button type="tertiary" size="xs" :label="ctrans('Accept')" :loading="isAnswering(request, 'accepted')" :disabled="!!answering" @click="answer(request, 'accepted')">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button type="tertiary" size="xs" :label="ctrans('Reject, keep old price')" :loading="isAnswering(request, 'rejected')" :disabled="!!answering" @click="answer(request, 'rejected')">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button v-if="adjustingId !== request.id" type="tertiary" size="xs" :label="ctrans('Set another price')" :disabled="!!answering" @click="openAdjust(request)">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faPen" class="text-gray-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>

                        <div v-if="adjustingId === request.id" class="flex flex-wrap items-end gap-2">
                            <label class="space-y-1">
                                <span class="block text-xs text-gray-500">{{ ctrans("Your price per unit") }} <span class="text-red-500">*</span></span>
                                <InputNumber v-model="adjustedCosts[request.id]" @input="(event) => (adjustedCosts[request.id] = event.value as number)" :min="0" v-bind="locale.unitCostInputOptions(request.currency)" :class="fieldFocusClass" size="small" />
                            </label>
                            <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="adjustingId = null">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button type="tertiary" size="xs" :label="ctrans('Save price')" :loading="isAnswering(request, 'adjusted')" :disabled="!adjustedCosts[request.id] || !!answering" @click="answer(request, 'adjusted')">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>
                    </template>
                    <p v-else class="text-xs text-gray-500">{{ ctrans("You can not answer for :organisation: it needs procurement edit rights there", { organisation: request.organisation }) }}</p>

                    <p v-if="requestErrors[request.id]" class="text-xs text-red-600">{{ requestErrors[request.id] }}</p>
                </div>
            </section>

            <section v-if="data.supplier_price_changes.length" class="space-y-2">
                <h3 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <FontAwesomeIcon :icon="faIndustry" class="text-amber-600" fixed-width aria-hidden="true" />
                    {{ ctrans("Price or MOQ changes proposed by the supplier") }}
                    <LoadingIcon v-if="isLoading" />
                </h3>

                <div v-for="change in data.supplier_price_changes" :key="change.id" class="space-y-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-xs text-gray-600">
                        <span v-if="change.supplier_code" class="whitespace-nowrap rounded bg-white px-1.5 py-0.5 font-semibold text-gray-700">{{ change.supplier_code }}</span>
                        <span v-if="change.supplier">{{ change.supplier }}</span>
                        <a :href="change.url" target="_blank" class="flex items-center gap-1 whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline">
                            {{ change.code ?? ctrans("Submission") }}
                            <FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" aria-hidden="true" />
                        </a>
                        <span v-if="change.submitted_at" class="ml-auto text-gray-500">{{ ctrans("Sent :date", { date: useFormatTime(change.submitted_at) }) }}</span>
                    </div>

                    <div class="flex flex-wrap items-baseline gap-x-3 tabular-nums text-amber-900">
                        <span v-if="change.cost !== null">
                            {{ ctrans("Current price :old per unit, the supplier proposes :new", { new: unitCost(change.currency, change.proposed_cost), old: unitCost(change.currency, change.cost) }) }}
                        </span>
                        <span v-else>{{ ctrans("The supplier proposes :new per unit", { new: unitCost(change.currency, change.proposed_cost) }) }}</span>
                        <span v-if="supplierChangePercent(change) !== null" class="font-semibold" :class="(supplierChangePercent(change) as number) > 0 ? 'text-red-600' : 'text-green-700'">
                            {{ (supplierChangePercent(change) as number) > 0 ? "+" : "" }}{{ supplierChangePercent(change) }}%
                        </span>
                        <span v-if="change.units_per_carton > 1" class="text-xs text-gray-500">
                            {{ ctrans(":new per carton of :units", { new: unitCost(change.currency, change.proposed_cost * change.units_per_carton), units: change.units_per_carton }) }}
                        </span>
                    </div>
                    <p v-if="change.proposed_moq" class="text-xs text-amber-900">
                        {{ change.minimum_carton_order && change.minimum_carton_order !== change.proposed_moq ? ctrans("Current MOQ :old cartons, the supplier proposes :new", { new: change.proposed_moq, old: change.minimum_carton_order }) : ctrans("MOQ :new cartons", { new: change.proposed_moq }) }}
                    </p>
                    <p v-if="change.effective_from || change.effective_until" class="text-xs text-gray-600">
                        <template v-if="change.effective_from">{{ ctrans("From :date", { date: useFormatTime(change.effective_from) }) }}</template>
                        <template v-if="change.effective_from && change.effective_until"> · </template>
                        <template v-if="change.effective_until">{{ ctrans("Until :date", { date: useFormatTime(change.effective_until) }) }}</template>
                    </p>
                    <p v-if="change.reason" class="text-xs italic text-gray-600">{{ change.reason }}</p>

                    <template v-if="change.decide_route">
                        <div v-if="rejectingSupplierChangeId !== change.id && change.open_purchase_order_lines.length" class="space-y-1">
                            <label class="flex w-fit cursor-pointer items-center gap-2 text-xs text-gray-700">
                                <Checkbox v-model="updateOpenPurchaseOrders[change.id]" binary size="small" />
                                {{ change.open_purchase_order_lines.length === 1 ? ctrans("Also use the new price on 1 open purchase order") : ctrans("Also use the new price on :count open purchase orders", { count: change.open_purchase_order_lines.length }) }}
                                <FontAwesomeIcon
                                    v-tooltip="ctrans('On accept, the unit cost of this product changes to the new price on draft and submitted purchase orders of every organisation you can edit procurement in. Agreed orders keep the price agreed with the supplier, and lines with a price request from the agent are answered on their own.')"
                                    :icon="faInfoCircle"
                                    class="cursor-help text-gray-400"
                                    fixed-width
                                    aria-hidden="true"
                                />
                            </label>
                            <ul class="space-y-0.5 pl-6 text-xs text-gray-600">
                                <li v-for="line in change.open_purchase_order_lines" :key="line.id" class="flex flex-wrap items-baseline gap-x-2 tabular-nums">
                                    <span class="rounded bg-white px-1.5 py-0.5 font-semibold text-gray-700">{{ line.organisation }}</span>
                                    <a :href="line.url" target="_blank" class="flex items-center gap-1 whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline">
                                        {{ line.reference }}
                                        <FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" aria-hidden="true" />
                                    </a>
                                    <span>{{ ctrans(":quantity units", { quantity: line.quantity_ordered }) }}</span>
                                    <span :class="updateOpenPurchaseOrders[change.id] ? 'text-amber-900' : 'text-gray-500'">
                                        {{ unitCost(change.currency, line.unit_cost) }}<template v-if="updateOpenPurchaseOrders[change.id]"> → {{ unitCost(change.currency, change.proposed_cost) }}</template>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="rejectingSupplierChangeId !== change.id && previewFor(change.master_costs_at_new_price).length" class="space-y-1 rounded border border-orange-200 bg-white px-2 py-1.5">
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-orange-800">
                                <PingIcon class="text-[6px] text-orange-500" aria-hidden="true" />
                                {{ previewFor(change.master_costs_at_new_price).length === 1 ? ctrans("To do: new selling price for 1 master product") : ctrans("To do: new selling prices for :count master products", { count: previewFor(change.master_costs_at_new_price).length }) }}
                            </p>
                            <ul class="space-y-0.5 pl-4 text-xs text-gray-600">
                                <li v-for="item in previewFor(change.master_costs_at_new_price)" :key="item.masterProduct.id" class="flex flex-wrap items-baseline gap-x-2 tabular-nums">
                                    <span class="whitespace-nowrap font-semibold text-gray-700">{{ item.masterProduct.master_shop?.toUpperCase() }} {{ item.masterProduct.code }}</span>
                                    <span v-for="price in item.currencies" :key="price.currency" class="whitespace-nowrap">
                                        {{ ctrans(":old → :new", { old: money(price.currency, price.old), new: money(price.currency, price.new) }) }}
                                    </span>
                                </li>
                            </ul>
                            <label class="flex w-fit cursor-pointer items-center gap-2 text-xs text-gray-700">
                                <Checkbox v-model="alsoSaveSellingPrices[`supplier-${change.id}`]" binary size="small" />
                                {{ ctrans("Also save these selling prices when I accept") }}
                                <FontAwesomeIcon
                                    v-tooltip="ctrans('Each price keeps the product\'s current margin: price × cost at the new price ÷ current cost. A bundle moves only by this SKU\'s share of its cost. Unticked, the prices are only filled in below for you to check and save. The RRPs stay as they are.')"
                                    :icon="faInfoCircle"
                                    class="cursor-help text-gray-400"
                                    fixed-width
                                    aria-hidden="true"
                                />
                            </label>
                        </div>

                        <div v-if="rejectingSupplierChangeId !== change.id" class="flex flex-wrap items-center gap-1.5">
                            <Button type="tertiary" size="xs" :label="ctrans('Accept, save as supplier cost')" :loading="isDecidingSupplierChange(change, 'accepted')" :disabled="!!decidingSupplierChange" @click="decideSupplierChange(change, 'accepted')">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button type="tertiary" size="xs" :label="ctrans('Reject')" :disabled="!!decidingSupplierChange" @click="openSupplierReject(change)">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>

                        <div v-else class="space-y-1.5">
                            <label class="block space-y-1">
                                <span class="block text-xs text-gray-500">{{ ctrans("Tell the supplier why") }} <span class="text-red-500">*</span></span>
                                <Textarea v-model="supplierRejectFeedback[change.id]" rows="2" autoResize class="w-full text-sm" />
                            </label>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="rejectingSupplierChangeId = null">
                                    <template #icon>
                                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                                <Button type="tertiary" size="xs" :label="ctrans('Reject, keep current cost')" :loading="isDecidingSupplierChange(change, 'not_proceeding')" :disabled="!supplierRejectFeedback[change.id]?.trim() || !!decidingSupplierChange" @click="decideSupplierChange(change, 'not_proceeding')">
                                    <template #icon>
                                        <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </template>
                    <p v-else class="text-xs text-gray-500">{{ ctrans("You need supply chain edit rights to decide this change") }}</p>

                    <p v-if="supplierChangeErrors[change.id]" class="text-xs text-red-600">{{ supplierChangeErrors[change.id] }}</p>
                </div>
            </section>

            <section class="space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Selling price and RRP of the master products") }}</h3>

                <p v-if="newPriceNote" class="text-xs text-gray-500">{{ newPriceNote }}</p>
                <p v-if="!data.master_products.length" class="text-xs text-gray-500">{{ ctrans("No active master product sells this product") }}</p>
                <p v-else-if="!data.can_edit_master_prices" class="text-xs text-gray-500">{{ ctrans("You need master edit rights to change these prices") }}</p>

                <div v-for="masterProduct in data.master_products" :key="masterProduct.id" class="space-y-2 rounded-lg border px-3 py-2" :class="hasSuggestion(masterProduct) ? 'border-orange-300' : 'border-gray-200'">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-xs text-gray-600">
                        <span v-if="hasSuggestion(masterProduct)" class="flex items-center gap-1.5 whitespace-nowrap rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-orange-700">
                            <PingIcon class="text-[6px] text-orange-500" aria-hidden="true" />
                            {{ ctrans("To do: save new price") }}
                        </span>
                        <span v-if="masterProduct.master_shop" class="rounded bg-gray-100 px-1.5 py-0.5 font-semibold uppercase text-gray-700">{{ masterProduct.master_shop }}</span>
                        <a v-if="masterProduct.url" :href="masterProduct.url" target="_blank" class="flex items-center gap-1 whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline">
                            {{ masterProduct.code }}
                            <FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" aria-hidden="true" />
                        </a>
                        <span v-else class="whitespace-nowrap font-medium text-gray-800">{{ masterProduct.code }}</span>
                        <span
                            v-if="masterProduct.stocks_in_product > 1"
                            v-tooltip="ctrans('A bundle sold as one product: :code is one of its :count SKUs, so its cost is only part of the price', { code: data.code, count: masterProduct.stocks_in_product })"
                            class="flex cursor-help items-center gap-1 whitespace-nowrap rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-purple-700"
                        >
                            <FontAwesomeIcon :icon="faLayerGroup" fixed-width aria-hidden="true" />
                            {{ ctrans("Bundle of :count SKUs", { count: masterProduct.stocks_in_product }) }}<template v-if="masterProduct.stock_quantity > 1"> · {{ ctrans(":quantity× :code", { quantity: masterProduct.stock_quantity, code: data.code }) }}</template>
                        </span>
                        <span
                            v-else
                            v-tooltip="ctrans('This product is :code on its own', { code: data.code })"
                            class="flex cursor-help items-center gap-1 whitespace-nowrap rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-sky-700"
                        >
                            <FontAwesomeIcon :icon="faCube" fixed-width aria-hidden="true" />
                            {{ masterProduct.stock_quantity > 1 ? ctrans("Own product, :quantity per outer", { quantity: masterProduct.stock_quantity }) : ctrans("Own product") }}
                        </span>
                        <span class="min-w-[12rem] flex-1">{{ masterProduct.name }}</span>
                        <span class="text-gray-500">{{ ctrans(":units units per outer", { units: masterProduct.units }) }}</span>
                        <a
                            v-if="masterProduct.master_family"
                            :href="masterProduct.master_family.pricing_url"
                            target="_blank"
                            v-tooltip="ctrans('Open the pricing of every product in :family to change the siblings too', { family: masterProduct.master_family.code })"
                            class="flex items-center gap-1 whitespace-nowrap text-[var(--app-accent)] hover:underline"
                        >
                            {{ ctrans("Family :family pricing", { family: masterProduct.master_family.code }) }}
                            <FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" aria-hidden="true" />
                        </a>
                        <span v-if="hasCostAtNewPrice(masterProduct)" class="text-gray-500">
                            {{ ctrans("Cost :cost, :new at the new price", { cost: money(masterProduct.cost_currency, masterProduct.cost), new: money(masterProduct.cost_currency, masterProduct.cost_at_new_price) }) }}
                        </span>
                        <span v-else-if="masterProduct.cost !== null" class="text-gray-500">{{ ctrans("Cost :cost", { cost: money(masterProduct.cost_currency, masterProduct.cost) }) }}</span>
                    </div>

                    <table class="w-full text-xs tabular-nums">
                        <thead class="whitespace-nowrap text-left text-[10px] uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="w-16 pr-3 font-medium">{{ ctrans("Currency") }}</th>
                                <th class="w-px pr-2 font-medium">{{ (masterProduct.units || 1) === 1 ? ctrans("Price per outer (1 unit)") : ctrans("Price per outer (:units units)", { units: masterProduct.units }) }}</th>
                                <th class="w-px pr-6 font-medium">
                                    <span v-tooltip="ctrans('Our margin = (price per outer − cost) ÷ price per outer')" class="inline-flex cursor-help items-center gap-1">{{ ctrans("Our margin") }}<FontAwesomeIcon :icon="faInfoCircle" class="text-[10px] text-gray-400" fixed-width aria-hidden="true" /></span>
                                </th>
                                <th class="w-px pr-6 font-medium">{{ ctrans("RRP per unit") }}</th>
                                <th class="font-medium">
                                    <span v-tooltip="ctrans('Retail margin = (RRP per unit × units per outer − price per outer) ÷ (RRP per unit × units per outer)')" class="inline-flex cursor-help items-center gap-1">{{ ctrans("Retail margin") }}<FontAwesomeIcon :icon="faInfoCircle" class="text-[10px] text-gray-400" fixed-width aria-hidden="true" /></span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="{ currency, following } in currencyRows(masterProduct)" :key="currency">
                            <tr v-if="!following">
                                <td class="py-1 pr-3 font-semibold text-gray-700">
                                    <span class="flex items-center gap-1 whitespace-nowrap">
                                        {{ currency }}
                                        <FontAwesomeIcon v-if="currency === masterProduct.cost_currency" v-tooltip="ctrans('Main currency: the cost is in :currency', { currency })" :icon="faStar" class="text-[10px] text-amber-500" fixed-width :aria-label="ctrans('Main currency')" />
                                    </span>
                                </td>
                                <td class="py-1 pr-3">
                                    <InputNumber
                                        v-if="data.can_edit_master_prices"
                                        v-model="masterForms[masterProduct.id].prices[currency]"
                                        @input="(event) => (masterForms[masterProduct.id].prices[currency] = event.value as number | null)"
                                        :min="0"
                                        v-bind="locale.currencyInputOptions(currency)"
                                        :class="fieldFocusClass"
                                        :aria-label="ctrans('Price in :currency', { currency })"
                                        size="small"
                                    />
                                    <span v-else>{{ money(currency, masterProduct.prices[currency]) }}</span>
                                    <span v-if="hasSuggestion(masterProduct, currency)" class="mt-0.5 flex items-center gap-1 whitespace-nowrap text-[10px] text-orange-700">
                                        <PingIcon class="text-[5px] text-orange-500" aria-hidden="true" />
                                        {{ ctrans("Suggested price. Previous was :old", { old: money(currency, suggestedPrices[masterProduct.id][currency].old) }) }}
                                    </span>
                                </td>
                                <td class="py-1 pr-3">
                                    <span v-if="margin(masterProduct, currency) !== null" v-tooltip="marginTooltip(masterProduct, currency)" class="inline-flex cursor-help items-center gap-1" :class="(margin(masterProduct, currency) as number) < 0 ? 'text-red-600' : 'text-gray-700'">{{ margin(masterProduct, currency) }}%<FontAwesomeIcon :icon="faInfoCircle" class="text-[10px] text-gray-400" fixed-width aria-hidden="true" /></span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="py-1 pr-3">
                                    <InputNumber
                                        v-if="data.can_edit_master_prices"
                                        v-model="masterForms[masterProduct.id].rrps[currency]"
                                        @input="(event) => (masterForms[masterProduct.id].rrps[currency] = event.value as number | null)"
                                        :min="0"
                                        v-bind="locale.currencyInputOptions(currency)"
                                        :class="fieldFocusClass"
                                        :aria-label="ctrans('RRP in :currency', { currency })"
                                        size="small"
                                    />
                                    <span v-else>{{ money(currency, rrpsPerUnit(masterProduct)[currency]) }}</span>
                                </td>
                                <td class="py-1">
                                    <span v-if="retailMargin(masterProduct, currency) !== null" v-tooltip="retailMarginTooltip(masterProduct, currency)" class="inline-flex cursor-help items-center gap-1" :class="(retailMargin(masterProduct, currency) as number) < 0 ? 'text-red-600' : 'text-gray-700'">{{ retailMargin(masterProduct, currency) }}%<FontAwesomeIcon :icon="faInfoCircle" class="text-[10px] text-gray-400" fixed-width aria-hidden="true" /></span>
                                    <span v-else v-tooltip="retailMarginTooltip(masterProduct, currency)" class="inline-flex cursor-help items-center gap-1 text-gray-300">—<FontAwesomeIcon :icon="faInfoCircle" class="text-[10px] text-gray-400" fixed-width aria-hidden="true" /></span>
                                </td>
                            </tr>
                            <tr v-else class="text-gray-500">
                                <td class="py-1 pr-3 font-semibold text-gray-700">
                                    <span class="flex items-center gap-1 whitespace-nowrap">
                                        {{ following.currency }}
                                        <FontAwesomeIcon v-if="following.currency === masterProduct.cost_currency" v-tooltip="ctrans('Main currency: the cost is in :currency', { currency: following.currency })" :icon="faStar" class="text-[10px] text-amber-500" fixed-width :aria-label="ctrans('Main currency')" />
                                    </span>
                                </td>
                                <td class="py-1 pr-3">{{ money(following.currency, followingPrice(masterProduct, following)) }}</td>
                                <td class="py-1 pr-3">
                                    <span v-if="margin(masterProduct, following.currency) !== null" v-tooltip="marginTooltip(masterProduct, following.currency)" class="inline-flex cursor-help items-center gap-1" :class="(margin(masterProduct, following.currency) as number) < 0 ? 'text-red-600' : 'text-gray-700'">{{ margin(masterProduct, following.currency) }}%<FontAwesomeIcon :icon="faInfoCircle" class="text-[10px] text-gray-400" fixed-width aria-hidden="true" /></span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="py-1 pr-3 text-[10px]">{{ ctrans("follows :major × :exchange", { major: following.major, exchange: following.exchange }) }}</td>
                                <td class="py-1">
                                    <span v-tooltip="ctrans('No RRP is set in :currency', { currency: following.currency })" class="inline-flex cursor-help items-center gap-1 text-gray-300">—<FontAwesomeIcon :icon="faInfoCircle" class="text-[10px] text-gray-400" fixed-width aria-hidden="true" /></span>
                                </td>
                            </tr>
                            </template>
                        </tbody>
                        <tfoot v-if="rrpCopies(masterProduct).length">
                            <tr>
                                <td colspan="3" />
                                <td colspan="2" class="pt-1">
                                    <label class="flex w-full cursor-pointer items-start gap-2 rounded border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] px-2 py-1.5 text-xs text-gray-700">
                                        <Checkbox v-model="copyRrps[masterProduct.id]" binary size="small" class="mt-0.5" />
                                        <span class="space-y-0.5">
                                            <span class="block">{{ ctrans("Also set the same RRP per unit on") }}</span>
                                            <span v-for="copy in rrpCopies(masterProduct)" :key="copy.masterProduct.id" class="flex flex-wrap items-baseline gap-x-2 tabular-nums">
                                                <span class="whitespace-nowrap font-semibold">{{ copy.masterProduct.master_shop?.toUpperCase() }} {{ copy.masterProduct.code }}</span>
                                                <span v-for="currency in copy.currencies" :key="currency" class="whitespace-nowrap text-gray-600">
                                                    {{ ctrans(":old → :new", { old: money(currency, rrpsPerUnit(copy.masterProduct)[currency] ?? null), new: money(currency, masterForms[masterProduct.id].rrps[currency] ?? null) }) }}
                                                </span>
                                            </span>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <div v-if="data.can_edit_master_prices" class="flex flex-wrap items-center gap-2">
                        <span v-if="masterProduct.other_currencies.length" class="text-xs text-gray-500">
                            {{ ctrans(":currencies follow by exchange rate, except prices set by hand", { currencies: masterProduct.other_currencies.join(", ") }) }}
                        </span>
                        <span v-if="savedMasterId === masterProduct.id && !isMasterChanged(masterProduct)" class="ml-auto flex items-center gap-1 text-xs text-green-700">
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            {{ ctrans("Saved, shops following the master pick it up in the next minutes") }}
                        </span>
                        <span class="relative ml-auto">
                            <Button
                                type="tertiary"
                                size="xs"
                                :label="ctrans('Save price and RRP')"
                                :loading="savingMasterId === masterProduct.id"
                                :disabled="!isMasterChanged(masterProduct) || savingMasterId !== null"
                                @click="saveMaster(masterProduct)"
                            >
                                <template #icon>
                                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <PingIcon v-if="isMasterChanged(masterProduct) && savingMasterId !== masterProduct.id" class="pointer-events-none !absolute -right-0.5 -top-0.5 text-[7px] text-orange-500" aria-hidden="true" />
                        </span>
                    </div>
                    <p v-if="masterErrors[masterProduct.id]" class="text-xs text-red-600">{{ masterErrors[masterProduct.id] }}</p>
                </div>

                <div v-if="acceptedPriceSummary && suggestionCount" class="flex items-start gap-2 rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-xs text-gray-700">
                    <PingIcon class="mt-1 shrink-0 text-[7px] text-orange-500" aria-hidden="true" />
                    <div class="space-y-1">
                        <p class="font-semibold text-orange-800">
                            {{ suggestionCount === 1 ? ctrans("To do: check and save the new selling price of 1 master product") : ctrans("To do: check and save the new selling prices of :count master products", { count: suggestionCount }) }}
                        </p>
                        <p>{{ acceptedPriceSummary }}</p>
                        <p>{{ ctrans("The prices marked above are filled in to keep each product's current margin: price × cost at the new price ÷ current cost. A bundle moves only by this SKU's share of its cost.") }}</p>
                        <p>{{ ctrans("Nothing is saved yet: change a price if you want, then click Save price and RRP on each product, or Save all suggested prices. The RRPs stay as they are.") }}</p>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <Button type="tertiary" size="xs" :label="suggestionCount === 1 ? ctrans('Save the suggested price') : ctrans('Save all :count suggested prices', { count: suggestionCount })" :loading="isSavingAll" :disabled="savingMasterId !== null" @click="saveAllSuggested">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>
                    </div>
                </div>
                <p v-else-if="acceptedPriceSummary && data.can_edit_master_prices" class="rounded border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-800">
                    {{ acceptedPriceSummary }} {{ ctrans("Every master product is saved or has no cost to work from, so no selling price is waiting.") }}
                </p>
                <p v-else-if="acceptedPriceSummary" class="text-xs text-gray-500">
                    {{ acceptedPriceSummary }} {{ ctrans("Ask someone with master edit rights to update the selling prices.") }}
                </p>
            </section>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" full :label="ctrans('Close')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
