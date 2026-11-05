<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 12 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { useFormatTime } from "@/Composables/useFormatTime"
import { computed, ref } from "vue"
import Image from "@common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import PurchaseOrderItemStockInfo from "@/Components/Procurement/PurchaseOrderItemStockInfo.vue"
import PurchaseOrderSuggestButton from "@/Components/Procurement/PurchaseOrderSuggestButton.vue"
import Table from "@/Components/Table/Table.vue"
import ModalMinimumCartonOrder from "@/Components/Procurement/ModalMinimumCartonOrder.vue"
import { useLocaleStore } from "@/Stores/locale"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { getOrderingLevels, suggestionAtOrderingLevel, unitsPerOrderingLevel, type OrderingLevel } from "@/Composables/useOrderingLevel"
import { faCheckCircle, faExclamationCircle } from "@fas"
import { faTrashAlt, faHandPointRight, faBox } from "@fal"

type BucketItem = {
    id: number
    slug: string
    code: string
    name: string
    image: object | null
    bucket: string | null
    supplier_code: string | null
    supplier_name?: string | null
    lead_time_days: number | null
    lead_time_measured: boolean
    our_stock: number | null
    our_days_of_cover: number | null
    recommended_quantity: number | null
    units_per_carton: number | null
    minimum_carton_order: number | null
    cost: number | null
    currency: string | null
    ordered_quantity: number
    units_per_pack: number
    org_exchange: number | null
    has_org_stock: boolean
    org_stock_id?: number | null
    org_stock_code?: string | null
    stock_in_locations: string | null
    other_organisations_drafts: { organisation_code: string, organisation_name: string, reference?: string, buyer_name?: string | null, units: number }[]
    stock_deliveries?: { coming?: { slug: string, reference: string, state_label: string, quantity: number }[], recent_received?: { slug: string, reference: string, received_at: string, quantity: number }[] } | null
    other_open_purchase_orders?: { slug: string | null, reference: string, stage?: string | null, state: string, quantity_ordered: number }[]
    saveRoute?: LineRoute
    deleteRoute?: LineRoute | null
}

type LineRoute = { name: string, parameters: Record<string, number | string>, method: string }

const props = withDefaults(defineProps<{
    items: { data: BucketItem[] }
    orgAgent?: { id: number, slug: string, currency: string }
    addRoute?: { name: string, parameters: (string | number)[] }
    currency?: string
    bucket?: string | null
    bucketLabels: Record<string, { label: string, tone: string }>
    view?: "cards" | "list"
    reloadOnly?: string[]
    tableName?: string
    level?: OrderingLevel
}>(), { bucket: null, view: "list", level: "cartons" })

const bucketChip: Record<string, string> = {
    "red-deep": "bg-red-100 text-red-800",
    red: "bg-red-100 text-red-700",
    orange: "bg-orange-100 text-orange-700",
    amber: "bg-amber-100 text-amber-700",
    yellow: "bg-yellow-100 text-yellow-700",
    green: "bg-green-100 text-green-700",
    slate: "bg-slate-100 text-slate-600",
    violet: "bg-violet-100 text-violet-700",
    gray: "bg-gray-100 text-gray-600",
}

const locale = useLocaleStore()

const orgCurrency = computed(() => props.currency ?? props.orgAgent?.currency ?? "")

const typedUnits = ref<Record<number, number>>({})
const commitTimers: Record<number, ReturnType<typeof setTimeout>> = {}

const unitsPerCarton = (item: BucketItem) => Math.max(1, item.units_per_carton ?? 1)

const orderingLevel = computed(() => getOrderingLevels().find((level) => level.key === props.level) ?? getOrderingLevels()[0])

const isCartonLevel = computed(() => props.level === "cartons")

const unitsFor = (item: BucketItem): number => typedUnits.value[item.id] ?? (Number(item.ordered_quantity) || 0)

const cartonsFor = (item: BucketItem): number => unitsFor(item) / unitsPerCarton(item)

