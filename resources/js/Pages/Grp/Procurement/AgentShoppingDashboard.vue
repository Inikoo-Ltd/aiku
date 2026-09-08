<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 31 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref, computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle } from "@fal"
import ModalAutoFillAgentShoppingList from "@/Components/Procurement/ModalAutoFillAgentShoppingList.vue"

library.add(faExclamationTriangle)

type LeadTime = { days: number, source: "measured" | "estimate" | "default", samples: number }
type RankBreakdown = { rank: string, count: number, on_list: number }
type CoverBucket = { bucket: string, label: string, tone: keyof typeof toneClasses, count: number, on_list: number, on_the_way: number, untouched: number, suppliers: number, stock_value: number, ranks: RankBreakdown[] }
type SupplierRow = LeadTime & {
    supplier_id: number
    code: string
    name: string
    open_orders: number
    late_orders: number
    worst_days_late: number | null
    no_eta_orders: number
    open_deliveries: number
    list_lines: number
}
type OrderCapacity = {
    agent_capacity: { lands_for_us_per_30d: number | null, source: "measured" | "sales" | "none", samples: number }
    list: { value: number, lines: number, units: number }
    warehouse: { total_locations: number, empty_locations: number, free_ratio: number | null, inbound_open_po_lines: number, agent_share_used: number, agent_share_limit: number }
    currency: string
    blocked: { at_capacity: boolean, warehouse_full: boolean }
}
type SupplierPurchaseOrder = { id: number, slug: string, reference: string, state: string, supplier_code: string | null, supplier_id: number | null, date: string, days_old: number, days_late: number | null, no_eta: boolean }
type OpenStockDelivery = { id: number, slug: string, reference: string, state: string, supplier_code: string | null, items: number, days_in_transit: number | null, date: string, days_old: number }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgAgent: { id: number, slug: string, name: string, currency: string }
    shoppingListRoute: routeType
    stockDeliveriesRoute: routeType
    supplierPurchaseOrdersRoute: routeType
    stats: { open_items_count: number, oldest_item_at: string | null, estimated_total: number }
    coverBuckets: CoverBucket[]
    coverTotal: number
    leadTime: LeadTime
    orderCapacity: OrderCapacity
    suppliers: SupplierRow[]
    openSupplierPurchaseOrders: SupplierPurchaseOrder[]
    openStockDeliveries: OpenStockDelivery[]
}>()

const locale = useLocaleStore()

const toneClasses = {
    "red-deep": "text-red-800",
    red: "text-red-600",
    orange: "text-orange-600",
    amber: "text-amber-600",
    yellow: "text-yellow-600",
    green: "text-green-700",
    slate: "text-slate-500",
    violet: "text-violet-700",
    gray: "text-gray-600",
}

const notOrderable = ["ok", "dead", "gone", "never"]

const capacityShare = () => {
    const cap = props.orderCapacity.agent_capacity.lands_for_us_per_30d
    return cap ? Math.min(props.orderCapacity.list.value / cap, 1) : null
}

const meterTone = (share: number) => (share >= 1 ? "bg-red-500" : share >= 0.8 ? "bg-amber-500" : "bg-green-500")

const warehouseSegment = (part: number) => `${(part / Math.max(props.orderCapacity.warehouse.total_locations, 1)) * 100}%`

const dashboardReload = { only: ["coverBuckets", "coverTotal", "orderCapacity", "stats"], preserveScroll: true }

const removeMisplaced = (bucket: string) => {
    router.delete(
        route("grp.org.procurement.org_agents.show.shopping.misplaced.destroy", [route().params.organisation, props.orgAgent.slug]),
        { data: { bucket }, ...dashboardReload }
    )
}

const autoFillOpen = ref(false)
const autoFillScope = ref<{ bucket: string, rank: string | null, supplierId: number | null, label: string } | null>(null)

const openAutoFill = (bucket: string, label: string, rank: string | null = null, supplierId: number | null = null) => {
    autoFillScope.value = { bucket, rank, supplierId, label: rank ? `${label} · ${rank}` : label }
    autoFillOpen.value = true
}

