<script setup lang="ts">
import { withDefaults, ref, onMounted } from "vue"
import Chart from "primevue/chart"
import SectionTable from "@/Components/Table/SectionTable.vue"

// Props definition
const props = defineProps<{
	widget: {}
}>()
</script>
<template>
	<div >
		<!-- Installation Team Card -->
		<div
			class="bg-white rounded-lg p-4 flex flex-col justify-between shadow-sm border border-gray-300">
			<h3 class="text-lg font-semibold mb-2">Installation Team</h3>
			<!-- Metrics -->
			<div>
				<p class="text-4xl font-bold">75%</p>
				<p class="text-sm text-gray-600">Warehouse capacity</p>
			</div>
			<div class="mt-4">
				<p class="text-2xl font-bold">12 day</p>
				<p class="text-sm text-gray-600">Avg. installation time</p>
			</div>
		</div>
	</div>
</template>
