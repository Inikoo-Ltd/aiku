<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"

library.add(faCheck)

const props = defineProps<{
	fieldValue: {}
	theme?: any
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

</script>

<template>
	<div
		class="container mx-auto max-w-7xl px-6 lg:px-8"
		:style="getStyles(fieldValue?.container?.properties)">
		<div class="relative py-8">
			<!-- ➊ thicker, rounded, full‑height line, behind everything -->
			<div
				class="absolute left-1/2 top-0 transform -translate-x-1/2 h-full w-1 bg-gray-200 rounded-full z-0"></div>

			<div
				v-for="(step, idx) in fieldValue.timeline"
				:key="idx"
				class="mb-16 md:grid md:grid-cols-9 md:items-center relative">
				<!-- left content… -->
				<div v-if="idx % 2 === 0" class="md:col-span-4 md:pr-8 text-right px-4">
					<div v-html="step.title" />
					<div v-html="step.description" />
				</div>
				<div v-else class="md:col-span-4"></div>

				<!-- ➋ circle wrapper with higher stacking -->
				<div class="md:col-span-1 flex justify-center relative z-10">
					<div
						class="bg-blue-600 text-white font-bold rounded-full w-14 h-14 flex items-center justify-center shadow-lg ring-4 ring-white ring-offset-2 ring-offset-white">
						{{ idx + 1 }}
					</div>
				</div>

				<!-- right content… -->
				<div v-if="idx % 2 === 1" class="md:col-span-4 md:pl-8 text-left px-4">
					<div v-html="step.title" />
					<div v-html="step.description" />
				</div>
				<div v-else class="md:col-span-4"></div>
			</div>
		</div>
	</div>
</template>
