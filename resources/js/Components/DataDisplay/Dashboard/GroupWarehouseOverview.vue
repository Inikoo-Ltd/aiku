<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHeartbeat, faQuestionCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faHeartbeat, faQuestionCircle)

type Route = { name: string; parameters: Record<string, string | null> }
type Figures = {
	stock_health: Record<string, number>
}

const props = defineProps<{
	overview: {
		totals: Figures
		stock_levels: { bucket: string; label: string; description: string | null; tone: string }[]
		organisations: (Figures & {
			name: string
			slug: string
			routes: Record<"stock_health", Route | null>
		})[]
	}
}>()

const locale = useLocaleStore()

const toneColor: Record<string, string> = {
	"red-deep": "bg-red-700",
	red: "bg-red-500",
	orange: "bg-orange-500",
	amber: "bg-amber-400",
	yellow: "bg-yellow-300",
	green: "bg-green-500",
	blue: "bg-sky-400",
	gray: "bg-gray-400",
}

const stockItems = computed(() => props.overview.stock_levels.map((level) => ({
	key: level.bucket,
	label: level.label,
	help: level.description,
	color: toneColor[level.tone] ?? "bg-gray-300",
})))

const stockTotal = (health: Figures["stock_health"]) => Object.values(health).reduce((sum, value) => sum + value, 0)

const stockPercentage = (health: Figures["stock_health"], key: string) => {
	const total = stockTotal(health)
	return total > 0 ? (health[key] / total) * 100 : 0
}

const groupStockTotal = computed(() => stockTotal(props.overview.totals.stock_health))
</script>

<template>
	<div class="px-3 sm:px-6 mb-4">
		<div class="bg-white rounded-lg shadow ring-1 ring-gray-200 overflow-hidden">
			<div class="px-5 pt-4 pb-3">
				<h3 class="flex items-center gap-x-1.5 text-sm font-semibold text-gray-700">
					<FontAwesomeIcon icon="fal fa-heartbeat" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ ctrans("Stock health") }}
					<FontAwesomeIcon icon="fal fa-question-circle" class="cursor-help text-gray-300" fixed-width aria-hidden="true" v-tooltip="ctrans('Active SKOs by days of cover against their supplier lead time, the same levels as the procurement stock cover. Out of stock uses the same rule as the Out of Stock figure above.')" />
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
				<dl class="mt-3 grid grid-cols-4 lg:grid-cols-8 gap-x-4 gap-y-2">
					<div v-for="item in stockItems" :key="item.key">
						<dt class="flex items-center gap-x-1.5 text-xs font-medium text-gray-500" v-tooltip="item.help">
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

	</div>
</template>
