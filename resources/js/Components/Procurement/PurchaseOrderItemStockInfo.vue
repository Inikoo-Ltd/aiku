<script setup lang="ts">
import { computed, nextTick, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import Popover from "primevue/popover"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { usePurchaseOrderStockCover } from "@/Composables/usePurchaseOrderStockCover"

interface QuarterUsage {
	period: string
	sales: number
	days_out_of_stock: number
	top_customer?: { id: number; name: string; sales: number } | null
}

const props = defineProps<{
	item: any
	isPartner?: boolean
	typedSkosById?: Record<number, number>
	isOrderClosed?: boolean
	isOrderLocked?: boolean
}>()

const locale = useLocaleStore()
const routeParams = route().params

const { pack, cover, dailyUsage, leadDays, overstockDays, stock, comingDeliveries, otherOrders, incoming, coverDays, hasHistory, suggestion, weeksLabel } =
	usePurchaseOrderStockCover(
		() => props.item,
		() => props.isPartner
	)

const showPastDeliveries = ref(false)

const recentDeliveries = computed<any[]>(() => props.item.stock_deliveries?.recent_received ?? [])

const thisOrder = computed(() => {
	if (props.isOrderClosed) {
		return 0
	}

	const typedSkos = props.typedSkosById?.[props.item.id]

	return typedSkos !== undefined && typedSkos !== null
		? Number(typedSkos) || 0
		: (Number(props.item.quantity_ordered) || 0) / pack.value
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
const isCurrentQuarter = (record: QuarterUsage) => record.period === currentQuarter
const isSingleCustomerQuarter = (record: QuarterUsage) => customerShare(record) >= 50

const customerShare = (record: QuarterUsage) =>
	record.top_customer && Number(record.sales) > 0 ? Math.round((record.top_customer.sales / Number(record.sales)) * 100) : 0

const dominantCustomer = computed(() => {
	const totalSales = lastQuarters.value.reduce((total, record) => total + (Number(record.sales) || 0), 0)
	if (totalSales <= 0) {
		return null
	}
	const salesByCustomer = new Map<number, { name: string; sales: number }>()
	lastQuarters.value.forEach((record) => {
		if (!record.top_customer) {
			return
		}
		const entry = salesByCustomer.get(record.top_customer.id) ?? { name: record.top_customer.name, sales: 0 }
		entry.sales += record.top_customer.sales
		salesByCustomer.set(record.top_customer.id, entry)
	})
	const top = [...salesByCustomer.values()].sort((a, b) => b.sales - a.sales)[0]
	const share = top ? Math.round((top.sales / totalSales) * 100) : 0

	return share >= 50 ? { name: top.name, share } : null
})

const daysOutOfStock = computed(() => lastQuarters.value.reduce((total, record) => total + (Number(record.days_out_of_stock) || 0), 0))

const compactNumber = new Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 1 })
const formatCompact = (value: number) => compactNumber.format(Math.round(Number(value) || 0))

const quarterShortLabel = (period: string) => {
	const [year, quarter] = period.split("Q")

	return quarter ? `Q${quarter} '${year.slice(-2)}` : period
}

const quarterBarHeight = (record: QuarterUsage) => Math.max(4, (Number(record.sales) / quarterMax.value) * 100)

const quarterChartPopover = ref()

const isQuarterChartMounted = ref(false)
const showQuarterChart = async (event: MouseEvent) => {
	const target = event.currentTarget
	isQuarterChartMounted.value = true
	await nextTick()
	quarterChartPopover.value?.show(event, target)
}
const hideQuarterChart = () => quarterChartPopover.value?.hide()

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
	return route("grp.org.procurement.stock_deliveries.show", [routeParams.organisation, slug])
}

