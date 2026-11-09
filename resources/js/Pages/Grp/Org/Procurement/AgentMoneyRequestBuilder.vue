<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { useIntersectionObserver } from "@vueuse/core"
import Checkbox from "primevue/checkbox"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faUser, faClipboardList, faTimes, faWallet, faInfoCircle, faShippingFast } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faPaperPlane, faUser, faClipboardList, faTimes, faWallet, faInfoCircle, faShippingFast)

interface DueOrder {
	purchase_order_id: number
	reference: string
	submitted_at: string | null
	placed_by: string | null
	route: routeType
	supplier_id: number | null
	supplier_code: string | null
	supplier_name: string | null
	supplier_company: string | null
	supplier_nickname: string | null
	supplier_route: routeType | null
	total: number
	requested: number
	advance_percentage: number
	advance_due: number
	balance_due: number
}

interface DueShipment {
	stock_delivery_id: number
	reference: string
	route: routeType
	charges: { type: string; label: string; amount: number }[]
	total: number
	requested: number
	due: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	clients: { slug: string; label: string }[]
	client: { id: number; slug: string; code: string }
	purchase_order: string | null
	due: {
		currency_code: string
		wallet: { received: number; paid: number; balance: number }
		negative_balance: number
		shipments: DueShipment[]
		orders: DueOrder[]
	}
	store_route: routeType
	advance_route_name: string
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.due.currency_code, amount)
const amountInputOptions = computed(() => locale.currencyInputOptions(props.due.currency_code))
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const ageInDays = (date: string | null) => (date ? Math.max(0, Math.round((Date.parse(new Date().toISOString().slice(0, 10)) - Date.parse(date)) / 86400000)) : null)

interface Line {
	key: string
	kind: "advance" | "balance" | "shipment" | "wallet"
	purchase_order_id: number | null
	stock_delivery_id: number | null
	order: DueOrder | null
	shipment: DueShipment | null
	due: number
}

const orderLine = (kind: "advance" | "balance", order: DueOrder, due: number): Line => ({
	key: `${kind}:${order.purchase_order_id}`,
	kind,
	purchase_order_id: order.purchase_order_id,
	stock_delivery_id: null,
	order,
	shipment: null,
	due,
})

const lines = computed<Line[]>(() => [
	...(props.due.negative_balance > 0
		? [{ key: "wallet", kind: "wallet" as const, purchase_order_id: null, stock_delivery_id: null, order: null, shipment: null, due: props.due.negative_balance }]
		: []),
	...(props.due.shipments ?? []).map((shipment) => ({
		key: `shipment:${shipment.stock_delivery_id}`,
		kind: "shipment" as const,
		purchase_order_id: null,
		stock_delivery_id: shipment.stock_delivery_id,
		order: null,
		shipment,
		due: shipment.due,
	})),
	...props.due.orders.flatMap((order) => [
		...(order.advance_due > 0 ? [orderLine("advance", order, order.advance_due)] : []),
		...(order.balance_due > 0 ? [orderLine("balance", order, order.balance_due)] : []),
	]),
])

const ticked = reactive<Record<string, boolean>>({})
const amounts = reactive<Record<string, number | null>>({})
const offeredDue: Record<string, number> = {}

watch(
	lines,
	(current) => {
		current.forEach((line) => {
			if (!(line.key in ticked)) ticked[line.key] = line.kind !== "balance"
			const amount = amounts[line.key]
			const untouched = amount === undefined || amount === null || amount === offeredDue[line.key]
			amounts[line.key] = untouched ? line.due : Math.min(amount, line.due)
			offeredDue[line.key] = line.due
		})
	},
	{ immediate: true }
)

const percentages = reactive<Record<number, number | null>>({})
watch(
	() => props.due.orders,
	(orders) => orders.forEach((order) => (percentages[order.purchase_order_id] = order.advance_percentage)),
	{ immediate: true }
)

const orderOf = (purchaseOrderId: number | null) => props.due.orders.find((order) => order.purchase_order_id === purchaseOrderId)

type LineFilter = "all" | "purchase_orders" | "shipments"

