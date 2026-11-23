<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import AutoComplete from "primevue/autocomplete"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle, faArrowUp, faArrowDown, faBox, faFolder } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import {
	MAX_PRODUCTS_PER_CAROUSEL,
	PRODUCTS_PER_VIEW_OPTIONS,
	type ProductCarouselAttributes,
	type ProductCarouselRoutes,
	type ProductCarouselSource,
	familyProductsLimit,
} from "./productCarouselAttributes"

interface PickedProduct {
	code: string
	name?: string
	image?: any
	isMissing?: boolean
}

interface FamilyOption {
	code: string
	name: string
	number_current_products?: number
	state?: { tooltip?: string }
}

const props = defineProps<{
	show: boolean
	attributes: ProductCarouselAttributes
	isNew: boolean
	routes?: ProductCarouselRoutes
}>()

const emit = defineEmits<{
	(e: "close"): void
	(e: "update", value: ProductCarouselAttributes): void
}>()

const visible = ref(props.show)
const source = ref<ProductCarouselSource>(props.attributes.source)
const title = ref(props.attributes.title)
const perView = ref(String(props.attributes.perView))
const limit = ref(familyProductsLimit(props.attributes.limit))
const pickedProducts = ref<PickedProduct[]>(props.attributes.codes.map((code) => ({ code })))
const productQuery = ref<any>(null)
const productSuggestions = ref<any[]>([])
const family = ref<FamilyOption | null>(props.attributes.family ? { code: props.attributes.family, name: "" } : null)
const familySuggestions = ref<FamilyOption[]>([])
const isLoadingPicked = ref(false)
const formError = ref("")

const sourceOptions = computed(() => [
	{ label: ctrans("Products"), value: "products" as ProductCarouselSource, icon: faBox },
	{ label: ctrans("Family"), value: "family" as ProductCarouselSource, icon: faFolder },
])

const perViewOptions = PRODUCTS_PER_VIEW_OPTIONS.map((value) => ({ label: String(value), value: String(value) }))

const isFull = computed(() => pickedProducts.value.length >= MAX_PRODUCTS_PER_CAROUSEL)

const imageOf = (product: any) => product?.image_thumbnail ?? product?.web_images?.main?.thumbnail ?? product?.web_images?.main?.gallery ?? null

const routeUrl = (target: { name: string, parameters: Record<string, any> }) => route(target.name, target.parameters)

watch(
	() => props.show,
	(isShown) => (visible.value = isShown),
)

watch(source, () => (formError.value = ""))

async function loadPickedProducts() {
	if (!props.routes || !pickedProducts.value.length) {
		return
	}
	isLoadingPicked.value = true
	try {
		const response = await axios.get(routeUrl(props.routes.lookupProducts), {
			params: { codes: pickedProducts.value.map((product) => product.code).join(",") },
		})
		const found = Object.fromEntries((response.data?.data ?? []).map((product: any) => [product.code.toLowerCase(), product]))
		pickedProducts.value = pickedProducts.value.map((picked) => {
			const product = found[picked.code.toLowerCase()]

			return product
				? { code: product.code, name: product.name, image: imageOf(product), isMissing: !product.iris_url }
				: { ...picked, isMissing: true }
		})
	} finally {
		isLoadingPicked.value = false
	}
}

async function loadFamily() {
	if (!props.routes || !family.value?.code) {
		return
	}
	const response = await axios.get(routeUrl(props.routes.searchFamilies), {
		params: { "filter[global]": family.value.code, perPage: 20 },
	})
	const match = (response.data?.data ?? []).find((option: FamilyOption) => option.code.toLowerCase() === family.value?.code.toLowerCase())
	if (match) {
		family.value = match
	}
}

onMounted(() => {
	loadPickedProducts()
	loadFamily()
})

async function searchProducts(event: { query: string }) {
	if (!props.routes) {
		return
	}
	const response = await axios.get(routeUrl(props.routes.searchProducts), {
		params: { "filter[global]": event.query.trim(), perPage: 20 },
	})
	const pickedCodes = new Set(pickedProducts.value.map((product) => product.code.toLowerCase()))
	productSuggestions.value = (response.data?.data ?? []).filter((product: any) => !pickedCodes.has(product.code.toLowerCase()))
}

