<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Product } from "@/types/product"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, ref, computed, watch, nextTick } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { FontAwesomeIcon, FontAwesomeLayers } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { debounce, get, set } from "lodash-es"
import PureProgressBar from "@/Components/PureProgressBar.vue"
import {
	faConciergeBell,
	faGarage,
	faExclamationTriangle,
	faSyncAlt,
	faPencil,
	faSearch,
	faThLarge,
	faListUl,
	faStar as falStar,
	faTrashAlt,
	faExclamationCircle,
	faClone,
	faLink,
	faScrewdriver,
	faTools,
	faRecycle,
	faHandPointer,
	faHandshakeSlash,
	faHandshake,
	faTimes,
	faSkullCrossbones,
	faBan,
	faDollarSign,
	faCube,
} from "@fal"
import { faStar, faFilter, faImages, faSparkles } from "@fas"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faCheck, faCross } from "@far"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { notify } from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import axios from "axios"
import { routeType } from "@/types/route"
import { InputText, Message, Dialog, Textarea, Checkbox } from "primevue"
import QuantitySelector from "@/Components/Dropshipping/QuantitySelector.vue"
import { EditorContent } from "@tiptap/vue-3"
import Editor2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { useBundle } from "@/Composables/useBundle"

library.add(
	faSparkles,
	faImages,
	faHandshake,
	faHandshakeSlash,
	faHandPointer,
	fadExclamationTriangle,
	faSyncAlt,
	faConciergeBell,
	faGarage,
	faExclamationTriangle,
	faPencil,
	faSearch,
	faThLarge,
	faListUl,
	faStar,
	faFilter,
	falStar,
	faTrashAlt,
	faCheck,
	faExclamationCircle,
	faClone,
	faLink,
	faScrewdriver,
	faTools,
	faCross
)

interface PlatformData {
	id: number
	code: string
	name: string
	type: string
}

interface PlatformProduct {
	id: string // "gid://shopify/Product/12148498727252"
	name: string // "Aarhus Atomiser - Classic Pod - USB - Colour Change - Timer"
	slug: string // "aarhus-atomiser-classic-pod-usb-colour-change-timer"
	vendor: string // "AW-Dropship"
	images: {
		src: string
	}[] // []
}

const props = defineProps<{
	data: {}
	tab?: string
	selectedData: {
		products: number[]
	}
	routes: {
		batch_upload: routeType
		batch_match: routeType
		fetch_products: routeType
		single_create_new: routeType
		single_match: routeType
	}
	bundle_routes: {
		update: routeType
		delete: routeType
		images: routeType
		ai: any
	}
	platform_data: PlatformData
	platform_user_id: number
	is_platform_connected: boolean
	route_match: routeType
	route_create_new: routeType
	progressToUploadToShopify: {}
	isPlatformManual?: boolean
	customerSalesChannel: {}
	useCheckBox?: boolean
	progressToUploadToEcom: {}
	count_product_not_synced: number
	disabled?: boolean
}>()

const emits = defineEmits<{
	(e: 'showBulkButton'): void
	(e: 'hideBulkButton'): void
}>()

const errorBluk = ref([])
const _table = ref(null)
function portfolioRoute(product: Product) {
	if (product.type == "StoredItem") {
		return route("retina.fulfilment.itemised_storage.stored_items.show", [product.slug])
	}

	return route("retina.dropshipping.customer_sales_channels.portfolios.show", [
		route().params["customerSalesChannel"],
		product.id,
	])
}

const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", retinaLayoutStructure)
const isEbay = computed(() => props.platform_data?.type === "ebay")
const selectedProducts = defineModel<number[]>("selectedProducts")
const selectedInvalidProductsCreate = ref<number[]>([]);
// Table: Filter out-of-stock and discontinued
const compTableFilterStatus = computed(() => {
	return layout.currentQuery?.[`${props.tab}_filter`]?.status
})
const isLoadingTable = ref<null | string>(null)
const onClickFilterOutOfStock = (query: string) => {
	let xx: string | null = ""
	if (compTableFilterStatus.value === query) {
		xx = null
	} else {
		xx = query
	}

	router.reload({
		data: { [`${props.tab}_filter[status]`]: xx }, // Sent to url parameter (?tab=showcase, ?tab=menu)
		// only: [tabSlug],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
		onStart: () => {
			isLoadingTable.value = query || null
		},
		onSuccess: () => { },
		onFinish: (e) => {
			isLoadingTable.value = null
		},
		onError: (e) => { },
	})
}

// Section: Modal Shopify select variant
const isOpenModal = ref(false)
const selectedPortfolio = ref(null)
const isLoadingSubmit = ref(false)
const querySearchPortfolios = ref("")
const filteredPortfolios = computed(() => {
	if (!querySearchPortfolios.value) {
		return selectedPortfolio.value?.platform_possible_matches
	}
	return selectedPortfolio.value?.platform_possible_matches.filter((portfolio) => {
		return (
			portfolio.name.toLowerCase().includes(querySearchPortfolios.value.toLowerCase()) ||
			portfolio.code.toLowerCase().includes(querySearchPortfolios.value.toLowerCase())
		)
	})
})
const selectedVariant = ref<Product | null>(null)
const onSubmitVariant = () => {
	/* selectedVariant.value = null
	selectedPortfolio.value = null */

	/* Section: Submit */
	router.post(
		route(props.routes.single_match.name, {
			portfolio: selectedPortfolio.value?.id,
			platform_product_id: selectedVariant.value?.id,
		}),
		{
			// data: 'qqq'
		},
		{
			preserveScroll: true,
			preserveState: true,
			onStart: () => {
				isLoadingSubmit.value = true
			},
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("Successfully match the product"),
					type: "success",
				})

				isOpenModal.value = false
				setTimeout(() => {
					selectedVariant.value = null
					selectedPortfolio.value = null
				}, 700)
			},
			onError: (errors) => {
				notify({
					title: trans("Something went wrong"),
					text: errors.message ?? trans("Failed to match the product to platform"),
					type: "error",
				})
			},
			onFinish: () => {
				isLoadingSubmit.value = false
			},
		}
	)
}

