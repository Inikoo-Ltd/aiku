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
import ModalAutoFillSupplierShoppingList from "@/Components/Procurement/ModalAutoFillSupplierShoppingList.vue"

library.add(faExclamationTriangle)

type RankBreakdown = { rank: string, count: number, on_list: number }
type CoverBucket = { bucket: string, label: string, tone: keyof typeof toneClasses, count: number, on_list: number, on_the_way: number, untouched: number, stock_value: number, ranks: RankBreakdown[] }
type OrderCapacity = {
    supplier_capacity: { delivers_to_us_per_30d: number | null, source: string, samples: number }
    list: { value: number, lines: number }
    warehouse: { total_locations: number, empty_locations: number, free_ratio: number | null, inbound_open_po_lines: number, supplier_share_used: number, supplier_share_limit: number }
    blocked: { at_capacity: boolean, warehouse_full: boolean }
}
type OpenStockDelivery = { id: number, slug: string, reference: string, state: string, items: number, days_in_transit: number | null, date: string, days_old: number }
type LatePurchaseOrder = { id: number, slug: string, reference: string, state: string, days_late: number, no_eta: boolean }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgSupplier: { id: number, slug: string, name: string, currency: string }
    productsRoute: routeType
    shoppingListRoute: routeType
    stockDeliveriesRoute: routeType
    stats: { open_items_count: number, oldest_item_at: string | null, estimated_total: number }
    coverBuckets: CoverBucket[]
    coverTotal: number
    leadTime: { days: number, source: "measured" | "estimate", samples: number, measured_products: number, products: number }
    latePurchaseOrders: LatePurchaseOrder[]
    openStockDeliveries: OpenStockDelivery[]
    orderCapacity: OrderCapacity
}>()

const toneClasses = {
    "red-deep": "text-red-800",
    red: "text-red-600",
    orange: "text-orange-600",
    amber: "text-amber-600",
    yellow: "text-yellow-600",
    green: "text-green-700",
    violet: "text-violet-700",
    gray: "text-gray-600",
}

const capacityShare = () => {
    const cap = props.orderCapacity.supplier_capacity.delivers_to_us_per_30d
    return cap ? Math.min(props.orderCapacity.list.value / cap, 1) : null
}

const problemThreshold = () => Math.max(props.leadTime.days * 10, 30)

const isProblemOrder = (sd: OpenStockDelivery) => sd.days_old > problemThreshold()

const agingThreshold = () => Math.max(props.leadTime.days * 3, 14)

const ageClasses = (daysOld: number) => (daysOld > problemThreshold() ? "text-red-600" : daysOld > agingThreshold() ? "text-amber-600" : "text-gray-400")

const dashboardReload = { only: ["coverBuckets", "coverTotal", "orderCapacity", "stats"], preserveScroll: true }

const removeMisplaced = (bucket: string) => {
    router.delete(
        route("grp.org.procurement.org_suppliers.show.shopping.misplaced.destroy", [route().params.organisation, props.orgSupplier.slug]),
        { data: { bucket }, ...dashboardReload }
    )
}

const autoFillOpen = ref(false)
const autoFillScope = ref<{ bucket: string, rank: string | null, label: string } | null>(null)

const openAutoFill = (bucket: { bucket: string, label: string }, rank: string | null = null) => {
    autoFillScope.value = { bucket: bucket.bucket, rank, label: rank ? `${bucket.label} · ${rank}` : bucket.label }
    autoFillOpen.value = true
}

const warehouseSegment = (part: number) => `${(part / Math.max(props.orderCapacity.warehouse.total_locations, 1)) * 100}%`

const meterTone = (share: number) => (share >= 1 ? "bg-red-500" : share >= 0.8 ? "bg-amber-500" : "bg-green-500")

