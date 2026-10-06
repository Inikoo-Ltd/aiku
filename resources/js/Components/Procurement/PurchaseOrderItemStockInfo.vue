<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"

interface QuarterUsage {
	period: string
	sales: number
	days_out_of_stock: number
}

const props = defineProps<{
	item: any
	isPartner?: boolean
	typedSkos?: number | null
	isOrderClosed?: boolean
}>()

const emit = defineEmits<{
	(e: "suggest", skos: number): void
}>()

const locale = useLocaleStore()

const MAX_DAYS = 730

const pack = computed(() => Number(props.item.units_per_pack) || 1)
const cover = computed(() => props.item.stock_cover ?? null)
const dailyUsage = computed(() => Number(cover.value?.daily_usage) || 0)
const leadDays = computed(() => Number(cover.value?.lead_time_days) || 14)
const overstockDays = computed(() => Number(cover.value?.overstock_days) || 120)
const stock = computed(() => Math.max(0, Number(props.item.stock_in_locations) || 0))

const comingDeliveries = computed<any[]>(() => props.item.stock_deliveries?.coming ?? [])
const otherOrders = computed<any[]>(() => props.item.other_open_purchase_orders ?? [])
const incoming = computed(
	() =>
		comingDeliveries.value.reduce((total, delivery) => total + Number(delivery.quantity || 0), 0) / pack.value +
		otherOrders.value.reduce((total, order) => total + Number(order.quantity_ordered || 0), 0) / pack.value
)

const thisOrder = computed(() => {
	if (props.isOrderClosed) {
		return 0
	}

	return props.typedSkos !== undefined && props.typedSkos !== null
		? Number(props.typedSkos) || 0
		: (Number(props.item.quantity_ordered) || 0) / pack.value
})

const weeklyForecast = computed<number[]>(() => cover.value?.weekly_forecast ?? [])

function coverDays(amount: number): number {
	let left = amount
	let days = 0
	for (const week of weeklyForecast.value) {
		if (week <= 0) {
			days += 7
			continue
		}
		if (left < week) {
			return days + (left / week) * 7
		}
		left -= week
		days += 7
	}
	if (dailyUsage.value <= 0) {
		return MAX_DAYS
	}

	return Math.min(MAX_DAYS, days + left / dailyUsage.value)
}

function demandOver(targetDays: number): number {
	let demand = 0
	let days = 0
	for (const week of weeklyForecast.value) {
		if (days + 7 > targetDays) {
			return demand + (week * (targetDays - days)) / 7
		}
		demand += week
		days += 7
	}

	return demand + dailyUsage.value * Math.max(0, targetDays - days)
}

const hasHistory = computed(() => dailyUsage.value > 0 || weeklyForecast.value.some((week) => week > 0))

const targetDays = computed(() =>
	Math.min(Math.max(leadDays.value + 30, 2 * leadDays.value), Math.max(leadDays.value + 14, overstockDays.value - 7))
)

const cartonSkos = computed(() => (props.isPartner ? 1 : Math.max(1, (Number(props.item.units_per_carton) || 1) / pack.value)))

const suggestion = computed(() => {
	if (!hasHistory.value) {
		return null
	}
	const need = demandOver(targetDays.value) - stock.value - incoming.value

	return need <= 0 ? 0 : Math.ceil(need / cartonSkos.value) * cartonSkos.value
})

const daysNow = computed(() => coverDays(stock.value + incoming.value))
const daysAfter = computed(() => coverDays(stock.value + incoming.value + thisOrder.value))

const suggestedQuantityHint = computed(() => {
	if (suggestion.value === null) {
		return ""
	}
	if (suggestion.value === 0) {
		return ctrans("Stock in hand and coming is already enough, so no order is needed.")
	}

	return ctrans("Suggested quantity: :quantity SKOs.", { quantity: formatNumber(suggestion.value) })
})

