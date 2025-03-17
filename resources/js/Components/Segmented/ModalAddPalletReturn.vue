<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { routeType } from "@/types/route"
import axios from "axios"
import { debounce, get, set } from "lodash-es"
import { router, useForm } from "@inertiajs/vue3"
import { faCloud, faCompressWide, faExpandArrowsAlt, faSearch, faSpinner } from "@fal"
import { faMinus, faPlus, faSave, faUndo } from "@fas"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import QuantityInput from "@/Components/Utils/QuantityInput.vue"
import LoadingIcon from "../Utils/LoadingIcon.vue"
import PureInputNumber from "../Pure/PureInputNumber.vue"
import NumberWithButtonSave from "../NumberWithButtonSave.vue"
import Button from "../Elements/Buttons/Button.vue"
import Icon from "../Icon.vue"

library.add( faSearch, faPlus, faMinus, faSpinner, faCloud, faUndo, faExpandArrowsAlt, faSave, faCompressWide )

const props = defineProps<{
	fetchRoute: routeType
	palletReturn: {}
}>()

const emits = defineEmits<{
	(e: "optionsList", value: any[]): void
	(e: "update:tab", value: string): void
}>()

const products = ref<any[]>([])
const optionsMeta = ref(null)
const optionsLinks = ref(null)
const isLoading = ref<string | boolean>(false)
const searchQuery = ref("")
const iconStates = ref<Record<number, { increment: string; decrement: string }>>({})
const addedProductIds = ref(new Set<number>())
const addedOrderIds = ref(new Set<number>())


const resetProducts = () => {
	products.value = []
	optionsMeta.value = null
	optionsLinks.value = null
}

const getUrlFetch = (additionalParams: {}) => {
	return route(props.fetchRoute.name, {
		...props.fetchRoute.parameters,
		...additionalParams,
	})
}

const fetchProductList = async (url?: string) => {
	isLoading.value = "fetchProduct"
	const urlToFetch = url || route(props.fetchRoute.name, props.fetchRoute.parameters)

	try {
		const response = await axios.get(urlToFetch)
		const data = response.data

		if (url && optionsLinks.value?.next) {
			products.value = [...products.value, ...data.data]
		} else {
			resetProducts()
			products.value = data.data
		}

		optionsMeta.value = data.meta
		optionsLinks.value = data.links

		if (!addedProductIds.value) {
			addedProductIds.value = new Set()
		}
		if (!addedOrderIds.value) {
			addedOrderIds.value = new Set()
		}
		data.data.forEach((product: any) => {
			if (product.purchase_order_id) {
				addedProductIds.value.add(product.purchase_order_id)
			} else if (product.order_id) {
				addedOrderIds.value.add(product.order_id)
			}
		})

		emits("optionsList", products.value)
	} catch (error) {
		console.error("Error fetching product list:", error)
	} finally {
		isLoading.value = false
	}
}

const isSearchLoading = ref(false)

const debouncedFetch = debounce(async (query: string) => {
	isSearchLoading.value = true
	try {
		const url = getUrlFetch({ "filter[global]": query.trim() || undefined })
		await fetchProductList(url)
	} finally {
		isSearchLoading.value = false
	}
}, 500)

const onSearchQuery = (query: string) => {
	debouncedFetch(query)
}

const formProducts = useForm({
	quantity_ordered: 0,
})


const onFetchNext = async () => {
	if (optionsLinks.value?.next && !isLoading.value) {
		await fetchProductList(optionsLinks.value.next)
	}
}

watch(searchQuery, (newValue) => {
	debouncedFetch(newValue)
})

onMounted(() => {
	const tableBody = document.querySelector(".p-datatable-scrollable-body")
	if (tableBody) {
		tableBody.addEventListener("scroll", debounce(onFetchNext, 200))
	}

	fetchProductList()
})

onUnmounted(() => {
	const tableBody = document.querySelector(".p-datatable-scrollable-body")
	if (tableBody) {
		tableBody.removeEventListener("scroll", onFetchNext)
	}
})

const isLoadingAttach = ref([])
const onSelectAttach = (attachRoute: routeType, idPallet: number, item: {}) => {
	router[attachRoute.method || 'post'](
		route(attachRoute.name, {...attachRoute.parameters, palletReturn: props.palletReturn.id }),
		{},
		{
			onStart: () => {
				isLoadingAttach.value.push(idPallet)
			},
			onSuccess: (e) => {
				item.id = 1  // atleast not null
			},
			onFinish: () => {
				isLoadingAttach.value = isLoadingAttach.value.filter((id) => id !== idPallet)
			},
		}
	)
}
</script>