const quantityFor = (item: BucketItem): number => Math.round((unitsFor(item) / unitsPerOrderingLevel(item, props.level)) * 1000) / 1000

const setQuantity = (item: BucketItem, quantity: number) => setUnits(item, quantity * unitsPerOrderingLevel(item, props.level))

function setUnits(item: BucketItem, units: number) {
    typedUnits.value[item.id] = Math.max(0, Math.round(units * 1000) / 1000)
    if (commitTimers[item.id]) {
        clearTimeout(commitTimers[item.id])
    }
    commitTimers[item.id] = setTimeout(() => commit(item), 600)
}

function commit(item: BucketItem) {
    const quantityUnits = unitsFor(item)
    if (quantityUnits === (Number(item.ordered_quantity) || 0)) {
        return
    }

    const forget = () => {
        if (typedUnits.value[item.id] === quantityUnits) {
            delete typedUnits.value[item.id]
        }
    }
    const refuse = (message?: string) => {
        delete typedUnits.value[item.id]
        notify({
            title: ctrans("Not saved"),
            text: message ?? ctrans("The quantity could not be saved, it is back to what is on the draft"),
            type: "error",
        })
    }

    if (item.saveRoute) {
        const lineRoute = quantityUnits === 0 && item.deleteRoute ? item.deleteRoute : item.saveRoute
        axios({ method: lineRoute.method, url: route(lineRoute.name, lineRoute.parameters), data: { quantity_ordered: quantityUnits } })
            .then(() => router.reload({ ...(props.reloadOnly ? { only: props.reloadOnly } : {}), onSuccess: forget }))
            .catch((error) => refuse(error?.response?.data?.message))
        return
    }

    if (!props.addRoute) {
        return
    }

    router.post(
        route(props.addRoute.name, [...props.addRoute.parameters, item.id]),
        { quantity_units: quantityUnits },
        {
            preserveScroll: true,
            preserveState: true,
            ...(props.reloadOnly ? { only: props.reloadOnly } : {}),
            onSuccess: forget,
            onError: (errors) => refuse(Object.values(errors)[0]),
        }
    )
}

const isPartCarton = (item: BucketItem) => (item.ordered_quantity ?? 0) % unitsPerCarton(item) !== 0

const packOf = (item: BucketItem) => Number(item.units_per_pack) || 1

const typedSkos = computed<Record<number, number>>(() =>
    Object.fromEntries(props.items.data.map((item) => [item.id, unitsFor(item) / packOf(item)]))
)

const otherCartons = (item: BucketItem) => (item.other_organisations_drafts ?? []).reduce((units, draft) => units + draft.units, 0) / unitsPerCarton(item)

const suggestedCartons = (item: BucketItem, skos: number) =>
    Math.max(Math.ceil((item.minimum_carton_order ?? 1) - otherCartons(item)), Math.ceil((skos * packOf(item)) / unitsPerCarton(item)))

const suggestedQuantity = (item: BucketItem, skos: number) =>
    isCartonLevel.value ? suggestedCartons(item, skos) : suggestionAtOrderingLevel(item, skos, props.level)

const suggestionLabel = (item: BucketItem, skos: number) => {
    const quantity = suggestedQuantity(item, skos)
    if (isCartonLevel.value) {
        return ctransChoice(":number carton|:number cartons", quantity, { number: locale.number(quantity) })
    }
    return `${locale.number(quantity)} ${quantity === 1 ? orderingLevel.value.singular : orderingLevel.value.quantity}`
}

const suggestedSkos = (item: BucketItem, skos: number) =>
    isCartonLevel.value ? (suggestedCartons(item, skos) * unitsPerCarton(item)) / packOf(item) : skos

const applySuggestion = (item: BucketItem, skos: number) => setQuantity(item, suggestedQuantity(item, skos))

