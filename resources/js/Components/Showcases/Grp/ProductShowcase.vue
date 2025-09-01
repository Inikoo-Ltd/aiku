<script setup lang="ts">
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Image from "@/Components/Image.vue"
// import { Tab, TabGroup, TabList, TabPanel, TabPanels } from "@headlessui/vue"
import { inject, ref, computed, watch } from "vue"
// import EmptyState from "@/Components/Utils/EmptyState.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo } from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
// import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { Images } from "@/types/Images"
import { Link, router } from "@inertiajs/vue3"
// import { useLocaleStore } from "@/Stores/locale"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from 'primevue/dialog'
import { faImage } from "@far"
import EditTradeUnit from "@/Components/Goods/EditTradeUnit.vue"
import { Fieldset, Select } from "primevue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { Accordion, AccordionPanel, AccordionHeader, AccordionContent } from 'primevue'
import ProductSummary from "@/Components/Product/ProductSummary.vue"
// import TranslationBox from '@/Components/TranslationBox.vue';


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
	}
}>()
console.log('qqq', props.data.trade_units)

console.log(props.data)

const locale = inject("locale", aikuLocaleStructure)
const selectedImage = ref(0)
const isLoading = ref<string[] | number[]>([])
const showAllImages = ref(false)
const showAllStats = ref(false)
const isModalGallery = ref(false)
const showFullWarnings = ref(false)
const showFullInstructions = ref(false)

const images = computed(() => props.data?.product?.data?.images ?? [])

const displayedImages = computed(() =>
	showAllImages.value ? images.value : images.value.slice(0, 6)
)

const displayedStats = computed(() => {
	if (!props.data.stats) return []
	const filtered = props.data.stats.filter(item => !item.name.toLowerCase().includes("all"))
	return showAllStats.value ? filtered : filtered.slice(0, 6)
})

function changeSelectedImage(index: number) {
	selectedImage.value = index
}

watch(images, (newVal) => {
	if (!newVal?.length || selectedImage.value > newVal.length - 1) {
		selectedImage.value = 0
	}
}, { immediate: true })

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
					title: trans('Success'),
					text: trans('New image added'),
					type: 'success',
				})


				isModalGallery.value = false


			},
			onError: () => {

				notify({
					title: trans('Upload failed'),
					text: trans('Failed to add new image'),
					type: 'error',
				})
			},

		}
	)
}

const selectedTradeUnit = ref(props.data.trade_units.length > 0 ? props.data.trade_units[0].tradeUnit.code : null)
const compSelectedTradeUnit = computed(() => {
	return props.data.trade_units.find((unit) => unit.tradeUnit.code === selectedTradeUnit.value)
})

const hazardDefinitions = ref([
	{ key: 'acuteToxicity', name: 'Acute Toxicity', icon: 'toxic-icon.png' },
	{ key: 'corrosive', name: 'Corrosive', icon: 'corrosive-icon.png' },
	{ key: 'explosive', name: 'Explosive', icon: 'explosive.jpg' },
	{ key: 'flammable', name: 'Flammable', icon: 'flammable.png' },
	{ key: 'gasUnderPressure', name: 'Gas under pressure', icon: 'gas.png' },
	{ key: 'environmentHazard', name: 'Hazards to the environment', icon: 'hazard-env.png' },
	{ key: 'healthHazard', name: 'Health hazard', icon: 'health-hazard.png' },
	{ key: 'oxidising', name: 'Oxidising', icon: 'oxidising.png' },
	{ key: 'seriousHealthHazard', name: 'Serious Health hazard', icon: 'serious-health-hazard.png' }
])

const getHazardIconPath = (iconName) => {
	return `/hazardIcon/${iconName}`
}

const getActiveHazards = () => {
	return hazardDefinitions.value.filter(hazard => {
		switch (hazard.key) {
			case 'acuteToxicity': return props.data.gpsr.acute_toxicity
			case 'corrosive': return props.data.gpsr.corrosive
			case 'explosive': return props.data.gpsr.explosive
			case 'flammable': return props.data.gpsr.flammable
			case 'gasUnderPressure': return props.data.gpsr.gas_under_pressure
			case 'environmentHazard': return props.data.gpsr.hazard_environment
			case 'healthHazard': return props.data.gpsr.health_hazard
			case 'oxidising': return props.data.gpsr.oxidising
			default: return false
		}
	})
}

// console.log(props)
</script>

<template>
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mx-3 lg:mx-0 mt-2">
		<!-- Sidebar -->
		<div class="space-y-4 lg:space-y-6">
			<!-- Image Preview & Thumbnails -->
			<div class="bg-white rounded-xl shadow-sm  p-4 lg:p-5">
				<ImageProducts v-if="data.product.data.images?.length" :images="data.product.data.images" :breakpoints="{
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

				<!-- Add Image Button -->
				<div class="mt-4">
					<Button type="primary" full @click="isModalGallery = true" label="Add Images" :icon="faPlus" />
				</div>
			</div>
		</div>

		<!-- Right Section -->
		<div class="space-y-4 lg:space-y-6">
			<div>
				<Fieldset class="bg-white rounded-xl shadow-sm w-full md:w-auto" legend="Trade units">
					<template #legend>
						<div class="flex items-center gap-2 font-bold">
							<FontAwesomeIcon icon="fal fa-atom" class="text-gray-400" fixed-width />
							Trade units
						</div>
					</template>

					<template #default>
						<div>
							<template v-if="props.data.trade_units.length">
								<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-4">
									<Select v-model="selectedTradeUnit" :options="props.data.trade_units"
										optionLabel="tradeUnit.name" optionValue="tradeUnit.code"
										placeholder="Select a City" class="w-full sm:w-80" />
									<Link v-if="compSelectedTradeUnit?.tradeUnit?.slug"
										:href="route('grp.goods.trade-units.show', compSelectedTradeUnit?.tradeUnit.slug)"
										v-tooltip="trans('Open trade unit')"
										class="text-gray-400 hover:text-gray-600 text-center sm:text-left">
									<FontAwesomeIcon icon="fal fa-external-link" fixed-width />
									</Link>
								</div>
								<EditTradeUnit v-if="compSelectedTradeUnit" v-bind="compSelectedTradeUnit" />
							</template>
							<div v-else class="text-gray-500 text-center py-4">
								{{ trans("No trade units for this product") }}
							</div>
						</div>
					</template>
				</Fieldset>
			</div>

			<!-- <TranslationBox v-bind="data.translation_box" :master="data.product.data" :needTranslation="data.product.data" /> -->
		</div>

		<!-- Product Summary -->
		<ProductSummary :data="{...data.product.data, gpsr : data.gpsr}" />
	</div>

	<!-- Gallery Dialog -->
	<Dialog v-model:visible="isModalGallery" modal closable dismissableMask header="Gallery Management"
		:style="{ width: '95vw', maxWidth: '900px' }" :pt="{ root: { class: 'rounded-xl shadow-xl' } }">
		<GalleryManagement :multiple="true" :uploadRoute="data.uploadImageRoute"
			:submitUpload="(file, refDAta) => onSubmitUpload(file, refDAta)"
			:imagesUploadedRoutes="data.imagesUploadedRoutes" :attachImageRoute="data.attachImageRoute"
			:stockImagesRoute="data.stockImagesRoute" @selectImage="(image) => console.log('Selected:', image)" />
	</Dialog>
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