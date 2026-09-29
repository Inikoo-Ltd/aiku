<script setup lang="ts">
import SalesAnalysisReport from "@/Components/SalesAnalysis/SalesAnalysisReport.vue"

defineProps<{
	data?: InstanceType<typeof SalesAnalysisReport>["$props"]["data"]
	breakdownRoute?: InstanceType<typeof SalesAnalysisReport>["$props"]["breakdownRoute"]
}>()
</script>

<template>
	<SalesAnalysisReport v-if="data" :data="data" :breakdownRoute="breakdownRoute" />
	<div v-else class="space-y-4 px-4 py-4" aria-busy="true">
		<div class="flex gap-2">
			<div v-for="index in 3" :key="index" class="h-6 w-24 animate-pulse rounded-full bg-gray-100" />
			<div class="ml-auto h-6 w-72 animate-pulse rounded bg-gray-100" />
		</div>
		<div class="flex flex-wrap gap-2">
			<div v-for="index in 6" :key="index" class="h-9 w-44 animate-pulse rounded-lg bg-gray-100" />
		</div>
		<div class="h-80 animate-pulse rounded-lg bg-gray-100" />
		<div class="grid gap-4 lg:grid-cols-2">
			<div class="h-64 animate-pulse rounded-lg bg-gray-100" />
			<div class="h-64 animate-pulse rounded-lg bg-gray-100" />
		</div>
	</div>
</template>
