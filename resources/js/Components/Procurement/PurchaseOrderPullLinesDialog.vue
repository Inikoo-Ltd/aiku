<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-13 08:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle, faIndustry, faCoins, faExternalLink, faBan } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type IntoOrder = {
	reference: string
	org_supplier_id: number
	supplier_code: string
	currency: string | null
	production_started: boolean
	deposit_paid: boolean
	accepts_supplier_price: boolean
}

type SourceOrder = {
	id: number
	reference: string
	slug: string
	url: string
	org_supplier_id: number
	supplier_code: string
	supplier_name: string
	state_label: string
	number_of_items: number
	movable_items: number
	production_started: boolean
	deposit_paid: boolean
}

type SourceLine = {
	id: number
	code: string
	name: string | null
	quantity_ordered: number
	pullable_quantity: number
	received_at_agent: number
	blocked_reason: string | null
	stock_delivery: { reference: string; slug: string; state_label: string } | null
	unit_cost: number
	currency: string | null
	units_per_carton: number | null
	their_product: { code: string; cost: number; units_per_carton: number } | null
}

type LineForm = {
	selected: boolean
	quantity: number | null
	code: string
	name: string
	cost: number | null
	unitsPerCarton: number | null
}

type SupplierScope = "same_supplier" | "other_suppliers"

