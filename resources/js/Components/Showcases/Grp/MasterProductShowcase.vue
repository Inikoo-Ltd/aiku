<script setup lang="ts">

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, computed, inject } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faTrash as falTrash,
	faEdit,
	faExternalLink,
	faPuzzlePiece,
	faShieldAlt,
	faInfoCircle,
	faChevronDown,
	faChevronUp,
	faBox,
	faVideo,
} from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import { faImage } from "@far"
import ImagePrime from "primevue/image"
import { toInteger } from "lodash"
import { routeType } from "@/types/route"
import { ProductResource } from "@/types/Iris/Products"
import { Image as ImageTS } from "@/types/Image"
import MasterProductSummary from "@/Components/Goods/MasterProductSummary.vue"


library.add(
	faCircle,
	faTrash,
	falTrash,
	faEdit,
	faExternalLink,
	faPlay,
	faPlus,
	faBarcode,
	faPuzzlePiece,
	faShieldAlt,
	faInfoCircle,
	faChevronDown,
	faChevronUp,
	faBox,
	faVideo
)


const props = defineProps<{
	currency: string,
	handleTabUpdate: Function
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
}>();

/* const imagesSetup = ref(
	props.data.images
		.filter(item => item.type === "image")
		.map(item => ({
			label: item.label,
			column: item.column_in_db,
			images: item.images,
		}))
)
 */
/* const videoSetup = ref(
	props.data.images.find(item => item.type === "video") || null
)
 */
/* const images = computed(() => props.data?.images ?? []) */


/* const validImages = computed(() =>
	imagesSetup.value
		.filter(item => item.images) // only keep if images exist
		.flatMap(item => {
			const images = Array.isArray(item.images) ? item.images : [item.images] // normalize to array
			return images.map(img => ({
				source: img,
				thumbnail: img
			}))
		})
) */

</script>


<template>
	<div class="w-full  px-4 py-3 mb-3 shadow-sm">


		<span class="text-xl font-semibold text-gray-800 whitespace-pre-wrap">
			<!-- Units box -->
			<span
				v-if="data.masterProduct?.units !== null && data.masterProduct?.units !== undefined && data.masterProduct?.units !== ''"
				class="inline-flex items-center border border-gray-300 rounded px-2 py-0.5 mr-2 bg-white text-gray-900">
				<span>{{ toInteger(data.masterProduct.units) }}</span>
				<span class="ml-1">x</span>
			</span>
			<!-- Product name -->
			<span class="align-middle">
				{{ data.masterProduct.name }}
			</span>
		</span>


	</div>
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Image Preview & Thumbnails -->
			<div class="bg-white   p-4 lg:p-5">
				<div v-if="props.data?.main_image?.webp" class="max-w-[550px] w-full">
					<ImagePrime :src="props.data?.main_image.webp" :alt="props?.data?.product?.data?.name" preview />
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
		<MasterProductSummary :data="data.masterProduct" :video="videoSetup?.url"
			:hide="['price', 'rrp', 'stock', 'weight', 'dimension','picking']" />
	</div>
</template>
