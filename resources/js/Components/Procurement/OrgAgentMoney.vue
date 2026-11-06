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
import InputText from "primevue/inputtext"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faPaperclip, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableAgentMoneyRequests, { MoneyRequestRow } from "@/Components/Tables/Grp/Org/Procurement/TableAgentMoneyRequests.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faCheck, faPaperclip, faTimes)

const props = defineProps<{
	data: {
		agent_currency_code: string
		client_currency_code: string
		wallet: { received: number; paid: number; balance: number }
		requests: object
	}
	tab?: string
}>()

const locale = useLocaleStore()
const money = (code: string, amount: number) => locale.currencyFormat(code, amount)
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const fullWidthInput = { pcInputText: { root: { class: "!w-full" } } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const paying = ref<MoneyRequestRow | null>(null)
const isOpen = computed({ get: () => paying.value !== null, set: (open: boolean) => !open && (paying.value = null) })
const saving = ref(false)
const payError = ref<string | null>(null)
const approvingId = ref<number | null>(null)
const approveError = ref<string | null>(null)

const firstError = (errors: Record<string, string> | undefined) => Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")

const approve = (row: MoneyRequestRow) => changeApproval(row, row.approve_route)
const unapprove = (row: MoneyRequestRow) => changeApproval(row, row.unapprove_route)

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
const form = ref({ date: new Date() as Date | null, amount: null as number | null, original_amount: null as number | null, reference: "", receipt: null as File | null })

const openPay = (row: MoneyRequestRow) => {
	form.value = { date: new Date(), amount: row.outstanding, original_amount: null, reference: "", receipt: null }
	payError.value = null
	paying.value = row
}

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const pay = () => {
	const row = paying.value
	if (!row?.pay_route || !form.value.date || !form.value.amount || !form.value.receipt) return
	payError.value = null
	router.post(
		route(row.pay_route.name, row.pay_route.parameters),
		{
			date: toIsoDate(form.value.date),
			amount: form.value.amount,
			original_amount: form.value.original_amount,
			reference: form.value.reference || null,
			receipt: form.value.receipt,
		},
		{
			preserveScroll: true,
			forceFormData: true,
			onStart: () => (saving.value = true),
			onFinish: () => (saving.value = false),
			onSuccess: () => (paying.value = null),
			onError: (errors) => (payError.value = firstError(errors)),
		}
	)
}
</script>

<template>
	<div class="mx-4 mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
		<div class="rounded-lg border border-gray-200 bg-white p-4">
			<div class="text-xs text-gray-400">{{ ctrans("Balance with the agent") }}</div>
			<div class="mt-1 text-lg font-semibold" :class="data.wallet.balance < 0 ? 'text-red-600' : 'text-gray-800'">
				{{ money(data.agent_currency_code, data.wallet.balance) }}
			</div>
		</div>
		<div class="rounded-lg border border-gray-200 bg-white p-4">
			<div class="text-xs text-gray-400">{{ ctrans("Sent to the agent") }}</div>
			<div class="mt-1 text-lg font-semibold text-gray-800">{{ money(data.agent_currency_code, data.wallet.received) }}</div>
		</div>
		<div class="rounded-lg border border-gray-200 bg-white p-4">
			<div class="text-xs text-gray-400">{{ ctrans("Paid out by the agent") }}</div>
			<div class="mt-1 text-lg font-semibold text-gray-800">{{ money(data.agent_currency_code, data.wallet.paid) }}</div>
		</div>
	</div>

	<p v-if="approveError" class="mx-4 mt-4 text-xs text-red-600">{{ approveError }}</p>

	<TableAgentMoneyRequests :data="data.requests" :tab="tab" :approvingId="approvingId" @approve="approve" @unapprove="unapprove" @pay="openPay" />

	<Dialog v-model:visible="isOpen" modal :header="ctrans('Pay :reference', { reference: paying?.reference ?? '' })" :style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="paying" class="space-y-3">
			<p class="text-sm text-gray-600">
				{{ ctrans("Record the transfer you sent to the agent. It goes into your balance with the agent and settles the request once it covers :amount.", { amount: money(paying.currency_code, paying.outstanding) }) }}
				{{ ctrans("The agent and the people copied on this agent's money requests get an email with the bank statement.") }}
			</p>
			<div class="grid grid-cols-2 gap-3">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date sent") }}</span>
					<DatePicker v-model="form.date" dateFormat="dd/mm/yy" :maxDate="new Date()" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount sent (:currency)", { currency: data.client_currency_code }) }}</span>
					<InputNumber v-model="form.original_amount" mode="currency" :currency="data.client_currency_code" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
			</div>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Amount the agent receives (:currency)", { currency: paying.currency_code }) }}</span>
				<InputNumber v-model="form.amount" mode="currency" :currency="paying.currency_code" :min="0" :max="paying.outstanding" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
			</label>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Reference") }}</span>
				<InputText v-model="form.reference" maxlength="255" class="mt-1 w-full" :class="fieldFocusClass" />
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
			<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="paying = null">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Button type="primary" full :label="ctrans('Record payment')" :disabled="!form.date || !form.amount || !form.receipt" :loading="saving" @click="pay">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</div>
	</Dialog>
</template>
