<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 Oct 2026, Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"

type Card = {
	id: number
	stock_code: string
	stock_name: string
	quantity: number | string
	quantity_to_produce: number | string | null
	priority?: string | null
	family?: string | null
	buyer_code?: string | null
	stock_available?: number | string | null
}

const props = defineProps<{ item: Card }>()

const emit = defineEmits<{
	(e: "changeQuantity", quantity: number): void
	(e: "open", event: MouseEvent): void
}>()

const locale = useLocaleStore()

const quantityToMake = () => Math.ceil(Number(props.item.quantity_to_produce ?? props.item.quantity))

function onQuantityChange(event: Event) {
	const quantity = Number((event.target as HTMLInputElement).value)
	if (quantity >= 1 && quantity !== quantityToMake()) {
		emit("changeQuantity", quantity)
	}
}
</script>

<template>
	<div
		class="cursor-pointer rounded border border-gray-200 bg-white px-2 py-1.5 text-left text-xs transition hover:border-indigo-300 dark:border-gray-700 dark:bg-gray-900"
		@click="emit('open', $event)">
		<div class="flex items-center gap-1.5">
			<span class="font-medium">{{ item.stock_code }}</span>
			<span
				class="ml-auto flex items-center gap-1 tabular-nums"
				:class="item.priority === 'urgent' ? 'font-semibold text-red-600' : ''">
				×{{ locale.number(Math.ceil(Number(item.quantity))) }} {{ ctrans("SKO") }}
				<span class="text-indigo-600">→</span>
				<input
					type="number"
					min="1"
					step="1"
					:value="quantityToMake()"
					:title="ctrans('Quantity to make, edit to change')"
					class="w-14 rounded border-gray-200 px-1 py-0 text-right text-xs font-semibold tabular-nums text-indigo-700 hover:border-indigo-300 focus:border-indigo-500"
					@click.stop
					@mousedown.stop
					@change="onQuantityChange" />
			</span>
		</div>
		<div class="truncate text-gray-600" :title="item.stock_name">{{ item.stock_name }}</div>
		<div class="flex items-center gap-1 text-gray-400">
			<span>{{ item.buyer_code ?? ctrans("Warehouse") }}</span>
			<span v-if="item.family">· {{ item.family }}</span>
		</div>
		<div class="text-gray-400">
			<span
				:class="
					Number(item.stock_available) >= Number(item.quantity) ? 'text-emerald-600' : ''
				"
				>{{ ctrans("In stock") }}:
				{{ locale.number(Number(item.stock_available ?? 0)) }}</span
			>
		</div>
	</div>
</template>
