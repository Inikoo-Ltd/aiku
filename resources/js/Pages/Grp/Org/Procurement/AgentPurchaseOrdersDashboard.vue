<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentDashboardPurchaseOrders, { type AgentDashboardPurchaseOrdersData } from "@/Components/Procurement/AgentDashboardPurchaseOrders.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: AgentDashboardPurchaseOrdersData
}>()
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<AgentDashboardPurchaseOrders class="mx-4 mt-4" :data="data" />
</template>
