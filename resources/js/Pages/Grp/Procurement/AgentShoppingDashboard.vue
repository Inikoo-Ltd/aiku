<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 31 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import InputNumber from "primevue/inputnumber"
import ToggleSwitch from "primevue/toggleswitch"
import { ref, computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SupplierOrderProposals, { type SupplierOrderProposalsData } from "@/Components/Procurement/SupplierOrderProposals.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faExclamationTriangle,
    faWallet,
    faWarehouse,
    faStopwatch,
    faLayerGroup,
    faChevronDown,
    faTrashAlt,
    faCheck,
    faSkullCrossbones,
    faSeedling,
    faBan,
    faCheckSquare,
    faSquare,
    faThLarge,
    faList,
    faArrowRight,
} from "@fal"

library.add(
    faExclamationTriangle,
    faWallet,
    faWarehouse,
    faStopwatch,
    faLayerGroup,
    faChevronDown,
    faTrashAlt,
    faCheck,
    faSkullCrossbones,
    faSeedling,
    faBan,
    faCheckSquare,
    faSquare,
    faThLarge,
    faList,
    faArrowRight
)

type Tone = "red-deep" | "red" | "orange" | "amber" | "yellow" | "green" | "slate" | "violet" | "gray"
type PrepareBucket = { bucket: string, count: number, cost: number, order_lines: number, order_cost: number, cbm: number, order_cbm: number, cbm_estimated_lines: number, order_cbm_estimated_lines: number }
type LeadTime = { days: number, source: "measured" | "estimate" | "default", samples: number }
type RankBreakdown = { rank: string, count: number, on_list: number }
type CoverBucket = { bucket: string, label: string, tone: Tone, count: number, on_list: number, on_the_way: number, on_order_value: number, lost: number, untouched: number, suppliers: number, stock_value: number, ranks: RankBreakdown[] }
type OrderCapacity = {
    agent_capacity: { lands_for_us_per_30d: number | null, source: "measured" | "sales" | "none", samples: number, usual_month_orders: number | null, restock_out_of_stock: number, container_value: number | null }
    pipeline: { waiting: number, being_made: number, on_the_way: number, total: number }
    container: { label: string, cbm: number }
    list: { value: number, lines: number, units: number }
    warehouse: { total_locations: number, empty_locations: number, free_ratio: number | null, inbound_open_po_lines: number, agent_share_used: number, agent_share_limit: number }
    currency: string
    blocked: { at_capacity: boolean, warehouse_full: boolean }
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgAgent: { id: number, slug: string, name: string, currency: string }
    stats: { open_items_count: number, oldest_item_at: string | null, estimated_total: number }
    coverBuckets: CoverBucket[]
    coverTotal: number
    leadTime: LeadTime
    orderCapacity: OrderCapacity
    canPrepareOrder: boolean
    prepareOrder?: { buckets: PrepareBucket[], url: string }
    supplierOrderProposals: SupplierOrderProposalsData
}>()

const locale = useLocaleStore()

const tonePalette: Record<Tone, { text: string, soft: string, accent: string, chip: string, hex: string }> = {
    "red-deep": { text: "text-red-800", soft: "bg-red-50", accent: "bg-red-800", chip: "bg-red-100 text-red-800", hex: "#991b1b" },
    red: { text: "text-red-600", soft: "bg-red-50", accent: "bg-red-500", chip: "bg-red-100 text-red-700", hex: "#ef4444" },
    orange: { text: "text-orange-600", soft: "bg-orange-50", accent: "bg-orange-500", chip: "bg-orange-100 text-orange-700", hex: "#f97316" },
    amber: { text: "text-amber-600", soft: "bg-amber-50", accent: "bg-amber-500", chip: "bg-amber-100 text-amber-700", hex: "#f59e0b" },
    yellow: { text: "text-yellow-600", soft: "bg-yellow-50", accent: "bg-yellow-400", chip: "bg-yellow-100 text-yellow-700", hex: "#facc15" },
    green: { text: "text-green-700", soft: "bg-green-50", accent: "bg-green-500", chip: "bg-green-100 text-green-700", hex: "#22c55e" },
    slate: { text: "text-slate-500", soft: "bg-slate-100", accent: "bg-slate-400", chip: "bg-slate-100 text-slate-600", hex: "#94a3b8" },
    violet: { text: "text-violet-700", soft: "bg-violet-50", accent: "bg-violet-400", chip: "bg-violet-100 text-violet-700", hex: "#a78bfa" },
    gray: { text: "text-gray-600", soft: "bg-gray-100", accent: "bg-gray-300", chip: "bg-gray-100 text-gray-600", hex: "#d1d5db" },
}

