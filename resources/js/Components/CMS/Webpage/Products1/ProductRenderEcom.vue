<script setup lang="ts">
import Image from '@/Components/Image.vue'
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router, usePage } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faHeart } from '@far'
import { faCircle, faStar, faHeart as fasHeart, faEllipsisV, faMedal } from '@fas'
import { Image as ImageTS } from '@/types/Image'
import ButtonAddPortfolio from '@/Components/Iris/Products/ButtonAddPortfolio.vue'
import { getStyles } from "@/Composables/styles";
import Button from '@/Components/Elements/Buttons/Button.vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle } from "@fal"
import { faStarHalfAlt } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { InputNumber } from 'primevue'
import { get, set } from 'lodash-es'
import ButtonAddToBasketInFamily from '@/Components/Iris/Products/ButtonAddToBasketInFamily.vue'
import { ProductResource } from '@/types/Iris/Products'
import NewAddToCartButton from './NewAddToCartButton.vue' // Import button baru
library.add(faStarHalfAlt, faQuestionCircle)

const layout = inject('layout', retinaLayoutStructure)

const locale = useLocaleStore()


const props = defineProps<{
    product: ProductResource
}>()


const currency = layout?.iris?.currency

// Section: Add to Favourites
const isLoadingFavourite = ref(false)
const onAddFavourite = (product: ProductResource) => {

    // Section: Submit
    router.post(
        route('iris.models.favourites.store', {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            only: ['iris'],
            preserveState: true,
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                product.is_favourite = true
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
            },
        }
    )
}
const onUnselectFavourite = (product: ProductResource) => {

    // Section: Submit
    router.delete(
        route('iris.models.favourites.delete', {
            product: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ['iris'],
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                // notify({
                //     title: trans("Success"),
                //     text: trans("Added to portfolio"),
                //     type: "success"
                // })
                product.is_favourite = false
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to remove the product from favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
            },
        }
    )
}





// Method: generate url for Login
const urlLoginWithRedirect = () => {
    if (route()?.current() !== "retina.login.show" && route()?.current() !== "retina.register") {
        return `/app/login?ref=${encodeURIComponent(window?.location.pathname)}${window?.location.search ? encodeURIComponent(window?.location.search) : ""
            }`
    } else {
        return "/app/login"
    }
}
</script>

