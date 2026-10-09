<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileInvoice, faFilePdf, faPlus, faTrashAlt } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faFileInvoice, faFilePdf, faPlus, faTrashAlt)

interface Charge {
	description: string
	type?: "freight" | "other"
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
	destroy_route: routeType
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
			pdf_route: routeType
		} | null
		payments: Payment[]
		is_open: boolean
		store_route: routeType
		charges_update_route: routeType | null
		payment_store_route: routeType
	}
	currencyCode: string
}>()

const locale = useLocaleStore()
const confirm = useConfirm()
const page = usePage()

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] [&_textarea:focus]:!border-[--app-accent]"

const money = (amount: number) => locale.currencyFormat(props.data.invoice?.currency_code ?? props.currencyCode, amount)

const errorMessage = computed(() => {
	const errors = (page.props.errors ?? {}) as Record<string, string>
	return Object.entries(errors).find(([key]) => key === "invoice" || key.startsWith("charges") || ["date", "amount", "reference", "notes"].includes(key))?.[1]
})

const onError = (errors: Record<string, string>) =>
	notify({ title: ctrans("Something went wrong"), text: Object.values(errors)[0], type: "error" })

const invoiceLoading = ref(false)
const makeInvoice = () =>
	router.post(route(props.data.store_route.name, props.data.store_route.parameters), {}, {
		preserveScroll: true,
		onStart: () => (invoiceLoading.value = true),
		onFinish: () => (invoiceLoading.value = false),
		onSuccess: () => notify({ title: ctrans("Invoice made"), type: "success" }),
		onError,
	})

const confirmRemakeInvoice = () =>
	confirm.require({
		message: ctrans("The goods lines are taken again from the container as it stands now. The invoice number and your charges are kept."),
		header: ctrans("Make the invoice again?"),
		acceptLabel: ctrans("Make again"),
		rejectLabel: ctrans("Cancel"),
		accept: makeInvoice,
	})

const charges = ref<Charge[]>([])
const syncCharges = () => (charges.value = (props.data.invoice?.charges ?? []).map(charge => ({ ...charge, type: charge.type ?? "other" })))
const chargeTypeOptions = [
	{ label: ctrans("Freight"), value: "freight" },
	{ label: ctrans("Other"), value: "other" },
]
watch(() => props.data.invoice?.charges, syncCharges, { immediate: true, deep: true })

const chargesDirty = computed(() => JSON.stringify(charges.value) !== JSON.stringify((props.data.invoice?.charges ?? []).map(charge => ({ ...charge, type: charge.type ?? "other" }))))
const chargesTotal = computed(() => charges.value.reduce((sum, charge) => sum + (Number(charge.amount) || 0), 0))
const chargesLoading = ref(false)

const addCharge = () => charges.value.push({ description: "", type: "other", amount: null })
const removeCharge = (index: number) => charges.value.splice(index, 1)
const saveCharges = () => {
	if (!props.data.charges_update_route) return
	router.patch(route(props.data.charges_update_route.name, props.data.charges_update_route.parameters), { charges: charges.value }, {
		preserveScroll: true,
		onStart: () => (chargesLoading.value = true),
		onFinish: () => (chargesLoading.value = false),
		onSuccess: () => notify({ title: ctrans("Charges saved"), type: "success" }),
		onError,
	})
}

const deposits = computed(() => props.data.invoice?.advance_payments.filter(payment => payment.type === "deposit") ?? [])

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
		route(props.data.payment_store_route.name, props.data.payment_store_route.parameters),
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
			router.delete(route(row.destroy_route.name, row.destroy_route.parameters), {
				preserveScroll: true,
				onSuccess: () => notify({ title: ctrans("Payment removed"), type: "success" }),
				onError,
			}),
	})
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
					<Button v-if="data.is_open" type="transparent" size="xs" icon="fal fa-plus" :label="ctrans('Add')" @click="addCharge" />
				</div>
				<template v-if="data.is_open">
					<p v-if="!charges.length" class="mt-2 text-sm text-gray-500">{{ ctrans("Commission, packing, freight: anything on top of the goods. Mark freight charges as Freight so they count as the container's shipping cost.") }}</p>
					<div v-for="(charge, index) in charges" :key="index" class="mt-2 flex flex-wrap items-center gap-2">
						<InputText v-model="charge.description" size="small" class="min-w-0 basis-full flex-1 sm:basis-auto" :class="fieldFocusClass" :placeholder="ctrans('Description')" :aria-label="ctrans('Description')" />
						<SegmentedToggle v-model="charge.type" :options="chargeTypeOptions" :ariaLabel="ctrans('Charge type')" />
						<InputNumber v-model="charge.amount" mode="currency" :currency="data.invoice.currency_code" :min="0" size="small" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-32 !text-right' } } }" :aria-label="ctrans('Amount')" />
						<button type="button" class="text-gray-400 hover:text-red-600" :title="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="removeCharge(index)">
							<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
						</button>
					</div>
					<div v-if="charges.length || chargesDirty" class="mt-3 flex items-center justify-between text-sm">
						<span class="text-gray-500">{{ ctrans("Charges total") }} <span class="tabular-nums text-gray-800">{{ money(chargesTotal) }}</span></span>
						<Button type="save" size="xs" :label="ctrans('Save')" :disabled="!chargesDirty" :loading="chargesLoading" @click="saveCharges" />
					</div>
				</template>
				<template v-else>
					<p v-if="!data.invoice.charges.length" class="mt-2 text-sm text-gray-500">{{ ctrans("No charges.") }}</p>
					<ul v-else class="mt-2 space-y-1 text-sm">
						<li v-for="(charge, index) in data.invoice.charges" :key="index" class="flex justify-between gap-4">
							<span class="text-gray-700">{{ charge.description }}<span v-if="charge.type === 'freight'" class="ml-1.5 text-xs text-gray-400">{{ ctrans("Freight") }}</span></span>
							<span class="tabular-nums text-gray-800">{{ money(Number(charge.amount)) }}</span>
						</li>
					</ul>
				</template>
			</div>

			<div class="px-4 py-4">
				<div class="flex items-center justify-between">
					<h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Paid in advance") }}</h3>
					<Button type="transparent" size="xs" icon="fal fa-plus" :label="ctrans('Add payment')" @click="openPaymentDialog" />
				</div>
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
							<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ ctrans("Payment") }}</span>
							{{ row.reference ?? "—" }}
							<span class="text-gray-400"> · {{ useFormatTime(row.date) }}</span>
						</span>
						<span class="flex items-center gap-2">
							<span class="tabular-nums text-gray-800">{{ money(row.amount) }}</span>
							<button type="button" class="text-gray-400 hover:text-red-600" :title="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="confirmDeletePayment(row)">
								<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
							</button>
						</span>
					</li>
				</ul>
			</div>
		</div>

		<Dialog v-model:visible="isPaymentDialogOpen" modal :header="ctrans('Add payment')" class="w-full max-w-md">
			<div class="space-y-3">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date") }}</span>
					<DatePicker v-model="payment.date" dateFormat="dd/mm/yy" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount") }}</span>
					<InputNumber v-model="payment.amount" mode="currency" :currency="data.invoice?.currency_code ?? currencyCode" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
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
	</section>
</template>
