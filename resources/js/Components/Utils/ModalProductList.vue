<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
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
import { debounce } from "lodash-es"
import { useForm } from "@inertiajs/vue3"
import { faCloud, faCompressWide, faExpandArrowsAlt, faSearch, faSpinner } from "@fal"
import { faMinus, faPlus, faSave, faUndo } from "@fas"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import Image from "../Image.vue"
import NumberWithButtonSave from "../NumberWithButtonSave.vue"
import LoadingIcon from "./LoadingIcon.vue"

library.add(
	faSearch,
	faPlus,
	faMinus,
	faSpinner,
	faCloud,
	faUndo,
	faExpandArrowsAlt,
	faSave,
	faCompressWide
)

const props = defineProps<{
	fetchRoute: routeType
	action: any
	current: string | number
	typeModel: string
	currentTab: string
}>()

const emits = defineEmits<{
	(e: "optionsList", value: any[]): void
	(e: "update:currentTab", value: string): void
	(e: "update:tab", value: string): void
}>()

const model = defineModel()
const products = ref<any[]>([])
const optionsMeta = ref(null)
const optionsLinks = ref(null)
const isLoading = ref<string | boolean>(false)
const searchQuery = ref("")
const iconStates = ref<Record<number, { increment: string; decrement: string }>>({})
const addedProductIds = ref(new Set<number>())
const addedOrderIds = ref(new Set<number>())
const currentTab = ref(props.current)

const handleAction = (event: { type: string; value?: number }, slotProps: any) => {
	switch (event.type) {
		case "increment":
		case "decrement":
		case "save":
			onSubmitAddProducts(action, slotProps)
			break
		case "undo":
			onUndoClick(slotProps.data.id)
			break
	}
}

// Method: click Tab
const onClickProduct = async (tabSlug: string) => {
	emits("update:currentTab", tabSlug)
	closeModal()
}

const resetIcons = (id: number) => {
	const product = products.value.find((product) => product.id === id)

	// Reset inputTriggered for the product
	if (product) {
		product.inputTriggered = false
	}

	iconStates.value[id] = {
		increment: "fal fa-plus",
		decrement: "fal fa-minus",
	}
}

const onUndoClick = (id: number) => {
	resetIcons(id)
}

const onManualInputChange = (value: number, slotProps: any) => {
	slotProps.data.quantity_ordered = value

	// Mark input as triggered and update icons
	slotProps.data.inputTriggered = true
	iconStates.value[slotProps.data.id] = {
		increment: "fal fa-cloud",
		decrement: "fal fa-undo",
	}
}

const closeModal = () => {
	model.value = false
}

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
			products.value = data.data
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
}, 300)

const onSearchQuery = (query: string) => {
	debouncedFetch(query)
}

const formProducts = useForm({
	quantity_ordered: 0,
})

