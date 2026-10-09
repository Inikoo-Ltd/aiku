<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import type { Component } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"

import ConfirmDialog from "primevue/confirmdialog"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import ProcurementOrderData from "@/Components/Procurement/ProcurementOrderData.vue"
import StockDeliveryCostingChecklist from "@/Components/Procurement/StockDeliveryCostingChecklist.vue"
import StockDeliveryInvoiceCosting from "@/Components/Procurement/StockDeliveryInvoiceCosting.vue"
import StockDeliveryInvoiceEntry from "@/Components/Procurement/StockDeliveryInvoiceEntry.vue"
import StockDeliveryAgentPayments from "@/Components/Procurement/StockDeliveryAgentPayments.vue"
import StockDeliveryServiceInvoices from "@/Components/Procurement/StockDeliveryServiceInvoices.vue"
import AgentContainerInvoicePanel from "@/Components/Procurement/AgentContainerInvoicePanel.vue"
import TableStockDeliveryItems from "@/Components/Tables/Grp/Org/Procurement/TableStockDeliveryItems.vue"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"

import { useLocaleStore } from "@/Stores/locale"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"

import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Timeline as TSTimeline } from "@/types/Timeline"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faWarehouse, faPersonDolly, faBoxUsd, faTruck, faTerminal, faCameraRetro, faPaperclip, faInfoCircle, faHandHoldingBox, faPeopleArrows, faExclamationTriangle, faBoxOpen, faClipboardList } from "@fal"
import { faBars, faBoxCheck, faInventory as fasInventory, faShare, faArrowCircleRight, faArrowCircleLeft, faExclamationCircle, faBoxFull } from "@fas"

library.add(
	faInventory,
	faWarehouse,
	faPersonDolly,
	faBoxUsd,
	faTruck,
	faTerminal,
	faCameraRetro,
	faPaperclip,
	faInfoCircle,
	faHandHoldingBox,
	faPeopleArrows,
	faExclamationTriangle,
	faBars,
	faBoxCheck,
	fasInventory,
	faShare,
	faArrowCircleRight,
	faArrowCircleLeft,
	faExclamationCircle,
	faBoxOpen,
	faBoxFull,
	faClipboardList
)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	stock_delivery: {
		state: string
	}
	timelines: {
		[key: string]: TSTimeline
	}
	purchase_orders?: {}
	box_stats: {
		first_block: {
			orderer: {
				id?: number
				slug?: string
				type?: string
				name?: string
			}
			delivery: {
				type: string | null
				incoterm: string | null
				port_of_export: string | null
				port_of_import: string | null
				delivery_address: string | null
			}
		}
		second_block: {
			state: string
			total_items: number
			total_received_checked_items: number
			total_placed_items: number
			total_new_org_stocks: number
			show_delivery_discrepancy: boolean
			total_under_delivered_items: number
			total_over_delivered_items: number
			weight: number | null
			volume: number | null
			is_weight_partial: boolean
			is_volume_partial: boolean
			production_time: string | null
			delivery_time: string | null
		}
		third_block: {
			currency: string | null
			org_currency: string | null
			org_exchange: number | string | null
			items: number | string | null
			extra: number | string | null
			shipping: number | string | null
			duties: number | string | null
			tax: number | string | null
			total: number | string
			org_items: number | string
		}
		invoice_entry: { route: routeType; is_agent: boolean } | null
		invoice: {
			kind: "agent" | "supplier"
			source: "agent" | "actual" | "estimated"
			reference: string | null
			date: string
			charges_list: { description: string; type?: "freight" | "other"; amount: number }[]
			currency: string
			org_currency: string
			org_exchange: number | null
			goods: number
			charges: number
			total: number
			paid: number | null
			balance_due: number | null
			agent: {
				can_edit: boolean
				charges_approved: boolean
				approve_route: routeType
				deposits: { type: string; reference: string | null; date: string | null; amount: number }[]
				payments: { id: number; date: string; amount: number; reference: string | null; notes: string | null; delete_route: routeType }[]
				payment_store_route: routeType
			} | null
		} | null
	}
	tabs: {
		current: string
		navigation: {}
	}
	attachmentRoutes: {
		attachRoute: routeType
		detachRoute: routeType
	}
	costing: {
		is_costed: boolean
		is_partner: boolean
		can_edit: boolean
		can_edit_payments: boolean
		reopened: { at: string, by: string | null, reason: string } | null
		currency: string | null
		checklist: any[]
		agent_invoice_missing: boolean
		storeCostRoute: routeType
		distributeExtraCostRoute: routeType | null
	}
	items?: {}
	pending_items?: {}
	done_items?: {}
	under_over_delivered?: {}
	showcase?: {}
	attachments?: {}
	attachmentScopes: { name: string; code: string }[]
	invoice_costing: InstanceType<typeof StockDeliveryInvoiceCosting>["$props"]["invoices"]
	agentInvoice?: InstanceType<typeof AgentContainerInvoicePanel>["$props"]["data"] | null
	service_invoices: InstanceType<typeof StockDeliveryServiceInvoices>["$props"]["data"] | null
	notes?: {}
	note_store_route?: routeType
	history?: {}
}>()

