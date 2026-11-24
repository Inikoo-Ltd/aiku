<!--
    * Author: Raul Perusquia <raul@inikoo.com>
    * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
    * Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faUndoAlt, faCheck } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type PlannedRefund = { payment_id: number; reference: string; date: string | null; method: string | null; amount: number }
type Plan = { balance: number; gifted: number; refunds: PlannedRefund[]; to_card: number; stays: number }
type Result = {
	refunded: { payment_id: number; reference: string; amount: number; action_id: string }[]
	failed: { payment_id: number; reference: string; amount: number; reason: string }[]
	unknown: { payment_id: number; reference: string; amount: number; reason: string }[]
	stopped: boolean
	to_card: number
	balance: number
}

const props = defineProps<{
	customerId: number
	currencyCode: string
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currencyCode, amount)

const plan = ref<Plan | null>(null)
const result = ref<Result | null>(null)
const error = ref<string | null>(null)
const isLoading = ref(false)
const isRefunding = ref(false)
const attempted = ref(false)

const loadPlan = async () => {
	isLoading.value = true
	error.value = null
	result.value = null
	plan.value = null
	try {
		const { data } = await axios.get(route("grp.json.customer.refund_balance_by_api.preview", { customer: props.customerId }))
		plan.value = data
	} catch {
		error.value = ctrans("The refund plan could not be loaded")
	} finally {
		isLoading.value = false
	}
}

watch(visible, (isVisible) => {
	if (isVisible) {
		loadPlan()
	}
}, { immediate: true })

const canRefund = computed(() => !!plan.value && plan.value.to_card > 0 && !isRefunding.value && !result.value)

const refund = async () => {
	if (!canRefund.value) {
		return
	}
	isRefunding.value = true
	attempted.value = true
	error.value = null
	try {
		const { data } = await axios.post(route("grp.models.customer.refund_balance_by_api", { customer: props.customerId }), { confirmed_amount: plan.value?.to_card })
		result.value = data
	} catch (e: any) {
		error.value = Object.values(e?.response?.data?.errors ?? {}).flat().join(", ") || ctrans("The refund could not be made")
	} finally {
		isRefunding.value = false
	}
}

const onHide = () => {
	if (attempted.value) {
		attempted.value = false
		router.reload()
	}
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Refund balance to card')" :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false" :closable="!isRefunding" :closeOnEscape="!isRefunding" @hide="onHide">
		<div class="flex flex-col gap-4 text-sm">
			<p class="text-gray-600">
				{{ ctrans("We refund as much of the balance as we can to the cards that paid it, through Checkout.com. Some of it may not be possible: money that cannot go back automatically (bank transfers, credit we gave, payments too old) stays in the balance for you to refund another way. The balance never goes below zero.") }}
			</p>

			<div v-if="isLoading" class="flex justify-center py-4"><LoadingIcon /></div>

			<template v-else-if="plan && !result">
				<div class="flex justify-between font-medium">
					<span>{{ ctrans("Balance") }}</span>
					<span>{{ money(plan.balance) }}</span>
				</div>

				<table v-if="plan.refunds.length" class="w-full">
					<thead class="whitespace-nowrap text-left text-xs text-gray-500">
						<tr>
							<th class="py-1 font-medium">{{ ctrans("Payment") }}</th>
							<th class="py-1 font-medium">{{ ctrans("Date") }}</th>
							<th class="py-1 text-right font-medium">{{ ctrans("Back to card") }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="refund in plan.refunds" :key="refund.payment_id" class="border-t border-gray-100">
							<td class="whitespace-nowrap py-1">{{ refund.reference }} <span v-if="refund.method" class="text-gray-400">· {{ refund.method }}</span></td>
							<td class="whitespace-nowrap py-1">{{ refund.date }}</td>
							<td class="py-1 text-right">{{ money(refund.amount) }}</td>
						</tr>
					</tbody>
				</table>

				<div class="flex flex-col gap-1 border-t border-gray-200 pt-2">
					<div v-if="plan.gifted > 0" class="flex justify-between text-gray-500">
						<span>{{ ctrans("Credit we gave in the last year, never refunded to card") }}</span>
						<span>{{ money(plan.gifted) }}</span>
					</div>
					<div class="flex justify-between">
						<span>{{ ctrans("Can go back to card now") }}</span>
						<span class="font-medium">{{ money(plan.to_card) }}</span>
					</div>
					<div class="flex justify-between" :class="plan.stays > 0 ? 'text-amber-700' : 'text-gray-500'">
						<span>{{ ctrans("Stays in the balance, refund another way") }}</span>
						<span class="font-medium">{{ money(plan.stays) }}</span>
					</div>
				</div>

				<p v-if="plan.to_card <= 0" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800">
					{{ ctrans("None of this balance can go back to a card automatically.") }}
				</p>
			</template>

			<template v-if="result">
				<p v-if="result.refunded.length" class="rounded border border-green-300 bg-green-50 px-3 py-2 text-green-800">
					{{ ctrans("Sent :amount back to the card in :count refunds through Checkout.com.", { amount: money(result.to_card), count: result.refunded.length }) }}
				</p>
				<div v-for="unknown in result.unknown" :key="'unknown-' + unknown.payment_id" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-red-800">
					{{ ctrans(":reference: :amount may or may not have been refunded. Check this payment in Checkout.com before trying again. Nothing more was sent. :reason", { reference: unknown.reference, amount: money(unknown.amount), reason: unknown.reason }) }}
				</div>
				<div v-for="failed in result.failed" :key="failed.payment_id" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800">
					{{ ctrans(":reference: :amount not refunded, it stays in the balance. :reason", { reference: failed.reference, amount: money(failed.amount), reason: failed.reason }) }}
				</div>
				<div class="flex justify-between font-medium">
					<span>{{ ctrans("Balance now") }}</span>
					<span>{{ money(result.balance) }}</span>
				</div>
				<p v-if="result.balance > 0" class="text-gray-600">{{ ctrans(":amount stays in the balance: refund it another way, e.g. by bank transfer.", { amount: money(result.balance) }) }}</p>
			</template>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="result ? ctrans('Close') : ctrans('Cancel')" :disabled="isRefunding" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="result ? faCheck : faTimes" :class="result ? 'text-green-600' : 'text-red-500'" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="!result" type="primary" full :label="plan && plan.to_card > 0 ? ctrans('Refund :amount to card', { amount: money(plan.to_card) }) : ctrans('Refund')" :loading="isRefunding" :disabled="!canRefund" @click="refund">
					<template #icon>
						<FontAwesomeIcon :icon="faUndoAlt" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
