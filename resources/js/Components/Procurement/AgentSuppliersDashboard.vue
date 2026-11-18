<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboardList, faBullhorn, faShip, faStopwatch, faBalanceScale, faArchive, faChevronRight, faClone, faPencil, faCompressAlt, faBoxOpen, faExclamationTriangle, faForward } from "@fal"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import AgentChasePanelDialog from "@/Components/Procurement/AgentChasePanelDialog.vue"
import SuggestSupplierChangeDialog from "@/Components/Procurement/SuggestSupplierChangeDialog.vue"
import AgentSupplierChangeRequests, { SupplierChangeRequestRow } from "@/Components/Procurement/AgentSupplierChangeRequests.vue"
import AgentSupplierPacking from "@/Components/Procurement/AgentSupplierPacking.vue"

library.add(faClipboardList, faBullhorn, faShip, faStopwatch, faBalanceScale, faArchive, faChevronRight, faClone, faPencil, faCompressAlt, faBoxOpen, faExclamationTriangle, faForward)

type PerformanceRow = {
	code: string
	name: string
	spend: number
	purchase_orders: number
	received: number
	on_time: number | null
	median_days: number | null
	factory_days: number | null
	quantity_errors: number | null
	issues: number
	issue_rate: number | null
	issue_types: { type: string | null; label: string; number: number }[]
	score: number | null
	route: routeType | null
}

type SupplierLink = { id: number; code: string; name: string; pending: string | null; last_ordered_at: string | null; route: routeType | null }

const props = defineProps<{
	data: {
		skip_duplicates_route: routeType
		permissions: { can_suggest: boolean; can_decide: boolean }
		filters: {
			buyer: string | null
			buyers: { slug: string; code: string; name: string }[]
			currency: string
			currencies: string[]
		}
		kpis: {
			open: { value: number; amount: number; without_eta: number; route: routeType }
			chase: { value: number; past_eta: number; unconfirmed: number; route: routeType }
			in_transit: { value: number; amount: number; route: routeType }
			lead_time: { median_days: number | null; factory_days: number | null; on_time: number | null; purchase_orders: number }
			quantity_errors: { value: number; checked: number; percentage: number | null }
			dormant: { value: number; active: number }
			issues: { value: number; open: number }
			extra_packing: { value: number; amount: number }
		}
		stages: { key: string; label: string; value: number; amount: number; route: routeType | null }[]
		ages: { label: string; value: number }[]
		chase: {
			total: number
			rows: {
				id: number
				reason: "past_eta" | "unconfirmed"
				reference: string
				supplier: string
				buyer: string
				days_open: number
				eta: string | null
				amount: number
				route: routeType
				supplier_route: routeType | null
				panel_route: routeType
				last_chase: { chased_at: string; channel: string; promised_at: string | null; note: string | null; by: string | null } | null
			}[]
		}
		performance: { top_spend: PerformanceRow[]; best: PerformanceRow[]; attention: PerformanceRow[]; minimum: number }
		issues: PerformanceRow[]
		packing: InstanceType<typeof AgentSupplierPacking>["$props"]["packing"]
		quantity: { supplier: string; code: string; lines: number; wrong: number; short: number; over: number; percentage: number; route: routeType | null }[]
		cleanup: {
			buckets: { key: string; label: string; value: number }[]
			dormant: { id: number; code: string; name: string; pending: string | null; bucket: string; last_ordered_at: string | null; products: number; route: routeType | null }[]
			duplicates: { match: "name" | "email"; suppliers: SupplierLink[] }[]
			requests: SupplierChangeRequestRow[]
		}
		completeness: { key: string; label: string; filled: number; total: number; percentage: number | null }[]
	}
}>()

const locale = inject("locale", aikuLocaleStructure)
const number = (value: number) => locale.number(value)
const money = (amount: number) => locale.currencyFormat(props.data.filters.currency, Math.round(amount))
const href = (target: routeType) => route(target.name, target.parameters)
const percent = (value: number | null) => (value === null ? "—" : `${value}%`)

