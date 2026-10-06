import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

const MAX_DAYS = 730

export function usePurchaseOrderStockCover(getItem: () => any, getIsPartner: () => boolean | undefined) {
	const pack = computed(() => Number(getItem().units_per_pack) || 1)
	const cover = computed(() => getItem().stock_cover ?? null)
	const dailyUsage = computed(() => Number(cover.value?.daily_usage) || 0)
	const leadDays = computed(() => Number(cover.value?.lead_time_days) || 14)
	const overstockDays = computed(() => Number(cover.value?.overstock_days) || 120)
	const stock = computed(() => Math.max(0, Number(getItem().stock_in_locations) || 0))

	const comingDeliveries = computed<any[]>(() => getItem().stock_deliveries?.coming ?? [])
	const otherOrders = computed<any[]>(() => getItem().other_open_purchase_orders ?? [])
	const incoming = computed(
		() =>
			comingDeliveries.value.reduce((total, delivery) => total + Number(delivery.quantity || 0), 0) / pack.value +
			otherOrders.value.reduce((total, order) => total + Number(order.quantity_ordered || 0), 0) / pack.value
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

	const hasHistory = computed(() => dailyUsage.value > 0 || weeklyForecast.value.some((week) => week > 0))

	const targetDays = computed(() =>
		Math.min(Math.max(leadDays.value + 30, 2 * leadDays.value), Math.max(leadDays.value + 14, overstockDays.value - 7))
	)

	const cartonSkos = computed(() =>
		Math.max(1, (Number(getIsPartner() ? getItem().partner_units_per_carton : getItem().units_per_carton) || 1) / pack.value)
	)

	const suggestion = computed(() => {
		if (!hasHistory.value) {
			return null
		}
		const need = demandOver(targetDays.value) - stock.value - incoming.value

		if (need <= 0) {
			return 0
		}
		const wholeCartons = Math.ceil(need / cartonSkos.value) * cartonSkos.value
		if (getIsPartner() && !getItem().whole_cartons_only && coverDays(stock.value + incoming.value + wholeCartons) > overstockDays.value) {
			return Math.ceil(need)
		}

		return wholeCartons
	})

	return {
		MAX_DAYS,
		pack,
		cover,
		dailyUsage,
		leadDays,
		overstockDays,
		stock,
		comingDeliveries,
		otherOrders,
		incoming,
		weeklyForecast,
		coverDays,
		hasHistory,
		targetDays,
		suggestion,
		weeksLabel,
	}
}
