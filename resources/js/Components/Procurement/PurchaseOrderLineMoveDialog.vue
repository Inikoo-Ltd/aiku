<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import AutoComplete from "primevue/autocomplete"
import Checkbox from "primevue/checkbox"
import RadioButton from "primevue/radiobutton"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle, faPlus } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type SupplierProduct = { code: string; cost: number; units_per_carton: number }

type UnsettledPurchaseOrder = {
	reference: string
	state: string
	state_label: string
	number_of_items: number
	can_take_lines: boolean
}

type SupplierOption = {
	org_supplier_id: number
	is_current: boolean
	code: string
	name: string
	currency: string | null
	reference: string
	open_purchase_orders: string[]
	unsettled_purchase_orders: UnsettledPurchaseOrder[]
	products: Record<number, SupplierProduct | null>
	products_count: number
}

type MoveItem = {
	id: number
	code: string
	name?: string | null
	quantity_ordered: number | string
	unit_cost: number | string | null
	net_currency: string | null
	units_per_carton: number | string | null
	moveRoute: routeType
}

type LineForm = {
	quantity: number | null
	code: string
	name: string
	cost: number | null
	unitsPerCarton: number | null
}

const props = defineProps<{
	items: MoveItem[]
}>()

const emit = defineEmits<{
	(e: "moved"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const productFields = ["code", "name", "cost", "units_per_carton"]

type MoveTarget = "other_supplier" | "same_supplier"

const suppliers = ref<SupplierOption[]>([])
const isFetching = ref(false)
const moveTarget = ref<MoveTarget>("other_supplier")
const onlySuppliersWithProducts = ref(false)
const orgSupplierId = ref<number | null>(null)
const purchaseOrderReference = ref("")
const referenceSuggestions = ref<string[]>([])
const lines = ref<Record<number, LineForm>>({})
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const isMany = computed(() => props.items.length > 1)
const purchaseOrderId = computed(() => props.items[0]?.moveRoute.parameters.purchaseOrder)
const isSameSupplier = computed(() => moveTarget.value === "same_supplier")
const currentSupplier = computed(() => suppliers.value.find((option) => option.is_current) ?? null)
const otherSuppliers = computed(() => suppliers.value.filter((option) => !option.is_current))
const supplier = computed(() => isSameSupplier.value
	? currentSupplier.value
	: otherSuppliers.value.find((option) => option.org_supplier_id === orgSupplierId.value) ?? null)
const typedReference = computed(() => purchaseOrderReference.value.trim())
const joinsOpenOrder = computed(() => !!supplier.value && supplier.value.open_purchase_orders.some((reference) => reference.toLowerCase() === typedReference.value.toLowerCase()))
const createsNewOrder = computed(() => !!supplier.value && !!typedReference.value && !joinsOpenOrder.value)

const sellsEveryProduct = (option: SupplierOption) => option.products_count === props.items.length
const sellsSomeProducts = (option: SupplierOption) => option.products_count > 0 && !sellsEveryProduct(option)
const supplierOptions = computed(() =>
	otherSuppliers.value
		.filter((option) => !onlySuppliersWithProducts.value || sellsEveryProduct(option))
		.sort((first, second) => second.products_count - first.products_count)
		.map((option) => ({
			...option,
			label: `${option.code} ${option.name}`,
		}))
)

const ordered = (item: MoveItem) => Number(item.quantity_ordered)
const agreedCost = (item: MoveItem) => Number(item.unit_cost ?? 0)
const productOf = (item: MoveItem) => isSameSupplier.value ? null : supplier.value?.products[item.id] ?? null
const needsProduct = (item: MoveItem) => !isSameSupplier.value && !!supplier.value && !productOf(item)
const newCost = (item: MoveItem) => productOf(item)?.cost ?? lines.value[item.id]?.cost ?? null
const isCostDifferent = (item: MoveItem) => {
	if (isSameSupplier.value) {
		return false
	}

	const cost = newCost(item)

	return cost !== null && cost > 0 && Math.round(cost * 1e6) !== Math.round(agreedCost(item) * 1e6)
}
const lineError = (item: MoveItem, field: string) => errors.value[`lines.${item.id}.${field}`] ?? null

const isLineReady = (item: MoveItem) => {
	const line = lines.value[item.id]

	return !!line
		&& !!line.quantity
		&& line.quantity > 0
		&& line.quantity <= ordered(item)
		&& (!needsProduct(item) || (!!line.code.trim() && line.cost !== null && !!line.unitsPerCarton))
}

const canSubmit = computed(() => !!supplier.value && !!typedReference.value && props.items.every(isLineReady))

const otherErrors = computed(() => {
	const shown = new Set(isSameSupplier.value ? ["reference"] : ["org_supplier_id", "reference"])
	props.items.forEach((item) => {
		shown.add(`lines.${item.id}.quantity`)
		if (needsProduct(item)) {
			productFields.forEach((field) => shown.add(`lines.${item.id}.${field}`))
		}
	})

	return Object.entries(errors.value).filter(([field]) => !shown.has(field)).map(([, message]) => message)
})

watch(supplier, (selected) => {
	purchaseOrderReference.value = selected?.reference ?? ""
})

watch(onlySuppliersWithProducts, () => {
	if (orgSupplierId.value !== null && !supplierOptions.value.some((option) => option.org_supplier_id === orgSupplierId.value)) {
		orgSupplierId.value = null
	}
})

const unsettledPurchaseOrderOf = (reference: string) =>
	supplier.value?.unsettled_purchase_orders?.find((unsettledPurchaseOrder) => unsettledPurchaseOrder.reference.toLowerCase() === reference.toLowerCase()) ?? null
const cannotTakeLines = (reference: string) => unsettledPurchaseOrderOf(reference)?.can_take_lines === false

function suggestReferences(event: { query: string }) {
	const options = [...new Set([
		supplier.value?.reference ?? "",
		...(supplier.value?.open_purchase_orders ?? []),
		...(supplier.value?.unsettled_purchase_orders ?? []).map((unsettledPurchaseOrder) => unsettledPurchaseOrder.reference),
	].filter(Boolean))]
	const query = event.query.trim().toLowerCase()
	referenceSuggestions.value = query ? options.filter((reference) => reference.toLowerCase().includes(query)) : options
}

watch(visible, async (isVisible) => {
	if (!isVisible) {
		return
	}

	moveTarget.value = "other_supplier"
	orgSupplierId.value = null
	purchaseOrderReference.value = ""
	onlySuppliersWithProducts.value = false
	errors.value = {}
	lines.value = Object.fromEntries(props.items.map((item) => [item.id, {
		quantity: ordered(item),
		code: item.code,
		name: item.name ?? "",
		cost: agreedCost(item) || null,
		unitsPerCarton: Number(item.units_per_carton) || null,
	}]))

	isFetching.value = true
	suppliers.value = []
	try {
		const response = await axios.get(route("grp.models.purchase-order.transaction.move.suppliers", {
			purchaseOrder: purchaseOrderId.value,
			transactions: props.items.map((item) => item.id),
		}))
		suppliers.value = response.data
	} catch (error: any) {
		errors.value = { org_supplier_id: error?.response?.data?.message || ctrans("Could not load the suppliers") }
	} finally {
		isFetching.value = false
	}
}, { immediate: true })

function submit() {
	if (!canSubmit.value) {
		return
	}

	router.post(
		route("grp.models.purchase-order.transaction.move_many", { purchaseOrder: purchaseOrderId.value }),
		{
			org_supplier_id: supplier.value?.org_supplier_id,
			reference: typedReference.value,
			lines: props.items.map((item) => {
				const line = lines.value[item.id]

				return {
					id: item.id,
					quantity: line.quantity,
					...(needsProduct(item) ? { code: line.code, name: line.name || null, cost: line.cost, units_per_carton: line.unitsPerCarton } : {}),
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
			onSuccess: () => {
				visible.value = false
				emit("moved")
			},
			onError: (responseErrors: Record<string, string>) => (errors.value = responseErrors ?? {}),
		}
	)
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="isMany ? ctrans('Move :count items', { count: items.length }) : ctrans('Move :code', { code: items[0]?.code })"
		:style="{ width: isMany ? '38rem' : '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="space-y-4 text-sm">
			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Move to") }}</span>
				<div class="flex flex-wrap gap-x-5 gap-y-2">
					<label class="flex cursor-pointer items-center gap-2">
						<RadioButton v-model="moveTarget" inputId="move-target-other" value="other_supplier" size="small" />
						<span>{{ ctrans("Another supplier") }}</span>
					</label>
					<label class="flex items-center gap-2" :class="currentSupplier ? 'cursor-pointer' : 'cursor-not-allowed opacity-50'">
						<RadioButton v-model="moveTarget" inputId="move-target-same" value="same_supplier" size="small" :disabled="!currentSupplier" />
						<span>{{ currentSupplier ? ctrans("Same supplier (:supplier)", { supplier: currentSupplier.code }) : ctrans("Same supplier") }}</span>
					</label>
				</div>
			</div>

			<div v-if="!isSameSupplier" class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Supplier") }} <span class="text-red-500">*</span></span>
				<Select
					v-model="orgSupplierId"
					:options="supplierOptions"
					optionLabel="label"
					optionValue="org_supplier_id"
					filter
					:loading="isFetching"
					:placeholder="ctrans('Choose a supplier of the same agent')"
					:emptyMessage="isFetching
						? ctrans('Loading…')
						: onlySuppliersWithProducts
							? ctrans('No other supplier of the agent sells these products: untick the box to see them all')
							: ctrans('The agent has no other supplier')"
					:class="fieldFocusClass"
					fluid
				>
					<template #option="{ option }">
						<div class="flex w-full min-w-0 items-center gap-2">
							<span class="whitespace-nowrap font-medium">{{ option.code }}</span>
							<span class="truncate text-gray-500">{{ option.name }}</span>
							<span
								v-if="sellsEveryProduct(option)"
								class="ml-auto flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700"
							>
								<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
								{{ isMany ? ctrans("Has all products") : ctrans("Has this product") }}
							</span>
							<span
								v-else-if="sellsSomeProducts(option)"
								class="ml-auto shrink-0 whitespace-nowrap rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
							>
								{{ ctrans("Has :count of :total", { count: option.products_count, total: items.length }) }}
							</span>
						</div>
					</template>
				</Select>
				<label class="flex cursor-pointer items-center gap-2 text-xs text-gray-600 w-fit">
					<Checkbox v-model="onlySuppliersWithProducts" binary size="small" />
					{{ isMany
						? ctrans("Only suppliers that sell all :count products", { count: items.length })
						: ctrans("Only suppliers that sell this product") }}
				</label>
				<p v-if="errors.org_supplier_id" class="text-xs text-red-600">{{ errors.org_supplier_id }}</p>
			</div>

			<div v-if="supplier" class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Purchase order") }} <span class="text-red-500">*</span></span>
				<AutoComplete
					v-model="purchaseOrderReference"
					:suggestions="referenceSuggestions"
					dropdown
					completeOnFocus
					:optionDisabled="cannotTakeLines"
					:emptySearchMessage="ctrans('Create a new purchase order :reference for :supplier', { reference: typedReference, supplier: supplier.code })"
					:class="fieldFocusClass"
					fluid
					@complete="suggestReferences"
				>
					<template #option="{ option }">
						<div class="flex w-full min-w-0 items-center gap-2">
							<span class="truncate font-medium">{{ option }}</span>
							<template v-if="unsettledPurchaseOrderOf(option)">
								<span
									v-if="cannotTakeLines(option)"
									v-tooltip="ctrans('This order can not take these items: it is in another state, in production or already has a delivery')"
									class="ml-auto shrink-0 whitespace-nowrap text-xs text-gray-400"
								>
									{{ ctrans("Can not take these items") }}
								</span>
								<span
									class="shrink-0 whitespace-nowrap rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
									:class="{ 'ml-auto': !cannotTakeLines(option) }"
								>
									{{ unsettledPurchaseOrderOf(option)!.state_label }}
								</span>
								<span class="shrink-0 whitespace-nowrap text-xs text-gray-500">
									{{ ctransChoice(":count item|:count items", unsettledPurchaseOrderOf(option)!.number_of_items) }}
								</span>
							</template>
							<span v-else class="ml-auto shrink-0 whitespace-nowrap rounded-full bg-[var(--app-accent-soft)] px-2 py-0.5 text-xs text-[var(--app-accent-deep)]">
								{{ ctrans("New") }}
							</span>
						</div>
					</template>
					<template #empty>
						<div class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700">
							<FontAwesomeIcon :icon="faPlus" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
							<span>{{ ctrans("Create a new purchase order :reference for :supplier", { reference: typedReference, supplier: supplier.code }) }}</span>
						</div>
					</template>
				</AutoComplete>
				<span class="block text-xs text-gray-500">
					{{ joinsOpenOrder
						? ctrans("Added to :reference, an open order of :supplier", { reference: typedReference, supplier: supplier.code })
						: ctrans("A new purchase order :reference is created for :supplier", { reference: typedReference, supplier: supplier.code }) }}
				</span>
				<p v-if="errors.reference" class="text-xs text-red-600">{{ errors.reference }}</p>
			</div>

			<div :class="isMany ? 'max-h-[50vh] space-y-3 overflow-y-auto pr-1' : 'space-y-4'">
				<div v-for="item in items" :key="item.id" :class="isMany ? 'space-y-3 rounded-md border border-gray-200 p-3' : 'space-y-4'">
					<div v-if="isMany" class="flex min-w-0 items-baseline gap-2">
						<span class="whitespace-nowrap font-medium">{{ item.code }}</span>
						<span v-if="item.name" class="truncate text-xs text-gray-500">{{ item.name }}</span>
					</div>

					<div v-if="lines[item.id]" class="space-y-1">
						<span class="block text-xs text-gray-500">{{ ctrans("Units to move") }} <span class="text-red-500">*</span></span>
						<InputNumber v-model="lines[item.id].quantity" :min="0" :max="ordered(item)" :maxFractionDigits="3" :suffix="` ${ctrans('units')}`" :class="fieldFocusClass" fluid />
						<span class="block text-xs text-gray-500">
							{{ lines[item.id].quantity !== null && lines[item.id].quantity! < ordered(item)
								? ctrans(":left units stay on this order", { left: locale.number(Math.round((ordered(item) - lines[item.id].quantity!) * 1000) / 1000) })
								: ctrans("The whole line moves, it is cancelled here") }}
						</span>
						<p v-if="lineError(item, 'quantity')" class="text-xs text-red-600">{{ lineError(item, "quantity") }}</p>
						<p v-if="productOf(item)" class="text-xs text-gray-500">
							{{ ctrans("Their product :code, :cost a unit", { code: productOf(item)!.code, cost: locale.currencyFormatUnitCost(supplier?.currency ?? "", productOf(item)!.cost) }) }}
						</p>
					</div>

					<div v-if="needsProduct(item) && lines[item.id]" class="space-y-3 rounded-md border border-gray-200 p-3">
						<p class="text-xs text-gray-600">{{ ctrans(":supplier does not sell this SKO yet: its product is created for the same SKO", { supplier: supplier?.code ?? "" }) }}</p>
						<div class="grid grid-cols-2 gap-3">
							<label class="space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Code") }} <span class="text-red-500">*</span></span>
								<InputText v-model="lines[item.id].code" :class="fieldFocusClass" fluid />
								<span v-if="lineError(item, 'code')" class="block text-xs text-red-600">{{ lineError(item, "code") }}</span>
							</label>
							<label class="space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Unit cost") }} <span class="text-red-500">*</span></span>
								<InputNumber
									v-model="lines[item.id].cost"
									v-bind="locale.unitCostInputOptions(supplier?.currency)"
									:min="0"
									:class="fieldFocusClass"
									fluid
								/>
								<span v-if="lineError(item, 'cost')" class="block text-xs text-red-600">{{ lineError(item, "cost") }}</span>
							</label>
							<label class="col-span-2 space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Name") }}</span>
								<InputText v-model="lines[item.id].name" :class="fieldFocusClass" fluid />
								<span v-if="lineError(item, 'name')" class="block text-xs text-red-600">{{ lineError(item, "name") }}</span>
							</label>
							<label class="space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Units per carton") }} <span class="text-red-500">*</span></span>
								<InputNumber v-model="lines[item.id].unitsPerCarton" :min="0" :maxFractionDigits="3" :class="fieldFocusClass" fluid />
								<span v-if="lineError(item, 'units_per_carton')" class="block text-xs text-red-600">{{ lineError(item, "units_per_carton") }}</span>
							</label>
						</div>
					</div>

					<p v-if="isCostDifferent(item)" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
						<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
						<span>{{ ctrans("The line keeps the agreed :old a unit and the buyer is asked to accept the new :new", {
							old: locale.currencyFormatUnitCost(item.net_currency ?? "", agreedCost(item)),
							new: locale.currencyFormatUnitCost(item.net_currency ?? "", newCost(item) ?? 0),
						}) }}</span>
					</p>
				</div>
			</div>

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
					:label="createsNewOrder
						? (isMany ? ctransChoice('Move :count item (new PO)|Move :count items (new PO)', items.length) : ctrans('Move (new PO)'))
						: (isMany ? ctransChoice('Move :count item|Move :count items', items.length) : ctrans('Move'))"
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
