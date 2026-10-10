<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 9 Oct 2026 Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faIndustryAlt, faHandshake, faPeopleArrows, faClipboardList, faShip } from "@fal"
library.add(faIndustryAlt, faHandshake, faPeopleArrows, faClipboardList, faShip)

interface CounterpartyCard {
	type: "hub" | "partner" | "agent"
	name: string
	country_code: string | null
	out: number
	doomed: number
	lost: number
	orders: number
	orders_value: number
	containers: number
	url: string
}

const props = defineProps<{
	counterparties: { currency: string; cards: CounterpartyCard[] }
}>()

const locale = useLocaleStore()

const wholeMoney = (amount: number) =>
	new Intl.NumberFormat(locale.locale_iso || undefined, {
		style: "currency",
		currency: props.counterparties.currency,
		currencyDisplay: "narrowSymbol",
		maximumFractionDigits: 0,
	}).format(amount)

const typeMeta = {
	hub: { icon: "fal fa-industry-alt", label: ctrans("Manufacturing hub") },
	partner: { icon: "fal fa-handshake", label: ctrans("Partner") },
	agent: { icon: "fal fa-people-arrows", label: ctrans("Agent") },
}
</script>

<template>
	<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
		<Link
			v-for="card in counterparties.cards"
			:key="card.type + card.name"
			:href="card.url"
			class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-600 shadow-sm hover:border-gray-300 hover:shadow">
			<div class="flex items-center gap-2">
				<img
					v-if="card.country_code"
					:src="'/flags/' + card.country_code.toLowerCase() + '.png'"
					:alt="card.country_code"
					class="h-3.5 w-auto shrink-0" />
				<span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900">{{
					card.name
				}}</span>
				<FontAwesomeIcon
					v-tooltip="typeMeta[card.type].label"
					:icon="typeMeta[card.type].icon"
					class="shrink-0 text-gray-400"
					fixed-width />
			</div>

			<div class="flex flex-wrap items-center gap-1.5 tabular-nums">
				<span v-if="card.out" class="rounded bg-red-100 px-1.5 py-0.5 text-red-800">
					{{ ctrans("Out") }} <span class="font-semibold">{{ locale.number(card.out) }}</span>
				</span>
				<span v-if="card.doomed" class="rounded bg-rose-50 px-1.5 py-0.5 text-rose-700">
					{{ ctrans("Doomed") }}
					<span class="font-semibold">{{ locale.number(card.doomed) }}</span>
				</span>
				<span v-if="!card.out && !card.doomed" class="text-emerald-700">{{
					ctrans("Nothing running out")
				}}</span>
				<span v-if="card.lost" class="ml-auto font-semibold text-red-700">
					{{ wholeMoney(card.lost) }}
					<span class="font-normal text-gray-500">{{ ctrans("lost sales") }}</span>
				</span>
			</div>

			<div class="flex items-center gap-3 border-t border-gray-100 pt-2 tabular-nums">
				<span>
					<FontAwesomeIcon icon="fal fa-clipboard-list" class="text-gray-400" fixed-width />
					{{ ctrans(":count open orders", { count: locale.number(card.orders) }) }}
					<template v-if="card.orders_value"> · {{ wholeMoney(card.orders_value) }}</template>
				</span>
				<span class="ml-auto">
					<FontAwesomeIcon icon="fal fa-ship" class="text-gray-400" fixed-width />
					{{ ctrans(":count containers", { count: locale.number(card.containers) }) }}
				</span>
			</div>
		</Link>
	</div>
</template>
