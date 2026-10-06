<script setup lang="ts">
import { computed, ref } from "vue"
import { useElementSize } from "@vueuse/core"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowDown, faArrowUp, faStore, faCube, faFrown, faSmile, faInbox, faChevronDown } from "@fal"

interface Mover {
	label: string
	name: string | null
	kind: "website" | "product"
	sales: number
	previous_sales: number
	href: string | null
}

const props = defineProps<{
	teaser?: {
		currency: string
		shop_count: number
		breakdown_label: string | null
		shops: Array<{ shop_code: string; shop_name?: string; shop_short_name?: string | null; shop_url?: string; sales: number; previous_sales: number }>
		breakdown: Array<{ id: number; slug: string | null; code: string; name?: string; sales: number; previous_sales: number }>
	}
	breakdownRoute?: (row: { id: number; slug: string | null }) => string | null
}>()

const money = (value: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.teaser?.currency ?? "GBP", maximumFractionDigits: 0, signDisplay: "exceptZero" }).format(Math.round(value))
const percentChange = (row: Mover) => (row.previous_sales ? ((row.sales - row.previous_sales) / row.previous_sales) * 100 : null)
const formatPercent = (value: number | null) => (value === null ? ctrans("new") : `${value > 0 ? "+" : ""}${Math.round(value)}%`)

const productHref = (row: { id: number; slug: string | null }) => {
	const href = row.id ? props.breakdownRoute?.(row) : null
	return href ? `${href}?tab=sales_analysis` : null
}

