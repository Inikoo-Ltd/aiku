<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, inject, ref, watch } from "vue"
import Select from "primevue/select"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSeedling, faPaperPlane, faCheckCircle, faCheckDouble, faExclamationTriangle } from "@fal"

library.add(faSeedling, faPaperPlane, faCheckCircle, faCheckDouble, faExclamationTriangle)

interface Card {
	id: number
	reference: string
	supplier: string
	organisation: string | null
	agent_order: string | null
	amount: number
	days_waiting: number
	is_overdue: boolean
	route: routeType
}

interface Column {
	key: string
	label: string
	icon: string
	count: number
	total: number
	cards: Card[]
	route: routeType
}

interface Option {
	value: number
	label: string
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: {
		currency: string
		settled_days: number
		columns: Column[]
		suppliers: Option[]
		organisations: Option[]
		filters: { supplier: number | null; organisation: number | null }
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (amount: number) => locale.currencyFormat(props.data.currency, amount)
const href = (target: routeType) => route(target.name, target.parameters)
const waiting = (days: number) => (days === 1 ? ctrans("1 day") : ctrans(":count days", { count: days }))

const columnClasses: Record<string, string> = {
	in_process: "bg-gray-100 border-t-4 border-gray-400",
	submitted: "bg-amber-50 border-t-4 border-amber-400",
	confirmed: "bg-gray-100 border-t-4 border-gray-500",
	settled: "bg-green-50 border-t-4 border-green-500",
}

const waitingClass = (column: Column, card: Card) => {
	if (column.key === "settled") return "text-gray-400"
	if (card.days_waiting >= 30) return "text-red-600 font-medium"
	if (card.days_waiting >= 7) return "text-amber-600"
	return "text-gray-500"
}

const supplier = ref<number | null>(props.data.filters.supplier)
const organisation = ref<number | null>(props.data.filters.organisation)

watch([supplier, organisation], ([supplierId, organisationId]) => {
	router.get(
		window.location.pathname,
		{ supplier: supplierId || undefined, organisation: organisationId || undefined },
		{ preserveState: true, preserveScroll: true, replace: true }
	)
})

const fieldClass = "h-9 w-64 items-center text-sm [&.p-focus]:!border-[--app-accent]"

const hasFilters = computed(() => supplier.value || organisation.value)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<div class="min-w-0 p-4">
		<div class="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm">
			<label class="flex items-center gap-2">
				<span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Supplier") }}</span>
				<Select v-model="supplier" :options="data.suppliers" optionLabel="label" optionValue="value" filter showClear :placeholder="ctrans('All suppliers')" :class="fieldClass" />
			</label>
			<label class="flex items-center gap-2">
				<span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Client") }}</span>
				<Select v-model="organisation" :options="data.organisations" optionLabel="label" optionValue="value" showClear :placeholder="ctrans('All organisations')" :class="fieldClass" />
			</label>
			<button v-if="hasFilters" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="supplier = null; organisation = null">
				× {{ ctrans("Clear") }}
			</button>
			<span class="ml-auto text-xs text-gray-400">{{ ctrans("Settled orders stay on the board for :days days", { days: data.settled_days }) }}</span>
		</div>

		<div class="-mx-4 overflow-x-auto px-4 pb-2 [scrollbar-width:thin]">
			<div class="flex min-w-max gap-3">
				<div v-for="column in data.columns" :key="column.key" class="flex w-72 flex-col rounded-lg p-2" :class="columnClasses[column.key]">
					<div class="flex items-center gap-1.5 whitespace-nowrap px-1 pb-2">
						<FontAwesomeIcon :icon="column.icon" class="text-gray-500" fixed-width aria-hidden="true" />
						<span class="text-sm font-semibold">{{ column.label }}</span>
						<span class="rounded bg-white/70 px-1.5 py-0.5 text-xs tabular-nums text-gray-600">{{ locale.number(column.count) }}</span>
						<span class="ml-auto text-xs tabular-nums text-gray-500">{{ money(column.total) }}</span>
					</div>

					<div class="thinScrollbar max-h-[75vh] min-h-24 flex-1 space-y-2 overflow-y-auto pr-1">
						<Link
							v-for="card in column.cards"
							:key="card.id"
							:href="href(card.route)"
							class="block rounded-md border border-gray-200 bg-white p-2.5 text-sm shadow-sm transition hover:border-gray-400 hover:shadow">
							<div class="flex items-start justify-between gap-2">
								<span class="font-semibold text-gray-900">{{ card.reference }}</span>
								<span class="shrink-0 text-xs tabular-nums" :class="waitingClass(column, card)">{{ waiting(card.days_waiting) }}</span>
							</div>
							<div class="mt-1 truncate text-gray-700" :title="card.supplier">{{ card.supplier }}</div>
							<div class="mt-1 flex items-center justify-between gap-2 text-xs text-gray-500">
								<span class="truncate">{{ card.organisation ?? "" }}</span>
								<span class="shrink-0 tabular-nums text-gray-700">{{ money(card.amount) }}</span>
							</div>
							<div v-if="card.is_overdue && column.key !== 'settled'" class="mt-1 flex items-center gap-1 text-xs text-red-600">
								<FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
								{{ ctrans("Past expected date") }}
							</div>
						</Link>
						<p v-if="!column.cards.length" class="px-1 py-6 text-center text-xs text-gray-400">{{ ctrans("Nothing here") }}</p>
						<Link
							v-if="column.count > column.cards.length"
							:href="href(column.route)"
							class="block rounded-md px-2 py-1.5 text-center text-xs text-gray-600 hover:bg-white/70 hover:underline">
							{{ ctrans("+:count more", { count: locale.number(column.count - column.cards.length) }) }}
						</Link>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
