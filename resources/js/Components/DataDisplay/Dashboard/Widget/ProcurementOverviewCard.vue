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
	value: number
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
	violet: {
		icon: "bg-violet-50 text-violet-600",
		hover: "group-hover/card:text-violet-700",
		metric: "hover:bg-violet-50 hover:text-violet-700",
		dot: "bg-violet-500",
	},
	emerald: {
		icon: "bg-emerald-50 text-emerald-600",
		hover: "group-hover/card:text-emerald-700",
		metric: "hover:bg-emerald-50 hover:text-emerald-700",
		dot: "bg-emerald-500",
	},
	amber: {
		icon: "bg-amber-50 text-amber-600",
		hover: "group-hover/card:text-amber-700",
		metric: "hover:bg-amber-50 hover:text-amber-700",
		dot: "bg-amber-500",
	},
	indigo: {
		icon: "bg-indigo-50 text-indigo-600",
		hover: "group-hover/card:text-indigo-700",
		metric: "hover:bg-indigo-50 hover:text-indigo-700",
		dot: "bg-indigo-500",
	},
	sky: {
		icon: "bg-sky-50 text-sky-600",
		hover: "group-hover/card:text-sky-700",
		metric: "hover:bg-sky-50 hover:text-sky-700",
		dot: "bg-sky-500",
	},
}

const tone = toneClasses[props.card.tone]
</script>

<template>
	<article
		class="group/card rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow">
		<Link
			:href="route(card.route.name, card.route.parameters)"
			class="block rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
			@start="loadingTarget = 'card'"
			@finish="loadingTarget = null">
			<div class="flex items-center justify-between gap-3">
				<div class="flex min-w-0 items-center gap-2.5">
					<div
						class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md"
						:class="tone.icon">
						<LoadingIcon v-if="loadingTarget === 'card'" />
						<FontAwesomeIcon
							v-else
							:icon="card.icon"
							class="text-sm"
							fixed-width
							aria-hidden="true" />
					</div>
					<h2 class="truncate text-sm font-semibold text-gray-600" :class="tone.hover">
						{{ card.label }}
					</h2>
				</div>
				<FontAwesomeIcon
					icon="fal fa-arrow-right"
					class="text-xs text-gray-300 transition group-hover/card:translate-x-0.5 group-hover/card:text-gray-500"
					aria-hidden="true" />
			</div>

			<div class="mt-3 flex items-baseline gap-2">
				<span class="text-2xl font-semibold tracking-tight text-gray-800 tabular-nums">{{
					locale.number(card.value)
				}}</span>
				<span class="truncate text-xs text-gray-400">{{ card.description }}</span>
			</div>
		</Link>

		<div
			v-if="card.metrics?.length"
			class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-100 pt-2.5">
			<Link
				v-for="metric in card.metrics"
				:key="metric.label"
				:href="route(metric.route.name, metric.route.parameters)"
				class="flex min-w-0 items-center gap-1.5 rounded px-1.5 py-1 text-xs text-gray-500 transition"
				:class="tone.metric"
				@start="loadingTarget = metric.label"
				@finish="loadingTarget = null">
				<span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="tone.dot" />
				<span class="truncate">{{ metric.label }}</span>
				<LoadingIcon v-if="loadingTarget === metric.label" class="text-xs" />
				<span v-else class="font-semibold text-gray-700 tabular-nums">{{
					locale.number(metric.value)
				}}</span>
			</Link>
		</div>
	</article>
</template>
