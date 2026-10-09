<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 10 Oct 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxOpen, faBalanceScale, faTasks, faHistory, faArrowUp, faArrowDown, faInboxIn, faInboxOut } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faBoxOpen, faBalanceScale, faTasks, faHistory, faArrowUp, faArrowDown, faInboxIn, faInboxOut)

type Status = "no_trade_unit" | "no_packaging" | "no_components" | "legacy" | "complete"

const props = defineProps<{
	data?: {
		from: string
		to: string
		has_data: boolean
		summary: {
			units: number
			skos: number
			coverage: number | null
			previous_coverage: number | null
			units_by_status: Record<Status, number>
			skos_by_status: Record<Status, number>
		}
		activities: { activity: string; label: string; units: number; coverage: number | null }[]
		rows: {
			org_stock_id: number | null
			code: string | null
			name: string | null
			trade_unit_slug: string | null
			packaging_family_code: string | null
			status: Status
			units: number
			units_in: number
			units_out: number
			share: number
		}[]
		rows_total: number
	}
}>()

const locale = useLocaleStore()

const statuses: Record<Status, { label: string; dot: string; badge: string }> = {
	no_trade_unit: { label: ctrans("No trade unit"), dot: "bg-red-600", badge: "bg-red-50 text-red-700 ring-red-200" },
	no_packaging: { label: ctrans("No packaging"), dot: "bg-red-400", badge: "bg-red-50 text-red-700 ring-red-200" },
	no_components: { label: ctrans("Packaging without weights"), dot: "bg-amber-500", badge: "bg-amber-50 text-amber-700 ring-amber-200" },
	legacy: { label: ctrans("Old UK sheet weights"), dot: "bg-sky-400", badge: "bg-sky-50 text-sky-700 ring-sky-200" },
	complete: { label: ctrans("Complete"), dot: "bg-emerald-500", badge: "bg-emerald-50 text-emerald-700 ring-emerald-200" },
}
const statusOrder = Object.keys(statuses) as Status[]

const summary = computed(() => props.data?.summary)
const missingUnits = computed(() => (summary.value ? summary.value.units_by_status.no_trade_unit + summary.value.units_by_status.no_packaging + summary.value.units_by_status.no_components : 0))
const skosToFix = computed(() => (summary.value ? summary.value.skos - summary.value.skos_by_status.complete : 0))
const coverageDelta = computed(() => {
	if (summary.value?.coverage == null || summary.value?.previous_coverage == null) return null
	return Math.round((summary.value.coverage - summary.value.previous_coverage) * 10) / 10
})
const statusShare = (status: Status) => (summary.value?.units ? (summary.value.units_by_status[status] / summary.value.units) * 100 : 0)

const rowsWithCumulative = computed(() => {
	let cumulative = 0
	return (props.data?.rows ?? []).map((row) => {
		cumulative += row.share
		return { ...row, cumulative }
	})
})

const percent = (value: number | null) => (value == null ? "—" : `${locale.number(value)}%`)
const date = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" })
</script>

