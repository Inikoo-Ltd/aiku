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
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import PartnerMiniShoppingList from "@/Components/Procurement/PartnerMiniShoppingList.vue"
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
    faChartNetwork
)

const props = defineProps<{
    title: string
    pageHead: object
    dashboardCards: any[]
    search_demand?: any
    shoppingLists?: any
}>()

const shoppingListTotalItems = computed(() =>
    (props.shoppingLists?.withItems ?? []).reduce((sum: number, cart: any) => sum + cart.count, 0)
)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="mx-4 mt-3 flex flex-wrap gap-3">
        <ProcurementOverviewPill v-for="card in dashboardCards" :key="card.label" :card="card" />
    </div>

    <div class="mx-4 mt-4 flex flex-col gap-4">
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
