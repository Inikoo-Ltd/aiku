<script setup lang="ts">
import { ref, onMounted, inject, watch, computed } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue"
import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { getStyles } from "@/Composables/styles"

import { faPresentation, faLink, faExternalLink } from "@fal"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faPresentation, faLink, faExternalLink, faSpinnerThird)

const props = defineProps<{
	modelValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
	(e: "autoSave"): void
}>()

const layout = inject("layout")
const data = ref<any>(null)
const isLoading = ref(false)

const activeSlug = computed(() => {
	const responsive = props.modelValue?.banner_responsive

	if (!responsive) {
		return props.modelValue?.banner_slug ?? null
	}

	const current = responsive[props.screenType]?.slug

	if (!current && props.screenType !== "desktop") {
		return responsive.desktop?.slug ?? null
	}

	return current ?? null
})

const getRouteShow = () => {
	const currentRoute = route().current()

	if (currentRoute.includes("fulfilments") || route().params["fulfilment"]) {
		return route("grp.org.fulfilments.show.web.banners.show", {
			organisation: route().params["organisation"],
			fulfilment: route().params["fulfilment"],
			website: route().params["website"],
			banner: activeSlug.value,
		})
	}

	return route("grp.org.shops.show.web.banners.show", {
		organisation: route().params["organisation"],
		shop: route().params["shop"],
		website: route().params["website"],
		banner: activeSlug.value,
	})
}

const getDataBanner = async (): Promise<void> => {
	// If slug is null or empty, do not fetch
	if (!activeSlug.value) {
		data.value = null
		return
	}

	try {
		isLoading.value = true
		const url = getRouteShow()
		const response = await axios.get(url)
		data.value = response.data
	} catch (error: any) {
		console.error(error)
		notify({
			title: "Failed to fetch banners data",
			text: error?.message || "An error occurred",
			type: "error",
		})
		data.value = null
	} finally {
		isLoading.value = false
	}
}

/**
 * Single watcher for slug changes
 */
watch(
	activeSlug,
	(newSlug, oldSlug) => {
		if (newSlug !== oldSlug) {
			getDataBanner()
		}
	},
	{ immediate: true }
)

onMounted(() => {
	if (activeSlug.value) {
		getDataBanner()
	}
})
</script>

<template>
	<div id="banner">
		<div v-if="isLoading" class="flex justify-center h-36 items-center">
			<LoadingIcon class="text-4xl" />
		</div>

		<section
			v-else-if="data && activeSlug"
			class="relative"
		>
			<div
				v-if="data.state !== 'switch_off'"
				class="pointer-events-none select-none"
				:style="{
					...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
					...getStyles(modelValue.container?.properties, screenType)
				}"
			>
				<SliderLandscape
					v-if="data.type === 'landscape'"
					:data="data.compiled_layout"
					:production="true"
					:view="screenType"
				/>
				<SliderSquare
					v-else
					:data="data.compiled_layout"
					:production="true"
					:view="screenType"
				/>
			</div>

			
		</section>

		<div v-else>
				<EmptyState
					:data="{
						title: trans('You do not have slides to show'),
						description: trans('Create new slides in the workshop to get started')
					}"
				/>
			</div>
	</div>
</template>