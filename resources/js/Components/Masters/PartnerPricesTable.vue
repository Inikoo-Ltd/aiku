<script setup lang="ts">
import { computed, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import { cloneDeep } from "lodash-es"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faExclamationTriangle, faSave as falSave } from "@fal"
import { faSave as fadSave, faSpinnerThird } from "@fad"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import PureMultiplePriceCurrency from "@/Components/Pure/PureMultiplePriceCurrency.vue"
import FlashOnChange from "@/Components/Utils/FlashOnChange.vue"

library.add(faLink, faExclamationTriangle, falSave, fadSave, faSpinnerThird)

interface RouteTarget {
    name: string
    parameters: Record<string, string | number>
}

interface Partner {
    org_partner_id: number
    code: string
    name: string
    currency_code: string
    currency_symbol: string
    from_base_rate: number
    shop_currencies: string[]
}

interface CurrencyPrice {
    value: number | null
    independent: boolean
}

interface Sibling {
    id: number
    code: string
    master_shop: string
    units: number
    master_prices: Record<string, CurrencyPrice> | null
    master_rrps: Record<string, CurrencyPrice> | null
    currencies: Record<string, any>
    costs: Record<string, number | null> | null
    update_route: RouteTarget
}

const props = defineProps<{
    masterAssetCode?: string
    meta: {
        seller_code: string
        rates_from_base: Record<string, number>
        base_currency: { code: string, symbol: string }
        partner_discount: number
        partners: Partner[]
    }
    row: {
        list_price: number
        cost: number | null
        prices: Record<string, number | null>
        sibling: Sibling | null
    }
    updateRoute: RouteTarget
}>()

const locale = useLocaleStore()

const partnerForm = useForm({
    partner_prices: cloneDeep(props.row.prices) as Record<string, number | null>,
    _method: "patch",
})

const siblingPriceForm = useForm({
    master_prices: cloneDeep(props.row.sibling?.master_prices ?? {}),
    _method: "patch",
})

const siblingRrpForm = useForm({
    master_rrps: cloneDeep(props.row.sibling?.master_rrps ?? {}),
    _method: "patch",
})

watch(() => props.row, (row) => {
    if (!partnerForm.isDirty) {
        partnerForm.partner_prices = cloneDeep(row.prices)
        partnerForm.defaults("partner_prices", cloneDeep(row.prices))
    }
    if (!siblingPriceForm.isDirty) {
        siblingPriceForm.master_prices = cloneDeep(row.sibling?.master_prices ?? {})
        siblingPriceForm.defaults("master_prices", cloneDeep(row.sibling?.master_prices ?? {}))
    }
    if (!siblingRrpForm.isDirty) {
        siblingRrpForm.master_rrps = cloneDeep(row.sibling?.master_rrps ?? {})
        siblingRrpForm.defaults("master_rrps", cloneDeep(row.sibling?.master_rrps ?? {}))
    }
}, { deep: true })

const defaultPriceFor = (partner: Partner): number =>
    Math.round(props.row.list_price * (1 - props.meta.partner_discount) * partner.from_base_rate * 100) / 100

const customPriceFor = (partner: Partner, saved = false): number | null => {
    const value = saved ? props.row.prices[partner.org_partner_id] : partnerForm.partner_prices[partner.org_partner_id]

    return value === null || value === undefined ? null : Number(value)
}

const paidFor = (partner: Partner, saved = false): number => customPriceFor(partner, saved) ?? defaultPriceFor(partner)

const paidInBase = (partner: Partner, saved = false): number => partner.from_base_rate ? paidFor(partner, saved) / partner.from_base_rate : paidFor(partner, saved)

const isAboveListPrice = (partner: Partner): boolean => customPriceFor(partner) !== null && paidInBase(partner) > props.row.list_price + 0.005

const percent = (numerator: number, denominator: number): number | null => denominator > 0 ? (numerator / denominator) * 100 : null

const aromaMargin = (partner: Partner, saved = false): number | null =>
    props.row.cost === null ? null : percent(paidInBase(partner, saved) - props.row.cost, paidInBase(partner, saved))

const siblingPriceIn = (currencyCode: string, saved = false): number | null => {
    const value = (saved ? props.row.sibling?.master_prices : siblingPriceForm.master_prices)?.[currencyCode]?.value

    return value === null || value === undefined ? null : Number(value)
}