const stockDeliveryColumns = computed(() => [
    { key: "being_prepared", label: trans("Being prepared"), states: ["in_process", "confirmed"], card: "bg-white", dot: "bg-indigo-200", badge: "bg-indigo-50 text-indigo-600" },
    { key: "ready_to_ship", label: trans("Ready to ship"), states: ["ready_to_ship"], card: "bg-white", dot: "bg-indigo-300", badge: "bg-indigo-50 text-indigo-600" },
    { key: "in_transit", label: trans("In transit"), states: ["dispatched"], card: "bg-white", dot: "bg-indigo-500", badge: "bg-indigo-100 text-indigo-700" },
    { key: "arrived", label: trans("Arrived, booking in"), states: ["received", "checked", "booking_in"], card: "bg-white", dot: "bg-indigo-700", badge: "bg-indigo-100 text-indigo-700" },
].map((column) => ({
    ...column,
    deliveries: props.openStockDeliveries.filter((sd) => column.states.includes(sd.state)),
})))

const shouldNotBeOrdered = (bucket: CoverBucket) => ["ok", "dead"].includes(bucket.bucket) && bucket.on_list > 0

const segmentWidth = (bucket: CoverBucket, part: number) => (bucket.count ? `${Math.max((part / bucket.count) * 100, part ? 1.5 : 0)}%` : "0%")

const needsAction = (bucket: CoverBucket) => !["ok", "dead", "never"].includes(bucket.bucket) && bucket.untouched > 0

const bucketRoute = (bucket: string, rank?: string) =>
    `${route("grp.org.procurement.org_suppliers.show.shopping.items.index", [route().params.organisation, props.orgSupplier.slug])}?cover=${bucket}${rank ? `&rank=${rank}` : ""}`