const editMinimumOf = ref<BucketItem | null>(null)
const isMinimumModalOpen = computed({
    get: () => editMinimumOf.value !== null,
    set: (isOpen: boolean) => {
        if (!isOpen) {
            editMinimumOf.value = null
        }
    },
})
const reloadItems = () => router.reload(props.reloadOnly ? { only: props.reloadOnly } : {})

const roundCartons = (cartons: number) => locale.number(Math.round(cartons * 100) / 100)

const moqLabel = (item: BucketItem) => {
    if (!item.minimum_carton_order) {
        return `${ctrans("MOQ")} (${ctrans("not set")})`
    }
    if (!groupCartons(item)) {
        return `${ctrans("MOQ")} ${ctransChoice(":number carton|:number cartons", item.minimum_carton_order, { number: locale.number(item.minimum_carton_order) })}`
    }

    return ctrans(":ours:others / :moq MOQ", {
        ours: roundCartons(cartonsFor(item)),
        others: otherCartons(item) ? ` (${roundCartons(otherCartons(item))})` : "",
        moq: locale.number(item.minimum_carton_order),
    })
}

const isMoqShort = (item: BucketItem) => groupCartons(item) > 0 && !isMoqMet(item)

const pokingId = ref<number | null>(null)

const pokeOtherOrganisations = async (item: BucketItem) => {
    pokingId.value = item.id
    try {
        const response = await axios.post(route("grp.models.org_supplier_product.minimum_poke", { orgSupplierProduct: item.id }), {
            cartons_short: Math.round((item.minimum_carton_order! - groupCartons(item)) * 100) / 100,
        })
        const notified = response.data.notified as number
        notify(notified
            ? { title: ctrans("Asked"), text: ctransChoice(":count buyer asked to add to their draft|:count buyers asked to add to their drafts", notified, { count: notified }), type: "success" }
            : { title: ctrans("Nobody to ask"), text: ctrans("No other organisation has a draft with this supplier, or its buyer was asked in the last 15 minutes"), type: "info" })
    } catch {
        notify({ title: ctrans("Something went wrong"), text: ctrans("The other organisations could not be asked"), type: "error" })
    } finally {
        pokingId.value = null
    }
}

const otherDraftsSummary = (item: BucketItem) => (item.other_organisations_drafts ?? [])
    .map((draft) => [draft.organisation_code, draft.reference, draft.buyer_name, draftCartonsLabel(item, draft.units)].filter(Boolean).join(" · "))
    .join("; ")

const moqLineTooltip = (item: BucketItem) => [
    isMoqShort(item)
        ? ctrans("Short of the supplier's minimum of :moq by :short: :ours on this order", {
            moq: ctransChoice(":number carton|:number cartons", item.minimum_carton_order!, { number: locale.number(item.minimum_carton_order!) }),
            short: ctransChoice(":number carton|:number cartons", item.minimum_carton_order! - groupCartons(item), { number: roundCartons(item.minimum_carton_order! - groupCartons(item)) }),
            ours: ctransChoice(":number carton|:number cartons", cartonsFor(item), { number: roundCartons(cartonsFor(item)) }),
        })
        : (cartonsFor(item) && item.minimum_carton_order ? moqTooltip(item) : ""),
    otherDraftsSummary(item) ? ctrans("Other organisations' drafts: :drafts", { drafts: otherDraftsSummary(item) }) : "",
    ctrans("Double-click to change the minimum order"),
].filter(Boolean).join(". ")

const draftCartonsLabel = (item: BucketItem, units: number) => {
    const cartons = Math.round((units / unitsPerCarton(item)) * 100) / 100
    return ctransChoice(":number carton|:number cartons", cartons, { number: locale.number(cartons) })
}

const groupCartons = (item: BucketItem) => cartonsFor(item) + otherCartons(item)

const isMoqMet = (item: BucketItem) => !item.minimum_carton_order || groupCartons(item) >= item.minimum_carton_order