<template>
    <div class="pb-3 relative flex flex-col justify-between h-full" comp="product-render-ecom">

        <!-- Top Section: Stock, Images, Title, Code, Price -->
        <div class=" text-gray-800 isolate">
            <div v-if="product?.top_seller"
                class="z-10 absolute top-2 left-2 border text-xs font-bold px-2 py-0.5 rounded" :class="{
                    'text-[#FFD700] bg-[#584b015] border-[#FFD700]': product.top_seller == 1, // Gold
                    'text-[#C0C0C0] bg-[#C0C0C033] border-[#C0C0C0]': product.top_seller === 2, // Silver
                    'text-[#CD7F32] bg-[#CD7F3222] border-[#CD7F32]': product.top_seller === 3  // Bronze
                }">
                <FontAwesomeIcon :icon="faMedal" class=" mr-0 md:mr-2" fixed-width s/>

                <span class="hidden md:inline">{{ trans("BESTSELLER") }}</span>
            </div>




            <!-- Icon: status (stocks) -->
            <!-- <template v-if="layout?.iris?.is_logged_in">

                <div class="absolute top-9 right-2">
                    <div class="cursor-pointer group text-xl ">
                        <FontAwesomeIcon
                            v-if="product.stock"
                            v-tooltip="trans('Product is ready stock')"
                            :icon="faCircle"
                            class="text-green-400 animate-pulse"
                            fixed-width
                        />
                        <FontAwesomeIcon
                            v-else
                            v-tooltip="trans('Product is out of stock')"
                            :icon="faCircle"
                            class="text-red-500"
                            fixed-width
                        />
                    </div>
                </div>
            </template> -->


            <!-- Product Image -->
            <component :is="product.url ? Link : 'div'" :href="product.url"
                class="block w-full mb-1 rounded sm:h-[305px] h-[180px] relative">
                <Image :src="product?.web_images?.main?.gallery" alt="product image"
                    :style="{ objectFit: 'contain' }" />

                <!-- New Add to Cart Button - hanya tampil jika user sudah login -->
                <div v-if="layout?.iris?.is_logged_in" class="absolute right-2 bottom-2">
                    <NewAddToCartButton v-if="product.stock > 0" :product="product" :key="product" />
                </div>
            </component>

            <div class="px-3">
                <!-- Title -->
                <Link v-if="product.url" :href="product.url" class="hover:text-gray-500 font-bold text-sm mb-1">
                {{ product.name }}
                </Link>
                <div v-else class="hover:text-gray-500 font-bold text-sm mb-1">
                    {{ product.name }}
                </div>

                <!-- SKU and RRP -->
                <div class="flex gap-x-2">
                    <div class="w-full">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>{{ product?.code }}</span>
                            <!-- <span v-if="product.rrp">
                                RRP: {{ locale.currencyFormat(currency.code,product.rrp) }}/ {{ product.unit }}
                            </span> -->
                        </div>

                        <!-- Rating and Stock -->
                        <div class="flex justify-between items-center text-xs mb-2">
                            <div v-if="layout?.iris?.is_logged_in">
                                <div v-if="product.stock > 0" class="flex items-center gap-1"
                                    :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]"
                                        :class="product.stock > 0 ? 'animate-pulse' : ''" />
                                    <span>({{ product.stock > 0 ? product.stock : 0 }} {{ trans('available') }})</span>
                                </div>
                                <div v-else class="bg-red-500/20 px-2 xpy-1 rounded text-xs text-red-500 border border-red-500/50">
                                    {{ trans("Out of stock") }}
                                </div>
                            </div>
                            <div class="flex items-center space-x-[1px] text-gray-500">
                            </div>
                        </div>
                    </div>

                    <!-- Favorite Icon -->
                    <div v-if="layout?.iris?.is_logged_in" class="flex items-center">

                        <div v-if="isLoadingFavourite" class="xabsolute top-2 right-2 text-gray-500 text-xl px-2 -mr-2">
                            <LoadingIcon />
                        </div>
                        <div v-else
                            @click="() => product.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)"
                            class="cursor-pointer xabsolute top-2 right-2 group text-xl px-2 -mr-2">

                            <FontAwesomeIcon v-if="product.is_favourite" :icon="fasHeart" fixed-width
                                class="text-pink-500" />
                            <div v-else class="relative" v-tooltip="trans('Add To Favourite')">
                                <!-- <FontAwesomeIcon :icon="fasHeart" class="hidden group-hover:inline text-pink-400"
                                    fixed-width /> -->
                                <FontAwesomeIcon :icon="faHeart" class="inline text-pink-300"
                                    fixed-width />
                            </div>

                        </div>
                    </div>
                </div>


                <div v-if="layout?.iris?.is_logged_in"
                    class="text-sm flex flex-wrap items-center justify-between gap-x-2 mb-3 tabular-nums">
                    <div class="">
                        <div>{{ trans('Price') }}: <span class="font-semibold">{{ locale.currencyFormat(currency?.code,
                                product.price || 0) }}</span></div>
                        <div>
                            <span class="text-sm text-gray-400 xtext-base font-normal">
                                ({{ locale.currencyFormat(currency?.code, (product.price / product.units).toFixed(2))
                                }}/{{
                                product.unit }})
                            </span>
                        </div>
                    </div>

                    <div v-if="product.rrp" class="text-xs xmt-1 text-right">
                        <div>RRP: {{ locale.currencyFormat(currency?.code, product.rrp || 0) }}</div>
                        <div class="text-gray-400 xtext-base font-normal">
                            ({{ locale.currencyFormat(currency?.code, (product.rrp / product.units).toFixed(2)) }}/{{
                            product.unit }})
                        </div>
                    </div>
                </div>

                <!-- Section: Coupon -->
                <!-- <div class="mb-2">
                    <div v-if="!xxxxxxx" class="cursor-pointer rounded py-1 px-3 bg-gray-100 border border-gray-300 w-fit text-xs" >
                        <FontAwesomeIcon icon="fas fa-star-half-alt" class="" fixed-width aria-hidden="true" />
                        <span class="">↓5%</span>
                        <InformationIcon :information="trans('Information')" />
                    </div>
                    <div v-else class="cursor-pointer rounded py-1 px-3 bg-green-100 border border-green-300 text-green-700 w-fit text-xs" >
                        <FontAwesomeIcon icon="fas fa-star-half-alt" class="" fixed-width aria-hidden="true" />
                        <span class="">↓5%</span>
                        <InformationIcon :information="trans('Information')" />
                    </div>
                </div> -->
            </div>
        </div>


        <!-- Old Button - Commented Out -->
        <!-- <div class="px-3">
            <div v-if="layout?.iris?.is_logged_in" class="w-full">

                <ButtonAddToBasketInFamily
                    v-if="product.stock > 0"
                    :product
                />

                <div v-else>
                    <Button :label="trans('Out of stock')" type="tertiary" disabled full />
                </div>
            </div>

            <Link v-else :href="urlLoginWithRedirect()" class="block text-center border border-gray-200 text-sm px-3 py-2 rounded text-gray-600 w-full">
                {{ trans("Login or Register for Wholesale Prices") }}
            </Link>
        </div> -->

        <!-- Login Button for Non-Logged In Users -->
        <div v-if="!layout?.iris?.is_logged_in" class="px-3">
            <Link :href="urlLoginWithRedirect()"
                class="block text-center border border-gray-200 text-sm px-3 py-2 rounded text-gray-600 w-full">
            {{ trans("Login or Register for Wholesale Prices") }}
            </Link>
        </div>
    </div>
</template>


<style scoped></style>