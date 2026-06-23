<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch, inject } from "vue"
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faPlayCircle } from "@fas"
import {
	faChevronCircleLeft,
	faChevronCircleRight,
	faTimes,
	faVideoSlash,
	faChevronDown,
} from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { getStyles } from "@/Composables/styles"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import Dialog from "primevue/dialog"
import Image from "@/Common/Components/Image.vue"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { debounce } from "lodash-es"

library.add(
	faCube,
	faLink,
	faInfoCircle,
	faStar,
	faCircle,
	faBadgePercent,
	faChevronCircleLeft,
	faChevronCircleRight,
	faPlayCircle
)

const props = defineProps<{
	screenType: "mobile" | "tablet" | "desktop"
	indexBlock: number
	modelValue: {
		department: {
			name: string
			description_title?: string
			description?: string
			description_extra?: string
			images: {
				png: string
				avif: string
				webp: string
				original: string
			}
			active_offers: {}[]
			offers_data?: {
				[key: string]: {
					state: string
					duration: string
					label: string
					allowances: {
						class: string // 'discount'
						type: string // 'percentage_off'
						label: string // '5.0%'
					}[]
					note: string
				}
			}
		}
		sub_departments: {
			name: string
			url: string
		}[]
		collections: {
			name: string
			url: string
		}[]
	}
}>()

const layout: any = inject("layout", {})
const videoDialogVisible = ref(false)
const _sidebar = ref()
const _content = ref()

const embedUrl = computed(() => {
	const v = props.modelValue?.department?.showcase_video
	if (!v) return null

	try {
		const u = new URL(v)
		const host = u.hostname.replace("www.", "")

		if (host.includes("youtube.com")) {
			const id = u.searchParams.get("v") || u.pathname.split("/").pop() || ""

			return id
				? `https://www.youtube.com/embed/${id}?autoplay=1&mute=1&playsinline=1&rel=0`
				: v
		}

		if (host.includes("youtu.be")) {
			const id = u.pathname.slice(1)

			return `https://www.youtube.com/embed/${id}?autoplay=1&mute=1&playsinline=1&rel=0`
		}

		if (host.includes("vimeo.com")) {
			const id = u.pathname.split("/").filter(Boolean).pop()

			return id ? `https://player.vimeo.com/video/${id}?autoplay=1&muted=1` : v
		}
	} catch (e) {
		//
	}

	return v
})

const desktopMediaRef = ref<HTMLElement | null>(null)
const mobileMediaRef = ref<HTMLElement | null>(null)
const desktopDescriptionRef = ref<HTMLElement | null>(null)
const mobileDescriptionRef = ref<HTMLElement | null>(null)
const name = ref(props.modelValue?.department?.description_title || props.modelValue?.department?.name)
const expanded = ref(false)
const showReadMore = ref(false)
const maxDescriptionHeight = ref(0)
const maxSideBarHeight = ref(0)
let resizeObserver: ResizeObserver | null = null

const calculateDescriptionHeight = async () => {
	await nextTick()

	const media = props.screenType === "mobile" ? mobileMediaRef.value : desktopMediaRef.value
	const description =
		props.screenType === "mobile" ? mobileDescriptionRef.value : desktopDescriptionRef.value

	if (!media || !description) return



	maxDescriptionHeight.value = media.offsetHeight - 190
	showReadMore.value = description.scrollHeight > media.offsetHeight
}

const calculateSidebarHeight = async () => {
	await nextTick()

	if (!_content.value) {
		return
	}

	const newHeight = _content.value.offsetHeight - 80

	if (newHeight !== maxSideBarHeight.value) {
		maxSideBarHeight.value = newHeight
	}
}

const saveDescription = debounce(async (key: string, value: string) => {
	try {
		const url = route('grp.models.product_category.update', {
			productCategory: props.modelValue.department.id,
		})
		await axios.patch(url, { [key]: value })
	} catch (error: any) {
		console.error('Save failed:', error)
		notify({
			title: 'Failed to Save',
			text: error?.response?.data?.message || 'Please check your input and try again.',
			type: 'error',
		})
	}
}, 1000)

onMounted(async () => {
	await nextTick()

	calculateDescriptionHeight()
	calculateSidebarHeight()

	if (window.ResizeObserver) {
		resizeObserver = new ResizeObserver(() => {
			calculateDescriptionHeight()
			calculateSidebarHeight()
		})

		if (_content.value) {
			resizeObserver.observe(_content.value)
		}

		if (desktopMediaRef.value) {
			resizeObserver.observe(desktopMediaRef.value)
		}

		if (mobileMediaRef.value) {
			resizeObserver.observe(mobileMediaRef.value)
		}
	}
})

