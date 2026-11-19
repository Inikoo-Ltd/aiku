<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TablePartnerShipments from "@/Components/Tables/Grp/Org/Dispatching/TablePartnerShipments.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLayerGroup } from "@fal"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
library.add(faLayerGroup)

defineProps<{
	pageHead: PageHeadingTypes
	title: string
	notice: string
	data: object
}>()
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<p class="px-4 pt-2 text-xs text-gray-500">{{ notice }}</p>
	<TablePartnerShipments :data="data" />
</template>
