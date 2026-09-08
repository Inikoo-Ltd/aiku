<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { inject, ref } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { routeType } from "@/types/route"

interface ProcurementMetric {
	label: string
	value: number
	route: routeType
}

interface ProcurementCard {
	label: string
	description: string
	icon: string | string[]
	value: number | null
	tone: "violet" | "emerald" | "amber" | "indigo" | "sky"
	route: routeType
	metrics: ProcurementMetric[]
}

const props = defineProps<{
	card: ProcurementCard
}>()

const locale = inject("locale", aikuLocaleStructure)
const loadingTarget = ref<string | null>(null)

const toneClasses = {
	violet: { icon: "text-violet-600", dot: "bg-violet-500" },
	emerald: { icon: "text-emerald-600", dot: "bg-emerald-500" },
	amber: { icon: "text-amber-600", dot: "bg-amber-500" },
	indigo: { icon: "text-indigo-600", dot: "bg-indigo-500" },
	sky: { icon: "text-sky-600", dot: "bg-sky-500" },
}

const tone = toneClasses[props.card.tone]
</script>

<template>
	<div
		class="inline-flex items-center bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm text-sm tabular-nums">
		<Link
			v-tooltip="card.label + (card.description ? ' — ' + card.description : '')"
			:href="route(card.route.name, card.route.parameters)"
			class="flex items-center gap-1.5 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
			@start="loadingTarget = 'card'"
			@finish="loadingTarget = null">
			<LoadingIcon v-if="loadingTarget === 'card'" class="text-xs" />
			<FontAwesomeIcon v-else :icon="card.icon" :class="tone.icon" fixed-width aria-hidden="true" />
			<span v-if="card.value !== null" class="font-semibold text-gray-700">{{ locale.number(card.value) }}</span>
		</Link>

		<template v-for="metric in card.metrics" :key="metric.label">
			<Link
				v-tooltip="metric.label"
				:href="route(metric.route.name, metric.route.parameters)"
				class="flex items-center gap-1.5 border-l border-gray-200 pl-3 ml-3 rounded text-gray-500 hover:text-gray-700"
				@start="loadingTarget = metric.label"
				@finish="loadingTarget = null">
				<span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="tone.dot" />
				<LoadingIcon v-if="loadingTarget === metric.label" class="text-xs" />
				<span v-else class="font-semibold text-gray-700">{{ locale.number(metric.value) }}</span>
			</Link>
		</template>
	</div>
</template>