<template>
	<div class="px-4 py-5 space-y-5 text-sm text-gray-700">
		<div v-if="!data" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
			<div v-for="n in 4" :key="n" class="h-20 rounded-lg bg-gray-100 animate-pulse" />
		</div>

		<div v-else-if="!data.has_data" class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
			<FontAwesomeIcon :icon="['fal', 'box-open']" class="text-2xl text-gray-400" fixed-width aria-hidden="true" />
			<p class="mt-2 font-medium text-gray-900">{{ ctrans("Nothing moved between :from and :to", { from: date(data.from), to: date(data.to) }) }}</p>
			<p class="mt-1 text-xs text-gray-500">{{ ctrans("Deliveries and dispatches are classified every night. Older periods appear once they have been built.") }}</p>
		</div>

		<template v-else-if="summary">
			<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
				<div class="rounded-lg ring-1 ring-gray-200 bg-white px-4 py-3">
					<div class="flex items-center gap-1.5 text-xs text-gray-500">
						<FontAwesomeIcon :icon="['fal', 'balance-scale']" fixed-width aria-hidden="true" />
						{{ ctrans("Units with packaging weights") }}
					</div>
					<div class="mt-1 flex items-baseline gap-2">
						<span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ percent(summary.coverage) }}</span>
						<span v-if="coverageDelta !== null" class="text-xs tabular-nums" :class="coverageDelta >= 0 ? 'text-emerald-600' : 'text-red-600'">
							<FontAwesomeIcon :icon="['fal', coverageDelta >= 0 ? 'arrow-up' : 'arrow-down']" fixed-width aria-hidden="true" />
							{{ ctrans(":points pts vs previous period", { points: locale.number(Math.abs(coverageDelta)) }) }}
						</span>
					</div>
				</div>
				<div class="rounded-lg ring-1 ring-gray-200 bg-white px-4 py-3">
					<div class="flex items-center gap-1.5 text-xs text-gray-500">
						<FontAwesomeIcon :icon="['fal', 'box-open']" fixed-width aria-hidden="true" />
						{{ ctrans("Units without weights") }}
					</div>
					<div class="mt-1 font-medium text-gray-900 tabular-nums">{{ locale.number(Math.round(missingUnits)) }}</div>
					<div class="text-xs text-gray-500 tabular-nums">{{ ctrans("of :units moved", { units: locale.number(Math.round(summary.units)) }) }}</div>
				</div>
				<div class="rounded-lg ring-1 ring-gray-200 bg-white px-4 py-3">
					<div class="flex items-center gap-1.5 text-xs text-gray-500">
						<FontAwesomeIcon :icon="['fal', 'tasks']" fixed-width aria-hidden="true" />
						{{ ctrans("SKOs to fix") }}
					</div>
					<div class="mt-1 font-medium text-gray-900 tabular-nums">{{ locale.number(skosToFix) }}</div>
					<div class="text-xs text-gray-500 tabular-nums">{{ ctrans("of :skos that moved", { skos: locale.number(summary.skos) }) }}</div>
				</div>
				<div class="rounded-lg ring-1 ring-gray-200 bg-white px-4 py-3">
					<div class="flex items-center gap-1.5 text-xs text-gray-500">
						<FontAwesomeIcon :icon="['fal', 'history']" fixed-width aria-hidden="true" />
						{{ ctrans("On old UK sheet weights") }}
					</div>
					<div class="mt-1 font-medium text-gray-900 tabular-nums">{{ locale.number(summary.skos_by_status.legacy) }}</div>
					<div class="text-xs text-gray-500 tabular-nums">{{ ctrans(":share of units, to be weighed", { share: percent(Math.round(statusShare('legacy') * 10) / 10) }) }}</div>
				</div>
			</div>

			<div class="grid gap-3 lg:grid-cols-2">
				<div class="rounded-lg ring-1 ring-gray-200 bg-white px-4 py-3">
					<h3 class="font-semibold text-gray-900">{{ ctrans("Coverage by activity") }}</h3>
					<ul class="mt-3 space-y-2.5">
						<li v-for="activity in data.activities" :key="activity.activity" class="grid grid-cols-[minmax(8rem,12rem)_minmax(0,1fr)_3.5rem] items-center gap-3 text-xs">
							<div>
								<div class="text-gray-700">{{ activity.label }}</div>
								<div class="text-gray-500 tabular-nums">{{ ctrans(":units units", { units: locale.number(Math.round(activity.units)) }) }}</div>
							</div>
							<div class="h-2 rounded-full bg-gray-100 overflow-hidden" role="img" :aria-label="percent(activity.coverage)">
								<div class="h-full rounded-full bg-emerald-500" :style="{ width: `${activity.coverage ?? 0}%` }" />
							</div>
							<div class="text-right tabular-nums text-gray-700">{{ percent(activity.coverage) }}</div>
						</li>
					</ul>
				</div>

				<div class="rounded-lg ring-1 ring-gray-200 bg-white px-4 py-3">
					<h3 class="font-semibold text-gray-900">{{ ctrans("Where the units stand") }}</h3>
					<div class="mt-3 flex h-3 rounded-full overflow-hidden bg-gray-100" role="img" :aria-label="ctrans('Units by packaging status')">
						<div v-for="status in statusOrder" :key="status" :class="statuses[status].dot" :style="{ width: `${statusShare(status)}%` }" />
					</div>
					<ul class="mt-3 space-y-1.5 text-xs">
						<li v-for="status in statusOrder" :key="status" class="grid grid-cols-[minmax(0,1fr)_5rem_4rem] items-center gap-3">
							<span class="flex items-center gap-2">
								<span class="h-2.5 w-2.5 rounded-full" :class="statuses[status].dot" aria-hidden="true" />
								{{ statuses[status].label }}
							</span>
							<span class="text-right tabular-nums text-gray-500">{{ ctrans(":skos SKOs", { skos: locale.number(summary.skos_by_status[status]) }) }}</span>
							<span class="text-right tabular-nums">{{ percent(Math.round(statusShare(status) * 10) / 10) }}</span>
						</li>
					</ul>
				</div>
			</div>

			<div class="rounded-lg ring-1 ring-gray-200 bg-white">
				<div class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 border-b border-gray-200">
					<h3 class="font-semibold text-gray-900">{{ ctrans("SKOs to fix, most units first") }}</h3>
					<span class="text-xs text-gray-500">{{ ctrans("Showing :shown of :total", { shown: locale.number(data.rows.length), total: locale.number(data.rows_total) }) }}</span>
				</div>
				<div v-if="!data.rows.length" class="px-4 py-8 text-center text-emerald-700">{{ ctrans("Every SKO that moved has packaging weights.") }}</div>
				<div v-else class="overflow-x-auto">
					<table class="min-w-full text-xs">
						<thead class="text-gray-600">
							<tr class="text-left">
								<th class="px-4 py-2 font-medium">{{ ctrans("SKO") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("Status") }}</th>
								<th class="px-2 py-2 font-medium text-right"><FontAwesomeIcon :icon="['fal', 'inbox-in']" v-tooltip="ctrans('Units in')" :aria-label="ctrans('Units in')" fixed-width /></th>
								<th class="px-2 py-2 font-medium text-right"><FontAwesomeIcon :icon="['fal', 'inbox-out']" v-tooltip="ctrans('Units out')" :aria-label="ctrans('Units out')" fixed-width /></th>
								<th class="px-2 py-2 font-medium text-right">{{ ctrans("Share of units") }}</th>
								<th class="px-4 py-2 font-medium text-right">{{ ctrans("Cumulative") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="row in rowsWithCumulative" :key="`${row.org_stock_id}-${row.trade_unit_slug}`">
								<td class="px-4 py-2">
									<Link v-if="row.trade_unit_slug" :href="route('grp.trade_units.units.show', { tradeUnit: row.trade_unit_slug, tab: 'compliance' })" class="font-medium hover:underline">{{ row.code ?? "—" }}</Link>
									<span v-else class="font-medium">{{ row.code ?? "—" }}</span>
									<div class="text-gray-500 truncate max-w-xs">{{ row.name }}</div>
								</td>
								<td class="px-2 py-2">
									<span class="inline-flex items-center rounded px-1.5 py-0.5 ring-1 ring-inset whitespace-nowrap" :class="statuses[row.status].badge">{{ statuses[row.status].label }}</span>
								</td>
								<td class="px-2 py-2 text-right tabular-nums">{{ locale.number(Math.round(row.units_in)) }}</td>
								<td class="px-2 py-2 text-right tabular-nums">{{ locale.number(Math.round(row.units_out)) }}</td>
								<td class="px-2 py-2 text-right tabular-nums">{{ percent(row.share) }}</td>
								<td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ percent(Math.round(row.cumulative * 10) / 10) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</template>
	</div>
</template>
