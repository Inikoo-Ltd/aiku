<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Product } from "@/types/product"
import Icon from "@/Components/Icon.vue"

import { remove as loRemove } from "lodash-es"

import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faConciergeBell,
	faGarage,
	faExclamationTriangle,
	faPencil,
	faSearch,
	faThLarge,
	faListUl,
	faStar as falStar,
	faTrashAlt,
} from "@fal"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { computed, onMounted, onUnmounted, ref, watch } from "vue"
import Tag from "@/Components/Tag.vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Multiselect from "@vueform/multiselect"
import SelectButton from "primevue/selectbutton"
import { trans } from "laravel-vue-i18n"
import DataView from "primevue/dataview"
import DataTable from "primevue/datatable"
import { FilterMatchMode } from "@primevue/core/api"
import { useLocaleStore } from "@/Stores/locale"
import Column from "primevue/column"
import InputIcon from "primevue/inputicon"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import Select from "primevue/select"
import { faStar } from "@fas"
import route from "../../../../../../../vendor/tightenco/ziggy/src/js/index"
import Modal from "@/Components/Utils/Modal.vue"
import Image from "@/Components/Image.vue"

library.add(
	faConciergeBell,
	faGarage,
	faExclamationTriangle,
	faPencil,
	faSearch,
	faThLarge,
	faListUl,
	faStar,
	falStar,
	faTrashAlt
)

const props = defineProps<{
	data: {}
	tab?: string
	location?: string
	routes: {
		dataList: routeType
		submitAttach: routeType
		detach: routeType
	}
	is_manual?: boolean
	tagsList: tag[]
	tagRoute?: {}
	productRoute?: any
	orderMode?: boolean
	order_route?: routeType
}>()

interface tag {
	id: number
	slug: string
	name: string
	type: string
}

const productView = ref("list")

function productRoute(product: Product) {
	switch (route().current()) {
		case "retina.dropshipping.products.index":
			return route("retina.dropshipping.products.show", [product.slug])
		case "retina.dropshipping.portfolios.index":
		case "retina.dropshipping.platforms.portfolios.index":
			if (product.type == "StoredItem") {
				return route("retina.fulfilment.itemised_storage.stored_items.show", [product.slug])
			}

			return route("retina.dropshipping.portfolios.show", [product.slug])

		case "grp.overview.catalogue.products.index":
			return route("grp.org.shops.show.catalogue.products.current_products.show", [
				product.organisation_slug,
				product.shop_slug,
				product.slug,
			])
		default:
			return null
	}
}

const locale = useLocaleStore()
const selectedProducts = ref([])
const isLoadingSubmit = ref(false)
const tagsListTemp = ref<tag[]>(props.tagsList)
const onEditProduct = ref(false)
const actionInput = ref(1)
const optionsView = [
	{
		id: 1,
		label: trans("Grid"),
		value: "grid",
		icon: "fal fa-th-large",
	},
	{
		id: 2,
		label: trans("List"),
		value: "list",
		icon: "fal fa-list-ul",
	},
]

const isDeleting = ref(false)
const showConfirmModal = ref(false)
const productQuantities = ref<{ [key: number]: number }>({})
const productToDelete = ref<any>(null)
const isLoadingDetach = ref<string[]>([])
const sortKey = ref()
const sortOrder = ref()
const sortField = ref()
const gridSortOptions = ref([
	{ label: "Price High to Low", value: "!price" },
	{ label: "Price Low to High", value: "price" },
	{ label: "Alphabetically a-z", value: "name" },
	{ label: "Alphabetically z-a", value: "!name" },
])

const filters = ref({
	global: { value: null, matchMode: FilterMatchMode.CONTAINS },
})

const onSortChange = (event) => {
	const value = event.value.value
	const sortValue = event.value

	if (value.indexOf("!") === 0) {
		sortOrder.value = -1
		sortField.value = value.substring(1, value.length)
		sortKey.value = sortValue
	} else {
		sortOrder.value = 1
		sortField.value = value
		sortKey.value = sortValue
	}
}

