<!-- MultiCard.vue -->
<script setup lang="ts">
import { defineProps } from "vue"

defineProps<{
	label: string
	data: Array<{
		label: string
		value: number | string
		type?: string
	}>
	gridCols?: number // Default: 2 columns
	cardStyle?: string // Custom style for child cards
}>()
</script>

<template>
	<div class="bg-white text-gray-800 rounded-lg p-6 shadow-md border border-gray-200">
		<h3 class="text-gray-500 font-semibold text-lg mb-4">{{ label }}</h3>
		<div :class="`grid grid-cols-1 gap-4`">
			<!-- Changed to single-column layout -->
			<div
				v-for="(item, idx) in data"
				:key="idx"
				:class="cardStyle || 'flex flex-col items-start p-2'">
				<p class="text-4xl font-bold">
					{{ item.value }}
				</p>
				<p class="text-gray-500">
					{{ item.label }}
				</p>
			</div>
		</div>
	</div>
</template>
