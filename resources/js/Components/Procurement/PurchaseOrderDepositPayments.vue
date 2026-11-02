<script setup lang="ts">
import { ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputNumber, { type InputNumberInputEvent } from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import FileUpload from "primevue/fileupload"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faCheckCircle, faInfoCircle, faPaperclip, faPlus, faTimes, faUndo } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faCheck, faCheckCircle, faInfoCircle, faPaperclip, faPlus, faTimes, faUndo)

export interface DepositPayment {
	id: number
	date: string
	amount: number
	method: string
	reference: string | null
	paid_by: string | null
	bank_statement: { ulid: string; caption: string | null } | null
	undo_route: routeType
}

export interface PurchaseOrderPaymentsData {
	can_confirm: boolean
	currency_code: string | null
	methods: { name: string; code: string }[]
	total: number
	paid: number
	left: number
	balance_awaits_qc?: boolean
	paid_at: string | null
	deposits: DepositPayment[]
	confirm_route: routeType
}

const props = defineProps<{
	payments: PurchaseOrderPaymentsData
	orgCurrencyCode?: string | null
	orgRate?: number | null
}>()

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] focus:!border-[--app-accent]"
const quietLinkClass = "inline-flex items-center gap-1 text-gray-500 underline decoration-gray-300 hover:text-gray-800"
const dialogPt = { header: { class: "!px-4 !py-2.5 border-b border-gray-200" }, content: { class: "!p-4" }, footer: { class: "!px-4 !pb-4" } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const money = (amount: number) => locale.currencyFormat(props.payments.currency_code ?? "", amount)
const orgMoney = (amount: number | null) => (props.orgRate && props.orgCurrencyCode && amount ? locale.currencyFormat(props.orgCurrencyCode, amount * props.orgRate) : null)
const formatDate = (date: string) => useFormatTime(date, { formatTime: "d MMM yyyy" })

const isFullyPaid = computed(() => props.payments.left <= 0)
const isMarkedPaidWithoutDeposits = computed(() => !!props.payments.paid_at && !props.payments.deposits.length)
const isNextPaymentBalance = computed(() => props.payments.deposits.length > 0)
const payButtonLabel = computed(() => (isNextPaymentBalance.value ? ctrans("Pay balance") : ctrans("Pay deposit")))
const paymentLabel = (index: number) => (index === 0 ? ctrans("Deposit") : ctrans("Balance payment"))

const firstError = (errors: Record<string, string>) => Object.values(errors)[0] ?? ctrans("Something went wrong")

const isLoading = ref(false)

const isPayDialogOpen = ref(false)
const payError = ref<string | null>(null)
const form = ref<{ amount: number | null; paid_at: Date | null; method: string; reference: string; file: File | null; reason: string }>({ amount: null, paid_at: new Date(), method: "bank_transfer", reference: "", file: null, reason: "" })

const openPayDialog = () => {
	form.value = { amount: null, paid_at: new Date(), method: "bank_transfer", reference: "", file: null, reason: "" }
	percent.value = null
	payError.value = null
	isPayDialogOpen.value = true
}

const roundTo2Decimals = (value: number) => Math.round(value * 100) / 100
const canPayByPercent = computed(() => props.payments.total > 0)
const maxPercent = computed(() => (canPayByPercent.value ? roundTo2Decimals((props.payments.left / props.payments.total) * 100) : 0))

const percent = ref<number | null>(null)
const typedValue = (event: InputNumberInputEvent) => (typeof event.value === "number" ? event.value : null)

const onAmountTyped = (amount: number | null) => {
	form.value.amount = amount
	percent.value = amount !== null && canPayByPercent.value ? roundTo2Decimals((amount / props.payments.total) * 100) : null
}

const onPercentTyped = (typedPercent: number | null) => {
	percent.value = typedPercent
	form.value.amount = typedPercent !== null ? Math.min(roundTo2Decimals((props.payments.total * typedPercent) / 100), props.payments.left) : null
}

const needsStatement = computed(() => form.value.method !== "cash")
const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const isPastDate = computed(() => !!form.value.paid_at && toIsoDate(form.value.paid_at) < toIsoDate(new Date(Date.now() - 86400000)))
const needsReason = computed(() => isPastDate.value || !!props.payments.balance_awaits_qc)
const canSubmit = computed(
	() =>
		!!form.value.amount &&
		form.value.amount <= props.payments.left &&
		!!form.value.paid_at &&
		!!form.value.method &&
		(!needsStatement.value || !!form.value.file) &&
		(!needsReason.value || !!form.value.reason.trim())
)

const confirmPayment = () => {
	if (!canSubmit.value) return
	router.post(
		route(props.payments.confirm_route.name, props.payments.confirm_route.parameters),
		{
			amount: form.value.amount,
			paid_at: toIsoDate(form.value.paid_at as Date),
			method: form.value.method,
			reference: form.value.reference || null,
			bank_statement: form.value.file,
			reason: needsReason.value ? form.value.reason.trim() : null,
		},
		{
			preserveScroll: true,
			forceFormData: true,
			onStart: () => {
				isLoading.value = true
				payError.value = null
			},
			onFinish: () => (isLoading.value = false),
			onError: (errors) => (payError.value = firstError(errors)),
			onSuccess: () => (isPayDialogOpen.value = false),
		}
	)
}

const undoingPaymentId = ref<number | null>(null)
const paymentToUndo = ref<DepositPayment | null>(null)
const undoReason = ref("")
const undoError = ref<string | null>(null)

const openUndo = (payment: DepositPayment) => {
	undoReason.value = ""
	undoError.value = null
	paymentToUndo.value = payment
}

const undoPayment = () => {
	const payment = paymentToUndo.value
	if (!payment || !undoReason.value.trim()) return
	router.delete(route(payment.undo_route.name, payment.undo_route.parameters), {
		data: { reason: undoReason.value.trim() },
		preserveScroll: true,
		onStart: () => {
			undoingPaymentId.value = payment.id
			undoError.value = null
		},
		onFinish: () => (undoingPaymentId.value = null),
		onSuccess: () => (paymentToUndo.value = null),
		onError: (errors) => (undoError.value = firstError(errors)),
	})
}
</script>

<template>
	<div class="mt-2 space-y-2 border-t border-gray-200 pt-2 text-xs">
		<div class="flex items-baseline justify-between gap-2">
			<span class="text-gray-500">{{ ctrans("Total to pay") }}</span>
			<span class="tabular-nums text-gray-700">{{ money(payments.total) }}</span>
		</div>
		<div class="flex items-baseline justify-between gap-2">
			<span class="text-gray-500">{{ ctrans("Paid") }}</span>
			<span class="tabular-nums" :class="isFullyPaid ? 'text-green-600' : 'text-gray-700'">{{ money(payments.paid) }}</span>
		</div>
		<div class="flex items-baseline justify-between gap-2 font-medium">
			<span class="text-gray-600">{{ ctrans("Left to pay") }}</span>
			<span class="tabular-nums" :class="isFullyPaid ? 'text-green-600' : 'text-amber-600'">{{ money(payments.left) }}</span>
		</div>

		<div v-if="isMarkedPaidWithoutDeposits" class="flex items-center gap-1 text-gray-400">
			<FontAwesomeIcon icon="fal fa-check-circle" class="text-gray-400" aria-hidden="true" />
			{{ ctrans("Marked paid :date, no deposit recorded", { date: formatDate(payments.paid_at as string) }) }}
		</div>

		<ul v-if="payments.deposits.length" class="space-y-1.5">
			<li v-for="(payment, index) in payments.deposits" :key="payment.id" class="rounded border border-gray-200 bg-white px-2 py-1.5">
				<div class="flex items-baseline justify-between gap-2">
					<span class="text-gray-600" v-tooltip="payment.paid_by ? ctrans('confirmed by :name', { name: payment.paid_by }) : undefined">
						{{ paymentLabel(index) }} · {{ formatDate(payment.date) }}
					</span>
					<span class="tabular-nums font-medium text-gray-700">{{ money(payment.amount) }}</span>
				</div>
				<div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-gray-400">
					<span>{{ [payment.method, payment.reference].filter(Boolean).join(" · ") }}</span>
					<a
						v-if="payment.bank_statement"
						v-tooltip="payment.bank_statement.caption"
						:href="route('grp.media.download', { media: payment.bank_statement.ulid })"
						target="_blank"
						:class="quietLinkClass">
						<FontAwesomeIcon icon="fal fa-paperclip" aria-hidden="true" />
						{{ ctrans("Bank statement") }}
					</a>
					<button
						v-if="payments.can_confirm"
						type="button"
						:class="quietLinkClass"
						:disabled="undoingPaymentId === payment.id"
						@click="openUndo(payment)">
						<FontAwesomeIcon icon="fal fa-undo" class="text-amber-500" aria-hidden="true" />
						{{ ctrans("Undo") }}
					</button>
				</div>
			</li>
		</ul>

		<Button v-if="payments.can_confirm && !isFullyPaid" type="tertiary" size="xs" full :label="payButtonLabel" :tooltip="isNextPaymentBalance ? ctrans('Click to record a balance payment to the supplier') : ctrans('Click to record a deposit paid to the supplier')" @click="openPayDialog">
			<template #icon>
				<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
			</template>
		</Button>

		<Dialog v-model:visible="isPayDialogOpen" modal dismissableMask :closable="false" :pt="dialogPt" class="w-full max-w-md">
			<template #header>
				<div class="flex w-full items-center justify-between gap-3">
					<span class="text-sm font-semibold text-gray-900">{{ payButtonLabel }}</span>
					<button type="button" class="text-gray-400 hover:text-gray-700" :aria-label="ctrans('Close')" @click="isPayDialogOpen = false">
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
					</button>
				</div>
			</template>
			<div class="space-y-3">
				<div class="block text-sm">
					<div class="flex gap-2">
						<label class="min-w-0 flex-1">
							<span class="text-gray-600">{{ ctrans("Amount") }} <span class="text-red-500">*</span></span>
							<InputNumber :modelValue="form.amount" @update:modelValue="onAmountTyped" @input="(event: InputNumberInputEvent) => onAmountTyped(typedValue(event))" v-bind="locale.currencyInputOptions(payments.currency_code)" :min="0" :max="payments.left" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
						</label>
						<label v-if="canPayByPercent" class="w-28 shrink-0">
							<span class="text-gray-600" v-tooltip="ctrans('Percentage of the total to pay (:total): the order cost plus the supplier\'s estimated expenses, not of what is left to pay', { total: String(money(payments.total)) })">
								{{ ctrans("% of total") }}
								<FontAwesomeIcon icon="fal fa-info-circle" class="text-gray-400" aria-hidden="true" />
							</span>
							<InputNumber :modelValue="percent" @update:modelValue="onPercentTyped" @input="(event: InputNumberInputEvent) => onPercentTyped(typedValue(event))" suffix="%" :min="0" :max="maxPercent" :minFractionDigits="0" :maxFractionDigits="2" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
						</label>
					</div>
					<span class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-gray-400">
						<span v-if="orgMoney(form.amount)">{{ orgMoney(form.amount) }} ·</span>
						<button
							type="button"
							class="cursor-pointer underline decoration-dotted underline-offset-2 hover:text-[--app-accent]"
							v-tooltip="ctrans('Click to fill in the amount left to pay')"
							@click="onAmountTyped(payments.left)"
						>
							{{ ctrans("Left to pay :amount", { amount: String(money(payments.left)) }) }}
						</button>
					</span>
				</div>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date the payment was sent") }} <span class="text-red-500">*</span></span>
					<DatePicker v-model="form.paid_at" dateFormat="dd/mm/yy" :maxDate="new Date()" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
				</label>
				<label v-if="needsReason" class="block text-sm">
					<span class="text-gray-600">{{ payments.balance_awaits_qc ? ctrans("The QC is not approved yet. Why is the balance paid now? (kept in the order's history)") : ctrans("Reason for the past date (kept in the order's history)") }} <span class="text-red-500">*</span></span>
					<Textarea v-model="form.reason" rows="2" autoResize maxlength="500" class="mt-1 w-full" :class="fieldFocusClass" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Payment method") }} <span class="text-red-500">*</span></span>
					<Select v-model="form.method" :options="payments.methods" optionLabel="name" optionValue="code" class="mt-1 w-full" :class="fieldFocusClass" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Payment reference") }}</span>
					<InputText v-model="form.reference" maxlength="255" class="mt-1 w-full" :class="fieldFocusClass" />
				</label>
				<div class="block text-sm">
					<span class="text-gray-600">{{ needsStatement ? ctrans("Bank statement") : ctrans("Receipt (optional)") }} <span v-if="needsStatement" class="text-red-500">*</span></span>
					<div class="mt-1 flex flex-wrap items-center gap-2">
						<FileUpload mode="basic" :auto="false" customUpload accept=".pdf,image/*" :maxFileSize="20000000" :chooseLabel="ctrans('Choose file')" :pt="chooseFilePt" @select="(event: { files: File[] }) => (form.file = event.files[0] ?? null)" />
					</div>
				</div>
				<p v-if="payError" class="text-xs text-red-600">{{ payError }}</p>
			</div>
			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="isPayDialogOpen = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="secondary" full :label="ctrans('Confirm payment')" :disabled="!canSubmit" :loading="isLoading" @click="confirmPayment">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</template>
		</Dialog>

		<Dialog :visible="!!paymentToUndo" modal :closable="false" :pt="dialogPt" class="w-full max-w-md" @update:visible="(visible: boolean) => !visible && (paymentToUndo = null)">
			<template #header>
				<span class="text-sm font-semibold text-gray-900">{{ ctrans("Undo payment") }}</span>
			</template>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Why is this payment taken back? (kept in the order's history)") }}</span>
				<Textarea v-model="undoReason" rows="2" autoResize maxlength="500" autofocus class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<p v-if="undoError" class="mt-2 text-xs text-red-600">{{ undoError }}</p>
			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="paymentToUndo = null">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" :label="ctrans('Undo payment')" :disabled="!undoReason.trim()" :loading="undoingPaymentId !== null" @click="undoPayment">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-undo" class="text-amber-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</template>
		</Dialog>
	</div>
</template>
