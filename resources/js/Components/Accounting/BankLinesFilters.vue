<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import DatePicker from "primevue/datepicker"
import InputNumber from "primevue/inputnumber"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

const props = defineProps<{
	organisationId: number | null
	organisation: string | null
	organisations: { value: string; label: string }[]
	summary: { count: number; total: number | null }
	currencyTotals: Record<string, { count: number; total: number }>
	orgCurrency: string
	min: number | null
	max: number | null
	period: "90d" | "1y" | "all" | null
	currency: string | null
	currencies: string[]
	from: string | null
	to: string | null
	extraQuery?: Record<string, string | null>
	summaryNoun?: [string, string]
}>()

const locale = useLocaleStore()

const shortAmount = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.orgCurrency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: Math.abs(amount) >= 1000 ? 1 : 0 }).format(amount)
const periodOptions = [
	{ label: ctrans("Last 90 days"), value: "90d" },
	{ label: ctrans("Last year"), value: "1y" },
	{ label: ctrans("All"), value: "all" },
]
const currencyOptions = computed(() => [
	{ label: ctrans("All"), value: "all" },
	...props.currencies.filter((code) => props.currencyTotals[code]?.count || code === props.currency).map((code) => ({
		label: code,
		value: code,
		count: props.currencyTotals[code]?.count ?? 0,
		note: props.currencyTotals[code]?.total ? shortAmount(props.currencyTotals[code].total) : undefined,
	})),
])

const reload = (query: Record<string, string | number | null>) => {
	const params = { ...props.extraQuery, org: props.organisation, period: props.period, currency: props.currency, from: props.from, to: props.to, min: props.min, max: props.max, ...query }
	router.get(
		route(route().current() as string, props.organisationId ? [route().params["organisation"]] : []),
		Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== "")),
		{ preserveState: false }
	)
}
const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => reload({ org: value === "all" ? null : value }),
})
const period = computed({
	get: () => props.period,
	set: (value: string) => reload({ period: value, from: null, to: null }),
})
const currency = computed({
	get: () => props.currency ?? "all",
	set: (value: string) => reload({ currency: value === "all" ? null : value }),
})

const fromIsoDate = (iso: string): Date => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const dateRange = computed<(Date | null)[] | null>({
	get: () => (props.from ? [fromIsoDate(props.from), props.to ? fromIsoDate(props.to) : null] : null),
	set: (value) => {
		if (!value?.[0]) {
			reload({ from: null, to: null })
		} else if (value[1]) {
			reload({ from: toLocalIsoDate(value[0]), to: toLocalIsoDate(value[1]) })
		}
	},
})
const amountRange = ref<{ min: number | null; max: number | null }>({ min: props.min, max: props.max })
const applyAmountRange = () => {
	if (amountRange.value.min !== props.min || amountRange.value.max !== props.max) {
		reload({ min: amountRange.value.min, max: amountRange.value.max })
	}
}
const amountInputPt = { pcInputText: { root: { class: "!w-24 !py-1 !text-xs" } } }

const datePickerPt = { pcInputText: { root: { class: "!w-56 !py-1 !text-xs" } } }
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
</script>

<template>
	<div class="flex flex-wrap items-center gap-3 px-4 pt-3">
		<SegmentedToggle v-if="organisations.length > 1" v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
		<slot :reload="reload" />
		<SegmentedToggle v-model="period" :options="periodOptions" soft :aria-label="ctrans('Received in')" />
		<DatePicker
			v-model="dateRange"
			selectionMode="range"
			:manualInput="false"
			dateFormat="d M yy"
			showIcon
			iconDisplay="input"
			showButtonBar
			:placeholder="ctrans('From – to')"
			:class="fieldFocusClass"
			:pt="datePickerPt"
			:aria-label="ctrans('Received between')"
		/>
		<div class="flex items-center gap-1 text-xs text-gray-500">
			<InputNumber v-model="amountRange.min" :min="0" :maxFractionDigits="2" :placeholder="ctrans('Min')" :class="fieldFocusClass" :pt="amountInputPt" :aria-label="ctrans('Smallest amount')" @blur="applyAmountRange" @keydown.enter="applyAmountRange" />
			<span>–</span>
			<InputNumber v-model="amountRange.max" :min="0" :maxFractionDigits="2" :placeholder="ctrans('Max')" :class="fieldFocusClass" :pt="amountInputPt" :aria-label="ctrans('Largest amount')" @blur="applyAmountRange" @keydown.enter="applyAmountRange" />
			<span>{{ orgCurrency }}</span>
		</div>
		<SegmentedToggle v-if="currencies.length > 1" v-model="currency" :options="currencyOptions" soft :aria-label="ctrans('Currency')" />
		<span v-if="summary.count" class="ml-auto whitespace-nowrap text-xs tabular-nums text-gray-500">
			{{ ctrans(summary.count === 1 ? (summaryNoun?.[0] ?? ":count payment") : (summaryNoun?.[1] ?? ":count payments"), { count: locale.number(summary.count) }) }}<template v-if="summary.total"> · {{ locale.currencyFormat(orgCurrency, summary.total) }}</template>
		</span>
	</div>
</template>