const verdict = computed(() => {
	if (!hasHistory.value) {
		return {
			label: ctrans("No sales history"),
			class: "text-gray-400",
			tooltip: ctrans("This product has no recent sales, so there is no way to tell how long the stock will last"),
		}
	}
	const days = thisOrder.value > 0 ? daysAfter.value : daysNow.value
	const times = { time: weeksLabel(days), lead: weeksLabel(leadDays.value), overstock: weeksLabel(overstockDays.value) }
	if (days < leadDays.value) {
		return thisOrder.value > 0
			? {
					label: ctrans("Understock"),
					class: "text-red-600",
					tooltip: [
						ctrans("Understock: with this order the stock lasts only :time, but a new order takes :lead to arrive.", times),
						ctrans("The stock will run out before the next delivery, so increase the quantity."),
						suggestedQuantityHint.value,
					]
						.filter(Boolean)
						.join(" "),
				}
			: {
					label: ctrans("Order now"),
					class: "text-red-600",
					tooltip: ctrans("Stock lasts :time, less than the :lead lead time. Order now or it will run out before a new order arrives", times),
				}
	}
	if (days > overstockDays.value) {
		return thisOrder.value > 0
			? {
					label: ctrans("Overstock"),
					class: "text-amber-600",
					tooltip: [
						ctrans("Overstock: with this order the stock lasts :time, beyond the :overstock limit.", times),
						ctrans("The extra stock ties up money and warehouse space, so reduce the quantity."),
						suggestedQuantityHint.value,
					]
						.filter(Boolean)
						.join(" "),
				}
			: {
					label: ctrans("Not needed"),
					class: "text-gray-400",
					tooltip: ctrans("No need to order this for now, the stock is more than enough (lasts :time, beyond the :overstock overstock limit)", times),
				}
	}

	return {
		label: ctrans("OK"),
		class: "text-green-600",
		tooltip:
			thisOrder.value > 0
				? ctrans("With this order the stock lasts :time, between the :lead lead time and the :overstock overstock limit. The quantity is right", times)
				: ctrans("Stock lasts :time, between the :lead lead time and the :overstock overstock limit. No need to order yet", times),
	}
})

function weeksLabel(days: number): string {
	if (days >= MAX_DAYS) {
		return ctrans("2+ years")
	}
	if (days < 14) {
		const count = Math.max(0, Math.round(days))
		return count === 1 ? ctrans("1 day") : ctrans(":count days", { count: String(count) })
	}
	if (days < 120) {
		return ctrans(":count wk", { count: String(Math.round(days / 7)) })
	}

	return ctrans(":count months", { count: String(Math.round(days / 30)) })
}

const scaleDays = computed(() => Math.max(overstockDays.value * 1.25, leadDays.value * 1.5))
const percent = (days: number) => Math.min(100, (Math.max(0, days) / scaleDays.value) * 100)

const currentCoverTooltip = computed(() =>
	incoming.value > 0
		? ctrans("In stock and coming: lasts :time", { time: weeksLabel(daysNow.value) })
		: ctrans("In stock: lasts :time", { time: weeksLabel(daysNow.value) })
)

const currentCoverZones = computed(() =>
	[
		{
			key: "before-lead-time",
			from: 0,
			to: leadDays.value,
			explanation: ctrans("First :time: stock needed while waiting for a new order to arrive. If the bar ends here, order now", {
				time: weeksLabel(leadDays.value),
			}),
		},
		{
			key: "safe-range",
			from: leadDays.value,
			to: overstockDays.value,
			explanation: ctrans("From :from to :to: safe range. If the bar ends here (between red line and yellow line), there is enough stock for now", {
				from: weeksLabel(leadDays.value),
				to: weeksLabel(overstockDays.value),
			}),
		},
		{
			key: "overstock",
			from: overstockDays.value,
			to: Infinity,
			explanation: ctrans("Beyond :time: overstock. This stock is more than needed", { time: weeksLabel(overstockDays.value) }),
		},
	]
		.map((zone) => ({
			key: zone.key,
			left: percent(zone.from),
			width: Math.max(0, percent(Math.min(daysNow.value, zone.to)) - percent(zone.from)),
			tooltip: `${currentCoverTooltip.value}. ${zone.explanation}`,
		}))
		.filter((zone) => zone.width > 0)
)

const thisOrderSegment = computed(() => {
	const added = { added: weeksLabel(Math.max(0, daysAfter.value - daysNow.value)), total: weeksLabel(daysAfter.value) }
	if (daysAfter.value > overstockDays.value) {
		return {
			class: "bg-amber-300 hover:bg-amber-400",
			tooltip: ctrans("This order adds :added (lasts :total), past the overstock limit", added),
		}
	}
	if (daysAfter.value < leadDays.value) {
		return {
			class: "bg-red-300 hover:bg-red-400",
			tooltip: ctrans("This order adds :added (lasts :total), still runs out before the lead time", added),
		}
	}

	return {
		class: "bg-green-300 hover:bg-green-400",
		tooltip: ctrans("This order adds :added (lasts :total), within the safe range", added),
	}
})

const leadTimeMarkerTooltip = computed(() =>
	ctrans("Lead time: :time. Stock should last at least this long, until a new order arrives", { time: weeksLabel(leadDays.value) })
)

const overstockMarkerTooltip = computed(() =>
	ctrans("Overstock limit: :time. Stock lasting longer than this is too much", { time: weeksLabel(overstockDays.value) })
)

const formatNumber = (value: number) => locale.number(Math.round(value))

const currentQuarter = (() => {
	const now = new Date()
	return `${now.getFullYear()}Q${Math.floor(now.getMonth() / 3) + 1}`
})()

