<!--
  - Author Louis Perez
  - Created on 25-11-2026-15h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faPencil, faPercentage, faQuestionCircle, faUserEdit } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"

export interface AromaMaterialCost {
	source: 'recipe' | 'stock' | 'none'
	organisation_code: string
	currency_code: string
	effective_cost: number | null
	materials_total: number
	rest: number
	materials: {
		code: string
		name: string
		quantity: number
		unit_cost: number
		cost: number
		share: number
		next_delivery: { eta: string | null, is_estimate: boolean, is_overdue: boolean, reference: string | null, supplier: string | null, quantity: number } | null
	}[]
}

export interface AromaPartnerPricing {
	partner_discount: number
	sibling: {
		code: string
		master_shop: string
		currencies: { currency_code: string, is_major: boolean, price: number | null, price_margin: number | null, rrp: number | null, rrp_margin: number | null }[]
	} | null
	partners: { code: string, currency_code: string, price: number, margin: number | null, is_custom: boolean }[]
}

const props = defineProps<{
	data: {
		pricingCosts?: Record<string, number | null> | null
		editPricingRoute?: routeType | null
		materialCost?: AromaMaterialCost | null
		partnerPricing?: AromaPartnerPricing | null
	}
	majorCodes: string[]
}>()

const locale = inject('locale', aikuLocaleStructure)

const localeStore = useLocaleStore()

const THIN_PARTNER_MARGIN = 10

const showSiblingMinors = ref(false)

