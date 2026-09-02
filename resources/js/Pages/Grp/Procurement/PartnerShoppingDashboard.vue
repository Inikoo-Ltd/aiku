<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 29 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref, computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faExclamationTriangle,
    faWallet,
    faWarehouse,
    faStopwatch,
    faLayerGroup,
    faChevronRight,
    faMagic,
    faTrashAlt,
    faCheck,
    faSkullCrossbones,
    faSeedling,
    faShoppingBasket,
    faClipboardList,
    faBoxCheck,
    faShippingFast,
    faDolly,
    faTruck,
    faClock,
} from "@fal"
import ModalAutoFillShoppingList from "@/Components/Procurement/ModalAutoFillShoppingList.vue"

library.add(
    faExclamationTriangle,
    faWallet,
    faWarehouse,
    faStopwatch,
    faLayerGroup,
    faChevronRight,
    faMagic,
    faTrashAlt,
    faCheck,
    faSkullCrossbones,
    faSeedling,
    faShoppingBasket,
    faClipboardList,
    faBoxCheck,
    faShippingFast,
    faDolly,
    faTruck,
    faClock
)

type Tone = "red-deep" | "red" | "orange" | "amber" | "yellow" | "green" | "violet" | "gray"
type PriorityBreakdown = { priority: string, label: string, count: number }
type RankBreakdown = { rank: string, count: number, on_list: number }
type CoverBucket = { bucket: string, label: string, tone: Tone, count: number, on_list: number, on_the_way: number, untouched: number, stock_value: number, ranks: RankBreakdown[] }
type OrderCapacity = {
    partner_capacity: { delivers_to_us_per_30d: number | null, source: "measured" | "estimate", samples: number }
    list: { value: number, lines: number }
    warehouse: { total_locations: number, empty_locations: number, free_ratio: number | null, inbound_open_po_lines: number, partner_share_used: number, partner_share_limit: number }
    blocked: { at_capacity: boolean, warehouse_full: boolean }
}
type OpenStockDelivery = { id: number, slug: string, reference: string, state: string, items: number, days_in_transit: number | null, date: string, days_old: number }
type LatePurchaseOrder = { id: number, slug: string, reference: string, state: string, days_late: number, no_eta: boolean }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgPartner: { id: number, slug: string, name: string, currency: string }
    browseRoute: routeType
    shoppingListRoute: routeType
    canBrowse: boolean
    stats: { open_items_count: number, oldest_item_at: string | null, estimated_total: number, priority_breakdown: PriorityBreakdown[] }
    coverBuckets: CoverBucket[]
    coverTotal: number
    leadTime: { days: number, source: "measured" | "estimate", samples: number }
    leadTimeRoute: routeType
    latePurchaseOrders: LatePurchaseOrder[]
    openStockDeliveries: OpenStockDelivery[]
    stockDeliveriesRoute: routeType
    orderCapacity: OrderCapacity
}>()

const locale = useLocaleStore()

const tonePalette: Record<Tone, { text: string, soft: string, accent: string, chip: string, hex: string }> = {
    "red-deep": { text: "text-red-800", soft: "bg-red-50", accent: "bg-red-800", chip: "bg-red-100 text-red-800", hex: "#991b1b" },
    red: { text: "text-red-600", soft: "bg-red-50", accent: "bg-red-500", chip: "bg-red-100 text-red-700", hex: "#ef4444" },
    orange: { text: "text-orange-600", soft: "bg-orange-50", accent: "bg-orange-500", chip: "bg-orange-100 text-orange-700", hex: "#f97316" },
    amber: { text: "text-amber-600", soft: "bg-amber-50", accent: "bg-amber-500", chip: "bg-amber-100 text-amber-700", hex: "#f59e0b" },
    yellow: { text: "text-yellow-600", soft: "bg-yellow-50", accent: "bg-yellow-400", chip: "bg-yellow-100 text-yellow-700", hex: "#facc15" },
    green: { text: "text-green-700", soft: "bg-green-50", accent: "bg-green-500", chip: "bg-green-100 text-green-700", hex: "#22c55e" },
    violet: { text: "text-violet-700", soft: "bg-violet-50", accent: "bg-violet-400", chip: "bg-violet-100 text-violet-700", hex: "#a78bfa" },
    gray: { text: "text-gray-600", soft: "bg-gray-100", accent: "bg-gray-300", chip: "bg-gray-100 text-gray-600", hex: "#d1d5db" },
}

