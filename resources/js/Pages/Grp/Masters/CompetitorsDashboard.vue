<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import EdgeTrafficChart from "@/Components/Web/EdgeTrafficChart.vue"
import TableCompetitors from "@/Components/Tables/Grp/Masters/TableCompetitors.vue"
import TableMasterAssetCompetitorProducts from "@/Components/Tables/Grp/Masters/TableMasterAssetCompetitorProducts.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faBinoculars, faArrowUp, faArrowDown } from "@fal"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faPlus, faBinoculars, faArrowUp, faArrowDown)

type MasterShopRow = { slug: string; name: string; cheaper: number; same: number; dearer: number; median_difference: number | null; products_with_price: number; active_products: number }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    can_edit: boolean
    dashboard: {
        number_competitors: number
        status?: { ok: number; failing: number; not_read: number; last_read: string | null; failures: { id: number; slug: string; name: string; reason: string; last_error: string | null; fetched_at: string | null }[] }
        to_review?: number
        throughput?: { reviewed: { this_week: number; last_week: number }; confirmed: { this_week: number; last_week: number } }
        by_master_shop?: MasterShopRow[]
        trend?: { day: string; confirmed: number }[]
    }
    competitors?: object
    they_cheaper?: object
    we_cheaper?: object
}>()

const locale = useLocaleStore()

const weekChange = (row: { this_week: number; last_week: number }) => row.this_week - row.last_week

const share = (part: number, whole: number) => (whole ? Math.round((100 * part) / whole) : 0)

const trendLabels = computed(() => (props.dashboard.trend ?? []).map((point) => useFormatTime(point.day, { formatTime: "dd MMM" })))
const trendData = computed(() => [{ label: ctrans("Confirmed matches"), data: (props.dashboard.trend ?? []).map((point) => point.confirmed), color: "#6b7280" }])
const trendTotal = computed(() => (props.dashboard.trend ?? []).reduce((carry, point) => carry + point.confirmed, 0))

