<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { computed } from "vue"
import { Deferred, Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProcurementOverviewCard from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewCard.vue"
import StockOutsWidget from "@/Components/Procurement/StockOutsWidget.vue"
import ProcurementCounterpartyCards from "@/Components/Procurement/ProcurementCounterpartyCards.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle, faHourglassHalf, faComments } from "@fal"
import { faChartNetwork } from "@fal"

library.add(faPeopleArrows, faBoxUsd, faPersonDolly, faTruckContainer, faClipboardList, faArrowRight, faExclamationTriangle, faHourglassHalf, faComments, faChartNetwork)

const props = defineProps(["title", "pageHead", "dashboardCards", "stockLevels", "stockOuts", "counterparties"])

const needsAttention = (card) => card.attention && Number(card.value) > 0

const costingAlerts = computed(() => (props.dashboardCards ?? []).filter(needsAttention).flatMap(card => card.metrics?.length
	? card.metrics.filter(metric => Number(metric.value) > 0).map(metric => ({ label: metric.label, value: metric.value, route: metric.route, isMain: false }))
	: [{ label: card.label, value: card.value, route: card.route, isMain: true }]))
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<div v-if="stockOuts && costingAlerts.length" class="mx-4 mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm text-amber-900">
		<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-600" fixed-width aria-hidden="true" />
		<component
			:is="alert.route ? Link : 'span'"
			v-for="alert in costingAlerts"
			:key="alert.label"
			:href="alert.route ? route(alert.route.name, alert.route.parameters) : undefined"
			class="flex items-center gap-1.5"
			:class="[alert.isMain ? 'font-semibold' : 'text-amber-800', alert.route ? 'hover:underline' : '']"
		>
			{{ alert.label }}
			<span class="rounded bg-white/70 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-amber-900">{{ alert.value }}</span>
		</component>
	</div>
	<StockOutsWidget v-if="stockOuts" :stockOuts="stockOuts" :stockLevels="stockLevels" :cards="dashboardCards.filter(card => !needsAttention(card))" storageKey="procurement-dashboard-stock-outs" class="mx-4 mt-3" />
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