const passiveBucketKeys = ["ok", "dead", "never"]
const passiveBucketIcons: Record<string, typeof faCheck> = { ok: faCheck, dead: faSkullCrossbones, never: faSeedling }
const rankClasses: Record<string, string> = { A: "", B: "", C: "", D: "opacity-50", Z: "opacity-50" }

const isPassiveBucket = (bucket: CoverBucket) => passiveBucketKeys.includes(bucket.bucket)
const orderingBuckets = computed(() => props.coverBuckets.filter((bucket) => !isPassiveBucket(bucket)))
const passiveBuckets = computed(() => props.coverBuckets.filter(isPassiveBucket))
const orderingCount = computed(() => orderingBuckets.value.reduce((sum, bucket) => sum + bucket.count, 0))

const coverPercentLabel = (bucket: CoverBucket) => {
    if (!props.coverTotal || !bucket.count) {
        return "0%"
    }
    const percent = (bucket.count / props.coverTotal) * 100
    return percent < 1 ? "<1%" : `${Math.round(percent)}%`
}

const DONUT_CENTER = 90
const DONUT_OUTER_RADIUS = 86
const DONUT_INNER_RADIUS = 56

const donutPoint = (radius: number, angle: number) => `${DONUT_CENTER + radius * Math.cos(angle)} ${DONUT_CENTER + radius * Math.sin(angle)}`

const donutArcPath = (start: number, end: number) => {
    const cappedEnd = Math.min(end, start + Math.PI * 2 - 0.001)
    const largeArc = cappedEnd - start > Math.PI ? 1 : 0
    return [
        `M ${donutPoint(DONUT_OUTER_RADIUS, start)}`,
        `A ${DONUT_OUTER_RADIUS} ${DONUT_OUTER_RADIUS} 0 ${largeArc} 1 ${donutPoint(DONUT_OUTER_RADIUS, cappedEnd)}`,
        `L ${donutPoint(DONUT_INNER_RADIUS, cappedEnd)}`,
        `A ${DONUT_INNER_RADIUS} ${DONUT_INNER_RADIUS} 0 ${largeArc} 0 ${donutPoint(DONUT_INNER_RADIUS, start)}`,
        "Z",
    ].join(" ")
}

const donutSegments = computed(() => {
    if (!props.coverTotal) {
        return []
    }
    let angle = -Math.PI / 2
    return props.coverBuckets
        .filter((bucket) => bucket.count > 0)
        .map((bucket) => {
            const sweep = (bucket.count / props.coverTotal) * Math.PI * 2
            const segment = { bucket, path: donutArcPath(angle, angle + sweep) }
            angle += sweep
            return segment
        })
})

const segmentWidth = (bucket: CoverBucket, part: number) => (bucket.count ? `${Math.max((part / bucket.count) * 100, part ? 1.5 : 0)}%` : "0%")
const shouldNotBeOrdered = (bucket: CoverBucket) => ["ok", "dead"].includes(bucket.bucket) && bucket.on_list > 0
const needsAction = (bucket: CoverBucket) => !isPassiveBucket(bucket) && bucket.untouched > 0
const canAutoFill = (bucket: CoverBucket) => !isPassiveBucket(bucket) && bucket.ranks.some((rank) => rank.count > rank.on_list)
const visibleRanks = (bucket: CoverBucket) => bucket.ranks.filter((rank) => rank.count > 0)

const capacityShare = computed(() => {
    const cap = props.orderCapacity.partner_capacity.delivers_to_us_per_30d
    return cap ? Math.min(props.orderCapacity.list.value / cap, 1) : null
})

