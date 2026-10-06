<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue"
import type { Component } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import ProcurementOrderData from "@/Components/Procurement/ProcurementOrderData.vue"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import TablePurchaseOrderTransactions from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrderTransactions.vue"
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import TableDispatchedEmailsInOrder from "@/Pages/Grp/Org/Ordering/TableDispatchedEmailsInOrder.vue"
import ModalProductList from "@/Components/Utils/ModalProductList.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Checkbox from "primevue/checkbox"
import RadioButton from "primevue/radiobutton"
import ConfirmDialog from "primevue/confirmdialog"
import DatePicker from "primevue/datepicker"
import Dialog from "primevue/dialog"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"

import { useLocaleStore } from "@/Stores/locale"
import { useTabChange } from "@/Composables/tab-change"
import type { OrderingLevel } from "@/Composables/useOrderingLevel"
import { capitalize } from "@/Composables/capitalize"

import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Timeline as TSTimeline } from "@/types/Timeline"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faIdCardAlt, faEnvelope, faPhone, faWeight, faCube, faShoppingCart, faStickyNote, faShip, faBox, faHandHoldingBox, faPaperPlane, faExclamationTriangle, faClipboardList, faPeopleArrows, faCalendarAlt, faDownload } from "@fal"
import { faArrowCircleDown, faArrowCircleLeft, faArrowCircleRight, faBars, faExclamationCircle, faInventory, faPencil, faShare, faTruck } from "@fas"
import { faPlus } from "@far"

library.add(
	faDownload,
	faIdCardAlt,
	faEnvelope,
	faPhone,
	faWeight, faCube, faShoppingCart,
	faStickyNote,
	faShip,
	faBox,
	faHandHoldingBox,
	faPaperPlane,
	faExclamationTriangle,
    faShare,
    faArrowCircleDown,
	faArrowCircleRight,
	faArrowCircleLeft,
	faExclamationCircle,
	faPencil,
    faPlus,
    faInventory,
    faBars,
	faTruck,
	faClipboardList,
	faPeopleArrows,
	faCalendarAlt,
)