const resultOfFetchPlatformProduct = ref<PlatformProduct[]>([])
const isLoadingFetchPlatformProduct = ref(false)

const fetchRoute = async () => {
	isLoadingFetchPlatformProduct.value = true
	try {
		const www = await axios.get(
			route(props.routes.fetch_products.name, {
				customerSalesChannel: props.customerSalesChannel?.id,
				query: querySearchPortfolios.value,
			})
		)

		if (!Array.isArray(www.data) || www.data.length < 50) {
			hasMore.value = false
		}

		resultOfFetchPlatformProduct.value = www.data
		// console.log('qweqw', www)
	} catch (e) {
		console.error("Error processing products", e)
	}
	isLoadingFetchPlatformProduct.value = false
}

const debounceGetPortfoliosList = debounce(() => fetchRoute(), 700)


const onChangeCheked = (checked: boolean, item: DeliveryNote) => {
	if (!selectedProducts.value) return

	const changeButtonState = disableCreateNew(item);

	if (checked) {
		if (!selectedProducts.value.includes(item.id)) {
			selectedProducts.value.push(item.id)
		}

		if (!selectedInvalidProductsCreate.value?.includes(item.id) && changeButtonState) {
			selectedInvalidProductsCreate.value?.push(item.id)
		}
	} else {
		selectedProducts.value = selectedProducts.value.filter((id) => id != item.id)

		if (changeButtonState) {
			selectedInvalidProductsCreate.value = selectedInvalidProductsCreate.value?.filter(id => id != item.id)
		}
	}

	if (selectedInvalidProductsCreate.value.length > 0) {
		emits('hideBulkButton');
	} else {
		emits('showBulkButton');
	}
}

const onCheckedAll = ({ data, allChecked }) => {
	if (!selectedProducts.value) return

	if (allChecked) {
		const newIds = data.map((row) => row.id)
		selectedProducts.value = Array.from(new Set([...selectedProducts.value, ...newIds]))
	} else {
		const uncheckIds = data.map((row) => row.id)
		selectedProducts.value = selectedProducts.value.filter((id) => !uncheckIds.includes(id))
	}
}

const onDisableCheckbox = (item) => {
	if (disableButtons(item)) {
		return true
	}

	if (
		!isEbay.value &&
		item.platform_status &&
		item.exist_in_platform &&
		item.has_valid_platform_product_id
	) {
		return true
	}
	return false
}

const disableCreateNew = (item) => {
	if (disableButtons(item)) {
		return true
	}

	if (
		!isEbay.value &&
		item.platform_status &&
		item.exist_in_platform &&
		item.has_valid_platform_product_id
	) {
		return true
	}
	return false
}

const disableButtons = (item) => {
	return item.product_state == "discontinued" || !item.is_for_sale
}

const listErrorProducts = ref({})

// Section: Modal Error Product (i.e Ebay title too long)
const selectedEditProduct = ref(null)
const selectedErrorProduct = ref(null)
const isOpenModalEditProduct = ref(false)
const isLoadingSubmitErrorTitle = ref(false)

const calculateAdjustedPrice = (
	basePrice: number,
	adjustment: number,
	type: "percent" | "fixed"
): number => {
	if (type === "percent") {
		return basePrice * (1 + adjustment / 100)
	}

	return basePrice * 1 + adjustment
}

const submitUpdateAndUploadProduct = (sel, state: "draft" | "publish") => {
	// Section: Submit
	router.post(
		route(`retina.models.portfolio.update_new_product.${state}`, {
			portfolio: sel.id,
		}),
		{
			title: sel.name,
			price: sel.customer_price,
			description: sel.description,
		},
		{
			preserveScroll: true,
			preserveState: true,
			onStart: () => {
				isLoadingSubmitErrorTitle.value = true
			},
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("Successfully submit the data"),
					type: "success",
				})
				isOpenModalEditProduct.value = false
			},
			onError: (errors) => {
				notify({
					title: trans("Something went wrong"),
					text: trans("Try again or contact administrator"),
					type: "error",
				})
			},
			onFinish: () => {
				isLoadingSubmitErrorTitle.value = false
			},
		}
	)
}

const calculateVat = (price: number) => {
	return price * 1 + price * props.customerSalesChannel.vat_rate
}

let observer = null
const sentinel = ref(null)
const currentOffset = ref(0)
const hasMore = ref(true)
const isLoadingMore = ref(false)

if (props.platform_data?.type === "ebay") {
	watch(sentinel, async (element) => {
		if (!element) return
		await nextTick()
		observer = new IntersectionObserver(([entry]) => {
			if (entry.isIntersecting && hasMore) {
				loadMore()
			}
		})
		observer.observe(element)
	})

	const loadMore = async () => {
		if (resultOfFetchPlatformProduct.value.length < 50 || !hasMore.value) {
			hasMore.value = false
			return
		}
		currentOffset.value += 50
		isLoadingMore.value = true
		try {
			const www = await axios.get(
				route(props.routes.fetch_products.name, {
					customerSalesChannel: props.customerSalesChannel?.id,
					offset: currentOffset.value,
				})
			)
			if (!Array.isArray(www.data) || www.data.length < 50) {
				console.log("Doesn't have more")
				hasMore.value = false
			}
			isLoadingMore.value = false
			resultOfFetchPlatformProduct.value = [
				...resultOfFetchPlatformProduct.value,
				...www.data,
			]
		} catch (e) {
			console.error("Error processing products", e)
			isLoadingMore.value = false
		}
	}
}

