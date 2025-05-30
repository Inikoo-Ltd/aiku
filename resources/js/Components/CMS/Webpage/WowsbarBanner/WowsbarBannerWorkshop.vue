<script setup lang="ts">
import { ref, onMounted, inject, watch } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { trans } from "laravel-vue-i18n"
import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue"
import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"

import { faPresentation, faLink, faExternalLink } from "@fal"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImages, faPlus } from "@fas"
import Image from "@/Components/Image.vue"
import Paginator from "primevue/paginator"
import { faPencil } from "@far"

library.add(faPresentation, faLink, faExternalLink, faSpinnerThird)

const props = defineProps<{
	modelValue: any
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout = inject("layout", layoutStructure)
const bannersList = ref([])
const isModalOpen = ref(false)
const data = ref(null)
const isLoading = ref(false)
const isLoadingFetching = ref(false)

// Add a reactive pagination object to store meta info
const pagination = ref({
	current_page: 1,
	per_page: 10,
	total: 0,
})

const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
	(e: "autoSave"): void
}>()

const onPickBanner = (banner) => {
	emits("update:modelValue", {
		...props.modelValue,
		emptyState: false,
		banner_id: banner.id,
		banner_slug: banner.slug,
	})
	emits("autoSave")
	isModalOpen.value = false
}

const getRouteIndex = () => {
	const currentRoute = route().current()
	if (currentRoute.includes("fulfilments") || route().params["fulfilment"]) {
		return route("grp.org.fulfilments.show.web.banners.index", {
			organisation: route().params["organisation"],
			fulfilment: route().params["fulfilment"],
			website: route().params["website"],
		})
	} else {
		return route("grp.org.shops.show.web.banners.index", {
			organisation: route().params["organisation"],
			shop: route().params["shop"],
			website: route().params["website"],
		})
	}
}

const getRouteShow = () => {
	const currentRoute = route().current()
	if (currentRoute.includes("fulfilments") || route().params["fulfilment"]) {
		return route("grp.org.fulfilments.show.web.banners.show", {
			organisation: route().params["organisation"],
			fulfilment: route().params["fulfilment"],
			website: route().params["website"],
			banner: props.modelValue.banner_slug,
		})
	} else {
		return route("grp.org.shops.show.web.banners.show", {
			organisation: route().params["organisation"],
			shop: route().params["shop"],
			website: route().params["website"],
			banner: props.modelValue.banner_slug,
		})
	}
}

const getRouteCreate = () => {
	const currentRoute = route().current()
	if (currentRoute.includes("fulfilments") || route().params["fulfilment"]) {
		return route("grp.org.fulfilments.show.web.banners.create", {
			organisation: route().params["organisation"],
			fulfilment: route().params["fulfilment"],
			website: route().params["website"],
		})
	} else {
		return route("grp.org.shops.show.web.banners.create", {
			organisation: route().params["organisation"],
			shop: route().params["shop"],
			website: route().params["website"],
		})
	}
}

const getBannersList = async (page = 1): Promise<void> => {
	try {
		isLoadingFetching.value = true
		const url = getRouteIndex()
		console.log(getRouteIndex())
		const response = await axios.get(url, {
			params: {
				"filter[state]": "live",
				page: page,
			},
		})
		isLoadingFetching.value = false
		bannersList.value = response.data.data
		// Update pagination meta if available
		if (response.data.meta) {
			pagination.value.current_page = response.data.meta.current_page
			pagination.value.per_page = response.data.meta.per_page
			pagination.value.total = response.data.meta.total
		}
	} catch (error: any) {
		console.error(error)
		isLoadingFetching.value = false
		notify({
			title: "Failed to fetch banners data",
			text: error.message || "An error occurred",
			type: "error",
		})
	}
}

const onPageChange = (event: any) => {
	// event.page is zero-indexed, so add 1 to get the actual page number
	const newPage = event.page + 1
	getBannersList(newPage)
}

const getDataBanner = async (): Promise<void> => {
	if (props.modelValue.banner_slug) {
		try {
			isLoading.value = true
			const url = getRouteShow()
			const response = await axios.get(url)
			data.value = response.data
			isLoading.value = false
		} catch (error: any) {
			console.error(error)
			isLoading.value = false
			notify({
				title: "Failed to fetch banners data",
				text: error.message || "An error occurred",
				type: "error",
			})
		}
	}
}

// Watch for changes on the modelValue to update banner data
watch(
	() => props.modelValue,
	(newValue, oldValue) => {
		if (newValue.banner_slug !== oldValue.banner_slug) {
			getDataBanner()
		}
	}
)