const rankClasses: Record<string, string> = {
    A: "",
    B: "",
    C: "",
    D: "opacity-50",
    Z: "opacity-50",
}
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
                {{ useLocaleStore().currencyFormat(orgSupplier.currency, stats.estimated_total) }}
                <span v-if="orderCapacity.supplier_capacity.delivers_to_us_per_30d" class="text-sm font-normal text-gray-400">
                    / {{ useLocaleStore().currencyFormat(orgSupplier.currency, orderCapacity.supplier_capacity.delivers_to_us_per_30d) }}
                </span>
            </div>
            <template v-if="capacityShare() !== null">
                <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100">
                    <div class="h-1.5 rounded-full" :class="meterTone(capacityShare()!)" :style="{ width: `${capacityShare()! * 100}%` }" />
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    <template v-if="orderCapacity.supplier_capacity.source === 'measured'">
                        {{ trans("one order cycle of what they historically deliver to us, measured from :n deliveries", { n: orderCapacity.supplier_capacity.samples }) }}
                    </template>
                    <template v-else>
                        {{ trans("budget = one order cycle (:days days) of what we actually sell of their products, at their cost", { days: Math.min(leadTime.days + 7, 30) }) }}
                    </template>
                </div>
            </template>
            <div v-else class="mt-2 text-xs text-gray-500">
                {{ trans("No budget: no delivery history and no dispatch data yet — the list is uncapped.") }}
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
                {{ trans("new products from this supplier: :used of :limit free slots (their fair share)", { used: orderCapacity.warehouse.supplier_share_used, limit: orderCapacity.warehouse.supplier_share_limit }) }}
            </div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between text-sm text-gray-500">
                <span>{{ orgSupplier.name }}</span>
                <span v-if="latePurchaseOrders.length" class="font-medium text-red-600">{{ latePurchaseOrders.length }} {{ trans("late") }}</span>
            </div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ leadTime.days }} <span class="text-sm font-normal text-gray-400">{{ trans("days order → booked in") }}</span>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                <template v-if="leadTime.source === 'measured'">
                    {{ trans("median across their products, measured on :measured of :total from :samples deliveries", { measured: leadTime.measured_products, total: leadTime.products, samples: leadTime.samples }) }}
                </template>
                <template v-else>
                    {{ trans("estimate — set per product") }}
                    <Link class="underline" :href="route(productsRoute.name, productsRoute.parameters)">{{ trans("in their product list") }}</Link>
                </template>
            </div>
            <div v-if="latePurchaseOrders.length" class="mt-1 text-xs text-red-600">
                {{ trans("worst delay :days days", { days: latePurchaseOrders[0].days_late }) }}
            </div>
            <div class="mt-1 text-xs text-gray-400">{{ trans(":total products they sell us", { total: coverTotal.toLocaleString() }) }}</div>
        </div>
    </div>

    <div class="mx-4 mt-4 rounded-xl border-2 border-indigo-200 bg-indigo-50/40 p-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link
                v-for="bucket in coverBuckets"
                :key="bucket.bucket"
                :href="bucketRoute(bucket.bucket)"
                class="flex flex-col rounded-lg border border-gray-200 bg-white px-3 py-2 hover:border-gray-300 hover:bg-gray-50"
                :class="toneClasses[bucket.tone]"
            >
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-semibold tabular-nums">{{ bucket.count }}</span>
                    <span v-if="bucket.bucket === 'dead'" class="ml-auto text-xs tabular-nums opacity-70">
                        {{ useLocaleStore().currencyFormat(orgSupplier.currency, bucket.stock_value) }}
                    </span>
                    <span v-if="shouldNotBeOrdered(bucket)" class="text-xs font-medium tabular-nums text-red-600" :title="trans('On the shopping list but not short of stock')">
                        <FontAwesomeIcon :icon="faExclamationTriangle" aria-hidden="true" />
                        {{ bucket.on_list }} {{ trans("on list") }}
                        <button type="button" class="ml-0.5 rounded border border-red-300 px-1 text-[10px] hover:bg-red-100" @click.prevent.stop="removeMisplaced(bucket.bucket)">
                            {{ trans("remove") }}
                        </button>
                    </span>
                    <span v-else-if="needsAction(bucket)" class="text-xs font-medium tabular-nums">
                        {{ trans(":count need action", { count: bucket.untouched.toLocaleString() }) }}
                    </span>
                    <span v-else-if="bucket.count && !['ok', 'dead', 'never'].includes(bucket.bucket)" class="text-xs tabular-nums opacity-70">
                        {{ trans("all handled") }}
                    </span>
                </div>
                <div class="text-xs leading-4">{{ bucket.label }}</div>
                <div v-if="(bucket.on_the_way || bucket.on_list) && !['ok', 'dead', 'never'].includes(bucket.bucket)" class="mt-1.5 flex h-1 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                    <div v-if="bucket.on_the_way" class="h-1 bg-current" :style="{ width: segmentWidth(bucket, bucket.on_the_way) }" :title="trans('on the way')" />
                    <div v-if="bucket.on_list" class="h-1 bg-current opacity-40" :style="{ width: segmentWidth(bucket, bucket.on_list) }" :title="trans('on the shopping list')" />
                </div>
                <div v-if="(bucket.on_the_way || bucket.on_list) && !['ok', 'dead', 'never'].includes(bucket.bucket)" class="mt-0.5 text-[10px] tabular-nums opacity-70">
                    <span v-if="bucket.on_the_way">{{ bucket.on_the_way }} {{ trans("on the way") }}</span>
                    <span v-if="bucket.on_the_way && bucket.on_list"> · </span>
                    <span v-if="bucket.on_list">{{ bucket.on_list }} {{ trans("on list") }}</span>
                </div>
                <div v-if="bucket.bucket !== 'never'" class="mt-auto flex gap-2 pt-1.5 text-xs tabular-nums">
                    <Link
                        v-for="rank in bucket.ranks.filter((rank) => rank.count > 0)"
                        :key="rank.rank"
                        :href="bucketRoute(bucket.bucket, rank.rank)"
                        class="hover:underline"
                        :class="rankClasses[rank.rank]"
                        @click.stop
                    >
                        <span class="font-bold">{{ rank.rank }}</span> {{ rank.count }} <span class="text-[10px] opacity-60">{{ rank.on_list }}</span>
                    </Link>
                    <button
                        v-if="!['ok', 'dead', 'never'].includes(bucket.bucket) && bucket.ranks.some((rank) => rank.count > rank.on_list)"
                        type="button"
                        class="ml-auto rounded border border-current px-1 text-[10px] opacity-60 hover:opacity-100"
                        :title="trans('Auto-fill the shopping list from this bucket')"
                        @click.prevent.stop="openAutoFill(bucket)"
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
        <div class="mt-2 grid grid-cols-2 gap-3 lg:grid-cols-5">
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
                    <div class="text-xs text-gray-500">{{ useLocaleStore().currencyFormat(orgSupplier.currency, stats.estimated_total) }} {{ trans("waiting to be ordered") }}</div>
                    <div v-if="stats.oldest_item_at" class="mt-1 text-xs text-gray-500">
                        {{ trans("oldest since") }} {{ useFormatTime(stats.oldest_item_at, { formatTime: "mdy" }) }}
                    </div>
                </Link>
            </div>
            <div v-for="column in stockDeliveryColumns" :key="column.key" class="rounded-lg bg-gray-50 p-2">
                <div class="mb-2 flex items-center justify-between px-1">
                    <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
                        <span class="h-2 w-2 rounded-full" :class="column.dot" />
                        {{ column.label }}
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium tabular-nums" :class="column.badge" :title="trans('deliveries')">{{ column.deliveries.length }}</span>
                        <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium tabular-nums text-violet-700" :title="trans('items')">{{ column.deliveries.reduce((sum, sd) => sum + sd.items, 0) }} {{ trans("items") }}</span>
                    </span>
                </div>
                <div class="flex flex-col gap-2">
                    <Link
                        v-for="sd in column.deliveries"
                        :key="sd.id"
                        :href="route('grp.org.procurement.stock_deliveries.show', [route().params.organisation, sd.slug])"
                        class="block rounded-md border-l-2 p-2 shadow-sm hover:ring-1 hover:ring-indigo-300"
                        :class="isProblemOrder(sd) ? 'border-red-400 bg-red-50' : [column.card, sd.days_old > agingThreshold() ? 'border-amber-400' : 'border-transparent']"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="whitespace-nowrap text-sm font-bold" :class="isProblemOrder(sd) ? 'text-red-700' : 'text-gray-900'">{{ sd.reference }}</span>
                            <span class="whitespace-nowrap text-xs tabular-nums" :class="ageClasses(sd.days_old)">{{ useFormatTime(sd.date, { formatTime: "mdy" }) }}</span>
                        </div>
                        <div class="text-xs text-gray-500">{{ sd.items }} {{ trans("items") }} · {{ sd.state.replace("_", " ") }}</div>
                        <div
                            v-if="column.key === 'in_transit' && sd.days_in_transit !== null"
                            class="mt-1 text-xs font-semibold tabular-nums"
                            :class="sd.days_in_transit > 14 ? 'text-amber-600' : 'text-gray-600'"
                        >
                            {{ sd.days_in_transit }} {{ trans("days in transit") }}
                        </div>
                    </Link>
                    <div v-if="!column.deliveries.length" class="px-1 py-2 text-xs text-gray-400">—</div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="latePurchaseOrders.length" class="mx-4 mt-6 max-w-3xl">
        <h3 class="text-sm font-semibold text-gray-700">{{ trans("Late from this supplier") }}</h3>
        <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            <li v-for="purchaseOrder in latePurchaseOrders" :key="purchaseOrder.id" class="flex items-center justify-between gap-3 px-4 py-3">
                <div>
                    <Link
                        class="text-sm font-medium text-indigo-600 hover:underline"
                        :href="route('grp.org.procurement.org_suppliers.show.purchase-orders.show', [route().params.organisation, orgSupplier.slug, purchaseOrder.slug])"
                    >
                        {{ purchaseOrder.reference }}
                    </Link>
                    <div class="text-xs text-gray-500">{{ purchaseOrder.state }}</div>
                </div>
                <div class="text-right text-sm font-semibold tabular-nums" :class="purchaseOrder.days_late > 14 ? 'text-red-600' : 'text-amber-600'">
                    {{ purchaseOrder.days_late }} {{ trans("days late") }}
                    <div v-if="purchaseOrder.no_eta" class="text-xs font-normal text-gray-400">{{ trans("no delivery date given") }}</div>
                </div>
            </li>
        </ul>
    </div>

    <ModalAutoFillSupplierShoppingList
        v-model="autoFillOpen"
        :orgSupplierSlug="orgSupplier.slug"
        :currency="orgSupplier.currency"
        :bucket="autoFillScope?.bucket"
        :rank="autoFillScope?.rank"
        :scopeLabel="autoFillScope?.label"
    />
</template>