const meterTone = (share: number) => (share >= 1 ? "bg-red-500" : share >= 0.8 ? "bg-amber-500" : "bg-green-500")

const usedLocations = computed(() => props.orderCapacity.warehouse.total_locations - props.orderCapacity.warehouse.empty_locations)
const warehouseSegment = (part: number) => `${(part / Math.max(props.orderCapacity.warehouse.total_locations, 1)) * 100}%`
const warehouseLegend = computed(() => [
    { label: ctrans("in use"), value: usedLocations.value, dot: "bg-gray-400" },
    { label: ctrans("inbound PO/SD lines"), value: props.orderCapacity.warehouse.inbound_open_po_lines, dot: "bg-indigo-400" },
    { label: ctrans("this shopping list"), value: props.orderCapacity.list.lines, dot: "bg-violet-400" },
])

const problemThreshold = computed(() => Math.max(props.leadTime.days * 10, 30))
const agingThreshold = computed(() => Math.max(props.leadTime.days * 3, 14))
const isProblemOrder = (sd: OpenStockDelivery) => sd.days_old > problemThreshold.value
const isAgingOrder = (sd: OpenStockDelivery) => sd.days_old > agingThreshold.value
const ageBadgeClasses = (sd: OpenStockDelivery) => (isProblemOrder(sd) ? "bg-red-100 text-red-700" : isAgingOrder(sd) ? "bg-amber-100 text-amber-700" : "bg-gray-100 text-gray-500")
const deliveryCardClasses = (sd: OpenStockDelivery) => (isProblemOrder(sd) ? "border-red-300 bg-red-50/70" : isAgingOrder(sd) ? "border-amber-300 bg-white" : "border-gray-200 bg-white")

const stockDeliveryColumns = computed(() =>
    [
        { key: "being_prepared", label: ctrans("Being prepared"), states: ["in_process", "confirmed"], icon: faClipboardList, iconClasses: "bg-indigo-50 text-indigo-500" },
        { key: "ready_to_ship", label: ctrans("Ready to ship"), states: ["ready_to_ship"], icon: faBoxCheck, iconClasses: "bg-indigo-100 text-indigo-600" },
        { key: "in_transit", label: ctrans("In transit"), states: ["dispatched"], icon: faShippingFast, iconClasses: "bg-indigo-500 text-white" },
        { key: "arrived", label: ctrans("Arrived, booking in"), states: ["received", "checked", "booking_in"], icon: faDolly, iconClasses: "bg-indigo-700 text-white" },
    ].map((column) => {
        const deliveries = props.openStockDeliveries.filter((sd) => column.states.includes(sd.state))
        return { ...column, deliveries, items: deliveries.reduce((sum, sd) => sum + sd.items, 0) }
    })
)

const bucketRoute = (bucket: string, rank?: string) => `${route(props.browseRoute.name, props.browseRoute.parameters)}?cover=${bucket}${rank ? `&rank=${rank}` : ""}`
const bucketItemsRoute = (bucket: string) => `${route("grp.org.procurement.org_partners.show.shopping.items.index", [route().params.organisation, props.orgPartner.id])}?cover=${bucket}`
const stockDeliveryRoute = (sd: OpenStockDelivery) => route("grp.org.procurement.org_partners.show.stock-deliveries.show", [route().params.organisation, props.orgPartner.id, sd.slug])
const purchaseOrderRoute = (purchaseOrder: LatePurchaseOrder) => route("grp.org.procurement.org_partners.show.purchase-orders.show", [route().params.organisation, props.orgPartner.id, purchaseOrder.slug])

const dashboardReload = { only: ["coverBuckets", "coverTotal", "orderCapacity", "stats"], preserveScroll: true }

const removeMisplaced = (bucket: string) => {
    router.delete(
        route("grp.org.procurement.org_partners.show.shopping.misplaced.destroy", [route().params.organisation, props.orgPartner.id]),
        { data: { bucket }, ...dashboardReload }
    )
}