const moqTooltip = (item: BucketItem) =>
    item.minimum_carton_order
        ? ctrans("The supplier's minimum of :moq is met: :ordered on drafts, every organisation counted", {
            moq: ctransChoice(":number carton|:number cartons", item.minimum_carton_order, { number: locale.number(item.minimum_carton_order) }),
            ordered: ctransChoice(":number carton|:number cartons", groupCartons(item), { number: locale.number(Math.round(groupCartons(item) * 100) / 100) }),
        })
        : ctrans("This product has no minimum order")

type OnTheWayRow = { key: string, label: string, tone: string, reference: string, tooltip: string, href: string | null, units: number }

const onTheWayRows = (item: BucketItem): OnTheWayRow[] => [
    ...(item.other_organisations_drafts ?? []).map((draft) => ({
        key: `draft-${draft.organisation_code}`,
        label: ctrans("Draft"),
        tone: "text-gray-500",
        reference: draft.organisation_code,
        tooltip: [draft.organisation_name, draft.reference, draft.buyer_name].filter(Boolean).join(" · "),
        href: null,
        units: draft.units,
    })),
    ...(item.stock_deliveries?.coming ?? []).map((delivery) => ({
        key: `delivery-${delivery.slug}`,
        label: delivery.state_label,
        tone: "text-emerald-700",
        reference: delivery.reference,
        tooltip: delivery.reference,
        href: stockDeliveryRoute(delivery.slug),
        units: Number(delivery.quantity) || 0,
    })),
    ...(item.other_open_purchase_orders ?? []).map((order) => ({
        key: `order-${order.slug ?? order.reference}`,
        label: order.stage ?? (order.state === "sent" ? ctrans("Requested") : ctrans("Ordered")),
        tone: "text-amber-700",
        reference: order.reference,
        tooltip: order.reference,
        href: order.slug ? route("grp.org.procurement.purchase_orders.show", [route().params.organisation, order.slug]) : null,
        units: Number(order.quantity_ordered) || 0,
    })),
]

const pastDeliveriesShown = ref<Record<number, boolean>>({})

const recentDeliveries = (item: BucketItem) => item.stock_deliveries?.recent_received ?? []

const ownSkoCode = (item: BucketItem) => (item.org_stock_code && item.org_stock_code !== item.code ? item.org_stock_code : null)

const stockDeliveryRoute = (slug: string) => route("grp.org.procurement.stock_deliveries.show", [route().params.organisation, slug])

const amountOf = (item: BucketItem) => unitsFor(item) * Number(item.cost ?? 0)

const orgAmountOf = (item: BucketItem) =>
    item.org_exchange && item.currency && item.currency !== orgCurrency.value ? amountOf(item) * item.org_exchange : null

const quantityBreakdown = (item: BucketItem) => {
    const units = unitsFor(item)
    const skos = units / packOf(item)
    const parts = [
        ctransChoice(":number carton|:number cartons", cartonsFor(item), { number: roundCartons(cartonsFor(item)) }),
        ctransChoice(":number SKO|:number SKOs", skos, { number: locale.number(Math.round(skos * 100) / 100) }),
    ]
    if (packOf(item) !== 1) {
        parts.push(ctransChoice(":number unit|:number units", units, { number: locale.number(units) }))
    }
    return parts.join(" · ")
}
</script>