const props = defineProps < {
    title: string
    pageHead: PageHeadingTypes
    data: {
        data: {
            state: string
            state_label: string
            is_partner?: boolean
        }
    }
   	timelines: {
		[key: string]: TSTimeline
	}
	stock_delivery_timelines: {
		reference: string
		state: string
		state_icon: any
		timeline: {
			[key: string]: TSTimeline
		}
		route: routeType
	}[]
    delivery_items: {
        id: number
        code: string | null
        name: string | null
        quantity_ordered: number | string
    }[]
    tabs: {
        current: string
        navigation: {}
    }
	routes: {
		updatePurchaseOrderRoute: routeType
		products_list: routeType
    }
	box_stats: {
		first_block: {
			orderer: {
                slug: string
				type: string
				name: string
            }
            delivery: {
    			type: string | null
    			incoterm: string | null
    			port_of_export: string | null
    			port_of_import: string | null
    			delivery_address: string | null
    			is_own_warehouse?: boolean
    		}
    		seller_order?: { reference: string; url: string | null } | null
        }
        second_block: {
            state: string
            delivery_state: {
                tooltip: string
                icon: string
                class: string
                color: string
            }
            total_items: number
            total_delivery_items: number | null
            total_placed_items: number | null
            is_delivery_items_active: boolean
            is_placed_items_active: boolean
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
	items?: {}
	products?: {}
	showcase?: { blueprint: any[]; updateRoute: routeType }
	notes?: {}
	note_store_route?: routeType
	attachments?: {}
	attachmentRoutes: { attachRoute: routeType; detachRoute: routeType }
	attachmentScopes: { name: string; code: string }[]
	history?: {}
    upload_excel?: {
        title: { label: string, information: string }
        progressDescription: string
        preview_template: { header: string[], rows: Record<string, string>[] }
        upload_spreadsheet: any
    } | null
}>()

const locale = useLocaleStore()

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

const orgPerOrder = computed(() => {
	const { items, org_items, org_exchange } = props.box_stats.third_block
	const poItems = Number(items)
	const orgItems = Number(org_items)

	if (poItems) {
		return orgItems / poItems
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
	const { currency, org_currency, total, org_items } = props.box_stats.third_block

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

	const organisationBlock = {
		key: "org",
		title: rateLabel,
		rows: [
			...costRows.value.map(row => ({ label: row.label, value: money(orgCurrency, orgAmount(row)) })),
			{ label: ctrans("Total"), value: money(orgCurrency, orgTotal), isTotal: true },
		],
	}

	return sameCurrency ? [supplierBlock] : [supplierBlock, organisationBlock]
})

const summaryGroups = computed(() => {
	const { items, extra, shipping, duties, tax, total, currency, org_currency, org_items } = props.box_stats.third_block
	const inOrgCurrency = org_currency && org_currency !== currency
	const orgMoney = (amount: number) => (inOrgCurrency ? locale.currencyFormat(org_currency, amount) : undefined)
	const rate = Number(orgPerOrder.value) || 0
	const net = (Number(items) || 0) + (Number(extra) || 0) + (Number(shipping) || 0) + (Number(duties) || 0)

	return [
		[{ label: ctrans("Items"), quantity: props.box_stats.second_block.total_items, price_total: Number(items) || 0, information: orgMoney(Number(org_items) || 0) }],
		[
			{ label: ctrans("Extra costs"), price_total: Number(extra) || 0 },
			{ label: ctrans("Shipping"), price_total: Number(shipping) || 0 },
			{ label: ctrans("Duties"), price_total: Number(duties) || 0 },
		],
		[
			{ label: ctrans("Net"), price_total: net },
			{ label: ctrans("Tax"), price_total: Number(tax) || 0 },
		],
		[{ label: ctrans("Total"), price_total: Number(total) || 0, information: inOrgCurrency ? `${orgMoney(Number(total) * rate)} · ${moneyTable.value.rateLabel}` : undefined }],
	]
})

const moneyTable = computed(() => {
	const [supplierBlock, organisationBlock] = costBlocks.value

	return {
		title: supplierBlock.title,
		rateLabel: organisationBlock?.title ?? null,
		rows: supplierBlock.rows.map((row, index) => ({
			label: row.label,
			supplier: row.value,
			org: organisationBlock?.rows[index]?.value ?? null,
			isTotal: row.isTotal ?? false,
		})),
	}
})

const currentTab = ref(props.tabs.current)
const isModalUploadExcel = ref(false)

const currentLevel = ref<OrderingLevel>(props.data.data.is_partner ? "skos" : "cartons")

const isOrderingLevelTab = computed(() => ["items", "products"].includes(currentTab.value))

const isModalProductListOpen = ref(false)
const currentAction = ref<any>(null)

const openProductListModal = (action: any) => {
	currentAction.value = action
	isModalProductListOpen.value = true
}

watch(isModalProductListOpen, (isOpen, wasOpen) => {
	if (wasOpen && !isOpen) {
		router.reload({ only: [currentTab.value, "items", "products", "box_stats", "pageHead"] })
	}
})

const confirm = useConfirm()
const deleteLoading = ref(false)
const submitLoading = ref(false)
const cancelLoading = ref(false)
const undoSubmitLoading = ref(false)
const confirmLoading = ref(false)
const undoConfirmLoading = ref(false)
const newStockDeliveryLoading = ref(false)
const estimatedReceivingDate = ref<Date | null>(null)
const estimatedDeliveryDateModalOpen = ref(false)
const deliveryScopeModalOpen = ref(false)
const deliveryItemsModalOpen = ref(false)
const estimatedDeliveryDateAction = ref<any>(null)
const newStockDeliveryAction = ref<any>(null)
let partnerOrderPoll: ReturnType<typeof setInterval> | null = null
const stopPartnerOrderPoll = () => {
	if (partnerOrderPoll) {
		clearInterval(partnerOrderPoll)
		partnerOrderPoll = null
	}
}
watch(
	() => props.data.data.is_partner && props.data.data.state === "submitted",
	(isWaitingForPartnerOrder) => {
		stopPartnerOrderPoll()
		if (!isWaitingForPartnerOrder) {
			return
		}
		let attempts = 0
		partnerOrderPoll = setInterval(() => {
			if (++attempts > 30) {
				stopPartnerOrderPoll()
				return
			}
			router.reload()
		}, 4000)
	},
	{ immediate: true }
)
onUnmounted(stopPartnerOrderPoll)

const selectedDeliveryItemIds = ref<number[]>([])

const formatDate = (date: Date | null): string | null => {
	if (!date) {
		return null
	}

	const year = date.getFullYear()
	const month = String(date.getMonth() + 1).padStart(2, "0")
	const day = String(date.getDate()).padStart(2, "0")

	return `${year}-${month}-${day}`
}

const submitDialogAction = ref<any>(null)
const doNotSend = "none"
const sendVia = ref<string>(doNotSend)

const submitPurchaseOrder = (action: any) => {
	if (action.send_channels?.length && !submitDialogAction.value) {
		sendVia.value = action.send_channels[0].channel
		submitDialogAction.value = action
		return
	}

	router.patch(route(action.route.name, action.route.parameters), { send_via: sendVia.value === doNotSend ? null : sendVia.value }, {
		onStart: () => { submitLoading.value = true },
		onFinish: () => {
			submitLoading.value = false
			submitDialogAction.value = null
			sendVia.value = doNotSend
		},
		onError: () => {
			notify({
				title: ctrans("Something went wrong"),
				text: ctrans("Failed to submit purchase order"),
				type: "error",
			})
		},
	})
}

const confirmDeletePurchaseOrder = (action: any) => {
	confirm.require({
		group: "purchase-order",
		message: ctrans("Are you sure you want to delete this purchase order? This action cannot be undone."),
		header: ctrans("Delete Purchase Order"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Delete"), severity: "danger" },
		accept: () => {
			router.delete(route(action.route.name, action.route.parameters), {
				onStart: () => { deleteLoading.value = true },
				onFinish: () => { deleteLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to delete purchase order"),
						type: "error",
					})
				},
			})
		},
	})
}

const cancelDialogAction = ref<any>(null)
const cancelConfirmationText = ref("")
const isCancelConfirmed = computed(() => cancelConfirmationText.value.trim().toLowerCase() === "yes")

const confirmCancelPurchaseOrder = (action: any) => {
	cancelConfirmationText.value = ""
	cancelDialogAction.value = action
}

const cancelPurchaseOrder = () => {
	const action = cancelDialogAction.value
	router.patch(route(action.route.name, action.route.parameters), { counterparty_informed: "yes" }, {
		onStart: () => { cancelLoading.value = true },
		onFinish: () => { cancelLoading.value = false },
		onSuccess: () => { cancelDialogAction.value = null },
		onError: () => {
			notify({
				title: ctrans("Something went wrong"),
				text: ctrans("Failed to cancel purchase order"),
				type: "error",
			})
		},
	})
}

const confirmUndoSubmitPurchaseOrder = (action: any) => {
	confirm.require({
		group: "purchase-order",
		message: ctrans("Are you sure you want to undo the submission? This purchase order will go back to in process."),
		header: ctrans("Undo Submit Purchase Order"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Undo submit"), severity: "danger" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { undoSubmitLoading.value = true },
				onFinish: () => { undoSubmitLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to undo submit purchase order"),
						type: "error",
					})
				},
			})
		},
	})
}

