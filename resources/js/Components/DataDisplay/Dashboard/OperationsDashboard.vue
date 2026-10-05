<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClock, faDolly, faTruckContainer, faUsers, faInventory, faUndoAlt, faChartLine, faSyncAlt, faChevronDown, faShippingFast, faQuestionCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faClock, faDolly, faTruckContainer, faUsers, faInventory, faUndoAlt, faChartLine, faSyncAlt, faChevronDown, faShippingFast, faQuestionCircle)

type RouteLink = { name: string; parameters: Record<string, string | number> } | null
type Breakdown = { warehouse: string; value: number; route: RouteLink }[]
type Tile = { count: number | null; oldest?: string | null; breakdown?: Breakdown; [key: string]: unknown }
type Stage = Tile & { replacements: number; premium: number; amount: number | null }

const props = defineProps<{
	fetchRoute: { name: string }
	active: boolean
}>()

const locale = useLocaleStore()
const data = ref<Record<string, any> | null>(null)
const isLoading = ref(false)
const failed = ref(false)
const showSales = ref(true)
const now = ref(Date.now())
const filters = ref<{ warehouse: number | null; channel: string | null; period: number }>({ warehouse: null, channel: null, period: 30 })

let pollTimer: ReturnType<typeof setInterval> | null = null
let clockTimer: ReturnType<typeof setInterval> | null = null

const load = async (remember = false): Promise<void> => {
	isLoading.value = true
	try {
		const params = data.value || remember
			? { warehouse: filters.value.warehouse ?? "", channel: filters.value.channel ?? "", period: filters.value.period, remember: remember ? 1 : 0 }
			: {}
		const response = await axios.get(route(props.fetchRoute.name), { params })
		data.value = response.data
		filters.value = {
			warehouse: response.data.filters.warehouse,
			channel: response.data.filters.channel,
			period: response.data.filters.period,
		}
		failed.value = false
	} catch {
		failed.value = true
	} finally {
		isLoading.value = false
	}
}

const startPolling = () => {
	stopPolling()
	pollTimer = setInterval(() => {
		if (document.visibilityState === "visible") {
			load()
		}
	}, 60000)
}

const stopPolling = () => {
	if (pollTimer) {
		clearInterval(pollTimer)
		pollTimer = null
	}
}

watch(
	() => props.active,
	(isActive) => {
		if (isActive) {
			load()
			startPolling()
		} else {
			stopPolling()
		}
	},
	{ immediate: true }
)

onMounted(() => {
	clockTimer = setInterval(() => (now.value = Date.now()), 30000)
})

onBeforeUnmount(() => {
	stopPolling()
	if (clockTimer) {
		clearInterval(clockTimer)
	}
})

const changeFilter = () => load(true)

const minutesSince = (iso?: string | null): number | null => (iso ? Math.max(0, Math.round((now.value - new Date(iso).getTime()) / 60000)) : null)

const duration = (minutes: number | null): string => {
	if (minutes === null) {
		return "—"
	}
	if (minutes < 60) {
		return `${minutes}m`
	}
	if (minutes < 48 * 60) {
		const hours = Math.floor(minutes / 60)
		const rest = minutes % 60
		return rest ? `${hours}h ${rest}m` : `${hours}h`
	}
	return `${Math.floor(minutes / 1440)}d`
}

const age = (iso?: string | null): string => duration(minutesSince(iso))
const seconds = (value: number | null | undefined): string => (value === null || value === undefined ? "—" : duration(Math.round(value / 60)))
const daysLate = (date?: string | null): number | null => (date ? Math.max(0, Math.floor((now.value - new Date(date).getTime()) / 86400000)) : null)

const money = (amount: number | null | undefined, currency?: string): string | null =>
	amount === null || amount === undefined ? null : locale.CurrencyShort(currency || data.value?.currency_code, Number(amount))

const thresholds = computed(() => data.value?.thresholds ?? {})
const attention = computed<Record<string, Tile>>(() => data.value?.attention ?? {})
const pipeline = computed<Record<string, Stage>>(() => data.value?.pipeline ?? {})
const isSingleWarehouse = computed(() => (data.value?.warehouses?.length ?? 0) === 1)

type Tone = "grey" | "neutral" | "amber" | "red"

const tileTone = (key: string, tile: Tile): Tone => {
	if (!tile || tile.count === null) {
		return "grey"
	}
	if (tile.count === 0) {
		return "grey"
	}
	const minutes = minutesSince(tile.oldest as string | null)
	switch (key) {
		case "urgent":
			return minutes !== null && minutes > thresholds.value.urgent_minutes ? "red" : "amber"
		case "blocked":
			if (minutes !== null && minutes > thresholds.value.blocked_red_hours * 60) {
				return "red"
			}
			return tile.count > thresholds.value.blocked_amber_count ? "amber" : "neutral"
		case "customer_service":
			return minutes !== null && minutes > thresholds.value.cs_red_hours * 60 ? "red" : "neutral"
		case "out_of_stock":
		case "stock_errors":
			return "red"
		case "replenishment":
		case "overdue":
			return "amber"
		default:
			return "neutral"
	}
}

