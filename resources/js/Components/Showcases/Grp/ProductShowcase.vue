<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import ImagePrime from "primevue/image"
import { ref, computed, inject } from "vue"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo} from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode, faCheckCircle, faTimesCircle } from "@fas"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { Images } from "@/types/Images"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import { faImage } from "@far"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import ProductSummary from "@/Components/Product/ProductSummary.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import ReviewContent from "@/Components/ReviewContent.vue"
import AttachmentCard from "@/Components/AttachmentCard.vue"
import ProductPriceGrp from "@/Components/Product/ProductPriceGrp.vue"
import { ProductResource } from "@/types/Iris/Products"
import { Image as ImageTS } from "@/types/Image"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import { router } from "@inertiajs/vue3"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Modal from "@/Components/Utils/Modal.vue"
import LabelSKU from '@/Components/Utils/Product/LabelSKU.vue'


library.add(faCircle, faTrash, falTrash, faEdit, faExternalLink, faPlay, faPlus, faBarcode, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo)

const props = defineProps<{
	data: {
		stockImagesRoute: routeType
		uploadImageRoute: routeType
		attachImageRoute: routeType
		deleteImageRoute: routeType
		imagesUploadedRoutes: routeType
		webpage_url: string
		attachment_box?: {}
		translation_box: {
			title: string
			languages: Record<string, string>
			save_route: routeType
		}
		product: {
			data: ProductResource
		}
		stats: {
			amount: number | null
			amount_ly: number | null
			name: string
			percentage: number | null
		}[] | null
		trade_units: {
			brand: {}
			brand_routes: {
				index_brand: routeType
				store_brand: routeType
				update_brand: routeType
				delete_brand: routeType
				attach_brand: routeType
				detach_brand: routeType
			}
			tag_routes: {
				index_tag: routeType
				store_tag: routeType
				update_tag: routeType
				delete_tag: routeType
				attach_tag: routeType
				detach_tag: routeType
			}
			tags: {}[]
			tags_selected_id: number[]
		}[],
		gpsr: {
			acute_toxicity: boolean
			corrosive: boolean
			eu_responsible: string | null
			explosive: boolean
			flammable: boolean
			gas_under_pressure: boolean
			gpsr_class_category_danger: string | null
			hazard_environment: boolean
			health_hazard: boolean | null
			how_to_use: string
			manufacturer: null | string
			oxidising: boolean
			product_languages: string | null
			warnings: string | null
		}
		availability_status?: {
			from_master: boolean     
			from_trade_unit: boolean  
			is_for_sale: boolean           
			product_state: string        
			product_state_icon: []
			parentLink?: []
		}
		images: any
		main_image: ImageTS
	}
	handleTabUpdate?: Function
}>()


const tradeUnitTags = computed(() => {
	const list = props.data?.trade_units ?? []
	const tags = list.flatMap(item => item.tags ?? [])
	const unique = new Map(tags.map(tag => [tag.id, tag]))
	return [...unique.values()]
})


const tradeUnitBrands = computed(() => {
  return (props.data?.trade_units ?? [])
    .flatMap(unit => unit?.brand ?? [])
})


const editIsForSale = () => {
	let url = route('grp.org.shops.show.catalogue.products.all_products.edit', {
			...route().params,
			section: 4
	});
	if(props.data.availability_status?.from_master && props.data.availability_status?.parentLink){
		url = route(props.data.availability_status?.parentLink['url'], {
			...props.data.availability_status?.parentLink['params'],
			section: 6
		});
	}
	if(props.data.availability_status?.from_trade_unit && props.data.availability_status?.parentLink){
		url = route(props.data.availability_status?.parentLink['url'], {
			...props.data.availability_status?.parentLink['params'],
			section: 8
		});
	}

    router.visit(url)
}

const getTooltips = () => {
	let tooltipText = props.data.availability_status?.is_for_sale ? trans('Product is currently for sale and available to be purchased') : trans('Product is currently not for sale and unavailable to be purchased')

	if(props.data.availability_status?.from_master || props.data.availability_status?.parentLink){
		tooltipText = props.data.availability_status?.from_master ? trans('This product For Sale status has been modified from the Master Product level') : trans('This product For Sale status has been modified from the Trade Unit level')
	}

	return tooltipText;
}

</script>

