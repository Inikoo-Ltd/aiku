<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProcurementOverviewCard from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewCard.vue"
import SearchDemandOpportunities from "@/Components/DataDisplay/Dashboard/Widget/SearchDemandOpportunities.vue"

import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faPeopleArrows,
    faBoxUsd,
    faPersonDolly,
    faClipboardList,
    faArrowRight,
    faRadar,
    faShoppingBasket,
} from "@fal"

library.add(
    faPeopleArrows,
    faBoxUsd,
    faPersonDolly,
    faClipboardList,
    faArrowRight,
    faRadar,
    faShoppingBasket
)

defineProps<{
    title: string
    pageHead: object
    dashboardCards: any[]
    search_demand?: any
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="mx-4 mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        <ProcurementOverviewCard v-for="card in dashboardCards" :key="card.label" :card="card" />
    </div>
    <div class="mx-4 mt-6 max-w-3xl">
        <Deferred data="search_demand">
            <template #fallback>
                <div class="h-48 animate-pulse rounded-lg border border-gray-200 bg-gray-100" />
            </template>
            <SearchDemandOpportunities :demand="search_demand" />
        </Deferred>
    </div>
</template>
