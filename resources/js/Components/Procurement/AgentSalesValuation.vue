<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed } from "vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"

export interface Valuation {
	currency: string
	measure: "cost" | "sales" | "supplier"
	agent_currency: string
	currencies: string[]
}

const props = defineProps<{
	valuation: Valuation
}>()

const currencyOptions = computed(() => [
	{ label: props.valuation.agent_currency, value: "agent" },
	...props.valuation.currencies.filter((code) => code !== props.valuation.agent_currency).map((code) => ({ label: code, value: code })),
])

const measureOptions = computed(() => [
	{ label: ctrans("At cost"), value: "cost" },
	{ label: ctrans("Wholesale"), value: "sales" },
])

const visit = (change: Partial<Valuation>) => {
	const next = { ...props.valuation, ...change }
	router.reload({
		data: { currency: next.currency === "agent" ? undefined : next.currency, measure: next.measure === "cost" ? undefined : next.measure },
	})
}

const currency = computed({ get: () => props.valuation.currency, set: (value) => visit({ currency: value }) })
const measure = computed({ get: () => props.valuation.measure, set: (value) => visit({ measure: value }) })
</script>

<template>
	<div class="flex flex-wrap items-center gap-3">
		<SegmentedToggle v-model="measure" :options="measureOptions" :aria-label="ctrans('Value sales')" />
		<SegmentedToggle v-model="currency" :options="currencyOptions" :aria-label="ctrans('Currency')" />
	</div>
</template>