<template>
	<div class="w-full px-4 py-3 mb-3 shadow-sm grid grid-cols-2">
		<div class="text-xl font-semibold text-gray-800 whitespace-pre-wrap justify-self-start">
			<!-- Units box -->
			<ProductUnitLabel
				v-if="data.product?.data?.units"
				:units="data.product?.data?.units"
				:unit="data.product?.data?.unit"
				class="mr-2"
			/>
			
			<!-- Product name -->
			<span class="align-middle">
				{{ data.product.data.name }}
			</span>
		</div>
		
		<div v-if="data.availability_status" class="text-md text-gray-800 whitespace-pre-wrap justify-self-end self-center flex gap-y-2 flex-wrap justify-end">
			<LabelSKU
				:product="data.product.data"
				:trade_units="data.trade_units"
				xrouteFunction="tradeUnitRoute"
			/>

			<span
				class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-help"
				:class="data.availability_status.product_state_icon['class'].replace('text', 'border').replace('500', '300')">
                <span class="opacity-50"> {{trans('Procurement')}}:</span>	 {{ data.availability_status.product_state}}
				<FontAwesomeIcon :icon="data.availability_status.product_state_icon['icon']" :class="data.availability_status.product_state_icon['class']"/>
			</span>

			<span 
				v-tooltip="getTooltips()"
				class="border border-solid hover:opacity-80 py-1 px-3 rounded-md hover:cursor-pointer"
				v-on:click="editIsForSale"
				:class="data.availability_status.is_for_sale ? 'border-green-500' : 'border-red-500'"
			>
			{{ data.availability_status.is_for_sale ? trans('For Sale') : trans('Not For Sale') }}
				<FontAwesomeIcon :icon="data.availability_status.is_for_sale ? faCheckCircle : faTimesCircle" :class="data.availability_status.is_for_sale ? 'text-green-500' : 'text-red-500'"/>
				<FontAwesomeIcon
					v-if="data.availability_status?.from_master"
					icon="fab fa-octopus-deploy"
					:class="'ms-1'"
					color="#4B0082"
				/>
				<FontAwesomeIcon
					v-if="data.availability_status?.from_trade_unit"
					icon="fal fa-atom"
					:class="'ms-1'"
				/>
			</span>
		</div>
	</div>
	
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Product Tags -->
			<dd v-if="tradeUnitTags && tradeUnitTags.length > 0" class="font-medium flex flex-wrap gap-1 p-4">
				<span v-for="tag in tradeUnitTags" :key="tag.id" v-tooltip="'tag'" class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
					{{ tag.name }}
				</span>
			</dd>
			<!-- Image Preview & Thumbnails -->
			<div class="bg-white   p-4 lg:p-5">
				<div v-if="props.data?.main_image?.webp" class="max-w-[550px] w-full">
					<ImagePrime :src="props.data?.main_image.webp" :alt="props?.data?.product?.data?.name" preview
						class="min-h-60" />
					<!-- <div class="text-sm italic text-gray-500">
						See all the images of this product in the tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">Media</span>
					</div> -->
				</div>
				<div v-else>
					<div
						class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg">
						<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
						<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
					</div>
					<!-- <div class="mt-2 text-sm italic text-gray-500">
						Manage images in tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">Media</span>
					</div> -->
				</div>
			</div>
		</div>

		<!-- Product Summary -->
		<ProductSummary 
			 :data="{...data.product.data, tags : tradeUnitTags, brands : tradeUnitBrands}" 
			 :properties="data.properties" 
			 :parts="data.parts"
			 :public-attachment="data.attachment_box.public" 
			 :gpsr="data.gpsr"
			 :attachments="data.attachment_box"
		/>
		<div class="bg-white h-fit mx-4  shadow-sm ">
			<div class="flex items-center gap-2 text-3xl text-gray-600 mb-4">
				<FontAwesomeIcon :icon="faCircle" class="text-[10px]"
					:class="data?.product?.data?.stock > 0 ? 'text-green-600' : 'text-red-600'" />
				<span>
					{{
					data?.product?.data?.stock > 0
					? trans("In stock") + ` (${data?.product?.data?.stock} ` + trans("available") + `)`
					: trans("Out Of Stock")
					}}
				</span>
			</div>
			<!-- Section: Price -->
			<ProductPriceGrp :product="data?.product?.data" :currency_code="data.product.data?.currency_code" />
			<!-- <div>
				<AttachmentCard :public="data.attachment_box.public" :private="data.attachment_box.private" />
			</div> -->

		</div>

	</div>
</template>

<style scoped>
/* Add custom styles if needed for better text readability */
.whitespace-pre-wrap {
	white-space: pre-wrap;
	word-wrap: break-word;
}

/* Remove all padding from accordion */
:deep(.p-accordion) {
	padding: 0;
}

:deep(.p-accordion-panel) {
	border: none;
}

:deep(.p-accordionheader) {
	padding: 10px 0;
	background: #f8fafc;
	border-radius: 0.5rem;
	border: none;
	background-color: #ffffff;
}

:deep(.p-accordionheader:hover) {
	background: #e2e8f0;
}

:deep(.p-accordioncontent-content) {
	padding: 0 !important;
	border: none;
}

:deep(.p-accordionheader-text) {
	padding: 0.75rem 1rem;
	width: 100%;
}
</style>