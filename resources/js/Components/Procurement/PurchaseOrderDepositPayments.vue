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
import { faArrowLeft, faBell, faCheck, faCheckCircle, faInfoCircle, faPaperclip, faPlus, faTimes, faUndo } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faArrowLeft, faBell, faCheck, faCheckCircle, faInfoCircle, faPaperclip, faPlus, faTimes, faUndo)

export interface DepositPayment {
	id: number
	date: string
	amount: number
	original_amount?: number | null
	original_currency_code?: string | null
	method: string
	reference: string | null
	paid_by: string | null
	created_at: string | null
	bank_statement: { ulid: string; caption: string | null } | null
	undo_route: routeType
}

export interface PurchaseOrderPaymentsData {
	can_confirm: boolean
	currency_code: string | null
	currencies?: { id: number; code: string; name: string; rate?: number | null }[]
	methods: { name: string; code: string }[]
	total: number
	paid: number
	left: number
	default_deposit?: { percentage: number; amount: number } | null
	balance_awaits_qc?: boolean
	paid_at: string | null
	deposits: DepositPayment[]
	confirm_route: routeType
	balance_reminder?: BalanceReminder | null
}

export interface BalanceReminder {
	organisation: string
	last_reminded_at: string | null
	last_reminded_by: string | null
	number_reminders: number
	next_reminder_at: string | null
	recipients?: { to: BalanceReminderRecipient[]; cc: BalanceReminderRecipient[] }
	route: routeType
}

export interface BalanceReminderRecipient {
	name: string
	email: string | null
	by_email: boolean
	is_buyer: boolean
}