function purchaseOrderRoute(slug: string) {
	return route("grp.org.procurement.purchase_orders.show", [routeParams.organisation, slug])
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
			<span v-if="!isOrderClosed && !isOrderLocked && !(thisOrder > 0 && hasHistory)" v-tooltip="verdict.tooltip" class="cursor-help font-semibold uppercase tracking-wide" :class="verdict.class">{{ verdict.label }}</span>
		</div>

		<div v-if="hasHistory && !isOrderLocked" class="flex flex-wrap items-center gap-x-3 gap-y-1">
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


			<div
				v-if="quarters.length"
				class="flex cursor-help items-end gap-0.5 rounded-sm px-0.5 hover:bg-gray-200"
				@mouseenter="showQuarterChart"
				@mouseleave="hideQuarterChart">
				<div v-for="record in quarters" :key="record.period" class="flex w-4 flex-col items-center">
					<span class="text-[8px] leading-none text-gray-500">{{ formatCompact(record.sales) }}</span>
					<div class="relative mt-0.5 flex h-5 w-2.5 items-end">
						<div
							class="flex w-full flex-col overflow-hidden rounded-sm"
							:class="isCurrentQuarter(record) ? 'bg-gray-300' : 'bg-gray-500'"
							:style="{ height: quarterBarHeight(record) + '%' }">
							<div v-if="isSingleCustomerQuarter(record)" :class="isCurrentQuarter(record) ? 'bg-amber-300' : 'bg-amber-500'" :style="{ height: customerShare(record) + '%' }" />
						</div>
						<span v-if="record.days_out_of_stock" class="absolute left-1/2 top-0 h-1 w-1 -translate-x-1/2 rounded-full bg-red-500 ring-1 ring-white" />
					</div>
				</div>
			</div>
			<Popover v-if="isQuarterChartMounted" ref="quarterChartPopover" class="pointer-events-none">
				<div class="w-96 p-1 text-xs text-gray-600">
					<div class="mb-3 flex items-baseline justify-between">
						<span class="text-sm font-semibold text-gray-800">{{ ctrans("Sales per quarter") }}</span>
						<span v-if="salesPerQuarter !== null" class="text-gray-500">
							{{ ctrans("avg :quantity SKOs", { quantity: formatNumber(salesPerQuarter) }) }}
						</span>
					</div>

					<div class="flex h-36 items-end gap-3 border-b border-gray-200 px-1">
						<div v-for="record in quarters" :key="record.period" class="flex h-full flex-1 flex-col items-center justify-end">
							<span class="mb-1 font-semibold tabular-nums text-gray-700">{{ formatNumber(record.sales) }}</span>
							<div
								class="relative flex w-full max-w-[2.75rem] flex-col rounded-t-md"
								:class="isCurrentQuarter(record) ? 'bg-gray-200' : 'bg-gray-400'"
								:style="{ height: quarterBarHeight(record) * 0.8 + '%' }">
								<div
									v-if="isSingleCustomerQuarter(record)"
									class="flex items-start justify-center rounded-t-md pt-0.5 text-[10px] font-semibold text-white"
									:class="isCurrentQuarter(record) ? 'bg-amber-300' : 'bg-amber-500'"
									:style="{ height: customerShare(record) + '%' }">
									<span v-if="customerShare(record) >= 30">{{ customerShare(record) }}%</span>
								</div>
								<span v-if="record.days_out_of_stock" class="absolute -top-1 left-1/2 h-2 w-2 -translate-x-1/2 rounded-full bg-red-500 ring-2 ring-white" />
							</div>
						</div>
					</div>
					<div class="mt-1.5 flex gap-3 px-1">
						<div v-for="record in quarters" :key="record.period" class="flex-1 text-center leading-tight">
							<div class="font-medium text-gray-700">{{ quarterShortLabel(record.period) }}</div>
							<div v-if="isCurrentQuarter(record)" class="text-[10px] text-gray-400">{{ ctrans("so far") }}</div>
							<div v-if="record.days_out_of_stock" class="text-[10px] text-red-600">{{ ctrans(":days d out", { days: String(record.days_out_of_stock) }) }}</div>
						</div>
					</div>

					<div v-if="quarters.some((record) => record.top_customer)" class="mt-3 rounded-md bg-gray-50 px-2.5 py-2">
						<div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Biggest customer") }}</div>
						<template v-for="record in quarters" :key="record.period">
							<div v-if="record.top_customer" class="flex items-center gap-2 py-0.5">
								<span class="w-11 shrink-0 text-gray-400">{{ quarterShortLabel(record.period) }}</span>
								<span
									class="h-2 w-2 shrink-0 rounded-full"
									:class="isSingleCustomerQuarter(record) ? 'bg-amber-500' : 'bg-gray-300'" />
								<span class="truncate" :class="isSingleCustomerQuarter(record) ? 'font-medium text-gray-800' : ''">{{ record.top_customer.name }}</span>
								<span class="ml-auto shrink-0 tabular-nums text-gray-500">
									{{ formatNumber(record.top_customer.sales) }}
									<span :class="isSingleCustomerQuarter(record) ? 'font-semibold text-amber-700' : ''">· {{ customerShare(record) }}%</span>
								</span>
							</div>
						</template>
					</div>

					<div v-if="dominantCustomer || daysOutOfStock" class="mt-2 space-y-1.5">
						<div v-if="dominantCustomer" class="rounded-md bg-amber-50 px-2.5 py-1.5 text-amber-800">
							⚠ {{ ctrans(":percent% of these sales went to one customer (:customer). Ordering for this demand is risky if they stop buying.", { percent: String(dominantCustomer.share), customer: dominantCustomer.name }) }}
						</div>
						<div v-if="daysOutOfStock" class="rounded-md bg-red-50 px-2.5 py-1.5 text-red-700">
							⚠ {{ ctrans("Out of stock for :days days in the last :count quarters. Sales were lost on those days, so real demand is likely higher than shown.", { days: String(daysOutOfStock), count: String(lastQuarters.length) }) }}
						</div>
					</div>

					<div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 border-t border-gray-100 pt-2 text-[10px] text-gray-500">
						<span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-gray-400" />{{ ctrans("Completed quarter") }}</span>
						<span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-gray-200" />{{ ctrans("Current quarter") }}</span>
						<span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-amber-500" />{{ ctrans("One customer, half or more") }}</span>
						<span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500" />{{ ctrans("Ran out of stock") }}</span>
					</div>
				</div>
			</Popover>
		</div>

		<div v-if="thisOrder > 0 && hasHistory && !isOrderLocked" class="flex items-center gap-x-2 text-gray-500">
			<span>{{ ctrans("With this order: lasts :time", { time: weeksLabel(daysAfter) }) }}</span>
			<span v-tooltip="verdict.tooltip" class="cursor-help font-semibold uppercase tracking-wide" :class="verdict.class">{{ verdict.label }}</span>
		</div>

		<div v-if="comingDeliveries.length || otherOrders.length" class="space-y-0.5">
			<div v-for="delivery in comingDeliveries" :key="delivery.slug" class="flex items-baseline gap-1.5 text-gray-600">
				<span class="text-[10px] uppercase tracking-wide text-emerald-700">{{ delivery.state_label }}</span>
				<Link :href="stockDeliveryRoute(delivery.slug)" class="font-mono text-gray-700 hover:underline">{{ delivery.reference }}</Link>
				<span class="tabular-nums">{{ formatNumber(Number(delivery.quantity) / pack) }}</span>
			</div>
			<div v-for="order in otherOrders" :key="order.slug ?? order.reference" class="flex items-baseline gap-1.5 text-gray-600">
				<span class="text-[10px] uppercase tracking-wide text-amber-700">{{ order.state === "sent" ? ctrans("Requested") : ctrans("Ordered") }}</span>
				<Link v-if="order.slug" :href="purchaseOrderRoute(order.slug)" class="font-mono text-gray-700 hover:underline">{{ order.reference }}</Link>
				<span v-else class="text-gray-700">{{ order.reference }}</span>
				<span class="tabular-nums">{{ formatNumber(Number(order.quantity_ordered) / pack) }}</span>
			</div>
		</div>

		<div v-if="recentDeliveries.length" class="text-gray-400">
			<button type="button" class="hover:text-gray-600" @click="showPastDeliveries = !showPastDeliveries">
				{{ ctrans("Last arrived :date", { date: useFormatTime(recentDeliveries[0].received_at) }) }}
				<span class="text-[10px]">{{ showPastDeliveries ? "▴" : "▾" }}</span>
			</button>
			<div v-if="showPastDeliveries" class="mt-0.5 space-y-0.5">
				<div v-for="delivery in recentDeliveries" :key="delivery.slug" class="flex items-baseline gap-1.5">
					<span class="w-24 shrink-0">{{ useFormatTime(delivery.received_at) }}</span>
					<Link :href="stockDeliveryRoute(delivery.slug)" class="font-mono hover:text-gray-600 hover:underline">{{ delivery.reference }}</Link>
					<span class="tabular-nums">{{ formatNumber(Number(delivery.quantity) / pack) }}</span>
				</div>
			</div>
		</div>
	</div>
</template>
