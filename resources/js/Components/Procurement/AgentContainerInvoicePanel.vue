<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PaymentDialog, { PaymentPayload } from "@/Components/Procurement/PaymentDialog.vue"
import AgentContainerChargeDialog, { ChargeForm } from "@/Components/Procurement/AgentContainerChargeDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileInvoice, faFilePdf, faPlus, faTrashAlt, faPaperclip } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faFileInvoice, faFilePdf, faPlus, faTrashAlt, faPaperclip)

interface Charge {
	description: string
	type?: string
	amount: number | null
}

interface AdvancePayment {
	type: "deposit" | "payment"
	reference: string | null
	date: string | null
	amount: number
}

interface Payment {
	id: number
	date: string
	amount: number
	reference: string | null
	notes: string | null
	method: string | null
	bank_statement: string | null
	delete_route: routeType | null
}

const props = defineProps<{
	data: {
		invoice: {
			id: number
			reference: string
			date: string
			currency_code: string
			goods_amount: number
			charges: Charge[]
			charges_amount: number
			total_amount: number
			number_lines: number
			advance_payments: AdvancePayment[]
			paid_amount: number
			balance_due: number
			charges_approved: boolean
			pdf_route: routeType
		} | null
		payments: Payment[]
		methods: { name: string; code: string }[]
		payment_store_route: routeType | null
		is_open: boolean
		store_route: routeType
		ledger_route: routeType | null
		charge_form: ChargeForm | null
	}
	currencyCode: string
}>()

const locale = useLocaleStore()
const confirm = useConfirm()
const page = usePage()


const money = (amount: number) => locale.currencyFormat(props.data.invoice?.currency_code ?? props.currencyCode, amount)

const isPaymentDialogOpen = ref(false)
const paymentLoading = ref(false)

const storePayment = (payload: PaymentPayload) => {
	const storeRoute = props.data.payment_store_route
	if (!storeRoute) return
	router.post(route(storeRoute.name, storeRoute.parameters), payload, {
		preserveScroll: true,
		forceFormData: true,
		onStart: () => {
			paymentLoading.value = true
			actionError.value = null
		},
		onFinish: () => (paymentLoading.value = false),
		onSuccess: () => (isPaymentDialogOpen.value = false),
		onError,
	})
}

const deletePayment = (row: Payment) => {
	const deleteRoute = row.delete_route
	if (!deleteRoute) return
	confirm.require({
		group: "stock-delivery",
		message: ctrans("The payment is taken off this container and off the agent's ledger."),
		header: ctrans("Remove this payment?"),
		acceptLabel: ctrans("Remove"),
		rejectLabel: ctrans("Cancel"),
		accept: () =>
			router.delete(route(deleteRoute.name, deleteRoute.parameters), {
				preserveScroll: true,
				onStart: () => (actionError.value = null),
				onError,
			}),
	})
}

const actionError = ref<string | null>(null)
const errorMessage = computed(() => {
	const errors = (page.props.errors ?? {}) as Record<string, string>
	return actionError.value ?? Object.entries(errors).find(([key]) => key === "invoice" || key.startsWith("charges"))?.[1]
})

const onError = (errors: Record<string, string>) => (actionError.value = Object.values(errors)[0] ?? ctrans("Something went wrong"))

const isChargeDialogOpen = ref(false)

const invoiceLoading = ref(false)
const makeInvoice = () =>
	router.post(route(props.data.store_route.name, props.data.store_route.parameters), {}, {
		preserveScroll: true,
		onStart: () => {
			invoiceLoading.value = true
			actionError.value = null
		},
		onFinish: () => (invoiceLoading.value = false),
		onError,
	})

const confirmRemakeInvoice = () =>
	confirm.require({
		group: "stock-delivery",
		message: ctrans("The goods lines are taken again from the container as it stands now. The invoice number is kept, and the charges follow your ledger."),
		header: ctrans("Make the invoice again?"),
		acceptLabel: ctrans("Make again"),
		rejectLabel: ctrans("Cancel"),
		accept: makeInvoice,
	})

