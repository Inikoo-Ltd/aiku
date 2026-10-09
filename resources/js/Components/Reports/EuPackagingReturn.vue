<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 10 Oct 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDownload, faArrowUp, faArrowDown, faFileCertificate, faExclamationTriangle } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faDownload, faArrowUp, faArrowDown, faFileCertificate, faExclamationTriangle)

const props = defineProps<{
	data?: {
		scheme_label: string | null
		from: string
		to: string
		has_data: boolean
		kg: number
		previous_kg: number | null
		rows: { scheme_material: string; scheme_subcategory: string | null; sales_kg: number; shipment_kg: number; kg: number; previous_kg: number | null; change: number | null }[]
		domestic_parcel_share: number | null
		plastic_without_polymer: number
		units: number
		units_without_weights: number
	}
	downloadUrl: string
}>()

const locale = useLocaleStore()
const change = computed(() => (props.data?.previous_kg ? Math.round(((props.data.kg - props.data.previous_kg) / props.data.previous_kg) * 1000) / 10 : null))
const withoutWeights = computed(() => (props.data?.units ? Math.round((props.data.units_without_weights / props.data.units) * 1000) / 10 : 0))
const kg = (value: number | null) => (value == null ? "—" : locale.number(Math.round(value * 10) / 10))
const date = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" })
</script>

<template>
	<div class="px-4 py-5 space-y-5 text-sm text-gray-700">
		<div v-if="!data" class="space-y-3">
			<div class="h-20 rounded-lg bg-gray-100 animate-pulse" />
			<div class="h-40 rounded-lg bg-gray-100 animate-pulse" />
		</div>

		<div v-else-if="!data.has_data" class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
			<FontAwesomeIcon :icon="['fal', 'file-certificate']" class="text-2xl text-gray-400" fixed-width aria-hidden="true" />
			<p class="mt-2 font-medium text-gray-900">{{ ctrans("No packaging weights for what was sold in the country between :from and :to", { from: date(data.from), to: date(data.to) }) }}</p>
		</div>

		<template v-else>
			<div class="flex flex-wrap items-end gap-x-8 gap-y-2">
				<div>
					<div class="text-xs text-gray-500">{{ ctrans("Packaging placed on the market · :scheme", { scheme: data.scheme_label ?? "" }) }}</div>
					<div class="flex items-baseline gap-2">
						<span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ ctrans(":kg kg", { kg: kg(data.kg) }) }}</span>
						<span v-if="change !== null" class="text-xs tabular-nums" :class="Math.abs(change) > 30 ? 'text-amber-600' : 'text-gray-500'">
							<FontAwesomeIcon :icon="['fal', change >= 0 ? 'arrow-up' : 'arrow-down']" fixed-width aria-hidden="true" />
							{{ ctrans(":change% vs :kg kg the period before", { change: locale.number(Math.abs(change)), kg: kg(data.previous_kg) }) }}
						</span>
					</div>
				</div>
				<a :href="downloadUrl" class="sm:ml-auto">
					<Button type="tertiary" :icon="['fal', 'download']" :label="ctrans('Download return (csv)')" />
				</a>
			</div>

			<div class="rounded-lg ring-1 ring-gray-200 bg-white overflow-x-auto">
				<table class="min-w-full text-xs">
					<thead class="text-gray-600">
						<tr class="text-left">
							<th class="px-4 py-2 font-medium">{{ ctrans("Material") }}</th>
							<th class="px-2 py-2 font-medium text-right">{{ ctrans("Sales packaging") }}</th>
							<th class="px-2 py-2 font-medium text-right">{{ ctrans("Shipment packaging (estimate)") }}</th>
							<th class="px-2 py-2 font-medium text-right">{{ ctrans("Total kg") }}</th>
							<th class="px-4 py-2 font-medium text-right">{{ ctrans("Change") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 tabular-nums">
						<tr v-for="row in data.rows" :key="`${row.scheme_material}${row.scheme_subcategory}`">
							<td class="px-4 py-2">
								<span class="font-medium text-gray-900">{{ row.scheme_material }}</span>
								<span v-if="row.scheme_subcategory" class="text-gray-500"> · {{ row.scheme_subcategory }}</span>
							</td>
							<td class="px-2 py-2 text-right">{{ kg(row.sales_kg) }}</td>
							<td class="px-2 py-2 text-right">{{ kg(row.shipment_kg) }}</td>
							<td class="px-2 py-2 text-right font-medium text-gray-900">{{ kg(row.kg) }}</td>
							<td class="px-4 py-2 text-right" :class="row.change !== null && Math.abs(row.change) > 30 ? 'text-amber-600' : 'text-gray-500'" v-tooltip="ctrans('Previous period: :kg kg', { kg: kg(row.previous_kg) })">
								{{ row.change === null ? "—" : `${row.change > 0 ? "+" : ""}${locale.number(row.change)}%` }}
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<ul class="space-y-1.5 text-xs text-gray-500">
				<li>{{ ctrans("Sales packaging of what was dispatched to addresses in the country. Goods sent to other countries fall under their schemes and are not included.") }}</li>
				<li v-if="data.domestic_parcel_share !== null">
					{{ ctrans("Shipment packaging is the cartons and mailers used in the period times the share of parcels that stayed in the country (:share%).", { share: locale.number(data.domestic_parcel_share) }) }}
				</li>
				<li v-if="withoutWeights > 0">{{ ctrans(":share% of the units sold in the country have no packaging weights yet and are missing; the Completeness tab lists them.", { share: locale.number(withoutWeights) }) }}</li>
				<li v-if="data.plastic_without_polymer > 0" class="text-amber-700">
					<FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" fixed-width aria-hidden="true" />
					{{ ctrans(":kg kg of plastic has no polymer recorded and is reported on the general plastic line; EPS, PVC and other plastics are charged at higher rates.", { kg: kg(data.plastic_without_polymer) }) }}
				</li>
			</ul>
		</template>
	</div>
</template>