const quarters = computed<QuarterUsage[]>(() => props.item.quarterly_usage ?? [])
const quarterMax = computed(() => Math.max(1, ...quarters.value.map((record) => Number(record.sales) || 0)))

const lastQuarters = computed(() => quarters.value.filter((record) => record.period !== currentQuarter).slice(-4))
const salesPerQuarter = computed(() =>
	lastQuarters.value.length ? lastQuarters.value.reduce((total, record) => total + (Number(record.sales) || 0), 0) / lastQuarters.value.length : null
)
const daysOutOfStock = computed(() => lastQuarters.value.reduce((total, record) => total + (Number(record.days_out_of_stock) || 0), 0))

const salesPerQuarterTooltip = computed(() =>
	ctrans("Average sales per quarter (3 months): about :quantity SKOs, based on the last :count completed quarters", {
		quantity: formatNumber(salesPerQuarter.value ?? 0),
		count: String(lastQuarters.value.length),
	})
)

const daysOutOfStockTooltip = computed(() =>
	ctrans(
		"This product was out of stock for :days days in the last :count completed quarters. Sales were lost on those days, so real demand is likely higher than the average shown",
		{ days: String(daysOutOfStock.value), count: String(lastQuarters.value.length) }
	)
)

const quarterMonths = ["Jan–Mar", "Apr–Jun", "Jul–Sep", "Oct–Dec"]

const quarterLabel = (period: string) => {
	const [year, quarter] = period.split("Q")
	const months = quarterMonths[Number(quarter) - 1]

	return months ? `${months} ${year}` : period
}

const quarterTooltip = (record: QuarterUsage) =>
	[
		record.period === currentQuarter
			? ctrans("Sold in :quarter (this quarter, so far): :quantity SKOs.", { quarter: quarterLabel(record.period), quantity: formatNumber(record.sales) })
			: ctrans("Sold in :quarter: :quantity SKOs.", { quarter: quarterLabel(record.period), quantity: formatNumber(record.sales) }),
		record.days_out_of_stock ? ctrans("Out of stock for :days days (red dot).", { days: String(record.days_out_of_stock) }) : "",
	]
		.filter(Boolean)
		.join(" ")

const quarterChartTooltip = ctrans("Sales per quarter, oldest on the left. The lighter bar is the current quarter. A red dot means the product ran out of stock that quarter")

const stockTooltip = computed(() => {
	const lines: string[] = []
	if (cover.value?.out_of_stock_at && dailyUsage.value > 0) {
		lines.push(ctrans("Runs out around :date", { date: useFormatTime(cover.value.out_of_stock_at) }))
	}
	if (cover.value?.days_worst_case != null) {
		lines.push(ctrans("If sales run high: :time", { time: weeksLabel(Number(cover.value.days_worst_case)) }))
	}
	lines.push(ctrans("Lead time: :time", { time: weeksLabel(leadDays.value) }))
	const lastReceived = props.item.stock_deliveries?.last_received
	if (lastReceived) {
		lines.push(
			ctrans("Last received: :reference, :date (:quantity SKOs)", {
				reference: lastReceived.reference,
				date: useFormatTime(lastReceived.received_at),
				quantity: formatNumber(Number(lastReceived.quantity) / pack.value),
			})
		)
	}

	return lines.join("\n")
})

function stockDeliveryRoute(slug: string) {
	return route("grp.org.procurement.stock_deliveries.show", [route().params.organisation, slug])
}

function purchaseOrderRoute(slug: string) {
	return route("grp.org.procurement.purchase_orders.show", [route().params.organisation, slug])
}
</script>

