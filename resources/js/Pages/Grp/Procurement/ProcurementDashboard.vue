<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import StockOutsWidget from "@/Components/Procurement/StockOutsWidget.vue"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle } from "@fal"
import { faChartNetwork } from "@fal"

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle, faChartNetwork)

defineProps(["title", "pageHead", "dashboardCards", "stockLevels", "stockOuts", "stockOutPipeline"])
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<StockOutsWidget v-if="stockOuts" :stockOuts="stockOuts" :stockLevels="stockLevels" :stockOutPipeline="stockOutPipeline" storageKey="procurement-dashboard-stock-outs" class="mx-4 mt-3" />
	<div class="mx-4 mt-3 flex flex-wrap gap-3">
		<ProcurementOverviewPill v-for="card in dashboardCards" :key="card.label" :card="card" />
	</div>
</template>
