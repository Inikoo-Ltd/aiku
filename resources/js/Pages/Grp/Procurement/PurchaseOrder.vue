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
import type { RelatedProductDevelopment } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRelatedList.vue"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import ProcurementOrderData from "@/Components/Procurement/ProcurementOrderData.vue"
import AgreementSummary from "@/Components/Procurement/AgreementSummary.vue"
import PackingSpecGaps from "@/Components/Procurement/PackingSpecGaps.vue"
import AgreedTermDifferences from "@/Components/Procurement/AgreedTermDifferences.vue"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import AgentOrderingItems from "@/Components/Procurement/AgentOrderingItems.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import qs from "qs"
import TablePurchaseOrderTransactions from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrderTransactions.vue"
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import TablePurchaseOrderComplianceDocuments from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrderComplianceDocuments.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import TableDispatchedEmailsInOrder from "@/Pages/Grp/Org/Ordering/TableDispatchedEmailsInOrder.vue"
import ModalProductList from "@/Components/Utils/ModalProductList.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Checkbox from "primevue/checkbox"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import RadioButton from "primevue/radiobutton"
import ConfirmDialog from "primevue/confirmdialog"
import DatePicker from "primevue/datepicker"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import StaffTaskDialog from "@/Components/Tasks/StaffTaskDialog.vue"
import AspoDepositsChecklist from "@/Components/Procurement/AspoDepositsChecklist.vue"
import PurchaseOrderPayments from "@/Components/Procurement/PurchaseOrderPayments.vue"
import PurchaseOrderDepositPayments, { type PurchaseOrderPaymentsData } from "@/Components/Procurement/PurchaseOrderDepositPayments.vue"
import AgentPurchaseOrderMoney, { type AgentPurchaseOrderMoneyData } from "@/Components/Procurement/AgentPurchaseOrderMoney.vue"
import PurchaseOrderStages from "@/Components/Procurement/PurchaseOrderStages.vue"
import AgentPurchaseOrderGoodsReceipts from "@/Components/Procurement/AgentPurchaseOrderGoodsReceipts.vue"
import AgentReceiveGoodsButton from "@/Components/Procurement/AgentReceiveGoodsButton.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import ButtonDownloadWithOptions from "@/Components/Utils/ButtonDownloadWithOptions.vue"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"

import { useLocaleStore } from "@/Stores/locale"
import { useTabChange } from "@/Composables/tab-change"
import { getOrderingLevels, type OrderingLevel } from "@/Composables/useOrderingLevel"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"

import { PageHeadingTypes } from "@/types/PageHeading"
import { Action } from "@/types/Action"
import { routeType } from "@/types/route"
import { Timeline as TSTimeline } from "@/types/Timeline"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faIdCardAlt, faEnvelope, faPhone, faWeight, faCube, faShoppingCart, faStickyNote, faShip, faBox, faHandHoldingBox, faPaperPlane, faExclamationTriangle, faClipboardList, faPeopleArrows, faCalendarAlt, faDownload, faSearch, faChevronDown, faChevronRight, faTimes, faBan, faThLarge, faList, faFileExcel, faCheckDouble, faBoxOpen, faClipboardCheck, faPlus as falPlus } from "@fal"
import { faArrowCircleDown, faArrowCircleLeft, faArrowCircleRight, faBars, faExclamationCircle, faInventory, faPencil, faShare, faTruck } from "@fas"
import { faPlus } from "@far"

