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
	route: routeType | null
	metrics: ProcurementMetric[]
}

const props = defineProps<{
	card: ProcurementCard
	compact?: boolean
}>()

const locale = inject("locale", aikuLocaleStructure)
const loadingTarget = ref<string | null>(null)

const toneClasses = {
	violet: { icon: "text-violet-600", dots: ["bg-violet-300", "bg-violet-500", "bg-violet-700"] },
	emerald: { icon: "text-emerald-600", dots: ["bg-emerald-300", "bg-emerald-500", "bg-emerald-700"] },
	amber: { icon: "text-amber-600", dots: ["bg-amber-300", "bg-amber-500", "bg-amber-700"] },
	indigo: { icon: "text-indigo-600", dots: ["bg-indigo-300", "bg-indigo-500", "bg-indigo-700"] },
	sky: { icon: "text-sky-600", dots: ["bg-sky-300", "bg-sky-500", "bg-sky-700"] },
}

const tone = toneClasses[props.card.tone]
</script>

<template>
	<div
		class="inline-flex items-center bg-white rounded-lg shadow-sm tabular-nums"
		:class="compact ? 'text-xs' : 'text-sm'">
		<span class="rounded-l-lg border border-indigo-400 px-3 last:rounded-r-lg bg-indigo-50/50">
			<Link
				v-if="card.route"
				v-tooltip="card.label + (card.description ? ' — ' + card.description : '')"
				:href="route(card.route.name, card.route.parameters)"
				class="flex items-center gap-1.5 rounded-l focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
				:class="compact ? 'py-1' : 'py-2'"
				@start="loadingTarget = 'card'"
				@finish="loadingTarget = null">
				<LoadingIcon v-if="loadingTarget === 'card'" class="text-xs" />
				<FontAwesomeIcon v-else :icon="card.icon" :class="tone.icon" fixed-width aria-hidden="true" />
				<span v-if="card.value !== null" class="font-semibold text-gray-700">{{ locale.number(card.value) }}</span>
			</Link>
			<span
				v-else
				v-tooltip="card.label + (card.description ? ' — ' + card.description : '')"
				class="flex items-center gap-1.5"
				:class="compact ? 'py-1' : 'py-2'">
				<FontAwesomeIcon :icon="card.icon" :class="tone.icon" fixed-width aria-hidden="true" />
				<span v-if="card.value !== null" class="font-semibold text-gray-700">{{ locale.number(card.value) }}</span>
			</span>
		</span>

		<template v-for="(metric, metricIndex) in card.metrics" :key="metric.label">
			<Link
				v-tooltip="metric.label"
				:href="route(metric.route.name, metric.route.parameters)"
				class="flex items-center gap-1.5 pl-3 text-gray-500 hover:text-gray-700 border-y border-r border-gray-300 px-3 last:rounded-r-lg"
				:class="compact ? 'py-1' : 'py-2'"
				@start="loadingTarget = metric.label"
				@finish="loadingTarget = null">
				<span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="tone.dots[metricIndex % tone.dots.length]" />
				<LoadingIcon v-if="loadingTarget === metric.label" class="text-xs py-1" />
				<span v-else class="font-semibold text-gray-700">{{ locale.number(metric.value) }}</span>
			</Link>
		</template>
	</div>
</template>
