<script setup lang="ts">
import { computed, inject, nextTick, onMounted, onUnmounted, ref, watch } from "vue"

import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@far"
import { getBestOffer } from "@/Composables/useOffers"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"
import type { Image as TypeImage } from "@/types/Image"

interface ImageFormats {
	original?: string
	avif?: string
	webp?: string
}

interface FamilyImage {
	original: ImageFormats
	srcset?: ImageFormats
	alt?: string
}

interface FamilyTag {
	name?: string
	web_image?: TypeImage | null
}

interface FamilyData {
	name?: string
	description?: string
	description_image?: Record<string, FamilyImage>
	offers_data: object
	tags?: FamilyTag[]
}

interface FieldValue {
	id?: string | number
	family?: FamilyData
	container?: {
		properties?: Record<string, unknown>
	}
}

type ScreenType = "mobile" | "tablet" | "desktop"

const props = defineProps<{
	fieldValue: FieldValue
	screenType: ScreenType
	indexBlock: number
}>()

const layout = inject<Record<string, any>>("layout", {})

const cleanedDescription = computed(() => {
	const html = props.fieldValue?.family?.description || ""

	return html
		.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
		.replace(/<img\b(?![^>]*\bloading=)/gi, '<img loading="lazy" decoding="async"')
})

const images = computed<FamilyImage[]>(() => {
	const data = props.fieldValue?.family?.description_image

	if (!data) return []

	return Object.values(data).filter((item) => item && item.original)
})

const hasImage = (index: number) => {
	return Boolean(images.value?.[index]?.original)
}

const bestOffer = computed(() => {
	return getBestOffer(props.fieldValue?.family?.offers_data)
})

const MAX_VISIBLE_TAGS = 3

const visibleTags = computed(() =>
	(props.fieldValue?.family?.tags ?? []).slice(0, MAX_VISIBLE_TAGS)
)

const isLcpBlock = computed(() => Number(props.indexBlock) === 0)

const secondaryImageAttributes = computed(() =>
	isLcpBlock.value
		? { loading: "eager" as const, decoding: "async" as const }
		: { loading: "lazy" as const, decoding: "async" as const }
)

const descriptionBoxRef = ref<HTMLElement | null>(null)
const descriptionBodyRef = ref<HTMLElement | null>(null)
const imagesRef = ref<HTMLElement | null>(null)
const offersRef = ref<HTMLElement | null>(null)
const offerBadgesRef = ref<HTMLElement | null>(null)
const titleRef = ref<HTMLElement | null>(null)
const titleTextRef = ref<HTMLElement | null>(null)
const truncatedTitleRef = ref<HTMLElement | null>(null)
const actionsRef = ref<HTMLElement | null>(null)
const isTitleTruncated = ref(false)
const isTitleSingleLine = ref(true)
const expanded = ref(false)
const showReadMore = ref(false)
const collapsedHeight = ref(0)
const titleWidth = ref(0)
let resizeObserver: ResizeObserver | null = null

const TITLE_OFFERS_GAP = 16
const MIN_TITLE_WIDTH = 150
const TITLE_ROW_MIN_SCREEN_WIDTH = 1024

const titleWidthStyle = computed(() =>
	titleWidth.value ? { width: `${titleWidth.value}px` } : {}
)

const COLLAPSED_HEIGHTS = [
	{ minWidth: 1536, height: 250 },
	{ minWidth: 1024, height: 195 },
	{ minWidth: 0, height: 260 },
]

const MIN_COLLAPSED_HEIGHT = 300

const getFallbackCollapsedHeight = (): number => {
	const width = window.innerWidth

	return COLLAPSED_HEIGHTS.find((entry) => width >= entry.minWidth)!.height
}

const isSideBySide = (): boolean =>
	Boolean(imagesRef.value) && !layout.rightbasket?.show && window.innerWidth >= 1024

const getCollapsedHeight = (): number => {
	if (!isSideBySide()) {
		return getFallbackCollapsedHeight()
	}

	const siblingsHeight = [offersRef, titleRef, truncatedTitleRef, actionsRef].reduce(
		(total, element) => total + (element.value?.offsetHeight ?? 0),
		0
	)

	return Math.max(MIN_COLLAPSED_HEIGHT, imagesRef.value!.offsetHeight - siblingsHeight)
}