const worstBucket = computed(() => props.coverBuckets.find((bucket) => !notOrderable.includes(bucket.bucket) && bucket.untouched > 0))

const fillFromSupplier = (supplier: SupplierRow) => {
    const bucket = worstBucket.value
    if (bucket) {
        openAutoFill(bucket.bucket, `${bucket.label} · ${supplier.code}`, null, supplier.supplier_id)
    }
}

const bucketItemsRoute = (bucket: string, rank?: string) =>
    `${route("grp.org.procurement.org_agents.show.shopping.items.index", [route().params.organisation, props.orgAgent.slug])}?cover=${bucket}${rank ? `&rank=${rank}` : ""}`

const shouldNotBeOrdered = (bucket: CoverBucket) => ["ok", "dead", "gone"].includes(bucket.bucket) && bucket.on_list > 0

const needsAction = (bucket: CoverBucket) => !notOrderable.includes(bucket.bucket) && bucket.untouched > 0

const segmentWidth = (bucket: CoverBucket, part: number) => (bucket.count ? `${Math.max((part / bucket.count) * 100, part ? 1.5 : 0)}%` : "0%")

const rankClasses: Record<string, string> = { A: "", B: "", C: "", D: "opacity-50", Z: "opacity-50" }

const problemThreshold = () => Math.max(props.leadTime.days * 2, 30)

const agingThreshold = () => Math.max(Math.round(props.leadTime.days * 1.2), 21)

const ageClasses = (daysOld: number) =>
    daysOld > problemThreshold() ? "text-red-600" : daysOld > agingThreshold() ? "text-amber-600" : "text-gray-400"

const stockDeliveryColumns = computed(() =>
    [
        { key: "being_prepared", label: trans("Being prepared"), states: ["in_process", "confirmed"], dot: "bg-indigo-200", badge: "bg-indigo-50 text-indigo-600" },
        { key: "ready_to_ship", label: trans("Ready to ship"), states: ["ready_to_ship"], dot: "bg-indigo-300", badge: "bg-indigo-50 text-indigo-600" },
        { key: "in_transit", label: trans("In transit"), states: ["dispatched"], dot: "bg-indigo-500", badge: "bg-indigo-100 text-indigo-700" },
        { key: "arrived", label: trans("Arrived, booking in"), states: ["received", "checked", "booking_in"], dot: "bg-indigo-700", badge: "bg-indigo-100 text-indigo-700" },
    ].map((column) => ({
        ...column,
        deliveries: props.openStockDeliveries.filter((sd) => column.states.includes(sd.state)),
    }))
)

const lateSupplierOrders = computed(() => props.openSupplierPurchaseOrders.filter((order) => order.days_late !== null))

const latestSuppliers = computed(() => props.suppliers.filter((supplier) => supplier.open_orders > 0 || supplier.list_lines > 0 || supplier.open_deliveries > 0))