const onClickFilterForSale = (query: string) => {
	let xx: string | null = "true"
	if (compTableFilterForSale.value === query) {
		xx = null
	} else {
		xx = query
	}

	router.reload({
		data: { [`${props.tab}_filter[is_for_sale]`]: xx }, // Sent to url parameter (?tab=showcase, ?tab=menu)
		// only: [tabSlug],  // Only reload the props with dynamic name tabSlug (i.e props.showcase, props.menu)
		onStart: () => {
			isLoadingTable.value = query || null
		},
		onSuccess: () => { },
		onFinish: (e) => {
			isLoadingTable.value = null
		},
		onError: (e) => { },
	})
}

const compTableFilterForSale = computed(() => {
	return layout.currentQuery?.[`${props.tab}_filter`]?.is_for_sale
})

// action bundle edit
const openEditModal = (item: any) => {
	isOpenModalEditProduct.value = true

	selectedEditProduct.value = {
		...item,
		basePrice: item?.customer_price,
	}
	fetchEditMediaGallery()
}

const isLoadingMedia = ref(false)
const showMediaModal = ref(false)
const selectedMedia = ref<any[]>([])
const isGeneratingAI = ref(false)
const selectedMediaIds = ref<number[]>([])
const mediaGallery = ref<string[]>([])
const editMediaGallery = ref<string[]>([])
const showGenerateModal = ref(false)
const isSubmitBundle = ref(false)
const aiPrompt = ref('')
const selectedMediaForAI = ref<any[]>([])
const selectedMediaAIIds = ref<any[]>([])
const bundleItems = ref<any[]>([])

const fetchEditMediaGallery = async () => {
	try {
		isLoadingMedia.value = true

		const routeParams = {
			...props.bundle_routes.images.edit.parameters,
			bundle: [selectedEditProduct.value.bundle_id]
		}
		const url = route(
			props.bundle_routes.images.edit.name,
			routeParams
		)
		const response = await axios.get(url)
		const data = response.data.data || {}

		editMediaGallery.value = data

		if (data.current_images) {
			selectedMedia.value = Object.entries(data.current_images).map(
				([image_id, img]: any, index) => ({
					key: String(image_id),
					image_id: Number(image_id),
					image: img,
					url: img.original,
					is_main: index === 0 // default first jadi main
				})
			)
			selectedMediaIds.value = selectedMedia.value.map(m => m.image_id)
		}
		if (data.items) {
			bundleItems.value = data.items.map((i: any) => ({
				id: i.bundle_item_id,
				quantity: i.quantity,
				product_id: i.item.id,
				name: i.item.name,
				image: i.item.images?.[0]?.source?.original || null,
				raw: i
			}))
		}
	} catch (e) {
		console.error(e)

		notify({
			title: trans('Error'),
			text: trans('Failed to load media'),
			type: 'error'
		})
	} finally {
		isLoadingMedia.value = false
	}
}

const updateItemQty = (id: number, qty: number) => {
	bundleItems.value = bundleItems.value.map(item =>
		item.id === id
			? { ...item, quantity: qty }
			: item
	)
}

const fetchMediaGallery = async () => {
	try {
		isLoadingMedia.value = true

		const url = route(
			props.bundle_routes.images.get.name,
			{
				product_ids: [selectedEditProduct.value.bundle_id]
			}
		)
		const response = await axios.get(url)
		mediaGallery.value = response.data.data || []
	} catch (e) {
		console.error(e)

		notify({
			title: trans('Error'),
			text: trans('Failed to load media'),
			type: 'error'
		})
	} finally {
		isLoadingMedia.value = false
	}
}

const flatMediaGallery = computed(() => {

	const result: any[] = []

	mediaGallery.value.forEach(product => {

		if (!product.image) return

		Object.entries(product.image).forEach(([imageId, imageData]: any) => {

			result.push({
				product_id: product.id,
				image_id: Number(imageId),
				key: `${product.id}-${imageId}`,
				url: imageData.original,
				image: imageData
			})

		})

	})
	return result
})

const toggleSelect = (img: any) => {

	const index = selectedMediaIds.value.indexOf(img.key)

	if (index !== -1) {

		selectedMediaIds.value.splice(index, 1)

		selectedMedia.value =
			selectedMedia.value.filter(m => m.key !== img.key)

	} else {

		selectedMediaIds.value.push(img.key)

		selectedMedia.value.push({
			key: img.key,
			image_id: img.image_id,
			product_id: img.product_id,
			url: img.url,
			image: img.image,
			is_main: false
		})

	}
}

const generateAITitle = async () => {
	try {
		isGeneratingAI.value = true
		
		const { data } = await axios.post(
			route(
				props.bundle_routes.ai.generate_title.name
			),
			{
				products: bundleItems.value.map(item => item.product_id)
			}
		)
		selectedEditProduct.value.name = data
		notify({
			title: trans('Success'),
			text: trans('Success generate AI'),
			type: 'success'
		})
	} catch (e) {
		notify({
			title: trans('Error'),
			text: trans('Failed to generate AI'),
			type: 'error'
		})
	} finally {
		isGeneratingAI.value = false
	}
}

const generateAIDescription = async () => {
	try {
		isGeneratingAI.value = true
		
		const { data } = await axios.post(
			route(
				props.bundle_routes.ai.generate_description.name
			),
			{
				products: bundleItems.value.map(item => item.product_id)
			}
		)
		selectedEditProduct.value.description = data

		notify({
			title: trans('Success'),
			text: trans('Success generate AI'),
			type: 'success'
		})
	} catch (e) {
		notify({
			title: trans('Error'),
			text: trans('Failed to generate AI'),
			type: 'error'
		})
	} finally {
		isGeneratingAI.value = false
	}
}

const openExistingMedia = async () => {
	showMediaModal.value = true
	fetchMediaGallery()
}

const setMainImage = (imageId: number) => {
	selectedMedia.value = selectedMedia.value.map(img => ({
		...img,
		is_main: img.image_id === imageId
	}))
}

const removeMedia = (media: any) => {
	selectedMedia.value =
		selectedMedia.value.filter(m => m.image_id !== media.image_id)
}

