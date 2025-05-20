<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Table } from '@/types/Table'
import RetinaTableCustomerSalesChannels from "@/Components/Tables/Retina/RetinaTableCustomerSalesChannels.vue"


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
	<RetinaTableCustomerSalesChannels :data="data" />
</template>