const toneClass: Record<Tone, string> = {
	grey: "bg-gray-50 ring-gray-200 text-gray-400",
	neutral: "bg-white ring-gray-200 text-gray-800",
	amber: "bg-amber-50 ring-amber-300 text-amber-800",
	red: "bg-red-50 ring-red-300 text-red-700",
}

const attentionTiles = computed(() => {
	const tiles = attention.value
	if (!tiles.urgent) {
		return []
	}
	return [
		{
			key: "urgent",
			label: ctrans("Urgent queue not picked"),
			help: ctrans("Premium dispatch delivery notes not picked yet (to assign or queued). Red when the oldest has waited over :minutes minutes.", { minutes: thresholds.value.urgent_minutes }),
			sub: tiles.urgent.count ? ctrans("Oldest :age", { age: age(tiles.urgent.oldest) }) : null,
		},
		{
			key: "at_risk",
			label: ctrans("At risk of missing collection"),
			help: ctrans("Needs the carrier collection times per warehouse, which the goods out supervisor maintains. Not set up yet."),
			sub: ctrans("Collection times not set"),
		},
		{
			key: "blocked",
			label: ctrans("Blocked orders"),
			help: ctrans("Delivery notes blocked in picking. Stock: an item is waiting for the warehouse. CS: an item is waiting for customer service. Amber over :count, red when one has been blocked over :hours hours.", { count: thresholds.value.blocked_amber_count, hours: thresholds.value.blocked_red_hours }),
			sub: tiles.blocked.count
				? [
					tiles.blocked.reasons.stock ? `${ctrans("Stock")} ${tiles.blocked.reasons.stock}` : null,
					tiles.blocked.reasons.customer_service ? `${ctrans("CS")} ${tiles.blocked.reasons.customer_service}` : null,
					tiles.blocked.reasons.other ? `${ctrans("Other")} ${tiles.blocked.reasons.other}` : null,
				].filter(Boolean).join(" · ")
				: null,
		},
		{
			key: "customer_service",
			label: ctrans("CS waiting for decision"),
			help: ctrans("Delivery notes in picking or blocked with an item waiting for customer service. Red when the oldest is over :hours hours.", { hours: thresholds.value.cs_red_hours }),
			sub: tiles.customer_service.count ? ctrans("Oldest :age", { age: age(tiles.customer_service.oldest) }) : null,
		},
		{
			key: "out_of_stock",
			label: ctrans("Out of stock on open orders"),
			help: ctrans("SKOs with no stock left in any location that are still to be picked on an open delivery note."),
			sub: tiles.out_of_stock.count ? ctrans(":count delivery notes", { count: tiles.out_of_stock.delivery_notes as number }) : null,
		},
		{
			key: "replenishment",
			label: ctrans("Replenishment needed"),
			help: ctrans("Picking locations below their minimum while another location holds stock."),
			sub: ctrans("Pick faces below minimum"),
		},
		{
			key: "overdue",
			label: ctrans("Overdue deliveries"),
			help: ctrans("Inbound stock deliveries not yet arrived whose expected date has passed (delivery date, else the purchase order's)."),
			sub: tiles.overdue.count ? ctrans("Oldest :days days late", { days: daysLate(tiles.overdue.oldest as string) ?? 0 }) : null,
		},
		{
			key: "stock_errors",
			label: ctrans("Stock errors"),
			help: ctrans("Locations holding negative stock."),
			sub: tiles.stock_errors.count ? ctrans("Negative stock") : null,
		},
	]
})

const stages = computed(() => [
	{ key: "unassigned", label: ctrans("To assign"), help: ctrans("In the warehouse, not given to a picker yet. Age since it arrived in the warehouse.") },
	{ key: "queued", label: ctrans("Queued"), help: ctrans("Given to a picker, not started. Age since queued.") },
	{ key: "picking", label: ctrans("Picking"), help: ctrans("Being picked. Age since picking started.") },
	{ key: "blocked", label: ctrans("Blocked"), help: ctrans("Picking stopped: waiting for stock or for customer service. Age since blocked.") },
	{ key: "packing", label: ctrans("Packing"), help: ctrans("Picked or being packed. Age since picked.") },
	{ key: "packed", label: ctrans("Packed"), help: ctrans("Packed, not yet invoiced and finalised. Age since packed.") },
	{ key: "finalised", label: ctrans("Waiting for dispatch"), help: ctrans("Invoiced and ready, waiting for the carrier. Same figure as Waiting for dispatch on the Sales tab, which counts orders.") },
	{ key: "dispatched", label: ctrans("Dispatched today"), help: ctrans("Dispatched since midnight, warehouse local time.") },
])

const waitingTotal = computed(() => (pipeline.value.unassigned?.count ?? 0) + (pipeline.value.queued?.count ?? 0))

const percentOf = (value: number, total: number) => (total > 0 ? (value / total) * 100 : 0)

const comparison = (todayValue: number, before: number): string | null => {
	if (!before) {
		return null
	}
	const change = Math.round(((todayValue - before) / before) * 100)
	return `${change > 0 ? "+" : ""}${change}%`
}

