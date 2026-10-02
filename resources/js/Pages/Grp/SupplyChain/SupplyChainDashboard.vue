<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head, Link } from "@inertiajs/vue3"
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import PartnerMiniShoppingList from "@/Components/Procurement/PartnerMiniShoppingList.vue"
import StockOutsWidget from "@/Components/Procurement/StockOutsWidget.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import SearchDemandOpportunities from "@/Components/DataDisplay/Dashboard/Widget/SearchDemandOpportunities.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCartPlus,
    faChevronDown,
    faChevronRight,
    faPeopleArrows,
    faBoxUsd,
    faPersonDolly,
    faClipboardList,
    faArrowRight,
    faRadar,
    faShoppingBasket,
    faChartNetwork,
    faRoute,
    faExclamationTriangle,
} from "@fal"

library.add(
    faCartPlus,
    faChevronDown,
    faChevronRight,
    faPeopleArrows,
    faBoxUsd,
    faPersonDolly,
    faClipboardList,
    faArrowRight,
    faRadar,
    faShoppingBasket,
    faChartNetwork,
    faRoute,
    faExclamationTriangle
)

const props = defineProps<{
    title: string
    pageHead: object
    dashboardCards: any[]
    search_demand?: any
    shoppingLists?: any
    stockOuts?: any
    stockLevelsByOrganisation?: any[]
    poJourney?: {
        currency: string
        summary: {
            open: number
            open_value: number
            on_track: number
            at_risk: number
            overdue: number
            completed: number
        }
        blockages: { stage: string; label: string; count: number; max_days_overdue: number }[]
        route: { name: string; parameters: Record<string, any> }
    }
}>()

const poJourneyTiles = computed(() => {
    const journey = props.poJourney
    if (!journey) {
        return []
    }

    return [
        { label: ctrans("Open orders"), value: journey.summary.open.toLocaleString(), class: "text-gray-900", status: null },
        {
            label: ctrans("Open value"),
            value: new Intl.NumberFormat("en-GB", { style: "currency", currency: journey.currency, notation: "compact", maximumFractionDigits: 2 }).format(journey.summary.open_value),
            class: "text-gray-900",
            status: null
        },
        { label: ctrans("On track"), value: journey.summary.on_track, class: "text-emerald-600", status: "on_track" },
        { label: ctrans("At risk"), value: journey.summary.at_risk, class: "text-amber-500", status: "at_risk" },
        { label: ctrans("Overdue"), value: journey.summary.overdue, class: "text-red-600", status: "overdue" },
        { label: ctrans("Completed"), value: journey.summary.completed, class: "text-blue-600", status: "completed" }
    ]
})

function poJourneyHref(query: Record<string, string | null> = {}): string {
    const journeyRoute = props.poJourney!.route
    const filters = Object.fromEntries(Object.entries(query).filter(([, value]) => value))

    return route(journeyRoute.name, { ...journeyRoute.parameters, ...filters })
}

