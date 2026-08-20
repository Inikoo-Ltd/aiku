<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableAgentSuppliers from "@/Components/Tables/Grp/SupplyChain/TableAgentSuppliers.vue"
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
		<TableAgentSuppliers :data="data" />
	</div>
</template>
