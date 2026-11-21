<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import DatePicker from "primevue/datepicker"
import Dialog from "primevue/dialog"
import FileUpload from "primevue/fileupload"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBan, faCheck, faHandHoldingUsd, faInfoCircle, faPaperclip, faTimes, faUndo } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableAgentMoneyRequests, { MoneyRequestRow } from "@/Components/Tables/Grp/Org/Procurement/TableAgentMoneyRequests.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faBan, faCheck, faHandHoldingUsd, faInfoCircle, faPaperclip, faTimes, faUndo)

type BankLine = {
	id: number
	date: string
	bank: string
	payee: string | null
	reference: string | null
	sent_amount: number
	sent_currency: string
	received_amount: number | null
	currency_code: string
	amount_to_record: number
	too_much: boolean
	set_aside: boolean
	pay_route: { name: string; parameters: Record<string, unknown> }
	dismiss_route: { name: string; parameters: Record<string, unknown> }
}

type BankTransfer = {
	id: number
	date: string | null
	bank: string | null
	reference: string | null
	amount: number
	currency_code: string
	sent_amount: number | null
	sent_currency: string | null
	requests: string[]
	problem: boolean
	undo_route: { name: string; parameters: Record<string, unknown> } | null
}

const props = defineProps<{
	data: {
		agent_currency_code: string
		client_currency_code: string
		client_rate: number | null
		wallet: { received: number; paid: number; balance: number }
		pay_route: { name: string; parameters: Record<string, unknown> } | null
		bank_lines: BankLine[]
		bank_transfers: BankTransfer[]
		to_pay: {
			currency_code: string | null
			amount: number
			client_amount: number | null
			references: string[]
			requests: { reference: string; currency_code: string; lines: { description: string; reference: string | null; amount: number }[]; total: number; paid: number; due: number }[]
			waiting_approval: number
		}
		requests: object
	}
	tab?: string
}>()