const buyer = ref(props.data.filters.buyer ?? "")
const currency = ref(props.data.filters.currency)
watch([buyer, currency], () =>
	router.reload({
		data: { tab: "dashboard", buyer: buyer.value || undefined, currency: currency.value },
		only: ["dashboard"],
		preserveScroll: true,
	})
)
const buyerOptions = computed(() => [
	{ label: ctrans("All"), value: "" },
	...props.data.filters.buyers.map((buyer) => ({ label: buyer.code, value: buyer.slug })),
])
const currencyOptions = computed(() => props.data.filters.currencies.map((code) => ({ label: code, value: code })))

const kpis = computed(() => props.data.kpis)

const tiles = computed(() => [
	{
		key: "open",
		label: ctrans("Open orders"),
		information: ctrans("Orders sent to the suppliers and not finished yet, confirmed or not, and their value."),
		icon: "fal fa-clipboard-list",
		value: number(kpis.value.open.value),
		detail: kpis.value.open.without_eta
			? ctrans(":value, :count without an expected date", { value: money(kpis.value.open.amount), count: number(kpis.value.open.without_eta) })
			: money(kpis.value.open.amount),
		tone: "neutral",
		route: kpis.value.open.route,
	},
	{
		key: "chase",
		label: ctrans("Need chasing"),
		information: ctrans("Orders past their expected date with no container booked, and orders sent over :days days ago the supplier has not confirmed.", { days: 14 }),
		icon: "fal fa-bullhorn",
		value: number(kpis.value.chase.value),
		detail: ctrans(":past_eta past expected date, :unconfirmed not confirmed", { past_eta: number(kpis.value.chase.past_eta), unconfirmed: number(kpis.value.chase.unconfirmed) }),
		tone: kpis.value.chase.past_eta ? "red" : kpis.value.chase.value ? "amber" : "neutral",
		route: kpis.value.chase.route,
	},
	{
		key: "in_transit",
		label: ctrans("In transit"),
		information: ctrans("Open orders whose goods have been dispatched and are on the way."),
		icon: "fal fa-ship",
		value: number(kpis.value.in_transit.value),
		detail: money(kpis.value.in_transit.amount),
		tone: "neutral",
		route: kpis.value.in_transit.route,
	},
	{
		key: "lead_time",
		label: ctrans("Order to warehouse"),
		information: ctrans("Median days from sending the order to its first container reaching the warehouse, and the share that arrived no later than a week after the expected date. Orders sent in the last 24 months."),
		icon: "fal fa-stopwatch",
		value: kpis.value.lead_time.median_days === null ? "—" : ctrans(":days days", { days: kpis.value.lead_time.median_days }),
		detail: [
			ctrans(":on_time on time, :count orders", { on_time: percent(kpis.value.lead_time.on_time), count: number(kpis.value.lead_time.purchase_orders) }),
			kpis.value.lead_time.factory_days !== null ? ctrans("factory :days days", { days: kpis.value.lead_time.factory_days }) : null,
		].filter(Boolean).join(", "),
		tone: kpis.value.lead_time.on_time !== null && kpis.value.lead_time.on_time < 70 ? "amber" : "neutral",
		route: null,
	},
	{
		key: "quantity",
		label: ctrans("Quantity errors"),
		information: ctrans("Delivered lines where the quantity counted at the warehouse differs from the quantity on the delivery, in the last 24 months."),
		icon: "fal fa-balance-scale",
		value: number(kpis.value.quantity_errors.value),
		detail: ctrans(":percentage of :count lines checked", { percentage: percent(kpis.value.quantity_errors.percentage), count: number(kpis.value.quantity_errors.checked) }),
		tone: "neutral",
		route: null,
		anchor: "#quantity",
	},
	{
		key: "dormant",
		label: ctrans("Deactivation candidates"),
		information: ctrans("Active suppliers with no order in the last 24 months, or never ordered from."),
		icon: "fal fa-archive",
		value: number(kpis.value.dormant.value),
		detail: ctrans("of :count active suppliers", { count: number(kpis.value.dormant.active) }),
		tone: kpis.value.dormant.value ? "amber" : "neutral",
		route: null,
		anchor: "#cleanup",
	},
	{
		key: "extra_packing",
		label: ctrans("Extra packing"),
		information: ctrans("Suppliers ordered from in the last 12 months whose goods are packed by a packaging supplier, and the spend with packaging suppliers."),
		icon: "fal fa-box-open",
		value: number(kpis.value.extra_packing.value),
		detail: ctrans(":amount with packaging suppliers", { amount: money(kpis.value.extra_packing.amount) }),
		tone: "neutral",
		route: null,
		anchor: "#packing",
	},
	{
		key: "issues",
		label: ctrans("Issues logged"),
		information: ctrans("Incidents logged on the suppliers, their products or their purchase orders in the last 24 months."),
		icon: "fal fa-exclamation-triangle",
		value: number(kpis.value.issues.value),
		detail: ctrans(":count still open", { count: number(kpis.value.issues.open) }),
		tone: kpis.value.issues.open ? "amber" : "neutral",
		route: null,
		anchor: "#issues",
	},
])

