<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 25 Oct 2022 12:21:09 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Head, Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import PartnerMiniShoppingList from "@/Components/Procurement/PartnerMiniShoppingList.vue"
import { trans } from "laravel-vue-i18n"

defineProps(["title", "pageHead", "dashboardCards", "search_demand", "shoppingLists"])

import SearchDemandOpportunities from "@/Components/DataDisplay/Dashboard/Widget/SearchDemandOpportunities.vue"

import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faPeopleArrows,
	faBoxUsd,
	faPersonDolly,
	faTruckContainer,
	faClipboardList,
	faArrowRight,
	faShoppingBasket,
} from "@fal"

library.add(
	faPeopleArrows,
	faBoxUsd,
	faPersonDolly,
	faTruckContainer,
	faClipboardList,
	faArrowRight,
	faShoppingBasket
)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<div class="mx-4 mt-3 flex flex-wrap gap-3">
		<ProcurementOverviewPill v-for="card in dashboardCards" :key="card.label" :card="card" />
	</div>

	<div v-if="shoppingLists?.withItems?.length || shoppingLists?.empty?.length" class="mx-4 mt-6">
		<h2 class="text-sm font-semibold text-gray-600">{{ trans("Shopping lists") }}</h2>

		<div v-if="shoppingLists.withItems.length" class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
			<PartnerMiniShoppingList
				v-for="miniCart in shoppingLists.withItems"
				:key="miniCart.partner_name + miniCart.listRoute.name"
				:miniCart="miniCart" />
		</div>

		<div v-if="shoppingLists.empty.length" class="mt-4 flex flex-wrap items-center gap-2">
			<span class="text-xs text-gray-400">{{ trans("Empty lists:") }}</span>
			<Link
				v-for="list in shoppingLists.empty"
				:key="list.name + list.route.name"
				:href="route(list.route.name, list.route.parameters)"
				class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700">
				<FontAwesomeIcon icon="fal fa-shopping-basket" fixed-width aria-hidden="true" />
				{{ list.name }}
			</Link>
		</div>
	</div>

	<div class="mx-4 mt-6 max-w-3xl">
		<SearchDemandOpportunities :demand="search_demand" />
	</div>
</template>
