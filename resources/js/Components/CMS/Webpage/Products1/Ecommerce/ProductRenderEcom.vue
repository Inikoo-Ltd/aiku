<script setup lang="ts">
import Image from '@/Components/Image.vue'
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faEnvelope, faHeart } from '@far'
import { faCircle, faHeart as fasHeart } from '@fas'
import { urlLoginWithRedirect } from '@/Composables/urlLoginWithRedirect'
import Button from '@/Components/Elements/Buttons/Button.vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle } from "@fal"
import { faStarHalfAlt } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ProductResource } from '@/types/Iris/Products'
import NewAddToCartButton from '@/Components/CMS/Webpage/Products/NewAddToCartButton.vue' 
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import BestsellerBadge from '@/Components/CMS/Webpage/Products/BestsellerBadge.vue'
import Prices from '@/Components/CMS/Webpage/Products1/Prices.vue'
import { routeType } from '@/types/route'
import LabelComingSoon from '@/Components/Iris/Products/LabelComingSoon.vue'

library.add(faStarHalfAlt, faQuestionCircle)

const layout = inject('layout', retinaLayoutStructure)
const locale = useLocaleStore()

const props = withDefaults(defineProps<{
    product: ProductResource
    hasInBasket?: any
    basketButton?: boolean
    bestSeller?: any
    buttonStyleHover?: any
    buttonStyle?: object | undefined
    buttonStyleLogin?: object | undefined
    addToBasketRoute:routeType
    updateBasketQuantityRoute?:routeType
    isLoadingFavourite:boolean
    isLoadingRemindBackInStock:boolean
    screenType:string
}>(), {
    basketButton: true,
    addToBasketRoute: {
        name: 'iris.models.transaction.store',
    },
    updateBasketQuantityRoute: {
        name: 'iris.models.transaction.update',
    },
})

const emits = defineEmits<{
    (e: 'setFavorite', value: any[]): void
    (e: 'unsetFavorite', value: any[]): void
    (e: 'setBackInStock', value: any[]): void
    (e: 'unsetBackInStock', value: any[]): void
}>()


const currency = layout?.iris?.currency


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


</script>

<template>           
    <div  class="text-gray-800 isolate h-full flex flex-col"  comp="product-render-ecom">

        <!-- Top Section: Stock, Images, Title, Code, Price -->
        <div class="text-gray-800 isolate h-full">
            <BestsellerBadge v-if="product?.top_seller" :topSeller="product?.top_seller" :data="bestSeller" :screenType="screenType"/>

            <!-- Product Image -->
            <component :is="product.url ? LinkIris : 'div'" :href="product.url" :id="product?.url?.id"
                :type="typeOfLink" class="block w-full mb-1 rounded sm:h-[305px] h-[180px] relative"
                @start="() => idxSlideLoading = true" @finish="() => idxSlideLoading = false">
                <slot name="image" :product="product">
                    <Image v-if="product?.web_images?.main?.gallery" :src="product?.web_images?.main?.gallery" :alt="product.name"
                        :style="{ objectFit: 'contain' }" />
                    <FontAwesomeIcon v-else icon="fal fa-image" class="opacity-20 text-3xl md:text-7xl absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2" fixed-width aria-hidden="true" />
                </slot>

                <template v-if="layout?.iris?.is_logged_in">
                    <div v-if="isLoadingFavourite" class="absolute bottom-2 left-2 text-gray-500 text-xl z-10">
                        <LoadingIcon />
                    </div>
                    <div v-else
                        @click.prevent="() => product.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)"
                        class="cursor-pointer absolute left-2 bottom-2 group text-xl z-10">

                        <FontAwesomeIcon v-if="product.is_favourite" :icon="fasHeart" fixed-width
                            class="text-pink-500" />
                        <div v-else class="relative">
                            <FontAwesomeIcon :icon="fasHeart" class="hidden group-hover:inline text-pink-400"
                                fixed-width />
                            <FontAwesomeIcon :icon="faHeart" class="inline group-hover:hidden text-pink-300"
                                fixed-width />
                        </div>
                    </div>
                </template>

                <!-- New Add to Cart Button - hanya tampil jika user sudah login -->
                <div v-if="layout?.iris?.is_logged_in" class="absolute right-2 bottom-2">
                    <NewAddToCartButton 
                        v-if="product.stock > 0 && basketButton && !product.is_coming_soon" 
                        :hasInBasket 
                        :product="product"
                        :key="product" 
                        :addToBasketRoute="addToBasketRoute" 
                        :buttonStyleHover="buttonStyleHover"
                        :updateBasketQuantityRoute="updateBasketQuantityRoute" 
                        :buttonStyle="buttonStyle" 
                    />
                    <button v-else-if="layout?.app?.environment === 'local' && product.stock < 1"
                        @click.prevent="() => product.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                        class="rounded-full bg-gray-200 hover:bg-gray-300 h-10 w-10 flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                        v-tooltip="product.is_back_in_stock ? trans('You will be notified') : trans('Remind me when back in stock')">
                        <LoadingIcon v-if="isLoadingRemindBackInStock" />
                        <FontAwesomeIcon v-else :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                            fixed-width :class="[product.is_back_in_stock ? 'text-green-600' : 'text-gray-600']" />
                    </button>
                </div>
            </component>

            <div class="px-3">
                <!-- Title -->
                <LinkIris v-if="product.url" :href="product.url" class="hover:text-gray-500 font-bold text-sm mb-1"
                    :type="typeOfLink" :id="product?.url?.id">
                    <template #default>
                        <span v-if="product.units != 1" class="text-indigo-900">{{ product.units }}x</span>
                        {{ product.name }}
                    </template>
                </LinkIris>
                <div v-else class="hover:text-gray-500 font-bold text-sm mb-1">
                    <span v-if="product.units != 1" class="text-indigo-900">{{ product.units }}x</span> {{ product.name}}
                </div>

                <!-- Price Card -->
                <div
                    class="text-xs text-gray-600 mb-1 w-full grid grid-cols-1 md:grid-cols-[auto_1fr] gap-1 items-center">
                    <!-- Product Code -->
                    <div class="flex items-center">
                        {{ product?.code }}
                    </div>

                    <!-- Section: Stock -->
                    <div v-if="layout?.iris?.is_logged_in" class="flex items-center md:justify-end justify-start">
                        <LabelComingSoon v-if="product.is_coming_soon" :product class="w-full text-center md:w-fit md:text-right"/>
                        <div v-else
                            class="flex items-start gap-1 px-2 py-1 rounded-xl font-medium max-w-[10rem] break-words leading-snug"
                            :class="product.stock > 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'">


                            <span class="text-xs">
                                <FontAwesomeIcon :icon="faCircle" class="text-[6px] " />
                                {{ product.is_on_demand
                                    ? trans("Unlimited quantity available")
                                    : (product.stock > 0 ? product.stock + ' ' + trans('available') : '0 ' +
                                        trans('available'))
                                }}
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            <div 
                v-if="idxSlideLoading"
                class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl">
                <LoadingIcon />
            </div>
        </div>

        
        <div class="px-3 mt-auto">
            <Prices :product="product" :currency="currency" />
            <div v-if="!layout?.iris?.is_logged_in" class="mt-2">
                <a :href="urlLoginWithRedirect()" class="w-full">
                    <Button label="Login or Register for Wholesale Prices" class="rounded-none" full :injectStyle="buttonStyleLogin" />
                </a>
            </div>
        </div>
    </div>
</template>


<style scoped></style>