function addProduct(event: { value: any }) {
	const product = event.value
	productQuery.value = null
	if (!product?.code || isFull.value || pickedProducts.value.some((picked) => picked.code.toLowerCase() === product.code.toLowerCase())) {
		return
	}
	pickedProducts.value.push({ code: product.code, name: product.name, image: imageOf(product) })
	formError.value = ""
}

function moveProduct(index: number, step: number) {
	const target = index + step
	if (target < 0 || target >= pickedProducts.value.length) {
		return
	}
	const products = [...pickedProducts.value]
	;[products[index], products[target]] = [products[target], products[index]]
	pickedProducts.value = products
}

function removeProduct(index: number) {
	pickedProducts.value.splice(index, 1)
}

async function searchFamilies(event: { query: string }) {
	if (!props.routes) {
		return
	}
	const response = await axios.get(routeUrl(props.routes.searchFamilies), {
		params: { "filter[global]": event.query.trim(), perPage: 20 },
	})
	familySuggestions.value = response.data?.data ?? []
}

function closeDialog() {
	visible.value = false
	emit("close")
}

function save() {
	if (source.value === "products" && !pickedProducts.value.length) {
		formError.value = ctrans("Select at least one product.")
		return
	}
	if (source.value === "family" && !family.value?.code) {
		formError.value = ctrans("Select a family.")
		return
	}
	emit("update", {
		source: source.value,
		codes: source.value === "products" ? pickedProducts.value.map((product) => product.code) : [],
		family: source.value === "family" ? family.value?.code ?? "" : "",
		limit: familyProductsLimit(limit.value),
		title: title.value.trim(),
		perView: Number(perView.value),
	})
	closeDialog()
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		:header="isNew ? ctrans('Add product carousel') : ctrans('Edit product carousel')"
		modal
		class="w-full max-w-2xl"
		@hide="closeDialog"
	>
		<form class="flex flex-col space-y-4" @submit.prevent="save">
			<div>
				<label for="input-product-carousel-title" class="mb-2 block select-none text-sm text-gray-600">
					{{ ctrans("Title") }}
				</label>
				<InputText id="input-product-carousel-title" v-model="title" class="w-full" :placeholder="ctrans('Optional, e.g. Our Christmas picks')" />
			</div>

			<div>
				<div class="mb-2 select-none text-sm text-gray-600">{{ ctrans("Show") }} <span class="text-red-500">*</span></div>
				<SegmentedToggle v-model="source" :options="sourceOptions" :ariaLabel="ctrans('Show')" />
			</div>

			<div v-if="source === 'products'">
				<label for="input-product-carousel-product" class="mb-2 block select-none text-sm text-gray-600">
					{{ ctrans("Products") }} <span class="text-red-500">*</span>
				</label>
				<AutoComplete
					v-model="productQuery"
					inputId="input-product-carousel-product"
					:suggestions="productSuggestions"
					optionLabel="code"
					:placeholder="isFull ? ctrans('This carousel is full') : ctrans('Search by product code or name')"
					:disabled="isFull"
					:delay="300"
					fluid
					@complete="searchProducts"
					@option-select="addProduct"
				>
					<template #option="{ option }">
						<div class="flex w-full min-w-0 items-center gap-3">
							<div class="size-9 shrink-0 overflow-hidden rounded border border-gray-100 bg-white">
								<Image v-if="imageOf(option)" :src="imageOf(option)" :alt="option.code" class="size-full object-contain" />
							</div>
							<div class="min-w-0 flex-1">
								<div class="truncate text-sm font-medium">{{ option.name }}</div>
								<div class="whitespace-nowrap font-mono text-xs text-gray-500">{{ option.code }}</div>
							</div>
						</div>
					</template>
				</AutoComplete>
				<p class="mt-1 text-xs text-gray-500">
					{{ ctrans("In the order they should appear. Up to :max products from this website's shop.", { max: MAX_PRODUCTS_PER_CAROUSEL }) }}
				</p>

				<div v-if="pickedProducts.length" class="mt-3 rounded-md border border-gray-200">
					<div class="flex items-center justify-between border-b border-gray-200 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">
						<span>{{ ctrans("Selected") }} ({{ pickedProducts.length }}/{{ MAX_PRODUCTS_PER_CAROUSEL }})</span>
						<LoadingIcon v-if="isLoadingPicked" />
					</div>
					<ul class="max-h-64 divide-y divide-gray-100 overflow-y-auto">
						<li v-for="(product, index) in pickedProducts" :key="product.code" class="flex items-center gap-3 px-3 py-1.5 text-sm">
							<div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded border border-gray-100 bg-white">
								<Image v-if="product.image" :src="product.image" :alt="product.code" class="size-full object-contain" />
								<FontAwesomeIcon v-else-if="product.isMissing" :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
							</div>
							<div class="min-w-0 flex-1">
								<div class="truncate font-medium text-gray-800">{{ product.name ?? product.code }}</div>
								<div class="flex gap-2 text-xs text-gray-500">
									<span class="whitespace-nowrap font-mono">{{ product.code }}</span>
									<span v-if="product.isMissing" class="text-amber-600">{{ ctrans("No live webpage, will not show") }}</span>
								</div>
							</div>
							<div class="flex shrink-0 items-center gap-1 text-gray-400">
								<button type="button" class="hover:text-gray-700 disabled:opacity-30" :disabled="index === 0" v-tooltip="ctrans('Move up')" @click="moveProduct(index, -1)">
									<FontAwesomeIcon :icon="faArrowUp" fixed-width />
								</button>
								<button type="button" class="hover:text-gray-700 disabled:opacity-30" :disabled="index === pickedProducts.length - 1" v-tooltip="ctrans('Move down')" @click="moveProduct(index, 1)">
									<FontAwesomeIcon :icon="faArrowDown" fixed-width />
								</button>
								<button type="button" class="hover:text-red-500" v-tooltip="ctrans('Remove')" @click="removeProduct(index)">
									<FontAwesomeIcon :icon="faTimes" fixed-width />
								</button>
							</div>
						</li>
					</ul>
				</div>
			</div>

			<template v-else>
				<div>
					<label for="input-product-carousel-family" class="mb-2 block select-none text-sm text-gray-600">
						{{ ctrans("Family") }} <span class="text-red-500">*</span>
					</label>
					<AutoComplete
						v-model="family"
						inputId="input-product-carousel-family"
						:suggestions="familySuggestions"
						optionLabel="code"
						:placeholder="ctrans('Search by family code or name')"
						:delay="300"
						dropdown
						fluid
						@complete="searchFamilies"
					>
						<template #option="{ option }">
							<div class="flex w-full min-w-0 items-center gap-2">
								<span class="whitespace-nowrap font-mono text-sm font-medium">{{ option.code }}</span>
								<span class="min-w-0 flex-1 truncate text-sm text-gray-600">{{ option.name }}</span>
								<span v-if="option.number_current_products !== undefined" class="shrink-0 text-xs text-gray-400">
									{{ ctrans(":count products", { count: option.number_current_products ?? 0 }) }}
								</span>
							</div>
						</template>
					</AutoComplete>
					<p v-if="family?.name" class="mt-1 text-xs text-gray-500">
						{{ family.name }}<template v-if="family.number_current_products !== undefined"> · {{ ctrans(":count products", { count: family.number_current_products ?? 0 }) }}</template>
					</p>
					<p class="mt-1 text-xs text-gray-500">
						{{ ctrans("Shows the family's products with a live webpage, in stock first, and follows the family as products are added or removed.") }}
					</p>
				</div>

				<div>
					<label for="input-product-carousel-limit" class="mb-2 block select-none text-sm text-gray-600">
						{{ ctrans("Number of products") }}
					</label>
					<div class="w-40">
						<InputNumber v-model="limit" inputId="input-product-carousel-limit" :min="1" :max="MAX_PRODUCTS_PER_CAROUSEL" showButtons fluid />
					</div>
				</div>
			</template>

			<div>
				<div class="mb-2 select-none text-sm text-gray-600">{{ ctrans("Products shown at once on desktop") }}</div>
				<SegmentedToggle v-model="perView" :options="perViewOptions" :ariaLabel="ctrans('Products shown at once on desktop')" />
				<p class="mt-1 text-xs text-gray-500">{{ ctrans("Tablets and phones show fewer, and visitors swipe for the rest.") }}</p>
			</div>

			<p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>

			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="closeDialog">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full nativeType="submit" :label="isNew ? ctrans('Add product carousel') : ctrans('Apply')">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</form>
	</Dialog>
</template>