<template>
    <div>
            <div v-if="view === 'cards'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <div v-for="item in items.data" :key="item.id" class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <div class="aspect-square w-full bg-gray-50">
                        <Image v-if="item.image" :src="item.image" imageCover />
                    </div>
                    <div class="flex flex-1 flex-col gap-1 p-2">
                        <p class="truncate text-sm font-medium text-gray-800" :title="item.name">{{ item.name }}</p>
                        <p class="flex items-center justify-between gap-2 text-xs text-gray-400">
                            <span class="truncate">
                                {{ item.code }}
                                <template v-if="ownSkoCode(item)"> ({{ ownSkoCode(item) }})</template>
                                <template v-if="item.supplier_code"> · {{ item.supplier_code }}</template>
                            </span>
                            <span
                                v-if="bucket === null && item.bucket && bucketLabels[item.bucket]"
                                v-tooltip="bucketLabels[item.bucket].label"
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                :class="bucketChip[bucketLabels[item.bucket].tone] ?? 'bg-gray-100 text-gray-600'"
                            >{{ bucketLabels[item.bucket].label.split(":")[0] }}</span>
                        </p>
                        <div class="space-y-0.5">
                            <div class="text-xs text-gray-500">
                                {{ ctrans("Unit cost") }}: <span class="tabular-nums text-gray-700">{{ item.cost !== null ? locale.currencyFormat(item.currency ?? orgCurrency, item.cost) : "-" }}</span>
                                <template v-if="item.units_per_carton"> · {{ ctrans(":count per carton", { count: locale.number(item.units_per_carton) }) }}</template>
                            </div>
                            <div v-if="item.lead_time_days" class="text-xs text-gray-500">
                                {{ ctrans("Lead time") }}: <span class="tabular-nums" :class="item.lead_time_measured ? 'text-gray-700' : 'text-gray-400'">{{ ctrans(":days d", { days: item.lead_time_days }) }} {{ item.lead_time_measured ? ctrans("measured") : ctrans("estimate") }}</span>
                            </div>
                            <div v-if="!item.has_org_stock" class="text-xs font-medium text-violet-600">{{ ctrans("never stocked") }}</div>
                            <PurchaseOrderItemStockInfo :item="item" :typedSkosById="typedSkos" hideComing :inCartons="isCartonLevel" />
                        </div>

                        <div class="mt-auto flex items-end justify-between gap-3 pt-2">
                            <div class="text-xs">
                                <div v-if="cartonsFor(item)" class="space-y-0.5 tabular-nums">
                                    <div class="text-gray-500">
                                        {{ quantityBreakdown(item) }}
                                    </div>
                                    <div v-if="item.cost !== null" class="font-medium text-gray-800">
    {{ locale.currencyFormat(item.currency ?? orgCurrency, amountOf(item)) }}
    <span v-if="orgAmountOf(item) !== null" class="font-normal text-gray-500">({{ locale.currencyFormat(orgCurrency, orgAmountOf(item)!) }})</span>
</div>
                                <div v-if="isCartonLevel && isPartCarton(item) && typedUnits[item.id] === undefined" class="text-amber-600">{{ ctrans("The draft holds :units units, not whole cartons", { units: locale.number(item.ordered_quantity) }) }}</div>
                                </div>
<div v-if="cartonsFor(item) || item.minimum_carton_order" v-tooltip="moqLineTooltip(item)" class="flex cursor-pointer select-none items-center gap-1 pt-1.5 tabular-nums" :class="isMoqShort(item) ? 'font-medium text-red-600' : 'text-gray-500'" @dblclick="editMinimumOf = item">
    <FontAwesomeIcon v-if="isMoqShort(item)" :icon="faExclamationCircle" class="text-red-600" aria-hidden="true" />
    <FontAwesomeIcon v-else-if="cartonsFor(item) && isMoqMet(item)" :icon="faCheckCircle" class="text-green-600" aria-hidden="true" />
    <span>{{ moqLabel(item) }}</span>
</div>
<Button v-if="isMoqShort(item)" type="tertiary" size="xxs" class="mt-1" :label="ctrans('Ask other organisations')" :tooltip="ctrans('Asks the buyers of the other organisations\' drafts with this supplier to add some, so the orders together reach the minimum')" :loading="pokingId === item.id" @click="pokeOtherOrganisations(item)">
    <template #icon>
        <FontAwesomeIcon :icon="faHandPointRight" fixed-width aria-hidden="true" />
    </template>
