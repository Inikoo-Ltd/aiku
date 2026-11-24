<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 12 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Dialog from "primevue/dialog"
import AutoComplete from "primevue/autocomplete"
import axios from "axios"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import BankLinesFilters from "@/Components/Accounting/BankLinesFilters.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUniversity, faCheck, faCheckDouble, faTimes, faArchive, faReceipt, faUndo, faPiggyBank, faUserCheck } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faUniversity, faCheck, faCheckDouble, faTimes, faArchive, faReceipt, faUndo, faPiggyBank, faUserCheck)

interface BankLine {
	id: number
	booked_at: string
	bank: string
	amount: number
	currency_code: string
	org_amount: number | null
	org_currency_code: string
	counterparty_name: string | null
	counterparty_account: string | null
	reference: string | null
	reason: string | null
	owed: number | null
	order_reference: string | null
	order_slug: string | null
	shop_slug: string | null
	is_dismissed: boolean
	can_bring_back: boolean
	organisation_slug: string
	organisation_code: string
	actionable: boolean
	suggested_orders: SuggestedOrder[]
	staff_hint: { customer_reference: string; customer_name: string | null; shop_code: string; note: string | null; by: string; at: string } | null
	payout: {
		provider: "checkout" | "pastpay" | "cod"
		payout_id: string | null
		orders: string[]
		reconciled: boolean
		payments: number
		refunds: number
		processor_fees: number
		bank_fee: number
		payments_missing: number
		carrier: string | null
		differences: { order: string; list: number; aiku: number; not_recorded?: boolean }[]
		not_recorded: string[]
	} | null
}

interface SuggestedOrder {
	id: number
	reference: string
	customer: string | null
	owed: number
	total: number
	date: string | null
	kind: "pay" | "already_paid"
}

interface OrderCandidate {
	id: number
	reference: string
	customer: string | null
	shop: string | null
	owed: number
	paid: number
	total: number
	currency_code: string
	date: string | null
	amount_matches: boolean
}

interface CustomerCandidate {
	id: number
	reference: string | null
	name: string | null
	shop: string
	balance: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	canEdit: boolean
	organisationId: number | null
	shop?: { id: number; slug: string; code: string; currency: string } | null
	organisation: string | null
	organisations: { value: string; label: string }[]
	section: "customers" | "marketplaces" | "payment_accounts" | "other" | "needs_person"
	summary: { count: number; total: number | null }
	provider: string | null
	providers: { value: string; label: string; count: number }[]
	currencyTotals: Record<string, { count: number; total: number }>
	orgCurrency: string
	min: number | null
	max: number | null
	period: "90d" | "1y" | "all" | null
	currency: string | null
	currencies: string[]
	from: string | null
	to: string | null
	data: { data: BankLine[] }
}>()

const locale = useLocaleStore()

const sentenceCase = (text: string) => text.charAt(0).toUpperCase() + text.slice(1)

const orderRoute = (line: BankLine) =>
	line.order_slug && line.shop_slug
		? route("grp.org.shops.show.ordering.orders.show", [line.organisation_slug, line.shop_slug, line.order_slug])
		: null

const lineRoute = (name: string, lineId: number) =>
	props.shop
		? route(`grp.models.org.shop.bank_transaction.${name}`, [props.organisationId, props.shop.id, lineId])
		: route(`grp.models.org.bank_transaction.${name}`, [props.organisationId, lineId])

const candidatesRoute = (name: string, lineId: number) =>
	props.shop
		? route(`grp.json.shop.bank_transaction.${name}`, [props.organisationId, props.shop.id, lineId])
		: route(`grp.json.bank_transaction.${name}`, [props.organisationId, lineId])

const orderOfAnotherShop = (line: BankLine) => !!props.shop && !!line.order_reference && line.shop_slug !== props.shop.slug

const providerOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.providers])

const ALREADY_PAID_NOTE = "Already recorded on the order by hand"

