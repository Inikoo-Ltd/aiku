<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch, inject } from "vue"
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent, faPlayCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight, faChevronDown, faVideoSlash } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import LinkIris from "@/Iris/Components/LinkIris.vue"
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

type ScreenType = "mobile" | "tablet" | "desktop"

const props = defineProps<{
	screenType: ScreenType
	indexBlock: number
	code?: string
	webpageData?: {
		images_upload_route?: {
			name: string
		}
	}
	blockData?: {
		id?: number
	}
	modelValue: {
		id?: string
		department: {
			id: number
			name: string
			description_title?: string
			description?: string
			description_extra?: string
			showcase_video?: string
			showcase_video_thumbnail?: string
			showcase_image?: {
				png: string
				avif: string
				webp: string
				original: string
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
const mobileVideoActivated = ref(false)
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

			return id
				? `https://player.vimeo.com/video/${id}?badge=0&autopause=0&player_id=0&app_id=58479&autoplay=1&loop=1&muted=1&playsinline=1&controls=1`
				: v
		}
	} catch (e) {
		//
	}

	return v
})

const descriptionRef = ref<HTMLElement | null>(null)
const name = ref(
	props.modelValue?.department?.description_title || props.modelValue?.department?.name
)
const expanded = ref(false)
const showReadMore = ref(false)
let resizeObserver: ResizeObserver | null = null

const responsiveClasses = computed(() => ({
	containerPadding:
		props.screenType === "desktop"
			? "px-12 py-14"
			: props.screenType === "tablet"
				? "px-8 py-8"
				: "px-4 py-6",
	gridLayout:
		props.screenType === "desktop" ? "grid-cols-[320px_1fr] gap-14" : "grid-cols-1 gap-6",
	sidebarVisible: props.screenType === "desktop",
	headingSize:
		props.screenType === "desktop"
			? "text-[46px]"
			: props.screenType === "tablet"
				? "text-[36px]"
				: "text-[28px]",
	descriptionSize:
		props.screenType === "desktop"
			? "text-[17px] leading-8"
			: props.screenType === "tablet"
				? "text-[15px] leading-7"
				: "text-[14px] leading-7",
	descriptionMaxHeight:
		props.screenType === "desktop" ? "128px" : props.screenType === "tablet" ? "112px" : "84px",
	categoryItemSize:
		props.screenType === "desktop"
			? "text-[18px]"
			: props.screenType === "tablet"
				? "text-[16px]"
				: "text-[15px]",
	categoryHeadingSize: props.screenType === "desktop" ? "text-lg" : "text-base",
	mediaHeight: props.screenType === "desktop" ? "h-[500px]" : "aspect-[4/3]",
	iconSize: props.screenType === "mobile" ? "text-5xl" : "text-6xl",
}))

const calculateDescriptionHeight = async () => {
	await nextTick()

	const description = descriptionRef.value

	if (!description || expanded.value) return

	showReadMore.value = description.scrollHeight > description.clientHeight + 4
}

const saveDescription = debounce(async (key: string, value: string) => {
	try {
		const url = route("grp.models.product_category.update", {
			productCategory: props.modelValue.department.id,
		})
		await axios.patch(url, { [key]: value })
	} catch (error: any) {
		console.error("Save failed:", error)
		notify({
			title: "Failed to Save",
			text: error?.response?.data?.message || "Please check your input and try again.",
			type: "error",
		})
	}
}, 1000)

onMounted(async () => {
	await nextTick()

	calculateDescriptionHeight()

	if (window.ResizeObserver) {
		resizeObserver = new ResizeObserver(() => {
			calculateDescriptionHeight()
		})

		if (descriptionRef.value) {
			resizeObserver.observe(descriptionRef.value)
		}

		if (_content.value) {
			resizeObserver.observe(_content.value)
		}
	}
})

onUnmounted(() => {
	if (resizeObserver) {
		resizeObserver.disconnect()
	}
})

watch(
	() => [
		props.modelValue?.department?.description,
		props.modelValue?.department?.description_extra,
		props.screenType,
	],
	() => {
		calculateDescriptionHeight()
	},
	{ immediate: true }
)

watch(name, (val) => {
	saveDescription("description_title", val)
})

watch(
	() => props.modelValue?.department,
	(val) => {
		name.value = val?.description_title || val?.name
	},
	{ immediate: true }
)
</script>

