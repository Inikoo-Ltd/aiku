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
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUniversity, faCheck, faCheckDouble, faTimes, faArchive, faReceipt, faUndo } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faUniversity, faCheck, faCheckDouble, faTimes, faArchive, faReceipt, faUndo)

interface BankLine {
	id: number
	booked_at: string
	bank: string
	amount: number
	currency_code: string
	counterparty_name: string | null
	reference: string | null
	reason: string | null
	owed: number | null
	order_reference: string | null
	order_slug: string | null
	shop_slug: string | null
	is_dismissed: boolean
}

interface OrderCandidate {
	id: number
	reference: string
	customer: string | null
	shop: string | null
	owed: number
	total: number
	currency_code: string
	date: string | null
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	canEdit: boolean
	organisationId: number
	scope: "review" | "unmatched" | "dismissed"
	period: "90d" | "1y" | "all"
	data: { data: BankLine[] }
}>()

const locale = useLocaleStore()

const sentenceCase = (text: string) => text.charAt(0).toUpperCase() + text.slice(1)

const orderRoute = (line: BankLine) =>
	line.order_slug && line.shop_slug
		? route("grp.org.shops.show.ordering.orders.show", [route().params["organisation"], line.shop_slug, line.order_slug])
		: null

const scopeOptions = [
	{ label: ctrans("To review"), value: "review" },
	{ label: ctrans("No order number"), value: "unmatched" },
	{ label: ctrans("Not for an order"), value: "dismissed" },
]
const periodOptions = [
	{ label: ctrans("Last 90 days"), value: "90d" },
	{ label: ctrans("Last year"), value: "1y" },
	{ label: ctrans("All"), value: "all" },
]
const reload = (query: Record<string, string>) =>
	router.get(
		route("grp.org.accounting.bank_review.index", [route().params["organisation"]]),
		{ scope: props.scope, period: props.period, ...query },
		{ preserveState: false }
	)
const scope = computed({
	get: () => props.scope,
	set: (value: string) => reload({ scope: value }),
})
const period = computed({
	get: () => props.period,
	set: (value: string) => reload({ period: value }),
})

const ALREADY_PAID_NOTE = "Already recorded on the order by hand"

