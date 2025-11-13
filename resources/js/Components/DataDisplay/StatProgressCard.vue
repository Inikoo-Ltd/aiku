<script setup lang="ts">
import ProgressBar from "primevue/progressbar"

defineProps({
	title: {
		type: String,
		required: true,
	},
	utilization: {
		type: Number,
		required: true,
	},
	stockValue: {
		type: Number,
		required: true,
	},
})
</script>

<template>
	<div class="flex flex-col bg-white text-gray-800 rounded-lg p-5 w-64 shadow-md">
		<h3 class="text-lg font-bold mb-6">{{ title }}</h3>

		<!-- Utilization Section -->
		<div v-if="utilization !== undefined && utilization !== null" class="mb-6">
			<p class="text-4xl font-bold">{{ utilization }}%</p>
			<p class="text-sm text-gray-400">Utilization</p>
			<div class="mt-3">
				<!-- ProgressBar with Blue Style -->
				<ProgressBar
                    
					:value="utilization"
					:style="{ backgroundColor: '#E0E7FF', height: '10px' }"
					class="progress-bar" />
				<div class="flex justify-between text-xs text-gray-400 mt-1">
					<span>0%</span>
					<span>100%</span>
				</div>
			</div>
		</div>
		<!-- Fallback if utilization is not provided -->
		<div v-else class="mb-6 text-sm text-gray-400">
			<p>Utilization data not available</p>
		</div>

		<!-- Stock Value -->
		<div v-if="stockValue !== undefined && stockValue !== null" class="mt-auto">
			<p class="text-4xl font-bold">${{ stockValue }}M</p>
			<p class="text-sm text-gray-400">Value of stock</p>
		</div>
		<!-- Fallback if stockValue is not provided -->
		<div v-else class="mt-auto text-sm text-gray-400">
			<p>Stock value data not available</p>
		</div>
	</div>
</template>

<style scoped lang="scss">
:deep(.p-progressbar-value) {
    background-color: #2563EB !important; /* Custom blue color */
}

:deep(.p-progressbar) {
    background-color: #E0E7FF; /* Track color */
}
</style>