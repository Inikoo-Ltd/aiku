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
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type SupplierOption = {
	org_supplier_id: number
	code: string
	name: string
	currency: string | null
	product: { code: string; cost: number; units_per_carton: number } | null
}

const props = defineProps<{
	item: {
		id: number
		code: string
		name?: string | null
		quantity_ordered: number | string
		unit_cost: number | string | null
		net_currency: string | null
		units_per_carton: number | string | null
		moveRoute: routeType
	}
}>()

const emit = defineEmits<{
	(e: "moved"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const suppliers = ref<SupplierOption[]>([])
const isFetching = ref(false)
const orgSupplierId = ref<number | null>(null)
const quantity = ref<number | null>(null)
const productCode = ref("")
const productName = ref("")
const cost = ref<number | null>(null)
const unitsPerCarton = ref<number | null>(null)
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const ordered = computed(() => Number(props.item.quantity_ordered))
const agreedCost = computed(() => Number(props.item.unit_cost ?? 0))
const supplier = computed(() => suppliers.value.find((option) => option.org_supplier_id === orgSupplierId.value) ?? null)
const needsProduct = computed(() => !!supplier.value && !supplier.value.product)
const newCost = computed(() => (supplier.value?.product ? supplier.value.product.cost : cost.value))
const isCostDifferent = computed(() => newCost.value !== null && newCost.value > 0 && Math.round(newCost.value * 1e6) !== Math.round(agreedCost.value * 1e6))
const fieldsWithError = ["org_supplier_id", "quantity", "code", "name", "cost", "units_per_carton"]
const otherError = computed(() => Object.entries(errors.value).find(([field]) => !fieldsWithError.includes(field) || (field !== "org_supplier_id" && field !== "quantity" && !needsProduct.value))?.[1] ?? null)
const supplierOptions = computed(() => suppliers.value.map((option) => ({ ...option, label: `${option.code} ${option.name}` })))

const canSubmit = computed(
	() =>
		!!supplier.value &&
		!!quantity.value &&
		quantity.value > 0 &&
		quantity.value <= ordered.value &&
		(!needsProduct.value || (!!productCode.value.trim() && cost.value !== null && !!unitsPerCarton.value))
)

watch(visible, async (isVisible) => {
	if (!isVisible) {
		return
	}

	orgSupplierId.value = null
	quantity.value = ordered.value
	productCode.value = props.item.code
	productName.value = props.item.name ?? ""
	cost.value = agreedCost.value || null
	unitsPerCarton.value = Number(props.item.units_per_carton) || null
	errors.value = {}

	isFetching.value = true
	try {
		const response = await axios.get(route("grp.models.purchase-order.transaction.move.suppliers", props.item.moveRoute.parameters))
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
		route(props.item.moveRoute.name, props.item.moveRoute.parameters),
		{
			org_supplier_id: orgSupplierId.value,
			quantity: quantity.value,
			...(needsProduct.value ? { code: productCode.value, name: productName.value || null, cost: cost.value, units_per_carton: unitsPerCarton.value } : {}),
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
		:header="ctrans('Move :code to another supplier', { code: item.code })"
		:style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="space-y-4 text-sm">
			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Supplier") }}</span>
				<Select
					v-model="orgSupplierId"
					:options="supplierOptions"
					optionLabel="label"
					optionValue="org_supplier_id"
					filter
					:loading="isFetching"
					:placeholder="ctrans('Choose a supplier of the same agent')"
					:emptyMessage="isFetching ? ctrans('Loading…') : ctrans('The agent has no other supplier')"
					:class="fieldFocusClass"
					fluid
				/>
				<p v-if="errors.org_supplier_id" class="text-xs text-red-600">{{ errors.org_supplier_id }}</p>
				<p v-if="supplier?.product" class="text-xs text-gray-500">
					{{ ctrans("Their product :code, :cost :currency a unit", { code: supplier.product.code, cost: locale.number(supplier.product.cost), currency: supplier.currency ?? "" }) }}
				</p>
			</div>

			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Units to move") }}</span>
				<InputNumber v-model="quantity" :min="0" :max="ordered" :maxFractionDigits="3" :suffix="` ${ctrans('units')}`" :class="fieldFocusClass" fluid />
				<span class="block text-xs text-gray-500">
					{{ quantity !== null && quantity < ordered
						? ctrans(":left units stay with this supplier", { left: locale.number(Math.round((ordered - quantity) * 1000) / 1000) })
						: ctrans("The whole line moves, it is cancelled here") }}
				</span>
				<p v-if="errors.quantity" class="text-xs text-red-600">{{ errors.quantity }}</p>
			</div>

			<div v-if="needsProduct" class="space-y-3 rounded-md border border-gray-200 p-3">
				<p class="text-xs text-gray-600">{{ ctrans(":supplier does not sell this SKO yet: its product is created for the same SKO", { supplier: supplier?.code ?? "" }) }}</p>
				<div class="grid grid-cols-2 gap-3">
					<label class="space-y-1">
						<span class="block text-xs text-gray-500">{{ ctrans("Code") }}</span>
						<InputText v-model="productCode" :class="fieldFocusClass" fluid />
						<span v-if="errors.code" class="block text-xs text-red-600">{{ errors.code }}</span>
					</label>
					<label class="space-y-1">
						<span class="block text-xs text-gray-500">{{ ctrans("Unit cost (:currency)", { currency: supplier?.currency ?? "" }) }}</span>
						<InputNumber v-model="cost" :min="0" :maxFractionDigits="6" :class="fieldFocusClass" fluid />
						<span v-if="errors.cost" class="block text-xs text-red-600">{{ errors.cost }}</span>
					</label>
					<label class="col-span-2 space-y-1">
						<span class="block text-xs text-gray-500">{{ ctrans("Name") }}</span>
						<InputText v-model="productName" :class="fieldFocusClass" fluid />
						<span v-if="errors.name" class="block text-xs text-red-600">{{ errors.name }}</span>
					</label>
					<label class="space-y-1">
						<span class="block text-xs text-gray-500">{{ ctrans("Units per carton") }}</span>
						<InputNumber v-model="unitsPerCarton" :min="0" :maxFractionDigits="3" :class="fieldFocusClass" fluid />
						<span v-if="errors.units_per_carton" class="block text-xs text-red-600">{{ errors.units_per_carton }}</span>
					</label>
				</div>
			</div>

			<p v-if="isCostDifferent" class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
				<span>{{ ctrans("The line keeps the agreed :old :currency a unit and the buyer is asked to accept the new :new :currency", { old: locale.number(agreedCost), new: locale.number(newCost ?? 0), currency: item.net_currency ?? "" }) }}</span>
			</p>

			<p v-if="otherError" class="text-xs text-red-600">{{ otherError }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" full :label="ctrans('Move')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
