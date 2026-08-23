<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from '@inertiajs/vue3'
import { useLocaleStore } from "@/Stores/locale"
import { paymentProviderLogo } from "@/Composables/usePaymentProviderLogo"

const locale = useLocaleStore();

defineProps<{
	data: object
	tab?: string
}>()
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(method)="{ item }">
			<div class="flex items-center gap-1.5">
				<Link :href="item.href" class="primaryLink">
					{{ item.method_label || item.method || '-' }}
				</Link>
				<img v-if="item.payment_account_type !== item.method && paymentProviderLogo(item.payment_account_type)" :src="paymentProviderLogo(item.payment_account_type)" :alt="item.payment_account_type" :title="item.payment_account_type" class="h-3 w-auto max-w-16 opacity-70" />
			</div>
		</template>

		<template #cell(sub_method)="{ item }">
			<span class="text-gray-600">{{ item.sub_method_label || '' }}</span>
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

		<template #cell(sales_share)="{ item }">
			<div class="flex items-center gap-2 tabular-nums">
				<div class="h-1.5 w-16 rounded bg-gray-200">
					<div class="h-1.5 rounded bg-indigo-500" :style="{ width: Math.min(100, parseFloat(item.sales_share)) + '%' }" />
				</div>
				<span class="text-gray-700">{{ item.sales_share }}%</span>
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

	</Table>
</template>
