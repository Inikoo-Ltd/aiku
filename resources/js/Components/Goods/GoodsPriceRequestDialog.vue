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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faPen, faTags, faExternalLink, faLayerGroup, faCube } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
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
    can_update_supplier_cost: boolean
    answer_route: routeType | null
}

interface MasterProduct {
    id: number
    code: string
    name: string | null
    master_shop: string | null
    units: number
    stocks_in_product: number
    stock_quantity: number
    cost: number | null
    cost_currency: string
    main_currencies: string[]
    other_currencies: string[]
    prices: Record<string, number | null>
    rrps: Record<string, number | null>
    url: string | null
    master_family: { code: string; pricing_url: string } | null
    update_route: routeType
}

interface PriceRequestData {
    code: string
    name: string | null
    requests: PriceRequest[]
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

const masterForms = reactive<Record<number, { prices: Record<string, number | null>; rrps: Record<string, number | null> }>>({})
const savingMasterId = ref<number | null>(null)
const masterErrors = reactive<Record<number, string | null>>({})
const savedMasterId = ref<number | null>(null)

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
        const response = await axios.get(route("grp.goods.products.price_requests", { stock: props.stockSlug }))
        data.value = response.data
        for (const masterProduct of data.value?.master_products ?? []) {
            masterForms[masterProduct.id] = { prices: { ...masterProduct.prices }, rrps: rrpsPerUnit(masterProduct) }
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

    return data.value.requests.length || answeredHere.value ? ctrans("Price requests for :code", { code: data.value.code }) : ctrans("Price and RRP of :code", { code: data.value.code })
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
                fetchData()
            },
            onError: (errors) => (requestErrors[request.id] = firstError(errors)),
            onFinish: () => (answering.value = null),
        }
    )
}

function isAnswering(request: PriceRequest, answerValue: Answer): boolean {
    return answering.value?.id === request.id && answering.value.answer === answerValue
}

function margin(masterProduct: MasterProduct, currency: string): number | null {
    const price = masterForms[masterProduct.id]?.prices[currency]
    if (masterProduct.cost === null || currency !== masterProduct.cost_currency || !price) {
        return null
    }

    return Math.round(((price - masterProduct.cost) / price) * 1000) / 10
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

function saveMaster(masterProduct: MasterProduct): void {
    const form = masterForms[masterProduct.id]
    savingMasterId.value = masterProduct.id
    masterErrors[masterProduct.id] = null
    savedMasterId.value = null

    router.patch(
        route(masterProduct.update_route.name, masterProduct.update_route.parameters),
        {
            master_prices: changedValues(form.prices, masterProduct.prices),
            master_rrps: rrpsPerOuter(changedValues(form.rrps, rrpsPerUnit(masterProduct)), masterProduct.units),
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: props.reloadProps,
            onSuccess: async () => {
                await fetchData()
                savedMasterId.value = masterProduct.id
            },
            onError: (errors) => (masterErrors[masterProduct.id] = firstError(errors)),
            onFinish: () => (savingMasterId.value = null),
        }
    )
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
            <p v-if="data.name" class="-mt-2 text-xs text-gray-500">{{ data.name }}</p>

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
                                <InputNumber v-model="adjustedCosts[request.id]" :min="0" v-bind="locale.unitCostInputOptions(request.currency)" :class="fieldFocusClass" size="small" />
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

            <section class="space-y-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Selling price and RRP of the master products") }}</h3>

                <p v-if="!data.master_products.length" class="text-xs text-gray-500">{{ ctrans("No active master product sells this product") }}</p>
                <p v-else-if="!data.can_edit_master_prices" class="text-xs text-gray-500">{{ ctrans("You need master edit rights to change these prices") }}</p>

                <div v-for="masterProduct in data.master_products" :key="masterProduct.id" class="space-y-2 rounded-lg border border-gray-200 px-3 py-2">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-xs text-gray-600">
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
                        <span class="min-w-0 flex-1 truncate">{{ masterProduct.name }}</span>
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
                        <span v-if="masterProduct.cost !== null" class="text-gray-500">{{ ctrans("Cost :cost", { cost: money(masterProduct.cost_currency, masterProduct.cost) }) }}</span>
                    </div>

                    <table class="w-full text-xs tabular-nums">
                        <thead class="whitespace-nowrap text-left text-[10px] uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="w-16 pr-3 font-medium">{{ ctrans("Currency") }}</th>
                                <th class="pr-3 font-medium">{{ ctrans("Price per outer") }}</th>
                                <th class="pr-3 font-medium">{{ ctrans("RRP per unit") }}</th>
                                <th class="font-medium">{{ ctrans("Margin") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="currency in masterProduct.main_currencies" :key="currency">
                                <td class="py-1 pr-3 font-semibold text-gray-700">{{ currency }}</td>
                                <td class="py-1 pr-3">
                                    <InputNumber
                                        v-if="data.can_edit_master_prices"
                                        v-model="masterForms[masterProduct.id].prices[currency]"
                                        :min="0"
                                        v-bind="locale.currencyInputOptions(currency)"
                                        :class="fieldFocusClass"
                                        :aria-label="ctrans('Price in :currency', { currency })"
                                        size="small"
                                    />
                                    <span v-else>{{ money(currency, masterProduct.prices[currency]) }}</span>
                                </td>
                                <td class="py-1 pr-3">
                                    <InputNumber
                                        v-if="data.can_edit_master_prices"
                                        v-model="masterForms[masterProduct.id].rrps[currency]"
                                        :min="0"
                                        v-bind="locale.currencyInputOptions(currency)"
                                        :class="fieldFocusClass"
                                        :aria-label="ctrans('RRP in :currency', { currency })"
                                        size="small"
                                    />
                                    <span v-else>{{ money(currency, rrpsPerUnit(masterProduct)[currency]) }}</span>
                                </td>
                                <td class="py-1">
                                    <span v-if="margin(masterProduct, currency) !== null" :class="(margin(masterProduct, currency) as number) < 0 ? 'text-red-600' : 'text-gray-700'">{{ margin(masterProduct, currency) }}%</span>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="data.can_edit_master_prices" class="flex flex-wrap items-center gap-2">
                        <span v-if="masterProduct.other_currencies.length" class="text-xs text-gray-500">
                            {{ ctrans(":currencies follow by exchange rate, except prices set by hand", { currencies: masterProduct.other_currencies.join(", ") }) }}
                        </span>
                        <span v-if="savedMasterId === masterProduct.id && !isMasterChanged(masterProduct)" class="ml-auto flex items-center gap-1 text-xs text-green-700">
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            {{ ctrans("Saved, shops following the master pick it up in the next minutes") }}
                        </span>
                        <Button
                            type="tertiary"
                            size="xs"
                            class="ml-auto"
                            :label="ctrans('Save price and RRP')"
                            :loading="savingMasterId === masterProduct.id"
                            :disabled="!isMasterChanged(masterProduct) || savingMasterId !== null"
                            @click="saveMaster(masterProduct)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <p v-if="masterErrors[masterProduct.id]" class="text-xs text-red-600">{{ masterErrors[masterProduct.id] }}</p>
                </div>
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
