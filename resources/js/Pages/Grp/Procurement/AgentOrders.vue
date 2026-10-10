<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableAgentOrders from "@/Components/Tables/Grp/Org/Procurement/TableAgentOrders.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxes, faPeopleArrows } from "@fal"

library.add(faBoxes, faPeopleArrows)

defineProps<{
	pageHead: object
	title: string
	data: object
}>()
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<TableAgentOrders :data="data" />
</template>
