<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Deferred, Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProcurementOverviewCard from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewCard.vue"
import StockOutsWidget from "@/Components/Procurement/StockOutsWidget.vue"
import ProcurementCounterpartyCards from "@/Components/Procurement/ProcurementCounterpartyCards.vue"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle, faHourglassHalf } from "@fal"
import { faChartNetwork } from "@fal"

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle, faHourglassHalf, faChartNetwork)

defineProps(["title", "pageHead", "dashboardCards", "stockLevels", "stockOuts", "counterparties"])
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<StockOutsWidget v-if="stockOuts" :stockOuts="stockOuts" :stockLevels="stockLevels" :cards="dashboardCards" storageKey="procurement-dashboard-stock-outs" class="mx-4 mt-3" />
	<div v-else class="mx-4 mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
		<ProcurementOverviewCard v-for="card in dashboardCards" :key="card.label" :card="card" />
	</div>
	<div v-if="counterparties !== null" class="mx-4 my-4">
		<Deferred data="counterparties">
			<template #fallback>
				<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
					<div v-for="n in 4" :key="n" class="h-[106px] animate-pulse rounded-lg bg-gray-100" />
				</div>
			</template>
			<ProcurementCounterpartyCards :counterparties="counterparties" />
		</Deferred>
	</div>
</template>
