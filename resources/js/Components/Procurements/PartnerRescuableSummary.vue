<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 6 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { computed } from "vue"
import RescueOrderButton from "@/Components/Procurements/RescueOrderButton.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

export interface Rescuable {
	order: { lines: number; cost: number }
	buckets: {
		bucket: "out" | "w1" | "w2" | "w3"
		label: string
		tone: string
		count: number
		bestsellers: number
		lost: number
		cost: number
		order_lines: number
		order_cost: number
		cheapest: number
		order_cheapest: number
		left_out: Record<string, number>
	}[]
	top: {
		code: string
		name: string
		rank: string
		bucket: string
		spare: number | null
		their_stock: number
		quantity: number
		days_of_cover: number | null
		lost: number | null
		hub_name: string | null
		order_quantum: number
	}[]
}

const props = withDefaults(
	defineProps<{
		rescuable: Rescuable
		isHub: boolean
		orgPartnerId: number
		partnerName: string
		currencyCode: string
		seeAllUrl?: string | null
		canCreate?: boolean
		draftReference?: string | null
		onList?: { lines: number; cost: number } | null
		analyseUrl?: string | null
		buttonSize?: string
		orderUrl?: string | null
	}>(),
	{ canCreate: false, draftReference: null, onList: null, analyseUrl: null, buttonSize: "xs", seeAllUrl: null, orderUrl: null }
)

const locale = useLocaleStore()

const wholeMoney = (amount: number) =>
	new Intl.NumberFormat(locale.locale_iso || undefined, {
		style: "currency",
		currency: props.currencyCode,
		currencyDisplay: "narrowSymbol",
		maximumFractionDigits: 0,
	}).format(amount)

const bucketClass: Record<string, string> = {
	out: "bg-red-100 text-red-800",
	w1: "bg-rose-50 text-rose-700",
	w2: "bg-orange-50 text-orange-700",
	w3: "bg-amber-50 text-amber-700",
}

const bucketShortLabel = (bucket: string) =>
	({ out: ctrans("Out"), w1: ctrans("Doomed"), w2: ctrans("Critical"), w3: ctrans("Danger") })[
		bucket
	] ?? bucket

const rescuableTotal = computed(() =>
	props.rescuable.buckets.reduce((total, bucket) => total + bucket.count, 0)
)

const hasRescuable = computed(() => props.rescuable.buckets.some((bucket) => bucket.count > 0))
</script>

<template>
	<section
		v-if="hasRescuable"
		class="overflow-hidden rounded-md ring-1 ring-gray-200">
		<div
			v-tooltip="
				isHub
					? ctrans(
							'Our selling SKOs that are out or about to run out, with nothing on order, that they make'
						)
					: ctrans(
							'Our selling SKOs that are out or about to run out, with nothing on order, that they can spare without putting their own stock at risk'
						)
			"
			class="border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-xs font-medium text-gray-600">
			{{
				isHub
					? ctrans("To order from them")
					: ctrans("They can rescue")
			}}
		</div>
		<div class="space-y-1.5 px-2 py-2">
			<table class="w-full text-xs tabular-nums">
				<thead>
					<tr class="text-left text-gray-500 [&_th]:pb-1 [&_th]:font-normal">
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
						v-for="bucket in rescuable.buckets.filter(
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
				v-if="rescuable.top.length"
				class="pt-1 text-xs text-gray-500">
				{{
					ctrans("Worst :shown of :total, by lost sales", {
						shown: rescuable.top.length,
						total: locale.number(rescuableTotal),
					})
				}}
			</div>
			<table
				v-if="rescuable.top.length"
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
							{{
								isHub
									? ctrans("Their stock")
									: ctrans("Can spare")
							}}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="item in rescuable.top"
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
											: item.bucket === 'w3'
												? 'bg-amber-400'
												: 'bg-orange-400'
								"
								:title="bucketShortLabel(item.bucket)" />
						</td>
						<td class="truncate py-0.5 font-medium text-gray-700">
							{{ item.code }}
							<span
								v-if="item.hub_name"
								v-tooltip="
									ctrans(
										':hub makes this. Order it from :hub unless it is urgent and they cannot ship in time',
										{ hub: item.hub_name }
									)
								"
								class="ml-1 inline-block h-1.5 w-1.5 rounded-full bg-red-500 align-middle" />
						</td>
						<td class="py-0.5 text-gray-600">{{ item.rank }}</td>
						<td class="py-0.5 text-right tabular-nums text-gray-700">
							{{ item.lost ? wholeMoney(item.lost) : "-" }}
						</td>
						<td
							class="py-0.5 text-right font-medium tabular-nums text-gray-900">
							<span
								v-if="item.order_quantum > 1"
								v-tooltip="
									ctrans(
										'Made in batches: ordered in multiples of :quantum SKOs',
										{
											quantum: item.order_quantum,
										}
									)
								"
								class="mr-1 cursor-help text-xs font-normal text-gray-400"
								>×{{ item.order_quantum }}</span
							>
							{{ locale.number(item.quantity) }}
						</td>
						<td class="py-0.5 text-right tabular-nums text-gray-500">
							{{ locale.number(item.spare ?? item.their_stock) }}
						</td>
					</tr>
				</tbody>
			</table>
			<div class="flex items-center gap-3 border-t border-gray-100 pt-2">
				<RescueOrderButton
					v-if="canCreate"
					:orgPartnerId="orgPartnerId"
					:partnerName="partnerName"
					:draftReference="draftReference"
					:currencyCode="currencyCode"
					:buckets="rescuable.buckets"
					:isHub="isHub"
					:onList="onList"
					:analyseUrl="analyseUrl"
					:orderUrl="orderUrl"
					:size="buttonSize" />
				<span
					v-if="rescuable.order.lines"
					v-tooltip="
						ctrans(
							'Lines that would lose sales, and A/B bestsellers, at the suggested order'
						)
					"
					class="text-xs tabular-nums text-gray-500">
					{{
						ctrans(":lines lines ≈ :cost", {
							lines: locale.number(rescuable.order.lines),
							cost: wholeMoney(rescuable.order.cost),
						})
					}}
				</span>
				<Link
					v-if="seeAllUrl"
					:href="seeAllUrl"
					class="ml-auto whitespace-nowrap text-xs font-medium text-indigo-600 hover:underline">
					{{
						ctrans("See all :total", {
							total: locale.number(rescuableTotal),
						})
					}}
					→
				</Link>
			</div>
		</div>
	</section>
</template>