const toggleSelectAI = (media: any) => {

	const index = selectedMediaAIIds.value.indexOf(media.key)

	if (index !== -1) {

		selectedMediaAIIds.value.splice(index, 1)

		selectedMediaForAI.value =
			selectedMediaForAI.value.filter(
				m => m.key !== media.key
			)

	} else {

		selectedMediaAIIds.value.push(media.key)

		selectedMediaForAI.value.push(media)

	}
}

const generateAIImages = async () => {
	try {
		isGeneratingAI.value = true

		const payload = {
			images: selectedMediaForAI.value.map(m => m.image_id),
			prompt: aiPrompt.value
		}
		const routeParams = {
			...props.bundle_routes.ai.generate_images.parameters,
			product: selectedEditProduct.value.product_id
		}


		const res = await axios.post(
			route(
				props.bundle_routes.ai.generate_images.name,
				routeParams
			),
			payload
		)

		const media = res.data?.data

		if (media) {

			selectedMedia.value.push({
				id: media.id,
				image_id: media.id,
				url: media.source?.original || media.thumbnail?.original,
				image: media.thumbnail || media.source,
				is_ai: true,
				is_main: false
			})

		}

		showGenerateModal.value = false

		aiPrompt.value = ''
		selectedMediaForAI.value = []

		notify({
			title: 'AI Image Generated',
			type: 'success'
		})
	} catch (e) {
		notify({
			title: trans('Error'),
			text: trans('Failed to generate AI'),
			type: 'error'
		})
	} finally {
		isGeneratingAI.value = false
	}
}

const bundle = useBundle(props.bundle_routes)

const submitBundle = async () => {
	
		const payloadItems = bundleItems.value.map(i => ({
			bundle_item_id: i.id,
			quantity: i.quantity
		}))

		const payload = {
			description: selectedEditProduct?.value.description,
			images: selectedMedia.value.map(img => ({
				id: img.image_id,
				is_main: img.is_main
			})),
			payloadItems
		}

		const routeParams = {
			...props.bundle_routes.update.parameters,
			bundle: selectedEditProduct?.value.bundle_id
		}
		router.patch(
			route(props.bundle_routes.update.name, routeParams),
			payload,
			{
				preserveScroll: true,
				preserveState: true,
				onStart: () => {
					isSubmitBundle.value = true
				},
				onSuccess: () => {
					notify({
						title: trans('Success'),
						text: trans('Success edit bundle'),
						type: 'success'
					})
					isSubmitBundle.value = false
					isOpenModalEditProduct.value = false
					bundle.resetBundle()
				},
				onError: errors => {
								notify({
									title: trans("Something went wrong"),
									text: trans("Failed to submit the data, please try again"),
									type: "error"
								})
				},
				 onFinish: () => {
					isSubmitBundle.value = false
				},
			}
		)
}
</script>