const locale = useLocaleStore()
const money = (code: string, amount: number) => locale.currencyFormat(code, amount)
const bank = (code: string, amount: number) => `${code} ${new Intl.NumberFormat(locale.locale_iso || locale.language.code, { minimumFractionDigits: Number.isInteger(amount) ? 0 : 2, maximumFractionDigits: 2 }).format(amount)}`
const inClientCurrency = (amount: number) => (props.data.client_rate ? `≈ ${money(props.data.client_currency_code, Math.round(amount * props.data.client_rate * 100) / 100)}` : "")
const toPayCurrency = computed(() => props.data.to_pay.currency_code ?? props.data.agent_currency_code)
const isBreakdownOpen = ref(false)
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const fullWidthInput = { pcInputText: { root: { class: "!w-full" } } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const isOpen = ref(false)
const saving = ref(false)
const payError = ref<string | null>(null)
const approvingId = ref<number | null>(null)
const approveError = ref<string | null>(null)

const firstError = (errors: Record<string, string> | undefined) => Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")

const approve = (row: MoneyRequestRow) => changeApproval(row, row.approve_route)

const changeApproval = (row: MoneyRequestRow, approvalRoute: MoneyRequestRow["approve_route"]) => {
	if (!approvalRoute) return
	approveError.value = null
	router.patch(route(approvalRoute.name, approvalRoute.parameters), {}, {
		preserveScroll: true,
		onStart: () => (approvingId.value = row.id),
		onFinish: () => (approvingId.value = null),
		onError: (errors) => (approveError.value = `${row.reference}: ${firstError(errors)}`),
	})
}
const form = ref({ date: new Date() as Date | null, amount: null as number | null, original_amount: null as number | null, receipt: null as File | null })

const rejecting = ref<MoneyRequestRow | null>(null)
const isRejectOpen = computed({ get: () => rejecting.value !== null, set: (open: boolean) => !open && (rejecting.value = null) })
const rejectReason = ref("")
const rejectError = ref<string | null>(null)

const openReject = (row: MoneyRequestRow) => {
	rejectReason.value = ""
	rejectError.value = null
	rejecting.value = row
}

const reject = () => {
	const row = rejecting.value
	if (!row?.reject_route || !rejectReason.value.trim()) return
	rejectError.value = null
	router.patch(route(row.reject_route.name, row.reject_route.parameters), { reason: rejectReason.value.trim() }, {
		preserveScroll: true,
		onStart: () => (saving.value = true),
		onFinish: () => (saving.value = false),
		onSuccess: () => (rejecting.value = null),
		onError: (errors) => (rejectError.value = firstError(errors)),
	})
}

const openPay = () => {
	form.value = { date: new Date(), amount: props.data.to_pay.amount, original_amount: null, receipt: null }
	payError.value = null
	isOpen.value = true
}

const bankLineBusyId = ref<number | null>(null)
const bankLineError = ref<string | null>(null)
const answerBankLine = (line: BankLine, isPayment: boolean) => {
	const lineRoute = isPayment ? line.pay_route : line.dismiss_route
	bankLineError.value = null
	router.visit(route(lineRoute.name, lineRoute.parameters), {
		method: isPayment ? "post" : "patch",
		data: isPayment ? { amount: line.amount_to_record } : {},
		preserveScroll: true,
		onStart: () => (bankLineBusyId.value = line.id),
		onFinish: () => (bankLineBusyId.value = null),
		onError: (errors) => (bankLineError.value = firstError(errors)),
	})
}
const shortDate = (isoDate: string) => new Date(`${isoDate}T00:00:00`).toLocaleDateString(locale.locale_iso || locale.language.code, { day: "numeric", month: "short", year: "numeric" })

const offeredLines = computed(() => (props.data.bank_lines ?? []).filter((line) => !line.set_aside))
const setAsideLines = computed(() => (props.data.bank_lines ?? []).filter((line) => line.set_aside))
const isSetAsideOpen = ref(false)
const confirmUndoId = ref<number | null>(null)
const undo = (transfer: BankTransfer) => {
	if (!transfer.undo_route) return
	bankLineError.value = null
	router.patch(route(transfer.undo_route.name, transfer.undo_route.parameters), {}, {
		preserveScroll: true,
		onStart: () => (bankLineBusyId.value = -transfer.id),
		onFinish: () => (bankLineBusyId.value = null),
		onSuccess: () => (confirmUndoId.value = null),
		onError: (errors) => (bankLineError.value = firstError(errors)),
	})
}

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const pay = () => {
	const payRoute = props.data.pay_route
	if (!payRoute || !form.value.date || !form.value.amount || !form.value.receipt) return
	payError.value = null
	router.post(
		route(payRoute.name, payRoute.parameters),
		{
			date: toIsoDate(form.value.date),
			amount: form.value.amount,
			original_amount: form.value.original_amount,
			receipt: form.value.receipt,
		},
		{
			preserveScroll: true,
			forceFormData: true,
			onStart: () => (saving.value = true),
			onFinish: () => (saving.value = false),
			onSuccess: () => (isOpen.value = false),
			onError: (errors) => (payError.value = firstError(errors)),
		}
	)
}
</script>

<template>
	<div class="mx-4 mt-4 rounded-lg border-2 p-5" :class="data.to_pay.amount > 0 ? 'border-pink-400 bg-pink-50' : 'border-gray-200 bg-white'">
		<template v-if="data.to_pay.amount > 0">
			<div class="text-sm font-semibold uppercase tracking-wide text-pink-800">{{ ctrans("Amount to pay the agent now") }}</div>
			<div class="mt-1 flex flex-wrap items-center gap-3">
				<div class="text-4xl font-bold text-gray-900">{{ bank(toPayCurrency, data.to_pay.amount) }}</div>
				<Button type="tertiary" size="xs" :label="ctrans('Why this amount?')" @click="isBreakdownOpen = true">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="data.pay_route" type="tertiary" :label="ctrans('Set as paid')" class="ml-auto" @click="openPay">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-hand-holding-usd" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
			<div v-if="data.to_pay.client_amount" class="mt-1 text-sm text-gray-500">
				{{ ctrans("About :amount at the latest exchange rate, for information only. The agent must receive :due, so make the transfer in :currency.", { amount: money(data.client_currency_code, data.to_pay.client_amount), due: bank(toPayCurrency, data.to_pay.amount), currency: toPayCurrency }) }}
			</div>
			<p class="mt-2 text-sm text-gray-700">
				{{ ctrans("Send exactly this amount in one bank transfer. It is what is still due on approved requests :requests.", { requests: data.to_pay.references.join(", ") }) }}
				{{ ctrans("Then press Set as paid and attach the bank statement.") }}
			</p>
			<p class="mt-1 text-xs text-gray-500">{{ ctrans("Paid from a bank connected to Aiku? It is set as paid on its own once the bank shows the transfer.") }}</p>
		</template>
		<div v-else class="text-lg font-semibold text-gray-700">{{ ctrans("Nothing to pay the agent now") }}</div>
	</div>
	<p v-if="data.to_pay.waiting_approval > 0" class="mx-4 mt-2 text-sm text-gray-600">
		{{ ctrans(":amount more is waiting for approval. Do not pay it until it is approved.", { amount: bank(toPayCurrency, data.to_pay.waiting_approval) }) }}
	</p>

	<div v-if="offeredLines.length" class="mx-4 mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
		<div class="text-sm font-semibold text-gray-900">{{ ctrans("Is this the payment?") }}</div>
		<p class="mt-1 text-sm text-gray-600">{{ ctrans("Your bank sent these transfers to the agent, but they do not add up exactly to the amount to pay.") }}</p>
		<div v-for="line in offeredLines" :key="line.id" class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border border-amber-200 bg-white p-3">
			<div class="min-w-0 flex-1 text-sm">
				<div class="font-semibold text-gray-900">
					{{ bank(line.sent_currency, line.sent_amount) }}
					<span class="font-normal text-gray-600">{{ ctrans("sent on :date from :bank", { date: shortDate(line.date), bank: line.bank }) }}</span>
				</div>
				<div class="text-xs text-gray-500">
					<span v-if="line.received_amount !== null && line.sent_currency !== line.currency_code">{{ ctrans("The agent received :amount", { amount: bank(line.currency_code, line.received_amount) }) }} · </span>
					<span>{{ line.payee }}</span>
					<span v-if="line.reference"> · {{ line.reference }}</span>
				</div>
				<div v-if="line.received_amount === null" class="mt-1 text-xs text-amber-700">{{ ctrans("Sent in :currency, so the bank does not say what the agent received. Yes sets the whole amount still due as paid.", { currency: line.sent_currency }) }}</div>
				<div v-if="line.too_much" class="mt-1 text-xs text-amber-700">{{ ctrans("That is more than is still due on the approved requests, so it can not be set as paid here.") }}</div>
			</div>
			<div class="flex shrink-0 gap-2">
				<Button type="tertiary" size="xs" :label="ctrans('Not this one')" :loading="bankLineBusyId === line.id" @click="answerBankLine(line, false)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="!line.too_much" type="tertiary" size="xs" :label="ctrans('Yes, set :amount as paid', { amount: bank(line.currency_code, line.amount_to_record) })" :loading="bankLineBusyId === line.id" @click="answerBankLine(line, true)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</div>
	</div>

	<div v-if="setAsideLines.length" class="mx-4 mt-3 text-sm">
		<button type="button" class="text-gray-600 underline hover:text-gray-900" @click="isSetAsideOpen = !isSetAsideOpen">
			{{ ctrans("Transfers you said were not the payment (:count)", { count: setAsideLines.length }) }}
		</button>
		<div v-if="isSetAsideOpen" class="mt-2 space-y-2">
			<div v-for="line in setAsideLines" :key="line.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-md border border-gray-200 bg-white p-3">
				<div class="min-w-0 flex-1">
					<span class="font-semibold text-gray-900">{{ bank(line.sent_currency, line.sent_amount) }}</span>
					<span class="text-gray-600"> {{ ctrans("sent on :date from :bank", { date: shortDate(line.date), bank: line.bank }) }}</span>
					<span v-if="line.reference" class="text-xs text-gray-500"> · {{ line.reference }}</span>
				</div>
				<Button v-if="!line.too_much" type="tertiary" size="xs" :label="ctrans('Yes, set :amount as paid', { amount: bank(line.currency_code, line.amount_to_record) })" :loading="bankLineBusyId === line.id" @click="answerBankLine(line, true)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</div>
	</div>

	<div v-if="data.bank_transfers?.length" class="mx-4 mt-4">
		<div class="text-sm font-semibold text-gray-900">{{ ctrans("Set as paid from your bank") }}</div>
		<div v-for="transfer in data.bank_transfers" :key="transfer.id" class="mt-2 rounded-md border p-3 text-sm" :class="transfer.problem ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white'">
			<div class="flex flex-wrap items-center gap-x-4 gap-y-2">
				<div class="min-w-0 flex-1">
					<span class="font-semibold text-gray-900">{{ bank(transfer.currency_code, transfer.amount) }}</span>
					<span v-if="transfer.sent_amount !== null && transfer.sent_currency" class="text-gray-600"> ({{ ctrans("sent :amount", { amount: bank(transfer.sent_currency, transfer.sent_amount) }) }})</span>
					<span class="text-gray-600"> · {{ transfer.date ? shortDate(transfer.date) : "" }}<span v-if="transfer.bank"> · {{ transfer.bank }}</span></span>
					<div class="text-xs text-gray-500">
						{{ ctrans("Paid requests :requests", { requests: transfer.requests.join(", ") }) }}<span v-if="transfer.reference"> · {{ transfer.reference }}</span>
					</div>
					<div v-if="transfer.problem" class="mt-1 text-xs font-medium text-red-700">{{ ctrans("The bank reversed or changed this transfer. If the agent did not get the money, undo it.") }}</div>
				</div>
				<template v-if="transfer.undo_route">
					<Button v-if="confirmUndoId !== transfer.id" type="tertiary" size="xs" :label="ctrans('Undo')" @click="confirmUndoId = transfer.id">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-undo" class="text-gray-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<div v-else class="flex flex-wrap items-center gap-2">
						<span class="text-xs text-gray-700">{{ ctrans("Requests :requests will be waiting to be paid again.", { requests: transfer.requests.join(", ") }) }}</span>
						<Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="confirmUndoId = null" />
						<Button type="tertiary" size="xs" :label="ctrans('Yes, undo')" :loading="bankLineBusyId === -transfer.id" @click="undo(transfer)">
							<template #icon>
								<FontAwesomeIcon icon="fal fa-undo" class="text-red-500" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</div>
				</template>
			</div>
		</div>
	</div>

	<p v-if="bankLineError" class="mx-4 mt-2 text-xs text-red-600">{{ bankLineError }}</p>

	<div class="mx-4 mt-4 text-xs text-gray-500">{{ ctrans("Your account with the agent, for information only. These are not amounts to pay.") }}</div>
	<div class="mx-4 mt-1 grid grid-cols-1 gap-3 sm:grid-cols-3">
		<div class="rounded-lg border border-gray-200 bg-white p-3">
			<div class="text-xs text-gray-400">{{ ctrans("Balance with the agent") }}</div>
			<div class="mt-1 font-semibold text-gray-600">{{ bank(data.agent_currency_code, data.wallet.balance) }}</div>
			<div v-if="data.client_rate && data.wallet.balance" class="text-xs text-gray-400">{{ inClientCurrency(data.wallet.balance) }}</div>
		</div>
		<div class="rounded-lg border border-gray-200 bg-white p-3">
			<div class="text-xs text-gray-400">{{ ctrans("Sent to the agent") }}</div>
			<div class="mt-1 font-semibold text-gray-600">{{ bank(data.agent_currency_code, data.wallet.received) }}</div>
			<div v-if="data.client_rate && data.wallet.received" class="text-xs text-gray-400">{{ inClientCurrency(data.wallet.received) }}</div>
		</div>
		<div class="rounded-lg border border-gray-200 bg-white p-3">
			<div class="text-xs text-gray-400">{{ ctrans("Paid out by the agent") }}</div>
			<div class="mt-1 font-semibold text-gray-600">{{ bank(data.agent_currency_code, data.wallet.paid) }}</div>
			<div v-if="data.client_rate && data.wallet.paid" class="text-xs text-gray-400">{{ inClientCurrency(data.wallet.paid) }}</div>
		</div>
	</div>

	<Dialog v-model:visible="isBreakdownOpen" modal :header="ctrans('Why you pay :amount', { amount: bank(toPayCurrency, data.to_pay.amount) })" :style="{ width: '44rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-for="request in data.to_pay.requests" :key="request.reference" class="mb-5 last:mb-0">
			<div class="mb-2 text-sm font-semibold text-gray-800">{{ ctrans("Approved request :reference", { reference: request.reference }) }}</div>
			<table class="w-full text-sm">
				<tbody>
					<tr v-for="(line, index) in request.lines" :key="index" class="border-b border-gray-100">
						<td class="py-1 pr-3 text-gray-700">
							{{ line.description }}
							<span v-if="line.reference" class="text-gray-500">· {{ line.reference }}</span>
						</td>
						<td class="whitespace-nowrap py-1 text-right tabular-nums text-gray-800">{{ bank(request.currency_code, line.amount) }}</td>
						<td v-if="data.client_rate" class="whitespace-nowrap py-1 pl-4 text-right text-xs tabular-nums text-gray-400">{{ inClientCurrency(line.amount) }}</td>
					</tr>
					<tr>
						<td class="pt-2 pr-3 font-medium text-gray-700">{{ ctrans("Total asked") }}</td>
						<td class="whitespace-nowrap pt-2 text-right font-medium tabular-nums text-gray-800">{{ bank(request.currency_code, request.total) }}</td>
						<td v-if="data.client_rate" class="whitespace-nowrap py-1 pl-4 text-right text-xs tabular-nums text-gray-400">{{ inClientCurrency(request.total) }}</td>
					</tr>
					<tr v-if="request.paid > 0">
						<td class="pr-3 text-gray-700">{{ ctrans("Already paid by you") }}</td>
						<td class="whitespace-nowrap text-right tabular-nums text-gray-800">−{{ bank(request.currency_code, request.paid) }}</td>
						<td v-if="data.client_rate" class="whitespace-nowrap py-1 pl-4 text-right text-xs tabular-nums text-gray-400">−{{ inClientCurrency(request.paid) }}</td>
					</tr>
					<tr>
						<td class="pt-1 pr-3 font-semibold text-gray-900">{{ ctrans("Still due") }}</td>
						<td class="whitespace-nowrap pt-1 text-right font-semibold tabular-nums text-gray-900">{{ bank(request.currency_code, request.due) }}</td>
						<td v-if="data.client_rate" class="whitespace-nowrap py-1 pl-4 text-right text-xs tabular-nums text-gray-400">{{ inClientCurrency(request.due) }}</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div v-if="data.to_pay.requests.length > 1" class="mt-4 flex justify-between border-t-2 border-gray-300 pt-2 text-base font-bold text-gray-900">
			<span>{{ ctrans("Amount to pay the agent now") }}</span>
			<span class="tabular-nums">{{ bank(toPayCurrency, data.to_pay.amount) }} <span v-if="data.client_rate" class="text-xs font-normal text-gray-400">{{ inClientCurrency(data.to_pay.amount) }}</span></span>
		</div>
		<p v-if="data.client_rate" class="mt-4 text-xs text-gray-500">{{ ctrans("Amounts in :currency are at the latest exchange rate, for information only. Make the transfer in :agent_currency.", { currency: data.client_currency_code, agent_currency: toPayCurrency }) }}</p>
	</Dialog>

	<p v-if="approveError" class="mx-4 mt-4 text-xs text-red-600">{{ approveError }}</p>

	<TableAgentMoneyRequests :data="data.requests" :tab="tab" forClient :clientCurrencyCode="data.client_currency_code" :clientRate="data.client_rate" :approvingId="approvingId" @approve="approve" @reject="openReject" />

	<Dialog v-model:visible="isRejectOpen" modal :header="ctrans('Decline :reference', { reference: rejecting?.reference ?? '' })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="rejecting" class="space-y-3">
			<p class="text-sm text-gray-600">
				{{ ctrans("Nothing will be paid against this request. The agent gets an email with your reason and can ask again with a new request.") }}
			</p>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Reason") }}</span>
				<Textarea v-model="rejectReason" rows="3" autoResize maxlength="2000" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
		</div>
		<p v-if="rejectError" class="mt-3 text-xs text-red-600">{{ rejectError }}</p>
		<div class="mt-5 flex w-full gap-2">
			<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="rejecting = null">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Button type="tertiary" full :label="ctrans('Decline request')" :disabled="!rejectReason.trim()" :loading="saving" @click="reject">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-ban" class="text-amber-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</div>
	</Dialog>

	<Dialog v-model:visible="isOpen" modal :header="ctrans('Set as paid')" :style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-3">
			<div class="rounded-lg border-2 border-pink-400 bg-pink-50 p-3">
				<div class="text-xs font-semibold uppercase tracking-wide text-pink-800">{{ ctrans("Amount to pay the agent now") }}</div>
				<div class="mt-1 text-2xl font-bold text-gray-900">{{ bank(toPayCurrency, data.to_pay.amount) }}</div>
			</div>
			<p class="text-sm text-gray-600">
				{{ ctrans("Record the transfer you already sent from your bank. It settles approved requests :requests, oldest first.", { requests: data.to_pay.references.join(", ") }) }}
				{{ ctrans("The agent and the people copied on this agent's money requests get an email for each request, with the bank statement.") }}
			</p>
			<div class="grid grid-cols-2 gap-3">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date sent") }}</span>
					<DatePicker v-model="form.date" dateFormat="dd/mm/yy" :maxDate="new Date()" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
				<label v-if="data.client_rate" class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount sent (:currency)", { currency: data.client_currency_code }) }}</span>
					<InputNumber v-model="form.original_amount" mode="currency" :currency="data.client_currency_code" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
			</div>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Amount the agent receives (:currency)", { currency: toPayCurrency }) }}</span>
				<InputNumber v-model="form.amount" :min="0" :max="data.to_pay.amount" :minFractionDigits="0" :maxFractionDigits="2" :prefix="`${toPayCurrency} `" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
			</label>
			<div class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Bank statement") }}</span>
				<div class="mt-1">
					<FileUpload mode="basic" :auto="false" customUpload accept=".pdf,image/*" :maxFileSize="20000000" :chooseLabel="ctrans('Choose file')" :pt="chooseFilePt" @select="(event: { files: File[] }) => (form.receipt = event.files[0] ?? null)">
						<template #chooseicon>
							<FontAwesomeIcon icon="fal fa-paperclip" class="text-gray-500" fixed-width aria-hidden="true" />
						</template>
					</FileUpload>
				</div>
			</div>
		</div>
		<p v-if="payError" class="mt-3 text-xs text-red-600">{{ payError }}</p>
		<div class="mt-5 flex w-full gap-2">
			<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isOpen = false">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Button type="primary" full :label="ctrans('Set as paid')" :disabled="!form.date || !form.amount || !form.receipt" :loading="saving" @click="pay">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</div>
	</Dialog>
</template>