const partnerMargin = (partner: Partner, saved = false): number | null => {
    const sells = siblingPriceIn(partner.currency_code, saved)

    return sells === null ? null : percent(sells - paidFor(partner, saved), sells)
}

const marginMoved = (current: number | null, saved: number | null): boolean =>
    current !== null && saved !== null && current.toFixed(1) !== saved.toFixed(1)

const marginClass = (value: number | null): string => {
    if (value === null) {
        return "text-gray-400"
    }
    if (value < 5) {
        return "bg-red-50 text-red-700"
    }
    if (value < 15) {
        return "bg-amber-50 text-amber-700"
    }

    return "bg-green-50 text-green-700"
}

const formatMargin = (value: number | null): string => value === null ? "—" : `${value.toFixed(1)}%`

const useDefault = (partner: Partner) => {
    partnerForm.partner_prices[partner.org_partner_id] = null
}

const savePartnerPrices = () => {
    partnerForm.post(route(props.updateRoute.name, props.updateRoute.parameters), {
        preserveScroll: true,
        onSuccess: () => partnerForm.defaults("partner_prices", cloneDeep(partnerForm.partner_prices)),
    })
}

const saveSibling = (form: typeof siblingPriceForm | typeof siblingRrpForm, field: "master_prices" | "master_rrps") => {
    if (!props.row.sibling) {
        return
    }
    form.post(route(props.row.sibling.update_route.name, props.row.sibling.update_route.parameters), {
        preserveScroll: true,
        onSuccess: () => form.defaults(field, cloneDeep(form[field])),
    })
}

const partnerCostsFor = (saved: boolean): Record<string, number | null> => {
    const costs: Record<string, number | null> = {}
    for (const currencyCode of Object.keys(props.row.sibling?.currencies ?? {})) {
        const rate = props.meta.rates_from_base?.[currencyCode]
        const paidByPartners = props.meta.partners
            .filter(partner => rate && partner.shop_currencies?.includes(currencyCode))
            .map(partner => paidInBase(partner, saved) * rate)

        costs[currencyCode] = paidByPartners.length
            ? Math.round(Math.max(...paidByPartners) * 100) / 100
            : (props.row.sibling?.costs?.[currencyCode] ?? null)
    }

    return costs
}

const siblingPartnerCosts = computed(() => partnerCostsFor(false))

const savedSiblingPartnerCosts = computed(() => partnerCostsFor(true))

const partnersByCurrency = computed(() =>
    Object.keys(props.row.sibling?.currencies ?? {})
        .map(currencyCode => ({
            currencyCode,
            partners: props.meta.partners.filter(partner => partner.shop_currencies?.includes(currencyCode)).map(partner => partner.code),
        }))
        .filter(entry => entry.partners.length > 1)
)

const partnerCodes = computed(() => props.meta.partners.map(partner => partner.code).join(", "))
</script>

