<script setup lang="ts">
import Image from "@/Components/Image.vue"
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, computed } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle} from "@fas"
import { Image as ImageTS } from "@/types/Image"
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import BestsellerBadge from "@/Components/CMS/Webpage/Products/BestsellerBadge.vue"
import Prices from "@/Components/CMS/Webpage/Products1/Prices.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons'
import { faEnvelope } from '@far'
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import LoadingOverlay2 from "@/Components/Utils/LoadingOverlay2.vue"

const layout = inject("layout", retinaLayoutStructure)

const locale = useLocaleStore()

interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS,
    }
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    url: string | null
    canonical_url: string | null
    units: number
    bestseller?: boolean
    is_favourite?: boolean
    exist_in_portfolios_channel: number[]
    is_exist_in_all_channel: boolean
    top_seller: number | null
    web_images: {
        main: {
            original: ImageTS,
            gallery: ImageTS
        }
    }
}

const props = withDefaults(defineProps<{
    product: ProductResource
    productHasPortfolio?: Array<number>
    bestSeller: any
    buttonStyle?: object
    currency?: {
        code: string
        name: string
    }
    buttonStyleLogin?: object
    screenType: string
    hideButtonPortofolio?: boolean
}>(), {
    hideButtonPortofolio: false,
})



const currency = layout?.iris?.currency || props.currency

const emits = defineEmits<{
    (e: 'setFavorite', value: any[]): void
    (e: 'unsetFavorite', value: any[]): void
    (e: 'setBackInStock', value: any[]): void
    (e: 'unsetBackInStock', value: any[]): void
}>()


const onAddFavourite = (product: ProductResource) => {
     emits('setFavorite', product)
}
const onUnselectFavourite = (product: ProductResource) => {
    emits('unsetFavorite', product)
}


const onAddBackInStock = (product: ProductResource) => {
     emits('setBackInStock', product)
}

const onUnselectBackInStock = (product: ProductResource) => {
    emits('unsetBackInStock', product)
}


const idxSlideLoading = ref(false)
const typeOfLink = (typeof window !== 'undefined' && route()?.current()?.startsWith('iris.')) ? 'internal' : 'external'

const images = computed(() => {
    if (!props.product?.web_images) return []

    const arr = []

    if (props.product?.web_images?.main?.gallery) {
        arr.push(props.product.web_images.main.gallery)
    }

    if (props.product?.web_images?.all?.length) {
        props.product.web_images.all.slice(1).forEach(img => {
            if (img?.original) arr.push(img.original)
        })
    }

    return arr
})

const currentIndex = ref(0)
const mobileSlider = ref<HTMLElement | null>(null)

const onScroll = () => {
    if (!mobileSlider.value) return
    const el = mobileSlider.value

    const slideWidth = el.offsetWidth
    const index = Math.round(el.scrollLeft / slideWidth)

    currentIndex.value = index
}

const onTouchEnd = () => {
    if (!mobileSlider.value) return
    const el = mobileSlider.value

    const slideWidth = el.offsetWidth
    const index = Math.round(el.scrollLeft / slideWidth)

    el.scrollTo({
        left: index * slideWidth,
        behavior: 'smooth'
    })
}


</script>

