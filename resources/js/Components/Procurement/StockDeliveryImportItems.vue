<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-30 14:38
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import FileUpload from "primevue/fileupload"
import Checkbox from "primevue/checkbox"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faExclamationTriangle, faFileExcel, faTimes, faInfoCircle, faUndoAlt, faExternalLink, faMagic, faHandPointer } from "@fal"
import { faSpinnerThird } from "@fad"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Item = {
	id: number
	purchase_order_id: number
	purchase_order_reference: string
	supplier: string | null
	code: string | null
	name: string | null
	quantity_ordered: number
	quantity_awaiting_delivery: number
	units_per_sko: number
	units_per_carton: number
	confirmed_at: string | null
	purchase_order_slug: string
	quantity_received: number
}

type Line = {
	key: string
	code: string
	sheet_code: string
	name: string | null
	rows: number[]
	sheet_quantity: number
	units: number | null
	sheet_purchase_order: string | null
	preferred_purchase_order_ids: number[]
	in_container_units: number
	item_ids: number[]
	warnings: string[]
}

type Preview = {
	errors: string[]
	file: string
	sheets: string[]
	sheet: string | null
	heading_row: number | null
	columns: { key: string; heading: string; letter: string }[]
	quantity_in: "units" | "skos" | "cartons" | null
	rows_read: number
	from_agent_warehouse: boolean
	tracks_receipts: boolean
	receive_url: string | null
	lines: Line[]
	items: Record<number, Item>
	ignored: {
		rows: number[]
		code: string | null
		quantity: number | null
		sheet_purchase_order: string | null
		purchase_orders: { reference: string; slug: string; state: string }[]
		needs_receiving: boolean
		reason: string
	}[]
}

type PurchaseOrderChoice = "all" | number

type LineState = {
	included: boolean
	choice: PurchaseOrderChoice
	overrides: Record<number, number | null> | null
}

type Problem = { tone: "red" | "amber"; text: string; link?: { label: string; url: string } }

type Tab = "add" | "check" | "ignored"

