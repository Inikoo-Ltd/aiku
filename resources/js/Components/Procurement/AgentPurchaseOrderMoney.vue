<script setup lang="ts">
import { ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMoneyCheckAlt, faPaperclip, faHandHoldingUsd } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import AgentPurchaseOrderPaySupplierDialog, { type PaySupplierForm } from "@/Components/Procurement/AgentPurchaseOrderPaySupplierDialog.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"

library.add(faMoneyCheckAlt, faPaperclip, faHandHoldingUsd)

export interface AgentPurchaseOrderMoneyData {
	currency_code: string
	goods: number
	requested: number
	received: number
	paid_to_suppliers: number
	held: number
	to_pay: number
	requests: {
		id: number
		number: string
		reference: string
		kind: string
		amount: number
		state: string
		state_label: string
		requested_at: string | null
		paid_at: string | null
	}[]
	supplier_payments: {
		id: number
		date: string | null
		type: string
		amount: number
		reference: string | null
		receipt: string | null
	}[]
	route: { name: string; parameters: Record<string, unknown>; label: string; tooltip?: string } | null
	requests_route: { name: string; parameters: Record<string, unknown> } | null
	pay_form: PaySupplierForm | null
}

const props = defineProps<{
	money: AgentPurchaseOrderMoneyData
}>()

const locale = useLocaleStore()
const format = (amount: number) => locale.currencyFormat(props.money.currency_code, amount)

const isPaying = ref(false)

const requestStateClass = (line: AgentPurchaseOrderMoneyData["requests"][number]) =>
	line.paid_at ? "text-green-600" : line.state === "requested" ? "text-amber-600" : "text-gray-500"
</script>

<template>
	<div class="mt-2 w-0 min-w-full space-y-2 border-t border-gray-200 pt-2 text-xs">
		<div class="flex items-baseline justify-between gap-2">
			<span class="text-gray-500">{{ ctrans("Goods") }}</span>
			<span class="tabular-nums text-gray-700">{{ format(money.goods) }}</span>
		</div>
		<div class="flex items-baseline justify-between gap-2">
			<span class="text-gray-500">{{ ctrans("Money requested") }}</span>
			<span class="tabular-nums text-gray-700">{{ format(money.requested) }}</span>
		</div>
		<div class="flex items-baseline justify-between gap-2">
			<span class="text-gray-500">{{ ctrans("Received from client") }}</span>
			<span class="tabular-nums" :class="money.received > 0 && money.received >= money.requested ? 'text-green-600' : 'text-gray-700'">{{ format(money.received) }}</span>
		</div>
		<div class="flex items-baseline justify-between gap-2">
			<span class="text-gray-500">{{ ctrans("Paid to suppliers") }}</span>
			<span class="tabular-nums" :class="money.to_pay <= 0 ? 'text-green-600' : 'text-gray-700'">{{ format(money.paid_to_suppliers) }}</span>
		</div>
		<div v-if="money.held > 0" class="flex items-baseline justify-between gap-2" :title="ctrans('Received from the client for this order and not yet paid to the supplier')">
			<span class="text-gray-500">{{ ctrans("Held by the agent") }}</span>
			<span class="tabular-nums text-amber-600">{{ format(money.held) }}</span>
		</div>
		<div class="flex items-baseline justify-between gap-2 font-medium">
			<span class="text-gray-600">{{ ctrans("Still to pay") }}</span>
			<span class="tabular-nums" :class="money.to_pay <= 0 ? 'text-green-600' : 'text-amber-600'">{{ format(money.to_pay) }}</span>
		</div>

		<div v-if="money.requests.length" class="space-y-1 border-t border-gray-100 pt-2">
			<div class="flex items-baseline justify-between gap-2">
				<span class="font-medium text-gray-600">{{ ctrans("Money requests") }}</span>
				<Link v-if="money.requests_route" :href="route(money.requests_route.name, money.requests_route.parameters)" class="text-gray-500 underline decoration-gray-300 hover:text-gray-800">
					{{ ctrans("Open") }}
				</Link>
			</div>
			<div v-for="line in money.requests" :key="line.id" class="flex items-baseline justify-between gap-2">
				<span class="min-w-0 truncate text-gray-500" :title="line.reference">
					<span class="whitespace-nowrap">{{ line.number }}</span><span v-if="line.requested_at" class="text-gray-400"> · {{ useFormatTime(line.requested_at) }}</span>
					<span :class="requestStateClass(line)">
						· {{ line.paid_at ? ctrans("received :date", { date: useFormatTime(line.paid_at) }) : line.state_label }}
					</span>
				</span>
				<span class="shrink-0 tabular-nums text-gray-700">{{ format(line.amount) }}</span>
			</div>
		</div>

		<div v-if="money.supplier_payments.length" class="space-y-1 border-t border-gray-100 pt-2">
			<span class="font-medium text-gray-600">{{ ctrans("Supplier payments") }}</span>
			<div v-for="payment in money.supplier_payments" :key="payment.id" class="flex items-baseline justify-between gap-2">
				<span class="min-w-0 truncate text-gray-500" :title="payment.reference ?? undefined">
					{{ payment.type }}<span v-if="payment.date"> · {{ useFormatTime(payment.date) }}</span>
					<a v-if="payment.receipt" :href="route('grp.media.download', { media: payment.receipt, inline: 1 })" target="_blank" rel="noopener" class="ml-1 text-gray-400 hover:text-gray-700" :title="ctrans('Receipt')">
						<FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
					</a>
					<span v-else class="ml-1 text-amber-600">{{ ctrans("No receipt") }}</span>
				</span>
				<span class="shrink-0 tabular-nums text-gray-700">{{ format(payment.amount) }}</span>
			</div>
		</div>

		<div class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-1">
			<Button v-if="money.pay_form" type="tertiary" size="xs" :label="ctrans('Pay supplier')" @click="isPaying = true">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-hand-holding-usd" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Link
				v-if="money.route"
				:href="route(money.route.name, money.route.parameters)"
				v-tooltip="money.route.tooltip"
				class="inline-flex items-center gap-1 text-gray-500 underline decoration-gray-300 hover:text-gray-800">
				<FontAwesomeIcon icon="fal fa-money-check-alt" class="text-gray-400" fixed-width aria-hidden="true" />
				{{ money.route.label }}
			</Link>
		</div>

		<AgentPurchaseOrderPaySupplierDialog v-if="money.pay_form" v-model:visible="isPaying" :payForm="money.pay_form" :currency="money.currency_code" />
	</div>
</template>