<template>
	<div
		:id="modelValue?.id ? modelValue?.id : 'department-1-iris' + indexBlock"
		component="department-1-iris"
		class="pt-2">
		<div
			:style="{
				...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
				...getStyles(modelValue?.container?.properties, screenType),
				width: 'auto',
			}"
			:class="responsiveClasses.containerPadding">
			<div
				ref="_content"
				:style="{ ...getStyles(modelValue?.description?.properties, screenType) }">
				<h1>
					<input
						v-model="name"
						type="text"
						:placeholder="ctrans('Department Title')"
						:class="[
							'w-full appearance-none bg-transparent border-none p-0 m-0 font-bold leading-tight text-slate-900 focus:outline-none focus:ring-0 shadow-none',
							responsiveClasses.headingSize,
						]" />
				</h1>

				<div class="relative mt-4">
					<div
						ref="descriptionRef"
						:class="[
							'text-slate-700 overflow-hidden transition-all duration-300',
							responsiveClasses.descriptionSize,
						]"
						:style="
							!expanded && showReadMore
								? { maxHeight: responsiveClasses.descriptionMaxHeight }
								: {}
						">
						<EditorV2
							v-model="modelValue.department.description"
							:key="`description-${modelValue.department.id}`"
							:placeholder="ctrans('Department Description')"
							:uploadImageRoute="{
								name: webpageData?.images_upload_route?.name,
								parameters: { modelHasWebBlocks: blockData?.id },
							}"
							@update:model-value="(e) => saveDescription('description', e)" />

						<EditorV2
							v-model="modelValue.department.description_extra"
							:key="`description-extra-${modelValue.department.id}`"
							:placeholder="ctrans('Extra Description')"
							:uploadImageRoute="{
								name: webpageData?.images_upload_route?.name,
								parameters: { modelHasWebBlocks: blockData?.id },
							}"
							@update:model-value="(e) => saveDescription('description_extra', e)" />
					</div>

					<div
						v-if="!expanded && showReadMore"
						class="absolute bottom-0 left-0 right-0 h-10 pointer-events-none bg-gradient-to-t from-white via-white/90 to-transparent" />
				</div>

				<button
					v-if="showReadMore"
					type="button"
					class="mt-2 underline italic text-xs text-slate-700"
					@click="expanded = !expanded">
					{{ expanded ? ctrans("Read Less") : ctrans("Read More") }}
				</button>
			</div>

			<div
				:class="['mt-8 grid', responsiveClasses.gridLayout]"
				:style="{ ...getStyles(modelValue?.cta?.properties, screenType) }">
				<!-- Sidebar Desktop -->
				<aside
					v-if="responsiveClasses.sidebarVisible"
					class="flex flex-col border-r border-gray-300 pr-8"
					:style="{ ...getStyles(modelValue?.sidebar?.properties, screenType) }">
					<h3 :class="['font-bold mb-5', responsiveClasses.categoryHeadingSize]">
						{{ ctrans("Browse By Category:") }}
					</h3>

					<div class="relative flex-1 min-h-0">
						<div
							ref="_sidebar"
							class="absolute inset-0 overflow-y-auto category-scroll pr-4 space-y-4">
							<LinkIris
								v-for="item of modelValue.sub_departments"
								:key="item.url"
								:type="'internal'"
								:href="item.url"
								:class="[
									'block text-slate-700 underline hover:no-underline',
									responsiveClasses.categoryItemSize,
								]">
								{{ item.name }}
							</LinkIris>
							<LinkIris
								v-for="collection of modelValue.collections"
								:key="collection.url"
								:type="'internal'"
								:href="collection.url"
								:class="[
									'block text-slate-700 underline hover:no-underline',
									responsiveClasses.categoryItemSize,
								]">
								{{ collection.name }}
							</LinkIris>
						</div>
					</div>
				</aside>

				<!-- Sidebar Mobile & Tablet -->
				<details
					v-else
					class="border-y border-gray-300"
					:style="{ ...getStyles(modelValue?.sidebar?.properties, screenType) }">
					<summary
						class="flex items-center justify-between py-5 px-4 text-xl font-bold list-none cursor-pointer">
						{{ ctrans("Browse By Category:") }}

						<FontAwesomeIcon
							:icon="faChevronDown"
							class="w-8 h-8 transition-transform details-arrow" />
					</summary>

					<div class="pb-4 px-4 space-y-3">
						<LinkIris
							v-for="item of modelValue.sub_departments"
							:key="item.url"
							:href="item.url"
							type="internal"
							:class="[
								'block text-slate-700 underline hover:no-underline',
								responsiveClasses.categoryItemSize,
							]">
							{{ item.name }}
						</LinkIris>
						<LinkIris
							v-for="collection of modelValue.collections"
							:key="collection.url"
							:type="'internal'"
							:href="collection.url"
							:class="[
								'block text-slate-700 underline hover:no-underline',
								responsiveClasses.categoryItemSize,
							]">
							{{ collection.name }}
						</LinkIris>
					</div>
				</details>

				<!-- Media -->
				<div :class="['overflow-hidden', responsiveClasses.mediaHeight]">
					<template v-if="modelValue.department.showcase_video && embedUrl">
						<div class="relative w-full h-full">
							<iframe
								v-if="screenType === 'desktop' || mobileVideoActivated"
								:src="embedUrl"
								frameborder="0"
								allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share"
								referrerpolicy="strict-origin-when-cross-origin"
								allowfullscreen
								class="absolute inset-0 w-full h-full"
								:title="modelValue.department.name || ctrans('Department video')" />
							<button
								v-else
								type="button"
								class="absolute inset-0 w-full h-full"
								:aria-label="ctrans('Play video')"
								@click="mobileVideoActivated = true">
								<Image
									v-if="modelValue.department.showcase_video_thumbnail"
									:src="{ original: modelValue.department.showcase_video_thumbnail }"
									:responsiveEnabled="false"
									:alt="modelValue.department.name || ctrans('Department video')"
									:imageCover="true"
									class="absolute inset-0 w-full h-full" />
								<Image
									v-else-if="modelValue.department.showcase_image"
									:src="modelValue.department.showcase_image"
									:alt="modelValue.department.name || ctrans('Department video')"
									:imageCover="true"
									class="absolute inset-0 w-full h-full" />
								<div v-else class="absolute inset-0 bg-gray-200" />
								<span class="absolute inset-0 flex items-center justify-center">
									<FontAwesomeIcon
										:icon="faPlayCircle"
										class="text-6xl text-white drop-shadow-lg" />
								</span>
							</button>
						</div>
					</template>

					<template v-else-if="modelValue.department.showcase_image">
						<Image
							:src="modelValue.department.showcase_image"
							:alt="modelValue.department.name || 'showcase image'"
							class="w-full h-full object-cover" />
					</template>

					<template v-else>
						<div class="w-full h-full flex items-center justify-center bg-gray-100">
							<FontAwesomeIcon
								:icon="faVideoSlash"
								:class="['text-gray-400', responsiveClasses.iconSize]" />
						</div>
					</template>
				</div>
			</div>
		</div>
	</div>
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
</style>
