<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-18 11:26
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { debounce } from "lodash-es"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type SourceSupplier = {
	id: number
	agent_id: number
	label: string
	number_products: number
	company_name: string | null
	contact_name: string | null
}

type SourceProduct = {
	id: number
	code: string
	name: string | null
	state: string
	cost: string | null
	currency_code: string | null
	already_in_supplier: boolean
}

const props = defineProps<{
	copyProducts: {
		agent_id: number
		agents: { id: number, label: string }[]
		copy_route: routeType
		supplier_id: number
		source_products_route: routeType
	}
	sourceSuppliers?: SourceSupplier[]
	isLoadingSuppliers?: boolean
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const sourceAgentId = ref<number>(props.copyProducts.agent_id)
const sourceSupplierId = ref<number | null>(null)
const search = ref("")
const products = ref<SourceProduct[]>([])
const selectedProducts = ref<Record<number, boolean>>({})
const currentPage = ref(1)
const lastPage = ref(1)
const isLoadingProducts = ref(false)
const isAdding = ref(false)
const error = ref<string | null>(null)

const agentSuppliers = computed(() => (props.sourceSuppliers ?? []).filter((supplier) => supplier.agent_id === sourceAgentId.value))
const selectedIds = computed(() => Object.keys(selectedProducts.value).filter((id) => selectedProducts.value[Number(id)]).map(Number))
const selectableProducts = computed(() => products.value.filter((product) => !product.already_in_supplier))
const areAllShownSelected = computed(() => selectableProducts.value.length > 0 && selectableProducts.value.every((product) => selectedProducts.value[product.id]))

watch(visible, (isVisible) => {
	if (!isVisible) {
		return
	}
	sourceAgentId.value = props.copyProducts.agent_id
	sourceSupplierId.value = null
	resetProducts()
})

function resetProducts() {
	search.value = ""
	products.value = []
	selectedProducts.value = {}
	currentPage.value = 1
	lastPage.value = 1
	error.value = null
}

async function fetchProducts(page: number) {
	if (!sourceSupplierId.value) {
		return
	}

	isLoadingProducts.value = true
	try {
		const response = await axios.get(
			route(props.copyProducts.source_products_route.name, [...Object.values(props.copyProducts.source_products_route.parameters ?? {}), sourceSupplierId.value]),
			{ params: { page, ...(search.value ? { filter: { global: search.value } } : {}) } }
		)
		products.value = page === 1 ? response.data.data : [...products.value, ...response.data.data]
		currentPage.value = response.data.meta.current_page
		lastPage.value = response.data.meta.last_page
	} catch {
		error.value = ctrans("Could not load the products of this supplier.")
	} finally {
		isLoadingProducts.value = false
	}
}

const debouncedSearch = debounce(() => fetchProducts(1), 300)

function onSourceAgentChange() {
	sourceSupplierId.value = null
	resetProducts()
}

function onSourceSupplierChange() {
	resetProducts()
	fetchProducts(1)
}

function toggleAllShown(isChecked: boolean) {
	selectableProducts.value.forEach((product) => {
		selectedProducts.value[product.id] = isChecked
	})
}

function addProducts() {
	if (!selectedIds.value.length) {
		return
	}

	router.post(
		route(props.copyProducts.copy_route.name, [...Object.values(props.copyProducts.copy_route.parameters ?? {}), props.copyProducts.supplier_id]),
		{ supplier_products: selectedIds.value },
		{
			preserveScroll: true,
			onStart: () => {
				isAdding.value = true
				error.value = null
			},
			onFinish: () => isAdding.value = false,
			onError: (errors) => error.value = Object.values(errors)[0] as string,
			onSuccess: () => visible.value = false,
		}
	)
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="ctrans('Add products from another supplier')"
		:style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="flex flex-col gap-2">
			<p class="text-sm text-gray-600">
				{{ ctrans("The products stay with their supplier and are added to this one, linked to the same SKOs. Products this supplier already has are skipped.") }}
			</p>

			<template v-if="copyProducts.agents.length > 1">
				<label for="add-products-source-agent" class="font-medium text-gray-700">
					{{ ctrans("Agent") }}
				</label>
				<Select
					v-model="sourceAgentId"
					inputId="add-products-source-agent"
					:options="copyProducts.agents"
					optionLabel="label"
					optionValue="id"
					fluid
					@change="onSourceAgentChange"
				/>
				<p v-if="sourceAgentId !== copyProducts.agent_id" class="text-sm text-amber-600">
					{{ ctrans("Costs are converted to the supplier's currency at today's rate. Review them after adding.") }}
				</p>
			</template>

			<label for="add-products-source-supplier" class="font-medium text-gray-700">
				{{ ctrans("From supplier") }} <span class="text-red-500">*</span>
			</label>
			<Select
				v-model="sourceSupplierId"
				inputId="add-products-source-supplier"
				:options="agentSuppliers"
				optionLabel="label"
				optionValue="id"
				filter
				autoFilterFocus
				:filterFields="['label', 'company_name', 'contact_name']"
				fluid
				:loading="isLoadingSuppliers"
				:placeholder="ctrans('Select a supplier')"
				:emptyMessage="ctrans('No other supplier')"
				@change="onSourceSupplierChange"
			>
				<template #option="{ option }">
					<div class="flex w-full items-center justify-between gap-3">
						<div class="min-w-0">
							<div class="truncate">{{ option.label }}</div>
							<div v-if="option.company_name" class="truncate text-xs text-gray-500">{{ option.company_name }}</div>
						</div>
						<span class="shrink-0 text-xs tabular-nums text-gray-500">
							{{ ctransChoice(":count product|:count products", option.number_products, { count: String(option.number_products) }) }}
						</span>
					</div>
				</template>
			</Select>

			<template v-if="sourceSupplierId">
				<label for="add-products-search" class="mt-2 font-medium text-gray-700">
					{{ ctrans("Products") }} <span class="text-red-500">*</span>
				</label>
				<InputText
					id="add-products-search"
					v-model="search"
					:placeholder="ctrans('Search code or name')"
					:class="fieldFocusClass"
					fluid
					@input="debouncedSearch"
				/>

				<div class="max-h-80 overflow-y-auto rounded border border-gray-200">
					<label v-if="selectableProducts.length" class="flex cursor-pointer items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
						<Checkbox :modelValue="areAllShownSelected" binary @update:modelValue="toggleAllShown" />
						{{ ctrans("Select all shown") }}
					</label>

					<div v-if="isLoadingProducts && !products.length" class="flex flex-col gap-1 p-2">
						<div v-for="index in 5" :key="index" class="skeleton h-8 rounded" />
					</div>

					<p v-else-if="!products.length" class="px-3 py-4 text-center text-sm text-gray-500">
						{{ ctrans("No products found") }}
					</p>

					<label
						v-for="product in products"
						:key="product.id"
						class="flex items-center gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0"
						:class="product.already_in_supplier ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-[var(--app-accent-soft)]'"
					>
						<Checkbox v-model="selectedProducts[product.id]" binary :disabled="product.already_in_supplier" />
						<span class="whitespace-nowrap font-medium">{{ product.code }}</span>
						<span class="min-w-0 flex-1 truncate text-gray-600">{{ product.name }}</span>
						<span v-if="product.already_in_supplier" class="shrink-0 whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
							{{ ctrans("Already added") }}
						</span>
						<span v-else-if="product.cost !== null" class="shrink-0 tabular-nums text-gray-500">
							{{ locale.currencyFormatUnitCost(product.currency_code, product.cost) }}
						</span>
					</label>

					<div v-if="currentPage < lastPage" class="flex justify-center p-2">
						<Button type="tertiary" size="xs" :label="ctrans('Load more')" :loading="isLoadingProducts" @click="fetchProducts(currentPage + 1)" />
					</div>
					<div v-else-if="isLoadingProducts && products.length" class="flex justify-center p-2">
						<LoadingIcon />
					</div>
				</div>
			</template>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
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
					:label="ctransChoice('Add :count product|Add :count products', selectedIds.length, { count: String(selectedIds.length) })"
					:loading="isAdding"
					:disabled="!selectedIds.length"
					@click="addProducts"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
