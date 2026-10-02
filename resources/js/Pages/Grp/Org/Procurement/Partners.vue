<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import RescueOrderButton from "@/Components/Procurements/RescueOrderButton.vue"
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
	type: "purchase_order" | "stock_delivery"
	reference: string
	state: string
	state_label: string
	lines: number
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
		rescuable?: {
			order: { lines: number; cost: number }
			buckets: {
				bucket: string
				label: string
				tone: string
				count: number
				bestsellers: number
				lost: number
				cost: number
				order_lines: number
				order_cost: number
				left_out: Record<string, number>
			}[]
			top: {
				code: string
				name: string
				rank: string
				bucket: string
				spare: number
				quantity: number
				days_of_cover: number | null
				lost: number | null
			}[]
		}
	}
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	currency_code: string
	partners: PartnerCard[]
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
	if (item.type === "purchase_order" && item.state === "in_process") {
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

const bucketClass: Record<string, string> = {
	out: "bg-red-100 text-red-800",
	w1: "bg-rose-50 text-rose-700",
	w2: "bg-orange-50 text-orange-700",
}

const bucketShortLabel = (bucket: string) =>
	({ out: ctrans("Out"), w1: ctrans("Doomed"), w2: ctrans("Critical") })[bucket] ?? bucket

const rescuableTotal = (partner: PartnerCard) =>
	partner.stats.rescuable?.buckets.reduce((total, bucket) => total + bucket.count, 0) ?? 0

const hasRescuable = (partner: PartnerCard) =>
	partner.stats.rescuable?.buckets.some((bucket) => bucket.count > 0)

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
					class="h-4 rounded-sm ring-1 ring-gray-200" />
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
				<template v-if="partner.is_hub">
					<div
						v-if="partner.stats.open_shopping_list_items"
						class="flex items-center gap-2">
						<FontAwesomeIcon
							icon="fal fa-shopping-basket"
							class="text-gray-500"
							fixed-width
							aria-hidden="true" />
						<span class="font-medium tabular-nums">{{
							locale.number(partner.stats.open_shopping_list_items)
						}}</span>
						<span class="text-gray-500">{{ ctrans("on the list") }}</span>
						<span
							class="ml-auto font-semibold tabular-nums text-gray-900"
							:title="
								ctrans('In :currency, your currency', { currency: currency_code })
							">
							{{ wholeMoney(partner.stats.open_shopping_list_items_value ?? 0) }}
						</span>
					</div>
					<div v-else class="text-gray-500">
						{{ ctrans("The shopping list is empty") }}
					</div>
				</template>

				<template v-else>
					<section
						v-if="hasRescuable(partner)"
						class="overflow-hidden rounded-md ring-1 ring-gray-200">
						<div
							v-tooltip="
								ctrans(
									'Our selling SKOs that are out or about to run out, with nothing on order, that they can spare without putting their own stock at risk'
								)
							"
							class="border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-xs font-medium text-gray-600">
							{{ ctrans("They can rescue") }}
						</div>
						<div class="space-y-1.5 px-2 py-2">
							<table class="w-full text-xs tabular-nums">
								<thead>
									<tr
										class="text-left text-gray-500 [&_th]:pb-1 [&_th]:font-normal">
										<th />
										<th class="text-right">{{ ctrans("SKOs") }}</th>
										<th
											v-tooltip="
												ctrans(
													'Sales lost over the lead time and a month after, if nothing more is ordered'
												)
											"
											class="text-right">
											{{ ctrans("Lost sales") }}
										</th>
										<th
											v-tooltip="
												ctrans(
													'What the suggested order costs at their price, in our currency'
												)
											"
											class="text-right">
											{{ ctrans("Cost to reorder") }}
										</th>
									</tr>
								</thead>
								<tbody>
									<tr
										v-for="bucket in partner.stats.rescuable!.buckets.filter(
											(bucket) => bucket.count
										)"
										:key="bucket.bucket"
										v-tooltip="
											bucket.label +
											' · ' +
											ctrans(':count bestsellers (A/B)', {
												count: bucket.bestsellers,
											})
										">
										<td class="py-0.5">
											<span
												class="rounded px-1.5 py-0.5"
												:class="bucketClass[bucket.bucket]">
												{{ bucketShortLabel(bucket.bucket) }}
											</span>
										</td>
										<td class="py-0.5 text-right font-medium text-gray-900">
											{{ locale.number(bucket.count) }}
										</td>
										<td class="py-0.5 text-right">
											{{ bucket.lost ? wholeMoney(bucket.lost) : "-" }}
										</td>
										<td class="py-0.5 text-right">
											{{ bucket.cost ? wholeMoney(bucket.cost) : "-" }}
										</td>
									</tr>
								</tbody>
							</table>
							<div
								v-if="partner.stats.rescuable!.top.length"
								class="pt-1 text-xs text-gray-500">
								{{
									ctrans("Worst :shown of :total, by lost sales", {
										shown: partner.stats.rescuable!.top.length,
										total: locale.number(rescuableTotal(partner)),
									})
								}}
							</div>
							<table
								v-if="partner.stats.rescuable!.top.length"
								class="w-full table-fixed text-xs [&_td]:pr-2 [&_th]:pr-2 [&_td:last-child]:pr-0 [&_th:last-child]:pr-0">
								<thead>
									<tr class="text-left text-gray-500">
										<th class="w-3 font-normal" />
										<th class="w-24 font-normal">{{ ctrans("SKO") }}</th>
										<th class="w-12 font-normal">{{ ctrans("Rank") }}</th>
										<th
											v-tooltip="
												ctrans(
													'Sales lost over the lead time and a month after, if nothing more is ordered'
												)
											"
											class="text-right font-normal">
											{{ ctrans("Lost sales") }}
										</th>
										<th
											v-tooltip="
												ctrans(
													'Enough to get out of the critical zone, never more than they can spare'
												)
											"
											class="w-24 whitespace-nowrap text-right font-normal">
											{{ ctrans("Suggested order") }}
										</th>
										<th class="w-16 text-right font-normal">
											{{ ctrans("Can spare") }}
										</th>
									</tr>
								</thead>
								<tbody>
									<tr
										v-for="item in partner.stats.rescuable!.top"
										:key="item.code"
										v-tooltip="item.name">
										<td class="py-0.5">
											<span
												class="block h-1.5 w-1.5 rounded-full"
												:class="
													item.bucket === 'out'
														? 'bg-red-500'
														: item.bucket === 'w1'
															? 'bg-rose-400'
															: 'bg-orange-400'
												"
												:title="bucketShortLabel(item.bucket)" />
										</td>
										<td class="truncate py-0.5 font-medium text-gray-700">
											{{ item.code }}
										</td>
										<td class="py-0.5 text-gray-600">{{ item.rank }}</td>
										<td class="py-0.5 text-right tabular-nums text-gray-700">
											{{ item.lost ? wholeMoney(item.lost) : "-" }}
										</td>
										<td
											class="py-0.5 text-right font-medium tabular-nums text-gray-900">
											{{ locale.number(item.quantity) }}
										</td>
										<td class="py-0.5 text-right tabular-nums text-gray-500">
											{{ locale.number(item.spare) }}
										</td>
									</tr>
								</tbody>
							</table>
							<div class="flex items-center gap-3 border-t border-gray-100 pt-2">
								<RescueOrderButton
									:orgPartnerId="partner.id"
									:partnerName="partner.name"
									:draftReference="purchaseOrderInProcess(partner)?.reference"
									:currencyCode="currency_code"
									:buckets="partner.stats.rescuable!.buckets"
									size="xs" />
								<span
									v-if="partner.stats.rescuable!.order.lines"
									v-tooltip="
										ctrans(
											'Lines that would lose sales, and A/B bestsellers, at the suggested order'
										)
									"
									class="text-xs tabular-nums text-gray-500">
									{{
										ctrans(":lines lines ≈ :cost", {
											lines: locale.number(
												partner.stats.rescuable!.order.lines
											),
											cost: wholeMoney(partner.stats.rescuable!.order.cost),
										})
									}}
								</span>
								<Link
									:href="
										partnerUrl(
											partner,
											'grp.org.procurement.org_partners.show.rescue.index'
										)
									"
									class="ml-auto whitespace-nowrap text-xs font-medium text-indigo-600 hover:underline">
									{{
										ctrans("See all :total", {
											total: locale.number(rescuableTotal(partner)),
										})
									}}
									→
								</Link>
							</div>
						</div>
					</section>
					<section class="overflow-hidden rounded-md ring-1 ring-gray-200">
						<div
							class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-xs">
							<span class="font-medium text-gray-600">{{
								ctrans("Open and on the way")
							}}</span>
							<span
								v-if="partner.stats.last_submitted_at"
								class="ml-auto text-gray-500">
								{{ ctrans("Last order") }}
								<span class="tabular-nums text-gray-700">{{
									shortDate(partner.stats.last_submitted_at)
								}}</span>
							</span>
						</div>
						<ul v-if="partner.stats.current?.length" class="divide-y divide-gray-100">
							<li
								v-for="item in partner.stats.current"
								:key="item.type + item.reference">
								<Link
									:href="item.url"
									class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50">
									<FontAwesomeIcon
										:icon="
											item.type === 'stock_delivery'
												? 'fal fa-truck-container'
												: 'fal fa-clipboard-list'
										"
										class="text-gray-500"
										fixed-width
										:title="
											item.type === 'stock_delivery'
												? ctrans('Stock delivery')
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
				</template>
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
					v-else-if="!purchaseOrderInProcess(partner)"
					:label="ctrans('New purchase order')"
					icon="fal fa-plus"
					size="s"
					:loading="creatingFor === partner.id"
					class="whitespace-nowrap"
					@click="createPurchaseOrder(partner)" />
				<Link
					v-if="partner.is_hub"
					:href="
						partnerUrl(
							partner,
							'grp.org.procurement.org_partners.show.shopping_list.index'
						)
					"
					class="ml-auto whitespace-nowrap text-gray-500 hover:text-gray-900 hover:underline">
					{{ ctrans("Shopping list") }}
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