onUnmounted(() => {
	if (resizeObserver) {
		resizeObserver.disconnect()
	}

	window.removeEventListener("resize", calculateDescriptionHeight)
})

watch(
	() => [
		props.modelValue.department.description_extra,
		props.modelValue.department.showcase_video,
		props.modelValue.department.showcase_image,
		props.screenType,
	],
	calculateDescriptionHeight,
	{ immediate: true }
)

watch(name, (val) => {
	name.value = val
	saveDescription('description_title', val)
})

watch(
	() => props.modelValue.department,
	(val) => {
		name.value = val.description_title || val.name
	},
	{ immediate: true }
)

const responsiveClasses = computed(() => ({
	containerPadding: props.screenType === 'desktop' ? 'px-12 py-14' : props.screenType === 'tablet' ? 'px-8 py-10' : 'px-4 py-6',
	gridLayout: props.screenType === 'desktop' ? 'grid-cols-[320px_1fr] gap-14' : props.screenType === 'tablet' ? 'grid-cols-[260px_1fr] gap-10' : 'grid-cols-1 gap-6',
	sidebarVisible: props.screenType !== 'mobile',
	bannerDesktopVisible: props.screenType !== 'mobile',
	bannerMobileVisible: props.screenType === 'mobile',
	sidebarPadding: props.screenType === 'desktop' ? 'pr-8' : 'pr-4',
	headingSize: props.screenType === 'desktop' ? 'text-[60px]' : props.screenType === 'tablet' ? 'text-[46px]' : 'text-[36px]',
	descriptionSize: props.screenType === 'desktop' ? 'text-[17px]' : 'text-[15px]',
	categoryItemSize: props.screenType === 'desktop' ? 'text-[18px]' : props.screenType === 'tablet' ? 'text-[16px]' : 'text-[15px]',
	categoryHeadingSize: props.screenType === 'desktop' ? 'text-xl' : 'text-lg',
	mediaHeight: props.screenType === 'desktop' ? 'h-[500px]' : props.screenType === 'tablet' ? 'h-[360px]' : 'h-[280px]',
	contentPadding: props.screenType === 'desktop' ? 'px-16 py-14' : props.screenType === 'tablet' ? 'px-10 py-10' : 'px-5 py-8',
	buttonPadding: props.screenType === 'desktop' ? 'px-12 py-4' : props.screenType === 'tablet' ? 'px-10 py-3' : 'px-8 py-3',
	maxWidth: props.screenType === 'desktop' ? 'max-w-[650px]' : 'max-w-[520px]',
	iconSize: props.screenType === 'desktop' ? 'text-6xl' : 'text-5xl',
}))

</script>

