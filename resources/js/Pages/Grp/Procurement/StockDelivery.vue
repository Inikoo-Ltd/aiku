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
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import ProcurementOrderData from "@/Components/Procurement/ProcurementOrderData.vue"
import StockDeliveryCostingChecklist from "@/Components/Procurement/StockDeliveryCostingChecklist.vue"
import TableStockDeliveryItems from "@/Components/Tables/Grp/Org/Procurement/TableStockDeliveryItems.vue"
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
	purchase_order: {
		reference: string
		route: routeType
	} | null
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
		can_edit: boolean
		can_edit_payments: boolean
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
	notes?: {}
	note_store_route?: routeType
	history?: {}
}>()

const locale = useLocaleStore()

const currentTab = ref(props.tabs.current)
const isModalUploadOpen = ref(false)

const component = computed(() => {
	const components: Component = {
		items: TableStockDeliveryItems,
		pending_items: TableStockDeliveryItems,
		done_items: TableStockDeliveryItems,
		under_over_delivered: TableStockDeliveryItems,
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

	const orderPerOrg = rate ? 1 / rate : null
	const rateLabel = sameCurrency
		? `${ctrans("Organisation currency")} ${orgCurrency ?? ""}`.trim()
		: orderPerOrg === null
			? ""
			: `1 ${orgCurrency} = ${orderPerOrg.toLocaleString(locale.locale_iso ?? "en", { maximumFractionDigits: 5 })} ${currency ?? ""}`.trim()

	return [
		supplierBlock,
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
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to dispatch stock delivery"),
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
	<div v-if="timelines" class="flex items-center gap-x-4 py-2 border-b border-gray-300" :class="purchase_order ? 'pl-4' : ''">
		<Link
			v-if="purchase_order"
			:href="route(purchase_order.route.name, purchase_order.route.parameters)"
			class="primaryLink flex items-center gap-x-2 text-sm whitespace-nowrap"
		>
			<FontAwesomeIcon icon="fal fa-clipboard-list" fixed-width aria-hidden="true" />
			{{ purchase_order.reference }}
		</Link>
		<Timeline
			class="flex-1 min-w-0"
			:options="timelines"
			:state="stock_delivery.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>

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
		<BoxStatPallet v-for="block in costBlocks" :key="block.key" class="p-4">
			<div class="flex justify-center text-center">
				{{ block.title }}
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
					<span>{{ row.value }}</span>
				</div>
			</div>
		</BoxStatPallet>

		<BoxStatPallet v-for="n in (2 - costBlocks.length)" :key="`cost-empty-${n}`" class="p-4" />
	</div>

	<StockDeliveryCostingChecklist
		v-if="!['in_process', 'confirmed', 'ready_to_ship', 'cancelled', 'not_received'].includes(stock_delivery.state)"
		:costing="costing"
		:canEdit="costing.can_edit"
		:canEditPayments="costing.can_edit_payments"
	/>

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
	/>

	<ConfirmDialog group="stock-delivery">
		<template #icon>
			<FontAwesomeIcon :icon="faExclamationTriangle" class="text-xl text-orange-500" fixed-width />
		</template>
	</ConfirmDialog>
</template>