const props = defineProps<{
	previewRoute: routeType
	addRoute: routeType
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const SHEET_STORAGE_KEY = "stock-delivery-import-items-sheet"

const file = ref<File | null>(null)
const preview = ref<Preview | null>(null)
const isLoading = ref(false)
const isSubmitting = ref(false)
const errorMessages = ref<string[]>([])
const lineStates = ref<Record<string, LineState>>({})
const countInContainer = ref(true)
const includeNotReceived = ref(false)
const isNotReceivedConfirmed = ref(false)
const search = ref("")
const shownTab = ref<Tab>("add")
const isGuideOpen = ref(false)
const keysToCheck = ref<Set<string>>(new Set())

const reset = () => {
	file.value = null
	preview.value = null
	errorMessages.value = []
	lineStates.value = {}
	countInContainer.value = true
	includeNotReceived.value = false
	isNotReceivedConfirmed.value = false
	search.value = ""
	shownTab.value = "add"
	isGuideOpen.value = false
	keysToCheck.value = new Set()
}

watch(visible, (isVisible) => {
	if (!isVisible) {
		reset()
	}
})

const rememberedSheet = (): string | null => {
	try {
		return localStorage.getItem(SHEET_STORAGE_KEY)
	} catch {
		return null
	}
}

const rememberSheet = (sheet: string) => {
	try {
		localStorage.setItem(SHEET_STORAGE_KEY, sheet)
	} catch {
		return
	}
}

const errorsFrom = (error: any, fallback: string): string[] => {
	if (error?.response?.status === 413) {
		return [ctrans("The file is too large for the server (:size MB). Save a copy without the pictures, or ask IT to raise the upload limit.", { size: file.value ? (file.value.size / 1048576).toFixed(1) : "?" })]
	}
	const errors = error?.response?.data?.errors
	if (errors) {
		return Object.values(errors).flat() as string[]
	}
	return [error?.response?.data?.message || fallback]
}

const itemOf = (id: number): Item => preview.value!.items[id]

const round = (value: number) => Math.round(value * 1000) / 1000

const formatNumber = (value: number) => locale.number(round(value))

const sortedItemIds = (line: Line): number[] => [...line.item_ids].sort((a, b) => {
	const preferredA = line.preferred_purchase_order_ids.includes(itemOf(a).purchase_order_id) ? 0 : 1
	const preferredB = line.preferred_purchase_order_ids.includes(itemOf(b).purchase_order_id) ? 0 : 1
	if (preferredA !== preferredB) {
		return preferredA - preferredB
	}
	return (itemOf(a).confirmed_at ?? "").localeCompare(itemOf(b).confirmed_at ?? "") || itemOf(a).purchase_order_id - itemOf(b).purchase_order_id
})

const purchaseOrderIdsOf = (line: Line): number[] => [...new Set(sortedItemIds(line).map((id) => itemOf(id).purchase_order_id))]

const initialState = (line: Line): LineState => ({
	included: true,
	choice: line.preferred_purchase_order_ids.length === 1 && purchaseOrderIdsOf(line).length > 1 ? line.preferred_purchase_order_ids[0] : "all",
	overrides: null,
})

const loadPreview = async (sheet: string | null) => {
	if (!file.value) {
		return
	}
	isLoading.value = true
	errorMessages.value = []
	const data = new FormData()
	data.append("file", file.value)
	if (sheet) {
		data.append("sheet", sheet)
	}
	try {
		const { data: result } = await axios.post<Preview>(route(props.previewRoute.name, props.previewRoute.parameters), data)
		preview.value = result
		lineStates.value = Object.fromEntries(result.lines.map((line) => [line.key, initialState(line)]))
		keysToCheck.value = new Set(result.lines.filter((line) => problems(line).length > 0).map((line) => line.key))
		shownTab.value = result.lines.length ? "add" : "ignored"
		if (result.sheet && !result.errors.length) {
			rememberSheet(result.sheet)
		}
	} catch (error: any) {
		errorMessages.value = errorsFrom(error, ctrans("The file could not be read, try again"))
	} finally {
		isLoading.value = false
	}
}

const onSelectFile = (selected: File | undefined) => {
	if (!selected) {
		return
	}
	reset()
	file.value = selected
	loadPreview(rememberedSheet())
}

const selectedSheet = computed({
	get: () => preview.value?.sheet ?? null,
	set: (sheet: string | null) => loadPreview(sheet),
})

const visibleItemIds = (line: Line): number[] => {
	const choice = lineStates.value[line.key]?.choice ?? "all"
	return sortedItemIds(line).filter((id) => choice === "all" || itemOf(id).purchase_order_id === choice)
}

const unitsAlreadyCounted = computed(() => {
	const left: Record<string, number> = {}
	const counted: Record<string, number> = {}
	for (const line of preview.value?.lines ?? []) {
		const product = line.key.split("|")[0]
		left[product] ??= countInContainer.value ? line.in_container_units : 0
		counted[line.key] = Math.min(left[product], line.units ?? 0)
		left[product] -= counted[line.key]
	}
	return counted
})

const wantedUnits = (line: Line) => Math.max(0, (line.units ?? 0) - (unitsAlreadyCounted.value[line.key] ?? 0))

const receivedUnits = (item: Item) => Math.min(item.quantity_awaiting_delivery, item.quantity_received)

const isLimitedToReceived = computed(() => Boolean(preview.value?.tracks_receipts) && !includeNotReceived.value)

const capacity = (item: Item) => isLimitedToReceived.value ? receivedUnits(item) : item.quantity_awaiting_delivery

const allocationPriority = (line: Line) => {
	const state = lineStates.value[line.key]
	if (state?.overrides) {
		return 0
	}
	if (state?.choice !== "all" || line.preferred_purchase_order_ids.length) {
		return 1
	}
	return 2
}

const allocationState = computed(() => {
	const remaining: Record<number, number> = {}
	for (const item of Object.values(preview.value?.items ?? {})) {
		remaining[item.id] = capacity(item)
	}

	const quantitiesByLine: Record<string, Record<number, number>> = {}
	const takenBy: Record<number, string[]> = {}
	const take = (line: Line, id: number, units: number) => {
		quantitiesByLine[line.key][id] = units
		remaining[id] -= units
		takenBy[id] = [...(takenBy[id] ?? []), line.key]
	}

	const lines = [...(preview.value?.lines ?? [])].sort((a, b) => allocationPriority(a) - allocationPriority(b))
	for (const line of lines) {
		const state = lineStates.value[line.key]
		quantitiesByLine[line.key] = {}
		if (!state?.included) {
			continue
		}

		if (state.overrides) {
			for (const id of visibleItemIds(line)) {
				const units = state.overrides[id] ?? 0
				if (units > 0) {
					take(line, id, units)
				}
			}
			continue
		}

		let wanted = wantedUnits(line)
		for (const id of visibleItemIds(line)) {
			const units = round(Math.min(wanted, Math.max(0, remaining[id])))
			if (units > 0) {
				take(line, id, units)
				wanted -= units
			}
		}
	}
	return { quantitiesByLine, takenBy }
})

const allocation = computed(() => allocationState.value.quantitiesByLine)

const rowsTakingUnitsOf = (line: Line): number[] => {
	const lineByKey = new Map((preview.value?.lines ?? []).map((other) => [other.key, other]))
	const rows = visibleItemIds(line)
		.flatMap((id) => allocationState.value.takenBy[id] ?? [])
		.filter((key) => key !== line.key)
		.flatMap((key) => lineByKey.get(key)?.rows ?? [])
	return [...new Set(rows)].sort((a, b) => a - b)
}

const isShortOfReceipts = (line: Line) => Boolean(preview.value?.from_agent_warehouse)
	|| (isLimitedToReceived.value && visibleItemIds(line).some((id) => itemOf(id).quantity_awaiting_delivery > receivedUnits(itemOf(id)) + 0.001))

const shortfallReason = (line: Line, isPartial: boolean): string => {
	const rows = rowsTakingUnitsOf(line)
	if (rows.length) {
		return isPartial
			? ctrans("the rest is taken by :rows of the file", { rows: rowsText(rows).toLowerCase() })
			: ctrans("the units awaiting are taken by :rows of the file", { rows: rowsText(rows).toLowerCase() })
	}
	if (isShortOfReceipts(line)) {
		const reason = isPartial ? ctrans("the rest is not received at the agent warehouse yet") : ctrans("not received at the agent warehouse yet")
		return isLimitedToReceived.value ? `${reason} (${ctrans("receive it, or tick \"Also add units not received\" above")})` : reason
	}
	return isPartial ? ctrans("the rest is not awaiting delivery on the purchase orders chosen") : ctrans("nothing is awaiting delivery on the purchase orders chosen")
}

const usedUnits = computed(() => {
	const used: Record<number, number> = {}
	for (const quantities of Object.values(allocation.value)) {
		for (const [id, units] of Object.entries(quantities)) {
			used[Number(id)] = (used[Number(id)] ?? 0) + units
		}
	}
	return used
})

const lineUnits = (line: Line) => Object.values(allocation.value[line.key] ?? {}).reduce((sum, units) => sum + units, 0)

const setQuantity = (line: Line, id: number, units: number | null) => {
	const state = lineStates.value[line.key]
	state.overrides = { ...(state.overrides ?? { ...allocation.value[line.key] }), [id]: units }
}

const setChoice = (line: Line, choice: PurchaseOrderChoice) => {
	lineStates.value[line.key] = { ...lineStates.value[line.key], choice, overrides: null }
}

const resetLine = (line: Line) => {
	lineStates.value[line.key] = initialState(line)
}

const breakdown = (item: Item, units: number) => {
	const unitsPerSko = Number(item.units_per_sko) || 1
	const parts = [ctrans(":count units", { count: formatNumber(units) }), ctrans(":count SKO", { count: formatNumber(units / unitsPerSko) })]
	if (Number(item.units_per_carton) > 0) {
		parts.push(ctrans(":count cartons", { count: formatNumber(units / Number(item.units_per_carton)) }))
	}
	return parts.join(" · ")
}

const quantityInLabel = computed(() => ({
	units: ctrans("units"),
	skos: ctrans("SKO"),
	cartons: ctrans("cartons"),
})[preview.value?.quantity_in ?? "units"])

const receiveLink = (line: Line): Problem["link"] => rowsTakingUnitsOf(line).length === 0 && isShortOfReceipts(line) && preview.value?.receive_url
	? { label: ctrans("Receive goods at the agent warehouse"), url: preview.value.receive_url }
	: undefined

const problems = (line: Line): Problem[] => {
	const state = lineStates.value[line.key]
	if (!state?.included) {
		return []
	}

	const list: Problem[] = line.warnings.map((text) => ({ tone: "amber", text }))
	const quantities = allocation.value[line.key] ?? {}
	const units = lineUnits(line)

	for (const [id, allocated] of Object.entries(quantities)) {
		const item = itemOf(Number(id))
		if ((usedUnits.value[item.id] ?? 0) > round(item.quantity_awaiting_delivery) + 0.001) {
			list.push({ tone: "red", text: ctrans(":purchaseOrder has only :awaiting awaiting delivery", { purchaseOrder: item.purchase_order_reference, awaiting: breakdown(item, item.quantity_awaiting_delivery) }) })
		}
		if (notReceivedUnitsOf(item) > 0) {
			list.push({ tone: "amber", text: ctrans(":units of these units are not received at the agent warehouse yet", { units: formatNumber(notReceivedUnitsOf(item)) }) })
		}
		const skos = allocated / (Number(item.units_per_sko) || 1)
		if (Math.abs(skos - Math.round(skos)) > 0.001) {
			list.push({ tone: "amber", text: ctrans(":purchaseOrder: :units units is not a whole number of SKOs", { purchaseOrder: item.purchase_order_reference, units: formatNumber(allocated) }) })
		}
	}

	if (units <= 0) {
		list.push(wantedUnits(line) === 0 && line.units
			? { tone: "amber", text: ctrans("Everything in the file is already in this container") }
			: { tone: "amber", text: ctrans("Nothing to add: :reason", { reason: shortfallReason(line, false) }), link: receiveLink(line) })
	} else if (!state.overrides && units + 0.001 < wantedUnits(line)) {
		list.push({ tone: "amber", text: ctrans("Only :units of :wanted units can be added: :reason", { units: formatNumber(units), wanted: formatNumber(wantedUnits(line)), reason: shortfallReason(line, true) }), link: receiveLink(line) })
	}

	return list
}

const notReceivedUnitsOf = (item: Item) => preview.value?.tracks_receipts ? Math.max(0, round((usedUnits.value[item.id] ?? 0) - receivedUnits(item))) : 0

const notReceived = computed(() => {
	const items = Object.keys(usedUnits.value).map((id) => itemOf(Number(id))).filter((item) => notReceivedUnitsOf(item) > 0)
	return {
		units: items.reduce((sum, item) => sum + notReceivedUnitsOf(item), 0),
		items,
		codes: [...new Set(items.map((item) => item.code ?? "-"))],
	}
})

const notReceivedAvailable = computed(() => {
	if (!preview.value?.tracks_receipts) {
		return { units: 0, lines: 0 }
	}
	const ids = new Set((preview.value.lines ?? []).flatMap((line) => line.item_ids))
	const items = [...ids].map(itemOf).filter((item) => item.quantity_awaiting_delivery > receivedUnits(item) + 0.001)
	return { units: items.reduce((sum, item) => sum + item.quantity_awaiting_delivery - receivedUnits(item), 0), lines: items.length }
})

watch(includeNotReceived, (isIncluded) => {
	if (!isIncluded) {
		isNotReceivedConfirmed.value = false
	}
})

const hasBlockingProblem = computed(() => (preview.value?.lines ?? []).some((line) => problems(line).some((problem) => problem.tone === "red")))

const matchesSearch = (texts: (string | null | undefined)[]) => {
	const term = search.value.trim().toLowerCase()
	return !term || texts.some((text) => text?.toLowerCase().includes(term))
}

const shownLines = computed(() => (preview.value?.lines ?? [])
	.filter((line) => (shownTab.value === "check") === keysToCheck.value.has(line.key))
	.filter((line) => matchesSearch([line.code, line.sheet_code, line.name, line.sheet_purchase_order, ...line.item_ids.map((id) => itemOf(id).purchase_order_reference)])))

const shownIgnored = computed(() => (preview.value?.ignored ?? []).filter((row) => matchesSearch([row.code, row.reason, row.sheet_purchase_order, ...row.purchase_orders.map((purchaseOrder) => purchaseOrder.reference)])))

const tabOptions = computed(() => {
	const lines = preview.value?.lines ?? []
	const toCheck = lines.filter((line) => keysToCheck.value.has(line.key)).length
	return [
		{ label: ctrans("Ready to add"), value: "add" as Tab, count: lines.length - toCheck, tone: "emerald" as const },
		{ label: ctrans("To check"), value: "check" as Tab, count: toCheck, tone: "amber" as const },
		{ label: ctrans("Not imported"), value: "ignored" as Tab, count: preview.value?.ignored.length ?? 0, tone: "gray" as const },
	]
})

const hasLinesInContainer = computed(() => (preview.value?.lines ?? []).some((line) => line.in_container_units > 0))

const purchaseOrderOptions = (line: Line) => [
	{ label: ctrans("All purchase orders, oldest first"), value: "all" as PurchaseOrderChoice },
	...purchaseOrderIdsOf(line).map((purchaseOrderId) => {
		const awaiting = line.item_ids.map(itemOf).filter((item) => item.purchase_order_id === purchaseOrderId).reduce((sum, item) => sum + item.quantity_awaiting_delivery, 0)
		const reference = line.item_ids.map(itemOf).find((item) => item.purchase_order_id === purchaseOrderId)!.purchase_order_reference
		return { label: ctrans(":reference · :awaiting units awaiting", { reference, awaiting: formatNumber(awaiting) }), value: purchaseOrderId as PurchaseOrderChoice }
	}),
]

const toSubmit = computed(() => {
	const units: Record<number, number> = {}
	for (const [id, used] of Object.entries(usedUnits.value)) {
		if (used > 0) {
			units[Number(id)] = round(used)
		}
	}
	return Object.entries(units).map(([id, unit_quantity]) => ({ purchase_order_transaction_id: Number(id), unit_quantity }))
})

const totals = computed(() => toSubmit.value.reduce((sum, line) => {
	const item = itemOf(line.purchase_order_transaction_id)
	return { units: sum.units + line.unit_quantity, skos: sum.skos + line.unit_quantity / (Number(item.units_per_sko) || 1) }
}, { units: 0, skos: 0 }))

const disabledReason = computed((): string | null => {
	if (isLoading.value) {
		return ctrans("Reading the file…")
	}
	if (!preview.value || preview.value.errors.length) {
		return ctrans("Choose an Excel file and a tab first")
	}
	if (toSubmit.value.length === 0) {
		return ctrans("Nothing to add")
	}
	if (hasBlockingProblem.value) {
		return ctrans("Fix the lines in red first")
	}
	if (notReceived.value.units > 0 && !isNotReceivedConfirmed.value) {
		return ctrans("Confirm that the products not received are in the agent warehouse")
	}
	return null
})

const submit = () => {
	if (disabledReason.value) {
		return
	}
	const items = toSubmit.value
	errorMessages.value = []
	router.post(route(props.addRoute.name, props.addRoute.parameters), { items }, {
		preserveScroll: true,
		onStart: () => { isSubmitting.value = true },
		onFinish: () => { isSubmitting.value = false },
		onSuccess: () => { visible.value = false },
		onError: (errors: Record<string, string>) => {
			errorMessages.value = Object.entries(errors).map(([key, message]) => {
				const index = key.match(/^items\.(\d+)\./)?.[1]
				const item = index === undefined ? null : itemOf(items[Number(index)].purchase_order_transaction_id)
				return item ? `${item.code} (${item.purchase_order_reference}): ${message}` : message
			})
		},
	})
}

const exampleColumns = [
	{ letter: "B", heading: "BARCODE", need: "optional", values: ["5056368315763", "5056368333750"] },
	{ letter: "C", heading: "CODE", need: "required", values: ["JCG-06", "BTS-02"] },
	{ letter: "E", heading: "Unit description", need: "optional", values: ["Medium Gong in Stand", "Beer Barrel Table"] },
	{ letter: "H", heading: "Total Units", need: "quantity", values: ["4", "5"] },
	{ letter: "J", heading: "Units/SKO", need: "optional", values: ["1", "1"] },
	{ letter: "N", heading: "Total CTN", need: "optional", values: ["4", "5"] },
	{ letter: "AA", heading: "PO Number", need: "recommended", values: ["PO-56 ES", "PO-059 ES"] },
]

const columnGuide = computed(() => [
	{ heading: "CODE", aliases: "Code, SKU, Product code, Item code, Part reference, Supplier code, Kode", text: ctrans("Required. Matched with the product code of the purchase order lines; text in brackets after it, such as (A) or (PART OF), is ignored.") },
	{ heading: "Total Units", aliases: "Units, Qty, Quantity, Pcs, Jumlah · Total SKOs, SKOs · Total CTN, Cartons, CTN", text: ctrans("Required, one of them. Units are used first, then SKOs, then cartons; SKOs and cartons are turned into units with the packing in Aiku.") },
	{ heading: "PO Number", aliases: "PO, PO reference, Purchase order, Proforma", text: ctrans("Recommended. Picks the purchase order when an item is on several; \"PO-059 ES\" finds AWIndo-059-ES by its number. Rows of the same code and PO are added up.") },
	{ heading: "BARCODE", aliases: "Barcode, EAN, Unit barcode", text: ctrans("Optional. Used only when the code is not found, and only when the barcode belongs to one product.") },
	{ heading: "Units/SKO", aliases: "Units per SKO, Units/pack", text: ctrans("Optional, compared with Aiku: a difference is shown as a warning.") },
])

const needLabel = (need: string) => ({
	required: ctrans("Required"),
	quantity: ctrans("Quantity"),
	recommended: ctrans("Recommended"),
	optional: ctrans("Optional"),
})[need]

const needClass = (need: string) => ({
	required: "bg-red-50 text-red-700",
	quantity: "bg-green-50 text-green-700",
	recommended: "bg-[--app-accent-soft] text-[--app-accent-deep]",
	optional: "bg-gray-100 text-gray-600",
})[need]

const showGuide = computed(() => !isLoading.value && (!preview.value || preview.value.errors.length > 0 || isGuideOpen.value))

const purchaseOrderUrl = (slug: string) => route("grp.org.procurement.purchase_orders.show", [route().params.organisation, slug])

const linkedPurchaseOrders = (line: Line) => {
	const seen = new Map<number, Item>()
	for (const id of visibleItemIds(line)) {
		seen.set(itemOf(id).purchase_order_id, itemOf(id))
	}
	return [...seen.values()]
}

const purchaseOrderStateLabel = (state: string) => ({
	in_process: ctrans("in process"),
	submitted: ctrans("submitted"),
	confirmed: ctrans("confirmed"),
	settled: ctrans("completed"),
	cancelled: ctrans("cancelled"),
	not_received: ctrans("not received"),
})[state] ?? state

const matchNote = (line: Line): { tone: "green" | "amber" | "gray"; icon: any; text: string } => {
	if (lineStates.value[line.key]?.choice !== initialState(line).choice) {
		return { tone: "gray", icon: faHandPointer, text: ctrans("Chosen by you") }
	}
	if (line.sheet_purchase_order && line.preferred_purchase_order_ids.length) {
		return { tone: "green", icon: faCheck, text: ctrans("Same PO as the file") }
	}
	if (line.sheet_purchase_order) {
		return { tone: "amber", icon: faExclamationTriangle, text: ctrans("The file's PO :purchaseOrder is not awaiting this item", { purchaseOrder: line.sheet_purchase_order }) }
	}
	return purchaseOrderIdsOf(line).length > 1
		? { tone: "gray", icon: faMagic, text: ctrans("No PO in the file: oldest first") }
		: { tone: "gray", icon: faMagic, text: ctrans("The only PO awaiting this item") }
}

const matchNoteClass = (tone: "green" | "amber" | "gray") => ({
	green: "text-green-600",
	amber: "text-amber-600",
	gray: "text-gray-500",
})[tone]

const rowsText = (rows: number[]) => rows.length === 1 ? ctrans("Row :row", { row: rows[0] }) : ctrans("Rows :rows", { rows: rows.join(", ") })
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="ctrans('Import items from Excel')"
		:style="{ width: '72rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="space-y-3 text-sm">
			<div class="flex flex-wrap items-center gap-3">
				<FileUpload
					mode="basic"
					:auto="false"
					customUpload
					accept=".xlsx,.xls,.csv"
					:chooseButtonProps="{ severity: 'secondary', outlined: true, size: 'small' }"
					:chooseLabel="file ? ctrans('Choose another file') : ctrans('Choose Excel file')"
					:disabled="isLoading || isSubmitting"
					@select="(event) => onSelectFile(event.files?.[0])"
				/>
				<span v-if="file" class="flex items-center gap-1 text-xs text-gray-600">
					<FontAwesomeIcon :icon="faFileExcel" class="text-green-600" fixed-width aria-hidden="true" />
					{{ file.name }}
				</span>
				<div v-if="preview && preview.sheets.length > 1" class="flex items-center gap-2 text-xs text-gray-600">
					{{ ctrans("Tab") }}
					<Select
						v-model="selectedSheet"
						:options="preview.sheets"
						:disabled="isLoading || isSubmitting"
						filter
						size="small"
						class="min-w-[14rem]"
						:aria-label="ctrans('Tab')"
					/>
				</div>
				<button
					v-if="preview && !preview.errors.length && !isLoading"
					type="button"
					class="ml-auto text-xs font-medium text-[--app-accent] hover:underline"
					@click="isGuideOpen = !isGuideOpen"
				>
					{{ isGuideOpen ? ctrans("Hide which columns are read") : ctrans("Which columns are read?") }}
				</button>
			</div>

			<div v-if="showGuide" class="space-y-3 rounded border border-gray-200 bg-gray-50/60 p-3">
				<div>
					<div class="text-xs font-semibold text-gray-700">{{ ctrans("What to upload") }}</div>
					<p class="mt-0.5 text-xs text-gray-600">
						{{ ctrans("Any container list or proforma (.xlsx, .xls or .csv). A CSV saved from one tab is much smaller and faster than the whole workbook. For a workbook, choose its tab after uploading; the tab chosen last time is opened first. The heading row is found in the first 30 rows by its headings, in any column and order; other columns are ignored. Nothing is added until you confirm the preview.") }}
					</p>
				</div>

				<div class="overflow-x-auto rounded border border-gray-300 bg-white">
					<table class="text-xs">
						<thead class="whitespace-nowrap">
							<tr class="bg-gray-100 text-center text-[10px] text-gray-400">
								<th class="w-8 border-b border-r border-gray-300 font-normal"></th>
								<th v-for="column in exampleColumns" :key="column.heading" class="border-b border-r border-gray-300 px-2 font-normal">{{ column.letter }}</th>
							</tr>
							<tr class="text-left">
								<th class="border-b border-r border-gray-300 bg-gray-100 text-center text-[10px] font-normal text-gray-400">5</th>
								<th v-for="column in exampleColumns" :key="column.heading" class="border-b border-r border-gray-200 px-2 py-1 align-top">
									<div class="font-semibold text-gray-800">{{ column.heading }}</div>
									<span class="mt-0.5 inline-block rounded px-1 text-[10px] font-medium" :class="needClass(column.need)">{{ needLabel(column.need) }}</span>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="rowIndex in [0, 1]" :key="rowIndex">
								<td class="border-b border-r border-gray-300 bg-gray-100 text-center text-[10px] text-gray-400">{{ 6 + rowIndex }}</td>
								<td v-for="column in exampleColumns" :key="column.heading" class="whitespace-nowrap border-b border-r border-gray-100 px-2 py-0.5 text-gray-600">
									{{ column.values[rowIndex] }}
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<dl class="grid grid-cols-1 gap-x-4 gap-y-1.5 text-xs sm:grid-cols-[max-content_1fr]">
					<template v-for="column in columnGuide" :key="column.heading">
						<dt class="whitespace-nowrap font-semibold text-gray-700">{{ column.heading }}</dt>
						<dd class="text-gray-600">
							{{ column.text }}
							<div class="text-gray-500">{{ ctrans("Also read as") }}: {{ column.aliases }}</div>
						</dd>
					</template>
				</dl>
			</div>

			<ul v-if="errorMessages.length" class="list-disc space-y-0.5 pl-5 text-xs text-red-600">
				<li v-for="(error, index) in errorMessages" :key="index">{{ error }}</li>
			</ul>

			<div v-if="isLoading" class="flex items-center gap-2 py-6 text-gray-500">
				<FontAwesomeIcon :icon="faSpinnerThird" class="animate-spin" fixed-width aria-hidden="true" />
				{{ ctrans("Reading the file…") }}
			</div>

			<template v-else-if="preview">
				<ul v-if="preview.errors.length" class="list-disc space-y-0.5 pl-5 text-xs text-red-600">
					<li v-for="(error, index) in preview.errors" :key="index">{{ error }}</li>
				</ul>

				<template v-else>
					<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
						<span>{{ ctrans("Heading row :row", { row: preview.heading_row ?? "-" }) }}</span>
						<span>
							{{ ctrans("Columns read") }}:
							<span v-for="(column, index) in preview.columns" :key="column.key" class="whitespace-nowrap">
								<span class="font-medium text-gray-800">{{ column.heading }}</span> ({{ column.letter }})<template v-if="index < preview.columns.length - 1">, </template>
							</span>
						</span>
						<span>{{ ctrans("Quantity read in :unit", { unit: quantityInLabel }) }}</span>
					</div>

					<label v-if="notReceivedAvailable.lines > 0" class="flex items-center gap-2 text-xs text-gray-700 border-t border-dashed border-gray-300 pt-3">
						<Checkbox v-model="includeNotReceived" binary inputId="stock-delivery-import-include-not-received" />
						<span>
							{{ ctrans("Also add units not received at the agent warehouse yet (:units units on :lines purchase order lines)", { units: formatNumber(notReceivedAvailable.units), lines: notReceivedAvailable.lines }) }}
							<span class="text-gray-500">· {{ ctrans("by default only units with a goods receipt are added") }}</span>
						</span>
					</label>
					
					<div v-if="notReceived.units > 0" class="space-y-1.5 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
						<div class="flex items-center gap-2 font-semibold">
							<FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-600" fixed-width aria-hidden="true" />
							{{ ctrans(":units units on :lines purchase order lines have no goods receipt", { units: formatNumber(notReceived.units), lines: notReceived.items.length }) }}
						</div>
						<p>
							{{ ctrans("Nobody recorded them arriving at the agent warehouse. Add them only if you checked that they are really there; otherwise receive them first.") }}
							<a v-if="preview.receive_url" :href="preview.receive_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 whitespace-nowrap font-medium text-[--app-accent] hover:underline">
								{{ ctrans("Receive goods") }}
								<FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" fixed-width aria-hidden="true" />
							</a>
						</p>
						<p class="text-amber-800"><span class="font-medium">{{ ctrans("Codes") }}:</span> {{ notReceived.codes.join(", ") }}</p>
						<label class="flex items-center gap-2 font-medium">
							<Checkbox v-model="isNotReceivedConfirmed" binary inputId="stock-delivery-import-confirm-not-received" />
							<span>{{ ctrans("I checked: these products are in the agent warehouse") }}</span>
						</label>
					</div>
					
					<label v-if="hasLinesInContainer" class="flex items-center gap-2 text-xs text-gray-700">
						<Checkbox v-model="countInContainer" binary inputId="stock-delivery-import-count-in-container" />
						<span>{{ ctrans("The sheet is the whole container: what is already in it counts, only the difference is added") }}</span>
					</label>

					<div class="flex flex-wrap items-center gap-3">
						<SegmentedToggle v-model="shownTab" :options="tabOptions" :ariaLabel="ctrans('Lines')" />
						<InputText v-model="search" size="small" class="min-w-[14rem] flex-1" :placeholder="ctrans('Search code, name or purchase order')" />
					</div>

					<div v-if="shownTab !== 'ignored'" class="max-h-[50vh] overflow-auto rounded border border-gray-200">
						<table class="w-full text-xs">
							<thead class="sticky top-0 z-10 whitespace-nowrap bg-gray-50 text-left text-gray-600">
								<tr>
									<th class="w-8 px-2 py-1"></th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Code") }}</th>
									<th class="px-2 py-1 text-right font-semibold">
										<span v-tooltip="ctrans('What the file says: the quantity and the purchase order it names')">{{ ctrans("From file") }}</span>
									</th>
									<th class="px-2 py-1 font-semibold">
										<span class="inline-flex items-center gap-1" v-tooltip="ctrans('The system picks the purchase order named in the file; without one, the oldest confirmed purchase order awaiting the item. Change it in the list, every link opens in a new tab.')">
											{{ ctrans("Purchase order") }}
											<span class="font-normal text-gray-400">{{ ctrans("(auto match by system)") }}</span>
											<FontAwesomeIcon :icon="faInfoCircle" class="text-gray-400" fixed-width aria-hidden="true" />
										</span>
									</th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Units to add") }}</th>
								</tr>
							</thead>
							<tbody>
								<tr
									v-for="line in shownLines"
									:key="line.key"
									class="border-t border-gray-100 align-top"
									:class="{ 'opacity-50': !lineStates[line.key]?.included }"
								>
									<td class="px-2 py-1.5">
										<Checkbox v-model="lineStates[line.key].included" binary :aria-label="ctrans('Add this line')" />
									</td>
									<td class="min-w-[14rem] px-2 py-1.5">
										<div class="whitespace-nowrap font-medium text-gray-800">
											{{ line.code }}
											<span v-if="line.sheet_code.toLowerCase() !== line.code.toLowerCase()" class="font-normal text-gray-500">({{ line.sheet_code }})</span>
										</div>
										<div class="text-gray-600">{{ line.name }}</div>
										<div class="text-gray-400">{{ rowsText(line.rows) }}</div>
									</td>
									<td class="whitespace-nowrap px-2 py-1.5 text-right">
										<div class="font-medium text-gray-800">{{ formatNumber(line.sheet_quantity) }} {{ quantityInLabel }}</div>
										<div v-if="preview.quantity_in !== 'units' && line.units !== null" class="text-gray-500">= {{ ctrans(":count units", { count: formatNumber(line.units) }) }}</div>
										<div v-if="unitsAlreadyCounted[line.key] > 0" class="text-gray-500">{{ ctrans("− :count in container", { count: formatNumber(unitsAlreadyCounted[line.key]) }) }}</div>
										<div v-if="line.sheet_purchase_order" class="text-gray-500">{{ ctrans("PO") }}: {{ line.sheet_purchase_order }}</div>
									</td>
									<td class="px-2 py-1.5">
										<Select
											v-if="purchaseOrderIdsOf(line).length > 1"
											:modelValue="lineStates[line.key].choice"
											:options="purchaseOrderOptions(line)"
											optionLabel="label"
											optionValue="value"
											size="small"
											class="w-full min-w-[16rem]"
											:disabled="!lineStates[line.key].included"
											:aria-label="ctrans('Purchase order')"
											@update:modelValue="(choice) => setChoice(line, choice)"
										/>
										<div class="mt-0.5 flex flex-wrap gap-x-3">
											<a
												v-for="item in linkedPurchaseOrders(line)"
												:key="item.purchase_order_id"
												:href="purchaseOrderUrl(item.purchase_order_slug)"
												target="_blank"
												rel="noopener"
												class="inline-flex items-center gap-1 whitespace-nowrap font-medium text-[--app-accent] hover:underline"
											>
												{{ item.purchase_order_reference }}
												<FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" fixed-width aria-hidden="true" />
											</a>
										</div>
										<div class="text-gray-500">{{ itemOf(line.item_ids[0]).supplier }}</div>
										<div class="mt-0.5 flex items-start gap-1" :class="matchNoteClass(matchNote(line).tone)">
											<FontAwesomeIcon :icon="matchNote(line).icon" class="mt-0.5" fixed-width aria-hidden="true" />
											{{ matchNote(line).text }}
										</div>
									</td>
									<td class="px-2 py-1.5">
										<div class="space-y-1">
											<div v-for="id in visibleItemIds(line)" :key="id" class="flex flex-wrap items-center gap-2">
												<a
													v-if="visibleItemIds(line).length > 1"
													:href="purchaseOrderUrl(itemOf(id).purchase_order_slug)"
													target="_blank"
													rel="noopener"
													class="w-36 truncate whitespace-nowrap text-[--app-accent] hover:underline"
												>{{ itemOf(id).purchase_order_reference }}</a>
												<InputNumber
													:modelValue="allocation[line.key]?.[id] ?? 0"
													:min="0"
													:maxFractionDigits="3"
													:suffix="` ${ctrans('units')}`"
													:disabled="!lineStates[line.key].included"
													inputClass="!w-28 !py-1 text-right"
													:aria-label="ctrans('Units to add')"
													@update:modelValue="(units) => setQuantity(line, id, units)"
												/>
												<span class="whitespace-nowrap text-gray-500">
													{{ ctrans("of :awaiting awaiting", { awaiting: formatNumber(itemOf(id).quantity_awaiting_delivery) }) }}
													<template v-if="preview.tracks_receipts">
														·
														<span :class="receivedUnits(itemOf(id)) + 0.001 < itemOf(id).quantity_awaiting_delivery ? 'text-amber-600' : 'text-green-600'">
															{{ ctrans(":received received", { received: formatNumber(receivedUnits(itemOf(id))) }) }}
														</span>
													</template>
												</span>
											</div>
											<div v-if="lineUnits(line) > 0" class="text-gray-600">= {{ breakdown(itemOf(line.item_ids[0]), lineUnits(line)) }}</div>
											<div
												v-for="(problem, index) in problems(line)"
												:key="index"
												class="flex items-start gap-1"
												:class="problem.tone === 'red' ? 'text-red-600' : 'text-amber-600'"
											>
												<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
												<span>
													{{ problem.text }}
													<a v-if="problem.link" :href="problem.link.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 whitespace-nowrap font-medium text-[--app-accent] hover:underline">
														{{ problem.link.label }}
														<FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" fixed-width aria-hidden="true" />
													</a>
												</span>
											</div>
											<button
												v-if="lineStates[line.key].overrides"
												type="button"
												class="flex items-center gap-1 text-[--app-accent] hover:underline"
												@click="resetLine(line)"
											>
												<FontAwesomeIcon :icon="faUndoAlt" fixed-width aria-hidden="true" />
												{{ ctrans("Back to the sheet quantity") }}
											</button>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
						<p v-if="shownLines.length === 0" class="p-4 text-center text-gray-500">
							{{ search ? ctrans("No lines match your search") : shownTab === "check" ? ctrans("No line needs checking") : ctrans("No line is ready to add") }}
						</p>
					</div>

					<div v-else class="max-h-[50vh] overflow-auto rounded border border-gray-200">
						<table class="w-full text-xs">
							<thead class="sticky top-0 whitespace-nowrap bg-gray-50 text-left text-gray-600">
								<tr>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Row") }}</th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Code") }}</th>
									<th class="px-2 py-1 text-right font-semibold">
										<span v-tooltip="ctrans('What the file says: the quantity and the purchase order it names')">{{ ctrans("From file") }}</span>
									</th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Purchase order") }}</th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Reason") }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="row in shownIgnored" :key="row.rows.join('-') + (row.code ?? '')" class="border-t border-gray-100 align-top">
									<td class="whitespace-nowrap px-2 py-1 text-gray-500">{{ row.rows.join(", ") }}</td>
									<td class="whitespace-nowrap px-2 py-1 font-medium text-gray-800">{{ row.code ?? "-" }}</td>
									<td class="whitespace-nowrap px-2 py-1 text-right text-gray-700">{{ row.quantity === null ? "-" : `${formatNumber(row.quantity)} ${quantityInLabel}` }}</td>
									<td class="px-2 py-1">
										<div v-if="row.sheet_purchase_order" class="whitespace-nowrap text-gray-500">{{ ctrans("File") }}: {{ row.sheet_purchase_order }}</div>
										<div v-for="purchaseOrder in row.purchase_orders" :key="purchaseOrder.slug" class="flex items-center gap-1.5">
											<a
												:href="purchaseOrderUrl(purchaseOrder.slug)"
												target="_blank"
												rel="noopener"
												class="inline-flex items-center gap-1 whitespace-nowrap font-medium text-[--app-accent] hover:underline"
											>
												{{ purchaseOrder.reference }}
												<FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" fixed-width aria-hidden="true" />
											</a>
											<span class="whitespace-nowrap text-gray-500">{{ purchaseOrderStateLabel(purchaseOrder.state) }}</span>
										</div>
										<span v-if="!row.sheet_purchase_order && !row.purchase_orders.length" class="text-gray-400">-</span>
									</td>
									<td class="px-2 py-1 font-medium text-gray-700">
										{{ row.reason }}
										<a v-if="row.needs_receiving && preview.receive_url" :href="preview.receive_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 whitespace-nowrap font-medium text-[--app-accent] hover:underline">
											{{ ctrans("Receive goods") }}
											<FontAwesomeIcon :icon="faExternalLink" class="text-[10px]" fixed-width aria-hidden="true" />
										</a>
									</td>
								</tr>
							</tbody>
						</table>
						<p v-if="shownIgnored.length === 0" class="p-4 text-center text-gray-500">
							{{ search ? ctrans("No rows match your search") : ctrans("Every row of the sheet is imported") }}
						</p>
					</div>

					<div class="flex items-start gap-2 text-xs text-gray-500">
						<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5" fixed-width aria-hidden="true" />
						{{ preview.sheet
							? ctrans(":rows rows read from tab :sheet. What is not added stays on its purchase order for another container.", { rows: preview.rows_read, sheet: preview.sheet })
							: ctrans(":rows rows read from :file. What is not added stays on its purchase order for another container.", { rows: preview.rows_read, file: preview.file }) }}
					</div>
				</template>
			</template>
		</div>

		<template #footer>
			<div class="flex w-full flex-wrap items-center justify-end gap-3">
				<span v-if="toSubmit.length" class="mr-auto text-sm text-gray-600">
					{{ ctrans(":items purchase order lines · :units units · :skos SKO", { items: toSubmit.length, units: formatNumber(totals.units), skos: formatNumber(totals.skos) }) }}
					<span v-if="notReceived.units > 0" class="text-amber-600">· {{ ctrans(":units not received", { units: formatNumber(notReceived.units) }) }}</span>
				</span>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<span v-tooltip="disabledReason ?? undefined">
					<Button
						type="tertiary"
						:label="ctransChoice('Add :count line to the container|Add :count lines to the container', toSubmit.length)"
						:loading="isSubmitting"
						:disabled="disabledReason !== null || isSubmitting"
						@click="submit"
					>
						<template #icon>
							<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</span>
			</div>
		</template>
	</Dialog>
</template>
