<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrgAgentSuppliers from "@/Components/Tables/Grp/Org/Procurement/TableOrgAgentSuppliers.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPeopleArrows } from "@fal"
library.add(faPeopleArrows)

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
