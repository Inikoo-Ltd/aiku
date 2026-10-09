<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 10 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDownload, faArrowUp, faArrowDown, faFileCertificate } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faDownload, faArrowUp, faArrowDown, faFileCertificate)

type Cell = { kg: number; previous_kg: number | null; change: number | null; warning: boolean }

const props = defineProps<{
	data?: {
		from: string
		to: string
		submission_period: string | null
		has_data: boolean
		kg: number
		previous_kg: number | null
		materials: string[]
		form: ({ activity: string; label: string; cells: Record<string, Cell> } & Cell)[]
		lines: { activity: string; type: string; class: string; material: string; ram: string | null; kg: number }[]
	}
	ownBrandImports: boolean
	downloadUrl: string
}>()

const emit = defineEmits<{ (e: "update:ownBrandImports", value: boolean): void }>()

const locale = useLocaleStore()

const materialLabels: Record<string, string> = {
	PC: ctrans("Paper or card"),
	GL: ctrans("Glass"),
	PL: ctrans("Plastic"),
	AL: ctrans("Aluminium"),
	ST: ctrans("Steel"),
	WD: ctrans("Wood"),
	FC: ctrans("Fibre composite"),
	OT: ctrans("Other"),
}

const importMethod = computed({
	get: () => (props.ownBrandImports ? "all" : "once"),
	set: (value: string) => emit("update:ownBrandImports", value === "all"),
})
const importMethods = [
	{ label: ctrans("Own-brand imports reported once"), value: "once" },
	{ label: ctrans("Own-brand imports also under Imported"), value: "all" },
]

const change = computed(() => (props.data?.previous_kg ? Math.round(((props.data.kg - props.data.previous_kg) / props.data.previous_kg) * 1000) / 10 : null))
const kg = (value: number | null) => (value == null ? "—" : locale.number(Math.round(value)))
const date = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" })
</script>

<template>
	<div class="px-4 py-5 space-y-5 text-sm text-gray-700">
		<div class="flex flex-wrap items-center gap-3">
			<SegmentedToggle v-model="importMethod" :options="importMethods" :ariaLabel="ctrans('How own-brand imports are counted')" />
			<a :href="downloadUrl" class="sm:ml-auto">
				<Button type="tertiary" :icon="['fal', 'download']" :label="ctrans('Download return (csv)')" />
			</a>
		</div>

		<div v-if="!data" class="space-y-3">
			<div class="h-20 rounded-lg bg-gray-100 animate-pulse" />
			<div class="h-40 rounded-lg bg-gray-100 animate-pulse" />
		</div>

		<div v-else-if="!data.has_data" class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
			<FontAwesomeIcon :icon="['fal', 'file-certificate']" class="text-2xl text-gray-400" fixed-width aria-hidden="true" />
			<p class="mt-2 font-medium text-gray-900">{{ ctrans("No packaging weights for what moved between :from and :to", { from: date(data.from), to: date(data.to) }) }}</p>
			<p class="mt-1 text-xs text-gray-500">{{ ctrans("The return is worked out from the packaging set on each trade unit. The Completeness tab lists the SKOs still without it.") }}</p>
		</div>

		<template v-else>
			<div class="flex flex-wrap items-end gap-x-8 gap-y-2">
				<div>
					<div class="text-xs text-gray-500">{{ ctrans("Packaging to report") }}</div>
					<div class="flex items-baseline gap-2">
						<span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ ctrans(":kg kg", { kg: kg(data.kg) }) }}</span>
						<span v-if="change !== null" class="text-xs tabular-nums" :class="Math.abs(change) > 30 ? 'text-amber-600' : 'text-gray-500'">
							<FontAwesomeIcon :icon="['fal', change >= 0 ? 'arrow-up' : 'arrow-down']" fixed-width aria-hidden="true" />
							{{ ctrans(":change% vs :kg kg the period before", { change: locale.number(Math.abs(change)), kg: kg(data.previous_kg) }) }}
						</span>
					</div>
				</div>
				<div class="text-xs text-gray-500">
					<template v-if="data.submission_period">{{ ctrans("Submission period :period", { period: data.submission_period }) }}</template>
					<template v-else>{{ ctrans("Not a reporting half-year: the file is for checking only") }}</template>
				</div>
			</div>

			<div class="rounded-lg ring-1 ring-gray-200 bg-white overflow-x-auto">
				<table class="min-w-full text-xs">
					<thead class="text-gray-600">
						<tr>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("kg") }}</th>
							<th v-for="material in data.materials" :key="material" class="px-3 py-2 text-right font-medium whitespace-nowrap">{{ materialLabels[material] ?? material }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Total") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in data.form" :key="row.activity">
							<th class="px-4 py-2 text-left font-medium text-gray-700 whitespace-nowrap">{{ row.label }}</th>
							<td v-for="material in data.materials" :key="material" class="px-3 py-2 text-right tabular-nums align-top">
								<div class="text-gray-700">{{ kg(row.cells[material].kg) }}</div>
								<div v-if="row.cells[material].change !== null" :class="row.cells[material].warning ? 'text-amber-600' : 'text-gray-400'" v-tooltip="ctrans('Previous period: :kg kg', { kg: kg(row.cells[material].previous_kg) })">
									{{ row.cells[material].change > 0 ? "+" : "" }}{{ locale.number(row.cells[material].change) }}%
								</div>
							</td>
							<td class="px-4 py-2 text-right tabular-nums align-top">
								<div class="font-medium text-gray-900">{{ kg(row.kg) }}</div>
								<div v-if="row.change !== null" :class="row.warning ? 'text-amber-600' : 'text-gray-400'">{{ row.change > 0 ? "+" : "" }}{{ locale.number(row.change) }}%</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<details class="rounded-lg ring-1 ring-gray-200 bg-white">
				<summary class="px-4 py-3 cursor-pointer font-semibold text-gray-900">{{ ctrans("Rows in the file (:count)", { count: data.lines.length }) }}</summary>
				<div class="overflow-x-auto border-t border-gray-200">
					<table class="min-w-full text-xs">
						<thead class="text-gray-600">
							<tr class="text-left">
								<th class="px-4 py-2 font-medium">{{ ctrans("Activity") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("Type") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("Class") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("Material") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("RAM") }}</th>
								<th class="px-4 py-2 font-medium text-right">{{ ctrans("kg") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 tabular-nums">
							<tr v-for="line in data.lines" :key="`${line.activity}${line.type}${line.class}${line.material}${line.ram}`">
								<td class="px-4 py-1.5">{{ line.activity }}</td>
								<td class="px-2 py-1.5">{{ line.type }}</td>
								<td class="px-2 py-1.5">{{ line.class }}</td>
								<td class="px-2 py-1.5">{{ line.material }}</td>
								<td class="px-2 py-1.5">{{ line.ram ?? "—" }}</td>
								<td class="px-4 py-1.5 text-right">{{ kg(line.kg) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</details>
		</template>
	</div>
</template>
