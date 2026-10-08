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
import StockOutsWidget from "@/Components/Procurement/StockOutsWidget.vue"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle, faHourglassHalf } from "@fal"
import { faChartNetwork } from "@fal"

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle, faHourglassHalf, faChartNetwork)

defineProps(["title", "pageHead", "dashboardCards", "stockLevels", "stockOuts"])
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<StockOutsWidget v-if="stockOuts" :stockOuts="stockOuts" :stockLevels="stockLevels" :cards="dashboardCards" storageKey="procurement-dashboard-stock-outs" class="mx-4 mt-3" />
	<div v-else class="mx-4 mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
		<ProcurementOverviewCard v-for="card in dashboardCards" :key="card.label" :card="card" />
	</div>
</template>
