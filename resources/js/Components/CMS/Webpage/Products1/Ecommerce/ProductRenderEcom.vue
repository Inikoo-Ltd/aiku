<script setup lang="ts">
import Image from '@/Components/Image.vue'
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
import { routeType } from '@/types/route'
import LabelComingSoon from '@/Components/Iris/Products/LabelComingSoon.vue'
import Prices2 from '../Prices2.vue'

library.add(faStarHalfAlt, faQuestionCircle)

const layout = inject('layout', retinaLayoutStructure)

const props = withDefaults(defineProps<{
    product: ProductResource  // IrisAuthenticatedProductsInWebpageResource
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
    (e: 'onVariantClick', value: any[]): void
}>()

const _button_variant = ref(null)
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


const onClickVariant = (product: ProductResource, event : Event) => {
    emits('onVariantClick', product.variant, event)
   
}



const idxSlideLoading = ref(false)
const typeOfLink = (typeof window !== 'undefined' && route()?.current()?.startsWith('iris.')) ? 'internal' : 'external'

defineExpose({
 _button_variant
})

</script>

<template> 
    <div  class="text-gray-800 isolate h-full flex flex-col flex-grow"  comp="product-render-ecom">

        <!-- Top Section: Stock, Images, Title, Code, Price -->
        <div class="text-gray-800 isolate h-full">
            <BestsellerBadge v-if="product?.top_seller" :topSeller="product?.top_seller" :data="bestSeller" :screenType="screenType"/>

            <!-- Section: Product Image, Add to Cart button, Email out of stock, Favourite -->
            <component :is="product.url ? LinkIris : 'div'" :href="product.url" :id="product?.url?.id"
                :type="typeOfLink"   class="relative block w-full mb-1 rounded overflow-hidden sm:aspect-square aspect-[4/5]"
                @start="() => idxSlideLoading = true" @finish="() => idxSlideLoading = false"
            >
                <slot name="image" :product="product">
                    <Image
                        v-if="product?.web_images?.main?.gallery" :src="product?.web_images?.main?.gallery"
                        :alt="product.name"
                        :style="{ objectFit: 'contain' }"
                        class="w-full h-full flex justify-center items-center"
                    />
                    <FontAwesomeIcon v-else icon="fal fa-image" class="opacity-20 text-3xl md:text-7xl absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2" fixed-width aria-hidden="true" />
                </slot>

                <!-- Section: Favourite -->
                <template v-if="layout?.iris?.is_logged_in && basketButton && !product.is_variant">
                    <div v-if="isLoadingFavourite" class="absolute right-2 top-2 text-pink-400 text-xl z-10">
                        <LoadingIcon />
                    </div>
                    <div v-else
                        @click.prevent="() => product.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)"
                        class="cursor-pointer absolute right-2 top-2 group text-xl z-10">

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

                <div v-if="layout?.iris?.is_logged_in && !product.variant" class="absolute right-2 bottom-2">
                    <NewAddToCartButton 
                        v-if="product.stock && basketButton && !product.is_coming_soon" 
                        :hasInBasket 
                        :product="product"
                        :key="product" 
                        :addToBasketRoute="addToBasketRoute" 
                        :buttonStyleHover="buttonStyleHover"
                        :updateBasketQuantityRoute="updateBasketQuantityRoute" 
                        :buttonStyle="buttonStyle" 
                    />
                    <button v-else-if="!product.stock && layout?.outboxes?.oos_notification?.state == 'active' && basketButton && !product.variant"
                        @click.prevent="() => product.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                        class="rounded-full bg-gray-200 hover:bg-gray-300 h-10 w-10 flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                        v-tooltip="product.is_back_in_stock ? trans('You will be notified') : trans('Remind me when back in stock')">
                        <LoadingIcon v-if="isLoadingRemindBackInStock" />
                        <FontAwesomeIcon v-else :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                            fixed-width :class="[product.is_back_in_stock ? 'text-green-600' : 'text-gray-600']" />
                    </button>
                </div>

                <div v-if="layout?.iris?.is_logged_in && product.variant"
                    class="absolute inset-x-0 bottom-2 z-10 text-gray-500 text-xl">
                    <div class="flex justify-center">
                        <Button :label="trans('Choose variants')" size="xs"
                             @click.prevent.stop="(e)=>onClickVariant(product,e)"  :ref="(e)=>_button_variant=e" />
                    </div>
                </div>

            </component>

            <div class="xpx-3 mt-2">
                <!-- Title -->
                <LinkIris v-if="product.url" :href="product.url" class="hover:text-gray-500 font-bold text-sm mb-1"
                    :type="typeOfLink" :id="product?.url?.id">
                    <template #default>
                        <p class="inline-block leading-4 text-justify">
                            <span v-if="product.units != 1" class="text-indigo-900">{{ product.units }}x</span>
                            {{ product.name }}
                        </p>
                    </template>
                </LinkIris>
                <div v-else class="hover:text-gray-500 font-bold text-sm mb-1">
                    <span v-if="product.units != 1" class="text-indigo-900">{{ product.units }}x</span> {{ product.name}}
                </div>

                <!-- Product Code -->
                <div class="flex items-center text-xs mt-1">
                    {{ product?.code }}
                </div>

                <!-- Section: 'Coming Soon', Stock -->
                <div v-if="layout?.iris?.is_logged_in" class="text-xs text-gray-600 xmb-1 w-full flex justify-between gap-x-2 items-center">
                    <div class="flex items-center w-full">
                        <LabelComingSoon v-if="product.is_coming_soon" :product class="w-full text-center "/>
                        <div v-else
                            v-tooltip="trans('Available product stocks')"
                            class="flex items-center gap-1 py-1 font-medium w-fit break-words leading-snug"
                            :class="(product.stock > 0 ) ? 'xbg-green-50 xtext-green-700' : 'bg-red-50 text-red-600'"
                        >
                            <FontAwesomeIcon :icon="faCircle" class="xtext-[6px] shrink-0" fixed-width :class="(product.stock > 0 ) ? 'text-green-600' : 'bg-red-50 text-red-600'" />
                            <span>
                                ({{
                                    product?.stock >= 250
                                    ? trans("Unlimited quantity")
                                    : (product.stock > 0
                                        ? product.stock
                                        : '0')
                                }})
                            </span>
                        </div>
                    </div>

                    <!-- <div v-if="!product.is_coming_soon" class="w-full text-right">
                        <FontAwesomeIcon icon="fas fa-star" class="" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-star" class="" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-star" class="" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-star" class="" fixed-width aria-hidden="true" />
                        <FontAwesomeIcon icon="fas fa-star" class="" fixed-width aria-hidden="true" />
                    </div> -->
                </div>
            </div>
        </div>

        
        <div class="xpx-3 mt-auto">
            <Prices2
                v-if="layout?.iris?.is_logged_in"
                :product="product"
                :currency="currency"
                :basketButton
            />
            <!-- <Prices :product="product" :currency="currency" /> -->
            
            <div v-else class="mt-2">
                <a :href="urlLoginWithRedirect()" class="w-full">
                    <Button :label="trans('Login or Register for Wholesale Prices')" class="rounded-none" full :injectStyle="buttonStyleLogin" />
                </a>
            </div>
        </div>

        <div 
            v-if="idxSlideLoading"
            class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl">
            <LoadingIcon />
        </div>
    </div>
</template>


<style scoped></style>