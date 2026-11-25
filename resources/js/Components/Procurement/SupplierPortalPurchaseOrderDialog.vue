<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import SupplierPortalCompliance, { type SupplierComplianceProduct } from "@/Components/Procurement/SupplierPortalCompliance.vue"
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Select from "primevue/select"
import FileUpload from "primevue/fileupload"
import Textarea from "primevue/textarea"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import BoxNote from "@/Components/Pallet/BoxNote.vue"
import type { PDRNotes } from "@/types/Pallet"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faTag, faCheck, faPencil, faTimes, faLock, faExclamationCircle, faReceipt, faDownload, faFileAlt, faUpload, faHandHoldingUsd } from "@fal"
import { faCheckCircle } from "@fas"

library.add(faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faTag, faCheck, faPencil, faTimes, faLock, faExclamationCircle, faReceipt, faDownload, faFileAlt, faUpload, faHandHoldingUsd, faCheckCircle)

type SegmentState = "done" | "skipped" | "untracked" | "overdue" | "at_risk" | "on_track" | "future"

interface Payment {
	date: string
	amount: number
	original_amount: number | null
	original_currency_code: string | null
	reference: string | null
	paid_by: string | null
	receipt: { file_name: string | null; view_url: string; download_url: string } | null
}

interface PurchaseOrderDetail {
	order: {
		id: number
		reference: string
		organisation_name: string
		agent_name: string | null
		state: string
		state_label: string
		delivery_label: string | null
		open: boolean
		submitted_at: string | null
		submitted_by: string | null
		confirmed_at: string | null
		estimated_received_at: string | null
		settled_at: string | null
		currency_code: string
		total: number
		terms: { label: string; value: string }[]
		labels: Record<"carton" | "sko" | "unit", string> | null
	}
	journey: {
		stages: { key: string; label: string; description: string; note: string | null }[]
		ribbon: {
			current_stage: string | null
			current_label: string | null
			status: string
			days_overdue: number
			eta: string | null
			segments: { key: string; state: SegmentState; done_at: string | null; planned_at: string; days_overdue: number; no_deposit?: boolean }[]
		}
	} | null
	payments: {
		currency_code: string
		total: number
		paid: number
		left: number
		deposit_paid_at: string | null
		balance_paid_at: string | null
		deposit: Payment[]
		balance: Payment[]
	}
	deposit: {
		choosable: boolean
		not_needed: boolean
		not_needed_by: "supplier" | "buyer" | null
		request: { percentage: number; amount: number; requested_at: string } | null
		request_percentage: number
		request_amount: number
		order_amount: number
		recipients: { name: string; organisation: string }[]
		agreement_missing: boolean
	}
	production: {
		started_at: string | null
		expected_finish_at: string | null
		production_days: number | null
		editable: boolean
		blocked: string | null
	}
	notes: {
		note_list: (PDRNotes & { placeholder?: string | null })[]
	}
	compliance?: {
		open: boolean
		products: SupplierComplianceProduct[]
	}
	documents: {
		open: boolean
		types: { value: string; label: string }[]
		files: { type: string; file_name: string | null; uploaded_at: string | null; view_url: string; download_url: string }[]
	}
	completion: {
		available: boolean
		completed_at: string | null
		by_supplier: boolean
		completable: boolean
		blocked: string | null
	}
	lines: {
		id: number
		code: string | null
		our_code: string | null
		name: string | null
		image: object | null
		units_per_carton: number | null
		cartons: number | null
		quantity: number
		unit: string | null
		unit_price: number
		amount: number
		currency_code: string
		cancelled: boolean
	}[]
}

const props = defineProps<{
	purchaseOrderRoute: string
	productionRoute?: string
	depositRoute?: string
	documentsRoute?: string
	completeRoute?: string
	notesRoute?: string
	complianceSubmitUrl?: string | null
	submissionFileRoute?: string | null
}>()

const purchaseOrderId = defineModel<number | null>({ required: true })
const startsProduction = defineModel<boolean>("startProduction", { default: false })

const emit = defineEmits<{ agreement: []; productionSaved: [] }>()

const openAgreement = () => {
	purchaseOrderId.value = null
	emit("agreement")
}

const locale = useLocaleStore()

const detail = ref<PurchaseOrderDetail | null>(null)
const isLoading = ref(false)
const loadError = ref<string | null>(null)

const isVisible = computed({
	get: () => purchaseOrderId.value !== null,
	set: (visible: boolean) => {
		if (!visible) {
			purchaseOrderId.value = null
		}
	},
})

const reloadCompliance = async () => {
	const id = purchaseOrderId.value
	if (id === null) {
		return
	}
	const { data } = await axios.get<PurchaseOrderDetail>(props.purchaseOrderRoute.replace(/\/0$/, `/${id}`))
	if (purchaseOrderId.value === id && detail.value) {
		detail.value.compliance = data.compliance
	}
}

watch(purchaseOrderId, async (id) => {
	detail.value = null
	loadError.value = null
	if (id === null) {
		return
	}

	isLoading.value = true
	try {
		const { data } = await axios.get<PurchaseOrderDetail>(props.purchaseOrderRoute.replace(/\/0$/, `/${id}`))
		if (purchaseOrderId.value === id) {
			detail.value = data
			if (startsProduction.value && data.production.editable && !data.production.started_at) {
				openProductionEditor()
			}
		}
	} catch {
		loadError.value = ctrans("This order could not be opened. Try again.")
	} finally {
		isLoading.value = false
		startsProduction.value = false
	}
})

const stageIcons: Record<string, string> = {
	submitted: "fal fa-rocket",
	deposit_paid: "fal fa-coins",
	in_production: "fal fa-industry-alt",
	ready: "fal fa-clipboard-check",
	handed_over: "fal fa-box",
}

const segmentsByKey = computed(() => Object.fromEntries((detail.value?.journey?.ribbon.segments ?? []).map((segment) => [segment.key, segment])))

