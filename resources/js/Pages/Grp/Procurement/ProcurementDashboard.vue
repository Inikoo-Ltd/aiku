<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProcurementOverviewCard from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewCard.vue"

defineProps(["title", "pageHead", "dashboardCards", "search_demand"])

import SearchDemandOpportunities from "@/Components/DataDisplay/Dashboard/Widget/SearchDemandOpportunities.vue"

import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faPeopleArrows,
	faBoxUsd,
	faPersonDolly,
	faTruckContainer,
	faClipboardList,
	faArrowRight,
} from "@fal"

library.add(
	faPeopleArrows,
	faBoxUsd,
	faPersonDolly,
	faTruckContainer,
	faClipboardList,
	faArrowRight
)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<div class="mx-4 mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
		<ProcurementOverviewCard v-for="card in dashboardCards" :key="card.label" :card="card" />
	</div>
	<div class="mx-4 mt-6 max-w-3xl">
		<SearchDemandOpportunities :demand="search_demand" />
	</div>
</template>