const toneClasses: Record<string, string> = {
	red: "bg-red-50 text-red-600",
	amber: "bg-amber-50 text-amber-600",
	neutral: "bg-gray-100 text-gray-500",
}

const totalAged = computed(() => props.data.ages.reduce((sum, age) => sum + age.value, 0))
const ageColours = ["bg-gray-300", "bg-gray-400", "bg-amber-300", "bg-amber-500", "bg-red-500"]

const openPanel = ref<routeType | null>(null)
const suggestion = ref<InstanceType<typeof SuggestSupplierChangeDialog>["$props"]["suggestion"]>(null)
const suggestDeactivation = (supplier: { id: number; code: string; name: string }) => (suggestion.value = { type: "deactivate", supplier })
const suggestMerge = (supplier: SupplierLink, group: SupplierLink[]) => (suggestion.value = { type: "merge", supplier, candidates: group.filter((other) => other.id !== supplier.id) })

const skippingDuplicates = ref<number | null>(null)
const skipDuplicates = (index: number, group: SupplierLink[]) => {
	router.post(
		href(props.data.skip_duplicates_route),
		{ supplier_ids: group.map((supplier) => supplier.id) },
		{
			preserveScroll: true,
			onStart: () => (skippingDuplicates.value = index),
			onFinish: () => (skippingDuplicates.value = null),
		}
	)
}

const performanceTab = ref<"top_spend" | "best" | "attention">("top_spend")
const performanceTabs = computed(() => [
	{ label: ctrans("Top by spend"), value: "top_spend" },
	{ label: ctrans("Best performers"), value: "best" },
	{ label: ctrans("Needs attention"), value: "attention" },
])
const performanceRows = computed(() => props.data.performance[performanceTab.value])
const scoreClass = (score: number | null) => (score === null ? "text-gray-400" : score >= 80 ? "text-green-700" : score < 60 ? "text-red-600" : "text-amber-700")

const cleanupBucket = ref("")
const cleanupOptions = computed(() => [
	{ label: ctrans("All :count", { count: number(kpis.value.dormant.value) }), value: "" },
	...props.data.cleanup.buckets.map((bucket) => ({ label: `${bucket.label} ${number(bucket.value)}`, value: bucket.key })),
])
const dormantSuppliers = computed(() => props.data.cleanup.dormant.filter((supplier) => !cleanupBucket.value || supplier.bucket === cleanupBucket.value))
</script>