const ageBuckets = computed(() => {
	const buckets = data.value?.age_buckets ?? {}
	return [
		{ key: "under_4h", label: ctrans("Under 4h"), value: buckets.under_4h ?? 0 },
		{ key: "h4_24", label: ctrans("4–24h"), value: buckets.h4_24 ?? 0 },
		{ key: "d1_2", label: ctrans("1–2 days"), value: buckets.d1_2 ?? 0 },
		{ key: "over_2d", label: ctrans("Over 2 days"), value: buckets.over_2d ?? 0 },
	]
})
const ageBucketMax = computed(() => Math.max(1, ...ageBuckets.value.map((bucket) => bucket.value)))

const stateLabel = (state: string): string =>
	({
		confirmed: ctrans("Confirmed"),
		ready_to_ship: ctrans("Ready to ship"),
		dispatched: ctrans("On the way"),
		received: ctrans("Arrived – not booked in"),
		checked: ctrans("Checked – not booked in"),
		booking_in: ctrans("Booking in"),
	})[state] ?? state

const singleRoute = (tile?: Tile): RouteLink => (tile?.breakdown?.length === 1 ? tile.breakdown[0].route : null)
</script>

<template>
	<div class="px-3 sm:px-6 py-4 space-y-4">
		<div class="flex flex-wrap items-center gap-2 text-sm">
			<select v-model="filters.warehouse" class="rounded-md border-gray-300 py-1.5 text-sm" :aria-label="ctrans('Warehouse')" @change="changeFilter">
				<option :value="null">{{ ctrans("All warehouses") }}</option>
				<option v-for="warehouse in data?.filters?.options?.warehouses ?? []" :key="warehouse.id" :value="warehouse.id">{{ warehouse.label }}</option>
			</select>
			<select v-model="filters.channel" class="rounded-md border-gray-300 py-1.5 text-sm" :aria-label="ctrans('Channel')" @change="changeFilter">
				<option :value="null">{{ ctrans("All channels") }}</option>
				<option v-for="channel in data?.filters?.options?.channels ?? []" :key="channel.value" :value="channel.value">{{ channel.label }}</option>
			</select>
			<div class="ml-auto flex flex-wrap items-center gap-x-3 text-xs text-gray-500">
				<span v-for="warehouse in data?.warehouses ?? []" :key="warehouse.id" v-tooltip="warehouse.timezone">
					{{ warehouse.name }} {{ warehouse.local_time }}
				</span>
				<button type="button" class="flex items-center gap-x-1 text-gray-400 hover:text-gray-700" :aria-label="ctrans('Refresh')" @click="load()">
					<FontAwesomeIcon icon="fal fa-sync-alt" :spin="isLoading" fixed-width aria-hidden="true" />
					<span>{{ ctrans("Live") }}</span>
				</button>
			</div>
		</div>

		<div v-if="failed && !data" class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ ctrans("The operations figures could not be loaded.") }}</div>

		<div v-if="!data" class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3" aria-busy="true">
			<div v-for="index in 8" :key="index" class="h-24 animate-pulse rounded-lg bg-gray-100" />
		</div>

		<template v-else>
			<section>
				<h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Needs attention now") }}</h3>
				<div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3">
					<div v-for="tile in attentionTiles" :key="tile.key" class="flex flex-col rounded-lg p-3 ring-1" :class="toneClass[tileTone(tile.key, attention[tile.key])]">
						<div class="flex items-start justify-between gap-x-1 text-xs font-medium leading-tight">
							<span>{{ tile.label }}</span>
							<FontAwesomeIcon icon="fal fa-question-circle" class="mt-0.5 shrink-0 cursor-help opacity-50" fixed-width aria-hidden="true" v-tooltip="tile.help" />
						</div>
						<component
							:is="singleRoute(attention[tile.key]) ? Link : 'span'"
							:href="singleRoute(attention[tile.key]) ? route(singleRoute(attention[tile.key])!.name, singleRoute(attention[tile.key])!.parameters) : undefined"
							class="mt-1 text-2xl font-semibold tabular-nums"
							:class="singleRoute(attention[tile.key]) ? 'hover:underline' : ''"
						>
							{{ attention[tile.key].count === null ? "—" : locale.number(attention[tile.key].count as number) }}
						</component>
						<span v-if="tile.sub" class="text-xs opacity-80">{{ tile.sub }}</span>
						<div v-if="!isSingleWarehouse && attention[tile.key].breakdown" class="mt-auto flex flex-wrap gap-x-2 pt-1 text-[11px]">
							<template v-for="row in attention[tile.key].breakdown" :key="row.warehouse">
								<Link v-if="row.value && row.route" :href="route(row.route.name, row.route.parameters)" class="opacity-80 hover:underline">{{ row.warehouse }} {{ row.value }}</Link>
							</template>
						</div>
					</div>
				</div>
			</section>

			<section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
				<div class="xl:col-span-2 rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
					<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
						<FontAwesomeIcon icon="fal fa-dolly" class="text-gray-400" fixed-width aria-hidden="true" />
						{{ ctrans("Order pipeline") }}
						<span class="text-xs font-normal text-gray-400">{{ ctrans("Delivery notes · oldest in stage") }}</span>
					</h3>
					<div class="mt-3 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
						<div v-for="stage in stages" :key="stage.key" class="rounded-md bg-gray-50 p-2">
							<div class="flex items-center gap-x-1 text-xs font-medium text-gray-500">
								{{ stage.label }}
								<FontAwesomeIcon icon="fal fa-question-circle" class="cursor-help text-gray-300" fixed-width aria-hidden="true" v-tooltip="stage.help" />
							</div>
							<component
								:is="singleRoute(pipeline[stage.key]) ? Link : 'span'"
								:href="singleRoute(pipeline[stage.key]) ? route(singleRoute(pipeline[stage.key])!.name, singleRoute(pipeline[stage.key])!.parameters) : undefined"
								class="block text-xl font-semibold tabular-nums"
								:class="[stage.key === 'blocked' && pipeline[stage.key].count ? 'text-red-600' : 'text-gray-800', singleRoute(pipeline[stage.key]) ? 'hover:underline' : '']"
							>
								{{ locale.number(pipeline[stage.key].count ?? 0) }}
							</component>
							<div class="text-[11px] text-gray-500">
								<span v-if="stage.key !== 'dispatched' && pipeline[stage.key].oldest">{{ ctrans("Oldest :age", { age: age(pipeline[stage.key].oldest) }) }}</span>
								<span v-if="data.can_see_value && pipeline[stage.key].amount" class="block">{{ money(pipeline[stage.key].amount) }}</span>
								<span v-if="pipeline[stage.key].replacements" class="block" v-tooltip="ctrans('Replacements: no order value, not counted on the Sales tab')">{{ ctrans(":count replacements", { count: pipeline[stage.key].replacements }) }}</span>
							</div>
							<div v-if="!isSingleWarehouse" class="mt-1 flex flex-wrap gap-x-1.5 text-[10px] text-gray-400">
								<template v-for="row in pipeline[stage.key].breakdown" :key="row.warehouse">
									<Link v-if="row.value && row.route" :href="route(row.route.name, row.route.parameters)" class="hover:text-gray-700 hover:underline">{{ row.warehouse }} {{ row.value }}</Link>
								</template>
							</div>
						</div>
					</div>

					<div class="mt-4">
						<div class="text-xs font-medium text-gray-500">
							{{ ctrans("Why :count delivery notes are waiting", { count: waitingTotal }) }}
							<FontAwesomeIcon icon="fal fa-question-circle" class="cursor-help text-gray-300" fixed-width aria-hidden="true" v-tooltip="ctrans('Against the stock on the shelves now; other orders asking for the same stock are not netted off.')" />
						</div>
						<div class="mt-1 flex h-3 w-full overflow-hidden rounded-full bg-gray-100">
							<div class="h-full bg-green-500" :style="{ width: percentOf(data.waiting_split.pickable, waitingTotal) + '%' }" />
							<div class="h-full bg-amber-400" :style="{ width: percentOf(data.waiting_split.partly, waitingTotal) + '%' }" />
							<div class="h-full bg-gray-400" :style="{ width: percentOf(data.waiting_split.no_stock, waitingTotal) + '%' }" />
						</div>
						<div class="mt-1 flex flex-wrap gap-x-4 text-xs text-gray-600">
							<span><span class="inline-block h-2 w-2 rounded-full bg-green-500" /> {{ ctrans("Pickable now") }} {{ data.waiting_split.pickable }}</span>
							<span><span class="inline-block h-2 w-2 rounded-full bg-amber-400" /> {{ ctrans("Partly pickable") }} {{ data.waiting_split.partly }}</span>
							<span><span class="inline-block h-2 w-2 rounded-full bg-gray-400" /> {{ ctrans("Awaiting stock") }} {{ data.waiting_split.no_stock }}</span>
							<span>{{ ctrans("Premium") }} {{ data.waiting_split.premium }} · {{ ctrans("Normal") }} {{ data.waiting_split.normal }}</span>
						</div>
					</div>
				</div>

				<div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
					<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
						<FontAwesomeIcon icon="fal fa-shipping-fast" class="text-gray-400" fixed-width aria-hidden="true" />
						{{ ctrans("Next collections") }}
					</h3>
					<p class="mt-3 rounded-md bg-gray-50 p-3 text-sm text-gray-500">
						{{ ctrans("The countdown to each carrier's collection needs the collection times per warehouse. They are not set up in Aiku yet.") }}
					</p>
					<div class="mt-4 text-xs font-medium text-gray-500">{{ ctrans("Dispatched today, by this time") }}</div>
					<table class="mt-1 w-full text-sm">
						<thead>
							<tr class="text-left text-[11px] text-gray-400">
								<th class="font-normal" />
								<th class="font-normal text-right">{{ ctrans("Orders") }}</th>
								<th class="font-normal text-right">{{ ctrans("Parcels") }}</th>
								<th class="font-normal text-right">{{ ctrans("Lines") }}</th>
							</tr>
						</thead>
						<tbody class="tabular-nums">
							<tr>
								<td class="text-gray-500">{{ ctrans("Today") }}</td>
								<td class="text-right font-semibold">{{ pipeline.dispatched.count }}</td>
								<td class="text-right font-semibold">{{ pipeline.dispatched.parcels }}</td>
								<td class="text-right font-semibold">{{ pipeline.dispatched.lines }}</td>
							</tr>
							<tr v-for="row in [{ key: 'yesterday', label: ctrans('Yesterday') }, { key: 'same_day_last_week', label: ctrans('Same day last week') }]" :key="row.key" class="text-gray-600">
								<td class="text-gray-500">{{ row.label }}</td>
								<td class="text-right">
									{{ pipeline.dispatched[row.key].count }}
									<span class="text-[10px] text-gray-400">{{ comparison(pipeline.dispatched.count as number, pipeline.dispatched[row.key].count) }}</span>
								</td>
								<td class="text-right">{{ pipeline.dispatched[row.key].parcels }}</td>
								<td class="text-right">{{ pipeline.dispatched[row.key].lines }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
				<div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
					<div class="flex items-center justify-between">
						<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
							<FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
							{{ ctrans("Time to dispatch") }}
							<FontAwesomeIcon icon="fal fa-question-circle" class="cursor-help text-gray-300" fixed-width aria-hidden="true" v-tooltip="ctrans('From the delivery note reaching the warehouse to its dispatch, orders only (not replacements). Same day on the warehouse calendar.')" />
						</h3>
						<select v-model="filters.period" class="rounded-md border-gray-300 py-1 text-xs" :aria-label="ctrans('Period')" @change="changeFilter">
							<option :value="1">{{ ctrans("Today") }}</option>
							<option :value="7">{{ ctrans("Last 7 days") }}</option>
							<option :value="30">{{ ctrans("Last 30 days") }}</option>
						</select>
					</div>
					<dl class="mt-3 grid grid-cols-4 gap-3">
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("Median") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">{{ seconds(data.time_to_dispatch.median_seconds) }}</dd>
						</div>
						<div>
							<dt class="text-xs text-gray-500" v-tooltip="data.time_to_dispatch.is_combined ? ctrans('Slowest warehouse') : null">{{ ctrans("90th percentile") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">{{ seconds(data.time_to_dispatch.p90_seconds) }}</dd>
						</div>
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("Same day") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">{{ data.time_to_dispatch.same_day_percent === null ? "—" : data.time_to_dispatch.same_day_percent + "%" }}</dd>
						</div>
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("Within SLA") }}</dt>
							<dd class="text-xl font-semibold tabular-nums text-gray-300" v-tooltip="ctrans('Needs the SLA per channel and fulfilment client')">—</dd>
						</div>
					</dl>
					<div class="mt-4 text-xs font-medium text-gray-500">{{ ctrans("Open delivery notes by age") }}</div>
					<div class="mt-1 space-y-1">
						<div v-for="bucket in ageBuckets" :key="bucket.key" class="flex items-center gap-x-2 text-xs">
							<span class="w-20 text-gray-500">{{ bucket.label }}</span>
							<div class="h-2 flex-1 rounded-full bg-gray-100">
								<div class="h-2 rounded-full" :class="bucket.key === 'over_2d' ? 'bg-gray-500' : 'bg-gray-400'" :style="{ width: percentOf(bucket.value, ageBucketMax) + '%' }" />
							</div>
							<span class="w-10 text-right tabular-nums">{{ bucket.value }}</span>
						</div>
					</div>
				</div>

				<div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
					<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
						<FontAwesomeIcon icon="fal fa-users" class="text-gray-400" fixed-width aria-hidden="true" />
						{{ ctrans("Pickers and packers") }}
						<span class="text-xs font-normal text-gray-400">{{ ctrans("Team view · named view after HR approval") }}</span>
					</h3>
					<div class="mt-3 grid grid-cols-2 gap-4">
						<div v-for="team in [{ key: 'pickers', label: ctrans('Pickers'), unit: ctrans('lines') }, { key: 'packers', label: ctrans('Packers'), unit: ctrans('orders') }]" :key="team.key">
							<div class="text-xs font-medium text-gray-500">{{ team.label }}</div>
							<div class="text-xl font-semibold tabular-nums">
								{{ data.people[team.key].active }}
								<span class="text-xs font-normal text-gray-500">{{ ctrans("active now") }}</span>
							</div>
							<div v-if="data.people[team.key].idle" class="text-xs text-amber-700">{{ ctrans(":count idle over :minutes min", { count: data.people[team.key].idle, minutes: thresholds.idle_minutes }) }}</div>
							<dl class="mt-2 space-y-0.5 text-xs text-gray-600">
								<div class="flex justify-between"><dt>{{ ctrans("Today") }}</dt><dd class="tabular-nums">{{ locale.number(data.people[team.key].today) }} {{ team.unit }}</dd></div>
								<div class="flex justify-between">
									<dt v-tooltip="ctrans('Per person per active hour today: from each person\'s first to last scan')">{{ ctrans("Per person per hour") }}</dt>
									<dd class="tabular-nums">{{ data.people[team.key].per_person_hour ?? "—" }}</dd>
								</div>
								<div class="flex justify-between"><dt>{{ ctrans("Last hour") }}</dt><dd class="tabular-nums">{{ data.people[team.key].last_hour }} · {{ ctrans(":count people", { count: data.people[team.key].last_hour_people }) }}</dd></div>
								<div v-if="team.key === 'pickers'" class="flex justify-between">
									<dt v-tooltip="ctrans('Lines marked as not picked today')">{{ ctrans("Short picks") }}</dt>
									<dd class="tabular-nums">{{ data.people.pickers.short }}</dd>
								</div>
							</dl>
						</div>
					</div>
				</div>
			</section>

			<section class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
				<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
					<FontAwesomeIcon icon="fal fa-truck-container" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ ctrans("Goods in") }}
					<span class="text-xs font-normal text-gray-400">{{ ctrans("Put away first what releases the most orders") }}</span>
				</h3>
				<dl class="mt-3 grid grid-cols-2 sm:grid-cols-6 gap-3">
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("On the way") }}</dt>
						<dd class="text-xl font-semibold tabular-nums">{{ data.goods_in.counts.on_the_way }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("Overdue") }}</dt>
						<dd class="text-xl font-semibold tabular-nums" :class="data.goods_in.overdue ? 'text-amber-700' : ''">{{ data.goods_in.overdue }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("To book in") }}</dt>
						<dd class="text-xl font-semibold tabular-nums">{{ data.goods_in.counts.to_book_in }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("Booking in") }}</dt>
						<dd class="text-xl font-semibold tabular-nums">{{ data.goods_in.counts.booking_in }}</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500" v-tooltip="ctrans('From arrival to booked in, deliveries booked in over the last 90 days. Median, then 90th percentile.')">{{ ctrans("Dock to stock") }}</dt>
						<dd class="text-xl font-semibold tabular-nums">
							{{ seconds(data.goods_in.dock_to_stock.median_seconds) }}
							<span class="text-xs font-normal text-gray-500">/ {{ seconds(data.goods_in.dock_to_stock.p90_seconds) }}</span>
						</dd>
					</div>
					<div>
						<dt class="text-xs text-gray-500">{{ ctrans("Without ETA / PO") }}</dt>
						<dd class="text-xl font-semibold tabular-nums">{{ data.goods_in.counts.without_eta }} / {{ data.goods_in.counts.without_po }}</dd>
					</div>
				</dl>
				<div class="mt-3 overflow-x-auto">
					<table class="w-full text-sm">
						<thead>
							<tr class="border-b border-gray-200 text-left text-xs text-gray-500">
								<th class="py-1.5 pr-3 font-medium">{{ ctrans("ETA") }}</th>
								<th class="py-1.5 pr-3 font-medium">{{ ctrans("Delivery") }}</th>
								<th v-if="!isSingleWarehouse" class="py-1.5 pr-3 font-medium">{{ ctrans("Warehouse") }}</th>
								<th class="py-1.5 pr-3 text-right font-medium">{{ ctrans("Size") }}</th>
								<th class="py-1.5 pr-3 text-right font-medium" v-tooltip="ctrans('Open delivery notes waiting for stock this delivery carries')">{{ ctrans("Releases") }}</th>
								<th class="py-1.5 font-medium">{{ ctrans("Status") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="delivery in data.goods_in.deliveries" :key="delivery.id">
								<td class="py-1.5 pr-3 whitespace-nowrap" :class="delivery.is_overdue ? 'text-amber-700 font-medium' : 'text-gray-600'">
									<template v-if="delivery.is_overdue">{{ ctrans("Overdue :days d", { days: daysLate(delivery.eta) ?? 0 }) }}</template>
									<template v-else>{{ delivery.eta ? new Date(delivery.eta).toLocaleDateString() : "—" }}</template>
								</td>
								<td class="py-1.5 pr-3">
									<Link :href="route(delivery.route.name, delivery.route.parameters)" class="text-gray-800 hover:text-blue-600 hover:underline">{{ delivery.supplier }}</Link>
									<span class="ml-1 text-xs text-gray-400">{{ delivery.reference }}</span>
								</td>
								<td v-if="!isSingleWarehouse" class="py-1.5 pr-3 text-gray-500">{{ delivery.warehouse }}</td>
								<td class="py-1.5 pr-3 text-right tabular-nums text-gray-600 whitespace-nowrap">
									{{ ctrans(":count lines", { count: delivery.lines }) }}<span v-if="delivery.cbm"> · {{ delivery.cbm }} m³</span>
								</td>
								<td class="py-1.5 pr-3 text-right tabular-nums whitespace-nowrap">
									<span v-if="delivery.releases">{{ ctrans(":count orders", { count: delivery.releases }) }}</span>
									<span v-else class="text-gray-300">0</span>
									<span v-if="data.can_see_value && delivery.releases_value" class="ml-1 text-xs text-gray-500">{{ money(delivery.releases_value, delivery.currency_code) }}</span>
								</td>
								<td class="py-1.5 text-gray-600 whitespace-nowrap">
									{{ stateLabel(delivery.state) }}
									<span v-if="delivery.received_at && ['received', 'checked'].includes(delivery.state)" class="text-xs text-gray-400">· {{ age(delivery.received_at) }}</span>
								</td>
							</tr>
							<tr v-if="!data.goods_in.deliveries.length">
								<td colspan="6" class="py-3 text-center text-gray-400">{{ ctrans("No deliveries on the way") }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
				<div class="lg:col-span-2 rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
					<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
						<FontAwesomeIcon icon="fal fa-inventory" class="text-gray-400" fixed-width aria-hidden="true" />
						{{ ctrans("Stock and locations") }}
					</h3>
					<dl class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("Empty locations") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">
								<component
									:is="singleRoute(data.stock.empty_locations) ? Link : 'span'"
									:href="singleRoute(data.stock.empty_locations) ? route(singleRoute(data.stock.empty_locations)!.name, singleRoute(data.stock.empty_locations)!.parameters) : undefined"
									:class="singleRoute(data.stock.empty_locations) ? 'hover:underline' : ''"
								>
									{{ locale.number(data.stock.empty_locations.count) }}
								</component>
								<span class="text-xs font-normal text-gray-400">/ {{ locale.number(data.stock.locations) }}</span>
							</dd>
						</div>
						<div>
							<dt class="text-xs text-gray-500" v-tooltip="ctrans('Locations with stock not counted in the last 90 days')">{{ ctrans("Not counted 90 days") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">{{ locale.number(data.stock.not_audited_90d) }}</dd>
						</div>
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("Negative stock") }}</dt>
							<dd class="text-xl font-semibold tabular-nums" :class="data.stock.negative ? 'text-red-600' : ''">{{ data.stock.negative }}</dd>
						</div>
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("Replenishments due") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">{{ locale.number(data.stock.replenishment) }}</dd>
						</div>
					</dl>
					<div class="mt-4 text-xs font-medium text-gray-500">{{ ctrans("Out of stock with open orders") }}</div>
					<div class="overflow-x-auto">
						<table class="mt-1 w-full text-sm">
							<thead>
								<tr class="border-b border-gray-200 text-left text-xs text-gray-500">
									<th class="py-1.5 pr-3 font-medium">{{ ctrans("SKO") }}</th>
									<th class="py-1.5 pr-3 text-right font-medium">{{ ctrans("Delivery notes waiting") }}</th>
									<th v-if="data.can_see_value" class="py-1.5 pr-3 text-right font-medium">{{ ctrans("Value") }}</th>
									<th class="py-1.5 font-medium">{{ ctrans("Inbound") }}</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100">
								<tr v-for="orgStock in data.stock.out_of_stock" :key="orgStock.warehouse + orgStock.code">
									<td class="py-1.5 pr-3">
										<Link :href="route(orgStock.route.name, orgStock.route.parameters)" class="font-medium text-gray-800 hover:text-blue-600 hover:underline">{{ orgStock.code }}</Link>
										<span class="ml-1 text-xs text-gray-400">{{ orgStock.name }}</span>
									</td>
									<td class="py-1.5 pr-3 text-right tabular-nums">{{ orgStock.delivery_notes }}</td>
									<td v-if="data.can_see_value" class="py-1.5 pr-3 text-right tabular-nums">{{ money(orgStock.org_amount, orgStock.currency_code) }}</td>
									<td class="py-1.5 text-gray-600 whitespace-nowrap">
										<template v-if="orgStock.arrived">{{ ctrans("Arrived – to book in") }}</template>
										<template v-else-if="orgStock.inbound">{{ orgStock.inbound_eta ? new Date(orgStock.inbound_eta).toLocaleDateString() : ctrans("No ETA") }}</template>
										<span v-else class="text-gray-400">{{ ctrans("No inbound") }}</span>
									</td>
								</tr>
								<tr v-if="!data.stock.out_of_stock.length">
									<td colspan="4" class="py-3 text-center text-gray-400">{{ ctrans("Nothing out of stock on open orders") }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
					<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
						<FontAwesomeIcon icon="fal fa-undo-alt" class="text-gray-400" fixed-width aria-hidden="true" />
						{{ ctrans("Returns") }}
					</h3>
					<dl class="mt-3 grid grid-cols-2 gap-3">
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("To process") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">
								<component
									:is="singleRoute(data.returns.to_process) ? Link : 'span'"
									:href="singleRoute(data.returns.to_process) ? route(singleRoute(data.returns.to_process)!.name, singleRoute(data.returns.to_process)!.parameters) : undefined"
									:class="singleRoute(data.returns.to_process) ? 'hover:underline' : ''"
								>
									{{ data.returns.to_process.count }}
								</component>
							</dd>
							<dd v-if="data.returns.to_process.oldest" class="text-xs text-gray-500">{{ ctrans("Oldest :age", { age: age(data.returns.to_process.oldest) }) }}</dd>
						</div>
						<div>
							<dt class="text-xs text-gray-500">{{ ctrans("Customer returns received") }}</dt>
							<dd class="text-xl font-semibold tabular-nums">{{ data.returns.received.count }}</dd>
							<dd class="text-xs text-gray-500">{{ ctrans(":count expected", { count: data.returns.expected }) }}</dd>
						</div>
					</dl>
					<div class="mt-3 text-xs font-medium text-gray-500">{{ ctrans("Processed this month (units)") }}</div>
					<div class="mt-1 flex gap-x-4 text-sm tabular-nums">
						<span>{{ ctrans("Restocked") }} {{ data.returns.outcomes.restocked }}</span>
						<span>{{ ctrans("Damaged") }} {{ data.returns.outcomes.damaged }}</span>
						<span>{{ ctrans("Not returned") }} {{ data.returns.outcomes.not_returned }}</span>
					</div>
					<div v-if="data.returns.reasons.length" class="mt-3 text-xs font-medium text-gray-500">{{ ctrans("Top return reasons this month") }}</div>
					<ul class="mt-1 space-y-0.5 text-sm">
						<li v-for="reason in data.returns.reasons" :key="reason.reason" class="flex justify-between gap-x-2">
							<span class="truncate text-gray-700">{{ reason.reason }}</span>
							<span class="tabular-nums text-gray-500">{{ reason.count }}</span>
						</li>
					</ul>
				</div>
			</section>

			<section v-if="data.sales.rows.length" class="rounded-lg bg-white shadow ring-1 ring-gray-200">
				<button type="button" class="flex w-full items-center gap-x-1.5 p-4 text-left text-sm font-semibold text-gray-700" :aria-expanded="showSales" @click="showSales = !showSales">
					<FontAwesomeIcon icon="fal fa-chart-line" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ ctrans("Sales by organisation") }}
					<span class="text-xs font-normal text-gray-400">{{ ctrans("Workload signal") }}<template v-if="!data.can_see_value"> · {{ ctrans("order counts only") }}</template></span>
					<FontAwesomeIcon icon="fal fa-chevron-down" class="ml-auto text-gray-400 transition-transform" :class="showSales ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
				</button>
				<div v-if="showSales" class="overflow-x-auto px-4 pb-4">
					<table class="w-full text-sm">
						<thead>
							<tr class="border-b border-gray-200 text-left text-xs text-gray-500">
								<th class="py-1.5 pr-3 font-medium">{{ ctrans("Organisation") }}</th>
								<th class="py-1.5 pr-3 text-right font-medium" v-tooltip="ctrans('Orders submitted today, local time, last 7 days in the line')">{{ ctrans("Orders in today") }}</th>
								<th class="py-1.5 pr-3 text-right font-medium" v-tooltip="ctrans('Against the same weekday last year, up to the same time')">{{ ctrans("vs same weekday LY") }}</th>
								<template v-if="data.can_see_value">
									<th class="py-1.5 pr-3 text-right font-medium" v-tooltip="ctrans('Net value of the orders submitted today')">{{ ctrans("Value in today") }}</th>
									<th class="py-1.5 pr-3 text-right font-medium" v-tooltip="ctrans('Net value of the orders in the warehouse, not dispatched yet')">{{ ctrans("In warehouse pipeline") }}</th>
									<th class="py-1.5 pr-3 text-right font-medium" v-tooltip="ctrans('Invoiced this month')">{{ ctrans("Month to date") }}</th>
									<th class="py-1.5 text-right font-medium">{{ ctrans("% of month target") }}</th>
								</template>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 tabular-nums">
							<tr v-for="row in [...data.sales.rows, ...(data.sales.total ? [data.sales.total] : [])]" :key="row.name" :class="row === data.sales.total ? 'font-semibold' : ''">
								<td class="py-1.5 pr-3">
									<Link v-if="row.route" :href="route(row.route.name, row.route.parameters)" class="text-gray-800 hover:text-blue-600 hover:underline">{{ row.name }}</Link>
									<span v-else>{{ row.name }} <span class="text-xs font-normal text-gray-400">({{ row.currency_code }})</span></span>
								</td>
								<td class="py-1.5 pr-3 text-right" :class="row.is_busy ? 'text-red-600' : ''" v-tooltip="row.is_busy ? ctrans('Over 25% above the average of the last four same weekdays (:average)', { average: row.weekday_average }) : null">
									<span class="mr-2 inline-flex h-4 items-end gap-px align-middle" aria-hidden="true">
										<span v-for="(day, index) in row.sparkline" :key="index" class="w-1 bg-gray-300" :style="{ height: Math.max(2, (day / Math.max(1, ...row.sparkline)) * 16) + 'px' }" />
									</span>
									{{ row.orders_today }}
								</td>
								<td class="py-1.5 pr-3 text-right text-gray-600">{{ row.change_percent === null ? "—" : (row.change_percent > 0 ? "+" : "") + row.change_percent + "%" }}</td>
								<template v-if="data.can_see_value">
									<td class="py-1.5 pr-3 text-right">{{ money(row.value_today, row.currency_code) }}</td>
									<td class="py-1.5 pr-3 text-right">{{ money(row.pipeline, row.currency_code) ?? "—" }}</td>
									<td class="py-1.5 pr-3 text-right">{{ money(row.month_to_date, row.currency_code) ?? "—" }}</td>
									<td class="py-1.5 text-right">{{ row.target_percent === null ? "—" : row.target_percent + "%" }}</td>
								</template>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</template>
	</div>
</template>