const lineFilter = ref<LineFilter>("all")
const isPurchaseOrderLine = (line: Line) => line.kind === "advance" || line.kind === "balance"
const purchaseOrderLines = computed(() => lines.value.filter(isPurchaseOrderLine))
const shipmentLines = computed(() => lines.value.filter((line) => line.kind === "shipment"))

const lineFilterOptions = computed<{ label: string; value: LineFilter; count: number; disabled: boolean }[]>(() => [
	{ label: ctrans("All"), value: "all", count: lines.value.length, disabled: false },
	{ label: ctrans("PO"), value: "purchase_orders", count: purchaseOrderLines.value.length, disabled: !purchaseOrderLines.value.length },
	{ label: ctrans("Shipment"), value: "shipments", count: shipmentLines.value.length, disabled: !shipmentLines.value.length },
])

const visibleLines = computed(() => {
	if (lineFilter.value === "purchase_orders") return purchaseOrderLines.value
	if (lineFilter.value === "shipments") return shipmentLines.value
	return lines.value
})

watch(visibleLines, (current) => {
	if (!current.length && lineFilter.value !== "all") lineFilter.value = "all"
})

const tickedCount = computed(() => visibleLines.value.filter((line) => ticked[line.key]).length)
const allTicked = computed(() => visibleLines.value.length > 0 && tickedCount.value === visibleLines.value.length)
const someTicked = computed(() => tickedCount.value > 0 && !allTicked.value)
const tickAll = (value: boolean) => visibleLines.value.forEach((line) => (ticked[line.key] = value))

const chosen = computed(() => lines.value.filter((line) => ticked[line.key] && Number(amounts[line.key]) > 0))
const total = computed(() => chosen.value.reduce((sum, line) => sum + Number(amounts[line.key]), 0))

const reference = ref(props.purchase_order ?? "")
const notes = ref("")
const sending = ref(false)
const sendError = ref<string | null>(null)
const sendBarEnd = ref<HTMLElement | null>(null)
const isSendBarFloating = ref(false)
useIntersectionObserver(sendBarEnd, ([entry]) => (isSendBarFloating.value = !entry.isIntersecting))

const advanceMessages = reactive<Record<number, { text: string; isError: boolean }>>({})

const switchClient = (slug: string) =>
	router.get(route(route().current() as string, { ...route().params, client: slug }), {}, { preserveScroll: true })

const allOrdersUrl = computed(() =>
	route(route().current() as string, Object.fromEntries(Object.entries(route().params).filter(([key]) => key !== "purchase_order")))
)

const hasShipmentLines = computed(() => (props.due.shipments ?? []).length > 0)

const walletHint = computed(() => {
	const balance = props.due.wallet.balance
	if (balance < 0) {
		const replacements = { amount: money(-balance), client: props.client.code }
		if (hasShipmentLines.value) {
			return props.due.negative_balance > 0
				? ctrans("The agent has paid out :amount more than :client sent; shipment costs are asked per shipment below and the rest is the first line", replacements)
				: ctrans("The agent has paid out :amount more than :client sent; shipment costs are asked per shipment below", replacements)
		}
		return props.due.negative_balance > 0
			? ctrans("The agent has paid out :amount more than :client sent; covering it is the first line below", replacements)
			: ctrans("The agent has paid out :amount more than :client sent; it was already asked for in an earlier request", replacements)
	}
	if (balance > 0) {
		return ctrans(":amount of :client money is still unspent with the agent; it is not taken off the lines below, lower them if it should be used first", { amount: money(balance), client: props.client.code })
	}
	return null
})

const saveAdvance = (order: DueOrder, percentage: number | null) => {
	if (!order.supplier_id || percentage === order.advance_percentage) return
	router.patch(route(props.advance_route_name, { supplier: order.supplier_id }), { advance_percentage: percentage }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () =>
			(advanceMessages[order.purchase_order_id] = {
				text:
					percentage === null
						? ctrans(":supplier is back to the usual 30% in advance", { supplier: order.supplier_code ?? "" })
						: ctrans(":supplier asks :percentage% in advance from now on", { supplier: order.supplier_code ?? "", percentage: String(percentage) }),
				isError: false,
			}),
		onError: (errors) => (advanceMessages[order.purchase_order_id] = { text: (Object.values(errors ?? {})[0] as string) ?? ctrans("Something went wrong"), isError: true }),
	})
}

