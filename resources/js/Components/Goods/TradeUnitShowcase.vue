<script setup lang="ts">
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Image from "@/Components/Image.vue"
import { ref, computed, watch } from "vue"
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
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { router } from "@inertiajs/vue3"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from "primevue/dialog"
import { faImage } from "@far"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import ProductSummary from "@/Components/Product/ProductSummary.vue"
import EditTradeUnit from "./EditTradeUnit.vue"

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
	data: {
		tradeUnit: TradeUnit
		brand: {}
		brand_routes: Record<string, routeType>
		tag_routes: Record<string, routeType>
		tags: {}[]
		tags_selected_id: number[]
    gpsr:any
		translation_box: {
			title: string
			save_route: routeType
		}
	}
}>()

console.log("Trade Unit Showcase : ", props)

const selectedImage = ref(0)
const isLoading = ref<string[] | number[]>([])
const isModalGallery = ref(false)

const images = computed(() => props.data?.tradeUnit?.data?.images ?? [])

function changeSelectedImage(index: number) {
	selectedImage.value = index
}

watch(
	images,
	(newVal) => {
		if (!newVal?.length || selectedImage.value > newVal.length - 1) {
			selectedImage.value = 0
		}
	},
	{ immediate: true }
)

const deleteImage = async (image, index: number) => {
	router.delete(
		route(props.data.deleteImageRoute.name, {
			...props.data.deleteImageRoute.parameters,
			media: image.id,
		}),
		{
			onStart: () => isLoading.value.push(image.id),
			onFinish: () =>
				notify({ title: trans("Success"), text: trans("Image deleted"), type: "success" }),
			onError: () =>
				notify({
					title: trans("Failed"),
					text: trans("Cannot delete image"),
					type: "error",
				}),
		}
	)
}

const onSubmitUpload = async (files: File[], refData = null) => {
	const formData = new FormData()
	files.forEach((file, index) => {
		formData.append(`images[${index}]`, file)
	})

	router.post(
		route(props.data.uploadImageRoute.name, props.data.uploadImageRoute.parameters),
		formData,
		{
			preserveScroll: true,

			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("New image added"),
					type: "success",
				})

				isModalGallery.value = false
			},
			onError: () => {
				notify({
					title: trans("Upload failed"),
					text: trans("Failed to add new image"),
					type: "error",
				})
			},
		}
	)
}
</script>

<template>
  <div>
   <EditTradeUnit
      :tags_selected_id="props.data.tags_selected_id"
      :brand="props.data.brand"
      :brand_routes="props.data.brand_routes"
      :tags="props.data.tags"
      :tag_routes="props.data.tag_routes"
    />
  </div>

   <TranslationBox
        v-bind="data.translation_box" 
        :master="data.tradeUnit" 
        :needTranslation="data.tradeUnit" 
        
    />
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Image Preview & Thumbnails -->
			<div class="bg-white rounded-xl shadow-sm p-4 lg:p-5">
				<ImageProducts
					v-if="data.tradeUnit.images?.length"
					:images="data.tradeUnit.images"
					:breakpoints="{
						0: { slidesPerView: 3 },
						480: { slidesPerView: 4 },
						640: { slidesPerView: 5 },
						1024: { slidesPerView: 6 },
					}"
					class="overflow-x-auto">
					<template #image-thumbnail="{ image, index }">
						<div
							class="aspect-square w-full overflow-hidden group relative rounded-lg border border-gray-200">
							<Image
								:src="image.thumbnail"
								:alt="`Thumbnail ${index + 1}`"
								class="block w-full h-full object-cover" />
							<ModalConfirmationDelete
								:routeDelete="{
									name: props.data.deleteImageRoute.name,
									parameters: {
										...props.data.deleteImageRoute.parameters,
										media: image.id,
									},
								}"
								:title="trans('Are you sure you want to delete the image?')"
								:description="trans('This action cannot be undone.')"
								isFullLoading
								noLabel="Delete"
								noIcon="fal fa-times">
								<template #default="{ changeModel }">
									<div
										@click="changeModel"
										class="absolute top-2 right-2 bg-white shadow-md rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition cursor-pointer hover:bg-red-500 hover:text-white text-red-500">
										<FontAwesomeIcon icon="fal fa-times" fixed-width />
									</div>
								</template>
							</ModalConfirmationDelete>
						</div>
					</template>
				</ImageProducts>

				<!-- Empty State -->
				<div
					v-else
					class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg">
					<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
					<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
				</div>

				<!-- Add Image Button -->
				<div class="mt-4">
					<Button
						type="primary"
						full
						@click="isModalGallery = true"
						label="Add Images"
						:icon="faPlus" />
				</div>
			</div>
		</div>
		<!-- tradeUnit Summary -->
		<ProductSummary :data="data.tradeUnit" :gpsr="data.gpsr" />
	</div>

	<!-- Gallery Dialog -->
	<Dialog
		v-model:visible="isModalGallery"
		modal
		closable
		dismissableMask
		header="Gallery Management"
		:style="{ width: '95vw', maxWidth: '900px' }"
		:pt="{ root: { class: 'rounded-xl shadow-xl' } }">
		<GalleryManagement
			:multiple="true"
			:uploadRoute="data.uploadImageRoute"
			:submitUpload="(file, refDAta) => onSubmitUpload(file, refDAta)"
			:imagesUploadedRoutes="data.imagesUploadedRoutes"
			:attachImageRoute="data.attachImageRoute"
			:stockImagesRoute="data.stockImagesRoute"
			@selectImage="(image) => console.log('Selected:', image)" />
	</Dialog>
</template>
