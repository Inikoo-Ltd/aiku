<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup>
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import OrgChart from "@/Components/HumanResources/OrgChart.vue"
import { capitalize } from "@/Composables/capitalize"

defineProps(["title", "pageHead", "orgChartNodes"])
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>

	<div class="mt-6">
		<OrgChart :nodes="orgChartNodes"></OrgChart>
	</div>
</template>