const STOCK_COVER_STORAGE_KEY = "agentShopping.stockCoverOpen"

const readStockCoverOpen = () => {
    try {
        return localStorage.getItem(STOCK_COVER_STORAGE_KEY) !== "0"
    } catch {
        return true
    }
}

const stockCoverOpen = ref(readStockCoverOpen())
const toggleStockCover = () => {
    stockCoverOpen.value = !stockCoverOpen.value
    try {
        localStorage.setItem(STOCK_COVER_STORAGE_KEY, stockCoverOpen.value ? "1" : "0")
    } catch {
        return
    }
}

const passiveBucketKeys = ["ok", "dead", "gone", "never"]
const passiveBucketIcons: Record<string, typeof faCheck> = { ok: faCheck, dead: faSkullCrossbones, gone: faBan, never: faSeedling }
const rankClasses: Record<string, string> = { A: "", B: "", C: "", D: "opacity-50", Z: "opacity-50" }

const isPassiveBucket = (bucket: CoverBucket) => passiveBucketKeys.includes(bucket.bucket)
const orderingBuckets = computed(() => props.coverBuckets.filter((bucket) => !isPassiveBucket(bucket)))
const passiveBuckets = computed(() => props.coverBuckets.filter(isPassiveBucket))
const orderingCount = computed(() => orderingBuckets.value.reduce((sum, bucket) => sum + bucket.count, 0))

const shortLabel = (bucket: CoverBucket) => bucket.label.split(":")[0]

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

type PieSlice = { key: string, label: string, fullLabel: string, count: number, lost: number, fill: string, dot: string, href: string | null }

const NOT_REORDERED_KEYS = ["dead", "gone", "never"]

const sliceOf = (bucket: CoverBucket): PieSlice => ({
    key: bucket.bucket,
    label: shortLabel(bucket),
    fullLabel: bucket.label,
    count: bucket.count,
    lost: bucket.lost,
    fill: tonePalette[bucket.tone].hex,
    dot: tonePalette[bucket.tone].accent,
    href: bucketItemsRoute(bucket.bucket),
})

const percentLabel = (count: number, total: number) => {
    if (!total || !count) {
        return "0%"
    }
    const percent = (count / total) * 100
    return percent < 1 ? "<1%" : `${Math.round(percent)}%`
}

const pieOf = (title: string, centerLabel: string, slices: PieSlice[], legendClass: string, showsLost: boolean) => {
    const total = slices.reduce((sum, slice) => sum + slice.count, 0)
    let angle = -Math.PI / 2
    return {
        title,
        centerLabel,
        total,
        legendClass,
        showsLost,
        slices,
        segments: slices
            .filter((slice) => slice.count > 0)
            .map((slice) => {
                const sweep = (slice.count / total) * Math.PI * 2
                const segment = { slice, path: donutArcPath(angle, angle + sweep) }
                angle += sweep
                return segment
            }),
    }
}

const pies = computed(() => {
    const reordered = props.coverBuckets.filter((bucket) => !NOT_REORDERED_KEYS.includes(bucket.bucket))
    const notReordered = props.coverBuckets.filter((bucket) => NOT_REORDERED_KEYS.includes(bucket.bucket))
    const reorderedCount = reordered.reduce((sum, bucket) => sum + bucket.count, 0)

    return [
        pieOf(ctrans("Our range"), ctrans("products"), reordered.map(sliceOf), "grid-rows-6 sm:grid-rows-3", true),
        pieOf(
            ctrans("Their whole catalogue"),
            ctrans("products"),
            [
                { key: "reordered", label: ctrans("Our range"), fullLabel: ctrans("Everything in the first chart: what we stock from this agent and keep reordering"), count: reorderedCount, lost: reordered.reduce((sum, bucket) => sum + bucket.lost, 0), fill: "#334155", dot: "bg-slate-700", href: null },
                ...notReordered.map(sliceOf),
            ],
            "grid-rows-4",
            false
        ),
    ]
})

const segmentWidth = (bucket: CoverBucket, part: number) => (bucket.count ? `${Math.max((part / bucket.count) * 100, part ? 1.5 : 0)}%` : "0%")
const shouldNotBeOrdered = (bucket: CoverBucket) => ["ok", "dead", "gone"].includes(bucket.bucket) && bucket.on_list > 0
const canAutoFill = (bucket: CoverBucket) => !isPassiveBucket(bucket) && bucket.ranks.some((rank) => rank.count > rank.on_list)
const visibleRanks = (bucket: CoverBucket) => bucket.ranks.filter((rank) => rank.count > 0)