const deposits = computed(() => props.data.invoice?.advance_payments.filter(payment => payment.type === "deposit") ?? [])

</script>

<template>
	<section class="mx-4 mt-4 rounded-lg border border-gray-200 bg-white shadow-sm">
		<div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
			<h2 class="flex items-center gap-x-2 text-sm font-semibold text-gray-900">
				<FontAwesomeIcon icon="fal fa-file-invoice" class="text-gray-400" fixed-width aria-hidden="true" />
				{{ ctrans("Invoice") }}
				<span v-if="data.invoice" class="font-normal text-gray-500">{{ data.invoice.reference }} · {{ useFormatTime(data.invoice.date) }}</span>
			</h2>
			<div v-if="data.invoice" class="flex flex-wrap items-center gap-2">
				<a :href="route(data.invoice.pdf_route.name, data.invoice.pdf_route.parameters)" target="_blank" rel="noopener" class="inline-flex items-center gap-x-1.5 text-sm text-gray-900 hover:underline">
					<FontAwesomeIcon icon="fal fa-file-pdf" fixed-width aria-hidden="true" />
					{{ ctrans("Download PDF") }}
				</a>
				<Button v-if="data.is_open" type="tertiary" size="xs" :label="ctrans('Make again from the container')" :loading="invoiceLoading" @click="confirmRemakeInvoice" />
			</div>
		</div>

		<div v-if="errorMessage" class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ errorMessage }}</div>

		<div v-if="!data.invoice" class="flex flex-col items-start gap-3 px-4 py-5 sm:flex-row sm:items-center sm:justify-between">
			<p class="text-sm text-gray-600">{{ ctrans("This container needs its invoice before it can be dispatched. It is made from the container's lines; you can add your charges once it exists.") }}</p>
			<Button v-if="data.is_open" type="primary" :label="ctrans('Make invoice')" icon="fal fa-file-invoice" :loading="invoiceLoading" @click="makeInvoice" />
			<span v-else class="text-sm text-gray-500">{{ ctrans("The container has left the agent without an invoice.") }}</span>
		</div>

		<div v-else class="grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
			<div class="px-4 py-4">
				<dl class="space-y-1.5 text-sm">
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500">{{ ctrans("Goods (:lines lines)", { lines: data.invoice.number_lines }) }}</dt>
						<dd class="tabular-nums text-gray-800">{{ money(data.invoice.goods_amount) }}</dd>
					</div>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500">{{ ctrans("Charges") }}</dt>
						<dd class="tabular-nums text-gray-800">{{ money(data.invoice.charges_amount) }}</dd>
					</div>
					<div class="flex justify-between gap-4 border-t border-gray-100 pt-1.5">
						<dt class="text-gray-700">{{ ctrans("Total") }}</dt>
						<dd class="tabular-nums font-medium text-gray-900">{{ money(data.invoice.total_amount) }}</dd>
					</div>
					<div class="flex justify-between gap-4">
						<dt class="text-gray-500">{{ ctrans("Paid in advance") }}</dt>
						<dd class="tabular-nums text-gray-800">{{ money(data.invoice.paid_amount) }}</dd>
					</div>
					<div class="flex justify-between gap-4 border-t border-gray-100 pt-1.5">
						<dt class="font-semibold text-gray-900">{{ ctrans("Balance due") }}</dt>
						<dd class="tabular-nums font-bold" :class="data.invoice.balance_due > 0 ? 'text-amber-700' : 'text-green-700'">{{ money(data.invoice.balance_due) }}</dd>
					</div>
				</dl>
			</div>

			<div class="px-4 py-4">
				<div class="flex items-center justify-between">
					<h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Your charges") }}</h3>
					<div class="flex items-center gap-3">
						<Link v-if="data.ledger_route" :href="route(data.ledger_route.name, data.ledger_route.parameters)" class="text-xs text-gray-500 underline hover:text-gray-800">{{ ctrans("Ledger") }}</Link>
						<Button v-if="data.charge_form" type="tertiary" size="xs" :label="ctrans('Add charge')" @click="isChargeDialogOpen = true">
							<template #icon>
								<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</div>
				</div>
				<AgentContainerChargeDialog v-if="data.charge_form" v-model:visible="isChargeDialogOpen" :chargeForm="data.charge_form" :currency="data.invoice.currency_code" :hasCharges="data.invoice.charges.length > 0" />
				<p class="mt-1 text-xs text-gray-500">{{ ctrans("Charges are the money you paid out for this container, as recorded in your ledger against it: loading, inland charges, container supplies, packaging and other costs.") }}</p>
				<p v-if="data.invoice.charges.length && !data.invoice.charges_approved" class="mt-2 rounded bg-amber-50 px-2 py-1 text-sm text-amber-700">{{ ctrans("Your charges are waiting for approval by the organisation paying them") }}</p>
				<p v-else-if="data.invoice.charges.length" class="mt-2 rounded bg-green-50 px-2 py-1 text-sm text-green-700">{{ ctrans("Charges approved") }}</p>
				<ul v-if="data.invoice.charges.length" class="mt-2 space-y-1 text-sm">
					<li v-for="(charge, index) in data.invoice.charges" :key="index" class="flex justify-between gap-4">
						<span class="text-gray-700">{{ charge.description }}</span>
						<span class="tabular-nums text-gray-800">{{ money(Number(charge.amount)) }}</span>
					</li>
				</ul>
			</div>

			<div class="px-4 py-4">
				<div class="flex items-center justify-between">
					<h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Paid in advance") }}</h3>
					<Button v-if="data.payment_store_route" type="tertiary" size="xs" :label="ctrans('Add payment')" @click="isPaymentDialogOpen = true">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
				<PaymentDialog
					v-if="data.payment_store_route"
					v-model:visible="isPaymentDialogOpen"
					:title="ctrans('Add payment')"
					:currency="data.invoice?.currency_code ?? currencyCode"
					:methods="data.methods"
					:defaultAmount="data.invoice?.balance_due"
					:loading="paymentLoading"
					@submit="storePayment"
				/>
				<p v-if="!deposits.length && !data.payments.length" class="mt-2 text-sm text-gray-500">{{ ctrans("No deposits applied and no payments recorded yet.") }}</p>
				<ul v-else class="mt-2 space-y-1.5 text-sm">
					<li v-for="(deposit, index) in deposits" :key="`deposit-${index}`" class="flex items-center justify-between gap-4">
						<span class="min-w-0 truncate text-gray-700">
							<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ ctrans("Deposit") }}</span>
							{{ deposit.reference ?? "—" }}
							<span v-if="deposit.date" class="text-gray-400"> · {{ useFormatTime(deposit.date) }}</span>
						</span>
						<span class="tabular-nums text-gray-800">{{ money(deposit.amount) }}</span>
					</li>
					<li v-for="row in data.payments" :key="`payment-${row.id}`" class="flex items-center justify-between gap-4">
						<span class="min-w-0 truncate text-gray-700" :title="row.notes ?? undefined">
							<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ row.method ?? ctrans("Payment") }}</span>
							{{ row.reference ?? "—" }}
							<span class="text-gray-400"> · {{ useFormatTime(row.date) }}</span>
							<a v-if="row.bank_statement" :href="route('grp.media.download', { id: row.bank_statement })" target="_blank" class="ml-2 text-gray-500 underline decoration-gray-300 hover:text-gray-800">
								<FontAwesomeIcon icon="fal fa-paperclip" aria-hidden="true" />
								{{ ctrans("Bank statement") }}
							</a>
						</span>
						<span class="flex items-center gap-2">
							<span class="tabular-nums text-gray-800">{{ money(row.amount) }}</span>
							<button v-if="row.delete_route" type="button" class="text-gray-400 hover:text-red-600" :title="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="deletePayment(row)">
								<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
							</button>
						</span>
					</li>
				</ul>
			</div>
		</div>
	</section>
</template>
