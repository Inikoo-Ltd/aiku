<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"

const locale = useLocaleStore();

defineProps<{
	data: object
	tab?: string
}>()
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(method)="{ item }">
			<div class="font-medium text-gray-700">
				{{ item.method || '-' }}
			</div>
		</template>

		<template #cell(number_payments)="{ item }">
			<div class="text-gray-700 tabular-nums">
				{{ item.number_payments.toLocaleString() }}
			</div>
		</template>

		<template #cell(total_sales)="{ item }">
			<div class="text-gray-700 font-medium tabular-nums">
				{{ locale.currencyFormat(item.currency_code, item.total_sales) }}
			</div>
		</template>

		<template #cell(number_success)="{ item }">
			<div class="text-green-600 tabular-nums">
				{{ item.number_success.toLocaleString() }}
			</div>
		</template>

		<template #cell(success_rate)="{ item }">
			<div class="tabular-nums" :class="{
				'text-green-600': parseFloat(item.success_rate) >= 80,
				'text-yellow-600': parseFloat(item.success_rate) >= 50 && parseFloat(item.success_rate) < 80,
				'text-red-600': parseFloat(item.success_rate) < 50
			}">
				{{ item.success_rate }}%
			</div>
		</template>

		<template #cell(currency_code)="{ item }">
			<div class="text-gray-500 text-xs uppercase">
				{{ item.currency_code }}
			</div>
		</template>
	</Table>
</template>