const money = (amount: number) =>
    new Intl.NumberFormat(locale.locale_iso || undefined, { style: "currency", currency: props.orgAgent.currency, currencyDisplay: "narrowSymbol", maximumFractionDigits: 0 }).format(amount)

const roundBudget = computed(() => props.orderCapacity.agent_capacity.lands_for_us_per_30d)

const containers = (amount: number) => {
    const containerValue = props.orderCapacity.agent_capacity.container_value
    return containerValue ? (amount / containerValue).toFixed(1) : null
}

const containerCount = (amount: number) => {
    const count = containers(amount)
    return count === null ? "" : count === "1.0" ? ctrans("1 container") : ctrans(":count containers", { count })
}

const pipelineStages = computed(() => [
    { label: ctrans("waiting for the agent to confirm"), value: props.orderCapacity.pipeline.waiting, dot: "bg-amber-400" },
    { label: ctrans("confirmed, being made"), value: props.orderCapacity.pipeline.being_made, dot: "bg-sky-400" },
    { label: ctrans("on the way"), value: props.orderCapacity.pipeline.on_the_way, dot: "bg-green-500" },
])

const capacityShare = computed(() => {
    const cap = props.orderCapacity.agent_capacity.lands_for_us_per_30d
    return cap ? Math.min(props.orderCapacity.list.value / cap, 1) : null
})

const meterTone = (share: number) => (share >= 1 ? "bg-red-500" : share >= 0.8 ? "bg-amber-500" : "bg-green-500")

const usedLocations = computed(() => props.orderCapacity.warehouse.total_locations - props.orderCapacity.warehouse.empty_locations)
const warehouseSegment = (part: number) => `${(part / Math.max(props.orderCapacity.warehouse.total_locations, 1)) * 100}%`
const warehouseLegend = computed(() => [
    { label: ctrans("in use"), value: usedLocations.value, dot: "bg-gray-400" },
    { label: ctrans("inbound PO/SD lines"), value: props.orderCapacity.warehouse.inbound_open_po_lines, dot: "bg-teal-400" },
    { label: ctrans("on your draft orders"), value: props.orderCapacity.list.lines, dot: "bg-violet-400" },
])

const browseRoute = computed(() => route("grp.org.procurement.org_agents.show.shopping.browse.index", [route().params.organisation, props.orgAgent.slug]))

const orderingViews = computed(() => [
    { value: "cards", label: ctrans("Cards"), icon: faThLarge, href: `${browseRoute.value}?view=cards` },
    { value: "list", label: ctrans("Table"), icon: faList, href: `${browseRoute.value}?view=list` },
])

const bucketItemsRoute = (bucket: string, rank?: string) =>
    `${route("grp.org.procurement.org_agents.show.shopping.items.index", [route().params.organisation, props.orgAgent.slug])}?cover=${bucket}${rank ? `&rank=${rank}` : ""}`

const dashboardReload = { only: ["coverBuckets", "coverTotal", "orderCapacity", "stats", "prepareOrder"], preserveScroll: true }

const removeMisplaced = (bucket: string) => {
    router.delete(
        route("grp.org.procurement.org_agents.show.shopping.misplaced.destroy", [route().params.organisation, props.orgAgent.slug]),
        { data: { bucket }, ...dashboardReload }
    )
}

const fieldFocusClass = "[&.p-focus]:!border-gray-500 [&_input:focus]:!border-gray-500"

const selectedBuckets = ref<string[]>(["out", "w1", "w2"])
const worstOnly = ref(true)
const budget = ref<number | null>(null)
const isPreparing = ref(false)

const prepareByBucket = computed(() => Object.fromEntries((props.prepareOrder?.buckets ?? []).map((row) => [row.bucket, row])))
const isPreparable = (bucket: CoverBucket) => ["out", "w1", "w2", "w3"].includes(bucket.bucket)
const isSelected = (bucket: CoverBucket) => isPreparable(bucket) && selectedBuckets.value.includes(bucket.bucket)

const toOrder = (bucket: CoverBucket) => {
    const row = prepareByBucket.value[bucket.bucket]
    return row
        ? {
            lines: worstOnly.value ? row.order_lines : row.count,
            cost: worstOnly.value ? row.order_cost : row.cost,
            cbm: worstOnly.value ? row.order_cbm : row.cbm,
            cbmEstimatedLines: worstOnly.value ? row.order_cbm_estimated_lines : row.cbm_estimated_lines,
        }
        : null
}

