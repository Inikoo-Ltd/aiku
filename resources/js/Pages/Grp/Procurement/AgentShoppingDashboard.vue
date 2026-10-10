<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 31 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle } from "@fal"
import ModalAutoFillAgentShoppingList from "@/Components/Procurement/ModalAutoFillAgentShoppingList.vue"

library.add(faExclamationTriangle)

type LeadTime = { days: number, source: "measured" | "estimate" | "default", samples: number }
type RankBreakdown = { rank: string, count: number, on_list: number }
type CoverBucket = { bucket: string, label: string, tone: keyof typeof toneClasses, count: number, on_list: number, on_the_way: number, untouched: number, suppliers: number, stock_value: number, ranks: RankBreakdown[] }
type OrderCapacity = {
    agent_capacity: { lands_for_us_per_30d: number | null, source: "measured" | "sales" | "none", samples: number }
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

const bucketItemsRoute = (bucket: string, rank?: string) =>
    `${route("grp.org.procurement.org_agents.show.shopping.items.index", [route().params.organisation, props.orgAgent.slug])}?cover=${bucket}${rank ? `&rank=${rank}` : ""}`

const shouldNotBeOrdered = (bucket: CoverBucket) => ["ok", "dead", "gone"].includes(bucket.bucket) && bucket.on_list > 0

const needsAction = (bucket: CoverBucket) => !notOrderable.includes(bucket.bucket) && bucket.untouched > 0

const segmentWidth = (bucket: CoverBucket, part: number) => (bucket.count ? `${Math.max((part / bucket.count) * 100, part ? 1.5 : 0)}%` : "0%")

const rankClasses: Record<string, string> = { A: "", B: "", C: "", D: "opacity-50", Z: "opacity-50" }

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between text-sm text-gray-500">
                <span>{{ ctrans("Order budget used") }}</span>
                <span v-if="orderCapacity.blocked.at_capacity" class="font-medium text-red-600">{{ ctrans("at capacity") }}</span>
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
                        {{ ctrans("one order cycle of what they historically land for us, measured from :n deliveries", { n: orderCapacity.agent_capacity.samples }) }}
                    </template>
                    <template v-else>
                        {{ ctrans("budget = one order cycle (:days days) of what we actually sell of their products", { days: leadTime.days + 7 }) }}
                    </template>
                </div>
            </template>
            <div v-else class="mt-2 text-xs text-gray-500">
                {{ ctrans("No budget: no delivery history and no forecast data yet — the list is uncapped.") }}
            </div>
            <div class="mt-1 text-xs text-gray-400">
                {{ ctrans("all sub-supplier currencies converted to :currency", { currency: orgAgent.currency }) }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-baseline justify-between text-sm text-gray-500">
                <span>{{ ctrans("Warehouse space") }}</span>
                <span v-if="orderCapacity.blocked.warehouse_full" class="font-medium text-red-600">{{ ctrans("full") }}</span>
            </div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ orderCapacity.warehouse.empty_locations.toLocaleString() }}
                <span class="text-sm font-normal text-gray-400">/ {{ orderCapacity.warehouse.total_locations.toLocaleString() }} {{ ctrans("locations free") }}</span>
            </div>
            <div class="mt-2 flex h-1.5 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                <div class="h-1.5 bg-gray-400" :style="{ width: warehouseSegment(orderCapacity.warehouse.total_locations - orderCapacity.warehouse.empty_locations) }" />
                <div class="h-1.5 bg-indigo-400" :style="{ width: warehouseSegment(orderCapacity.warehouse.inbound_open_po_lines) }" />
                <div class="h-1.5 bg-violet-400" :style="{ width: warehouseSegment(orderCapacity.list.lines) }" />
            </div>
            <div class="mt-1 flex flex-wrap gap-x-3 text-xs tabular-nums text-gray-500">
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-gray-400" />{{ (orderCapacity.warehouse.total_locations - orderCapacity.warehouse.empty_locations).toLocaleString() }} {{ ctrans("in use") }}</span>
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-indigo-400" />{{ orderCapacity.warehouse.inbound_open_po_lines.toLocaleString() }} {{ ctrans("inbound PO/SD lines") }}</span>
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-violet-400" />{{ orderCapacity.list.lines }} {{ ctrans("this shopping list") }}</span>
            </div>
            <div class="mt-1 text-xs text-gray-500">
                {{ ctrans("new products from this agent: :used of :limit free slots (their fair share)", { used: orderCapacity.warehouse.agent_share_used, limit: orderCapacity.warehouse.agent_share_limit }) }}
            </div>
        </div>

    </div>

    <div class="mx-4 mt-4 rounded-xl border-2 border-[--app-accent-muted] bg-[--app-accent-soft] p-4">
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
                    <span v-if="shouldNotBeOrdered(bucket)" class="ml-auto text-xs font-medium tabular-nums text-red-600" :title="ctrans('On the shopping list but not short of stock')">
                        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                        {{ bucket.on_list }} {{ ctrans("on list") }}
                        <button
                            v-if="bucket.bucket !== 'gone'"
                            type="button"
                            class="ml-0.5 rounded border border-red-300 px-1 text-[10px] hover:bg-red-100"
                            @click.prevent.stop="removeMisplaced(bucket.bucket)"
                        >
                            {{ ctrans("remove") }}
                        </button>
                    </span>
                    <span v-else-if="needsAction(bucket)" class="ml-auto text-xs font-medium tabular-nums">
                        {{ ctrans(":count need action", { count: bucket.untouched.toLocaleString() }) }}
                    </span>
                    <span v-else-if="bucket.count && !notOrderable.includes(bucket.bucket)" class="ml-auto text-xs tabular-nums opacity-70">
                        {{ ctrans("all handled") }}
                    </span>
                </div>
                <div class="text-xs leading-4">{{ bucket.label }}</div>
                <div v-if="bucket.suppliers" class="text-[10px] tabular-nums opacity-60">
                    {{ ctrans("across :n suppliers", { n: bucket.suppliers }) }}
                </div>
                <div v-if="(bucket.on_the_way || bucket.on_list) && !notOrderable.includes(bucket.bucket)" class="mt-1.5 flex h-1 w-full gap-px overflow-hidden rounded-full bg-gray-100">
                    <div v-if="bucket.on_the_way" class="h-1 bg-current" :style="{ width: segmentWidth(bucket, bucket.on_the_way) }" :title="ctrans('on the way')" />
                    <div v-if="bucket.on_list" class="h-1 bg-current opacity-40" :style="{ width: segmentWidth(bucket, bucket.on_list) }" :title="ctrans('on the shopping list')" />
                </div>
                <div v-if="(bucket.on_the_way || bucket.on_list) && !notOrderable.includes(bucket.bucket)" class="mt-0.5 text-[10px] tabular-nums opacity-70">
                    <span v-if="bucket.on_the_way">{{ bucket.on_the_way }} {{ ctrans("on the way") }}</span>
                    <span v-if="bucket.on_the_way && bucket.on_list"> · </span>
                    <span v-if="bucket.on_list">{{ bucket.on_list }} {{ ctrans("on list") }}</span>
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
                        :title="ctrans('Fill the shopping list from this bucket')"
                        @click.prevent.stop="openAutoFill(bucket.bucket, bucket.label)"
                    >
                        + {{ ctrans("fill") }}
                    </button>
                </div>
            </Link>
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