const leadSourceLabel = (source: LeadTime["source"], samples: number) =>
    source === "measured"
        ? trans("measured from :samples deliveries", { samples })
        : source === "estimate"
          ? trans("estimate from supplier product settings")
          : trans("no data — house default")
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between text-sm text-gray-500">
                <span>{{ trans("Order budget used") }}</span>
                <span v-if="orderCapacity.blocked.at_capacity" class="font-medium text-red-600">{{ trans("at capacity") }}</span>
            </div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ locale.currencyFormat(orgAgent.currency, stats.estimated_total) }}
                <span v-if="orderCapacity.agent_capacity.lands_for_us_per_30d" class="text-sm font-normal text-gray-400">
                    / {{ locale.currencyFormat(orgAgent.currency, orderCapacity.agent_capacity.lands_for_us_per_30d) }}
                </span>
            </div>
            <template v-if="capacityShare() !== null">
                <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100">
                    <div class="h-1.5 rounded-full" :class="meterTone(capacityShare()!)" :style="{ width: `${capacityShare()! * 100}%` }" />
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    <template v-if="orderCapacity.agent_capacity.source === 'measured'">
                        {{ trans("one order cycle of what they historically land for us, measured from :n deliveries", { n: orderCapacity.agent_capacity.samples }) }}
                    </template>
                    <template v-else>
                        {{ trans("budget = one order cycle (:days days) of what we actually sell of their products", { days: leadTime.days + 7 }) }}
                    </template>
                </div>
            </template>
            <div v-else class="mt-2 text-xs text-gray-500">
                {{ trans("No budget: no delivery history and no forecast data yet — the list is uncapped.") }}
            </div>
            <div class="mt-1 text-xs text-gray-400">
                {{ trans("all sub-supplier currencies converted to :currency", { currency: orgAgent.currency }) }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between text-sm text-gray-500">
                <span>{{ trans("Warehouse space") }}</span>
                <span v-if="orderCapacity.blocked.warehouse_full" class="font-medium text-red-600">{{ trans("full") }}</span>
            </div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ orderCapacity.warehouse.empty_locations.toLocaleString() }}
                <span class="text-sm font-normal text-gray-400">/ {{ orderCapacity.warehouse.total_locations.toLocaleString() }} {{ trans("locations free") }}</span>
            </div>
            <div class="mt-2 flex h-1.5 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                <div class="h-1.5 bg-gray-400" :style="{ width: warehouseSegment(orderCapacity.warehouse.total_locations - orderCapacity.warehouse.empty_locations) }" />
                <div class="h-1.5 bg-indigo-400" :style="{ width: warehouseSegment(orderCapacity.warehouse.inbound_open_po_lines) }" />
                <div class="h-1.5 bg-violet-400" :style="{ width: warehouseSegment(orderCapacity.list.lines) }" />
            </div>
            <div class="mt-1 flex flex-wrap gap-x-3 text-xs tabular-nums text-gray-500">
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-gray-400" />{{ (orderCapacity.warehouse.total_locations - orderCapacity.warehouse.empty_locations).toLocaleString() }} {{ trans("in use") }}</span>
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-indigo-400" />{{ orderCapacity.warehouse.inbound_open_po_lines.toLocaleString() }} {{ trans("inbound PO/SD lines") }}</span>
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-violet-400" />{{ orderCapacity.list.lines }} {{ trans("this shopping list") }}</span>
            </div>
            <div class="mt-1 text-xs text-gray-500">
                {{ trans("new products from this agent: :used of :limit free slots (their fair share)", { used: orderCapacity.warehouse.agent_share_used, limit: orderCapacity.warehouse.agent_share_limit }) }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between text-sm text-gray-500">
                <span>{{ orgAgent.name }}</span>
                <span v-if="lateSupplierOrders.length" class="font-medium text-red-600">{{ lateSupplierOrders.length }} {{ trans("late") }}</span>
            </div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ suppliers.length }} <span class="text-sm font-normal text-gray-400">{{ trans("sub-suppliers, each on its own clock") }}</span>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                {{ trans("agent roll-up :days days order → booked in", { days: leadTime.days }) }} · {{ leadSourceLabel(leadTime.source, leadTime.samples) }}
            </div>
            <div v-if="lateSupplierOrders.length" class="mt-1 text-xs text-red-600">
                {{ trans("worst delay :days days (:supplier)", { days: lateSupplierOrders[0].days_late ?? 0, supplier: lateSupplierOrders[0].supplier_code ?? "—" }) }}
            </div>
            <div class="mt-1 text-xs text-gray-400">{{ trans(":total products across their suppliers", { total: coverTotal.toLocaleString() }) }}</div>
        </div>
    </div>

    <div class="mx-4 mt-4 rounded-xl border-2 border-indigo-200 bg-indigo-50/40 p-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <Link
                v-for="bucket in coverBuckets"
                :key="bucket.bucket"
                :href="bucketItemsRoute(bucket.bucket)"
                class="flex flex-col rounded-lg border border-gray-200 bg-white px-3 py-2 hover:border-gray-300 hover:bg-gray-50"
                :class="toneClasses[bucket.tone]"
            >
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-semibold tabular-nums">{{ bucket.count.toLocaleString() }}</span>
                    <span v-if="bucket.bucket === 'dead'" class="ml-auto text-xs tabular-nums opacity-70">
                        {{ locale.currencyFormat(orgAgent.currency, bucket.stock_value) }}
                    </span>
                    <span v-if="shouldNotBeOrdered(bucket)" class="ml-auto text-xs font-medium tabular-nums text-red-600" :title="trans('On the shopping list but not short of stock')">
                        <FontAwesomeIcon :icon="faExclamationTriangle" aria-hidden="true" />
                        {{ bucket.on_list }} {{ trans("on list") }}
                        <button
                            v-if="bucket.bucket !== 'gone'"
                            type="button"
                            class="ml-0.5 rounded border border-red-300 px-1 text-[10px] hover:bg-red-100"
                            @click.prevent.stop="removeMisplaced(bucket.bucket)"
                        >
                            {{ trans("remove") }}
                        </button>
                    </span>
                    <span v-else-if="needsAction(bucket)" class="ml-auto text-xs font-medium tabular-nums">
                        {{ trans(":count need action", { count: bucket.untouched.toLocaleString() }) }}
                    </span>
                    <span v-else-if="bucket.count && !notOrderable.includes(bucket.bucket)" class="ml-auto text-xs tabular-nums opacity-70">
                        {{ trans("all handled") }}
                    </span>
                </div>
                <div class="text-xs leading-4">{{ bucket.label }}</div>
                <div v-if="bucket.suppliers" class="text-[10px] tabular-nums opacity-60">
                    {{ trans("across :n suppliers", { n: bucket.suppliers }) }}
                </div>
                <div v-if="(bucket.on_the_way || bucket.on_list) && !notOrderable.includes(bucket.bucket)" class="mt-1.5 flex h-1 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                    <div v-if="bucket.on_the_way" class="h-1 bg-current" :style="{ width: segmentWidth(bucket, bucket.on_the_way) }" :title="trans('on the way')" />
                    <div v-if="bucket.on_list" class="h-1 bg-current opacity-40" :style="{ width: segmentWidth(bucket, bucket.on_list) }" :title="trans('on the shopping list')" />
                </div>
                <div v-if="(bucket.on_the_way || bucket.on_list) && !notOrderable.includes(bucket.bucket)" class="mt-0.5 text-[10px] tabular-nums opacity-70">
                    <span v-if="bucket.on_the_way">{{ bucket.on_the_way }} {{ trans("on the way") }}</span>
                    <span v-if="bucket.on_the_way && bucket.on_list"> · </span>
                    <span v-if="bucket.on_list">{{ bucket.on_list }} {{ trans("on list") }}</span>
                </div>
                <div v-if="bucket.ranks.length" class="mt-auto flex gap-2 pt-1.5 text-xs tabular-nums">
                    <Link
                        v-for="rank in bucket.ranks.filter((rank) => rank.count > 0)"
                        :key="rank.rank"
                        :href="bucketItemsRoute(bucket.bucket, rank.rank)"
                        class="hover:underline"
                        :class="rankClasses[rank.rank]"
                        @click.stop
                    >
                        <span class="font-bold">{{ rank.rank }}</span> {{ rank.count }} <span class="text-[10px] opacity-60">{{ rank.on_list }}</span>
                    </Link>
                    <button
                        v-if="!notOrderable.includes(bucket.bucket) && bucket.ranks.some((rank) => rank.count > rank.on_list)"
                        type="button"
                        class="ml-auto rounded border border-current px-1 text-[10px] opacity-60 hover:opacity-100"
                        :title="trans('Fill the shopping list from this bucket')"
                        @click.prevent.stop="openAutoFill(bucket.bucket, bucket.label)"
                    >
                        + {{ trans("fill") }}
                    </button>
                </div>
            </Link>
        </div>
    </div>

    <div class="mx-4 mt-6">
        <h3 class="text-sm font-semibold text-gray-700">
            <Link class="hover:underline" :href="route(stockDeliveriesRoute.name, stockDeliveriesRoute.parameters)">
                {{ trans("Order pipeline") }}
            </Link>
        </h3>
        <div class="mt-2 grid grid-cols-2 gap-3 lg:grid-cols-6">
            <div class="rounded-lg bg-gray-50 p-2">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                        <span class="h-2 w-2 rounded-full bg-indigo-100 ring-1 ring-indigo-300" />
                        {{ trans("On shopping list") }}
                    </span>
                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium tabular-nums text-indigo-600">{{ stats.open_items_count }}</span>
                </div>
                <Link :href="route(shoppingListRoute.name, shoppingListRoute.parameters)" class="block rounded-md bg-white p-2 shadow-sm hover:bg-indigo-50">
                    <div class="text-sm font-bold text-gray-900">{{ stats.open_items_count }} {{ trans("items") }}</div>
                    <div class="text-xs text-gray-500">{{ locale.currencyFormat(orgAgent.currency, stats.estimated_total) }} {{ trans("waiting to be ordered") }}</div>
                    <div v-if="stats.oldest_item_at" class="mt-1 text-xs text-gray-500">
                        {{ trans("oldest since") }} {{ useFormatTime(stats.oldest_item_at, { formatTime: "mdy" }) }}
                    </div>
                </Link>
            </div>

            <div class="rounded-lg bg-gray-50 p-2">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                        <span class="h-2 w-2 rounded-full bg-purple-300" />
                        {{ trans("With the suppliers") }}
                    </span>
                    <span class="rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium tabular-nums text-purple-700">{{ openSupplierPurchaseOrders.length }}</span>
                </div>
                <div class="flex flex-col gap-2">
                    <Link
                        v-for="order in openSupplierPurchaseOrders.slice(0, 12)"
                        :key="order.id"
                        :href="route('grp.org.procurement.agent_supplier_purchase_orders.show', [route().params.organisation, order.slug])"
                        class="block rounded-md border-l-2 p-2 shadow-sm hover:ring-1 hover:ring-purple-300"
                        :class="order.days_late && order.days_late > problemThreshold() ? 'border-red-400 bg-red-50' : ['bg-white', order.days_late ? 'border-amber-400' : 'border-transparent']"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="whitespace-nowrap text-sm font-bold text-gray-900">{{ order.supplier_code ?? order.reference }}</span>
                            <span class="whitespace-nowrap text-xs tabular-nums" :class="ageClasses(order.days_old)">{{ useFormatTime(order.date, { formatTime: "mdy" }) }}</span>
                        </div>
                        <div class="text-xs text-gray-500">{{ order.reference }}</div>
                        <div v-if="order.days_late" class="mt-1 text-xs font-semibold tabular-nums text-red-600">
                            {{ order.days_late }} {{ trans("days late") }}
                            <span v-if="order.no_eta" class="font-normal text-gray-400">· {{ trans("no ETA") }}</span>
                        </div>
                    </Link>
                    <Link
                        v-if="openSupplierPurchaseOrders.length > 12"
                        :href="route(supplierPurchaseOrdersRoute.name, supplierPurchaseOrdersRoute.parameters)"
                        class="px-1 py-1 text-xs text-indigo-600 hover:underline"
                    >
                        {{ trans("and :n more", { n: openSupplierPurchaseOrders.length - 12 }) }}
                    </Link>
                    <div v-if="!openSupplierPurchaseOrders.length" class="px-1 py-2 text-xs text-gray-400">—</div>
                </div>
            </div>

            <div v-for="column in stockDeliveryColumns" :key="column.key" class="rounded-lg bg-gray-50 p-2">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                        <span class="h-2 w-2 rounded-full" :class="column.dot" />
                        {{ column.label }}
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium tabular-nums" :class="column.badge" :title="trans('deliveries')">{{ column.deliveries.length }}</span>
                    </span>
                </div>
                <div class="flex flex-col gap-2">
                    <Link
                        v-for="sd in column.deliveries"
                        :key="sd.id"
                        :href="route('grp.org.procurement.stock_deliveries.show', [route().params.organisation, sd.slug])"
                        class="block rounded-md border-l-2 p-2 shadow-sm hover:ring-1 hover:ring-indigo-300"
                        :class="sd.days_old > problemThreshold() ? 'border-red-400 bg-red-50' : ['bg-white', sd.days_old > agingThreshold() ? 'border-amber-400' : 'border-transparent']"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="whitespace-nowrap text-sm font-bold text-gray-900">{{ sd.reference }}</span>
                            <span class="whitespace-nowrap text-xs tabular-nums" :class="ageClasses(sd.days_old)">{{ useFormatTime(sd.date, { formatTime: "mdy" }) }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            <span v-if="sd.supplier_code" class="font-medium">{{ sd.supplier_code }} · </span>{{ sd.items }} {{ trans("items") }}
                        </div>
                        <div
                            v-if="column.key === 'in_transit' && sd.days_in_transit !== null"
                            class="mt-1 text-xs font-semibold tabular-nums"
                            :class="sd.days_in_transit > 45 ? 'text-amber-600' : 'text-gray-600'"
                        >
                            {{ sd.days_in_transit }} {{ trans("days in transit") }}
                        </div>
                    </Link>
                    <div v-if="!column.deliveries.length" class="px-1 py-2 text-xs text-gray-400">—</div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="latestSuppliers.length" class="mx-4 mb-8 mt-6">
        <h3 class="text-sm font-semibold text-gray-700">
            {{ trans("Sub-suppliers behind this agent") }}
        </h3>
        <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th class="px-4 py-2 font-normal">{{ trans("Supplier") }}</th>
                        <th class="px-4 py-2 text-right font-normal">{{ trans("Lead time") }}</th>
                        <th class="px-4 py-2 text-right font-normal">{{ trans("Open orders") }}</th>
                        <th class="px-4 py-2 text-right font-normal">{{ trans("Late") }}</th>
                        <th class="px-4 py-2 text-right font-normal">{{ trans("In the pipeline") }}</th>
                        <th class="px-4 py-2 text-right font-normal">{{ trans("On list") }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="supplier in latestSuppliers" :key="supplier.supplier_id">
                        <td class="px-4 py-2">
                            <span class="font-medium text-gray-900">{{ supplier.code }}</span>
                            <span class="ml-2 text-xs text-gray-500">{{ supplier.name }}</span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ supplier.days }}<span class="text-xs text-gray-400">{{ trans("d") }}</span>
                            <div class="text-[10px] text-gray-400">{{ leadSourceLabel(supplier.source, supplier.samples) }}</div>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ supplier.open_orders || "—" }}</td>
                        <td class="px-4 py-2 text-right tabular-nums" :class="supplier.late_orders ? 'font-medium text-red-600' : 'text-gray-400'">
                            <template v-if="supplier.late_orders">
                                {{ supplier.late_orders }}
                                <div class="text-[10px] font-normal">{{ trans("worst :days days", { days: supplier.worst_days_late ?? 0 }) }}</div>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ supplier.open_deliveries || "—" }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ supplier.list_lines || "—" }}</td>
                        <td class="px-4 py-2 text-right">
                            <button
                                v-if="worstBucket"
                                type="button"
                                class="rounded border border-gray-300 px-1.5 text-[10px] text-gray-500 hover:bg-gray-50 hover:text-gray-800"
                                :title="trans('Fill the shopping list from this supplier')"
                                @click="fillFromSupplier(supplier)"
                            >
                                + {{ trans("fill") }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <ModalAutoFillAgentShoppingList
        v-model="autoFillOpen"
        :orgAgentSlug="orgAgent.slug"
        :currency="orgAgent.currency"
        :bucket="autoFillScope?.bucket"
        :rank="autoFillScope?.rank"
        :supplierId="autoFillScope?.supplierId"
        :scopeLabel="autoFillScope?.label"
    />
</template>
