<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBullseye, faDoorOpen, faStopwatch, faEye } from "@fal"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

const props = defineProps<{
	engagement?: {
		days?: number
		page_views?: number
		visitors?: number
		add_to_baskets?: number
		conversion_rate?: number
		bounces?: number
		bounce_rate?: number
		avg_time_on_page?: number
		timed_page_views?: number
	} | null
}>()

const isLoading = computed(() => props.engagement === undefined)

const duration = (seconds: number) => (seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`)

const percent = (value?: number) => `${(value ?? 0).toFixed(2)}%`

const tiles = computed(() => [
	{
		key: "page_views",
		label: ctrans("Page views"),
		icon: faEye,
		color: "text-indigo-600",
		value: String(props.engagement?.page_views ?? 0),
		hint: ctrans(":count visitors", { count: props.engagement?.visitors ?? 0 }),
	},
	{
		key: "conversion_rate",
		label: ctrans("Conversion rate"),
		icon: faBullseye,
		color: "text-emerald-600",
		value: percent(props.engagement?.conversion_rate),
		hint: ctrans(":count added to basket", { count: props.engagement?.add_to_baskets ?? 0 }),
	},
	{
		key: "bounce_rate",
		label: ctrans("Bounce rate"),
		icon: faDoorOpen,
		color: "text-rose-600",
		value: percent(props.engagement?.bounce_rate),
		hint: ctrans(":count sessions saw this page and nothing else", { count: props.engagement?.bounces ?? 0 }),
	},
	{
		key: "avg_time_on_page",
		label: ctrans("Avg. time on page"),
		icon: faStopwatch,
		color: "text-sky-600",
		value: duration(props.engagement?.avg_time_on_page ?? 0),
		hint: ctrans("Measured on :timed of :views views", {
			timed: props.engagement?.timed_page_views ?? 0,
			views: props.engagement?.page_views ?? 0,
		}),
	},
])
</script>

<template>
	<div class="rounded-lg border border-gray-200 bg-white shadow-sm">
		<div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-6 py-3">
			<span class="text-sm font-semibold">{{ ctrans("Ads performance") }}</span>
			<span class="text-xs text-gray-500">{{ ctrans("Last :days days", { days: engagement?.days ?? 30 }) }}</span>
		</div>

		<div v-if="isLoading" class="flex justify-center px-6 py-8">
			<LoadingIcon class="text-4xl" />
		</div>

		<template v-else>
			<div class="grid grid-cols-2 gap-px bg-gray-200">
				<div v-for="tile in tiles" :key="tile.key" class="bg-white p-4">
					<div class="flex items-center gap-2 text-xs text-gray-500">
						<FontAwesomeIcon :icon="tile.icon" :class="tile.color" fixed-width aria-hidden="true" />
						{{ tile.label }}
					</div>
					<div class="mt-1 text-2xl font-semibold text-gray-800">{{ tile.value }}</div>
					<div class="mt-1 text-[11px] leading-tight text-gray-500">{{ tile.hint }}</div>
				</div>
			</div>

			<div v-if="!engagement?.page_views" class="border-t border-gray-200 px-6 py-3 text-xs text-gray-500">
				{{ ctrans("No visitors recorded yet, so nothing can be read from these numbers") }}
			</div>
		</template>
	</div>
</template>
