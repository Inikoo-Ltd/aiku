<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 03 Nov 2026 Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import InputNumber from "primevue/inputnumber"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

const props = defineProps<{
	item: {
		agent_unit_expense: number | null
		net_currency?: string
		agentUnitExpenseRoute: routeType | null
	}
	tab?: string
}>()

const locale = useLocaleStore()

const value = ref<number | null>(props.item.agent_unit_expense)
const error = ref<string | null>(null)
const saving = ref(false)

watch(() => props.item.agent_unit_expense, (newValue) => (value.value = newValue))

function save() {
	const expenseRoute = props.item.agentUnitExpenseRoute
	if (!expenseRoute || value.value === props.item.agent_unit_expense) {
		return
	}

	saving.value = true
	error.value = null
	router.patch(route(expenseRoute.name, expenseRoute.parameters), { agent_unit_expense: value.value }, {
		preserveScroll: true,
		only: [props.tab ?? "items", "box_stats"],
		onError: (errors) => (error.value = Object.values(errors ?? {})[0] ?? null),
		onFinish: () => (saving.value = false),
	})
}
</script>

<template>
	<div v-if="item.agentUnitExpenseRoute" class="text-xs">
		<label class="flex items-center gap-1.5 text-gray-500">
			<span
				v-tooltip="ctrans('Additional expense per unit, on top of the unit price. This is not the unit price')"
				class="cursor-help border-b border-dotted border-gray-400"
			>
				{{ ctrans("Your unit expense") }}
			</span>
			<InputNumber
				v-model="value"
				v-bind="locale.unitCostInputOptions(item.net_currency)"
				:min="0"
				:disabled="saving"
				size="small"
				inputClass="!w-28 !py-0.5 !text-xs"
				@blur="save"
				@keydown.enter="save"
			/>
		</label>
		<div v-if="error" class="text-red-600">{{ error }}</div>
	</div>
	<div
		v-else-if="item.agent_unit_expense !== null"
		v-tooltip="ctrans('Set by the agent on this order; replaces the Group estimate for this line')"
		class="text-xs text-gray-500"
	>
		{{ ctrans("Agent unit expense") }}: {{ locale.currencyFormat(item.net_currency ?? "EUR", Number(item.agent_unit_expense)) }}
	</div>
</template>