const autoFillOpen = ref(false)
const autoFillScope = ref<{ bucket: string, rank: string | null, label: string } | null>(null)

const openAutoFill = (bucket: CoverBucket, rank: string | null = null) => {
    autoFillScope.value = { bucket: bucket.bucket, rank, label: rank ? `${bucket.label} · ${rank}` : bucket.label }
    autoFillOpen.value = true
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 flex flex-col gap-6 pb-8">
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <FontAwesomeIcon :icon="faWallet" fixed-width aria-hidden="true" />
                        </span>
                        <span class="text-sm font-medium text-gray-600">{{ ctrans("Order budget used") }}</span>
                    </div>
                    <span v-if="orderCapacity.blocked.at_capacity" class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                        {{ ctrans("at capacity") }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="text-2xl font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(orgPartner.currency, stats.estimated_total) }}</span>
                    <span v-if="orderCapacity.partner_capacity.delivers_to_us_per_30d" class="text-sm tabular-nums text-gray-400">
                        / {{ locale.currencyFormat(orgPartner.currency, orderCapacity.partner_capacity.delivers_to_us_per_30d) }}
                    </span>
                </div>
                <template v-if="capacityShare !== null">
                    <div class="mt-3 flex items-center gap-2">
                        <div class="h-2 grow overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full transition-all" :class="meterTone(capacityShare)" :style="{ width: `${capacityShare * 100}%` }" />
                        </div>
                        <span class="w-10 text-right text-xs font-medium tabular-nums text-gray-500">{{ Math.round(capacityShare * 100) }}%</span>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-gray-500">
                        <template v-if="orderCapacity.partner_capacity.source === 'measured'">
                            {{ ctrans("one order cycle of what they historically deliver to us, measured from :n deliveries", { n: orderCapacity.partner_capacity.samples }) }}
                        </template>
                        <template v-else>
                            {{ ctrans("budget = one order cycle (:days days) of what we actually sell of their products", { days: Math.min(leadTime.days + 7, 30) }) }}
                        </template>
                    </p>
                </template>
                <p v-else class="mt-3 text-xs leading-5 text-gray-500">
                    {{ ctrans("No budget: no delivery history and no forecast data yet — the list is uncapped.") }}
                </p>
            </div>

            <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <FontAwesomeIcon :icon="faWarehouse" fixed-width aria-hidden="true" />
                        </span>
                        <span class="text-sm font-medium text-gray-600">{{ ctrans("Warehouse space") }}</span>
                    </div>
                    <span v-if="orderCapacity.blocked.warehouse_full" class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                        {{ ctrans("full") }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(orderCapacity.warehouse.empty_locations) }}</span>
                    <span class="text-sm tabular-nums text-gray-400">/ {{ locale.number(orderCapacity.warehouse.total_locations) }} {{ ctrans("locations free") }}</span>
                </div>
                <div class="mt-3 flex h-2 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full bg-gray-400" :style="{ width: warehouseSegment(usedLocations) }" />
                    <div class="h-full bg-indigo-400" :style="{ width: warehouseSegment(orderCapacity.warehouse.inbound_open_po_lines) }" />
                    <div class="h-full bg-violet-400" :style="{ width: warehouseSegment(orderCapacity.list.lines) }" />
                </div>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs tabular-nums text-gray-500">
                    <span v-for="legend in warehouseLegend" :key="legend.label" class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full" :class="legend.dot" />
                        <b class="font-medium text-gray-700">{{ locale.number(legend.value) }}</b> {{ legend.label }}
                    </span>
                </div>
                <p class="mt-2 text-xs leading-5 text-gray-500">
                    {{ ctrans("new products from this partner: :used of :limit free slots (their fair share)", { used: orderCapacity.warehouse.partner_share_used, limit: orderCapacity.warehouse.partner_share_limit }) }}
                </p>
            </div>

            <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                            <FontAwesomeIcon :icon="faStopwatch" fixed-width aria-hidden="true" />
                        </span>
                        <span class="truncate text-sm font-medium text-gray-600">{{ ctrans("Lead time") }} · {{ orgPartner.name }}</span>
                    </div>
                    <span v-if="latePurchaseOrders.length" class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-red-700">
                        {{ latePurchaseOrders.length }} {{ ctrans("late") }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="text-2xl font-semibold tabular-nums text-gray-900">{{ leadTime.days }}</span>
                    <span class="text-sm text-gray-400">{{ ctrans("days order → booked in") }}</span>
                </div>
                <p class="mt-3 text-xs leading-5 text-gray-500">
                    <template v-if="leadTime.source === 'measured'">
                        {{ ctrans("measured from :samples deliveries", { samples: leadTime.samples }) }}
                    </template>
                    <template v-else>
                        {{ ctrans("estimate — set per product in supplier settings") }}
                    </template>
                </p>
                <p v-if="latePurchaseOrders.length" class="mt-1 flex items-center gap-1.5 text-xs text-red-600">
                    <FontAwesomeIcon :icon="faExclamationTriangle" aria-hidden="true" />
                    {{ ctrans("worst delay :days days", { days: latePurchaseOrders[0].days_late }) }}
                </p>
                <p class="mt-1 text-xs text-gray-400">{{ ctrans(":total products in their catalogue", { total: locale.number(coverTotal) }) }}</p>
            </div>
        </section>

        <section v-if="canBrowse" class="rounded-r bg-indigo-300/5 border-l-4 border-indigo-500">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div>
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon :icon="faLayerGroup" class="text-gray-400" fixed-width aria-hidden="true" />
                        {{ ctrans("Stock cover") }}
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ ctrans(":total products in their catalogue, grouped by how long our stock lasts against a :days day lead time", { total: locale.number(coverTotal), days: leadTime.days }) }}
                    </p>
                </div>
                <Link :href="route(browseRoute.name, browseRoute.parameters)" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500">
                    {{ ctrans("Browse catalogue") }}
                    <FontAwesomeIcon :icon="faChevronRight" class="text-[10px]" aria-hidden="true" />
                </Link>
            </div>

            <div class="flex flex-col gap-5 p-4">
                <div v-if="donutSegments.length" class="flex flex-col items-center gap-x-8 gap-y-4 sm:flex-row">
                    <div class="relative h-44 w-44 shrink-0">
                        <svg viewBox="0 0 180 180" class="h-full w-full" role="img" :aria-label="ctrans('Stock cover distribution')">
                            <path
                                v-for="segment in donutSegments"
                                :key="segment.bucket.bucket"
                                v-tooltip="`${segment.bucket.label}: ${locale.number(segment.bucket.count)} (${coverPercentLabel(segment.bucket)})`"
                                :d="segment.path"
                                :fill="tonePalette[segment.bucket.tone].hex"
                                stroke="white"
                                stroke-width="2"
                                class="cursor-pointer transition-opacity hover:opacity-75"
                                @click="router.visit(bucketRoute(segment.bucket.bucket))"
                            />
                        </svg>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-semibold leading-none tabular-nums text-gray-900">{{ locale.number(coverTotal) }}</span>
                            <span class="mt-1 text-[11px] text-gray-500">{{ ctrans("products") }}</span>
                        </div>
                    </div>
                    <div class="grid w-full min-w-0 grow grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2">
                        <Link
                            v-for="bucket in coverBuckets"
                            :key="bucket.bucket"
                            :href="bucketRoute(bucket.bucket)"
                            class="group flex items-center gap-2 rounded-md px-2 py-1 text-xs hover:bg-gray-50"
                        >
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :class="tonePalette[bucket.tone].accent" />
                            <span class="min-w-0 grow truncate font-medium text-gray-700 group-hover:text-indigo-600 group-hover:underline">{{ bucket.label }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-gray-900">{{ locale.number(bucket.count) }}</span>
                            <span class="w-9 shrink-0 text-right tabular-nums text-gray-400">{{ coverPercentLabel(bucket) }}</span>
                        </Link>
                    </div>
                </div>

                <div class="border-t border-dashed border-indigo-500/30" />

                <div class="">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ ctrans("Needs ordering") }}
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 normal-case tracking-normal tabular-nums text-gray-600">{{ locale.number(orderingCount) }}</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                        <div
                            v-for="bucket in orderingBuckets"
                            :key="bucket.bucket"
                            class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:border-gray-300 hover:shadow-sm"
                        >
                            <div class="h-1 w-full" :class="tonePalette[bucket.tone].accent" />
                            <div class="flex grow flex-col gap-2.5 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <Link
                                            :href="bucketItemsRoute(bucket.bucket)"
                                            class="text-3xl font-semibold leading-none tabular-nums hover:underline"
                                            :class="bucket.count ? tonePalette[bucket.tone].text : 'text-gray-300'"
                                        >
                                            {{ locale.number(bucket.count) }}
                                        </Link>
                                        <Link :href="bucketRoute(bucket.bucket)" class="mt-1.5 block text-xs font-medium leading-4 text-gray-700 hover:text-indigo-600">
                                            {{ bucket.label }}
                                        </Link>
                                    </div>
                                    <span v-if="needsAction(bucket)" class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums" :class="tonePalette[bucket.tone].chip">
                                        {{ ctrans(":count need action", { count: locale.number(bucket.untouched) }) }}
                                    </span>
                                    <span v-else-if="bucket.count" class="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700">
                                        {{ ctrans("all handled") }}
                                    </span>
                                </div>

                                <template v-if="bucket.on_the_way || bucket.on_list">
                                    <div class="flex h-1.5 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                                        <div v-if="bucket.on_the_way" class="h-full" :class="tonePalette[bucket.tone].accent" :style="{ width: segmentWidth(bucket, bucket.on_the_way) }" />
                                        <div v-if="bucket.on_list" class="h-full opacity-40" :class="tonePalette[bucket.tone].accent" :style="{ width: segmentWidth(bucket, bucket.on_list) }" />
                                    </div>
                                    <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] tabular-nums text-gray-500">
                                        <span v-if="bucket.on_the_way" class="flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="tonePalette[bucket.tone].accent" />
                                            {{ bucket.on_the_way }} {{ ctrans("on the way") }}
                                        </span>
                                        <span v-if="bucket.on_list" class="flex items-center gap-1">
                                            <span class="h-1.5 w-1.5 rounded-full opacity-40" :class="tonePalette[bucket.tone].accent" />
                                            {{ bucket.on_list }} {{ ctrans("on list") }}
                                        </span>
                                    </div>
                                </template>

                                <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
                                    <Link
                                        v-for="rank in visibleRanks(bucket)"
                                        :key="rank.rank"
                                        :href="bucketRoute(bucket.bucket, rank.rank)"
                                        v-tooltip="ctrans(':count ranked :rank, :on_list already on the list', { count: rank.count, rank: rank.rank, on_list: rank.on_list })"
                                        class="rounded-md border border-gray-200 px-1.5 py-0.5 text-[11px] tabular-nums text-gray-600 hover:border-gray-300 hover:bg-gray-50"
                                        :class="rankClasses[rank.rank]"
                                    >
                                        <b class="font-bold text-gray-800">{{ rank.rank }}</b> {{ rank.count }}<span v-if="rank.on_list" class="text-gray-400">/{{ rank.on_list }}</span>
                                    </Link>
                                    <button
                                        v-if="canAutoFill(bucket)"
                                        type="button"
                                        v-tooltip="ctrans('Auto-fill the shopping list from this bucket')"
                                        class="ml-auto inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2 py-0.5 text-[11px] font-medium text-white hover:bg-indigo-500"
                                        @click="openAutoFill(bucket)"
                                    >
                                        <FontAwesomeIcon :icon="faMagic" aria-hidden="true" />
                                        {{ ctrans("Fill") }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Not for ordering") }}</div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div
                            v-for="bucket in passiveBuckets"
                            :key="bucket.bucket"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2.5 transition hover:border-gray-300 hover:shadow-sm"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="[tonePalette[bucket.tone].soft, tonePalette[bucket.tone].text]">
                                <FontAwesomeIcon :icon="passiveBucketIcons[bucket.bucket]" fixed-width aria-hidden="true" />
                            </span>
                            <div class="min-w-0 grow">
                                <div class="flex flex-wrap items-baseline gap-x-2">
                                    <Link :href="bucketItemsRoute(bucket.bucket)" class="text-xl font-semibold leading-none tabular-nums hover:underline" :class="tonePalette[bucket.tone].text">
                                        {{ locale.number(bucket.count) }}
                                    </Link>
                                    <Link :href="bucketRoute(bucket.bucket)" class="text-xs font-medium text-gray-700 hover:text-indigo-600">{{ bucket.label }}</Link>
                                    <span v-if="bucket.bucket === 'dead'" class="text-xs tabular-nums text-gray-400">
                                        {{ locale.currencyFormat(orgPartner.currency, bucket.stock_value) }}
                                    </span>
                                </div>
                                <div v-if="visibleRanks(bucket).length || shouldNotBeOrdered(bucket)" class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] tabular-nums text-gray-500">
                                    <Link
                                        v-for="rank in visibleRanks(bucket)"
                                        :key="rank.rank"
                                        :href="bucketRoute(bucket.bucket, rank.rank)"
                                        class="hover:underline"
                                        :class="rankClasses[rank.rank]"
                                    >
                                        <b class="font-bold text-gray-700">{{ rank.rank }}</b> {{ rank.count }}
                                    </Link>
                                    <span
                                        v-if="shouldNotBeOrdered(bucket)"
                                        v-tooltip="ctrans('On the shopping list but not short of stock')"
                                        class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700"
                                    >
                                        <FontAwesomeIcon :icon="faExclamationTriangle" aria-hidden="true" />
                                        {{ bucket.on_list }} {{ ctrans("on list") }}
                                        <button type="button" class="ml-0.5 inline-flex items-center gap-1 rounded border border-red-300 px-1 text-[10px] hover:bg-red-100" @click="removeMisplaced(bucket.bucket)">
                                            <FontAwesomeIcon :icon="faTrashAlt" aria-hidden="true" />
                                            {{ ctrans("remove") }}
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <FontAwesomeIcon :icon="faTruck" class="text-gray-400" fixed-width aria-hidden="true" />
                        {{ ctrans("Order pipeline") }}
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-500">{{ ctrans("From the shopping list to booked-in stock, oldest first") }}</p>
                </div>
                <Link :href="route(stockDeliveriesRoute.name, stockDeliveriesRoute.parameters)" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500">
                    {{ ctrans("All stock deliveries") }}
                    <FontAwesomeIcon :icon="faChevronRight" class="text-[10px]" aria-hidden="true" />
                </Link>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div class="flex flex-col rounded-xl border border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2">
                        <span class="flex min-w-0 items-center gap-2 text-xs font-semibold text-gray-700">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-white text-indigo-500 ring-1 ring-indigo-200">
                                <FontAwesomeIcon :icon="faShoppingBasket" aria-hidden="true" />
                            </span>
                            <span class="truncate">{{ ctrans("On shopping list") }}</span>
                        </span>
                        <span class="shrink-0 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-indigo-600">{{ locale.number(stats.open_items_count) }}</span>
                    </div>
                    <div class="p-2">
                        <Link
                            :href="route(shoppingListRoute.name, shoppingListRoute.parameters)"
                            class="block rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm transition hover:border-indigo-300 hover:shadow"
                        >
                            <div class="text-sm font-semibold text-gray-900">{{ locale.number(stats.open_items_count) }} {{ ctrans("items") }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">{{ locale.currencyFormat(orgPartner.currency, stats.estimated_total) }} {{ ctrans("waiting to be ordered") }}</div>
                            <div v-if="stats.oldest_item_at" class="mt-1.5 flex items-center gap-1 text-[11px] text-gray-400">
                                <FontAwesomeIcon :icon="faClock" aria-hidden="true" />
                                {{ ctrans("oldest since") }} {{ useFormatTime(stats.oldest_item_at, { formatTime: "mdy" }) }}
                            </div>
                        </Link>
                    </div>
                </div>

                <div v-for="column in stockDeliveryColumns" :key="column.key" class="flex flex-col rounded-xl border border-gray-200 bg-gray-50">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2">
                        <span class="flex min-w-0 items-center gap-2 text-xs font-semibold text-gray-700">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md" :class="column.iconClasses">
                                <FontAwesomeIcon :icon="column.icon" aria-hidden="true" />
                            </span>
                            <span class="truncate">{{ column.label }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-1">
                            <span v-tooltip="ctrans('deliveries')" class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-indigo-600">{{ column.deliveries.length }}</span>
                            <span v-if="column.items" v-tooltip="ctrans('items')" class="rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-violet-700">
                                {{ locale.number(column.items) }}
                            </span>
                        </span>
                    </div>
                    <div class="flex grow flex-col gap-2 p-2">
                        <Link
                            v-for="sd in column.deliveries"
                            :key="sd.id"
                            :href="stockDeliveryRoute(sd)"
                            class="block rounded-lg border p-2.5 shadow-sm transition hover:border-indigo-300 hover:shadow"
                            :class="deliveryCardClasses(sd)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold" :class="isProblemOrder(sd) ? 'text-red-700' : 'text-gray-900'">{{ sd.reference }}</span>
                                <span v-tooltip="ctrans('days since the delivery date')" class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold tabular-nums" :class="ageBadgeClasses(sd)">
                                    {{ ctrans(":days days", { days: sd.days_old }) }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2 text-xs text-gray-500">
                                <span class="truncate">{{ sd.items }} {{ ctrans("items") }} · {{ sd.state.replace("_", " ") }}</span>
                                <span class="shrink-0 tabular-nums">{{ useFormatTime(sd.date, { formatTime: "mdy" }) }}</span>
                            </div>
                            <div
                                v-if="column.key === 'in_transit' && sd.days_in_transit !== null"
                                class="mt-1.5 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold tabular-nums"
                                :class="sd.days_in_transit > 14 ? 'bg-amber-50 text-amber-700' : 'bg-indigo-50 text-indigo-600'"
                            >
                                <FontAwesomeIcon :icon="faShippingFast" aria-hidden="true" />
                                {{ sd.days_in_transit }} {{ ctrans("days in transit") }}
                            </div>
                        </Link>
                        <div v-if="!column.deliveries.length" class="rounded-lg border border-dashed border-gray-200 py-4 text-center text-xs text-gray-400">
                            {{ ctrans("Nothing here") }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="latePurchaseOrders.length" class="max-w-3xl">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" class="text-red-500" fixed-width aria-hidden="true" />
                {{ ctrans("Late from this partner") }}
                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-red-700">{{ latePurchaseOrders.length }}</span>
            </h3>
            <ul class="mt-3 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <li v-for="purchaseOrder in latePurchaseOrders" :key="purchaseOrder.id" class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
                            <FontAwesomeIcon :icon="faClock" fixed-width aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <Link class="text-sm font-semibold text-gray-900 hover:text-indigo-600" :href="purchaseOrderRoute(purchaseOrder)">
                                {{ purchaseOrder.reference }}
                            </Link>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                <span class="capitalize">{{ purchaseOrder.state }}</span>
                                <span v-if="purchaseOrder.no_eta" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500">{{ ctrans("no delivery date given") }}</span>
                            </div>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums" :class="purchaseOrder.days_late > 14 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">
                        {{ ctrans(":days days late", { days: purchaseOrder.days_late }) }}
                    </span>
                </li>
            </ul>
        </section>
    </div>

    <ModalAutoFillShoppingList
        v-model="autoFillOpen"
        :orgPartnerId="orgPartner.id"
        :currency="orgPartner.currency"
        :bucket="autoFillScope?.bucket"
        :rank="autoFillScope?.rank"
        :scopeLabel="autoFillScope?.label"
    />
</template>