const isXxLoading = ref<number | null>(null)
const onSubmitAddProducts = async (data: any, slotProps: any) => {
	const productId = slotProps.data.purchase_order_id
	const orderId = slotProps.data.order_id
	isXxLoading.value = slotProps.data.id
	try {
		if (slotProps.data.quantity_ordered > 0) {
			if (
				(addedProductIds.value && addedProductIds.value.has(productId)) ||
				(addedOrderIds.value && addedOrderIds.value.has(orderId))
			) {
				// Update product
				if (slotProps.data.purchase_order_id || slotProps.data.order_id) {
					await formProducts
						.transform(() => ({
							quantity_ordered: slotProps.data.quantity_ordered,
						}))
						.patch(
							route(slotProps?.data?.updateRoute?.name || "#", {
								...slotProps.data.updateRoute?.parameters,
							})
						)
				}
			} else if (props.typeModel === "purchase_order") {
				// Add product ,
				await formProducts
					.transform(() => ({
						quantity_ordered: slotProps.data.quantity_ordered,
					}))
					.post(
						route(data.route?.name || "#", {
							...data.route?.parameters,
							historicSupplierProduct: slotProps.data.historic_id,
							orgStock: slotProps.data.org_stock_id,
						})
					)

				// Refresh list and update addedProductIds
				await fetchProductList()
				addedProductIds.value.add(productId)
				iconStates.value[productId] = {
					increment: "fal fa-cloud",
					decrement: "fal fa-undo",
				}
			} else if (props.typeModel === "order") {
				await formProducts
					.transform(() => ({
						quantity_ordered: slotProps.data.quantity_ordered,
					}))
					.post(
						route(data.route?.name || "#", {
							...data.route?.parameters,
							historicAsset: slotProps.data.historic_id,
						})
					)

				// Refresh list and update addedProductIds
				await fetchProductList()
				addedProductIds.value.add(productId)
				iconStates.value[productId] = {
					increment: "fal fa-cloud",
					decrement: "fal fa-undo",
				}
			}
			notify({
				title: trans("Success!"),
				text: trans("Product successfully added or updated."),
				type: "success",
			})
		} else if (slotProps.data.quantity_ordered === 0) {
			// Handle delete
			if (addedProductIds.value && addedProductIds.value.has(productId)) {
				await formProducts.delete(
					route(slotProps?.data?.deleteRoute?.name || "#", {
						...slotProps.data.deleteRoute?.parameters,
					})
				)

				// Remove product ID from the addedProductIds set
				addedProductIds.value.delete(productId)

				// Refresh the list to reflect changes
				await fetchProductList()

				// Notify success
				notify({
					title: trans("Success!"),
					text: trans("Product successfully deleted."),
					type: "success",
				})
			}
		}
	} catch (error) {
		console.error("Error adding/updating/deleting product:", error)

		// Notify error
		notify({
			title: trans("Something went wrong"),
			text: trans("An error occurred while processing the product."),
			type: "error",
		})
	}

	isXxLoading.value = null
}


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
</script>

<template>
	<KeepAlive>
		<Modal :isOpen="model" @onClose="closeModal" :closeButton="true" width="w-full max-w-2xl md:max-w-5xl">
			<div class="flex flex-col justify-between h-[600px] overflow-y-auto pb-4 px-3">
				<div>
					<!-- Title -->
					<div class="flex justify-center py-2 text-gray-600 font-medium mb-3">
						<h2>Product List</h2>
					</div>

					<!-- Search and Table -->
					<div class=" flex items-start gap-x-2 gap-y-2 flex-col mt-4">
						<div class="card w-full ">
							<DataTable
								:value="products"
								scrollable
								scrollHeight="400px"
								:loading="isLoading === 'fetchProduct'">
								<template #header>
									<div class="flex justify-between items-center">
										<div class="flex items-center">
											<FontAwesomeIcon
												@click="onClickProduct('products')"
												icon="fal fa-compress-wide"
												v-tooltip="'maximize '"
												class="text-gray-500 hover:text-gray-700 text-lg cursor-pointer" />
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
								
								<template #empty> No Product found. </template>

								<!-- Loading Icon -->
								<template #loading>
									<div class="text-5xl">
										<LoadingIcon />
									</div>
								</template>

								<Column header="Image">
									<template #body="slotProps">
										<div class="w-16 h-16 rounded">
											<Image :src="slotProps.data.image_thumbnail" />
										</div>
									</template>
								</Column>
								<Column field="code" header="Code"></Column>
								<Column field="name" header="Description"></Column>
								<Column header="" style="width: 8%">
									<template #body="slotProps">
											<NumberWithButtonSave
												v-model="slotProps.data.quantity_ordered"
												:min="1"
												:isLoading="isXxLoading === slotProps.data.id"
												@onSave="(e)=> onSubmitAddProducts(action, slotProps)"
											/>

									</template>
								</Column>

								<template #footer>
									<div class="text-center">
										In total there are
										{{ products ? products.length : 0 }} products.
									</div>
								</template>
							</DataTable>
						</div>
					</div>
				</div>
			</div>
		</Modal>
	</KeepAlive>
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
