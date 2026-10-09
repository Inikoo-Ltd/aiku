<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTrashAlt } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faPlus, faTrashAlt)

interface Payment {
	id: number
	date: string
	amount: number
	reference: string | null
	notes: string | null
	delete_route: routeType
}

const props = defineProps<{
	currency: string
	hasCharges: boolean
	agent: {
		can_edit: boolean
		charges_approved: boolean
		approve_route: routeType
		deposits: { type: string; reference: string | null; date: string | null; amount: number }[]
		payments: Payment[]
		payment_store_route: routeType
	}
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] [&_textarea:focus]:!border-[--app-accent]"

const money = (amount: number) => locale.currencyFormat(props.currency, amount)

const onError = (errors: Record<string, string>) =>
	notify({ title: ctrans("Something went wrong"), text: Object.values(errors)[0], type: "error" })

const approveLoading = ref(false)
const confirmApproveCharges = () =>
	confirm.require({
		message: ctrans("Approve the agent's charges? The costing can then go ahead."),
		header: ctrans("Approve charges"),
		acceptLabel: ctrans("Approve charges"),
		rejectLabel: ctrans("Cancel"),
		accept: () =>
			router.post(route(props.agent.approve_route.name, props.agent.approve_route.parameters), {}, {
				preserveScroll: true,
				onStart: () => (approveLoading.value = true),
				onFinish: () => (approveLoading.value = false),
				onError,
			}),
	})

const isPaymentDialogOpen = ref(false)
const paymentLoading = ref(false)
const payment = ref<{ date: Date | null; amount: number | null; reference: string; notes: string }>({ date: new Date(), amount: null, reference: "", notes: "" })

const openPaymentDialog = () => {
	payment.value = { date: new Date(), amount: null, reference: "", notes: "" }
	isPaymentDialogOpen.value = true
}

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const storePayment = () => {
	if (!payment.value.date || !payment.value.amount) return
	router.post(
		route(props.agent.payment_store_route.name, props.agent.payment_store_route.parameters),
		{ date: toIsoDate(payment.value.date), amount: payment.value.amount, reference: payment.value.reference || null, notes: payment.value.notes || null },
		{
			preserveScroll: true,
			onStart: () => (paymentLoading.value = true),
			onFinish: () => (paymentLoading.value = false),
			onSuccess: () => {
				isPaymentDialogOpen.value = false
				notify({ title: ctrans("Payment recorded"), type: "success" })
			},
			onError,
		}
	)
}

const confirmDeletePayment = (row: Payment) =>
	confirm.require({
		message: ctrans("Remove the payment of :amount from :date?", { amount: String(money(row.amount)), date: useFormatTime(row.date) }),
		header: ctrans("Remove payment"),
		acceptLabel: ctrans("Remove"),
		rejectLabel: ctrans("Cancel"),
		acceptClass: "p-button-danger",
		accept: () =>
			router.delete(route(row.delete_route.name, row.delete_route.parameters), {
				preserveScroll: true,
				onSuccess: () => notify({ title: ctrans("Payment removed"), type: "success" }),
				onError,
			}),
	})
</script>

<template>
	<div class="mt-3 space-y-3 border-t border-gray-200 pt-3 text-sm">
		<div v-if="hasCharges" class="flex flex-wrap items-center justify-between gap-2">
			<template v-if="!agent.charges_approved">
				<span class="rounded bg-amber-50 px-2 py-1 text-amber-700">{{ ctrans("The agent's charges wait for your approval, the costing waits for it") }}</span>
				<Button v-if="agent.can_edit" type="primary" size="xs" :label="ctrans('Approve charges')" :loading="approveLoading" @click="confirmApproveCharges" />
			</template>
			<span v-else class="rounded bg-green-50 px-2 py-1 text-green-700">{{ ctrans("Charges approved") }}</span>
		</div>

		<div>
			<div class="flex items-center justify-between">
				<h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Payments to the agent") }}</h3>
				<Button v-if="agent.can_edit" type="transparent" size="xs" icon="fal fa-plus" :label="ctrans('Add payment')" @click="openPaymentDialog" />
			</div>
			<p v-if="!agent.deposits.length && !agent.payments.length" class="mt-2 text-gray-500">{{ ctrans("No deposits applied and no payments recorded yet.") }}</p>
			<ul v-else class="mt-2 space-y-1.5">
				<li v-for="(deposit, index) in agent.deposits" :key="`deposit-${index}`" class="flex items-center justify-between gap-4">
					<span class="min-w-0 truncate text-gray-700">
						<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ ctrans("Deposit") }}</span>
						{{ deposit.reference ?? "—" }}
						<span v-if="deposit.date" class="text-gray-400"> · {{ useFormatTime(deposit.date) }}</span>
					</span>
					<span class="tabular-nums text-gray-800">{{ money(deposit.amount) }}</span>
				</li>
				<li v-for="row in agent.payments" :key="`payment-${row.id}`" class="flex items-center justify-between gap-4">
					<span class="min-w-0 text-gray-700">
						<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ ctrans("Payment") }}</span>
						{{ row.reference ?? "—" }}
						<span class="text-gray-400"> · {{ useFormatTime(row.date) }}</span>
						<span v-if="row.notes" class="block truncate text-xs text-gray-500">{{ row.notes }}</span>
					</span>
					<span class="flex items-center gap-2">
						<span class="tabular-nums text-gray-800">{{ money(row.amount) }}</span>
						<button v-if="agent.can_edit" type="button" class="text-gray-400 hover:text-red-600" :title="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="confirmDeletePayment(row)">
							<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
						</button>
					</span>
				</li>
			</ul>
		</div>

		<Dialog v-model:visible="isPaymentDialogOpen" modal :header="ctrans('Add payment')" class="w-full max-w-md">
			<div class="space-y-3">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date") }}</span>
					<DatePicker v-model="payment.date" dateFormat="dd/mm/yy" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount") }}</span>
					<InputNumber v-model="payment.amount" mode="currency" :currency="currency" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Reference") }}</span>
					<InputText v-model="payment.reference" class="mt-1 w-full" :class="fieldFocusClass" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Notes") }}</span>
					<Textarea v-model="payment.notes" rows="2" class="mt-1 w-full" :class="fieldFocusClass" autoResize />
				</label>
			</div>
			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="isPaymentDialogOpen = false" />
				<Button type="save" :label="ctrans('Record payment')" :disabled="!payment.date || !payment.amount" :loading="paymentLoading" @click="storePayment" />
			</template>
		</Dialog>
	</div>
</template>
