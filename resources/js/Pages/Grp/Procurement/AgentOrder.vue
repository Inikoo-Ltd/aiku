<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import ConfirmDialog from "primevue/confirmdialog"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import RadioButton from "primevue/radiobutton"
import { useConfirm } from "primevue/useconfirm"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxes, faExclamationTriangle, faFilePdf, faPaperPlane, faTrashAlt } from "@fal"
import { faPlus } from "@far"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Icon from "@/Components/Icon.vue"
import ModalProductList from "@/Components/Utils/ModalProductList.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import type { OrderingLevel } from "@/Composables/useOrderingLevel"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faBoxes, faExclamationTriangle, faFilePdf, faPaperPlane, faTrashAlt, faPlus)

interface Money {
	currency_code: string
	amount: number
}

interface OrderLine {
	id: number
	code: string | null
	name: string | null
	sko_code: string | null
	quantity_ordered: number
	units_per_carton: number | string | null
	unit_cost: number | string
	net_amount: number | string
	state: string
	updateRoute: routeType | null
	deleteRoute: routeType | null
}

interface SupplierOrder {
	id: number
	reference: string
	supplier: { code: string; name: string }
	state: string
	state_label: string
	state_icon: any
	delivery_state_label: string
	estimated_received_at: string | null
	approved_ready_at: string | null
	deposit_amount: number | string | null
	deposit_paid_at: string | null
	currency_code: string
	cost_items: number | string
	cost_total: number | string
	route: routeType
	lines: OrderLine[]
}

