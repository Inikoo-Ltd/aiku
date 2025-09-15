<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"
import { ref, computed, watch } from "vue"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo } from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { Images } from "@/types/Images"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import { faImage } from "@far"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import ProductSummary from "@/Components/Product/ProductSummary.vue"


library.add(faCircle, faTrash, falTrash, faEdit, faExternalLink, faPlay, faPlus, faBarcode, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo)

const props = defineProps<{
	taxonomy: any
	data: {
		stockImagesRoute: routeType
		uploadImageRoute: routeType
		attachImageRoute: routeType
		deleteImageRoute: routeType
		imagesUploadedRoutes: routeType
		translation_box: {
			title: string
			languages: Record<string, string>
			save_route: routeType
		}
		product: {
			data: {
				id: number
				slug: string
				image_id: number
				code: string
				name: string
				price: string
				description?: string
				state: string
				created_at: string
				updated_at: string
				images: Images[]
				currency_code: string
			}
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
		images: {}
	}
}>()

const imagesSetup = ref(
	props.data.images
		.filter(item => item.type === "image")
		.map(item => ({
			label: item.label,
			column: item.column_in_db,
			images: item.images,
		}))
)

const videoSetup = ref(
	props.data.images.find(item => item.type === "video") || null
)


const validImages = computed(() =>
  imagesSetup.value
    .filter(item => item.images) // only keep if images exist
    .flatMap(item => {
      const images = Array.isArray(item.images) ? item.images : [item.images] // normalize to array
      return images.map(img => ({
        source: img,
        thumbnail: img
      }))
    })
)
</script>

<template>
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Image Preview & Thumbnails -->
			<div class="bg-white   p-4 lg:p-5">
				<ImageProducts v-if="validImages.length" :images="validImages" :breakpoints="{
					0: { slidesPerView: 3 },
					480: { slidesPerView: 4 },
					640: { slidesPerView: 5 },
					1024: { slidesPerView: 6 }
				}" class="overflow-x-auto">
					<template #image-thumbnail="{ image, index }">
						<div
							class="aspect-square w-full overflow-hidden group relative rounded-lg border border-gray-200">
							<Image :src="image.thumbnail" :alt="`Thumbnail ${index + 1}`"
								class="block w-full h-full object-cover" />
							<!-- Delete Icon -->
							<ModalConfirmationDelete :routeDelete="{
								name: props.data.deleteImageRoute.name,
								parameters: {
									...props.data.deleteImageRoute.parameters,
									media: image.id,
								}
							}" :title="trans('Are you sure you want to delete the image?')"
								:description="trans('This action cannot be undone.')" isFullLoading noLabel="Delete"
								noIcon="fal fa-times">
								<template #default="{ changeModel }">
									<div @click="changeModel"
										class="absolute top-2 right-2 bg-white shadow-md rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition cursor-pointer hover:bg-red-500 hover:text-white text-red-500">
										<FontAwesomeIcon icon="fal fa-times" fixed-width />
									</div>
								</template>
							</ModalConfirmationDelete>
						</div>
					</template>
				</ImageProducts>

				<!-- Empty State -->
				<div v-else
					class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg">
					<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
					<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
				</div>
			</div>
		</div>

		<!-- Product Summary -->
		<ProductSummary :data="data.product.data" :gpsr="data.gpsr" :properties="data.properties" :parts="data.parts" :video="videoSetup.url" />
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