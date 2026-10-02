<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 2 Oct 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import RescueOrderButton from "@/Components/Procurements/RescueOrderButton.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLifeRing } from "@fal"
library.add(faLifeRing)

interface RescueItem {
	org_stock_id: number
	code: string
	name: string
	rank: string | null
	bucket: "out" | "w1" | "w2"
	our_stock: number
	spare: number
	quantity: number
	days_of_cover: number | null
	lost: number | null
}

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	currency_code: string
	orgPartner: { id: number; name: string }
	items: {
		data: RescueItem[]
		total: number
		from: number | null
		to: number | null
		prev_page_url: string | null
		next_page_url: string | null
	}
}>()

const locale = useLocaleStore()

const wholeMoney = (amount: number) =>
	new Intl.NumberFormat(locale.locale_iso || undefined, {
		style: "currency",
		currency: props.currency_code,
		currencyDisplay: "narrowSymbol",
		maximumFractionDigits: 0,
	}).format(amount)

const bucketLabel = { out: ctrans("Out of stock"), w1: ctrans("Doomed"), w2: ctrans("Critical") }
const bucketClass = {
	out: "bg-red-100 text-red-800",
	w1: "bg-rose-50 text-rose-700",
	w2: "bg-orange-50 text-orange-700",
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<RescueOrderButton :orgPartnerId="orgPartner.id" :partnerName="orgPartner.name" />
		</template>
	</PageHeading>

	<div class="max-w-6xl p-4 text-sm text-gray-700">
		<p class="mb-3 text-gray-500">
			{{
				ctrans(
					"Our selling SKOs that are out or about to run out, with nothing on order, that they can spare without putting their own stock at risk. Worst offenders first."
				)
			}}
		</p>

		<div class="overflow-x-auto rounded-md ring-1 ring-gray-200">
			<table class="w-full text-xs">
				<thead class="bg-gray-50 text-left text-gray-500">
					<tr class="[&_th]:px-3 [&_th]:py-2 [&_th]:font-normal">
						<th>{{ ctrans("SKO") }}</th>
						<th>{{ ctrans("Name") }}</th>
						<th>{{ ctrans("Rank") }}</th>
						<th>{{ ctrans("Status") }}</th>
						<th class="text-right">{{ ctrans("Our stock") }}</th>
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
									'Enough to get out of the critical zone, never more than they can spare'
								)
							"
							class="text-right">
							{{ ctrans("Suggested order") }}
						</th>
						<th class="text-right">{{ ctrans("Can spare") }}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr
						v-for="item in items.data"
						:key="item.org_stock_id"
						class="[&_td]:px-3 [&_td]:py-1.5">
						<td class="whitespace-nowrap font-medium text-gray-900">{{ item.code }}</td>
						<td class="max-w-xs truncate text-gray-500">{{ item.name }}</td>
						<td class="text-gray-600">{{ item.rank ?? "-" }}</td>
						<td>
							<span
								class="whitespace-nowrap rounded px-1.5 py-0.5"
								:class="bucketClass[item.bucket]">
								{{ bucketLabel[item.bucket] }}
							</span>
						</td>
						<td class="text-right tabular-nums">{{ locale.number(item.our_stock) }}</td>
						<td class="text-right tabular-nums">
							{{ item.lost ? wholeMoney(item.lost) : "-" }}
						</td>
						<td class="text-right font-medium tabular-nums text-gray-900">
							{{ locale.number(item.quantity) }}
						</td>
						<td class="text-right tabular-nums text-gray-500">
							{{ locale.number(item.spare) }}
						</td>
					</tr>
					<tr v-if="!items.data.length">
						<td colspan="8" class="px-3 py-4 text-gray-500">
							{{ ctrans("Nothing to rescue right now") }}
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="mt-3 flex items-center text-xs text-gray-500">
			<span v-if="items.total" class="tabular-nums">
				{{
					ctrans(":from to :to of :total", {
						from: items.from ?? 0,
						to: items.to ?? 0,
						total: locale.number(items.total),
					})
				}}
			</span>
			<div class="ml-auto flex gap-4">
				<Link
					v-if="items.prev_page_url"
					:href="items.prev_page_url"
					preserve-scroll
					class="secondaryLink">
					{{ ctrans("Previous") }}
				</Link>
				<Link
					v-if="items.next_page_url"
					:href="items.next_page_url"
					preserve-scroll
					class="secondaryLink">
					{{ ctrans("Next") }}
				</Link>
			</div>
		</div>
	</div>
</template>
