<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 08 Sept 2022 10:30:19 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Stats from "@/Components/DataDisplay/Stats.vue"
import OrgChart from "@/Components/HumanResources/OrgChart.vue"
import { capitalize } from "@/Composables/capitalize"
defineProps(["title", "pageHead", "stats"])
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>

	<Stats :stats="stats"></Stats>

	<div class="mt-6">
		<OrgChart></OrgChart>
	</div>
</template>