<template>
    <div class="w-full rounded-md border border-gray-200" @keydown.enter.prevent>
            <section class="flex min-w-0 flex-col border-b border-gray-200">
                <header class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-3 py-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-gray-700">{{ ctrans("Partner pays :seller", { seller: meta.seller_code }) }}</div>
                        <div class="truncate text-xs text-gray-500">{{ ctrans("Per outer, in the partner's own currency") }}</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                    <span v-if="partnerForm.errors.partner_prices" class="text-xs text-red-600">{{ partnerForm.errors.partner_prices }}</span>
                    <span v-else-if="partnerForm.recentlySuccessful" class="text-xs text-green-600" role="status">{{ ctrans("Saved") }}</span>
                    <span v-else-if="partnerForm.isDirty" class="text-xs text-amber-700">{{ ctrans("Unsaved changes") }}</span>
                    <button
                        type="button"
                        class="disabled:cursor-not-allowed"
                        :disabled="partnerForm.processing || !partnerForm.isDirty"
                        v-tooltip="ctrans('Save partner prices')"
                        :aria-label="ctrans('Save partner prices')"
                        @click="savePartnerPrices"
                    >
                        <FontAwesomeIcon v-if="partnerForm.processing" icon="fad fa-spinner-third" class="animate-spin text-xl" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon v-else-if="partnerForm.isDirty" icon="fad fa-save" class="text-xl" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon v-else icon="fal fa-save" class="text-xl text-gray-300" fixed-width aria-hidden="true" />
                    </button>
                    </div>
                </header>

                <div class="grid grid-cols-1 divide-y divide-gray-200 sm:grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] sm:divide-x sm:divide-y-0">
                    <div v-for="partner in meta.partners" :key="partner.org_partner_id" class="flex min-w-0 flex-col gap-2 px-3 py-3">
                        <div class="flex items-baseline gap-2">
                            <span class="font-semibold text-gray-800">{{ partner.code }}</span>
                            <span class="truncate text-xs text-gray-500">{{ partner.name }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                        <label :for="`partner-price-${partner.org_partner_id}`" class="w-9 shrink-0 text-sm font-medium text-gray-600">{{ partner.currency_code }}</label>
                        <div class="relative min-w-0 flex-1">
                            <InputNumber
                                v-model="partnerForm.partner_prices[partner.org_partner_id]"
                                v-bind="locale.currencyInputOptions(partner.currency_code)"
                                :min="0"
                                :placeholder="locale.currencyFormat(partner.currency_code, defaultPriceFor(partner)).toString()"
                                :inputId="`partner-price-${partner.org_partner_id}`"
                                :aria-label="ctrans(':partner pays :seller', { partner: partner.code, seller: meta.seller_code })"
                                size="small"
                                fluid
                                :pt="{ pcInputText: { root: { class: 'focus:!border-[var(--app-accent)] pr-7' } } }"
                            />
                            <FontAwesomeIcon
                                v-if="customPriceFor(partner) === null"
                                icon="fal fa-link"
                                class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[var(--app-accent)]"
                                v-tooltip="ctrans('Not a custom amount: Price / Outer less :discount% at the live rate', { discount: Math.round(meta.partner_discount * 100) })"
                                fixed-width
                                aria-hidden="true"
                            />
                        </div>
                        </div>

                        <div class="min-h-[1rem] pl-11 text-xs">
                            <template v-if="customPriceFor(partner) === null">
                                <span class="italic text-gray-400">
                                    {{ partner.currency_code === meta.base_currency.code
                                        ? ctrans("Price / Outer −:discount%", { discount: Math.round(meta.partner_discount * 100) })
                                        : ctrans("Price / Outer −:discount% at live rate", { discount: Math.round(meta.partner_discount * 100) }) }}
                                </span>
                            </template>
                            <template v-else>
                                <span v-if="isAboveListPrice(partner)" class="text-amber-700">
                                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                                    {{ ctrans("Higher than Price / Outer :price. Default: :default", {
                                        price: locale.currencyFormat(meta.base_currency.code, row.list_price),
                                        default: locale.currencyFormat(partner.currency_code, defaultPriceFor(partner)),
                                    }) }}
                                </span>
                                <span v-else class="text-gray-500">
                                    {{ partner.currency_code === meta.base_currency.code
                                        ? ctrans("Custom")
                                        : ctrans("Custom · ≈ :amount", { amount: locale.currencyFormat(meta.base_currency.code, paidInBase(partner)) }) }}
                                </span>
                                ·
                                <button type="button" class="text-[var(--app-accent)] hover:underline" @click="useDefault(partner)">
                                    {{ ctrans("Use default") }}
                                </button>
                            </template>
                        </div>

                        <dl class="mt-auto grid grid-cols-2 gap-2 border-t border-gray-100 pt-2 text-xs">
                            <div v-tooltip="ctrans(':seller margin on this sale', { seller: meta.seller_code })">
                                <dt class="text-gray-500">{{ ctrans(":seller margin", { seller: meta.seller_code }) }}</dt>
                                <dd class="flex items-center gap-1">
                                    <span v-if="marginMoved(aromaMargin(partner), aromaMargin(partner, true))" class="tabular-nums text-gray-400" v-tooltip="ctrans('Saved margin')">{{ formatMargin(aromaMargin(partner, true)) }} →</span>
                                    <FlashOnChange :value="aromaMargin(partner)"><span class="inline-block rounded px-1.5 py-0.5 font-semibold tabular-nums" :class="marginClass(aromaMargin(partner))">{{ formatMargin(aromaMargin(partner)) }}</span></FlashOnChange>
                                </dd>
                            </div>
                            <div v-tooltip="ctrans('Partner margin when it sells at the partners\' price below')">
                                <dt class="text-gray-500">{{ ctrans(":partner margin", { partner: partner.code }) }}</dt>
                                <dd class="flex items-center gap-1">
                                    <span v-if="marginMoved(partnerMargin(partner), partnerMargin(partner, true))" class="tabular-nums text-gray-400" v-tooltip="ctrans('Saved margin')">{{ formatMargin(partnerMargin(partner, true)) }} →</span>
                                    <FlashOnChange :value="partnerMargin(partner)"><span class="inline-block rounded px-1.5 py-0.5 font-semibold tabular-nums" :class="marginClass(partnerMargin(partner))">{{ formatMargin(partnerMargin(partner)) }}</span></FlashOnChange>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

            </section>

            <div v-if="row.sibling" class="grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                <section class="flex min-w-0 flex-col">
                    <header class="border-b border-gray-200 bg-gray-50 px-3 py-2" v-tooltip="ctrans('Changes the :class master :code and every shop that follows it', { class: row.sibling.master_shop, code: row.sibling.code })">
                        <div class="truncate text-sm font-semibold text-gray-700">{{ ctrans("Partners' Price / Outer") }}</div>
                        <div class="truncate text-xs text-gray-500">{{ partnerCodes }} {{ ctrans("shops") }}, {{ row.sibling.master_shop }}</div>
                    </header>
                    <div class="px-3 py-2">
                        <PureMultiplePriceCurrency
                            v-model="siblingPriceForm.master_prices"
                            :currencies="row.sibling.currencies"
                            :masterAsset="row.sibling.id"
                            flashMarginChanges
                            type_input="price"
                            :units="row.sibling.units"
                            :costs="siblingPartnerCosts"
                            :baselineCosts="savedSiblingPartnerCosts"
                            :form="siblingPriceForm"
                            :submitForm="() => saveSibling(siblingPriceForm, 'master_prices')"
                        />
                    </div>
                </section>
                <section class="flex min-w-0 flex-col">
                    <header class="border-b border-gray-200 bg-gray-50 px-3 py-2" v-tooltip="ctrans('Changes the :class master :code and every shop that follows it', { class: row.sibling.master_shop, code: row.sibling.code })">
                        <div class="truncate text-sm font-semibold text-gray-700">{{ ctrans("Partners' RRP / Unit") }}</div>
                        <div class="truncate text-xs text-gray-500">{{ partnerCodes }} {{ ctrans("shops") }}, {{ row.sibling.master_shop }}</div>
                    </header>
                    <div class="px-3 py-2">
                        <PureMultiplePriceCurrency
                            v-model="siblingRrpForm.master_rrps"
                            :currencies="row.sibling.currencies"
                            :masterAsset="row.sibling.id"
                            flashMarginChanges
                            type_input="rrp"
                            :units="row.sibling.units"
                            :perUnits="row.sibling.units"
                            :counterpartRecord="siblingPriceForm.master_prices"
                            :baselineCounterpartRecord="row.sibling.master_prices"
                            :form="siblingRrpForm"
                            :submitForm="() => saveSibling(siblingRrpForm, 'master_rrps')"
                        />
                    </div>
                </section>
            </div>
            <div v-else class="p-6 text-center text-sm text-gray-500">
                {{ ctrans("No :code in the :partners shops, so only partner prices can be set.", { code: masterAssetCode ?? "", partners: partnerCodes }) }}
            </div>
        <p v-if="row.sibling" class="border-t border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-500">
            {{ ctrans("Partners' Price / Outer and RRP / Unit change the :class master :code and every shop that follows it.", { class: row.sibling.master_shop, code: row.sibling.code }) }}
                        {{ ctrans("M: the partner's margin over what it pays :seller, live as you type.", { seller: meta.seller_code }) }}
                        <template v-for="entry in partnersByCurrency" :key="entry.currencyCode">
                            {{ ctrans(":currency is shared by :partners: the higher of their prices is used.", { currency: entry.currencyCode, partners: entry.partners.join(" & ") }) }}
                        </template>
        </p>
    </div>
</template>
