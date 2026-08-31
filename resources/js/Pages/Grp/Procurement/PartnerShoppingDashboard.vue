<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 29 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref } from "vue"
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
type CoverBucket = { bucket: string, label: string, tone: keyof typeof toneClasses, count: number, on_list: number, on_the_way: number, stock_value: number }
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

const editingLeadTime = ref(false)
const leadTimeDraft = ref(props.leadTime.days)

const saveLeadTime = () => {
    editingLeadTime.value = false
    if (leadTimeDraft.value !== props.leadTime.days) {
        router.patch(
            route(props.leadTimeRoute.name, props.leadTimeRoute.parameters),
            { lead_time_days: leadTimeDraft.value },
            { only: ["coverBuckets", "coverTotal", "leadTime"], preserveScroll: true }
        )
    }
}

const shouldNotBeOrdered = (bucket: CoverBucket) => ["ok", "dead"].includes(bucket.bucket) && bucket.on_list > 0

const handledShare = (bucket: CoverBucket) => (bucket.count ? bucket.on_list / bucket.count : 0)

const handledPercent = (bucket: CoverBucket) => {
    const share = handledShare(bucket) * 100

    if (share > 0 && share < 1) {
        return "<1%"
    }

    return `${Math.round(share)}%`
}

const handledWidth = (bucket: CoverBucket) => `${Math.max(handledShare(bucket) * 100, bucket.on_list ? 2 : 0)}%`

const bucketRoute = (bucket: string) => `${route(props.browseRoute.name, props.browseRoute.parameters)}?cover=${bucket}`

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
                <template v-else-if="editingLeadTime">
                    <input
                        v-model.number="leadTimeDraft"
                        type="number"
                        min="1"
                        max="365"
                        class="w-16 rounded border-gray-300 px-1 py-0 text-xs"
                        autofocus
                        @keyup.enter="saveLeadTime"
                        @blur="saveLeadTime"
                    />
                    {{ trans("days") }}
                </template>
                <template v-else>
                    {{ trans(":days-day lead time", { days: leadTime.days }) }}
                    <button type="button" class="italic text-indigo-500 hover:underline" @click="editingLeadTime = true">
                        {{ trans("estimate, no delivery history yet — edit") }}
                    </button>
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
                    <span
                        v-if="bucket.count"
                        class="text-xs tabular-nums"
                        :class="shouldNotBeOrdered(bucket) ? 'font-medium text-red-600' : 'opacity-70'"
                        :title="shouldNotBeOrdered(bucket) ? trans('On the shopping list but not short of stock') : undefined"
                    >
                        <FontAwesomeIcon v-if="shouldNotBeOrdered(bucket)" :icon="faExclamationTriangle" aria-hidden="true" />
                        {{ bucket.on_list }}/{{ bucket.count }} ({{ handledPercent(bucket) }})
                    </span>
                </div>
                <div class="text-xs leading-4">{{ bucket.label }}</div>
                <div v-if="bucket.count" class="mt-1.5 h-1 w-full rounded-full bg-white/70">
                    <div class="h-1 rounded-full opacity-70" :class="shouldNotBeOrdered(bucket) ? 'bg-red-500' : 'bg-current'" :style="{ width: handledWidth(bucket) }" />
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