<template>
	<Message v-if="errorBluk.length > 0 && progressToUploadToEcom.total == 0" severity="error"
		class="relative m-4 pr-10">
		<!-- Close Button -->
		<button @click="errorBluk = []" class="absolute top-0 right-2 text-red-400 hover:text-red-600 transition"
			aria-label="Close">
			<FontAwesomeIcon :icon="faTimes" class="w-4 h-4" />
		</button>

		<!-- Message Content -->
		<h3 class="font-semibold mb-2 text-red-700">Upload Error(s):</h3>
		<ul class="list-disc list-inside text-sm text-red-800">
			<li v-for="(item, index) in errorBluk" :key="index">
				{{
					`Error when uploading item with code: ${item.item_code} - ${item.upload_warning}`
				}}
			</li>
		</ul>
	</Message>
	<Table :resource="data" :name="tab" class="mt-5" :isCheckBox="true"
		@onChecked="(item) => onChangeCheked(true, item)" @onUnchecked="(item) => onChangeCheked(false, item)"
		@onCheckedAll="(data) => onCheckedAll(data)" checkboxKey="id"
		:isChecked="(item) => selectedProducts.includes(item.id)" :rowColorFunction="(item) => {
			if (disableButtons(item)) {
				// return item.product_state == 'discontinued' ? 'bg-red-100' : 'bg-red-50'
				return item.platform_status === true ? 'bg-green-50' : 'bg-red-50'
			} else if (
				!isPlatformManual &&
				is_platform_connected &&
				!item.platform_product_id &&
				get(progressToUploadToShopify, [item.id], undefined) != 'success'
			) {
				return 'bg-yellow-50'
			} else {
				return ''
			}
		}
			" :isParentLoading="!!isLoadingTable">

		<template #add-on-button-in-before>
			<div class="border-r px-4">
				<PureProgressBar v-if="progressToUploadToEcom.total != 0" :progressBars="progressToUploadToEcom" />
			</div>
		</template>

		<template #add-on-button>
			<Button @click="onClickFilterForSale('true')" v-tooltip="trans('Only show products that are for sale')"
				:label="trans('Only For Sale')" size="xs" class="whitespace-nowrap" :key="compTableFilterForSale"
				:type="compTableFilterForSale ? 'secondary' : 'tertiary'"
				:icon="compTableFilterForSale ? 'fas fa-filter' : 'fal fa-filter'" iconRight="fal fa-times"
				:loading="isLoadingTable == 'discontinued'" />
			<Button @click="onClickFilterOutOfStock('discontinued')"
				v-tooltip="trans('Filter the product that discontinued')" label="Discontinued" size="xs"
				class="whitespace-nowrap" :key="compTableFilterStatus"
				:type="compTableFilterStatus === 'discontinued' ? 'secondary' : 'tertiary'"
				:icon="compTableFilterStatus === 'discontinued' ? 'fas fa-filter' : 'fal fa-filter'"
				iconRight="fal fa-times" :loading="isLoadingTable == 'discontinued'" />
			<Button @click="onClickFilterOutOfStock('out-of-stock')"
				v-tooltip="trans('Filter the product that out of stock')" label="Out of stock" size="xs"
				class="whitespace-nowrap" :key="compTableFilterStatus"
				:type="compTableFilterStatus === 'out-of-stock' ? 'secondary' : 'tertiary'"
				:icon="compTableFilterStatus === 'out-of-stock' ? 'fas fa-filter' : 'fal fa-filter'"
				iconRight="fal fa-exclamation-triangle" :loading="isLoadingTable == 'out-of-stock'" />
		</template>

		<template #cell(image)="{ item: product }">
			<div class="relative group">
				<div class="relative overflow-hidden w-10 h-10">
					<Image :src="product.image" :alt="product.name" />
				</div>
				<!-- Popover with larger image -->
				<div
					class="absolute left-full top-0 ml-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 pointer-events-none">
					<div class="bg-white border border-gray-200 rounded-lg shadow-lg p-2">
						<div class="w-64 h-64 overflow-hidden rounded">
							<Image :src="product.full_size_image || product.image" :alt="product.name"
								class="w-full h-full object-cover" />
						</div>
					</div>
				</div>
			</div>
		</template>

		<template #cell(name)="{ item: product }">
			<Link :href="portfolioRoute(product)" class="primaryLink whitespace-nowrap">
				{{ product["code"] }}
			</Link>
			<div class="text-base font-semibold">
				{{ product["name"] }}
			</div>
			<div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
				<div>{{ trans("Stocks:") }} {{ locale.number(product.quantity_left) }}</div>
			</div>

			<div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
				<div>
					{{ trans("Weight:") }} <span v-tooltip="trans('Marketing weight')">{{
						locale.number(product.marketing_weight / 1000)
					}}Kg</span> / <span v-tooltip="trans('Weight including packing')">{{
							locale.number(product.weight / 1000)
						}}Kg</span>
				</div>
			</div>

			<div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
				<div>
					{{ trans("Dimension:") }}
					{{ product.dimension }}
				</div>
			</div>

			<div class="text-sm text-gray-500 italic flex gap-x-10 gap-y-2">
				<div v-if="customerSalesChannel.include_vat">
					{{ trans("Price (include VAT):") }}
					{{ locale.currencyFormat(product.currency_code, calculateVat(product.price)) }}
				</div>
				<div v-else>
					{{ trans("Price:") }}
					{{ locale.currencyFormat(product.currency_code, product.price) }}
				</div>
				<div v-if="customerSalesChannel.include_vat">
					{{ trans("RRP (include VAT):") }}
					{{ locale.currencyFormat(product.currency_code, product.customer_price) }}
				</div>
				<div v-else-if="platform_data.type === 'ebay'">
					{{ trans("RRP:") }}
					{{ locale.currencyFormat(product.currency_code, product.customer_price * 0.8) }}
				</div>
				<div v-else>
					{{ trans("RRP:") }}
					{{ locale.currencyFormat(product.currency_code, product.customer_price) }}
				</div>
			</div>
		</template>

		<!-- Column: Status (repair) -->
		<template #cell(status)="{ item }">
			<div class="whitespace-nowrap">
				<FontAwesomeIcon v-if="item.has_valid_platform_product_id"
					v-tooltip="trans('Has valid platform product id')" icon="fal fa-check" class="text-green-500"
					fixed-width aria-hidden="true" />
				<FontAwesomeIcon v-else v-tooltip="trans('Has valid platform product id')" icon="fal fa-times"
					class="text-red-500" fixed-width aria-hidden="true" />
				<FontAwesomeIcon v-if="item.exist_in_platform" v-tooltip="trans('Exist in platform')"
					icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
				<FontAwesomeIcon v-else v-tooltip="trans('Exist in platform')" icon="fal fa-times" class="text-red-500"
					fixed-width aria-hidden="true" />
				<FontAwesomeIcon v-if="item.platform_status" v-tooltip="trans('Platform status')" icon="fal fa-check"
					class="text-green-500" fixed-width aria-hidden="true" />
				<FontAwesomeIcon v-else v-tooltip="trans('Platform status')" icon="fal fa-times" class="text-red-500"
					fixed-width aria-hidden="true" />
			</div>
		</template>

		<template #cell(message)="{ item }">
			<div class="whitespace min-w-[50px] text-left font-medium italic whitespace-break-spaces text-red-500"
				v-if="disableButtons(item)">
				<FontAwesomeLayers v-if="item.product_state == 'discontinued'" v-tooltip="trans('This product line has been discontinued. Please remove this item')
					" class="flex h-full w-full">
					<FontAwesomeIcon :icon="faBan" class="text-2xl" />
					<FontAwesomeIcon :icon="faCube" class="text-md text-center" />
				</FontAwesomeLayers>
				<FontAwesomeLayers v-else v-tooltip="trans('This product line is currently not for sale')"
					class="flex h-full w-full">
					<FontAwesomeIcon :icon="faBan" class="text-2xl" />
					<FontAwesomeIcon :icon="faDollarSign" class="text-lg text-center" />
				</FontAwesomeLayers>
			</div>
			<div class="whitespace min-w-[50px] font-medium whitespace-break-spaces flex items-center text-center w-full"
				v-else-if="item.message">
				<FontAwesomeIcon v-tooltip="item.message" v-if="item.message === 'OK'" icon="fal fa-check-circle"
					class="text-green-500 text-xl" style="width: 100% !important" fixed-width aria-hidden="true" />
				<FontAwesomeIcon v-tooltip="item.message" v-else icon="fal fa-exclamation-circle"
					class="text-red-500 text-xl" style="width: 100% !important" fixed-width aria-hidden="true" />
			</div>
			<div v-else />
		</template>

		<!-- Column: Actions (connect) -->
		<template #cell(matches)="{ item }">
			<template v-if="item.customer_sales_channel_platform_status">
				<template v-if="!item.platform_status">
					<div v-if="item.platform_possible_matches?.number_matches" class="border rounded p-1" :class="selectedProducts?.includes(item.id)
						? 'bg-green-200 border-green-400'
						: 'border-gray-300'
						">
						<div class="flex gap-x-2 items-center border border-gray-300 rounded p-1">
							<div v-if="
								item.platform_possible_matches?.raw_data?.[0]?.images?.[0]?.src
							" class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow border border-gray-300 rounded">
								<img :src="item.platform_possible_matches?.raw_data?.[0]?.images?.[0]
									?.src
									" />
							</div>
							<div>
								<span class="mr-1">{{
									item.platform_possible_matches?.matches_labels[0]
								}}</span>
							</div>
						</div>
						<ButtonWithLink v-if="
							item.platform_possible_matches?.number_matches &&
							!disabled &&
							item.is_for_sale
						" v-tooltip="item.is_for_sale
							? trans('Match to existing :platform product', {
								platform: platform_data?.name || 'Platform',
							})
							: trans('This product line is currently not for sale')
							" :routeTarget="item.is_for_sale
								? {
									method: 'post',
									name: props.routes.single_match.name,
									parameters: {
										portfolio: item.id,
										platform_product_id:
											item.platform_possible_matches.raw_data?.[0]
												?.id,
									},
								}
								: {}
								" :bindToLink="{ preserveScroll: true }" type="primary" :label="trans('Match with this product')" size="xxs"
							icon="fal fa-hand-pointer" :disabled="disableButtons(item)" />
					</div>

					<div v-if="!disabled">
						<Button v-if="item.platform_possible_matches?.number_matches" @click="
							() => {
								if (item.is_for_sale) {
									fetchRoute()
									isOpenModal = true
									selectedPortfolio = item
								}
							}
						" v-tooltip="item.is_for_sale
							? trans('Choose another product from your shop')
							: trans('This product line is currently not for sale')
							" :label="trans('Choose another product from your shop')" :capitalize="false" size="xxs" type="tertiary"
							:style="'white-w-outline'" :disabled="disableButtons(item)" />
						<Button v-else @click="
							() => {
								if (item.is_for_sale) {
									fetchRoute()
									isOpenModal = true
									selectedPortfolio = item
								}
							}
						" v-tooltip="item.is_for_sale
							? trans('Match it with an existing product in your shop')
							: trans('This product line is currently not for sale')
							" :label="trans('Match it with an existing product in your shop')" :capitalize="false" size="xxs"
							type="tertiary" :style="'white-w-outline'" :disabled="disableButtons(item)" />
					</div>
				</template>

				<template v-else>
					<template v-if="item.platform_product_data?.name">
						<div class="flex gap-x-2 items-center">
							<div v-if="item.platform_product_data?.images?.[0]?.src"
								class="min-h-5 h-auto max-h-9 min-w-9 w-auto max-w-9 shadow border border-gray-300 rounded">
								<img :src="item.platform_product_data?.images?.[0]?.src" />
							</div>

							<div>
								<span class="mr-1">{{ item.platform_product_data?.name }}</span>
							</div>
						</div>
					</template>
					<Button v-if="!disabled" class="mt-2" @click="
						() => {
							if (item.is_for_sale) {
								fetchRoute()
								isOpenModal = true
								selectedPortfolio = item
							}
						}
					" v-tooltip="item.is_for_sale
						? trans('Connect with other product')
						: trans('This product line is currently not for sale')
						" :label="trans('Connect with other product')" :capitalize="false" :icon="faRecycle" size="xxs" type="tertiary"
						:style="'white-w-outline'" :disabled="disableButtons(item)" />
				</template>
			</template>
		</template>

		<!-- Column: Actions 2 (Modal shopify) -->
		<template #cell(create_new)="{ item }" v-if="!disabled">
			<!-- {{ item.customer_sales_channel_platform_status }} --- {{ !item.platform_status }} -->
			<div v-if="item.customer_sales_channel_platform_status && !item.platform_status"
				class="flex gap-x-2 items-center">
				<ButtonWithLink v-tooltip="item.is_for_sale
					? trans('Will create new product in :platform', {
						platform: props.platform_data.name,
					})
					: trans('This product line is currently not for sale')
					" :routeTarget="item.is_for_sale
						? {
							method: 'post',
							name: props.routes.single_create_new.name,
							parameters: {
								portfolio: item.id,
							},
						}
						: {}
						" isWithError icon="" :label="trans('Create new product')" size="xxs" type="tertiary" :style="'white-w-outline'"
					:bindToLink="{
						preserveScroll: true,
					}" @success="
						(a) => {
							// console.log('zvvcvc', a)
						}
					" @error="
						(e) => {
							// console.log('aaaaaaaaaaaa', e, item)
							selectedErrorProduct = {
								product: item,
								error: e,
							}
							set(listErrorProducts, [`x${item.id}`], e)
						}
					" :disabled="disableButtons(item)" />
			</div>
		</template>

		<!-- Column: Actions 3 -->
		<template #cell(delete)="{ item }" v-if="!disabled">
			
			<div class="flex gap-2">
				<Button v-tooltip="trans('Edit Bundle')" type="tertiary" :style="'white-w-outline'" size="xs"
				icon="fal fa-pencil" @click="openEditModal(item)" />
				
				<ButtonWithLink v-tooltip="trans('Delete Bundle', {
					platform: props.platform_data.name,
				})" type="negative" icon="fal fa-trash-alt" size="xs" :style="'white-r-outline'" :method="'delete'"
					:bindToLink="{ preserveScroll: true }" :routeTarget="{
						...props.bundle_routes.delete,
						parameters: {
							...props.bundle_routes.delete.parameters,
							bundle: item.bundle_id
						}
					}" />
			</div>
		</template>
	</Table>

	<Modal :isOpen="isOpenModal" width="w-full max-w-2xl h-full min-h-fit" @close="
		() => {
			isOpenModal = false
			selectedVariant = null
			resultOfFetchPlatformProduct = []
			currentOffset = 0
			hasMore = true
		}
	">
		<div class="relative isolate">
			<div v-if="isLoadingSubmit || isLoadingMore"
				class="flex justify-center items-center text-7xl text-white absolute z-10 inset-0 bg-black/40">
				<LoadingIcon />
			</div>

			<div class="mb-2">
				<strong>
					{{
						trans("List of Products under your :_storetype Store", {
							_storetype: platform_data.name,
						})
					}}
				</strong>
			</div>

			<div class="mb-2 relative">
				<PureInput v-model="querySearchPortfolios" @update:modelValue="() => debounceGetPortfoliosList()"
					:placeholder="trans('Search in :platform', { platform: platform_data.name })"
					:disabled="isLoadingFetchPlatformProduct" />
				<div v-if="isLoadingFetchPlatformProduct" class="absolute right-2 text-xl top-1/2 -translate-y-1/2">
					<LoadingIcon />
				</div>
				<slot name="afterInput"> </slot>
			</div>

			<div class="xh-full xmd:h-[570px] text-base font-normal">
				<div class="col-span-4 pb-8 md:pb-2 h-fit overflow-auto flex flex-col">
					<div class="flex justify-between items-center">
						<!-- <div class="font-semibold text-lg py-1">{{ trans("Result") }} ({{ locale?.number(portfoliosMeta?.total || 0) }})</div> -->
					</div>
					<div class="border-t border-gray-300 mb-1"></div>
					<div class="h-full md:h-[400px] overflow-x-clip overflow-y-scroll py-2 relative"
						style="scrollbar-width: thin; scrollbar-gutter: stable">
						<!-- Products list -->
						<div
							class="min-h-24 relative mb-4 pb-4 p-2 xborder-b xborder-indigo-300 grid grid-cols-2 gap-3 pr-2">
							<div v-if="isLoadingFetchPlatformProduct" class="text-center text-gray-500 col-span-3">
								<LoadingIcon class="ml-1" />
								{{
									trans("Fetching your :_storetype product list", {
										_storetype: platform_data.name,
									})
								}}
							</div>
							<template ref="list" v-else-if="resultOfFetchPlatformProduct?.length > 0">
								<div v-for="(item, index) in resultOfFetchPlatformProduct" :key="index" @click="
									() => {
										selectedVariant = item
									}
								" class="relative h-fit rounded cursor-pointer p-2 flex flex-col md:flex-row gap-x-2 border" :class="[
									selectedVariant?.id === item.id
										? 'bg-green-100 border-green-400'
										: '',
								]">
									<Transition name="slide-to-right">
										<FontAwesomeIcon v-if="selectedVariant?.id === item.id"
											icon="fas fa-check-circle" class="-top-2 -right-2 absolute text-green-500"
											fixed-width aria-hidden="true" />
									</Transition>
									<slot name="product" :item="item">
										<!--                                        <Image v-if="item.images?.src" :src="item.images?.src"
                                               class="w-16 h-16 overflow-hidden mx-auto md:mx-0 mb-4 md:mb-0" imageCover
                                               :alt="item.name"/>-->
										<div
											class="min-h-3 h-auto max-h-9 min-w-9 w-auto max-w-9 border border-gray-300 rounded">
											<img :src="item.images?.[0]?.src" class="shadow" />
										</div>
										<div class="flex flex-col justify-between">
											<div class="w-fit" xclick="() => selectProduct(item)">
												<div v-tooltip="trans('Name')"
													class="w-fit font-semibold leading-none mb-1">
													{{ item.name || "no name" }}
												</div>
												<div v-if="!item.no_code" v-tooltip="trans('Code')"
													class="w-fit text-xs text-gray-400 italic">
													{{ item.code || "no code" }}
												</div>
												<div v-if="item.reference" v-tooltip="trans('Reference')"
													class="w-fit text-xs text-gray-400 italic">
													{{ item.reference || "no reference" }}
												</div>
												<div v-if="item.gross_weight" v-tooltip="trans('Weight')"
													class="w-fit text-xs text-gray-400 italic">
													{{ item.gross_weight }}
												</div>
											</div>
											<div v-if="!item.no_price && item.price" xclick="() => selectProduct(item)"
												v-tooltip="trans('Price')" class="w-fit text-xs text-gray-x500">
												{{
													locale?.currencyFormat(
														item.currency_code || "usd",
														item.price || 0
													)
												}}
											</div>
										</div>
									</slot>
								</div>
								<div ref="sentinel" class="col-span-2 justify-items-center flex mx-auto">
									<LoadingIcon v-if="hasMore" />
								</div>
								<div v-if="!hasMore && platform_data.type == 'ebay'" class="col-span-2 text-center">
									{{ trans("You've reached the end of item list") }}
								</div>
							</template>
							<div v-else class="text-center text-gray-500 col-span-3">
								{{ trans("No products found") }}
							</div>
						</div>
					</div>
					<div class="mt-4">
						<Button @click="() => onSubmitVariant()" :disabled="!selectedVariant?.id" v-tooltip="!selectedVariant?.id
							? trans('Select at least one product on your platform')
							: ''
							" :label="trans('Link :_productcode to selected item on your platform', {
								_productcode: selectedPortfolio?.code ?? 'it',
							})
								" type="primary" full xicon="fas fa-plus" :loading="isLoadingSubmit" />
					</div>
				</div>
			</div>
		</div>
	</Modal>

	<Modal :isOpen="isOpenModalEditProduct" width="w-full max-w-3xl h-full" @close="isOpenModalEditProduct = false">
		<div class="overflow-auto">
			<div class="text-xl font-semibold text-center">
				{{ trans("Edit Bundle") }}
			</div>

			<div class="mb-3 relative">
				<label for="edit-product-title" class="block text-sm font-semibold">{{
					trans("Title")
				}}</label>
				<InputText v-model="selectedEditProduct.name" fluid inputId="edit-product-title" size="small"
					:disabled="isLoadingSubmitErrorTitle" />
				<Button icon="fal fa-sparkles" type="button" @click="generateAITitle" :loading="isGeneratingAI" :disabled="isGeneratingAI"
					v-tooltip="trans('Generate AI')"
					class="absolute right-2 top-10 -translate-y-1/2 h-7 w-7 flex items-center justify-center rounded-md border bg-white hover:bg-gray-100 transition shadow-sm" />

			</div>

			<div class="mb-3 space-y-2">
				<label for="edit-product-description" class="block text-sm font-semibold">{{
					trans("Description")
				}}</label>
				<Textarea v-model="selectedEditProduct.description" rows="6" autoResize class="w-full mt-1" placeholder="Input your description" />
				<Button icon="fal fa-sparkles" @click="generateAIDescription" :loading="isGeneratingAI" type="primary"
				:label="trans('Generate with AI')"
			:disabled="!selectedEditProduct?.description.length" />
			</div>

			<div class="mb-5">
				<label class="text-sm font-semibold">
					{{ trans('Bundle media') }}
				</label>

				<div class="bg-gray-100 rounded-xl p-3 mt-1 grid grid-cols-3 gap-3 min-h-[110px]">
					<div v-for="img in selectedMedia" class="relative group">
						<Image :key="img.id" :src="img.image" class="h-24 w-full rounded-lg" imageCover />
						<input type="radio" name="main_image" :checked="img.is_main"
							@change="setMainImage(img.image_id)" class="absolute top-2 left-2 z-20" />
						<div v-if="img.is_main"
							class="absolute bottom-1 left-1 text-[10px] bg-black/70 text-white px-1 rounded">
							MAIN IMAGE
						</div>
						<button
							class="absolute top-1 right-1 bg-black/70 text-white text-xs px-1 rounded opacity-0 group-hover:opacity-100"
							@click="removeMedia(img)">
							✕
						</button>
					</div>
				</div>
			</div>

			<div class="mb-5">
				<label class="text-sm font-semibold">
					Bundle Items
				</label>

				<div class="mt-2 space-y-2">
					<div v-for="item in bundleItems" :key="item.id"
						class="flex items-center gap-3 p-2 border rounded-lg bg-white">
						<!-- IMAGE -->
						<img :src="item.image" class="w-12 h-12 object-cover rounded bg-gray-100" />

						<!-- INFO -->
						<div class="flex-1 text-sm font-medium line-clamp-2">
							{{ item.name }}
						</div>

						<!-- QUANTITY -->
						<QuantitySelector :modelValue="item.quantity"
							@update:modelValue="(val) => updateItemQty(item.id, val)" />
					</div>
				</div>
			</div>
			<div class="mb-3">
				<div class="flex gap-2 mt-3">
					<Button @click="openExistingMedia" type="secondary">
						<FontAwesomeIcon :icon="faImages" class="mr-2" fixed-width />
						Select existing media
					</Button>

					<Button @click="showGenerateModal = true" type="primary" icon="fal fa-arrow-left"
						:disabled="!selectedMedia.length">
						<FontAwesomeIcon :icon="faSparkles" class="mr-2" fixed-width />
						Generate Image AI
					</Button>
				</div>
			</div>

			<div class="mt-3 flex gap-2">
				<Button @click="submitBundle" :label="isSubmitBundle ? trans('Loading') : trans('Save')" full  icon="fad fa-save" :loading="isSubmitBundle" :disabled="!selectedEditProduct?.description.length || isSubmitBundle"/>
			</div>
		</div>
	</Modal>

	<Dialog v-model:visible="showMediaModal" modal header="Select Images" :style="{ width: '600px' }">
		<div v-if="isLoadingMedia" class="py-10 text-center">
			<LoadingIcon />
		</div>
		<div v-else class="grid grid-cols-4 gap-3">
			<template v-if="flatMediaGallery.length">
				<div v-for="img in flatMediaGallery" :key="img.key"
					class="relative aspect-square rounded-xl overflow-hidden border cursor-pointer group"
					@click="toggleSelect(img)">

					<div class="absolute inset-0 z-0">
						<Image :src="img.image" class="w-full h-full" imageCover />
					</div>

					<div v-if="selectedMediaIds.includes(img.key)" class="absolute inset-0 bg-black/40 z-10" />

					<Checkbox :modelValue="selectedMediaIds.includes(img.key)" binary
						class="absolute top-2 left-2 z-20 bg-white rounded shadow pointer-events-none" />

					<div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition z-5" />

				</div>
			</template>
			<template v-else>
				<div class="col-span-4 text-center text-gray-400 py-6">
					No images yet
				</div>
			</template>
		</div>

		<template #footer>
			<Button @click="showMediaModal = false" type="primary">
				Done
			</Button>
		</template>

	</Dialog>

	<Dialog v-model:visible="showGenerateModal" header="Generate AI Image" modal :style="{ width: '600px' }">

		<div class="mb-4">
			<div class="text-sm font-semibold mb-2">
				Select images of products you want to include in generated image
			</div>

			<div class="grid grid-cols-4 gap-3">

				<div v-for="media in selectedMedia" :key="media.key"
					class="relative aspect-square rounded-xl overflow-hidden border cursor-pointer group"
					@click="toggleSelectAI(media)">


					<!-- IMAGE -->
					<div class="absolute inset-0 z-0">
						<Image :src="media.image" class="w-full h-full" imageCover />
					</div>

					<!-- DARK OVERLAY -->
					<div v-if="selectedMediaAIIds.includes(media.key)" class="absolute inset-0 bg-black/40 z-10" />

					<!-- CHECKBOX (visual only) -->
					<Checkbox :modelValue="selectedMediaAIIds.includes(media.key)" binary
						class="absolute top-2 left-2 z-20 bg-white rounded shadow pointer-events-none" />

					<!-- HOVER -->
					<div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition" />

				</div>

			</div>
		</div>

		<div class="mb-4">
			<div class="text-sm font-semibold mb-1">
				Describe your image
			</div>

			<Textarea v-model="aiPrompt" rows="3" class="w-full" placeholder="Input description" />
		</div>

		<template #footer>
			<Button label="Generate" @click="generateAIImages" :loading="isGeneratingAI"
				:disabled="!selectedMediaForAI.length || !aiPrompt" />
		</template>

	</Dialog>
</template>