const shoppingListTotalItems = computed(() =>
    (props.shoppingLists?.withItems ?? []).reduce((sum: number, cart: any) => sum + cart.count, 0)
)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Deferred :data="['stockOuts', 'stockLevelsByOrganisation']">
        <template #fallback>
            <div class="mx-4 mt-3 h-80 animate-pulse rounded-xl border border-gray-200 bg-gray-100" />
        </template>

        <StockOutsWidget v-if="stockOuts" :stockOuts="stockOuts" :organisationStockLevels="stockLevelsByOrganisation ?? []" :cards="dashboardCards" storageKey="supply-chain-overview-stock-outs" class="mx-4 mt-3" />
    </Deferred>

    <div class="mx-4 mt-4 flex flex-col gap-4">
        <Deferred data="poJourney">
            <template #fallback>
                <div class="h-24 animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            </template>

            <DashboardWidgetBox v-if="poJourney" storageKey="sc_po_journey_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-route" class="text-indigo-600" fixed-width aria-hidden="true" />
                        {{ ctrans("PO journey") }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ poJourney.summary.open }} {{ ctrans("open orders") }}
                        · {{ poJourney.summary.overdue }} {{ ctrans("overdue") }}
                    </span>
                </template>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    <Link
                        v-for="tile in poJourneyTiles"
                        :key="tile.label"
                        :href="poJourneyHref({ status: tile.status })"
                        class="rounded-md border border-gray-200 bg-white px-3 py-2 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">
                        <span class="block text-xs text-gray-500">{{ tile.label }}</span>
                        <span class="block text-lg font-semibold tabular-nums" :class="tile.class">{{ tile.value }}</span>
                    </Link>
                </div>

                <div v-if="poJourney.blockages.length" class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="flex items-center gap-1.5 text-xs text-gray-400">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-500" fixed-width aria-hidden="true" />
                        {{ ctrans("Urgent blockages:") }}
                    </span>
                    <Link
                        v-for="blockage in poJourney.blockages.slice(0, 3)"
                        :key="blockage.stage"
                        :href="poJourneyHref({ stage: blockage.stage, status: 'overdue' })"
                        class="inline-flex items-center gap-1.5 rounded-full border border-red-100 bg-red-50 px-2.5 py-1 text-xs text-red-700 hover:bg-red-100">
                        <span class="font-semibold">{{ blockage.count }}</span>
                        {{ blockage.label }}
                    </Link>
                </div>

                <div class="mt-3 flex justify-end">
                    <Link :href="poJourneyHref()" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        {{ ctrans("See full report") }}
                        <FontAwesomeIcon icon="fal fa-arrow-right" fixed-width aria-hidden="true" />
                    </Link>
                </div>
            </DashboardWidgetBox>
        </Deferred>

        <Deferred data="shoppingLists">
            <template #fallback>
                <div class="h-16 animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            </template>

            <DashboardWidgetBox v-if="shoppingLists?.withItems?.length || shoppingLists?.empty?.length" storageKey="sc_shopping_lists_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-shopping-basket" class="text-emerald-600" fixed-width aria-hidden="true" />
                        {{ ctrans("Shopping lists") }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ shoppingLists.withItems.length }} {{ ctrans("with items") }}
                        · {{ shoppingListTotalItems }} {{ ctrans("items") }}
                        · {{ shoppingLists.empty.length }} {{ ctrans("empty") }}
                    </span>
                </template>

                <div v-if="shoppingLists.empty.length" class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-400">{{ ctrans("Empty lists:") }}</span>
                    <Link
                        v-for="list in shoppingLists.empty"
                        :key="list.name"
                        :href="route(list.route.name, list.route.parameters)"
                        class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                        <FontAwesomeIcon icon="fal fa-shopping-basket" fixed-width aria-hidden="true" />
                        {{ list.name }}
                    </Link>
                </div>

                <div v-if="shoppingLists.withItems.length" class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <PartnerMiniShoppingList
                        v-for="miniCart in shoppingLists.withItems"
                        :key="miniCart.partner_name"
                        :miniCart="miniCart" />
                </div>
            </DashboardWidgetBox>
        </Deferred>

        <Deferred data="search_demand">
            <template #fallback>
                <div class="h-16 max-w-3xl animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            </template>

            <DashboardWidgetBox v-if="search_demand" class="max-w-3xl" storageKey="sc_search_demand_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-cart-plus" class="text-green-600" fixed-width aria-hidden="true" />
                        {{ ctrans("Customers asked for, we do not sell") }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ search_demand.opportunities?.length ?? 0 }} {{ ctrans("terms") }}
                        · {{ ctrans("last :days days", { days: String(search_demand.days) }) }}
                    </span>
                </template>
                <SearchDemandOpportunities :demand="search_demand" bare />
            </DashboardWidgetBox>
        </Deferred>
    </div>
</template>