<template>
	<div class="space-y-4 p-4 sm:p-6">
		<div class="flex flex-wrap items-center gap-x-6 gap-y-2">
			<div class="flex items-center gap-2 text-sm text-gray-600">
				<span>{{ ctrans("Buying company") }}</span>
				<SegmentedToggle v-model="buyer" :options="buyerOptions" :aria-label="ctrans('Buying company')" />
			</div>
			<div class="flex items-center gap-2 text-sm text-gray-600">
				<span>{{ ctrans("Currency") }}</span>
				<SegmentedToggle v-model="currency" :options="currencyOptions" :aria-label="ctrans('Currency')" />
			</div>
		</div>

		<div class="grid grid-cols-[repeat(auto-fill,minmax(10.5rem,1fr))] gap-3">
			<article v-for="tile in tiles" :key="tile.key" class="group flex flex-col rounded-lg border border-gray-200 bg-white px-3 py-2.5 shadow-sm transition hover:border-gray-300">
				<component :is="tile.route ? Link : 'a'" :href="tile.route ? href(tile.route) : tile.anchor" class="block">
					<div class="flex items-start gap-2">
						<span class="flex h-6 w-6 shrink-0 items-center justify-center rounded" :class="toneClasses[tile.tone]">
							<FontAwesomeIcon :icon="tile.icon" class="text-xs" fixed-width aria-hidden="true" />
						</span>
						<h2 class="min-w-0 flex-1 pt-1 text-xs font-semibold leading-tight text-gray-700">{{ tile.label }}</h2>
						<InformationIcon :information="tile.information" class="shrink-0 pt-1 text-xs text-gray-400" @click.prevent />
					</div>
					<div class="mt-1.5 whitespace-nowrap text-2xl font-semibold tracking-tight tabular-nums" :class="tile.tone === 'red' ? 'text-red-600' : 'text-gray-900'">
						{{ tile.value }}
					</div>
				</component>
				<p v-if="tile.detail" class="mt-0.5 text-xs leading-snug text-gray-500">{{ tile.detail }}</p>
			</article>
		</div>

		<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-gray-700">
				{{ ctrans("Open orders by stage") }}
				<InformationIcon :information="ctrans('Each open order counted once, at the furthest stage recorded on it. Drafts are still being prepared by the buying company.')" class="text-xs font-normal text-gray-500" />
			</h2>
			<div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
				<component
					:is="stage.route ? Link : 'div'"
					v-for="stage in data.stages"
					:key="stage.key"
					:href="stage.route ? href(stage.route) : undefined"
					class="rounded-md border border-gray-100 px-3 py-2"
					:class="stage.route ? 'hover:border-gray-300 hover:bg-gray-50' : 'bg-gray-50'">
					<div class="text-xs" :class="stage.value ? 'text-gray-600' : 'text-gray-400'">{{ stage.label }}</div>
					<div class="text-xl font-semibold tabular-nums" :class="stage.value ? 'text-gray-900' : 'text-gray-300'">{{ number(stage.value) }}</div>
					<div class="text-xs tabular-nums text-gray-500">{{ stage.value ? money(stage.amount) : "" }}</div>
				</component>
			</div>

			<h3 class="mb-2 mt-5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">
				{{ ctrans("Days since the order was sent") }}
			</h3>
			<div v-if="totalAged" class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100">
				<div
					v-for="(age, index) in data.ages"
					:key="age.label"
					v-tooltip="ctrans(':label days: :count orders', { label: age.label, count: number(age.value) })"
					:class="ageColours[index]"
					:style="{ width: `${(age.value / totalAged) * 100}%` }" />
			</div>
			<div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
				<span v-for="(age, index) in data.ages" :key="age.label" class="flex items-center gap-1.5">
					<span class="h-2 w-2 rounded-full" :class="ageColours[index]" />
					{{ ctrans(":label days", { label: age.label }) }}
					<span class="tabular-nums text-gray-900">{{ number(age.value) }}</span>
				</span>
			</div>
		</section>

		<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
			<header class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
				<h2 class="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
					{{ ctrans("Chase list") }}
					<InformationIcon :information="ctrans('Orders past their expected date first, oldest date first, then the longest waiting for confirmation.')" class="text-xs font-normal text-gray-500" />
				</h2>
				<Link v-if="data.chase.total > data.chase.rows.length" :href="href(kpis.chase.route)" class="text-xs text-gray-500 hover:text-gray-900 hover:underline">
					{{ ctrans("All :count orders to chase", { count: number(data.chase.total) }) }}
				</Link>
			</header>
			<p v-if="!data.chase.rows.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("Nothing to chase today") }}</p>
			<div v-else class="overflow-x-auto">
				<table class="w-full text-sm">
					<thead class="bg-gray-50 text-xs text-gray-500">
						<tr>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("Status") }}</th>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("Purchase order") }}</th>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("Supplier") }}</th>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("Buyer") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Days open") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("ETA") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Value") }}</th>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("Next action") }}</th>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("Last chased") }}</th>
							<th class="px-4 py-2" />
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in data.chase.rows" :key="row.id" class="hover:bg-gray-50">
							<td class="whitespace-nowrap px-4 py-2">
								<span class="rounded px-2 py-0.5 text-xs font-medium" :class="row.reason === 'past_eta' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'">
									{{ row.reason === "past_eta" ? ctrans("Past ETA") : ctrans("Not confirmed") }}
								</span>
							</td>
							<td class="px-4 py-2"><Link :href="href(row.route)" class="font-medium text-gray-700 hover:underline">{{ row.reference }}</Link></td>
							<td class="px-4 py-2">
								<Link v-if="row.supplier_route" :href="href(row.supplier_route)" class="text-gray-700 hover:underline">{{ row.supplier }}</Link>
								<span v-else>{{ row.supplier }}</span>
							</td>
							<td class="px-4 py-2 text-gray-600">{{ row.buyer }}</td>
							<td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number(row.days_open) }}</td>
							<td class="whitespace-nowrap px-4 py-2 text-right" :class="row.reason === 'past_eta' ? 'text-red-600' : 'text-gray-500'">{{ row.eta ? useFormatTime(row.eta) : "—" }}</td>
							<td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ money(row.amount) }}</td>
							<td class="px-4 py-2 text-gray-600">{{ row.reason === "past_eta" ? ctrans("Ask for a new delivery date") : ctrans("Get the order confirmed") }}</td>
							<td class="px-4 py-2 text-xs text-gray-600">
								<template v-if="row.last_chase">
									<div class="whitespace-nowrap">{{ useFormatTime(row.last_chase.chased_at) }} · {{ row.last_chase.channel }}</div>
									<div v-if="row.last_chase.promised_at" class="whitespace-nowrap text-gray-500">{{ ctrans("Promised :date", { date: useFormatTime(row.last_chase.promised_at) }) }}</div>
								</template>
								<span v-else class="text-gray-400">{{ ctrans("Not chased") }}</span>
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right">
								<Button type="tertiary" size="xs" :label="ctrans('Update / chase')" @click="openPanel = row.panel_route">
									<template #icon>
										<FontAwesomeIcon icon="fal fa-pencil" class="text-gray-500" fixed-width aria-hidden="true" />
									</template>
								</Button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>

		<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
			<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm xl:col-span-2">
				<header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
					<h2 class="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
						{{ ctrans("Supplier performance") }}
						<InformationIcon
							:information="ctrans('Spend and orders over the last 12 months; the rest over 24 months. Score: half on time, 30% accuracy (quantity errors and logged issues), 20% speed (factory lead time, or days to warehouse, against the agent median). Scored from :count received orders.', { count: data.performance.minimum })"
							class="text-xs font-normal text-gray-500" />
					</h2>
					<SegmentedToggle v-model="performanceTab" :options="performanceTabs" :aria-label="ctrans('Supplier performance')" />
				</header>
				<p v-if="!performanceRows.length" class="px-4 py-8 text-center text-sm text-gray-400">
					{{ performanceTab === "top_spend" ? ctrans("No orders in the last 12 months") : ctrans("No supplier has :count received orders yet", { count: data.performance.minimum }) }}
				</p>
				<div v-else class="overflow-x-auto">
					<table class="w-full text-sm">
						<thead class="bg-gray-50 text-xs text-gray-500">
							<tr>
								<th class="px-4 py-2 text-left font-medium">{{ ctrans("Supplier") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Spend") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Orders") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("On time") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Days to warehouse") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Factory days") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Quantity errors") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Issues") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Score") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="supplier in performanceRows" :key="supplier.code" class="hover:bg-gray-50">
								<td class="px-4 py-2">
									<Link v-if="supplier.route" :href="href(supplier.route)" class="font-medium text-gray-700 hover:underline">{{ supplier.name }}</Link>
									<span v-else>{{ supplier.name }}</span>
									<span class="ml-1 text-xs text-gray-400">{{ supplier.code }}</span>
								</td>
								<td class="px-4 py-2 text-right tabular-nums text-gray-900">{{ money(supplier.spend) }}</td>
								<td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number(supplier.purchase_orders) }}</td>
								<td class="px-4 py-2 text-right tabular-nums" :class="supplier.on_time !== null && supplier.on_time < 70 ? 'text-amber-700' : 'text-gray-700'">{{ percent(supplier.on_time) }}</td>
								<td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ supplier.median_days ?? "—" }}</td>
								<td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ supplier.factory_days ?? "—" }}</td>
								<td class="px-4 py-2 text-right tabular-nums" :class="supplier.quantity_errors !== null && supplier.quantity_errors >= 5 ? 'text-amber-700' : 'text-gray-700'">{{ percent(supplier.quantity_errors) }}</td>
								<td class="px-4 py-2 text-right tabular-nums" :class="supplier.issues ? 'text-amber-700' : 'text-gray-400'">{{ number(supplier.issues) }}</td>
								<td class="px-4 py-2 text-right font-semibold tabular-nums" :class="scoreClass(supplier.score)">{{ supplier.score ?? "—" }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<section id="quantity" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
				<header class="flex items-center gap-1.5 border-b border-gray-200 px-4 py-3">
					<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Most quantity errors") }}</h2>
					<InformationIcon :information="ctrans('Suppliers with the most delivered lines counted short or over at the warehouse, last 24 months.')" class="text-xs text-gray-500" />
				</header>
				<p v-if="!data.quantity.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("No quantity errors in the last 24 months") }}</p>
				<ul v-else class="divide-y divide-gray-100">
					<li v-for="supplier in data.quantity" :key="supplier.code" class="px-4 py-2.5">
						<div class="flex items-center justify-between gap-2">
							<Link v-if="supplier.route" :href="href(supplier.route)" class="truncate text-sm font-medium text-gray-700 hover:underline">{{ supplier.supplier }}</Link>
							<span v-else class="truncate text-sm">{{ supplier.supplier }}</span>
							<span class="shrink-0 text-sm tabular-nums text-gray-700">{{ percent(supplier.percentage) }}</span>
						</div>
						<div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
							<span>{{ ctrans(":wrong of :lines lines", { wrong: number(supplier.wrong), lines: number(supplier.lines) }) }}</span>
							<span v-if="supplier.short" class="rounded bg-amber-50 px-1.5 text-amber-700">{{ ctrans(":count short", { count: number(supplier.short) }) }}</span>
							<span v-if="supplier.over" class="rounded bg-gray-100 px-1.5 text-gray-600">{{ ctrans(":count over", { count: number(supplier.over) }) }}</span>
						</div>
					</li>
				</ul>
			</section>
		</div>

		<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
			<section id="issues" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
				<header class="flex items-center gap-1.5 border-b border-gray-200 px-4 py-3">
					<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-gray-400" fixed-width aria-hidden="true" />
					<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Most issues") }}</h2>
					<InformationIcon :information="ctrans('Suppliers ranked by issues logged per received order, last 24 months. Issues are logged as incident notes on the supplier, one of its products or a purchase order.')" class="text-xs text-gray-500" />
				</header>
				<p v-if="!data.issues.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("No issues logged in the last 24 months") }}</p>
				<ul v-else class="divide-y divide-gray-100">
					<li v-for="supplier in data.issues" :key="supplier.code" class="px-4 py-2.5">
						<div class="flex items-center justify-between gap-2">
							<Link v-if="supplier.route" :href="href(supplier.route)" class="truncate text-sm font-medium text-gray-700 hover:underline">{{ supplier.name }}</Link>
							<span v-else class="truncate text-sm">{{ supplier.name }}</span>
							<span class="shrink-0 text-sm tabular-nums text-gray-700">
								{{ supplier.issue_rate !== null ? ctrans(":rate per 100 orders", { rate: supplier.issue_rate }) : ctrans(":count issues", { count: supplier.issues }) }}
							</span>
						</div>
						<div class="mt-1 flex flex-wrap gap-1.5 text-xs">
							<span v-for="issueType in supplier.issue_types" :key="issueType.type ?? 'none'" class="rounded bg-amber-50 px-1.5 text-amber-700">{{ issueType.label }} {{ issueType.number }}</span>
						</div>
					</li>
				</ul>
			</section>

			<AgentSupplierPacking :packing="data.packing" :currency="data.filters.currency" :canEdit="data.permissions.can_suggest" />
		</div>

		<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
			<section id="cleanup" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm xl:col-span-2">
				<header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
					<h2 class="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
						{{ ctrans("Clean-up: deactivation candidates") }}
						<InformationIcon :information="ctrans('Active suppliers with no order in the last 24 months, or never ordered from, by when they were last ordered from.')" class="text-xs font-normal text-gray-500" />
					</h2>
					<SegmentedToggle v-model="cleanupBucket" :options="cleanupOptions" :aria-label="ctrans('Last ordered')" />
				</header>
				<p v-if="!dormantSuppliers.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("Every active supplier was ordered from in the last 24 months") }}</p>
				<div v-else class="max-h-96 overflow-auto">
					<table class="w-full text-sm">
						<thead class="sticky top-0 bg-gray-50 text-xs text-gray-500">
							<tr>
								<th class="px-4 py-2 text-left font-medium">{{ ctrans("Supplier") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Last ordered") }}</th>
								<th class="px-4 py-2 text-right font-medium">{{ ctrans("Products") }}</th>
								<th v-if="data.permissions.can_suggest" class="px-4 py-2" />
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="supplier in dormantSuppliers" :key="supplier.code" class="hover:bg-gray-50">
								<td class="px-4 py-2">
									<Link v-if="supplier.route" :href="href(supplier.route)" class="font-medium text-gray-700 hover:underline">{{ supplier.name }}</Link>
									<span v-else>{{ supplier.name }}</span>
									<span class="ml-1 text-xs text-gray-400">{{ supplier.code }}</span>
								</td>
								<td class="whitespace-nowrap px-4 py-2 text-right text-gray-600">{{ supplier.last_ordered_at ? useFormatTime(supplier.last_ordered_at) : ctrans("Never") }}</td>
								<td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number(supplier.products) }}</td>
								<td v-if="data.permissions.can_suggest" class="whitespace-nowrap px-4 py-2 text-right">
									<span v-if="supplier.pending" class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{{ ctrans("Waiting for directors") }}</span>
									<Button v-else type="tertiary" size="xs" :label="ctrans('Suggest deactivation')" @click="suggestDeactivation(supplier)">
										<template #icon>
											<FontAwesomeIcon icon="fal fa-archive" class="text-amber-600" fixed-width aria-hidden="true" />
										</template>
									</Button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<div class="space-y-4">
				<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="flex items-center gap-1.5 border-b border-gray-200 px-4 py-3">
						<FontAwesomeIcon icon="fal fa-clone" class="text-gray-400" fixed-width aria-hidden="true" />
						<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Possible duplicate suppliers") }}</h2>
						<InformationIcon :information="ctrans('Active suppliers with the same name, once words like Co. or Ltd are dropped, or the same email.')" class="text-xs text-gray-500" />
					</header>
					<p v-if="!data.cleanup.duplicates.length" class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No possible duplicates found") }}</p>
					<ul v-else class="divide-y divide-gray-100">
						<li v-for="(group, index) in data.cleanup.duplicates" :key="index" class="px-4 py-2.5">
							<div class="flex items-center justify-between gap-2">
								<span class="text-xs text-gray-400">{{ group.match === "email" ? ctrans("Same email") : ctrans("Same name") }}</span>
								<Button
									v-if="data.permissions.can_suggest"
									type="tertiary"
									size="xs"
									icon="fal fa-forward"
									:label="ctrans('Skip')"
									:loading="skippingDuplicates === index"
									v-tooltip="ctrans('These are different suppliers, stop listing them as possible duplicates')"
									@click="skipDuplicates(index, group.suppliers)" />
							</div>
							<div v-for="supplier in group.suppliers" :key="supplier.code" class="flex items-center justify-between gap-2 text-sm">
								<span class="truncate">
									<Link v-if="supplier.route" :href="href(supplier.route)" class="font-medium text-gray-700 hover:underline">{{ supplier.code }}</Link>
									<span class="ml-1 text-gray-500">{{ supplier.name }}</span>
								</span>
								<span class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
									{{ supplier.last_ordered_at ? useFormatTime(supplier.last_ordered_at) : ctrans("Never ordered") }}
									<span v-if="supplier.pending" class="rounded bg-amber-50 px-1.5 text-amber-700">{{ ctrans("Waiting") }}</span>
									<Button v-else-if="data.permissions.can_suggest" type="tertiary" size="xs" :label="ctrans('Merge')" v-tooltip="ctrans('Suggest merging this record into another one of the group')" @click="suggestMerge(supplier, group.suppliers)">
										<template #icon>
											<FontAwesomeIcon icon="fal fa-compress-alt" class="text-amber-600" fixed-width aria-hidden="true" />
										</template>
									</Button>
								</span>
							</div>
						</li>
					</ul>
				</section>

				<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
					<h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-gray-700">
						{{ ctrans("Stage dates filled in") }}
						<InformationIcon :information="ctrans('Share of the orders sent in the last 12 months with each date recorded.')" class="text-xs font-normal text-gray-500" />
					</h2>
					<div class="space-y-2">
						<div
							v-for="field in data.completeness"
							:key="field.key"
							v-tooltip="ctrans(':filled of :total orders', { filled: number(field.filled), total: number(field.total) })"
							class="grid grid-cols-[9rem_minmax(0,1fr)_3rem] items-center gap-3 text-sm">
							<span class="text-gray-600">{{ field.label }}</span>
							<span class="h-2 overflow-hidden rounded-full bg-gray-100">
								<span class="block h-full rounded-full bg-gray-700" :style="{ width: `${field.percentage ?? 0}%` }" />
							</span>
							<span class="text-right tabular-nums text-gray-700">{{ percent(field.percentage) }}</span>
						</div>
					</div>
				</section>
			</div>
		</div>

		<AgentSupplierChangeRequests :requests="data.cleanup.requests" :canDecide="data.permissions.can_decide" />

		<AgentChasePanelDialog :panelRoute="openPanel" @close="openPanel = null" />
		<SuggestSupplierChangeDialog :suggestion="suggestion" @close="suggestion = null" />
	</div>
</template>
