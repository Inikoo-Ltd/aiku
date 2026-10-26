<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableSupplierSubmissions from "@/Components/Tables/Grp/SupplyChain/TableSupplierSubmissions.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInboxIn } from "@fal"

library.add(faInboxIn)

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
		<TableSupplierSubmissions :data="data" />
	</div>
</template>
