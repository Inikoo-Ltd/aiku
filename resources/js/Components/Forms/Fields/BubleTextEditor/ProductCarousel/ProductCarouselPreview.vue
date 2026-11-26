<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch, nextTick } from "vue"
import axios from "axios"
import { debounce } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronLeft, faChevronRight } from "@far"
import { faImage, faExclamationTriangle } from "@fal"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import {
	DEFAULT_PRODUCT_NAME_TAG,
	DEFAULT_TITLE_TAG,
	type ProductCarouselAttributes,
	carouselTextTag,
	productCarouselHasContent,
	productCarouselPreviewParams,
	productCarouselSource,
	productsPerView,
} from "./productCarouselAttributes"

interface PreviewProduct {
	id: number
	code: string
	name: string
	stock: number | null
	price: number | null
	units: number | null
	unit: string | null
	rrp_per_unit: number | null
	web_images?: { main?: { gallery?: any, thumbnail?: any } }
}

const props = defineProps<{
	attributes: ProductCarouselAttributes
	previewRoute?: { name: string, parameters: Record<string, any> }
}>()

const GAP_PX = 12

const locale = useLocaleStore()

const products = ref<PreviewProduct[]>([])
const currencyCode = ref<string | null>(null)
const isLoading = ref(false)
const hasFailed = ref(false)
const track = ref<HTMLElement | null>(null)
const canScrollBack = ref(false)
const canScrollForward = ref(false)
let pendingRequest: AbortController | null = null
let resizeObserver: ResizeObserver | null = null

const perView = computed(() => productsPerView(props.attributes.perView))
const titleTag = computed(() => carouselTextTag(props.attributes.titleTag, DEFAULT_TITLE_TAG))
const productNameTag = computed(() => carouselTextTag(props.attributes.productNameTag, DEFAULT_PRODUCT_NAME_TAG))
const requestParams = computed(() => productCarouselPreviewParams(props.attributes))
const hasContent = computed(() => productCarouselHasContent(props.attributes))

const cardWidth = computed(() => `calc((100% - ${(perView.value - 1) * GAP_PX}px) / ${perView.value})`)

const missingCodes = computed(() => {
	if (productCarouselSource(props.attributes.source) !== "products" || isLoading.value || hasFailed.value) {
		return []
	}
	const shownCodes = new Set(products.value.map((product) => product.code.toLowerCase()))

	return (props.attributes.codes ?? []).filter((code) => !shownCodes.has(code.toLowerCase()))
})

const money = (amount: number) => (currencyCode.value ? locale.currencyFormat(currencyCode.value, amount) : amount)

const imageOf = (product: PreviewProduct) => product.web_images?.main?.gallery ?? product.web_images?.main?.thumbnail ?? null

const updateScrollEdges = () => {
	const element = track.value
	if (!element) {
		return
	}
	canScrollBack.value = element.scrollLeft > 1
	canScrollForward.value = element.scrollLeft + element.clientWidth < element.scrollWidth - 1
}

const scrollByCard = (direction: number) => {
	const element = track.value
	if (!element) {
		return
	}
	const card = element.firstElementChild as HTMLElement | null
	element.scrollBy({ left: direction * ((card?.offsetWidth ?? element.clientWidth) + GAP_PX), behavior: "smooth" })
}

async function loadProducts() {
	pendingRequest?.abort()
	if (!props.previewRoute || !hasContent.value) {
		products.value = []
		isLoading.value = false
		return
	}
	const request = new AbortController()
	pendingRequest = request
	isLoading.value = true
	hasFailed.value = false
	try {
		const response = await axios.get(route(props.previewRoute.name, props.previewRoute.parameters), {
			params: requestParams.value,
			signal: request.signal,
		})
		products.value = response.data?.data ?? []
		currencyCode.value = response.data?.currency_code ?? null
	} catch (error) {
		if (!axios.isCancel(error)) {
			hasFailed.value = true
		}
	} finally {
		if (pendingRequest === request) {
			isLoading.value = false
			await nextTick()
			updateScrollEdges()
		}
	}
}

const loadProductsSoon = debounce(loadProducts, 300)

watch(() => JSON.stringify(requestParams.value), loadProductsSoon)
watch(perView, () => nextTick(updateScrollEdges))

onMounted(() => {
	loadProducts()
	if (track.value && typeof ResizeObserver !== "undefined") {
		resizeObserver = new ResizeObserver(updateScrollEdges)
		resizeObserver.observe(track.value)
	}
})

onBeforeUnmount(() => {
	loadProductsSoon.cancel()
	pendingRequest?.abort()
	resizeObserver?.disconnect()
})
</script>