const locale = useLocaleStore()

const currentTab = ref(props.tabs.current)
const isModalUploadOpen = ref(false)
const isCostingOpen = ref(false)
const isCostingVisible = computed(() => !["in_process", "confirmed", "ready_to_ship", "cancelled", "not_received"].includes(props.stock_delivery.state))

const component = computed(() => {
	const components: Component = {
		items: TableStockDeliveryItems,
		pending_items: TableStockDeliveryItems,
		done_items: TableStockDeliveryItems,
		under_over_delivered: TableStockDeliveryItems,
		purchase_orders: TablePurchaseOrders,
		showcase: ProcurementOrderData,
		attachments: TableAttachments,
		notes: TableProcurementNotes,
		history: TableHistories,
	}

	return components[currentTab.value]
})

const metrics = computed(() => {
	const { weight, volume, is_weight_partial, is_volume_partial } = props.box_stats.second_block

	return [
		{
			key: "weight",
			isUnknown: weight === null,
			showMark: weight === null || is_weight_partial,
			text: weight === null ? ctrans("Unknown weight") : `${locale.number(weight)}Kg`,
			tooltip: weight === null ? ctrans("No item has weight data") : ctrans("Some items have unknown weight"),
		},
		{
			key: "volume",
			isUnknown: volume === null,
			showMark: volume === null || is_volume_partial,
			text: volume === null ? ctrans("Unknown CBM") : `${locale.number(volume)} m³`,
			tooltip: volume === null ? ctrans("No item has CBM data") : ctrans("Some items have unknown CBM"),
		},
	]
})

const ordererRoute = computed<string>(() => {
	const orderer = props.box_stats.first_block.orderer
	const routeKey = orderer.slug ?? orderer.id
	const type = orderer.type

	if (!routeKey || !type) return ""

	const organisation = route().params["organisation"]

	switch (type) {
		case "Agent":
			return route("grp.org.procurement.org_agents.show", [organisation, routeKey])
		case "Supplier":
			return route("grp.org.procurement.org_suppliers.show", [organisation, routeKey])
		case "Partner":
			return route("grp.org.procurement.org_partners.show", [organisation, routeKey])
		default:
			return ""
	}
})

const orgPerOrder = computed(() => {
	const { items, org_items, org_exchange } = props.box_stats.third_block
	const deliveryItems = Number(items)
	const orgItems = Number(org_items)

	if (deliveryItems) {
		return orgItems / deliveryItems
	}

	return Number(org_exchange) || null
})

const costRows = computed(() => {
	const { items, extra, shipping, duties, tax } = props.box_stats.third_block

	return [
		{ key: "items", label: ctrans("Items"), amount: Number(items) || 0, alwaysShown: true },
		{ key: "extra", label: ctrans("Extra costs"), amount: Number(extra) || 0, alwaysShown: false },
		{ key: "shipping", label: ctrans("Shipping"), amount: Number(shipping) || 0, alwaysShown: false },
		{ key: "duties", label: ctrans("Duties"), amount: Number(duties) || 0, alwaysShown: false },
		{ key: "tax", label: ctrans("Tax"), amount: Number(tax) || 0, alwaysShown: false },
	].filter(row => row.alwaysShown || row.amount !== 0)
})

