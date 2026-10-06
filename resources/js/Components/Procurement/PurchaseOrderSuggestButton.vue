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
	currentSkos?: number | null
}>()

const emit = defineEmits<{
	(e: "suggest", skos: number): void
}>()

const locale = useLocaleStore()

const { suggestion, targetDays, leadDays, weeksLabel } = usePurchaseOrderStockCover(
	() => props.item,
	() => props.isPartner
)

const orderedSkos = computed(() =>
	props.currentSkos !== undefined && props.currentSkos !== null
		? Number(props.currentSkos) || 0
		: (Number(props.item.quantity_ordered) || 0) / (Number(props.item.units_per_pack) || 1)
)

const isApplied = computed(() => suggestion.value !== null && Math.round(orderedSkos.value) === suggestion.value)

const formattedSuggestion = computed(() => locale.number(Math.round(suggestion.value ?? 0)))

const partnerStock = computed(() =>
	props.isPartner && props.item.partner_stock !== undefined && props.item.partner_stock !== null ? Math.max(0, Number(props.item.partner_stock) || 0) : null
)

const partnerShortfall = computed(() =>
	partnerStock.value !== null && suggestion.value !== null && suggestion.value > partnerStock.value ? suggestion.value - partnerStock.value : 0
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
		: ctrans("Suggested from the sales forecast, stock in hand, stock coming and the lead time: enough for :time after it arrives. Click to use :quantity SKOs", {
				time: weeksLabel(targetDays.value - leadDays.value),
				quantity: formattedSuggestion.value,
			})
)
</script>

<template>
	<div v-if="suggestion !== null && suggestion > 0" class="mt-1 flex max-w-[14rem] flex-col items-end gap-0.5">
		<button
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
			<span>{{ isApplied ? ctrans("Suggested") : ctrans("Suggest :quantity", { quantity: formattedSuggestion }) }}</span>
		</button>
		<span v-if="partnerShortfall > 0" v-tooltip="partnerShortfallTooltip" class="cursor-help text-right text-[11px] leading-tight text-amber-600">
			<FontAwesomeIcon :icon="faExclamationTriangle" class="text-[10px]" fixed-width aria-hidden="true" />
			{{ ctrans("Partner has only :stock, :shortfall more must be bought by them first", { stock: locale.number(Math.round(partnerStock ?? 0)), shortfall: locale.number(Math.round(partnerShortfall)) }) }}
		</span>
	</div>
</template>
