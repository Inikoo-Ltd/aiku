<script setup lang="ts">
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Image from "@/Components/Image.vue"
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from "@headlessui/vue"
import { inject, ref, computed, watch } from "vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faTrash as falTrash, faEdit, faExternalLink } from "@fal"
import { faCircle, faPlay, faTrash, faPlus } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { Images } from "@/types/Images"
import { Link, router } from "@inertiajs/vue3"
import { useLocaleStore } from "@/Stores/locale"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from 'primevue/dialog'
import { faImage } from "@far"
import EditTradeUnit from "@/Components/Goods/EditTradeUnit.vue"
import { Fieldset, Select } from "primevue"


library.add(faCircle, faTrash, falTrash, faEdit, faExternalLink, faPlay, faPlus)

const props = defineProps<{
	taxonomy: any
	data: {
		stockImagesRoute: routeType
		uploadImageRoute: routeType
		attachImageRoute: routeType
		deleteImageRoute: routeType
		imagesUploadedRoutes: routeType
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
		}[]
	}
}>()
console.log('qqq', props.data.trade_units)

const locale = inject("locale", aikuLocaleStructure)
const selectedImage = ref(0)
const isLoading = ref<string[] | number[]>([])
const showAllImages = ref(false)
const showAllStats = ref(false)
const isModalGallery = ref(false)

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
</script>