const costBlocks = computed(() => {
	const { currency, org_currency, items, total, org_items } = props.box_stats.third_block

	const money = (code: string | null, amount: number) => locale.currencyFormat(code ?? "", amount)

	const supplierBlock = {
		key: "supplier",
		title: `${ctrans("Supplier invoice currency")} ${currency ?? ""}`.trim(),
		rows: [
			...costRows.value.map(row => ({ label: row.label, value: money(currency, row.amount) })),
			{ label: ctrans("Total"), value: money(currency, Number(total)), isTotal: true },
		],
	}

	const sameCurrency = !org_currency || org_currency === currency
	const orgCurrency = org_currency || currency
	const rate = sameCurrency ? 1 : (orgPerOrder.value ?? 1)

	const orgAmount = (row: { key: string; amount: number }) =>
		row.key === "items" && !sameCurrency ? Number(org_items) : row.amount * rate

	const orgTotal = costRows.value.reduce((sum, row) => sum + orgAmount(row), 0)

	const rateLabel = `${ctrans("Organisation currency")} ${orgCurrency ?? ""}`.trim()

	const agentInvoice = props.box_stats.invoice
	const agentInvoiceRate = agentInvoice?.org_exchange ?? null
	const agentInvoiceRow = (label: string, amount: number, isTotal = false) => ({
		label,
		value: agentInvoiceRate === null ? money(agentInvoice!.currency, amount) : money(agentInvoice!.org_currency, amount * agentInvoiceRate),
		sub: agentInvoiceRate === null || agentInvoice!.currency === agentInvoice!.org_currency ? null : money(agentInvoice!.currency, amount),
		isTotal,
	})

	const firstBlock = agentInvoice
		? {
			key: "invoice",
			title: `${agentInvoice.kind === "agent" ? ctrans("Agent invoice") : ctrans("Supplier invoice")} ${agentInvoice.reference ?? ""}`.trim(),
			badge: agentInvoice.source === "estimated" ? ctrans("Estimated") : null,
			rows: [
				agentInvoiceRow(ctrans("Goods"), agentInvoice.goods),
				agentInvoiceRow(agentInvoice.kind === "agent" ? ctrans("Agent charges") : ctrans("Supplier charges"), agentInvoice.charges),
				agentInvoiceRow(ctrans("Total"), agentInvoice.total, true),
				...(agentInvoice.paid === null || agentInvoice.balance_due === null ? [] : [
					agentInvoiceRow(ctrans("Deposits and payments"), -agentInvoice.paid),
					agentInvoiceRow(ctrans("Balance due"), agentInvoice.balance_due, true),
				]),
			],
		}
		: supplierBlock

	if (agentInvoice) {
		const deliveryAmount = (key: string) => Number(props.box_stats.third_block[key as "extra"]) || 0
		const agentInvoiceInDelivery = agentInvoice.currency === currency ? agentInvoice.charges : 0
		const localCosts = [
			{ label: ctrans("Customs duties"), amount: deliveryAmount("duties") },
			{ label: ctrans("Import tax"), amount: deliveryAmount("tax") },
			{ label: ctrans("Local shipping and other"), amount: deliveryAmount("shipping") + deliveryAmount("extra") - agentInvoiceInDelivery },
		].filter(row => Math.abs(row.amount) > 0.005)
		const localTotal = localCosts.reduce((sum, row) => sum + row.amount, 0) * rate
		const agentTotal = agentInvoiceRate === null ? 0 : agentInvoice.total * agentInvoiceRate

		return [
			firstBlock,
			{
				key: "landed",
				title: `${ctrans("Landed costs")} ${orgCurrency ?? ""}`.trim(),
				rows: [
					...(localCosts.length
						? localCosts.map(row => ({ label: row.label, value: money(orgCurrency, row.amount * rate) }))
						: [{ label: ctrans("No local costs entered yet"), value: "" }]),
					{ label: ctrans("Local costs"), value: money(orgCurrency, localTotal), isTotal: true },
					{ label: ctrans("Landed total"), value: money(orgCurrency, agentTotal + localTotal), sub: ctrans("agent invoice + local costs"), isTotal: true },
				],
			},
		]
	}

	return [
		firstBlock,
		{
			key: "org",
			title: rateLabel,
			rows: [
				...costRows.value.map(row => ({ label: row.label, value: money(orgCurrency, orgAmount(row)) })),
				{ label: ctrans("Total"), value: money(orgCurrency, orgTotal), isTotal: true },
			],
		},
	]
})

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const confirm = useConfirm()
const deleteLoading = ref(false)
const dispatchLoading = ref(false)
const undispatchLoading = ref(false)
const receiveLoading = ref(false)
const unreceiveLoading = ref(false)
const cancelLoading = ref(false)

