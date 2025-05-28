<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Table } from '@/types/Table'
import RetinaTableFulfilmentCustomerSalesChannels from "@/Components/Tables/Retina/RetinaTableFulfilmentCustomerSalesChannels.vue"
import {library} from "@fortawesome/fontawesome-svg-core";
import {faUnlink} from "@fal";

defineProps<{
	data: Table
	title: string
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: {}
	}

}>()

library.add(faUnlink)

</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
	</PageHeading>
	<RetinaTableFulfilmentCustomerSalesChannels :data="data" />
</template>
