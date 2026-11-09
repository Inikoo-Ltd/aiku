<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import Checkbox from "primevue/checkbox"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faUser, faClipboardList } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faPaperPlane, faUser, faClipboardList)

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
		orders: DueOrder[]
	}
	store_route: routeType
	advance_route_name: string
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.due.currency_code, amount)
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const ageInDays = (date: string | null) => (date ? Math.max(0, Math.round((Date.parse(new Date().toISOString().slice(0, 10)) - Date.parse(date)) / 86400000)) : null)

interface Line {
	key: string
	kind: "advance" | "balance" | "wallet"
	purchase_order_id: number | null
	order: DueOrder | null
	due: number
}

const lines = computed<Line[]>(() => [
	...(props.due.negative_balance > 0 ? [{ key: "wallet", kind: "wallet" as const, purchase_order_id: null, order: null, due: props.due.negative_balance }] : []),
	...props.due.orders.flatMap((order) => [
		...(order.advance_due > 0 ? [{ key: `advance:${order.purchase_order_id}`, kind: "advance" as const, purchase_order_id: order.purchase_order_id, order, due: order.advance_due }] : []),
		...(order.balance_due > 0 ? [{ key: `balance:${order.purchase_order_id}`, kind: "balance" as const, purchase_order_id: order.purchase_order_id, order, due: order.balance_due }] : []),
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

const chosen = computed(() => lines.value.filter((line) => ticked[line.key] && Number(amounts[line.key]) > 0))
const total = computed(() => chosen.value.reduce((sum, line) => sum + Number(amounts[line.key]), 0))

const reference = ref(props.purchase_order ?? "")
const notes = ref("")
const sending = ref(false)
const sendError = ref<string | null>(null)
const advanceMessages = reactive<Record<number, { text: string; isError: boolean }>>({})

const switchClient = (slug: string) =>
	router.get(route(route().current() as string, { ...route().params, client: slug }), {}, { preserveScroll: true })

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
			lines: chosen.value.map((line) => ({ kind: line.kind, purchase_order_id: line.purchase_order_id, amount: Number(amounts[line.key]) })),
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

	<div class="mx-4 mt-4 flex flex-wrap items-end gap-4">
		<label class="block text-sm">
			<span class="text-gray-600">{{ ctrans("Client") }}</span>
			<Select :modelValue="client.slug" :options="clients" optionLabel="label" optionValue="slug" class="mt-1 w-72" :class="fieldFocusClass" @update:modelValue="switchClient" />
		</label>
		<div class="rounded-lg border border-gray-200 bg-white px-4 py-2">
			<div class="text-xs text-gray-400">{{ ctrans("Wallet balance") }}</div>
			<div class="font-semibold" :class="due.wallet.balance < 0 ? 'text-red-600' : 'text-gray-800'">{{ money(due.wallet.balance) }}</div>
		</div>
		<div v-if="purchase_order" class="text-sm text-gray-500">{{ ctrans("Only :reference", { reference: purchase_order }) }}</div>
	</div>

	<div class="mx-4 mt-4 overflow-x-auto rounded-lg border border-gray-200 bg-white">
		<table class="min-w-full text-sm">
			<thead class="whitespace-nowrap bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
				<tr>
					<th class="w-10 px-3 py-2"></th>
					<th class="px-3 py-2 font-medium">{{ ctrans("For") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Order total") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Already asked") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Advance %") }}</th>
					<th class="px-3 py-2 text-right font-medium">{{ ctrans("Amount") }}</th>
				</tr>
			</thead>
			<tbody class="divide-y divide-gray-100">
				<tr v-if="!lines.length">
					<td colspan="6" class="px-3 py-6 text-center text-gray-400">{{ ctrans("Nothing is due from :client", { client: client.code }) }}</td>
				</tr>
				<tr v-for="line in lines" :key="line.key" :class="ticked[line.key] ? '' : 'text-gray-400'">
					<td class="px-3 py-2"><Checkbox v-model="ticked[line.key]" binary /></td>
					<td class="min-w-[18rem] px-3 py-2">
						<span v-if="line.kind === 'wallet'" class="font-medium">{{ ctrans("Cover the negative balance") }}</span>
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
						<template v-if="line.kind !== 'wallet'">{{ money(orderOf(line.purchase_order_id)!.total) }}</template>
					</td>
					<td class="whitespace-nowrap px-3 py-2 text-right">
						<template v-if="line.kind !== 'wallet'">{{ orderOf(line.purchase_order_id)!.requested ? money(orderOf(line.purchase_order_id)!.requested) : "-" }}</template>
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
						<InputNumber v-model="amounts[line.key]" mode="currency" :currency="due.currency_code" :min="0" :max="line.due" :disabled="!ticked[line.key]" inputClass="w-36 text-right" :class="fieldFocusClass" />
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

	<div class="mx-4 my-5 flex flex-col items-end gap-1">
		<Button type="primary" icon="fal fa-paper-plane" :label="ctrans('Send request')" :disabled="!chosen.length" :loading="sending" @click="send" />
		<div v-if="sendError" class="text-xs text-red-600">{{ sendError }}</div>
	</div>
</template>