library.add(
	faDownload, faChevronDown, faChevronRight, faBan, faCheckDouble, faBoxOpen, falPlus,
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

interface SubmitProblem {
    message: string
    org_stock_id: number | null
    fixes: { label: string, route: routeType }[]
    task: { subject: string, description: string } | null
}

const props = defineProps < {
	productDevelopments?: RelatedProductDevelopment[]
    title: string
    pageHead: PageHeadingTypes
    data: {
        data: {
            id: number
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
    open_agent_deliveries: {
        id: number
        reference: string
        date: string | null
        number_purchase_orders: number
    }[]
    stages?: { stages: InstanceType<typeof PurchaseOrderStages>["$props"]["stages"]; route: routeType; upload_route: routeType | null; deposit: InstanceType<typeof PurchaseOrderStages>["$props"]["deposit"] } | null
    agent_receiving?: InstanceType<typeof AgentPurchaseOrderGoodsReceipts>["$props"]["receiving"] | null
    compliance?: object
    compliance_summary?: InstanceType<typeof TablePurchaseOrderComplianceDocuments>["$props"]["compliance"]
    deposits?: object | null
    payments?: PurchaseOrderPaymentsData | null
    agent_money?: AgentPurchaseOrderMoneyData | null
    delivery_items: {
        id: number
        code: string | null
        name: string | null
        quantity_ordered: number | string
        image_thumbnail: object | null
        image_full: { original_url: string; download_url: string } | null
    }[]
    submit_problems?: SubmitProblem[]
	agreement: { terms: object[], notes: object[], open_incidents: number, must_acknowledge: boolean }
	agreed_term_differences?: InstanceType<typeof AgreedTermDifferences>["$props"]["differences"] | null
	packing_spec_gaps?: InstanceType<typeof PackingSpecGaps>["$props"]["gaps"] | null
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
				via_agent?: { name: string, slug: string }
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
    		clean_handover?: {
    			proposed_ready_at: string | null
    			approved_ready_at: string | null
    			compliance_complete_at: string | null
    			chs_excluded: boolean
    			chs_exclusion_reason: string | null
    		} | null
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
            estimated_expenses?: number
        }
	}
	items?: {}
	agent_ordering?: {
		orgAgent: { id: number, slug: string, currency: string }
		addRoute: { name: string, parameters: (string | number)[] }
		bucketLabels: Record<string, { label: string, tone: string }>
	} | null
	products?: {
		data: { id: number }[]
		links: { prev: string | null, next: string | null }
		ordering?: {
			currency: string
			cover: { value: string, label: string, title: string, tone: string, count: number }[]
			rank: { value: string, label: string, count: number }[]
			active: { cover: string | null, rank: string | null, hide_ordered: boolean }
		}
	}
	showcase?: { blueprint: any[]; updateRoute: routeType }
	notes?: {}
	note_store_route?: routeType
	note_form?: object
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
	last_edit: { user: string | null, at: string } | null
}>()

const locale = useLocaleStore()

const lastEdit = ref(props.last_edit)
const lastEditChannel = `grp.purchase_order.${props.data.data.id}`
onMounted(() => {
	window.Echo?.private(lastEditChannel).listen(".last-edited", (edit: { user: string | null, at: string }) => {
		if (!lastEdit.value || edit.at >= lastEdit.value.at) {
			lastEdit.value = edit
		}
	})
})
onUnmounted(() => window.Echo?.leave(lastEditChannel))

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

const showExtraCosts = ref(false)

const paymentsOrgRate = computed(() => {
	const { currency, org_currency } = props.box_stats.third_block

	return org_currency && org_currency !== currency ? Number(orgPerOrder.value) || null : null
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
			{ label: ctrans("Extra costs, shipping and duties"), price_total: (Number(extra) || 0) + (Number(shipping) || 0) + (Number(duties) || 0), slot_name: "extra_costs" },
			...(showExtraCosts.value
				? [
					{ label: ctrans("Extra costs"), price_total: Number(extra) || 0, label_class: "pl-5" },
					{ label: ctrans("Shipping"), price_total: Number(shipping) || 0, label_class: "pl-5" },
					{ label: ctrans("Duties"), price_total: Number(duties) || 0, label_class: "pl-5" },
				]
				: []),
		],
		[
			{ label: ctrans("Net"), price_total: net },
			{ label: ctrans("Tax"), price_total: Number(tax) || 0 },
		],
		[{ label: ctrans("Total"), price_total: Number(total) || 0, information: inOrgCurrency ? `${orgMoney(Number(total) * rate)} · ${moneyTable.value.rateLabel}` : undefined }],
		...(Number(props.box_stats.third_block.estimated_expenses) > 0
			? [[
				{
					label: ctrans("Supplier expenses"),
					price_total: Number(props.box_stats.third_block.estimated_expenses),
					information: ctrans("unit expense × quantity"),
				},
				{
					label: ctrans("Estimated total incl. supplier expenses"),
					price_total: (Number(total) || 0) + Number(props.box_stats.third_block.estimated_expenses),
					information: ctrans("estimate, for budgeting"),
				},
			]]
			: []),
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
const newDeliveryOption = "new"
const targetStockDelivery = ref<number | string>(newDeliveryOption)
const stockDeliveryOptions = computed(() => [
	{ id: newDeliveryOption, label: ctrans("New delivery") },
	...props.open_agent_deliveries.map(delivery => ({
		id: delivery.id,
		label: `${delivery.reference} (${ctrans(":count purchase orders", { count: delivery.number_purchase_orders })})`,
	})),
])
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
const deliveryItemsSearch = ref("")

const filteredDeliveryItems = computed(() => {
	const search = deliveryItemsSearch.value.trim().toLowerCase()

	if (!search) {
		return props.delivery_items
	}

	return props.delivery_items.filter((item) =>
		(item.code ?? "").toLowerCase().includes(search) || (item.name ?? "").toLowerCase().includes(search)
	)
})

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

const criticalNotesAcknowledged = ref(false)

const submitPurchaseOrder = (action: any) => {
	if (props.agreement.must_acknowledge && !criticalNotesAcknowledged.value) {
		notify({
			title: ctrans("Read the critical notes first"),
			text: ctrans("Tick that you have read them, above the lines, and submit again."),
			type: "warn",
		})
		return
	}

	if (props.agreed_term_differences?.flags.some((flag) => !flag.accepted)) {
		notify({
			title: ctrans("Different from what was agreed"),
			text: ctrans("Accept each difference with a reason, above the lines, and submit again."),
			type: "warn",
		})
		return
	}

	if (action.send_channels?.length && !submitDialogAction.value) {
		sendVia.value = action.send_channels[0].channel
		submitDialogAction.value = action
		return
	}

	router.patch(route(action.route.name, action.route.parameters), { send_via: sendVia.value === doNotSend ? null : sendVia.value, critical_notes_acknowledged: criticalNotesAcknowledged.value }, {
		onStart: () => { submitLoading.value = true },
		onFinish: () => {
			submitLoading.value = false
			submitDialogAction.value = null
			sendVia.value = doNotSend
		},
		onError: (errors) => {
			submitErrors.value = Object.values(errors).flat() as string[]
			submitProblemsOpen.value = true
			router.reload({ only: ["submit_problems"] })
		},
	})
}

const submitErrors = ref<string[]>([])
const submitProblemsOpen = ref(false)
const taskProblem = ref<SubmitProblem | null>(null)

const submitProblems = computed<SubmitProblem[]>(() => {
	const problems = props.submit_problems ?? []
	const explained = problems.map((problem) => problem.message)

	return [
		...problems,
		...submitErrors.value
			.filter((message) => !explained.includes(message))
			.map((message) => ({ message, org_stock_id: null, fixes: [], task: null })),
	]
})

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
		acceptProps: { label: ctrans("Yes, confirm"), class: "buttonPrimary" },
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
			router.reload({ only: ["showcase", "pageHead", "timelines", "stages"] })
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
	deliveryItemsSearch.value = ""
	targetStockDelivery.value = newDeliveryOption
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
		...(targetStockDelivery.value === newDeliveryOption ? {} : { stock_delivery_id: targetStockDelivery.value }),
	}, {
		onStart: () => { newStockDeliveryLoading.value = true },
		onSuccess: () => {
			deliveryScopeModalOpen.value = false
			deliveryItemsModalOpen.value = false
		},
		onFinish: () => { newStockDeliveryLoading.value = false },
		onError: (errors) => {
			notify({
				title: ctrans("Failed to create delivery"),
				text: Object.values(errors)[0] || ctrans("Something went wrong"),
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

const productsView = ref<"cards" | "list">("list")
const productsViewOptions = [
	{ label: ctrans("Cards"), value: "cards", icon: faThLarge },
	{ label: ctrans("List"), value: "list", icon: faList },
]

const productsLevelOptions = getOrderingLevels().map((level) => ({ label: level.quantity, value: level.key, icon: level.icon }))

const productBucketLabels = computed(() =>
	Object.fromEntries((props.products?.ordering?.cover ?? []).map((bucket) => [bucket.value, { label: bucket.title, tone: bucket.tone }]))
)

const filterProducts = (key: "cover" | "rank" | "hide_ordered", value: string | null) => {
	const query = qs.parse(location.search.substring(1))
	const filters = { ...((query.products_filter as Record<string, unknown>) ?? {}), [key]: value }
	delete query.productsPage

	router.get(
		location.pathname + "?" + qs.stringify({ ...query, tab: "products", products_filter: filters }, { encodeValuesOnly: true, skipNulls: true }),
		{},
		{ only: ["products"], preserveState: true, preserveScroll: true, replace: true }
	)
}

const component = computed(() => {
	const components: Component = {
		items: TablePurchaseOrderTransactions,
		products: TablePurchaseOrderTransactions,
		notes: TableProcurementNotes,
		compliance: TablePurchaseOrderComplianceDocuments,
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

const ordererRoute = computed<string>(() => {
	const orderer = props.box_stats.first_block.orderer
    const slug = orderer.slug
    const type = orderer.type

	if (!slug || !type) return ""

	const organisation = route().params["organisation"]

	switch (type) {
		case "Supplier":
			return route("grp.org.procurement.org_suppliers.show", [organisation, slug])
		default:
			return ""
	}
})

const agentRoute = computed<string>(() => {
	const viaAgent = props.box_stats.first_block.orderer.via_agent

	return viaAgent ? route("grp.org.procurement.org_agents.show", [route().params["organisation"], viaAgent.slug]) : ""
})

const pageHeadButtonGroups: Record<string, string[]> = {
	pdf_excel: ["pdf", "excel"],
	upload_add_products: ["upload_products", "add_product"],
}

const pageHeadWrappedActionKeys = ["edit", "delete_purchase_order"]

const stageActionKeys: Record<string, string[]> = {
	submitted: ["undo_confirm_purchase_order", "cancel_purchase_order"],
	in_warehouse: ["packing_instructions"],
	shipped: ["new_stock_delivery"],
	received: ["edit_estimated_delivery_date"],
}

const uncheckedReceiptItems = computed(() => (props.agent_receiving?.lines ?? []).flatMap((line) =>
	line.items.filter((item) => item.check_route && !item.is_checked)
))

const goodsReceiptsSection = ref<InstanceType<typeof AgentPurchaseOrderGoodsReceipts> | null>(null)
const isGoodsReceiptsHighlighted = ref(false)
const isGoodsReceiptsShaking = ref(false)
let goodsReceiptsHighlightTimers: ReturnType<typeof setTimeout>[] = []

function showGoodsToCheck() {
	goodsReceiptsHighlightTimers.forEach(clearTimeout)
	goodsReceiptsSection.value?.$el?.scrollIntoView({ behavior: "smooth", block: "center" })
	isGoodsReceiptsHighlighted.value = true
	isGoodsReceiptsShaking.value = false
	goodsReceiptsHighlightTimers = [
		setTimeout(() => (isGoodsReceiptsShaking.value = true), 500),
		setTimeout(() => (isGoodsReceiptsShaking.value = false), 800),
		setTimeout(() => (isGoodsReceiptsHighlighted.value = false), 2500),
	]
}

onUnmounted(() => goodsReceiptsHighlightTimers.forEach(clearTimeout))

const stageActions = computed<Record<string, Action[]>>(() => {
	const actions: Action[] = (props.pageHead.actions ?? []).filter(Boolean)
	const stageKeys = (props.stages?.stages ?? []).map((stage) => stage.key)

	return Object.fromEntries(
		Object.entries(stageActionKeys)
			.filter(([stageKey]) => stageKeys.includes(stageKey))
			.map(([stageKey, keys]) => [stageKey, actions.filter((action) => keys.includes(action.key))])
			.filter(([stageKey, stageActionList]) => stageActionList.length || (stageKey === "in_warehouse" && (props.agent_receiving?.receive || uncheckedReceiptItems.value.length)))
	)
})

const isReceiveGoodsNextStep = computed(() => {
	const currentStage = props.stages?.stages.find((stage) => stage.current)?.key

	return !!props.agent_receiving?.receive && !uncheckedReceiptItems.value.length && ["in_production", "in_warehouse"].includes(currentStage ?? "")
})

const stageActionLoading: Record<string, () => boolean> = {
	undo_confirm_purchase_order: () => undoConfirmLoading.value,
	cancel_purchase_order: () => cancelLoading.value,
	new_stock_delivery: () => newStockDeliveryLoading.value,
	edit_estimated_delivery_date: () => confirmLoading.value,
}

function runStageAction(action: Action) {
	const handlers: Record<string, (action: Action) => void> = {
		undo_confirm_purchase_order: confirmUndoConfirmPurchaseOrder,
		cancel_purchase_order: confirmCancelPurchaseOrder,
		new_stock_delivery: openDeliveryScopeModal,
		edit_estimated_delivery_date: openEstimatedDeliveryDateModal,
	}
	handlers[action.key]?.(action)
}

const pageHeadWithGroupedActions = computed<PageHeadingTypes>(() => {
	const actionsInStages = Object.values(stageActions.value).flat().map((action) => action.key)
	const actions: Action[] = (props.pageHead.actions ?? []).filter(Boolean).filter((action: Action) => !actionsInStages.includes(action.key))
	const actionsByKey = Object.fromEntries(actions.map((action) => [action.key, action]))
	const placedGroupKeys = new Set<string>()

	return {
		...props.pageHead,
		actions: actions.flatMap((action) => {
			if (pageHeadWrappedActionKeys.includes(action.key)) {
				return []
			}

			const groupKey = Object.keys(pageHeadButtonGroups).find((key) => pageHeadButtonGroups[key].includes(action.key))
			const members = groupKey ? pageHeadButtonGroups[groupKey].map((key) => actionsByKey[key]).filter(Boolean) : []

			if (!groupKey || members.length < 2) {
				return [action]
			}

			if (placedGroupKeys.has(groupKey)) {
				return []
			}

			placedGroupKeys.add(groupKey)

			return [{ type: "buttonGroup", key: groupKey, button: members } as Action]
		}),
		wrapped_actions: [
			...(props.pageHead.wrapped_actions ?? []),
			...actions.filter((action) => pageHeadWrappedActionKeys.includes(action.key)),
		],
	}
})

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHeadWithGroupedActions">
		<template #afterTitle2>
			<span v-if="lastEdit" class="text-xs font-normal text-gray-500">
				{{ ctrans("Last edited by :user on :date", { user: lastEdit.user ?? "?", date: useFormatTime(lastEdit.at, { formatTime: "short-datetime" }) }) }}
			</span>
		</template>
		<template #other>
			<Button v-if="currentTab === 'attachments'" :label="ctrans('Attach')" icon="upload" @click="() => (isModalUploadAttachmentOpen = true)" />
		</template>

		<template #button-pdf="{ action }">
			<ButtonDownloadWithOptions :action="action" />
		</template>

		<template #button-labels="{ action }">
			<ButtonDownloadWithOptions :action="action" />
		</template>

		<template #button-group-pdf="{ action }">
			<ButtonDownloadWithOptions :action="action" class="[&>button]:rounded-r-none" />
		</template>

		<template #button-group-excel="{ action }">
			<a :href="route(action.route.name, action.route.parameters)" :target="action.target" class="-ml-px">
				<Button
					type="tertiary"
					:icon="faFileExcel"
					:tooltip="`${action.label}: ${action.tooltip}`"
					class="h-full rounded-l-none"
				/>
			</a>
		</template>

		<template #button-group-upload-products="{ action }">
			<Button
				type="tertiary"
				:icon="action.icon"
				:tooltip="action.tooltip"
				class="h-full rounded-r-none"
				@click="() => isModalUploadExcel = true"
			/>
		</template>

		<template #button-group-add-product="{ action }">
			<Button
				type="tertiary"
				:label="action.label"
				:icon="action.icon"
				:tooltip="action.tooltip"
				class="-ml-px h-full rounded-l-none text-nowrap"
				@click="() => openProductListModal(action)"
			/>
		</template>

		<template #wrapped-edit="{ action }">
			<Link :href="route(action.route.name, action.route.parameters)">
				<Button type="tertiary" :label="action.label" icon="fal fa-pencil" />
			</Link>
		</template>

		<template #wrapped-delete-purchase-order="{ action }">
			<Button
				type="tertiary"
				:label="action.label"
				:tooltip="action.tooltip"
				:loading="deleteLoading"
				@click="() => confirmDeletePurchaseOrder(action)"
			>
				<template #icon>
					<FontAwesomeIcon :icon="action.icon" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
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

		<template #button-confirm-purchase-order="{ action }">
			<span v-if="action.disabled_reason" v-tooltip="action.disabled_reason" class="inline-flex cursor-not-allowed items-center gap-2">
				<Button
					:style="action.style"
					:label="action.label"
					:icon="action.icon"
					disabled
					class="pointer-events-none"
				/>
			</span>
			<Button
				v-else
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
			<span v-if="action.disabled_reason" v-tooltip="action.disabled_reason" class="inline-flex cursor-not-allowed items-center gap-2">
				<Button
					:style="action.style"
					:label="action.label"
					:icon="action.icon"
					disabled
					class="pointer-events-none"
				/>
			</span>
			<Button
				v-else
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

	<PurchaseOrderStages v-if="stages" :stages="stages.stages" :route="stages.route" :uploadRoute="stages.upload_route" :deposit="stages.deposit">
		<template v-if="agent_money" #stage-deposit_paid>
			<AgentPurchaseOrderMoney :money="agent_money" />
		</template>
		<template v-else-if="payments" #stage-deposit_paid>
			<PurchaseOrderDepositPayments :payments="payments" :org-currency-code="box_stats.third_block.org_currency" :org-rate="paymentsOrgRate" />
		</template>
		<template v-for="(actions, stageKey) in stageActions" :key="stageKey" #[`stage-${stageKey}`]>
			<div class="mt-2 flex flex-col gap-1">
				<AgentReceiveGoodsButton
					v-if="stageKey === 'in_warehouse' && agent_receiving?.receive"
					:scopes="agent_receiving.receive.scopes"
					:purchaseOrderId="agent_receiving.receive.purchase_order_id"
					:isNextStep="isReceiveGoodsNextStep"
					size="xs"
					full
				/>
				<span v-if="stageKey === 'in_warehouse' && uncheckedReceiptItems.length" class="relative block">
					<Button
						type="tertiary"
						size="xs"
						full
						:label="ctrans('Check goods (:count)', { count: uncheckedReceiptItems.length })"
						:tooltip="ctrans('Next step: check the received goods for rejects in Received at our warehouse')"
						@click="showGoodsToCheck"
					>
						<template #icon>
							<FontAwesomeIcon :icon="faClipboardCheck" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<PingIcon class="!absolute -right-1 -top-1 text-[7px] text-amber-500" aria-hidden="true" />
				</span>
				<template v-for="action in actions" :key="action.key">
					<a v-if="action.key === 'packing_instructions'" :href="route(action.route.name, action.route.parameters)" target="_blank">
						<Button type="tertiary" size="xs" full :label="action.label" :tooltip="action.tooltip">
							<template #icon>
								<FontAwesomeIcon :icon="action.icon" class="text-gray-500" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</a>
					<span v-else v-tooltip="action.disabled_reason">
						<Button
							type="tertiary"
							size="xs"
							full
							:label="action.label"
							:tooltip="action.disabled_reason ? undefined : action.tooltip"
							:disabled="!!action.disabled_reason"
							:loading="stageActionLoading[action.key]?.()"
							@click="() => runStageAction(action)"
						>
							<template #icon>
								<FontAwesomeIcon :icon="action.icon" :class="action.style === 'delete' ? 'text-red-500' : 'text-gray-500'" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</span>
				</template>
			</div>
		</template>
	</PurchaseOrderStages>
	<div v-else-if="timelines" class="py-2 border-b border-gray-300">
		<Timeline
			:options="timelines"
			:state="props.data.data.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>
	<div v-if="!stages && agent_money" class="max-w-sm border-b border-gray-300 px-4 pb-3">
		<AgentPurchaseOrderMoney :money="agent_money" />
	</div>
	<div v-else-if="!stages && payments" class="max-w-sm border-b border-gray-300 px-4 pb-3">
		<PurchaseOrderDepositPayments :payments="payments" :org-currency-code="box_stats.third_block.org_currency" :org-rate="paymentsOrgRate" />
	</div>

	<AgentPurchaseOrderGoodsReceipts
		v-if="agent_receiving"
		ref="goodsReceiptsSection"
		:receiving="agent_receiving"
		class="transition-colors duration-700"
		:class="[isGoodsReceiptsHighlighted ? 'bg-orange-50 ring-2 ring-inset ring-orange-500' : '', isGoodsReceiptsShaking ? 'animate-error_shake' : '']"
	/>

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
				<div v-if="box_stats.first_block.orderer.via_agent" class="flex items-center gap-3 text-sm">
					<FontAwesomeIcon v-tooltip="ctrans('Agent')" icon="fal fa-people-arrows" class="text-gray-400" aria-hidden="true" fixed-width />
					<span class="text-gray-500">{{ ctrans("via agent") }}</span>
					<Link v-if="agentRoute" :href="agentRoute" class="primaryLink">
						{{ box_stats.first_block.orderer.via_agent.name }}
					</Link>
					<span v-else class="text-gray-700">{{ box_stats.first_block.orderer.via_agent.name }}</span>
				</div>
				<div v-if="box_stats.first_block.clean_handover" class="flex flex-col gap-1 text-sm text-gray-500">
					<div v-tooltip="ctrans('Date the buyer or the agent proposes the goods will be ready for handover')" class="flex w-fit items-center gap-3">
						<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" aria-hidden="true" fixed-width />
						<span>{{ ctrans("Proposed ready") }}: {{ box_stats.first_block.clean_handover.proposed_ready_at ? useFormatTime(box_stats.first_block.clean_handover.proposed_ready_at) : "-" }}</span>
					</div>
					<div v-tooltip="ctrans('Ready date approved by management, in answer to the proposed date')" class="flex w-fit items-center gap-3">
						<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" aria-hidden="true" fixed-width />
						<span>{{ ctrans("Approved ready") }}: {{ box_stats.first_block.clean_handover.approved_ready_at ? useFormatTime(box_stats.first_block.clean_handover.approved_ready_at) : "-" }}</span>
					</div>
					<div class="flex items-center gap-3">
						<FontAwesomeIcon icon="fal fa-clipboard-list" class="text-gray-400" aria-hidden="true" fixed-width />
						<span>{{ ctrans("Compliance complete") }}: {{ box_stats.first_block.clean_handover.compliance_complete_at ? useFormatTime(box_stats.first_block.clean_handover.compliance_complete_at) : "-" }}</span>
					</div>
					<div v-if="box_stats.first_block.clean_handover.chs_excluded" class="flex items-center gap-3 text-amber-600">
						<FontAwesomeIcon icon="fal fa-exclamation-triangle" aria-hidden="true" fixed-width />
						<span>{{ ctrans("Excluded from clean handover score") }}<template v-if="box_stats.first_block.clean_handover.chs_exclusion_reason">: {{ box_stats.first_block.clean_handover.chs_exclusion_reason }}</template></span>
					</div>
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
				<div v-for="development in productDevelopments ?? []" :key="development.id" class="flex items-center gap-3 text-sm">
					<FontAwesomeIcon v-tooltip="ctrans('Product development')" icon="fal fa-lightbulb-on" class="text-gray-400" aria-hidden="true" fixed-width />
					<Link :href="route(development.route.name, development.route.parameters)" class="primaryLink truncate">{{ development.code }}</Link>
					<span class="truncate text-gray-600">{{ development.name }}</span>
					<span class="shrink-0 text-xs text-gray-400">{{ development.stage }}</span>
				</div>
				<ProcurementOrderData v-if="showcase" :data="showcase" :isOwnWarehouse="box_stats.first_block.delivery.is_own_warehouse" />
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
					<OrderSummary :order_summary="summaryGroups" :currency_code="box_stats.third_block.currency ?? ''">
						<template #cell_extra_costs_1="{ fieldSummary }">
							<dt class="col-span-3">
								<button type="button" class="flex items-center gap-1.5 text-left leading-none hover:text-gray-800" :aria-expanded="showExtraCosts" @click="showExtraCosts = !showExtraCosts">
									<FontAwesomeIcon :icon="showExtraCosts ? 'fal fa-chevron-down' : 'fal fa-chevron-right'" class="w-3.5 text-gray-400" aria-hidden="true" />
									{{ fieldSummary.label }}
								</button>
							</dt>
						</template>
					</OrderSummary>
					<PurchaseOrderPayments v-if="payments" :payments="payments" :org-currency-code="box_stats.third_block.org_currency" :org-rate="paymentsOrgRate" />
				</section>
			</div>
		</BoxStatPallet>
	</div>

	<AspoDepositsChecklist v-if="deposits" :deposits="deposits" />

	<div v-if="agreement.terms.length || agreement.notes.length || agreement.open_incidents" class="px-4 pt-4">
		<AgreementSummary :agreement="agreement" showLevel />
		<label v-if="agreement.must_acknowledge" class="mt-2 flex items-center gap-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
			<Checkbox v-model="criticalNotesAcknowledged" binary />
			<span>{{ ctrans("I have read the critical notes for this order") }}</span>
		</label>
	</div>

	<div v-if="agreed_term_differences" class="px-4 pt-4">
		<AgreedTermDifferences :differences="agreed_term_differences" />
	</div>

	<div v-if="packing_spec_gaps" class="px-4 pt-4">
		<PackingSpecGaps :gaps="packing_spec_gaps" />
	</div>

	<Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

	<div class="pb-12">
		<template v-if="agent_ordering && isOrderingLevelTab && props[currentTab as keyof typeof props]">
			<div v-if="currentTab === 'products' && products?.ordering" class="mx-4 mb-3 mt-4 flex flex-wrap items-center gap-2">
				<PillFilterBar :label="ctrans('Stock group')" :options="products.ordering.cover" :selected="products.ordering.active.cover" @select="(value) => filterProducts('cover', value)" />
				<PillFilterBar :label="ctrans('Rank')" :options="products.ordering.rank" :selected="products.ordering.active.rank" @select="(value) => filterProducts('rank', value)" />
				<label class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-1 text-xs" :class="products.ordering.active.hide_ordered ? 'border-gray-400 bg-gray-100 text-gray-800' : 'border-gray-200 bg-white text-gray-600'">
					<Checkbox :modelValue="products.ordering.active.hide_ordered" binary size="small" @update:modelValue="(value: boolean) => filterProducts('hide_ordered', value ? '1' : null)" />
					{{ ctrans("Hide what is already on order") }}
				</label>
			</div>
			<AgentOrderingItems :key="currentTab === 'products' ? `products-${products?.ordering?.active.cover}-${products?.ordering?.active.rank}-${products?.ordering?.active.hide_ordered}` : currentTab" :items="props[currentTab as keyof typeof props]" v-bind="agent_ordering" :tableName="currentTab" />
		</template>
		<div v-else-if="currentTab === 'products' && products?.ordering" class="mt-4">
			<div class="mx-4 mb-3 flex flex-wrap items-center gap-2">
				<PillFilterBar :label="ctrans('Stock group')" :options="products.ordering.cover" :selected="products.ordering.active.cover" @select="(value) => filterProducts('cover', value)" />
				<PillFilterBar :label="ctrans('Rank')" :options="products.ordering.rank" :selected="products.ordering.active.rank" @select="(value) => filterProducts('rank', value)" />
				<label class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-1 text-xs" :class="products.ordering.active.hide_ordered ? 'border-gray-400 bg-gray-100 text-gray-800' : 'border-gray-200 bg-white text-gray-600'">
					<Checkbox :modelValue="products.ordering.active.hide_ordered" binary size="small" @update:modelValue="(value: boolean) => filterProducts('hide_ordered', value ? '1' : null)" />
					{{ ctrans("Hide what is already on order") }}
				</label>
				<SegmentedToggle v-model="currentLevel" class="ml-auto" :options="productsLevelOptions" :ariaLabel="ctrans('Order in cartons, SKOs or units')" />
				<SegmentedToggle v-model="productsView" :options="productsViewOptions" :ariaLabel="ctrans('Show products as cards or as a list')" />
			</div>
			<AgentOrderingItems
				:key="`${products.ordering.active.cover}-${products.ordering.active.rank}-${products.ordering.active.hide_ordered}`"
				:class="productsView === 'cards' ? 'mx-4' : ''"
				:items="products"
				:currency="products.ordering.currency"
				:bucketLabels="productBucketLabels"
				:view="productsView"
				:level="currentLevel"
				:reloadOnly="['products', 'box_stats', 'pageHead']"
				tableName="products"
			/>
			<div v-if="productsView === 'cards' && (products.links.prev || products.links.next)" class="mx-4 mt-3 flex justify-between text-sm">
				<Link v-if="products.links.prev" :href="products.links.prev" preserve-scroll class="secondaryLink">{{ ctrans("Previous") }}</Link>
				<span v-else />
				<Link v-if="products.links.next" :href="products.links.next" preserve-scroll class="secondaryLink">{{ ctrans("Next") }}</Link>
			</div>
		</div>
		<component
			v-else
			:is="component"
			:key="currentTab"
			:data="props[currentTab as keyof typeof props]"
			:tab="currentTab"
			:state="data.data.state"
			:updateRoute="routes.updateOrderRoute"
			:storeRoute="currentTab === 'notes' && note_form?.can_write !== false ? note_store_route : undefined"
			:agreement="currentTab === 'notes' ? note_form : undefined"
			:compliance="currentTab === 'compliance' ? compliance_summary : undefined"
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
			<InputText
				id="purchase-order-cancel-confirmation"
				v-model="cancelConfirmationText"
				autocomplete="off"
				fluid
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
		v-model:visible="submitProblemsOpen"
		modal
		:header="ctrans('This purchase order cannot be submitted yet')"
		:style="{ width: '44rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<p class="text-sm text-gray-600">
			{{ submitProblems.length > 1 ? ctrans("These need correcting first. Nothing was sent.") : ctrans("This needs correcting first. Nothing was sent.") }}
		</p>
		<ul class="mt-3 divide-y divide-gray-200 border-y border-gray-200">
			<li v-for="problem in submitProblems" :key="problem.message" class="py-3">
				<div class="flex items-start gap-3">
					<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
					<span class="text-sm text-gray-800">{{ problem.message }}</span>
				</div>
				<div v-if="problem.fixes.length || problem.task" class="mt-2 flex flex-wrap gap-2 pl-8">
					<a v-for="fix in problem.fixes" :key="fix.label" :href="route(fix.route.name, fix.route.parameters)" target="_blank">
						<Button :label="fix.label" type="tertiary" :icon="faPencil" size="s" />
					</a>
					<Button
						v-if="problem.task"
						:label="problem.fixes.length ? ctrans('Ask somebody else to correct it') : ctrans('Ask somebody to correct it')"
						type="tertiary"
						:icon="faClipboardList"
						size="s"
						@click="taskProblem = problem"
					/>
				</div>
			</li>
		</ul>
		<p class="mt-3 text-xs text-gray-500">{{ ctrans("Once it is corrected, press Submit again.") }}</p>

		<template #footer>
			<Button :label="ctrans('Close')" type="tertiary" @click="submitProblemsOpen = false">
				<template #icon>
					<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</Dialog>

	<StaffTaskDialog
		:is-open="!!taskProblem"
		:subject="taskProblem?.task?.subject"
		:description="taskProblem?.task?.description"
		department="masters"
		:model-type="taskProblem?.org_stock_id ? 'OrgStock' : null"
		:model-id="taskProblem?.org_stock_id"
		@close="taskProblem = null"
	/>

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
			<p v-if="newStockDeliveryAction?.agent_note" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
				<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
				<span>{{ newStockDeliveryAction.agent_note }}</span>
			</p>
			<div v-if="open_agent_deliveries.length" class="flex flex-col gap-2">
				<label for="purchase-order-target-delivery" class="font-medium text-gray-700">{{ ctrans("Add to") }}</label>
				<Select
					v-model="targetStockDelivery"
					inputId="purchase-order-target-delivery"
					:options="stockDeliveryOptions"
					optionLabel="label"
					optionValue="id"
					fluid
				/>
			</div>
			<p class="text-gray-600">{{ ctrans("Which purchase order items should be included in this delivery?") }}</p>

			<button
				type="button"
				class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-[--app-accent] hover:bg-[--app-accent-soft] disabled:cursor-not-allowed disabled:opacity-50"
				:disabled="delivery_items.length === 0 || newStockDeliveryLoading"
				@click="createStockDelivery(delivery_items.map(item => item.id))"
			>
				<FontAwesomeIcon icon="fal fa-box" class="mt-0.5 text-[--app-accent]" fixed-width aria-hidden="true" />
				<span class="flex flex-col gap-1">
					<span class="font-medium text-gray-800">{{ ctrans("All items") }}</span>
					<span class="text-sm text-gray-500">{{ ctrans("Include every item in this purchase order") }}</span>
				</span>
			</button>

			<button
				type="button"
				class="flex items-start gap-3 rounded-lg border border-gray-200 p-4 text-left hover:border-[--app-accent] hover:bg-[--app-accent-soft] disabled:cursor-not-allowed disabled:opacity-50"
				:disabled="delivery_items.length === 0 || newStockDeliveryLoading"
				@click="openDeliveryItemsModal"
			>
				<FontAwesomeIcon icon="fal fa-clipboard-list" class="mt-0.5 text-[--app-accent]" fixed-width aria-hidden="true" />
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
		<IconField class="mb-3">
			<InputIcon>
				<FontAwesomeIcon :icon="faSearch" fixed-width aria-hidden="true" />
			</InputIcon>
			<InputText v-model="deliveryItemsSearch" class="w-full" :placeholder="ctrans('Search code or name')" />
		</IconField>

		<div class="flex max-h-[60vh] flex-col divide-y divide-gray-200 overflow-y-auto rounded-lg border border-gray-200">
			<label
				v-for="item in filteredDeliveryItems"
				:key="item.id"
				class="flex cursor-pointer items-center gap-3 p-3 hover:bg-gray-50"
			>
				<Checkbox v-model="selectedDeliveryItemIds" :value="item.id" />
				<ZoomableImage :src="item.image_thumbnail" :full="item.image_full" :alt="item.code" class="h-12 w-12" @click.prevent />
				<span class="min-w-0 flex-1">
					<span class="block font-medium text-gray-800">{{ item.code || ctrans("No code") }}</span>
					<span class="block truncate text-sm text-gray-500">{{ item.name }}</span>
				</span>
				<span class="text-sm text-gray-500">
					{{ ctrans("Quantity") }}: {{ locale.number(Number(item.quantity_ordered)) }}
				</span>
			</label>
			<p v-if="filteredDeliveryItems.length === 0" class="p-4 text-center text-sm text-gray-500">
				{{ ctrans("No items match your search") }}
			</p>
		</div>

		<template #footer>
			<Button
				:label="ctrans('Back')"
				type="secondary"
				@click="deliveryItemsModalOpen = false; deliveryScopeModalOpen = true"
			/>
			<Button
				:label="selectedDeliveryItemIds.length === 1
					? ctrans('Create delivery (1 SKO)')
					: ctrans('Create delivery (:count SKOs)', { count: selectedDeliveryItemIds.length })"
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