const send = () => {
	if (!chosen.value.length) return
	router.post(
		route(props.store_route.name, props.store_route.parameters),
		{
			organisation_id: props.client.id,
			reference: reference.value || null,
			notes: notes.value || null,
			lines: chosen.value.map((line) => ({
				kind: line.kind,
				purchase_order_id: line.purchase_order_id,
				stock_delivery_id: line.stock_delivery_id,
				amount: Number(amounts[line.key]),
			})),
		},
		{
			onStart: () => {
				sending.value = true
				sendError.value = null
			},
			onFinish: () => (sending.value = false),
			onError: (errors) => (sendError.value = (Object.values(errors ?? {})[0] as string) ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="mx-4 mt-4 rounded-lg border border-gray-200 bg-white">
		<div class="flex flex-wrap items-start justify-between gap-x-8 gap-y-4 p-4">
			<div class="flex flex-col gap-2">
				<label for="money-request-client" class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Client") }}</label>
				<Select inputId="money-request-client" :modelValue="client.slug" :options="clients" optionLabel="label" optionValue="slug" filter class="w-80 max-w-full" :class="fieldFocusClass" @update:modelValue="switchClient" />
				<div v-if="purchase_order" class="flex items-center gap-2 text-xs">
					<span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] px-2 py-0.5 text-gray-700">
						<FontAwesomeIcon icon="fal fa-clipboard-list" fixed-width aria-hidden="true" />
						{{ ctrans("Only :reference", { reference: purchase_order }) }}
					</span>
					<Link :href="allOrdersUrl" preserveScroll class="inline-flex items-center gap-1 text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline">
						<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
						{{ ctrans("Show all orders") }}
					</Link>
				</div>
			</div>

			<div class="flex flex-col gap-2">
				<span class="flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-gray-500">
					<FontAwesomeIcon icon="fal fa-wallet" fixed-width aria-hidden="true" />
					{{ ctrans(":client wallet held by the agent", { client: client.code }) }}
				</span>
				<dl class="grid grid-cols-3 divide-x divide-gray-200 rounded-lg border border-gray-200">
					<div class="px-4 py-2">
						<dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Received") }}</dt>
						<dd class="whitespace-nowrap font-medium text-gray-800">{{ money(due.wallet.received) }}</dd>
					</div>
					<div class="px-4 py-2">
						<dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Paid out") }}</dt>
						<dd class="whitespace-nowrap font-medium text-gray-800">{{ money(due.wallet.paid) }}</dd>
					</div>
					<div class="px-4 py-2">
						<dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Balance") }}</dt>
						<dd class="whitespace-nowrap font-semibold" :class="due.wallet.balance < 0 ? 'text-red-600' : due.wallet.balance > 0 ? 'text-green-600' : 'text-gray-800'">{{ money(due.wallet.balance) }}</dd>
					</div>
				</dl>
			</div>
		</div>

		<div v-if="walletHint" class="flex items-start gap-2 border-t border-gray-200 px-4 py-2 text-xs" :class="due.wallet.balance < 0 ? 'bg-amber-50 text-amber-800' : 'bg-gray-50 text-gray-600'">
			<FontAwesomeIcon icon="fal fa-info-circle" fixed-width class="mt-0.5 shrink-0" aria-hidden="true" />
			<span>{{ walletHint }}</span>
		</div>
	</div>

	<div v-if="lines.length" class="sticky top-10 z-[5] mx-4 mt-4 bg-white pt-2">
		<div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] px-4 py-2">
			<div class="flex flex-wrap items-center gap-3">
				<SegmentedToggle v-model="lineFilter" :options="lineFilterOptions" :ariaLabel="ctrans('Show the lines for')" />
				<span class="text-sm text-gray-600">{{ ctrans(":count of :total lines selected", { count: String(chosen.length), total: String(lines.length) }) }}</span>
			</div>
			<span class="flex items-baseline gap-2">
				<span class="text-xs uppercase tracking-wide text-gray-500">{{ ctrans("To ask for") }}</span>
				<span class="whitespace-nowrap text-lg font-semibold text-gray-900">{{ money(total) }}</span>
			</span>
		</div>
	</div>

	<div class="mx-4 overflow-x-auto rounded-lg border border-gray-200 bg-white" :class="lines.length ? 'mt-2' : 'mt-4'">
		<table class="min-w-full text-sm">
			<thead class="whitespace-nowrap bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
				<tr>
					<th class="w-10 px-3 py-2">
						<Checkbox
							v-if="visibleLines.length"
							v-tooltip="allTicked ? ctrans('Unselect all') : ctrans('Select all')"
							:modelValue="allTicked"
							:indeterminate="someTicked"
							binary
							@update:modelValue="tickAll(!allTicked)"
						/>
					</th>
					<th class="px-3 py-2 font-medium">{{ ctrans("For") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Total") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Already asked") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Advance %") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Amount") }}</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				<tr v-if="!lines.length">
					<td colspan="6" class="px-3 py-6 text-center text-gray-400">{{ ctrans("Nothing is due from :client", { client: client.code }) }}</td>
				</tr>
				<tr v-for="line in visibleLines" :key="line.key" :class="ticked[line.key] ? '' : 'text-gray-400'">
					<td class="px-3 py-2"><Checkbox v-model="ticked[line.key]" binary /></td>
					<td class="min-w-[18rem] px-3 py-2">
						<span v-if="line.kind === 'wallet'" class="font-medium">{{ ctrans("Cover the negative balance") }}</span>
						<div v-else-if="line.shipment" class="space-y-1 text-xs text-gray-600">
							<div class="flex items-start gap-2">
								<span class="flex items-center gap-1">
									<FontAwesomeIcon v-tooltip="ctrans('Shipment')" icon="fal fa-shipping-fast" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
									<Link :href="route(line.shipment.route.name, line.shipment.route.parameters)" class="whitespace-nowrap text-sm font-semibold text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline">{{ line.shipment.reference }}</Link>
								</span>
								<span class="ml-auto shrink-0 whitespace-nowrap rounded border border-gray-200 px-1.5 font-medium text-gray-700">{{ ctrans("Shipment costs") }}</span>
							</div>
							<div class="flex flex-wrap items-center gap-x-1">
								<template v-for="(charge, index) in line.shipment.charges" :key="charge.type">
									<span v-if="index" class="text-gray-400">·</span>
									<span class="whitespace-nowrap">{{ charge.label }} {{ money(charge.amount) }}</span>
								</template>
							</div>
						</div>
						<div v-else-if="line.order" class="space-y-1 text-xs text-gray-600">
							<div class="flex items-start gap-2">
								<div class="flex min-w-0 flex-col">
									<Link v-if="line.order.supplier_route" :href="route(line.order.supplier_route.name, line.order.supplier_route.parameters)" class="text-sm font-semibold leading-snug text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline" :title="line.order.supplier_code ?? ''">{{ line.order.supplier_company ?? line.order.supplier_code }}</Link>
									<span v-else class="text-sm font-semibold leading-snug text-gray-900" :title="line.order.supplier_code ?? ''">{{ line.order.supplier_company ?? line.order.supplier_code }}</span>
									<span v-if="line.order.supplier_nickname" class="text-gray-500">{{ line.order.supplier_nickname }}</span>
								</div>
								<span class="ml-auto shrink-0 whitespace-nowrap rounded border border-gray-200 px-1.5 font-medium text-gray-700">{{ line.kind === "advance" ? ctrans("Advance") : ctrans("Balance") }}</span>
							</div>
							<div class="flex flex-wrap items-center gap-x-1">
								<span class="flex items-center gap-1">
									<FontAwesomeIcon v-tooltip="ctrans('Placed by')" icon="fal fa-user" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
									{{ line.order.placed_by ?? ctrans("Unknown") }}
								</span>
								<template v-if="line.order.submitted_at">
									<span class="text-gray-400">·</span>
									<span class="whitespace-nowrap" :title="ctrans('Calendar days since the order was submitted (Day Zero)')">{{ ctrans("Age") }} Z+{{ ageInDays(line.order.submitted_at) }}</span>
								</template>
							</div>
							<div class="flex items-center gap-1">
								<FontAwesomeIcon v-tooltip="ctrans('Purchase order')" icon="fal fa-clipboard-list" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
								<Link :href="route(line.order.route.name, line.order.route.parameters)" class="min-w-0 truncate whitespace-nowrap text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline" :title="line.order.reference">{{ line.order.reference }}</Link>
							</div>
						</div>
					</td>
					<td class="whitespace-nowrap px-3 py-2 text-right">
						<template v-if="line.order ?? line.shipment">{{ money((line.order ?? line.shipment)!.total) }}</template>
					</td>
					<td class="whitespace-nowrap px-3 py-2 text-right">
						<template v-if="line.order ?? line.shipment">{{ (line.order ?? line.shipment)!.requested ? money((line.order ?? line.shipment)!.requested) : "-" }}</template>
					</td>
					<td class="px-3 py-2 text-right">
						<InputNumber
							v-if="line.kind === 'advance'"
							v-model="percentages[line.purchase_order_id!]"
							:min="0"
							:max="100"
							suffix="%"
							inputClass="w-20 text-right"
							:class="fieldFocusClass"
							@blur="saveAdvance(orderOf(line.purchase_order_id)!, percentages[line.purchase_order_id!])"
						/>
						<div v-if="line.kind === 'advance' && advanceMessages[line.purchase_order_id!]" class="mt-1 max-w-[12rem] text-xs" :class="advanceMessages[line.purchase_order_id!].isError ? 'text-red-600' : 'text-green-600'">
							{{ advanceMessages[line.purchase_order_id!].text }}
						</div>
					</td>
					<td class="px-3 py-2 text-right">
						<InputNumber v-model="amounts[line.key]" v-bind="amountInputOptions" :min="0" :max="line.due" :disabled="!ticked[line.key]" inputClass="w-36 text-right" :class="fieldFocusClass" />
					</td>
				</tr>
			</tbody>
			<tfoot v-if="lines.length" class="border-t border-gray-200">
				<tr>
					<td colspan="5" class="px-3 py-2 text-right text-xs uppercase tracking-wide text-gray-500">{{ ctrans("To ask for") }}</td>
					<td class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-800">{{ money(total) }}</td>
				</tr>
			</tfoot>
		</table>
	</div>

	<div class="mx-4 mt-4 grid gap-3 sm:grid-cols-2">
		<label class="block text-sm">
			<span class="text-gray-600">{{ ctrans("Reference") }}</span>
			<InputText v-model="reference" maxlength="255" class="mt-1 w-full" :class="fieldFocusClass" />
		</label>
		<label class="block text-sm">
			<span class="text-gray-600">{{ ctrans("Notes") }}</span>
			<Textarea v-model="notes" rows="1" autoResize class="mt-1 w-full" :class="fieldFocusClass" />
		</label>
	</div>

	<div
		class="sticky bottom-0 z-[5] mt-2 flex flex-col items-end gap-1 border-t px-4 pt-3 pb-7 transition-[background-color,box-shadow,border-color] duration-200"
		:class="isSendBarFloating ? 'border-gray-200 bg-white/95 shadow-[0_-4px_12px_rgb(0_0_0/0.08)] backdrop-blur' : 'border-transparent'">
		<div class="flex flex-wrap items-center justify-end gap-4">
			<span v-if="isSendBarFloating" class="flex items-baseline gap-2">
				<span class="text-xs uppercase tracking-wide text-gray-500">{{ ctrans(":count lines", { count: String(chosen.length) }) }}</span>
				<span class="whitespace-nowrap text-base font-semibold text-gray-900">{{ money(total) }}</span>
			</span>
			<span class="relative inline-flex">
				<Button type="primary" icon="fal fa-paper-plane" :label="ctrans('Send request')" :disabled="!chosen.length" :loading="sending" @click="send" />
				<PingIcon v-if="chosen.length && !sending" class="pointer-events-none !absolute -right-1 -top-1 text-[7px] text-amber-500" aria-hidden="true" />
			</span>
		</div>
		<div v-if="sendError" class="text-xs text-red-600">{{ sendError }}</div>
	</div>
	<div ref="sendBarEnd" class="mb-2 h-px" />
</template>
