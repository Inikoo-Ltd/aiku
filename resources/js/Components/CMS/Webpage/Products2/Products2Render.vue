<script setup lang="ts">
import { inject, ref } from 'vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope, faHeart as farHeart } from '@far'
import { faHeart as fasHeart, faStarHalfAlt } from '@fas'
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons'
import { faQuestionCircle } from "@fal"

import Image from '@/Components/Image.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import NewAddToCartButton from '@/Components/CMS/Webpage/Products/NewAddToCartButton.vue'
import BestsellerBadge from '@/Components/CMS/Webpage/Products/BestsellerBadge.vue'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { useLocaleStore } from "@/Stores/locale"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { trans } from 'laravel-vue-i18n'
import { urlLoginWithRedirect } from '@/Composables/urlLoginWithRedirect'
import { ProductResource } from '@/types/Iris/Products'
import { routeType } from '@/types/route'

library.add(faStarHalfAlt, faQuestionCircle)

const layout = inject('layout', retinaLayoutStructure)
const locale = useLocaleStore()

const props = withDefaults(
    defineProps<{
        product: ProductResource
        hasInBasket?: any
        basketButton?: boolean
        bestSeller?: any
        buttonStyleHover?: any
        buttonStyle?: object
        buttonStyleLogin?: object
        addToBasketRoute?: routeType
        updateBasketQuantityRoute?: routeType
        isLoadingFavourite?: boolean
        isLoadingRemindBackInStock?: boolean
        button: any
    }>(),
    {
        isLoadingFavourite: false,
        isLoadingRemindBackInStock: false,
        basketButton: true,
        addToBasketRoute: { name: 'iris.models.transaction.store' },
        updateBasketQuantityRoute: { name: 'iris.models.transaction.update' },
    }
)

const emit = defineEmits([
    'setFavorite',
    'unsetFavorite',
    'setBackInStock',
    'unsetBackInStock'
])

const currency = layout?.iris?.currency
const idxSlideLoading = ref(false)

const typeOfLink =
    typeof window !== 'undefined' &&
        route()?.current()?.startsWith('iris.')
        ? 'internal'
        : 'external'

const toggleFavourite = () =>
    props.product.is_favourite
        ? emit('unsetFavorite', props.product)
        : emit('setFavorite', props.product)

const toggleBackInStock = () =>
    props.product.is_back_in_stock
        ? emit('unsetBackInStock', props.product)
        : emit('setBackInStock', props.product)
</script>