const rows = computed<Mover[]>(() => [
	...(props.teaser?.shops ?? []).map((row) => ({ label: row.shop_short_name || row.shop_code, name: row.shop_name ?? null, kind: "website" as const, sales: row.sales, previous_sales: row.previous_sales, href: row.shop_url ?? null })),
	...(props.teaser?.breakdown ?? []).map((row) => ({ label: row.code, name: row.name ?? null, kind: "product" as const, sales: row.sales, previous_sales: row.previous_sales, href: productHref(row) })),
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
const panel = ref<HTMLElement | null>(null)
const { width: panelWidth } = useElementSize(panel)
const isWide = computed(() => panelWidth.value >= 560)

const shownUnit = computed(() => (isWide.value ? "money" : unit.value))
const order = computed(() => (shownUnit.value === "money" ? byMoney : byPercent))
const display = (row: Mover) => (shownUnit.value === "money" ? money(row.sales - row.previous_sales) : formatPercent(percentChange(row)))
const tooltip = (row: Mover) => (shownUnit.value === "money" ? formatPercent(percentChange(row)) : money(row.sales - row.previous_sales))

const availableColumns = computed(() => [
	{ kind: "website" as const, label: ctrans("Shops"), icon: faStore, isShown: (props.teaser?.shop_count ?? 0) > 1 },
	{ kind: "product" as const, label: props.teaser?.breakdown_label ?? "", icon: faCube, isShown: !!props.teaser?.breakdown_label },
])

const shownColumns = computed(() => {
	const shown = availableColumns.value.filter((column) => column.isShown)

	return shown.length ? shown : availableColumns.value.filter((column) => column.kind === "website")
})

const columns = computed(() =>
	shownColumns.value
		.map((column) => {
		const movers = rows.value.filter((row) => row.kind === column.kind)
		const shown = [...movers.filter((row) => row.sales < row.previous_sales), ...movers.filter((row) => row.sales > row.previous_sales)]
		return {
			...column,
			template: `auto minmax(0, ${Math.max(0, ...shown.map((row) => row.label.length)) + 1}ch) ${Math.max(0, ...shown.map((row) => money(row.sales - row.previous_sales).length)) + 2}ch${isWide.value ? " 6ch" : ""}`,
			falling: movers.filter((row) => row.sales < row.previous_sales).sort(byMoney).slice(0, 5).sort(order.value),
			growing: movers.filter((row) => row.sales > row.previous_sales).sort(byMoney).reverse().slice(0, 5).sort(order.value).reverse(),
		}
	})
)
const gridColumns = computed(() => ({ gridTemplateColumns: `repeat(${Math.max(1, columns.value.length)}, minmax(0, 1fr))` }))
const isAnythingGrowing = computed(() => columns.value.some((column) => column.growing.length))
const isAnythingFalling = computed(() => columns.value.some((column) => column.falling.length))
const hasMovers = computed(() => isAnythingGrowing.value || isAnythingFalling.value)

const isOpenByUser = ref<boolean | null>(null)
const isOpen = computed(() => isOpenByUser.value ?? hasMovers.value)
</script>

<template>
	<div ref="panel" class="grid grid-rows-[auto_1fr_auto_1fr] rounded-lg border border-gray-200 bg-white text-xs text-gray-700 shadow-sm" :class="{ 'h-fit': !isOpen }">
		<template v-if="teaser">
			<div class="grid cursor-pointer select-none gap-4 px-3 py-2 text-gray-500" :class="{ 'border-b border-gray-200': isOpen }" :style="gridColumns" @click="isOpenByUser = !isOpen">
				<div v-for="(column, index) in columns" :key="column.kind" class="flex items-center gap-1.5">
					<FontAwesomeIcon :icon="column.icon" fixed-width aria-hidden="true" />
					{{ column.label }}
					<span v-if="!hasMovers && index === 0" class="text-gray-400">· {{ ctrans("No data available") }}</span>
					<div v-if="index === columns.length - 1" class="ml-auto flex items-center gap-2">
						<div v-if="isOpen && hasMovers && !isWide" class="flex cursor-default overflow-hidden rounded border border-gray-300" data-movers-unit @click.stop>
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
						<button
							type="button"
							class="flex h-6 w-6 items-center justify-center rounded text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
							@click.stop="isOpenByUser = !isOpen">
							<FontAwesomeIcon :icon="faChevronDown" class="transition-transform" :class="{ 'rotate-180': isOpen }" fixed-width aria-hidden="true" />
						</button>
					</div>
				</div>
			</div>

			<template v-if="!isOpen" />

			<div v-else-if="!hasMovers" class="flex flex-col items-center justify-center gap-1 py-4 text-gray-400">
				<FontAwesomeIcon :icon="faInbox" class="text-2xl" fixed-width aria-hidden="true" />
				{{ ctrans("No data available") }}
			</div>

			<template v-else>
			<div v-if="isAnythingGrowing" class="grid gap-4 px-3 pt-2" :style="gridColumns">
				<div v-for="column in columns" :key="column.kind" class="grid min-w-0 content-start items-center justify-start gap-x-1.5 tabular-nums" :style="{ gridTemplateColumns: column.template }">
					<div v-for="row in column.growing" :key="row.label" class="contents">
						<FontAwesomeIcon :icon="faArrowUp" class="py-0.5 text-green-600" fixed-width aria-hidden="true" />
						<Link v-if="row.href" v-tooltip="row.name" :href="row.href" class="truncate font-medium hover:underline">{{ row.label }}</Link>
						<span v-else v-tooltip="row.name" class="truncate font-medium">{{ row.label }}</span>
						<span v-tooltip="isWide ? null : tooltip(row)" class="whitespace-nowrap pl-2 text-right text-green-600">{{ display(row) }}</span>
						<span v-if="isWide" class="whitespace-nowrap pl-1 text-right text-green-600/70">{{ tooltip(row) }}</span>
					</div>
					<div v-if="!column.growing.length" class="col-span-full py-0.5 text-gray-400">—</div>
				</div>
			</div>
			<div v-else class="flex flex-col items-center justify-center gap-1 pt-2 text-gray-400">
				<FontAwesomeIcon :icon="faFrown" class="text-2xl" fixed-width aria-hidden="true" />
				{{ ctrans("Nothing is growing") }}
			</div>

			<div class="mx-3 my-1 border-t border-gray-100" />

			<div v-if="isAnythingFalling" class="grid gap-4 px-3 pb-2" :style="gridColumns">
				<div v-for="column in columns" :key="column.kind" class="grid min-w-0 content-start items-center justify-start gap-x-1.5 tabular-nums" :style="{ gridTemplateColumns: column.template }">
					<div v-for="row in column.falling" :key="row.label" class="contents">
						<FontAwesomeIcon :icon="faArrowDown" class="py-0.5 text-red-600" fixed-width aria-hidden="true" />
						<Link v-if="row.href" v-tooltip="row.name" :href="row.href" class="truncate font-medium hover:underline">{{ row.label }}</Link>
						<span v-else v-tooltip="row.name" class="truncate font-medium">{{ row.label }}</span>
						<span v-tooltip="isWide ? null : tooltip(row)" class="whitespace-nowrap pl-2 text-right text-red-600">{{ display(row) }}</span>
						<span v-if="isWide" class="whitespace-nowrap pl-1 text-right text-red-600/70">{{ tooltip(row) }}</span>
					</div>
					<div v-if="!column.falling.length" class="col-span-full py-0.5 text-gray-400">—</div>
				</div>
			</div>
			<div v-else class="flex flex-col items-center justify-center gap-1 pb-2 text-gray-400">
				<FontAwesomeIcon :icon="faSmile" class="text-2xl" fixed-width aria-hidden="true" />
				{{ ctrans("Nothing is falling") }}
			</div>
			</template>
		</template>
		<div v-else class="grid gap-4 p-3">
			<div class="h-32 animate-pulse rounded bg-gray-100" />
			<div class="h-32 animate-pulse rounded bg-gray-100" />
		</div>
	</div>
</template>