// Add new Tag
const addNewTag = async (option: tag, idProduct: number) => {
	// console.log('option', option, idLocation)
	try {
		const response: any = await axios.post(
			route("grp.models.product.tag.store", idProduct),
			{ name: option.name },
			{
				headers: { "Content-Type": "multipart/form-data" },
			}
		)
		tagsListTemp.value.push(response.data.data) // (manipulation) Add new data to reactive data
		// return option
	} catch (error: any) {
		notify({
			title: "Failed to add new tag",
			text: error,
			type: "error",
		})
		// return false
	}
}

const closeModal = () => {
	showConfirmModal.value = false
}
// On update data Tags (add tag or delete tag)
const updateTagItemTable = async (tags: string[], idProduct: number) => {
	try {
		await axios.patch(route("grp.models.product.tag.attach", idProduct), { tags: tags })

		// Refetch the data of Table to update the item.tags (v-model doesn't work)
		router.reload({
			only: ["products"],
		})
	} catch (error: any) {
		notify({
			title: "Failed to update tag",
			text: error,
			type: "error",
		})
		return false
	}
}

onMounted(() => {
	if (typeof window !== "undefined") {
		document.addEventListener("keydown", (e) =>
			e.keyCode == 27 ? (onEditProduct.value = false) : ""
		)
	}
})

onUnmounted(() => {
	document.removeEventListener("keydown", () => false)
})

function isShopifyAdmin() {
	console.log(window.location.hostname)
	return window.location.hostname === "admin.shopify.com"
}

const isSelected = (item_id: number) => {
	return selectedProducts.value.some((product) => product.item_id === item_id)
}

const toggleItem = (id) => {
	console.log(id, "asdxxca")

	const index = selectedProducts.value.findIndex((item) => item.id === id)
	if (index !== -1) {
		// If item is found, remove it
		selectedProducts.value.splice(index, 1)
	} else {
		// If item is not found, add it
		selectedProducts.value.push({ id: id })
	}
}

function openConfirmModal(item: any) {
	productToDelete.value = item
	showConfirmModal.value = true
}
function cancelDelete() {
	showConfirmModal.value = false
	productToDelete.value = null
}

const onChangeDisplay = (type: string) => {
	if (productView.value == type) return
	productView.value = type
}

const updateQuantity = (item_id: number, value: string) => {
	productQuantities.value[item_id] = Number(value)
}

const selectedProductsWithQuantity = computed(() => {
	return selectedProducts.value.map((product) => ({
		id: product.item_id,
		quantity: productQuantities.value[product.item_id] || 1,
	}))
})

const onSubmitProduct = () => {
	console.log()

	router.post(
		route(props.order_route.name, props.order_route.parameters),
		{
			products: selectedProductsWithQuantity.value,
		},
		{
			headers: {
				Authorization: `Bearer ${window.sessionToken}`,
				"Content-Type": "application/x-www-form-urlencoded",
			},
			onStart: () => {
				isLoadingSubmit.value = true
			},
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text:
						trans("Successfully added") +
						` ${selectedProductsWithQuantity.value.length} ` +
						trans("Order"),
					type: "success",
				})
				selectedProductsWithQuantity.value = []
			},
			onError: () => {
				notify({
					title: trans("Failed"),
					text: trans("Something went wrong. Try again."),
					type: "error",
				})
			},
			onFinish: () => {
				isLoadingSubmit.value = false
			},
		}
	)
}

function confirmDelete() {
	if (!productToDelete.value) return

	const deleteDef = productToDelete.value.delete_product ?? {
		name: productToDelete.value.delete_product.name,
		parameters: productToDelete.value.delete_product.parameters,
		method: productToDelete.value.delete_product.method || "delete",
	}

	const verb = deleteDef.method.toLowerCase()
	console.log(productToDelete.value, "xxx")

	isDeleting.value = true
	router[verb](route(deleteDef.name, deleteDef.parameters), {
		onFinish: () => {
			isDeleting.value = false
			cancelDelete()
		},
	})
}