const confirmDispatchStockDelivery = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: props.costing.agent_invoice_missing
			? ctrans("The agent invoice has not been received yet. Are you sure you want to mark this stock delivery as dispatched?")
			: ctrans("Are you sure you want to mark this stock delivery as dispatched?"),
		header: ctrans("Dispatch Stock Delivery"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Mark as Dispatched"), severity: "primary" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { dispatchLoading.value = true },
				onFinish: () => { dispatchLoading.value = false },
				onError: (errors) => {
					notify({
						title: ctrans("Something went wrong"),
						text: errors?.invoice ?? ctrans("Failed to dispatch stock delivery"),
						type: "error",
					})
				},
			})
		},
	})
}

const confirmUndispatchStockDelivery = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("Are you sure you want to unmark this stock delivery as dispatched? It will be reverted to its previous state."),
		header: ctrans("Unmark as Dispatched"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Unmark as Dispatched"), severity: "primary" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { undispatchLoading.value = true },
				onFinish: () => { undispatchLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to unmark stock delivery as dispatched"),
						type: "error",
					})
				},
			})
		},
	})
}

const confirmReceiveStockDelivery = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("Are you sure you want to mark this stock delivery as received? This can not be reverted."),
		header: ctrans("Receive Stock Delivery"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Mark as Received"), severity: "primary" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { receiveLoading.value = true },
				onFinish: () => { receiveLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to receive stock delivery"),
						type: "error",
					})
				},
			})
		},
	})
}

const confirmUnreceiveStockDelivery = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("Are you sure you want to unmark this stock delivery as received? It will be reverted to its previous state."),
		header: ctrans("Unmark as Received"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Unmark as Received"), severity: "primary" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { unreceiveLoading.value = true },
				onFinish: () => { unreceiveLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to unmark stock delivery as received"),
						type: "error",
					})
				},
			})
		},
	})
}

const confirmCancelStockDelivery = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("Are you sure you want to cancel this stock delivery? Its items will be emptied and the purchase order will allow a new delivery."),
		header: ctrans("Cancel Stock Delivery"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Cancel Stock Delivery"), severity: "danger" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { cancelLoading.value = true },
				onFinish: () => { cancelLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to cancel stock delivery"),
						type: "error",
					})
				},
			})
		},
	})
}

const estimatedDeliveryDateAction = ref<any>(null)
const estimatedReceivingDate = ref<Date | null>(null)
const estimatedDeliveryDateLoading = ref(false)

const openEstimatedDeliveryDateModal = (action: any) => {
	estimatedReceivingDate.value = action.estimated_receiving_date
		? new Date(`${String(action.estimated_receiving_date).slice(0, 10)}T00:00:00`)
		: null
	estimatedDeliveryDateAction.value = action
}

const saveEstimatedDeliveryDate = () => {
	const action = estimatedDeliveryDateAction.value
	const date = estimatedReceivingDate.value

	router.patch(route(action.route.name, action.route.parameters), {
		estimated_receiving_date: date
			? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
			: null,
	}, {
		preserveScroll: true,
		onStart: () => { estimatedDeliveryDateLoading.value = true },
		onSuccess: () => { estimatedDeliveryDateAction.value = null },
		onFinish: () => { estimatedDeliveryDateLoading.value = false },
		onError: () => {
			notify({
				title: ctrans("Something went wrong"),
				text: ctrans("Failed to update estimated delivery date"),
				type: "error",
			})
		},
	})
}

const startCostingLoading = ref(false)

const confirmStartStockDeliveryCosting = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("Are you sure you want to place this stock delivery? This is its final state."),
		header: ctrans("Place stock delivery"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Place"), severity: "primary" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { startCostingLoading.value = true },
				onFinish: () => { startCostingLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to start checking the costs"),
						type: "error",
					})
				},
			})
		},
	})
}

const updateCostingLoading = ref(false)

