<script setup lang="ts">
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject, ref } from "vue"
import CountUp from "vue-countup-v3"
import { Link } from "@inertiajs/vue3"
import Icon from "@/Components/Icon.vue"
import { routeType } from "@/types/route"
import LoadingIcon from "../Utils/LoadingIcon.vue"

const props = defineProps<{
	box_stats: {
		name: string
		value: number
		route: routeType
		color?: string
		icon: {
			icon: string
			tooltip: string
		} | string
	}[]
}>()

const locale = inject("locale", aikuLocaleStructure)
const isLoading = ref<null | number>(null)

const colorMap: Record<string, string> = {
	red: 'text-red-600 border-red-300 bg-red-50 hover:bg-red-100',
	orange: 'text-orange-600 border-orange-300 bg-orange-50 hover:bg-orange-100',
}
</script>

<template>
	<div class="flex gap-x-3 gap-y-4 p-4 flex-wrap">
		<Link v-for="(stats, index) in box_stats"
			:key="stats.route?.name"
			:href="stats.route?.name ? route(stats.route.name, stats.route.parameters) : '#'"
			@start="() => isLoading = index"
			@finish="() => isLoading = null"
			class=" w-64 border rounded-md p-6 block "
			:class="isLoading === index ? 'bg-gray-200 cursor-default border-gray-300' : (stats.color ? colorMap[stats.color] : 'border-gray-300 bg-gray-50 hover:bg-gray-100')"
		>
			<div class="flex justify-between items-center mb-1">
				<div class="capitalize">{{ stats.name }}</div>
				<LoadingIcon v-if="isLoading === index" class="text-xl text-gray-400" />
				<Icon v-else :data="stats.icon?.icon ? stats.icon : { icon: stats.icon }" class="text-xl" :class="stats.color ? '' : 'text-gray-400'" />
			</div>

			<div class="mb-1 text-2xl font-semibold">
				<CountUp :endVal="stats.value" :duration="1.5" :scrollSpyOnce="true" :options="{
					formattingFn: (value: number) => locale.number(value)
				}" />
			</div>
		</Link>
	</div>
</template>
