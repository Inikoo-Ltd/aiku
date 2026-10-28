<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentSalesValuation, { Valuation } from "@/Components/Procurement/AgentSalesValuation.vue"
import SalesDashboard from "@/Components/Procurement/SalesDashboard.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
	title: string
	pageHead: PageHeadingTypes
	supplier?: { code: string; name: string } | null
	seller?: "agent" | "supplier"
	staff?: boolean
	links: Record<"all" | "out" | "low" | "dead", string> | null
	valuation: Valuation
	sales?: InstanceType<typeof SalesDashboard>["$props"]["sales"]
	feedback?: InstanceType<typeof SalesDashboard>["$props"]["feedback"]
}>()
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<div class="space-y-4 p-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<AgentSalesValuation :valuation="valuation" />
		</div>
		<SalesDashboard :supplier="supplier" :seller="seller" :staff="staff" :links="links" :valuation="valuation" :sales="sales" :feedback="feedback" />
	</div>
</template>