const leftOut = (bucket: CoverBucket) => Math.max(0, bucket.count - bucket.on_the_way - (toOrder(bucket)?.lines ?? 0))

const estimate = computed(() =>
    orderingBuckets.value
        .filter(isSelected)
        .reduce(
            (total, bucket) => ({
                lines: total.lines + (toOrder(bucket)?.lines ?? 0),
                cost: total.cost + (toOrder(bucket)?.cost ?? 0),
                cbm: total.cbm + (toOrder(bucket)?.cbm ?? 0),
                cbmEstimatedLines: total.cbmEstimatedLines + (toOrder(bucket)?.cbmEstimatedLines ?? 0),
            }),
            { lines: 0, cost: 0, cbm: 0, cbmEstimatedLines: 0 }
        )
)
const isCappedByBudget = computed(() => !!budget.value && budget.value < estimate.value.cost)
const roundLeft = computed(() => (roundBudget.value === null ? null : Math.max(0, roundBudget.value - props.stats.estimated_total)))
const isOverRound = computed(() => roundLeft.value !== null && (isCappedByBudget.value ? budget.value! : estimate.value.cost) > roundLeft.value)

const prepareError = ref<string | null>(null)

const prepare = () => {
    if (!props.prepareOrder) {
        return
    }
    prepareError.value = null
    router.post(
        props.prepareOrder.url,
        { buckets: selectedBuckets.value, worst_only: worstOnly.value, ...(budget.value ? { budget: budget.value } : {}) },
        {
            onStart: () => (isPreparing.value = true),
            onFinish: () => (isPreparing.value = false),
            onError: (errors) => {
                prepareError.value = errors.rescue ?? errors.budget ?? errors.buckets ?? ctrans("Something went wrong, please try again")
            },
        }
    )
}


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 flex flex-col gap-6 pb-8">
        <SupplierOrderProposals :data="supplierOrderProposals" :can-decide="canPrepareOrder" show-supplier />

        <section class="rounded-r border-l-4 border-gray-300 bg-gray-50">
            <div class="flex flex-col gap-3 px-4 py-3">
                <div class="flex flex-col gap-x-8 gap-y-3 lg:flex-row lg:items-center">
                    <div class="shrink-0 lg:w-52">
                        <button type="button" class="flex items-center gap-2 text-sm font-semibold text-gray-800" :aria-expanded="stockCoverOpen" @click="toggleStockCover">
                            <FontAwesomeIcon :icon="faLayerGroup" class="text-gray-400" fixed-width aria-hidden="true" />
                            {{ ctrans("Stock cover") }}
                            <FontAwesomeIcon :icon="faChevronDown" class="text-[10px] text-gray-400 transition-transform" :class="{ '-rotate-90': !stockCoverOpen }" fixed-width aria-hidden="true" />
                        </button>
                        <p class="mt-1 text-xs leading-5 text-gray-500">
                            {{ ctrans(":total products in their catalogue, grouped by how long our stock lasts against a :days day lead time", { total: locale.number(coverTotal), days: leadTime.days }) }}
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-gray-700">
                            <span class="font-normal text-gray-500">{{ ctrans("Order from the whole catalogue") }}:</span>
                            <Link v-for="orderingView in orderingViews" :key="orderingView.value" :href="orderingView.href" class="group inline-flex items-center gap-1.5">
                                <FontAwesomeIcon :icon="orderingView.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                                <span class="underline decoration-dotted underline-offset-2 group-hover:decoration-solid">{{ orderingView.label }}</span>
                            </Link>
                        </div>
                    </div>

                    <div v-if="coverTotal" v-show="stockCoverOpen" class="flex min-w-0 grow flex-wrap items-center gap-x-8 gap-y-4 2xl:flex-nowrap">
                        <div v-for="pie in pies" :key="pie.title" class="flex min-w-0 items-center gap-x-4">
                            <div class="relative h-24 w-24 shrink-0">
                                <svg viewBox="0 0 180 180" class="h-full w-full" role="img" :aria-label="pie.title">
                                    <path
                                        v-for="segment in pie.segments"
                                        :key="segment.slice.key"
                                        v-tooltip="`${segment.slice.fullLabel}: ${locale.number(segment.slice.count)} (${percentLabel(segment.slice.count, pie.total)})`"
                                        :d="segment.path"
                                        :fill="segment.slice.fill"
                                        stroke="white"
                                        stroke-width="2"
                                        class="transition-opacity hover:opacity-75"
                                        :class="{ 'cursor-pointer': segment.slice.href }"
                                        @click="segment.slice.href && router.visit(segment.slice.href)"
                                    />
                                </svg>
                                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-base font-semibold leading-none tabular-nums text-gray-900">{{ locale.number(pie.total) }}</span>
                                    <span class="mt-0.5 text-[10px] text-gray-500">{{ pie.centerLabel }}</span>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="mb-1 flex items-baseline gap-2 text-[11px] text-gray-400">
                                    <span class="font-semibold uppercase tracking-wide text-gray-500">{{ pie.title }}</span>
                                    <span v-if="pie.showsLost" v-tooltip="ctrans('For each group: how many products, their share of this chart, and the sales we expect to lose in the next 8 weeks because the product is out or will run out before stock arrives. Orders already on the way are taken into account.')" class="cursor-help underline decoration-dotted underline-offset-2">{{ ctrans("products · share · sales lost in 8 weeks") }}</span>
                                </div>
                                <div class="grid auto-cols-max grid-flow-col gap-x-10 gap-y-0.5" :class="pie.legendClass">
                                    <component
                                        :is="slice.href ? Link : 'div'"
                                        v-for="slice in pie.slices"
                                        :key="slice.key"
                                        v-tooltip="slice.fullLabel"
                                        :href="slice.href ?? undefined"
                                        class="group grid items-center gap-x-3 text-xs tabular-nums"
                                        :class="pie.showsLost ? 'grid-cols-[0.625rem_6.5rem_3rem_2.25rem_4.5rem]' : 'grid-cols-[0.625rem_8.5rem_3rem_2.25rem]'"
                                    >
                                        <span class="h-2.5 w-2.5 rounded-sm" :class="slice.dot" />
                                        <span class="truncate text-gray-700" :class="{ 'group-hover:underline': slice.href }">{{ slice.label }}</span>
                                        <span class="text-right font-semibold text-gray-900">{{ locale.number(slice.count) }}</span>
                                        <span class="text-right text-gray-400">{{ percentLabel(slice.count, pie.total) }}</span>
                                        <span v-if="pie.showsLost" class="text-right" :class="slice.lost >= 1 ? 'font-medium text-red-600' : 'text-gray-300'">{{ slice.lost >= 1 ? money(slice.lost) : "—" }}</span>
                                    </component>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="stockCoverOpen" class="border-t border-dashed border-gray-300" />

                <div>
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ ctrans("What to order") }}
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 normal-case tracking-normal tabular-nums text-gray-600">{{ locale.number(orderingCount) }}</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                        <div
                            v-for="bucket in orderingBuckets"
                            :key="bucket.bucket"
                            class="flex flex-col overflow-hidden rounded-lg border bg-white transition hover:shadow-sm"
                            :class="isSelected(bucket) ? 'border-gray-400' : 'border-gray-200 hover:border-gray-300'"
                        >
                            <div class="h-1 w-full" :class="tonePalette[bucket.tone].accent" />
                            <div class="flex grow flex-col gap-2.5 p-3">
                                <label
                                    v-if="canPrepareOrder && isPreparable(bucket)"
                                    class="flex items-start gap-2 rounded has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gray-400"
                                    :class="toOrder(bucket)?.lines ? 'cursor-pointer' : 'cursor-default opacity-60'"
                                >
                                    <input v-model="selectedBuckets" type="checkbox" :value="bucket.bucket" :disabled="!toOrder(bucket)?.lines" class="sr-only" />
                                    <FontAwesomeIcon
                                        :icon="isSelected(bucket) && toOrder(bucket)?.lines ? faCheckSquare : faSquare"
                                        class="mt-px text-base"
                                        :class="isSelected(bucket) && toOrder(bucket)?.lines ? 'text-gray-800' : 'text-gray-400'"
                                        fixed-width
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0 grow text-xs font-medium leading-4 text-gray-700">{{ bucket.label }}</span>
                                </label>
                                <div v-else class="text-xs font-medium leading-4 text-gray-700">{{ bucket.label }}</div>

                                <Link :href="bucketItemsRoute(bucket.bucket)" class="flex items-baseline justify-between gap-2 hover:underline">
                                    <span class="text-3xl font-semibold leading-none tabular-nums" :class="bucket.count ? tonePalette[bucket.tone].text : 'text-gray-300'">{{ locale.number(bucket.count) }}</span>
                                    <span v-if="bucket.suppliers" class="text-[11px] tabular-nums text-gray-400">{{ ctrans(":n suppliers", { n: bucket.suppliers }) }}</span>
                                </Link>

                                <div v-if="bucket.on_the_way || bucket.on_list" class="flex h-1.5 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                                    <div v-if="bucket.on_the_way" class="h-full" :class="tonePalette[bucket.tone].accent" :style="{ width: segmentWidth(bucket, bucket.on_the_way) }" />
                                    <div v-if="bucket.on_list" class="h-full opacity-40" :class="tonePalette[bucket.tone].accent" :style="{ width: segmentWidth(bucket, bucket.on_list) }" />
                                </div>

                                <div class="grid grid-cols-[1fr_auto_auto] items-baseline gap-x-3 gap-y-1 text-sm tabular-nums">
                                    <span class="text-gray-700">{{ ctrans("already on order") }}</span>
                                    <span class="text-right text-gray-700">{{ locale.number(bucket.on_the_way) }}</span>
                                    <span class="text-right text-gray-700">{{ bucket.on_order_value ? money(bucket.on_order_value) : "" }}</span>
                                    <template v-if="isPreparable(bucket)">
                                        <div v-if="!prepareOrder" class="col-span-3 h-10 animate-pulse rounded bg-gray-100" role="status" :aria-label="ctrans('Working out what to order')" />
                                        <template v-else>
                                            <template v-if="leftOut(bucket)">
                                                <span v-tooltip="ctrans('Not on an order and not proposed: stock would last until the order arrives and a month after, or the product is switched off for automatic ordering')" class="cursor-help justify-self-start text-gray-700 underline decoration-dotted underline-offset-2">{{ ctrans("can wait") }}</span>
                                                <span class="text-right text-gray-700">{{ locale.number(leftOut(bucket)) }}</span>
                                                <span />
                                            </template>
                                            <span class="col-span-3 border-t border-gray-100" />
                                            <span class="font-semibold" :class="toOrder(bucket)?.lines ? tonePalette[bucket.tone].text : 'text-gray-400'">{{ ctrans("still to order") }}</span>
                                            <span class="text-right font-semibold" :class="toOrder(bucket)?.lines ? tonePalette[bucket.tone].text : 'text-gray-400'">{{ locale.number(toOrder(bucket)?.lines ?? 0) }}</span>
                                            <span class="text-right font-semibold text-gray-900">{{ money(toOrder(bucket)?.cost ?? 0) }}</span>
                                        </template>
                                    </template>
                                    <template v-else>
                                        <span class="col-span-3 border-t border-gray-100" />
                                        <span class="font-semibold text-gray-700">{{ ctrans("not on an order yet") }}</span>
                                        <span class="text-right font-semibold text-gray-700">{{ locale.number(bucket.untouched) }}</span>
                                        <span />
                                    </template>
                                </div>

                                <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-1">
                                    <Link
                                        v-for="rank in visibleRanks(bucket)"
                                        :key="rank.rank"
                                        :href="bucketItemsRoute(bucket.bucket, rank.rank)"
                                        v-tooltip="ctrans(':count ranked :rank', { count: rank.count, rank: rank.rank })"
                                        class="rounded-md border border-gray-200 px-1.5 py-0.5 text-[11px] tabular-nums text-gray-600 hover:border-gray-300 hover:bg-gray-50"
                                        :class="rankClasses[rank.rank]"
                                    >
                                        <b class="font-bold text-gray-800">{{ rank.rank }}</b> {{ rank.count }}
                                    </Link>
                                    <Link
                                        v-if="canPrepareOrder && canAutoFill(bucket)"
                                        :href="`${browseRoute}?cover=${bucket.bucket}`"
                                        class="ml-auto inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-0.5 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        {{ ctrans("Go ordering") }}
                                        <FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form v-if="canPrepareOrder" class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-3 rounded-lg border border-gray-200 bg-white px-4 py-3" @submit.prevent="prepare">
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                            <ToggleSwitch v-model="worstOnly" />
                            <span v-tooltip="ctrans('Leaves out products with enough stock to last until this order arrives and a month after. Products already out of stock and your best sellers (rank A and B) always go in.')" class="cursor-help underline decoration-dotted underline-offset-2">
                                {{ ctrans("Only what would run out") }}
                            </span>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            {{ ctrans("Spend up to") }}
                            <InputNumber
                                v-model="budget"
                                mode="currency"
                                :currency="orgAgent.currency"
                                :min="1"
                                :maxFractionDigits="0"
                                :placeholder="ctrans('No limit')"
                                :class="fieldFocusClass"
                                :pt="{ pcInputText: { root: { class: '!w-32 !py-1 text-sm' } } }"
                            />
                        </label>

                        <div class="ml-auto flex flex-wrap items-center gap-x-4 gap-y-2">
                            <div class="text-right text-sm tabular-nums">
                                <div>
                                    <span class="text-gray-500">{{ ctrans(isCappedByBudget ? "up to" : "This order") }}</span>
                                    <b class="ml-1 font-semibold text-gray-900">≈ {{ money(isCappedByBudget ? budget! : estimate.cost) }}</b>
                                    <span class="ml-1 text-gray-500">
                                        · {{ ctrans(":lines lines", { lines: locale.number(estimate.lines) }) }}
                                        <template v-if="estimate.cbm && !isCappedByBudget">
                                            ·
                                            <span
                                                v-tooltip="estimate.cbmEstimatedLines ? ctrans(':count of these products have no carton volume recorded. For those the usual carton of the same supplier is used, so the volume is approximate.', { count: locale.number(estimate.cbmEstimatedLines) }) : ctrans('From the carton volume recorded on each product')"
                                                class="cursor-help underline decoration-dotted underline-offset-2"
                                            >{{ estimate.cbmEstimatedLines ? "≈ " : "" }}{{ locale.number(Math.round(estimate.cbm * 10) / 10) }} m³</span>
                                        </template>
                                        <template v-if="estimate.cbm && !isCappedByBudget">
                                            ·
                                            <span v-tooltip="ctrans('By volume, against a :container of :cbm m³. A loaded container never reaches its full volume.', { container: orderCapacity.container.label, cbm: orderCapacity.container.cbm })" class="cursor-help underline decoration-dotted underline-offset-2">
                                                {{ ctrans(":share of a :container", { share: `${Math.round((estimate.cbm / orderCapacity.container.cbm) * 100)}%`, container: orderCapacity.container.label }) }}
                                            </span>
                                        </template>
                                    </span>
                                </div>
                                <div v-if="roundLeft !== null" class="text-xs" :class="isOverRound ? 'font-medium text-red-600' : 'text-gray-400'">
                                    {{ isOverRound
                                        ? ctrans("Over what is left of this round's budget (:left)", { left: money(roundLeft) })
                                        : ctrans(":left left of this round's budget", { left: money(roundLeft) }) }}
                                </div>
                            </div>
                            <Button
                                :label="ctrans('Prepare order')"
                                type="tertiary"
                                v-tooltip="ctrans('Adds these products, in whole cartons, to a draft purchase order for each supplier. Nothing is sent to the agent.')"
                                icon="fal fa-clipboard-list"
                                :iconRight="faArrowRight"
                                size="s"
                                nativeType="submit"
                                :loading="isPreparing"
                                :disabled="!prepareOrder || !estimate.lines"
                            />
                        </div>
                        <p v-if="prepareError" class="w-full text-xs text-red-600">{{ ctrans("No order prepared") }}: {{ prepareError }}</p>
                    </form>
                </div>

                <div>
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Not for ordering") }}</div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
                                    <Link :href="bucketItemsRoute(bucket.bucket)" class="text-xs font-medium text-gray-700 hover:underline">{{ bucket.label }}</Link>
                                    <span v-if="bucket.bucket === 'dead'" class="text-xs tabular-nums text-gray-400">
                                        {{ locale.currencyFormat(orgAgent.currency, bucket.stock_value) }}
                                    </span>
                                </div>
                                <div v-if="visibleRanks(bucket).length || shouldNotBeOrdered(bucket)" class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] tabular-nums text-gray-500">
                                    <Link
                                        v-for="rank in visibleRanks(bucket)"
                                        :key="rank.rank"
                                        :href="bucketItemsRoute(bucket.bucket, rank.rank)"
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
                                        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                                        {{ bucket.on_list }} {{ ctrans("on list") }}
                                        <button v-if="bucket.bucket !== 'gone'" type="button" class="ml-0.5 inline-flex items-center gap-1 rounded border border-red-300 px-1 text-[10px] hover:bg-red-100" @click="removeMisplaced(bucket.bucket)">
                                            <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
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

        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                            <FontAwesomeIcon :icon="faWallet" fixed-width aria-hidden="true" />
                        </span>
                        <span class="text-sm font-medium text-gray-600">{{ ctrans("This round's budget") }}</span>
                    </div>
                    <span v-if="orderCapacity.blocked.at_capacity" class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                        {{ ctrans("at budget") }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="text-2xl font-semibold tabular-nums text-gray-900">{{ money(stats.estimated_total) }}</span>
                    <span v-if="roundBudget" class="text-sm tabular-nums text-gray-400">/ {{ money(roundBudget) }}</span>
                </div>
                <p v-if="containers(stats.estimated_total) !== null" class="mt-0.5 text-xs tabular-nums text-gray-500">
                    {{ ctrans("on your drafts, about :drafts of a container. The budget is about :budget.", { drafts: containers(stats.estimated_total), budget: containerCount(roundBudget ?? 0) }) }}
                </p>
                <template v-if="capacityShare !== null">
                    <div class="mt-3 flex items-center gap-2">
                        <div class="h-2 grow overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full transition-all" :class="meterTone(capacityShare)" :style="{ width: `${capacityShare * 100}%` }" />
                        </div>
                        <span class="w-10 text-right text-xs font-medium tabular-nums text-gray-500">{{ Math.round(capacityShare * 100) }}%</span>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-gray-500">
                        <template v-if="orderCapacity.agent_capacity.source === 'measured'">
                            {{ ctrans("What you send this agent in a usual month (:month, from :n orders in the last year) plus :restock to restock what is out of stock.", { month: money(orderCapacity.agent_capacity.usual_month_orders ?? 0), n: locale.number(orderCapacity.agent_capacity.samples), restock: money(orderCapacity.agent_capacity.restock_out_of_stock) }) }}
                        </template>
                        <template v-else>
                            {{ ctrans("No orders to this agent in the last year, so it is a month of what you sell of their products (:month) plus :restock to restock what is out of stock.", { month: money(orderCapacity.agent_capacity.usual_month_orders ?? 0), restock: money(orderCapacity.agent_capacity.restock_out_of_stock) }) }}
                        </template>
                    </p>
                </template>
                <p v-else class="mt-3 text-xs leading-5 text-gray-500">
                    {{ ctrans("No budget: no order history and no sales of their products yet, the round is uncapped.") }}
                </p>

                <div class="mt-3 border-t border-dashed border-gray-200 pt-3 text-xs text-gray-500">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="font-medium text-gray-700">{{ ctrans("Already ordered, not landed yet") }}</span>
                        <span class="tabular-nums">
                            <b class="font-semibold text-gray-900">{{ money(orderCapacity.pipeline.total) }}</b>
                            <template v-if="containers(orderCapacity.pipeline.total) !== null"> · {{ containerCount(orderCapacity.pipeline.total) }}</template>
                        </span>
                    </div>
                    <dl class="mt-1.5 space-y-0.5 tabular-nums">
                        <div v-for="stage in pipelineStages" :key="stage.label" class="flex items-baseline justify-between gap-2">
                            <dt class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full" :class="stage.dot" />{{ stage.label }}</dt>
                            <dd class="text-gray-700">{{ money(stage.value) }}</dd>
                        </div>
                    </dl>
                    <p v-if="orderCapacity.agent_capacity.container_value" class="mt-2 text-gray-400">
                        {{ ctrans("A container from this agent is worth about :value to you. All supplier currencies are converted to :currency.", { value: money(orderCapacity.agent_capacity.container_value), currency: orgAgent.currency }) }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
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
                    <div class="h-full bg-teal-400" :style="{ width: warehouseSegment(orderCapacity.warehouse.inbound_open_po_lines) }" />
                    <div class="h-full bg-violet-400" :style="{ width: warehouseSegment(orderCapacity.list.lines) }" />
                </div>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs tabular-nums text-gray-500">
                    <span v-for="legend in warehouseLegend" :key="legend.label" class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full" :class="legend.dot" />
                        <b class="font-medium text-gray-700">{{ locale.number(legend.value) }}</b> {{ legend.label }}
                    </span>
                </div>
                <p class="mt-2 text-xs leading-5 text-gray-500">
                    {{ ctrans("new products from this agent: :used of :limit free slots (their fair share)", { used: orderCapacity.warehouse.agent_share_used, limit: orderCapacity.warehouse.agent_share_limit }) }}
                </p>
            </div>

            <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                            <FontAwesomeIcon :icon="faStopwatch" fixed-width aria-hidden="true" />
                        </span>
                        <span class="truncate text-sm font-medium text-gray-600">{{ ctrans("Lead time") }} · {{ orgAgent.name }}</span>
                    </div>
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
                <p class="mt-1 text-xs text-gray-400">{{ ctrans(":total products in their catalogue", { total: locale.number(coverTotal) }) }}</p>
            </div>
        </section>
    </div>
</template>