const rowError = ref<{ id: number; message: string } | null>(null)
const markAlreadyPaid = (line: BankLine) => {
	rowError.value = null
	router.post(route("grp.models.org.bank_transaction.dismiss", [props.organisationId, line.id]), { note: ALREADY_PAID_NOTE, already_paid: true }, {
		preserveScroll: true,
		onError: (errors: Record<string, string>) => {
			open(line, "already_paid")
			error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")
		},
	})
}

const confirmSuggestion = (line: BankLine, order: SuggestedOrder) => {
	rowError.value = null
	const [url, payload] =
		order.kind === "pay"
			? [lineRoute("pay_order", line.id), { order_id: order.id, order_reference: order.reference, absorb_bank_fee: false, from_suggestion: true }]
			: [route("grp.models.org.bank_transaction.dismiss", [props.organisationId, line.id]), { note: ALREADY_PAID_NOTE, already_paid: true, order_id: order.id, from_suggestion: true }]
	router.post(url, payload, {
		preserveScroll: true,
		onError: (errors: Record<string, string>) => (rowError.value = { id: line.id, message: Object.values(errors ?? {})[0] ?? ctrans("Something went wrong") }),
	})
}

const restore = (line: BankLine) => {
	router.post(route("grp.models.org.bank_transaction.restore", [props.organisationId, line.id]), {}, {
		preserveScroll: true,
		onError: (errors: Record<string, string>) => (rowError.value = { id: line.id, message: Object.values(errors ?? {})[0] ?? ctrans("Something went wrong") }),
	})
}

const selected = ref<BankLine | null>(null)
const action = ref<"pay" | "already_paid" | "credit" | "dismiss" | "point" | null>(null)
const customerPick = ref<CustomerCandidate | string>("")
const customerSuggestions = ref<CustomerCandidate[]>([])
const payerCustomers = ref<CustomerCandidate[] | null>(null)
const orderPick = ref<OrderCandidate | string>("")
const suggestions = ref<OrderCandidate[]>([])
const payerOrders = ref<OrderCandidate[] | null>(null)
const absorbBankFee = ref(false)
const note = ref("")
const error = ref<string | null>(null)
const isSaving = ref(false)

const visible = computed({
	get: () => action.value !== null,
	set: (value: boolean) => {
		if (!value) {
			action.value = null
		}
	},
})

const header = computed(() =>
	action.value === "pay"
		? ctrans("Record this bank payment on an order")
		: action.value === "already_paid"
			? ctrans("Which order already has this payment?")
			: action.value === "credit"
			? ctrans("Add this bank payment to a customer credit")
			: action.value === "point"
			? ctrans("Which of our customers sent this payment?")
			: ctrans("This bank payment does not pay an order")
)

const pickedReference = computed(() => (typeof orderPick.value === "string" ? orderPick.value.trim() : orderPick.value?.reference ?? ""))

const pickedCustomer = computed(() => (typeof customerPick.value === "string" ? null : customerPick.value))

const canSubmit = computed(() =>
	action.value === "pay" ? pickedReference.value !== "" : action.value === "already_paid" ? typeof orderPick.value !== "string" : action.value === "credit" || action.value === "point" ? pickedCustomer.value !== null : note.value.trim() !== ""
)

const findCustomers = async (search: string): Promise<CustomerCandidate[]> => {
	if (!selected.value) {
		return []
	}
	const response = await axios.get(candidatesRoute("customer_candidates", selected.value.id), { params: { search, any_currency: action.value === "point" ? 1 : 0 } })
	return response.data
}

const searchCustomers = async (event: { query: string }) => {
	customerSuggestions.value = await findCustomers(event.query)
}

const loadPayerCustomers = async (line: BankLine) => {
	payerCustomers.value = null
	const customers = line.counterparty_name ? await findCustomers("") : []
	if (selected.value?.id === line.id) {
		payerCustomers.value = customers
	}
}

const orderAmountLabel = (order: OrderCandidate) =>
	action.value === "already_paid"
		? ctrans("paid :amount", { amount: locale.currencyFormat(order.currency_code, order.paid) })
		: ctrans("owes :amount", { amount: locale.currencyFormat(order.currency_code, order.owed) })