const TRUNCATION_TOLERANCE = 1
const SINGLE_LINE_TOLERANCE = 1.5
const NORMAL_LINE_HEIGHT_RATIO = 1.2
const SINGLE_LINE_FONT_SIZE = "1.5rem"
const MULTI_LINE_FONT_SIZE = "1.4rem"

const titleFontSize = computed(() =>
	isTitleSingleLine.value ? SINGLE_LINE_FONT_SIZE : MULTI_LINE_FONT_SIZE
)

const calculateTitleWidth = () => {
	const offersRow = offersRef.value

	if (!offersRow || window.innerWidth < TITLE_ROW_MIN_SCREEN_WIDTH) {
		titleWidth.value = 0

		return
	}

	if (titleRef.value) {
		titleWidth.value = Math.round(titleRef.value.clientWidth)

		return
	}

	const badgesWidth = offerBadgesRef.value?.offsetWidth ?? 0
	const availableWidth =
		offersRow.clientWidth - badgesWidth - (badgesWidth ? TITLE_OFFERS_GAP : 0)

	titleWidth.value = Math.round(Math.max(MIN_TITLE_WIDTH, availableWidth))
}

const getLineHeight = (element: HTMLElement): number => {
	const { lineHeight, fontSize } = window.getComputedStyle(element)
	const parsedLineHeight = Number.parseFloat(lineHeight)

	return Number.isNaN(parsedLineHeight)
		? Number.parseFloat(fontSize) * NORMAL_LINE_HEIGHT_RATIO
		: parsedLineHeight
}

const checkTitleTruncated = () => {
	const title = titleTextRef.value

	if (!title) {
		isTitleTruncated.value = false
		isTitleSingleLine.value = true

		return
	}

	title.style.fontSize = SINGLE_LINE_FONT_SIZE
	isTitleSingleLine.value =
		title.scrollHeight <= getLineHeight(title) * SINGLE_LINE_TOLERANCE

	title.style.fontSize = titleFontSize.value
	isTitleTruncated.value = title.scrollHeight - title.clientHeight > TRUNCATION_TOLERANCE
}

const calculateDescriptionHeight = async () => {
	await nextTick()

	calculateTitleWidth()

	await nextTick()

	checkTitleTruncated()

	if (!descriptionBoxRef.value) return

	collapsedHeight.value = getCollapsedHeight()

	showReadMore.value =
		descriptionBoxRef.value.scrollHeight - collapsedHeight.value > TRUNCATION_TOLERANCE
}

const onWindowResize = () => {
	calculateDescriptionHeight()
}

onMounted(() => {
	calculateDescriptionHeight()

	resizeObserver = new ResizeObserver(() => {
		calculateDescriptionHeight()
	})

	if (descriptionBodyRef.value) {
		resizeObserver.observe(descriptionBodyRef.value)
	}

	if (imagesRef.value) {
		resizeObserver.observe(imagesRef.value)
	}

	if (titleTextRef.value) {
		resizeObserver.observe(titleTextRef.value)
	}

	if (offersRef.value) {
		resizeObserver.observe(offersRef.value)
	}

	if (offerBadgesRef.value) {
		resizeObserver.observe(offerBadgesRef.value)
	}

	window.addEventListener("resize", onWindowResize)

	document.fonts?.ready.then(() => {
		calculateDescriptionHeight()
	})
})

onUnmounted(() => {
	if (resizeObserver) {
		resizeObserver.disconnect()
		resizeObserver = null
	}

	window.removeEventListener("resize", onWindowResize)
})

watch(
	() => [
		props.fieldValue?.family?.name,
		props.fieldValue?.family?.description,
		props.fieldValue?.family?.description_image,
		layout?.user?.gr_data?.customer_is_gr,
		layout?.user?.gr_data?.amnesty,
		bestOffer.value?.type,
	],
	() => {
		calculateDescriptionHeight()
	}
)

const contentClass = computed(() =>
	layout.rightbasket?.show
		? "flex flex-col gap-6"
		: "flex flex-col gap-6 lg:flex-row lg:items-stretch"
)
</script>

