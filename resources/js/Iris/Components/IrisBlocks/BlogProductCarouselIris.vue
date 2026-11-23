<script setup lang="ts">
import { computed, inject, shallowRef } from "vue"
import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"
import { get } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronLeft, faChevronRight } from "@far"
import RangeAdvisorProductCard from "@/Iris/Components/RangeAdvisorProductCard.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { ProductResource } from "@/types/Iris/Products"

const props = defineProps<{
	title?: string
	codes: string[]
	perView: number
	products: Record<string, ProductResource>
}>()

const layout: any = inject("layout", retinaLayoutStructure)

const isDropshipping = computed(() => layout?.retina?.type === "dropshipping")

const carouselProducts = computed(() =>
	props.codes
		.map((code) => props.products?.[code.toLowerCase()])
		.filter((product): product is ProductResource => !!product)
)

const breakpoints = computed(() => ({
	0: { slidesPerView: Math.min(2, props.perView) + 0.2 },
	640: { slidesPerView: Math.min(3, props.perView) },
	1024: { slidesPerView: props.perView },
}))

const swiperInstance = shallowRef<any>(null)
const isBeginning = shallowRef(true)
const isEnd = shallowRef(false)

const updateEdges = (swiper: any) => {
	isBeginning.value = swiper.isBeginning
	isEnd.value = swiper.isEnd
}

const onSwiper = (swiper: any) => {
	swiperInstance.value = swiper
	updateEdges(swiper)
}

const hasMoreThanFits = computed(() => carouselProducts.value.length > props.perView)
</script>

<template>
	<section v-if="carouselProducts.length" class="blog-product-carousel" :aria-label="title || ctrans('Products')">
		<div v-if="title || hasMoreThanFits" class="blog-product-carousel-header">
			<h3 v-if="title" class="blog-product-carousel-title">{{ title }}</h3>
			<div v-if="hasMoreThanFits" class="blog-product-carousel-nav">
				<button type="button" :aria-label="ctrans('Previous')" :disabled="isBeginning" @click="swiperInstance?.slidePrev()">
					<FontAwesomeIcon :icon="faChevronLeft" fixed-width aria-hidden="true" />
				</button>
				<button type="button" :aria-label="ctrans('Next')" :disabled="isEnd" @click="swiperInstance?.slideNext()">
					<FontAwesomeIcon :icon="faChevronRight" fixed-width aria-hidden="true" />
				</button>
			</div>
		</div>

		<Swiper
			:breakpoints="breakpoints"
			:space-between="12"
			:watch-overflow="true"
			class="w-full"
			@swiper="onSwiper"
			@slide-change="updateEdges"
			@reach-end="updateEdges"
			@reach-beginning="updateEdges"
		>
			<SwiperSlide v-for="product in carouselProducts" :key="product.id" class="!h-auto">
				<RangeAdvisorProductCard
					:product="product as any"
					:hideBasket="isDropshipping"
					:hasInBasket="get(layout, ['family_page', 'productInBasket', 'list', product.id])"
					:buttonStyle="layout?.buttonBasket?.buttonStyle"
					:buttonStyleHover="layout?.buttonBasket?.buttonStyleHover"
				/>
			</SwiperSlide>
		</Swiper>
	</section>
</template>

<style scoped>
.blog-product-carousel {
	margin: 1.75rem 0;
}

.blog-product-carousel-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
	margin-bottom: 0.75rem;
}

.blog-product-carousel-title {
	margin: 0;
	font-size: 1.25rem;
	font-weight: 700;
	line-height: 1.75rem;
	color: #111827;
}

.blog-product-carousel-nav {
	display: flex;
	gap: 0.375rem;
	margin-left: auto;
}

.blog-product-carousel-nav button {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 2rem;
	height: 2rem;
	border: 1px solid #e5e7eb;
	border-radius: 9999px;
	background-color: #ffffff;
	color: #374151;
	transition: border-color 0.2s ease, color 0.2s ease;
}

.blog-product-carousel-nav button:hover:not(:disabled) {
	border-color: var(--theme-color-4);
	color: var(--theme-color-4);
}

.blog-product-carousel-nav button:disabled {
	opacity: 0.4;
	cursor: default;
}
</style>
