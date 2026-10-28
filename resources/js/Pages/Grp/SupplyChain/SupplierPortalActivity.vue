<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableSupplierPortalActivity from "@/Components/Tables/Grp/SupplyChain/TableSupplierPortalActivity.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSignInAlt } from "@fal"

library.add(faSignInAlt)

defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: InstanceType<typeof TableSupplierPortalActivity>["$props"]["data"]
}>()
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<TableSupplierPortalActivity :data="data" />
</template>
