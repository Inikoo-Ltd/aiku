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

type PriorityBreakdown = { priority: string, label: string, count: number }
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
}>()

const overviewCard = {
    label: trans("Open items"),
    description: trans("Open shopping list items"),
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

    <div class="mx-4 mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        <ProcurementOverviewCard :card="overviewCard" />
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm text-gray-500">{{ trans("Estimated open value") }}</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900">
                {{ useLocaleStore().currencyFormat(orgPartner.currency, stats.estimated_total) }}
            </div>
        </div>
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
