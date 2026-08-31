<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 29 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ProcurementOverviewCard from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewCard.vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle } from "@fal"

library.add(faExclamationTriangle)

type PriorityBreakdown = { priority: string, label: string, count: number }
type RankBreakdown = { rank: string, count: number, on_list: number }
type CoverBucket = { bucket: string, label: string, tone: keyof typeof toneClasses, count: number, on_list: number, on_the_way: number, untouched: number, stock_value: number, ranks: RankBreakdown[] }
type OpenStockDelivery = { id: number, slug: string, reference: string, state: string, items: number, days_in_transit: number | null }
type LatePurchaseOrder = { id: number, slug: string, reference: string, state: string, days_late: number, no_eta: boolean }
type RecentItem = { id: number, quantity: number, created_at: string, org_stock_code: string | null, org_stock_name: string | null, added_by_name: string | null }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgPartner: { id: number, slug: string, currency: string }
    browseRoute: routeType
    shoppingListRoute: routeType
    canBrowse: boolean
    stats: { open_items_count: number, estimated_total: number, priority_breakdown: PriorityBreakdown[] }
    recentItems: RecentItem[]
    coverBuckets: CoverBucket[]
    coverTotal: number
    leadTime: { days: number, source: "measured" | "estimate", samples: number }
    leadTimeRoute: routeType
    latePurchaseOrders: LatePurchaseOrder[]
    openStockDeliveries: OpenStockDelivery[]
    stockDeliveriesRoute: routeType
}>()

const toneClasses = {
    "red-deep": "border-red-300 bg-red-100 text-red-800 hover:bg-red-200",
    red: "border-red-200 bg-red-50 text-red-700 hover:bg-red-100",
    orange: "border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100",
    amber: "border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100",
    yellow: "border-yellow-200 bg-yellow-50 text-yellow-700 hover:bg-yellow-100",
    green: "border-green-200 bg-green-50 text-green-700 hover:bg-green-100",
    violet: "border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100",
    gray: "border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100",
}

const inTransit = () => props.openStockDeliveries.filter((sd) => ["ready_to_ship", "dispatched"].includes(sd.state))
const inPreparation = () => props.openStockDeliveries.filter((sd) => ["in_process", "confirmed"].includes(sd.state))

const shouldNotBeOrdered = (bucket: CoverBucket) => ["ok", "dead"].includes(bucket.bucket) && bucket.on_list > 0

const segmentWidth = (bucket: CoverBucket, part: number) => (bucket.count ? `${Math.max((part / bucket.count) * 100, part ? 1.5 : 0)}%` : "0%")

const needsAction = (bucket: CoverBucket) => !["ok", "dead", "never"].includes(bucket.bucket) && bucket.untouched > 0

const bucketRoute = (bucket: string, rank?: string) => `${route(props.browseRoute.name, props.browseRoute.parameters)}?cover=${bucket}${rank ? `&rank=${rank}` : ""}`

const rankClasses: Record<string, string> = {
    A: "text-green-700",
    B: "text-blue-700",
    C: "",
    D: "text-gray-500",
    Z: "text-gray-500",
}

