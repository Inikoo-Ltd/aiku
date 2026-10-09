<script setup lang="ts">
import { inject } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

interface ContainerCard {
	id: number
	reference: string
	organisation: string | null
	state_label: string
	purchase_orders: number
	items: number
	cbm: number | null
	gross_weight: number | null
	amount: number
	currency: string | null
	days_in_state: number
	route: routeType
}

defineProps<{
	title: string
	pageHead: PageHeadingTypes
	columns: { key: string; label: string; cards: ContainerCard[] }[]
}>()

const locale = inject("locale", aikuLocaleStructure)

const daysClass = (days: number) => (days >= 30 ? "text-red-600" : days >= 14 ? "text-amber-600" : "text-gray-500")
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="mx-4 mt-4 flex gap-4 overflow-x-auto pb-4">
			<section v-for="column in columns" :key="column.key" class="flex w-72 shrink-0 flex-col rounded-lg bg-gray-50 ring-1 ring-gray-200">
				<header class="flex items-center justify-between px-3 py-2.5">
					<h2 class="text-sm font-semibold text-gray-700">{{ column.label }}</h2>
					<span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium tabular-nums text-gray-600 ring-1 ring-gray-200">{{ column.cards.length }}</span>
				</header>
				<div class="flex flex-col gap-2 px-2 pb-2">
					<p v-if="!column.cards.length" class="px-2 py-6 text-center text-xs text-gray-400">{{ ctrans("No containers") }}</p>
					<article v-for="card in column.cards" :key="card.id" class="rounded-md bg-white p-3 shadow-sm ring-1 ring-gray-200">
						<div class="flex items-start justify-between gap-2">
							<Link :href="route(card.route.name, card.route.parameters)" class="font-semibold text-gray-900 hover:underline">
								{{ card.reference }}
							</Link>
							<span class="shrink-0 text-xs tabular-nums" :class="daysClass(card.days_in_state)">
								{{ ctrans(":days d", { days: card.days_in_state }) }}
							</span>
						</div>
						<p class="mt-0.5 text-sm text-gray-600">{{ card.organisation }}</p>
						<p v-if="column.key === 'arrived'" class="text-xs text-gray-400">{{ card.state_label }}</p>
						<dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
							<dt class="text-gray-400">{{ ctrans("Purchase orders") }}</dt>
							<dd class="text-right tabular-nums text-gray-700">{{ locale.number(card.purchase_orders) }}</dd>
							<dt class="text-gray-400">{{ ctrans("Items") }}</dt>
							<dd class="text-right tabular-nums text-gray-700">{{ locale.number(card.items) }}</dd>
							<dt class="text-gray-400">{{ ctrans("CBM") }}</dt>
							<dd class="text-right tabular-nums text-gray-700">{{ card.cbm !== null ? locale.number(card.cbm) : "-" }}</dd>
							<dt class="text-gray-400">{{ ctrans("Weight") }}</dt>
							<dd class="text-right tabular-nums text-gray-700">{{ card.gross_weight !== null ? locale.number(Math.round(card.gross_weight)) + " kg" : "-" }}</dd>
							<dt class="text-gray-400">{{ ctrans("Value") }}</dt>
							<dd class="text-right tabular-nums font-medium text-gray-800">{{ card.currency ? locale.currencyFormat(card.currency, card.amount) : locale.number(card.amount) }}</dd>
						</dl>
					</article>
				</div>
			</section>
		</div>
	</div>
</template>
