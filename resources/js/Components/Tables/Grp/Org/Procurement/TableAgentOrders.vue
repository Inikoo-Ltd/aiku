<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
	data: {}
	tab?: string
}>()
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(state)="{ item: agentOrder }">
			<div class="flex items-center gap-1">
				<Icon :data="agentOrder.state.icon" />
				<span>{{ agentOrder.state.label }}</span>
			</div>
		</template>

		<template #cell(reference)="{ item: agentOrder }">
			<Link :href="route(agentOrder.route.name, agentOrder.route.parameters)" class="primaryLink">
				{{ agentOrder.reference }}
			</Link>
		</template>

		<template #cell(suppliers)="{ item: agentOrder }">
			<div class="flex items-baseline gap-2">
				<span class="font-medium text-gray-700">
					{{ ctrans(":count supplier orders", { count: agentOrder.number_supplier_orders }) }}
				</span>
				<span v-tooltip="agentOrder.suppliers" class="max-w-xs truncate text-xs text-gray-500">
					{{ agentOrder.suppliers }}
				</span>
			</div>
		</template>

		<template #cell(date)="{ item: agentOrder }">
			<div class="text-right">
				{{ useFormatTime(agentOrder.date, { formatTime: "EEE, do MMM yy" }) }}
			</div>
		</template>

		<template #cell(org_total_cost)="{ item: agentOrder }">
			{{ useLocaleStore().currencyFormat(agentOrder.org_currency_code, agentOrder.org_total_cost) }}
		</template>
	</Table>
</template>