watch(
	() => selectedProducts.value,
	(newVal, oldVal) => {
		// Set default 1 untuk produk yang baru ditambahkan
		newVal.forEach((product) => {
			if (
				productQuantities.value[product.item_id] === undefined ||
				productQuantities.value[product.item_id] === ""
			) {
				productQuantities.value[product.item_id] = 1
			}
		})
		// Kosongkan quantity untuk produk yang dihapus
		oldVal.forEach((product) => {
			if (!newVal.some((p) => p.item_id === product.item_id)) {
				productQuantities.value[product.item_id] = ""
			}
		})
	},
	{ deep: true }
)
</script>

<template>
	<div v-if="is_manual">
		<div class="p-5">
			<div class="flex justify-end gap-x-3 mb-2">
				<SelectButton
					:modelValue="productView"
					@update:modelValue="(e: string) => onChangeDisplay(e)"
					:allowEmpty="false"
					:options="optionsView"
					optionValue="value"
					dataKey="value"
					aria-labelledby="custom">
					<template #option="{ option }">
						<FontAwesomeIcon
							:icon="option.icon"
							class=""
							fixed-width
							aria-hidden="true" />
					</template>
				</SelectButton>

				<Button
					v-if="props.orderMode"
					@click="() => onSubmitProduct()"
					:key="'buttonSubmit' + isLoadingSubmit"
					:loading="isLoadingSubmit"
					label="Submit Order"
					icon="fal fa-plus"
					:disabled="!selectedProducts.length"
					type="black" />
			</div>

			<div class="bg-stone-100 overflow-hidden rounded-2xl border border-stone-300">
				<DataTable
					v-if="productView === 'list'"
					ref="_dt"
					v-model:selection="selectedProducts"
					:value="data.data"
					dataKey="id"
					selectionMode="multiple"
					:paginator="true"
					:rows="20"
					:filters="filters"
					scrollable
					paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
					:rowsPerPageOptions="[5, 10, 20, 40]"
					currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products">
					<template #header headerStyle="background: #ff0000">
						<div class="flex flex-wrap gap-2 items-center justify-between">
							<IconField>
								<InputIcon>
									<FontAwesomeIcon
										icon="fal fa-search"
										class=""
										fixed-width
										aria-hidden="true" />
								</InputIcon>
								<InputText
									v-model="filters['global'].value"
									placeholder="Search..." />
							</IconField>
						</div>
					</template>

					<Column
						v-if="props.orderMode"
						selectionMode="multiple"
						style="width: 3rem"
						:exportable="false"
						frozen></Column>

					<Column field="code" header="Code" sortable style="min-width: 12rem">
						<template #body="{ data }">
							<Link :href="productRoute(data)" class="primaryLink">
								{{ data.code }}
							</Link>
						</template>
					</Column>

					<Column
						field="name"
						header="Name"
						sortable
						style="min-width: 16rem"
						frozen></Column>

					<Column field="quantity_left" header="Stock" style="min-width: 8rem"> </Column>
					<Column field="weight" header="Weight" style="min-width: 8rem"> </Column>
					<Column field="price" header="Price" style="min-width: 8rem">
						<template #body="{ data }">
							<div>
								{{
									useLocaleStore().currencyFormat(data.currency_code, data.price)
								}}
							</div>
						</template>
					</Column>
					<Column
						field="action"
						header="Action"
						style="min-width: 8rem; align-items: center">
						<template #body="{ data }">
							<FontAwesomeIcon
								@click="openConfirmModal(data)"
								:icon="faTrashAlt"
								class="text-red-500"
								fixed-width
								aria-hidden="true" />
						</template>
					</Column>
					<Column
						field="action"
						header="Action"
						style="min-width: 8rem"
						v-if="props.orderMode">
						<template #body="{ data }">
							<InputText
								type="number"
								v-model="productQuantities[data.item_id]"
								:disabled="!isSelected(data.item_id)"
								placeholder=""
								style="max-width: 7rem" />
						</template>
					</Column>
				</DataTable>

				<!-- View: Grid -->
				<DataView v-else :value="data.data" paginator :rows="12" :sortOrder :sortField>
					<template #header>
						<Select
							v-model="sortKey"
							:options="gridSortOptions"
							optionLabel="label"
							placeholder="Sort By Price"
							@change="onSortChange($event)" />
					</template>

					<template #list="{ items }">
						<div class="p-4 grid grid-cols-12 gap-4">
							<div
								v-for="(item, index) in items"
								:key="index"
								
								class="cursor-pointer h-full border rounded-lg flex flex-col col-span-12 sm:col-span-6 lg:col-span-3"
								>
								<!-- == {{ isSelected(item.id) }} == -->
								<div class="relative flex justify-center rounded">
									<Image
										:src="item.source"
										:imageCover="true"
										class="rounded w-full"
										style="max-width: 300px"
										:alt="'image alt'" />
								<!-- 	<div class="absolute top-1.5 left-2">
										<div
											class="capitalize text-xs inline-flex items-center gap-x-1 rounded select-none px-1.5 py-0.5 w-fit font-medium bg-emerald-100 hover:bg-emerald-200 border border-emerald-200 text-emerald-500"
											:theme="13">xx
											{{ item.state }}
										</div>
									</div> -->
									<div class="absolute top-1.5 right-2">
										<button
											@click.stop="openConfirmModal(item)"
											class="p-1 bg-white rounded-full shadow hover:bg-red-500 transition-colors">
											<FontAwesomeIcon
												:icon="faTrashAlt"
												class="text-red-500 hover:text-white w-4 h-4"
												aria-hidden="true" />
										</button>
									</div>
								</div>
								<!-- Info Block -->
								<div class="py-4 px-6 flex-1 flex flex-col justify-between">
									<!-- Code & Name -->
									<div>
										<span class="text-stone-500 text-sm">{{ item.code }}</span>
										<div class="text-lg font-medium">{{ item.name }}</div>
									</div>

									<div
										class="mt-4 grid grid-cols-3 gap-1 text-xl font-semibold text-gray-800">
										<div class="text-center">{{ item.quantity_left }}</div>
										<div class="text-center">{{ item.weight }}</div>
										<div class="text-center">
											{{
												useLocaleStore().currencyFormat(
													item.currency_code,
													item.price
												)
											}}
										</div>
									</div>
								</div>
							</div>
						</div>
					</template>
				</DataView>
			</div>
		</div>
	</div>

	<Table :resource="data" :name="tab" class="mt-5" v-else>
		<template #cell(state)="{ item: product }">
			<Icon :data="product.state"> </Icon>
		</template>

		<template #cell(slug)="{ item: product }">
			<div v-if="location !== 'pupil'">
				<Link :href="productRoute(product)" class="primaryLink">
					{{ product["slug"] }}
				</Link>
			</div>
			<div v-else>
				{{ product["slug"] }}
			</div>
		</template>

		<template #cell(shop_code)="{ item: product }">
			<Link v-if="product['shop_slug']" :href="productRoute(product)" class="secondaryLink">
				{{ product["shop_slug"] }}
			</Link>
		</template>

		<template #cell(actions)="{ item }">
			<Link
				v-if="routes?.detach?.name"
				as="button"
				:href="route(routes.detach.name, routes.detach.parameters)"
				:method="routes.detach.method"
				:data="{
					product: item.id,
				}"
				preserve-scroll
				@start="() => isLoadingDetach.push('detach' + item.id)"
				@finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
				<Button
					icon="fal fa-times"
					type="negative"
					size="xs"
					:loading="isLoadingDetach.includes('detach' + item.id)" />
			</Link>
			<Link
				:v-else="item?.delete_product?.name"
				as="button"
				:href="route(item.delete_product.name, item.delete_product.parameters)"
				:method="item.delete_product.method"
				:data="{
					product: item.id,
				}"
				preserve-scroll
				@start="() => isLoadingDetach.push('detach' + item.id)"
				@finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
				<Button
					icon="fal fa-times"
					type="negative"
					size="xs"
					:loading="isLoadingDetach.includes('detach' + item.id)" />
			</Link>
		</template>

		<template #cell(tags)="{ item }">
			<div class="min-w-[200px] relative p-0">
				<div v-if="onEditProduct !== item.slug" class="flex gap-x-1 gap-y-1.5 mb-2">
					<template v-if="item.tags?.length">
						<Tag
							v-for="tag in item.tags"
							:label="tag"
							:stringToColor="true"
							size="sm" />
					</template>
					<div v-else class="italic text-gray-400">No tags</div>

					<!-- Icon: pencil -->
					<div class="flex items-center px-1" @click="() => (onEditProduct = item.slug)">
						<FontAwesomeIcon
							icon="fal fa-pencil"
							class="text-gray-400 text-lg cursor-pointer hover:text-gray-500"
							fixed-width
							aria-hidden="true" />
					</div>
				</div>

				<div v-else>
					<Multiselect
						v-model="item.tags"
						:key="item.id"
						mode="tags"
						placeholder="Select the tag"
						valueProp="slug"
						trackBy="slug"
						label="name"
						@change="(tags) => updateTagItemTable(tags, item.id)"
						:closeOnSelect="false"
						searchable
						createOption
						:onCreate="(tag: tag) => addNewTag(tag, item.id)"
						:caret="false"
						:options="tagsListTemp"
						noResultsText="No one left. Type to add new one."
						appendNewTag>
						<template
							#tag="{
								option,
								handleTagRemove,
								disabled,
							}: {
								option: tag,
								handleTagRemove: Function,
								disabled: boolean,
							}">
							<div class="px-0.5 py-[3px]">
								<Tag
									:label="option.name"
									:closeButton="true"
									:stringToColor="true"
									size="sm"
									@onClose="(event) => handleTagRemove(option, event)" />
							</div>
						</template>
					</Multiselect>
					<div class="text-gray-400 italic text-xs">
						Press Esc to finish edit or
						<span
							@click="() => (onEditProduct = false)"
							class="hover:text-gray-500 cursor-pointer"
							>click here</span
						>.
					</div>
				</div>
			</div>
		</template>
	</Table>

	<Modal :isOpen="showConfirmModal" @onClose="closeModal" :closeButton="true" width="max-w-sm">
		<div class="px-6 pt-6">
			<h3 class="text-xl font-semibold">Confirm Deletion</h3>
		</div>

		<div class="px-6 py-4">
			<p class="text-gray-700">Are you sure you want to delete this product?</p>
		</div>

		<div class="px-6 pb-6 flex justify-end space-x-3">
			<div class="flex justify-end gap-2 p-4">
				<Button
					@click="cancelDelete"
					:key="'buttonSubmit' + isLoadingSubmit"
					:loading="isLoadingSubmit"
					label="Cancel"
					type="tertiary" />
				<Button
					@click="confirmDelete"
					:key="'buttonSubmit' + isLoadingSubmit"
					:loading="isLoadingSubmit"
					label="Confirm"
					type="red" />
			</div>
		</div>
	</Modal>
</template>

<style src="../../../../../../../node_modules/@vueform/multiselect/themes/default.css"></style>

<style lang="scss">
.multiselect-tags-search {
	@apply focus:outline-none focus:ring-0 focus:border-none h-full #{!important};
}

.multiselect.is-active {
	@apply shadow-none;
}

// .multiselect-tag {
//     @apply bg-gradient-to-r from-lime-300 to-lime-200 hover:bg-lime-400 ring-1 ring-lime-500 text-lime-600
// }

.multiselect-tags-search-wrapper {
	@apply mb-0 #{!important};
}

.multiselect-tags {
	@apply my-0.5 #{!important};
}

.multiselect-tags-search {
	@apply px-1 #{!important};
}

.multiselect-tag-remove-icon {
	@apply text-lime-800;
}
</style>
