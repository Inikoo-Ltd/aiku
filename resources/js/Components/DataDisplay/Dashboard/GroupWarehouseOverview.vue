<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDolly, faHeartbeat, faTruckContainer } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faDolly, faHeartbeat, faTruckContainer)

type Route = { name: string; parameters: Record<string, string | null> }
type Figures = {
	work: Record<"waiting" | "picking" | "blocked" | "packing" | "ready_to_ship", number>
	stock_health: Record<"out_of_stock" | "critical" | "low" | "ideal" | "excess" | "error", number>
	goods_in: Record<"confirmed" | "on_the_way" | "to_book_in" | "booking_in" | "open_purchase_orders", number>
}

const props = defineProps<{
	overview: {
		totals: Figures
		organisations: (Figures & {
			name: string
			slug: string
			routes: Record<"work" | "stock_health" | "goods_in", Route | null>
		})[]
	}
}>()

const locale = useLocaleStore()

const workItems = [
	{ key: "waiting", label: ctrans("Waiting"), alert: false },
	{ key: "picking", label: ctrans("Picking"), alert: false },
	{ key: "blocked", label: ctrans("Blocked"), alert: true },
	{ key: "packing", label: ctrans("Packing"), alert: false },
	{ key: "ready_to_ship", label: ctrans("Ready to ship"), alert: false },
] as const

const stockItems = [
	{ key: "out_of_stock", label: ctrans("Out of stock"), color: "bg-red-500" },
	{ key: "critical", label: ctrans("Critical"), color: "bg-orange-500" },
	{ key: "low", label: ctrans("Low"), color: "bg-amber-400" },
	{ key: "ideal", label: ctrans("Ideal"), color: "bg-green-500" },
	{ key: "excess", label: ctrans("Excess"), color: "bg-sky-400" },
	{ key: "error", label: ctrans("Error"), color: "bg-gray-400" },
] as const

const goodsInItems = [
	{ key: "confirmed", label: ctrans("Confirmed") },
	{ key: "on_the_way", label: ctrans("On the way") },
	{ key: "to_book_in", label: ctrans("To book in") },
	{ key: "booking_in", label: ctrans("Booking in") },
	{ key: "open_purchase_orders", label: ctrans("Open purchase orders") },
] as const

const stockTotal = (health: Figures["stock_health"]) => Object.values(health).reduce((sum, value) => sum + value, 0)

const stockPercentage = (health: Figures["stock_health"], key: keyof Figures["stock_health"]) => {
	const total = stockTotal(health)
	return total > 0 ? (health[key] / total) * 100 : 0
}

const groupStockTotal = computed(() => stockTotal(props.overview.totals.stock_health))
</script>