const confirmConfirmPurchaseOrder = (action: any) => {
	estimatedReceivingDate.value = action.estimated_receiving_date
		? new Date(`${action.estimated_receiving_date}T00:00:00`)
		: null

	confirm.require({
		group: "purchase-order-confirm",
		message: ctrans("Are you sure the supplier confirmed they will fulfil this purchase order?"),
		header: ctrans("Confirm Purchase Order"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Confirm") },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {
				estimated_receiving_date: formatDate(estimatedReceivingDate.value),
			}, {
				onStart: () => { confirmLoading.value = true },
				onFinish: () => { confirmLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to confirm purchase order"),
						type: "error",
					})
				},
			})
		},
	})
}

const openEstimatedDeliveryDateModal = (action: any) => {
	estimatedDeliveryDateAction.value = action
	estimatedReceivingDate.value = action.estimated_receiving_date
		? new Date(`${action.estimated_receiving_date}T00:00:00`)
		: null
	estimatedDeliveryDateModalOpen.value = true
}

const saveEstimatedDeliveryDate = () => {
	const action = estimatedDeliveryDateAction.value

	if (!action) {
		return
	}

	router.patch(route(action.route.name, action.route.parameters), {
		estimated_receiving_date: formatDate(estimatedReceivingDate.value),
	}, {
		onStart: () => { confirmLoading.value = true },
		onSuccess: () => {
			estimatedDeliveryDateModalOpen.value = false
			router.reload({ only: ["showcase", "pageHead", "timelines"] })
		},
		onFinish: () => { confirmLoading.value = false },
		onError: () => {
			notify({
				title: ctrans("Something went wrong"),
				text: ctrans("Failed to update estimated delivery date"),
				type: "error",
			})
		},
	})
}

