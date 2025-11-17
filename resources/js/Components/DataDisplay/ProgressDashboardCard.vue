<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"

const props = defineProps<{
	progressBar: {
		value: number
		max: number
		color?: string
		routeDashboard?: {}
		right_label: {
			route:{}
			label: string
		}
		label:string
	}
}>()

const progressPercentage = computed(() => {
	return Math.min((props.progressBar.value / props.progressBar.max) * 100, 100)
})

const progressColor = computed(() => {
	return props.progressBar.color || "bg-blue-500"
})

function RouteDashboard(shop: any) {
	return route(shop?.name, shop?.parameters)
}
</script>

<template>
	<div>
	<span>{{progressBar.label}}</span>
		<div class="w-full bg-gray-300 rounded-full h-2">
			<div
				:class="`h-2 rounded-full ${progressColor}`"
				:style="{ width: `${progressPercentage}%` }"></div>
		</div>
		<div class="flex justify-between text-xs text-gray-500 mt-1">
			<span>{{ progressPercentage.toFixed(1) }}%</span>
			<span v-if="progressBar?.right_label">
				<Link v-if="progressBar.right_label.route" :href="RouteDashboard(progressBar.right_label.route)" class="primaryLink">
					{{progressBar.right_label.label}}
				</Link>
				<template v-else>
					{{ progressBar.right_label.label }}
				</template>
			</span>
			<span v-else>{{ progressBar.max }}</span>
		</div>
	</div>
</template>