<template>
	<section :id="`family-2`" component="family-2-iris">
		<div
			class="mx-auto w-full max-w-[1700px] bg-white px-4 py-4 sm:px-8 lg:px-14 2xl:max-w-[1800px] 2xl:px-14"
			:style="{
				...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
				...getStyles(fieldValue?.container?.properties, screenType),
				width: 'auto',
			}">
			<div :class="contentClass">
				<!-- ============================================================= -->
				<!-- NEW IMAGE SECTION                                             -->
				<!-- 1 image  -> only large image                                 -->
				<!-- 2 images -> large + small top-right                          -->
				<!-- 3 images -> large + small top-right + small bottom-right     -->
				<!-- ============================================================= -->
				<div
					v-if="hasImage(0)"
					ref="imagesRef"
					class="flex shrink-0 items-start justify-center gap-[6px]">
					<!-- IMAGE 1 (large) -->
					<Image
						:src="images[0].original"
						:srcset="images[0].srcset"
						sizes="(min-width: 1536px) 420px, (min-width: 1024px) 340px, (min-width: 640px) 290px, 220px"
						:imageCover="true"
						:preload="isLcpBlock"
						width="420"
						height="380"
						:alt="images[0]?.alt || 'family image'"
						class="h-[280px] w-[220px] object-cover sm:w-[290px] lg:h-[320px] lg:w-[340px] 2xl:h-[380px] 2xl:w-[420px]" />

					<!-- Right column only when there is a 2nd image -->
					<div v-if="hasImage(1)" class="flex flex-col gap-[6px]">
						<!-- IMAGE 2 (small, top-right) -->
						<Image
							:src="images[1].original"
							:srcset="images[1].srcset"
							sizes="(min-width: 1024px) 200px, (min-width: 640px) 140px, 105px"
							:imageCover="true"
							:imgAttributes="secondaryImageAttributes"
							width="200"
							height="187"
							:alt="images[1]?.alt || 'family image'"
							class="h-[137px] w-[105px] object-cover sm:w-[140px] lg:h-[157px] lg:w-[160px] 2xl:h-[187px] 2xl:w-[200px]" />

						<!-- IMAGE 3 (small, bottom-right) only when there is a 3rd image -->
						<Image
							v-if="hasImage(2)"
							:src="images[2].original"
							:srcset="images[2].srcset"
							sizes="(min-width: 1024px) 200px, (min-width: 640px) 140px, 105px"
							:imageCover="true"
							:imgAttributes="secondaryImageAttributes"
							width="200"
							height="187"
							:alt="images[2]?.alt || 'family image'"
							class="h-[137px] w-[105px] object-cover sm:w-[140px] lg:h-[157px] lg:w-[160px] 2xl:h-[187px] 2xl:w-[200px]" />
					</div>
				</div>

				<!-- CONTENT -->
				<div class="flex min-w-0 flex-1 flex-col">
					<div aria-hidden="true" class="invisible h-0 overflow-hidden">
						<div class="w-full" :style="titleWidthStyle">
							<h1
								ref="titleTextRef"
								class="title break-words font-bold tracking-tight text-left">
								{{ fieldValue.family?.name }}
							</h1>
						</div>
					</div>

					<div
						ref="offersRef"
						class="flex flex-col-reverse gap-4 text-center items-center lg:text-left lg:flex-row"
						:class="
							isTitleTruncated
								? 'lg:items-end lg:justify-end'
								: isTitleSingleLine
									? 'lg:items-center lg:justify-between'
									: 'lg:items-start lg:justify-between'
						">
						<div
							v-if="!isTitleTruncated"
							ref="titleRef"
							class="w-full pb-1 2xl:pb-1 lg:min-w-0 lg:flex-1">
							<h1
								:style="{ fontSize: titleFontSize }"
								class="title break-words font-bold tracking-tight text-[#1d2430] text-left">
								{{ fieldValue.family?.name }}
							</h1>
						</div>
						<div
							v-if="
								fieldValue?.family?.offers_data?.number_offers &&
								layout.iris.is_logged_in
							"
							ref="offerBadgesRef"
							class="flex gap-x-1 gap-y-1 offer flex-wrap justify-center lg:justify-end">
							<DiscountByType
								:offers_data="fieldValue?.family?.offers_data"
								:template="
									bestOffer?.type == 'Category Quantity Ordered Order Interval'
										? 'active-inactive-gr-v2'
										: 'max_discount_2'
								" />

							<DiscountByType
								v-if="
									!(
										layout?.user?.gr_data?.amnesty ||
										layout?.user?.gr_data?.customer_is_gr
									) &&
									bestOffer?.type == 'Category Quantity Ordered Order Interval'
								"
								:offers_data="fieldValue?.family?.offers_data"
								:template="'triggers_labels_v2'" />
						</div>
					</div>

					<div class="px-3 lg:px-0">
						<div
							v-if="isTitleTruncated"
							ref="truncatedTitleRef"
							class="pb-1 2xl:pb-1 px-3 lg:px-0">
							<h1
								:style="{ fontSize: titleFontSize }"
								class="title break-words font-bold tracking-tight text-[#1d2430] text-left">
								{{ fieldValue.family?.name }}
							</h1>
						</div>
						<div
							ref="descriptionBoxRef"
							class="relative flex-1 min-h-0 space-y-[4px] text-[14px] leading-[1.6] text-[#1d2430] 2xl:space-y-2 overflow-hidden"
							:class="
								!expanded && !collapsedHeight
									? 'max-h-[260px] lg:max-h-[195px] 2xl:max-h-[250px]'
									: ''
							"
							:style="
								!expanded && collapsedHeight
									? { maxHeight: `${collapsedHeight}px` }
									: {}
							">
							<div ref="descriptionBodyRef" v-html="cleanedDescription"></div>

							<!-- Fade overlay -->
							<div
								v-if="!expanded && showReadMore"
								class="absolute bottom-0 left-0 right-0 h-8 pointer-events-none bg-gradient-to-t from-white to-transparent lg:h-6" />
						</div>
					</div>

					<!-- Always bottom -->
					<div
						ref="actionsRef"
						class="mt-auto px-3 pt-3 flex flex-nowrap items-center gap-x-2 gap-y-2 lg:px-0 lg:pt-1 lg:flex-wrap lg:gap-4 lg:justify-start 2xl:pt-8">
						<a
							v-if="fieldValue.family.description_extra || layout?.iris?.is_logged_in"
							href="#family-2-extra-description"
							class="shrink-0">
							<button
								class="h-[38px] rounded-xl border border-[#333] px-4 text-sm font-medium transition hover:bg-gray-50 sm:px-6 lg:h-[30px] lg:px-8 2xl:h-[48px] 2xl:px-12 2xl:text-base"
								:style="{
									...getStyles(
										fieldValue?.button?.container?.properties,
										screenType
									),
								}">
								{{ fieldValue?.button?.text || ctrans("Learn more") }}
							</button>
						</a>

						<div
							class="tags-row flex min-w-0 flex-1 items-center gap-1 overflow-x-auto lg:flex-none lg:gap-4 lg:overflow-visible">
							<div
								v-for="data in visibleTags"
								:key="data.name"
								class="flex shrink-0 items-center gap-1.5 px-1 py-1 sm:px-2 lg:gap-2 lg:px-2 lg:py-2 2xl:px-6 2xl:py-2.5">
								<Image
									:src="data.web_image"
									sizes="20px"
									width="20"
									height="20"
									class="h-4 w-4 shrink-0 2xl:h-5 2xl:w-5"
									image-class="object-contain" />

								<span
									class="whitespace-nowrap text-[11px] font-medium text-[#555] sm:text-xs lg:text-sm 2xl:text-base">
									{{ data.name }}
								</span>
							</div>
						</div>

						<button
							v-if="showReadMore"
							type="button"
							class="ml-auto shrink-0 py-1 text-xs italic underline"
							@click="expanded = !expanded">
							{{ expanded ? ctrans("Read Less") : ctrans("Read More") }}
						</button>
					</div>
				</div>
			</div>
		</div>
	</section>
</template>

<style scoped>
:deep(.offer .vd-content) {
	@apply flex flex-col justify-center -ml-4 pl-7 pr-3 my-0.5 mr-0.5 rounded-md bg-gray-900 shadow-sm min-w-0;
}

:deep(.offer .vd-triggers) {
	@apply text-[10px] leading-tight opacity-80 max-w-[7rem] md:max-w-full whitespace-normal overflow-visible;
}

.editor-class h1 {
	font-size: 1.75rem;
	/* mobile */
}

@media (min-width: 1280px) {
	.editor-class h1 {
		font-size: 1.8rem;
		/* line-height: 1.5rem; */
		/* lg */
	}
}

@media (min-width: 1536px) {
	.editor-class h1 {
		font-size: 2.5rem;
		/* 2xl */
	}
}

.tags-row {
	scrollbar-width: none;
	-ms-overflow-style: none;
}

.tags-row::-webkit-scrollbar {
	display: none;
}

.title {
	display: -webkit-box;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	overflow: hidden;

	/* font-size: clamp(1rem, 1.1rem + 1.2vw, 1.5rem); */
	line-height: 1.25;
}
</style>