<template>
	<div v-if="item.stock_in_locations !== undefined && item.stock_in_locations !== null" class="mt-1 max-w-md space-y-1 text-xs">
		<div class="flex flex-wrap items-center gap-x-2 text-gray-600">
			<span v-tooltip="stockTooltip" class="cursor-help">
				<span v-if="isOrderClosed" class="text-gray-500">{{ ctrans("Stock today") }}: </span>
				<span class="font-semibold text-gray-800">{{ formatNumber(stock) }}</span> {{ ctrans("in stock") }}
				<template v-if="incoming > 0">
					+ <span class="font-semibold text-gray-800">{{ formatNumber(incoming) }}</span> {{ ctrans("coming") }}
				</template>
				<template v-if="hasHistory">
					· {{ ctrans("lasts") }} <span class="font-semibold text-gray-800">{{ weeksLabel(daysNow) }}</span>
				</template>
			</span>
			<span v-if="!isOrderClosed && !(thisOrder > 0 && hasHistory)" v-tooltip="verdict.tooltip" class="cursor-help font-semibold uppercase tracking-wide" :class="verdict.class">{{ verdict.label }}</span>
		</div>

		<div v-if="hasHistory" class="flex flex-wrap items-center gap-x-3 gap-y-1">
			<div class="relative h-2 w-48 shrink-0 overflow-hidden rounded-full bg-gray-100 transition-[height] duration-150 hover:h-3 border border-gray-400">
				<div
					v-for="zone in currentCoverZones"
					:key="zone.key"
					v-tooltip="zone.tooltip"
					class="absolute inset-y-0 cursor-help bg-gray-400 transition-colors hover:bg-gray-500"
					:style="{ left: zone.left + '%', width: zone.width + '%' }" />
				<div
					v-if="thisOrder > 0"
					v-tooltip="thisOrderSegment.tooltip"
					class="absolute inset-y-0 cursor-help transition-colors"
					:class="thisOrderSegment.class"
					:style="{ left: percent(daysNow) + '%', width: Math.max(0, percent(daysAfter) - percent(daysNow)) + '%' }" />
				<div
					v-tooltip="leadTimeMarkerTooltip"
					class="group absolute inset-y-0 z-10 flex w-4 -translate-x-1/2 cursor-help justify-center"
					:style="{ left: percent(leadDays) + '%' }">
					<div class="h-full w-1 bg-red-500 transition-all group-hover:w-1 group-hover:bg-red-600" />
				</div>
				<div
					v-tooltip="overstockMarkerTooltip"
					class="group absolute inset-y-0 z-10 flex w-4 -translate-x-1/2 cursor-help justify-center"
					:style="{ left: percent(overstockDays) + '%' }">
					<div class="h-full w-1 bg-amber-500 transition-all group-hover:w-1 group-hover:bg-amber-600" />
				</div>
			</div>

			<span v-if="salesPerQuarter !== null" class="shrink-0 text-gray-500">
				<span v-tooltip="salesPerQuarterTooltip" class="cursor-help">~{{ formatNumber(salesPerQuarter) }}/{{ ctrans("qtr") }}</span>
				<span v-if="daysOutOfStock" v-tooltip="daysOutOfStockTooltip" class="cursor-help text-red-600">
					· {{ ctrans(":days d out of stock", { days: String(daysOutOfStock) }) }}
				</span>
			</span>

			<div v-if="quarters.length" class="flex items-end">
				<div
					v-for="record in quarters"
					:key="record.period"
					v-tooltip="quarterTooltip(record)"
					class="group flex h-6 w-3 cursor-help items-end justify-center rounded-sm px-0.5 pt-1.5 hover:bg-gray-200">
					<div
						class="relative w-full rounded-sm transition-colors"
						:class="record.period === currentQuarter ? 'bg-gray-300 group-hover:bg-gray-400' : 'bg-gray-500 group-hover:bg-gray-700'"
						:style="{ height: Math.max(8, (Number(record.sales) / quarterMax) * 100) + '%' }">
						<span v-if="record.days_out_of_stock" class="absolute -top-1.5 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-red-500" />
					</div>
				</div>
				<FontAwesomeIcon v-tooltip="quarterChartTooltip" :icon="faInfoCircle" class="ml-0.5 cursor-help self-start text-[10px] text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true" />
			</div>

			<button
				v-if="!isOrderClosed && suggestion !== null && suggestion > 0"
				type="button"
				v-tooltip="ctrans('Enough for :time after it arrives, counting what is in stock and coming. Click to set quantity order to :quantityOrder.', { time: weeksLabel(targetDays - leadDays), quantityOrder: formatNumber(suggestion) })"
				class="shrink-0 rounded border px-1.5 py-0.5 font-medium"
				:class="Math.round(thisOrder) === suggestion ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
				@click="emit('suggest', suggestion)">
				{{ ctrans("Suggest") }} {{ formatNumber(suggestion) }}
			</button>
		</div>

		<div v-if="thisOrder > 0 && hasHistory" class="flex items-center gap-x-2 text-gray-500">
			<span>{{ ctrans("With this order: lasts :time", { time: weeksLabel(daysAfter) }) }}</span>
			<span v-tooltip="verdict.tooltip" class="cursor-help font-semibold uppercase tracking-wide" :class="verdict.class">{{ verdict.label }}</span>
		</div>

		<div v-if="comingDeliveries.length || otherOrders.length" class="flex flex-wrap gap-x-2 text-gray-500">
			<span v-for="delivery in comingDeliveries" :key="delivery.slug">
				<Link :href="stockDeliveryRoute(delivery.slug)" class="primaryLink">{{ delivery.reference }}</Link>
				({{ formatNumber(Number(delivery.quantity) / pack) }})
			</span>
			<span v-for="order in otherOrders" :key="order.slug" class="text-amber-700">
				{{ ctrans("also on") }}
				<Link :href="purchaseOrderRoute(order.slug)" class="primaryLink">{{ order.reference }}</Link>
				({{ formatNumber(Number(order.quantity_ordered) / pack) }})
			</span>
		</div>
	</div>
</template>
