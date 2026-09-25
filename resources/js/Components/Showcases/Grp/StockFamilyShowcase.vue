<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Thu, 25 May 2023 15:03:05 Central European Summer Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import SalesAnalysisTeaser from "@/Components/SalesAnalysis/SalesAnalysisTeaser.vue"
import SalesAnalysisMovers from "@/Components/SalesAnalysis/SalesAnalysisMovers.vue"

defineProps<{
	salesAnalysisTeaser?: object
}>()
</script>

<template>
	<div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
		<SalesAnalysisTeaser :teaser="salesAnalysisTeaser" />
		<SalesAnalysisMovers :teaser="salesAnalysisTeaser" />
	</div>
</template>