const patchCosting = (action: any, data: Record<string, string>, failure: string) => {
	router.patch(route(action.route.name, action.route.parameters), data, {
		preserveScroll: true,
		onStart: () => { updateCostingLoading.value = true },
		onFinish: () => { updateCostingLoading.value = false },
		onError: (errors: Record<string, string>) => {
			notify({
				title: ctrans("Something went wrong"),
				text: Object.values(errors)[0] || failure,
				type: "error",
			})
		},
	})
}

const confirmReopenStockDeliveryCosting = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("This delivery is costed and its stock is already in the warehouse. Changing its costs changes the value of that stock: when you finish, the stock is revalued and its stock history is rebuilt from the day it was put away, so past stock values and reports will change. Every change is recorded with your name."),
		header: ctrans("Update costing"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Update costing"), severity: "danger" },
		accept: () => {
			const reason = window.prompt(ctrans("Why does the costing need to change?"))
			if (!reason?.trim()) {
				return
			}
			patchCosting(action, { reason: reason.trim() }, ctrans("Failed to update the costing"))
		},
	})
}

const confirmFinishStockDeliveryCosting = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("The stock put away from this delivery will be revalued with the new costs and its stock history rebuilt from the day it was put away."),
		header: ctrans("Finish costing"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Finish costing"), severity: "primary" },
		accept: () => patchCosting(action, {}, ctrans("Failed to finish the costing")),
	})
}

