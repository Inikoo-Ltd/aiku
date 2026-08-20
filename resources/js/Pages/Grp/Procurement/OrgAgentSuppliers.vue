<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrgAgentSuppliers from "@/Components/Tables/Grp/Org/Procurement/TableOrgAgentSuppliers.vue"
import { capitalize } from "@/Composables/capitalize"

defineProps<{
	pageHead: object
	title: string
	data: object
}>()
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />
		<TableOrgAgentSuppliers :data="data" />
	</div>
</template>
