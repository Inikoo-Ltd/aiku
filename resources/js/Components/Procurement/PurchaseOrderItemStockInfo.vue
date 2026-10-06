<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"

interface QuarterUsage {
	period: string
	sales: number
	days_out_of_stock: number
}

const props = defineProps<{
	item: any
	isPartner?: boolean
	typedSkos?: number | null
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

const thisOrder = computed(() =>
	props.typedSkos !== undefined && props.typedSkos !== null
		? Number(props.typedSkos) || 0
		: (Number(props.item.quantity_ordered) || 0) / pack.value
)

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

const verdict = computed(() => {
	if (!hasHistory.value) {
		return { label: ctrans("No sales history"), class: "text-gray-400" }
	}
	const days = thisOrder.value > 0 ? daysAfter.value : daysNow.value
	if (days < leadDays.value) {
		return thisOrder.value > 0
			? { label: ctrans("Too little"), class: "text-red-600" }
			: { label: ctrans("Order now"), class: "text-red-600" }
	}
	if (days > overstockDays.value) {
		return thisOrder.value > 0
			? { label: ctrans("Too much"), class: "text-amber-600" }
			: { label: ctrans("Not needed"), class: "text-gray-400" }
	}

	return { label: ctrans("OK"), class: "text-green-600" }
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

const quarterTooltip = (record: QuarterUsage) =>
	[
		`${record.period}: ${formatNumber(record.sales)} ${ctrans("SKOs")}`,
		record.period === currentQuarter ? ctrans("so far") : "",
		record.days_out_of_stock ? ctrans(":days days out of stock", { days: String(record.days_out_of_stock) }) : "",
	]
		.filter(Boolean)
		.join(" · ")

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
				<span class="font-semibold text-gray-800">{{ formatNumber(stock) }}</span> {{ ctrans("in stock") }}
				<template v-if="incoming > 0">
					+ <span class="font-semibold text-gray-800">{{ formatNumber(incoming) }}</span> {{ ctrans("coming") }}
				</template>
				<template v-if="hasHistory">
					· {{ ctrans("lasts") }} <span class="font-semibold text-gray-800">{{ weeksLabel(daysNow) }}</span>
				</template>
			</span>
			<span v-if="!(thisOrder > 0 && hasHistory)" class="font-semibold uppercase tracking-wide" :class="verdict.class">{{ verdict.label }}</span>
		</div>

		<div v-if="hasHistory" class="flex items-center gap-3">
			<div
				v-tooltip="thisOrder > 0 ? ctrans('With this order: lasts :time', { time: weeksLabel(daysAfter) }) : ctrans('Lead time: :time', { time: weeksLabel(leadDays) })"
				class="relative h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
				<div class="absolute inset-y-0 left-0 bg-gray-400" :style="{ width: percent(daysNow) + '%' }" />
				<div
					v-if="thisOrder > 0"
					class="absolute inset-y-0 bg-green-300"
					:class="daysAfter > overstockDays ? 'bg-amber-300' : daysAfter < leadDays ? 'bg-red-300' : 'bg-green-300'"
					:style="{ left: percent(daysNow) + '%', width: Math.max(0, percent(daysAfter) - percent(daysNow)) + '%' }" />
				<div class="absolute inset-y-0 w-0.5 bg-red-500" :style="{ left: percent(leadDays) + '%' }" />
				<div class="absolute inset-y-0 w-0.5 bg-amber-500" :style="{ left: percent(overstockDays) + '%' }" />
			</div>

			<span v-if="salesPerQuarter !== null" class="shrink-0 text-gray-500">
				~{{ formatNumber(salesPerQuarter) }}/{{ ctrans("qtr") }}
				<span v-if="daysOutOfStock" class="text-red-600">· {{ ctrans(":days d out of stock", { days: String(daysOutOfStock) }) }}</span>
			</span>

			<div v-if="quarters.length" class="flex h-4 items-end gap-0.5">
				<div
					v-for="record in quarters"
					:key="record.period"
					v-tooltip="quarterTooltip(record)"
					class="relative w-1.5 rounded-sm"
					:class="record.period === currentQuarter ? 'bg-gray-300' : 'bg-gray-500'"
					:style="{ height: Math.max(8, (Number(record.sales) / quarterMax) * 100) + '%' }">
					<span v-if="record.days_out_of_stock" class="absolute -top-1 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-red-500" />
				</div>
			</div>

			<button
				v-if="suggestion !== null && suggestion > 0"
				type="button"
				v-tooltip="ctrans('Enough for :time after it arrives, counting what is in stock and coming', { time: weeksLabel(targetDays - leadDays) })"
				class="shrink-0 rounded border px-1.5 py-0.5 font-medium"
				:class="Math.round(thisOrder) === suggestion ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
				@click="emit('suggest', suggestion)">
				{{ ctrans("Suggest") }} {{ formatNumber(suggestion) }}
			</button>
		</div>

		<div v-if="thisOrder > 0 && hasHistory" class="flex items-center gap-x-2 text-gray-500">
			<span>{{ ctrans("With this order: lasts :time", { time: weeksLabel(daysAfter) }) }}</span>
			<span class="font-semibold uppercase tracking-wide" :class="verdict.class">{{ verdict.label }}</span>
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
