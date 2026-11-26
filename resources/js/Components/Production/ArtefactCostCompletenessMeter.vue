<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 26 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type CostStatusKey = "complete" | "no_labour" | "unpriced_material" | "no_recipe"

const props = defineProps<{
	status: {
		total: number
		counts: Record<CostStatusKey, number>
		routes: Record<CostStatusKey, { name: string; parameters: Record<string, string> }> | null
	}
}>()

const locale = useLocaleStore()

const parts: { key: CostStatusKey; label: string; hint: string; colour: string }[] = [
	{ key: "complete", label: ctrans("Cost complete"), hint: ctrans("Every recipe material has a cost and every step a target per hour, so materials and labour can be costed"), colour: "bg-green-500" },
	{ key: "no_labour", label: ctrans("Step without target"), hint: ctrans("A recipe step has no target per hour, so its labour cannot be costed"), colour: "bg-amber-400" },
	{ key: "unpriced_material", label: ctrans("Material without cost"), hint: ctrans("A raw material in the recipe has no cost: set it on the raw material"), colour: "bg-orange-300" },
	{ key: "no_recipe", label: ctrans("No recipe materials"), hint: ctrans("The recipe lists no raw materials, so nothing can be costed"), colour: "bg-red-400" },
]

const percent = (count: number) => (props.status.total ? (100 * count) / props.status.total : 0)

const visibleParts = computed(() => parts.filter((part) => props.status.counts[part.key] > 0))

const hrefFor = (key: CostStatusKey) => {
	const target = props.status.routes?.[key]

	return target ? route(target.name, target.parameters) : null
}
</script>

<template>
	<div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm">
		<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
			<span class="font-medium text-gray-900">{{ ctrans("Production cost: active artefacts with complete data") }}</span>
			<span class="text-xs text-gray-500">{{ ctrans(":count artefacts", { count: locale.number(status.total) }) }}</span>
			<span class="ml-auto text-xs font-semibold tabular-nums text-gray-700">{{ ctrans(":percent% complete", { percent: percent(status.counts.complete).toFixed(1) }) }}</span>
		</div>

		<div class="mt-2 flex h-3 w-full gap-0.5 overflow-hidden rounded" role="img" :aria-label="ctrans('Artefacts by how complete their cost data is')">
			<component
				:is="hrefFor(part.key) ? Link : 'span'"
				v-for="part in visibleParts"
				:key="part.key"
				:href="hrefFor(part.key) ?? undefined"
				v-tooltip="`${part.label}: ${locale.number(status.counts[part.key])} (${percent(status.counts[part.key]).toFixed(1)}%)`"
				class="h-full first:rounded-l last:rounded-r"
				:class="part.colour"
				:style="{ width: `${percent(status.counts[part.key])}%`, minWidth: '4px' }"
			/>
		</div>

		<div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
			<component
				:is="hrefFor(part.key) ? Link : 'span'"
				v-for="part in visibleParts"
				:key="part.key"
				:href="hrefFor(part.key) ?? undefined"
				v-tooltip="part.hint"
				class="flex items-center gap-1.5 rounded px-1 text-xs text-gray-700"
				:class="hrefFor(part.key) ? 'hover:bg-gray-100' : ''"
			>
				<span class="inline-block h-2.5 w-2.5 rounded-sm" :class="part.colour" aria-hidden="true" />
				<span class="whitespace-nowrap">{{ part.label }}</span>
				<span class="tabular-nums">{{ locale.number(status.counts[part.key]) }}</span>
				<span class="tabular-nums text-gray-500">{{ percent(status.counts[part.key]).toFixed(1) }}%</span>
			</component>
		</div>
	</div>
</template>