</Button>
                            </div>
                            <div class="flex flex-col items-end">
                                <NumberWithButtonSave
                                    :modelValue="quantityFor(item)"
                                    :bindToTarget="{ min: 0 }"
                                    allowZero
                                    isWithRefreshModel
                                    noUndoButton
                                    noSaveButton
                                    @update:modelValue="(value: number) => setQuantity(item, value)"
                                />
                                <PurchaseOrderSuggestButton :item="item" :typedSkosById="typedSkos" :quantityLabel="(skos: number) => suggestionLabel(item, skos)" :appliedSkos="(skos: number) => suggestedSkos(item, skos)" @suggest="(skos: number) => applySuggestion(item, skos)" />
                            </div>
                        </div>
<table v-if="onTheWayRows(item).length" class="mt-2 w-full border-collapse border border-gray-200 text-left text-xs text-gray-600">
    <tr v-for="row in onTheWayRows(item)" :key="row.key" class="border-b border-gray-200 last:border-b-0">
        <td class="px-1.5 py-0.5 text-[10px] uppercase tracking-wide" :class="row.tone">{{ row.label }}</td>
        <td class="w-full px-1.5 py-0.5">
            <Link v-if="row.href" v-tooltip="row.tooltip" :href="row.href" class="block max-w-[9rem] truncate font-mono text-gray-700 hover:underline">{{ row.reference }}</Link>
            <span v-else v-tooltip="row.tooltip" class="font-medium text-gray-700">{{ row.reference }}</span>
        </td>
        <td class="whitespace-nowrap px-1.5 py-0.5 text-right tabular-nums">{{ draftCartonsLabel(item, row.units) }}</td>
    </tr>
</table>
<div v-if="recentDeliveries(item).length" class="mt-1 flex flex-col items-start text-xs text-gray-400">
    <button type="button" class="hover:text-gray-600" @click="pastDeliveriesShown[item.id] = !pastDeliveriesShown[item.id]">
        {{ ctrans("Last arrived :date", { date: useFormatTime(recentDeliveries(item)[0].received_at) }) }}
        <span class="text-[10px]">{{ pastDeliveriesShown[item.id] ? "▴" : "▾" }}</span>
    </button>
    <table v-if="pastDeliveriesShown[item.id]" class="mt-1 w-full border-collapse border border-gray-200 text-left">
        <tr v-for="delivery in recentDeliveries(item)" :key="delivery.slug" class="border-b border-gray-200 last:border-b-0">
            <td class="whitespace-nowrap px-1.5 py-0.5">{{ useFormatTime(delivery.received_at) }}</td>
            <td class="w-full px-1.5 py-0.5"><Link v-tooltip="delivery.reference" :href="stockDeliveryRoute(delivery.slug)" class="block max-w-[9rem] truncate font-mono hover:text-gray-600 hover:underline">{{ delivery.reference }}</Link></td>
            <td class="whitespace-nowrap px-1.5 py-0.5 text-right tabular-nums">{{ draftCartonsLabel(item, delivery.quantity) }}</td>
        </tr>
    </table>