onMounted(() => {
	if (props.modelValue.banner_slug && props.modelValue.banner_id) getDataBanner()
})
</script>

<template>
	<div v-if="isLoading" class="flex justify-center h-36 items-center">
		<LoadingIcon class="text-4xl" />
	</div>

	<div v-else-if="!props.modelValue.banner_id && !props.modelValue.banner_slug" class="h-64">
		<div
			class="flex justify-center gap-6 h-full border border-dashed border-gray-300 rounded-md p-6">
			<a
				target="_blank"
				:href="getRouteCreate()"
				class="flex flex-col items-center justify-center w-40 h-40 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition">
				<FontAwesomeIcon :icon="faPlus" class="text-4xl" />
				<span class="mt-2 text-sm font-medium">Create Banner</span>
			</a>
			<button
				@click="
					() => {
						isModalOpen = true
						getBannersList()
					}
				"
				class="flex flex-col items-center justify-center w-40 h-40 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
				<FontAwesomeIcon :icon="faImages" class="text-4xl" />
				<span class="mt-2 text-sm font-medium">Banner Gallery</span>
			</button>
		</div>
	</div>

	<section
		v-else-if="props.modelValue.banner_id && props.modelValue.banner_slug && data"
		class="relative">
		<div
			v-if="data.state != 'switch_off'"
			:style="getStyles(modelValue?.container?.properties,screenType)">
			<SliderLandscape
				v-if="data.type == 'landscape'"
				:data="data.compiled_layout"
				:production="true" :view="screenType"/>
			<SliderSquare v-else :data="data.compiled_layout" :production="true":view="screenType" />

			<!-- Icon: Edit -->
			<div class="absolute top-2 right-2 flex space-x-2 z-10">
				<Button
					:icon="faPencil"
					type="tertiary"
					size="xs"
					@click="
						() => {
							isModalOpen = true
							getBannersList()
						}
					" />
			</div>
		</div>
		<div v-else>
			<div class="absolute top-2 right-2 flex space-x-2 z-10">
				<Button
					:icon="['far', 'fa-pencil']"
					type="tertiary"
					size="xs"
					@click="
						() => {
							isModalOpen = true
							getBannersList()
						}
					" />
			</div>
			<EmptyState
				:data="{
					title:
						data.state != 'switch_off'
							? trans('You do not have slides to show')
							: trans('You turn off the banner'),
					description:
						data.state != 'switch_off'
							? trans('Create new slides in the workshop to get started')
							: trans('need re-publish the banner at workshop'),
				}" />
		</div>
	</section>

	<Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-[1200px]">
		<div class="flex flex-col h-[37rem] ">
			<!-- Header -->
			<div class="text-center font-semibold text-2xl mb-4">
				{{ trans("Banners Gallery") }}
			</div>
 
			<!-- Scrollable grid area -->
			<div class="flex-1 overflow-y-auto">
				<div v-if="!isLoadingFetching">
					<div
						v-if="bannersList.length"
						class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 p-4">
						<div
							v-for="banner in bannersList"
							:key="banner.slug"
							@click="() => onPickBanner(banner)"
							class="bg-white rounded-lg shadow-md overflow-hidden cursor-pointer transform transition duration-300 hover:scale-105 hover:shadow-xl">
							<div class="h-40 bg-gray-100 flex items-center justify-center">
								<Image
									v-if="banner.image_thumbnail"
									:src="banner.image_thumbnail"
									class="object-contain h-full w-full" />
							</div>
							<div class="p-4">
								<h3 class="text-lg font-bold text-gray-900">{{ banner.name }}</h3>
								<p class="text-sm text-gray-500">
									{{ useFormatTime(banner.date) }}
								</p>
							</div>
						</div>
					</div>
					<div v-else class="mt-24 text-center text-gray-500 text-lg italic">
						<div class="mb-2">{{ trans("You have no banner yet.") }}</div>
					</div>
				</div>
				<div v-else class="flex justify-center pt-32 items-center">
					<LoadingIcon class="text-6xl" />
				</div>
			</div>

			<!-- Paginator fixed at the bottom -->
			<div class="mt-4 px-4">
				<Paginator
					:first="(pagination.current_page - 1) * pagination.per_page"
					:rows="pagination.per_page"
					:totalRecords="pagination.total"
					:rowsPerPageOptions="[10, 20, 30]"
					@page="onPageChange" />
			</div>
		</div>
	</Modal>
</template>
