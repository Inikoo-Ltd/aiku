<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Deferred, Head, Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PartnerRescuableSummary, { type Rescuable } from "@/Components/Procurements/PartnerRescuableSummary.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { RouteParams } from "@/types/route-params"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faUsersClass,
	faShoppingBasket,
	faPencil,
	faPlus,
	faTruckContainer,
	faClipboardList,
	faIndustryAlt,
	faHandshake,
} from "@fal"
library.add(
	faUsersClass,
	faShoppingBasket,
	faPencil,
	faPlus,
	faTruckContainer,
	faClipboardList,
	faIndustryAlt,
	faHandshake
)

interface CurrentItem {
	type: "purchase_order" | "stock_delivery" | "shopping_list"
	reference: string
	state: string
	state_label: string
	lines: number
	value?: number
	date: string
	url: string
}

interface PartnerCard {
	id: number
	code: string
	name: string
	country_code: string | null
	country_name: string | null
	currency_code: string | null
	is_hub: boolean
	stats: {
		open_shopping_list_items?: number
		open_shopping_list_items_value?: number
		purchase_orders?: number
		last_submitted_at?: string | null
		current?: CurrentItem[]
		rescuable?: Rescuable
	}
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	currency_code: string
	can_create_purchase_orders?: boolean
	partners: PartnerCard[]
	rescuable?: Record<number, Rescuable>
}>()

const locale = useLocaleStore()

const wholeMoney = (amount: number) =>
	new Intl.NumberFormat(locale.locale_iso || undefined, {
		style: "currency",
		currency: props.currency_code,
		currencyDisplay: "narrowSymbol",
		maximumFractionDigits: 0,
	}).format(amount)
const organisation = (route().params as RouteParams).organisation

const partnerUrl = (partner: PartnerCard, routeName = "grp.org.procurement.org_partners.show") =>
	route(routeName, [organisation, partner.id])

const shortDate = (date: string) => useFormatTime(date, { formatTime: "d MMM yyyy" })