</div>
                    </div>
                </div>
                <p v-if="!items.data.length" class="col-span-full py-10 text-center text-sm text-gray-400">
                    {{ ctrans("No products in this bucket") }}
                </p>
            </div>

            <Table v-else :resource="items" :name="tableName" class="mt-0" rowAlignTop>
                <template #cell(code)="{ item }">
                    <div class="font-medium text-gray-800">{{ item.code }}</div>
                    <Link
                        v-if="ownSkoCode(item) && item.org_stock_id"
                        v-tooltip="ctrans('SKU code')"
                        :href="route('grp.majordomo.redirect_org_stock', [item.org_stock_id])"
                        class="whitespace-nowrap text-xs text-gray-500 hover:text-gray-700"
                    >
                        <FontAwesomeIcon :icon="faBox" aria-hidden="true" fixed-width />
                        {{ ownSkoCode(item) }}
                    </Link>
                </template>
                <template #cell(image)="{ item }">
                    <div class="h-20 w-20 overflow-hidden rounded bg-gray-50">
                        <Image v-if="item.image" :src="item.image" imageCover />
                    </div>
                </template>
                <template #cell(name)="{ item }">
                    <div class="space-y-0.5">
                        <div class="text-gray-900">{{ item.name }}</div>
                        <div class="text-xs text-gray-500">
                            {{ ctrans("Unit cost") }}: <span class="tabular-nums text-gray-700">{{ item.cost !== null ? locale.currencyFormat(item.currency ?? orgCurrency, item.cost) : "-" }}</span>
                            <template v-if="item.units_per_carton"> · {{ ctrans(":count per carton", { count: locale.number(item.units_per_carton) }) }}</template>
                        </div>
                        <div v-if="item.lead_time_days" class="text-xs text-gray-500">
                            {{ ctrans("Lead time") }}: <span class="tabular-nums" :class="item.lead_time_measured ? 'text-gray-700' : 'text-gray-400'">{{ ctrans(":days d", { days: item.lead_time_days }) }} {{ item.lead_time_measured ? ctrans("measured") : ctrans("estimate") }}</span>
                        </div>
                        <div v-if="!item.has_org_stock" class="text-xs font-medium text-violet-600">{{ ctrans("never stocked") }}</div>
                        <PurchaseOrderItemStockInfo :item="item" :typedSkosById="typedSkos" hideComing :inCartons="isCartonLevel" />
                    </div>
                </template>
                <template #cell(bucket)="{ item }">
                    <span
                        v-if="item.bucket && bucketLabels[item.bucket]"
                        v-tooltip="bucketLabels[item.bucket].label"
                        class="whitespace-nowrap rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                        :class="bucketChip[bucketLabels[item.bucket].tone] ?? 'bg-gray-100 text-gray-600'"
                    >{{ bucketLabels[item.bucket].label.split(":")[0] }}</span>
                </template>
                <template #cell(subtotals)="{ item }">
                    <div v-if="cartonsFor(item)" class="space-y-0.5 tabular-nums">
                        <div class="text-gray-500">
                            {{ quantityBreakdown(item) }}
                        </div>
                        <div v-if="item.cost !== null" class="font-medium text-gray-800">
    {{ locale.currencyFormat(item.currency ?? orgCurrency, amountOf(item)) }}
    <span v-if="orgAmountOf(item) !== null" class="font-normal text-gray-500">({{ locale.currencyFormat(orgCurrency, orgAmountOf(item)!) }})</span>
</div>
                                <div v-if="isCartonLevel && isPartCarton(item) && typedUnits[item.id] === undefined" class="text-amber-600">{{ ctrans("The draft holds :units units, not whole cartons", { units: locale.number(item.ordered_quantity) }) }}</div>
                    </div>
<div v-if="cartonsFor(item) || item.minimum_carton_order" v-tooltip="moqLineTooltip(item)" class="flex cursor-pointer select-none items-center gap-1 pt-1.5 tabular-nums" :class="isMoqShort(item) ? 'font-medium text-red-600' : 'text-gray-500'" @dblclick="editMinimumOf = item">
    <FontAwesomeIcon v-if="isMoqShort(item)" :icon="faExclamationCircle" class="text-red-600" aria-hidden="true" />
    <FontAwesomeIcon v-else-if="cartonsFor(item) && isMoqMet(item)" :icon="faCheckCircle" class="text-green-600" aria-hidden="true" />
    <span>{{ moqLabel(item) }}</span>
</div>
<Button v-if="isMoqShort(item)" type="tertiary" size="xxs" class="mt-1" :label="ctrans('Ask other organisations')" :tooltip="ctrans('Asks the buyers of the other organisations\' drafts with this supplier to add some, so the orders together reach the minimum')" :loading="pokingId === item.id" @click="pokeOtherOrganisations(item)">
    <template #icon>
        <FontAwesomeIcon :icon="faHandPointRight" fixed-width aria-hidden="true" />
    </template>