const confirmUndoConfirmPurchaseOrder = (action: any) => {
	confirm.require({
		group: "purchase-order",
		message: ctrans("Are you sure you want to undo the confirmation? This purchase order will go back to submitted."),
		header: ctrans("Undo Confirm Purchase Order"),
		rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
		acceptProps: { label: ctrans("Undo confirm"), severity: "danger" },
		accept: () => {
			router.patch(route(action.route.name, action.route.parameters), {}, {
				onStart: () => { undoConfirmLoading.value = true },
				onFinish: () => { undoConfirmLoading.value = false },
				onError: () => {
					notify({
						title: ctrans("Something went wrong"),
						text: ctrans("Failed to undo confirm purchase order"),
						type: "error",
					})
				},
			})
		},
	})
}

const openDeliveryScopeModal = (action: any) => {
	newStockDeliveryAction.value = action
	selectedDeliveryItemIds.value = []
	deliveryScopeModalOpen.value = true
}

const openDeliveryItemsModal = () => {
	deliveryScopeModalOpen.value = false
	deliveryItemsModalOpen.value = true
}

const createStockDelivery = (purchaseOrderTransactionIds: number[]) => {
	const action = newStockDeliveryAction.value

	if (!action || purchaseOrderTransactionIds.length === 0) {
		return
	}

	router.post(route(action.route.name, action.route.parameters), {
		purchase_order_transaction_ids: purchaseOrderTransactionIds,
	}, {
		onStart: () => { newStockDeliveryLoading.value = true },
		onSuccess: () => {
			deliveryScopeModalOpen.value = false
			deliveryItemsModalOpen.value = false
		},
		onFinish: () => { newStockDeliveryLoading.value = false },
		onError: () => {
			notify({
				title: ctrans("Something went wrong"),
				text: ctrans("Failed to create delivery"),
				type: "error",
			})
		},
	})
}

function openSupplierEmail(action: { mailto?: string, pdfUrl: string }) {
	window.open(action.pdfUrl, '_blank')
	if (action.mailto) {
		window.location.href = action.mailto
	}
}

const isModalUploadAttachmentOpen = ref(false)

const component = computed(() => {
	const components: Component = {
		items: TablePurchaseOrderTransactions,
		products: TablePurchaseOrderTransactions,
		notes: TableProcurementNotes,
		attachments: TableAttachments,
		dispatched_emails: TableDispatchedEmailsInOrder,
		history: TableHistories,
	}

	return components[currentTab.value]
})

const hasMiddleBox = computed(() =>
	!!props.stock_delivery_timelines.length
	|| props.data.data.state === "cancelled"
	|| !!props.box_stats.second_block.is_delivery_items_active
	|| !!props.box_stats.second_block.is_placed_items_active
)

const isOrgAgent = computed(() => props.box_stats.first_block.orderer.type === "Agent")

