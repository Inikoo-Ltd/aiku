<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-17 15:01
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
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle, faBan, faArrowRight } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type TheirProduct = { code: string; cost: number; units_per_carton: number }

type Line = {
	id: number
	code: string | null
	name: string | null
	has_sko: boolean
	quantity_ordered: number
	unit_cost: number
	units_per_carton: number | null
}

type Supplier = {
	org_supplier_id: number
	code: string
	name: string
	reference: string
	products: Record<number, TheirProduct | null>
	products_count: number
}

type Options = {
	blocked_reason: string | null
	reference: string
	slug: string
	supplier: { code: string; name: string }
	currency: string | null
	lines: Line[]
	suppliers: Supplier[]
}

type ProductForm = {
	code: string
	name: string
	cost: number | null
	unitsPerCarton: number | null
}

const props = defineProps<{
	purchaseOrderId: number
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const productFields = ["code", "name", "cost", "units_per_carton"]

const options = ref<Options | null>(null)
const orgSupplierId = ref<number | null>(null)
const reference = ref("")
const productForms = ref<Record<number, ProductForm>>({})
const isFetching = ref(false)
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const supplierOptions = computed(() => (options.value?.suppliers ?? []).map((supplier) => ({ ...supplier, label: `${supplier.code} ${supplier.name}` })))
const targetSupplier = computed(() => options.value?.suppliers.find((supplier) => supplier.org_supplier_id === orgSupplierId.value) ?? null)
const lines = computed(() => options.value?.lines ?? [])

const theirProduct = (line: Line) => targetSupplier.value?.products[line.id] ?? null
const needsProduct = (line: Line) => !!targetSupplier.value && !theirProduct(line)
const linesNeedingProduct = computed(() => lines.value.filter(needsProduct))
const lineError = (line: Line, field: string) => errors.value[`lines.${line.id}.${field}`] ?? null

const isProductFormReady = (line: Line) => {
	const form = productForms.value[line.id]

	return line.has_sko && !!form && !!form.code.trim() && form.cost !== null && !!form.unitsPerCarton
}

const canSubmit = computed(() =>
	!options.value?.blocked_reason
	&& !!targetSupplier.value
	&& !!reference.value.trim()
	&& linesNeedingProduct.value.every(isProductFormReady)
)

const otherErrors = computed(() => {
	const shown = new Set(["org_supplier_id", "reference"])
	linesNeedingProduct.value.forEach((line) => productFields.forEach((field) => shown.add(`lines.${line.id}.${field}`)))

	return Object.entries(errors.value).filter(([field]) => !shown.has(field)).map(([, message]) => message)
})

watch(targetSupplier, (supplier) => {
	errors.value = {}
	reference.value = supplier?.reference ?? ""
})

watch(visible, async (isVisible) => {
	if (!isVisible) {
		return
	}

	options.value = null
	orgSupplierId.value = null
	reference.value = ""
	errors.value = {}
	isFetching.value = true
	try {
		const response = await axios.get(route("grp.models.purchase-order.change_supplier.options", { purchaseOrder: props.purchaseOrderId }))
		options.value = response.data
		productForms.value = Object.fromEntries((response.data.lines as Line[]).map((line) => [line.id, {
			code: line.code ?? "",
			name: line.name ?? "",
			cost: line.unit_cost ?? null,
			unitsPerCarton: line.units_per_carton || null,
		}]))
	} catch (error: any) {
		errors.value = { org_supplier_id: error?.response?.data?.message || ctrans("Could not load the suppliers") }
	} finally {
		isFetching.value = false
	}
}, { immediate: true })

const purchaseOrderUrlFor = (slug: string) => {
	const currentRoute = route().current()
	const params = route().params

	if (!currentRoute || params.orgSupplier) {
		return route("grp.org.procurement.purchase_orders.show", { organisation: params.organisation, purchaseOrder: slug })
	}

	return route(currentRoute, { ...params, purchaseOrder: slug })
}

async function submit() {
	if (!canSubmit.value || !options.value) {
		return
	}

	isSaving.value = true
	errors.value = {}
	try {
		const { data } = await axios.patch(route("grp.models.purchase-order.change_supplier", { purchaseOrder: props.purchaseOrderId }), {
			org_supplier_id: orgSupplierId.value,
			reference: reference.value.trim(),
			lines: linesNeedingProduct.value.map((line) => {
				const form = productForms.value[line.id]

				return { id: line.id, code: form.code, name: form.name || null, cost: form.cost, units_per_carton: form.unitsPerCarton }
			}),
		})
		visible.value = false
		if (data.slug !== options.value.slug) {
			router.visit(purchaseOrderUrlFor(data.slug), { replace: true })
		} else {
			router.reload()
		}
	} catch (error: any) {
		const responseErrors = error?.response?.data?.errors
		errors.value = responseErrors
			? Object.fromEntries(Object.entries(responseErrors).map(([field, messages]) => [field, Array.isArray(messages) ? String(messages[0]) : String(messages)]))
			: { org_supplier_id: error?.response?.data?.message || ctrans("Could not change the supplier") }
	} finally {
		isSaving.value = false
	}
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="options ? ctrans('Change supplier of :reference', { reference: options.reference }) : ctrans('Change supplier')"
		:style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div v-if="isFetching" class="space-y-3">
			<div class="skeleton h-8 rounded" />
			<div class="skeleton h-10 rounded" />
		</div>

		<div v-else-if="options" class="space-y-4 text-sm">
			<p v-if="options.blocked_reason" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
				<FontAwesomeIcon :icon="faBan" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
				<span>{{ options.blocked_reason }}</span>
			</p>

			<template v-else>
				<p class="text-xs text-gray-600">
					{{ ctrans("Use this when the goods of this order are made by another supplier of the agent than :supplier. The whole order moves to that supplier with its items, agreed prices, goods received and stages.", { supplier: options.supplier.name }) }}
				</p>

				<div class="space-y-1">
					<span class="block text-xs text-gray-500">{{ ctrans("New supplier") }} <span class="text-red-500">*</span></span>
					<Select
						v-model="orgSupplierId"
						:options="supplierOptions"
						optionLabel="label"
						optionValue="org_supplier_id"
						filter
						:placeholder="ctrans('Choose a supplier of the agent')"
						:emptyMessage="ctrans('The agent has no other supplier in :currency', { currency: options.currency ?? '' })"
						:class="fieldFocusClass"
						fluid
					>
						<template #option="{ option }">
							<div class="flex w-full min-w-0 items-center gap-2">
								<span class="whitespace-nowrap font-medium">{{ option.code }}</span>
								<span class="truncate text-gray-500">{{ option.name }}</span>
								<span
									class="ml-auto shrink-0 whitespace-nowrap text-xs"
									:class="option.products_count === lines.length ? 'text-gray-500' : 'text-amber-700'"
								>
									{{ ctrans(":count of :total items sold", { count: option.products_count, total: lines.length }) }}
								</span>
							</div>
						</template>
					</Select>
					<p v-if="errors.org_supplier_id" class="text-xs text-red-600">{{ errors.org_supplier_id }}</p>
				</div>

				<template v-if="targetSupplier">
					<div class="flex items-center gap-2 text-xs text-gray-600">
						<span class="whitespace-nowrap">{{ options.supplier.code }}</span>
						<FontAwesomeIcon :icon="faArrowRight" class="text-gray-400" fixed-width aria-hidden="true" />
						<span class="whitespace-nowrap font-medium text-gray-800">{{ targetSupplier.code }}</span>
						<span class="truncate">{{ targetSupplier.name }}</span>
					</div>

					<label class="block space-y-1">
						<span class="block text-xs text-gray-500">{{ ctrans("New reference") }} <span class="text-red-500">*</span></span>
						<InputText v-model="reference" :class="fieldFocusClass" fluid />
						<span v-if="reference.trim() !== options.reference" class="block text-xs text-gray-500">{{ ctrans("The order's URL changes with its reference") }}</span>
						<span v-if="errors.reference" class="block text-xs text-red-600">{{ errors.reference }}</span>
					</label>

					<div class="max-h-[50vh] space-y-2 overflow-y-auto pr-1">
						<div v-for="line in lines" :key="line.id" class="space-y-2 rounded-md border border-gray-200 p-3">
							<div class="flex min-w-0 items-baseline gap-2">
								<span class="whitespace-nowrap font-medium">{{ line.code }}</span>
								<span v-if="line.name" class="truncate text-xs text-gray-500">{{ line.name }}</span>
								<span class="ml-auto shrink-0 whitespace-nowrap text-xs text-gray-500">
									{{ ctrans(":quantity units at :cost", { quantity: locale.number(line.quantity_ordered), cost: locale.currencyFormatUnitCost(options.currency ?? "", line.unit_cost) }) }}
								</span>
							</div>

							<p v-if="theirProduct(line)" class="text-xs text-gray-500">
								{{ ctrans(":supplier product :code, the line keeps its agreed price", { supplier: targetSupplier.code, code: theirProduct(line)!.code }) }}
							</p>

							<p v-else-if="!line.has_sko" class="flex items-start gap-1 text-xs text-red-600">
								<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
								{{ ctrans("This item has no SKO: the new supplier's product can not be made for it") }}
							</p>

							<div v-else class="space-y-3 rounded-md border border-gray-200 bg-white p-3">
								<p class="text-xs text-gray-600">{{ ctrans(":supplier does not sell this SKO yet: its product is created for the same SKO", { supplier: targetSupplier.code }) }}</p>
								<div class="grid grid-cols-2 gap-3">
									<label class="space-y-1">
										<span class="block text-xs text-gray-500">{{ ctrans("Code") }} <span class="text-red-500">*</span></span>
										<InputText v-model="productForms[line.id].code" :class="fieldFocusClass" fluid />
										<span v-if="lineError(line, 'code')" class="block text-xs text-red-600">{{ lineError(line, "code") }}</span>
									</label>
									<label class="space-y-1">
										<span class="block text-xs text-gray-500">{{ ctrans("Unit cost") }} <span class="text-red-500">*</span></span>
										<InputNumber v-model="productForms[line.id].cost" v-bind="locale.unitCostInputOptions(options.currency)" :min="0" :class="fieldFocusClass" fluid />
										<span v-if="lineError(line, 'cost')" class="block text-xs text-red-600">{{ lineError(line, "cost") }}</span>
									</label>
									<label class="col-span-2 space-y-1">
										<span class="block text-xs text-gray-500">{{ ctrans("Name") }}</span>
										<InputText v-model="productForms[line.id].name" :class="fieldFocusClass" fluid />
										<span v-if="lineError(line, 'name')" class="block text-xs text-red-600">{{ lineError(line, "name") }}</span>
									</label>
									<label class="space-y-1">
										<span class="block text-xs text-gray-500">{{ ctrans("Units per carton") }} <span class="text-red-500">*</span></span>
										<InputNumber v-model="productForms[line.id].unitsPerCarton" :min="0" :maxFractionDigits="3" :class="fieldFocusClass" fluid />
										<span v-if="lineError(line, 'units_per_carton')" class="block text-xs text-red-600">{{ lineError(line, "units_per_carton") }}</span>
									</label>
								</div>
							</div>
						</div>
					</div>

					<p v-if="linesNeedingProduct.length" class="text-xs text-gray-500">
						{{ ctransChoice(":count new product is created for :supplier|:count new products are created for :supplier", linesNeedingProduct.length, { supplier: targetSupplier.code }) }}
					</p>
				</template>
			</template>

			<p v-for="(message, index) in otherErrors" :key="index" class="text-xs text-red-600">{{ message }}</p>
		</div>

		<p v-else-if="errors.org_supplier_id" class="text-xs text-red-600">{{ errors.org_supplier_id }}</p>

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
					:label="targetSupplier ? ctrans('Move the order to :supplier', { supplier: targetSupplier.code }) : ctrans('Change supplier')"
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
