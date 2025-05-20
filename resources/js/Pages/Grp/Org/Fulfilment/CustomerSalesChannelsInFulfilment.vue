<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableFulfilmentCustomerSalesChannels from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentCustomerSalesChannels.vue"
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { Table } from "@/types/Table";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";


defineProps<{
    data: Table
    title: string
    pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: {}
	}
}>()


</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
	</PageHeading>
	<TableFulfilmentCustomerSalesChannels :data="data" />
</template>