const rowError = ref<{ id: number; message: string } | null>(null)
const markAlreadyPaid = (line: BankLine) => {
	rowError.value = null
	router.post(route("grp.models.org.bank_transaction.dismiss", [props.organisationId, line.id]), { note: ALREADY_PAID_NOTE }, {
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
const action = ref<"pay" | "dismiss" | null>(null)
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
	action.value === "pay" ? ctrans("Record this bank payment on an order") : ctrans("This bank payment does not pay an order")
)

const pickedReference = computed(() => (typeof orderPick.value === "string" ? orderPick.value.trim() : orderPick.value?.reference ?? ""))

const canSubmit = computed(() => (action.value === "pay" ? pickedReference.value !== "" : note.value.trim() !== ""))

const findOrders = async (search: string): Promise<OrderCandidate[]> => {
	if (!selected.value) {
		return []
	}
	const response = await axios.get(route("grp.json.bank_transaction.order_candidates", [props.organisationId, selected.value.id]), { params: { search } })
	return response.data
}

const searchOrders = async (event: { query: string }) => {
	suggestions.value = await findOrders(event.query)
}

const loadPayerOrders = async (line: BankLine) => {
	payerOrders.value = null
	const orders = line.counterparty_name ? await findOrders(line.counterparty_name) : []
	if (selected.value?.id === line.id) {
		payerOrders.value = orders
	}
}

const open = (line: BankLine, kind: "pay" | "dismiss") => {
	selected.value = line
	action.value = kind
	orderPick.value = line.order_reference ?? ""
	absorbBankFee.value = false
	note.value = ""
	error.value = null
	if (kind === "pay" && !line.order_reference) {
		loadPayerOrders(line)
	}
}

const submit = () => {
	if (!selected.value || !canSubmit.value) {
		return
	}
	const organisationId = props.organisationId
	const url =
		action.value === "pay"
			? route("grp.models.org.bank_transaction.pay_order", [organisationId, selected.value.id])
			: route("grp.models.org.bank_transaction.dismiss", [organisationId, selected.value.id])
	const payload =
		action.value === "pay"
			? {
					order_id: typeof orderPick.value === "string" ? null : orderPick.value.id,
					order_reference: pickedReference.value,
					absorb_bank_fee: absorbBankFee.value,
				}
			: { note: note.value.trim() }

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

	<div class="flex flex-wrap items-center gap-3 px-4 pt-3">
		<SegmentedToggle v-model="scope" :options="scopeOptions" :aria-label="ctrans('Which bank payments')" />
		<SegmentedToggle v-model="period" :options="periodOptions" soft :aria-label="ctrans('Received in')" />
	</div>

	<Table :resource="data" class="mt-5">
		<template #cell(booked_at)="{ item }">
			<span class="whitespace-nowrap">{{ useFormatTime(item.booked_at) }}</span>
		</template>

		<template #cell(amount)="{ item }">
			<span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.amount) }}</span>
		</template>

		<template #cell(reference)="{ item }">
			<span class="text-gray-600">{{ item.reference ?? "—" }}</span>
		</template>

		<template #cell(reason)="{ item }">
			<span class="text-sm text-gray-600">{{ item.reason ? sentenceCase(ctrans(item.reason)) : "—" }}</span>
		</template>

		<template #cell(order_reference)="{ item }">
			<Link v-if="orderRoute(item)" :href="orderRoute(item)" class="primaryLink whitespace-nowrap">
				{{ item.order_reference }}
			</Link>
			<span v-else class="text-gray-400">—</span>
		</template>

		<template #cell(actions)="{ item }">
			<div v-if="canEdit && item.is_dismissed" class="flex flex-col items-end gap-1 whitespace-nowrap">
				<Button type="tertiary" size="xs" :label="ctrans('Bring back')" @click="restore(item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-undo" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<span v-if="rowError?.id === item.id" class="text-xs text-red-600">{{ rowError.message }}</span>
			</div>
			<div v-else-if="canEdit" class="flex flex-col items-end gap-1">
				<div class="flex justify-end gap-2 whitespace-nowrap">
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
						@click="markAlreadyPaid(item)"
					>
						<template #icon>
							<FontAwesomeIcon icon="fal fa-check-double" class="text-green-600" fixed-width aria-hidden="true" />
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

			<template v-if="action === 'pay'">
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
								<span class="ml-auto whitespace-nowrap tabular-nums text-gray-500">{{ ctrans("owes :amount", { amount: locale.currencyFormat(option.currency_code, option.owed) }) }}</span>
							</div>
						</template>
					</AutoComplete>
				</label>
				<div v-if="!selected.order_reference && selected.counterparty_name" class="space-y-1">
					<span class="block text-xs font-medium text-gray-600">{{ ctrans("Unpaid orders of customers named like :payer", { payer: selected.counterparty_name }) }}</span>
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
						<span class="ml-auto whitespace-nowrap tabular-nums text-gray-500">{{ ctrans("owes :amount", { amount: locale.currencyFormat(order.currency_code, order.owed) }) }}</span>
					</button>
				</div>
				<label class="flex items-start gap-2">
					<Checkbox v-model="absorbBankFee" binary input-id="absorb-bank-fee" />
					<span>
						{{ ctrans("If it arrived a little short, absorb the bank fee so the order is paid") }}
						<span class="block text-xs text-gray-500">{{ ctrans("Only up to the bank fee limit; anything more stays owed on the order") }}</span>
					</span>
				</label>
			</template>

			<label v-else class="block">
				<span class="mb-2 block text-gray-600">{{ ctrans("For money that pays no order, like a marketplace payout, a refund back to us or a transfer between our own accounts. It leaves the review list; you can bring it back from the Not for an order tab.") }}</span>
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
					v-if="action === 'pay'"
					type="primary"
					full
					:label="ctrans('Record payment')"
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