<template>
    <div id="product-render" class="relative flex flex-col justify-between h-full ">
        <!-- Top Section -->
        <div>
            <BestsellerBadge v-if="product?.top_seller" :topSeller="product?.top_seller" :data="bestSeller" :screenType/>

            <!-- Product Image -->
            <component 
                :is="product.canonical_url || product.url ? LinkIris : 'div'" 
                :href="product.canonical_url || product.url"  
                :type="typeOfLink" 
                @start="() => idxSlideLoading = true" 
                @finish="() => idxSlideLoading = false"
                class="relative block w-full mb-1 rounded overflow-hidden sm:aspect-square aspect-[4/5]"
            >
                 <div class="relative w-full h-full bg-white">

                    <slot name="image" :product="product">

                        <!-- MOBILE -->
                        <div v-if="images.length" class="md:hidden w-full h-full relative">
                            <div v-if="images.length > 1" ref="mobileSlider" @scroll="onScroll" @touchend="onTouchEnd"
                                class="flex w-full h-full overflow-x-auto snap-x snap-mandatory scroll-smooth [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">

                                <div v-for="(img, i) in images" :key="i"
                                    class="w-full h-full flex-shrink-0 snap-start snap-always">

                                    <Image :src="img" :alt="product.name" class="w-full h-full"
                                       :style="{ objectFit: 'contain', objectPosition: 'center' }" />
                                </div>
                            </div>

                            <div v-else class="w-full h-full">
                                <Image :src="images[0]" :alt="product.name" class="w-full h-full"
                                   :style="{ objectFit: 'contain', objectPosition: 'center' }" />
                            </div>

                            <div v-if="images.length > 1"
                                class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                                <span v-for="(img, i) in images" :key="'dot-' + i"
                                    class="w-2 h-2 rounded-full transition-all"
                                    :class="i === currentIndex ? 'bg-black' : 'bg-black/30'" />
                            </div>

                        </div>

                        <!-- DESKTOP -->
                        <div v-if="images.length" class="hidden md:block relative w-full h-full overflow-hidden group">
                            <div class="flex w-full h-full"
                                :class="images.length > 1 ? 'group-hover:-translate-x-full' : ''">

                                <div class="w-full h-full flex-shrink-0 flex items-center justify-center">
                                    <Image :src="images[0]" :alt="product.name" class="max-w-full max-h-full"
                                       :style="{ objectFit: 'contain', objectPosition: 'center' }" />
                                </div>

                                <div v-if="images.length > 1"
                                    class="w-full h-full flex-shrink-0 flex items-center justify-center">
                                    <Image :src="images[1]" :alt="product.name" class="max-w-full max-h-full"
                                       :style="{ objectFit: 'contain', objectPosition: 'center' }" />
                                </div>

                            </div>
                        </div>

                        <FontAwesomeIcon v-if="!images.length" icon="fal fa-image"
                            class="opacity-20 text-3xl md:text-7xl absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2" />

                    </slot>
                </div>


                 <div v-if="layout?.iris?.is_logged_in && !product.variant" class="absolute right-2 bottom-2">
                    <button 
                        v-if="!product.stock && layout?.outboxes?.oos_notification?.state == 'active' && !product.variant"
                        @click.prevent="() => product.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                        class="rounded-full bg-gray-200 hover:bg-gray-300 h-10 w-10 flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                        v-tooltip="product.is_back_in_stock ? trans('You will be notified') : trans('Remind me when back in stock')">
                        <LoadingIcon v-if="isLoadingRemindBackInStock" />
                        <FontAwesomeIcon v-else :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                            fixed-width :class="[product.is_back_in_stock ? 'text-green-600' : 'text-gray-600']" />
                    </button>
                </div>
            </component>

            <!-- Title -->
            <LinkIris v-if="product.url" :href="product.url" type="internal"
                class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                <template #default>
                    <span class="">{{ product.units }}x</span> {{ product.name }}
                </template>
            </LinkIris>

            <div v-else class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                <span class="">{{ product.units }}x</span> {{ product.name }}
            </div>

            <!-- code and stock -->
            <div class="text-xs text-gray-600 mb-1 w-full grid grid-cols-1 md:grid-cols-[auto_1fr] gap-1 items-center">
                <!-- Product Code -->
                <div class="flex items-center">
                    {{ product?.code }}
                </div>

                <!-- Stock Info -->
                <div v-if="layout?.iris?.is_logged_in" class="flex items-center md:justify-end justify-start">
                    <div v-if="!product.is_coming_soon"
                        class="flex items-start gap-1 px-2 py-1 rounded-xl font-medium max-w-[12rem] break-words leading-snug"
                        :class="(product?.stock > 0 ) ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">
                        <span class="inline-flex items-center gap-1 text-xs leading-snug">
                            <FontAwesomeIcon :icon="faCircle" class="text-[6px] shrink-0" />
                            <span>
                                {{ product?.stock >= 250
                                    ? trans("Unlimited quantity available")
                                    : (product.stock > 0
                                        ? product.stock + ' ' + trans('available')
                                        : '0 ' + trans('available'))
                                }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-auto">
            <Prices :product="product" :currency="currency" />
        </div>

        <div v-if="!hideButtonPortofolio">
            <ButtonAddPortfolio v-if="!product.variant" :product="product" :productHasPortfolio="productHasPortfolio"
                :buttonStyle="buttonStyle" :buttonStyleLogin />
            <div v-else class="w-full">
                <LinkIris v-if="product.url" :href="product.url" type="internal"
                    class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                    <template #default>
                        <Button full :label="trans('Check Variants')" />
                    </template>
                </LinkIris>
            </div>
        </div>

        <LoadingOverlay2 v-if="idxSlideLoading" />
    </div>
</template>


<style scoped>
</style>