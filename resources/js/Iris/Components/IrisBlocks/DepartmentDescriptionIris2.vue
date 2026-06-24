<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch, inject } from "vue"
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faPlayCircle } from "@fas"
import {
	faChevronCircleLeft,
	faChevronCircleRight,
	faChevronDown,
	faTimes,
	faVideoSlash,
} from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { getStyles } from "@/Composables/styles"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import Dialog from "primevue/dialog"
import Image from "@/Common/Components/Image.vue"

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
	fieldValue: {  // GetIrisWebBlockDepartmentDescription
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
	const v = props.fieldValue?.department?.showcase_video
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

			return id ? `https://player.vimeo.com/video/${id}?autoplay=1&muted=1&playsinline=1` : v
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


	if (newHeight != maxSideBarHeight.value) {
		maxSideBarHeight.value = newHeight
	}
}

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
		props.fieldValue.department.description_extra,
		props.fieldValue.department.showcase_video,
		props.fieldValue.department.showcase_image,
		props.screenType,
	],
	()=>{
		calculateDescriptionHeight()
		calculateSidebarHeight()
	},
	{ immediate: true }
)

watch(
  () => embedUrl.value,
  (val) => {
    console.log("embedUrl changed", val)
  },
  { immediate: true }
)

</script>

<template>
	<div
		:id="fieldValue?.id ? fieldValue?.id : 'department-1-iris' + indexBlock"
		component="department-1-iris">
		<div
			:style="{
				...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
				...getStyles(fieldValue?.container?.properties, screenType),
				width: 'auto',
			}"
			class="py-6 md:py-8 lg:py-10 2xl:py-14 px-4 md:px-8 2xl:px-12">
			<div
				class="grid grid-cols-1 lg:grid-cols-[260px_1fr] 2xl:grid-cols-[320px_1fr] gap-6 lg:gap-10 2xl:gap-14">
				<!-- Sidebar -->
				<aside class="hidden lg:block border-r border-gray-300 pr-4 2xl:pr-8"
				:style="{
						...getStyles(fieldValue?.sidebar?.properties, screenType),
					}">
					<h3 class="font-bold text-lg 2xl:text-xl mb-6">
						{{ ctrans("Browse By Category:") }}
					</h3>

					<div
						ref="_sidebar"
						class="overflow-y-auto pr-4 space-y-4"
						:style="{ maxHeight: `${maxSideBarHeight}px` }">
						<LinkIris
							v-for="item of props.fieldValue.sub_departments"
							:key="item.url"
							:type="'internal'"
							:href="item.url"
							class="block text-[15px] lg:text-[16px] 2xl:text-[18px] text-slate-700 hover:underline">
							{{ item.name }}
						</LinkIris>
						<LinkIris
							v-for="(collection, idxCol) of props.fieldValue.collections"
							:key="collection.url"
							:type="'internal'"
							:href="collection.url"
							class="block text-[15px] lg:text-[16px] 2xl:text-[18px] text-slate-700 hover:underline">
							{{ collection.name }}
						</LinkIris>
					</div>
				</aside>

				<!-- Main Content -->
				<div ref="_content" :style="{...getStyles(fieldValue?.description?.properties, screenType)}" class="h-fit">
					<h1
						class="text-[28px] md:text-[36px] lg:text-[46px] 2xl:text-[60px] font-bold leading-tight text-slate-900">
						{{
							props.fieldValue.department.description_title ||
							props.fieldValue.department.name
						}}
					</h1>

					<p
						class="mt-4 text-[14px] md:text-[15px] 2xl:text-[17px] leading-7 text-slate-700"
						v-html="fieldValue.department.description" />

					<!-- Banner Desktop  -->
					<div class="mt-6 overflow-hidden bg-[#E7E7E7] hidden lg:block" :style="{
							...getStyles(fieldValue?.cta?.properties,screenType),
						}">
						<div class="grid grid-cols-1 lg:grid-cols-[46%_54%] items-start">
							<!-- Content -->
							<div
								class="flex flex-col justify-center px-5 py-8 md:px-8 lg:px-10 lg:py-10 2xl:px-16 2xl:py-14">
								<div class="relative">
									<div
										ref="desktopDescriptionRef"
										class="text-[14px] md:text-[15px] 2xl:text-[17px] leading-7 2xl:leading-8 text-slate-700 max-w-[520px] 2xl:max-w-[650px] mx-auto overflow-hidden transition-all duration-300"
										:style="
											!expanded && showReadMore
												? { maxHeight: `${maxDescriptionHeight}px` }
												: {}
										"
										v-html="fieldValue.department.description_extra" />

									<!-- Fade Overlay -->
									<div
										v-if="!expanded && showReadMore"
										class="absolute bottom-0 left-0 right-0 h-12 pointer-events-none bg-gradient-to-t from-[#E7E7E7] via-[#E7E7E7]/90 to-transparent" />
								</div>

								<div v-if="showReadMore" class="flex justify-start">
									<button
										type="button"
										class="underline italic text-xs"
										@click="expanded = !expanded">
										{{ expanded ? "Read Less" : "Read More" }}
									</button>
								</div>

								<div class="flex justify-center mt-5">
									<button
										v-if="fieldValue.department.showcase_video" :style="getStyles(fieldValue?.button?.container?.properties, screenType)"
										class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-6 py-3 md:px-8 lg:px-10 2xl:px-12 2xl:py-4 rounded-md transition"
										@click="videoDialogVisible = true">
										{{ fieldValue?.button?.text ? fieldValue?.button?.text :ctrans("See a video") }}
									</button>

									<a v-else href="#sub-department">
										<button :style="getStyles(fieldValue?.button?.container?.properties, screenType)"
											class="bg-slate-900 hover:bg-slate-800 text-white font-semibold px-6 py-3 md:px-8 lg:px-10 2xl:px-12 2xl:py-4 rounded-md transition">
												{{ fieldValue?.button?.text ? fieldValue?.button?.text : ctrans("Browse All") }}
										</button>
									</a>
								</div>
							</div>

							<!-- Image / Video / Placeholder -->
							<div
								class="overflow-hidden h-[220px] md:h-[280px] lg:h-[360px] 2xl:h-[500px]"
								ref="desktopMediaRef">
								<template v-if="fieldValue.department.showcase_video && embedUrl">
									<div
										class="video-cover w-full h-[220px] md:h-[280px] lg:h-[360px] 2xl:h-[500px]">
										<iframe
											v-once
											:src="embedUrl"
											frameborder="0"
											allow="autoplay; fullscreen; picture-in-picture"
											allowfullscreen
											class="video-iframe"
											@load="calculateDescriptionHeight" />
									</div>
								</template>

								<template v-else-if="fieldValue.department.showcase_image">
									<Image
										:src="fieldValue.department.showcase_image"
										:alt="fieldValue.department.name || 'showcase image'"
										class="w-full h-[220px] md:h-[280px] lg:h-[360px] 2xl:h-[500px] object-cover"
										@load="calculateDescriptionHeight" />
								</template>

								<template v-else>
									<div
										class="w-full h-[220px] md:h-[280px] lg:h-[360px] 2xl:h-[500px] flex items-center justify-center bg-gray-100">
										<FontAwesomeIcon
											:icon="faVideoSlash"
											class="text-5xl md:text-6xl text-gray-400" />
									</div>
								</template>
							</div>
						</div>
					</div>

					<!-- Banner Mobile  -->
					<details class="lg:hidden border-y border-gray-300" :style="{
						...getStyles(fieldValue?.sidebar?.properties,screenType),
					}">
						<summary
							class="flex items-center justify-between py-5 px-4 text-xl font-bold list-none cursor-pointer">
							{{ ctrans("Browse By Category:") }}

							<FontAwesomeIcon
								:icon="faChevronDown"
								class="w-8 h-8 transition-transform details-arrow" />
						</summary>

						<div class="pb-4 px-4 space-y-3">
							<LinkIris
								v-for="item in props.fieldValue.sub_departments"
								:key="item.url"
								:href="item.url"
								type="internal"
								class="block text-[15px] lg:text-[16px] 2xl:text-[18px] text-slate-700 hover:underline">
								{{ item.name }}
							</LinkIris>
							<LinkIris
								v-for="(collection, idxCol) of props.fieldValue.collections"
								:key="collection.url"
								:type="'internal'"
								:href="collection.url"
								class="block text-[15px] lg:text-[16px] 2xl:text-[18px] text-slate-700 hover:underline">
								{{ collection.name }}
							</LinkIris>
						</div>
					</details>

					<!-- Mobile Only -->
					<div class="lg:hidden bg-[#E7E7E7] overflow-hidden" :style="{
						...getStyles(fieldValue?.cta?.properties, screenType),
					}">
						<!-- Image -->
						<div class="aspect-[4/3] overflow-hidden" ref="mobileMediaRef">
							<template v-if="fieldValue.department.showcase_image">
								<Image
									:src="fieldValue.department.showcase_image"
									:alt="fieldValue.department.name"
									class="w-full h-full object-cover" />
							</template>

							<template v-else-if="fieldValue.department.showcase_video">
								<div class="relative w-full h-full">
									<iframe
										:src="embedUrl"
										class="absolute inset-0 w-full h-full"
										frameborder="0"
										allow="autoplay; fullscreen; picture-in-picture"
  										@load="console.log('iframe loaded')"
									/>
								</div>
							</template>

							<template v-else>
								<div
									class="w-full h-full flex items-center justify-center bg-gray-200">
									<FontAwesomeIcon
										:icon="faVideoSlash"
										class="text-5xl text-gray-400" />
								</div>
							</template>
						</div>

						<!-- Content -->
						<div class="px-6 py-8 text-center">
							<h2
								v-if="fieldValue.department.name"
								class="text-2xl font-bold text-slate-900 mb-4">
								{{ fieldValue.department.name }}
							</h2>

							<div class="relative text-slate-700 text-sm leading-6">
								<div
									ref="mobileDescriptionRef"
									class="overflow-hidden transition-all duration-300"
									:style="!expanded && showReadMore ? { maxHeight: '90px' } : {}"
									v-html="fieldValue.department.description_extra" />

								<div
									v-if="!expanded && showReadMore"
									class="absolute bottom-0 left-0 right-0 h-10 pointer-events-none bg-gradient-to-t from-[#E7E7E7] via-[#E7E7E7]/90 to-transparent" />
							</div>

							<button
								v-if="showReadMore"
								type="button"
								class="mt-3 text-xs underline text-slate-700 flex jsutfy-start"
								@click="expanded = !expanded">
								{{ expanded ? "Read Less" : "Read More" }}
							</button>
							<div>
								<button
									v-if="fieldValue.department.showcase_video" :style="getStyles(fieldValue?.button?.container?.properties, screenType)"
									class="mt-6 w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 rounded-md transition"
									@click="videoDialogVisible = true">
										{{ fieldValue?.button?.text ? fieldValue?.button?.text : ctrans("See a video") }}
								</button>
								<a v-else href="#sub-department">
									<button :style="getStyles(fieldValue?.button?.container?.properties, screenType)"
										class="mt-6 bg-slate-900 hover:bg-slate-800 text-white font-semibold px-6 py-3 md:px-8 lg:px-10 2xl:px-12 2xl:py-4 rounded-md transition">
										{{ fieldValue?.button?.text ? fieldValue?.button?.text : ctrans("Browse All") }}
									</button>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<Dialog
		v-model:visible="videoDialogVisible"
		modal
		:dismissableMask="false"
		:closable="false"
		class="w-full max-w-4xl !bg-transparent !shadow-none !border-0">
		<div class="relative w-full">
			<!-- Close Button -->
			<button
				type="button"
				class="absolute top-0 right-0 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-black-500/50 backdrop-blur text-white hover:bg-white/30 transition"
				@click="videoDialogVisible = false">
				<FontAwesomeIcon :icon="faTimes" />
			</button>

			<div class="aspect-video overflow-hidden rounded-xl">
				<iframe
					v-if="embedUrl"
					v-once
					:src="`${embedUrl}${embedUrl.includes('?') ? '&' : '?'}autoplay=1`"
					frameborder="0"
					allow="
						accelerometer;
						autoplay;
						clipboard-write;
						encrypted-media;
						gyroscope;
						picture-in-picture;
					"
					allowfullscreen
					@load="console.log('iframe loaded')"
					class="w-full h-full" />
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