const overviewCard = {
    label: trans("Shopping list"),
    description: trans("Items waiting to be ordered"),
    icon: "fal fa-list",
    value: props.stats.open_items_count,
    tone: "amber" as const,
    route: props.shoppingListRoute,
    metrics: props.stats.priority_breakdown
        .filter((priority) => priority.count > 0)
        .map((priority) => ({ label: priority.label, value: priority.count, route: props.shoppingListRoute })),
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-3 flex flex-wrap gap-3">
        <Link v-if="canBrowse" :href="route(browseRoute.name, browseRoute.parameters)">
            <Button icon="fal fa-store" :label="trans('Browse catalogue')" type="primary" />
        </Link>
        <Link :href="route(shoppingListRoute.name, shoppingListRoute.parameters)">
            <Button icon="fal fa-list" :label="trans('Shopping list')" />
        </Link>
    </div>

    <div v-if="canBrowse" class="mx-4 mt-4">
        <h3 class="text-sm font-semibold text-gray-700">
            {{ trans("Stock at risk") }}
            <span class="ml-1 font-normal text-gray-400">{{ trans(":total products this partner sells", { total: coverTotal.toLocaleString() }) }}</span>
            <span class="ml-2 font-normal text-gray-400">
                ·
                <template v-if="leadTime.source === 'measured'">
                    {{ trans("~:days-day lead time, measured from :samples deliveries", { days: leadTime.days, samples: leadTime.samples }) }}
                </template>
                <template v-else>
                    {{ trans(":days-day lead time (estimate — set per product in supplier settings)", { days: leadTime.days }) }}
                </template>
            </span>
        </h3>
        <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link
                v-for="bucket in coverBuckets"
                :key="bucket.bucket"
                :href="bucketRoute(bucket.bucket)"
                class="rounded-lg border px-3 py-2"
                :class="toneClasses[bucket.tone]"
            >
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-semibold tabular-nums">{{ bucket.count }}</span>
                    <span v-if="shouldNotBeOrdered(bucket)" class="text-xs font-medium tabular-nums text-red-600" :title="trans('On the shopping list but not short of stock')">
                        <FontAwesomeIcon :icon="faExclamationTriangle" aria-hidden="true" />
                        {{ bucket.on_list }} {{ trans("on list") }}
                    </span>
                    <span v-else-if="needsAction(bucket)" class="text-xs font-medium tabular-nums">
                        {{ trans(":count need action", { count: bucket.untouched.toLocaleString() }) }}
                    </span>
                    <span v-else-if="bucket.count && bucket.bucket !== 'ok' && bucket.bucket !== 'dead' && bucket.bucket !== 'never'" class="text-xs tabular-nums opacity-70">
                        {{ trans("all handled") }}
                    </span>
                </div>
                <div class="text-xs leading-4">{{ bucket.label }}</div>
                <div v-if="bucket.count && !['ok', 'dead', 'never'].includes(bucket.bucket)" class="mt-1.5 flex h-1 w-full gap-px overflow-hidden rounded-full bg-white/70">
                    <div v-if="bucket.on_the_way" class="h-1 bg-current" :style="{ width: segmentWidth(bucket, bucket.on_the_way) }" :title="trans('on the way')" />
                    <div v-if="bucket.on_list" class="h-1 bg-current opacity-40" :style="{ width: segmentWidth(bucket, bucket.on_list) }" :title="trans('on the shopping list')" />
                </div>
                <div v-if="(bucket.on_the_way || bucket.on_list) && !['ok', 'dead', 'never'].includes(bucket.bucket)" class="mt-0.5 text-[10px] tabular-nums opacity-70">
                    <span v-if="bucket.on_the_way">{{ bucket.on_the_way }} {{ trans("on the way") }}</span>
                    <span v-if="bucket.on_the_way && bucket.on_list"> · </span>
                    <span v-if="bucket.on_list">{{ bucket.on_list }} {{ trans("on list") }}</span>
                </div>
                <div v-if="bucket.bucket !== 'never'" class="mt-1 flex gap-2 text-xs tabular-nums">
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
                </div>
                <div v-if="bucket.bucket === 'dead'" class="mt-0.5 text-xs opacity-70 tabular-nums">
                    {{ useLocaleStore().currencyFormat(orgPartner.currency, bucket.stock_value) }}
                </div>
            </Link>
        </div>
    </div>

    <div class="mx-4 mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        <ProcurementOverviewCard :card="overviewCard" />
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm text-gray-500">{{ trans("Shopping list value") }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ useLocaleStore().currencyFormat(orgPartner.currency, stats.estimated_total) }}
            </div>
        </div>
    </div>

    <div v-if="openStockDeliveries.length" class="mx-4 mt-6 grid max-w-5xl grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <h3 class="text-sm font-semibold text-gray-700">
                <Link class="hover:underline" :href="route(stockDeliveriesRoute.name, stockDeliveriesRoute.parameters)">
                    {{ trans("Deliveries in transit") }}
                </Link>
            </h3>
            <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                <li v-for="sd in inTransit()" :key="sd.id" class="flex items-center justify-between gap-3 px-4 py-3">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ sd.reference }}</div>
                        <div class="text-xs text-gray-500">{{ sd.items }} {{ trans("items") }} · {{ sd.state.replace("_", " ") }}</div>
                    </div>
                    <div v-if="sd.days_in_transit !== null" class="text-right text-sm font-semibold tabular-nums" :class="sd.days_in_transit > 14 ? 'text-amber-600' : 'text-gray-600'">
                        {{ sd.days_in_transit }} {{ trans("days in transit") }}
                    </div>
                </li>
                <li v-if="!inTransit().length" class="px-4 py-3 text-sm text-gray-400">{{ trans("Nothing on the way") }}</li>
            </ul>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-gray-700">{{ trans("Being prepared by the partner") }}</h3>
            <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                <li v-for="sd in inPreparation()" :key="sd.id" class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="text-sm font-medium text-gray-900">{{ sd.reference }}</div>
                    <div class="text-xs text-gray-500">{{ sd.items }} {{ trans("items") }} · {{ sd.state.replace("_", " ") }}</div>
                </li>
                <li v-if="!inPreparation().length" class="px-4 py-3 text-sm text-gray-400">{{ trans("Nothing in preparation") }}</li>
            </ul>
        </div>
    </div>

    <div v-if="latePurchaseOrders.length" class="mx-4 mt-6 max-w-3xl">
        <h3 class="text-sm font-semibold text-gray-700">{{ trans("Late from this partner") }}</h3>
        <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            <li v-for="purchaseOrder in latePurchaseOrders" :key="purchaseOrder.id" class="flex items-center justify-between gap-3 px-4 py-3">
                <div>
                    <Link
                        class="text-sm font-medium text-indigo-600 hover:underline"
                        :href="route('grp.org.procurement.org_partners.show.purchase-orders.show', [route().params.organisation, orgPartner.id, purchaseOrder.slug])"
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

    <div class="mx-4 mt-6 max-w-3xl">
        <h3 class="text-sm font-semibold text-gray-700">{{ trans("Recently added") }}</h3>
        <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            <li v-for="item in recentItems" :key="item.id" class="flex items-center justify-between gap-3 px-4 py-3">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ item.org_stock_name ?? item.org_stock_code }}</div>
                    <div class="text-xs text-gray-500">{{ item.org_stock_code }} · {{ trans("Qty") }} {{ item.quantity }}</div>
                </div>
                <div class="text-right text-xs text-gray-500">
                    <div>{{ item.added_by_name }}</div>
                    <div>{{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}</div>
                </div>
            </li>
            <li v-if="recentItems.length === 0" class="px-4 py-3 text-sm text-gray-500">
                {{ trans("No open items yet") }}
            </li>
        </ul>
    </div>
</template>