<template>
	<div class="product-carousel-preview">
		<div class="mb-2 flex items-center justify-between gap-3">
			<component :is="titleTag" v-if="attributes.title" class="product-carousel-preview-title">{{ attributes.title }}</component>
			<div class="ml-auto flex shrink-0 items-center gap-1.5">
				<LoadingIcon v-if="isLoading && products.length" />
				<button
					type="button"
					class="product-carousel-preview-arrow"
					:aria-label="ctrans('Previous')"
					:disabled="!canScrollBack"
					@mousedown.prevent
					@click.stop="scrollByCard(-1)"
				>
					<FontAwesomeIcon :icon="faChevronLeft" fixed-width aria-hidden="true" />
				</button>
				<button
					type="button"
					class="product-carousel-preview-arrow"
					:aria-label="ctrans('Next')"
					:disabled="!canScrollForward"
					@mousedown.prevent
					@click.stop="scrollByCard(1)"
				>
					<FontAwesomeIcon :icon="faChevronRight" fixed-width aria-hidden="true" />
				</button>
			</div>
		</div>

		<div ref="track" class="product-carousel-preview-track" :style="{ gap: `${GAP_PX}px` }" @scroll.passive="updateScrollEdges">
			<template v-if="isLoading && !products.length">
				<div v-for="placeholder in perView" :key="placeholder" class="skeleton aspect-[3/4] shrink-0 rounded-lg" :style="{ width: cardWidth }" />
			</template>
			<div
				v-for="product in products"
				:key="product.id"
				class="product-carousel-preview-card"
				:style="{ width: cardWidth }"
			>
				<div class="flex aspect-square items-center justify-center overflow-hidden border-b border-gray-100 bg-white p-2">
					<Image v-if="imageOf(product)" :src="imageOf(product)" :alt="product.name" class="size-full object-contain" />
					<FontAwesomeIcon v-else :icon="faImage" class="text-3xl text-gray-300" fixed-width aria-hidden="true" />
				</div>
				<div class="flex flex-1 flex-col gap-1 p-2">
					<component :is="productNameTag" class="product-carousel-preview-name">
						<span v-if="(product.units ?? 1) > 1" class="text-[var(--app-accent)]">{{ product.units }}×</span>
						{{ product.name }}
					</component>
					<div class="mt-auto flex items-center justify-between gap-2 text-xs text-gray-500">
						<span class="flex min-w-0 items-center gap-1">
							<span class="size-1.5 shrink-0 rounded-full" :class="(product.stock ?? 0) > 0 ? 'bg-green-500' : 'bg-red-500'" />
							<span class="truncate whitespace-nowrap font-mono">{{ product.code }}</span>
						</span>
						<span v-if="(product.rrp_per_unit ?? 0) > 0" class="shrink-0 whitespace-nowrap">{{ ctrans("RRP") }} {{ money(product.rrp_per_unit ?? 0) }}</span>
					</div>
					<div v-if="product.price !== null" class="whitespace-nowrap text-sm font-semibold text-gray-900" v-tooltip="ctrans('Shown to logged in customers')">
						{{ money(product.price) }}<span v-if="(product.units ?? 1) <= 1 && product.unit" class="text-xs font-normal text-gray-500">/{{ product.unit }}</span>
					</div>
				</div>
			</div>
		</div>

		<p v-if="!isLoading && !products.length" class="rounded-md border border-dashed border-gray-300 bg-white px-3 py-4 text-center text-xs text-gray-500">
			<template v-if="hasFailed">{{ ctrans("The preview could not be loaded.") }}</template>
			<template v-else-if="!previewRoute">{{ ctrans("The preview is not available here.") }}</template>
			<template v-else-if="!hasContent">{{ ctrans("No products yet") }}</template>
			<template v-else>{{ ctrans("None of these products has a live webpage, so the carousel will not show on the website.") }}</template>
		</p>

		<p v-if="missingCodes.length && products.length" class="mt-2 flex items-start gap-1.5 text-xs text-amber-700">
			<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
			<span>{{ ctrans("Not shown, no live webpage: :codes", { codes: missingCodes.join(", ") }) }}</span>
		</p>
	</div>
</template>

<style scoped>
.product-carousel-preview-title {
	margin: 0;
	font-size: 1.125rem;
	font-weight: 700;
	line-height: 1.5rem;
	color: #111827;
}

.product-carousel-preview-track {
	display: flex;
	overflow-x: auto;
	scroll-snap-type: x mandatory;
	scrollbar-width: thin;
	padding-bottom: 0.25rem;
}

.product-carousel-preview-track > * {
	scroll-snap-align: start;
}

.product-carousel-preview-card {
	display: flex;
	flex-direction: column;
	flex-shrink: 0;
	overflow: hidden;
	border: 1px solid #e5e7eb;
	border-radius: 0.5rem;
	background: #ffffff;
}

.product-carousel-preview-name {
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	margin: 0;
	font-size: 0.8125rem;
	font-weight: 600;
	line-height: 1.125rem;
	color: #111827;
}

.product-carousel-preview-arrow {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 1.75rem;
	height: 1.75rem;
	border: 1px solid #e5e7eb;
	border-radius: 9999px;
	background-color: #ffffff;
	color: #374151;
	transition: border-color 0.2s ease, color 0.2s ease;
}

.product-carousel-preview-arrow:hover:not(:disabled) {
	border-color: var(--app-accent);
	color: var(--app-accent);
}

.product-carousel-preview-arrow:disabled {
	opacity: 0.4;
	cursor: default;
}
</style>