const findOrders = async (search: string, payer = false): Promise<OrderCandidate[]> => {
	if (!selected.value) {
		return []
	}
	const response = await axios.get(candidatesRoute("order_candidates", selected.value.id), {
		params: { search, payer: payer ? 1 : 0, paid: action.value === "already_paid" ? 1 : 0 },
	})
	return response.data
}

const searchOrders = async (event: { query: string }) => {
	suggestions.value = await findOrders(event.query)
}

const loadPayerOrders = async (line: BankLine) => {
	payerOrders.value = null
	const kind = action.value
	const orders = line.counterparty_name ? await findOrders("", true) : []
	if (selected.value?.id === line.id && action.value === kind) {
		payerOrders.value = orders
	}
}

const open = (line: BankLine, kind: "pay" | "already_paid" | "credit" | "dismiss" | "point") => {
	selected.value = line
	action.value = kind
	orderPick.value = kind === "already_paid" ? "" : line.order_reference ?? ""
	customerPick.value = ""
	absorbBankFee.value = false
	note.value = ""
	error.value = null
	if (kind === "already_paid" || (kind === "pay" && !line.order_reference)) {
		loadPayerOrders(line)
	}
	if (kind === "credit" || kind === "point") {
		loadPayerCustomers(line)
	}
}

const submit = () => {
	if (!selected.value || !canSubmit.value) {
		return
	}
	const organisationId = props.organisationId
	const [url, payload] =
		action.value === "pay"
			? [
					lineRoute("pay_order", selected.value.id),
					{
						order_id: typeof orderPick.value === "string" ? null : orderPick.value.id,
						order_reference: pickedReference.value,
						absorb_bank_fee: !props.shop && absorbBankFee.value,
					},
				]
			: action.value === "already_paid"
				? [route("grp.models.org.bank_transaction.dismiss", [organisationId, selected.value.id]), { note: ALREADY_PAID_NOTE, already_paid: true, order_id: typeof orderPick.value === "string" ? null : orderPick.value.id }]
				: action.value === "credit"
				? [lineRoute("customer_credit", selected.value.id), { customer_id: pickedCustomer.value?.id }]
				: action.value === "point"
				? [lineRoute("point_customer", selected.value.id), { customer_id: pickedCustomer.value?.id, note: note.value.trim() || null }]
				: [route("grp.models.org.bank_transaction.dismiss", [organisationId, selected.value.id]), { note: note.value.trim() }]

	router.post(url, payload, {
		preserveScroll: true,
		onStart: () => (isSaving.value = true),
		onFinish: () => (isSaving.value = false),
		onSuccess: () => (action.value = null),
		onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<BankLinesFilters
		:organisation-id="organisationId"
		:organisation="organisation"
		:organisations="organisations"
		:summary="summary"
		:currency-totals="currencyTotals"
		:org-currency="orgCurrency"
		:min="min"
		:max="max"
		:period="period"
		:currency="currency"
		:currencies="currencies"
		:from="from"
		:to="to"
		:extra-query="{ provider }"
	>
		<template #default="{ reload }">
			<SegmentedToggle v-if="providers.length > 1" :model-value="provider ?? 'all'" :options="providerOptions" :aria-label="ctrans('Payment provider')" @update:model-value="(value: string) => reload({ provider: value === 'all' ? null : value })" />
		</template>
	</BankLinesFilters>

	<Table :resource="data" class="mt-5">
		<template #cell(booked_at)="{ item }">
			<span class="whitespace-nowrap">{{ useFormatTime(item.booked_at, { formatTime: "d MMM yyyy" }) }}</span>
		</template>

		<template #cell(organisation_code)="{ item }">
			<Link :href="route(`grp.org.accounting.bank_incoming.${section}`, [item.organisation_slug])" class="primaryLink whitespace-nowrap" v-tooltip="ctrans('Open in the organisation, where payments are placed')">
				{{ item.organisation_code }}
			</Link>
		</template>

		<template #cell(bank)="{ item }">
			<span class="whitespace-nowrap">{{ item.bank }}</span>
		</template>

		<template #cell(counterparty_name)="{ item }">
			<span class="text-xs">{{ item.counterparty_name ?? "—" }}</span>
			<span v-if="item.counterparty_account" class="block whitespace-nowrap font-mono text-xs text-gray-500">{{ item.counterparty_account }}</span>
		</template>

		<template #cell(amount)="{ item }">
			<span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.amount) }}</span>
			<div v-if="item.org_amount !== null" class="whitespace-nowrap text-[11px] tabular-nums text-gray-400">{{ locale.currencyFormat(item.org_currency_code, item.org_amount) }}</div>
		</template>

		<template #cell(reference)="{ item }">
			<span class="break-all text-xs text-gray-600">{{ item.reference ?? "—" }}</span>
		</template>

		<template #cell(order_reference)="{ item }">
			<Link v-if="orderRoute(item)" :href="orderRoute(item)" class="primaryLink whitespace-nowrap">
				{{ item.order_reference }}
			</Link>
			<span v-else class="text-gray-400">—</span>
		</template>

		<template #cell(actions)="{ item }">
			<div v-if="item.payout" class="mb-1 text-right text-xs text-gray-500">
				<template v-if="item.payout.provider === 'pastpay' && item.payout.orders.length > 1">
					{{ ctrans("PastPay payout of :orders, PastPay kept :fees", {
						orders: item.payout.orders.join(", "),
						fees: locale.currencyFormat(item.currency_code, item.payout.processor_fees),
					}) }}
				</template>
				<template v-else-if="item.payout.provider === 'pastpay'">
					{{ ctrans("PastPay payout of this order, PastPay kept :fees", {
						fees: locale.currencyFormat(item.currency_code, item.payout.processor_fees),
					}) }}
				</template>
				<template v-else-if="item.payout.provider === 'cod' && item.payout.payments">
					{{ ctrans(":carrier cash on delivery payout of :payments parcels", { carrier: item.payout.carrier, payments: item.payout.payments }) }}
					<span v-if="item.payout.payments_missing" class="text-amber-600">
						· {{ ctrans(":count parcels not found in Aiku", { count: item.payout.payments_missing }) }}
					</span>
					<span v-if="item.payout.not_recorded.length" class="text-amber-600">
						· {{ ctrans("No cash on delivery payment yet on :orders", { orders: item.payout.not_recorded.join(", ") }) }}
					</span>
					<div v-for="difference in item.payout.differences" :key="difference.order" class="text-amber-600">
						{{ ctrans(":order: the list says :list, Aiku has :aiku", {
							order: difference.order,
							list: locale.currencyFormat(item.currency_code, difference.list),
							aiku: locale.currencyFormat(item.currency_code, difference.aiku),
						}) }}
					</div>
				</template>
				<template v-else-if="item.payout.provider === 'cod'">
					{{ ctrans(":carrier cash on delivery payout, its parcel list is not read yet", { carrier: item.payout.carrier }) }}
				</template>
				<template v-else-if="item.payout.provider === 'paypal'">
					{{ ctrans("PayPal payment through Checkout, PayPal kept :fees", {
						fees: locale.currencyFormat(item.currency_code, item.payout.processor_fees),
					}) }}
				</template>
				<template v-else-if="item.payout.reconciled">
					{{ ctrans("Checkout payout of :payments payments and :refunds refunds, Checkout kept :fees, the bank :bank_fee", {
						payments: item.payout.payments,
						refunds: item.payout.refunds,
						fees: locale.currencyFormat(item.currency_code, item.payout.processor_fees),
						bank_fee: locale.currencyFormat(item.currency_code, item.payout.bank_fee),
					}) }}
					<span v-if="item.payout.payments_missing" class="text-amber-600">
						· {{ ctrans(":count payments are not in Aiku", { count: item.payout.payments_missing }) }}
					</span>
				</template>
				<template v-else>{{ ctrans("Checkout payout, its report is not read yet") }}</template>
			</div>
			<div v-else-if="item.reason" class="mb-1 text-right text-xs text-gray-500">{{ sentenceCase(ctrans(item.reason)) }}</div>
			<div v-if="item.staff_hint" class="mb-1 text-right text-xs text-amber-700">
				<FontAwesomeIcon icon="fal fa-user-check" fixed-width aria-hidden="true" />
				{{ ctrans(":by says it is", { by: item.staff_hint.by }) }}
				{{ item.staff_hint.customer_name }}
				<span class="whitespace-nowrap">{{ item.staff_hint.customer_reference }} ({{ item.staff_hint.shop_code }})</span><template v-if="item.staff_hint.note">: {{ item.staff_hint.note }}</template>
			</div>
			<div v-if="canEdit && !shop && item.can_bring_back" class="flex flex-col items-end gap-1 whitespace-nowrap">
				<Button type="tertiary" size="xs" :label="ctrans('Bring back')" @click="restore(item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-undo" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<span v-if="rowError?.id === item.id" class="text-xs text-red-600">{{ rowError.message }}</span>
			</div>
			<div v-else-if="canEdit && item.actionable" class="flex flex-col items-end gap-1">
				<div v-for="order in item.suggested_orders" :key="order.id" class="flex items-center justify-end gap-2 text-xs text-gray-600">
					<span>
						{{ order.kind === "pay"
							? ctrans(":customer ordered :order on :date, it owes this amount", { customer: order.customer ?? "—", order: order.reference, date: order.date ? useFormatTime(order.date, { formatTime: "d MMM" }) : "—" })
							: ctrans(":customer ordered :order on :date, a bank payment of this amount is already on it", { customer: order.customer ?? "—", order: order.reference, date: order.date ? useFormatTime(order.date, { formatTime: "d MMM" }) : "—" }) }}
					</span>
					<Button
						type="tertiary"
						size="xs"
						class="whitespace-nowrap"
						:label="order.kind === 'pay' ? ctrans('Pay :order', { order: order.reference }) : ctrans('Already paid on :order', { order: order.reference })"
						@click="confirmSuggestion(item, order)"
					>
						<template #icon>
							<FontAwesomeIcon :icon="order.kind === 'pay' ? 'fal fa-check' : 'fal fa-check-double'" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
				<div v-if="shop" class="flex justify-end gap-2 whitespace-nowrap">
					<span v-if="orderOfAnotherShop(item)" class="text-xs text-gray-500">{{ ctrans("Names an order of another shop") }}</span>
					<Button v-else-if="item.currency_code === shop.currency" type="tertiary" size="xs" :label="ctrans('Pay order')" @click="open(item, 'pay')">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button
						v-if="item.currency_code === shop.currency && !item.order_reference"
						type="tertiary"
						size="xs"
						:label="ctrans('Customer credit')"
						v-tooltip="ctrans('Pays no order: add the money to the customer credit')"
						@click="open(item, 'credit')"
					>
						<template #icon>
							<FontAwesomeIcon icon="fal fa-piggy-bank" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button
						type="tertiary"
						size="xs"
						:label="ctrans('Our customer')"
						v-tooltip="ctrans('Say which of our customers sent it, so accounting can place it')"
						@click="open(item, 'point')"
					>
						<template #icon>
							<FontAwesomeIcon icon="fal fa-user-check" class="text-gray-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
				<div v-else class="flex justify-end gap-2 whitespace-nowrap">
					<Button type="tertiary" size="xs" :label="ctrans('Pay order')" @click="open(item, 'pay')">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button
						type="tertiary"
						size="xs"
						:label="ctrans('Already paid')"
						v-tooltip="ctrans('Someone already recorded this payment on the order by hand')"
						@click="item.order_reference ? markAlreadyPaid(item) : open(item, 'already_paid')"
					>
						<template #icon>
							<FontAwesomeIcon icon="fal fa-check-double" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button
						type="tertiary"
						size="xs"
						:label="ctrans('Customer credit')"
						v-tooltip="ctrans('Pays no order: add the money to the customer credit')"
						@click="open(item, 'credit')"
					>
						<template #icon>
							<FontAwesomeIcon icon="fal fa-piggy-bank" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button type="tertiary" size="xs" :label="ctrans('Not for an order')" @click="open(item, 'dismiss')">
						<template #icon>
							<FontAwesomeIcon icon="fal fa-archive" class="text-amber-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
				<span v-if="rowError?.id === item.id" class="text-xs text-red-600">{{ rowError.message }}</span>
			</div>
		</template>
	</Table>

	<Dialog
		v-model:visible="visible"
		modal
		:header="header"
		:style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div v-if="selected" class="space-y-4 text-sm">
			<div class="rounded border border-gray-200 px-3 py-2">
				<div class="font-semibold tabular-nums">{{ locale.currencyFormat(selected.currency_code, selected.amount) }}</div>
				<div class="text-gray-600">{{ selected.counterparty_name ?? "—" }} · {{ selected.bank }}</div>
				<div v-if="selected.reference" class="text-gray-500">{{ selected.reference }}</div>
			</div>

			<template v-if="action === 'pay' || action === 'already_paid'">
				<label class="block">
					<span class="mb-1 block font-medium">{{ ctrans("Order") }} <span class="text-red-500">*</span></span>
					<AutoComplete
						v-model="orderPick"
						:suggestions="suggestions"
						optionLabel="reference"
						:delay="300"
						fluid
						:placeholder="ctrans('Order number or customer name')"
						@complete="searchOrders"
					>
						<template #option="{ option }">
							<div class="flex w-full min-w-0 items-baseline gap-2">
								<span class="whitespace-nowrap font-medium">{{ option.reference }}</span>
								<span class="truncate text-gray-600">{{ option.customer ?? "—" }}</span>
								<span class="ml-auto whitespace-nowrap tabular-nums text-gray-500">{{ orderAmountLabel(option) }}</span>
							</div>
						</template>
					</AutoComplete>
				</label>
				<div v-if="(action === 'already_paid' || !selected.order_reference) && selected.counterparty_name" class="space-y-1">
					<span class="block text-xs font-medium text-gray-600">{{ action === "already_paid" ? ctrans("Paid orders of customers named like :payer", { payer: selected.counterparty_name }) : ctrans("Unpaid orders of customers named like :payer", { payer: selected.counterparty_name }) }}</span>
					<div v-if="payerOrders === null" class="skeleton h-8 rounded" />
					<p v-else-if="payerOrders.length === 0" class="text-xs text-gray-500">{{ ctrans("None found: search by order number or customer name above") }}</p>
					<button
						v-for="order in payerOrders ?? []"
						v-else
						:key="order.id"
						type="button"
						class="flex w-full min-w-0 items-baseline gap-2 rounded border px-2 py-1 text-left hover:bg-gray-50"
						:class="typeof orderPick !== 'string' && orderPick?.id === order.id ? 'border-[var(--app-accent)] bg-[var(--app-accent-soft)]' : 'border-gray-200'"
						@click="orderPick = order"
					>
						<span class="whitespace-nowrap font-medium">{{ order.reference }}</span>
						<span class="truncate text-gray-600">{{ order.customer ?? "—" }}</span>
						<span v-if="order.amount_matches" class="whitespace-nowrap text-xs text-green-600">{{ ctrans("same amount") }}</span>
						<span class="ml-auto whitespace-nowrap tabular-nums" :class="order.amount_matches ? 'font-medium text-gray-900' : 'text-gray-500'">{{ orderAmountLabel(order) }}</span>
					</button>
				</div>
				<label v-if="action === 'pay' && !shop" class="flex items-start gap-2">
					<Checkbox v-model="absorbBankFee" binary input-id="absorb-bank-fee" />
					<span>
						{{ ctrans("If it arrived a little short, absorb the bank fee so the order is paid") }}
						<span class="block text-xs text-gray-500">{{ ctrans("Only up to the bank fee limit; anything more stays owed on the order") }}</span>
					</span>
				</label>
			</template>

			<template v-else-if="action === 'credit' || action === 'point'">
				<p v-if="action === 'point'" class="text-gray-600">{{ ctrans("Nothing is paid: accounting sees your answer on this payment and places it.") }}</p>
				<label class="block">
					<span class="mb-1 block font-medium">{{ ctrans("Customer") }} <span class="text-red-500">*</span></span>
					<AutoComplete
						v-model="customerPick"
						:suggestions="customerSuggestions"
						optionLabel="name"
						:delay="300"
						fluid
						:placeholder="ctrans('Customer name or reference')"
						@complete="searchCustomers"
					>
						<template #option="{ option }">
							<div class="flex w-full min-w-0 items-baseline gap-2">
								<span class="truncate font-medium">{{ option.name ?? "—" }}</span>
								<span class="whitespace-nowrap text-gray-500">{{ option.reference }} · {{ option.shop }}</span>
								<span v-if="action === 'credit'" class="ml-auto whitespace-nowrap tabular-nums text-gray-500">{{ ctrans("credit :amount", { amount: locale.currencyFormat(selected.currency_code, option.balance) }) }}</span>
							</div>
						</template>
					</AutoComplete>
				</label>
				<div v-if="selected.counterparty_name" class="space-y-1">
					<span class="block text-xs font-medium text-gray-600">{{ ctrans("Customers named like :payer", { payer: selected.counterparty_name }) }}</span>
					<div v-if="payerCustomers === null" class="skeleton h-8 rounded" />
					<p v-else-if="payerCustomers.length === 0" class="text-xs text-gray-500">{{ ctrans("None found: search by customer name or reference above") }}</p>
					<button
						v-for="customer in payerCustomers ?? []"
						v-else
						:key="customer.id"
						type="button"
						class="flex w-full min-w-0 items-baseline gap-2 rounded border px-2 py-1 text-left hover:bg-gray-50"
						:class="pickedCustomer?.id === customer.id ? 'border-[var(--app-accent)] bg-[var(--app-accent-soft)]' : 'border-gray-200'"
						@click="customerPick = customer"
					>
						<span class="truncate font-medium">{{ customer.name ?? "—" }}</span>
						<span class="whitespace-nowrap text-gray-500">{{ customer.reference }} · {{ customer.shop }}</span>
						<span v-if="action === 'credit'" class="ml-auto whitespace-nowrap tabular-nums text-gray-500">{{ ctrans("credit :amount", { amount: locale.currencyFormat(selected.currency_code, customer.balance) }) }}</span>
					</button>
				</div>
				<label v-if="action === 'point'" class="block">
					<span class="mb-1 block font-medium">{{ ctrans("Note") }}</span>
					<Textarea v-model="note" rows="2" class="w-full" :placeholder="ctrans('e.g. paid order UK12345 in euros by mistake')" />
				</label>
			</template>

			<label v-else class="block">
				<span class="mb-2 block text-gray-600">{{ ctrans("For money that pays no order, like a marketplace payout, a refund back to us or a transfer between our own accounts. It moves to Other, where you can bring it back.") }}</span>
				<span class="mb-1 block font-medium">{{ ctrans("Why") }} <span class="text-red-500">*</span></span>
				<Textarea v-model="note" rows="3" class="w-full" :placeholder="ctrans('e.g. Amazon payout')" />
			</label>

			<p v-if="error" class="text-xs text-red-600">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					v-if="action !== 'dismiss'"
					type="primary"
					full
					:label="action === 'pay' ? ctrans('Record payment') : action === 'already_paid' ? ctrans('Already paid') : action === 'point' ? ctrans('This is our customer') : ctrans('Add to credit')"
					:loading="isSaving"
					:disabled="!canSubmit"
					@click="submit"
				>
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					v-else
					type="tertiary"
					full
					:label="ctrans('Not for an order')"
					:loading="isSaving"
					:disabled="!canSubmit"
					@click="submit"
				>
					<template #icon>
						<FontAwesomeIcon icon="fal fa-archive" class="text-amber-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