<template>
	<div class="flex flex-col h-[600px] overflow-y-auto pb-4 px-3">
		<!-- Title -->
		<div class="flex justify-center py-2 font-semibold mb-3">
			<h2 class="text-xl">{{ trans("Pallets list") }}</h2>
		</div>

		<!-- Search and Table -->
		<div class="flex items-start gap-x-2 gap-y-2 flex-col mt-4">
			<DataTable
				:value="products.filter((product) => !product.id)"
				scrollable
				:loading="isLoading === 'fetchProduct'" class="w-full" >
				<template #header>
					<div class="flex justify-between items-center">
						<div class="flex items-center">
							<!-- <FontAwesomeIcon
								@click="onClickProduct('products')"
								icon="fal fa-compress-wide"
								v-tooltip="'maximize '"
								class="text-gray-500 hover:text-gray-700 text-lg cursor-pointer" /> -->
						</div>

						<div class="flex items-center gap-2">
							<IconField>
								<InputIcon>
									<FontAwesomeIcon
										icon="fal fa-search"
										class="text-gray-500"
										fixed-width
										aria-hidden="true" />
								</InputIcon>
								<InputText
									v-model="searchQuery"
									placeholder="Search products"
									@input="onSearchQuery(searchQuery)"
									class="border border-gray-300 rounded-lg px-4 py-2 text-sm" />
							</IconField>
						</div>
					</div>
				</template>
				<template #empty>
					<div class="w-full text-center text-gray-500">
						{{ trans('No pallets to select') }}
					</div>
				</template>

				<!-- Loading Icon -->
				<template #loading>
					<LoadingIcon class="text-xl" />
				</template>

				<Column field="type" header="Type">
					<template #body="{ data }">
						<Icon :data="data.type_icon" />
					</template>
				</Column>

				<Column field="reference" header="Reference">
					<template #body="{ data }">
						<div class="">
							<span v-if="data.reference">{{ data.reference }}</span>
							<span v-else class="text-gray-400 italic">({{ trans('No system reference') }})</span>
						</div>
						<div v-tooltip="trans('Customer reference')" class="">
							<span v-if="data.customer_reference">{{ data.customer_reference }}</span>
							<span v-else class="text-gray-400 italic">({{ trans('No pallet reference') }})</span>
						</div>
					</template>
				</Column>

				<Column field="quantity" header="Quantity">
					<template #body="{ data }">
						<pre>{{ data.id }}</pre>
						<div class="w-fit">
							<NumberWithButtonSave
								v-if="!data.id"
								:modelValue="get(data, ['quantity_selected'], 0)"
								@update:modelValue="(e) => (set(data, ['quantity_selected'], e))"
								@xonSave="(form) => (console.log('form', form), set(data, ['quantity_selected'], form.quantity))"
								noSaveButton
								noUndoButton
							/>

							<div v-else></div>
						</div>
					</template>
				</Column>

				<Column header="Action" style="width: 12%">
					<template #body="{ data }">
						<Button
							v-if="!data.id"
							@click="() => onSelectAttach(data.attachRoute, data.pallet_id, data)"
							type="tertiary"
							icon="fal fa-plus"
							:disabled="get(data, ['quantity_selected'], 0) < 1"
							v-tooltip="get(data, ['quantity_selected'], 0) < 1 ? trans('Add quantity to select') : false"
							:loading="isLoadingAttach.includes(data.pallet_id)"
							:label="trans('Select')"
						/>
					</template>
				</Column>

				<!-- <template #footer>
					<div class="text-center">
						In total there are
						{{ products ? products.length : 0 }} products.
					</div>
				</template> -->
			</DataTable>

			<!-- <pre>{{ palletReturn }}</pre> -->
		</div>
	</div>
</template>

<style scoped>
.p-datatable .p-datatable-loading-overlay {
	background: transparent !important;
	box-shadow: none !important;
}

.p-datatable .p-datatable-loading-overlay .p-datatable-loading {
	background: none !important;
	border: none !important;
	box-shadow: none !important;
}

.custom-input-number :deep(.p-inputnumber) {
	--p-inputnumber-button-width: 35px;
	height: 35px;
}

.custom-button {
	width: var(--p-inputnumber-button-width, 35px);
	height: var(--p-inputnumber-button-width, 35px);
	display: flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
	background-color: #f5f5f5;
	border-radius: 4px;
}

/* InputNumber customization */
.custom-input-number :deep(.p-inputnumber) {
	--p-inputnumber-button-width: 35px; /* Standardize width for all buttons */
	height: 35px; /* Align button height */
}

/* Optional: Hover effect for buttons */
.custom-button:hover {
	background-color: #e0e0e0;
}

.animate-spin {
	animation: spin 1s linear infinite;
}
@keyframes spin {
	0% {
		transform: rotate(0deg);
	}
	100% {
		transform: rotate(360deg);
	}
}
</style>