const MATERIAL_COLOURS = ['#0ea5e9', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#ec4899', '#14b8a6', '#f97316', '#84cc16', '#64748b']
const materialColour = (index: number): string => MATERIAL_COLOURS[index % MATERIAL_COLOURS.length]
const UNCOVERED_COST_BACKGROUND = 'repeating-linear-gradient(135deg, #94a3b8 0 3px, #cbd5e1 3px 6px)'

const materialBarBase = computed(() => {
	const materialCost = props.data.materialCost
	if (!materialCost) {
		return 0
	}

	return Math.max(materialCost.materials_total, materialCost.effective_cost ?? 0)
})

const nextDeliveryLabel = (delivery: { eta: string | null, is_estimate: boolean, is_overdue: boolean } | null): string => {
	if (!delivery) {
		return ctrans('Nothing on order')
	}
	if (!delivery.eta) {
		return ctrans('On order, no date')
	}
	const date = useFormatTime(delivery.eta)
	if (delivery.is_overdue) {
		return ctrans('Overdue, was due :date', { date })
	}

	return delivery.is_estimate ? ctrans('About :date', { date }) : date
}

const partnerMarginClass = (margin: number | null): string => {
	if (margin === null) {
		return 'text-gray-400'
	}
	if (margin < 0) {
		return 'text-red-600'
	}

	return margin < THIN_PARTNER_MARGIN ? 'text-amber-600' : 'text-green-600'
}

const partnerMarginTooltip = (margin: number | null): string => {
	if (margin === null) {
		return ctrans('Margin vs effective cost')
	}
	if (margin < 0) {
		return ctrans('Loss: the partner pays less than the effective cost')
	}

	return margin < THIN_PARTNER_MARGIN
		? ctrans('Thin margin: under :n% over the effective cost', { n: `${THIN_PARTNER_MARGIN}` })
		: ctrans('Profit over the effective cost')
}
</script>

<template>
	<div class="contents">
		<div v-if="data.partnerPricing" class="w-full border border-solid border-gray-300 rounded-md px-3 py-1.5">
			<div class="flex items-start justify-between gap-3">
				<div class="flex items-center gap-1 pt-0.5 text-xs font-semibold text-gray-500">
					<span>{{ ctrans('Effective cost / Outer') }}</span>
					<FontAwesomeIcon
						:icon="faQuestionCircle"
						class="text-gray-400 hover:text-gray-600 cursor-help"
						v-tooltip="ctrans('What one outer costs us. For each organisation that stocks it: its last purchase price per SKO, divided by the units per SKO and multiplied by the trade units in the outer. The organisations are then averaged, weighted by how many outers each one has available, so the organisations holding the stock set the cost. With no stock anywhere it is a plain average.')"
						fixed-width
						aria-hidden="true"
					/>
				</div>
				<div v-if="data.materialCost && materialBarBase > 0" class="group relative flex-1 self-center px-2">
					<div class="flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100 cursor-help">
						<div
							v-for="(material, index) in data.materialCost.materials"
							:key="material.code"
							class="h-full"
							:style="{ width: `${material.share}%`, backgroundColor: materialColour(index) }"
						/>
						<div
							v-if="data.materialCost.rest > 0"
							class="h-full"
							:style="{ width: `${data.materialCost.rest / materialBarBase * 100}%`, background: UNCOVERED_COST_BACKGROUND }"
						/>
					</div>
					<div class="invisible absolute right-0 top-full z-30 mt-2 w-[42rem] max-w-[90vw] rounded-md border border-gray-200 bg-white p-3 text-xs font-normal text-gray-600 shadow-lg opacity-0 transition-opacity group-hover:visible group-hover:opacity-100">
						<div class="mb-2 font-semibold text-gray-700">
							<template v-if="data.materialCost.source === 'recipe'">{{ ctrans('Recipe materials of one outer (:org)', { org: data.materialCost.organisation_code }) }}</template>
							<template v-else-if="data.materialCost.source === 'stock'">{{ ctrans('No recipe in :org, so this is what one outer costs in :org stock', { org: data.materialCost.organisation_code }) }}</template>
							<template v-else>{{ ctrans('No recipe or stock in :org, so only the effective cost is known', { org: data.materialCost.organisation_code }) }}</template>
						</div>
						<table v-if="data.materialCost.materials.length" class="w-full">
							<thead>
								<tr class="text-left text-gray-400">
									<th class="pb-1 font-medium">{{ ctrans('Material') }}</th>
									<th class="whitespace-nowrap pb-1 pl-3 text-right font-medium">{{ ctrans('Qty') }}</th>
									<th class="whitespace-nowrap pb-1 pl-3 text-right font-medium">{{ ctrans('Unit cost') }}</th>
									<th class="whitespace-nowrap pb-1 pl-3 text-right font-medium">{{ ctrans('Cost') }}</th>
									<th class="whitespace-nowrap pb-1 pl-3 text-right font-medium">%</th>
									<th class="whitespace-nowrap pb-1 pl-4 font-medium">{{ ctrans('Next delivery') }}</th>
								</tr>
							</thead>
							<tbody class="tabular-nums">
								<tr v-for="(material, index) in data.materialCost.materials" :key="material.code" class="border-t border-gray-100 align-top">
									<td class="py-1 pr-2">
										<span class="mr-1 inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: materialColour(index) }" />
										<span class="whitespace-nowrap font-medium text-gray-700">{{ material.code }}</span>
										<div class="text-gray-400">{{ material.name }}</div>
									</td>
									<td class="whitespace-nowrap py-1 pl-3 text-right">{{ locale.number(material.quantity) }}</td>
									<td class="whitespace-nowrap py-1 pl-3 text-right">{{ localeStore.currencyFormatUnitCost(data.materialCost.currency_code, material.unit_cost) }}</td>
									<td class="whitespace-nowrap py-1 pl-3 text-right font-medium text-gray-700">{{ locale.currencyFormat(data.materialCost.currency_code, material.cost) }}</td>
									<td class="whitespace-nowrap py-1 pl-3 text-right">{{ Math.round(material.share) }}%</td>
									<td class="py-1 pl-4">
										<div class="whitespace-nowrap" :class="material.next_delivery?.is_overdue ? 'text-amber-600' : material.next_delivery ? 'text-gray-700' : 'text-gray-400'">{{ nextDeliveryLabel(material.next_delivery) }}</div>
										<div v-if="material.next_delivery" class="text-gray-400">
											{{ [material.next_delivery.reference, material.next_delivery.supplier].filter(Boolean).join(' · ') }}
										</div>
									</td>
								</tr>
							</tbody>
						</table>
						<div class="space-y-0.5 tabular-nums" :class="{ 'mt-2 border-t border-gray-200 pt-2': data.materialCost.materials.length }">
							<div v-if="data.materialCost.materials.length" class="flex justify-between">
								<span>{{ data.materialCost.source === 'stock' ? ctrans('Stock cost total') : ctrans('Materials total') }}</span>
								<span class="font-medium text-gray-700">{{ locale.currencyFormat(data.materialCost.currency_code, data.materialCost.materials_total) }}</span>
							</div>
							<div v-if="data.materialCost.effective_cost !== null" class="flex justify-between">
								<span>{{ ctrans('Effective cost') }}</span>
								<span class="font-medium text-gray-700">{{ locale.currencyFormat(data.materialCost.currency_code, data.materialCost.effective_cost) }}</span>
							</div>
							<div v-if="data.materialCost.rest > 0" class="flex justify-between text-gray-400">
								<span><span class="mr-1 inline-block h-2 w-2 rounded-full" :style="{ background: UNCOVERED_COST_BACKGROUND }" />{{ data.materialCost.source === 'recipe' ? ctrans('Not covered by recipe materials') : data.materialCost.source === 'stock' ? ctrans('Not covered by our stock cost') : ctrans('Effective cost, nothing to break it down by') }}</span>
								<span>{{ locale.currencyFormat(data.materialCost.currency_code, data.materialCost.rest) }}</span>
							</div>
							<div v-else-if="data.materialCost.effective_cost !== null && data.materialCost.materials_total > data.materialCost.effective_cost" class="text-amber-600">
								{{ data.materialCost.source === 'stock' ? ctrans('Our stock costs more than the effective cost') : ctrans('The materials cost more than the effective cost') }}
							</div>
						</div>
					</div>
				</div>
				<div class="grid grid-cols-[auto] justify-end gap-y-0.5 tabular-nums text-sm font-medium text-gray-700">
					<span v-for="code in majorCodes" :key="code" class="text-right">
						{{ data.pricingCosts?.[code] != null ? locale.currencyFormat(code, data.pricingCosts[code]!) : '-' }}
					</span>
				</div>
			</div>
		</div>

		<slot name="prices" />

		<div v-if="data.partnerPricing?.partners.length" class="w-full border border-solid border-gray-300 rounded-md px-3 py-1.5">
			<div class="flex items-start justify-between gap-3">
				<div class="flex items-center gap-1 pt-0.5 text-xs font-semibold text-gray-500">
					<span>{{ ctrans('Partner price / Outer') }}</span>
					<FontAwesomeIcon
						:icon="faQuestionCircle"
						class="text-gray-400 hover:text-gray-600 cursor-help"
						v-tooltip="ctrans('What each partner organisation pays us for one outer: the list price less the :discount partner discount, in the partner\'s currency, unless a custom partner price is set. The % is our margin over the effective cost.', { discount: `${Math.round(data.partnerPricing.partner_discount * 100)}%` })"
						fixed-width
						aria-hidden="true"
					/>
					<Link
						v-if="data.editPricingRoute"
						:href="route(data.editPricingRoute.name, data.editPricingRoute.parameters)"
						class="text-gray-400 hover:text-[var(--app-accent)]"
						v-tooltip="ctrans('Edit partner prices')"
					>
						<FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
					</Link>
				</div>
				<div class="grid grid-cols-[auto_auto_auto_auto] justify-end gap-x-2 gap-y-0.5 tabular-nums text-sm font-medium text-gray-700">
					<template v-for="partner in data.partnerPricing.partners" :key="partner.code">
						<FontAwesomeIcon
							:icon="partner.is_custom ? faUserEdit : faPercentage"
							class="self-center justify-self-end text-xs"
							:class="partner.is_custom ? 'text-[var(--app-accent)]' : 'text-gray-400'"
							v-tooltip="partner.is_custom
								? ctrans('Custom - A custom price set for this partner')
								: ctrans('Default - List price less the :discount partner discount', { discount: `${Math.round(data.partnerPricing.partner_discount * 100)}%` })"
							fixed-width
							aria-hidden="true"
						/>
						<span class="self-center text-xs font-semibold text-gray-500">{{ partner.code }}</span>
						<span class="self-center text-right text-xs font-medium" :class="partnerMarginClass(partner.margin)" v-tooltip="partnerMarginTooltip(partner.margin)">{{ partner.margin !== null ? `${partner.margin}%` : '' }}</span>
						<span class="text-right">{{ locale.currencyFormat(partner.currency_code, partner.price) }}</span>
					</template>
				</div>
			</div>
		</div>

		<div v-if="data.partnerPricing?.sibling?.currencies.length" class="w-full border border-solid border-gray-300 rounded-md px-3 py-1.5">
			<div class="flex items-start justify-between gap-3">
				<div class="flex items-center gap-1 pt-0.5 text-xs font-semibold text-gray-500">
					<span>{{ ctrans('Partners sell at') }}</span>
					<FontAwesomeIcon
						:icon="faQuestionCircle"
						class="text-gray-400 hover:text-gray-600 cursor-help"
						v-tooltip="ctrans('What the partners sell :code at in their own shops (:shop): the price per outer with their margin over what they pay us, and the RRP per unit with the margin over that price.', { code: data.partnerPricing.sibling.code, shop: data.partnerPricing.sibling.master_shop })"
						fixed-width
						aria-hidden="true"
					/>
				</div>
				<div class="grid grid-cols-[auto_auto_auto_auto_auto] items-baseline justify-end gap-x-2 gap-y-0.5 tabular-nums text-sm font-medium text-gray-700">
					<span />
					<span class="col-span-2 text-right text-[10px] font-normal uppercase tracking-wide text-gray-400">{{ ctrans('Price / Outer') }}</span>
					<span class="col-span-2 text-right text-[10px] font-normal uppercase tracking-wide text-gray-400">{{ ctrans('RRP / Unit') }}</span>
					<template v-for="row in data.partnerPricing.sibling.currencies.filter(currency => currency.is_major || showSiblingMinors)" :key="row.currency_code">
						<span class="text-xs font-semibold" :class="row.is_major ? 'text-gray-500' : 'text-gray-400'">{{ row.currency_code }}</span>
						<span class="text-right text-xs font-medium" :class="partnerMarginClass(row.price_margin)" v-tooltip="ctrans('Partner margin over what it pays us')">{{ row.price_margin !== null ? `${row.price_margin}%` : '' }}</span>
						<span class="text-right">{{ row.price !== null ? locale.currencyFormat(row.currency_code, row.price) : '-' }}</span>
						<span class="pl-2 text-right text-xs font-medium" :class="partnerMarginClass(row.rrp_margin)" v-tooltip="ctrans('Retail margin vs the partner price')">{{ row.rrp_margin !== null ? `${row.rrp_margin}%` : '' }}</span>
						<span class="text-right">{{ row.rrp !== null ? locale.currencyFormat(row.currency_code, row.rrp) : '-' }}</span>
					</template>
					<button
						v-if="data.partnerPricing.sibling.currencies.some(currency => !currency.is_major)"
						type="button"
						class="col-span-5 text-right text-xs font-normal text-gray-400 hover:text-gray-600"
						@click="showSiblingMinors = !showSiblingMinors"
					>
						{{ ctrans('Minor currencies') }} ({{ data.partnerPricing.sibling.currencies.filter(currency => !currency.is_major).length }})
						<FontAwesomeIcon :icon="faChevronDown" class="text-xs transition-transform duration-200" :class="{ '-rotate-90': !showSiblingMinors }" fixed-width />
					</button>
				</div>
			</div>
		</div>

		<slot name="after" />
	</div>
</template>
