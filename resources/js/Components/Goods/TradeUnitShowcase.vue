<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
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
import { faImage } from "@far"
import ProductSummary from "@/Components/Product/ProductSummary.vue"

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
		gpsr: any
		images: {}
		translation_box: {
			title: string
			save_route: routeType
		}
	}
}>()

const selectedImage = ref(0)
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

const images = computed(() => props.data?.tradeUnit?.data?.images ?? [])


watch(
	images,
	(newVal) => {
		if (!newVal?.length || selectedImage.value > newVal.length - 1) {
			selectedImage.value = 0
		}
	},
	{ immediate: true }
)


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
			<div class="bg-white rounded-xl  p-4 lg:p-5">
				<ImageProducts v-if="validImages.length" :images="validImages" :breakpoints="{
					0: { slidesPerView: 3 },
					480: { slidesPerView: 4 },
					640: { slidesPerView: 5 },
					1024: { slidesPerView: 6 },
				}" class="overflow-x-auto" />

				<div v-else
					class="flex flex-col items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-200 rounded-lg h-80">
					<FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
					<p class="text-sm text-gray-500 text-center">No images uploaded yet</p>
				</div>
			</div>
		</div>
		<!-- tradeUnit Summary -->
		<ProductSummary :data="data.tradeUnit" :gpsr="data.gpsr" :type="'trade_unit'" :video="videoSetup.url" />
	</div>
</template>