const props = defineProps<{
	purchaseOrderId: number
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const productFields = ["code", "name", "cost", "units_per_carton"]

const into = ref<IntoOrder | null>(null)
const sourceOrders = ref<SourceOrder[]>([])
const supplierScope = ref<SupplierScope>("same_supplier")
const sourceOrderId = ref<number | null>(null)
const sourceLines = ref<SourceLine[]>([])
const lines = ref<Record<number, LineForm>>({})
const isFetchingOrders = ref(false)
const isFetchingLines = ref(false)
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const isSameSupplierOrder = (order: SourceOrder) => order.org_supplier_id === into.value?.org_supplier_id
const sameSupplierOrders = computed(() => sourceOrders.value.filter(isSameSupplierOrder))
const otherSupplierOrders = computed(() => sourceOrders.value.filter((order) => !isSameSupplierOrder(order)))
const isSameSupplier = computed(() => supplierScope.value === "same_supplier")

const scopeOptions = computed(() => [
	{
		label: into.value ? ctrans("Same supplier (:supplier)", { supplier: into.value.supplier_code }) : ctrans("Same supplier"),
		value: "same_supplier" as SupplierScope,
		count: sameSupplierOrders.value.length,
	},
	{
		label: ctrans("Other suppliers of the agent"),
		value: "other_suppliers" as SupplierScope,
		count: otherSupplierOrders.value.length,
	},
])

const orderOptions = computed(() =>
	(isSameSupplier.value ? sameSupplierOrders.value : otherSupplierOrders.value).map((order) => ({
		...order,
		label: isSameSupplier.value ? order.reference : `${order.reference} ${order.supplier_code}`,
	}))
)

const purchaseOrderShowRoutes = ["grp.org.agent.purchase_orders.show", "grp.org.procurement.purchase_orders.show", "grp.org.agent.incoming_purchase_orders.show"]
const purchaseOrderUrl = (order: SourceOrder) => {
	const currentRoute = route().current()

	return currentRoute && purchaseOrderShowRoutes.includes(currentRoute)
		? route(currentRoute, { organisation: route().params.organisation, purchaseOrder: order.slug })
		: order.url
}

const stockDeliveryUrl = (slug: string) => route(
	route().current()?.startsWith("grp.org.agent.") ? "grp.org.agent.stock_deliveries.show" : "grp.org.procurement.stock_deliveries.show",
	{ organisation: route().params.organisation, stockDelivery: slug }
)

const sourceOrder = computed(() => sourceOrders.value.find((order) => order.id === sourceOrderId.value) ?? null)
const takesNewSupplier = computed(() => !!sourceOrder.value && !isSameSupplierOrder(sourceOrder.value))

const isPullable = (line: SourceLine) => !line.blocked_reason && line.pullable_quantity > 0
const pullableLines = computed(() => sourceLines.value.filter(isPullable))
const selectedLines = computed(() => sourceLines.value.filter((line) => lines.value[line.id]?.selected))
const needsProduct = (line: SourceLine) => takesNewSupplier.value && !line.their_product
const newCost = (line: SourceLine) => line.their_product?.cost ?? lines.value[line.id]?.cost ?? null
const isCostDifferent = (line: SourceLine) => {
	if (!takesNewSupplier.value) {
		return false
	}

	const cost = newCost(line)

	return cost !== null && cost > 0 && Math.round(cost * 1e6) !== Math.round(line.unit_cost * 1e6)
}
const lineError = (line: SourceLine, field: string) => errors.value[`lines.${line.id}.${field}`] ?? null

const isLineReady = (line: SourceLine) => {
	const form = lines.value[line.id]

	return !!form
		&& !!form.quantity
		&& form.quantity > 0
		&& form.quantity <= line.pullable_quantity
		&& (!needsProduct(line) || (!!form.code.trim() && form.cost !== null && !!form.unitsPerCarton))
}

const canSubmit = computed(() => !!sourceOrder.value && selectedLines.value.length > 0 && selectedLines.value.every(isLineReady))

const isEverythingSelected = computed({
	get: () => pullableLines.value.length > 0 && pullableLines.value.every((line) => lines.value[line.id]?.selected),
	set: (isSelected: boolean) => pullableLines.value.forEach((line) => (lines.value[line.id].selected = isSelected)),
})

const warnings = computed(() => {
	const messages: { icon: any; text: string }[] = []

	if (sourceOrder.value?.production_started) {
		messages.push({ icon: faIndustry, text: ctrans(":reference is already in production: tell the supplier these units are no longer made for it. Units the agent already received stay on it.", { reference: sourceOrder.value.reference }) })
	}
	if (sourceOrder.value?.deposit_paid) {
		messages.push({ icon: faCoins, text: ctrans("A deposit was paid on :reference: its total goes down and the deposit stays on it, settle it with the supplier.", { reference: sourceOrder.value.reference }) })
	}
	if (sourceOrder.value && into.value?.production_started) {
		messages.push({ icon: faIndustry, text: ctrans(":reference is already in production: tell the supplier about the added items.", { reference: into.value.reference }) })
	}
	if (sourceOrder.value && into.value?.deposit_paid) {
		messages.push({ icon: faCoins, text: ctrans("A deposit was paid on :reference: its total goes up, the deposit paid does not change.", { reference: into.value.reference }) })
	}

	return messages
})

const otherErrors = computed(() => {
	const shown = new Set(["source_purchase_order_id"])
	selectedLines.value.forEach((line) => {
		shown.add(`lines.${line.id}.quantity`)
		if (needsProduct(line)) {
			productFields.forEach((field) => shown.add(`lines.${line.id}.${field}`))
		}
	})

	return Object.entries(errors.value).filter(([field]) => !shown.has(field)).map(([, message]) => message)
})

const blockedGroups = computed(() => {
	const groups = new Map<string, { reason: string; count: number; stockDelivery: SourceLine["stock_delivery"] }>()
	sourceLines.value.filter((line) => !isPullable(line)).forEach((line) => {
		const key = line.stock_delivery?.slug ?? line.blocked_reason ?? ""
		const group = groups.get(key) ?? { reason: line.blocked_reason ?? "", count: 0, stockDelivery: line.stock_delivery }
		group.count++
		groups.set(key, group)
	})

	return [...groups.values()]
})

const quantityHint = (line: SourceLine) => line.received_at_agent > 0
	? ctrans(":pullable of :ordered units can move, the agent received :received", {
		pullable: locale.number(line.pullable_quantity),
		ordered: locale.number(line.quantity_ordered),
		received: locale.number(line.received_at_agent),
	})
	: ctrans(":ordered units on :reference", { ordered: locale.number(line.quantity_ordered), reference: sourceOrder.value?.reference ?? "" })

watch(supplierScope, () => {
	if (sourceOrder.value && !orderOptions.value.some((order) => order.id === sourceOrder.value!.id)) {
		sourceOrderId.value = null
	}
})

watch(sourceOrderId, async (orderId) => {
	sourceLines.value = []
	lines.value = {}
	errors.value = {}

	if (orderId === null) {
		return
	}

	isFetchingLines.value = true
	try {
		const response = await axios.get(route("grp.models.purchase-order.transaction.pull.lines", { purchaseOrder: props.purchaseOrderId, source: orderId }))
		if (sourceOrderId.value !== orderId) {
			return
		}
		sourceLines.value = response.data
		lines.value = Object.fromEntries(sourceLines.value.map((line) => [line.id, {
			selected: false,
			quantity: line.pullable_quantity || null,
			code: line.code ?? "",
			name: line.name ?? "",
			cost: line.unit_cost || null,
			unitsPerCarton: Number(line.units_per_carton) || null,
		}]))
	} catch (error: any) {
		errors.value = { source_purchase_order_id: error?.response?.data?.message || ctrans("Could not load the items") }
	} finally {
		isFetchingLines.value = false
	}
})

watch(visible, async (isVisible) => {
	if (!isVisible) {
		return
	}

	supplierScope.value = "same_supplier"
	sourceOrderId.value = null
	errors.value = {}
	isFetchingOrders.value = true
	try {
		const response = await axios.get(route("grp.models.purchase-order.transaction.pull.sources", { purchaseOrder: props.purchaseOrderId }))
		into.value = response.data.into
		sourceOrders.value = response.data.orders
	} catch (error: any) {
		errors.value = { source_purchase_order_id: error?.response?.data?.message || ctrans("Could not load the orders") }
	} finally {
		isFetchingOrders.value = false
	}
}, { immediate: true })

function submit() {
	if (!canSubmit.value) {
		return
	}

	router.post(
		route("grp.models.purchase-order.transaction.pull", { purchaseOrder: props.purchaseOrderId }),
		{
			source_purchase_order_id: sourceOrderId.value,
			lines: selectedLines.value.map((line) => {
				const form = lines.value[line.id]

				return {
					id: line.id,
					quantity: form.quantity,
					...(needsProduct(line) ? { code: form.code, name: form.name || null, cost: form.cost, units_per_carton: form.unitsPerCarton } : {}),
				}
			}),
		},
		{
			preserveScroll: true,
			onStart: () => {
				isSaving.value = true
				errors.value = {}
			},
			onFinish: () => (isSaving.value = false),
			onSuccess: () => (visible.value = false),
			onError: (responseErrors: Record<string, string>) => (errors.value = responseErrors ?? {}),
		}
	)
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="into ? ctrans('Add items from another PO to :reference', { reference: into.reference }) : ctrans('Add items from another PO')"
		:style="{ width: '44rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div v-if="isFetchingOrders" class="space-y-3">
			<div class="skeleton h-8 rounded" />
			<div class="skeleton h-10 rounded" />
		</div>

		<div v-else class="space-y-4 text-sm">
			<SegmentedToggle v-model="supplierScope" :options="scopeOptions" :aria-label="ctrans('Orders of')" />

			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Take items from") }} <span class="text-red-500">*</span></span>
				<Select
					v-model="sourceOrderId"
					:options="orderOptions"
					optionLabel="label"
					optionValue="id"
					filter
					:placeholder="ctrans('Choose an open order')"
					:emptyMessage="isSameSupplier
						? ctrans(':supplier has no other open order in :currency', { supplier: into?.supplier_code ?? '', currency: into?.currency ?? '' })
						: ctrans('The other suppliers of the agent have no open order in :currency', { currency: into?.currency ?? '' })"
					:class="fieldFocusClass"
					fluid
				>
					<template #option="{ option }">
						<div class="flex w-full min-w-0 items-center gap-2">
							<span class="whitespace-nowrap font-medium">{{ option.reference }}</span>
							<span v-if="!isSameSupplier" class="truncate text-gray-500">{{ option.supplier_code }} {{ option.supplier_name }}</span>
							<FontAwesomeIcon v-if="option.production_started" v-tooltip="ctrans('In production')" :icon="faIndustry" class="ml-auto shrink-0 text-amber-500" fixed-width aria-hidden="true" />
							<FontAwesomeIcon v-if="option.deposit_paid" v-tooltip="ctrans('Deposit paid')" :icon="faCoins" class="shrink-0 text-amber-500" :class="{ 'ml-auto': !option.production_started }" fixed-width aria-hidden="true" />
							<span
								class="shrink-0 whitespace-nowrap rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
								:class="{ 'ml-auto': !option.production_started && !option.deposit_paid }"
							>
								{{ option.state_label }}
							</span>
							<span
								class="shrink-0 whitespace-nowrap text-xs"
								:class="option.movable_items ? 'text-gray-500' : 'text-amber-700'"
							>
								{{ option.movable_items === option.number_of_items
									? ctransChoice(":count item|:count items", option.number_of_items)
									: ctrans(":movable of :count items can move", { movable: option.movable_items, count: option.number_of_items }) }}
							</span>
							<a
								v-tooltip="ctrans('Open :reference in a new tab', { reference: option.reference })"
								:href="purchaseOrderUrl(option)"
								target="_blank"
								rel="noopener"
								class="shrink-0 rounded px-1 text-gray-400 hover:text-[var(--app-accent)]"
								:aria-label="ctrans('Open :reference in a new tab', { reference: option.reference })"
								@click.stop
							>
								<FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" />
							</a>
						</div>
					</template>
				</Select>
				<a
					v-if="sourceOrder"
					:href="purchaseOrderUrl(sourceOrder)"
					target="_blank"
					rel="noopener"
					class="inline-flex items-center gap-1 whitespace-nowrap text-xs text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline"
				>
					{{ ctrans("Open :reference", { reference: sourceOrder.reference }) }}
					<FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" />
				</a>
				<p v-if="errors.source_purchase_order_id" class="text-xs text-red-600">{{ errors.source_purchase_order_id }}</p>
			</div>

			<div v-for="(warning, index) in warnings" :key="index" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
				<FontAwesomeIcon :icon="warning.icon" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
				<span>{{ warning.text }}</span>
			</div>

			<div v-if="isFetchingLines" class="flex justify-center py-6">
				<LoadingIcon />
			</div>

			<template v-else-if="sourceOrder">
				<p v-if="!sourceLines.length" class="text-xs text-gray-500">{{ ctrans(":reference has no items that can move", { reference: sourceOrder.reference }) }}</p>

				<div v-else class="space-y-2">
					<div v-if="blockedGroups.length" class="space-y-1 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
						<p class="flex gap-2 font-medium">
							<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
							{{ pullableLines.length
								? ctrans(":blocked of :count items of :reference can not move", { blocked: sourceLines.length - pullableLines.length, count: sourceLines.length, reference: sourceOrder.reference })
								: ctrans("None of the :count items of :reference can move", { count: sourceLines.length, reference: sourceOrder.reference }) }}
						</p>
						<p v-for="(group, index) in blockedGroups" :key="index" class="pl-6">
							{{ ctransChoice(":count item|:count items", group.count) }}: {{ group.reason }}
							<a
								v-if="group.stockDelivery"
								:href="stockDeliveryUrl(group.stockDelivery.slug)"
								target="_blank"
								rel="noopener"
								class="ml-1 inline-flex items-center gap-1 whitespace-nowrap text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline"
							>
								{{ ctrans("Open :reference", { reference: group.stockDelivery.reference }) }}
								<FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" />
							</a>
						</p>
					</div>

					<label v-if="pullableLines.length" class="flex w-fit cursor-pointer items-center gap-2 text-xs text-gray-600">
						<Checkbox v-model="isEverythingSelected" binary size="small" :disabled="!pullableLines.length" />
						{{ ctrans("Select all :count items that can move", { count: pullableLines.length }) }}
					</label>

					<div class="max-h-[50vh] space-y-2 overflow-y-auto pr-1">
						<div
							v-for="line in sourceLines"
							:key="line.id"
							class="space-y-2 rounded-md border p-3"
							:class="lines[line.id]?.selected ? 'border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)]' : isPullable(line) ? 'border-gray-200' : 'border-gray-200 bg-gray-50'"
						>
							<label class="flex min-w-0 items-start gap-2" :class="isPullable(line) ? 'cursor-pointer' : 'cursor-not-allowed'">
								<Checkbox v-if="lines[line.id]" v-model="lines[line.id].selected" binary size="small" :disabled="!isPullable(line)" class="mt-0.5" />
								<span class="min-w-0">
									<span class="flex min-w-0 items-baseline gap-2">
										<span class="whitespace-nowrap font-medium">{{ line.code }}</span>
										<span v-if="line.name" class="truncate text-xs text-gray-500">{{ line.name }}</span>
									</span>
									<span v-if="line.blocked_reason" class="flex items-start gap-1 text-xs text-amber-700">
										<FontAwesomeIcon :icon="faBan" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
										{{ line.blocked_reason }}
									</span>
									<span v-else class="block text-xs text-gray-500">{{ quantityHint(line) }}</span>
								</span>
							</label>

							<template v-if="lines[line.id]?.selected">
								<div class="space-y-1">
									<span class="block text-xs text-gray-500">{{ ctrans("Units to move") }} <span class="text-red-500">*</span></span>
									<InputNumber v-model="lines[line.id].quantity" :min="0" :max="line.pullable_quantity" :maxFractionDigits="3" :suffix="` ${ctrans('units')}`" :class="fieldFocusClass" fluid />
									<span class="block text-xs text-gray-500">
										{{ lines[line.id].quantity !== null && lines[line.id].quantity! < line.quantity_ordered
											? ctrans(":left units stay on :reference", { left: locale.number(Math.round((line.quantity_ordered - lines[line.id].quantity!) * 1000) / 1000), reference: sourceOrder.reference })
											: ctrans("The whole line moves, it is cancelled on :reference", { reference: sourceOrder.reference }) }}
									</span>
									<p v-if="lineError(line, 'quantity')" class="text-xs text-red-600">{{ lineError(line, "quantity") }}</p>
									<p v-if="takesNewSupplier && line.their_product" class="text-xs text-gray-500">
										{{ ctrans(":supplier product :code, :cost a unit", { supplier: into?.supplier_code ?? "", code: line.their_product.code, cost: locale.currencyFormatUnitCost(into?.currency ?? "", line.their_product.cost) }) }}
									</p>
								</div>

								<div v-if="needsProduct(line)" class="space-y-3 rounded-md border border-gray-200 bg-white p-3">
									<p class="text-xs text-gray-600">{{ ctrans(":supplier does not sell this SKO yet: its product is created for the same SKO", { supplier: into?.supplier_code ?? "" }) }}</p>
									<div class="grid grid-cols-2 gap-3">
										<label class="space-y-1">
											<span class="block text-xs text-gray-500">{{ ctrans("Code") }} <span class="text-red-500">*</span></span>
											<InputText v-model="lines[line.id].code" :class="fieldFocusClass" fluid />
											<span v-if="lineError(line, 'code')" class="block text-xs text-red-600">{{ lineError(line, "code") }}</span>
										</label>
										<label class="space-y-1">
											<span class="block text-xs text-gray-500">{{ ctrans("Unit cost") }} <span class="text-red-500">*</span></span>
											<InputNumber v-model="lines[line.id].cost" v-bind="locale.unitCostInputOptions(into?.currency)" :min="0" :class="fieldFocusClass" fluid />
											<span v-if="lineError(line, 'cost')" class="block text-xs text-red-600">{{ lineError(line, "cost") }}</span>
										</label>
										<label class="col-span-2 space-y-1">
											<span class="block text-xs text-gray-500">{{ ctrans("Name") }}</span>
											<InputText v-model="lines[line.id].name" :class="fieldFocusClass" fluid />
											<span v-if="lineError(line, 'name')" class="block text-xs text-red-600">{{ lineError(line, "name") }}</span>
										</label>
										<label class="space-y-1">
											<span class="block text-xs text-gray-500">{{ ctrans("Units per carton") }} <span class="text-red-500">*</span></span>
											<InputNumber v-model="lines[line.id].unitsPerCarton" :min="0" :maxFractionDigits="3" :class="fieldFocusClass" fluid />
											<span v-if="lineError(line, 'units_per_carton')" class="block text-xs text-red-600">{{ lineError(line, "units_per_carton") }}</span>
										</label>
									</div>
								</div>

								<p v-if="isCostDifferent(line)" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
									<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
									<span>{{ into?.accepts_supplier_price
										? ctrans("The line takes the new supplier's :new a unit, it was :old", {
											new: locale.currencyFormatUnitCost(into?.currency ?? "", newCost(line) ?? 0),
											old: locale.currencyFormatUnitCost(line.currency ?? into?.currency ?? "", line.unit_cost),
										})
										: ctrans("The line keeps the agreed :old a unit and the buyer is asked to accept the new :new", {
											old: locale.currencyFormatUnitCost(line.currency ?? into?.currency ?? "", line.unit_cost),
											new: locale.currencyFormatUnitCost(into?.currency ?? "", newCost(line) ?? 0),
										}) }}</span>
								</p>
							</template>
						</div>
					</div>
				</div>
			</template>

			<p v-for="(message, index) in otherErrors" :key="index" class="text-xs text-red-600">{{ message }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					type="primary"
					full
					:label="selectedLines.length
						? ctransChoice('Move :count item here|Move :count items here', selectedLines.length)
						: ctrans('Move here')"
					:loading="isSaving"
					:disabled="!canSubmit"
					@click="submit"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