<template>
	<div class="grid md:grid-cols-4 gap-x-1 gap-y-4">
		<!-- Sidebar -->
		<div class="p-5 space-y-5 grid grid-cols-1 max-w-[500px]">
			<!-- Image Preview & Thumbnails -->
			<div class="relative">
				<!-- Image Gallery -->
				<ImageProducts v-if="data.product.data.images?.length" :images="data.product.data.images">
					<template #image-thumbnail="{ image, index }">
						<div class="aspect-square w-full overflow-hidden group relative">
							<Image :src="image.thumbnail" :alt="`Thumbnail ${index + 1}`"
								class="block w-full h-full object-cover rounded-md border" />
							<!-- Delete Button on Hover -->
							<button @click.prevent="deleteImage(image, index)"
								class="absolute top-1 right-1 bg-white border border-gray-300 text-gray-700 p-1 opacity-0 group-hover:opacity-100 transition-opacity z-10 shadow-md hover:bg-red-500 hover:text-white">
								<FontAwesomeIcon :icon="faTrash" class="text-sm" />
							</button>
						</div>
					</template>
				</ImageProducts>

				<!-- Empty State -->
				<div v-else
					class="flex flex-col items-center justify-center text-gray-500 gap-2 py-8 border border-dashed border-gray-300 rounded-md">
					<FontAwesomeIcon :icon="faImage" class="text-4xl" />
					<p class="text-sm">No images uploaded yet</p>
				</div>

				<!-- Add Image Button -->
				<div class="mt-3">
					<Button type="dashed" full @click="isModalGallery = true" label="Add Images" :icon="faPlus" />
				</div>
			</div>


			<!-- Product Summary -->
			<section class="border border-gray-200 rounded-lg px-4 py-6">
				<h2 class="text-lg font-medium">{{ trans("Product summary") }}</h2>
				<dl class="mt-6 space-y-4 text-sm">
					<div class="flex justify-between">
						<dt>{{ trans("Added date") }}</dt>
						<dd class="font-medium">{{ useFormatTime(data.product.data.created_at) }}</dd>
					</div>
					<div class="flex justify-between">
						<dt>{{ trans("Stock") }}</dt>
						<dd class="font-medium">-- pcs</dd>
					</div>
					<div class="flex justify-between">
						<dt>{{ trans("Cost") }}</dt>
						<dd class="font-medium">--</dd>
					</div>
					<div class="flex justify-between">
						<dt>{{ trans("Price") }}</dt>
						<dd class="font-medium text-right">
							{{ locale.currencyFormat(data.product.data.currency_code || "usd", data.product.data.price)
							}}
							<span class="font-light">margin (--)</span>
						</dd>
					</div>
					<div class="flex justify-between">
						<dt>RRP</dt>
						<dd class="font-medium text-right">--- <span class="font-light">margin (--)</span></dd>
					</div>
				</dl>
			</section>
		</div>
		
		<div>

		</div>

		<div class="md:col-span-2 pr-6">
			<Fieldset
				class="p-5 space-y-5 h-fit w-full max-w-lg"
				legend="Trade units"
				xtoggleable
				xcollapsed
			>
				<template #legend>
					<div class="flex items-center gap-2 font-bold">
						<FontAwesomeIcon icon="fal fa-atom" class="text-gray-400 text-lg" fixed-width aria-hidden="true" />
						Trade units
					</div>
				</template>

				<template #default>
					<div class="px-4">
						<div class="flex items-center gap-x-2 mb-4">
							<template v-if="props.data.trade_units.length">
								<Select
									v-model="selectedTradeUnit"
									:options="props.data.trade_units"
									optionLabel="tradeUnit.name"
									optionValue="tradeUnit.code"
									placeholder="Select a City"
									class="w-full md:w-80"
								/>
								<Link
									v-if="compSelectedTradeUnit?.tradeUnit?.slug"
									:href="route('grp.goods.trade-units.show', compSelectedTradeUnit?.tradeUnit.slug)"
									v-tooltip="trans('Open trade unit')"
									class="text-gray-400 hover:text-gray-600 cursor-pointer"
								>
									<FontAwesomeIcon icon="fal fa-external-link" class="" fixed-width aria-hidden="true" />
								</Link>
							</template>
							<div v-else class="text-gray-500 text-center mx-auto">
								No trade units for this product
							</div>
						</div>

						<div v-if="compSelectedTradeUnit" class="">
							<EditTradeUnit
								:tags_selected_id="compSelectedTradeUnit.tags_selected_id"
								:brand="compSelectedTradeUnit.brand"
								:brand_routes="compSelectedTradeUnit.brand_routes"
								:tags="compSelectedTradeUnit.tags"
								:tag_routes="compSelectedTradeUnit.tag_routes"
							/>
						</div>
					</div>
				</template>
			</Fieldset>
		</div>

		<!-- Revenue Stats -->
		<div v-if="false && data.stats" class="pt-8 p-4 md:col-span-3">
			<h3 class="text-lg font-semibold">
				{{ trans("All Sales") }}:
				{{ useLocaleStore().currencyFormat(data.product.data.currency_code || "usd", data?.stats?.[0]?.amount ??
					0) }}
			</h3>

			<dl class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4 bg-white">
				<div v-for="item in displayedStats" :key="item.name"
					class="px-4 py-5 border border-gray-200 rounded-md">
					<dt class="text-base font-normal">{{ item.name }}</dt>
					<dd class="mt-1 flex flex-col sm:flex-row items-baseline justify-between min-h-[48px]">
						<div class="flex items-baseline text-2xl font-semibold text-indigo-600">
							<span v-if="item.amount !== null && item.amount !== undefined">
								{{ useLocaleStore().currencyFormat(data.product.data.currency_code || 'usd',
									item.amount) }}
							</span>
							<span v-else>-</span>
							<span class="ml-2 text-sm font-medium text-gray-500">
								from
								<span v-if="item.amount_ly !== null && item.amount_ly !== undefined">
									{{ useLocaleStore().currencyFormat(data.product.data.currency_code || 'usd',
										item.amount_ly)
									}}
								</span>
								<span v-else>-</span>
							</span>
						</div>
						<div class="flex items-center mt-2 md:mt-0">
							<span class="text-sm font-mono pr-1">
								<span v-if="item.percentage !== null && item.percentage !== undefined">
									{{ item.percentage > 0 ? '+' : '' }}{{ item.percentage.toFixed(2) }}%
								</span>
								<span v-else>0.00%</span>
							</span>
							<FontAwesomeIcon v-if="item.percentage !== null && item.percentage !== undefined"
								icon="fas fa-play"
								:class="item.percentage < 0 ? 'text-red-500 rotate-90' : 'text-green-500 rotate-[-90deg]'"
								class="text-xs opacity-60" />
						</div>
					</dd>
				</div>
			</dl>

			<!-- Show more stats -->
			<div v-if="props.data?.stats?.length > 6 && !showAllStats" @click="showAllStats = true"
				class="cursor-pointer border border-dashed border-gray-300 rounded-md mt-3 flex justify-center items-center p-4 w-full sm:w-40 mx-auto">
				<span class="text-sm font-medium">{{ trans("Show more") }}</span>
			</div>
		</div>
	</div>

	<!-- Gallery Dialog (PrimeVue) -->
	<Dialog v-model:visible="isModalGallery" modal closable dismissableMask header="Gallery Management"
		:style="{ width: '75vw' }" :pt="{ root: { class: 'rounded-xl shadow-xl' } }">
		<GalleryManagement :multiple="true" :uploadRoute="data.uploadImageRoute" :submitUpload="(file,refDAta)=>onSubmitUpload(file,refDAta)"
			:imagesUploadedRoutes="data.imagesUploadedRoutes" :attachImageRoute="data.attachImageRoute"
			:stockImagesRoute="data.stockImagesRoute" @selectImage="(image) => console.log('Selected:', image)" />
	</Dialog>
</template>
