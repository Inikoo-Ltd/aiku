<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-25 15:38
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber, { type InputNumberInputEvent } from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExternalLink, faCodeMerge } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type TargetProduct = { code: string; cost: number; units_per_carton: number }

type TargetSupplier = {
	id: number
	slug: string
	code: string
	name: string
	nickname: string | null
	company_name: string | null
	contact_name: string | null
	currency: string | null
	products: Record<number, TargetProduct | null>
	products_count: number
	merges_into: Record<number, number>
}

export type MovableSupplierProduct = {
	id: number
	code: string
	name?: string | null
	cost?: number | string | null
	currency_code?: string | null
	units_per_carton?: number | string | null
}

type ProductForm = {
	code: string
	name: string
	cost: number | null
	unitsPerCarton: number | null
}

const props = defineProps<{
	items: MovableSupplierProduct[]
	targetsRoute: routeType
}>()

const emit = defineEmits<{
	(e: "moved"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const productFields = ["code", "name", "cost", "units_per_carton"]

const targets = ref<TargetSupplier[]>([])
const isFetching = ref(false)
const supplierId = ref<number | null>(null)
const onlySuppliersWithProducts = ref(false)
const forms = ref<Record<number, ProductForm>>({})
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const isMany = computed(() => props.items.length > 1)
const supplier = computed(() => targets.value.find((target) => target.id === supplierId.value) ?? null)

const sellsEveryProduct = (target: TargetSupplier) => target.products_count === props.items.length
const sellsSomeProducts = (target: TargetSupplier) => target.products_count > 0 && !sellsEveryProduct(target)
const supplierOptions = computed(() =>
	targets.value
		.filter((target) => !onlySuppliersWithProducts.value || sellsEveryProduct(target))
		.sort((first, second) => second.products_count - first.products_count)
		.map((target) => ({
			...target,
			label: [target.code, target.company_name, target.contact_name, target.nickname].filter(Boolean).join(" · "),
			search: [target.code, target.company_name, target.contact_name, target.nickname, target.name].filter(Boolean).join(" "),
		}))
)

const twinOf = (item: MovableSupplierProduct) => supplier.value?.products[item.id] ?? null
const movingProductOf = (item: MovableSupplierProduct) => {
	const movingProductId = supplier.value?.merges_into[item.id]

	return movingProductId ? props.items.find((other) => other.id === movingProductId) ?? null : null
}
const needsProduct = (item: MovableSupplierProduct) => !!supplier.value && !twinOf(item) && !movingProductOf(item)
const productError = (item: MovableSupplierProduct, field: string) => errors.value[`products.${item.id}.${field}`] ?? null
const typedNumber = (event: InputNumberInputEvent) => (typeof event.value === "number" ? event.value : null)

const isProductReady = (item: MovableSupplierProduct) => {
	if (!needsProduct(item)) {
		return true
	}

	const form = forms.value[item.id]

	return !!form && !!form.code.trim() && form.cost !== null && !!form.unitsPerCarton
}

const canSubmit = computed(() => !!supplier.value && props.items.every(isProductReady))

const otherErrors = computed(() => {
	const shown = new Set(["supplier_id"])
	props.items.forEach((item) => {
		if (needsProduct(item)) {
			productFields.forEach((field) => shown.add(`products.${item.id}.${field}`))
		}
	})

	return Object.entries(errors.value).filter(([field]) => !shown.has(field)).map(([, message]) => message)
})

watch(onlySuppliersWithProducts, () => {
	if (supplierId.value !== null && !supplierOptions.value.some((option) => option.id === supplierId.value)) {
		supplierId.value = null
	}
})

watch(visible, async (isVisible) => {
	if (!isVisible) {
		return
	}

	supplierId.value = null
	onlySuppliersWithProducts.value = false
	errors.value = {}
	forms.value = Object.fromEntries(props.items.map((item) => [item.id, {
		code: item.code,
		name: item.name ?? "",
		cost: item.cost !== null && item.cost !== undefined && item.cost !== "" ? Number(item.cost) : null,
		unitsPerCarton: Number(item.units_per_carton) || null,
	}]))

	isFetching.value = true
	targets.value = []
	try {
		const response = await axios.get(route(props.targetsRoute.name, {
			...props.targetsRoute.parameters,
			supplier_products: props.items.map((item) => item.id),
		}))
		targets.value = response.data
	} catch (error: any) {
		errors.value = { supplier_id: error?.response?.data?.message || ctrans("Could not load the suppliers") }
	} finally {
		isFetching.value = false
	}
}, { immediate: true })

function submit() {
	if (!canSubmit.value || !supplier.value) {
		return
	}

	router.post(
		route("grp.models.supplier.supplier_products.move", { supplier: supplier.value.id }),
		{
			products: props.items.map((item) => {
				const form = forms.value[item.id]

				return {
					id: item.id,
					...(needsProduct(item) ? { code: form.code.trim(), name: form.name.trim() || null, cost: form.cost, units_per_carton: form.unitsPerCarton } : {}),
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
		:header="isMany ? ctrans('Move :count products to another supplier', { count: items.length }) : ctrans('Move :code to another supplier', { code: items[0]?.code })"
		:style="{ width: isMany ? '38rem' : '30rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="space-y-4 text-sm">
			<div class="space-y-1">
				<span class="block text-xs text-gray-500">{{ ctrans("Supplier") }} <span class="text-red-500">*</span></span>
				<Select
					v-model="supplierId"
					:options="supplierOptions"
					optionLabel="label"
					optionValue="id"
					filter
					autoFilterFocus
					:filterFields="['search']"
					:loading="isFetching"
					:placeholder="ctrans('Choose a supplier of the same agent')"
					:filterPlaceholder="ctrans('Search code, company, nickname or contact')"
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
							<span v-if="option.company_name" v-tooltip="ctrans('Company name')" class="truncate text-gray-600">{{ option.company_name }}</span>
							<span v-if="option.contact_name" v-tooltip="ctrans('Contact name')" class="truncate text-xs text-gray-400">{{ option.contact_name }}</span>
							<span
								v-if="option.nickname"
								v-tooltip="ctrans('Nickname')"
								class="shrink-0 whitespace-nowrap rounded bg-[var(--app-accent-soft)] px-1.5 py-0.5 text-xs text-[var(--app-accent-deep)]"
							>{{ option.nickname }}</span>
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
				<a
					v-if="supplier"
					:href="route('grp.supply-chain.suppliers.show', [supplier.slug])"
					target="_blank"
					rel="noopener"
					class="flex w-fit items-center gap-1 text-xs text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline"
				>
					{{ ctrans("Open supplier :supplier", { supplier: supplier.code }) }}
					<FontAwesomeIcon :icon="faExternalLink" fixed-width aria-hidden="true" />
				</a>
				<label class="flex w-fit cursor-pointer items-center gap-2 text-xs text-gray-600">
					<Checkbox v-model="onlySuppliersWithProducts" binary size="small" />
					{{ isMany
						? ctrans("Only suppliers that sell all :count products", { count: items.length })
						: ctrans("Only suppliers that sell this product") }}
				</label>
				<p v-if="errors.supplier_id" class="text-xs text-red-600">{{ errors.supplier_id }}</p>
			</div>

			<div v-if="supplier" :class="isMany ? 'max-h-[50vh] space-y-3 overflow-y-auto pr-1' : 'space-y-4'">
				<div v-for="item in items" :key="item.id" :class="isMany ? 'space-y-3 rounded-md border border-gray-200 p-3' : 'space-y-4'">
					<div v-if="isMany" class="flex min-w-0 items-baseline gap-2">
						<span class="whitespace-nowrap font-medium">{{ item.code }}</span>
						<span v-if="item.name" class="truncate text-xs text-gray-500">{{ item.name }}</span>
					</div>

					<p v-if="twinOf(item)" class="flex gap-2 rounded-md border border-green-200 bg-green-50 p-2 text-xs text-green-800">
						<FontAwesomeIcon :icon="faCodeMerge" class="mt-0.5 text-green-600" fixed-width aria-hidden="true" />
						<span>{{ ctrans(":supplier already sells this SKO as :code, :cost a unit: its SKOs are bought from :supplier and :old is discontinued", {
							supplier: supplier.code,
							code: twinOf(item)!.code,
							cost: locale.currencyFormatUnitCost(supplier.currency ?? "", twinOf(item)!.cost),
							old: item.code,
						}) }}</span>
					</p>

					<p v-else-if="movingProductOf(item)" class="flex gap-2 rounded-md border border-green-200 bg-green-50 p-2 text-xs text-green-800">
						<FontAwesomeIcon :icon="faCodeMerge" class="mt-0.5 text-green-600" fixed-width aria-hidden="true" />
						<span>{{ ctrans("Same SKO as :code :name, which moves to :supplier: its SKOs are bought from that product and :old is discontinued", {
							code: movingProductOf(item)!.code,
							name: movingProductOf(item)!.name ?? "",
							supplier: supplier.code,
							old: item.code,
						}) }}</span>
					</p>

					<div v-else-if="forms[item.id]" class="space-y-3 rounded-md border border-gray-200 p-3">
						<p class="text-xs text-gray-600">{{ ctrans(":supplier does not sell this SKO yet: the product moves there with its history, under these details", { supplier: supplier.code }) }}</p>
						<div class="grid grid-cols-2 gap-3">
							<label class="space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Code") }} <span class="text-red-500">*</span></span>
								<InputText v-model="forms[item.id].code" :class="fieldFocusClass" fluid />
								<span v-if="productError(item, 'code')" class="block text-xs text-red-600">{{ productError(item, "code") }}</span>
							</label>
							<label class="space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Unit cost") }} <span class="text-red-500">*</span></span>
								<InputNumber
									v-model="forms[item.id].cost"
									v-bind="locale.unitCostInputOptions(supplier.currency)"
									:min="0"
									:class="fieldFocusClass"
									fluid
									@input="(event: InputNumberInputEvent) => (forms[item.id].cost = typedNumber(event))"
								/>
								<span v-if="productError(item, 'cost')" class="block text-xs text-red-600">{{ productError(item, "cost") }}</span>
							</label>
							<label class="col-span-2 space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Name") }}</span>
								<InputText v-model="forms[item.id].name" :class="fieldFocusClass" fluid />
								<span v-if="productError(item, 'name')" class="block text-xs text-red-600">{{ productError(item, "name") }}</span>
							</label>
							<label class="space-y-1">
								<span class="block text-xs text-gray-500">{{ ctrans("Units per carton") }} <span class="text-red-500">*</span></span>
								<InputNumber
									v-model="forms[item.id].unitsPerCarton"
									:min="1"
									:maxFractionDigits="0"
									:class="fieldFocusClass"
									fluid
									@input="(event: InputNumberInputEvent) => (forms[item.id].unitsPerCarton = typedNumber(event))"
								/>
								<span v-if="productError(item, 'units_per_carton')" class="block text-xs text-red-600">{{ productError(item, "units_per_carton") }}</span>
							</label>
						</div>
					</div>
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
					:label="isMany ? ctransChoice('Move :count product|Move :count products', items.length) : ctrans('Move')"
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
