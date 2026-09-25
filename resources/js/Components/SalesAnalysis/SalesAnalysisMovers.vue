<script setup lang="ts">
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowDown, faArrowUp, faStore, faCube, faFrown } from "@fal"

interface Mover {
	label: string
	kind: "website" | "product"
	sales: number
	previous_sales: number
}

const props = defineProps<{
	teaser?: {
		currency: string
		shop_count: number
		breakdown_label: string | null
		shops: Array<{ shop_code: string; sales: number; previous_sales: number }>
		breakdown: Array<{ code: string; sales: number; previous_sales: number }>
	}
}>()

const money = (value: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.teaser?.currency ?? "GBP", maximumFractionDigits: 0, signDisplay: "exceptZero" }).format(Math.round(value))
const percentChange = (row: Mover) => (row.previous_sales ? ((row.sales - row.previous_sales) / row.previous_sales) * 100 : null)
const formatPercent = (value: number | null) => (value === null ? ctrans("new") : `${value > 0 ? "+" : ""}${Math.round(value)}%`)

const rows = computed<Mover[]>(() => [
	...(props.teaser?.shops ?? []).map((row) => ({ label: row.shop_code, kind: "website" as const, sales: row.sales, previous_sales: row.previous_sales })),
	...(props.teaser?.breakdown ?? []).map((row) => ({ label: row.code, kind: "product" as const, sales: row.sales, previous_sales: row.previous_sales })),
])

const byPercent = (a: Mover, b: Mover) => (percentChange(a) ?? Infinity) - (percentChange(b) ?? Infinity)
const byMoney = (a: Mover, b: Mover) => a.sales - a.previous_sales - (b.sales - b.previous_sales)

const UNIT_KEY = "masterFamilySalesMovers.unit"
const readUnit = (): "percent" | "money" => {
	try {
		return localStorage.getItem(UNIT_KEY) === "money" ? "money" : "percent"
	} catch {
		return "percent"
	}
}
const unit = ref<"percent" | "money">(readUnit())
const setUnit = (value: "percent" | "money") => {
	unit.value = value
	try {
		localStorage.setItem(UNIT_KEY, value)
	} catch {
		/* storage unavailable */
	}
}
const currencySymbol = computed(
	() => new Intl.NumberFormat(undefined, { style: "currency", currency: props.teaser?.currency ?? "GBP" }).formatToParts(0).find((part) => part.type === "currency")?.value ?? "£"
)
const order = computed(() => (unit.value === "money" ? byMoney : byPercent))
const display = (row: Mover) => (unit.value === "money" ? money(row.sales - row.previous_sales) : formatPercent(percentChange(row)))
const tooltip = (row: Mover) => (unit.value === "money" ? formatPercent(percentChange(row)) : money(row.sales - row.previous_sales))

const columns = computed(() =>
	[
		{ kind: "website" as const, label: ctrans("Websites"), icon: faStore, isShown: (props.teaser?.shop_count ?? 0) > 1 },
		{ kind: "product" as const, label: props.teaser?.breakdown_label ?? "", icon: faCube, isShown: !!props.teaser?.breakdown_label },
	]
		.filter((column) => column.isShown)
		.map((column) => {
		const movers = rows.value.filter((row) => row.kind === column.kind)
		return {
			...column,
			falling: movers.filter((row) => row.sales < row.previous_sales).sort(byMoney).slice(0, 5).sort(order.value),
			growing: movers.filter((row) => row.sales > row.previous_sales).sort(byMoney).reverse().slice(0, 5).sort(order.value).reverse(),
		}
	})
)
const gridColumns = computed(() => ({ gridTemplateColumns: `repeat(${Math.max(1, columns.value.length)}, minmax(0, 1fr))` }))
const isAnythingGrowing = computed(() => columns.value.some((column) => column.growing.length))
</script>

<template>
	<div class="grid grid-rows-[auto_1fr_auto_1fr] rounded-lg border border-gray-200 bg-white text-xs text-gray-700 shadow-sm">
		<template v-if="teaser">
			<div class="grid gap-4 border-b border-gray-200 px-3 py-2 text-gray-500" :style="gridColumns">
				<div v-for="(column, index) in columns" :key="column.kind" class="flex items-center gap-1.5">
					<FontAwesomeIcon :icon="column.icon" fixed-width aria-hidden="true" />
					{{ column.label }}
					<div v-if="index === columns.length - 1" class="ml-auto flex overflow-hidden rounded border border-gray-300" data-movers-unit>
						<button
							v-for="option in (['percent', 'money'] as const)"
							:key="option"
							type="button"
							class="px-1.5 leading-5"
							:class="unit === option ? 'bg-gray-700 text-white' : 'text-gray-600 hover:bg-gray-50'"
							@click="setUnit(option)">
							{{ option === "percent" ? "%" : currencySymbol }}
						</button>
					</div>
				</div>
			</div>

			<div v-if="isAnythingGrowing" class="grid gap-4 px-3 pt-2" :style="gridColumns">
				<div v-for="column in columns" :key="column.kind" class="min-w-0">
					<div v-for="row in column.growing" :key="row.label" v-tooltip="tooltip(row)" class="flex items-center gap-1.5 py-0.5 tabular-nums">
						<FontAwesomeIcon :icon="faArrowUp" class="text-green-600" fixed-width aria-hidden="true" />
						<span class="truncate font-medium">{{ row.label }}</span>
						<span class="ml-auto text-green-600">{{ display(row) }}</span>
					</div>
					<div v-if="!column.growing.length" class="py-0.5 text-gray-400">—</div>
				</div>
			</div>
			<div v-else class="flex flex-col items-center justify-center gap-1 pt-2 text-gray-400">
				<FontAwesomeIcon :icon="faFrown" class="text-2xl" fixed-width aria-hidden="true" />
				{{ ctrans("Nothing is growing") }}
			</div>

			<div class="mx-3 my-1 border-t border-gray-100" />

			<div class="grid gap-4 px-3 pb-2" :style="gridColumns">
				<div v-for="column in columns" :key="column.kind" class="min-w-0">
					<div v-for="row in column.falling" :key="row.label" v-tooltip="tooltip(row)" class="flex items-center gap-1.5 py-0.5 tabular-nums">
						<FontAwesomeIcon :icon="faArrowDown" class="text-red-600" fixed-width aria-hidden="true" />
						<span class="truncate font-medium">{{ row.label }}</span>
						<span class="ml-auto text-red-600">{{ display(row) }}</span>
					</div>
					<div v-if="!column.falling.length" class="py-0.5 text-gray-400">—</div>
				</div>
			</div>
		</template>
		<div v-else class="grid gap-4 p-3">
			<div class="h-32 animate-pulse rounded bg-gray-100" />
			<div class="h-32 animate-pulse rounded bg-gray-100" />
		</div>
	</div>
</template>
