<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { usePurchaseOrderStockCover } from "@/Composables/usePurchaseOrderStockCover"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSparkles, faExclamationTriangle } from "@fas"

const props = defineProps<{
	item: any
	isPartner?: boolean
	typedSkosById?: Record<number, number>
	quantityLabel?: (skos: number) => string
	appliedSkos?: (skos: number) => number
}>()

const emit = defineEmits<{
	(e: "suggest", skos: number): void
}>()

const locale = useLocaleStore()

const { suggestion, targetDays, leadDays, overstockDays, stock, incoming, coverDays, weeksLabel } = usePurchaseOrderStockCover(
	() => props.item,
	() => props.isPartner
)

const orderedSkos = computed(() => {
	const typedSkos = props.typedSkosById?.[props.item.id]

	return typedSkos !== undefined && typedSkos !== null
		? Number(typedSkos) || 0
		: (Number(props.item.quantity_ordered) || 0) / (Number(props.item.units_per_pack) || 1)
})

const suggestedSkos = computed(() => (suggestion.value !== null && props.appliedSkos ? props.appliedSkos(suggestion.value) : suggestion.value ?? 0))

const isApplied = computed(() => suggestion.value !== null && Math.round(orderedSkos.value) === Math.round(suggestedSkos.value))

const formattedSuggestion = computed(() => (props.quantityLabel ? props.quantityLabel(suggestion.value ?? 0) : locale.number(Math.round(suggestion.value ?? 0))))

const partnerStock = computed(() =>
	props.isPartner && props.item.partner_stock !== undefined && props.item.partner_stock !== null ? Math.max(0, Number(props.item.partner_stock) || 0) : null
)

const partnerShortfall = computed(() =>
	partnerStock.value !== null && orderedSkos.value > partnerStock.value ? orderedSkos.value - partnerStock.value : 0
)

const partnerShortfallTooltip = computed(() =>
	ctrans("The partner has only :stock SKOs in stock. The other :shortfall SKOs must be bought from their supplier first, so delivery may take longer than usual", {
		stock: locale.number(Math.round(partnerStock.value ?? 0)),
		shortfall: locale.number(Math.round(partnerShortfall.value)),
	})
)

const tooltip = computed(() =>
	isApplied.value
		? ctrans("You are using the suggested quantity. It covers :time after it arrives, based on the sales forecast, stock in hand, stock coming and the lead time", {
				time: weeksLabel(targetDays.value - leadDays.value),
			})
		: props.quantityLabel
			? ctrans("Suggested from the sales forecast, stock in hand, stock coming and the lead time: enough for :time after it arrives. Click to use :quantity", {
					time: weeksLabel(targetDays.value - leadDays.value),
					quantity: formattedSuggestion.value,
				})
			: ctrans("Suggested from the sales forecast, stock in hand, stock coming and the lead time: enough for :time after it arrives. Click to use :quantity SKOs", {
					time: weeksLabel(targetDays.value - leadDays.value),
					quantity: formattedSuggestion.value,
				})
)

const raisedToMinimum = computed(() => Math.round(suggestedSkos.value) > Math.round(suggestion.value ?? 0))

const suggestedCoverDays = computed(() => coverDays(stock.value + incoming.value + suggestedSkos.value))

const isOverstock = computed(() => suggestion.value !== null && suggestion.value > 0 && suggestedCoverDays.value > overstockDays.value)

const overstockNote = computed(() =>
	raisedToMinimum.value
		? ctrans("Lasts :time: the supplier's minimum order", { time: weeksLabel(suggestedCoverDays.value) })
		: ctrans("Lasts :time: the fewest whole cartons", { time: weeksLabel(suggestedCoverDays.value) })
)

const overstockTooltip = computed(() =>
	[
		raisedToMinimum.value
			? ctrans("The supplier's minimum order is :quantity, and that lasts :time, past the :overstock overstock limit.", {
					quantity: formattedSuggestion.value,
					time: weeksLabel(suggestedCoverDays.value),
					overstock: weeksLabel(overstockDays.value),
				})
			: ctrans("Fewer whole cartons would not cover the need, and :quantity lasts :time, past the :overstock overstock limit.", {
					quantity: formattedSuggestion.value,
					time: weeksLabel(suggestedCoverDays.value),
					overstock: weeksLabel(overstockDays.value),
				}),
		ctrans("Order it if the product is worth holding that long, or leave it at 0 and let it run out."),
	].join(" ")
)
</script>

<template>
	<div v-if="(suggestion !== null && suggestion > 0) || partnerShortfall > 0" class="mt-1 flex max-w-[14rem] flex-col items-end gap-0.5">
		<span v-if="partnerShortfall > 0" v-tooltip="partnerShortfallTooltip" class="cursor-help text-right text-[11px] leading-tight text-amber-600">
			<FontAwesomeIcon :icon="faExclamationTriangle" class="text-[10px]" fixed-width aria-hidden="true" />
			{{ ctrans("Partner has only :stock SKOs, :shortfall SKOs more must be bought by them first", { stock: locale.number(Math.round(partnerStock ?? 0)), shortfall: locale.number(Math.round(partnerShortfall)) }) }}
		</span>
		<button
			v-if="suggestion !== null && suggestion > 0"
			type="button"
			v-tooltip="tooltip"
			class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium transition-colors"
			:class="
				isApplied
					? 'border-green-300 bg-green-50 text-green-700'
					: 'border-violet-300 bg-gradient-to-r from-violet-50 to-fuchsia-50 text-violet-700 hover:from-violet-100 hover:to-fuchsia-100'
			"
			@click="emit('suggest', suggestion)">
			<FontAwesomeIcon :icon="faSparkles" class="text-[10px]" :class="isApplied ? 'text-green-500' : 'text-violet-500'" fixed-width aria-hidden="true" />
			<span>{{ isApplied ? ctrans("Suggested") : ctrans("Suggestion: :quantity", { quantity: formattedSuggestion }) }}</span>
		</button>
		<span v-if="isOverstock" v-tooltip="overstockTooltip" class="cursor-help text-right text-[11px] leading-tight text-amber-600">
			<FontAwesomeIcon :icon="faExclamationTriangle" class="text-[10px]" fixed-width aria-hidden="true" />
			{{ overstockNote }}
		</span>
	</div>
</template>