</Button>
                </template>
                <template #table-header-cartons>{{ orderingLevel.quantity }}</template>
                <template #cell(cartons)="{ item }">
                    <div class="flex flex-col items-end">
                        <div class="flex items-center gap-3">
                            <Button v-if="cartonsFor(item)" type="tertiary" size="xs" :tooltip="ctrans('Remove this product from the purchase order')" :aria-label="ctrans('Remove')" @click="setUnits(item, 0)">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <NumberWithButtonSave
                                :modelValue="quantityFor(item)"
                                :bindToTarget="{ min: 0 }"
                                allowZero
                                isWithRefreshModel
                                noUndoButton
                                noSaveButton
                                @update:modelValue="(value: number) => setQuantity(item, value)"
                            />
                        </div>
                        <PurchaseOrderSuggestButton :item="item" :typedSkosById="typedSkos" :quantityLabel="(skos: number) => suggestionLabel(item, skos)" :appliedSkos="(skos: number) => suggestedSkos(item, skos)" @suggest="(skos: number) => applySuggestion(item, skos)" />
<table v-if="onTheWayRows(item).length" class="mt-1.5 border-collapse border border-gray-200 text-left text-xs text-gray-600">
    <tr v-for="row in onTheWayRows(item)" :key="row.key" class="border-b border-gray-200 last:border-b-0">
        <td class="px-1.5 py-0.5 text-[10px] uppercase tracking-wide" :class="row.tone">{{ row.label }}</td>
        <td class="px-1.5 py-0.5">
            <Link v-if="row.href" v-tooltip="row.tooltip" :href="row.href" class="block max-w-[9rem] truncate font-mono text-gray-700 hover:underline">{{ row.reference }}</Link>
            <span v-else v-tooltip="row.tooltip" class="font-medium text-gray-700">{{ row.reference }}</span>
        </td>
        <td class="whitespace-nowrap px-1.5 py-0.5 text-right tabular-nums">{{ draftCartonsLabel(item, row.units) }}</td>
    </tr>
</table>
<div v-if="recentDeliveries(item).length" class="mt-1 flex flex-col items-end text-xs text-gray-400">
    <button type="button" class="hover:text-gray-600" @click="pastDeliveriesShown[item.id] = !pastDeliveriesShown[item.id]">
        {{ ctrans("Last arrived :date", { date: useFormatTime(recentDeliveries(item)[0].received_at) }) }}
        <span class="text-[10px]">{{ pastDeliveriesShown[item.id] ? "▴" : "▾" }}</span>
    </button>
    <table v-if="pastDeliveriesShown[item.id]" class="mt-1 border-collapse border border-gray-200 text-left">
        <tr v-for="delivery in recentDeliveries(item)" :key="delivery.slug" class="border-b border-gray-200 last:border-b-0">
            <td class="whitespace-nowrap px-1.5 py-0.5">{{ useFormatTime(delivery.received_at) }}</td>
            <td class="px-1.5 py-0.5"><Link v-tooltip="delivery.reference" :href="stockDeliveryRoute(delivery.slug)" class="block max-w-[9rem] truncate font-mono hover:text-gray-600 hover:underline">{{ delivery.reference }}</Link></td>
            <td class="whitespace-nowrap px-1.5 py-0.5 text-right tabular-nums">{{ draftCartonsLabel(item, delivery.quantity) }}</td>
        </tr>
    </table>
</div>
                    </div>
                </template>
            </Table>
        <ModalMinimumCartonOrder v-model:visible="isMinimumModalOpen" :product="editMinimumOf && { id: editMinimumOf.id, code: editMinimumOf.code, name: editMinimumOf.name, supplier_name: editMinimumOf.supplier_name ?? editMinimumOf.supplier_code, minimum_carton_order: editMinimumOf.minimum_carton_order }" @saved="reloadItems" />
    </div>
</template>
