<script setup lang="ts">
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Image from "@/Components/Image.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from "@headlessui/vue"
import { inject, ref, computed } from "vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import axios from "axios"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

import { faTrash as falTrash, faEdit } from "@fal"
import { faCircle, faPlay, faTrash } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { Images } from "@/types/Images"
import { router } from "@inertiajs/vue3"
import { useLocaleStore } from "@/Stores/locale"
import ImageProducts from "@/Components/CMS/Webpage/Product1/ImageProducts.vue"
library.add(faCircle, faTrash, falTrash, faEdit, faPlay)

const props = defineProps<{
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
		stats: [
			{
				amount: any
				amount_ly: any
				name: string
				percentage: any
			}
		]
	}
}>()

const locale = inject("locale", aikuLocaleStructure)
const selectedImage = ref(0)
const isLoading = ref<string[] | number[]>([])

/* const stats = [
    { name: '2024', stat: '71,897', previousStat: '70,946', change: '12%', changeType: 'increase' },
    { name: '2023', stat: '58.16%', previousStat: '56.14%', change: '2.02%', changeType: 'increase' },
    { name: '2022', stat: '24.57%', previousStat: '28.62%', change: '4.05%', changeType: 'decrease' },
    { name: '2021', stat: '71,897', previousStat: '70,946', change: '12%', changeType: 'increase' },
    { name: '2020', stat: '58.16%', previousStat: '56.14%', change: '2.02%', changeType: 'increase' },
    { name: '2019', stat: '24.57%', previousStat: '28.62%', change: '4.05%', changeType: 'decrease' },
]
 */
const product = ref({
	images: props.data?.product?.data?.images, // TODO: No need to use this, 'props.data.product.data.images' instead
})

// NEW: Show only 6 images by default and reveal all on demand
const showAllImages = ref(false)
const displayedImages = computed(() => {
	return showAllImages.value
		? props.data?.product?.data?.images
		: props.data?.product?.data?.images?.slice(0, 6)
})

const deleteImage = async (data, index: number) => {
	// isLoading.value.push(data?.id)

	router.delete(
		route(props.data.deleteImageRoute.name, {
			...props.data.deleteImageRoute.parameters,
			media: data.id,
		}),
		{
			onStart: () => {
				isLoading.value.push(data.id)
			},
			onFinish: () => {
				notify({
					title: trans("Success"),
					text: trans("Image deleted"),
					type: "success",
				})
			},
			onError: () => {
				notify({
					title: trans("Failed"),
					text: trans("Cannot show stock images"),
					type: "error",
				})
			},
		}
	)

	// try {
	//     const response = await axios.delete(route(props.data.deleteImageRoute.name, {
	//         ...props.data.deleteImageRoute.parameters, media: data.id
	//     }));

	//     if(selectedImage.value == index) selectedImage.value = 0
	//     product.value.images.splice(index,1)

	// } catch (error: any) {
	//     console.log('error', error);
	//     notify({
	//         title: 'Failed',
	//         text: 'cannot show stock images',
	//         type: 'error'
	//     })
	// } finally {
	//     isLoading.value.push(data.id)
	//     removeLodash(isLoading.value, (n) => {
	//         return n == data.id
	//     })
	// }
}

function changeSelectedImage(index: number) {
	selectedImage.value = index
}

const showAllStats = ref(false)
const displayedStats = computed(() => {
	const filtered = props.data.stats.filter((item) => !item.name.toLowerCase().includes("all"))
	return showAllStats.value ? filtered : filtered.slice(0, 6)
})

const isModalGallery = ref(false)
</script>