const positionSegments = (row: MasterShopRow) => {
    const total = row.cheaper + row.same + row.dearer
    return [
        { key: "cheaper", label: ctrans("We are cheaper"), value: row.cheaper, class: "bg-green-500", width: share(row.cheaper, total) },
        { key: "same", label: ctrans("About the same"), value: row.same, class: "bg-gray-300", width: share(row.same, total) },
        { key: "dearer", label: ctrans("We are dearer"), value: row.dearer, class: "bg-red-500", width: share(row.dearer, total) },
    ]
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div v-if="dashboard.number_competitors === 0" class="mx-4 mt-6 max-w-2xl rounded-lg border border-gray-300 bg-white p-6 text-sm text-gray-700">
        <div class="flex items-center gap-2 font-semibold">
            <FontAwesomeIcon icon="fal fa-binoculars" class="text-gray-500" fixed-width />
            {{ ctrans("No competitors yet") }}
        </div>
        <p class="mt-3">{{ ctrans("Add the websites of the competitors whose prices you want to know. For each one you only need a name and its website, and the master shop whose products it competes with.") }}</p>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-gray-600">
            <li>{{ ctrans("A Shopify or WooCommerce shop that shows its prices is read by itself the next morning: every product and pack size, with its price.") }}</li>
            <li>{{ ctrans("Any other website needs a search link, or the product feed it gives its trade customers.") }}</li>
            <li>{{ ctrans("After each read the AI matches their products to ours. It confirms the matches it is sure of and asks you to review the rest; confirmed prices feed the price tips.") }}</li>
        </ul>
        <div v-if="can_edit" class="mt-4">
            <Button type="tertiary" :label="ctrans('Add competitor')" @click="router.visit(route('grp.masters.competitors.create'))">
                <template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width /></template>
            </Button>
        </div>
    </div>

    <div v-else class="space-y-4 px-4 pt-4 text-sm text-gray-700">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section class="rounded-lg border border-gray-300 bg-white p-4">
                <p class="text-xs text-gray-500">{{ ctrans("Competitors") }}</p>
                <p class="mt-0.5 font-semibold">
                    <span class="text-green-600">{{ dashboard.status.ok }}</span> {{ ctrans("read fine") }}
                    <span v-if="dashboard.status.failing" class="ml-2"><span class="text-red-600">{{ dashboard.status.failing }}</span> {{ ctrans("failing") }}</span>
                    <span v-if="dashboard.status.not_read" class="ml-2 text-gray-500">{{ dashboard.status.not_read }} {{ ctrans("not read yet") }}</span>
                </p>
                <p class="text-xs text-gray-500">{{ dashboard.status.last_read ? ctrans("Last read :date", { date: useFormatTime(dashboard.status.last_read) }) : ctrans("Nothing read yet") }}</p>
            </section>
            <Link :href="route('grp.masters.competitors.prices.index')" class="rounded-lg border border-gray-300 bg-white p-4 hover:bg-gray-50">
                <p class="text-xs text-gray-500">{{ ctrans("Matches to review") }}</p>
                <p class="mt-0.5 font-semibold" :class="dashboard.to_review ? 'text-amber-600' : ''">{{ locale.number(dashboard.to_review) }}</p>
                <p class="text-xs text-gray-500">{{ ctrans("The AI was not sure: confirm or reject each one") }}</p>
            </Link>
            <section v-for="(row, key) in dashboard.throughput" :key="key" class="rounded-lg border border-gray-300 bg-white p-4">
                <p class="text-xs text-gray-500">{{ key === 'reviewed' ? ctrans("Reviewed by staff this week") : ctrans("Confirmed this week") }}</p>
                <p class="mt-0.5 font-semibold tabular-nums">
                    {{ locale.number(row.this_week) }}
                    <span class="ml-2 text-xs font-normal" :class="weekChange(row) > 0 ? 'text-green-600' : weekChange(row) < 0 ? 'text-red-600' : 'text-gray-500'">
                        <FontAwesomeIcon v-if="weekChange(row) > 0" icon="fal fa-arrow-up" fixed-width />
                        <FontAwesomeIcon v-else-if="weekChange(row) < 0" icon="fal fa-arrow-down" fixed-width />
                        {{ ctrans(":n last week", { n: row.last_week }) }}
                    </span>
                </p>
                <p class="text-xs text-gray-500">{{ key === 'reviewed' ? ctrans("Matches confirmed or rejected by hand") : ctrans("By staff or by the AI when it was sure") }}</p>
            </section>
        </div>

        <section v-if="dashboard.status.failures?.length" class="rounded-lg border border-red-200 bg-white p-4">
            <p class="font-medium">{{ ctrans("Competitors that could not be read") }}</p>
            <div class="mt-2 grid gap-x-6 gap-y-2 text-xs md:grid-cols-2">
                <div v-for="failure in dashboard.status.failures" :key="failure.id">
                    <Link :href="route('grp.masters.competitors.show', { competitor: failure.slug })" class="font-medium text-gray-700 hover:underline">{{ failure.name }}</Link>
                    <span class="text-red-600"> · {{ failure.reason }}</span>
                    <div v-if="failure.last_error" class="text-gray-500">{{ failure.last_error }}</div>
                </div>
            </div>
        </section>

        <div class="grid gap-4">
            <section>
                <p class="font-medium">{{ ctrans("Where they are cheapest") }}</p>
                <p class="text-xs text-gray-500">{{ ctrans("Confirmed matches with the biggest gap against us") }}</p>
                <TableMasterAssetCompetitorProducts :data="they_cheaper" tab="they_cheaper" :canEdit="can_edit" />
            </section>
            <section>
                <p class="font-medium">{{ ctrans("Where we are cheapest") }}</p>
                <p class="text-xs text-gray-500">{{ ctrans("Confirmed matches with the biggest gap in our favour") }}</p>
                <TableMasterAssetCompetitorProducts :data="we_cheaper" tab="we_cheaper" :canEdit="can_edit" />
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section v-for="row in dashboard.by_master_shop" :key="row.slug" class="rounded-lg border border-gray-300 bg-white p-4">
                <div class="flex items-baseline justify-between">
                    <Link :href="route('grp.masters.master_shops.show', { masterShop: row.slug })" class="font-medium text-gray-700 hover:underline">{{ row.name }}</Link>
                    <span v-if="row.median_difference !== null" class="text-xs text-gray-500">
                        {{ ctrans("Median difference") }}
                        <span class="font-semibold tabular-nums" :class="row.median_difference < 0 ? 'text-red-600' : 'text-green-600'">{{ row.median_difference > 0 ? "+" : "" }}{{ row.median_difference }}%</span>
                    </span>
                </div>
                <template v-if="row.cheaper + row.same + row.dearer">
                    <div class="mt-2 flex h-2 w-full gap-0.5 overflow-hidden rounded">
                        <div v-for="segment in positionSegments(row)" :key="segment.key" :class="segment.class" :style="{ width: segment.width + '%' }" v-tooltip="segment.label + ': ' + segment.value" />
                    </div>
                    <div class="mt-1 flex flex-wrap gap-x-4 text-xs text-gray-500">
                        <span v-for="segment in positionSegments(row)" :key="segment.key" class="flex items-center gap-1">
                            <span class="inline-block h-2 w-2 rounded-sm" :class="segment.class" />
                            {{ segment.label }} <span class="font-medium tabular-nums text-gray-700">{{ segment.value }}</span>
                        </span>
                    </div>
                </template>
                <p v-else class="mt-2 text-xs text-gray-500">{{ ctrans("No confirmed prices yet") }}</p>
                <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                    <span>{{ ctrans("Products with a competitor price") }}</span>
                    <span class="tabular-nums"><span class="font-medium text-gray-700">{{ share(row.products_with_price, row.active_products) }}%</span> ({{ row.products_with_price }} / {{ row.active_products }})</span>
                </div>
                <div class="mt-1 h-1.5 w-full rounded bg-gray-100">
                    <div class="h-1.5 rounded bg-gray-500" :style="{ width: share(row.products_with_price, row.active_products) + '%' }" />
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-gray-300 bg-white p-4">
            <div class="flex items-baseline justify-between">
                <p class="font-medium">{{ ctrans("Confirmed matches per day") }}</p>
                <p class="text-xs text-gray-500">{{ ctrans(":n in the last 30 days", { n: locale.number(trendTotal) }) }}</p>
            </div>
            <EdgeTrafficChart v-if="trendTotal" class="mt-2" type="line" :labels="trendLabels" :datasets="trendData" height="h-40" points />
            <p v-else class="mt-2 text-xs text-gray-500">{{ ctrans("No matches confirmed in the last 30 days") }}</p>
        </section>

        <section>
            <p class="font-medium">{{ ctrans("Competitors") }}</p>
            <p class="text-xs text-gray-500">{{ ctrans("Ranked by confirmed matches") }}</p>
            <TableCompetitors :data="competitors" tab="competitors" />
        </section>
    </div>
</template>