const stateClass = (item: CurrentItem) => {
	if (
		(item.type === "purchase_order" && item.state === "in_process") ||
		(item.type === "shopping_list" && item.state === "draft")
	) {
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

const ongoingPoOf = (partner: PartnerCard) => {
	const draft = partner.stats.current?.find(
		(item) => item.type === "shopping_list" && item.state === "draft"
	)

	return { lines: draft?.lines ?? 0, cost: draft?.value ?? 0 }
}

const creatingFor = ref<number | null>(null)
const purchaseOrderInProcess = (partner: PartnerCard) =>
	partner.stats.current?.find(
		(item) => item.type === "purchase_order" && item.state === "in_process"
	)

const olderPurchaseOrders = (partner: PartnerCard) =>
	(partner.stats.purchase_orders ?? 0) -
	(partner.stats.current?.filter((item) => item.type === "purchase_order").length ?? 0)

const createPurchaseOrder = (partner: PartnerCard) => {
	router.post(
		route("grp.models.org-partner.purchase-order.store", { orgPartner: partner.id }),
		{},
		{
			onStart: () => (creatingFor.value = partner.id),
			onFinish: () => (creatingFor.value = null),
			onError: (errors) => {
				notify({
					title: ctrans("No purchase order created"),
					text:
						Object.values(errors)[0] ??
						ctrans("Something went wrong, please try again"),
					type: "error",
				})
			},
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="grid max-w-[120rem] gap-4 p-4 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
		<div
			v-for="partner in partners"
			:key="partner.id"
			class="flex flex-col rounded-lg border bg-white shadow-sm"
			:class="partner.is_hub ? 'border-indigo-300' : 'border-gray-200'">
			<div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
				<img
					v-if="partner.country_code"
					:src="'/flags/' + partner.country_code.toLowerCase() + '.png'"
					:alt="partner.country_name ?? ''"
					:title="partner.country_name ?? ''"
					class="h-4 w-auto shrink-0" />
				<div class="min-w-0 flex-1">
					<Link
						:href="partnerUrl(partner)"
						class="block truncate font-semibold text-gray-900 hover:underline"
						>{{ partner.name }}</Link
					>
					<div class="text-xs text-gray-500">
						{{ partner.code }} · {{ partner.currency_code }}
					</div>
				</div>
				<FontAwesomeIcon
					v-tooltip="
						partner.is_hub
							? ctrans(
									'Manufacturing hub: order with the shopping list, the hub picks and sends it'
								)
							: ctrans(
									'Sister company: order with a purchase order, it goes straight to their warehouse'
								)
					"
					:icon="partner.is_hub ? 'fal fa-industry-alt' : 'fal fa-handshake'"
					class="shrink-0 cursor-help"
					:class="partner.is_hub ? 'text-indigo-500' : 'text-gray-400'"
					fixed-width
					:aria-label="
						partner.is_hub ? ctrans('Manufacturing hub') : ctrans('Sister company')
					" />
			</div>

			<div class="flex-1 space-y-3 px-4 py-3">
				<Deferred data="rescuable">
					<template #fallback>
						<div class="h-40 animate-pulse rounded-md bg-gray-100" />
					</template>
				<PartnerRescuableSummary
					v-if="rescuable?.[partner.id]"
					:rescuable="rescuable[partner.id]"
					:isHub="partner.is_hub"
					:orgPartnerId="partner.id"
					:partnerName="partner.name"
					:currencyCode="currency_code"
					:seeAllUrl="partnerUrl(partner, 'grp.org.procurement.org_partners.show.rescue.index')"
					:canCreate="can_create_purchase_orders"
					:draftReference="purchaseOrderInProcess(partner)?.reference"
					:onList="partner.is_hub ? ongoingPoOf(partner) : null"
					:analyseUrl="
						partner.is_hub
							? partnerUrl(partner, 'grp.org.procurement.org_partners.show.shopping.dashboard')
							: null
					" />
				</Deferred>
				<section class="overflow-hidden rounded-md ring-1 ring-gray-200">
					<div
						class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-xs">
						<span class="font-medium text-gray-600">{{
							ctrans("Open and on the way")
						}}</span>
						<span v-if="partner.stats.last_submitted_at" class="ml-auto text-gray-500">
							{{ ctrans("Last order") }}
							<span class="tabular-nums text-gray-700">{{
								shortDate(partner.stats.last_submitted_at)
							}}</span>
						</span>
					</div>
					<ul v-if="partner.stats.current?.length" class="divide-y divide-gray-100">
						<li v-for="item in partner.stats.current" :key="item.type + item.reference">
							<Link
								:href="item.url"
								class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50">
								<FontAwesomeIcon
									:icon="
										item.type === 'stock_delivery'
											? 'fal fa-truck-container'
											: item.type === 'shopping_list'
												? 'fal fa-shopping-basket'
												: 'fal fa-clipboard-list'
									"
									class="text-gray-500"
									fixed-width
									:title="
										item.type === 'stock_delivery'
											? ctrans('Stock delivery')
											: item.type === 'shopping_list'
												? ctrans('Basket')
												: ctrans('Purchase order')
									" />
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
											· {{ wholeMoney(item.value) }}
										</template>
										· {{ shortDate(item.date) }}
									</div>
								</div>
								<span
									class="shrink-0 whitespace-nowrap rounded px-1.5 text-xs"
									:class="stateClass(item)"
									>{{ item.state_label }}</span
								>
							</Link>
						</li>
					</ul>
					<div v-else class="px-2 py-2 text-xs text-gray-500">
						{{ ctrans("Nothing open or on the way") }}
					</div>
				</section>
			</div>

			<div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3">
				<Link
					v-if="partner.is_hub"
					:href="
						partnerUrl(
							partner,
							'grp.org.procurement.org_partners.show.shopping.dashboard'
						)
					">
					<Button :label="ctrans('Go shopping')" icon="fal fa-shopping-basket" size="s" />
				</Link>
				<Button
					v-else-if="can_create_purchase_orders && !purchaseOrderInProcess(partner)"
					:label="ctrans('New purchase order')"
					icon="fal fa-plus"
					size="s"
					:loading="creatingFor === partner.id"
					class="whitespace-nowrap"
					@click="createPurchaseOrder(partner)" />
				<Link
					v-else-if="can_create_purchase_orders && purchaseOrderInProcess(partner)"
					:href="purchaseOrderInProcess(partner)!.url">
					<Button
						:label="
							ctrans('Add items to :reference', {
								reference: purchaseOrderInProcess(partner)!.reference,
							})
						"
						icon="fal fa-plus"
						type="secondary"
						size="s"
						class="whitespace-nowrap" />
				</Link>
				<Link
					v-if="partner.is_hub"
					:href="
						partnerUrl(
							partner,
							'grp.org.procurement.org_partners.show.shopping_list.index'
						)
					"
					class="ml-auto whitespace-nowrap text-gray-500 hover:text-gray-900 hover:underline">
					{{ ctrans("Basket") }}
				</Link>
				<Link
					v-else-if="olderPurchaseOrders(partner)"
					:href="
						partnerUrl(
							partner,
							'grp.org.procurement.org_partners.show.purchase-orders.index'
						)
					"
					class="ml-auto whitespace-nowrap text-gray-500 hover:text-gray-900 hover:underline">
					{{ ctrans("Older purchase orders") }}
					<span class="tabular-nums"
						>({{ locale.number(olderPurchaseOrders(partner)) }})</span
					>
				</Link>
			</div>
		</div>

		<p v-if="!partners.length" class="text-gray-500">{{ ctrans("No partners found") }}</p>
	</div>
</template>
