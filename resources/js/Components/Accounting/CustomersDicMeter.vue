<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 25 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link, usePage } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type DicStatusKey = "ok" | "unchecked" | "name_differs" | "not_found" | "differs" | "no_dic"

const props = defineProps<{
	status: { total: number; counts: Record<DicStatusKey, number>; active: DicStatusKey | null }
}>()

const locale = useLocaleStore()
const page = usePage()

const parts: { key: DicStatusKey; label: string; hint: string; colour: string }[] = [
	{ key: "ok", label: ctrans("Confirmed"), hint: ctrans("The Finančná správa register confirms the DIČ"), colour: "bg-green-500" },
	{ key: "unchecked", label: ctrans("Not checked yet"), hint: ctrans("Has a DIČ, IČO or VAT number the register check has not reached yet"), colour: "bg-gray-300" },
	{ key: "name_differs", label: ctrans("IČO of another name"), hint: ctrans("Its IČO belongs to a company with another name: check the IČO"), colour: "bg-amber-400" },
	{ key: "not_found", label: ctrans("IČO not in register"), hint: ctrans("The register has no company with its IČO: check the IČO"), colour: "bg-orange-300" },
	{ key: "differs", label: ctrans("DIČ differs"), hint: ctrans("The DIČ is not the one the register has for its IČO"), colour: "bg-red-700" },
	{ key: "no_dic", label: ctrans("No DIČ"), hint: ctrans("No DIČ and no IČO or valid VAT number to look it up by: ask the customer"), colour: "bg-red-400" },
]

const hrefFor = (key: DicStatusKey | null) => {
	const url = new URL(page.url, "https://aiku.local")
	url.searchParams.delete("page")
	key ? url.searchParams.set("dic", key) : url.searchParams.delete("dic")

	return url.pathname + url.search
}

const percent = (count: number) => (props.status.total ? (100 * count) / props.status.total : 0)

const visibleParts = computed(() => parts.filter((part) => props.status.counts[part.key] > 0))
</script>

<template>
	<div class="mx-3 mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm md:mx-6">
		<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
			<span class="font-medium text-gray-900">{{ ctrans("Slovak e-invoices: DIČ of business customers") }}</span>
			<span class="text-xs text-gray-500">{{ ctrans(":count customers", { count: locale.number(status.total) }) }}</span>
			<Link v-if="status.active" :href="hrefFor(null)" preserve-scroll class="ml-auto text-xs text-gray-600 underline hover:text-gray-900">
				{{ ctrans("Show all customers") }}
			</Link>
		</div>

		<div class="mt-2 flex h-3 w-full gap-0.5 overflow-hidden rounded" role="img" :aria-label="ctrans('DIČ status of Slovak business customers')">
			<Link
				v-for="part in visibleParts"
				:key="part.key"
				:href="hrefFor(part.key)"
				preserve-scroll
				v-tooltip="`${part.label}: ${locale.number(status.counts[part.key])} (${percent(status.counts[part.key]).toFixed(1)}%)`"
				class="h-full first:rounded-l last:rounded-r"
				:class="[part.colour, status.active && status.active !== part.key ? 'opacity-30' : '']"
				:style="{ width: `${percent(status.counts[part.key])}%`, minWidth: '4px' }"
			/>
		</div>

		<div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
			<Link
				v-for="part in visibleParts"
				:key="part.key"
				:href="hrefFor(part.key)"
				preserve-scroll
				v-tooltip="part.hint"
				class="flex items-center gap-1.5 rounded px-1 text-xs text-gray-700 hover:bg-gray-100"
				:class="status.active === part.key ? 'bg-gray-100 font-semibold text-gray-900' : ''"
			>
				<span class="inline-block h-2.5 w-2.5 rounded-sm" :class="part.colour" aria-hidden="true" />
				<span class="whitespace-nowrap">{{ part.label }}</span>
				<span class="tabular-nums">{{ locale.number(status.counts[part.key]) }}</span>
				<span class="tabular-nums text-gray-500">{{ percent(status.counts[part.key]).toFixed(1) }}%</span>
			</Link>
		</div>
	</div>
</template>