const confirmDeleteStockDelivery = (action: any) => {
	confirm.require({
		group: "stock-delivery",
		message: ctrans("Are you sure you want to delete this stock delivery? The purchase order will be reverted to before this delivery."),
		header: ctrans("Delete Stock Delivery"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Delete"), severity: "danger" },
		accept: () => {
			router.delete(route(action.route.name, action.route.parameters), {
				onStart: () => { deleteLoading.value = true },
				onFinish: () => { deleteLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to delete stock delivery"),
						type: "error",
					})
				},
			})
		},
	})
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button
				v-if="currentTab === 'attachments'"
				label="Attach"
				icon="upload"
				@click="() => (isModalUploadOpen = true)"
			/>
		</template>

		<template #button-dispatch-stock-delivery="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="dispatchLoading"
				@click="() => confirmDispatchStockDelivery(action)"
			/>
		</template>

		<template #button-undispatch-stock-delivery="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="undispatchLoading"
				@click="() => confirmUndispatchStockDelivery(action)"
			/>
		</template>

		<template #button-receive-stock-delivery="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="receiveLoading"
				@click="() => confirmReceiveStockDelivery(action)"
			/>
		</template>

		<template #button-unreceive-stock-delivery="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="unreceiveLoading"
				@click="() => confirmUnreceiveStockDelivery(action)"
			/>
		</template>

		<template #button-cancel-stock-delivery="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="cancelLoading"
				@click="() => confirmCancelStockDelivery(action)"
			/>
		</template>

		<template #button-start-stock-delivery-costing="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="startCostingLoading"
				@click="() => confirmStartStockDeliveryCosting(action)"
			/>
		</template>

		<template #button-reopen-stock-delivery-costing="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="updateCostingLoading"
				@click="() => confirmReopenStockDeliveryCosting(action)"
			/>
		</template>

		<template #button-finish-stock-delivery-costing="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="updateCostingLoading"
				@click="() => confirmFinishStockDeliveryCosting(action)"
			/>
		</template>

		<template #button-edit-estimated-delivery-date="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="estimatedDeliveryDateLoading"
				@click="() => openEstimatedDeliveryDateModal(action)"
			/>
		</template>

		<template #button-delete-stock-delivery="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="deleteLoading"
				@click="() => confirmDeleteStockDelivery(action)"
			/>
		</template>
	</PageHeading>

	<!-- Stock Delivery Timeline -->
	<div v-if="timelines" class="py-2 border-b border-gray-300">
		<Timeline
			:options="timelines"
			:state="stock_delivery.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>

	<AgentContainerInvoicePanel v-if="agentInvoice" :data="agentInvoice" :currencyCode="box_stats.third_block.currency ?? ''" class="mb-4" />

	<div class="grid grid-cols-2 lg:grid-cols-4 text-gray-500 divide-x divide-gray-300 border-b border-gray-300">
		<!-- First Block -->
		<BoxStatPallet class="p-4">
			<div class="flex flex-col gap-4">
				<!-- Orderer -->
				<div v-if="box_stats.first_block.orderer.name" class="flex items-center gap-2">
					<dt>
						<FontAwesomeIcon
							v-tooltip="ctrans(box_stats.first_block.orderer.type ?? '')"
							icon="fal fa-hand-holding-box"
							aria-hidden="true"
							fixed-width
						/>
					</dt>
					<dd>
						<Link v-if="ordererRoute" :href="ordererRoute" class="primaryLink">
							{{ box_stats.first_block.orderer.name }}
						</Link>
						<span v-else>{{ box_stats.first_block.orderer.name }}</span>
					</dd>
				</div>

				<!-- Delivery terms -->
				<div v-if="box_stats.first_block.delivery.type === 'container'">
					<div class="flex items-center gap-2">
						<dt>
							<FontAwesomeIcon
								v-tooltip="ctrans('Incoterm')"
								icon="fas fa-share"
								aria-hidden="true"
								fixed-width
							/>
						</dt>
						<dd v-if="box_stats.first_block.delivery.incoterm">{{ box_stats.first_block.delivery.incoterm }}</dd>
						<dd v-else class="flex items-center gap-1 text-red-500 text-sm italic">
							<FontAwesomeIcon icon="fas fa-exclamation-circle" aria-hidden="true" fixed-width />
							<span>{{ ctrans("Incoterm not set") }}</span>
						</dd>
					</div>

					<div class="flex items-center gap-2">
						<dt>
							<FontAwesomeIcon
								v-tooltip="ctrans('Port of export')"
								icon="fas fa-arrow-circle-right"
								aria-hidden="true"
								fixed-width
							/>
						</dt>
						<dd v-if="box_stats.first_block.delivery.port_of_export">{{ box_stats.first_block.delivery.port_of_export }}</dd>
						<dd v-else class="flex items-center gap-1 text-red-500 text-sm italic">
							<FontAwesomeIcon icon="fas fa-exclamation-circle" aria-hidden="true" fixed-width />
							<span>{{ ctrans("Port of export not set") }}</span>
						</dd>
					</div>

					<div class="flex items-center gap-2">
						<dt>
							<FontAwesomeIcon
								v-tooltip="ctrans('Port of import')"
								icon="fas fa-arrow-circle-left"
								aria-hidden="true"
								fixed-width
							/>
						</dt>
						<dd v-if="box_stats.first_block.delivery.port_of_import">{{ box_stats.first_block.delivery.port_of_import }}</dd>
						<dd v-else class="flex items-center gap-1 text-red-500 text-sm italic">
							<FontAwesomeIcon icon="fas fa-exclamation-circle" aria-hidden="true" fixed-width />
							<span>{{ ctrans("Port of import not set") }}</span>
						</dd>
					</div>
				</div>

				<!-- Deliver to -->
				<div class="pt-2 text-sm">
					<div class="text-gray-400">{{ ctrans("Deliver to") }}:</div>
					<div v-if="box_stats.first_block.delivery.delivery_address" class="text-xs whitespace-pre-line">{{ box_stats.first_block.delivery.delivery_address }}</div>
					<div v-else class="flex items-center gap-1 text-red-500 text-xs italic">
						<FontAwesomeIcon icon="fas fa-exclamation-circle" aria-hidden="true" fixed-width />
						<span>{{ ctrans("Delivery address not set") }}</span>
					</div>
				</div>
			</div>
		</BoxStatPallet>

		<!-- Second Block -->
		<BoxStatPallet class="p-4">
			<div class="flex justify-center items-center gap-2">
				<FontAwesomeIcon
					v-tooltip="ctrans('Stock Delivery')"
					icon="fal fa-people-arrows"
					class="text-gray-400"
					fixed-width
					aria-hidden="true"
				/>
				<span>{{ box_stats.second_block.state }}</span>
			</div>

			<hr class="my-1 border-t border-gray-300" />

			<!-- Todo: not sure in which states production/delivery time should appear, so far only known when the purchase order is cancelled, hidden for now -->
			<template v-if="false">
				<div class="space-y-1 text-sm">
					<div class="flex items-center justify-between gap-4">
						<span>{{ ctrans("Production time") }}</span>
						<span :class="box_stats.second_block.production_time ? '' : 'italic text-gray-400'">
							{{ box_stats.second_block.production_time ?? ctrans("Unknown") }}
						</span>
					</div>
					<div class="flex items-center justify-between gap-4">
						<span>{{ ctrans("Delivery time") }}</span>
						<span :class="box_stats.second_block.delivery_time ? '' : 'italic text-gray-400'">
							{{ box_stats.second_block.delivery_time ?? ctrans("Unknown") }}
						</span>
					</div>
				</div>

				<hr class="my-1 border-t border-gray-300" />
			</template>

			<div class="flex justify-center gap-4">
				<div class="flex items-center gap-1">
					<FontAwesomeIcon v-tooltip="ctrans('Items')" icon="fas fa-bars" aria-hidden="true" fixed-width />
					<span>{{ box_stats.second_block.total_items }}</span>
				</div>

				<div class="flex items-center gap-1">
					<FontAwesomeIcon v-tooltip="ctrans('Received & checked items')" icon="fas fa-box-check" aria-hidden="true" fixed-width />
					<span>{{ box_stats.second_block.total_received_checked_items }}</span>
				</div>

				<div class="flex items-center gap-1">
					<FontAwesomeIcon v-tooltip="ctrans('Placed items')" icon="fas fa-inventory" aria-hidden="true" fixed-width />
					<span>{{ box_stats.second_block.total_placed_items }}</span>
				</div>

				<span v-if="box_stats.second_block.total_new_org_stocks > 0"
					v-tooltip="ctrans('Items that have never been in stock')"
					class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
					{{ ctrans(':count new', { count: box_stats.second_block.total_new_org_stocks }) }}
				</span>
			</div>

			<div class="mt-2 grid grid-cols-2 gap-2 text-sm">
				<div
					v-for="metric in metrics"
					:key="metric.key"
					class="flex items-center justify-center gap-1"
					:class="metric.isUnknown ? 'italic text-red-500' : ''"
				>
					<FontAwesomeIcon
						v-if="metric.showMark"
						v-tooltip="metric.tooltip"
						icon="fas fa-exclamation-circle"
						:class="metric.isUnknown ? 'text-red-500' : 'text-orange-500'"
						aria-hidden="true"
						fixed-width
					/>
					<span>{{ metric.text }}</span>
				</div>
			</div>

			<div v-if="box_stats.second_block.show_delivery_discrepancy" class="mt-2 flex justify-center gap-4">
				<div
					class="flex items-center gap-1"
					:class="box_stats.second_block.total_under_delivered_items ? 'text-orange-500' : ''"
				>
					<FontAwesomeIcon
						v-tooltip="ctrans('Items under delivered')"
						icon="fal fa-box-open"
						aria-hidden="true"
						fixed-width
					/>
					<span>{{ box_stats.second_block.total_under_delivered_items }}</span>
				</div>

				<div
					class="flex items-center gap-1"
					:class="box_stats.second_block.total_over_delivered_items ? 'text-orange-500' : ''"
				>
					<FontAwesomeIcon
						v-tooltip="ctrans('Items over delivered')"
						icon="fas fa-box-full"
						aria-hidden="true"
						fixed-width
					/>
					<span>{{ box_stats.second_block.total_over_delivered_items }}</span>
				</div>
			</div>
		</BoxStatPallet>

		<!-- Third Block: costs -->
		<BoxStatPallet v-for="(block, blockIndex) in costBlocks" :key="block.key" class="p-4">
			<div class="flex items-center justify-center gap-2 text-center">
				{{ block.title }}
				<span v-if="block.badge" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700" v-tooltip="ctrans('No invoice was entered for this delivery; these figures are estimated from what the delivery recorded')">{{ block.badge }}</span>
			</div>

			<hr class="my-1 border-t border-gray-300" />

			<div class="mt-2 space-y-1 text-sm">
				<div
					v-for="row in block.rows"
					:key="row.label"
					class="flex items-center justify-between gap-4"
					:class="row.isTotal ? 'font-semibold text-gray-700' : ''"
				>
					<span>{{ row.label }}</span>
					<span class="text-right">
						{{ row.value }}
						<span v-if="row.sub" class="block text-xs font-normal text-gray-400">{{ row.sub }}</span>
					</span>
				</div>
			</div>
			<div v-if="blockIndex === 0 && box_stats.invoice_entry" class="mt-3 flex justify-end">
				<StockDeliveryInvoiceEntry
					:route="box_stats.invoice_entry.route"
					:isAgent="box_stats.invoice_entry.is_agent"
					:invoice="box_stats.invoice"
					:currencyCode="box_stats.invoice?.currency ?? box_stats.third_block.currency ?? ''"
				/>
			</div>
			<StockDeliveryAgentPayments
				v-if="blockIndex === 0 && box_stats.invoice?.agent"
				:agent="box_stats.invoice.agent"
				:currency="box_stats.invoice.currency"
				:hasCharges="box_stats.invoice.charges_list.length > 0"
			/>
			<StockDeliveryServiceInvoices v-if="service_invoices && blockIndex === costBlocks.length - 1" :data="service_invoices" />
			<div v-if="isCostingVisible && blockIndex === costBlocks.length - 1" class="mt-3 flex justify-end">
				<Button
					type="tertiary"
					size="xs"
					:label="ctrans('Costing')"
					:icon="costing.agent_invoice_missing || !costing.is_costed ? 'fal fa-exclamation-triangle' : 'fal fa-box-usd'"
					@click="() => (isCostingOpen = true)"
				/>
			</div>
		</BoxStatPallet>

		<BoxStatPallet v-for="n in (2 - costBlocks.length)" :key="`cost-empty-${n}`" class="p-4">
			<div v-if="isCostingVisible && !costBlocks.length && n === 1" class="mt-3 flex justify-end">
				<Button
					type="tertiary"
					size="xs"
					:label="ctrans('Costing')"
					:icon="costing.agent_invoice_missing || !costing.is_costed ? 'fal fa-exclamation-triangle' : 'fal fa-box-usd'"
					@click="() => (isCostingOpen = true)"
				/>
			</div>
		</BoxStatPallet>
	</div>

	<Dialog
		:visible="!!estimatedDeliveryDateAction"
		modal
		:header="ctrans('Estimated delivery date')"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
		@update:visible="(visible: boolean) => { if (!visible) estimatedDeliveryDateAction = null }"
	>
		<div class="flex flex-col gap-2">
			<label for="stock-delivery-estimated-delivery-date" class="font-medium text-gray-700">
				{{ ctrans("Estimated delivery date") }}
			</label>
			<DatePicker
				v-model="estimatedReceivingDate"
				inputId="stock-delivery-estimated-delivery-date"
				dateFormat="yy-mm-dd"
				showIcon
				showButtonBar
				fluid
			/>
		</div>

		<template #footer>
			<Button :label="ctrans('Cancel')" type="secondary" @click="estimatedDeliveryDateAction = null" />
			<Button :label="ctrans('Save')" type="save" :loading="estimatedDeliveryDateLoading" @click="saveEstimatedDeliveryDate" />
		</template>
	</Dialog>

	<Dialog v-model:visible="isCostingOpen" modal dismissableMask :header="ctrans('Costing')" :style="{ width: '64rem' }" :breakpoints="{ '1024px': '95vw' }">
		<StockDeliveryCostingChecklist
			:costing="costing"
			:canEdit="costing.can_edit"
			:canEditPayments="costing.can_edit_payments"
		/>
		<StockDeliveryInvoiceCosting
			v-if="!costing.is_partner"
			:invoices="invoice_costing"
			:canEdit="costing.can_edit"
		/>
	</Dialog>

	<Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
	<component
		:is="component"
		:key="currentTab"
		:data="props[currentTab]"
		:tab="currentTab"
		:costing="currentTab === 'items' ? costing : undefined"
		:detachRoute="attachmentRoutes.detachRoute"
		:storeRoute="currentTab === 'notes' ? note_store_route : undefined"
	/>

	<UploadAttachment
		v-model="isModalUploadOpen"
		scope="attachment"
		:title="{
			label: 'Upload your file',
			information: 'The list of column file: customer_reference, notes, stored_items',
		}"
		progressDescription="Adding Stock Delivery Attachments"
		:attachmentRoutes="attachmentRoutes"
		:options="attachmentScopes"
	/>

	<ConfirmDialog group="stock-delivery">
		<template #icon>
			<FontAwesomeIcon :icon="faExclamationTriangle" class="text-xl text-orange-500" fixed-width />
		</template>
	</ConfirmDialog>
</template>
