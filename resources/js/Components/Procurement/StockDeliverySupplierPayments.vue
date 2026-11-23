<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 12 Oct 2026 07:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTrashAlt, faTimes, faCheck, faHandHoldingUsd, faPaperclip } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faPlus, faTrashAlt, faTimes, faCheck, faHandHoldingUsd, faPaperclip)

interface Payment {
	id: number
	date: string
	amount: number
	method: string
	reference: string | null
	bank_statement: string | null
	is_scheduled: boolean
	delete_route: routeType
}

const props = defineProps<{
	currency: string
	total: number
	orgCurrency?: string | null
	orgExchange?: number | null
	isEstimate: boolean
	supplier: {
		can_edit: boolean
		methods: { name: string; code: string }[]
		deposits: { reference: string | null; date: string; amount: number }[]
		payments: Payment[]
		paid: number
		balance_due: number
		payment_store_route: routeType
	}
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] focus:!border-[--app-accent]"
const dialogPt = { header: { class: "!px-4 !py-2.5 border-b border-gray-200" }, content: { class: "!p-4" }, footer: { class: "!px-4 !pb-4" } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const money = (amount: number) => locale.currencyFormat(props.currency, amount)
const orgMoney = (amount: number | null) =>
	props.orgExchange && props.orgCurrency && props.orgCurrency !== props.currency && amount ? locale.currencyFormat(props.orgCurrency, amount * props.orgExchange) : null

const onError = (errors: Record<string, string>) =>
	notify({ title: ctrans("Something went wrong"), text: Object.values(errors)[0], type: "error" })

const isOpen = ref(false)
const isPaymentDialogOpen = ref(false)
const isLoading = ref(false)
const isSettled = computed(() => props.supplier.paid > 0 && props.supplier.balance_due <= 0)

const oneYearAhead = new Date(new Date().setFullYear(new Date().getFullYear() + 1))
const emptyPayment = () => ({ date: new Date() as Date | null, amount: props.supplier.balance_due > 0 ? props.supplier.balance_due : (null as number | null), method: "bank_transfer", reference: "", file: null as File | null })
const payment = ref(emptyPayment())

const openPaymentDialog = () => {
	payment.value = emptyPayment()
	isPaymentDialogOpen.value = true
}

const needsStatement = computed(() => payment.value.method !== "cash")
const isScheduledDate = computed(() => !!payment.value.date && toIsoDate(payment.value.date as Date) > toIsoDate(new Date()))
const canSubmit = computed(() => !!payment.value.date && !!payment.value.amount && (!needsStatement.value || !!payment.value.file))

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const storePayment = () => {
	if (!canSubmit.value) return
	router.post(
		route(props.supplier.payment_store_route.name, props.supplier.payment_store_route.parameters),
		{
			date: toIsoDate(payment.value.date as Date),
			amount: payment.value.amount,
			method: payment.value.method,
			reference: payment.value.reference || null,
			bank_statement: payment.value.file,
		},
		{
			preserveScroll: true,
			forceFormData: true,
			onStart: () => (isLoading.value = true),
			onFinish: () => (isLoading.value = false),
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
		group: "stock-delivery",
		message: ctrans("Remove the payment of :amount from :date?", { amount: String(money(row.amount)), date: useFormatTime(row.date) }),
		header: ctrans("Remove payment"),
		acceptLabel: ctrans("Remove"),
		rejectLabel: ctrans("Cancel"),
		accept: () =>
			router.delete(route(row.delete_route.name, row.delete_route.parameters), {
				preserveScroll: true,
				onSuccess: () => notify({ title: ctrans("Payment removed"), type: "success" }),
				onError,
			}),
	})
</script>

<template>
	<div class="mt-3 flex justify-end">
		<Button type="tertiary" size="xs" :label="ctrans('Supplier payments')" @click="isOpen = true">
			<template #icon>
				<FontAwesomeIcon :icon="isSettled ? 'fal fa-check' : 'fal fa-hand-holding-usd'" :class="isSettled ? 'text-green-600' : ''" fixed-width aria-hidden="true" />
			</template>
		</Button>

		<Dialog v-model:visible="isOpen" modal dismissableMask :closable="false" :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }" :pt="dialogPt">
			<template #header>
				<div class="flex w-full items-center justify-between gap-3">
					<span class="text-sm font-semibold text-gray-900">{{ ctrans("Payments to the supplier") }}</span>
					<button type="button" class="text-gray-400 hover:text-gray-700" :aria-label="ctrans('Close')" @click="isOpen = false">
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
					</button>
				</div>
			</template>

			<div class="space-y-3 text-sm">
				<div class="flex items-center justify-between gap-4 text-gray-700">
					<span>{{ isEstimate ? ctrans("Invoice total (estimate)") : ctrans("Invoice total") }}</span>
					<span class="tabular-nums text-gray-900">{{ money(total) }}</span>
				</div>

				<div>
					<div class="flex items-center justify-between">
						<h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Deposits and payments") }}</h3>
						<Button v-if="supplier.can_edit" type="tertiary" size="xs" :label="ctrans('Add payment')" @click="openPaymentDialog">
							<template #icon>
								<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</div>
					<p v-if="!supplier.deposits.length && !supplier.payments.length" class="mt-2 text-gray-500">{{ ctrans("No deposit and no payments recorded yet.") }}</p>
					<ul v-else class="mt-2 space-y-1.5">
						<li v-for="(deposit, index) in supplier.deposits" :key="`deposit-${index}`" class="flex items-center justify-between gap-4">
							<span class="min-w-0 truncate text-gray-700">
								<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ ctrans("Deposit") }}</span>
								{{ deposit.reference ?? "—" }}
								<span class="text-gray-400"> · {{ useFormatTime(deposit.date) }}</span>
							</span>
							<span class="tabular-nums text-gray-800">{{ money(deposit.amount) }}</span>
						</li>
						<li v-for="row in supplier.payments" :key="`payment-${row.id}`" class="flex items-center justify-between gap-4">
							<span class="min-w-0 text-gray-700">
								<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ row.method }}</span>
								<span v-if="row.is_scheduled" class="mr-1.5 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-700" :title="ctrans('Not counted as paid until this date')">{{ ctrans("Scheduled") }}</span>
								{{ row.reference ?? "—" }}
								<span class="text-gray-400"> · {{ useFormatTime(row.date) }}</span>
								<a
									v-if="row.bank_statement"
									:href="route('grp.media.download', { id: row.bank_statement })"
									target="_blank"
									class="ml-2 text-gray-500 underline decoration-gray-300 hover:text-gray-800">
									<FontAwesomeIcon icon="fal fa-paperclip" aria-hidden="true" />
									{{ ctrans("Bank statement") }}
								</a>
							</span>
							<span class="flex items-center gap-2">
								<span class="tabular-nums text-gray-800">{{ money(row.amount) }}</span>
								<button v-if="supplier.can_edit" type="button" class="text-gray-400 hover:text-red-600" :title="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="confirmDeletePayment(row)">
									<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
								</button>
							</span>
						</li>
					</ul>
				</div>

				<div class="flex items-start justify-between gap-4 border-t border-gray-200 pt-3 font-medium text-gray-900">
					<span>{{ ctrans("Balance due") }}</span>
					<span class="flex flex-col text-right tabular-nums">
						<span :class="supplier.balance_due < 0 ? 'text-red-600' : ''">{{ money(supplier.balance_due) }}</span>
						<span v-if="orgMoney(supplier.balance_due)" class="text-xs font-normal text-gray-400">{{ orgMoney(supplier.balance_due) }}</span>
					</span>
				</div>
			</div>
		</Dialog>

		<Dialog v-model:visible="isPaymentDialogOpen" modal dismissableMask :closable="false" :pt="dialogPt" class="w-full max-w-md">
			<template #header>
				<div class="flex w-full items-center justify-between gap-3">
					<span class="text-sm font-semibold text-gray-900">{{ ctrans("Add payment") }}</span>
					<button type="button" class="text-gray-400 hover:text-gray-700" :aria-label="ctrans('Close')" @click="isPaymentDialogOpen = false">
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
					</button>
				</div>
			</template>
			<div class="space-y-3">
				<div class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount") }}</span>
					<InputNumber v-model="payment.amount" mode="currency" :currency="currency" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
					<span v-if="orgMoney(payment.amount)" class="mt-1 block text-xs tabular-nums text-gray-400">{{ orgMoney(payment.amount) }}</span>
				</div>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date the payment leaves the bank") }}</span>
					<DatePicker v-model="payment.date" dateFormat="dd/mm/yy" :maxDate="oneYearAhead" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
					<span v-if="isScheduledDate" class="mt-1 block text-xs text-amber-700">{{ ctrans("A later date records a scheduled payment. It counts as paid only from that date.") }}</span>
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Payment method") }}</span>
					<Select v-model="payment.method" :options="supplier.methods" optionLabel="name" optionValue="code" class="mt-1 w-full" :class="fieldFocusClass" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Payment reference") }}</span>
					<InputText v-model="payment.reference" maxlength="255" class="mt-1 w-full" :class="fieldFocusClass" />
				</label>
				<div class="block text-sm">
					<span class="text-gray-600">{{ needsStatement ? ctrans("Bank statement") : ctrans("Receipt (optional)") }}</span>
					<div class="mt-1 flex flex-wrap items-center gap-2">
						<FileUpload mode="basic" :auto="false" customUpload accept=".pdf,image/*" :maxFileSize="20000000" :chooseLabel="ctrans('Choose file')" :pt="chooseFilePt" @select="(event: { files: File[] }) => (payment.file = event.files[0] ?? null)" />
					</div>
				</div>
			</div>
			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="isPaymentDialogOpen = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" :label="ctrans('Record payment')" :disabled="!canSubmit" :loading="isLoading" @click="storePayment">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</template>
		</Dialog>
	</div>
</template>
