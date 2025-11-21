<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import ImagePrime from "primevue/image"
import { ref, computed, inject } from "vue"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo } from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
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


library.add(faCircle, faTrash, falTrash, faEdit, faExternalLink, faPlay, faPlus, faBarcode, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo)

const props = defineProps<{
	taxonomy: any
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


</script>

<template>
	<div class="w-full  px-4 py-3 mb-3 shadow-sm">


		<span class="text-xl font-semibold text-gray-800 whitespace-pre-wrap">
			<!-- Units box -->
			<span
				v-if="data.product?.data?.units !== null && data.product?.data?.units !== undefined && data.product?.data?.units !== ''"
				class="inline-flex items-center border border-gray-300 rounded px-2 py-0.5 mr-2 bg-white text-gray-900">
				<span>{{ data.product.data.units }}</span>
				<span class="ml-1">x</span>
			</span>
			<!-- Product name -->
			<span class="align-middle">
				{{data.product.data.name}}
			</span>
		</span>


	</div>


	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<dd class="font-medium flex flex-wrap gap-1 p-4">
				<span v-for="tag in tradeUnitTags" :key="tag.id" v-tooltip="'tag'"
					class="px-2 py-0.5 rounded-full text-xs bg-green-50 border border-blue-100">
					{{ tag.name }}
				</span>
			</dd>

			<!-- Image Preview & Thumbnails -->
			<div class="bg-white   p-4 lg:p-5">
				<div v-if="props.data?.main_image?.webp" class="max-w-[550px] w-full">
					<ImagePrime :src="props.data?.main_image.webp" :alt="props?.data?.product?.data?.name" preview
						class="min-h-60" />
					<div class="text-sm italic text-gray-500">
						See all the images of this product in the tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">Media</span>
					</div>
				</div>

				<div v-else>
					<div
						class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg">
						<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
						<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
					</div>
					<div class="mt-2 text-sm italic text-gray-500">
						Manage images in tab <span @click="() => handleTabUpdate('images')"
							class="underline text-indigo-500 hover:text-indigo-700 cursor-pointer">Media</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Product Summary -->
		<ProductSummary :data="data.product.data" :gpsr="data.gpsr" :properties="data.properties" :parts="data.parts"
			:hide="['price', 'rrp', 'stock']" :public-attachment="data.attachment_box.public" />


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


			<div>
				<AttachmentCard :public="data.attachment_box.public" :private="data.attachment_box.private" />
			</div>

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