const props = defineProps<{
	title: string
	breadcrumbs?: object[]
	pageHead: PageHeadingTypes
	agent_order: {
		reference: string
		state: { value: string; label: string; icon: any }
		agent: { code: string; name: string }
		number_supplier_orders: number
		number_items: number
		totals: Money[]
		org_total: Money
		is_open: boolean
		can_edit: boolean
	}
	supplier_orders: SupplierOrder[]
	products_list: routeType | null
	submit: {
		route: routeType
		reference: string
		channels: { channel: string; to: string }[]
		to_submit: { supplier_code: string; supplier_name: string; currency_code: string; cost_total: number | string }[]
		to_remove: string[]
	} | null
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const money = (code: string, amount: number | string | null) => locale.currencyFormat(code, Number(amount) || 0)

const date = (value: string | null) => (value ? useFormatTime(value, { formatTime: "EEE, do MMM yy" }) : "-")

const cartonsOf = (line: OrderLine) => {
	const perCarton = Number(line.units_per_carton)

	if (!perCarton) {
		return "-"
	}

	return locale.number(Math.round((Number(line.quantity_ordered) / perCarton) * 100) / 100)
}

const hasDeposit = (supplierOrder: SupplierOrder) => Number(supplierOrder.deposit_amount) > 0

const isModalProductListOpen = ref(false)
const currentTab = ref("items")
const currentLevel = ref<OrderingLevel>("cartons")

watch(isModalProductListOpen, (isOpen, wasOpen) => {
	if (wasOpen && !isOpen) {
		router.reload()
	}
})

const reloadOrders = () => router.reload({ only: ["supplier_orders", "agent_order", "submit", "pageHead"] })

const errorText = (error: any) => error?.response?.data?.message || ctrans("Something went wrong")

const drafts = reactive<Record<number, number | null>>({})
const savingLineIds = reactive(new Set<number>())

const draftOf = (line: OrderLine) => drafts[line.id] ?? line.quantity_ordered

const saveUnits = async (line: OrderLine) => {
	const quantity = drafts[line.id]

	if (!line.updateRoute || savingLineIds.has(line.id)) {
		return
	}

	if (quantity === undefined || quantity === null || Number(quantity) === Number(line.quantity_ordered)) {
		delete drafts[line.id]
		return
	}

	savingLineIds.add(line.id)
	try {
		await axios.patch(route(line.updateRoute.name, line.updateRoute.parameters), { quantity_ordered: quantity })
		notify({ title: ctrans("Success"), text: ctrans("Quantity updated"), type: "success" })
		await new Promise<void>((resolve) => {
			router.reload({
				only: ["supplier_orders", "agent_order", "submit"],
				onFinish: () => resolve(),
			})
		})
	} catch (error: any) {
		notify({ title: ctrans("Something went wrong"), text: errorText(error), type: "error" })
	} finally {
		delete drafts[line.id]
		savingLineIds.delete(line.id)
	}
}

const confirmDeleteLine = (line: OrderLine) => {
	confirm.require({
		group: "agent-order-line",
		header: ctrans("Remove product"),
		message: ctrans("Remove :code from this order?", { code: line.code ?? "" }),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Remove"), severity: "danger" },
		accept: async () => {
			savingLineIds.add(line.id)
			try {
				await axios.delete(route(line.deleteRoute!.name, line.deleteRoute!.parameters))
				reloadOrders()
			} catch (error: any) {
				notify({ title: ctrans("Something went wrong"), text: errorText(error), type: "error" })
			} finally {
				savingLineIds.delete(line.id)
			}
		},
	})
}

const doNotSend = "none"
const isSubmitDialogOpen = ref(false)
const sendVia = ref<string>(doNotSend)
const submitLoading = ref(false)

const openSubmitDialog = () => {
	sendVia.value = props.submit?.channels[0]?.channel ?? doNotSend
	isSubmitDialogOpen.value = true
}

const submitAll = () => {
	if (!props.submit) {
		return
	}

	router.patch(
		route(props.submit.route.name, props.submit.route.parameters),
		{
			agent_order_reference: props.submit.reference,
			send_via: sendVia.value === doNotSend ? null : sendVia.value,
		},
		{
			onStart: () => { submitLoading.value = true },
			onFinish: () => { submitLoading.value = false },
			onSuccess: () => {
				isSubmitDialogOpen.value = false
				notify({ title: ctrans("Success"), text: ctrans("Agent order submitted"), type: "success" })
			},
			onError: (errors) => {
				notify({
					title: ctrans("Something went wrong"),
					text: Object.values(errors)[0] || ctrans("Failed to submit agent order"),
					type: "error",
				})
			},
		}
	)
}

const hasToolbar = computed(() => !!props.products_list || !!props.submit)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template v-if="hasToolbar" #other>
			<Button
				v-if="products_list"
				type="secondary"
				icon="far fa-plus"
				:label="ctrans('Add products')"
				@click="isModalProductListOpen = true"
			/>
			<Button
				v-if="submit"
				type="save"
				icon="fal fa-paper-plane"
				:label="ctrans('Submit all')"
				:loading="submitLoading"
				@click="openSubmitDialog"
			/>
		</template>
	</PageHeading>

	<div class="mx-4 mt-4 rounded-lg border border-gray-200 bg-white p-4">
		<div class="flex flex-wrap items-start justify-between gap-x-8 gap-y-3">
			<div class="flex flex-col gap-1">
				<div class="flex items-center gap-2 text-lg font-semibold text-gray-700">
					<FontAwesomeIcon icon="fal fa-boxes" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ agent_order.agent.name }}
					<span class="text-sm font-normal text-gray-400">{{ agent_order.agent.code }}</span>
				</div>
				<div class="flex items-center gap-1 text-sm text-gray-600">
					<Icon :data="agent_order.state.icon" />
					<span>{{ agent_order.state.label }}</span>
				</div>
				<div class="text-sm text-gray-500">
					{{ ctrans(":count supplier orders", { count: agent_order.number_supplier_orders }) }}
					·
					{{ ctrans(":count items", { count: agent_order.number_items }) }}
				</div>
			</div>

			<div class="flex flex-col items-end gap-0.5 text-sm">
				<div v-for="total in agent_order.totals" :key="total.currency_code" class="text-gray-600">
					{{ money(total.currency_code, total.amount) }}
				</div>
				<div
					v-if="agent_order.totals.length !== 1 || agent_order.totals[0].currency_code !== agent_order.org_total.currency_code"
					class="mt-1 border-t border-gray-200 pt-1 font-semibold text-gray-700"
				>
					{{ money(agent_order.org_total.currency_code, agent_order.org_total.amount) }}
				</div>
			</div>
		</div>
		<p class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
			{{ ctrans("Each supplier has its own order. Open it for confirmations, deposits, dates and deliveries.") }}
		</p>
	</div>

	<div
		v-if="!supplier_orders.length"
		class="mx-4 mt-4 flex flex-col items-center gap-3 rounded-lg border border-dashed border-gray-300 p-10 text-center"
	>
		<FontAwesomeIcon icon="fal fa-boxes" class="text-3xl text-gray-300" aria-hidden="true" />
		<p class="text-gray-500">
			{{ agent_order.is_open ? ctrans("This agent order is empty. Add products from any of the agent's suppliers and each supplier gets its own order.") : ctrans("This agent order has no supplier orders.") }}
		</p>
		<Button
			v-if="products_list"
			type="secondary"
			icon="far fa-plus"
			:label="ctrans('Add products')"
			@click="isModalProductListOpen = true"
		/>
	</div>

	<div class="mx-4 mt-4 flex flex-col gap-4 pb-12">
		<section
			v-for="supplierOrder in supplier_orders"
			:key="supplierOrder.id"
			class="overflow-hidden rounded-lg border border-gray-200 bg-white"
		>
			<header class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
				<div class="flex flex-col gap-1">
					<div class="flex flex-wrap items-baseline gap-x-2">
						<span class="font-semibold text-gray-700">{{ supplierOrder.supplier.code }}</span>
						<span class="text-sm text-gray-500">{{ supplierOrder.supplier.name }}</span>
					</div>
					<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
						<Link :href="route(supplierOrder.route.name, supplierOrder.route.parameters)" class="primaryLink">
							{{ supplierOrder.reference }}
						</Link>
						<span class="flex items-center gap-1 text-gray-600">
							<Icon :data="supplierOrder.state_icon" />
							{{ supplierOrder.state_label }}
						</span>
						<span class="text-gray-500">{{ supplierOrder.delivery_state_label }}</span>
					</div>
				</div>

				<div class="flex flex-col gap-0.5 text-xs text-gray-500 sm:items-end">
					<span>{{ ctrans("ETA") }}: {{ date(supplierOrder.estimated_received_at) }}</span>
					<span v-if="supplierOrder.approved_ready_at">
						{{ ctrans("Approved ready") }}: {{ date(supplierOrder.approved_ready_at) }}
					</span>
					<span v-if="hasDeposit(supplierOrder)">
						{{ ctrans("Deposit") }}: {{ money(supplierOrder.currency_code, supplierOrder.deposit_amount) }}
						<template v-if="supplierOrder.deposit_paid_at">
							({{ ctrans("paid") }} {{ date(supplierOrder.deposit_paid_at) }})
						</template>
						<template v-else>({{ ctrans("not paid") }})</template>
					</span>
				</div>

				<div class="text-right">
					<div class="text-xs text-gray-400">{{ ctrans("Total") }}</div>
					<div class="text-lg font-semibold text-gray-700">
						{{ money(supplierOrder.currency_code, supplierOrder.cost_total) }}
					</div>
				</div>
			</header>

			<div class="overflow-x-auto">
				<table class="min-w-full text-sm">
					<thead class="bg-white text-left text-xs uppercase tracking-wide text-gray-400">
						<tr>
							<th class="px-4 py-2 font-medium">{{ ctrans("Code") }}</th>
							<th class="px-4 py-2 font-medium">{{ ctrans("Name") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Units/carton") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Cartons") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Units") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Unit cost") }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Amount") }}</th>
							<th class="w-10 px-2 py-2"></th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="line in supplierOrder.lines" :key="line.id">
							<td class="whitespace-nowrap px-4 py-2">
								<span class="font-medium text-gray-700">{{ line.code }}</span>
								<span v-if="line.sko_code && line.sko_code !== line.code" class="ml-2 text-xs text-gray-400">{{ line.sko_code }}</span>
							</td>
							<td class="px-4 py-2 text-gray-600">{{ line.name }}</td>
							<td class="px-4 py-2 text-right text-gray-500">{{ line.units_per_carton ?? "-" }}</td>
							<td class="px-4 py-2 text-right text-gray-600">{{ cartonsOf(line) }}</td>
							<td class="px-4 py-2 text-right">
								<InputNumber
									v-if="line.updateRoute"
									:modelValue="draftOf(line)"
									:min="0"
									:disabled="savingLineIds.has(line.id)"
									inputClass="w-24 text-right"
									@update:modelValue="(value: number | null) => (drafts[line.id] = value)"
									@blur="saveUnits(line)"
									@keydown.enter="saveUnits(line)"
								/>
								<span v-else class="text-gray-600">{{ locale.number(Number(line.quantity_ordered)) }}</span>
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right text-gray-600">
								{{ money(supplierOrder.currency_code, line.unit_cost) }}
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right font-medium text-gray-700">
								{{ money(supplierOrder.currency_code, line.net_amount) }}
							</td>
							<td class="px-2 py-2 text-right">
								<Button
									v-if="line.deleteRoute"
									type="delete"
									size="xs"
									icon="fal fa-trash-alt"
									:tooltip="ctrans('Remove')"
									:disabled="savingLineIds.has(line.id)"
									@click="confirmDeleteLine(line)"
								/>
							</td>
						</tr>
						<tr v-if="!supplierOrder.lines.length">
							<td colspan="8" class="px-4 py-4 text-center text-gray-400">{{ ctrans("No items") }}</td>
						</tr>
					</tbody>
					<tfoot class="border-t border-gray-200 bg-gray-50">
						<tr>
							<td colspan="6" class="px-4 py-2 text-right text-xs uppercase tracking-wide text-gray-400">
								{{ ctrans("Subtotal") }}
							</td>
							<td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-700">
								{{ money(supplierOrder.currency_code, supplierOrder.cost_items) }}
							</td>
							<td></td>
						</tr>
					</tfoot>
				</table>
			</div>
		</section>
	</div>

	<ModalProductList
		v-if="products_list"
		v-model="isModalProductListOpen"
		:fetchRoute="products_list"
		:action="null"
		:current="currentTab"
		v-model:currentTab="currentTab"
		typeModel="purchase_order"
		v-model:level="currentLevel"
	/>

	<ConfirmDialog group="agent-order-line">
		<template #icon>
			<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-xl text-orange-500" fixed-width />
		</template>
	</ConfirmDialog>

	<Dialog
		v-model:visible="isSubmitDialogOpen"
		modal
		:header="ctrans('Submit all supplier orders')"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="flex flex-col gap-3">
			<div class="rounded-md border border-gray-200 bg-gray-50 p-3">
				<p class="text-sm font-medium text-gray-700">
					{{ submit?.to_submit.length === 1 ? ctrans("1 supplier order will be submitted:") : ctrans(":count supplier orders will be submitted:", { count: submit?.to_submit.length ?? 0 }) }}
				</p>
				<ul class="mt-2 flex flex-col gap-1 text-sm">
					<li v-for="order in submit?.to_submit" :key="order.supplier_code" class="flex items-baseline justify-between gap-4">
						<span class="text-gray-700">
							{{ order.supplier_code }}
							<span class="text-xs text-gray-400">{{ order.supplier_name }}</span>
						</span>
						<span class="whitespace-nowrap font-medium text-gray-700">{{ money(order.currency_code, order.cost_total) }}</span>
					</li>
				</ul>
				<p class="mt-2 border-t border-gray-200 pt-2 text-xs text-gray-500">
					{{ ctrans("Supplier orders without products are removed.") }}
					<template v-if="submit?.to_remove.length">
						{{ ctrans("Removed now: :suppliers", { suppliers: submit.to_remove.join(", ") }) }}
					</template>
				</p>
			</div>
			<label v-for="option in submit?.channels" :key="option.channel" class="flex cursor-pointer items-start gap-3">
				<RadioButton v-model="sendVia" :value="option.channel" :inputId="`agent-order-send-${option.channel}`" />
				<span class="text-sm text-gray-700">
					{{ option.channel === "email" ? ctrans("Email the purchase order PDF to") : ctrans("Send the purchase order PDF by WhatsApp to") }}
					<span class="font-medium">{{ option.to }}</span>
				</span>
			</label>
			<label class="flex cursor-pointer items-start gap-3">
				<RadioButton v-model="sendVia" :value="doNotSend" inputId="agent-order-send-none" />
				<span class="text-sm text-gray-700">{{ ctrans("Don't send, I will send it myself") }}</span>
			</label>
			<p class="text-xs text-gray-500">{{ ctrans("Replies arrive in the procurement inbox.") }}</p>
		</div>

		<template #footer>
			<Button :label="ctrans('Cancel')" type="secondary" @click="isSubmitDialogOpen = false" />
			<Button
				:label="sendVia !== doNotSend ? ctrans('Submit and send email') : ctrans('Submit')"
				type="save"
				icon="fal fa-paper-plane"
				:loading="submitLoading"
				@click="submitAll"
			/>
		</template>
	</Dialog>
</template>