const isStageComplete = (key: string) => ["done", "skipped"].includes(segmentsByKey.value[key]?.state ?? "")
const isStageSkipped = (key: string) => segmentsByKey.value[key]?.state === "skipped" || Boolean(segmentsByKey.value[key]?.no_deposit)
const isStageCurrent = (key: string) => detail.value?.journey?.ribbon.current_stage === key

const fromIsoDate = (iso: string): Date => {
	const [year, month, dayOfMonth] = iso.slice(0, 10).split("-").map(Number)
	return new Date(year, month - 1, dayOfMonth)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const dayZero = computed(() => (detail.value?.order.submitted_at ?? new Date().toISOString()).slice(0, 10))
const daysBetween = (from: string, to: string) => Math.round((Date.parse(to.slice(0, 10)) - Date.parse(from.slice(0, 10))) / 86400000)
const zDay = (date: string | null) => {
	if (!date) {
		return ""
	}
	const days = daysBetween(dayZero.value, date)
	return `Z${days < 0 ? "" : "+"}${days}`
}

type StageSegment = NonNullable<PurchaseOrderDetail["journey"]>["ribbon"]["segments"][number]

const planDifference = (segment: StageSegment): number | null =>
	segment.key !== "submitted" && segment.state === "done" && segment.done_at && segment.planned_at ? daysBetween(segment.planned_at, segment.done_at) : null

const doneDateClass = (segment: StageSegment) => {
	const difference = planDifference(segment)
	if (difference === null || difference === 0) {
		return "text-gray-900"
	}
	return difference > 0 ? "text-red-600" : "text-green-700"
}

const addDays = (iso: string, days: number) => {
	const date = fromIsoDate(iso)
	date.setDate(date.getDate() + days)
	return toLocalIsoDate(date)
}

const productionChecks = computed(() => {
	const order = detail.value
	const plannedStart = segmentsByKey.value.in_production?.planned_at
	if (!order) {
		return []
	}

	const checks: { key: string; ok: boolean; label: string }[] = []
	const startedAt = order.production.started_at?.slice(0, 10) ?? null
	if (startedAt && plannedStart) {
		const late = daysBetween(plannedStart, startedAt)
		checks.push({
			key: "start",
			ok: late <= 0,
			label: late <= 0
				? ctrans("Started on plan (target :date)", { date: day(plannedStart) })
				: ctrans("Started :days days after the plan (target :date)", { days: String(late), date: day(plannedStart) }),
		})
	}

	const productionDays = order.production.production_days
	const finishAt = order.production.expected_finish_at
	if (finishAt && productionDays && (startedAt || plannedStart)) {
		const plannedFinish = addDays((startedAt ?? plannedStart)!, productionDays)
		const longer = daysBetween(plannedFinish, finishAt)
		checks.push({
			key: "finish",
			ok: longer <= 0,
			label: longer <= 0
				? ctrans("Expected finish fits the :days production days (by :date)", { days: String(productionDays), date: day(plannedFinish) })
				: ctrans("Expected finish is :late days longer than the :days production days (by :date)", { late: String(longer), days: String(productionDays), date: day(plannedFinish) }),
		})
	}

	return checks
})

const isProductionNextStep = computed(() => {
	const order = detail.value
	if (!order || !order.production.editable || order.production.started_at) {
		return false
	}

	return order.deposit.not_needed || isStageComplete("deposit_paid")
})

const isEditingProduction = ref(false)
const productionStartedAt = ref<Date | null>(null)
const productionFinishAt = ref<Date | null>(null)
const isSavingProduction = ref(false)
const productionError = ref<string | null>(null)

const isFinishFollowingStart = ref(false)

const finishAfterStart = (startedAt: Date | null): Date | null => {
	const productionDays = detail.value?.production.production_days
	if (!startedAt || !productionDays) {
		return null
	}

	const finishAt = new Date(startedAt)
	finishAt.setDate(finishAt.getDate() + productionDays)

	return finishAt
}

const openProductionEditor = () => {
	const production = detail.value?.production
	const startedAt = production?.started_at ? fromIsoDate(production.started_at) : new Date(new Date().setHours(0, 0, 0, 0))
	productionStartedAt.value = startedAt
	isFinishFollowingStart.value = !production?.expected_finish_at
	productionFinishAt.value = production?.expected_finish_at ? fromIsoDate(production.expected_finish_at) : finishAfterStart(startedAt)
	productionError.value = null
	isEditingProduction.value = true
}

const changeProductionStartedAt = (startedAt: Date | null) => {
	productionStartedAt.value = startedAt
	if (isFinishFollowingStart.value) {
		productionFinishAt.value = finishAfterStart(startedAt)
	}
}

const changeProductionFinishAt = (finishAt: Date | null) => {
	productionFinishAt.value = finishAt
	isFinishFollowingStart.value = false
}

const saveProduction = async () => {
	const id = purchaseOrderId.value
	if (!props.productionRoute || id === null) {
		return
	}

	isSavingProduction.value = true
	productionError.value = null
	try {
		const { data } = await axios.post<PurchaseOrderDetail>(props.productionRoute.replace(/\/0\/production$/, `/${id}/production`), {
			production_started_at: productionStartedAt.value ? toLocalIsoDate(productionStartedAt.value) : null,
			estimated_production_date: productionFinishAt.value ? toLocalIsoDate(productionFinishAt.value) : null,
		})
		if (purchaseOrderId.value === id) {
			detail.value = data
		}
		isEditingProduction.value = false
		emit("productionSaved")
	} catch (error: any) {
		const errors = error?.response?.data?.errors as Record<string, string[]> | undefined
		productionError.value = (errors && Object.values(errors)[0]?.[0]) ?? ctrans("The dates could not be saved. Try again.")
	} finally {
		isSavingProduction.value = false
	}
}

const editingNote = ref<(PDRNotes & { placeholder?: string | null }) | null>(null)
const noteDraft = ref("")
const isSavingNote = ref(false)
const noteError = ref<string | null>(null)

const isEditingNote = computed({
	get: () => editingNote.value !== null,
	set: (visible: boolean) => {
		if (!visible) {
			editingNote.value = null
		}
	},
})

const openNoteEditor = (note: PDRNotes & { placeholder?: string | null }) => {
	editingNote.value = note
	noteDraft.value = note.note ?? ""
	noteError.value = null
}

const saveNote = async () => {
	const id = purchaseOrderId.value
	if (!props.notesRoute || id === null || !editingNote.value) {
		return
	}

	isSavingNote.value = true
	noteError.value = null
	try {
		const { data } = await axios.post<PurchaseOrderDetail>(props.notesRoute.replace(/\/0\/notes$/, `/${id}/notes`), {
			[editingNote.value.field]: noteDraft.value.trim() === "" ? null : noteDraft.value.trim(),
		})
		if (purchaseOrderId.value === id) {
			detail.value = data
		}
		editingNote.value = null
	} catch (error: any) {
		const errors = error?.response?.data?.errors as Record<string, string[]> | undefined
		noteError.value = (errors && Object.values(errors)[0]?.[0]) ?? ctrans("The note could not be saved. Try again.")
	} finally {
		isSavingNote.value = false
	}
}

type DepositChoice = "not_needed" | "request"

const depositChoice = ref<DepositChoice | null>(null)
const isSavingDeposit = ref(false)
const depositError = ref<string | null>(null)

const requestPercentage = ref<number | null>(null)
const requestAmount = computed(() => (detail.value && requestPercentage.value ? Math.round(detail.value.deposit.order_amount * requestPercentage.value) / 100 : 0))
const isRequestPercentageValid = computed(() => !!requestPercentage.value && requestPercentage.value > 0 && requestPercentage.value <= 100)

const chooseDeposit = (choice: DepositChoice) => {
	depositError.value = null
	requestPercentage.value = detail.value?.deposit.request_percentage ?? null
	depositChoice.value = choice
}

const isConfirmingDeposit = computed({
	get: () => depositChoice.value !== null,
	set: (visible: boolean) => {
		if (!visible) {
			depositChoice.value = null
		}
	},
})

const saveDeposit = async () => {
	const id = purchaseOrderId.value
	if (!props.depositRoute || id === null || depositChoice.value === null) {
		return
	}

	isSavingDeposit.value = true
	depositError.value = null
	try {
		const { data } = await axios.post<PurchaseOrderDetail>(props.depositRoute.replace(/\/0\/deposit$/, `/${id}/deposit`), {
			choice: depositChoice.value,
			percentage: depositChoice.value === "request" ? requestPercentage.value : null,
		})
		if (purchaseOrderId.value === id) {
			detail.value = data
		}
		depositChoice.value = null
	} catch (error: any) {
		const errors = error?.response?.data?.errors as Record<string, string[]> | undefined
		depositError.value = (errors && Object.values(errors)[0]?.[0]) ?? ctrans("Your answer could not be sent. Try again.")
	} finally {
		isSavingDeposit.value = false
	}
}

const documentType = ref<string | null>(null)
const documentFiles = ref<File[]>([])
const documentFileKey = ref(0)
const isUploadingDocuments = ref(false)
const isDraggingDocuments = ref(false)

const chooseDocuments = (event: { files: File[] }) => {
	documentFiles.value = [...documentFiles.value, ...event.files]
	documentFileKey.value++
	documentsError.value = null
}

const dropDocuments = (event: DragEvent) => {
	isDraggingDocuments.value = false
	const files = Array.from(event.dataTransfer?.files ?? [])
	if (files.length) {
		documentFiles.value = [...documentFiles.value, ...files]
		documentsError.value = null
	}
}
const documentsError = ref<string | null>(null)
const isUploadingDocumentsOpen = ref(false)

const openDocumentsUpload = () => {
	documentsError.value = null
	isUploadingDocumentsOpen.value = true
}

const uploadDocuments = async () => {
	const id = purchaseOrderId.value
	if (!props.documentsRoute || id === null) {
		return
	}
	if (!documentType.value || !documentFiles.value.length) {
		documentsError.value = ctrans("Choose the document type and at least one file.")
		return
	}

	const body = new FormData()
	body.append("scope", documentType.value)
	documentFiles.value.forEach((file) => body.append("files[]", file))

	isUploadingDocuments.value = true
	documentsError.value = null
	try {
		const { data } = await axios.post<PurchaseOrderDetail>(props.documentsRoute.replace(/\/0\/documents$/, `/${id}/documents`), body)
		if (purchaseOrderId.value === id) {
			detail.value = data
		}
		documentType.value = null
		documentFiles.value = []
		documentFileKey.value++
		isUploadingDocumentsOpen.value = false
	} catch (error: any) {
		const errors = error?.response?.data?.errors as Record<string, string[]> | undefined
		documentsError.value = (errors && Object.values(errors)[0]?.[0]) ?? ctrans("The documents could not be uploaded. Try again.")
	} finally {
		isUploadingDocuments.value = false
	}
}

const isConfirmingCompletion = ref(false)
const isCompleting = ref(false)
const completionError = ref<string | null>(null)

const openCompletionConfirm = () => {
	completionError.value = null
	isConfirmingCompletion.value = true
}

const completeOrder = async () => {
	const id = purchaseOrderId.value
	if (!props.completeRoute || id === null) {
		return
	}

	isCompleting.value = true
	completionError.value = null
	try {
		const { data } = await axios.post<PurchaseOrderDetail>(props.completeRoute.replace(/\/0\/complete$/, `/${id}/complete`))
		if (purchaseOrderId.value === id) {
			detail.value = data
		}
		isConfirmingCompletion.value = false
		emit("productionSaved")
	} catch (error: any) {
		const errors = error?.response?.data?.errors as Record<string, string[]> | undefined
		completionError.value = (errors && Object.values(errors)[0]?.[0]) ?? ctrans("The order could not be completed. Try again.")
	} finally {
		isCompleting.value = false
	}
}

const tomorrow = computed(() => {
	const date = new Date()
	date.setDate(date.getDate() + 1)
	return date
})

const facts = computed(() => {
	const order = detail.value?.order
	if (!order) {
		return []
	}

	return [
		{ label: ctrans("Ordered by"), value: order.organisation_name },
		...(order.agent_name ? [{ label: ctrans("Agent"), value: order.agent_name }] : []),
		{ label: ctrans("Sent"), value: day(order.submitted_at) },
		...(order.confirmed_at ? [{ label: ctrans("Agreed"), value: day(order.confirmed_at) }] : []),
		...(order.estimated_received_at ? [{ label: ctrans("Expected"), value: day(order.estimated_received_at) }] : []),
		...(order.settled_at ? [{ label: ctrans("Received"), value: day(order.settled_at) }] : []),
		...order.terms,
	]
})

const day = (date: string | null) => (date ? useFormatTime(date) : "—")
const quantity = (value: number | null) => (value === null ? "" : String(locale.number(value)))
const money = (amount: number) => locale.currencyFormat(detail.value?.payments.currency_code ?? "", amount)
const stateClass = (order: PurchaseOrderDetail["order"]) => (order.open ? "text-amber-700" : order.state === "settled" ? "text-green-700" : "text-gray-500")
</script>

<template>
	<div>
	<Dialog
		v-model:visible="isVisible"
		modal
		dismissableMask
		:header="detail?.order.reference ?? ctrans('Purchase order')"
		:style="{ width: '72rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="isLoading" class="flex justify-center py-16"><LoadingIcon /></div>
		<p v-else-if="loadError" class="py-6 text-center text-xs text-red-600">{{ loadError }}</p>

		<div v-else-if="detail" class="space-y-5 text-sm">
			<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
				<span class="whitespace-nowrap text-lg font-semibold text-gray-900">{{ detail.order.reference }}</span>
				<span class="font-semibold" :class="stateClass(detail.order)">{{ detail.order.state_label }}</span>
				<span v-if="detail.order.delivery_label" class="text-gray-400">· {{ detail.order.delivery_label }}</span>
				<span class="ml-auto whitespace-nowrap text-lg font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(detail.order.currency_code, detail.order.total) }}</span>
			</div>

			<dl class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3 lg:grid-cols-4">
				<div v-for="fact in facts" :key="fact.label">
					<dt class="text-xs text-gray-400">{{ fact.label }}</dt>
					<dd class="text-gray-800">{{ fact.value }}</dd>
				</div>
			</dl>

			<section v-if="detail.journey" class="overflow-hidden rounded-lg border border-gray-200">
				<div class="border-b border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Stages") }}</div>
				<ol class="grid grid-cols-1 gap-px bg-gray-200 sm:grid-cols-2 lg:grid-cols-5">
					<li
						v-for="(stage, index) in detail.journey.stages"
						:key="stage.key"
						class="flex min-w-0 flex-col p-3"
						:class="isStageComplete(stage.key) ? 'bg-green-50' : 'bg-white'">
						<div class="flex items-center gap-2 font-medium" :class="isStageComplete(stage.key) || isStageCurrent(stage.key) ? 'text-gray-800' : 'text-gray-400'" v-tooltip="stage.description">
							<span class="w-4 shrink-0 text-center tabular-nums">{{ index + 1 }}</span>
							<FontAwesomeIcon :icon="stageIcons[stage.key]" class="text-gray-400" fixed-width aria-hidden="true" />
							<span class="truncate">{{ stage.label }}</span>
							<FontAwesomeIcon v-if="isStageComplete(stage.key)" icon="fas fa-check-circle" class="shrink-0 text-green-600" fixed-width aria-hidden="true" />
						</div>
						<div class="text-xs italic text-gray-400">{{ stage.description }}</div>
						<p v-if="stage.note" class="mt-1 rounded border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] px-2 py-1 text-xs text-[var(--app-accent-deep)]">{{ stage.note }}</p>

						<div v-if="stage.key === 'submitted' && detail.order.submitted_by" class="mt-2 text-xs">
							<div class="text-gray-500">{{ ctrans("Submitted by") }}</div>
							<div class="break-words text-gray-700">{{ detail.order.submitted_by }}</div>
						</div>

						<div v-if="stage.key === 'deposit_paid'" class="mt-2 text-xs">
							<div class="mb-1 font-semibold text-gray-500">{{ ctrans("Deposit") }}</div>
							<ul v-if="detail.payments.deposit.length" class="space-y-1.5">
								<li v-for="(payment, paymentIndex) in detail.payments.deposit" :key="paymentIndex">
									<div class="flex justify-between gap-2">
										<span class="tabular-nums text-gray-700">{{ day(payment.date) }}</span>
										<span class="whitespace-nowrap font-medium tabular-nums text-gray-900">{{ money(payment.amount) }}</span>
									</div>
									<div v-if="payment.original_amount !== null && payment.original_currency_code" class="text-right tabular-nums text-gray-400">
										{{ locale.currencyFormat(payment.original_currency_code, payment.original_amount) }}
									</div>
									<div class="break-words text-gray-500">{{ payment.paid_by }}<template v-if="payment.reference"> · {{ payment.reference }}</template></div>
									<div v-if="payment.receipt" class="mt-0.5 flex items-center gap-3">
										<a :href="payment.receipt.view_url" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-1 text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline" v-tooltip="payment.receipt.file_name">
											<FontAwesomeIcon icon="fal fa-receipt" fixed-width aria-hidden="true" />
											<span class="truncate">{{ ctrans("Transfer receipt") }}</span>
										</a>
										<a :href="payment.receipt.download_url" class="text-gray-500 hover:text-[var(--app-accent)]" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
											<FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
										</a>
									</div>
								</li>
							</ul>
							<p v-else class="text-gray-400">{{ ctrans("No deposit paid yet") }}</p>

							<div v-if="detail.deposit.request" class="mt-2 rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-amber-800">
								<div class="font-semibold">{{ ctrans("Deposit requested: :percentage%", { percentage: String(detail.deposit.request.percentage) }) }}</div>
								<div class="flex justify-between gap-2">
									<span class="tabular-nums">{{ day(detail.deposit.request.requested_at) }}</span>
									<span class="whitespace-nowrap font-medium tabular-nums">{{ money(detail.deposit.request.amount) }}</span>
								</div>
							</div>
							<div v-else-if="detail.deposit.not_needed" class="mt-2 flex items-center gap-1 text-green-700" v-tooltip="detail.deposit.not_needed_by === 'supplier' ? ctrans('Set by supplier') : detail.deposit.not_needed_by === 'buyer' ? ctrans('Set by buyer') : undefined">
								<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
								{{ ctrans("No deposit needed") }}
							</div>

							<div v-if="depositRoute && detail.deposit.choosable" class="mt-2 space-y-1.5">
								<Button type="tertiary" size="xs" full :label="ctrans('No deposit needed')" @click="chooseDeposit('not_needed')">
									<template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
								</Button>
								<Button type="tertiary" size="xs" full :label="ctrans('Request deposit')" :disabled="detail.deposit.agreement_missing" @click="chooseDeposit('request')">
									<template #icon><FontAwesomeIcon icon="fal fa-hand-holding-usd" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
								</Button>
								<p v-if="detail.deposit.agreement_missing" class="flex items-start gap-1 text-amber-700">
									<FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
									<span>
										{{ ctrans("Please accept Supplier Agreement before submitting deposit request.") }}
										<button type="button" class="font-medium text-[var(--app-accent)] underline hover:text-[var(--app-accent-strong)]" @click="openAgreement">{{ ctrans("Open Supplier Agreement") }}</button>
									</span>
								</p>
							</div>
						</div>

						<div v-else-if="stage.key === 'in_production'" class="mt-2 text-xs">
							<dl class="space-y-0.5">
								<div class="flex justify-between gap-2">
									<dt class="text-gray-500">{{ ctrans("Production started") }}</dt>
									<dd class="tabular-nums" :class="detail.production.started_at ? 'text-gray-700' : 'text-gray-400'">{{ detail.production.started_at ? day(detail.production.started_at) : "-" }}</dd>
								</div>
								<div class="flex justify-between gap-2">
									<dt class="text-gray-500">{{ ctrans("Expected finish") }}</dt>
									<dd class="tabular-nums" :class="detail.production.expected_finish_at ? 'text-gray-700' : 'text-gray-400'">{{ detail.production.expected_finish_at ? day(detail.production.expected_finish_at) : "-" }}</dd>
								</div>
							</dl>
							<ul v-if="productionChecks.length" class="mt-1.5 space-y-0.5">
								<li v-for="check in productionChecks" :key="check.key" class="flex items-start gap-1" :class="check.ok ? 'text-green-700' : 'text-amber-700'">
									<FontAwesomeIcon :icon="check.ok ? 'fal fa-check' : 'fal fa-exclamation-circle'" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
									<span>{{ check.label }}</span>
								</li>
							</ul>
							<div v-if="productionRoute && (detail.production.editable || detail.production.blocked)" class="mt-2">
								<span v-if="!detail.production.editable" class="block" v-tooltip="detail.production.blocked">
									<Button type="tertiary" size="xs" full disabled :label="ctrans('Start production')">
										<template #icon><FontAwesomeIcon icon="fal fa-lock" class="text-gray-400" fixed-width aria-hidden="true" /></template>
									</Button>
								</span>
								<span v-else class="relative block">
									<Button type="tertiary" size="xs" full :label="detail.production.started_at ? ctrans('Change production dates') : ctrans('Start production')" @click="openProductionEditor">
										<template #icon>
											<FontAwesomeIcon :icon="detail.production.started_at ? 'fal fa-pencil' : 'fal fa-check'" :class="detail.production.started_at ? 'text-gray-500' : 'text-green-600'" fixed-width aria-hidden="true" />
										</template>
									</Button>
									<PingIcon v-if="isProductionNextStep" class="!absolute -right-1 -top-1 text-[7px] text-amber-500" aria-hidden="true" />
								</span>
							</div>
						</div>

						<div v-else-if="stage.key === 'ready'" class="mt-2 text-xs">
							<template v-if="detail.payments.balance.length">
								<div class="mb-1 font-semibold text-gray-500">{{ ctrans("Balance") }}</div>
								<ul class="space-y-1.5">
									<li v-for="(payment, paymentIndex) in detail.payments.balance" :key="paymentIndex">
										<div class="flex justify-between gap-2">
											<span class="tabular-nums text-gray-700">{{ day(payment.date) }}</span>
											<span class="whitespace-nowrap font-medium tabular-nums text-gray-900">{{ money(payment.amount) }}</span>
										</div>
										<div v-if="payment.original_amount !== null && payment.original_currency_code" class="text-right tabular-nums text-gray-400">
											{{ locale.currencyFormat(payment.original_currency_code, payment.original_amount) }}
										</div>
										<div class="break-words text-gray-500">{{ payment.paid_by }}<template v-if="payment.reference"> · {{ payment.reference }}</template></div>
										<div v-if="payment.receipt" class="mt-0.5 flex items-center gap-3">
											<a :href="payment.receipt.view_url" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-1 text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline" v-tooltip="payment.receipt.file_name">
												<FontAwesomeIcon icon="fal fa-receipt" fixed-width aria-hidden="true" />
												<span class="truncate">{{ ctrans("Transfer receipt") }}</span>
											</a>
											<a :href="payment.receipt.download_url" class="text-gray-500 hover:text-[var(--app-accent)]" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
												<FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
											</a>
										</div>
									</li>
								</ul>
							</template>
							<dl class="space-y-0.5" :class="detail.payments.balance.length ? 'mt-2 border-t border-gray-200 pt-1.5' : ''">
								<div class="flex justify-between gap-2">
									<dt class="text-gray-500">{{ ctrans("Paid") }}</dt>
									<dd class="whitespace-nowrap tabular-nums text-gray-700">{{ money(detail.payments.paid) }} / {{ money(detail.payments.total) }}</dd>
								</div>
								<div v-if="detail.payments.left > 0" class="flex justify-between gap-2">
									<dt class="text-amber-700">{{ ctrans("Left to pay") }}</dt>
									<dd class="whitespace-nowrap font-medium tabular-nums text-amber-700">{{ money(detail.payments.left) }}</dd>
								</div>
								<div v-else-if="detail.payments.total > 0" class="flex items-center gap-1 text-green-700">
									<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
									{{ ctrans("Paid in full") }}
								</div>
							</dl>
						</div>

						<div v-else-if="stage.key === 'handed_over'" class="mt-2 space-y-2 text-xs">
							<div>
								<div class="mb-1 font-semibold text-gray-500">{{ ctrans("Shipping documents") }}</div>
								<ul v-if="detail.documents.files.length" class="space-y-1">
									<li v-for="(document, documentIndex) in detail.documents.files" :key="documentIndex">
										<div class="font-medium text-gray-500">{{ document.type }}</div>
										<div class="flex items-center gap-2">
											<a :href="document.view_url" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-1 text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline" v-tooltip="document.file_name">
												<FontAwesomeIcon icon="fal fa-file-alt" fixed-width aria-hidden="true" />
												<span class="truncate">{{ document.file_name }}</span>
											</a>
											<a :href="document.download_url" class="ml-auto shrink-0 text-gray-500 hover:text-[var(--app-accent)]" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
												<FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
											</a>
										</div>
									</li>
								</ul>
								<p v-else class="text-gray-400">{{ ctrans("No documents uploaded yet") }}</p>
								<Button v-if="documentsRoute && detail.documents.open" type="tertiary" size="xs" full class="mt-1.5" :label="ctrans('Upload documents')" @click="openDocumentsUpload">
									<template #icon><FontAwesomeIcon icon="fal fa-upload" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
								</Button>
							</div>
							<template v-if="detail.completion.available">
							<div v-if="detail.completion.completed_at" class="flex items-center gap-1 text-green-700">
								<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
								{{ detail.completion.by_supplier ? ctrans("Order complete") : ctrans("Handed over") }}
							</div>
							<div v-else-if="completeRoute && detail.order.open">
								<span v-if="!detail.completion.completable" class="block" v-tooltip="detail.completion.blocked">
									<Button type="tertiary" size="xs" full disabled :label="ctrans('Order complete')">
										<template #icon><FontAwesomeIcon icon="fal fa-lock" class="text-gray-400" fixed-width aria-hidden="true" /></template>
									</Button>
								</span>
								<Button v-else type="tertiary" size="xs" full :label="ctrans('Order complete')" @click="openCompletionConfirm">
									<template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
								</Button>
								<p v-if="detail.completion.blocked" class="mt-1 flex items-start gap-1 text-amber-700">
									<FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
									<span>{{ detail.completion.blocked }}</span>
								</p>
							</div>
							</template>
						</div>

						<div class="mt-auto pt-3">
							<div class="border-t border-gray-200 pt-2 text-xs tabular-nums">
								<div class="flex h-4 items-baseline justify-between gap-2 text-gray-400">
									<template v-if="segmentsByKey[stage.key]?.planned_at && stage.key !== 'submitted'">
										<span>{{ ctrans("Target") }}</span>
										<span class="whitespace-nowrap">{{ day(segmentsByKey[stage.key].planned_at) }} {{ zDay(segmentsByKey[stage.key].planned_at) }}</span>
									</template>
								</div>
								<div class="mt-0.5 h-5 whitespace-nowrap text-right text-sm font-semibold" :class="segmentsByKey[stage.key] ? doneDateClass(segmentsByKey[stage.key]) : ''">
									<span v-if="isStageSkipped(stage.key)" class="text-xs font-normal text-gray-400">{{ ctrans("Skipped") }}</span>
									<template v-else-if="segmentsByKey[stage.key]?.state === 'done' && segmentsByKey[stage.key].done_at">
										{{ day(segmentsByKey[stage.key].done_at) }} {{ zDay(segmentsByKey[stage.key].done_at) }}
									</template>
								</div>
							</div>
						</div>
					</li>
				</ol>
			</section>

			<section>
				<h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Products") }}</h3>
				<div class="overflow-x-auto rounded-lg border border-gray-200">
					<table class="w-full min-w-[48rem] text-sm">
						<thead class="whitespace-nowrap bg-gray-50 text-xs text-gray-500">
							<tr>
								<th class="w-8 px-2 py-2 text-left font-semibold">#</th>
								<th class="w-12 px-2 py-2" />
								<th class="px-2 py-2 text-left font-semibold">{{ ctrans("Code") }}</th>
								<th class="px-2 py-2 text-left font-semibold">{{ ctrans("Description") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Units / carton") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Cartons") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Units") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Unit price") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Amount") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="(line, index) in detail.lines" :key="line.id" :class="{ 'text-gray-400 line-through': line.cancelled }">
								<td class="px-2 py-2 tabular-nums text-gray-400">{{ index + 1 }}</td>
								<td class="px-2 py-1">
									<ZoomableImage v-if="line.image" :src="line.image" :alt="line.code" class="h-10 w-10" />
								</td>
								<td class="whitespace-nowrap px-2 py-2">
									<div class="font-semibold text-gray-900">{{ line.code }}</div>
									<div v-if="line.our_code" class="text-xs text-gray-400">{{ line.our_code }}</div>
								</td>
								<td class="px-2 py-2 text-gray-700">{{ line.name }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ quantity(line.units_per_carton) }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ quantity(line.cartons) }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ quantity(line.quantity) }}<span v-if="line.unit" class="text-gray-400"> {{ line.unit }}</span></td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">
									{{ locale.currencyFormatUnitCost(line.currency_code, line.unit_price) }}<span v-if="line.unit" class="text-gray-400"> / {{ line.unit }}</span>
								</td>
								<td class="whitespace-nowrap px-2 py-2 text-right font-semibold tabular-nums">{{ locale.currencyFormat(line.currency_code, line.amount) }}</td>
							</tr>
						</tbody>
						<tfoot class="border-t border-gray-200">
							<tr>
								<td colspan="8" class="px-2 py-2 text-right font-semibold text-gray-700">{{ ctrans("Total") }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(detail.order.currency_code, detail.order.total) }}</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</section>

			<section v-if="detail.notes?.note_list.length" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
				<BoxNote
					v-for="note in detail.notes.note_list"
					:key="note.field"
					:noteData="{ ...note, editable: note.editable && !!notesRoute }"
					:updateRoute="{ name: '', parameters: {} }"
					disableModal
					@click="openNoteEditor(note)"
				/>
			</section>

			<section class="overflow-hidden rounded-lg border border-gray-200">
				<div class="border-b border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Shipping documents") }}</div>
				<div class="space-y-3 p-3">
					<p class="text-xs text-gray-500">{{ ctrans("Upload the documents of this order: proforma, invoice, packing list, bill of lading, certificate of origin.") }}</p>
					<ul v-if="detail.documents.files.length" class="divide-y divide-gray-100">
						<li v-for="(document, documentIndex) in detail.documents.files" :key="documentIndex" class="flex items-center gap-3 py-1.5">
							<span class="w-40 shrink-0 whitespace-nowrap text-xs font-semibold text-gray-500">{{ document.type }}</span>
							<a :href="document.view_url" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-1 text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline">
								<FontAwesomeIcon icon="fal fa-file-alt" fixed-width aria-hidden="true" />
								<span class="truncate">{{ document.file_name }}</span>
							</a>
							<span class="ml-auto whitespace-nowrap text-xs tabular-nums text-gray-400">{{ day(document.uploaded_at) }}</span>
							<a :href="document.download_url" class="text-gray-500 hover:text-[var(--app-accent)]" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
								<FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
							</a>
						</li>
					</ul>
					<p v-else class="text-xs text-gray-400">{{ ctrans("No documents uploaded yet") }}</p>

					<Button v-if="documentsRoute && detail.documents.open" type="tertiary" size="xs" :label="ctrans('Upload documents')" @click="openDocumentsUpload">
						<template #icon><FontAwesomeIcon icon="fal fa-upload" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</section>

			<section v-if="detail.compliance?.products.length" class="overflow-hidden rounded-lg border border-gray-200">
				<div class="border-b border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Compliance documents") }}</div>
				<div class="space-y-3 p-3">
					<p class="text-xs text-gray-500">{{ ctrans("The compliance documents of the products on this order. Click a document to see it or send a new file for this order.") }}</p>
					<SupplierPortalCompliance
						:products="detail.compliance.products"
						:submitUrl="detail.compliance.open ? complianceSubmitUrl : null"
						:fileRoute="submissionFileRoute"
						:purchaseOrderId="detail.order.id"
						compact
						@sent="reloadCompliance"
					/>
				</div>
			</section>

			<section v-if="detail.order.labels" class="flex flex-wrap items-center gap-2">
				<span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Labels") }}</span>
				<a v-for="(label, level) in { carton: ctrans('Cartons'), sko: ctrans('SKOs'), unit: ctrans('Units') }" :key="level" :href="detail.order.labels[level]" target="_blank">
					<Button type="tertiary" size="xs" :label="label">
						<template #icon><FontAwesomeIcon icon="fal fa-tag" fixed-width aria-hidden="true" /></template>
					</Button>
				</a>
			</section>
		</div>
	</Dialog>

	<Dialog v-model:visible="isConfirmingDeposit" modal :header="depositChoice === 'request' ? ctrans('Request deposit') : ctrans('No deposit needed')" :style="{ width: '26rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="detail" class="space-y-3 text-sm">
			<p v-if="depositChoice === 'request'" class="text-gray-700">
				{{ ctrans("Ask :organisation for a deposit of :amount (:percentage% of the order) before you start production? We email the buyer and the agent.", { organisation: detail.order.organisation_name, amount: money(requestAmount), percentage: String(requestPercentage ?? 0) }) }}
			</p>
			<label v-if="depositChoice === 'request'" class="block">
				<span class="text-xs font-semibold text-gray-500">{{ ctrans("Deposit (% of the order)") }}</span>
				<div class="mt-1 flex items-center gap-2">
					<InputNumber v-model="requestPercentage" suffix="%" :min="1" :max="100" :minFractionDigits="0" :maxFractionDigits="2" class="w-32" :pt="{ pcInputText: { root: { class: '!w-full focus:!border-[var(--app-accent)]' } } }" />
					<span class="tabular-nums text-gray-700">{{ money(requestAmount) }}</span>
				</div>
			</label>
			<div v-if="depositChoice === 'request'">
				<div class="mb-1 text-xs font-semibold text-gray-500">{{ ctrans("Email goes to") }}</div>
				<ul v-if="detail.deposit.recipients.length" class="max-h-48 divide-y divide-gray-100 overflow-y-auto rounded border border-gray-200 text-xs">
					<li v-for="(recipient, recipientIndex) in detail.deposit.recipients" :key="recipientIndex" class="flex justify-between gap-2 px-2 py-1.5">
						<span class="font-medium text-gray-800">{{ recipient.name }}</span>
						<span class="whitespace-nowrap text-gray-400">{{ recipient.organisation }}</span>
					</li>
				</ul>
				<p v-else class="text-xs text-amber-700">{{ ctrans("Nobody gets this request by email. Tell your contact directly.") }}</p>
			</div>
			<p v-else class="text-gray-700">{{ ctrans("Confirm you start production of :reference without a deposit. The order terms are agreed.", { reference: detail.order.reference }) }}</p>
			<p v-if="depositError" class="text-xs text-red-600">{{ depositError }}</p>
		</div>
		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isConfirmingDeposit = false">
					<template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="primary" full :label="depositChoice === 'request' ? ctrans('Send request') : ctrans('Confirm')" :disabled="depositChoice === 'request' && !isRequestPercentageValid" :loading="isSavingDeposit" @click="saveDeposit">
					<template #icon><FontAwesomeIcon :icon="depositChoice === 'request' ? 'fal fa-hand-holding-usd' : 'fal fa-check'" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>
	</Dialog>

	<Dialog v-model:visible="isUploadingDocumentsOpen" modal :header="ctrans('Upload documents')" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="detail" class="space-y-2 text-sm">
			<p class="text-xs text-gray-500">{{ ctrans("Upload the documents of this order: proforma, invoice, packing list, bill of lading, certificate of origin.") }}</p>
			<div
				class="space-y-2 rounded-lg border-2 border-dashed p-3 transition-colors"
				:class="isDraggingDocuments ? 'border-[var(--app-accent)] bg-[var(--app-accent-soft)]' : 'border-gray-200'"
				@dragenter.prevent="isDraggingDocuments = true"
				@dragover.prevent="isDraggingDocuments = true"
				@dragleave.self="isDraggingDocuments = false"
				@drop.prevent="dropDocuments">
				<div class="flex items-center gap-2 text-xs text-gray-500">
					<FontAwesomeIcon icon="fal fa-upload" fixed-width aria-hidden="true" />
					{{ ctrans("Drag and drop files here, or choose them") }}
				</div>
				<div class="flex flex-wrap items-center gap-2">
					<Select v-model="documentType" :options="detail.documents.types" optionLabel="label" optionValue="value" :placeholder="ctrans('Document type')" class="w-56" size="small" />
					<FileUpload :key="documentFileKey" mode="basic" :auto="false" customUpload multiple :chooseLabel="ctrans('Choose files')" @select="chooseDocuments">
						<template #filelabel><span /></template>
					</FileUpload>
					<Button type="primary" size="xs" :label="ctrans('Upload')" :loading="isUploadingDocuments" @click="uploadDocuments">
						<template #icon><FontAwesomeIcon icon="fal fa-upload" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
				<ul v-if="documentFiles.length" class="flex flex-wrap gap-1.5 text-xs">
					<li v-for="(file, fileIndex) in documentFiles" :key="fileIndex" class="flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-gray-700">
						<span class="max-w-[16rem] truncate">{{ file.name }}</span>
						<button type="button" class="text-gray-400 hover:text-red-600" :aria-label="ctrans('Remove')" @click="documentFiles = documentFiles.filter((_, index) => index !== fileIndex)">
							<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
						</button>
					</li>
				</ul>
				<span class="block text-xs text-gray-400">{{ ctrans("Up to 10 files, 15 MB each. PDF, JPG, PNG, Excel or Word.") }}</span>
				<p v-if="documentsError" class="text-xs text-red-600">{{ documentsError }}</p>
			</div>
		</div>
	</Dialog>

	<Dialog v-model:visible="isConfirmingCompletion" modal :header="ctrans('Order complete')" :style="{ width: '26rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="detail" class="space-y-3 text-sm">
			<p class="text-gray-700">{{ ctrans("Confirm you dispatched :reference to :organisation today. This records the Clean Hand Over (CHO) of the order.", { reference: detail.order.reference, organisation: detail.order.organisation_name }) }}</p>
			<div>
				<div class="mb-1 text-xs font-semibold text-gray-500">{{ ctrans("Shipping documents") }}</div>
				<ul class="list-inside list-disc text-xs text-gray-700">
					<li v-for="(document, documentIndex) in detail.documents.files" :key="documentIndex">{{ document.type }}: {{ document.file_name }}</li>
				</ul>
			</div>
			<p v-if="completionError" class="text-xs text-red-600">{{ completionError }}</p>
		</div>
		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isConfirmingCompletion = false">
					<template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="primary" full :label="ctrans('Order complete')" :loading="isCompleting" @click="completeOrder">
					<template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>
	</Dialog>

	<Dialog v-model:visible="isEditingNote" modal :header="editingNote?.label ?? ctrans('Note')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-2 text-sm">
			<p v-if="editingNote?.information" class="text-xs text-gray-500">{{ editingNote.information }}</p>
			<Textarea v-model="noteDraft" rows="6" autoResize :maxlength="5000" :placeholder="editingNote?.placeholder ?? ctrans('Write your note here')" :disabled="isSavingNote" class="w-full text-sm placeholder:italic placeholder:!text-gray-400" />
			<p v-if="noteError" class="text-xs text-red-600">{{ noteError }}</p>
		</div>
		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isEditingNote = false">
					<template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="primary" full :label="ctrans('Save')" :loading="isSavingNote" @click="saveNote">
					<template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>
	</Dialog>

	<Dialog v-model:visible="isEditingProduction" modal :header="detail?.production.started_at ? ctrans('Production dates') : ctrans('Start production')" :style="{ width: '24rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-3 text-sm">
			<label class="block">
				<span class="mb-1 block text-xs font-semibold text-gray-600">{{ ctrans("Production started") }}</span>
				<DatePicker :modelValue="productionStartedAt" @update:modelValue="changeProductionStartedAt" :maxDate="tomorrow" dateFormat="d M yy" showIcon iconDisplay="input" showButtonBar fluid class="[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]" />
			</label>
			<label class="block">
				<span class="mb-1 block text-xs font-semibold text-gray-600">{{ ctrans("Expected finish") }}</span>
				<DatePicker :modelValue="productionFinishAt" @update:modelValue="changeProductionFinishAt" :minDate="productionStartedAt ?? undefined" dateFormat="d M yy" showIcon iconDisplay="input" showButtonBar fluid class="[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]" />
				<span v-if="isFinishFollowingStart && productionFinishAt && detail?.production.production_days" class="mt-1 block text-xs text-gray-500">{{ ctrans(":days days after production starts", { days: String(detail.production.production_days) }) }}</span>
			</label>
			<p v-if="productionError" class="text-xs text-red-600">{{ productionError }}</p>
		</div>
		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isEditingProduction = false">
					<template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="primary" full :label="detail?.production.started_at ? ctrans('Save') : ctrans('Start production')" :loading="isSavingProduction" @click="saveProduction">
					<template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>
	</Dialog>
	</div>
</template>