<template>
    <div id="products-2-render" class="pb-3 relative flex flex-col h-full" comp="product-2-render-ecom">
        <div class="text-gray-800 isolate flex flex-col h-full">

            <!-- TOP AREA (GROWS) -->
            <div class="flex-grow">

                <BestsellerBadge v-if="product?.top_seller" :topSeller="product?.top_seller" :data="bestSeller" />

                <!-- IMAGE -->
                <component :is="product.url ? LinkIris : 'div'" :href="product.url" :id="product?.url?.id"
                    :type="typeOfLink" class="block w-full mb-1 rounded-xl sm:h-[305px] h-[180px] relative"
                    @start="idxSlideLoading = true" @finish="idxSlideLoading = false">
                    <slot name="image" :product="product">
                        <Image v-if="product?.web_images?.main?.gallery" :src="product?.web_images?.main?.gallery"
                            :alt="product.name" :style="{
                                objectFit: 'contain',
                                opacity: product.stock <= 0 ? 0.4 : 1
                            }" />

                        <FontAwesomeIcon v-else icon="fal fa-image"
                            class="opacity-20 text-3xl md:text-7xl absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2"
                            fixed-width />

                       <div
                            v-if="product.stock <= 0"
                            class="absolute inset-0 z-10 flex items-center justify-center rounded-xl pointer-events-none"
                        >
                            <div
                                class="
                                    w-full 
                                    bg-white/95 text-gray-900
                                    text-xs sm:text-sm md:text-sm
                                    font-semibold md:font-bold
                                    tracking-wide uppercase
                                    py-1 sm:py-1
                                    text-center
                                    shadow-sm md:shadow-md
                                    backdrop-blur-sm
                                "
                            >
                                {{ trans("Out of Stock") }}
                            </div>
                        </div>
                    </slot>

                    <!-- FAVOURITE -->
                    <template v-if="layout?.iris?.is_logged_in">
                        <div v-if="isLoadingFavourite" class="absolute top-1 right-2 text-gray-500 text-xl z-10">
                            <LoadingIcon />
                        </div>

                        <div v-else @click.prevent="toggleFavourite"
                            class="cursor-pointer absolute top-1 right-2 group text-xl z-10">
                            <FontAwesomeIcon v-if="product.is_favourite" :icon="fasHeart" class="text-pink-500"
                                fixed-width />

                            <div v-else class="relative">
                                <FontAwesomeIcon :icon="fasHeart" class="hidden group-hover:inline text-pink-500"
                                    fixed-width />
                                <FontAwesomeIcon :icon="farHeart" class="inline group-hover:hidden text-gray-800"
                                    fixed-width />
                            </div>
                        </div>
                    </template>
                </component>

                <!-- PRODUCT INFO -->
                <div class="px-3 mb-4">
                    <component :is="product.url ? LinkIris : 'div'" :href="product.url" :id="product?.url?.id"
                        :type="product.url ? typeOfLink : undefined"
                        class="hover:text-gray-500 font-semibold l text-sm mb-1 block">
                        <span v-if="product.units != 1">
                            {{ product.units }}x
                        </span>
                        {{ product.name }}
                    </component>

                    <!-- CODE + RRP -->
                    <div
                        class="text-xs font-normal text-gray-600 mb-1
                                flex flex-col gap-0.5
                                md:grid md:grid-cols-[auto_1fr] md:gap-1"
                        >
                        <span class="text-gray-800 break-words">
                            {{ product?.code }}
                        </span>

                        <span  class="text-left md:text-right text-xs break-words">
                            {{ trans("RRP") }}:
                            {{ locale.currencyFormat(currency?.code, product.rrp_per_unit) }}
                            / {{ product.unit }}
                        </span>
                    </div>
                </div>

            </div>

            <!-- PRICE + BUTTON (FIXED AT BOTTOM) -->
            <div  v-if="layout?.iris?.is_logged_in" class="relative px-3 text-xs text-gray-600 mb-1 grid grid-cols-1 md:grid-cols-[auto_1fr] gap-1">
                <div class="">
                    <div class="font-extrabold text-black text-sm">
                        {{ trans("Price") }}:
                        {{ locale.currencyFormat(currency?.code, product.price) }}
                    </div>

                    <div class="mt-1 mr-9">
                        <span class="price_per_unit">
                            ( {{ locale.currencyFormat(currency?.code, product.price_per_unit) }}<span class="text-gray-600">/{{ product.unit }}</span> )
                        </span>
                    </div>
                </div>

                <!-- BUTTON -->
                <div class="absolute right-2 bottom-2 flex items-center justify-end">
                    <template v-if="layout?.iris?.is_logged_in">
                        <!-- In stock -->
                        <NewAddToCartButton
                            v-if="product.stock > 0 && basketButton"
                            :hasInBasket="hasInBasket"
                            :product="product"
                            :addToBasketRoute="addToBasketRoute"
                            :updateBasketQuantityRoute="updateBasketQuantityRoute"
                            :buttonStyleHover="buttonStyleHover"
                            :buttonStyle="buttonStyle"
                            :icon="button?.icon"
                        />

                        <!-- Back in stock notify -->
                        <button v-else-if="layout?.app?.environment === 'local'" @click.prevent="toggleBackInStock"
                            class="rounded-full bg-gray-200 hover:bg-gray-300 h-10 w-10 flex items-center justify-center transition-all shadow-lg"
                            v-tooltip="product.is_back_in_stock
                                ? trans('You will be notified')
                                : trans('Remind me when back in stock')">
                            <LoadingIcon v-if="isLoadingRemindBackInStock" />
                            <FontAwesomeIcon v-else
                                :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope" fixed-width
                                :class="product.is_back_in_stock ? 'text-green-600' : 'text-gray-600'" />
                        </button>
                    </template>
                </div>
            </div>

        </div>

        <!-- LOGIN CTA -->
        <div v-if="!layout?.iris?.is_logged_in" class="px-3">
            <a :href="urlLoginWithRedirect()" class="w-full">
                <Button label="Login or Register for Wholesale Prices" class="rounded-none" full :injectStyle="buttonStyleLogin" />
            </a>
        </div>

        <!-- LOADING OVERLAY -->
        <div v-if="idxSlideLoading" class="absolute inset-0 grid place-items-center bg-black/50 text-white text-5xl">
            <LoadingIcon />
        </div>
    </div>
</template>

<style scoped></style>