const ordererRoute = computed<string>(() => {
	const orderer = props.box_stats.first_block.orderer
    const slug = orderer.slug
    const type = orderer.type

	if (!slug || !type) return ""

	const organisation = route().params["organisation"]

	switch (type) {
		case "Agent":
			return route("grp.org.procurement.org_agents.show", [organisation, slug])
		case "Supplier":
			return route("grp.org.procurement.org_suppliers.show", [organisation, slug])
		default:
			return ""
	}
})

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button v-if="currentTab === 'attachments'" :label="ctrans('Attach')" icon="upload" @click="() => (isModalUploadAttachmentOpen = true)" />
		</template>

		<template #button-email-to-supplier="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:disabled="!action.mailto"
				@click="() => openSupplierEmail(action)"
			/>
		</template>

		<template #button-add-product="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				@click="() => openProductListModal(action)"
			/>
		</template>

		<template #button-upload-products="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				@click="() => isModalUploadExcel = true"
			/>
		</template>

		<template #button-submit-purchase-order="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="submitLoading"
				@click="() => submitPurchaseOrder(action)"
			/>
		</template>

		<template #button-delete-purchase-order="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="deleteLoading"
				@click="() => confirmDeletePurchaseOrder(action)"
			/>
		</template>

		<template #button-confirm-purchase-order="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="confirmLoading"
				@click="() => confirmConfirmPurchaseOrder(action)"
			/>
		</template>

		<template #button-undo-submit-purchase-order="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="undoSubmitLoading"
				@click="() => confirmUndoSubmitPurchaseOrder(action)"
			/>
		</template>

		<template #button-new-stock-delivery="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="newStockDeliveryLoading"
				@click="() => openDeliveryScopeModal(action)"
			/>
		</template>

		<template #button-edit-estimated-delivery-date="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="confirmLoading"
				@click="() => openEstimatedDeliveryDateModal(action)"
			/>
		</template>

		<template #button-undo-confirm-purchase-order="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="undoConfirmLoading"
				@click="() => confirmUndoConfirmPurchaseOrder(action)"
			/>
		</template>

		<template #button-cancel-purchase-order="{ action }">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				:loading="cancelLoading"
				@click="() => confirmCancelPurchaseOrder(action)"
			/>
		</template>
	</PageHeading>

	<!-- Purchase Order Timeline -->
	<div v-if="timelines" class="py-2 border-b border-gray-300">
		<Timeline
			:options="timelines"
			:state="props.data.data.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>

	<div
		v-for="stockDelivery in stock_delivery_timelines"
		:key="stockDelivery.reference"
		class="flex items-center gap-x-4 pl-4 py-1 border-b border-gray-200"
	>
		<Link
			:href="route(stockDelivery.route.name, stockDelivery.route.parameters)"
			class="primaryLink flex items-center gap-x-2 text-sm whitespace-nowrap"
		>
			<FontAwesomeIcon icon="fal fa-truck" fixed-width aria-hidden="true" />
			{{ stockDelivery.reference }}
		</Link>
		<Timeline
			class="flex-1 min-w-0"
			:options="stockDelivery.timeline"
			:state="stockDelivery.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy'"
		/>
	</div>

	<div class="grid grid-cols-2 text-gray-500 divide-x divide-gray-300 border-b border-gray-300" :class="['lg:grid-cols-2', 'lg:grid-cols-3', 'lg:grid-cols-4'][Number(hasMiddleBox) + Number(!!stock_delivery_timelines.length)]">
	    <!-- First Block -->
		<BoxStatPallet class="p-4">
			<div class="flex flex-col gap-2">
				<h3 class="text-lg font-semibold text-gray-700">
					{{ ctrans("Order") }}
					<span v-if="box_stats.first_block.orderer.type" class="text-base font-normal text-gray-400">({{ ctrans(box_stats.first_block.orderer.type) }})</span>
				</h3>
				<div v-if="box_stats.first_block.orderer.name" class="flex items-center gap-3 text-sm">
					<FontAwesomeIcon icon="fal fa-hand-holding-box" class="text-gray-400" aria-hidden="true" fixed-width />
					<Link v-if="ordererRoute" :href="ordererRoute" class="primaryLink">
						{{ box_stats.first_block.orderer.name }}
					</Link>
					<span v-else class="text-gray-700">{{ box_stats.first_block.orderer.name }}</span>
				</div>
				<div v-if="box_stats.first_block.seller_order" class="flex items-center gap-3 text-sm">
					<FontAwesomeIcon v-tooltip="ctrans('Their order')" icon="fal fa-shopping-cart" class="text-gray-400" aria-hidden="true" fixed-width />
					<Link v-if="box_stats.first_block.seller_order.url" :href="box_stats.first_block.seller_order.url" class="primaryLink">
						{{ box_stats.first_block.seller_order.reference }}
					</Link>
					<span v-else class="text-gray-700">{{ box_stats.first_block.seller_order.reference }}</span>
				</div>
				<div v-else-if="data.data.is_partner" class="flex items-center gap-3 text-sm text-gray-400">
					<FontAwesomeIcon icon="fal fa-shopping-cart" aria-hidden="true" fixed-width />
					<span class="italic">{{ ctrans("Their order is created when you submit") }}</span>
				</div>
				<ProcurementOrderData v-if="showcase" :data="showcase" bare :isOwnWarehouse="box_stats.first_block.delivery.is_own_warehouse" />
			</div>
		</BoxStatPallet>

		<!-- Second Block -->
		<BoxStatPallet v-if="hasMiddleBox" class="p-4">
            <div class="flex h-8 justify-center items-center gap-4">
                <div v-if="stock_delivery_timelines.length" class="flex items-center gap-2">
                    <FontAwesomeIcon
                        v-tooltip="ctrans('Stock Delivery')"
                        icon="fal fa-people-arrows"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true"
                    />
                    <span v-tooltip="box_stats.second_block.delivery_state.tooltip">
                        {{ box_stats.second_block.delivery_state.tooltip }}
                    </span>
                </div>
            </div>

            <hr class="-mx-4 mb-1 border-t border-gray-300" />

            <template v-if="data.data.state === 'cancelled'">
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

            <!-- Todo: Create Purchase Order Export as PDF -->

            <div v-if="box_stats.second_block.is_delivery_items_active || box_stats.second_block.is_placed_items_active" class="flex justify-center gap-4">
                <div
                    class="flex items-center gap-1"
                    :class="box_stats.second_block.is_delivery_items_active ? '' : 'text-gray-300'"
                >
                    <FontAwesomeIcon
                        v-tooltip="ctrans('Delivery items')"
        				icon="fas fa-arrow-circle-down"
        				aria-hidden="true"
        				fixed-width
    				/>
                    <span>{{ box_stats.second_block.total_delivery_items ?? '-' }}</span>
                </div>

                <div
                    class="flex items-center gap-1"
                    :class="box_stats.second_block.is_placed_items_active ? '' : 'text-gray-300'"
                >
                    <FontAwesomeIcon
                        v-tooltip="ctrans('Placed items')"
        				icon="fas fa-inventory"
        				aria-hidden="true"
        				fixed-width
    				/>
                    <span>{{ box_stats.second_block.total_placed_items ?? '-' }}</span>
                </div>
            </div>

		</BoxStatPallet>

		<!-- Third Block: stock deliveries -->
		<BoxStatPallet v-if="stock_delivery_timelines.length" class="p-4">
			<div class="flex h-8 items-center justify-center text-center">
				{{ ctrans("Stock Deliveries") }}
			</div>

			<hr class="-mx-4 mb-1 border-t border-gray-300" />

			<div v-if="stock_delivery_timelines.length" class="mt-2 space-y-1 text-sm">
				<div
					v-for="stockDelivery in stock_delivery_timelines"
					:key="stockDelivery.reference"
					class="flex items-center gap-2"
				>
					<FontAwesomeIcon icon="fal fa-truck" class="text-gray-400" fixed-width aria-hidden="true" />
					<Link :href="route(stockDelivery.route.name, stockDelivery.route.parameters)" class="primaryLink">
						{{ stockDelivery.reference }}
					</Link>
					<Icon :data="stockDelivery.state_icon" />
				</div>
			</div>
			<div v-else class="mt-2 text-center text-sm italic text-gray-400">
				{{ ctrans("No stock deliveries") }}
			</div>
		</BoxStatPallet>

		<!-- Fourth Block: money -->
		<BoxStatPallet class="min-w-0 pb-4">
			<div class="text-xs md:text-sm">
				<div class="flex items-center justify-between px-3 pt-2">
					<div class="text-base font-semibold">{{ ctrans("Summary") }}</div>
					<div class="text-xs text-gray-400">{{ moneyTable.title }}</div>
				</div>
				<section class="rounded-lg px-4 py-2">
					<div class="mb-2 flex items-center gap-x-4 border-b border-gray-300 pb-2 text-gray-500">
						<span v-for="metric in metrics" :key="metric.key" class="flex items-center gap-x-1.5 whitespace-nowrap" :class="metric.isUnknown ? 'italic text-gray-400' : ''">
							<FontAwesomeIcon :icon="metric.key === 'weight' ? 'fal fa-weight' : 'fal fa-cube'" fixed-width aria-hidden="true" />
							{{ metric.text }}
							<FontAwesomeIcon
								v-if="metric.showMark"
								v-tooltip="metric.tooltip"
								icon="fas fa-exclamation-circle"
								:class="metric.isUnknown ? 'text-red-500' : 'text-orange-500'"
								fixed-width
								aria-hidden="true" />
						</span>
					</div>
					<OrderSummary :order_summary="summaryGroups" :currency_code="box_stats.third_block.currency ?? ''" />
				</section>
			</div>
		</BoxStatPallet>
	</div>

	<Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

	<div class="pb-12">
		<component
			:is="component"
			:key="currentTab"
			:data="props[currentTab as keyof typeof props]"
			:tab="currentTab"
			:state="data.data.state"
			:isOrgAgent="isOrgAgent"
			:orgAgentSlug="box_stats.first_block.orderer.slug"
			:updateRoute="routes.updateOrderRoute"
			:storeRoute="currentTab === 'notes' ? note_store_route : undefined"
			:detachRoute="attachmentRoutes.detachRoute"
			v-bind="isOrderingLevelTab ? {
				level: currentLevel,
				isPartner: data.data.is_partner,
				'onUpdate:level': (value: OrderingLevel) => currentLevel = value,
			} : {}"
			@update:tab="handleTabUpdate"
		/>
	</div>

	<UploadAttachment
		v-model="isModalUploadAttachmentOpen"
		scope="attachment"
		:title="{ label: ctrans('Upload your file'), information: '' }"
		:progressDescription="ctrans('Adding purchase order attachments')"
		:attachmentRoutes="attachmentRoutes"
		:options="attachmentScopes"
	/>

	<ModalProductList
		v-if="routes.products_list?.name"
		v-model="isModalProductListOpen"
		:fetchRoute="routes.products_list"
		:action="currentAction"
		:current="currentTab"
		v-model:currentTab="currentTab"
		:typeModel="'purchase_order'"
		:isPartner="data.data.is_partner"
		v-model:level="currentLevel"
	/>

	<ConfirmDialog group="purchase-order">
		<template #icon>
			<FontAwesomeIcon :icon="faExclamationTriangle" class="text-xl text-orange-500" fixed-width />
		</template>
	</ConfirmDialog>

	<ConfirmDialog group="purchase-order-confirm">
		<template #message="{ message }">
			<div class="flex w-full flex-col gap-4">
				<div class="flex items-start gap-3">
					<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-xl text-orange-500" fixed-width />
					<span>{{ message.message }}</span>
				</div>

				<div class="flex flex-col gap-2">
					<label for="purchase-order-estimated-delivery-date" class="font-medium text-gray-700">
						{{ ctrans("Estimated delivery date") }}
					</label>
					<DatePicker
						v-model="estimatedReceivingDate"
						inputId="purchase-order-estimated-delivery-date"
						dateFormat="yy-mm-dd"
						:minDate="new Date()"
						showIcon
						showButtonBar
						fluid
					/>
				</div>
			</div>
		</template>
	</ConfirmDialog>

	<Dialog
		:visible="!!cancelDialogAction"
		modal
		:header="ctrans('Cancel purchase order')"
		:style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
		@update:visible="(visible) => { if (!visible) cancelDialogAction = null }"
	>
		<div class="flex flex-col gap-4">
			<div class="flex items-start gap-3 rounded-md border-2 border-red-500 bg-red-50 p-4 text-red-800">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-1 text-3xl text-red-600" fixed-width />
				<div class="flex flex-col gap-2">
					<p class="text-lg font-bold uppercase">{{ ctrans("Cancelling here does not tell the supplier") }}</p>
					<p class="text-sm">{{ ctrans("It is your responsibility to inform the supplier, agent or partner that you no longer want this order. If they are not told, they may still produce and send it.") }}</p>
				</div>
			</div>
			<p class="text-sm text-gray-700">{{ ctrans("All item amounts will be set to zero. This cannot be undone.") }}</p>
			<label for="purchase-order-cancel-confirmation" class="text-sm font-medium text-gray-700">
				{{ ctrans("Have you already informed them? Type yes to cancel this order.") }}
			</label>
			<input
				id="purchase-order-cancel-confirmation"
				v-model="cancelConfirmationText"
				type="text"
				autocomplete="off"
				class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:ring-red-500"
				@keyup.enter="isCancelConfirmed && cancelPurchaseOrder()"
			/>
		</div>

		<template #footer>
			<Button :label="ctrans('Keep')" type="secondary" @click="cancelDialogAction = null" />
			<Button
				:label="ctrans('Cancel order')"
				type="delete"
				:disabled="!isCancelConfirmed"
				:loading="cancelLoading"
				@click="cancelPurchaseOrder"
			/>
		</template>
	</Dialog>

	<Dialog
		:visible="!!submitDialogAction"
		modal
		:header="ctrans('Submit purchase order')"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
		@update:visible="(visible) => { if (!visible) submitDialogAction = null }"
	>
		<div class="flex flex-col gap-3">
			<label v-for="option in submitDialogAction?.send_channels" :key="option.channel" class="flex cursor-pointer items-start gap-3">
				<RadioButton v-model="sendVia" :value="option.channel" :inputId="`purchase-order-send-${option.channel}`" />
				<span class="text-sm text-gray-700">
					{{ option.channel === "email" ? ctrans("Email the purchase order PDF to") : ctrans("Send the purchase order PDF by WhatsApp to") }}
					<span class="font-medium">{{ option.to }}</span>
					<span v-if="option.channel === 'email'" class="mt-1 block text-xs text-gray-500">{{ ctrans("Delivery, opens and clicks are tracked in the Emails sent tab.") }}</span>
					<span v-else class="mt-1 block text-xs text-gray-500">{{ ctrans("Delivered and read receipts show in the supplier's inbox.") }}</span>
				</span>
			</label>
			<label class="flex cursor-pointer items-start gap-3">
				<RadioButton v-model="sendVia" :value="doNotSend" inputId="purchase-order-send-none" />
				<span class="text-sm text-gray-700">{{ ctrans("Don't send, I will send it myself") }}</span>
			</label>
			<p class="text-xs text-gray-500">{{ ctrans("Replies arrive in the procurement inbox.") }}</p>
		</div>

		<template #footer>
			<Button :label="ctrans('Cancel')" type="secondary" @click="submitDialogAction = null" />
			<Button
				:label="sendVia !== doNotSend ? ctrans('Submit and send email') : ctrans('Submit')"
				type="save"
				:icon="faPaperPlane"
				:loading="submitLoading"
				@click="submitPurchaseOrder(submitDialogAction)"
			/>
		</template>
	</Dialog>

	<Dialog
		v-model:visible="estimatedDeliveryDateModalOpen"
		modal
		:header="ctrans('Estimated delivery date')"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="flex flex-col gap-2">
			<label for="purchase-order-edit-estimated-delivery-date" class="font-medium text-gray-700">
				{{ ctrans("Estimated delivery date") }}
			</label>
			<DatePicker
				v-model="estimatedReceivingDate"
				inputId="purchase-order-edit-estimated-delivery-date"
				dateFormat="yy-mm-dd"
				:minDate="new Date()"
				showIcon
				showButtonBar
				fluid
			/>
		</div>

		<template #footer>
			<Button
				:label="ctrans('Cancel')"
				type="secondary"
				@click="estimatedDeliveryDateModalOpen = false"
			/>
			<Button
				:label="ctrans('Save')"
				type="save"
				:loading="confirmLoading"
				@click="saveEstimatedDeliveryDate"
			/>
		</template>
	</Dialog>

	<Dialog
		v-model:visible="deliveryScopeModalOpen"
		modal
		:header="ctrans('Create delivery')"
		:style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="flex flex-col gap-4">
			<p class="text-gray-600">{{ ctrans("Which purchase order items should be included in this delivery?") }}</p>

			<button
				type="button"
				class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-indigo-400 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
				:disabled="delivery_items.length === 0 || newStockDeliveryLoading"
				@click="createStockDelivery(delivery_items.map(item => item.id))"
			>
				<FontAwesomeIcon icon="fal fa-box" class="mt-0.5 text-indigo-500" fixed-width aria-hidden="true" />
				<span class="flex flex-col gap-1">
					<span class="font-medium text-gray-800">{{ ctrans("All items") }}</span>
					<span class="text-sm text-gray-500">{{ ctrans("Include every item in this purchase order") }}</span>
				</span>
			</button>

			<button
				type="button"
				class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-indigo-400 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
				:disabled="delivery_items.length === 0 || newStockDeliveryLoading"
				@click="openDeliveryItemsModal"
			>
				<FontAwesomeIcon icon="fal fa-clipboard-list" class="mt-0.5 text-indigo-500" fixed-width aria-hidden="true" />
				<span class="flex flex-col gap-1">
					<span class="font-medium text-gray-800">{{ ctrans("Only selected items") }}</span>
					<span class="text-sm text-gray-500">{{ ctrans("Choose the items to include in this delivery") }}</span>
				</span>
			</button>
		</div>
	</Dialog>

	<Dialog
		v-model:visible="deliveryItemsModalOpen"
		modal
		:header="ctrans('Select delivery items')"
		:style="{ width: '44rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="flex max-h-[60vh] flex-col divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200">
			<label
				v-for="item in delivery_items"
				:key="item.id"
				class="flex cursor-pointer items-center gap-3 p-3 hover:bg-gray-50"
			>
				<Checkbox v-model="selectedDeliveryItemIds" :value="item.id" />
				<span class="min-w-0 flex-1">
					<span class="block font-medium text-gray-800">{{ item.code || ctrans("No code") }}</span>
					<span class="block truncate text-sm text-gray-500">{{ item.name }}</span>
				</span>
				<span class="text-sm text-gray-500">
					{{ ctrans("Quantity") }}: {{ locale.number(Number(item.quantity_ordered)) }}
				</span>
			</label>
		</div>

		<template #footer>
			<Button
				:label="ctrans('Back')"
				type="secondary"
				@click="deliveryItemsModalOpen = false; deliveryScopeModalOpen = true"
			/>
			<Button
				:label="ctrans('Create delivery')"
				type="create"
				:loading="newStockDeliveryLoading"
				:disabled="selectedDeliveryItemIds.length === 0"
				@click="createStockDelivery(selectedDeliveryItemIds)"
			/>
		</template>
	</Dialog>

	<UploadExcel
		v-if="props.upload_excel"
		v-model="isModalUploadExcel"
		:title="props.upload_excel.title"
		:progressDescription="props.upload_excel.progressDescription"
		:upload_spreadsheet="props.upload_excel.upload_spreadsheet"
		:preview_template="props.upload_excel.preview_template"
		:propsRefreshAfterFinish="['items', 'box_stats']" />
</template>
