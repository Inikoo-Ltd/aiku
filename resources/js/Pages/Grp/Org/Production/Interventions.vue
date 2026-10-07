<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 7 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import PartnerRescuableSummary, { type Rescuable } from "@/Components/Procurements/PartnerRescuableSummary.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { RouteParams } from "@/types/route-params"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHandsHelping, faShoppingBasket, faTruckContainer } from "@fal"
library.add(faHandsHelping, faShoppingBasket, faTruckContainer)

interface CurrentItem {
	type: "shopping_list" | "stock_delivery"
	reference: string
	state: string
	state_label: string
	lines: number
	value?: number
	date: string
}

interface BuyerCard {
	id: number
	code: string
	name: string
	country_code: string | null
	country_name: string | null
	currency_code: string
	rescuable: Rescuable
	on_list: { lines: number; cost: number }
	current: CurrentItem[]
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	can_order: boolean
	production: string
	buyers: BuyerCard[]
}>()

const locale = useLocaleStore()
const organisation = (route().params as RouteParams).organisation

const wholeMoney = (amount: number, currencyCode: string) =>
	new Intl.NumberFormat(locale.locale_iso || undefined, {
		style: "currency",
		currency: currencyCode,
		currencyDisplay: "narrowSymbol",
		maximumFractionDigits: 0,
	}).format(amount)

const shortDate = (date: string) => useFormatTime(date, { formatTime: "d MMM yyyy" })

const orderUrl = (buyer: BuyerCard) =>
	route("grp.org.productions.show.intervention.order", [organisation, props.production, buyer.id])

const stateClass = (item: CurrentItem) => {
	if (item.type === "shopping_list" && item.state === "draft") {
		return "bg-amber-100 text-amber-800"
	}
	if (item.state === "dispatched") {
		return "bg-sky-100 text-sky-800"
	}
	if (["received", "checked", "booking_in"].includes(item.state)) {
		return "bg-emerald-100 text-emerald-800"
	}
	return "bg-gray-100 text-gray-700"
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<p class="max-w-3xl px-4 pt-3 text-sm text-gray-500">
		{{
			ctrans(
				"What each partner should order from us, as their buyers see it. Prepare order adds the lines to their ongoing PO as our suggestions, never changing lines they added. Their buyers are told, and they decide: submit to keep them, or drop them."
			)
		}}
	</p>

	<div class="grid max-w-[120rem] gap-4 p-4 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
		<div
			v-for="buyer in buyers"
			:key="buyer.id"
			class="flex flex-col rounded-lg border border-gray-200 bg-white shadow-sm">
			<div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
				<img
					v-if="buyer.country_code"
					:src="'/flags/' + buyer.country_code.toLowerCase() + '.png'"
					:alt="buyer.country_name ?? ''"
					:title="buyer.country_name ?? ''"
					class="h-4 rounded-sm ring-1 ring-gray-200" />
				<div class="min-w-0 flex-1">
					<div class="truncate font-semibold text-gray-900">{{ buyer.name }}</div>
					<div class="text-xs text-gray-500">
						{{ buyer.code }} · {{ buyer.currency_code }}
					</div>
				</div>
			</div>

			<div class="flex-1 space-y-3 px-4 py-3">
				<PartnerRescuableSummary
					:rescuable="buyer.rescuable"
					:isHub="true"
					:orgPartnerId="buyer.id"
					:partnerName="buyer.name"
					:currencyCode="buyer.currency_code"
					:canCreate="can_order"
					:onList="buyer.on_list"
					:orderUrl="orderUrl(buyer)" />
				<p
					v-if="!buyer.rescuable.buckets.some((bucket) => bucket.count > 0)"
					class="text-xs text-gray-500">
					{{ ctrans("Nothing they need to order from us right now") }}
				</p>

				<section class="overflow-hidden rounded-md ring-1 ring-gray-200">
					<div
						class="border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-xs font-medium text-gray-600">
						{{ ctrans("Open and on the way") }}
					</div>
					<ul v-if="buyer.current.length" class="divide-y divide-gray-100">
						<li
							v-for="item in buyer.current"
							:key="item.type + item.reference"
							class="flex items-center gap-2 px-2 py-1.5">
							<FontAwesomeIcon
								:icon="
									item.type === 'stock_delivery'
										? 'fal fa-truck-container'
										: 'fal fa-shopping-basket'
								"
								class="text-gray-500"
								fixed-width
								aria-hidden="true" />
							<div class="min-w-0 flex-1">
								<div class="truncate font-medium text-gray-900">
									{{ item.reference }}
								</div>
								<div class="text-xs tabular-nums text-gray-500">
									{{
										item.lines
											? ctrans(":count lines", { count: item.lines })
											: ctrans("empty")
									}}
									<template v-if="item.value !== undefined">
										· {{ wholeMoney(item.value, buyer.currency_code) }}
									</template>
									· {{ shortDate(item.date) }}
								</div>
							</div>
							<span
								class="shrink-0 whitespace-nowrap rounded px-1.5 text-xs"
								:class="stateClass(item)"
								>{{ item.state_label }}</span
							>
						</li>
					</ul>
					<div v-else class="px-2 py-2 text-xs text-gray-500">
						{{ ctrans("Nothing open or on the way") }}
					</div>
				</section>
			</div>
		</div>

		<p v-if="!buyers.length" class="text-gray-500">{{ ctrans("No partners buy from us") }}</p>
	</div>
</template>