<template>
	<!-- <pre>{{ props.data }}</pre> -->
	<div class="grid md:grid-cols-4 gap-x-1 gap-y-4">
		<div class="p-5 space-y-5 grid grid-cols-1 md:grid-cols-1 max-w-[500px]">
			<div class="relative">
				<div class="h-full rounded-lg md:shadow">
					<!-- Section: Gallery (primary and list) -->
					<!--  <ImageProducts  :images="modelValue.product.images.data" /> -->
					<TabGroup
						as="div"
						class="grid grid-cols-2 md:grid-cols-1 p-0 md:p-2.5 gap-x-3 h-full"
						:selectedIndex="selectedImage"
						@change="changeSelectedImage">
						<!-- Section: Main image (big) -->
						<TabPanels class="overflow-hidden duration-300">
							<template v-if="props.data?.product?.data?.images?.length > 0">
								<TabPanel
									v-for="image in props.data?.product?.data?.images"
									:key="image.id">
									<div
										class="relative flex items-center border border-gray-200 rounded-lg aspect-square w-auto h-auto overflow-hidden">
										<div
											class="absolute top-1 right-3 flex items-center gap-2 capitalize"
											:class="
												data.product?.data?.state === 'active'
													? 'text-green-500'
													: ''
											">
											<FontAwesomeIcon
												v-if="data.product?.data?.state === 'active'"
												icon="fas fa-circle"
												class="text-xs animate-pulse"
												fixed-width
												aria-hidden="true" />
											{{ data.product?.data?.state }}
										</div>
										<Image :src="image.source" :alt="image.name" class="" />
									</div>
								</TabPanel>
							</template>

							<template v-else>
								<TabPanel>
									<EmptyState
										:data="{
											title: 'You don\'t have any images',
											description: 'Click to upload',
										}"
										@click="isModalGallery = true"
										class="cursor-pointer hover:bg-gray-50" />
								</TabPanel>
							</template>
						</TabPanels>

						<!-- Section: Images list -->
						<div
							@scroll="(eee) => console.log('on scroll', eee)"
							class="h-44 md:h-64 md:max-h-80 overflow-y-auto mx-auto md:mt-6 w-full max-w-2xl block lg:max-w-none">
							<TabList
								class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-4 md:gap-x-2 md:gap-y-5">
								<!-- Adjusted: iterate over computed displayedImages instead of all images -->
								<Tab
									v-for="(image, index) in displayedImages"
									:key="image.id"
									class="group relative flex aspect-square h-auto cursor-pointer items-center justify-center rounded-md text-gray-900 hover:bg-gray-50"
									v-slot="{ selected }">
									<!-- <div class="absolute top-0 left-0 bg-indigo-500 z-10 text-xxs py-[1px] px-[1px]">{{ trans('Primary') }}</div> -->
									<span
										class="flex items-center absolute inset-0 overflow-hidden rounded-md">
										<Image :src="image.source" alt="" class="" />
									</span>

									<div
										:class="[
											selected
												? 'ring-2 ring-offset-2 ring-indigo-500'
												: 'ring-1 ring-gray-300',
											'pointer-events-none absolute inset-0 rounded-md ',
										]"
										aria-hidden="true"></div>

									<div
										v-if="!isLoading.includes(image.id)"
										@click.stop="deleteImage(image, index)"
										class="hidden absolute top-0.5 right-0.5 py-1 px-0.5 rounded group-hover:flex justify-center items-center bg-red-500/50 hover:bg-red-500 cursor-pointer text-white">
										<FontAwesomeIcon
											icon="fal fa-trash"
											class="text-xs"
											fixed-width
											aria-hidden="true" />
									</div>
									<div
										v-else
										class="absolute inset-0 rounded-sm flex justify-center items-center bg-red-500/70 cursor-pointer text-white">
										<LoadingIcon />
									</div>
								</Tab>

								<div
									v-if="
										!showAllImages &&
										props.data?.product?.data?.images?.length > 6
									"
									@click="showAllImages = true"
									class="border border-dashed border-gray-300 relative flex aspect-square h-auto cursor-pointer items-center justify-center rounded-md hover:bg-gray-100">
									<span class="text-sm font-medium">Show more</span>
								</div>

								<div
									@click="isModalGallery = true"
									class="border border-dashed border-gray-300 relative flex aspect-square h-auto cursor-pointer items-center justify-center rounded-md hover:bg-gray-100">
									<FontAwesomeIcon
										icon="fal fa-plus"
										class="text-xl md:text-2xl"
										fixed-width
										aria-hidden="true" />
								</div>
							</TabList>
						</div>
					</TabGroup>
				</div>
			</div>

			<!-- Order summary -->
			<section
				aria-labelledby="summary-heading"
				class="border border-gray-200 rounded-lg px-4 py-6 sm:p-4 lg:mt-0 lg:p-5">
				<h2 id="summary-heading" class="text-lg font-medium">
					{{ trans("Product summary") }}
				</h2>

				<dl class="mt-6 space-y-4">
					<div class="flex items-center justify-between">
						<dt class="text-sm">{{ trans("Added date") }}</dt>
						<dd class="text-sm font-medium">
							{{ useFormatTime(data.product?.data?.created_at) }}
						</dd>
					</div>

					<div class="flex items-center justify-between">
						<dt class="text-sm">{{ trans("Stock") }}</dt>
						<dd class="text-sm font-medium">-- pcs</dd>
					</div>

					<div class="flex items-center justify-between">
						<dt class="text-sm">{{ trans("Cost") }}</dt>
						<dd class="text-sm font-medium">--</dd>
					</div>

					<div class="flex items-center justify-between">
						<dt class="text-sm">{{ trans("Price") }}</dt>
						<dd class="text-sm font-medium text-right">
							{{ locale.currencyFormat("usd", data.product?.data?.price) }}
							<span class="font-light">margin (--)</span>
						</dd>
					</div>

					<div class="flex items-center justify-between">
						<dt class="text-sm">RRP</dt>
						<dd class="text-sm font-medium text-right">
							--- <span class="font-light">margin (--)</span>
						</dd>
					</div>
				</dl>
			</section>
		</div>

		<!-- Revenue -->
		<div class="pt-8 p-4 md:col-span-3">
			<!-- Header with responsive text sizes -->
			<h3 class="text-base md:text-lg font-semibold leading-6">
				{{ trans("All Sales") }}:
				{{
					useLocaleStore().currencyFormat(
						data.product?.data?.currency_code ? data.product?.data?.currency_code : "usd",
						data?.stats?.[0].amount 
					)
				}}
			</h3>
			<!-- Responsive grid for stat cards -->
			<dl
				class="mt-5 grid grid-cols-1 gap-4 overflow-hidden rounded bg-white md:grid-cols-3 md:gap-x-2 md:gap-y-4">
				<!-- Loop over displayedStats instead of full props.data.stats -->
				<div v-if="props.data.stats"
					v-for="item in displayedStats"
					:key="item.name"
					class="px-4 py-5 sm:p-6 border border-gray-200 rounded-md">
					<dt class="text-base font-normal">{{ item.name }}</dt>
					<dd class="mt-1 flex flex-col sm:flex-row items-baseline justify-between">
						<div class="flex items-baseline text-2xl font-semibold text-indigo-600">
							{{
								useLocaleStore().currencyFormat(
									data.product.data.currency_code ? data.product.data.currency_code : "usd",
									item.amount
								)
							}}
							<span
								class="ml-2 text-sm font-medium text-gray-500"
								v-tooltip="'Last Year Sales'">
								from
								{{
									useLocaleStore().currencyFormat(
										data.product.data.currency_code ? data.product.data.currency_code : "usd",
										item.amount_ly
									)
								}}
							</span>
						</div>
						<div>
							<span class="text-[14px] md:text-[16px] font-mono pr-1">
								{{
									item?.percentage
										? `${
												item.percentage > 0 ? "+" : ""
										  }${item.percentage.toFixed(2)}%`
										: `0.0%`
								}}
							</span>
							<FontAwesomeIcon
								v-if="item?.percentage"
								class="text-[9px] md:text-[14px] opacity-60"
								:icon="item.percentage < 0 ? 'fas fa-play' : 'fas fa-play'"
								:class="
									item.percentage < 0
										? 'text-red-500 rotate-90'
										: 'text-green-500 rotate-[-90deg]'
								" />
						</div>
					</dd>
				</div>
			</dl>
			<!-- "Show more" card: full width on mobile, fixed width on larger screens -->
			<div
				v-if="props.data?.stats?.length > 6 && !showAllStats"
				@click="showAllStats = true"
				class="cursor-pointer border border-dashed border-gray-300 rounded-md mt-3 flex items-center justify-center p-4 w-full sm:w-40 mx-auto">
				<span class="text-sm font-medium">{{ trans("Show more") }}</span>
			</div>
		</div>

		<!-- <pre>{{ data.product }}</pre> -->
	</div>

	<Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
		<GalleryManagement
			:uploadRoute="data.uploadImageRoute"
			:imagesUploadedRoutes="data.imagesUploadedRoutes"
			:attachImageRoute="data.attachImageRoute"
			:stockImagesRoute="data.stockImagesRoute"
			@selectImage="(image: {}) => console.log('image', image)" />
	</Modal>
</template>