<template>
	<div class="px-3 sm:px-6 mb-4 grid grid-cols-1 xl:grid-cols-3 gap-4">
		<div class="bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
			<div class="px-5 pt-4 pb-3">
				<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
					<FontAwesomeIcon icon="fal fa-dolly" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ ctrans("Warehouse work now") }}
				</h3>
				<dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-3">
					<div v-for="item in workItems" :key="item.key">
						<dt class="text-xs font-medium text-gray-500">{{ item.label }}</dt>
						<dd class="text-xl font-semibold tabular-nums" :class="item.alert && overview.totals.work[item.key] > 0 ? 'text-red-500' : 'text-gray-800'">
							{{ locale.numberShort(overview.totals.work[item.key]) }}
						</dd>
					</div>
				</dl>
			</div>
			<ul v-if="overview.organisations.length > 0" class="divide-y divide-gray-100 border-t border-gray-100 text-xs">
				<li v-for="org in overview.organisations" :key="org.slug" class="px-4 py-2 hover:bg-gray-50 transition-colors">
					<div class="font-medium">
						<Link v-if="org.routes.work" :href="route(org.routes.work.name, org.routes.work.parameters)" class="text-gray-800 hover:text-blue-600 hover:underline">{{ org.name }}</Link>
						<span v-else class="text-gray-800">{{ org.name }}</span>
					</div>
					<dl class="mt-1 grid grid-cols-6 gap-x-2">
						<div v-for="item in workItems" :key="item.key" class="text-right">
							<dt class="truncate text-[10px] text-gray-400" v-tooltip="item.label">{{ item.label }}</dt>
							<dd class="tabular-nums" :class="item.alert && org.work[item.key] > 0 ? 'text-red-500' : 'text-gray-700'">{{ locale.numberShort(org.work[item.key]) }}</dd>
						</div>
					</dl>
				</li>
			</ul>
		</div>

		<div class="bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
			<div class="px-5 pt-4 pb-3">
				<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
					<FontAwesomeIcon icon="fal fa-heartbeat" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ ctrans("Stock health") }}
				</h3>
				<p class="mt-3 text-xl font-semibold tabular-nums text-gray-800">{{ locale.numberShort(groupStockTotal) }}</p>
				<div class="mt-2 flex h-3 w-full overflow-hidden rounded-full bg-gray-100">
					<div
						v-for="item in stockItems"
						:key="item.key"
						class="h-full"
						:class="item.color"
						:style="{ width: stockPercentage(overview.totals.stock_health, item.key) + '%' }"
						v-tooltip="`${item.label}: ${overview.totals.stock_health[item.key]}`"
					/>
				</div>
				<dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-2">
					<div v-for="item in stockItems" :key="item.key">
						<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500">
							<span class="inline-block h-2 w-2 rounded-full" :class="item.color" />
							{{ item.label }}
						</dt>
						<dd class="text-sm font-semibold tabular-nums text-gray-800">{{ locale.numberShort(overview.totals.stock_health[item.key]) }}</dd>
					</div>
				</dl>
			</div>
			<ul v-if="overview.organisations.length > 0" class="divide-y divide-gray-100 border-t border-gray-100 text-xs">
				<li v-for="org in overview.organisations" :key="org.slug" class="flex items-center gap-x-3 px-4 py-2 hover:bg-gray-50 transition-colors">
					<span class="w-1/3 truncate font-medium">
						<Link v-if="org.routes.stock_health" :href="route(org.routes.stock_health.name, org.routes.stock_health.parameters)" class="text-gray-800 hover:text-blue-600 hover:underline">{{ org.name }}</Link>
						<span v-else class="text-gray-800">{{ org.name }}</span>
					</span>
					<div class="flex h-2 flex-1 overflow-hidden rounded-full bg-gray-100">
						<div
							v-for="item in stockItems"
							:key="item.key"
							class="h-full"
							:class="item.color"
							:style="{ width: stockPercentage(org.stock_health, item.key) + '%' }"
							v-tooltip="`${item.label}: ${org.stock_health[item.key]}`"
						/>
					</div>
					<span class="w-12 text-right tabular-nums text-gray-700">{{ locale.numberShort(stockTotal(org.stock_health)) }}</span>
				</li>
			</ul>
		</div>

		<div class="bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
			<div class="px-5 pt-4 pb-3">
				<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
					<FontAwesomeIcon icon="fal fa-truck-container" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ ctrans("Goods in") }}
				</h3>
				<dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-3">
					<div v-for="item in goodsInItems" :key="item.key">
						<dt class="text-xs font-medium text-gray-500">{{ item.label }}</dt>
						<dd class="text-xl font-semibold tabular-nums text-gray-800">{{ locale.numberShort(overview.totals.goods_in[item.key]) }}</dd>
					</div>
				</dl>
			</div>
			<ul v-if="overview.organisations.length > 0" class="divide-y divide-gray-100 border-t border-gray-100 text-xs">
				<li v-for="org in overview.organisations" :key="org.slug" class="px-4 py-2 hover:bg-gray-50 transition-colors">
					<div class="font-medium">
						<Link v-if="org.routes.goods_in" :href="route(org.routes.goods_in.name, org.routes.goods_in.parameters)" class="text-gray-800 hover:text-blue-600 hover:underline">{{ org.name }}</Link>
						<span v-else class="text-gray-800">{{ org.name }}</span>
					</div>
					<dl class="mt-1 grid grid-cols-5 gap-x-2">
						<div v-for="item in goodsInItems" :key="item.key" class="text-right">
							<dt class="truncate text-[10px] text-gray-400" v-tooltip="item.label">{{ item.label }}</dt>
							<dd class="tabular-nums text-gray-700">{{ locale.numberShort(org.goods_in[item.key]) }}</dd>
						</div>
					</dl>
				</li>
			</ul>
		</div>
	</div>
</template>