<template>
	<div :id="modelValue?.id ? modelValue?.id : 'department-1-iris' + indexBlock" component="department-1-iris">
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue?.container?.properties, screenType),
			width: 'auto',
		}" :class="['py-6 px-4', responsiveClasses.containerPadding]">
			<div :class="['grid grid-cols-1', responsiveClasses.gridLayout]">
				<!-- Sidebar -->
				<aside v-if="responsiveClasses.sidebarVisible"
					:class="['border-r border-gray-300', responsiveClasses.sidebarPadding]" :style="{
						...getStyles(modelValue?.sidebar?.properties, screenType),
					}">
					<h3 :class="['font-bold mb-6', responsiveClasses.categoryHeadingSize]">
						{{ ctrans("Browse By Category:") }}
					</h3>

					<div ref="_sidebar" class="overflow-y-auto pr-4 space-y-4"
						:style="{ maxHeight: `${maxSideBarHeight}px` }">
						<LinkIris v-for="item of props.modelValue.sub_departments"
							:key="item.url"
							:type="'internal'"
							:href="item.url"
							:class="['block text-slate-700 hover:underline', responsiveClasses.categoryItemSize]">
							{{ item.name }}
						</LinkIris>
						<LinkIris
							v-for="(collection, idxCol) of props.modelValue.collections"
							:key="collection.url"
							:type="'internal'"
							:href="collection.url"
							:class="['block text-slate-700 hover:underline', responsiveClasses.categoryItemSize]">
							{{ collection.name }}
						</LinkIris>
					</div>
				</aside>

				<!-- Main Content -->
				<div ref="_content" class="h-fit" :style="{
					...getStyles(modelValue?.description?.properties, screenType),
				}">
					<h1>
						<input v-model="name" type="text" placeholder="department Title"
							class="w-full appearance-none bg-transparent border-none p-0 m-0 ] text-[28px] md:text-[36px] lg:text-[46px] 2xl:text-[60px] font-bold leading-tight text-slate-900 focus:outline-none focus:ring-0 shadow-none" />
					</h1>

					<p :class="['mt-4 leading-7 text-slate-700', responsiveClasses.descriptionSize]">
						<EditorV2 v-model="modelValue.department.description" placeholder="department Description"
							:key="modelValue.department.id"
							@update:model-value="(e) => saveDescription('description', e)" :uploadImageRoute="{
								name: webpageData?.images_upload_route?.name,
								parameters: { modelHasWebBlocks: blockData?.id }
							}" />
					</p>

					<!-- Banner Desktop  -->
					<div v-if="responsiveClasses.bannerDesktopVisible" class="mt-6 overflow-hidden bg-[#E7E7E7]" :style="{
						...getStyles(modelValue?.cta?.properties, screenType),
					}">
						<div class="grid grid-cols-1 lg:grid-cols-[46%_54%] items-start">
							<!-- Content -->
							<div :class="['flex flex-col justify-center', responsiveClasses.contentPadding]">
								<div class="relative">
									<div ref="desktopDescriptionRef"
										:class="['leading-7 text-slate-700 mx-auto overflow-hidden transition-all duration-300', responsiveClasses.descriptionSize, responsiveClasses.maxWidth]"
										:style="!expanded && showReadMore
												? { maxHeight: `${maxDescriptionHeight}px` }
												: {}
											">
										<EditorV2 v-model="modelValue.department.description_extra"
											placeholder="Extra Description" :key="modelValue.department.id"
											@update:model-value="(e) => saveDescription('description_extra', e)"
											:uploadImageRoute="{
												name: webpageData?.images_upload_route?.name,
												parameters: { modelHasWebBlocks: blockData?.id }
											}" />

									</div>

									<!-- Fade Overlay -->
									<div v-if="!expanded && showReadMore"
										class="absolute bottom-0 left-0 right-0 h-12 pointer-events-none bg-gradient-to-t from-[#E7E7E7] via-[#E7E7E7]/90 to-transparent" />
								</div>

								<div v-if="showReadMore" class="flex justify-start">
									<button type="button" class="underline italic text-xs"
										@click="expanded = !expanded">
										{{ expanded ? "Read Less" : "Read More" }}
									</button>
								</div>

								<div class="flex justify-center mt-5">
									<button v-if="modelValue.department.showcase_video" :style="getStyles(modelValue?.button?.container?.properties, screenType)"
										:class="['bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-md transition', responsiveClasses.buttonPadding]"
										@click="videoDialogVisible = true">
										{{ modelValue?.button?.text ? modelValue?.button?.text : ctrans("See a video") }}
									</button>

									<a v-else href="#sub-department">
										<button :style="getStyles(modelValue?.button?.container?.properties, screenType)"
											:class="['bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-md transition', responsiveClasses.buttonPadding]">
												{{ modelValue?.button?.text ? modelValue?.button?.text : ctrans("Browse All") }}
										</button>
									</a>
								</div>
							</div>

							<!-- Image / Video / Placeholder -->
							<div :class="['overflow-hidden', responsiveClasses.mediaHeight]" ref="desktopMediaRef">
								<template v-if="modelValue.department.showcase_video && embedUrl">
									<div :class="['video-cover w-full', responsiveClasses.mediaHeight]">
										<iframe :src="embedUrl" frameborder="0"
											allow="autoplay; fullscreen; picture-in-picture" allowfullscreen
											class="video-iframe" @load="calculateDescriptionHeight" />
									</div>
								</template>

								<template v-else-if="modelValue.department.showcase_image">
									<Image :src="modelValue.department.showcase_image"
										:alt="modelValue.department.name || 'showcase image'"
										:class="['w-full object-cover', responsiveClasses.mediaHeight]"
										@load="calculateDescriptionHeight" />
								</template>

								<template v-else>
									<div
										:class="['w-full flex items-center justify-center bg-gray-100', responsiveClasses.mediaHeight]">
										<FontAwesomeIcon :icon="faVideoSlash"
											:class="['text-gray-400', responsiveClasses.iconSize]" />
									</div>
								</template>
							</div>
						</div>
					</div>

					<!-- Banner Mobile  -->
					<details v-if="responsiveClasses.bannerMobileVisible" class="border-y border-gray-300" :style="{
						...getStyles(modelValue?.sidebar?.properties, screenType),
					}">
						<summary
							class="flex items-center justify-between py-5 px-4 text-xl font-bold list-none cursor-pointer">
							{{ ctrans("Browse By Category:") }}

							<FontAwesomeIcon :icon="faChevronDown" class="w-8 h-8 transition-transform details-arrow" />
						</summary>

						<div class="pb-4 px-4 space-y-3">
							<LinkIris v-for="item in props.modelValue.sub_departments" :key="item.url" :href="item.url"
								type="internal" class="block text-slate-700">
								{{ item.name }}
							</LinkIris>
						</div>
					</details>

					<!-- Mobile Only -->
					<div v-if="responsiveClasses.bannerMobileVisible" class="bg-[#E7E7E7] overflow-hidden" :style="{
						...getStyles(modelValue?.cta?.properties, screenType),
					}">
						<!-- Image -->
						<div class="aspect-[4/3] overflow-hidden" ref="mobileMediaRef">
							<template v-if="modelValue.department.showcase_image">
								<Image :src="modelValue.department.showcase_image" :alt="modelValue.department.name"
									class="w-full h-full object-cover" />
							</template>

							<template v-else-if="modelValue.department.showcase_video">
								<div class="relative w-full h-full">
									<iframe :src="embedUrl" class="absolute inset-0 w-full h-full" frameborder="0"
										allow="autoplay; fullscreen; picture-in-picture" allowfullscreen />
								</div>
							</template>

							<template v-else>
								<div class="w-full h-full flex items-center justify-center bg-gray-200">
									<FontAwesomeIcon :icon="faVideoSlash" class="text-5xl text-gray-400" />
								</div>
							</template>
						</div>

						<!-- Content -->
						<div class="px-6 py-8 text-center">
							<h2 v-if="modelValue.department.name" class="text-2xl font-bold text-slate-900 mb-4">
								{{ modelValue.department.name }}
							</h2>

							<div class="relative text-slate-700 text-sm leading-6">
								<div ref="mobileDescriptionRef" class="overflow-hidden transition-all duration-300"
									:style="!expanded && showReadMore ? { maxHeight: '90px' } : {}"
									v-html="modelValue.department.description_extra" />

								<div v-if="!expanded && showReadMore"
									class="absolute bottom-0 left-0 right-0 h-10 pointer-events-none bg-gradient-to-t from-[#E7E7E7] via-[#E7E7E7]/90 to-transparent" />
							</div>

							<button v-if="showReadMore" type="button"
								class="mt-3 text-xs underline text-slate-700 flex jsutfy-start"
								@click="expanded = !expanded">
								{{ expanded ? "Read Less" : "Read More" }}
							</button>
							<div>
								<button v-if="modelValue.department.showcase_video" :style="getStyles(modelValue?.button?.container?.properties, screenType)"
									class="mt-6 w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 rounded-md transition"
									@click="videoDialogVisible = true">
									{{ modelValue?.button?.text ? modelValue?.button?.text : ctrans("See a video") }}
								</button>
								<a v-else href="#sub-department">
									<button :style="getStyles(modelValue?.button?.container?.properties, screenType)"
										class="mt-6 bg-slate-900 hover:bg-slate-800 text-white font-semibold px-6 py-3 rounded-md transition">
										{{ modelValue?.button?.text ? modelValue?.button?.text : ctrans("Browse All") }}
									</button>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<Dialog v-model:visible="videoDialogVisible" modal :dismissableMask="false" :closable="false"
		class="w-full max-w-4xl !bg-transparent !shadow-none !border-0">
		<div class="relative w-full">
			<!-- Close Button -->
			<button type="button"
				class="absolute top-0 right-0 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-red-500 backdrop-blur text-white hover:bg-white/30 transition"
				@click="videoDialogVisible = false">
				<FontAwesomeIcon :icon="faTimes" />
			</button>

			<div class="aspect-video overflow-hidden rounded-xl">
				<iframe v-if="embedUrl" :src="`${embedUrl}${embedUrl.includes('?') ? '&' : '?'}autoplay=1`"
					frameborder="0" allow="
						accelerometer;
						autoplay;
						clipboard-write;
						encrypted-media;
						gyroscope;
						picture-in-picture;
					" allowfullscreen class="w-full h-full" />
			</div>
		</div>
	</Dialog>
</template>
<style scoped>
.category-scroll::-webkit-scrollbar {
	width: 6px;
}

.category-scroll::-webkit-scrollbar-thumb {
	background: #cbd5e1;
	border-radius: 9999px;
}

.category-scroll::-webkit-scrollbar-track {
	background: transparent;
}

/* Make embedded iframe cover the container area */
.video-cover {
	position: relative;
	overflow: hidden;
}

.video-cover .video-iframe {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	min-width: 100%;
	min-height: 100%;
	width: auto;
	height: auto;
	border: 0;
}
</style>