const props = defineProps<{
	payments: PurchaseOrderPaymentsData
	orgCurrencyCode?: string | null
	orgRate?: number | null
	showsBalanceReminder?: boolean
}>()

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const quietLinkClass = "inline-flex items-center gap-1 text-gray-500 underline decoration-gray-300 hover:text-gray-800"
const dialogPt = { header: { class: "!px-4 !py-2.5 border-b border-gray-200" }, content: { class: "!p-4" }, footer: { class: "!px-4 !pb-4" } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const money = (amount: number) => locale.currencyFormat(props.payments.currency_code ?? "", amount)
const orgMoney = (amount: number | null) => (props.orgRate && props.orgCurrencyCode && amount ? locale.currencyFormat(props.orgCurrencyCode, amount * props.orgRate) : null)
const formatDate = (date: string) => useFormatTime(date, { formatTime: "d MMM yyyy" })
const formatDateTime = (date: string) => useFormatTime(date, { formatTime: "d MMM yyyy HH:mm" })

const paymentAddedTooltip = (payment: DepositPayment) => {
	if (!payment.created_at) {
		return payment.paid_by ? ctrans("confirmed by :name", { name: payment.paid_by }) : undefined
	}
	const time = formatDateTime(payment.created_at)
	return payment.paid_by ? ctrans("confirmed by :name on :time", { name: payment.paid_by, time }) : ctrans("added on :time", { time })
}

const isFullyPaid = computed(() => props.payments.left <= 0)
const defaultDeposit = computed(() => props.payments.default_deposit ?? null)
const formatPercentage = (percentage: number) => `${Number(percentage.toFixed(2))}%`
const isMarkedPaidWithoutDeposits = computed(() => !!props.payments.paid_at && !props.payments.deposits.length)
const isNextPaymentBalance = computed(() => props.payments.deposits.length > 0)
const payButtonLabel = computed(() => (isNextPaymentBalance.value ? ctrans("Pay balance") : ctrans("Pay deposit")))
const paymentLabel = (index: number) => (index === 0 ? ctrans("Deposit") : ctrans("Balance payment"))

const firstError = (errors: Record<string, string>) => Object.values(errors)[0] ?? ctrans("Something went wrong")

const balanceReminder = computed(() => (props.showsBalanceReminder ? props.payments.balance_reminder ?? null : null))
const isReminding = ref(false)
const reminderError = ref<string | null>(null)
const reminderSent = ref(false)

const isReminderDialogOpen = ref(false)
const reminderGroups = computed(() => [
	{ label: ctrans("To"), recipients: balanceReminder.value?.recipients?.to ?? [] },
	{ label: ctrans("Cc"), recipients: balanceReminder.value?.recipients?.cc ?? [] },
])
const hasReminderRecipients = computed(() => reminderGroups.value.some((group) => group.recipients.length > 0))

const openReminderDialog = () => {
	reminderError.value = null
	reminderSent.value = false
	isReminderDialogOpen.value = true
}

const remindBalance = () => {
	if (!balanceReminder.value) return
	reminderError.value = null
	router.post(route(balanceReminder.value.route.name, balanceReminder.value.route.parameters), {}, {
		preserveScroll: true,
		onStart: () => (isReminding.value = true),
		onFinish: () => (isReminding.value = false),
		onSuccess: () => {
			reminderSent.value = true
			isReminderDialogOpen.value = false
		},
		onError: (errors) => (reminderError.value = firstError(errors)),
	})
}

const isLoading = ref(false)

const isPayDialogOpen = ref(false)
const payError = ref<string | null>(null)
const currencies = computed(() => props.payments.currencies ?? [])
const orderCurrencyId = computed(() => currencies.value.find((currency) => currency.code === props.payments.currency_code)?.id ?? null)
const emptyForm = () => ({ amount: null as number | null, currency_id: orderCurrencyId.value, original_amount: null as number | null, paid_at: new Date() as Date | null, method: "bank_transfer", reference: "", file: null as File | null, reason: "" })
const form = ref(emptyForm())
const sentCurrency = computed(() => currencies.value.find((currency) => currency.id === form.value.currency_id) ?? null)
const isSentInOtherCurrency = computed(() => !!sentCurrency.value && sentCurrency.value.id !== orderCurrencyId.value)
const isCountedAmountTyped = ref(false)

const openPayDialog = (requestedAmount: number | null = null) => {
	form.value = emptyForm()
	isCountedAmountTyped.value = false
	percent.value = null
	payError.value = null
	const suggestedAmount = requestedAmount ?? defaultDeposit.value?.amount ?? null
	if (suggestedAmount !== null) setAmount(Math.min(suggestedAmount, props.payments.left))
	isPayDialogOpen.value = true
}

defineExpose({ openPayDialog })

const roundTo2Decimals = (value: number) => Math.round(value * 100) / 100
const canPayByPercent = computed(() => props.payments.total > 0)
const maxPercent = computed(() => (canPayByPercent.value ? roundTo2Decimals((props.payments.left / props.payments.total) * 100) : 0))

const percent = ref<number | null>(null)
const typedValue = (event: InputNumberInputEvent) => (typeof event.value === "number" ? event.value : null)

const onAmountTyped = (amount: number | null) => {
	isCountedAmountTyped.value = amount !== null
	setAmount(amount)
}

const setAmount = (amount: number | null) => {
	form.value.amount = amount
	percent.value = amount !== null && canPayByPercent.value ? roundTo2Decimals((amount / props.payments.total) * 100) : null
}

const onPercentTyped = (typedPercent: number | null) => {
	isCountedAmountTyped.value = typedPercent !== null
	percent.value = typedPercent
	form.value.amount = typedPercent !== null ? Math.min(roundTo2Decimals((props.payments.total * typedPercent) / 100), props.payments.left) : null
}

const sentToOrderRate = computed(() => (sentCurrency.value?.code === props.orgCurrencyCode && props.orgRate ? 1 / props.orgRate : Number(sentCurrency.value?.rate) || null))

const onSentAmountTyped = (sentAmount: number | null) => {
	form.value.original_amount = sentAmount
	if (isCountedAmountTyped.value || !sentToOrderRate.value) return
	setAmount(sentAmount !== null ? Math.min(roundTo2Decimals(sentAmount * sentToOrderRate.value), props.payments.left) : null)
}

const onCurrencyChanged = () => {
	form.value.original_amount = null
	if (!isCountedAmountTyped.value) setAmount(null)
}

const needsStatement = computed(() => form.value.method !== "cash")
const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const isPastDate = computed(() => !!form.value.paid_at && toIsoDate(form.value.paid_at) < toIsoDate(new Date(Date.now() - 86400000)))
const needsReason = computed(() => isPastDate.value || !!props.payments.balance_awaits_qc)
const canSubmit = computed(
	() =>
		!!form.value.amount &&
		form.value.amount <= props.payments.left &&
		(!isSentInOtherCurrency.value || !!form.value.original_amount) &&
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
			original_currency_id: isSentInOtherCurrency.value ? form.value.currency_id : null,
			original_amount: isSentInOtherCurrency.value ? form.value.original_amount : null,
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
		<div v-if="defaultDeposit" class="flex items-baseline justify-between gap-2" v-tooltip="ctrans('This supplier usually asks for a deposit of :percentage of the total to pay', { percentage: formatPercentage(defaultDeposit.percentage) })">
			<span class="text-gray-500">{{ ctrans("Default deposit (:percentage)", { percentage: formatPercentage(defaultDeposit.percentage) }) }}</span>
			<span class="tabular-nums text-gray-700">{{ money(defaultDeposit.amount) }}</span>
		</div>

		<div v-if="isMarkedPaidWithoutDeposits" class="flex items-center gap-1 text-gray-400">
			<FontAwesomeIcon icon="fal fa-check-circle" class="text-gray-400" aria-hidden="true" />
			{{ ctrans("Marked paid :date, no deposit recorded", { date: formatDate(payments.paid_at as string) }) }}
		</div>

		<ul v-if="payments.deposits.length" class="space-y-1.5">
			<li v-for="(payment, index) in payments.deposits" :key="payment.id" class="rounded border border-gray-200 bg-white px-2 py-1.5">
				<div class="flex items-baseline justify-between gap-2">
					<span class="text-gray-600" v-tooltip="paymentAddedTooltip(payment)">
						{{ paymentLabel(index) }} · {{ formatDate(payment.date) }}
					</span>
					<span class="tabular-nums font-medium text-gray-700">{{ money(payment.amount) }}</span>
				</div>
				<div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-gray-400">
					<span v-if="payment.original_amount && payment.original_currency_code">{{ ctrans("sent as :amount", { amount: String(locale.currencyFormat(payment.original_currency_code, payment.original_amount)) }) }}</span>
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

		<Button v-if="payments.can_confirm && !isFullyPaid" type="tertiary" size="xs" full :label="payButtonLabel" :tooltip="isNextPaymentBalance ? ctrans('Click to record a balance payment to the supplier') : ctrans('Click to record a deposit paid to the supplier')" @click="openPayDialog()">
			<template #icon>
				<FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
			</template>
		</Button>

		<div v-if="balanceReminder" class="space-y-1">
			<Button
				type="tertiary"
				size="xs"
				full
				:label="ctrans('Remind :organisation about the balance', { organisation: balanceReminder.organisation })"
				:tooltip="ctrans('Email and notify the payers of :organisation and the buyer: pay the balance if not paid yet, or upload the bank statement if already paid', { organisation: balanceReminder.organisation })"
				:disabled="!!balanceReminder.next_reminder_at"
				:loading="isReminding"
				@click="openReminderDialog">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-bell" class="text-amber-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<p v-if="reminderSent" class="text-green-600">{{ ctrans("Reminder sent") }}</p>
			<p v-if="balanceReminder.last_reminded_at" class="text-gray-400">
				{{
					balanceReminder.last_reminded_by
						? ctrans("Reminded :count times, last :time by :name", { count: balanceReminder.number_reminders, time: formatDateTime(balanceReminder.last_reminded_at), name: balanceReminder.last_reminded_by })
						: ctrans("Reminded :count times, last :time", { count: balanceReminder.number_reminders, time: formatDateTime(balanceReminder.last_reminded_at) })
				}}
				<template v-if="balanceReminder.next_reminder_at"> · {{ ctrans("next from :time", { time: formatDateTime(balanceReminder.next_reminder_at) }) }}</template>
			</p>
		</div>

		<Dialog v-if="balanceReminder" v-model:visible="isReminderDialogOpen" modal dismissableMask :closable="false" :pt="dialogPt" class="w-full max-w-md">
			<template #header>
				<div class="flex w-full items-center justify-between gap-3">
					<span class="text-sm font-semibold text-gray-900">{{ ctrans("Remind :organisation about the balance", { organisation: balanceReminder.organisation }) }}</span>
					<button type="button" class="text-gray-400 hover:text-gray-700" :aria-label="ctrans('Close')" @click="isReminderDialogOpen = false">
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
					</button>
				</div>
			</template>
			<div class="space-y-3 text-sm">
				<p class="text-gray-600">
					{{ ctrans("The payers of :organisation and the order's buyer get an email and an aiku notification: pay the balance of :amount, or upload its bank statement if it is already paid.", { organisation: balanceReminder.organisation, amount: money(payments.left) }) }}
				</p>
				<dl class="space-y-2">
					<div v-for="group in reminderGroups" :key="group.label" class="flex gap-3">
						<dt class="w-8 shrink-0 font-medium text-gray-500">{{ group.label }}</dt>
						<dd class="min-w-0 flex-1">
							<span v-if="!group.recipients.length" class="text-gray-400">{{ ctrans("Nobody") }}</span>
							<ul v-else class="space-y-1">
								<li v-for="recipient in group.recipients" :key="`${recipient.name}-${recipient.email}`" :class="recipient.by_email ? 'text-gray-900' : 'text-gray-400'">
									<span class="font-medium">{{ recipient.name }}</span>
									<span v-if="recipient.is_buyer" class="ml-1 text-xs text-gray-500">· {{ ctrans("Buyer") }}</span>
									<span class="ml-1 break-all text-xs" :class="recipient.by_email ? 'text-gray-500' : ''">{{ recipient.email ?? ctrans("no email") }}</span>
									<span v-if="!recipient.by_email" class="block text-xs">{{ recipient.email ? ctrans("Email off: only notified in aiku") : ctrans("Only notified in aiku") }}</span>
								</li>
							</ul>
						</dd>
					</div>
				</dl>
				<p v-if="!hasReminderRecipients" class="text-xs text-red-600">
					{{ ctrans("Nobody in :organisation can be reminded: set the payers of its money requests on its agent page, or a buyer on the order.", { organisation: balanceReminder.organisation }) }}
				</p>
				<p v-if="reminderError" class="text-xs text-red-600">{{ reminderError }}</p>
			</div>
			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Back')" @click="isReminderDialogOpen = false">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-arrow-left" class="text-gray-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="primary" full :label="ctrans('Send reminder')" :disabled="!hasReminderRecipients" :loading="isReminding" @click="remindBalance">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-bell" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>

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
				<label v-if="currencies.length > 1" class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Currency the payment was sent in") }} <span class="text-red-500">*</span></span>
					<Select v-model="form.currency_id" :options="currencies" optionValue="id" :optionLabel="(currency: { code: string; name: string }) => `${currency.code} · ${currency.name}`" class="mt-1 w-full" :class="fieldFocusClass" @change="onCurrencyChanged" />
				</label>
				<label v-if="isSentInOtherCurrency" class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount sent") }} <span class="text-red-500">*</span></span>
					<InputNumber :modelValue="form.original_amount" @update:modelValue="onSentAmountTyped" @input="(event: InputNumberInputEvent) => onSentAmountTyped(typedValue(event))" v-bind="locale.currencyInputOptions(sentCurrency?.code)" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
				</label>
				<div class="block text-sm">
					<div class="flex gap-2">
						<label class="min-w-0 flex-1">
							<span class="text-gray-600" v-tooltip="isSentInOtherCurrency ? ctrans('What the payment is worth in the currency of the order: this is what is taken off what is left to pay') : undefined">{{ isSentInOtherCurrency ? ctrans("Counts on the order as") : ctrans("Amount") }} <span class="text-red-500">*</span></span>
							<InputNumber :modelValue="form.amount" @update:modelValue="onAmountTyped" @input="(event: InputNumberInputEvent) => onAmountTyped(typedValue(event))" v-bind="locale.currencyInputOptions(payments.currency_code)" :min="0" :max="payments.left" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
						</label>
						<label v-if="canPayByPercent" class="w-28 shrink-0">
							<span class="text-gray-600" v-tooltip="ctrans('Percentage of the total to pay (:total): the price of the products, without extra costs, shipping or duties, not of what is left to pay', { total: String(money(payments.total)) })">
								{{ ctrans("% of total") }}
								<FontAwesomeIcon icon="fal fa-info-circle" class="text-gray-400" aria-hidden="true" />
							</span>
							<InputNumber :modelValue="percent" @update:modelValue="onPercentTyped" @input="(event: InputNumberInputEvent) => onPercentTyped(typedValue(event))" suffix="%" :min="0" :max="maxPercent" :minFractionDigits="0" :maxFractionDigits="2" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
						</label>
					</div>
					<span class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-gray-400">
						<span v-if="!isSentInOtherCurrency && orgMoney(form.amount)">{{ orgMoney(form.amount) }} ·</span>
						<button
							type="button"
							class="cursor-pointer underline decoration-dotted underline-offset-2 hover:text-[var(--app-accent)]"
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
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isPayDialogOpen = false">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="primary" full :label="ctrans('Confirm payment')" :disabled="!canSubmit" :loading="isLoading" @click="confirmPayment">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>

		<Dialog :visible="!!paymentToUndo" modal :closable="false" :pt="dialogPt" class="w-full max-w-md" @update:visible="(visible: boolean) => !visible && (paymentToUndo = null)">
			<template #header>
				<span class="text-sm font-semibold text-gray-900">{{ ctrans("Undo payment") }}</span>
			</template>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Why is this payment taken back? (kept in the order's history)") }} <span class="text-red-500">*</span></span>
				<Textarea v-model="undoReason" rows="2" autoResize maxlength="500" autofocus class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<p v-if="undoError" class="mt-2 text-xs text-red-600">{{ undoError }}</p>
			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="paymentToUndo = null">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="tertiary" full :label="ctrans('Undo payment')" :disabled="!undoReason.trim()" :loading="undoingPaymentId !== null" @click="undoPayment">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-undo" class="text-amber-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
